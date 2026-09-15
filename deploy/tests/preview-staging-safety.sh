#!/usr/bin/env bash
set -Eeuo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
script="$repo_root/deploy/preview-staging.sh"
tmp="$(mktemp -d)"
[[ "$tmp" == /tmp/* ]] || { printf 'Unsafe temporary directory: %s\n' "$tmp" >&2; exit 1; }
cleanup() { rm -rf -- "$tmp"; }
trap cleanup EXIT

fail() { printf 'preview safety: %s\n' "$*" >&2; exit 1; }
pass() { printf 'preview safety: %s\n' "$*"; }

bash -n "$script"

if grep -Eq '^[[:space:]]*(php[[:space:]]+artisan[[:space:]]+(migrate|db:seed|queue:restart)|mysql(dump)?|systemctl|service)[[:space:]]' "$script"; then
    fail 'database mutation, worker restart or service control command found'
fi

last_artisan="$(grep -nE '^[[:space:]]*php[[:space:]]+artisan[[:space:]]' "$script" | tail -n 1 | cut -d: -f1)"
storage_attach="$(grep -nF 'ln -s -- "$ROOT/shared/storage" "$app/storage"' "$script" | cut -d: -f1)"
[[ -n "$last_artisan" && -n "$storage_attach" && "$last_artisan" -lt "$storage_attach" ]] \
    || fail 'an Artisan command can run after shared storage is attached'
grep -Fq 'export VIEW_COMPILED_PATH="$app/bootstrap/cache/views"' "$script" \
    || fail 'release-local compiled view path is missing'
grep -Fq 'mv -Tf -- "$new_current" "$ROOT/current"' "$script" \
    || fail 'atomic current-pointer replacement is missing'
grep -Fq 'trap on_exit EXIT' "$script" \
    || fail 'EXIT rollback trap is missing'
grep -Fq "trap 'exit 130' INT" "$script" \
    || fail 'INT rollback path is missing'
grep -Fq "trap 'exit 143' TERM" "$script" \
    || fail 'TERM rollback path is missing'

work="$tmp/work"
bare="$tmp/repo.git"
root="$tmp/root"
mkdir -p \
    "$work/application/resources/css" \
    "$work/application/resources/views/admin/booking-calendar" \
    "$work/application/resources/views/admin/clients" \
    "$work/application/resources/views/admin/partials" \
    "$work/application/resources/views/admin/requests" \
    "$work/application/storage" \
    "$work/application/bootstrap/cache" \
    "$work/application/public"
printf 'base\n' > "$work/application/resources/css/storefront-v3.css"
printf 'base\n' > "$work/application/resources/css/admin-system.css"
for file in \
    application/resources/views/admin/booking-calendar/blackout-form.blade.php \
    application/resources/views/admin/booking-calendar/interval-form.blade.php \
    application/resources/views/admin/clients/form.blade.php \
    application/resources/views/admin/clients/show.blade.php \
    application/resources/views/admin/partials/navigation.blade.php \
    application/resources/views/admin/requests/day.blade.php \
    application/resources/views/admin/requests/form.blade.php \
    application/resources/views/admin/requests/index.blade.php \
    application/resources/views/admin/requests/show.blade.php; do
    printf 'base\n' > "$work/$file"
done
printf 'base\n' > "$work/application/vite.config.js"
printf '*\n!.gitignore\n' > "$work/application/storage/.gitignore"
printf '*\n!.gitignore\n' > "$work/application/bootstrap/cache/.gitignore"
git -C "$work" init -q
git -C "$work" config user.email preview-safety@example.test
git -C "$work" config user.name 'Preview safety'
git -C "$work" add .
git -C "$work" commit -qm base
base="$(git -C "$work" rev-parse HEAD)"
git -C "$work" switch -qc codex/issue-44-design-v2
printf 'allowed\n' >> "$work/application/resources/css/storefront-v3.css"
printf 'allowed\n' >> "$work/application/resources/css/admin-system.css"
for file in \
    application/resources/views/admin/booking-calendar/blackout-form.blade.php \
    application/resources/views/admin/booking-calendar/interval-form.blade.php \
    application/resources/views/admin/clients/form.blade.php \
    application/resources/views/admin/clients/show.blade.php \
    application/resources/views/admin/partials/navigation.blade.php \
    application/resources/views/admin/requests/day.blade.php \
    application/resources/views/admin/requests/form.blade.php \
    application/resources/views/admin/requests/index.blade.php \
    application/resources/views/admin/requests/show.blade.php; do
    printf 'allowed\n' >> "$work/$file"
done
printf 'allowed\n' >> "$work/application/vite.config.js"
git -C "$work" commit -qam allowed
allowed="$(git -C "$work" rev-parse HEAD)"
git clone -q --bare "$work" "$bare"

previous="$root/releases/accepted"
shared="$root/shared/storage"
mkdir -p "$previous/application/storage" "$previous/application/public/storage" "$shared/app/public"
printf '%s\n' "$base" > "$previous/REVISION"
printf 'APP_ENV=staging\n' > "$previous/application/.env"
mkdir -p "$root/current"

mockbin="$tmp/mockbin"
mkdir -p "$mockbin"
cat > "$mockbin/readlink" <<EOF
#!/usr/bin/env bash
case "\${*: -1}" in
    "$root/current") printf '%s\\n' "$previous" ;;
    "$previous/application/storage") printf '%s\\n' "$shared" ;;
    "$previous/application/public/storage") printf '%s\\n' "$shared/app/public" ;;
    *) /usr/bin/readlink "\$@" ;;
esac
EOF
cat > "$mockbin/forbidden-runtime-command" <<EOF
#!/usr/bin/env bash
printf '%s\\n' "\$(basename "\$0") \$*" >> "$tmp/runtime-command.log"
exit 97
EOF
chmod +x "$mockbin/readlink" "$mockbin/forbidden-runtime-command"
for command in php npm composer curl mysql mysqldump systemctl service; do
    cp "$mockbin/forbidden-runtime-command" "$mockbin/$command"
done

runner="$tmp/preview-staging.sh"
sed \
    -e "s|readonly ROOT='/var/www/staging-easytire.bethub.fun'|readonly ROOT='$root'|" \
    -e "s|readonly REPO='/srv/git/shop-ndi.git'|readonly REPO='$bare'|" \
    -e "s|readonly BASE='[0-9a-f]*'|readonly BASE='$base'|" \
    -e "s|readonly EXPECTED_CURRENT='[0-9a-f]*'|readonly EXPECTED_CURRENT='$base'|" \
    "$script" > "$runner"
chmod +x "$runner"

git --git-dir="$bare" update-ref refs/heads/codex/issue-44-design-v2 "$allowed"
PATH="$mockbin:$PATH" "$runner" "$allowed" --check > "$tmp/allowed.out"
grep -Fq 'Checks passed' "$tmp/allowed.out" || fail 'allowed presentation candidate did not pass --check'
[[ ! -s "$tmp/runtime-command.log" ]] || fail '--check invoked a forbidden runtime command'

mkdir -p "$work/application/app"
printf '<?php // forbidden backend change\n' > "$work/application/app/Unsafe.php"
git -C "$work" add application/app/Unsafe.php
git -C "$work" commit -qm forbidden-backend
forbidden="$(git -C "$work" rev-parse HEAD)"
git -C "$work" push -q "$bare" "$forbidden":refs/heads/codex/issue-44-design-v2
if PATH="$mockbin:$PATH" "$runner" "$forbidden" --check > "$tmp/forbidden.out" 2>&1; then
    fail 'backend change unexpectedly passed the presentation-only guard'
fi
grep -Fq 'Outside approved preview scope: application/app/Unsafe.php' "$tmp/forbidden.out" \
    || fail 'backend guard failed without the expected fail-closed reason'
[[ ! -s "$tmp/runtime-command.log" ]] || fail 'guard failure invoked a forbidden runtime command'

pass 'syntax, exact-path scope, command boundary, isolated-cache ordering, atomic pointer hooks and fail-closed guards passed'
