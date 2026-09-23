#!/usr/bin/env bash
# Offline fixtures only: no server connection, live database, build or deployment.
set -Eeuo pipefail
case "${OSTYPE:-}" in msys*) export MSYS=winsymlinks:sys ;; esac

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
script="$repo_root/deploy/classic-preview-staging.sh"
tmp="$(mktemp -d)"
[[ "$tmp" == /tmp/* ]] || { printf 'classic safety: unsafe temp directory\n' >&2; exit 1; }
cleanup() { rm -rf -- "$tmp"; }
trap cleanup EXIT
fail() { printf 'classic safety: %s\n' "$*" >&2; exit 1; }
pass() { printf 'classic safety: %s\n' "$*"; }

bash -n "$script"
grep -Fq "readonly ROOT='/var/www/staging-classic-easytire.bethub.fun'" "$script" || fail 'Classic root is not pinned'
grep -Fq "readonly PRIMARY_ROOT='/var/www/staging-easytire.bethub.fun'" "$script" || fail 'canonical root is not pinned'
grep -Fq "readonly BRANCH='refs/heads/codex/classic-storefront'" "$script" || fail 'Classic branch is not pinned'
grep -Fq "readonly BASE='7165ca13090e3f0f122b181cc6c37eb6fc9f874f'" "$script" || fail 'source base is not pinned'
grep -Fq "readonly PRIMARY_REVISION='5472266d2fe9412867b5d7f5eb6e5d6b8e26a808'" "$script" || fail 'canonical runtime is not pinned'
grep -Fq 'env -i PATH="$PATH" bash -s' "$script" || fail 'clean build/cache environment is missing'
grep -Fq 'DB_CONNECTION=sqlite DB_DATABASE=:memory:' "$script" || fail 'isolated SQLite build is missing'
grep -Fq 'SESSION_DRIVER=array CACHE_STORE=array QUEUE_CONNECTION=sync MAIL_MAILER=array' "$script" || fail 'isolated build adapters are missing'
grep -Fq 'mv -Tf -- "$new_current" "$ROOT/current"' "$script" || fail 'Classic atomic activation is missing'
grep -Fq 'mv -Tf -- "$rollback_link" "$ROOT/current"' "$script" || fail 'Classic atomic rollback is missing'
grep -Fq 'ln -s -- "$MEDIA_ROOT" "$app/public/storage"' "$script" || fail 'public-media-only link is missing'
grep -Fq 'array_key_exists("domain", $c["session"]??[]) && $c["session"]["domain"]===null' "$script" || fail 'null session-domain cache assertion is not null-safe'
if grep -Fq 'ln -s -- "$ROOT/shared/storage" "$app/storage"' "$script"; then fail 'whole Classic storage is shared'; fi
if grep -Eq '^[[:space:]]*(php[[:space:]]+artisan[[:space:]]+(migrate|db:seed|test|queue:restart|ndi:|easytire:backup|cache:clear)|mysql(dump)?[[:space:]]|systemctl[[:space:]]|service[[:space:]])' "$script"; then
    fail 'database test/mutation, import, backup, restart or service control command found'
fi
[[ "$(grep -c 'curl --fail' "$script")" -eq 2 ]] || fail 'activation must have exactly two probes'
if grep 'curl --fail' "$script" | grep -Fq '$PRIMARY_HOST'; then fail 'canonical host is probed during Classic activation'; fi
if grep 'curl --fail' "$script" | grep -Ev '\$HOST/(up|design-revision\.txt)' >/dev/null; then fail 'stateful Classic activation probe found'; fi
if grep -E '(mv|ln|unlink).+\$PRIMARY_ROOT/current' "$script" >/dev/null; then fail 'canonical pointer mutation found'; fi
if command -v php >/dev/null 2>&1; then
    php -r '$c=["session"=>["domain"=>null]]; $ok=array_key_exists("domain", $c["session"]??[]) && $c["session"]["domain"]===null; exit($ok?0:1);' \
        || fail 'real PHP rejected the expected null session domain'
    if php -r '$c=["session"=>[]]; $ok=array_key_exists("domain", $c["session"]??[]) && $c["session"]["domain"]===null; exit($ok?0:1);'; then
        fail 'real PHP accepted a missing session domain as explicit null'
    fi
fi

work="$tmp/work"
bare="$tmp/repo.git"
primary_root="$tmp/primary"
classic_root="$tmp/classic"
mockbin="$tmp/mockbin"
mkdir -p \
    "$work/application/app" "$work/application/database" "$work/application/routes" \
    "$work/application/resources/js" "$work/application/resources/views" \
    "$work/application/storage" "$work/application/bootstrap/cache" "$work/application/public" \
    "$work/deploy/tests" "$primary_root/releases" "$primary_root/shared/storage/app/public" \
    "$classic_root/releases" "$mockbin"
printf '#!/usr/bin/env php\n' > "$work/application/artisan"
printf '{}\n' > "$work/application/composer.lock"
printf '{"lockfileVersion":3}\n' > "$work/application/package-lock.json"
printf 'export default {}\n' > "$work/application/vite.config.js"
printf 'protected app\n' > "$work/application/app/Protected.php"
printf 'protected database\n' > "$work/application/database/.gitkeep"
printf 'protected route\n' > "$work/application/routes/web.php"
printf 'protected js\n' > "$work/application/resources/js/app.js"
printf 'base view\n' > "$work/application/resources/views/welcome.blade.php"
printf '*\n!.gitignore\n' > "$work/application/storage/.gitignore"
printf '*\n!.gitignore\n' > "$work/application/bootstrap/cache/.gitignore"
printf '*\n!.gitignore\n' > "$work/application/public/.gitignore"
cat > "$work/application/.env.example" <<'ENV'
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost
NDI_CATALOG_MODE=live
SESSION_DRIVER=database
CACHE_STORE=database
ENV
git -C "$work" init -q
git -C "$work" config user.email classic-safety@example.test
git -C "$work" config user.name 'Classic safety'
git -C "$work" add .
git -C "$work" commit -qm canonical
primary_revision="$(git -C "$work" rev-parse HEAD)"
git -C "$work" commit --allow-empty -qm source-base
base="$(git -C "$work" rev-parse HEAD)"
git -C "$work" switch -qc codex/classic-storefront
printf 'classic view one\n' > "$work/application/resources/views/welcome.blade.php"
git -C "$work" add application/resources/views/welcome.blade.php
git -C "$work" commit -qm classic-one
candidate_one="$(git -C "$work" rev-parse HEAD)"
git clone -q --bare "$work" "$bare"
git --git-dir="$bare" update-ref refs/heads/codex/classic-storefront "$candidate_one"

primary_release="$primary_root/releases/canonical"
mkdir -p "$primary_release/application"
printf '%s\n' "$primary_revision" > "$primary_release/REVISION"
cat > "$primary_release/application/.env" <<'ENV'
APP_NAME=EasyTire
APP_ENV=staging
APP_KEY=base64:fixture-key
APP_DEBUG=false
APP_URL=https://staging-easytire.bethub.fun
STOREFRONT_DESIGN_PROFILE=modern
STOREFRONT_CANONICAL_ORIGIN=https://staging-easytire.bethub.fun
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=easytire
DB_USERNAME=easytire_user
DB_PASSWORD=fixture-secret-never-log
NDI_CATALOG_MODE=snapshot
NDI_INQUIRY_ENABLED=false
NDI_ORDER_ENABLED=false
NDI_FULFILMENT_READ_ENABLED=false
NDI_SANDBOX_CATALOG_ENABLED=true
NDI_SANDBOX_INQUIRY_ENABLED=true
NDI_SANDBOX_ORDER_ENABLED=false
PAYMENTS_ENABLED=true
VEHICLE_FITMENT_ENABLED=true
OUTBOX_NOTION_ENABLED=false
OUTBOX_TELEGRAM_ENABLED=false
CUSTOMER_EMAIL_DELIVERY_ENABLED=false
SESSION_DRIVER=database
SESSION_COOKIE=easytire_session
SESSION_DOMAIN=null
CACHE_STORE=database
CACHE_PREFIX=easytire_cache
QUEUE_CONNECTION=database
MAIL_MAILER=log
ENV
cp "$primary_release/application/.env" "$tmp/expected-primary.env"
cp "$primary_release/application/.env" "$primary_root/shared/.env"
ln -s "$primary_release" "$primary_root/current"
: > "$primary_root/deploy.lock"
: > "$classic_root/deploy.lock"
printf 'public-media\n' > "$primary_root/shared/storage/app/public/media.txt"

cat > "$mockbin/composer" <<'SH'
#!/usr/bin/env bash
set -Eeuo pipefail
printf 'composer %s\n' "$*" >> "$MOCK_LOG"
grep -Fxq 'APP_ENV=local' .env
[[ "${DB_CONNECTION:-}" == sqlite && "${DB_DATABASE:-}" == :memory: ]]
[[ "${SESSION_DRIVER:-}" == array && "${CACHE_STORE:-}" == array && "${QUEUE_CONNECTION:-}" == sync && "${MAIL_MAILER:-}" == array ]]
[[ "${PAYMENTS_ENABLED:-}" == false && "${VEHICLE_FITMENT_ENABLED:-}" == false && "${NDI_SANDBOX_CATALOG_ENABLED:-}" == false ]]
SH
cat > "$mockbin/npm" <<'SH'
#!/usr/bin/env bash
set -Eeuo pipefail
printf 'npm %s\n' "$*" >> "$MOCK_LOG"
SH
cat > "$mockbin/php" <<'SH'
#!/usr/bin/env bash
set -Eeuo pipefail
if [[ "${1:-}" == -r && "$#" -eq 2 ]]; then
    printf 'base64:bW9jay1idWlsZC1rZXk='
    exit 0
fi
printf 'php %s\n' "$*" >> "$MOCK_LOG"
SH
cat > "$mockbin/chgrp" <<'SH'
#!/usr/bin/env bash
set -Eeuo pipefail
printf 'chgrp %s\n' "$*" >> "$MOCK_LOG"
SH
cat > "$mockbin/flock" <<'SH'
#!/usr/bin/env bash
set -Eeuo pipefail
printf 'flock %s\n' "$*" >> "$MOCK_LOG"
SH
cat > "$mockbin/curl" <<'SH'
#!/usr/bin/env bash
set -Eeuo pipefail
printf 'curl %s\n' "$*" >> "$MOCK_LOG"
url=''
for argument in "$@"; do
    case "$argument" in https://*) url="$argument" ;; esac
done
if [[ "$(cat "$MOCK_SCENARIO")" == health-fails && "$url" == */up ]]; then exit 22; fi
if [[ "$url" == */design-revision.txt ]]; then printf '%s' "$MOCK_REVISION"; fi
SH
chmod +x "$mockbin"/*
sed -i "s|\\\$MOCK_LOG|$tmp/runtime.log|g" "$mockbin"/*

runner="$tmp/classic-preview-staging.sh"
sed \
    -e "s|readonly ROOT='/var/www/staging-classic-easytire.bethub.fun'|readonly ROOT='$classic_root'|" \
    -e "s|readonly PRIMARY_ROOT='/var/www/staging-easytire.bethub.fun'|readonly PRIMARY_ROOT='$primary_root'|" \
    -e "s|readonly REPO='/srv/git/shop-ndi.git'|readonly REPO='$bare'|" \
    -e "s|readonly BASE='[0-9a-f]*'|readonly BASE='$base'|" \
    -e "s|readonly PRIMARY_REVISION='[0-9a-f]*'|readonly PRIMARY_REVISION='$primary_revision'|" \
    -e "s|readonly COMPOSER='/usr/local/bin/composer'|readonly COMPOSER='$mockbin/composer'|" \
    "$script" > "$runner"
chmod +x "$runner"
export MOCK_LOG="$tmp/runtime.log" MOCK_SCENARIO="$tmp/scenario"
: > "$MOCK_LOG"
printf 'ok\n' > "$MOCK_SCENARIO"

primary_env_sha="$(sha256sum "$primary_release/application/.env" | cut -d' ' -f1)"
primary_shared_env_sha="$(sha256sum "$primary_root/shared/.env" | cut -d' ' -f1)"
primary_revision_sha="$(sha256sum "$primary_release/REVISION" | cut -d' ' -f1)"
primary_pointer="$(readlink "$primary_root/current")"
run() {
    MOCK_REVISION="$1" PATH="$mockbin:$PATH" APP_URL=https://hostile.invalid DB_PASSWORD=caller-secret SESSION_DRIVER=redis CACHE_STORE=redis \
        "$runner" "$@"
}

run "$candidate_one" --check > "$tmp/check.out"
grep -Fq 'Checks passed' "$tmp/check.out" || fail 'valid first-release check failed'
[[ ! -s "$MOCK_LOG" ]] || fail '--check invoked runtime commands'
[[ ! -e "$classic_root/current" && "$(find "$classic_root/releases" -mindepth 1 -maxdepth 1 | wc -l)" -eq 0 ]] || fail '--check changed Classic state'

cp "$tmp/expected-primary.env" "$primary_release/application/.env"
printf 'APP_CONFIG_CACHE=/hostile/shared-config.php\n' >> "$primary_release/application/.env"
if run "$candidate_one" --check > "$tmp/unsafe-env.out" 2>&1; then fail 'unsafe canonical cache override was accepted'; fi
grep -Fq 'redirects runtime paths' "$tmp/unsafe-env.out" || fail 'unsafe environment failed for an unexpected reason'
cp "$tmp/expected-primary.env" "$primary_release/application/.env"

ln -s "$classic_root" "$tmp/classic-link"
path_runner="$tmp/path-runner.sh"
sed "s|readonly ROOT='$classic_root'|readonly ROOT='$tmp/classic-link'|" "$runner" > "$path_runner"
chmod +x "$path_runner"
if PATH="$mockbin:$PATH" "$path_runner" "$candidate_one" --check > "$tmp/unsafe-path.out" 2>&1; then fail 'symlinked Classic root was accepted'; fi
grep -Fq 'must be an existing, non-symlink directory' "$tmp/unsafe-path.out" || fail 'unsafe path failed for an unexpected reason'

git -C "$work" switch -qC forbidden-js "$candidate_one"
printf 'changed js\n' > "$work/application/resources/js/app.js"
git -C "$work" commit -qam forbidden-js
forbidden_js="$(git -C "$work" rev-parse HEAD)"
git -C "$work" push -q "$bare" "$forbidden_js:refs/heads/codex/classic-storefront"
if run "$forbidden_js" --check > "$tmp/forbidden-js.out" 2>&1; then fail 'JS drift from canonical was accepted'; fi
grep -Fq 'Protected app/database/routes/JS/lockfile paths differ' "$tmp/forbidden-js.out" || fail 'JS drift failed for an unexpected reason'
git --git-dir="$bare" update-ref refs/heads/codex/classic-storefront "$candidate_one"

git -C "$work" switch -qC forbidden-business "$candidate_one"
mkdir -p "$work/application/config"
printf 'business change\n' > "$work/application/config/payments.php"
git -C "$work" add application/config/payments.php
git -C "$work" commit -qm forbidden-business
forbidden_business="$(git -C "$work" rev-parse HEAD)"
git -C "$work" push -q "$bare" "$forbidden_business:refs/heads/codex/classic-storefront"
if run "$forbidden_business" --check > "$tmp/forbidden-business.out" 2>&1; then fail 'business change was accepted'; fi
grep -Fq 'Outside approved Classic scope: application/config/payments.php' "$tmp/forbidden-business.out" || fail 'business guard failed for an unexpected reason'
git --git-dir="$bare" update-ref refs/heads/codex/classic-storefront "$candidate_one"

: > "$MOCK_LOG"
if ! run "$candidate_one" --deploy > "$tmp/deploy-one.out" 2>&1; then
    cat "$tmp/deploy-one.out" >&2
    find "$classic_root/releases" -maxdepth 2 -type f \( -name 'build.log' -o -name 'cache.log' -o -name 'activation.log' \) -exec sh -c 'printf "%s\n" "--- $1 ---"; cat "$1"' _ {} \; >&2
    fail 'valid mocked first deployment failed'
fi
first_release="$(readlink -f "$classic_root/current")"
[[ "$first_release" == "$classic_root/releases/"* && "$(cat "$first_release/REVISION")" == "$candidate_one" ]] || fail 'first Classic release was not activated'
[[ -d "$first_release/application/storage" && ! -L "$first_release/application/storage" ]] || fail 'Classic storage is not release-owned'
[[ -d "$first_release/application/storage/framework/cache/data" && -d "$first_release/application/storage/framework/sessions" && -d "$first_release/application/storage/framework/views" && -d "$first_release/application/storage/logs" ]] || fail 'release-local writable directories are incomplete'
[[ -d "$first_release/application/bootstrap/cache" && ! -L "$first_release/application/bootstrap/cache" ]] || fail 'Classic bootstrap cache is not release-owned'
[[ -L "$first_release/application/public/storage" && "$(readlink -f "$first_release/application/public/storage")" == "$primary_root/shared/storage/app/public" ]] || fail 'Classic public media link is not canonical media only'

classic_env="$first_release/application/.env"
for expected in \
    'APP_URL=https://staging-classic-easytire.bethub.fun' \
    'STOREFRONT_DESIGN_PROFILE=classic' \
    'STOREFRONT_CANONICAL_ORIGIN=https://staging-easytire.bethub.fun' \
    'SESSION_DRIVER=file' 'SESSION_COOKIE=easytire_classic_session' 'SESSION_DOMAIN=null' \
    'SESSION_SECURE_COOKIE=true' 'SESSION_HTTP_ONLY=true' \
    'CACHE_STORE=file' 'CACHE_PREFIX=easytire_classic_cache'; do
    grep -Fxq "$expected" "$classic_env" || fail "missing Classic environment value: $expected"
done
allowed='^(APP_URL|STOREFRONT_DESIGN_PROFILE|STOREFRONT_CANONICAL_ORIGIN|SESSION_DRIVER|SESSION_COOKIE|SESSION_DOMAIN|SESSION_SECURE_COOKIE|SESSION_HTTP_ONLY|CACHE_STORE|CACHE_PREFIX)='
grep -Ev "$allowed" "$tmp/expected-primary.env" > "$tmp/primary-nonclassic.env"
grep -Ev "$allowed" "$classic_env" > "$tmp/classic-nonclassic.env"
cmp -s "$tmp/primary-nonclassic.env" "$tmp/classic-nonclassic.env" || fail 'environment changed outside Classic allowlist'
for key in NDI_INQUIRY_ENABLED NDI_ORDER_ENABLED NDI_FULFILMENT_READ_ENABLED NDI_SANDBOX_CATALOG_ENABLED NDI_SANDBOX_INQUIRY_ENABLED NDI_SANDBOX_ORDER_ENABLED PAYMENTS_ENABLED VEHICLE_FITMENT_ENABLED OUTBOX_NOTION_ENABLED OUTBOX_TELEGRAM_ENABLED CUSTOMER_EMAIL_DELIVERY_ENABLED; do
    primary_line="$(grep -E "^${key}=" "$tmp/expected-primary.env")"
    grep -Fxq "$primary_line" "$classic_env" || fail "$key was enabled, disabled or otherwise changed"
done
grep -Fq 'DB_PASSWORD=fixture-secret-never-log' "$classic_env" || fail 'database credentials were not preserved'
if grep -Fq 'fixture-secret-never-log' "$tmp/deploy-one.out" "$MOCK_LOG"; then fail 'secret appeared in deployment output'; fi
if grep -Eq 'artisan (test|migrate|db:seed|queue:restart|ndi:|easytire:backup)' "$MOCK_LOG"; then fail 'forbidden Artisan command ran'; fi

[[ "$(sha256sum "$primary_release/application/.env" | cut -d' ' -f1)" == "$primary_env_sha" ]] || fail 'canonical environment changed'
[[ "$(sha256sum "$primary_root/shared/.env" | cut -d' ' -f1)" == "$primary_shared_env_sha" ]] || fail 'canonical shared environment changed'
[[ "$(sha256sum "$primary_release/REVISION" | cut -d' ' -f1)" == "$primary_revision_sha" ]] || fail 'canonical revision marker changed'
[[ "$(readlink "$primary_root/current")" == "$primary_pointer" ]] || fail 'canonical current pointer changed'

git -C "$work" switch -qC codex/classic-storefront "$candidate_one"
printf 'classic view two\n' > "$work/application/resources/views/welcome.blade.php"
git -C "$work" commit -qam classic-two
candidate_two="$(git -C "$work" rev-parse HEAD)"
git -C "$work" push -q "$bare" "$candidate_two:refs/heads/codex/classic-storefront"
: > "$MOCK_LOG"
printf 'health-fails\n' > "$MOCK_SCENARIO"
if run "$candidate_two" --deploy > "$tmp/deploy-two.out" 2>&1; then fail 'failed health probe activated a release'; fi
[[ "$(readlink -f "$classic_root/current")" == "$first_release" ]] || fail 'failed release did not roll back to previous Classic release'
[[ -d "$first_release" && "$(find "$classic_root/releases" -mindepth 1 -maxdepth 1 -type d | wc -l)" -eq 2 ]] || fail 'previous/failed Classic releases were not retained'
[[ "$(sha256sum "$primary_release/application/.env" | cut -d' ' -f1)" == "$primary_env_sha" && "$(sha256sum "$primary_root/shared/.env" | cut -d' ' -f1)" == "$primary_shared_env_sha" && "$(readlink "$primary_root/current")" == "$primary_pointer" ]] || fail 'rollback changed canonical staging'

pass 'syntax, pinned scope/runtime, read-only check, hostile path/env refusal, allowlisted env derivation, unchanged integrations/canonical staging, release-local state and atomic Classic rollback passed'
