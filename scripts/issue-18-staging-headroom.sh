#!/usr/bin/env bash
set -Eeuo pipefail
umask 0027
export LC_ALL=C

: "${ISSUE18_EASY_SITE_ROOT:?ISSUE18_EASY_SITE_ROOT is required}"
: "${ISSUE18_EASY_CURRENT_RELEASE:?ISSUE18_EASY_CURRENT_RELEASE is required}"
: "${ISSUE18_EASY_ROLLBACK_RELEASE:?ISSUE18_EASY_ROLLBACK_RELEASE is required}"
: "${ISSUE18_HISTORY_SITE_ROOT:?ISSUE18_HISTORY_SITE_ROOT is required}"
: "${ISSUE18_HISTORY_CURRENT_RELEASE:?ISSUE18_HISTORY_CURRENT_RELEASE is required}"
: "${ISSUE18_HISTORY_ROLLBACK_ONE:?ISSUE18_HISTORY_ROLLBACK_ONE is required}"
: "${ISSUE18_HISTORY_ROLLBACK_TWO:?ISSUE18_HISTORY_ROLLBACK_TWO is required}"
: "${ISSUE18_TEMP_ROOT:?ISSUE18_TEMP_ROOT is required}"
: "${ISSUE18_TEMP_CANDIDATE_ONE:?ISSUE18_TEMP_CANDIDATE_ONE is required}"
: "${ISSUE18_TEMP_CANDIDATE_TWO:?ISSUE18_TEMP_CANDIDATE_TWO is required}"

readonly EASY_SITE_ROOT="${ISSUE18_EASY_SITE_ROOT%/}"
readonly EASY_RELEASES="$EASY_SITE_ROOT/releases"
readonly EASY_CURRENT="$EASY_SITE_ROOT/current"
readonly EASY_EXPECTED_CURRENT="$ISSUE18_EASY_CURRENT_RELEASE"
readonly EASY_EXPECTED_MANIFEST='36fcc2881bc4c58cf8bc7d0a4b0e1123cc9b9ee9d039f3a1d2bbe03116fbf93a'
readonly EASY_EXPECTED_COUNT=5

readonly HISTORY_SITE_ROOT="${ISSUE18_HISTORY_SITE_ROOT%/}"
readonly HISTORY_RELEASES="$HISTORY_SITE_ROOT/releases"
readonly HISTORY_CURRENT="$HISTORY_SITE_ROOT/current"
readonly HISTORY_EXPECTED_CURRENT="$ISSUE18_HISTORY_CURRENT_RELEASE"
readonly HISTORY_EXPECTED_MANIFEST='0ccf64f07a76bbe329ef68b0df599cba329406f3e363708820e86c01cf8687fa'
readonly HISTORY_EXPECTED_COUNT=87

readonly TEMP_ROOT="${ISSUE18_TEMP_ROOT%/}"

readonly -a EASY_PROTECTED=(
    "$ISSUE18_EASY_CURRENT_RELEASE"
    "$ISSUE18_EASY_ROLLBACK_RELEASE"
)

readonly -a HISTORY_PROTECTED=(
    "$ISSUE18_HISTORY_CURRENT_RELEASE"
    "$ISSUE18_HISTORY_ROLLBACK_ONE"
    "$ISSUE18_HISTORY_ROLLBACK_TWO"
)

readonly -a TEMP_DELETE=(
    "$TEMP_ROOT/$ISSUE18_TEMP_CANDIDATE_ONE"
    "$TEMP_ROOT/$ISSUE18_TEMP_CANDIDATE_TWO"
)

die() {
    printf 'issue-18: %s\n' "$*" >&2
    exit 1
}

manifest_sha() {
    local root="$1"

    find "$root" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' \
        | sort \
        | sha256sum \
        | cut -d' ' -f1
}

release_count() {
    local root="$1"

    find "$root" -mindepth 1 -maxdepth 1 -type d -printf '.' | wc -c
}

assert_release_snapshot() {
    local root="$1"
    local expected_count="$2"
    local expected_manifest="$3"
    local actual_count actual_manifest

    [[ -d "$root" && ! -L "$root" ]] || die "release root is unavailable: $root"
    actual_count="$(release_count "$root")"
    actual_manifest="$(manifest_sha "$root")"
    [[ "$actual_count" == "$expected_count" ]] || die "release count changed for $root"
    [[ "$actual_manifest" == "$expected_manifest" ]] || die "release manifest changed for $root"
}

