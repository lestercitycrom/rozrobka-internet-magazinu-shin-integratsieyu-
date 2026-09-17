#!/usr/bin/env bash
# Bounded shared-customer release for canonical and Classic staging together.
# This is not a Git hook and never migrates, seeds, imports, prunes or controls services.
set -Eeuo pipefail
umask 0027

readonly PRIMARY_ROOT='/var/www/staging-easytire.bethub.fun'
readonly CLASSIC_ROOT='/var/www/staging-classic-easytire.bethub.fun'
readonly REPO='/srv/git/shop-ndi.git'
readonly BASE='f776dbe4710f001c24ba8848739f331cc15bbe96'
readonly PRIMARY_REVISION='5472266d2fe9412867b5d7f5eb6e5d6b8e26a808'
readonly CLASSIC_REVISION='f776dbe4710f001c24ba8848739f331cc15bbe96'
readonly PRIMARY_PREVIOUS='/var/www/staging-easytire.bethub.fun/releases/20260916050502-5472266d2fe9-design-finish'
readonly CLASSIC_PREVIOUS='/var/www/staging-classic-easytire.bethub.fun/releases/20260916223242-f776dbe4710f-classic'
readonly BRANCH='refs/heads/codex/customer-shared-followups'
readonly PRIMARY_HOST='staging-easytire.bethub.fun'
readonly CLASSIC_HOST='staging-classic-easytire.bethub.fun'
readonly PRIMARY_NGINX='/etc/nginx/sites-available/staging-easytire.bethub.fun'
readonly CLASSIC_NGINX='/etc/nginx/sites-available/staging-classic-easytire.bethub.fun'
readonly COMPOSER='/usr/local/bin/composer'
readonly MEDIA_ROOT="$PRIMARY_ROOT/shared/storage/app/public"

revision="${1:-}"
mode="${2:-}"
log() { printf '[customer-shared] %s\n' "$*"; }
fail() { log "$*" >&2; exit 1; }
sha() { sha256sum "$1" | cut -d' ' -f1; }

env_value() {
    local file="$1" wanted="$2"
    awk -v wanted="$wanted" '
        { sub(/\r$/, "") }
        /^[[:space:]]*(export[[:space:]]+)?[A-Za-z_][A-Za-z0-9_]*[[:space:]]*=/ {
            line=$0; sub(/^[[:space:]]*/, "", line); sub(/^export[[:space:]]+/, "", line)
            key=line; sub(/[[:space:]]*=.*/, "", key)
            if (key == wanted) { sub(/^[^=]*=[[:space:]]*/, "", line); gsub(/^["\047]|["\047]$/, "", line); print line }
        }
    ' "$file"
}

