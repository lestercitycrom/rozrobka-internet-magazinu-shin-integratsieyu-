#!/usr/bin/env bash
# Offline fixtures only: no server connection, live database, build or deployment.
set -Eeuo pipefail
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
script="$repo_root/deploy/storefront-final-staging.sh"
tmp="$(mktemp -d)"
[[ "$tmp" == /tmp/* ]] || { printf 'Unsafe temporary directory.\n' >&2; exit 1; }
cleanup() { rm -rf -- "$tmp"; }
trap cleanup EXIT
fail() { printf 'storefront-final safety: %s\n' "$*" >&2; exit 1; }
pass() { printf 'storefront-final safety: %s\n' "$*"; }

bash -n "$script"
grep -Fq 'cp -- "$previous/application/.env" "$app/.env"' "$script" || fail 'retained environment copy missing'
grep -Fq 'cmp -s -- "$previous/application/.env" "$app/.env"' "$script" || fail 'byte-identical runtime environment guard missing'
if grep -Eq 'ENV_EDIT|file_put_contents|SNAPSHOT_ENV|sed -i' "$script"; then fail 'runtime environment editing found'; fi
if grep -Eq '^[[:space:]]*(php[[:space:]]+artisan[[:space:]]+(migrate|db:seed|queue:restart|ndi:|easytire:backup)|mysql(dump)?([[:space:]]|$)|systemctl([[:space:]]|$)|service([[:space:]]|$)|easytire:backup)' "$script"; then
    fail 'database, import, backup or service command found'
fi
if grep -Eq '^[[:space:]]*(mysqldump|pg_dump|sqlite3)([[:space:]]|$)' "$script"; then fail 'database export command found'; fi
[[ "$(grep -c 'curl --fail' "$script")" == 2 ]] || fail 'unexpected number of activation probes'
grep -Fq '"https://$HOST/up"' "$script" || fail 'health probe missing'
grep -Fq '"https://$HOST/design-revision.txt"' "$script" || fail 'revision probe missing'
if grep 'curl --fail' "$script" | grep -Ev '\$HOST/(up|design-revision\.txt)"' >/dev/null; then fail 'stateful activation probe found'; fi
grep -Fq 'export VIEW_COMPILED_PATH="$app/bootstrap/cache/views"' "$script" || fail 'release-local compiled view path missing'
grep -Fq 'export APP_CONFIG_CACHE="$app/bootstrap/cache/config.php"' "$script" || fail 'release-local config cache path missing'
grep -Fq 'env -i PATH="$PATH" bash -s' "$script" || fail 'clean build/cache environment missing'
grep -Fq 'assert_classic_unchanged' "$script" || fail 'Classic immutability guard missing'
grep -Fq 'trap on_exit EXIT' "$script" || fail 'EXIT rollback trap missing'
grep -Fq "trap 'exit 130' INT" "$script" || fail 'INT rollback path missing'
grep -Fq "trap 'exit 143' TERM" "$script" || fail 'TERM rollback path missing'
grep -Fq 'mv -Tf -- "$new_current" "$ROOT/current"' "$script" || fail 'atomic activation missing'
grep -Fq 'mv -Tf -- "$rollback_link" "$ROOT/current"' "$script" || fail 'atomic rollback missing'
last_artisan="$(grep -nE '^[[:space:]]*(env -i PATH="\$PATH" )?php artisan' "$script" | tail -n 1 | cut -d: -f1)"
storage_attach="$(grep -nF 'ln -s -- "$ROOT/shared/storage" "$app/storage"' "$script" | cut -d: -f1)"
[[ -n "$last_artisan" && -n "$storage_attach" && "$last_artisan" -lt "$storage_attach" ]] || fail 'Artisan command can run after shared storage is attached'

work="$tmp/work"
bare="$tmp/repo.git"
root="$tmp/primary"
classic_root="$tmp/classic"
previous="$root/releases/previous"
classic_previous="$classic_root/releases/previous"
shared="$root/shared/storage"
mockbin="$tmp/mockbin"
mkdir -p "$work/application/resources/css" "$work/application/resources/js" "$work/application/resources/views" \
    "$work/application/lang/nb" "$work/application/lang/ru" "$work/application/tests/E2E" \
    "$work/application/storage" "$work/application/bootstrap/cache" "$work/application/public" \
    "$work/deploy/tests" "$mockbin"
printf 'fixture\n' > "$work/application/.env.example"
printf 'fixture\n' > "$work/application/artisan"
printf '{}\n' > "$work/application/composer.lock"
printf '{}\n' > "$work/application/package-lock.json"
printf '*\n!.gitignore\n' > "$work/application/storage/.gitignore"
printf '*\n!.gitignore\n' > "$work/application/bootstrap/cache/.gitignore"
printf 'base\n' > "$work/application/resources/css/app.css"
printf 'base\n' > "$work/application/resources/css/customer-design-balance.css"
printf 'base\n' > "$work/application/resources/css/homepage-final-polish.css"
printf 'base\n' > "$work/application/resources/js/customer-card-overlays.js"
printf 'base\n' > "$work/application/resources/views/welcome.blade.php"
printf 'base\n' > "$work/application/lang/nb/project.php"
printf 'base\n' > "$work/application/lang/ru/project.php"
printf 'base\n' > "$work/application/tests/E2E/home-hero-process.spec.ts"
printf 'base\n' > "$work/application/tests/E2E/homepage-composition.spec.ts"
printf 'base\n' > "$work/application/tests/E2E/homepage-content-collections.spec.ts"
printf 'base\n' > "$work/deploy/storefront-final-staging.sh"
printf 'base\n' > "$work/deploy/tests/storefront-final-safety.sh"
git -C "$work" init -q
git -C "$work" config user.email storefront-final-safety@example.test
git -C "$work" config user.name 'Storefront final safety'
git -C "$work" add .
git -C "$work" commit -qm base
base="$(git -C "$work" rev-parse HEAD)"
git -C "$work" switch -qc codex/storefront-visual-debt
for path in \
    application/lang/nb/project.php application/lang/ru/project.php \
    application/resources/css/app.css application/resources/css/customer-design-balance.css application/resources/css/homepage-final-polish.css \
    application/resources/js/customer-card-overlays.js application/resources/views/welcome.blade.php \
    application/tests/E2E/home-hero-process.spec.ts application/tests/E2E/homepage-composition.spec.ts application/tests/E2E/homepage-content-collections.spec.ts \
    deploy/storefront-final-staging.sh deploy/tests/storefront-final-safety.sh; do
    printf 'allowed change\n' >> "$work/$path"
done
git -C "$work" add .
git -C "$work" commit -qm allowed
allowed="$(git -C "$work" rev-parse HEAD)"
git clone -q --bare "$work" "$bare"

mkdir -p "$previous/application/storage" "$previous/application/public/storage" "$previous/application/bootstrap/cache" \
    "$classic_previous/application" "$shared/app/public" "$root/releases" "$root/shared" "$classic_root/releases" \
    "$root/current" "$classic_root/current"
printf '%s\n' "$base" > "$previous/REVISION"
printf '%s\n' "$base" > "$classic_previous/REVISION"
printf 'APP_ENV=staging\n' > "$classic_previous/application/.env"
printf 'APP_ENV=staging\r\nAPP_KEY="unchanged-test-key"\r\n# retain CRLF, comment and no final newline\r\nNDI_CATALOG_MODE="snapshot" # keep spelling\r\nNDI_ORDER_ENABLED=false' > "$previous/application/.env"
cp "$previous/application/.env" "$tmp/expected-env"
printf 'APP_ENV=staging\nSHARED_ONLY=must-not-be-copied\n' > "$root/shared/.env"
printf '%s\n' "$previous" > "$tmp/current-target"
printf '%s\n' "$classic_previous" > "$tmp/classic-target"
printf '1\n' > "$tmp/counter"
printf 'ok\n' > "$tmp/scenario"
: > "$root/deploy.lock"
: > "$tmp/runtime.log"
original_env_sha="$(sha256sum "$previous/application/.env" | cut -d' ' -f1)"
shared_env_sha="$(sha256sum "$root/shared/.env" | cut -d' ' -f1)"
classic_revision_sha="$(sha256sum "$classic_previous/REVISION" | cut -d' ' -f1)"
classic_env_sha="$(sha256sum "$classic_previous/application/.env" | cut -d' ' -f1)"
classic_pointer="$classic_previous"

# Link mocks make the suite portable to Git Bash on Windows; release files and
# archives remain real so activation and rollback are exercised end-to-end.
cat > "$mockbin/readlink" <<EOF
#!/usr/bin/env bash
case "\${*: -1}" in
    "$root/current") cat "$tmp/current-target" ;;
    "$classic_root/current") cat "$tmp/classic-target" ;;
    "$previous/application/storage") printf '%s\\n' "$shared" ;;
    "$previous/application/public/storage") printf '%s\\n' "$shared/app/public" ;;
    "$root/shared/storage") printf '%s\\n' "$shared" ;;
    *) /usr/bin/readlink "\$@" ;;
esac
EOF
cat > "$mockbin/ln" <<EOF
#!/usr/bin/env bash
target="\${@: -2:1}"; destination="\${*: -1}"
case "\$destination" in
    "$root"/.current-*|"$root"/.storefront-final-rollback-*) printf '%s\\n' "\$target" > "\$destination" ;;
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
printf '20261004%06d\\n' "\$counter"
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
printf 'composer\\n' >> "$tmp/runtime.log"
EOF
cat > "$mockbin/npm" <<EOF
#!/usr/bin/env bash
[[ "\$APP_ENV" == testing && "\$DB_CONNECTION" == sqlite && ! -e storage/.mock-link ]] || exit 92
printf 'npm\\n' >> "$tmp/runtime.log"
EOF
cat > "$mockbin/php" <<EOF
#!/usr/bin/env bash
set -Eeuo pipefail
case "\${1:-}" in
    -r)
        if [[ "\$2" == *random_bytes* ]]; then printf 'base64:fixture-key\\n'; fi
        if [[ "\$(cat "$tmp/scenario")" == config-fails ]]; then exit 90; fi
        ;;
    artisan)
        printf 'artisan:%s\\n' "\$2" >> "$tmp/runtime.log"
        [[ ! -e storage/.mock-link && "\$APP_CONFIG_CACHE" == "\$PWD/bootstrap/cache/config.php" && "\$VIEW_COMPILED_PATH" == "\$PWD/bootstrap/cache/views" && -z "\${DB_CONNECTION:-}" ]] || exit 95
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
for command in mysql mysqldump pg_dump sqlite3 systemctl service; do
    printf '#!/usr/bin/env bash\nexit 99\n' > "$mockbin/$command"
done
chmod +x "$mockbin"/*

runner="$tmp/storefront-final-staging.sh"
sed \
    -e "s|readonly ROOT='/var/www/staging-easytire.bethub.fun'|readonly ROOT='$root'|" \
    -e "s|readonly CLASSIC_ROOT='/var/www/staging-classic-easytire.bethub.fun'|readonly CLASSIC_ROOT='$classic_root'|" \
    -e "s|readonly REPO='/srv/git/shop-ndi.git'|readonly REPO='$bare'|" \
    -e "s|readonly BASE='[0-9a-f]*'|readonly BASE='$base'|" \
    -e "s|readonly EXPECTED_CURRENT='[0-9a-f]*'|readonly EXPECTED_CURRENT='$base'|" \
    -e "s|readonly EXPECTED_CLASSIC='[0-9a-f]*'|readonly EXPECTED_CLASSIC='$base'|" \
    -e "s|readonly BRANCH='refs/heads/codex/storefront-visual-debt'|readonly BRANCH='refs/heads/codex/storefront-visual-debt'|" \
    -e "s|readonly COMPOSER='/usr/local/bin/composer'|readonly COMPOSER='$mockbin/composer'|" \
    "$script" > "$runner"
chmod +x "$runner"
run() { PATH="$mockbin:$PATH" DB_CONNECTION=must-not-leak NDI_ORDER_ENABLED=true "$runner" "$@"; }

run "$allowed" --check > "$tmp/check.out"
grep -Fq 'Checks passed' "$tmp/check.out" || fail 'allowed candidate rejected'
[[ ! -s "$tmp/runtime.log" ]] || fail '--check invoked runtime commands'
[[ "$(find "$root/releases" -mindepth 1 -maxdepth 1 -type d | wc -l)" -eq 1 ]] || fail '--check created a release'
[[ "$(cat "$tmp/classic-target")" == "$classic_pointer" ]] || fail '--check changed Classic current'
[[ "$(sha256sum "$classic_previous/REVISION" | cut -d' ' -f1)" == "$classic_revision_sha" ]] || fail '--check changed Classic revision'
[[ "$(sha256sum "$classic_previous/application/.env" | cut -d' ' -f1)" == "$classic_env_sha" ]] || fail '--check changed Classic environment'
if run "$base" --check > "$tmp/sha.out" 2>&1; then fail 'branch/SHA mismatch accepted'; fi
if run "$allowed" > "$tmp/mode.out" 2>&1; then fail 'omitted action accepted'; fi

assert_forbidden() {
    local label="$1" path="$2" forbidden
    git -C "$work" switch -qC "forbidden-$label" "$allowed"
    mkdir -p "$(dirname "$work/$path")"
    printf 'forbidden\n' > "$work/$path"
    git -C "$work" add "$path"
    git -C "$work" commit -qm "forbidden-$label"
    forbidden="$(git -C "$work" rev-parse HEAD)"
    git -C "$work" push -q "$bare" "$forbidden:refs/heads/fixture-$label"
    git --git-dir="$bare" update-ref refs/heads/codex/storefront-visual-debt "$forbidden"
    if run "$forbidden" --check > "$tmp/forbidden-$label.out" 2>&1; then fail "$label change accepted"; fi
    grep -Fq "Outside approved storefront scope: $path" "$tmp/forbidden-$label.out" || fail "$label failed for an unexpected reason"
}
assert_forbidden migration application/database/migrations/unsafe.php
assert_forbidden dependency application/composer.json
assert_forbidden lockfile application/package-lock.json
assert_forbidden integration application/app/Modules/NdiApi/SubmitNdiOrder.php
assert_forbidden backend application/app/Modules/Catalog/CatalogReadService.php
assert_forbidden admin application/resources/views/admin/requests/index.blade.php
assert_forbidden env application/.env
assert_forbidden hook deploy/post-receive
assert_forbidden service deploy/easytire-queue.service
git -C "$work" switch -qC forbidden-symlink "$allowed"
link_object="$(printf '/outside-fixture\n' | git -C "$work" hash-object -w --stdin)"
git -C "$work" update-index --cacheinfo "120000,$link_object,application/resources/css/app.css"
git -C "$work" commit -qm forbidden-symlink
symlink_candidate="$(git -C "$work" rev-parse HEAD)"
git -C "$work" push -q "$bare" "$symlink_candidate:refs/heads/fixture-symlink"
git --git-dir="$bare" update-ref refs/heads/codex/storefront-visual-debt "$symlink_candidate"
if run "$symlink_candidate" --check > "$tmp/symlink.out" 2>&1; then fail 'symlink tree accepted'; fi
grep -Fq 'Candidate contains a symlink' "$tmp/symlink.out" || fail 'symlink failed for an unexpected reason'
git --git-dir="$bare" update-ref refs/heads/codex/storefront-visual-debt "$allowed"
[[ ! -s "$tmp/runtime.log" ]] || fail 'failed preflight invoked runtime commands'

# Preflight must refuse changed Classic state without invoking build/runtime tools.
printf '%s\n' "$classic_root/releases/other" > "$tmp/classic-target"
if run "$allowed" --check > "$tmp/classic-pointer.out" 2>&1; then fail 'changed Classic pointer accepted'; fi
grep -Fq 'Active Classic revision changed' "$tmp/classic-pointer.out" || grep -Fq 'Current Classic release cannot be safely observed' "$tmp/classic-pointer.out" || fail 'Classic pointer failed for an unexpected reason'
printf '%s\n' "$classic_pointer" > "$tmp/classic-target"
printf 'changed\n' > "$classic_previous/REVISION"
if run "$allowed" --check > "$tmp/classic-revision.out" 2>&1; then fail 'changed Classic revision accepted'; fi
printf '%s\n' "$base" > "$classic_previous/REVISION"
[[ ! -s "$tmp/runtime.log" ]] || fail 'Classic preflight rejection invoked runtime commands'

for scenario in lock-fails config-fails importer-running importer-check-error health-fails revision-fails ok; do
    printf '%s\n' "$scenario" > "$tmp/scenario"
    printf '%s\n' "$previous" > "$tmp/current-target"
    printf '%s\n' "$classic_pointer" > "$tmp/classic-target"
    printf '%s\n' "$base" > "$classic_previous/REVISION"
    : > "$tmp/runtime.log"
    if run "$allowed" --deploy > "$tmp/$scenario.out" 2>&1; then
        [[ "$scenario" == ok ]] || fail "$scenario unexpectedly activated"
        activated="$(cat "$tmp/current-target")"
        [[ "$activated" != "$previous" && -d "$activated" ]] || fail 'successful release was not activated'
        [[ "$(cat "$activated/REVISION")" == "$allowed" ]] || fail 'activated SHA differs'
        cmp -s "$tmp/expected-env" "$activated/application/.env" || fail 'candidate environment was not copied byte-for-byte'
        [[ -f "$activated/PREVIOUS_RELEASE" ]] || fail 'previous-release marker missing'
        [[ "$(grep -c '^pointer$' "$tmp/runtime.log")" == 1 ]] || fail 'activation was not singular'
        [[ "$(grep -n '^artisan:' "$tmp/runtime.log" | tail -n 1 | cut -d: -f1)" -lt "$(grep -n '^pointer$' "$tmp/runtime.log" | cut -d: -f1)" ]] || fail 'Artisan ran after activation'
    else
        [[ "$scenario" != ok ]] || { cat "$tmp/$scenario.out"; fail 'allowed mocked deployment failed'; }
        [[ "$(cat "$tmp/current-target")" == "$previous" ]] || fail "$scenario failed to retain/restore Modern current"
        case "$scenario" in
            health-fails|revision-fails) [[ "$(grep -c '^pointer$' "$tmp/runtime.log")" == 2 ]] || fail "$scenario lacked atomic rollback" ;;
            *) if grep -q '^pointer$' "$tmp/runtime.log"; then fail "$scenario activated before verification"; fi ;;
        esac
    fi
    [[ "$(cat "$tmp/classic-target")" == "$classic_pointer" ]] || fail "$scenario changed Classic current"
    [[ "$(sha256sum "$classic_previous/REVISION" | cut -d' ' -f1)" == "$classic_revision_sha" ]] || fail "$scenario changed Classic revision"
    [[ "$(sha256sum "$classic_previous/application/.env" | cut -d' ' -f1)" == "$classic_env_sha" ]] || fail "$scenario changed Classic environment"
    [[ "$(sha256sum "$previous/application/.env" | cut -d' ' -f1)" == "$original_env_sha" && "$(sha256sum "$root/shared/.env" | cut -d' ' -f1)" == "$shared_env_sha" ]] || fail "$scenario altered retained/shared environment"
    if [[ "$scenario" == health-fails || "$scenario" == revision-fails ]]; then
        grep -Fq 'Rolled Modern back atomically' "$tmp/$scenario.out" || fail "$scenario did not report rollback"
    fi
    if grep -Eq '/var/www/|/srv/git/|/tmp/' "$tmp/$scenario.out"; then fail "$scenario log exposed a server/fixture path"; fi
done

pass 'syntax, exact scope guards, no database/import/backup/service commands, inert preflight, isolated build/cache, byte-identical env, Classic immutability and atomic rollback passed (offline mocks)'
