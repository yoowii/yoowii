#!/usr/bin/env bash
# Install root-owned at /usr/local/sbin/yoowii-deploy-preprod.
# Requires exactly one pinned commit SHA and its Compose file on stdin.
# Recreate php/nginx and an existing, explicitly opted-in Messenger worker.
# No migrations, database or volume deletion; never auto-enable a new worker.
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
pinned="$(mktemp "$dir/.compose.preprod.pinned.XXXXXXXX")"
env_tmp=""
cleanup() { rm -f "$staged" "$backup" "$pinned"; [[ -z "$env_tmp" ]] || rm -f "$env_tmp"; }
trap cleanup EXIT

# CI sends the committed Compose via SSH stdin; never read untrusted server paths.
cat > "$staged"
[[ -s "$staged" && "$(wc -c < "$staged")" -le 131072 ]] || { echo "Invalid or oversized Compose payload" >&2; exit 6; }
# A SHA argument alone is not proof that stdin belongs to the commit.
# Never run a caller-supplied Compose as root without checking its provenance.
curl --fail --silent --show-error --location --proto '=https' --tlsv1.2 \
  --connect-timeout 8 --max-time 40 \
  --output "$pinned" \
  "https://raw.githubusercontent.com/yoowii/yoowii/$1/compose.preprod.yml"
if ! cmp -s "$staged" "$pinned"; then
  echo "Compose payload differs from the immutable GitHub commit $1" >&2
  exit 8
fi
echo "Pinned Compose verified against GitHub commit $1"
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
  rm -f "$staged" "$backup" "$pinned" "$old_model" "$new_model"
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

# The worker overlay is an independently approved, root-owned opt-in file.
# Do not implicitly enable it; only reconcile a pre-existing worker.
worker_id=""
worker_was_running=false
worker_stopped=false
worker_reconciled=false
worker_compose=()
worker_ids="$(docker ps -a --filter 'label=com.docker.compose.project=yoowii-preprod' \
  --filter 'label=com.docker.compose.service=worker' --format '{{.ID}}')"
if [[ -n "$worker_ids" ]]; then
  [[ "$(printf '%s\n' "$worker_ids" | wc -l)" -eq 1 ]] || { echo "Ambiguous Messenger workers; manual reconciliation required" >&2; exit 9; }
  worker_id="$worker_ids"
  overlay="$dir/compose.preprod.worker.yml"
  [[ -f "$overlay" && ! -L "$overlay" ]] || { echo "Worker overlay missing or symlinked; deployment blocked" >&2; exit 9; }
  [[ "$(stat -c %u "$overlay")" == 0 ]] && [[ "$(( 8#$(stat -c %a "$overlay") & 8#022 ))" == 0 ]] || {
    echo "Worker overlay must be root-owned and not group/world writable" >&2; exit 9;
  }
  actual_worker_image="$(docker inspect --format '{{.Config.Image}}' "$worker_id")"
  [[ "$actual_worker_image" == "ghcr.io/yoowii/yoowii-php:$old_tag" ]] || {
    echo "Worker image differs from current PHP release; manual reconciliation required" >&2; exit 9;
  }
  worker_state="$(docker inspect --format '{{.State.Status}}' "$worker_id")"
  case "$worker_state" in
    running) worker_was_running=true ;;
    exited|created) ;;
    *) echo "Unsupported worker state: $worker_state" >&2; exit 9 ;;
  esac
  # Compose's overlay must use the same immutable PHP image, never publish ports,
  # and retain the dedicated queue and supplier/mail kill switches.
  worker_compose=(docker compose --profile messenger --env-file "$dir/.env" -f "$dir/compose.preprod.yml" -f "$overlay")
  YOOWII_IMAGE_TAG="$tag" "${worker_compose[@]}" config --quiet
  worker_model="$(YOOWII_IMAGE_TAG="$tag" "${worker_compose[@]}" config --format json)"
  WORKER_MODEL="$worker_model" python3 - "$tag" <<'PY'
import json, os, sys
model = json.loads(os.environ["WORKER_MODEL"])
w = model.get("services", {}).get("worker", {})
if w.get("image") != "ghcr.io/yoowii/yoowii-php:" + sys.argv[1]:
    sys.exit("Worker image must match the PHP deployment image")
if w.get("ports") or w.get("privileged") or set(w.get("networks", {})) != {"internal"}:
    sys.exit("Unsafe worker exposure / network configuration")
