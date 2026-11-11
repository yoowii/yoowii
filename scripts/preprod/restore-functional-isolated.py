#!/usr/bin/env python3
"""Restore a Yoowii backup ONLY on a disposable host (no Plesk, no live Docker).

The script checks the backed-up SHA256 manifest, imports the real SQL dump
into a network-disconnected disposable MySQL 8.4 container, safely extracts
media and private assets to private temporary directories, and checks counts.
It does not run Sylius or make any API, supplier, mail, or HTTP calls.
"""
import argparse
import hashlib
import os
from pathlib import Path, PurePosixPath
import re
import secrets
import shutil
import subprocess
import sys
import tarfile
import tempfile
import time


FILES = (
    "mysql.sql.gz",
    "media.tar.gz",
    "private_assets.tar.gz",
    "compose.preprod.yml",
    "preprod.env",
)


def run(argv, **kwargs):
    return subprocess.run(argv, check=True, **kwargs)


def refuse_live_host():
    if Path("/opt/yoowii/preprod").exists():
        raise RuntimeError("REFUSED: Yoowii preproduction directory exists on this host")
    active = run(
        ["docker", "ps", "-q", "--filter", "label=com.docker.compose.project=yoowii-preprod"],
        capture_output=True, text=True,
    )
    if active.stdout.strip():
        raise RuntimeError("REFUSED: active Yoowii preproduction containers detected")


def verify_manifest(backup):
    rows = (backup / "SHA256SUMS").read_text(encoding="utf-8").splitlines()
    recorded = {}
    for row in rows:
        match = re.fullmatch(r"([a-fA-F0-9]{64})  ([^\s/]+)", row)
        if not match:
            raise RuntimeError("Invalid SHA256SUMS entry")
        digest, filename = match.groups()
        if filename in recorded:
            raise RuntimeError("Duplicate SHA256 entry")
        recorded[filename] = digest.lower()
    if set(recorded) != set(FILES):
        raise RuntimeError("SHA256SUMS does not list precisely the required archives")
    for filename, expected in recorded.items():
        source = backup / filename
        if not source.is_file() or source.is_symlink():
            raise RuntimeError("Required regular file missing")
        digest = hashlib.file_digest(source.open("rb"), "sha256").hexdigest()
        if digest != expected:
            raise RuntimeError("Checksum mismatch: " + filename)
    print("PASS: backup SHA256 manifest")


def safe_extract(archive, output):
    total = 0
    with tarfile.open(archive, "r:gz") as tar:
        for item in tar:
            name = PurePosixPath(item.name)
            if item.name.startswith("/") or ".." in name.parts or "\\" in item.name:
                raise RuntimeError("Unsafe path in tar archive")
            if item.issym() or item.islnk() or not (item.isfile() or item.isdir()):
                raise RuntimeError("Unsupported link or special file in tar archive")
            if item.isfile():
                total += 1
        # Explicit extraction into private throwaway storage, without preserving
        # owners/modes and with links rejected above.
        for item in tar.getmembers():
            target = output.joinpath(*PurePosixPath(item.name).parts)
            if item.isdir():
                target.mkdir(parents=True, exist_ok=True)
            elif item.isfile():
                target.parent.mkdir(parents=True, exist_ok=True)
                stream = tar.extractfile(item)
                if stream is None:
                    raise RuntimeError("Missing tar member data")
                with stream, target.open("xb") as dst:
                    shutil.copyfileobj(stream, dst)
    return total


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--backup-dir", required=True, type=Path)
    parser.add_argument("--ack-isolated-host", action="store_true")
    args = parser.parse_args()
    if not args.ack_isolated_host:
        parser.error("Explicit --ack-isolated-host is required")
    if os.geteuid() == 0:
        raise RuntimeError("REFUSED: run as a non-root user on the disposable host")
    refuse_live_host()
    backup = args.backup_dir.resolve(strict=True)
    verify_manifest(backup)

    scratch = Path(tempfile.mkdtemp(prefix="yoowii-restore-disposable-"))
    os.chmod(scratch, 0o700)
    container = "yoowii-restore-" + secrets.token_hex(6)
    password = secrets.token_urlsafe(32)
    created = False

    try:
        media = scratch / "media"
        private = scratch / "private"
        mysql_data = scratch / "mysql"
        for directory in (media, private, mysql_data):
            directory.mkdir(mode=0o700)

        media_count = safe_extract(backup / "media.tar.gz", media)
        private_count = safe_extract(backup / "private_assets.tar.gz", private)
        print("PASS: media and private artwork extracted into disposable directories")
        print(f"INFO: restored media files={media_count}, private files={private_count}")

        # Deliberately no published ports, no external network, no links to
        # preproduction volumes. The container cannot contact providers.
        run([
            "docker", "run", "--rm", "-d", "--network", "none",
            "--name", container,
            "--mount", f"type=bind,src={mysql_data},dst=/var/lib/mysql",
            "-e", "MYSQL_DATABASE=yoowii_restore_test",
            "-e", f"MYSQL_ROOT_PASSWORD={password}",
            "mysql:8.4",
        ], stdout=subprocess.DEVNULL)
        created = True
        mysql = [
            "docker", "exec", "-i", "-e", f"MYSQL_PWD={password}", container,
            "mysql", "-uroot", "--protocol=socket", "yoowii_restore_test",
        ]
        for _ in range(90):
            ping = subprocess.run(
                mysql + ["-N", "-e", "SELECT 1"],
                stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
            )
            if ping.returncode == 0:
                break
            time.sleep(1)
        else:
            raise RuntimeError("Disposable MySQL did not become ready")

        with (backup / "mysql.sql.gz").open("rb") as compressed:
            import gzip
            with gzip.GzipFile(fileobj=compressed) as sql:
                process = subprocess.Popen(mysql, stdin=subprocess.PIPE, stdout=subprocess.DEVNULL,
                                           stderr=subprocess.DEVNULL)
                try:
                    assert process.stdin is not None
                    shutil.copyfileobj(sql, process.stdin)
                    process.stdin.close()
                    status = process.wait(timeout=180)
                except BaseException:
                    process.kill()
                    process.wait()
                    raise
                if status != 0:
                    raise RuntimeError("SQL import failed in disposable MySQL")

        output = run(mysql + [
            "-N", "-B", "-e",
            "SELECT COUNT(*) FROM information_schema.tables "
            "WHERE table_schema = 'yoowii_restore_test';",
        ], capture_output=True, text=True)
        table_count = int(output.stdout.strip())
        if table_count == 0:
            raise RuntimeError("SQL import produced no tables")
        print(f"PASS: MySQL 8.4 SQL dump imported to disposable database ({table_count} tables)")
        print("PASS: isolated file and database restore drill")
        print("LIMIT: no Sylius boot test; no consistency proof of cross-file references")
    finally:
        if created:
            subprocess.run(["docker", "rm", "-f", container],
                           stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        shutil.rmtree(scratch, ignore_errors=True)


if __name__ == "__main__":
    try:
        main()
    except (RuntimeError, OSError, subprocess.CalledProcessError, tarfile.TarError) as exc:
        print(f"RESTORE CHECK FAILED: {exc}", file=sys.stderr)
        sys.exit(1)
