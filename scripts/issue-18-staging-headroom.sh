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
: "${ISSUE18_EASY_UID:?ISSUE18_EASY_UID is required}"
: "${ISSUE18_EASY_GID:?ISSUE18_EASY_GID is required}"
: "${ISSUE18_HISTORY_UID:?ISSUE18_HISTORY_UID is required}"
: "${ISSUE18_HISTORY_GID:?ISSUE18_HISTORY_GID is required}"
: "${ISSUE18_TEMP_UID:?ISSUE18_TEMP_UID is required}"
: "${ISSUE18_TEMP_GID:?ISSUE18_TEMP_GID is required}"
: "${ISSUE18_EASY_ROOT_MODE:?ISSUE18_EASY_ROOT_MODE is required}"
: "${ISSUE18_EASY_RELEASE_MODE:?ISSUE18_EASY_RELEASE_MODE is required}"
: "${ISSUE18_HISTORY_ROOT_MODE:?ISSUE18_HISTORY_ROOT_MODE is required}"
: "${ISSUE18_HISTORY_RELEASE_MODE:?ISSUE18_HISTORY_RELEASE_MODE is required}"
: "${ISSUE18_TEMP_ROOT_MODE:?ISSUE18_TEMP_ROOT_MODE is required}"
: "${ISSUE18_TEMP_CANDIDATE_MODE:?ISSUE18_TEMP_CANDIDATE_MODE is required}"

readonly EASY_SITE_ROOT="${ISSUE18_EASY_SITE_ROOT%/}"
readonly EASY_RELEASES="$EASY_SITE_ROOT/releases"
readonly EASY_CURRENT="$EASY_SITE_ROOT/current"
readonly EASY_EXPECTED_CURRENT="$ISSUE18_EASY_CURRENT_RELEASE"
readonly EASY_EXPECTED_MANIFEST='36fcc2881bc4c58cf8bc7d0a4b0e1123cc9b9ee9d039f3a1d2bbe03116fbf93a'
readonly EASY_EXPECTED_METADATA='3a3e24e6936b2166f4c880c5943ed0af1ec5bd97f9ff7376818f7ae42f6c1a19'
readonly EASY_EXPECTED_COUNT=5

readonly HISTORY_SITE_ROOT="${ISSUE18_HISTORY_SITE_ROOT%/}"
readonly HISTORY_RELEASES="$HISTORY_SITE_ROOT/releases"
readonly HISTORY_CURRENT="$HISTORY_SITE_ROOT/current"
readonly HISTORY_EXPECTED_CURRENT="$ISSUE18_HISTORY_CURRENT_RELEASE"
readonly HISTORY_EXPECTED_MANIFEST='0ccf64f07a76bbe329ef68b0df599cba329406f3e363708820e86c01cf8687fa'
readonly HISTORY_EXPECTED_METADATA='357fab01046057b596418622ac78da3a43700a0b366f5e7c373e7313fb986568'
readonly HISTORY_EXPECTED_COUNT=87

readonly TEMP_ROOT="${ISSUE18_TEMP_ROOT%/}"
readonly TEMP_EXPECTED_METADATA='699d2dd753c88123d969b485c865f0983af943944996186956f559a2dbeb6bb5'
readonly EXPECTED_PLAN_DIGEST='ae5ce336aa6902b11c8328ce1fa8844fef34538eb8a5dd172f1a8e5b8304289e'

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

    find "$root" -mindepth 1 -maxdepth 1 -printf '%f\n' \
        | sort \
        | sha256sum \
        | cut -d' ' -f1
}

metadata_line() {
    local name="$1"
    local path="$2"
    local bytes mtime uid gid mode

    bytes="$(du -sB1 "$path" | awk '{print $1}')"
    IFS='|' read -r mtime uid gid mode < <(stat -c '%Y|%u|%g|%a' "$path")
    printf '%s|%s|%s|%s|%s|%s\n' "$name" "$bytes" "$mtime" "$uid" "$gid" "$mode"
}

