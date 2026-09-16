#!/usr/bin/env bash
# Offline fixtures only: no deployment, server connection or real database use.
set -Eeuo pipefail
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
script="$repo_root/deploy/staging-catalog-snapshot.sh"
tmp="$(mktemp -d)"
[[ "$tmp" == /tmp/* ]] || { printf 'Unsafe temporary directory.\n' >&2; exit 1; }
cleanup() { rm -rf -- "$tmp"; }
trap cleanup EXIT
fail() { printf 'snapshot safety: %s\n' "$*" >&2; exit 1; }
pass() { printf 'snapshot safety: %s\n' "$*"; }
bash -n "$script"

if grep -Eq '^[[:space:]]*(php[[:space:]]+artisan[[:space:]]+(migrate|db:seed|queue:restart|ndi:|easytire:backup:prune)|mysql(dump)?[[:space:]]|systemctl[[:space:]]|service[[:space:]]|rm[[:space:]])' "$script"; then
    fail 'mutation, import, pruning or restart command found'
fi
[[ "$(grep -c 'curl --fail' "$script")" == 2 ]] || fail 'unexpected number of activation probes'
grep -Fq '"https://$HOST/up"' "$script" || fail 'health probe missing'
grep -Fq '"https://$HOST/design-revision.txt"' "$script" || fail 'revision probe missing'
if grep 'curl --fail' "$script" | grep -Ev '\$HOST/(up|design-revision\.txt)"' >/dev/null; then fail 'stateful activation probe found'; fi
grep -Fq 'export VIEW_COMPILED_PATH="$app/bootstrap/cache/views"' "$script" || fail 'local view cache missing'
grep -Fq 'export APP_CONFIG_CACHE="$app/bootstrap/cache/config.php"' "$script" || fail 'local config cache missing'
grep -Fq 'env -i PATH="$PATH" bash -s' "$script" || fail 'clean build/cache environment missing'
grep -Fq 'trap on_exit EXIT' "$script" || fail 'EXIT rollback trap missing'
grep -Fq "trap 'exit 130' INT" "$script" || fail 'INT rollback trap missing'
grep -Fq "trap 'exit 143' TERM" "$script" || fail 'TERM rollback trap missing'
grep -Fq 'mv -Tf -- "$new_current" "$ROOT/current"' "$script" || fail 'atomic activation missing'
grep -Fq 'mv -Tf -- "$rollback_link" "$ROOT/current"' "$script" || fail 'atomic rollback missing'
last_artisan="$(grep -nE '^[[:space:]]*(env -i PATH="\$PATH" )?php artisan' "$script" | tail -n 1 | cut -d: -f1)"
storage_attach="$(grep -nF 'ln -s -- "$ROOT/shared/storage" "$app/storage"' "$script" | cut -d: -f1)"
backup_validation="$(grep -nF 'gzip -t -- "$backup"' "$script" | cut -d: -f1)"
[[ "$last_artisan" -lt "$storage_attach" && "$backup_validation" -lt "$storage_attach" ]] || fail 'commands or backup validation can occur after shared storage attachment'
importer_check="$(grep -nF 'pgrep -f -- "$IMPORT_PROCESS_PATTERN"' "$script" | cut -d: -f1)"
[[ "$backup_validation" -lt "$importer_check" && "$importer_check" -lt "$storage_attach" ]] || fail 'in-flight importer check is not after backup and before attachment'
import_pattern="$(sed -n "s/^readonly IMPORT_PROCESS_PATTERN='\(.*\)'$/\1/p" "$script")"
[[ -n "$import_pattern" ]] || fail 'in-flight importer pattern missing'
for command in 'php artisan ndi:inbox:sync --limit=20' '/usr/bin/php8.5 -d memory_limit=1G /srv/app/artisan ndi:import bundle' '/bin/sh -c cd /srv/app && php artisan ndi:inbox:sync'; do
    printf '%s\n' "$command" | grep -Eq "$import_pattern" || fail 'importer pattern missed a supported CLI invocation'
done
for command in "pgrep -f -- $import_pattern" 'bash staging-catalog-snapshot.sh revision --deploy' 'php artisan queue:work' 'php artisan ndi:import-other' 'php artisan ndi:inbox:syncing'; do
    if printf '%s\n' "$command" | grep -Eq "$import_pattern"; then fail 'importer pattern matched itself or a non-import command'; fi
done

work="$tmp/work"
bare="$tmp/repo.git"
root="$tmp/root"
previous="$root/releases/design"
shared="$root/shared/storage"
mockbin="$tmp/mockbin"
mkdir -p "$work/.github/workflows" "$work/application/app/Console/Commands" "$work/application/config" "$work/application/storage" "$work/application/bootstrap/cache" "$work/application/public" "$mockbin"
printf 'name: Quality\n' > "$work/.github/workflows/quality.yml"
printf 'base\n' > "$work/application/config/ndi.php"
printf 'unchanged-safe-backup\n' > "$work/application/app/Console/Commands/BackupMySqlDatabase.php"
printf '*\n!.gitignore\n' > "$work/application/storage/.gitignore"
printf '*\n!.gitignore\n' > "$work/application/bootstrap/cache/.gitignore"
printf 'APP_ENV=local\nNDI_CATALOG_MODE=live\n' > "$work/application/.env.example"
printf 'fixture\n' > "$work/application/artisan"
printf '{}\n' > "$work/application/composer.lock"
printf 'fixture\n' > "$work/application/public/.gitignore"
git -C "$work" init -q
git -C "$work" config user.email snapshot-safety@example.test
git -C "$work" config user.name 'Snapshot safety'
git -C "$work" add .
git -C "$work" commit -qm base
base="$(git -C "$work" rev-parse HEAD)"
git -C "$work" switch -qc codex/staging-catalog-snapshot
printf 'snapshot\n' >> "$work/application/config/ndi.php"
printf '# snapshot safety CI\n' >> "$work/.github/workflows/quality.yml"
git -C "$work" commit -qam allowed
allowed="$(git -C "$work" rev-parse HEAD)"
git clone -q --bare "$work" "$bare"
mkdir -p "$previous/application/storage" "$previous/application/public/storage" "$previous/application/app/Console/Commands" "$shared/app/public" "$shared/app/backups" "$root/current"
printf '%s\n' "$base" > "$previous/REVISION"
printf 'APP_ENV=staging\nAPP_KEY=unchanged-test-key\nNDI_ORDER_ENABLED=false\n' > "$previous/application/.env"
cp "$previous/application/.env" "$root/shared/.env"
cp "$work/application/app/Console/Commands/BackupMySqlDatabase.php" "$previous/application/app/Console/Commands/BackupMySqlDatabase.php"
printf '%s\n' "$previous" > "$tmp/current-target"
printf '1\n' > "$tmp/counter"
printf 'ok\n' > "$tmp/scenario"
: > "$root/deploy.lock"
: > "$tmp/runtime.log"
original_env_sha="$(sha256sum "$previous/application/.env" | cut -d' ' -f1)"
shared_env_sha="$(sha256sum "$root/shared/.env" | cut -d' ' -f1)"

# Filesystem-link mocks let the same offline suite run on Git Bash without the
# Windows symlink privilege; actual release moves/archives remain real files.
cat > "$mockbin/readlink" <<EOF
#!/usr/bin/env bash
case "\${*: -1}" in
    "$root/current") cat "$tmp/current-target" ;;
    "$previous/application/storage") printf '%s\\n' "$shared" ;;
    "$previous/application/public/storage") printf '%s\\n' "$shared/app/public" ;;
    *) /usr/bin/readlink "\$@" ;;
esac
EOF
cat > "$mockbin/ln" <<EOF
#!/usr/bin/env bash
target="\${@: -2:1}"; destination="\${*: -1}"
case "\$destination" in
    "$root"/.current-*|"$root"/.snapshot-rollback-*) printf '%s\\n' "\$target" > "\$destination" ;;
    */application/storage|*/application/public/storage) mkdir -p "\$destination"; printf '%s\\n' "\$target" > "\$destination/.mock-link" ;;
    *) exit 91 ;;
