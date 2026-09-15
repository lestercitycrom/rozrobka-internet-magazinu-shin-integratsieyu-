#!/usr/bin/env bash
# Issue #44 / PM 5673643971: published design candidates only; no DB mutation.
set -Eeuo pipefail
umask 0027

readonly ROOT='/var/www/staging-easytire.bethub.fun'
readonly REPO='/srv/git/shop-ndi.git'
readonly BASE='bf7f03e3d3265174e4e4307064769a742dd087cc'
readonly BRANCH='refs/heads/codex/issue-44-design-v2'
readonly HOST='staging-easytire.bethub.fun'
readonly COMPOSER='/usr/local/bin/composer'
revision="${1:-}"
mode="${2:---deploy}"
log() { printf '[design-preview] %s\n' "$*"; }
fail() { log "$*" >&2; exit 1; }

[[ "$revision" =~ ^[0-9a-f]{40}$ ]] || fail 'Supply the exact published GitHub SHA.'
[[ "$mode" == '--check' || "$mode" == '--deploy' ]] || fail 'Use --check or --deploy.'
[[ "$(git --git-dir="$REPO" rev-parse "$BRANCH^{commit}")" == "$revision" ]] || fail 'The received design branch does not match the requested SHA.'
git --git-dir="$REPO" merge-base --is-ancestor "$BASE" "$revision" || fail 'Candidate is not based on the accepted main.'

# Fail closed if a preview contains backend, data, dependency or environment changes.
while IFS= read -r -d '' file; do
    case "$file" in
        application/resources/css/app.css|application/resources/css/design-tokens.css|application/resources/css/pro-design.css|application/resources/css/storefront-v3.css|application/resources/css/storefront-contracts.css|application/resources/views/storefront/*|application/resources/views/components/layouts/storefront.blade.php|application/resources/views/components/icon.blade.php|application/resources/views/welcome.blade.php|application/resources/js/ui.js|application/resources/js/catalog-live.js|application/resources/js/product-media.js|application/lang/nb/pro.php|application/lang/ru/pro.php|application/tests/*|application/playwright.config.ts|project-control/*|evidence/*|deploy/preview-staging.sh|deploy/tests/preview-staging-safety.sh|deploy/README.md) ;;
        application/public/brand/*.webp|application/public/brand/*.jpg|application/public/brand/*.jpeg|application/public/brand/*.png|application/public/brand/*.avif|application/public/brand/*.svg|application/public/brand/*.mp4|application/public/brand/*.webm|application/public/brand/*.woff2) ;;
        *) fail "Outside presentation-only preview scope: $file" ;;
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
[[ -f "$previous/application/.env" && -d "$ROOT/shared/storage" ]] || fail 'Existing runtime settings/storage are missing.'
[[ "$(readlink -f "$previous/application/storage")" == "$ROOT/shared/storage" ]] || fail 'Unexpected active storage layout.'
[[ -d "$ROOT/shared/storage/app/public" && "$(readlink -f "$previous/application/public/storage")" == "$ROOT/shared/storage/app/public" ]] || fail 'Unexpected public media storage layout.'
fallback_found=false
for marker in "$ROOT"/releases/*/REVISION; do
    if [[ -f "$marker" && "$(head -n 1 "$marker")" == "$BASE" ]]; then fallback_found=true; break; fi
done
[[ "$fallback_found" == true ]] || fail 'Accepted fallback release must remain available.'
[[ ! -L "$ROOT/deploy.lock" ]] || fail 'Unexpected lock symlink.'
if [[ "$mode" == '--check' ]]; then
    log "Checks passed; candidate=$revision; retained=$previous; no files or data changed."
    exit 0
fi