release_metadata_digest() {
    local root="$1"
    local name
    local root_mtime root_uid root_gid root_mode

    {
        IFS='|' read -r root_mtime root_uid root_gid root_mode < <(stat -c '%Y|%u|%g|%a' "$root")
        printf '@root|-|%s|%s|%s|%s\n' "$root_mtime" "$root_uid" "$root_gid" "$root_mode"
        while IFS= read -r name; do
            metadata_line "$name" "$root/$name"
        done < <(find "$root" -mindepth 1 -maxdepth 1 -printf '%f\n' | sort)
    } | sha256sum | cut -d' ' -f1
}

temp_metadata_digest() {
    local root_uid root_gid root_mode

    {
        IFS='|' read -r root_uid root_gid root_mode < <(stat -c '%u|%g|%a' "$TEMP_ROOT")
        printf '@root|-|-|%s|%s|%s\n' "$root_uid" "$root_gid" "$root_mode"
        metadata_line "$ISSUE18_TEMP_CANDIDATE_ONE" "$TEMP_ROOT/$ISSUE18_TEMP_CANDIDATE_ONE"
        metadata_line "$ISSUE18_TEMP_CANDIDATE_TWO" "$TEMP_ROOT/$ISSUE18_TEMP_CANDIDATE_TWO"
    } | sha256sum | cut -d' ' -f1
}

host_marker() {
    {
        printf 'hostname=%s\n' "$(hostname -f 2>/dev/null || hostname)"
        printf 'machine_id=%s\n' "$(tr -d '\r\n' </etc/machine-id)"
    } | sha256sum | cut -d' ' -f1
}

sealed_plan_digest() {
    local marker="$1"

    {
        printf 'version=issue-18-v3\n'
        printf 'host_marker=%s\n' "$marker"
        printf 'easy_site_root=%s\n' "$EASY_SITE_ROOT"
        printf 'easy_current=%s\n' "$ISSUE18_EASY_CURRENT_RELEASE"
        printf 'easy_rollback=%s\n' "$ISSUE18_EASY_ROLLBACK_RELEASE"
        printf 'history_site_root=%s\n' "$HISTORY_SITE_ROOT"
        printf 'history_current=%s\n' "$ISSUE18_HISTORY_CURRENT_RELEASE"
        printf 'history_rollback_one=%s\n' "$ISSUE18_HISTORY_ROLLBACK_ONE"
        printf 'history_rollback_two=%s\n' "$ISSUE18_HISTORY_ROLLBACK_TWO"
        printf 'temp_root=%s\n' "$TEMP_ROOT"
        printf 'temp_candidate_one=%s\n' "$ISSUE18_TEMP_CANDIDATE_ONE"
        printf 'temp_candidate_two=%s\n' "$ISSUE18_TEMP_CANDIDATE_TWO"
        printf 'easy_uid=%s\n' "$ISSUE18_EASY_UID"
        printf 'easy_gid=%s\n' "$ISSUE18_EASY_GID"
        printf 'history_uid=%s\n' "$ISSUE18_HISTORY_UID"
        printf 'history_gid=%s\n' "$ISSUE18_HISTORY_GID"
        printf 'temp_uid=%s\n' "$ISSUE18_TEMP_UID"
        printf 'temp_gid=%s\n' "$ISSUE18_TEMP_GID"
        printf 'easy_root_mode=%s\n' "$ISSUE18_EASY_ROOT_MODE"
        printf 'easy_release_mode=%s\n' "$ISSUE18_EASY_RELEASE_MODE"
        printf 'history_root_mode=%s\n' "$ISSUE18_HISTORY_ROOT_MODE"
        printf 'history_release_mode=%s\n' "$ISSUE18_HISTORY_RELEASE_MODE"
        printf 'temp_root_mode=%s\n' "$ISSUE18_TEMP_ROOT_MODE"
        printf 'temp_candidate_mode=%s\n' "$ISSUE18_TEMP_CANDIDATE_MODE"
        printf 'easy_name_manifest=%s\n' "$EASY_EXPECTED_MANIFEST"
        printf 'history_name_manifest=%s\n' "$HISTORY_EXPECTED_MANIFEST"
        printf 'easy_metadata_manifest=%s\n' "$EASY_EXPECTED_METADATA"
        printf 'history_metadata_manifest=%s\n' "$HISTORY_EXPECTED_METADATA"
        printf 'temp_metadata_manifest=%s\n' "$TEMP_EXPECTED_METADATA"
    } | sha256sum | cut -d' ' -f1
}

