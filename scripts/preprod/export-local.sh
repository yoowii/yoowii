#!/usr/bin/env bash
# Read-only logical backup of the local Docker MySQL database.
set -Eeuo pipefail
umask 077

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$project_root"

compose=(docker compose)
mysql_service="${YOOWII_MYSQL_SERVICE:-mysql}"
backup_dir="${YOOWII_BACKUP_DIR:-$project_root/backups/preprod}"

fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
require() { command -v "$1" >/dev/null 2>&1 || fail "Required command is unavailable: $1"; }
require docker
require gzip
require sha256sum

"${compose[@]}" ps --status running -q "$mysql_service" | grep -q . \
    || fail "The local MySQL service is not running. Start it explicitly, then retry."
"${compose[@]}" exec -T "$mysql_service" mysqladmin -uroot ping --silent \
    || fail "Local MySQL is not reachable as root. No backup was created."

database="${YOOWII_DB_NAME:-}"
if [[ -z "$database" ]]; then
    mapfile -t candidates < <("${compose[@]}" exec -T "$mysql_service" mysql -uroot -Nse \
        "SELECT schema_name FROM information_schema.schemata WHERE schema_name LIKE 'sylius\\_%' ORDER BY schema_name")
    [[ "${#candidates[@]}" -eq 1 ]] \
        || fail "Unable to identify one Sylius database; set YOOWII_DB_NAME explicitly."
    database="${candidates[0]}"
fi
[[ "$database" =~ ^[A-Za-z0-9_]+$ ]] || fail 'YOOWII_DB_NAME may only contain letters, digits and underscores.'

mkdir -p "$backup_dir"
timestamp="$(date -u +%Y%m%d-%H%M%S)"
archive="$backup_dir/yoowii-preprod-${timestamp}.sql.gz"
checksum="${archive}.sha256"
tmp_archive="${archive}.partial"
trap 'rm -f "$tmp_archive"' EXIT

# Do not use --databases: the target is intentionally created by compose and the
# restore procedure refuses a non-empty target before importing this schema/data dump.
"${compose[@]}" exec -T "$mysql_service" mysqldump \
    -uroot --single-transaction --quick --routines --events --triggers \
    --default-character-set=utf8mb4 --set-gtid-purged=OFF "$database" \
    | gzip -9 > "$tmp_archive"

[[ -s "$tmp_archive" ]] || fail 'The SQL archive is empty.'
gzip -t "$tmp_archive"
mv "$tmp_archive" "$archive"
(cd "$backup_dir" && sha256sum "$(basename "$archive")" > "$(basename "$checksum")")

printf 'Created %s and %s for database %s.\n' "$archive" "$checksum" "$database"