env = w.get("environment", {})
required = {"MAILER_DSN": "null://null", "YOOWII_REALISAPRINT_ENABLED": "0",
            "YOOWII_REALISAPRINT_FTP_ENABLED": "0",
            "YOOWII_REALISAPRINT_PRESCRIPT_QUICK_PUBLICATION_ENABLED": "0",
            "YOOWII_REALISAPRINT_QUOTE_ENABLED": "0"}
if any(str(env.get(k)) != v for k,v in required.items()):
    sys.exit("Worker supplier/mail isolation violated")
if not env.get("MESSENGER_TRANSPORT_DSN") or "queue_name=default" in str(env["MESSENGER_TRANSPORT_DSN"]):
    sys.exit("Worker dedicated Messenger transport missing")
PY
  echo "Existing worker verified (initial state: $worker_state); will reconcile with PHP"
fi

swapped=false
rollback() {
  local exit_code="$?"
  trap - ERR
  if [[ "$swapped" == true || "$worker_stopped" == true ]]; then
    echo "Deployment failed (exit $exit_code), reverting Compose and images" >&2
    "${compose[@]}" ps >&2 || true
    "${compose[@]}" logs --tail=80 php nginx >&2 || true
    if [[ "$swapped" == true ]]; then
      cp -p "$backup" "$dir/.compose.preprod.rollback.tmp"
      mv -f "$dir/.compose.preprod.rollback.tmp" "$dir/compose.preprod.yml"
    fi
    if YOOWII_IMAGE_TAG="$old_tag" "${compose[@]}" up -d --no-deps --no-build --force-recreate php nginx; then
      healthcheck && echo "Rollback healthcheck passed" >&2 || echo "CRITICAL: rollback healthcheck failed" >&2
    else
      echo "CRITICAL: rollback recreation failed" >&2
    fi
    if [[ -n "$worker_id" ]]; then
      if [[ "$worker_reconciled" == true ]]; then
        if YOOWII_IMAGE_TAG="$old_tag" "${worker_compose[@]}" create --no-deps --no-build --force-recreate worker; then
          if [[ "$worker_was_running" == true ]]; then
            docker start "$("${worker_compose[@]}" ps -a -q worker)" || echo "CRITICAL: worker rollback start failed" >&2
          fi
          echo "Worker restored to previous image and initial running state" >&2
        else
          echo "CRITICAL: worker rollback recreation failed" >&2
        fi
      elif [[ "$worker_stopped" == true && "$worker_was_running" == true ]]; then
        docker start "$worker_id" || echo "CRITICAL: worker restart failed" >&2
      fi
    fi
  fi
  exit "$exit_code"
}
trap rollback ERR

# Stop a running worker before recreating PHP, respecting its 90s grace period.
# On stop timeout/error, abort before swapping Compose or PHP containers.
if [[ "$worker_was_running" == true ]]; then
  docker stop --time 90 "$worker_id"
  worker_stopped=true
fi

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

# Reconcile the opted-in worker before publishing the new image tag.
# 'create' preserves a previously stopped worker without starting it.
if [[ -n "$worker_id" ]]; then
  YOOWII_IMAGE_TAG="$tag" "${worker_compose[@]}" create --no-deps --no-build --force-recreate worker
  worker_reconciled=true
  if [[ "$worker_was_running" == true ]]; then
    worker_new_id="$(YOOWII_IMAGE_TAG="$tag" "${worker_compose[@]}" ps -a -q worker)"
    [[ -n "$worker_new_id" ]] || { echo "Worker recreation returned no container" >&2; false; }
    docker start "$worker_new_id"
    [[ "$(docker inspect --format '{{.State.Status}}' "$worker_new_id")" == running ]] || {
      echo "Worker failed to start after recreation" >&2; false;
    }
  fi
fi

# Persist the image tag last; keep all other .env values and ownership.
env_tmp="$(mktemp "$dir/.env.XXXXXXXX")"
sed "s/^YOOWII_IMAGE_TAG=.*/YOOWII_IMAGE_TAG=$tag/" .env > "$env_tmp"
chown --reference=.env "$env_tmp"
chmod --reference=.env "$env_tmp"
mv -f "$env_tmp" .env
env_tmp=""
trap - ERR
echo "Deployment successful: $tag (Compose and image SHA aligned)"
