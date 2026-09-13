#!/usr/bin/env bash
set -Eeuo pipefail
umask 0027

if [[ $# -ne 4 ]]; then
    printf 'Usage: %s <hook-target> <nginx-vhost> <expected-hook-sha256> <expected-nginx-sha256>\n' "$0" >&2
    exit 64
fi

readonly HOOK_TARGET="$1"
readonly NGINX_VHOST="$2"
readonly EXPECTED_HOOK_SHA="$3"
readonly EXPECTED_NGINX_SHA="$4"
readonly SOURCE_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
readonly SOURCE_HOOK="$SOURCE_ROOT/post-receive"

[[ "$(id -u)" -eq 0 ]] || { printf 'Run as root.\n' >&2; exit 77; }
[[ -f "$SOURCE_HOOK" && -f "$HOOK_TARGET" && -f "$NGINX_VHOST" ]] || { printf 'Required file is missing.\n' >&2; exit 66; }
[[ "$(sha256sum "$HOOK_TARGET" | awk '{print $1}')" == "$EXPECTED_HOOK_SHA" ]] || { printf 'Installed hook precondition failed.\n' >&2; exit 65; }
[[ "$(sha256sum "$NGINX_VHOST" | awk '{print $1}')" == "$EXPECTED_NGINX_SHA" ]] || { printf 'Installed nginx precondition failed.\n' >&2; exit 65; }

bash -n "$SOURCE_HOOK"
candidate="$(mktemp)"
timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
hook_backup="${HOOK_TARGET}.pre-issue44-${timestamp}"
nginx_backup="${NGINX_VHOST}.pre-issue44-${timestamp}"
trap 'rm -f -- "$candidate"' EXIT
cp --preserve=mode,ownership,timestamps -- "$NGINX_VHOST" "$candidate"

python3 - "$candidate" <<'PY'
from pathlib import Path
import sys

path = Path(sys.argv[1])
text = path.read_text(encoding="utf-8")
marker = "    location / {\n"
if text.count(marker) != 1:
    raise SystemExit("Expected exactly one application location block.")
if "location = /robots.txt" in text:
    raise SystemExit("A robots location already exists; refusing to guess ownership.")
block = (
    "    # Staging remains non-indexable across application rollbacks.\n"
    "    location = /robots.txt {\n"
    "        default_type text/plain;\n"
    "        return 200 \"User-agent: *\\nDisallow: /\\n\";\n"
    "    }\n\n"
)
path.write_text(text.replace(marker, block + marker, 1), encoding="utf-8")
PY

cp --preserve=mode,ownership,timestamps -- "$HOOK_TARGET" "$hook_backup"
cp --preserve=mode,ownership,timestamps -- "$NGINX_VHOST" "$nginx_backup"

restore_files() {
    status=$?
    trap - ERR
    set +e
    cp --preserve=mode,ownership,timestamps -- "$hook_backup" "$HOOK_TARGET"
    cp --preserve=mode,ownership,timestamps -- "$nginx_backup" "$NGINX_VHOST"
    nginx -t && systemctl reload nginx
    printf 'Guard installation failed; both files were restored.\n' >&2
    exit "$status"
}
trap restore_files ERR

install -m 755 -- "$SOURCE_HOOK" "$HOOK_TARGET"
install -m 644 -- "$candidate" "$NGINX_VHOST"
nginx -t
systemctl reload nginx
trap - ERR

printf 'hook_sha256=%s\n' "$(sha256sum "$HOOK_TARGET" | awk '{print $1}')"
printf 'nginx_sha256=%s\n' "$(sha256sum "$NGINX_VHOST" | awk '{print $1}')"
