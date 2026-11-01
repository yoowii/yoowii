#!/usr/bin/env bash
# Installed as /usr/local/sbin/yoowii-deploy-preprod, root-owned and not writable by deploy-yoowii.
# Deploys immutable application images only. Database and media volumes are never recreated/deleted.
set -Eeuo pipefail
umask 077

[[ "${EUID}" -eq 0 ]] || { echo "Must run as root" >&2; exit 1; }
[[ "$#" -eq 1 && "$1" =~ ^[a-f0-9]{40}$ ]] || { echo "Usage: $0 <40-char commit SHA>" >&2; exit 2; }
sha="$1"
tag="sha-$sha"
dir=/opt/yoowii/preprod
cd "$dir"
exec 9>/run/lock/yoowii-preprod-deploy.lock
flock -n 9 || { echo "Another preprod deployment is running" >&2; exit 3; }

compose=(docker compose --env-file "$dir/.env" -f "$dir/compose.preprod.yml")
[[ -f .env && -f compose.preprod.yml ]] || { echo "Missing compose or .env" >&2; exit 4; }
old_tag="$(sed -n 's/^YOOWII_IMAGE_TAG=//p' .env | tail -1)"
[[ "$old_tag" =~ ^sha-[a-f0-9]{40}$ ]] || { echo "Invalid existing image tag; refusing unattended deployment" >&2; exit 5; }

echo "Deploying Yoowii preprod image tag $tag (previous $old_tag)"
YOOWII_IMAGE_TAG="$tag" "${compose[@]}" config --quiet
docker pull "ghcr.io/yoowii/yoowii-php:$tag"
docker pull "ghcr.io/yoowii/yoowii-nginx:$tag"

rollback() {
  echo "Deployment failed, restoring application images $old_tag" >&2
  YOOWII_IMAGE_TAG="$old_tag" "${compose[@]}" up -d --no-deps --no-build --force-recreate php nginx || true
}
trap rollback ERR

YOOWII_IMAGE_TAG="$tag" "${compose[@]}" up -d --no-deps --no-build --force-recreate php nginx

healthy=false
for attempt in $(seq 1 24); do
  code="$(curl -sS --max-time 10 -o /dev/null -w '%{http_code}' -H 'Host: preprod.yoowii.com' http://127.0.0.1:8086/fr_FR/ || true)"
  if [[ "$code" == 200 ]]; then healthy=true; break; fi
  sleep 5
done
[[ "$healthy" == true ]] || { echo "HTTP healthcheck failed" >&2; false; }
[[ "$(YOOWII_IMAGE_TAG="$tag" "${compose[@]}" ps --status running --services | grep -Ec '^(php|nginx)$')" == 2 ]] || { echo "Application containers not running" >&2; false; }

# Atomic update preserves all secrets and file permissions.
tmp="$(mktemp "$dir/.env.XXXXXXXX")"
trap 'rm -f "$tmp"' EXIT
sed "s/^YOOWII_IMAGE_TAG=.*/YOOWII_IMAGE_TAG=$tag/" .env > "$tmp"
chown --reference=.env "$tmp"
chmod --reference=.env "$tmp"
mv -f "$tmp" .env
trap - ERR
echo "Deployment successful: $tag"
