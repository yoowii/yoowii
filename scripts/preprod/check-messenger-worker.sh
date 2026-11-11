#!/usr/bin/env bash
# Read-only operational check. Does not start/stop containers or consume messages.
# Requires Docker access on VPS. No secrets, message bodies or headers are printed.
set -Eeuo pipefail

dir="${YOOWII_PREPROD_DIR:-/opt/yoowii/preprod}"
require_running=false
if [[ "${1:-}" == "--require-running" && "$#" -eq 1 ]]; then
  require_running=true
elif [[ "$#" -ne 0 ]]; then
  echo "Usage: $0 [--require-running]" >&2
  exit 64
fi

cd "$dir"
[[ -f .env && -f compose.preprod.yml ]] || { echo "CRITICAL: missing VPS Compose or .env" >&2; exit 2; }

# Do not source .env: it contains secrets and must never be evaluated as shell.
image_tag="$(sed -n 's/^YOOWII_IMAGE_TAG=//p' .env | tail -1)"
[[ "$image_tag" =~ ^sha-[a-f0-9]{40}$ ]] || { echo "CRITICAL: invalid image tag" >&2; exit 2; }
expected="ghcr.io/yoowii/yoowii-php:$image_tag"

compose=(docker compose --env-file "$dir/.env" -f "$dir/compose.preprod.yml")
php_id="$("${compose[@]}" ps -q php)"
[[ -n "$php_id" ]] || { echo "CRITICAL: PHP service is missing" >&2; exit 2; }
php_running="$(docker inspect --format '{{.State.Running}}' "$php_id")"
php_image="$(docker inspect --format '{{.Config.Image}}' "$php_id")"
[[ "$php_running" == true && "$php_image" == "$expected" ]] || {
  echo "CRITICAL: PHP not running on the expected immutable release" >&2
  exit 2
}

# Overlay is optional and is never required merely to read a stopped worker.
worker_id="$(docker ps -a -q --filter 'label=com.docker.compose.project=yoowii-preprod' --filter 'label=com.docker.compose.service=worker' | head -n 1)"
state="inactive"
problems=0
if [[ -n "$worker_id" ]]; then
  worker_running="$(docker inspect --format '{{.State.Running}}' "$worker_id")"
  worker_image="$(docker inspect --format '{{.Config.Image}}' "$worker_id")"
  restart_count="$(docker inspect --format '{{.RestartCount}}' "$worker_id")"
  if [[ "$worker_running" == true ]]; then
    state="running"
    if [[ "$worker_image" != "$expected" ]]; then
      echo "CRITICAL: worker release differs from PHP; reconcile it before new application changes" >&2
      problems=1
    fi
    if [[ "$restart_count" =~ ^[0-9]+$ && "$restart_count" -gt 0 ]]; then
      echo "WARNING: worker has restarted $restart_count time(s)" >&2
      problems=1
    fi
  else
    state="stopped"
  fi
fi
echo "worker=$state php_image_aligned=$([[ "$problems" -eq 0 ]] && echo yes || echo no)"
if [[ "$require_running" == true && "$state" != running ]]; then
  echo "CRITICAL: worker is not running" >&2
  problems=1
fi

# Query aggregate counters only; never select the body/headers columns.
# Password remains in the MySQL container environment and is not echoed.
sql="SELECT queue_name, COUNT(*),
  SUM(delivered_at IS NULL AND available_at <= UTC_TIMESTAMP()),
  COALESCE(TIMESTAMPDIFF(SECOND,
    MIN(CASE WHEN delivered_at IS NULL AND available_at <= UTC_TIMESTAMP()
      THEN created_at ELSE NULL END), UTC_TIMESTAMP()), 0)
FROM messenger_messages
WHERE queue_name IN ('yoowii_async','yoowii_failed','yoowii_supplier_async')
GROUP BY queue_name"
result="$("${compose[@]}" exec -T mysql sh -c '
  MYSQL_PWD="$MYSQL_PASSWORD" exec mysql --batch --skip-column-names --raw \
    -u "$MYSQL_USER" "$MYSQL_DATABASE" -e "$1"
' sh "$sql")" || { echo "CRITICAL: cannot read Messenger queue counters" >&2; exit 2; }

# Empty output means no messages in any monitored queue.
while IFS=$'\t' read -r name total ready oldest; do
  [[ -n "$name" ]] || continue
  [[ "$total" =~ ^[0-9]+$ && "$ready" =~ ^[0-9]+$ && "$oldest" =~ ^[0-9]+$ ]] || {
    echo "CRITICAL: invalid aggregate queue statistics" >&2
    exit 2
  }
  echo "queue=$name count=$total ready=$ready oldest_ready_seconds=$oldest"
  if [[ "$name" == yoowii_failed && "$total" -gt 0 ]]; then
    echo "WARNING: failed Yoowii messages require review" >&2
    problems=1
  elif [[ "$name" == yoowii_async && ( "$ready" -gt 50 || "$oldest" -gt 900 ) ]]; then
    echo "WARNING: artwork queue exceeds 50 ready messages or 15-minute age" >&2
    problems=1
  fi
done <<< "$result"

if [[ "$problems" -ne 0 ]]; then
  exit 1
fi
echo "PASS: read-only Messenger operational check"
