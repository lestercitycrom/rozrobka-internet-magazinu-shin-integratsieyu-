#!/usr/bin/env bash
set -Eeuo pipefail

readonly SOURCE_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)"
readonly SOURCE_HOOK="$SOURCE_ROOT/deploy/post-receive"
readonly SANDBOX="$(mktemp -d)"
trap 'rm -rf -- "$SANDBOX"' EXIT

readonly APP_ROOT="$SANDBOX/app-root"
readonly SHARED="$APP_ROOT/shared"
readonly RELEASES="$APP_ROOT/releases"
readonly FIXTURE="$SANDBOX/fixture"
readonly BIN="$SANDBOX/bin"
readonly LOG="$SANDBOX/commands.log"
readonly SHA='8ea1a65cb34f7786764c520d6662fe86201d310d'

mkdir -p "$SHARED/storage/app/public" "$RELEASES/old/application" "$FIXTURE/application/storage/app/public" "$FIXTURE/application/storage/framework/cache/data" "$FIXTURE/application/storage/framework/sessions" "$FIXTURE/application/storage/framework/testing" "$FIXTURE/application/storage/framework/views" "$FIXTURE/application/storage/logs" "$FIXTURE/application/public" "$FIXTURE/application/bootstrap/cache" "$BIN"
printf 'old\n' > "$RELEASES/old/REVISION"
ln -s "$RELEASES/old" "$APP_ROOT/current"
printf 'RUNTIME_SENTINEL=shared-only\nAPP_ENV=staging\n' > "$SHARED/.env"
printf 'persistent-control\n' > "$SHARED/storage/persistent-control.txt"
chmod -R ugo+rwX "$SHARED"
[[ -w "$SHARED/storage/app/public" ]]
printf 'shared-writable\n' > "$SHARED/storage/app/public/writable-probe.txt"

printf '#!/usr/bin/env php\n' > "$FIXTURE/application/artisan"
printf '{}\n' > "$FIXTURE/application/composer.json"
cat > "$FIXTURE/application/.env.example" <<'ENV'
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost
DB_CONNECTION=mysql
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
MAIL_MAILER=log
PAYMENTS_ENABLED=false
NDI_INQUIRY_ENABLED=false
NDI_ORDER_ENABLED=false
NDI_C1_TEST_MODE=false
NDI_SANDBOX_CATALOG_ENABLED=false
NDI_SANDBOX_INQUIRY_ENABLED=false
NDI_SANDBOX_ORDER_ENABLED=false
NDI_FULFILMENT_READ_ENABLED=false
OUTBOX_NOTION_ENABLED=false
OUTBOX_TELEGRAM_ENABLED=false
VEHICLE_FITMENT_ENABLED=false
ENV

cat > "$BIN/git" <<'SH'
#!/usr/bin/env bash
set -Eeuo pipefail
[[ "$1 $2" == 'checkout --force' ]]
cp -a "$HOOK_FIXTURE/." "$GIT_WORK_TREE/"
SH

cat > "$BIN/composer" <<'SH'
#!/usr/bin/env bash
set -Eeuo pipefail
printf 'composer %s\n' "$*" >> "$HOOK_LOG"
grep -Fxq 'APP_ENV=testing' .env
! grep -q 'RUNTIME_SENTINEL' .env
[[ -d storage && ! -L storage && ! -e public/storage && ! -L public/storage ]]
SH

cat > "$BIN/npm" <<'SH'
#!/usr/bin/env bash
set -Eeuo pipefail
printf 'npm %s\n' "$*" >> "$HOOK_LOG"
SH

cat > "$BIN/chgrp" <<'SH'
#!/usr/bin/env bash
set -Eeuo pipefail
printf 'chgrp %s\n' "$*" >> "$HOOK_LOG"
SH

cat > "$BIN/php" <<'SH'
#!/usr/bin/env bash
set -Eeuo pipefail
if [[ "${1:-}" == '-r' ]]; then
    printf 'base64:dGVzdC1vbmx5LWhlcm1ldGljLWtleQ=='
    exit 0
fi

