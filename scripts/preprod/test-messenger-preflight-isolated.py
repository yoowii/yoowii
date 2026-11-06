#!/usr/bin/env python3
"""Safe, mocked tests for the read-only preproduction Messenger readiness gate."""
from __future__ import annotations

import os
from pathlib import Path
import subprocess
import tempfile

GATE = Path(__file__).with_name("preflight-messenger-rollout.sh")
SHA = "a" * 40
FAKE_DOCKER = r"""#!/usr/bin/env bash
set -Eeuo pipefail
case "${1:-}" in
  compose)
    case " $* " in
      *" config --quiet "*) exit "${FAKE_COMPOSE_RC:-0}" ;;
      *" exec -T mysql "*) printf '%s\n' "${FAKE_COUNTS:-default	19}"; exit "${FAKE_MYSQL_RC:-0}" ;;
      *) echo 'Unexpected Compose command' >&2; exit 70 ;;
    esac
    ;;
  ps)
    if [[ "${FAKE_WORKER_RUNNING:-0}" == 1 ]]; then echo worker-456; fi
    ;;
  *) echo 'Unexpected Docker command' >&2; exit 70 ;;
esac
"""


def run(root: Path, **extra: str) -> subprocess.CompletedProcess[str]:
    env = os.environ.copy()
    env.update({"PATH": str(root / "bin") + os.pathsep + env["PATH"],
                "YOOWII_PREPROD_DIR": str(root), **extra})
    return subprocess.run(["bash", str(GATE)], capture_output=True, text=True,
                          env=env, timeout=10, check=False)


def check(label: str, result: subprocess.CompletedProcess[str],
          code: int, expected: str) -> None:
    output = result.stdout + result.stderr
    assert result.returncode == code, f"{label}: exit={result.returncode} output={output}"
    assert expected in output, f"{label}: missing {expected!r} output={output}"
    assert "API_KEY" not in output, "Secret leaked to logs"
    print(f"PASS: {label}")


def main() -> None:
    with tempfile.TemporaryDirectory(prefix="yoowii-preflight-") as tmp:
        root = Path(tmp)
        (root / "bin").mkdir()
        (root / "bin" / "docker").write_text(FAKE_DOCKER)
        (root / "bin" / "docker").chmod(0o755)
        (root / "compose.preprod.yml").write_text("services: {}\n")
        env_file = root / ".env"
        env_file.write_text(f"YOOWII_IMAGE_TAG=sha-{SHA}\n"
                            "API_KEY=never-echo-this\n")
        env_file.chmod(0o600)

        check("no overlay or opt-in blocks", run(root), 1, "worker overlay not installed")
        (root / "compose.preprod.worker.yml").write_text("services: {}\n")
        check("no DSN opt-in blocks", run(root), 1, "dedicated artwork DSN")

        env_file.write_text(env_file.read_text() +
                            "YOOWII_MESSENGER_TRANSPORT_DSN=doctrine://default?queue_name=yoowii_async\n")
        check("empty target queues ready for manual review", run(root), 0, "READY FOR MANUAL REVIEW")
        check("legacy queue allowed", run(root, FAKE_COUNTS="default\t19"), 0, "queue=default count=19")
        check("supplier backlog blocks", run(root, FAKE_COUNTS="default\t19\nyoowii_supplier_async\t1"),
              1, "non-legacy queue has pending messages")
        check("active worker blocks", run(root, FAKE_WORKER_RUNNING="1"), 1, "no running worker")
        check("invalid Compose blocks", run(root, FAKE_COMPOSE_RC="1"), 1, "current Compose invalid")
        check("DB error blocks", run(root, FAKE_MYSQL_RC="1"), 1, "cannot read Messenger aggregate")
        env_file.chmod(0o644)
        check("unsafe env permissions block", run(root), 1, "VPS .env mode 600")


if __name__ == "__main__":
    main()
