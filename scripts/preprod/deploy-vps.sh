#!/usr/bin/env bash
# Installed as root-owned /usr/local/sbin/yoowii-deploy-preprod.
# Only application images are recreated. Never modifies database or media volumes.
set -Eeuo pipefail
umask 077

[[ "${EUID}" -eq 0 ]] || { echo "Must run as root" >&2; exit 1; }
[[ "$#" -eq 1 && "$1" =~ ^[a-f0-9]{40}$ ]] || { echo "Usage: $0 <40-character commit SHA>" >&2; exit 2; }
tag="sha-$1"
dir=/opt/yoowii/preprod
cd "$dir"
exec 9>/run/lock/yoowii-preprod-deploy.lock
flock -n 9 || { echo "Another preprod deployment is running" >&2; exit 3; }

compose=(docker compose --env-file "$dir/.env" -f "$dir/compose.preprod.yml")
[[ -f .env && -f compose.preprod.yml ]] || { echo "Missing compose or .env" >&2; exit 4; }
old_tag="$(sed -n 's/^YOOWII_IMAGE_TAG=//p' .env | tail -1)"
[[ "$old_tag" =~ ^sha-[a-f0-9]{40}$ ]] || { echo "Invalid existing image tag" >&2; exit 5; }

# Mimic the public HTTPS proxy headers. The locale may otherwise redirect to /en_US/.
healthcheck() {
  local response attempt
  for attempt in $(seq 1 24); do
    response="$(curl --silent --show-error --connect-timeout 3 --max-time 20 \
      -o /dev/null -w '%{http_code}' \
      -H 'Host: preprod.yoowii.com' \
      -H 'X-Forwarded-Proto: https' \
      -H 'X-Forwarded-Host: preprod.yoowii.com' \
      -H 'X-Forwarded-Port: 443' \
      http://127.0.0.1:8086/fr_FR/ 2>&1)" || response="curl failed: $response"
    echo "Healthcheck $attempt/24: $response"
    if [[ "$response" == 200 ]]; then return 0; fi
    sleep 5
  done
  return 1
}

deployed=false
rollback() {
  local exit_code="$?"
  trap - ERR
  if [[ "$deployed" == true ]]; then
    echo "Deployment failed (exit $exit_code), collecting diagnostics before rollback" >&2
    "${compose[@]}" ps >&2 || true
    "${compose[@]}" logs --tail=80 php nginx >&2 || true
    echo "Restoring previous application images $old_tag" >&2
    if YOOWII_IMAGE_TAG="$old_tag" "${compose[@]}" up -d --no-deps --no-build --force-recreate php nginx; then
      if healthcheck; then
        echo "Rollback application healthcheck passed" >&2
      else
        echo "CRITICAL: rollback completed but application healthcheck failed" >&2
      fi
    else
      echo "CRITICAL: rollback container recreation failed" >&2
    fi
  fi
  exit "$exit_code"
}
trap rollback ERR

echo "Deploying Yoowii preprod $tag (previous: $old_tag)"
YOOWII_IMAGE_TAG="$tag" "${compose[@]}" config --quiet
docker pull "ghcr.io/yoowii/yoowii-php:$tag"
docker pull "ghcr.io/yoowii/yoowii-nginx:$tag"

# Do not touch MySQL/Redis or the named volumes.
deployed=true
YOOWII_IMAGE_TAG="$tag" "${compose[@]}" up -d --no-deps --no-build --force-recreate php nginx
healthcheck || { echo "HTTP healthcheck failed for $tag" >&2; false; }
[[ "$(YOOWII_IMAGE_TAG="$tag" "${compose[@]}" ps --status running --services | grep -Ec '^(php|nginx)$')" == 2 ]] \
  || { echo "Application containers are not both running" >&2; false; }

# Persist tag only after successful checks. Preserve secrets and metadata in .env.
tmp="$(mktemp "$dir/.env.XXXXXXXX")"
trap 'rm -f "$tmp"' EXIT
sed "s/^YOOWII_IMAGE_TAG=.*/YOOWII_IMAGE_TAG=$tag/" .env > "$tmp"
chown --reference=.env "$tmp"
chmod --reference=.env "$tmp"
mv -f "$tmp" .env
trap - ERR
echo "Deployment successful: $tag"
