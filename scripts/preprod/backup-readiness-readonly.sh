#!/usr/bin/env bash
# Read-only inventory of Yoowii preproduction backup prerequisites.
# Does NOT create/dump/restore a backup or inspect message/customer contents.
# Run manually as administrator after reviewing this exact version.
set -Eeuo pipefail
umask 077

dir=/opt/yoowii/preprod
[[ "${EUID}" -eq 0 && "${#}" -eq 0 ]] || {
  echo "Run as root with no arguments; no action taken" >&2
  exit 2
}
cd "$dir"
[[ -f .env && -f compose.preprod.yml && ! -L .env && ! -L compose.preprod.yml ]] || {
  echo "FAIL: protected preprod files missing or symlinked" >&2
  exit 2
}
for f in .env compose.preprod.yml; do
  owner="$(stat -c '%u' "$f")"
  mode="$(stat -c '%a' "$f")"
  [[ "$owner" == 0 ]] || { echo "FAIL: $f not root-owned"; exit 2; }
  case "$f:$mode" in
    .env:600|compose.preprod.yml:640|compose.preprod.yml:600) ;;
    *) echo "FAIL: unexpected permissions on $f"; exit 2 ;;
  esac
  echo "PASS: $f owner/mode"
done

tag="$(sed -n 's/^YOOWII_IMAGE_TAG=//p' .env | tail -n 1)"
[[ "$tag" =~ ^sha-[a-f0-9]{40}$ ]] || { echo "FAIL: release tag invalid"; exit 2; }
echo "PASS: immutable release tag format"

for service in php nginx mysql redis; do
  ids="$(docker ps -q --filter label=com.docker.compose.project=yoowii-preprod --filter "label=com.docker.compose.service=$service")" || exit 2
  [[ "$(printf '%s\n' "$ids" | grep -c . || true)" == 1 ]] || {
    echo "FAIL: expected exactly one running $service container"
    exit 2
  }
  echo "PASS: service $service running exactly once"
done

for name in mysql_data media private_assets; do
  ids="$(docker volume ls -q --filter "name=^yoowii-preprod_$name$")" || exit 2
  [[ "$ids" == "yoowii-preprod_$name" ]] || {
    echo "FAIL: persistent volume $name missing or ambiguous"; exit 2;
  }
  echo "PASS: persistent volume $name exists (contents NOT inspected)"
done

# Names/labels only: do not print credentials or the resolved Compose config.
mysql_id="$(docker ps -q --filter label=com.docker.compose.project=yoowii-preprod --filter label=com.docker.compose.service=mysql)"
# Credentials are used *inside* mysql container; not expanded on host or logged.
if docker exec "$mysql_id" sh -c '
  MYSQL_PWD="$MYSQL_PASSWORD" exec mysql -u "$MYSQL_USER" "$MYSQL_DATABASE" \
    --batch --skip-column-names --silent -e "SELECT 1"
' 2>/dev/null | grep -Fxq 1; then
  echo "PASS: Yoowii database responds to a read-only query"
else
  echo "FAIL: database read-only connectivity check failed"; exit 2
fi

[[ -f compose.preprod.worker.yml && ! -L compose.preprod.worker.yml ]] &&
  echo "INFO: worker overlay file exists" ||
  echo "INFO: worker overlay not present as ordinary file"

echo "INFO: Plesk backup existence, backup retention and isolated restore NOT validated"
echo "INFO: no backup was created; no Docker service changed; no database or volume modified"
echo "RESULT: inventory_readonly_complete; restore_go_no_go=pending"
