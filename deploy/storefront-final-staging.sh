#!/usr/bin/env bash
# PR #87: bounded Modern storefront polish and mobile catalogue-filter A11Y fix
# above the accepted shared-followups release.
# This is NOT the main-branch hook: no migrations, seeds, imports, pruning or service control.
set -Eeuo pipefail
umask 0027

readonly ROOT='/var/www/staging-easytire.bethub.fun'
readonly CLASSIC_ROOT='/var/www/staging-classic-easytire.bethub.fun'
readonly REPO='/srv/git/shop-ndi.git'
readonly BASE='833c98356c0ef9a1a53246682025790268f53227'
readonly EXPECTED_CURRENT='3705a24a2d0b8ac7e3899bf63b44fdae3ec4fd92'
readonly EXPECTED_CLASSIC='833c98356c0ef9a1a53246682025790268f53227'
readonly BRANCH='refs/heads/codex/storefront-visual-debt'
readonly HOST='staging-easytire.bethub.fun'
readonly COMPOSER='/usr/local/bin/composer'
readonly IMPORT_PROCESS_PATTERN='(^|[[:space:]/])[p]hp([0-9.]+)?[[:space:]]+([^[:space:]]+[[:space:]]+)*([^[:space:]]*/)?artisan[[:space:]]+ndi:(inbox:sync|import)([[:space:]]|$)'
revision="${1:-}"
mode="${2:-}"
log() { printf '[storefront-final] %s\n' "$*"; }
fail() { log "$*" >&2; exit 1; }

# Read dotenv only as text, never as shell code. Require the retained staging
# snapshot runtime and reject cache/storage paths that escape the release.
validate_env() {
    awk '
        { sub(/\r$/, "") }
        /^[[:space:]]*(export[[:space:]]+)?APP_ENV[[:space:]]*=/ {
            environments++
            value=$0; sub(/^[^=]*=[[:space:]]*/, "", value)
            if (value !~ /^(staging|"staging"|\047staging\047)[[:space:]]*(#.*)?$/) bad=1
        }
        /^[[:space:]]*(export[[:space:]]+)?NDI_CATALOG_MODE[[:space:]]*=/ {
            modes++
            value=$0; sub(/^[^=]*=[[:space:]]*/, "", value)
            if (value !~ /^(snapshot|"snapshot"|\047snapshot\047)[[:space:]]*(#.*)?$/) bad=1
        }
        /^[[:space:]]*(export[[:space:]]+)?(APP_CONFIG_CACHE|APP_ROUTES_CACHE|APP_EVENTS_CACHE|APP_SERVICES_CACHE|APP_PACKAGES_CACHE|VIEW_COMPILED_PATH|LARAVEL_STORAGE_PATH)[[:space:]]*=/ { bad=1 }
        END { exit (bad || environments != 1 || modes != 1) }
    ' "$1" || fail 'Runtime environment must be unambiguous staging/snapshot with no cache/storage overrides.'
}

[[ $# -eq 2 && "$revision" =~ ^[0-9a-f]{40}$ ]] || fail 'Supply the exact published SHA and --check or --deploy.'
[[ "$mode" == '--check' || "$mode" == '--deploy' ]] || fail 'Use --check or --deploy.'
[[ "$(git --git-dir="$REPO" rev-parse "$BRANCH^{commit}" 2>/dev/null)" == "$revision" ]] || fail 'Published storefront branch does not match the requested SHA.'
git --git-dir="$REPO" merge-base --is-ancestor "$BASE" "$revision" || fail 'Candidate is not stacked on the pinned accepted release.'

# Exact presentation/A11Y-only allowlist for PR #87. The JS and catalogue view
# entries are limited to the mobile filter dialog fix; backend, dependencies,
# admin, migrations, data and server configuration remain excluded.
while IFS= read -r -d '' file; do
    case "$file" in
        application/lang/nb/project.php|application/lang/ru/project.php) ;;
        application/resources/css/app.css|application/resources/css/customer-design-balance.css|application/resources/css/homepage-final-polish.css) ;;
        application/resources/js/customer-card-overlays.js|application/resources/js/ui.js) ;;
        application/resources/views/welcome.blade.php|application/resources/views/storefront/catalog/index.blade.php) ;;
        application/tests/Audit/issue-26-interaction-matrix.spec.ts) ;;
        application/tests/E2E/catalog-mobile-layout.spec.ts|application/tests/E2E/customer-design-balance.spec.ts|application/tests/E2E/design-foundation.spec.ts|application/tests/E2E/home-hero-process.spec.ts|application/tests/E2E/homepage-composition.spec.ts|application/tests/E2E/homepage-content-collections.spec.ts) ;;
        evidence/issue-47/README.md|evidence/issue-47/business-partners-1440.png|evidence/issue-47/business-partners-320.png|evidence/issue-47/business-partners-390.png) ;;
        evidence/issue-47/homepage-1280x800.jpg|evidence/issue-47/homepage-1366x768.jpg|evidence/issue-47/homepage-1440x900.jpg|evidence/issue-47/homepage-1920x1080.jpg|evidence/issue-47/homepage-320x800.jpg|evidence/issue-47/homepage-390x844.jpg|evidence/issue-47/homepage-800x900.jpg|evidence/issue-47/homepage-layout.json) ;;
        project-control/DECISIONS.md|project-control/WORKFLOW.md) ;;
        deploy/storefront-final-staging.sh|deploy/tests/storefront-final-safety.sh) ;;
        *) fail "Outside approved storefront scope: $file" ;;
    esac
