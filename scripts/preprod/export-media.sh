#!/usr/bin/env bash
# Read-only archive of all Yoowii persistent files accessible to the PHP service.
set -Eeuo pipefail
umask 077

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$project_root"

compose=(docker compose)
php_service="${YOOWII_PHP_SERVICE:-php}"
backup_dir="${YOOWII_BACKUP_DIR:-$project_root/backups/preprod}"

fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
require() { command -v "$1" >/dev/null 2>&1 || fail "Required command is unavailable: $1"; }
require docker
require sha256sum

"${compose[@]}" ps --status running -q "$php_service" | grep -q . \
    || fail "The local PHP service is not running. Start it explicitly, then retry."
"${compose[@]}" exec -T "$php_service" test -d /srv/sylius/public/media \
    || fail 'The Sylius media directory is unavailable. No archive was created.'

mkdir -p "$backup_dir"
timestamp="$(date -u +%Y%m%d-%H%M%S)"
archive="$backup_dir/yoowii-preprod-media-${timestamp}.tar.gz"
checksum="${archive}.sha256"
tmp_archive="${archive}.partial"
trap 'rm -f "$tmp_archive"' EXIT

# Preserve paths relative to /srv/sylius so the target can extract safely there.
# Liip Imagine derivatives and transient upload files are intentionally excluded.
"${compose[@]}" exec -T "$php_service" tar -C /srv/sylius \
    --exclude='public/media/cache' --exclude='public/media/.cache' \
    --exclude='var/private/print/tmp' --exclude='var/private/print/.tmp' \
    -czf - public/media var/private > "$tmp_archive"

[[ -s "$tmp_archive" ]] || fail 'The media archive is empty.'
tar -tzf "$tmp_archive" >/dev/null
mv "$tmp_archive" "$archive"
(cd "$backup_dir" && sha256sum "$(basename "$archive")" > "$(basename "$checksum")")

printf 'Created %s and %s.\n' "$archive" "$checksum"