esac
EOF
cat > "$mockbin/mv" <<EOF
#!/usr/bin/env bash
if [[ "\${*: -1}" == "$root/current" ]]; then
    source="\${@: -2:1}"
    cp "\$source" "$tmp/current-target"
    /usr/bin/rm -- "\$source"
    printf 'pointer\\n' >> "$tmp/runtime.log"
else
    /usr/bin/mv "\$@"
fi
EOF
cat > "$mockbin/date" <<EOF
#!/usr/bin/env bash
counter="\$(cat "$tmp/counter")"
printf '20260916%06d\\n' "\$counter"
printf '%s\\n' "\$((counter + 1))" > "$tmp/counter"
EOF
cat > "$mockbin/flock" <<EOF
#!/usr/bin/env bash
printf 'lock\\n' >> "$tmp/runtime.log"
[[ "\$(cat "$tmp/scenario")" != lock-fails ]]
EOF
cat > "$mockbin/pgrep" <<EOF
#!/usr/bin/env bash
printf 'importer-check\\n' >> "$tmp/runtime.log"
case "\$(cat "$tmp/scenario")" in
    importer-running) exit 0 ;;
    importer-check-error) exit 2 ;;
    *) exit 1 ;;
esac
EOF
cat > "$mockbin/composer" <<EOF
#!/usr/bin/env bash
[[ "\$APP_ENV" == testing && "\$DB_CONNECTION" == sqlite && "\$DB_DATABASE" == :memory: && "\$NDI_ORDER_ENABLED" == false && "\$NDI_CATALOG_MODE" == live && ! -e storage/.mock-link ]] || exit 92
printf 'build\\n' >> "$tmp/runtime.log"
EOF
cat > "$mockbin/npm" <<EOF
#!/usr/bin/env bash
[[ "\$APP_ENV" == testing && "\$DB_CONNECTION" == sqlite && ! -e storage/.mock-link ]] || exit 92
EOF
cat > "$mockbin/php" <<EOF
#!/usr/bin/env bash
set -Eeuo pipefail
case "\${1:-}" in
    -r)
        if [[ "\$2" == *random_bytes* ]]; then
            printf 'base64:fixture-key\\n'
        elif [[ "\$(cat "$tmp/scenario")" == config-fails ]]; then
            exit 90
        fi
        ;;
    artisan)
        printf '%s\\n' "\$2" >> "$tmp/runtime.log"
        if [[ "\$2" == easytire:backup:mysql ]]; then
            [[ "\$PWD" == "$previous/application" && -z "\${DB_CONNECTION:-}" ]] || exit 93
            [[ "\$(head -n 1 "$tmp/runtime.log")" == lock ]] || exit 93
            scenario="\$(cat "$tmp/scenario")"
            if [[ "\$scenario" == backup-fails ]]; then exit 94; fi
            if [[ "\$scenario" == backup-missing ]]; then exit 0; fi
            archive="$shared/app/backups/mysql-\$(cat "$tmp/counter").sql.gz"
            if [[ "\$scenario" == backup-corrupt ]]; then printf 'invalid\\n' > "\$archive"; else printf 'fixture SQL\\n' | /usr/bin/gzip > "\$archive"; fi
        else
            [[ ! -e storage/.mock-link && "\$APP_CONFIG_CACHE" == "\$PWD/bootstrap/cache/config.php" && "\$VIEW_COMPILED_PATH" == "\$PWD/bootstrap/cache/views" && -z "\${DB_CONNECTION:-}" ]] || exit 95
        fi
        ;;
    '')
        cat > /dev/null
        [[ -n "\${SNAPSHOT_ENV:-}" && -n "\${SNAPSHOT_PREVIOUS_ENV:-}" ]] || exit 96
        awk '!/^[[:space:]]*(export[[:space:]]+)?NDI_CATALOG_MODE[[:space:]]*=/' "\$SNAPSHOT_PREVIOUS_ENV" > "\$SNAPSHOT_ENV"
        printf 'NDI_CATALOG_MODE=snapshot\\n' >> "\$SNAPSHOT_ENV"
        ;;
    *) exit 97 ;;