release_count() {
    local root="$1"

    find "$root" -mindepth 1 -maxdepth 1 -printf '.' | wc -c
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

assert_numeric_plan_value() {
    [[ "$1" =~ ^[0-9]+$ ]] || die 'ownership value is not numeric'
}

assert_mode_plan_value() {
    [[ "$1" =~ ^[0-7]{3,4}$ ]] || die 'mode value is not octal'
}

assert_expected_owner_mode() {
    local path="$1"
    local expected_uid="$2"
    local expected_gid="$3"
    local expected_mode="$4"
    local allow_sticky="${5:-no}"
    local actual_uid actual_gid actual_mode numeric_mode

    IFS='|' read -r actual_uid actual_gid actual_mode < <(stat -c '%u|%g|%a' "$path")
    [[ "$actual_uid" == "$expected_uid" ]] || die 'candidate ownership changed'
    [[ "$actual_gid" == "$expected_gid" ]] || die 'candidate group changed'
    [[ "$actual_mode" == "$expected_mode" ]] || die 'candidate permissions changed'

    numeric_mode=$((8#$actual_mode))
    if [[ "$allow_sticky" == 'yes' ]]; then
        (( (numeric_mode & 01000) != 0 )) || die 'temporary root lacks sticky bit'
    else
        (( (numeric_mode & 00022) == 0 )) || die 'candidate is group/world writable'
    fi
}

assert_metadata_snapshot() {
    local easy_actual history_actual temp_actual

    easy_actual="$(release_metadata_digest "$EASY_RELEASES")"
    history_actual="$(release_metadata_digest "$HISTORY_RELEASES")"
    temp_actual="$(temp_metadata_digest)"

    [[ "$easy_actual" == "$EASY_EXPECTED_METADATA" ]] || die 'EasyTire release metadata changed'
    [[ "$history_actual" == "$HISTORY_EXPECTED_METADATA" ]] || die 'shared release metadata changed'
    [[ "$temp_actual" == "$TEMP_EXPECTED_METADATA" ]] || die 'temporary candidate metadata changed'
}

assert_sealed_plan() {
    local marker actual

    marker="$(host_marker)"
    actual="$(sealed_plan_digest "$marker")"
    [[ "$actual" == "$EXPECTED_PLAN_DIGEST" ]] || die 'private execution plan or staging host marker differs from the reviewed seal'
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
    for name in \
        "$ISSUE18_EASY_UID" "$ISSUE18_EASY_GID" \
        "$ISSUE18_HISTORY_UID" "$ISSUE18_HISTORY_GID" \
        "$ISSUE18_TEMP_UID" "$ISSUE18_TEMP_GID"; do
        assert_numeric_plan_value "$name"
    done
    for name in \
        "$ISSUE18_EASY_ROOT_MODE" "$ISSUE18_EASY_RELEASE_MODE" \
        "$ISSUE18_HISTORY_ROOT_MODE" "$ISSUE18_HISTORY_RELEASE_MODE" \
        "$ISSUE18_TEMP_ROOT_MODE" "$ISSUE18_TEMP_CANDIDATE_MODE"; do
        assert_mode_plan_value "$name"
    done
    for name in "${EASY_PROTECTED[@]}" "${HISTORY_PROTECTED[@]}"; do
        assert_release_name "$name"
    done
    assert_temp_name "$ISSUE18_TEMP_CANDIDATE_ONE"
    assert_temp_name "$ISSUE18_TEMP_CANDIDATE_TWO"

    assert_sealed_plan

    assert_release_snapshot "$EASY_RELEASES" "$EASY_EXPECTED_COUNT" "$EASY_EXPECTED_MANIFEST"
    assert_release_snapshot "$HISTORY_RELEASES" "$HISTORY_EXPECTED_COUNT" "$HISTORY_EXPECTED_MANIFEST"
    assert_metadata_snapshot
    assert_current_target "$EASY_CURRENT" "$EASY_RELEASES" "$EASY_EXPECTED_CURRENT"
    assert_current_target "$HISTORY_CURRENT" "$HISTORY_RELEASES" "$HISTORY_EXPECTED_CURRENT"
    assert_expected_owner_mode "$EASY_RELEASES" "$ISSUE18_EASY_UID" "$ISSUE18_EASY_GID" "$ISSUE18_EASY_ROOT_MODE"
    assert_expected_owner_mode "$HISTORY_RELEASES" "$ISSUE18_HISTORY_UID" "$ISSUE18_HISTORY_GID" "$ISSUE18_HISTORY_ROOT_MODE"
    assert_expected_owner_mode "$TEMP_ROOT" "$ISSUE18_TEMP_UID" "$ISSUE18_TEMP_GID" "$ISSUE18_TEMP_ROOT_MODE" yes

    for name in "${EASY_PROTECTED[@]}"; do
        assert_direct_child_directory "$EASY_RELEASES" "$EASY_RELEASES/$name"
        assert_expected_owner_mode "$EASY_RELEASES/$name" "$ISSUE18_EASY_UID" "$ISSUE18_EASY_GID" "$ISSUE18_EASY_RELEASE_MODE"
    done

    for name in "${HISTORY_PROTECTED[@]}"; do
        assert_direct_child_directory "$HISTORY_RELEASES" "$HISTORY_RELEASES/$name"
        assert_expected_owner_mode "$HISTORY_RELEASES/$name" "$ISSUE18_HISTORY_UID" "$ISSUE18_HISTORY_GID" "$ISSUE18_HISTORY_RELEASE_MODE"
    done

    mapfile -t easy_names < <(find "$EASY_RELEASES" -mindepth 1 -maxdepth 1 -printf '%f\n' | sort)
    for name in "${easy_names[@]}"; do
        assert_release_name "$name"
        contains "$name" "${EASY_PROTECTED[@]}" && continue
        path="$EASY_RELEASES/$name"
        assert_direct_child_directory "$EASY_RELEASES" "$path"
        assert_expected_owner_mode "$path" "$ISSUE18_EASY_UID" "$ISSUE18_EASY_GID" "$ISSUE18_EASY_RELEASE_MODE"
        easy_delete_paths+=("$path")
    done
    [[ "${#easy_delete_paths[@]}" -eq 3 ]] || die 'EasyTire cleanup count changed'

    mapfile -t history_names < <(find "$HISTORY_RELEASES" -mindepth 1 -maxdepth 1 -printf '%f\n' | sort)
    for name in "${history_names[@]}"; do
        assert_release_name "$name"
        contains "$name" "${HISTORY_PROTECTED[@]}" && continue
        path="$HISTORY_RELEASES/$name"
        assert_direct_child_directory "$HISTORY_RELEASES" "$path"
        assert_expected_owner_mode "$path" "$ISSUE18_HISTORY_UID" "$ISSUE18_HISTORY_GID" "$ISSUE18_HISTORY_RELEASE_MODE"
        history_delete_paths+=("$path")
    done
    [[ "${#history_delete_paths[@]}" -eq 84 ]] || die 'history cleanup count changed'

    for path in "${TEMP_DELETE[@]}"; do
        if [[ -e "$path" ]]; then
            assert_direct_child_directory "$TEMP_ROOT" "$path"
            assert_expected_owner_mode "$path" "$ISSUE18_TEMP_UID" "$ISSUE18_TEMP_GID" "$ISSUE18_TEMP_CANDIDATE_MODE"
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