done < <(git --git-dir="$REPO" diff --name-only -z "$BASE" "$revision")
while IFS= read -r entry; do
    case "$entry" in
        '100644 blob '*|'100755 blob '*) ;;
        *) fail 'Candidate contains a symlink, submodule or non-regular entry.' ;;
    esac
done < <(git --git-dir="$REPO" ls-tree -r "$revision")

previous="$(readlink -f "$ROOT/current")"
classic_previous="$(readlink -f "$CLASSIC_ROOT/current")"
[[ "$previous" == "$ROOT/releases/"* && -d "$previous" && -f "$previous/REVISION" ]] || fail 'Current Modern release cannot be safely retained.'
[[ "$classic_previous" == "$CLASSIC_ROOT/releases/"* && -d "$classic_previous" && -f "$classic_previous/REVISION" ]] || fail 'Current Classic release cannot be safely observed.'
[[ "$(head -n 1 "$previous/REVISION")" == "$EXPECTED_CURRENT" ]] || fail 'Active Modern staging revision changed; inspect before replacing it.'
[[ "$(head -n 1 "$classic_previous/REVISION")" == "$EXPECTED_CLASSIC" ]] || fail 'Active Classic revision changed; this deployment refuses to proceed.'
[[ -f "$previous/application/.env" && ! -L "$previous/application/.env" && -f "$ROOT/shared/.env" ]] || fail 'Release-owned and shared runtime environments must exist.'
[[ -f "$classic_previous/application/.env" && ! -L "$classic_previous/application/.env" ]] || fail 'Classic release-owned runtime environment must exist.'
[[ -d "$ROOT/shared/storage" && "$(readlink -f "$ROOT/shared/storage")" == "$ROOT/shared/storage" ]] || fail 'Unexpected shared storage layout.'
[[ "$(readlink -f "$previous/application/storage")" == "$ROOT/shared/storage" ]] || fail 'Unexpected active storage layout.'
[[ -d "$ROOT/shared/storage/app/public" && "$(readlink -f "$previous/application/public/storage")" == "$ROOT/shared/storage/app/public" ]] || fail 'Unexpected public media storage layout.'
validate_env "$previous/application/.env"
previous_env_sha="$(sha256sum "$previous/application/.env" | cut -d' ' -f1)"
shared_env_sha="$(sha256sum "$ROOT/shared/.env" | cut -d' ' -f1)"
classic_revision_sha="$(sha256sum "$classic_previous/REVISION" | cut -d' ' -f1)"
classic_env_sha="$(sha256sum "$classic_previous/application/.env" | cut -d' ' -f1)"
[[ -f "$ROOT/deploy.lock" && ! -L "$ROOT/deploy.lock" ]] || fail 'Existing regular deployment lock is required.'

assert_classic_unchanged() {
    [[ "$(readlink -f "$CLASSIC_ROOT/current")" == "$classic_previous" ]] || fail 'Classic current pointer changed; deployment refused.'
    [[ "$(head -n 1 "$classic_previous/REVISION")" == "$EXPECTED_CLASSIC" && "$(sha256sum "$classic_previous/REVISION" | cut -d' ' -f1)" == "$classic_revision_sha" ]] || fail 'Classic revision marker changed; deployment refused.'
    [[ "$(sha256sum "$classic_previous/application/.env" | cut -d' ' -f1)" == "$classic_env_sha" ]] || fail 'Classic runtime environment changed; deployment refused.'
}

if [[ "$mode" == '--check' ]]; then
    assert_classic_unchanged
    log "Checks passed; candidate=$revision; retained Modern=$EXPECTED_CURRENT; retained Classic=$EXPECTED_CLASSIC; no files or data changed."
    exit 0
