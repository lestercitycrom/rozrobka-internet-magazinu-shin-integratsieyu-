#!/usr/bin/env bash
# Issue #75: bounded snapshot-mode release, stacked on the active design preview.
# This is NOT the main-branch hook: no migrations, seeds, imports or pruning.
set -Eeuo pipefail
umask 0027

readonly ROOT='/var/www/staging-easytire.bethub.fun'
readonly REPO='/srv/git/shop-ndi.git'
readonly BASE='274a1610829f87ed8521f65d62ac0789fc86ec28'
readonly EXPECTED_CURRENT='274a1610829f87ed8521f65d62ac0789fc86ec28'
readonly BRANCH='refs/heads/codex/staging-catalog-snapshot'
readonly HOST='staging-easytire.bethub.fun'
readonly COMPOSER='/usr/local/bin/composer'
readonly IMPORT_PROCESS_PATTERN='(^|[[:space:]/])[p]hp([0-9.]+)?[[:space:]]+([^[:space:]]+[[:space:]]+)*([^[:space:]]*/)?artisan[[:space:]]+ndi:(inbox:sync|import)([[:space:]]|$)'
revision="${1:-}"
mode="${2:-}"
log() { printf '[catalog-snapshot] %s\n' "$*"; }
fail() { log "$*" >&2; exit 1; }