exec 9>"$ROOT/deploy.lock"
flock -n 9 || fail 'Another deployment is running.'
[[ "$(readlink -f "$ROOT/current")" == "$previous" ]] || fail 'Current changed while acquiring the lock; inspect again.'
release="$ROOT/releases/$(date -u +%Y%m%d%H%M%S)-${revision:0:12}-preview"
[[ "$release" == "$ROOT/releases/"* && ! -e "$release" ]] || fail 'Unsafe or existing candidate directory.'
completed=false
readonly main_pid="$BASHPID"
on_exit() {
    local exit_code=$?
    [[ "$BASHPID" == "$main_pid" ]] || return "$exit_code"
    trap - EXIT INT TERM
    if [[ "$completed" == true ]]; then return "$exit_code"; fi
    if [[ "$(readlink -f "$ROOT/current")" == "$release" ]]; then
        rollback_link="$ROOT/.preview-rollback-${revision:0:12}"
        if [[ ! -e "$rollback_link" && ! -L "$rollback_link" ]]; then
            ln -s -- "$previous" "$rollback_link"
            mv -Tf -- "$rollback_link" "$ROOT/current"
            log "Rolled back code/assets to $previous; no database restore."
        else
            log "Rollback link already exists; current must be inspected: $rollback_link"
        fi
    fi
    log "Failed candidate retained for inspection: $release"
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
[[ -d "$app/storage" && ! -L "$app/storage" && ! -e "$app/public/storage" ]] || fail 'Candidate storage is not isolated.'

# Build with candidate-local physical storage and a hermetic, non-networked env.
(
    cd "$app"
    cp .env.example .env
    export APP_ENV=testing APP_DEBUG=false APP_URL=http://127.0.0.1
    export APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
    export DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL=
    export SESSION_DRIVER=array CACHE_STORE=array QUEUE_CONNECTION=sync MAIL_MAILER=array
    export NDI_MEDIA_SYNC_ENABLED=false NDI_INQUIRY_ENABLED=false NDI_ORDER_ENABLED=false NDI_FULFILMENT_READ_ENABLED=false
    export NDI_SANDBOX_CATALOG_ENABLED=false NDI_SANDBOX_INQUIRY_ENABLED=false NDI_SANDBOX_ORDER_ENABLED=false
    export OUTBOX_NOTION_ENABLED=false OUTBOX_TELEGRAM_ENABLED=false CUSTOMER_EMAIL_DELIVERY_ENABLED=false PAYMENTS_ENABLED=false VEHICLE_FITMENT_ENABLED=false
    "$COMPOSER" install --prefer-dist --no-interaction --optimize-autoloader --no-dev
    npm ci --no-audit --no-fund
    npm run build
)

# Prepare runtime config and compiled Blade views while storage is still isolated.
# VIEW_COMPILED_PATH is release-local: no old shared templates are served or flushed.
cp -- "$previous/application/.env" "$app/.env"
chgrp www-data "$app/.env"
chmod 640 "$app/.env"
mkdir -p -- "$app/bootstrap/cache/views"
chgrp -R www-data "$app/bootstrap/cache"
chmod -R g+rwX "$app/bootstrap/cache"
(
    cd "$app"
    export VIEW_COMPILED_PATH="$app/bootstrap/cache/views"
    php artisan config:cache --no-interaction
    php artisan route:cache --no-interaction
    php artisan view:cache --no-interaction
)
# No Artisan commands or tests after attaching existing runtime storage.
mv -- "$app/storage" "$app/storage-build"
ln -s -- "$ROOT/shared/storage" "$app/storage"
ln -s -- "$ROOT/shared/storage/app/public" "$app/public/storage"
cp -- "$release/REVISION" "$app/public/design-revision.txt"
new_current="$ROOT/.current-${revision:0:12}-preview"
[[ ! -e "$new_current" && ! -L "$new_current" ]] || fail 'Activation link already exists.'
ln -s -- "$release" "$new_current"
mv -Tf -- "$new_current" "$ROOT/current"
curl --fail --silent --show-error --max-time 30 --resolve "$HOST:443:127.0.0.1" "https://$HOST/up" -o /dev/null
curl --fail --silent --show-error --max-time 30 --resolve "$HOST:443:127.0.0.1" "https://$HOST/" -o /dev/null
[[ "$(curl --fail --silent --show-error --max-time 30 --resolve "$HOST:443:127.0.0.1" "https://$HOST/design-revision.txt")" == "$revision" ]]
completed=true
trap - EXIT INT TERM
log "Activated $revision; retained previous=$previous and accepted fallback=$BASE."
log 'No migration, seed, pruning, runtime-setting change or service restart was performed.'