fi

exec 9<>"$ROOT/deploy.lock"
flock -n 9 || fail 'Another Modern deployment is running.'
assert_previous() {
    [[ "$(readlink -f "$ROOT/current")" == "$previous" && "$(head -n 1 "$previous/REVISION")" == "$EXPECTED_CURRENT" ]] || fail 'Current Modern release changed; inspect again.'
    [[ "$(sha256sum "$previous/application/.env" | cut -d' ' -f1)" == "$previous_env_sha" && "$(sha256sum "$ROOT/shared/.env" | cut -d' ' -f1)" == "$shared_env_sha" ]] || fail 'Retained Modern runtime environment changed; inspect again.'
    [[ "$(git --git-dir="$REPO" rev-parse "$BRANCH^{commit}")" == "$revision" ]] || fail 'Published storefront branch changed; inspect again.'
    assert_classic_unchanged
}
assert_previous
release="$ROOT/releases/$(date -u +%Y%m%d%H%M%S)-${revision:0:12}-storefront-final"
[[ "$release" == "$ROOT/releases/"* && ! -e "$release" && ! -L "$release" ]] || fail 'Unsafe or existing candidate directory.'
rollback_link="$ROOT/.storefront-final-rollback-${revision:0:12}"
new_current="$ROOT/.current-${revision:0:12}-storefront-final"
[[ ! -e "$rollback_link" && ! -L "$rollback_link" && ! -e "$new_current" && ! -L "$new_current" ]] || fail 'Activation or rollback link already exists.'
completed=false
readonly main_pid="$BASHPID"
on_exit() {
    local exit_code=$?
    [[ "$BASHPID" == "$main_pid" ]] || return "$exit_code"
    trap - EXIT INT TERM
    if [[ "$completed" == true ]]; then return "$exit_code"; fi
    if [[ "$(readlink -f "$ROOT/current")" == "$release" ]]; then
        if ln -s -- "$previous" "$rollback_link" && mv -Tf -- "$rollback_link" "$ROOT/current"; then
            log "Rolled Modern back atomically to $EXPECTED_CURRENT; no database restore."
        else
            log 'URGENT: Modern rollback pointer replacement failed; operator intervention required.' >&2
        fi
    fi
    log 'Deployment did not complete; candidate logs retained for inspection.' >&2
    [[ "$exit_code" -ne 0 ]] || exit_code=1
    exit "$exit_code"
}
trap on_exit EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
mkdir -- "$release"
git --git-dir="$REPO" archive "$revision" | tar -x -C "$release"
printf '%s\n' "$revision" > "$release/REVISION"
printf '%s\n' "$previous" > "$release/PREVIOUS_RELEASE"
app="$release/application"
[[ -f "$app/artisan" && -f "$app/composer.lock" && -d "$app/storage" && ! -L "$app/storage" && -d "$app/bootstrap/cache" && ! -L "$app/bootstrap/cache" && ! -e "$app/public/storage" && ! -L "$app/public/storage" && ! -e "$app/.env" ]] || fail 'Candidate build paths are not isolated.'

# No persistent environment or filesystem is attached to Composer or npm.
env -i PATH="$PATH" bash -s -- "$app" "$COMPOSER" > "$release/build.log" 2>&1 <<'BUILD'
set -Eeuo pipefail
app="$1"
cd "$app"
cp -- .env.example .env
export COMPOSER_HOME="$app/.composer-build"
export APP_ENV=testing APP_DEBUG=false APP_URL=http://127.0.0.1
export APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
export APP_CONFIG_CACHE="$app/bootstrap/cache/config.php" APP_ROUTES_CACHE="$app/bootstrap/cache/routes-v7.php"
export APP_SERVICES_CACHE="$app/bootstrap/cache/services.php" APP_PACKAGES_CACHE="$app/bootstrap/cache/packages.php" APP_EVENTS_CACHE="$app/bootstrap/cache/events.php"
export VIEW_COMPILED_PATH="$app/bootstrap/cache/views"
export DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL=
export SESSION_DRIVER=array CACHE_STORE=array QUEUE_CONNECTION=sync MAIL_MAILER=array
export NDI_CATALOG_MODE=live NDI_MEDIA_SYNC_ENABLED=false NDI_INQUIRY_ENABLED=false NDI_ORDER_ENABLED=false NDI_FULFILMENT_READ_ENABLED=false
export NDI_INBOX_PATH= NDI_SANDBOX_CATALOG_ENABLED=false NDI_SANDBOX_INQUIRY_ENABLED=false NDI_SANDBOX_ORDER_ENABLED=false
export OUTBOX_NOTION_ENABLED=false OUTBOX_TELEGRAM_ENABLED=false CUSTOMER_EMAIL_DELIVERY_ENABLED=false PAYMENTS_ENABLED=false VEHICLE_FITMENT_ENABLED=false
"$2" install --prefer-dist --no-interaction --optimize-autoloader --no-dev
npm ci --no-audit --no-fund
npm run build
BUILD

