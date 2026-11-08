#!/usr/bin/env python3
"""Test rollback after a simulated post-swap failure, with no real Docker."""
import json
import os
from pathlib import Path
import subprocess
import tempfile

SOURCE = Path(__file__).with_name("deploy-vps.sh")
OLD = "sha-" + "a" * 40
NEW = "sha-" + "b" * 40

def check(ok, msg):
    if not ok:
        raise AssertionError(msg)

with tempfile.TemporaryDirectory(prefix="yoowii-rollback-") as tmp:
    root = Path(tmp)
    site = root / "site"
    binpath = root / "fakebin"
    site.mkdir()
    binpath.mkdir()
    original_compose = "services:\n  php:\n    environment:\n      RELEASE: old\n"
    proposed_compose = "services:\n  php:\n    environment:\n      RELEASE: new\n"
    original_env = f"YOOWII_IMAGE_TAG={OLD}\nAPP_SECRET=preserved\n"
    (site / "compose.preprod.yml").write_text(original_compose)
    (site / ".env").write_text(original_env)
    script = SOURCE.read_text()
    check("dir=/opt/yoowii/preprod" in script, "Deployment path anchor changed")
    check("exec 9>/run/lock/yoowii-preprod-deploy.lock" in script, "Lock anchor changed")
    script = script.replace("dir=/opt/yoowii/preprod", f"dir={site}")
    script = script.replace("exec 9>/run/lock/yoowii-preprod-deploy.lock",
                            f"exec 9>{root / 'lock'}")
    script = script.replace('[[ "${EUID}" -eq 0 ]]', '[[ "${EUID}" -ge 0 ]]')
    (root / "isolated.sh").write_text(script)
    attempts = root / "attempts"
    attempts.write_text("0")
    events = root / "events"

    fake_docker = r'''#!/usr/bin/env python3
import json, os, sys
from pathlib import Path
args = sys.argv[1:]
with open(os.environ["FAKE_EVENTS"], "a") as f:
    f.write(" ".join(args) + " | " + os.environ.get("YOOWII_IMAGE_TAG", "") + "\n")
if args[0] == "ps" and "-a" in args:\n    if os.environ.get("FAKE_WORKER_PRESENT") == "1":\n        print("worker-456")\n    sys.exit(0)\nif args[0] == "pull":
    sys.exit(0)
if "config" in args:
    if "--services" in args:
        print("php\nnginx\nmysql\nredis")
    if "--format" in args:
        tag = os.environ["YOOWII_IMAGE_TAG"]
        path = Path(args[args.index("-f") + 1])
        release = "new" if "RELEASE: new" in path.read_text() else "old"
        print(json.dumps({
            "services": {
                "php": {"image": "ghcr.io/yoowii/yoowii-php:" + tag,
                        "environment": {"RELEASE": release}},
                "nginx": {"image": "ghcr.io/yoowii/yoowii-nginx:" + tag},
                "mysql": {"image": "mysql:8.4"},
                "redis": {"image": "redis:7.4-alpine"}
            },
            "networks": {"internal": {}, "web": {}},
            "volumes": {"mysql_data": {}, "media": {}, "private_assets": {}}
        }))
    sys.exit(0)
if "up" in args:
    p = Path(os.environ["FAKE_ATTEMPTS"])
    count = int(p.read_text()) + 1
    p.write_text(str(count))
    sys.exit(42 if count == 1 else 0)
if "ps" in args or "logs" in args:
    if "--status" in args:
        print("php\nnginx")
    sys.exit(0)
sys.exit("Unexpected docker action")
'''
    (binpath / "docker").write_text(fake_docker)
    (binpath / "curl").write_text("""#!/usr/bin/env python3
import os, sys
from pathlib import Path
args = sys.argv[1:]
if "--output" in args:
    Path(args[args.index("--output") + 1]).write_text(os.environ["FAKE_PINNED_COMPOSE"])
else:
    print("200", end="")
""")
    (binpath / "sleep").write_text("#!/bin/sh\nexit 0\n")
    for name in ("docker", "curl", "sleep"):
        (binpath / name).chmod(0o755)
    env = dict(os.environ, PATH=str(binpath) + ":" + os.environ["PATH"],
               FAKE_EVENTS=str(events), FAKE_ATTEMPTS=str(attempts),
               FAKE_PINNED_COMPOSE=proposed_compose)
    outcome = subprocess.run(["bash", str(root / "isolated.sh"), NEW[4:]],
                             input=proposed_compose, text=True, capture_output=True,
                             env=env, timeout=30)
    check(outcome.returncode != 0, "Failure reported as success")
    check("Infrastructure guard passed" in outcome.stdout, "Guard not tested")
    check("reverting Compose and images" in outcome.stderr, "Rollback not triggered")
    check("Rollback healthcheck passed" in outcome.stderr, "Rollback healthcheck failed")
    check((site / "compose.preprod.yml").read_text() == original_compose, "Compose not restored")
    check((site / ".env").read_text() == original_env, "Secret .env modified")
    check(attempts.read_text() == "2", "Expected failed deploy and rollback")
    rows = [x for x in events.read_text().splitlines() if " up -d " in x]
    check(len(rows) == 2 and rows[0].endswith(NEW) and rows[1].endswith(OLD),
          "Incorrect deployed/rollback image tags")
    print("PASS: post-swap error triggers rollback with nonzero result")
    print("PASS: previous Compose, image tag and .env preserved")
    print("PASS: no real Docker daemon or HTTP requests used")

    # A running OR stopped worker is unsafe during a PHP-only image promotion.
    # Must fail BEFORE pulls, Compose replacement or any container recreation.
    events.write_text("")
    attempts.write_text("0")
    env["FAKE_WORKER_PRESENT"] = "1"
    blocked = subprocess.run(["bash", str(root / "isolated.sh"), NEW[4:]],
                             input=proposed_compose, text=True, capture_output=True,
                             env=env, timeout=30)
    check(blocked.returncode == 9, f"Worker guard exit mismatch: {blocked.stderr}")
    check("Messenger worker container exists" in blocked.stderr, "Worker guard did not explain blocker")
    check((site / "compose.preprod.yml").read_text() == original_compose, "Guard changed current Compose")
    check((site / ".env").read_text() == original_env, "Guard changed .env")
    check(attempts.read_text() == "0", "Guard recreated application containers")
    check(not any(" pull " in " " + row + " " or " up -d " in row for row in events.read_text().splitlines()),
          "Guard performed container image pull or recreation")
    print("PASS: active/stopped Messenger worker prevents PHP promotion before mutation")
