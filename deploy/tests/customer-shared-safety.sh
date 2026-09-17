#!/usr/bin/env bash
# Offline fixtures only: no server connection, live database, build or deployment.
set -Eeuo pipefail
case "${OSTYPE:-}" in msys*) export MSYS=winsymlinks:sys ;; esac

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
script="$repo_root/deploy/customer-shared-staging.sh"
tmp="$(mktemp -d)"
[[ "$tmp" == /tmp/* ]] || { printf 'customer-shared safety: unsafe temp directory\n' >&2; exit 1; }
cleanup() { rm -rf -- "$tmp"; }
trap cleanup EXIT
fail() { printf 'customer-shared safety: %s\n' "$*" >&2; exit 1; }
pass() { printf 'customer-shared safety: %s\n' "$*"; }
sha() { sha256sum "$1" | cut -d' ' -f1; }

bash -n "$script"
grep -Fq "readonly BASE='f776dbe4710f001c24ba8848739f331cc15bbe96'" "$script" || fail 'accepted source is not pinned'
grep -Fq "readonly BRANCH='refs/heads/codex/customer-shared-followups'" "$script" || fail 'branch is not pinned'
grep -Fq "readonly PRIMARY_REVISION='fee302fc34ec741e5975bdf56394e8f38c2fe7d6'" "$script" || fail 'primary predecessor is not pinned'
grep -Fq "readonly CLASSIC_REVISION='fee302fc34ec741e5975bdf56394e8f38c2fe7d6'" "$script" || fail 'Classic predecessor is not pinned'
grep -Fq 'env -i PATH="$PATH" bash -s' "$script" || fail 'clean build/cache execution is missing'
grep -Fq 'DB_CONNECTION=sqlite DB_DATABASE=:memory:' "$script" || fail 'isolated SQLite build is missing'
grep -Fq 'NDI_INQUIRY_ENABLED=false NDI_ORDER_ENABLED=false' "$script" || fail 'build gates are not disabled'
grep -Fq 'cp -- "$primary_env" "$primary_app/.env"' "$script" || fail 'primary does not inherit its own environment'
grep -Fq 'cp -- "$classic_env" "$classic_app/.env"' "$script" || fail 'Classic does not inherit its own environment'
grep -Fq 'ln -s -- "$PRIMARY_ROOT/shared/storage" "$primary_app/storage"' "$script" || fail 'primary shared storage is missing'
grep -Fq 'ln -s -- "$MEDIA_ROOT" "$classic_app/public/storage"' "$script" || fail 'Classic media-only share is missing'
if grep -Fq 'ln -s -- "$PRIMARY_ROOT/shared/storage" "$classic_app/storage"' "$script"; then fail 'Classic shares primary runtime storage'; fi
[[ "$(grep -c 'curl --fail' "$script")" -eq 4 ]] || fail 'activation must have exactly two probes per host'
if grep 'curl --fail' "$script" | grep -Ev '/(up|design-revision\.txt)' >/dev/null; then fail 'stateful activation probe found'; fi
if grep -Eq '^[[:space:]]*(php[[:space:]]+artisan[[:space:]]+(migrate|db:seed|test|queue:restart|ndi:|easytire:backup)|mysql(dump)?[[:space:]]|systemctl[[:space:]]|service[[:space:]])' "$script"; then
    fail 'database mutation/test, import, backup, restart or service control command found'
fi

work="$tmp/work"
bare="$tmp/repo.git"
primary_root="$tmp/primary"
classic_root="$tmp/classic"
mockbin="$tmp/mockbin"
nginx="$tmp/nginx"
mkdir -p "$work/application/app/Http/Controllers/Storefront" "$work/application/app/Modules/Content/ReadModel" \
    "$work/application/database" "$work/application/config" "$work/application/routes" \
    "$work/application/resources/css" "$work/application/resources/js" "$work/application/resources/views" \
    "$work/application/storage" "$work/application/bootstrap/cache" "$work/application/public" \
    "$primary_root/releases" "$primary_root/shared/storage/app/public" "$classic_root/releases" "$mockbin" "$nginx"
printf '#!/usr/bin/env php\n' > "$work/application/artisan"
printf '{}\n' > "$work/application/composer.json"
printf '{}\n' > "$work/application/composer.lock"
printf '{}\n' > "$work/application/package.json"
printf '{"lockfileVersion":3}\n' > "$work/application/package-lock.json"
printf 'business config\n' > "$work/application/config/business.php"
printf '*\n!.gitignore\n' > "$work/application/storage/.gitignore"
printf '*\n!.gitignore\n' > "$work/application/bootstrap/cache/.gitignore"
printf '*\n!.gitignore\n' > "$work/application/public/.gitignore"
cat > "$work/application/.env.example" <<'ENV'
APP_ENV=local
APP_KEY=
APP_URL=http://localhost
NDI_CATALOG_MODE=live
STOREFRONT_DESIGN_PROFILE=modern
ENV
cat > "$work/application/routes/web.php" <<'PHP'
<?php
use Illuminate\Support\Facades\Route;
Route::get('/', fn () => 'ok');
PHP
git -C "$work" init -q
git -C "$work" config user.email customer-shared-safety@example.test
git -C "$work" config user.name 'Customer shared safety'
git -C "$work" add .
git -C "$work" commit -qm base
base="$(git -C "$work" rev-parse HEAD)"
git -C "$work" switch -qc codex/customer-shared-followups
cat > "$work/application/app/Http/Controllers/Storefront/SiteSearchController.php" <<'PHP'
<?php
final class SiteSearchController {}
PHP
cat > "$work/application/app/Modules/Content/ReadModel/SiteContentSearch.php" <<'PHP'
<?php
final class SiteContentSearch {}
PHP
cat > "$work/application/routes/web.php" <<'PHP'
<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Storefront\SiteSearchController;
Route::get('/', fn () => 'ok');
    Route::get('/sok', SiteSearchController::class)->name('storefront.site-search.index');
    Route::get('/sok', SiteSearchController::class)->name('site-search.index');
PHP
printf 'shared customer styles\n' > "$work/application/resources/css/customer-shared-followups.css"
git -C "$work" add .
git -C "$work" commit -qm candidate
candidate="$(git -C "$work" rev-parse HEAD)"
git clone -q --bare "$work" "$bare"

primary_previous="$primary_root/releases/primary-old"
classic_previous="$classic_root/releases/classic-old"
mkdir -p "$primary_previous/application" "$classic_previous/application/storage/framework/sessions" "$classic_previous/application/public"
printf '%s\n' primary-old > "$primary_previous/REVISION"
printf '%s\n' classic-old > "$classic_previous/REVISION"
cat > "$primary_previous/application/.env" <<'ENV'
APP_ENV=staging
APP_KEY=primary-secret
APP_URL=https://staging-easytire.bethub.fun
NDI_CATALOG_MODE=snapshot
SESSION_DRIVER=database
SESSION_COOKIE=easytire_session
CACHE_STORE=database
CACHE_PREFIX=easytire_cache
ENV
cat > "$classic_previous/application/.env" <<'ENV'
APP_ENV=staging
APP_KEY=classic-secret
APP_URL=https://staging-classic-easytire.bethub.fun
NDI_CATALOG_MODE=snapshot
STOREFRONT_DESIGN_PROFILE=classic
STOREFRONT_CANONICAL_ORIGIN=https://staging-easytire.bethub.fun
SESSION_DRIVER=file
SESSION_COOKIE=easytire_classic_session
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
CACHE_STORE=file
CACHE_PREFIX=easytire_classic_cache
ENV
cp "$primary_previous/application/.env" "$primary_root/shared/.env"
ln -s "$primary_root/shared/storage" "$primary_previous/application/storage"
mkdir -p "$primary_previous/application/public"
ln -s "$primary_root/shared/storage/app/public" "$primary_previous/application/public/storage"
ln -s "$primary_root/shared/storage/app/public" "$classic_previous/application/public/storage"
printf 'cart-session-fixture\n' > "$classic_previous/application/storage/framework/sessions/cart-session"
ln -s "$primary_previous" "$primary_root/current"
ln -s "$classic_previous" "$classic_root/current"
: > "$primary_root/deploy.lock"
: > "$classic_root/deploy.lock"
printf 'primary nginx fixture\n' > "$nginx/primary.conf"
printf 'classic nginx fixture\n' > "$nginx/classic.conf"

cat > "$mockbin/composer" <<'SH'
#!/usr/bin/env bash
set -Eeuo pipefail
printf 'composer %s\n' "$*" >> "$MOCK_LOG"
[[ "${DB_CONNECTION:-}" == sqlite && "${DB_DATABASE:-}" == :memory: ]]
[[ "${SESSION_DRIVER:-}" == array && "${CACHE_STORE:-}" == array ]]
[[ "${NDI_INQUIRY_ENABLED:-}" == false && "${NDI_ORDER_ENABLED:-}" == false && "${PAYMENTS_ENABLED:-}" == false ]]
mkdir -p vendor
printf 'autoload\n' > vendor/autoload.php
SH
cat > "$mockbin/npm" <<'SH'
#!/usr/bin/env bash
set -Eeuo pipefail
printf 'npm %s\n' "$*" >> "$MOCK_LOG"
if [[ "${1:-}" == run && "${2:-}" == build ]]; then mkdir -p public/build; printf '{}\n' > public/build/manifest.json; fi
SH
cat > "$mockbin/php" <<'SH'
#!/usr/bin/env bash
set -Eeuo pipefail
if [[ "${1:-}" == -r && "$#" -eq 2 ]]; then printf 'base64:bW9jay1rZXk='; exit 0; fi
printf 'php %s\n' "$*" >> "$MOCK_LOG"
SH
cat > "$mockbin/flock" <<'SH'
#!/usr/bin/env bash
set -Eeuo pipefail
printf 'flock %s\n' "$*" >> "$MOCK_LOG"
SH
cat > "$mockbin/chgrp" <<'SH'
#!/usr/bin/env bash
set -Eeuo pipefail
printf 'chgrp %s\n' "$*" >> "$MOCK_LOG"
SH
cat > "$mockbin/chmod" <<'SH'
#!/usr/bin/env bash
set -Eeuo pipefail
printf 'chmod %s\n' "$*" >> "$MOCK_LOG"
SH
cat > "$mockbin/date" <<'SH'
#!/usr/bin/env bash
set -Eeuo pipefail
case "$(cat "$MOCK_SCENARIO")" in second-activation-fails) printf '20260917010101\n' ;; *) printf '20260917010102\n' ;; esac
SH
cat > "$mockbin/curl" <<'SH'
#!/usr/bin/env bash
set -Eeuo pipefail
printf 'curl %s\n' "$*" >> "$MOCK_LOG"
url=''
for argument in "$@"; do case "$argument" in https://*) url="$argument" ;; esac; done
if [[ "$(cat "$MOCK_SCENARIO")" == second-activation-fails && "$url" == https://staging-classic-easytire.bethub.fun/up ]]; then exit 22; fi
if [[ "$url" == */design-revision.txt ]]; then printf '%s' "$MOCK_REVISION"; fi
SH
chmod +x "$mockbin"/*
sed -i "s|\\\$MOCK_LOG|$tmp/runtime.log|g" "$mockbin"/*

runner="$tmp/customer-shared-staging.sh"
sed \
    -e "s|readonly PRIMARY_ROOT='/var/www/staging-easytire.bethub.fun'|readonly PRIMARY_ROOT='$primary_root'|" \
    -e "s|readonly CLASSIC_ROOT='/var/www/staging-classic-easytire.bethub.fun'|readonly CLASSIC_ROOT='$classic_root'|" \
    -e "s|readonly REPO='/srv/git/shop-ndi.git'|readonly REPO='$bare'|" \
    -e "s|readonly BASE='[0-9a-f]*'|readonly BASE='$base'|" \
    -e "s|readonly PRIMARY_REVISION='[0-9a-f]*'|readonly PRIMARY_REVISION='primary-old'|" \
    -e "s|readonly CLASSIC_REVISION='[0-9a-f]*'|readonly CLASSIC_REVISION='classic-old'|" \
    -e "s|readonly PRIMARY_PREVIOUS='[^']*'|readonly PRIMARY_PREVIOUS='$primary_previous'|" \
    -e "s|readonly CLASSIC_PREVIOUS='[^']*'|readonly CLASSIC_PREVIOUS='$classic_previous'|" \
    -e "s|readonly PRIMARY_NGINX='[^']*'|readonly PRIMARY_NGINX='$nginx/primary.conf'|" \
    -e "s|readonly CLASSIC_NGINX='[^']*'|readonly CLASSIC_NGINX='$nginx/classic.conf'|" \
    -e "s|readonly COMPOSER='/usr/local/bin/composer'|readonly COMPOSER='$mockbin/composer'|" \
    "$script" > "$runner"
chmod +x "$runner"
export MOCK_LOG="$tmp/runtime.log" MOCK_SCENARIO="$tmp/scenario" MOCK_REVISION="$candidate"
: > "$MOCK_LOG"
printf 'ok\n' > "$MOCK_SCENARIO"
run() { PATH="$mockbin:$PATH" APP_ENV=production DB_CONNECTION=mysql PAYMENTS_ENABLED=true "$runner" "$@"; }

run "$candidate" --check > "$tmp/check.out"
grep -Fq 'Checks passed' "$tmp/check.out" || fail 'valid read-only check failed'
[[ ! -s "$MOCK_LOG" ]] || fail '--check invoked runtime commands'

# Protected dependency/business drift is rejected before any runtime command.
git -C "$work" switch -qC forbidden "$candidate"
printf 'mutated\n' > "$work/application/config/business.php"
git -C "$work" commit -qam forbidden
forbidden="$(git -C "$work" rev-parse HEAD)"
git -C "$work" push -q "$bare" "$forbidden:refs/heads/codex/customer-shared-followups"
if run "$forbidden" --check > "$tmp/forbidden.out" 2>&1; then fail 'protected business drift was accepted'; fi
grep -Fq 'Protected business database/configuration or dependency inputs changed' "$tmp/forbidden.out" || fail 'protected drift failed for an unexpected reason'
git --git-dir="$bare" update-ref refs/heads/codex/customer-shared-followups "$candidate"

# Pointer drift and Classic profile/cookie/cache drift are independently guarded.
mkdir -p "$classic_root/releases/drift"
rm "$classic_root/current"; ln -s "$classic_root/releases/drift" "$classic_root/current"
if run "$candidate" --check > "$tmp/pointer-drift.out" 2>&1; then fail 'current pointer drift was accepted'; fi
grep -Fq 'Classic current release drifted' "$tmp/pointer-drift.out" || fail 'pointer drift failed for an unexpected reason'
rm "$classic_root/current"; ln -s "$classic_previous" "$classic_root/current"
cp "$classic_previous/application/.env" "$tmp/classic.env"
sed -i 's/CACHE_PREFIX=easytire_classic_cache/CACHE_PREFIX=wrong/' "$classic_previous/application/.env"
if run "$candidate" --check > "$tmp/env-drift.out" 2>&1; then fail 'Classic cookie/cache drift was accepted'; fi
grep -Fq 'Classic retained URL/profile/cookie/cache isolation drifted' "$tmp/env-drift.out" || fail 'environment guard failed for an unexpected reason'
cp "$tmp/classic.env" "$classic_previous/application/.env"

primary_env_sha="$(sha "$primary_previous/application/.env")"
classic_env_sha="$(sha "$classic_previous/application/.env")"
shared_env_sha="$(sha "$primary_root/shared/.env")"
primary_nginx_sha="$(sha "$nginx/primary.conf")"
classic_nginx_sha="$(sha "$nginx/classic.conf")"

# The primary succeeds first; a Classic health failure must roll both pointers back.
: > "$MOCK_LOG"
printf 'second-activation-fails\n' > "$MOCK_SCENARIO"
if run "$candidate" --deploy > "$tmp/second-fails.out" 2>&1; then fail 'second-host activation failure unexpectedly succeeded'; fi
[[ "$(readlink -f "$primary_root/current")" == "$primary_previous" ]] || fail 'primary was not rolled back after second-host failure'
[[ "$(readlink -f "$classic_root/current")" == "$classic_previous" ]] || fail 'Classic was not rolled back after second-host failure'
[[ "$(grep -c '^curl ' "$MOCK_LOG")" -eq 3 ]] || fail 'unexpected probe sequence before second-host failure'
[[ "$(find "$primary_root/releases" -mindepth 1 -maxdepth 1 -type d | wc -l)" -ge 2 && "$(find "$classic_root/releases" -mindepth 1 -maxdepth 1 -type d | wc -l)" -ge 3 ]] || fail 'failed candidate releases were not retained'

# Successful activation uses the same SHA/build outputs while retaining distinct profiles.
: > "$MOCK_LOG"
printf 'ok\n' > "$MOCK_SCENARIO"
run "$candidate" --deploy > "$tmp/ok.out"
primary_release="$(readlink -f "$primary_root/current")"
classic_release="$(readlink -f "$classic_root/current")"
[[ "$(cat "$primary_release/REVISION")" == "$candidate" && "$(cat "$classic_release/REVISION")" == "$candidate" ]] || fail 'hosts did not activate the same source SHA'
cmp -s "$primary_previous/application/.env" "$primary_release/application/.env" || fail 'primary environment was not inherited byte-for-byte'
cmp -s "$classic_previous/application/.env" "$classic_release/application/.env" || fail 'Classic environment was not inherited byte-for-byte'
if grep -q '^STOREFRONT_DESIGN_PROFILE=' "$primary_release/application/.env"; then fail 'primary default profile was mutated into its environment'; fi
grep -Fxq 'STOREFRONT_DESIGN_PROFILE=classic' "$classic_release/application/.env" || fail 'Classic profile was not retained'
[[ -L "$primary_release/application/storage" && "$(readlink -f "$primary_release/application/storage")" == "$primary_root/shared/storage" ]] || fail 'primary storage semantics changed'
[[ -d "$classic_release/application/storage" && ! -L "$classic_release/application/storage" ]] || fail 'Classic storage is not physical/release-local'
cmp -s "$classic_previous/application/storage/framework/sessions/cart-session" "$classic_release/application/storage/framework/sessions/cart-session" || fail 'Classic session/cart continuity was not preserved'
[[ -L "$classic_release/application/public/storage" && "$(readlink -f "$classic_release/application/public/storage")" == "$primary_root/shared/storage/app/public" ]] || fail 'Classic public media link changed'
[[ "$(sha "$primary_release/application/vendor/autoload.php")" == "$(sha "$classic_release/application/vendor/autoload.php")" && "$(sha "$primary_release/application/public/build/manifest.json")" == "$(sha "$classic_release/application/public/build/manifest.json")" ]] || fail 'reused dependencies/assets differ'
[[ "$(grep -c '^composer ' "$MOCK_LOG")" -eq 1 && "$(grep -c '^npm ci' "$MOCK_LOG")" -eq 1 && "$(grep -c '^npm run build' "$MOCK_LOG")" -eq 1 ]] || fail 'dependencies/assets were not built exactly once'
[[ "$(grep -c '^curl ' "$MOCK_LOG")" -eq 4 ]] || fail 'successful activation did not run exactly four probes'
if grep -Eq 'php artisan (migrate|db:seed|test|queue:restart|ndi:|easytire:backup)' "$MOCK_LOG"; then fail 'forbidden Artisan command ran'; fi

[[ "$(sha "$primary_previous/application/.env")" == "$primary_env_sha" && "$(sha "$classic_previous/application/.env")" == "$classic_env_sha" && "$(sha "$primary_root/shared/.env")" == "$shared_env_sha" ]] || fail 'retained/shared environment changed'
[[ "$(sha "$nginx/primary.conf")" == "$primary_nginx_sha" && "$(sha "$nginx/classic.conf")" == "$classic_nginx_sha" ]] || fail 'nginx fixture changed'

pass 'syntax, exact scope/routes, protected drift/env/profile guards, one isolated build, dual activation, second-host rollback, storage isolation and four safe probes passed'