# Runtime configuration is inherited byte-for-byte, including snapshot mode.
cp -- "$previous/application/.env" "$app/.env"
assert_candidate_env() {
    [[ -f "$app/.env" && ! -L "$app/.env" ]] || fail 'Candidate environment is not release-owned.'
    [[ "$(sha256sum "$app/.env" | cut -d' ' -f1)" == "$previous_env_sha" ]] \
        && cmp -s -- "$previous/application/.env" "$app/.env" \
        || fail 'Candidate environment differs from the retained Modern release.'
}
assert_candidate_env
validate_env "$app/.env"
chgrp www-data "$app/.env"
chmod 640 "$app/.env"
mkdir -p -- "$app/bootstrap/cache/views"
chgrp -R www-data "$app/bootstrap/cache"
chmod -R g+rwX "$app/bootstrap/cache"
# All candidate Artisan commands finish before shared storage is attached.
env -i PATH="$PATH" bash -s -- "$app" > "$release/cache.log" 2>&1 <<'CACHE'
set -Eeuo pipefail
app="$1"
cd "$app"
export APP_CONFIG_CACHE="$app/bootstrap/cache/config.php" APP_ROUTES_CACHE="$app/bootstrap/cache/routes-v7.php"
export APP_SERVICES_CACHE="$app/bootstrap/cache/services.php" APP_PACKAGES_CACHE="$app/bootstrap/cache/packages.php" APP_EVENTS_CACHE="$app/bootstrap/cache/events.php"
export VIEW_COMPILED_PATH="$app/bootstrap/cache/views"
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
php -r '$c = require $argv[1]; if (($c["app"]["env"] ?? null) !== "staging" || ($c["ndi"]["catalog_mode"] ?? null) !== "snapshot" || ($c["view"]["compiled"] ?? null) !== $argv[2]) { exit(1); }' "$app/bootstrap/cache/config.php" "$app/bootstrap/cache/views"
CACHE
assert_candidate_env

# This release is presentation-only and performs no database writes, so it does
# not create a new backup or otherwise touch the shared database.
assert_previous
assert_candidate_env

# Refuse activation while an old-code NDI importer is visible. Never signal it.
importer_status=0
pgrep -f -- "$IMPORT_PROCESS_PATTERN" > /dev/null 2>> "$release/activation.log" || importer_status=$?
case "$importer_status" in
    0) fail 'An NDI importer is still running; wait for it to finish and retry.' ;;
    1) ;;
    *) fail 'Cannot verify absence of running NDI importers; activation refused.' ;;
esac

# Attach unchanged Modern runtime storage only after build/cache verification.
mv -- "$app/storage" "$app/storage-build"
ln -s -- "$ROOT/shared/storage" "$app/storage"
ln -s -- "$ROOT/shared/storage/app/public" "$app/public/storage"
cp -- "$release/REVISION" "$app/public/design-revision.txt"
ln -s -- "$release" "$new_current"
mv -Tf -- "$new_current" "$ROOT/current"
# Stateless probes only; do not GET the storefront during activation.
curl --fail --silent --show-error --max-time 30 --resolve "$HOST:443:127.0.0.1" "https://$HOST/up" -o /dev/null 2>> "$release/activation.log"
[[ "$(curl --fail --silent --show-error --max-time 30 --resolve "$HOST:443:127.0.0.1" "https://$HOST/design-revision.txt" 2>> "$release/activation.log")" == "$revision" ]] || fail 'Activation revision probe failed.'
[[ "$(sha256sum "$previous/application/.env" | cut -d' ' -f1)" == "$previous_env_sha" && "$(sha256sum "$ROOT/shared/.env" | cut -d' ' -f1)" == "$shared_env_sha" ]] || fail 'Retained Modern environment changed during activation.'
assert_candidate_env
assert_classic_unchanged
completed=true
trap - EXIT INT TERM
log "Activated Modern $revision with unchanged snapshot environment; previous Modern $EXPECTED_CURRENT and Classic $EXPECTED_CLASSIC retained."
log 'No database reads or writes, migrations, seeds, imports, pruning, integration calls, Classic writes or service/queue restart performed.'