# Read dotenv only as text, never as shell code. Require an unambiguous staging
# value, and reject cache/storage redirection that could escape the release.
validate_env() {
    awk '
        { sub(/\r$/, "") }
        /^[[:space:]]*(export[[:space:]]+)?APP_ENV[[:space:]]*=/ {
            environments++
            value=$0; sub(/^[^=]*=[[:space:]]*/, "", value)
            if (value !~ /^(staging|"staging"|\047staging\047)[[:space:]]*(#.*)?$/) bad=1
        }
        /^[[:space:]]*(export[[:space:]]+)?NDI_CATALOG_MODE[[:space:]]*=/ { modes++ }
        /^[[:space:]]*(export[[:space:]]+)?(APP_CONFIG_CACHE|APP_ROUTES_CACHE|APP_EVENTS_CACHE|APP_SERVICES_CACHE|APP_PACKAGES_CACHE|VIEW_COMPILED_PATH|LARAVEL_STORAGE_PATH)[[:space:]]*=/ { bad=1 }
        END { exit (bad || environments != 1 || modes > 1) }
    ' "$1" || fail 'Runtime environment must be unambiguous staging with no cache/storage overrides.'
}

[[ $# -eq 2 && "$revision" =~ ^[0-9a-f]{40}$ ]] || fail 'Supply the exact published SHA and --check or --deploy.'
[[ "$mode" == '--check' || "$mode" == '--deploy' ]] || fail 'Use --check or --deploy.'
[[ "$(git --git-dir="$REPO" rev-parse "$BRANCH^{commit}" 2>/dev/null)" == "$revision" ]] || fail 'Published snapshot branch does not match the requested SHA.'
git --git-dir="$REPO" merge-base --is-ancestor "$BASE" "$revision" || fail 'Candidate is not stacked on the pinned active design release.'

# Keep reviewed backend changes narrowly scoped; dependencies, routes, jobs,
# migrations, seeders, integrations and the main deployment hook are forbidden.
while IFS= read -r -d '' file; do
    case "$file" in
        application/app/Modules/Catalog/CatalogDataMode.php|application/app/Modules/Catalog/ReadModel/*|application/app/Modules/Catalog/Presentation/CatalogItemPresenter.php|application/app/Modules/Pricing/Data/SellingPrice.php|application/app/Modules/Pricing/SellingPriceService.php|application/app/Http/Controllers/Storefront/CatalogController.php|application/config/ndi.php|application/.env.example|application/phpunit.xml) ;;
        application/app/Console/Commands/SyncNdiInbox.php|application/app/Console/Commands/ImportNdiBundle.php|application/app/Console/Commands/EasyTireHealth.php|application/app/Modules/Operations/SystemHealth.php|application/resources/views/admin/health.blade.php) ;;
        application/resources/views/storefront/catalog/index.blade.php|application/resources/views/storefront/catalog/_card.blade.php|application/resources/views/storefront/catalog/show.blade.php|application/lang/nb/storefront.php|application/lang/ru/storefront.php|application/lang/nb/health.php|application/lang/ru/health.php) ;;
        application/tests/*|application/playwright.snapshot.config.ts|.github/workflows/quality.yml|.gitleaksignore|docs/operations/*|project-control/*|evidence/*|deploy/staging-catalog-snapshot.sh|deploy/tests/staging-catalog-snapshot-safety.sh|deploy/README.md) ;;
        *) fail "Outside approved snapshot scope: $file" ;;
    esac
done < <(git --git-dir="$REPO" diff --name-only -z "$BASE" "$revision")
while IFS= read -r entry; do
    case "$entry" in
        '100644 blob '*|'100755 blob '*) ;;
        *) fail 'Candidate contains a symlink, submodule or non-regular entry.' ;;
    esac
done < <(git --git-dir="$REPO" ls-tree -r "$revision")

previous="$(readlink -f "$ROOT/current")"
[[ "$previous" == "$ROOT/releases/"* && -d "$previous" && -f "$previous/REVISION" ]] || fail 'Current release cannot be safely retained.'
[[ "$(head -n 1 "$previous/REVISION")" == "$EXPECTED_CURRENT" ]] || fail 'Active staging revision changed; inspect before replacing it.'
[[ -f "$previous/application/.env" && ! -L "$previous/application/.env" && -f "$ROOT/shared/.env" ]] || fail 'Release-owned and shared runtime environments must exist.'
[[ -d "$ROOT/shared/storage" && "$(readlink -f "$ROOT/shared/storage")" == "$ROOT/shared/storage" ]] || fail 'Unexpected shared storage layout.'
[[ "$(readlink -f "$previous/application/storage")" == "$ROOT/shared/storage" ]] || fail 'Unexpected active storage layout.'
[[ -d "$ROOT/shared/storage/app/public" && "$(readlink -f "$previous/application/public/storage")" == "$ROOT/shared/storage/app/public" ]] || fail 'Unexpected public media storage layout.'
validate_env "$previous/application/.env"
previous_env_sha="$(sha256sum "$previous/application/.env" | cut -d' ' -f1)"
shared_env_sha="$(sha256sum "$ROOT/shared/.env" | cut -d' ' -f1)"
readonly backup_helper='application/app/Console/Commands/BackupMySqlDatabase.php'
[[ -f "$previous/$backup_helper" && ! -L "$previous/$backup_helper" ]] || fail 'Accepted backup helper is missing.'
[[ "$(git --git-dir="$REPO" show "$BASE:$backup_helper" | sha256sum | cut -d' ' -f1)" == "$(sha256sum "$previous/$backup_helper" | cut -d' ' -f1)" ]] || fail 'Active backup helper differs from the pinned implementation.'
[[ -f "$ROOT/deploy.lock" && ! -L "$ROOT/deploy.lock" ]] || fail 'Existing regular deployment lock is required.'
if [[ "$mode" == '--check' ]]; then
    log "Checks passed; candidate=$revision; retained=$EXPECTED_CURRENT; no files or data changed."
    exit 0
fi

exec 9<>"$ROOT/deploy.lock"
flock -n 9 || fail 'Another deployment is running.'
assert_previous() {
    [[ "$(readlink -f "$ROOT/current")" == "$previous" && "$(head -n 1 "$previous/REVISION")" == "$EXPECTED_CURRENT" ]] || fail 'Current release changed; inspect again.'
    [[ "$(sha256sum "$previous/application/.env" | cut -d' ' -f1)" == "$previous_env_sha" && "$(sha256sum "$ROOT/shared/.env" | cut -d' ' -f1)" == "$shared_env_sha" ]] || fail 'Retained runtime environment changed; inspect again.'
    [[ "$(git --git-dir="$REPO" rev-parse "$BRANCH^{commit}")" == "$revision" ]] || fail 'Published snapshot branch changed; inspect again.'
}
assert_previous
release="$ROOT/releases/$(date -u +%Y%m%d%H%M%S)-${revision:0:12}-snapshot"
[[ "$release" == "$ROOT/releases/"* && ! -e "$release" && ! -L "$release" ]] || fail 'Unsafe or existing candidate directory.'
rollback_link="$ROOT/.snapshot-rollback-${revision:0:12}"
new_current="$ROOT/.current-${revision:0:12}-snapshot"
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
            log "Rolled back atomically to $EXPECTED_CURRENT; no database restore."
        else
            log 'URGENT: rollback pointer replacement failed; operator intervention required.' >&2
        fi
    fi
    log 'Deployment did not complete; candidate logs and any new backup retained for inspection.' >&2
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

# env -i prevents caller DB/integration/cache variables from leaking into builds.
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

# Byte-preserve every retained .env line except this single release-local key.
cp -- "$previous/application/.env" "$app/.env"
env -i PATH="$PATH" SNAPSHOT_ENV="$app/.env" SNAPSHOT_PREVIOUS_ENV="$previous/application/.env" php > "$release/env-check.log" 2>&1 <<'ENV_EDIT'
<?php
$path = getenv('SNAPSHOT_ENV');
$before = file_get_contents(getenv('SNAPSHOT_PREVIOUS_ENV'));
$pattern = '/^[\t ]*(?:export[\t ]+)?NDI_CATALOG_MODE[\t ]*=[^\r\n]*(\r?\n|$)/m';
if ($before === false || ($before !== '' && ! str_ends_with($before, "\n"))) {
    exit(1);
}
$count = preg_match_all($pattern, $before);
if ($count > 1) {
    exit(1);
}
$after = $count === 1
    ? preg_replace_callback($pattern, fn ($match) => 'NDI_CATALOG_MODE=snapshot'.$match[1], $before)
    : $before."NDI_CATALOG_MODE=snapshot\n";
if (preg_replace($pattern, '', $before) !== preg_replace($pattern, '', $after)
    || file_put_contents($path, $after) !== strlen($after)
    || file_get_contents($path) !== $after) {
    exit(1);
}
ENV_EDIT
validate_env "$app/.env"
[[ "$(awk '{ sub(/\r$/, "") } /^NDI_CATALOG_MODE=snapshot$/ { n++ } END { print n+0 }' "$app/.env")" == 1 ]] || fail 'Snapshot mode was not set exactly once.'
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

# Invoke only the pinned, unchanged backup implementation from the old release.
# It uses --single-transaction/--skip-lock-tables and never invokes retention.
# Filenames are compared before/after; an older backup cannot satisfy this gate.
assert_previous
backup_dir="$ROOT/shared/storage/app/backups"
[[ -d "$backup_dir" && ! -L "$backup_dir" && "$(readlink -f "$backup_dir")" == "$backup_dir" ]] || fail 'Existing regular backup directory is required.'
declare -A existing_backups=()
while IFS= read -r -d '' backup; do existing_backups["$backup"]=1; done < <(find "$backup_dir" -maxdepth 1 -type f -name 'mysql-*.sql.gz' -print0)
(
    cd "$previous/application"
    env -i PATH="$PATH" php artisan easytire:backup:mysql --no-interaction
) > "$release/backup.log" 2>&1
fresh_backups=()
while IFS= read -r -d '' backup; do
    [[ ${existing_backups["$backup"]+present} ]] || fresh_backups+=("$backup")
done < <(find "$backup_dir" -maxdepth 1 -type f -name 'mysql-*.sql.gz' -print0)
[[ ${#fresh_backups[@]} -eq 1 ]] || fail 'Backup did not produce exactly one new archive.'
backup="${fresh_backups[0]}"
[[ -s "$backup" && ! -L "$backup" ]] && gzip -t -- "$backup" 2>/dev/null || fail 'New backup failed gzip validation.'
printf '%s\n' "$backup" > "$release/DATABASE_BACKUP"
sha256sum "$backup" > "$release/DATABASE_BACKUP_SHA256"
assert_previous

# The deploy lock does not own Laravel's import lock. Refuse a visible in-flight
# old-code importer immediately before attachment/activation, without signalling
# processes or changing services. [p]hp avoids matching this pgrep's own pattern.
# This read-only observation reduces, but cannot eliminate, a cron start race.
importer_status=0
pgrep -f -- "$IMPORT_PROCESS_PATTERN" > /dev/null 2>> "$release/activation.log" || importer_status=$?
case "$importer_status" in
    0) fail 'An NDI importer is still running; wait for it to finish and retry.' ;;
    1) ;;
    *) fail 'Cannot verify absence of running NDI importers; activation refused.' ;;
esac

# Attach unchanged runtime storage only after build/cache/backup verification.
mv -- "$app/storage" "$app/storage-build"
ln -s -- "$ROOT/shared/storage" "$app/storage"
ln -s -- "$ROOT/shared/storage/app/public" "$app/public/storage"
cp -- "$release/REVISION" "$app/public/design-revision.txt"
ln -s -- "$release" "$new_current"
mv -Tf -- "$new_current" "$ROOT/current"
# /up bypasses the stateful web middleware; the revision endpoint is static.
# Do not GET /, catalog, cart or admin during deployment activation.
curl --fail --silent --show-error --max-time 30 --resolve "$HOST:443:127.0.0.1" "https://$HOST/up" -o /dev/null 2>> "$release/activation.log"
[[ "$(curl --fail --silent --show-error --max-time 30 --resolve "$HOST:443:127.0.0.1" "https://$HOST/design-revision.txt" 2>> "$release/activation.log")" == "$revision" ]] || fail 'Activation revision probe failed.'
[[ "$(sha256sum "$previous/application/.env" | cut -d' ' -f1)" == "$previous_env_sha" && "$(sha256sum "$ROOT/shared/.env" | cut -d' ' -f1)" == "$shared_env_sha" ]] || fail 'Retained environment changed during activation.'
completed=true
trap - EXIT INT TERM
log "Activated $revision in snapshot mode; previous design release $EXPECTED_CURRENT retained."
log 'Fresh backup validated. No migrations, seeds, imports, pruning, integration calls or service/queue restart performed.'
