#!/usr/bin/env bash
# Inventory read-only: never print .env, Docker environment, or resolved Compose.
set -Eeuo pipefail
base=/opt/yoowii/preprod
failures=0; warnings=0
ok() { printf 'PASS  %s\n' "$1"; }
warn() { printf 'WARN  %s\n' "$1"; warnings=$((warnings+1)); }
fail() { printf 'FAIL  %s\n' "$1"; failures=$((failures+1)); }
check_file() {
  local file="$1" name="$2" owner mode
  if [[ -L "$file" ]]; then fail "$name is symlinked"; return 1; fi
  if [[ ! -e "$file" ]]; then warn "$name absent"; return 1; fi
  owner="$(stat -c %u "$file")" || { fail "$name owner unavailable"; return 1; }
  mode="$(stat -c %a "$file")" || { fail "$name mode unavailable"; return 1; }
  printf 'INFO  %s uid=%s mode=%s\n' "$name" "$owner" "$mode"
  if (( (8#$mode & 8#022) != 0 )); then fail "$name writable by group/others"; fi
  return 0
}
printf 'Yoowii preprod read-only diagnostic\n'
if [[ ! -d "$base" ]]; then fail 'Preprod directory missing'; exit 2; fi
check_file "$base/.env" 'VPS .env' || true
check_file "$base/compose.preprod.yml" 'Main Compose' || true
if check_file /usr/local/sbin/yoowii-deploy-preprod 'Installed deploy script'; then
  [[ "$(stat -c %u /usr/local/sbin/yoowii-deploy-preprod)" == 0 ]] || fail 'Deploy script not root-owned'
fi
overlay="$base/compose.preprod.worker.yml"
if [[ -e "$overlay" || -L "$overlay" ]]; then
  if check_file "$overlay" 'Opt-in worker overlay'; then
    [[ "$(stat -c %u "$overlay")" == 0 ]] || fail 'Worker overlay not root-owned'
  fi
else
  warn 'Worker overlay absent (normal if never enabled)'
fi
release=''
if [[ -r "$base/.env" ]]; then
  release="$(sed -n 's/^YOOWII_IMAGE_TAG=//p' "$base/.env" | tail -n 1)"
  if [[ "$release" =~ ^sha-[a-f0-9]{40}$ ]]; then
    ok 'Installed image tag matches SHA format'
  else
    fail 'Installed image tag invalid'
  fi
  if grep -q '^YOOWII_MESSENGER_TRANSPORT_DSN=' "$base/.env"; then
    ok 'Messenger transport variable exists (queue/value NOT verified)'
  else
    warn 'Dedicated Messenger transport variable missing'
  fi
else
  warn '.env unreadable; run using authorized administrator permissions'
fi
if ! command -v docker >/dev/null 2>&1; then
  fail 'Docker unavailable'
elif ! ids="$(docker ps -a --filter label=com.docker.compose.project=yoowii-preprod --filter label=com.docker.compose.service=worker --format '{{.ID}}')"; then
  fail 'Cannot inspect Docker containers'
else
  count=0
  [[ -z "$ids" ]] || count="$(printf '%s\n' "$ids" | wc -l)"
  printf 'INFO  worker_count=%s\n' "$count"
  (( count <= 1 )) || fail 'Multiple workers detected'
  if (( count == 0 )); then ok 'No worker to reconcile'; fi
  if (( count > 0 )) && [[ ! -f "$overlay" || -L "$overlay" ]]; then
    fail 'Existing worker requires a safe regular overlay file'
  fi
  while IFS= read -r id; do
    [[ -n "$id" ]] || continue
    if ! info="$(docker inspect --format '{{.Config.Image}} {{.State.Status}}' "$id")"; then
      fail 'Cannot inspect worker metadata'; continue
    fi
    image="${info%% *}"
    state="${info#* }"
    printf 'INFO  worker_state=%s\n' "$state"
    case "$state" in running|exited|created) ok 'Supported worker state';; *) fail 'Unsupported worker state';; esac
    if [[ -n "$release" && "$image" == "ghcr.io/yoowii/yoowii-php:$release" ]]; then
      ok 'Worker image aligned with PHP release'
    else
      fail 'Worker image mismatch or release unavailable'
    fi
  done <<< "$ids"
fi
printf 'RESULT fail=%d warn=%d\n' "$failures" "$warnings"
if (( failures > 0 )); then exit 2; fi
if (( warnings > 0 )); then exit 1; fi
exit 0