validate_env() {
    local file="$1" profile="$2" actual_profile
    [[ -f "$file" && ! -L "$file" ]] || fail 'A retained runtime environment is missing or unsafe.'
    awk '
        { sub(/\r$/, "") }
        /^[[:space:]]*(export[[:space:]]+)?[A-Za-z_][A-Za-z0-9_]*[[:space:]]*=/ {
            line=$0; sub(/^[[:space:]]*/, "", line); sub(/^export[[:space:]]+/, "", line)
            key=line; sub(/[[:space:]]*=.*/, "", key)
            if (++seen[key] > 1) duplicate=1
            value=line; sub(/^[^=]*=[[:space:]]*/, "", value)
            if (key == "APP_ENV") { app_env++; if (value !~ /^(staging|"staging"|\047staging\047)[[:space:]]*(#.*)?$/) bad=1 }
            if (key == "NDI_CATALOG_MODE") { catalog++; if (value !~ /^(snapshot|"snapshot"|\047snapshot\047)[[:space:]]*(#.*)?$/) bad=1 }
            if (key ~ /^(APP_CONFIG_CACHE|APP_ROUTES_CACHE|APP_EVENTS_CACHE|APP_SERVICES_CACHE|APP_PACKAGES_CACHE|VIEW_COMPILED_PATH|LARAVEL_STORAGE_PATH)$/) unsafe=1
        }
        END { exit (duplicate || bad || unsafe || app_env != 1 || catalog != 1) }
    ' "$file" || fail 'Runtime environment is ambiguous, not staging/snapshot, or redirects release paths.'
    actual_profile="$(env_value "$file" STOREFRONT_DESIGN_PROFILE)"
    if [[ "$profile" == modern ]]; then
        [[ -z "$actual_profile" || "$actual_profile" == modern ]] || fail 'Primary retained profile is neither the modern default nor explicit modern.'
    else
        [[ "$actual_profile" == classic ]] || fail 'Classic retained profile is not explicit.'
    fi
}

assert_regular_dir() {
    [[ -d "$1" && ! -L "$1" && "$(readlink -f -- "$1")" == "$1" ]] || fail "$2 must be an existing, non-symlink directory."
}

assert_layout() {
    assert_regular_dir "$PRIMARY_ROOT" 'Primary root'
    assert_regular_dir "$PRIMARY_ROOT/releases" 'Primary releases directory'
    assert_regular_dir "$CLASSIC_ROOT" 'Classic root'
    assert_regular_dir "$CLASSIC_ROOT/releases" 'Classic releases directory'
    assert_regular_dir "$PRIMARY_ROOT/shared/storage" 'Primary shared storage'
    assert_regular_dir "$MEDIA_ROOT" 'Primary public media'
    [[ -d "$REPO" && ! -L "$REPO" ]] || fail 'Bare repository is missing or unsafe.'
    [[ -f "$PRIMARY_ROOT/deploy.lock" && ! -L "$PRIMARY_ROOT/deploy.lock" ]] || fail 'Primary deployment lock is missing or unsafe.'
    [[ -f "$CLASSIC_ROOT/deploy.lock" && ! -L "$CLASSIC_ROOT/deploy.lock" ]] || fail 'Classic deployment lock is missing or unsafe.'
    [[ -f "$PRIMARY_NGINX" && ! -L "$PRIMARY_NGINX" && -f "$CLASSIC_NGINX" && ! -L "$CLASSIC_NGINX" ]] || fail 'Installed nginx files are missing or unsafe.'
}

capture_retained_state() {
    [[ -L "$PRIMARY_ROOT/current" && "$(readlink -f -- "$PRIMARY_ROOT/current")" == "$PRIMARY_PREVIOUS" ]] || fail 'Primary current release drifted from the pinned predecessor.'
    [[ -L "$CLASSIC_ROOT/current" && "$(readlink -f -- "$CLASSIC_ROOT/current")" == "$CLASSIC_PREVIOUS" ]] || fail 'Classic current release drifted from the pinned predecessor.'
    [[ "$(head -n 1 "$PRIMARY_PREVIOUS/REVISION")" == "$PRIMARY_REVISION" ]] || fail 'Primary retained revision marker drifted.'
    [[ "$(head -n 1 "$CLASSIC_PREVIOUS/REVISION")" == "$CLASSIC_REVISION" ]] || fail 'Classic retained revision marker drifted.'
    primary_env="$PRIMARY_PREVIOUS/application/.env"
    classic_env="$CLASSIC_PREVIOUS/application/.env"
    primary_shared_env="$PRIMARY_ROOT/shared/.env"
    validate_env "$primary_env" modern
    validate_env "$classic_env" classic
    [[ -f "$primary_shared_env" && ! -L "$primary_shared_env" ]] || fail 'Primary shared environment is missing or unsafe.'
    [[ "$(env_value "$primary_env" APP_URL)" == "https://$PRIMARY_HOST" ]] || fail 'Primary retained URL drifted.'
    [[ "$(env_value "$classic_env" APP_URL)" == "https://$CLASSIC_HOST" \
        && "$(env_value "$classic_env" STOREFRONT_CANONICAL_ORIGIN)" == "https://$PRIMARY_HOST" \
        && "$(env_value "$classic_env" SESSION_DRIVER)" == file \
        && "$(env_value "$classic_env" SESSION_COOKIE)" == easytire_classic_session \
        && "$(env_value "$classic_env" SESSION_DOMAIN)" == null \
        && "$(env_value "$classic_env" SESSION_SECURE_COOKIE)" == true \
        && "$(env_value "$classic_env" SESSION_HTTP_ONLY)" == true \
        && "$(env_value "$classic_env" CACHE_STORE)" == file \
        && "$(env_value "$classic_env" CACHE_PREFIX)" == easytire_classic_cache ]] \
        || fail 'Classic retained URL/profile/cookie/cache isolation drifted.'
    [[ "$(readlink -f -- "$PRIMARY_PREVIOUS/application/storage")" == "$PRIMARY_ROOT/shared/storage" ]] || fail 'Primary retained storage is not shared storage.'
    [[ "$(readlink -f -- "$PRIMARY_PREVIOUS/application/public/storage")" == "$MEDIA_ROOT" ]] || fail 'Primary retained public media is unsafe.'
    [[ -d "$CLASSIC_PREVIOUS/application/storage" && ! -L "$CLASSIC_PREVIOUS/application/storage" ]] || fail 'Classic retained storage is not release-owned.'
    [[ -L "$CLASSIC_PREVIOUS/application/public/storage" && "$(readlink -f -- "$CLASSIC_PREVIOUS/application/public/storage")" == "$MEDIA_ROOT" ]] || fail 'Classic retained public media is unsafe.'

    primary_link="$(readlink -- "$PRIMARY_ROOT/current")"
    classic_link="$(readlink -- "$CLASSIC_ROOT/current")"
    primary_env_sha="$(sha "$primary_env")"
    classic_env_sha="$(sha "$classic_env")"
    shared_env_sha="$(sha "$primary_shared_env")"
    primary_revision_sha="$(sha "$PRIMARY_PREVIOUS/REVISION")"
    classic_revision_sha="$(sha "$CLASSIC_PREVIOUS/REVISION")"
    primary_nginx_sha="$(sha "$PRIMARY_NGINX")"
    classic_nginx_sha="$(sha "$CLASSIC_NGINX")"
}

assert_retained_state() {
    [[ -L "$PRIMARY_ROOT/current" && "$(readlink -- "$PRIMARY_ROOT/current")" == "$primary_link" && "$(readlink -f -- "$PRIMARY_ROOT/current")" == "$PRIMARY_PREVIOUS" ]] || fail 'Primary current pointer changed.'
    [[ -L "$CLASSIC_ROOT/current" && "$(readlink -- "$CLASSIC_ROOT/current")" == "$classic_link" && "$(readlink -f -- "$CLASSIC_ROOT/current")" == "$CLASSIC_PREVIOUS" ]] || fail 'Classic current pointer changed.'
    [[ "$(sha "$primary_env")" == "$primary_env_sha" && "$(sha "$classic_env")" == "$classic_env_sha" && "$(sha "$primary_shared_env")" == "$shared_env_sha" ]] || fail 'A retained runtime environment changed.'
    [[ "$(sha "$PRIMARY_PREVIOUS/REVISION")" == "$primary_revision_sha" && "$(sha "$CLASSIC_PREVIOUS/REVISION")" == "$classic_revision_sha" ]] || fail 'A retained revision marker changed.'
    [[ "$(sha "$PRIMARY_NGINX")" == "$primary_nginx_sha" && "$(sha "$CLASSIC_NGINX")" == "$classic_nginx_sha" ]] || fail 'An installed nginx file changed.'
}

validate_scope() {
    git --git-dir="$REPO" diff --quiet "$BASE" "$revision" -- \
        application/database application/config application/composer.json application/composer.lock \
        application/package.json application/package-lock.json application/pnpm-lock.yaml application/yarn.lock \
        || fail 'Protected business database/configuration or dependency inputs changed.'
    while IFS= read -r -d '' file; do
        case "$file" in
            application/app/Http/Controllers/Storefront/SiteSearchController.php) ;;
            application/app/Modules/Content/ReadModel/SiteContentSearch.php) ;;
            application/routes/web.php) ;;
            application/resources/css/app.css|application/resources/css/customer-card-overlays.css|application/resources/css/customer-catalog-quantity.css|application/resources/css/customer-shared-followups.css|application/resources/css/site-search.css) ;;
            application/resources/js/app.js|application/resources/js/ui.js|application/resources/js/customer-card-overlays.js|application/resources/js/site-search.js) ;;
            application/resources/views/components/layouts/storefront.blade.php) ;;
            application/resources/views/storefront/_homepage-promotions.blade.php|application/resources/views/storefront/catalog/_card.blade.php|application/resources/views/storefront/pricing/index.blade.php|application/resources/views/storefront/search/*) ;;
            application/resources/views/welcome.blade.php) ;;
            application/lang/nb/pro.php|application/lang/nb/customer_search.php|application/lang/ru/pro.php|application/lang/ru/customer_search.php) ;;
            application/tests/*|application/playwright*.ts) ;;
            .github/workflows/quality.yml|project-control/*|evidence/customer-shared-followups/*) ;;
            deploy/customer-shared-staging.sh|deploy/tests/customer-shared-safety.sh) ;;
            *) fail "Outside approved shared-customer scope: $file" ;;
        esac
    done < <(git --git-dir="$REPO" diff --name-only -z "$BASE" "$revision")
    while IFS= read -r entry; do
        case "$entry" in '100644 blob '*|'100755 blob '*) ;; *) fail 'Candidate contains a symlink, submodule or non-regular entry.' ;; esac
    done < <(git --git-dir="$REPO" ls-tree -r "$revision")

    mapfile -t route_additions < <(git --git-dir="$REPO" diff --unified=0 "$BASE" "$revision" -- application/routes/web.php | sed -n '/^+++ /!s/^+//p')
    if git --git-dir="$REPO" diff --unified=0 "$BASE" "$revision" -- application/routes/web.php | grep -E '^-' | grep -v '^--- ' >/dev/null; then
        fail 'Existing routes were removed or edited.'
    fi
    [[ ${#route_additions[@]} -eq 3 \
        && "${route_additions[0]}" == 'use App\Http\Controllers\Storefront\SiteSearchController;' \
        && "${route_additions[1]}" == "    Route::get('/sok', SiteSearchController::class)->name('storefront.site-search.index');" \
        && "${route_additions[2]}" == "    Route::get('/sok', SiteSearchController::class)->name('site-search.index');" ]] \
        || fail 'Routes change must be exactly the SiteSearch import and two GET search routes.'
}

[[ $# -eq 2 && "$revision" =~ ^[0-9a-f]{40}$ ]] || fail 'Supply the exact published SHA and --check or --deploy.'
[[ "$mode" == --check || "$mode" == --deploy ]] || fail 'Use --check or --deploy.'
assert_layout
[[ "$(git --git-dir="$REPO" rev-parse "$BRANCH^{commit}" 2>/dev/null)" == "$revision" ]] || fail 'Published shared-customer branch does not match the requested SHA.'
git --git-dir="$REPO" merge-base --is-ancestor "$BASE" "$revision" || fail 'Candidate is not stacked on the pinned accepted source.'
validate_scope
capture_retained_state
assert_retained_state
if [[ "$mode" == --check ]]; then
    log "Checks passed; candidate=$revision; both retained releases unchanged."
    exit 0
fi

# Fixed lock order serializes this build against both existing deploy helpers.
exec 8<>"$PRIMARY_ROOT/deploy.lock"
flock -n 8 || fail 'Primary deployment/build is running.'
exec 9<>"$CLASSIC_ROOT/deploy.lock"
flock -n 9 || fail 'Classic deployment/build is running.'
assert_retained_state
[[ "$(git --git-dir="$REPO" rev-parse "$BRANCH^{commit}")" == "$revision" ]] || fail 'Published branch changed while acquiring locks.'

stamp="$(date -u +%Y%m%d%H%M%S)"
primary_release="$PRIMARY_ROOT/releases/$stamp-${revision:0:12}-customer-shared"
classic_release="$CLASSIC_ROOT/releases/$stamp-${revision:0:12}-customer-shared"
primary_new="$PRIMARY_ROOT/.current-${revision:0:12}-customer-shared"
classic_new="$CLASSIC_ROOT/.current-${revision:0:12}-customer-shared"
primary_rollback="$PRIMARY_ROOT/.customer-shared-rollback-${revision:0:12}"
classic_rollback="$CLASSIC_ROOT/.customer-shared-rollback-${revision:0:12}"
for path in "$primary_release" "$classic_release" "$primary_new" "$classic_new" "$primary_rollback" "$classic_rollback"; do
    [[ ! -e "$path" && ! -L "$path" ]] || fail 'A candidate, activation or rollback path already exists.'
done

completed=false
readonly main_pid="$BASHPID"
on_exit() {
    local code=$? primary_active='' classic_active='' rollback_failed=false
    [[ "$BASHPID" == "$main_pid" ]] || return "$code"
    trap - EXIT INT TERM
    if [[ "$completed" == true ]]; then return "$code"; fi
    set +e
    classic_active="$(readlink -f -- "$CLASSIC_ROOT/current" 2>/dev/null)"
    primary_active="$(readlink -f -- "$PRIMARY_ROOT/current" 2>/dev/null)"
    if [[ "$classic_active" == "$classic_release" ]]; then
        ln -s -- "$CLASSIC_PREVIOUS" "$classic_rollback" && mv -Tf -- "$classic_rollback" "$CLASSIC_ROOT/current" \
            && log 'Rolled Classic back atomically.' || rollback_failed=true
    fi
    if [[ "$primary_active" == "$primary_release" ]]; then
        ln -s -- "$PRIMARY_PREVIOUS" "$primary_rollback" && mv -Tf -- "$primary_rollback" "$PRIMARY_ROOT/current" \
            && log 'Rolled primary back atomically.' || rollback_failed=true
    fi
    [[ "$rollback_failed" == false ]] || log 'URGENT: one or more rollback pointer replacements failed.' >&2
    log 'Dual deployment did not complete; both candidate releases were retained for inspection.' >&2
    [[ "$code" -ne 0 ]] || code=1
    exit "$code"
}
trap on_exit EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

mkdir -- "$primary_release"
git --git-dir="$REPO" archive "$revision" | tar -x -C "$primary_release"
printf '%s\n' "$revision" > "$primary_release/REVISION"
printf '%s\n' "$PRIMARY_PREVIOUS" > "$primary_release/PREVIOUS_RELEASE"
primary_app="$primary_release/application"
[[ -f "$primary_app/artisan" && ! -L "$primary_app/artisan" && -f "$primary_app/composer.lock" && -f "$primary_app/package-lock.json" ]] || fail 'Candidate build inputs are incomplete.'
[[ -d "$primary_app/storage" && ! -L "$primary_app/storage" && -d "$primary_app/bootstrap/cache" && ! -L "$primary_app/bootstrap/cache" ]] || fail 'Candidate build paths are not isolated.'
[[ ! -e "$primary_app/public/storage" && ! -L "$primary_app/public/storage" && ! -e "$primary_app/.env" && ! -L "$primary_app/.env" ]] || fail 'Candidate contains unsafe runtime paths.'

# Dependencies and assets are built once with disposable adapters and all gates off.
env -i PATH="$PATH" bash -s -- "$primary_app" "$COMPOSER" > "$primary_release/build.log" 2>&1 <<'BUILD'
set -Eeuo pipefail
app="$1"; composer="$2"; cd "$app"
cp -- .env.example .env
export COMPOSER_HOME="$app/.composer-build" npm_config_cache="$app/.npm-build"
export APP_ENV=testing APP_DEBUG=false APP_URL=http://127.0.0.1
export APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
export STOREFRONT_DESIGN_PROFILE=modern STOREFRONT_CANONICAL_ORIGIN=http://127.0.0.1
export APP_CONFIG_CACHE="$app/bootstrap/cache/config.php" APP_ROUTES_CACHE="$app/bootstrap/cache/routes-v7.php"
export APP_SERVICES_CACHE="$app/bootstrap/cache/services.php" APP_PACKAGES_CACHE="$app/bootstrap/cache/packages.php" APP_EVENTS_CACHE="$app/bootstrap/cache/events.php"
export VIEW_COMPILED_PATH="$app/bootstrap/cache/views" LARAVEL_STORAGE_PATH="$app/storage"
export DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL=
export SESSION_DRIVER=array CACHE_STORE=array QUEUE_CONNECTION=sync MAIL_MAILER=array
export NDI_CATALOG_MODE=snapshot NDI_MEDIA_SYNC_ENABLED=false NDI_INQUIRY_ENABLED=false NDI_ORDER_ENABLED=false NDI_C1_TEST_MODE=false NDI_FULFILMENT_READ_ENABLED=false
export NDI_INBOX_PATH= NDI_SANDBOX_CATALOG_ENABLED=false NDI_SANDBOX_INQUIRY_ENABLED=false NDI_SANDBOX_ORDER_ENABLED=false
export OUTBOX_NOTION_ENABLED=false OUTBOX_TELEGRAM_ENABLED=false CUSTOMER_EMAIL_DELIVERY_ENABLED=false PAYMENTS_ENABLED=false VEHICLE_FITMENT_ENABLED=false
"$composer" install --prefer-dist --no-interaction --optimize-autoloader --no-dev
npm ci --no-audit --no-fund
npm run build
BUILD
[[ "$primary_app" == "$primary_release/application" && "$primary_release" == "$PRIMARY_ROOT/releases/"* ]] || fail 'Generated build path is unsafe.'
rm -rf -- "$primary_app/node_modules" "$primary_app/.composer-build" "$primary_app/.npm-build"
rm -f -- "$primary_app/.env"
find "$primary_app/bootstrap/cache" -mindepth 1 ! -name '.gitignore' -delete
[[ -f "$primary_app/public/build/manifest.json" && -d "$primary_app/vendor" ]] || fail 'Shared dependency/asset build is incomplete.'

mkdir -- "$classic_release"
cp -a -- "$primary_release/." "$classic_release/"
printf '%s\n' "$revision" > "$classic_release/REVISION"
printf '%s\n' "$CLASSIC_PREVIOUS" > "$classic_release/PREVIOUS_RELEASE"
classic_app="$classic_release/application"
for artifact in application/composer.lock application/package-lock.json application/public/build/manifest.json; do
    [[ "$(sha "$primary_release/$artifact")" == "$(sha "$classic_release/$artifact")" ]] || fail 'Reused dependency/asset artifact differs between releases.'
done

configure_primary() {
    cp -- "$primary_env" "$primary_app/.env"
    cmp -s -- "$primary_env" "$primary_app/.env" || fail 'Primary candidate environment is not byte-identical.'
    validate_env "$primary_app/.env" modern
    mkdir -p -- "$primary_app/bootstrap/cache/views"
    chgrp -R www-data "$primary_app/bootstrap/cache"; chmod -R g+rwX "$primary_app/bootstrap/cache"
    chgrp www-data "$primary_app/.env"; chmod 640 "$primary_app/.env"
    env -i PATH="$PATH" bash -s -- "$primary_app" > "$primary_release/cache.log" 2>&1 <<'CACHE'
set -Eeuo pipefail
app="$1"; cd "$app"
export APP_CONFIG_CACHE="$app/bootstrap/cache/config.php" APP_ROUTES_CACHE="$app/bootstrap/cache/routes-v7.php"
export APP_SERVICES_CACHE="$app/bootstrap/cache/services.php" APP_PACKAGES_CACHE="$app/bootstrap/cache/packages.php" APP_EVENTS_CACHE="$app/bootstrap/cache/events.php"
export VIEW_COMPILED_PATH="$app/bootstrap/cache/views"
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
php -r '$c=require $argv[1]; exit((($c["app"]["env"]??null)==="staging") && (($c["storefront"]["design_profile"]??null)==="modern") && (($c["view"]["compiled"]??null)===$argv[2]) ? 0 : 1);' "$app/bootstrap/cache/config.php" "$app/bootstrap/cache/views"
CACHE
    cmp -s -- "$primary_env" "$primary_app/.env" || fail 'Primary candidate environment changed during cache compilation.'
    mv -- "$primary_app/storage" "$primary_app/storage-build"
    ln -s -- "$PRIMARY_ROOT/shared/storage" "$primary_app/storage"
    ln -s -- "$MEDIA_ROOT" "$primary_app/public/storage"
}

configure_classic() {
    cp -- "$classic_env" "$classic_app/.env"
    cmp -s -- "$classic_env" "$classic_app/.env" || fail 'Classic candidate environment is not byte-identical.'
    validate_env "$classic_app/.env" classic
    mkdir -p -- "$classic_app/storage/app" "$classic_app/storage/framework/cache/data" "$classic_app/storage/framework/sessions" "$classic_app/storage/framework/views" "$classic_app/storage/logs" "$classic_app/bootstrap/cache"
    classic_old_sessions="$CLASSIC_PREVIOUS/application/storage/framework/sessions"
    [[ -d "$classic_old_sessions" && ! -L "$classic_old_sessions" && "$(readlink -f -- "$classic_old_sessions")" == "$CLASSIC_PREVIOUS/application/storage/"* ]] || fail 'Retained Classic session directory is unsafe.'
    if find "$classic_old_sessions" -mindepth 1 \( -type l -o \( ! -type f ! -type d \) \) -print -quit | grep -q .; then
        fail 'Retained Classic sessions contain a symlink or non-regular entry.'
    fi
    cp -a -- "$classic_old_sessions/." "$classic_app/storage/framework/sessions/"
    chgrp -R www-data "$classic_app/storage" "$classic_app/bootstrap/cache"; chmod -R g+rwX "$classic_app/storage" "$classic_app/bootstrap/cache"
    chgrp www-data "$classic_app/.env"; chmod 640 "$classic_app/.env"
    env -i PATH="$PATH" bash -s -- "$classic_app" > "$classic_release/cache.log" 2>&1 <<'CACHE'
set -Eeuo pipefail
app="$1"; cd "$app"
export APP_CONFIG_CACHE="$app/bootstrap/cache/config.php" APP_ROUTES_CACHE="$app/bootstrap/cache/routes-v7.php"
export APP_SERVICES_CACHE="$app/bootstrap/cache/services.php" APP_PACKAGES_CACHE="$app/bootstrap/cache/packages.php" APP_EVENTS_CACHE="$app/bootstrap/cache/events.php"
export VIEW_COMPILED_PATH="$app/storage/framework/views" LARAVEL_STORAGE_PATH="$app/storage"
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
php -r '$c=require $argv[1]; $ok=(($c["app"]["env"]??null)==="staging") && (($c["app"]["url"]??null)===$argv[2]) && (($c["storefront"]["design_profile"]??null)==="classic") && (($c["storefront"]["canonical_origin"]??null)===$argv[3]) && (($c["session"]["driver"]??null)==="file") && (($c["session"]["cookie"]??null)==="easytire_classic_session") && array_key_exists("domain", $c["session"]??[]) && $c["session"]["domain"]===null && (($c["session"]["secure"]??null)===true) && (($c["session"]["http_only"]??null)===true) && (($c["cache"]["default"]??null)==="file") && (($c["cache"]["prefix"]??null)==="easytire_classic_cache") && (($c["view"]["compiled"]??null)===$argv[4]); exit($ok?0:1);' "$app/bootstrap/cache/config.php" "https://staging-classic-easytire.bethub.fun" "https://staging-easytire.bethub.fun" "$app/storage/framework/views"
CACHE
    cmp -s -- "$classic_env" "$classic_app/.env" || fail 'Classic candidate environment changed during cache compilation.'
    for writable in "$classic_app/storage/framework/cache/data" "$classic_app/storage/framework/sessions" "$classic_app/storage/framework/views"; do
        [[ -d "$writable" && ! -L "$writable" && "$(readlink -f -- "$writable")" == "$classic_app/storage/"* ]] || fail 'Classic cache/session/view path escaped its release.'
    done
    ln -s -- "$MEDIA_ROOT" "$classic_app/public/storage"
}

configure_primary
configure_classic
assert_retained_state
[[ "$(git --git-dir="$REPO" rev-parse "$BRANCH^{commit}")" == "$revision" ]] || fail 'Published branch changed during the isolated build.'
printf '%s\n' "$revision" > "$primary_app/public/design-revision.txt"
printf '%s\n' "$revision" > "$classic_app/public/design-revision.txt"

ln -s -- "$primary_release" "$primary_new"
mv -Tf -- "$primary_new" "$PRIMARY_ROOT/current"
curl --fail --silent --show-error --max-time 30 --resolve "$PRIMARY_HOST:443:127.0.0.1" "https://$PRIMARY_HOST/up" -o /dev/null 2>> "$primary_release/activation.log"
[[ "$(curl --fail --silent --show-error --max-time 30 --resolve "$PRIMARY_HOST:443:127.0.0.1" "https://$PRIMARY_HOST/design-revision.txt" 2>> "$primary_release/activation.log")" == "$revision" ]] || fail 'Primary revision probe failed.'

ln -s -- "$classic_release" "$classic_new"
mv -Tf -- "$classic_new" "$CLASSIC_ROOT/current"
curl --fail --silent --show-error --max-time 30 --resolve "$CLASSIC_HOST:443:127.0.0.1" "https://$CLASSIC_HOST/up" -o /dev/null 2>> "$classic_release/activation.log"
[[ "$(curl --fail --silent --show-error --max-time 30 --resolve "$CLASSIC_HOST:443:127.0.0.1" "https://$CLASSIC_HOST/design-revision.txt" 2>> "$classic_release/activation.log")" == "$revision" ]] || fail 'Classic revision probe failed.'

[[ "$(sha "$primary_env")" == "$primary_env_sha" && "$(sha "$classic_env")" == "$classic_env_sha" && "$(sha "$primary_shared_env")" == "$shared_env_sha" ]] || fail 'A retained environment changed during activation.'
[[ "$(sha "$PRIMARY_PREVIOUS/REVISION")" == "$primary_revision_sha" && "$(sha "$CLASSIC_PREVIOUS/REVISION")" == "$classic_revision_sha" ]] || fail 'A retained revision marker changed during activation.'
[[ "$(sha "$PRIMARY_NGINX")" == "$primary_nginx_sha" && "$(sha "$CLASSIC_NGINX")" == "$classic_nginx_sha" ]] || fail 'An nginx file changed during activation.'
cmp -s -- "$primary_env" "$primary_app/.env" || fail 'Primary candidate environment changed during activation.'
cmp -s -- "$classic_env" "$classic_app/.env" || fail 'Classic candidate environment changed during activation.'
completed=true
trap - EXIT INT TERM
log "Activated $revision on primary and Classic from one build; both predecessors retained."
log 'No migrations, seeds, imports, existing-release pruning, DB tests, integration submissions or service changes were performed.'
