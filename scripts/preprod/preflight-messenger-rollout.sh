#!/usr/bin/env bash
# Read-only readiness gate BEFORE merging/activating Messenger worker (#4).
# Does not run PHP handlers, consume messages, write .env, create tables, or
# change Docker state. Never prints DSNs, secrets, or message bodies/headers.
set -Eeuo pipefail

dir="${YOOWII_PREPROD_DIR:-/opt/yoowii/preprod}"
cd "$dir"
[[ -f .env && -f compose.preprod.yml ]] || {
  echo "BLOCKED: missing preprod .env or Compose" >&2; exit 2;
}

blocked=0
check() {
  local description="$1" valid="$2"
  if [[ "$valid" == yes ]]; then
    echo "PASS: $description"
  else
    echo "BLOCKED: $description" >&2
    blocked=1
  fi
}

# Keep secrets private; never source or dump the environment.
env_mode="$(stat -c '%a' .env)"
check "VPS .env mode 600" "$([[ "$env_mode" == 600 ]] && echo yes || echo no)"
tag="$(sed -n 's/^YOOWII_IMAGE_TAG=//p' .env | tail -n 1)"
check "immutable application release tag" "$([[ "$tag" =~ ^sha-[a-f0-9]{40}$ ]] && echo yes || echo no)"

# The existing main Compose can be checked without pulling or starting images.
if docker compose --env-file "$dir/.env" -f "$dir/compose.preprod.yml" config --quiet >/dev/null 2>&1; then
  echo "PASS: current Compose parses"
else
  echo "BLOCKED: current Compose invalid" >&2
  blocked=1
fi

# Worker overlay must be copied from the reviewed, pinned PR commit by root.
# It is deliberately absent from automatic deployments until an approved rollout.
if [[ -f compose.preprod.worker.yml ]]; then
  if docker compose --env-file "$dir/.env" -f "$dir/compose.preprod.yml" \
      -f "$dir/compose.preprod.worker.yml" --profile messenger config --quiet >/dev/null 2>&1; then
    echo "PASS: opt-in worker overlay parses"
  else
    echo "BLOCKED: worker overlay invalid or dedicated DSN missing" >&2
    blocked=1
  fi
else
  echo "BLOCKED: worker overlay not installed (expected before activation)" >&2
  blocked=1
fi

# Never expose actual DSNs. Also confirm they are not accidentally configured
# to consume default or supplier queues. Dedicated DSN is an opt-in gate.
expected_preflight='doctrine://default?queue_name=yoowii_async'
expected_supplier='doctrine://default?queue_name=yoowii_supplier_async'
preflight="$(sed -n 's/^YOOWII_MESSENGER_TRANSPORT_DSN=//p' .env | tail -n 1)"
supplier="$(sed -n 's/^YOOWII_SUPPLIER_MESSENGER_TRANSPORT_DSN=//p' .env | tail -n 1)"
check "dedicated artwork DSN in protected VPS .env" "$([[ "$preflight" == "$expected_preflight" ]] && echo yes || echo no)"
if [[ -z "$supplier" || "$supplier" == "$expected_supplier" ]]; then
  echo "PASS: supplier DSN uses the dedicated producer fallback or explicit dedicated queue"
else
  echo "BLOCKED: supplier DSN differs from expected isolated queue" >&2
  blocked=1
fi

# Read database queue counts without looking at message contents; no SQL writes.
# Never take credentials from the host command line.
sql="SELECT queue_name, COUNT(*) FROM messenger_messages
WHERE queue_name IN ('default','yoowii_async','yoowii_failed','yoowii_supplier_async')
GROUP BY queue_name"
if counts="$(docker compose --env-file "$dir/.env" -f "$dir/compose.preprod.yml" \
  exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql \
    --batch --skip-column-names --raw -u "$MYSQL_USER" "$MYSQL_DATABASE" -e "$1"' sh "$sql" 2>/dev/null)"; then
  echo "PASS: Messenger table accessible for aggregate counts"
  while IFS=$'\t' read -r queue total; do
    [[ -n "$queue" ]] || continue
    if [[ "$total" =~ ^[0-9]+$ ]]; then
      echo "queue=$queue count=$total"
      if [[ "$queue" != default && "$total" != 0 ]]; then
        echo "BLOCKED: non-legacy queue has pending messages requiring review" >&2
        blocked=1
      fi
    else
      echo "BLOCKED: invalid aggregate queue count" >&2
      blocked=1
    fi
  done <<< "$counts"
else
  echo "BLOCKED: cannot read Messenger aggregate queue counters" >&2
  blocked=1
fi

# Check explicitly that no consumer runs. Never stop one automatically.
worker_ids="$(docker ps -q --filter label=com.docker.compose.project=yoowii-preprod --filter label=com.docker.compose.service=worker)"
check "no running worker before activation" "$([[ -z "$worker_ids" ]] && echo yes || echo no)"

if [[ "$blocked" -ne 0 ]]; then
  echo "NOT READY: no action was taken; review BLOCKED lines" >&2
  exit 1
fi
echo "READY FOR MANUAL REVIEW: no action taken and no worker started"