printf 'php %s\n' "$*" >> "$HOOK_LOG"
if [[ "${1:-} ${2:-}" == 'artisan test' ]]; then
    grep -Fxq 'APP_ENV=testing' .env
    ! grep -q 'RUNTIME_SENTINEL' .env
    mkdir -p storage/app/public/media/homepage/issue-46
    printf 'candidate-only\n' > storage/app/public/media/homepage/issue-46/test-write.txt
    [[ -d storage && ! -L storage ]]
    [[ ! -e public/storage && ! -L public/storage ]]
    [[ ! -e "$HOOK_SHARED/storage/app/public/media/homepage/issue-46/test-write.txt" ]]
    [[ "${FAIL_TESTS:-0}" != '1' ]] || exit 42
fi

if [[ "${1:-} ${2:-}" == 'artisan easytire:backup:mysql' ]]; then
    [[ -L storage && "$(readlink -f storage)" == "$(readlink -f "$HOOK_SHARED/storage")" ]]
    [[ -L public/storage && "$(readlink -f public/storage)" == "$(readlink -f "$HOOK_SHARED/storage/app/public")" ]]
    grep -q 'RUNTIME_SENTINEL=shared-only' .env
    [[ ! -e "$HOOK_SHARED/storage/app/public/media/homepage/issue-46/test-write.txt" ]]
fi
SH

chmod 755 "$BIN/git" "$BIN/composer" "$BIN/npm" "$BIN/chgrp" "$BIN/php"

make_hook() {
    local target="$1"
    sed \
        -e "s|readonly APP_ROOT='/var/www/staging-easytire.bethub.fun'|readonly APP_ROOT='$APP_ROOT'|" \
        -e "s|readonly COMPOSER='/usr/local/bin/composer'|readonly COMPOSER='$BIN/composer'|" \
        "$SOURCE_HOOK" > "$target"
    chmod 755 "$target"
}

run_hook() {
    local target="$1"
    printf '%s %s refs/heads/main\n' "$SHA" "$SHA" | \
        env PATH="$BIN:$PATH" HOOK_FIXTURE="$FIXTURE" HOOK_LOG="$LOG" HOOK_SHARED="$SHARED" FAIL_TESTS="${FAIL_TESTS:-0}" \
        bash "$target"
}

failure_hook="$SANDBOX/post-receive-failure"
make_hook "$failure_hook"
: > "$LOG"
export FAIL_TESTS=1
if run_hook "$failure_hook" > "$SANDBOX/failure.out" 2>&1; then
    printf 'Expected the simulated test failure to stop deployment.\n' >&2
    exit 1
fi
unset FAIL_TESTS

[[ "$(readlink -f "$APP_ROOT/current")" == "$(readlink -f "$RELEASES/old")" ]]
[[ "$(find "$RELEASES" -mindepth 1 -maxdepth 1 -type d | wc -l | tr -d ' ')" == '1' ]]
[[ "$(cat "$SHARED/storage/persistent-control.txt")" == 'persistent-control' ]]
[[ "$(cat "$SHARED/storage/app/public/writable-probe.txt")" == 'shared-writable' ]]
[[ ! -e "$SHARED/storage/app/public/media/homepage/issue-46/test-write.txt" ]]
! grep -q 'easytire:backup:mysql' "$LOG"
! grep -q 'artisan migrate' "$LOG"
! grep -q 'artisan db:seed' "$LOG"
grep -q 'Failed pre-database release was removed.' "$SANDBOX/failure.out"

success_hook="$SANDBOX/post-receive-success"
make_hook "$success_hook"
: > "$LOG"
unset FAIL_TESTS
run_hook "$success_hook" > "$SANDBOX/success.out" 2>&1

[[ "$(cat "$APP_ROOT/current/REVISION")" == "$SHA" ]]
[[ "$(cat "$SHARED/storage/persistent-control.txt")" == 'persistent-control' ]]
[[ "$(cat "$SHARED/storage/app/public/writable-probe.txt")" == 'shared-writable' ]]
[[ ! -e "$SHARED/storage/app/public/media/homepage/issue-46/test-write.txt" ]]
[[ -L "$APP_ROOT/current/application/storage" ]]
[[ -L "$APP_ROOT/current/application/public/storage" ]]

test_line="$(grep -n '^php artisan test$' "$LOG" | cut -d: -f1)"
backup_line="$(grep -n '^php artisan easytire:backup:mysql --no-interaction$' "$LOG" | cut -d: -f1)"
migrate_line="$(grep -n '^php artisan migrate --force --no-interaction$' "$LOG" | cut -d: -f1)"
[[ "$test_line" -lt "$backup_line" && "$backup_line" -lt "$migrate_line" ]]

printf 'post-receive storage isolation checks passed.\n'
