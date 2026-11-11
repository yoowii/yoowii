#!/usr/bin/env python3
"""End-to-end checks for the read-only monitor using fake Docker.

No network, database, Docker daemon, actual queue or VPS needed.
"""
from __future__ import annotations

import os
from pathlib import Path
import subprocess
import tempfile

MONITOR = Path(__file__).with_name("check-messenger-worker.sh")
SHA = "a" * 40
FAKE_DOCKER = r"""#!/usr/bin/env bash
set -Eeuo pipefail
if [[ "${1:-}" == compose ]]; then
  shift
  case " $* " in
    *" ps -q php "*) echo php-123; exit 0 ;;
    *" exec -T mysql "*) printf '%s\n' "${FAKE_QUEUES:-}"; exit 0 ;;
    *) echo "Unexpected compose invocation: $*" >&2; exit 70 ;;
  esac
fi
if [[ "${1:-}" == inspect ]]; then
  case "${4:-}" in
    php-123)
      case "${3:-}" in
        '{{.State.Running}}') echo true ;;
        '{{.Config.Image}}') echo "ghcr.io/yoowii/yoowii-php:sha-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa" ;;
        *) exit 70 ;;
      esac
      ;;
    worker-456)
      case "${3:-}" in
        '{{.State.Running}}') echo "${FAKE_WORKER_RUNNING:-true}" ;;
        '{{.Config.Image}}') echo "${FAKE_WORKER_IMAGE:-ghcr.io/yoowii/yoowii-php:sha-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa}" ;;
        '{{.RestartCount}}') echo "${FAKE_WORKER_RESTARTS:-0}" ;;
        *) exit 70 ;;
      esac
      ;;
    *) exit 70 ;;
  esac
  exit 0
fi
if [[ "${1:-}" == ps ]]; then
  if [[ "${FAKE_WORKER_PRESENT:-0}" == 1 ]]; then echo worker-456; fi
  exit 0
fi
echo "Unexpected Docker invocation: $*" >&2
exit 70
"""


def run_case(directory: Path, *, env_overrides: dict[str, str], args: tuple[str, ...] = ()) -> subprocess.CompletedProcess[str]:
    env = os.environ.copy()
    env.update({"PATH": str(directory / "bin") + os.pathsep + env["PATH"],
                "YOOWII_PREPROD_DIR": str(directory), **env_overrides})
    return subprocess.run(["bash", str(MONITOR), *args], env=env, capture_output=True, text=True, check=False, timeout=10)


def assert_case(label: str, proc: subprocess.CompletedProcess[str], expected: int, match: str) -> None:
    output = proc.stdout + proc.stderr
    assert proc.returncode == expected, f"{label}: exit={proc.returncode}, output={output}"
    assert match in output, f"{label}: expected {match!r}, got {output}"
    print(f"PASS: {label}")


def main() -> None:
    with tempfile.TemporaryDirectory(prefix="yoowii-monitor-") as tmp:
        root = Path(tmp)
        (root / "bin").mkdir()
        (root / "bin" / "docker").write_text(FAKE_DOCKER)
        (root / "bin" / "docker").chmod(0o755)
        (root / "compose.preprod.yml").write_text("services: {}\n")
        (root / ".env").write_text(f"YOOWII_IMAGE_TAG=sha-{SHA}\n")
        empty = {"FAKE_QUEUES": ""}
        assert_case("optional worker stopped", run_case(root, env_overrides=empty), 0, "worker=inactive")
        assert_case("explicit running requirement", run_case(root, env_overrides=empty, args=("--require-running",)), 1, "worker is not running")
        assert_case("healthy running worker", run_case(root, env_overrides={
            "FAKE_WORKER_PRESENT": "1", "FAKE_QUEUES": "yoowii_async\t1\t0\t0"}), 0, "PASS: read-only Messenger operational check")
        assert_case("stale worker image", run_case(root, env_overrides={
            "FAKE_WORKER_PRESENT": "1", "FAKE_WORKER_IMAGE": "ghcr.io/yoowii/yoowii-php:sha-" + "b" * 40}), 1, "worker release differs")
        assert_case("failed backlog", run_case(root, env_overrides={
            "FAKE_QUEUES": "yoowii_failed\t1\t1\t1"}), 1, "failed Yoowii messages")
        assert_case("old ready backlog", run_case(root, env_overrides={
            "FAKE_QUEUES": "yoowii_async\t3\t2\t901"}), 1, "artwork queue exceeds")
        assert_case("consumer restarts", run_case(root, env_overrides={
            "FAKE_WORKER_PRESENT": "1", "FAKE_WORKER_RESTARTS": "2"}), 1, "worker has restarted")


if __name__ == "__main__":
    main()
