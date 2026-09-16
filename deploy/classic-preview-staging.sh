#!/usr/bin/env bash
# Bounded Classic storefront deployment beside (never over) canonical staging.
# This is not a Git hook and never migrates, seeds, imports, prunes or controls services.
set -Eeuo pipefail
umask 0027

readonly ROOT='/var/www/staging-classic-easytire.bethub.fun'
readonly PRIMARY_ROOT='/var/www/staging-easytire.bethub.fun'
readonly REPO='/srv/git/shop-ndi.git'
readonly BASE='7165ca13090e3f0f122b181cc6c37eb6fc9f874f'
readonly PRIMARY_REVISION='5472266d2fe9412867b5d7f5eb6e5d6b8e26a808'
readonly BRANCH='refs/heads/codex/classic-storefront'
readonly HOST='staging-classic-easytire.bethub.fun'
readonly PRIMARY_HOST='staging-easytire.bethub.fun'
readonly COMPOSER='/usr/local/bin/composer'
readonly MEDIA_ROOT="$PRIMARY_ROOT/shared/storage/app/public"
readonly CLASSIC_URL="https://$HOST"
readonly CANONICAL_ORIGIN="https://$PRIMARY_HOST"
readonly ENV_OVERRIDE_KEYS='APP_URL STOREFRONT_DESIGN_PROFILE STOREFRONT_CANONICAL_ORIGIN SESSION_DRIVER SESSION_COOKIE SESSION_DOMAIN SESSION_SECURE_COOKIE SESSION_HTTP_ONLY CACHE_STORE CACHE_PREFIX'

revision="${1:-}"
mode="${2:-}"
log() { printf '[classic-preview] %s\n' "$*"; }
fail() { log "$*" >&2; exit 1; }

env_key() {
    local line="$1"
    line="${line%$'\r'}"
    if [[ "$line" =~ ^[[:space:]]*(export[[:space:]]+)?([A-Za-z_][A-Za-z0-9_]*)[[:space:]]*= ]]; then
        printf '%s\n' "${BASH_REMATCH[2]}"
    fi
}

is_env_override_key() {
    case "$1" in
        APP_URL|STOREFRONT_DESIGN_PROFILE|STOREFRONT_CANONICAL_ORIGIN|SESSION_DRIVER|SESSION_COOKIE|SESSION_DOMAIN|SESSION_SECURE_COOKIE|SESSION_HTTP_ONLY|CACHE_STORE|CACHE_PREFIX) return 0 ;;
        *) return 1 ;;
    esac
}

validate_source_env() {
    local source="$1"
    [[ -f "$source" && ! -L "$source" ]] || fail 'Canonical runtime environment must be a regular file.'
    awk '
        { sub(/\r$/, "") }
        /^[[:space:]]*(export[[:space:]]+)?[A-Za-z_][A-Za-z0-9_]*[[:space:]]*=/ {
            line=$0
            sub(/^[[:space:]]*/, "", line)
            sub(/^export[[:space:]]+/, "", line)
            key=line
            sub(/[[:space:]]*=.*/, "", key)
            if (++seen[key] > 1) duplicate=1
            value=line
            sub(/^[^=]*=[[:space:]]*/, "", value)
            if (key == "APP_ENV") {
                app_env++
                if (value !~ /^(staging|"staging"|\047staging\047)[[:space:]]*(#.*)?$/) bad=1
            }
            if (key == "NDI_CATALOG_MODE") {
                catalog_mode++
                if (value !~ /^(snapshot|"snapshot"|\047snapshot\047)[[:space:]]*(#.*)?$/) bad=1
            }
            if (key ~ /^(APP_CONFIG_CACHE|APP_ROUTES_CACHE|APP_EVENTS_CACHE|APP_SERVICES_CACHE|APP_PACKAGES_CACHE|VIEW_COMPILED_PATH|LARAVEL_STORAGE_PATH)$/) unsafe_path=1
        }
        END { exit (duplicate || bad || unsafe_path || app_env != 1 || catalog_mode != 1) }
    ' "$source" || fail 'Canonical environment is ambiguous, not staging/snapshot, or redirects runtime paths.'
}

desired_env_value() {
    case "$1" in
        APP_URL) printf '%s\n' "$CLASSIC_URL" ;;
        STOREFRONT_DESIGN_PROFILE) printf '%s\n' 'classic' ;;
        STOREFRONT_CANONICAL_ORIGIN) printf '%s\n' "$CANONICAL_ORIGIN" ;;
        SESSION_DRIVER) printf '%s\n' 'file' ;;
        SESSION_COOKIE) printf '%s\n' 'easytire_classic_session' ;;
        SESSION_DOMAIN) printf '%s\n' 'null' ;;
        SESSION_SECURE_COOKIE) printf '%s\n' 'true' ;;
        SESSION_HTTP_ONLY) printf '%s\n' 'true' ;;
        CACHE_STORE) printf '%s\n' 'file' ;;
        CACHE_PREFIX) printf '%s\n' 'easytire_classic_cache' ;;
        *) return 1 ;;
    esac
}

