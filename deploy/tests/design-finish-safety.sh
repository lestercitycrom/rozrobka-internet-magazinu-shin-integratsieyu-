#!/usr/bin/env bash
# Offline fixtures only: no deployment, server connection or real database use.
set -Eeuo pipefail
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
script="$repo_root/deploy/design-finish-staging.sh"
tmp="$(mktemp -d)"
[[ "$tmp" == /tmp/* ]] || { printf 'Unsafe temporary directory.\n' >&2; exit 1; }
cleanup() { rm -rf -- "$tmp"; }
trap cleanup EXIT
fail() { printf 'design safety: %s\n' "$*" >&2; exit 1; }
pass() { printf 'design safety: %s\n' "$*"; }
bash -n "$script"
grep -Fq 'cp -- "$previous/application/.env" "$app/.env"' "$script" || fail 'retained environment copy missing'
grep -Fq 'cmp -s -- "$previous/application/.env" "$app/.env"' "$script" || fail 'byte-identical runtime environment guard missing'
if grep -Eq 'ENV_EDIT|file_put_contents|SNAPSHOT_ENV|sed -i' "$script"; then fail 'runtime environment editing found'; fi

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
for command in "pgrep -f -- $import_pattern" 'bash design-finish-staging.sh revision --deploy' 'php artisan queue:work' 'php artisan ndi:import-other' 'php artisan ndi:inbox:syncing'; do
    if printf '%s\n' "$command" | grep -Eq "$import_pattern"; then fail 'importer pattern matched itself or a non-import command'; fi
done

work="$tmp/work"
bare="$tmp/repo.git"
root="$tmp/root"
previous="$root/releases/design"
shared="$root/shared/storage"
mockbin="$tmp/mockbin"
mkdir -p "$work/.github/workflows" "$work/application/app/Console/Commands" "$work/application/resources/css" "$work/application/storage" "$work/application/bootstrap/cache" "$work/application/public" "$mockbin"
printf 'name: Quality\n' > "$work/.github/workflows/quality.yml"
printf 'base\n' > "$work/application/resources/css/storefront-v3.css"
printf 'unchanged-safe-backup\n' > "$work/application/app/Console/Commands/BackupMySqlDatabase.php"
printf '*\n!.gitignore\n' > "$work/application/storage/.gitignore"
printf '*\n!.gitignore\n' > "$work/application/bootstrap/cache/.gitignore"
printf 'APP_ENV=local\nNDI_CATALOG_MODE=live\n' > "$work/application/.env.example"
printf 'fixture\n' > "$work/application/artisan"
printf '{}\n' > "$work/application/composer.lock"
printf 'fixture\n' > "$work/application/public/.gitignore"
git -C "$work" init -q
git -C "$work" config user.email filters-safety@example.test
git -C "$work" config user.name 'Filters safety'
git -C "$work" add .
git -C "$work" commit -qm base
base="$(git -C "$work" rev-parse HEAD)"
git -C "$work" switch -qc codex/autonomous-design-finish
printf 'filters\n' >> "$work/application/resources/css/storefront-v3.css"
printf '# design safety CI\n' >> "$work/.github/workflows/quality.yml"
git -C "$work" commit -qam allowed
allowed="$(git -C "$work" rev-parse HEAD)"
git clone -q --bare "$work" "$bare"
mkdir -p "$previous/application/storage" "$previous/application/public/storage" "$previous/application/app/Console/Commands" "$shared/app/public" "$shared/app/backups" "$root/current"
printf '%s\n' "$base" > "$previous/REVISION"
printf 'APP_ENV=staging\r\nAPP_KEY="unchanged-test-key"\r\n# preserve comments, CRLF and no final newline\r\nNDI_CATALOG_MODE="snapshot" # keep this spelling\r\nNDI_ORDER_ENABLED=false' > "$previous/application/.env"
cp "$previous/application/.env" "$tmp/expected-env"
printf 'APP_ENV=staging\nSHARED_ONLY=must-not-be-copied\n' > "$root/shared/.env"
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
    "$root"/.current-*|"$root"/.design-finish-rollback-*) printf '%s\\n' "\$target" > "\$destination" ;;
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
        elif [[ "\$(cat "$tmp/scenario")" == candidate-env-mutated ]]; then
            printf '\\nUNEXPECTED_ENV_CHANGE=true\\n' >> .env
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
runner="$tmp/design-finish-staging.sh"
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
for invalid in $'APP_ENV=production\nNDI_CATALOG_MODE=snapshot' 'APP_ENV=staging' $'APP_ENV=staging\nNDI_CATALOG_MODE=live' $'APP_ENV=staging\nAPP_ENV=staging\nNDI_CATALOG_MODE=snapshot' $'APP_ENV=staging\nNDI_CATALOG_MODE=live\nNDI_CATALOG_MODE=snapshot' $'APP_ENV=staging\nNDI_CATALOG_MODE=snapshot\nAPP_CONFIG_CACHE=/shared/config.php'; do
    printf '%s\n' "$invalid" > "$previous/application/.env"
    if run "$allowed" --check > "$tmp/env-invalid.out" 2>&1; then fail 'ambiguous/unsafe environment accepted'; fi
done
cp "$tmp/expected-env" "$previous/application/.env"
assert_forbidden() {
    local label="$1" path="$2" forbidden
    git -C "$work" switch -qC "forbidden-$label" "$allowed"
    mkdir -p "$(dirname "$work/$path")"
    printf 'forbidden\n' > "$work/$path"
    git -C "$work" add "$path"
    git -C "$work" commit -qm "forbidden-$label"
    forbidden="$(git -C "$work" rev-parse HEAD)"
    git -C "$work" push -q "$bare" "$forbidden:refs/heads/fixture-$label"
    git --git-dir="$bare" update-ref refs/heads/codex/autonomous-design-finish "$forbidden"
    if run "$forbidden" --check > "$tmp/forbidden-$label.out" 2>&1; then fail "$label change accepted"; fi
    grep -Fq "Outside approved design scope: $path" "$tmp/forbidden-$label.out" || fail "$label failed for an unexpected reason"
}
assert_forbidden migration application/database/migrations/unsafe.php
assert_forbidden dependency application/composer.json
assert_forbidden integration application/app/Modules/NdiApi/SubmitNdiOrder.php
assert_forbidden hook deploy/post-receive
assert_forbidden env application/.env
assert_forbidden other-workflow .github/workflows/deploy.yml
assert_forbidden snapshot-policy application/app/Modules/Catalog/CatalogDataMode.php
assert_forbidden snapshot-config application/config/ndi.php
assert_forbidden pricing application/app/Modules/Pricing/SellingPriceService.php
assert_forbidden admin-css application/resources/css/admin-system.css
assert_forbidden backend application/app/Modules/Catalog/ReadModel/CatalogReadService.php
assert_forbidden old-helper deploy/staging-catalog-snapshot.sh
git -C "$work" switch -qC forbidden-symlink "$allowed"
link_object="$(printf '/outside-fixture\n' | git -C "$work" hash-object -w --stdin)"
git -C "$work" update-index --cacheinfo "120000,$link_object,application/resources/css/storefront-v3.css"
git -C "$work" commit -qm forbidden-symlink
symlink_candidate="$(git -C "$work" rev-parse HEAD)"
git -C "$work" push -q "$bare" "$symlink_candidate:refs/heads/fixture-symlink"
git --git-dir="$bare" update-ref refs/heads/codex/autonomous-design-finish "$symlink_candidate"
if run "$symlink_candidate" --check > "$tmp/symlink.out" 2>&1; then fail 'symlink tree accepted'; fi
grep -Fq 'Candidate contains a symlink' "$tmp/symlink.out" || fail 'symlink failed for an unexpected reason'
unrelated="$(git --git-dir="$bare" -c user.email=filters-safety@example.test -c user.name='Filters safety' commit-tree "$allowed^{tree}" -m unrelated)"
git --git-dir="$bare" update-ref refs/heads/codex/autonomous-design-finish "$unrelated"
if run "$unrelated" --check > "$tmp/unrelated.out" 2>&1; then fail 'unrelated history accepted'; fi
grep -Fq 'not stacked on the pinned active snapshot/design release' "$tmp/unrelated.out" || fail 'ancestry failed for an unexpected reason'
git --git-dir="$bare" update-ref refs/heads/codex/autonomous-design-finish "$allowed"
[[ ! -s "$tmp/runtime.log" ]] || fail 'failed preflight invoked runtime commands'

for scenario in lock-fails config-fails candidate-env-mutated backup-fails backup-corrupt backup-missing importer-running importer-check-error health-fails revision-fails ok; do
    printf '%s\n' "$scenario" > "$tmp/scenario"
    printf '%s\n' "$previous" > "$tmp/current-target"
    : > "$tmp/runtime.log"
    if run "$allowed" --deploy > "$tmp/$scenario.out" 2>&1; then
        [[ "$scenario" == ok ]] || fail "$scenario unexpectedly activated"
        [[ "$(cat "$tmp/current-target")" != "$previous" ]] || fail 'successful release was not activated'
        activated="$(cat "$tmp/current-target")"
        [[ "$(cat "$activated/REVISION")" == "$allowed" ]] || fail 'activated SHA differs'
        cmp -s "$tmp/expected-env" "$activated/application/.env" || fail 'candidate environment was not copied byte-for-byte'
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

pass 'syntax, exact public design scope/revision/env guards, byte-identical runtime copy, clean builds, backup/importer gates and atomic rollback passed (offline mocks)'