esac
EOF
cat > "$mockbin/curl" <<EOF
#!/usr/bin/env bash
case "\$*" in
    *https://*/up*)
        printf 'probe-up\\n' >> "$tmp/runtime.log"
        [[ "\$(cat "$tmp/scenario")" != health-fails ]]
        ;;
    *https://*/design-revision.txt*)
        printf 'probe-revision\\n' >> "$tmp/runtime.log"
        if [[ "\$(cat "$tmp/scenario")" == revision-fails ]]; then printf 'wrong\\n'; else printf '%s\\n' "$allowed"; fi
        ;;
    *) exit 98 ;;
esac
EOF
cat > "$mockbin/chgrp" <<'EOF'
#!/usr/bin/env bash
exit 0
EOF
for command in mysql mysqldump systemctl service; do
    printf '#!/usr/bin/env bash\nexit 99\n' > "$mockbin/$command"
done
chmod +x "$mockbin"/*
runner="$tmp/staging-catalog-snapshot.sh"
sed \
    -e "s|readonly ROOT='/var/www/staging-easytire.bethub.fun'|readonly ROOT='$root'|" \
    -e "s|readonly REPO='/srv/git/shop-ndi.git'|readonly REPO='$bare'|" \
    -e "s|readonly BASE='[0-9a-f]*'|readonly BASE='$base'|" \
    -e "s|readonly EXPECTED_CURRENT='[0-9a-f]*'|readonly EXPECTED_CURRENT='$base'|" \
    -e "s|readonly COMPOSER='/usr/local/bin/composer'|readonly COMPOSER='$mockbin/composer'|" \
    "$script" > "$runner"
chmod +x "$runner"
run() { PATH="$mockbin:$PATH" DB_CONNECTION=must-not-leak NDI_ORDER_ENABLED=true "$runner" "$@"; }
run "$allowed" --check > "$tmp/check.out"
grep -Fq 'Checks passed' "$tmp/check.out" || fail 'allowed candidate rejected'
[[ ! -s "$tmp/runtime.log" ]] || fail '--check invoked runtime commands'
[[ "$(find "$root/releases" -mindepth 1 -maxdepth 1 -type d | wc -l)" -eq 1 ]] || fail '--check created a release'
if run "$base" --check > "$tmp/sha.out" 2>&1; then fail 'branch/SHA mismatch accepted'; fi
if run "$allowed" > "$tmp/mode.out" 2>&1; then fail 'omitted action accepted'; fi
printf '%s\n' "$allowed" > "$previous/REVISION"
if run "$allowed" --check > "$tmp/current.out" 2>&1; then fail 'changed current accepted'; fi
printf '%s\n' "$base" > "$previous/REVISION"
for invalid in 'APP_ENV=production' $'APP_ENV=staging\nAPP_ENV=staging' $'APP_ENV=staging\nNDI_CATALOG_MODE=live\nNDI_CATALOG_MODE=snapshot' $'APP_ENV=staging\nAPP_CONFIG_CACHE=/shared/config.php'; do
    printf '%s\n' "$invalid" > "$previous/application/.env"
    if run "$allowed" --check > "$tmp/env-invalid.out" 2>&1; then fail 'ambiguous/unsafe environment accepted'; fi
done
cp "$root/shared/.env" "$previous/application/.env"
assert_forbidden() {
    local label="$1" path="$2" forbidden
    git -C "$work" switch -qC "forbidden-$label" "$allowed"
    mkdir -p "$(dirname "$work/$path")"
    printf 'forbidden\n' > "$work/$path"
    git -C "$work" add "$path"
    git -C "$work" commit -qm "forbidden-$label"
    forbidden="$(git -C "$work" rev-parse HEAD)"
    git -C "$work" push -q "$bare" "$forbidden:refs/heads/fixture-$label"
    git --git-dir="$bare" update-ref refs/heads/codex/staging-catalog-snapshot "$forbidden"
    if run "$forbidden" --check > "$tmp/forbidden-$label.out" 2>&1; then fail "$label change accepted"; fi
    grep -Fq "Outside approved snapshot scope: $path" "$tmp/forbidden-$label.out" || fail "$label failed for an unexpected reason"
}
assert_forbidden migration application/database/migrations/unsafe.php
assert_forbidden dependency application/composer.json
assert_forbidden integration application/app/Modules/NdiApi/SubmitNdiOrder.php
assert_forbidden hook deploy/post-receive
assert_forbidden env application/.env
assert_forbidden other-workflow .github/workflows/deploy.yml
git -C "$work" switch -qC forbidden-symlink "$allowed"
link_object="$(printf '/outside-fixture\n' | git -C "$work" hash-object -w --stdin)"
git -C "$work" update-index --cacheinfo "120000,$link_object,application/config/ndi.php"
git -C "$work" commit -qm forbidden-symlink
symlink_candidate="$(git -C "$work" rev-parse HEAD)"
git -C "$work" push -q "$bare" "$symlink_candidate:refs/heads/fixture-symlink"
git --git-dir="$bare" update-ref refs/heads/codex/staging-catalog-snapshot "$symlink_candidate"
if run "$symlink_candidate" --check > "$tmp/symlink.out" 2>&1; then fail 'symlink tree accepted'; fi
grep -Fq 'Candidate contains a symlink' "$tmp/symlink.out" || fail 'symlink failed for an unexpected reason'
unrelated="$(git --git-dir="$bare" -c user.email=snapshot-safety@example.test -c user.name='Snapshot safety' commit-tree "$allowed^{tree}" -m unrelated)"
git --git-dir="$bare" update-ref refs/heads/codex/staging-catalog-snapshot "$unrelated"
if run "$unrelated" --check > "$tmp/unrelated.out" 2>&1; then fail 'unrelated history accepted'; fi
grep -Fq 'not stacked on the pinned active design release' "$tmp/unrelated.out" || fail 'ancestry failed for an unexpected reason'
git --git-dir="$bare" update-ref refs/heads/codex/staging-catalog-snapshot "$allowed"
[[ ! -s "$tmp/runtime.log" ]] || fail 'failed preflight invoked runtime commands'

for scenario in lock-fails config-fails backup-fails backup-corrupt backup-missing importer-running importer-check-error health-fails revision-fails ok; do
    printf '%s\n' "$scenario" > "$tmp/scenario"
    printf '%s\n' "$previous" > "$tmp/current-target"
    : > "$tmp/runtime.log"
    if run "$allowed" --deploy > "$tmp/$scenario.out" 2>&1; then
        [[ "$scenario" == ok ]] || fail "$scenario unexpectedly activated"
        [[ "$(cat "$tmp/current-target")" != "$previous" ]] || fail 'successful release was not activated'
        activated="$(cat "$tmp/current-target")"
        [[ "$(cat "$activated/REVISION")" == "$allowed" ]] || fail 'activated SHA differs'
        grep -Fxq 'NDI_CATALOG_MODE=snapshot' "$activated/application/.env" || fail 'candidate mode missing'
        [[ -f "$activated/DATABASE_BACKUP" && -f "$activated/PREVIOUS_RELEASE" ]] || fail 'recovery markers missing'
        [[ "$(grep -c '^pointer$' "$tmp/runtime.log")" == 1 ]] || fail 'activation was not singular'
        [[ "$(grep -n '^easytire:backup:mysql$' "$tmp/runtime.log" | cut -d: -f1)" -lt "$(grep -n '^importer-check$' "$tmp/runtime.log" | cut -d: -f1)" ]] || fail 'importer check did not run after backup'
    else
        [[ "$scenario" != ok ]] || { cat "$tmp/$scenario.out"; fail 'allowed mocked deployment failed'; }
        [[ "$(cat "$tmp/current-target")" == "$previous" ]] || fail "$scenario failed to retain/restore current"
        case "$scenario" in
            health-fails|revision-fails) [[ "$(grep -c '^pointer$' "$tmp/runtime.log")" == 2 ]] || fail "$scenario lacked atomic rollback" ;;
            *) if grep -q '^pointer$' "$tmp/runtime.log"; then fail "$scenario activated before verification"; fi ;;
        esac
    fi
    [[ "$(sha256sum "$previous/application/.env" | cut -d' ' -f1)" == "$original_env_sha" && "$(sha256sum "$root/shared/.env" | cut -d' ' -f1)" == "$shared_env_sha" ]] || fail "$scenario altered retained/shared environment"
    if grep -Eq '/var/www/|/srv/git/|/tmp/' "$tmp/$scenario.out"; then fail 'result log exposed a server/fixture path'; fi
done

# Exercise the real byte-preserving PHP edit separately when PHP is provided.
# SNAPSHOT_TEST_PHP can identify a local native runtime; it never boots Laravel.
php_binary="${SNAPSHOT_TEST_PHP:-$(command -v php || true)}"
if [[ -n "$php_binary" ]]; then
    sed -n '/^<?php$/,/^ENV_EDIT$/p' "$script" | sed '$d' > "$tmp/env-edit.php"
    php_path() { if [[ "$OSTYPE" == msys* ]]; then cygpath -m "$1"; else printf '%s\n' "$1"; fi; }
    for initial_mode in absent live quoted; do
        printf 'APP_ENV=staging\r\nUNCHANGED="value with spaces"\r\n' > "$tmp/env-before"
        case "$initial_mode" in live) printf 'NDI_CATALOG_MODE=live\r\n' >> "$tmp/env-before" ;; quoted) printf 'export NDI_CATALOG_MODE="live" # previous\r\n' >> "$tmp/env-before" ;; esac
        SNAPSHOT_ENV="$(php_path "$tmp/env-after")" SNAPSHOT_PREVIOUS_ENV="$(php_path "$tmp/env-before")" "$php_binary" "$(php_path "$tmp/env-edit.php")"
        sed '/NDI_CATALOG_MODE/d' "$tmp/env-before" > "$tmp/unchanged-before"
        sed '/NDI_CATALOG_MODE/d' "$tmp/env-after" > "$tmp/unchanged-after"
        cmp "$tmp/unchanged-before" "$tmp/unchanged-after" || fail 'environment edit changed unrelated bytes'
        [[ "$(tr -d '\r' < "$tmp/env-after" | grep -c '^NDI_CATALOG_MODE=snapshot$')" == 1 ]] || fail 'environment edit did not write exactly one snapshot key'
    done
    printf 'APP_ENV=staging\nNDI_CATALOG_MODE=live\nNDI_CATALOG_MODE=live\n' > "$tmp/env-before"
    if SNAPSHOT_ENV="$(php_path "$tmp/env-after")" SNAPSHOT_PREVIOUS_ENV="$(php_path "$tmp/env-before")" "$php_binary" "$(php_path "$tmp/env-edit.php")"; then fail 'real environment edit accepted duplicate mode'; fi
    pass 'real PHP environment editing preserves unrelated bytes and rejects duplicate mode'
else
    pass 'real PHP environment-edit test skipped (set SNAPSHOT_TEST_PHP to enable)'
fi
pass 'syntax, exact revision/scope/env guards, clean builds, backup/importer failure gates, atomic activation/rollback and retained environments passed (offline mocks)'
