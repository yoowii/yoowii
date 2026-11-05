#!/usr/bin/env bash
# Install root-owned at /usr/local/sbin/yoowii-deploy-preprod.
# Requires exactly one pinned commit SHA and its Compose file on stdin.
# Only php/nginx are recreated; no migrations, database or volume deletion.
set -Eeuo pipefail
umask 077

[[ "${EUID}" -eq 0 ]] || { echo "Must run as root" >&2; exit 1; }
[[ "$#" -eq 1 && "$1" =~ ^[a-f0-9]{40}$ ]] || { echo "Usage: $0 <40-character commit SHA> < compose.preprod.yml" >&2; exit 2; }
tag="sha-$1"
dir=/opt/yoowii/preprod
cd "$dir"
exec 9>/run/lock/yoowii-preprod-deploy.lock
flock -n 9 || { echo "Another preprod deployment is running" >&2; exit 3; }

[[ -f .env && -f compose.preprod.yml ]] || { echo "Missing Compose or .env" >&2; exit 4; }
old_tag="$(sed -n 's/^YOOWII_IMAGE_TAG=//p' .env | tail -1)"
[[ "$old_tag" =~ ^sha-[a-f0-9]{40}$ ]] || { echo "Invalid existing image tag" >&2; exit 5; }

staged="$(mktemp "$dir/.compose.preprod.staged.XXXXXXXX")"
backup="$(mktemp "$dir/.compose.preprod.previous.XXXXXXXX")"
env_tmp=""
cleanup() { rm -f "$staged" "$backup"; [[ -z "$env_tmp" ]] || rm -f "$env_tmp"; }
trap cleanup EXIT

# CI sends the committed Compose via SSH stdin; never read untrusted server paths.
cat > "$staged"
[[ -s "$staged" && "$(wc -c < "$staged")" -le 131072 ]] || { echo "Invalid or oversized Compose payload" >&2; exit 6; }
cp -p compose.preprod.yml "$backup"
chown --reference=compose.preprod.yml "$staged"
chmod --reference=compose.preprod.yml "$staged"

# Validate before touching the active Compose, images or app containers.
YOOWII_IMAGE_TAG="$tag" docker compose --env-file "$dir/.env" -f "$staged" config --quiet
for service in php nginx; do
  YOOWII_IMAGE_TAG="$tag" docker compose --env-file "$dir/.env" -f "$staged" config --services | grep -Fxq "$service" || {
    echo "Required service absent: $service" >&2; exit 7;
  }
done

# This pipeline only recreates php/nginx. Reject changes requiring a separate
# infrastructure rollout, including edits to mounts and published ports.
# Compare normalized Compose models using the same image tag and .env.
old_model="$(mktemp "$dir/.compose.preprod.old-json.XXXXXXXX")"
new_model="$(mktemp "$dir/.compose.preprod.new-json.XXXXXXXX")"
cleanup() {
  rm -f "$staged" "$backup" "$old_model" "$new_model"
  [[ -z "$env_tmp" ]] || rm -f "$env_tmp"
}
YOOWII_IMAGE_TAG="$tag" docker compose --env-file "$dir/.env" -f "$dir/compose.preprod.yml" config --format json > "$old_model"
YOOWII_IMAGE_TAG="$tag" docker compose --env-file "$dir/.env" -f "$staged" config --format json > "$new_model"
python3 - "$old_model" "$new_model" "$tag" <<'PY'
import json
import sys

with open(sys.argv[1], encoding="utf-8") as source:
    old = json.load(source)
with open(sys.argv[2], encoding="utf-8") as source:
    new = json.load(source)

expected = {"php", "nginx", "mysql", "redis"}
if set(new.get("services", {})) != expected:
    sys.exit("Unsafe Compose: service list changed; use an approved infrastructure rollout.")

for name in ("mysql", "redis"):
    if old["services"].get(name) != new["services"].get(name):
        sys.exit(f"Unsafe Compose: {name} configuration changed.")

for name in ("networks", "volumes", "secrets", "configs"):
    if old.get(name, {}) != new.get(name, {}):
        sys.exit(f"Unsafe Compose: top-level {name} changed.")

for name in ("php", "nginx"):
    before = old["services"].get(name, {})
    after = new["services"][name]
    expected_image = f"ghcr.io/yoowii/yoowii-{name}:{sys.argv[3]}"
    if after.get("image") != expected_image:
        sys.exit(f"Unsafe Compose: {name} image must be {expected_image}.")
    for field in ("volumes", "networks", "ports", "expose", "network_mode",
                  "privileged", "devices", "cap_add", "pid", "ipc",
                  "userns_mode", "secrets", "configs"):
        if before.get(field) != after.get(field):
            sys.exit(f"Unsafe Compose: {name}.{field} changed.")

print("Infrastructure guard passed: protected services, networks, volumes and mounts unchanged.")
PY

compose=(docker compose --env-file "$dir/.env" -f "$dir/compose.preprod.yml")
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

swapped=false
rollback() {
  local exit_code="$?"
  trap - ERR
  if [[ "$swapped" == true ]]; then
    echo "Deployment failed (exit $exit_code), reverting Compose and images" >&2
    "${compose[@]}" ps >&2 || true
    "${compose[@]}" logs --tail=80 php nginx >&2 || true
    cp -p "$backup" "$dir/.compose.preprod.rollback.tmp"
    mv -f "$dir/.compose.preprod.rollback.tmp" "$dir/compose.preprod.yml"
    if YOOWII_IMAGE_TAG="$old_tag" "${compose[@]}" up -d --no-deps --no-build --force-recreate php nginx; then
      healthcheck && echo "Rollback healthcheck passed" >&2 || echo "CRITICAL: rollback healthcheck failed" >&2
    else
      echo "CRITICAL: rollback recreation failed" >&2
    fi
  fi
  exit "$exit_code"
}
trap rollback ERR

echo "Deploying $tag (previous $old_tag), matching committed Compose from stdin"
docker pull "ghcr.io/yoowii/yoowii-php:$tag"
docker pull "ghcr.io/yoowii/yoowii-nginx:$tag"

# Atomic replacement, on the same filesystem. The .env and volumes are untouched.
mv -f "$staged" "$dir/compose.preprod.yml"
swapped=true
YOOWII_IMAGE_TAG="$tag" "${compose[@]}" up -d --no-deps --no-build --force-recreate php nginx
healthcheck || { echo "HTTP healthcheck failed" >&2; false; }
[[ "$(YOOWII_IMAGE_TAG="$tag" "${compose[@]}" ps --status running --services | grep -Ec '^(php|nginx)$')" == 2 ]] \
  || { echo "Application containers are not both running" >&2; false; }

# Persist the image tag last; keep all other .env values and ownership.
env_tmp="$(mktemp "$dir/.env.XXXXXXXX")"
sed "s/^YOOWII_IMAGE_TAG=.*/YOOWII_IMAGE_TAG=$tag/" .env > "$env_tmp"
chown --reference=.env "$env_tmp"
chmod --reference=.env "$env_tmp"
mv -f "$env_tmp" .env
env_tmp=""
trap - ERR
echo "Deployment successful: $tag (Compose and image SHA aligned)"
