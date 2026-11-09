#!/usr/bin/env bash
# Root-owned, fixed-command audit. No user arguments, no writes, no secrets.
set -Eeuo pipefail
[[ "$#" -eq 0 ]] || { echo 'ERROR: arguments are not accepted' >&2; exit 2; }
BASE=/opt/yoowii/preprod
FAIL=0
WARN=0
ok() { printf 'PASS %s\n' "$1"; }
bad() { printf 'FAIL %s\n' "$1"; FAIL=$((FAIL+1)); }
warning() { printf 'WARN %s\n' "$1"; WARN=$((WARN+1)); }
file_status() {
  local path="$1" label="$2" owner mode
  if [[ -L "$path" ]]; then bad "$label is a symlink"; return 0; fi
  if [[ ! -f "$path" ]]; then bad "$label missing"; return 0; fi
  owner="$(stat -c %u "$path")"
  mode="$(stat -c %a "$path")"
  printf 'INFO %s uid=%s mode=%s\n' "$label" "$owner" "$mode"
  if (( (8#$mode & 8#022) != 0 )); then bad "$label writable by group or others"; fi
}
printf 'YOOWII preprod read-only audit\n'
if [[ ! -d "$BASE" ]]; then bad 'preprod directory missing'; exit 2; fi
file_status "$BASE/.env" 'env'
file_status "$BASE/compose.preprod.yml" 'compose'
file_status /usr/local/sbin/yoowii-deploy-preprod 'deployer'
if [[ "$(stat -c %u /usr/local/sbin/yoowii-deploy-preprod 2>/dev/null || echo unknown)" != 0 ]]; then
  bad 'deployer must be root-owned'
fi
if [[ -L "$BASE/compose.preprod.worker.yml" ]]; then
  bad 'worker overlay is a symlink'
elif [[ -f "$BASE/compose.preprod.worker.yml" ]]; then
  file_status "$BASE/compose.preprod.worker.yml" 'worker overlay'
  [[ "$(stat -c %u "$BASE/compose.preprod.worker.yml")" == 0 ]] || bad 'worker overlay must be root-owned'
else
  warning 'worker overlay absent (expected if worker has not been enabled)'
fi
release=''
if [[ -f "$BASE/.env" && -r "$BASE/.env" ]]; then
  release="$(sed -n 's/^YOOWII_IMAGE_TAG=//p' "$BASE/.env" | tail -1)"
  if [[ "$release" =~ ^sha-[a-f0-9]{40}$ ]]; then
    ok 'release tag format valid'
  else
    bad 'release tag missing or invalid'
  fi
  if grep -q '^YOOWII_MESSENGER_TRANSPORT_DSN=' "$BASE/.env"; then
    ok 'dedicated Messenger DSN variable present (value NOT inspected)'
  else
    warning 'dedicated Messenger DSN variable not declared'
  fi
fi
if ! command -v docker >/dev/null 2>&1; then
  bad 'docker unavailable'
elif ! ids="$(docker ps -a --filter label=com.docker.compose.project=yoowii-preprod --filter label=com.docker.compose.service=worker --format '{{.ID}}')"; then
  bad 'docker inspection failed'
else
  count=0
  [[ -z "$ids" ]] || count="$(printf '%s\n' "$ids" | wc -l)"
  printf 'INFO worker_count=%s\n' "$count"
  (( count <= 1 )) || bad 'multiple workers found'
  if (( count > 0 )) && [[ ! -f "$BASE/compose.preprod.worker.yml" ]]; then
    bad 'worker exists but approved overlay is missing'
  fi
  while IFS= read -r id; do
    [[ -n "$id" ]] || continue
    if ! metadata="$(docker inspect --format '{{.Config.Image}} {{.State.Status}}' "$id")"; then
      bad 'unable to inspect worker'
      continue
    fi
    image="${metadata%% *}"
    state="${metadata#* }"
    printf 'INFO worker_state=%s\n' "$state"
    case "$state" in running|exited|created) ok 'worker state supported';; *) bad 'worker state unsupported';; esac
    if [[ -n "$release" && "$image" == "ghcr.io/yoowii/yoowii-php:$release" ]]; then
      ok 'worker image aligned with release'
    else
      bad 'worker image does not match current PHP release'
    fi
  done <<< "$ids"
fi
http_code="$(curl --silent --output /dev/null --connect-timeout 3 --max-time 10 --write-out '%{http_code}' -H 'Host: preprod.yoowii.com' -H 'X-Forwarded-Proto: https' http://127.0.0.1:8086/fr_FR/ || true)"
printf 'INFO local_http_status=%s\n' "$http_code"
[[ "$http_code" == 200 ]] || warning 'local storefront healthcheck not HTTP 200 (may require Basic auth)'
printf 'RESULT failures=%d warnings=%d\n' "$FAIL" "$WARN"
if (( FAIL > 0 )); then exit 2; fi
if (( WARN > 0 )); then exit 1; fi
exit 0
