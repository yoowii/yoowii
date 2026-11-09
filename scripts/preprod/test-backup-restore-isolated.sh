#!/usr/bin/env bash
# Synthetic CI-only restore: never uses VPS, Plesk or live customer data.
set -Eeuo pipefail
umask 077
[[ "$APP_ENV" == test && "$YOOWII_ALLOW_ISOLATED_RESTORE_TEST" == 1 && "$GITHUB_ACTIONS" == true ]] || {
  echo "Restricted to explicitly enabled GitHub Actions test job" >&2; exit 2;
}
scratch="$(mktemp -d)"
source_db=yoowii_restore_probe_source
target_db=yoowii_restore_probe_target
db() { docker run --rm --network host mysql:8.4 mysql --protocol=tcp -h127.0.0.1 -uroot "$@"; }
dump() { docker run --rm --network host mysql:8.4 mysqldump --protocol=tcp -h127.0.0.1 -uroot --no-tablespaces --skip-comments "$@"; }
created=false
cleanup() {
  if [[ "$created" == true ]]; then
    db -e "DROP DATABASE IF EXISTS $target_db; DROP DATABASE IF EXISTS $source_db;" >/dev/null 2>&1 || true
  fi
  rm -rf "$scratch"
}
trap cleanup EXIT
existing="$(db --batch --skip-column-names -e "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME IN ('$source_db', '$target_db');")"
[[ "$existing" == 0 ]] || { echo "Existing probe database: refuse to overwrite" >&2; exit 3; }
db -e "CREATE DATABASE $source_db; CREATE DATABASE $target_db;"
created=true
db -e "CREATE TABLE $source_db.restore_probe (id INT PRIMARY KEY, payload VARCHAR(80) NOT NULL);
INSERT INTO $source_db.restore_probe VALUES (1,'synthetic-one'),(2,'synthetic-two');"
dump "$source_db" > "$scratch/mysql.sql"
[[ -s "$scratch/mysql.sql" ]] || { echo "Empty MySQL backup" >&2; exit 4; }
docker run --rm -i --network host mysql:8.4 mysql --protocol=tcp -h127.0.0.1 -uroot "$target_db" < "$scratch/mysql.sql"
source_data="$(db --batch --skip-column-names -e "SELECT id,payload FROM $source_db.restore_probe ORDER BY id;")"
restored_data="$(db --batch --skip-column-names -e "SELECT id,payload FROM $target_db.restore_probe ORDER BY id;")"
[[ "$source_data" == "$restored_data" ]] || { echo "Restored rows mismatch" >&2; exit 5; }
[[ "$(printf '%s\n' "$restored_data" | wc -l)" -eq 2 ]] || exit 5
echo "PASS: synthetic MySQL 8.4 dump restored into separate CI database"
install -d -m 700 "$scratch/live" "$scratch/backup" "$scratch/restored"
printf 'APP_SECRET=ci-fake-only\nYOOWII_IMAGE_TAG=sha-test\n' > "$scratch/live/.env"
printf 'services:\n  php:\n    image: example.invalid/ci-fake\n' > "$scratch/live/compose.preprod.yml"
printf '# synthetic deployer\n' > "$scratch/live/yoowii-deploy-preprod"
chmod 600 "$scratch/live/.env"
chmod 640 "$scratch/live/compose.preprod.yml"
chmod 750 "$scratch/live/yoowii-deploy-preprod"
cp -a "$scratch/live/." "$scratch/backup/"
cp -a "$scratch/backup/." "$scratch/restored/"
for file in .env compose.preprod.yml yoowii-deploy-preprod; do
  cmp -s "$scratch/live/$file" "$scratch/restored/$file" || { echo "File mismatch" >&2; exit 6; }
  [[ "$(stat -c %a "$scratch/live/$file")" == "$(stat -c %a "$scratch/restored/$file")" ]] || { echo "Permissions mismatch" >&2; exit 6; }
done
echo "PASS: synthetic deployment configuration and permissions restored"
echo "PASS: no live volumes, providers, or real credentials accessed"