# Read dotenv as text, never shell code. Non-Classic lines are copied verbatim;
# only the explicit profile/session/cache allowlist can differ from canonical.
write_classic_env() {
    local source="$1" destination="$2" line key value
    local -A seen=()
    : > "$destination"
    while IFS= read -r line || [[ -n "$line" ]]; do
        key="$(env_key "$line")"
        if [[ -n "$key" ]] && is_env_override_key "$key"; then
            value="$(desired_env_value "$key")"
            printf '%s=%s\n' "$key" "$value" >> "$destination"
            seen["$key"]=1
        else
            printf '%s\n' "$line" >> "$destination"
        fi
    done < "$source"
    for key in $ENV_OVERRIDE_KEYS; do
        if [[ ! ${seen["$key"]+present} ]]; then
            value="$(desired_env_value "$key")"
            printf '%s=%s\n' "$key" "$value" >> "$destination"
        fi
    done
}

assert_classic_env() {
    local source="$1" candidate="$2" key expected actual
    [[ -f "$candidate" && ! -L "$candidate" ]] || fail 'Classic runtime environment must be release-owned.'
    validate_source_env "$candidate"
    for key in $ENV_OVERRIDE_KEYS; do
        expected="$(desired_env_value "$key")"
        actual="$(awk -v wanted="$key" '
            { sub(/\r$/, "") }
            /^[[:space:]]*(export[[:space:]]+)?[A-Za-z_][A-Za-z0-9_]*[[:space:]]*=/ {
                line=$0; sub(/^[[:space:]]*/, "", line); sub(/^export[[:space:]]+/, "", line)
                name=line; sub(/[[:space:]]*=.*/, "", name)
                if (name == wanted) { sub(/^[^=]*=[[:space:]]*/, "", line); print line }
            }
        ' "$candidate")"
        [[ "$actual" == "$expected" ]] || fail "Classic environment has an unexpected $key value."
    done
    cmp -s \
        <(awk 'BEGIN { split("APP_URL STOREFRONT_DESIGN_PROFILE STOREFRONT_CANONICAL_ORIGIN SESSION_DRIVER SESSION_COOKIE SESSION_DOMAIN SESSION_SECURE_COOKIE SESSION_HTTP_ONLY CACHE_STORE CACHE_PREFIX", a); for (i in a) allowed[a[i]]=1 }
            { raw=$0; probe=$0; sub(/\r$/, "", probe); sub(/^[[:space:]]*/, "", probe); sub(/^export[[:space:]]+/, "", probe); key=probe; sub(/[[:space:]]*=.*/, "", key); if (!(key in allowed)) print raw }' "$source") \
        <(awk 'BEGIN { split("APP_URL STOREFRONT_DESIGN_PROFILE STOREFRONT_CANONICAL_ORIGIN SESSION_DRIVER SESSION_COOKIE SESSION_DOMAIN SESSION_SECURE_COOKIE SESSION_HTTP_ONLY CACHE_STORE CACHE_PREFIX", a); for (i in a) allowed[a[i]]=1 }
            { raw=$0; probe=$0; sub(/\r$/, "", probe); sub(/^[[:space:]]*/, "", probe); sub(/^export[[:space:]]+/, "", probe); key=probe; sub(/[[:space:]]*=.*/, "", key); if (!(key in allowed)) print raw }' "$candidate") \
        || fail 'Classic environment differs outside the explicit profile/session/cache allowlist.'
}

assert_regular_directory() {
    local path="$1" label="$2"
    [[ -d "$path" && ! -L "$path" && "$(readlink -f -- "$path")" == "$path" ]] || fail "$label must be an existing, non-symlink directory."
}

assert_static_layout() {
    assert_regular_directory "$ROOT" 'Classic root'
    assert_regular_directory "$ROOT/releases" 'Classic releases directory'
    assert_regular_directory "$PRIMARY_ROOT" 'Canonical root'
    assert_regular_directory "$PRIMARY_ROOT/releases" 'Canonical releases directory'
    assert_regular_directory "$MEDIA_ROOT" 'Canonical public media directory'
    [[ -d "$REPO" && ! -L "$REPO" ]] || fail 'Bare repository must be an existing, non-symlink directory.'
    [[ -f "$ROOT/deploy.lock" && ! -L "$ROOT/deploy.lock" ]] || fail 'Classic deployment lock must be preprovisioned as a regular file.'
    [[ -f "$PRIMARY_ROOT/deploy.lock" && ! -L "$PRIMARY_ROOT/deploy.lock" ]] || fail 'Canonical deployment lock must be a regular file.'
}

capture_primary() {
    [[ -L "$PRIMARY_ROOT/current" ]] || fail 'Canonical current pointer must be a symlink.'
    primary_current="$(readlink -f -- "$PRIMARY_ROOT/current")"
    [[ "$primary_current" == "$PRIMARY_ROOT/releases/"* && -d "$primary_current" && ! -L "$primary_current" ]] || fail 'Canonical current target is unsafe.'
    [[ -f "$primary_current/REVISION" && ! -L "$primary_current/REVISION" ]] || fail 'Canonical revision marker is missing.'
    [[ "$(head -n 1 "$primary_current/REVISION")" == "$PRIMARY_REVISION" ]] || fail 'Canonical staging revision changed; inspect before deploying Classic.'
    primary_env="$primary_current/application/.env"
    validate_source_env "$primary_env"
    primary_shared_env="$PRIMARY_ROOT/shared/.env"
    [[ -f "$primary_shared_env" && ! -L "$primary_shared_env" ]] || fail 'Canonical shared environment must be a regular file.'
    primary_link_value="$(readlink -- "$PRIMARY_ROOT/current")"
    primary_env_sha="$(sha256sum "$primary_env" | cut -d' ' -f1)"
    primary_shared_env_sha="$(sha256sum "$primary_shared_env" | cut -d' ' -f1)"
    primary_revision_sha="$(sha256sum "$primary_current/REVISION" | cut -d' ' -f1)"
}

assert_primary_unchanged() {
    [[ -L "$PRIMARY_ROOT/current" && "$(readlink -- "$PRIMARY_ROOT/current")" == "$primary_link_value" ]] || fail 'Canonical current pointer changed during Classic deployment.'
    [[ "$(readlink -f -- "$PRIMARY_ROOT/current")" == "$primary_current" ]] || fail 'Canonical current target changed during Classic deployment.'
    [[ "$(sha256sum "$primary_env" | cut -d' ' -f1)" == "$primary_env_sha" ]] || fail 'Canonical runtime environment changed during Classic deployment.'
    [[ "$(sha256sum "$primary_shared_env" | cut -d' ' -f1)" == "$primary_shared_env_sha" ]] || fail 'Canonical shared environment changed during Classic deployment.'
    [[ "$(sha256sum "$primary_current/REVISION" | cut -d' ' -f1)" == "$primary_revision_sha" ]] || fail 'Canonical revision marker changed during Classic deployment.'
}

capture_classic_previous() {
    previous=''
    if [[ ! -e "$ROOT/current" && ! -L "$ROOT/current" ]]; then
        return
    fi
    [[ -L "$ROOT/current" ]] || fail 'Classic current pointer must be absent or a symlink.'
    previous="$(readlink -f -- "$ROOT/current")"
    [[ "$previous" == "$ROOT/releases/"* && -d "$previous" && ! -L "$previous" ]] || fail 'Classic current target is unsafe.'
    [[ -f "$previous/REVISION" && ! -L "$previous/REVISION" && -f "$previous/application/.env" && ! -L "$previous/application/.env" ]] || fail 'Classic current release is incomplete.'
    [[ -d "$previous/application/storage" && ! -L "$previous/application/storage" ]] || fail 'Classic current storage must be release-owned.'
    [[ -L "$previous/application/public/storage" && "$(readlink -f -- "$previous/application/public/storage")" == "$MEDIA_ROOT" ]] || fail 'Classic current public media link is unsafe.'
}

[[ $# -eq 2 && "$revision" =~ ^[0-9a-f]{40}$ ]] || fail 'Supply the exact published SHA and --check or --deploy.'
[[ "$mode" == '--check' || "$mode" == '--deploy' ]] || fail 'Use --check or --deploy.'
assert_static_layout
[[ "$(git --git-dir="$REPO" rev-parse "$BRANCH^{commit}" 2>/dev/null)" == "$revision" ]] || fail 'Published Classic branch does not match the requested SHA.'
git --git-dir="$REPO" merge-base --is-ancestor "$BASE" "$revision" || fail 'Candidate is not stacked on the pinned Classic source base.'

# Business/runtime paths must be byte-identical to canonical staging. Vite config
# is presentation build wiring; application resources/js itself remains pinned.
git --git-dir="$REPO" diff --quiet "$PRIMARY_REVISION" "$revision" -- \
    application/app application/database application/routes application/resources/js \
    ':(glob)**/composer.lock' ':(glob)**/package-lock.json' ':(glob)**/pnpm-lock.yaml' ':(glob)**/yarn.lock' \
    || fail 'Protected app/database/routes/JS/lockfile paths differ from canonical staging.'

# Candidate changes relative to the approved source base are exact, reviewable
# Classic presentation/config/test/deploy files—never general backend scope.
while IFS= read -r -d '' file; do
    case "$file" in
        application/.env.example|application/config/storefront.php|application/vite.config.js) ;;
        application/resources/css/storefront-classic.css) ;;
        application/resources/views/components/layouts/storefront.blade.php) ;;
        application/resources/views/storefront/_homepage-promotions.blade.php) ;;
        application/resources/views/storefront/_homepage-service-collection.blade.php) ;;
        application/resources/views/storefront/_quick-finder.blade.php) ;;
        application/resources/views/welcome.blade.php) ;;
        application/tests/Feature/Storefront/StorefrontDesignProfileTest.php) ;;
        application/tests/E2E/classic-preview.spec.ts|application/tests/E2E/prepare-classic-preview.php|application/tests/E2E/classic-preview-router.php) ;;
        application/playwright.classic.config.ts|.github/workflows/quality.yml) ;;
        project-control/CURRENT_TASK.md|evidence/classic-preview/*.md) ;;
        deploy/nginx/staging-classic-easytire.conf|deploy/nginx/staging-classic-easytire.http.conf) ;;
        deploy/classic-preview-staging.sh|deploy/tests/classic-preview-safety.sh) ;;
        *) fail "Outside approved Classic scope: $file" ;;
    esac
done < <(git --git-dir="$REPO" diff --name-only -z "$BASE" "$revision")
while IFS= read -r entry; do
    case "$entry" in
        '100644 blob '*|'100755 blob '*) ;;
        *) fail 'Candidate contains a symlink, submodule or non-regular entry.' ;;
    esac
done < <(git --git-dir="$REPO" ls-tree -r "$revision")

capture_primary
capture_classic_previous
assert_primary_unchanged
if [[ "$mode" == '--check' ]]; then
    log "Checks passed; candidate=$revision; canonical=$PRIMARY_REVISION; no files or data changed."
    exit 0
fi

# Serialize against both canonical and Classic deployment builds. The host is
# intentionally small; these locks also make the post-preflight recheck useful.
exec 8<>"$PRIMARY_ROOT/deploy.lock"
flock -n 8 || fail 'Canonical deployment/build is running.'
exec 9<>"$ROOT/deploy.lock"
flock -n 9 || fail 'Another Classic deployment/build is running.'
assert_primary_unchanged
capture_classic_previous
[[ "$(git --git-dir="$REPO" rev-parse "$BRANCH^{commit}")" == "$revision" ]] || fail 'Published Classic branch changed while acquiring locks.'

release="$ROOT/releases/$(date -u +%Y%m%d%H%M%S)-${revision:0:12}-classic"
[[ "$release" == "$ROOT/releases/"* && ! -e "$release" && ! -L "$release" ]] || fail 'Unsafe or existing Classic release directory.'
new_current="$ROOT/.current-${revision:0:12}-classic"
rollback_link="$ROOT/.classic-rollback-${revision:0:12}"
[[ ! -e "$new_current" && ! -L "$new_current" && ! -e "$rollback_link" && ! -L "$rollback_link" ]] || fail 'Activation or rollback link already exists.'

completed=false
readonly main_pid="$BASHPID"
on_exit() {
    local exit_code=$? active=''
    [[ "$BASHPID" == "$main_pid" ]] || return "$exit_code"
    trap - EXIT INT TERM
    if [[ "$completed" == true ]]; then return "$exit_code"; fi
    active="$(readlink -f -- "$ROOT/current" 2>/dev/null || true)"
    if [[ "$active" == "$release" ]]; then
        if [[ -n "$previous" ]]; then
            if ln -s -- "$previous" "$rollback_link" && mv -Tf -- "$rollback_link" "$ROOT/current"; then
                log 'Rolled back atomically to the retained Classic release.'
            else
                log 'URGENT: Classic rollback pointer replacement failed.' >&2
            fi
        elif [[ -L "$ROOT/current" ]] && unlink -- "$ROOT/current"; then
            log 'Removed the failed first-release Classic pointer; vhost returns its preactivation response.'
        else
            log 'URGENT: failed first-release Classic pointer could not be removed.' >&2
        fi
    fi
    log 'Classic deployment did not complete; candidate retained for inspection.' >&2
    [[ "$exit_code" -ne 0 ]] || exit_code=1
    exit "$exit_code"
}
trap on_exit EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

mkdir -- "$release"
git --git-dir="$REPO" archive "$revision" | tar -x -C "$release"
printf '%s\n' "$revision" > "$release/REVISION"
if [[ -n "$previous" ]]; then printf '%s\n' "$previous" > "$release/PREVIOUS_RELEASE"; fi
printf '%s\n' "$primary_current" > "$release/CANONICAL_RELEASE"
printf '%s\n' "$primary_env_sha" > "$release/CANONICAL_ENV_SHA256"
app="$release/application"
[[ -f "$app/artisan" && ! -L "$app/artisan" && -f "$app/composer.lock" && -f "$app/package-lock.json" ]] || fail 'Candidate build inputs are incomplete.'
[[ -d "$app/storage" && ! -L "$app/storage" && -d "$app/bootstrap/cache" && ! -L "$app/bootstrap/cache" ]] || fail 'Candidate storage/cache paths are not isolated.'
[[ ! -e "$app/public/storage" && ! -L "$app/public/storage" && ! -e "$app/.env" && ! -L "$app/.env" ]] || fail 'Candidate contains unsafe runtime paths.'

# Build uses only disposable SQLite/memory/file-free runtime adapters. No test,
# migration, seed, import or other live-environment Artisan command runs here.
env -i PATH="$PATH" bash -s -- "$app" "$COMPOSER" > "$release/build.log" 2>&1 <<'BUILD'
set -Eeuo pipefail
app="$1"
composer="$2"
cd "$app"
cp -- .env.example .env
export COMPOSER_HOME="$app/.composer-build" npm_config_cache="$app/.npm-build"
export APP_ENV=testing APP_DEBUG=false APP_URL=http://127.0.0.1
export APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
export STOREFRONT_DESIGN_PROFILE=classic STOREFRONT_CANONICAL_ORIGIN=https://staging-easytire.bethub.fun
export APP_CONFIG_CACHE="$app/bootstrap/cache/config.php" APP_ROUTES_CACHE="$app/bootstrap/cache/routes-v7.php"
export APP_SERVICES_CACHE="$app/bootstrap/cache/services.php" APP_PACKAGES_CACHE="$app/bootstrap/cache/packages.php" APP_EVENTS_CACHE="$app/bootstrap/cache/events.php"
export VIEW_COMPILED_PATH="$app/storage/framework/views" LARAVEL_STORAGE_PATH="$app/storage"
export DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL=
export SESSION_DRIVER=array CACHE_STORE=array QUEUE_CONNECTION=sync MAIL_MAILER=array
export NDI_CATALOG_MODE=snapshot NDI_MEDIA_SYNC_ENABLED=false NDI_INQUIRY_ENABLED=false NDI_ORDER_ENABLED=false NDI_C1_TEST_MODE=false NDI_FULFILMENT_READ_ENABLED=false
export NDI_INBOX_PATH= NDI_SANDBOX_CATALOG_ENABLED=false NDI_SANDBOX_INQUIRY_ENABLED=false NDI_SANDBOX_ORDER_ENABLED=false
export OUTBOX_NOTION_ENABLED=false OUTBOX_TELEGRAM_ENABLED=false CUSTOMER_EMAIL_DELIVERY_ENABLED=false PAYMENTS_ENABLED=false VEHICLE_FITMENT_ENABLED=false
"$composer" install --prefer-dist --no-interaction --optimize-autoloader --no-dev
npm ci --no-audit --no-fund
npm run build
BUILD
rm -f -- "$app/.env"

write_classic_env "$primary_env" "$app/.env.new"
chmod 600 "$app/.env.new"
mv -- "$app/.env.new" "$app/.env"
assert_classic_env "$primary_env" "$app/.env"
candidate_env_sha="$(sha256sum "$app/.env" | cut -d' ' -f1)"
printf '%s\n' "$candidate_env_sha" > "$release/CLASSIC_ENV_SHA256"

mkdir -p -- "$app/storage/app" "$app/storage/framework/cache/data" "$app/storage/framework/sessions" "$app/storage/framework/views" "$app/storage/logs" "$app/bootstrap/cache"
chgrp -R www-data "$app/storage" "$app/bootstrap/cache"
chmod -R g+rwX "$app/storage" "$app/bootstrap/cache"
chgrp www-data "$app/.env"
chmod 640 "$app/.env"

# Only cache compilation is allowed with the copied live DB/integration values.
# All cache/view/storage destinations are forced into this release.
env -i PATH="$PATH" bash -s -- "$app" "$CLASSIC_URL" "$CANONICAL_ORIGIN" > "$release/cache.log" 2>&1 <<'CACHE'
set -Eeuo pipefail
app="$1"
classic_url="$2"
canonical_origin="$3"
cd "$app"
export APP_CONFIG_CACHE="$app/bootstrap/cache/config.php" APP_ROUTES_CACHE="$app/bootstrap/cache/routes-v7.php"
export APP_SERVICES_CACHE="$app/bootstrap/cache/services.php" APP_PACKAGES_CACHE="$app/bootstrap/cache/packages.php" APP_EVENTS_CACHE="$app/bootstrap/cache/events.php"
export VIEW_COMPILED_PATH="$app/storage/framework/views" LARAVEL_STORAGE_PATH="$app/storage"
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
php -r '$c=require $argv[1]; $ok=(($c["app"]["env"]??null)==="staging") && (($c["app"]["url"]??null)===$argv[2]) && (($c["storefront"]["design_profile"]??null)==="classic") && (($c["storefront"]["canonical_origin"]??null)===$argv[3]) && (($c["session"]["driver"]??null)==="file") && (($c["session"]["cookie"]??null)==="easytire_classic_session") && array_key_exists("domain", $c["session"]??[]) && $c["session"]["domain"]===null && (($c["session"]["secure"]??null)===true) && (($c["session"]["http_only"]??null)===true) && (($c["cache"]["default"]??null)==="file") && (($c["view"]["compiled"]??null)===$argv[4]); exit($ok?0:1);' "$app/bootstrap/cache/config.php" "$classic_url" "$canonical_origin" "$app/storage/framework/views"
CACHE
assert_classic_env "$primary_env" "$app/.env"
[[ "$(sha256sum "$app/.env" | cut -d' ' -f1)" == "$candidate_env_sha" ]] || fail 'Classic runtime environment changed during cache compilation.'
assert_primary_unchanged

# The release owns all writable runtime state. Only public media is a read-only
# logical share with canonical staging; no whole-storage symlink is created.
ln -s -- "$MEDIA_ROOT" "$app/public/storage"
printf '%s\n' "$revision" > "$app/public/design-revision.txt"
ln -s -- "$release" "$new_current"
mv -Tf -- "$new_current" "$ROOT/current"

# /up bypasses stateful middleware; the other probe is a static release marker.
curl --fail --silent --show-error --max-time 30 --resolve "$HOST:443:127.0.0.1" "https://$HOST/up" -o /dev/null 2>> "$release/activation.log"
[[ "$(curl --fail --silent --show-error --max-time 30 --resolve "$HOST:443:127.0.0.1" "https://$HOST/design-revision.txt" 2>> "$release/activation.log")" == "$revision" ]] || fail 'Classic activation revision probe failed.'
assert_primary_unchanged
assert_classic_env "$primary_env" "$app/.env"
completed=true
trap - EXIT INT TERM
log "Activated Classic candidate $revision; canonical staging $PRIMARY_REVISION was not changed."
log 'No migrations, seeds, imports, pruning, DB tests, integration submissions, workers, schedules or service restarts were run.'