assert_current_target() {
    local current="$1"
    local releases="$2"
    local expected_name="$3"
    local target

    [[ -L "$current" ]] || die "current is not a symlink: $current"
    target="$(readlink -f "$current")"
    [[ "$target" == "$releases/$expected_name" ]] || die "current target changed for $current"
}

assert_direct_child_directory() {
    local root="$1"
    local path="$2"
    local resolved_root resolved_path

    [[ -d "$path" && ! -L "$path" ]] || die "candidate is not a real directory: $path"
    resolved_root="$(realpath -e "$root")"
    resolved_path="$(realpath -e "$path")"
    [[ "$(dirname -- "$resolved_path")" == "$resolved_root" ]] || die "candidate escaped its allowlisted root: $path"
    [[ "$(findmnt -n -o TARGET --target "$resolved_path")" == '/' ]] || die "candidate is not on the expected root filesystem: $path"
}

assert_safe_root() {
    local root="$1"

    [[ "$root" == /* ]] || die 'allowlisted root must be absolute'
    case "$root" in
        '/'|'/var'|'/srv'|'/tmp'|'/root'|'/home'|'/usr'|'/opt')
            die 'refusing broad allowlisted root'
            ;;
    esac
}

assert_safe_temp_root() {
    case "$1" in
        '/tmp'|'/var/tmp')
            ;;
        *)
            die 'temporary root must be an operating-system temporary directory'
            ;;
    esac
}

assert_release_name() {
    [[ "$1" =~ ^[0-9A-Za-z][0-9A-Za-z._-]{5,79}$ ]] || die 'unexpected release identifier'
    [[ "$1" != *'..'* ]] || die 'release identifier contains traversal syntax'
}

assert_temp_name() {
    [[ "$1" =~ ^[0-9A-Za-z][0-9A-Za-z._-]{5,119}$ ]] || die 'unexpected temporary candidate identifier'
    [[ "$1" != *'..'* ]] || die 'temporary candidate identifier contains traversal syntax'
}

contains() {
    local needle="$1"
    shift
    local item

    for item in "$@"; do
        [[ "$item" == "$needle" ]] && return 0
    done

    return 1
}

directory_bytes() {
    du -sB1 "$1" | awk '{print $1}'
}

sum_paths() {
    local total=0
    local path

    for path in "$@"; do
        total=$((total + $(directory_bytes "$path")))
    done

    printf '%s\n' "$total"
}

declare -a history_delete_paths=()
declare -a easy_delete_paths=()
declare -a temp_delete_paths=()

preflight() {
    local name path
    local -a easy_names=()
    local -a history_names=()

    command -v findmnt >/dev/null || die 'findmnt is required'
    command -v sha256sum >/dev/null || die 'sha256sum is required'

    assert_safe_root "$EASY_SITE_ROOT"
    assert_safe_root "$HISTORY_SITE_ROOT"
    assert_safe_temp_root "$TEMP_ROOT"
    for name in "${EASY_PROTECTED[@]}" "${HISTORY_PROTECTED[@]}"; do
        assert_release_name "$name"
    done
    assert_temp_name "$ISSUE18_TEMP_CANDIDATE_ONE"
    assert_temp_name "$ISSUE18_TEMP_CANDIDATE_TWO"

    assert_release_snapshot "$EASY_RELEASES" "$EASY_EXPECTED_COUNT" "$EASY_EXPECTED_MANIFEST"
    assert_release_snapshot "$HISTORY_RELEASES" "$HISTORY_EXPECTED_COUNT" "$HISTORY_EXPECTED_MANIFEST"
    assert_current_target "$EASY_CURRENT" "$EASY_RELEASES" "$EASY_EXPECTED_CURRENT"
    assert_current_target "$HISTORY_CURRENT" "$HISTORY_RELEASES" "$HISTORY_EXPECTED_CURRENT"

    for name in "${EASY_PROTECTED[@]}"; do
        assert_direct_child_directory "$EASY_RELEASES" "$EASY_RELEASES/$name"
    done

    for name in "${HISTORY_PROTECTED[@]}"; do
        assert_direct_child_directory "$HISTORY_RELEASES" "$HISTORY_RELEASES/$name"
    done

    mapfile -t easy_names < <(find "$EASY_RELEASES" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort)
    for name in "${easy_names[@]}"; do
        assert_release_name "$name"
        contains "$name" "${EASY_PROTECTED[@]}" && continue
        path="$EASY_RELEASES/$name"
        assert_direct_child_directory "$EASY_RELEASES" "$path"
        easy_delete_paths+=("$path")
    done
    [[ "${#easy_delete_paths[@]}" -eq 3 ]] || die 'EasyTire cleanup count changed'

    mapfile -t history_names < <(find "$HISTORY_RELEASES" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort)
    for name in "${history_names[@]}"; do
        assert_release_name "$name"
        contains "$name" "${HISTORY_PROTECTED[@]}" && continue
        path="$HISTORY_RELEASES/$name"
        assert_direct_child_directory "$HISTORY_RELEASES" "$path"
        history_delete_paths+=("$path")
    done
    [[ "${#history_delete_paths[@]}" -eq 84 ]] || die 'history cleanup count changed'

    for path in "${TEMP_DELETE[@]}"; do
        if [[ -e "$path" ]]; then
            assert_direct_child_directory "$TEMP_ROOT" "$path"
            temp_delete_paths+=("$path")
        fi
    done
}

print_plan() {
    local easy_bytes history_bytes temp_bytes=0

    easy_bytes="$(sum_paths "${easy_delete_paths[@]}")"
    history_bytes="$(sum_paths "${history_delete_paths[@]}")"
    if [[ "${#temp_delete_paths[@]}" -gt 0 ]]; then
        temp_bytes="$(sum_paths "${temp_delete_paths[@]}")"
    fi

    printf 'mode=inspect\n'
    printf 'easy_release_candidates=%s bytes=%s retained=%s\n' "${#easy_delete_paths[@]}" "$easy_bytes" "${#EASY_PROTECTED[@]}"
    printf 'history_release_candidates=%s bytes=%s retained=%s\n' "${#history_delete_paths[@]}" "$history_bytes" "${#HISTORY_PROTECTED[@]}"
    printf 'temporary_candidates=%s bytes=%s\n' "${#temp_delete_paths[@]}" "$temp_bytes"
    printf 'expected_reclaim_bytes=%s\n' "$((easy_bytes + history_bytes + temp_bytes))"
    df -B1 -P /
}

remove_allowlisted_tree() {
    local root="$1"
    local path="$2"

    assert_direct_child_directory "$root" "$path"
    rm -rf --one-file-system -- "$path"
    [[ ! -e "$path" ]] || die "candidate still exists after removal: $path"
}

execute_plan() {
    local path

    [[ "$(id -u)" -eq 0 ]] || die 'execute mode requires root'
    [[ "${2:-}" == 'STAGING_MAINTENANCE_APPROVED' && "${3:-}" == 'ISSUE-18' ]] \
        || die 'execute mode requires both approval tokens'

    for path in "${temp_delete_paths[@]}"; do
        remove_allowlisted_tree "$TEMP_ROOT" "$path"
    done
    for path in "${easy_delete_paths[@]}"; do
        remove_allowlisted_tree "$EASY_RELEASES" "$path"
    done
    for path in "${history_delete_paths[@]}"; do
        remove_allowlisted_tree "$HISTORY_RELEASES" "$path"
    done

    assert_current_target "$EASY_CURRENT" "$EASY_RELEASES" "$EASY_EXPECTED_CURRENT"
    assert_current_target "$HISTORY_CURRENT" "$HISTORY_RELEASES" "$HISTORY_EXPECTED_CURRENT"
    for path in "${EASY_PROTECTED[@]}"; do
        assert_direct_child_directory "$EASY_RELEASES" "$EASY_RELEASES/$path"
    done
    for path in "${HISTORY_PROTECTED[@]}"; do
        assert_direct_child_directory "$HISTORY_RELEASES" "$HISTORY_RELEASES/$path"
    done

    printf 'mode=executed\n'
    df -B1 -P /
}

mode="${1:---inspect}"
preflight

case "$mode" in
    --inspect)
        print_plan
        ;;
    --execute)
        execute_plan "$@"
        ;;
    *)
        die 'usage: issue-18-staging-headroom.sh [--inspect|--execute STAGING_MAINTENANCE_APPROVED ISSUE-18]'
        ;;
esac
