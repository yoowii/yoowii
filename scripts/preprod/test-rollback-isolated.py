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
    worker_runtime = root / "worker-runtime"
    worker_runtime.write_text("running")
    events = root / "events"
    (site / "compose.preprod.worker.yml").write_text("services:\n  worker:\n    image: fixed\n")

    fake_docker = r'''#!/usr/bin/env python3
import json, os, sys
from pathlib import Path
args = sys.argv[1:]
with open(os.environ["FAKE_EVENTS"], "a") as f:
    f.write(" ".join(args) + " | " + os.environ.get("YOOWII_IMAGE_TAG", "") + "\n")
state = os.environ.get("FAKE_WORKER_STATE", "")
runtime = Path(os.environ["FAKE_WORKER_RUNTIME"])
if args[0] == "ps" and "-a" in args:
    if state in ("running", "stopped"):
        print("worker-456")
    sys.exit(0)
if args[0] == "inspect":
    if any(".Config.Image" in arg for arg in args):
        print("ghcr.io/yoowii/yoowii-php:" + os.environ.get("FAKE_WORKER_IMAGE_TAG", "sha-" + "a"*40))
    elif any(".State.Status" in arg for arg in args):
        print(runtime.read_text().strip())
    sys.exit(0)
if args[0] == "stop":
    if os.environ.get("FAKE_FAIL_WORKER_STOP") == "1":
        sys.exit(44)
    runtime.write_text("exited")
    sys.exit(0)
if args[0] == "start":
    failure_marker = Path(os.environ["FAKE_FAIL_START_ONCE_FILE"])
    if os.environ.get("FAKE_FAIL_WORKER_START") == "1":
        if os.environ.get("FAKE_FAIL_START_ONCE") != "1" or not failure_marker.exists():
            failure_marker.write_text("1")
            sys.exit(45)
    runtime.write_text("running")
    sys.exit(0)
if args[0] == "pull":
    if os.environ.get("FAKE_FAIL_PULL") == "1":
        sys.exit(46)
    sys.exit(0)
if "config" in args:
    if "--services" in args:
        print("php\nnginx\nmysql\nredis")
    if "--format" in args:
        tag = os.environ["YOOWII_IMAGE_TAG"]
        path = Path(args[args.index("-f") + 1])
        release = "new" if "RELEASE: new" in path.read_text() else "old"
        services = {
            "php": {"image": "ghcr.io/yoowii/yoowii-php:" + tag,
                    "environment": {"RELEASE": release}},
            "nginx": {"image": "ghcr.io/yoowii/yoowii-nginx:" + tag},
            "mysql": {"image": "mysql:8.4"},
            "redis": {"image": "redis:7.4-alpine"},
        }
        if "compose.preprod.worker.yml" in " ".join(args):
            services["worker"] = {
                "image": "ghcr.io/yoowii/yoowii-php:" + tag,
                "networks": ["internal"],
                "environment": {
                    "MAILER_DSN": "null://null",
                    "YOOWII_REALISAPRINT_ENABLED": "0",
                    "YOOWII_REALISAPRINT_FTP_ENABLED": "0",
                    "YOOWII_REALISAPRINT_PRESCRIPT_QUICK_PUBLICATION_ENABLED": "0",
                    "YOOWII_REALISAPRINT_QUOTE_ENABLED": "0",
                    "MESSENGER_TRANSPORT_DSN": "doctrine://default?queue_name=yoowii_async"
                }
            }
        print(json.dumps({
            "services": services,
            "networks": {"internal": {}, "web": {}},
            "volumes": {"mysql_data": {}, "media": {}, "private_assets": {}}
        }))
    sys.exit(0)
if "create" in args:
    runtime.write_text("created")
    if os.environ.get("FAKE_FAIL_WORKER_CREATE") == "1" and os.environ.get("YOOWII_IMAGE_TAG", "").endswith("b"*40):
        sys.exit(43)
    sys.exit(0)
if "up" in args:
    p = Path(os.environ["FAKE_ATTEMPTS"])
    count = int(p.read_text()) + 1
    p.write_text(str(count))
    sys.exit(42 if os.environ.get("FAKE_FAIL_FIRST_UP") == "1" and count == 1 else 0)
if "ps" in args or "logs" in args:
    if "-q" in args:
        print("worker-456")
    elif "--status" in args:
        print("php\nnginx")
    sys.exit(0)
sys.exit("Unexpected docker action: " + " ".join(args))
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
    # Isolated CI has no root-owned files. Emulate the approved overlay
    # metadata without relaxing ownership checks in the production script.
    (binpath / "stat").write_text("""#!/bin/sh
if [ "$1" = "-c" ] && [ "$3" = "$FAKE_OVERLAY" ]; then
  case "$2" in
    %u) printf '0\\n' ;;
    %a) printf '644\\n' ;;
    *) exit 1 ;;
  esac
else
  exec /usr/bin/stat "$@"
fi
""")
    for name in ("docker", "curl", "sleep", "stat"):
        (binpath / name).chmod(0o755)
    env = dict(os.environ, PATH=str(binpath) + ":" + os.environ["PATH"],
               FAKE_EVENTS=str(events), FAKE_ATTEMPTS=str(attempts),
               FAKE_PINNED_COMPOSE=proposed_compose,
               FAKE_WORKER_RUNTIME=str(worker_runtime), FAKE_FAIL_FIRST_UP="1",
               FAKE_OVERLAY=str(site / "compose.preprod.worker.yml"),
               FAKE_FAIL_START_ONCE_FILE=str(root / "worker-failed-start"))
    outcome = subprocess.run(["bash", str(root / "isolated.sh"), NEW[4:]],
                             input=proposed_compose, text=True, capture_output=True,
                             env=env, timeout=30)
    check(outcome.returncode != 0, "Failure reported as success")
    check("Infrastructure guard passed" in outcome.stdout,
          f"Guard not tested: stdout={outcome.stdout!r}, stderr={outcome.stderr!r}")
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

    # Existing opt-in worker is coordinated with the PHP release.
    # A previously stopped worker must remain stopped, including after success.
    env["FAKE_FAIL_FIRST_UP"] = "0"
    for initial in ("running", "stopped"):
        env["FAKE_WORKER_STATE"] = initial
        worker_runtime.write_text("running" if initial == "running" else "exited")
        events.write_text("")
        attempts.write_text("0")
        (site / "compose.preprod.yml").write_text(original_compose)
        (site / ".env").write_text(original_env)
        success = subprocess.run(["bash", str(root / "isolated.sh"), NEW[4:]],
                                 input=proposed_compose, text=True, capture_output=True,
                                 env=env, timeout=30)
        check(success.returncode == 0,
              f"{initial} worker deploy failed: stdout={success.stdout!r}, stderr={success.stderr!r}")
        check("Infrastructure guard passed" in success.stdout,
              f"{initial}: infrastructure guard skipped")
        check((site / ".env").read_text().startswith(f"YOOWII_IMAGE_TAG={NEW}"),
              f"{initial}: image release not persisted")
        check(worker_runtime.read_text() == ("running" if initial == "running" else "created"),
              f"{initial}: worker initial running state not preserved")
        rows = events.read_text().splitlines()
        check(any(" create " in " " + x + " " and x.endswith(NEW) for x in rows),
              f"{initial}: worker not recreated with new image")
        check(not any("remove-orphans" in x for x in rows),
              f"{initial}: unsafe orphan removal")
        print(f"PASS: {initial} worker reconciled with PHP release")

    # The post-swap failure must restore the old image and worker state.
    env["FAKE_FAIL_FIRST_UP"] = "1"
    env["FAKE_WORKER_STATE"] = "running"
    worker_runtime.write_text("running")
    events.write_text("")
    attempts.write_text("0")
    (site / "compose.preprod.yml").write_text(original_compose)
    (site / ".env").write_text(original_env)
    rolled = subprocess.run(["bash", str(root / "isolated.sh"), NEW[4:]],
                            input=proposed_compose, text=True, capture_output=True,
                            env=env, timeout=30)
    check(rolled.returncode != 0, "Worker rollback failure reported success")
    check("Rollback healthcheck passed" in rolled.stderr,
          f"Rollback failed: stdout={rolled.stdout!r}, stderr={rolled.stderr!r}")
    check((site / "compose.preprod.yml").read_text() == original_compose,
          "Worker rollback did not restore Compose")
    check((site / ".env").read_text() == original_env,
          "Worker rollback changed secrets/image tag")
    check(worker_runtime.read_text() == "running",
          "Worker rollback did not restore running state")
    print("PASS: post-swap failure restores worker, Compose and image tag")

    # Failure during container creation is a partial mutation: attempt a
    # full old-image worker recreation, never just start a possibly removed ID.
    env["FAKE_FAIL_FIRST_UP"] = "0"
    env["FAKE_FAIL_WORKER_CREATE"] = "1"
    worker_runtime.write_text("running")
    events.write_text("")
    attempts.write_text("0")
    (site / "compose.preprod.yml").write_text(original_compose)
    (site / ".env").write_text(original_env)
    broken_create = subprocess.run(["bash", str(root / "isolated.sh"), NEW[4:]],
                                   input=proposed_compose, text=True, capture_output=True,
                                   env=env, timeout=30)
    check(broken_create.returncode != 0, "Partial worker create failure reported success")
    check((site / "compose.preprod.yml").read_text() == original_compose,
          "Partial worker create failure left new Compose")
    check((site / ".env").read_text() == original_env,
          "Partial worker create failure updated release tag")
    check(worker_runtime.read_text() == "running",
          f"Partial worker create rollback did not restart worker: {broken_create.stderr}")
    check(any(" create " in " " + row + " " and row.endswith(OLD)
              for row in events.read_text().splitlines()),
          "Partial worker create failure did not rebuild previous image")
    print("PASS: partial worker creation failure restores previous image and running state")

    # Refuse a pre-existing worker with an image that does not match PHP.
    env["FAKE_FAIL_WORKER_CREATE"] = "0"
    env["FAKE_WORKER_IMAGE_TAG"] = NEW
    worker_runtime.write_text("running")
    events.write_text("")
    attempts.write_text("0")
    invalid = subprocess.run(["bash", str(root / "isolated.sh"), NEW[4:]],
                             input=proposed_compose, text=True, capture_output=True,
                             env=env, timeout=30)
    check(invalid.returncode == 9, f"Wrong-image worker must block: {invalid.stderr}")
    check("Worker image differs" in invalid.stderr, "Missing mismatch explanation")
    check((site / "compose.preprod.yml").read_text() == original_compose,
          "Wrong-image worker changed Compose")
    check(attempts.read_text() == "0", "Wrong-image worker recreated PHP")
    print("PASS: worker image mismatch blocks deployment before mutation")

    # Guard: a stop failure must not replace Compose or application containers.
    # The worker is still running, and the rollback must not kill it.
    env.pop("FAKE_WORKER_IMAGE_TAG", None)
    env["FAKE_FAIL_WORKER_STOP"] = "1"
    env["FAKE_WORKER_STATE"] = "running"
    worker_runtime.write_text("running")
    events.write_text("")
    attempts.write_text("0")
    stop_failed = subprocess.run(["bash", str(root / "isolated.sh"), NEW[4:]],
                                 input=proposed_compose, text=True, capture_output=True,
                                 env=env, timeout=30)
    check(stop_failed.returncode != 0, "Failed worker stop reported success")
    check((site / "compose.preprod.yml").read_text() == original_compose,
          "Failed worker stop swapped Compose")
    check((site / ".env").read_text() == original_env,
          "Failed worker stop changed .env")
    check(attempts.read_text() == "0", "Failed worker stop recreated PHP/Nginx")
    check(worker_runtime.read_text() == "running", "Stop failure left worker stopped")
    print("PASS: worker stop failure aborts before app mutation")
    env.pop("FAKE_FAIL_WORKER_STOP")

    # Image pull fails after worker shutdown. Restore the worker without
    # pointlessly recreating already healthy PHP/Nginx.
    env["FAKE_FAIL_PULL"] = "1"
    worker_runtime.write_text("running")
    events.write_text("")
    attempts.write_text("0")
    pull_failed = subprocess.run(["bash", str(root / "isolated.sh"), NEW[4:]],
                                 input=proposed_compose, text=True, capture_output=True,
                                 env=env, timeout=30)
    check(pull_failed.returncode != 0, "Failed image pull reported success")
    check((site / "compose.preprod.yml").read_text() == original_compose,
          "Failed image pull swapped Compose")
    check((site / ".env").read_text() == original_env,
          "Failed image pull changed .env")
    check(worker_runtime.read_text() == "running",
          "Failed image pull did not restore running worker")
    check(attempts.read_text() == "0", "Failed image pull recreated PHP/Nginx")
    print("PASS: image pull failure restores worker before Compose swap")
    env.pop("FAKE_FAIL_PULL")

    # Worker start fails after the new container has been created; rollback
    # must recreate the previous image and restore its former running state.
    env["FAKE_FAIL_WORKER_START"] = "1"
    env["FAKE_FAIL_START_ONCE"] = "1"
    Path(env["FAKE_FAIL_START_ONCE_FILE"]).unlink(missing_ok=True)
    worker_runtime.write_text("running")
    events.write_text("")
    attempts.write_text("0")
    (site / "compose.preprod.yml").write_text(original_compose)
    (site / ".env").write_text(original_env)
    start_failed = subprocess.run(["bash", str(root / "isolated.sh"), NEW[4:]],
                                  input=proposed_compose, text=True, capture_output=True,
                                  env=env, timeout=30)
    check(start_failed.returncode != 0, "Failed worker start reported success")
    check((site / "compose.preprod.yml").read_text() == original_compose,
          "Failed worker start left new Compose")
    check((site / ".env").read_text() == original_env,
          "Failed worker start changed image tag")
    check(worker_runtime.read_text() == "running",
          f"Failed worker start rollback did not restore worker: {start_failed.stderr}")
    check(any(" create " in " " + row + " " and row.endswith(OLD)
              for row in events.read_text().splitlines()),
          "Failed worker start did not restore old worker image")
    print("PASS: worker start failure restores previous release and state")
