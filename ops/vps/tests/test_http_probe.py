#!/usr/bin/env python3
"""Offline regression tests for the VPS audit HTTP probe (never contacts VPS)."""
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

AUDIT = Path(__file__).resolve().parents[1] / "yoowii-audit-readonly"
ANCHOR = "# Probe the local HTTP endpoint without following arbitrary redirects."


class HttpProbeTest(unittest.TestCase):
    def check_case(self, status, location, expected_exit, expected_message):
        script = AUDIT.read_text()
        self.assertIn(ANCHOR, script)
        # Execute only the actual HTTP section, not the audit's Docker/SQL checks.
        probe = script[script.index(ANCHOR):]
        shell = (
            "set -Eeuo pipefail\n"
            "FAIL=0; WARN=0\n"
            'ok() { printf "PASS %s\\n" "$1"; }\n'
            'bad() { printf "FAIL %s\\n" "$1"; FAIL=$((FAIL+1)); }\n'
            'warning() { printf "WARN %s\\n" "$1"; WARN=$((WARN+1)); }\n'
            + probe
        )
        with tempfile.TemporaryDirectory() as root:
            fake = Path(root) / "curl"
            fake.write_text(
                "#!/bin/sh\n"
                'printf "%s\\n" "$@" > "$AUDIT_CURL_ARGS"\n'
                'printf "%s\\n" "$MOCK_HTTP_HEADERS"\n'
            )
            fake.chmod(0o700)
            args_path = Path(root) / "args"
            env = {
                **os.environ,
                "PATH": root + os.pathsep + os.environ["PATH"],
                "AUDIT_CURL_ARGS": str(args_path),
                "MOCK_HTTP_HEADERS": (
                    f"HTTP/1.1 {status} Mock\\r\\n"
                    + (f"Location: {location}\\r\\n" if location else "")
                ),
            }
            result = subprocess.run(
                ["bash", "-c", shell], env=env, text=True,
                capture_output=True, check=False, timeout=10,
            )
            self.assertEqual(expected_exit, result.returncode, result.stdout + result.stderr)
            self.assertIn(expected_message, result.stdout)
            args = args_path.read_text().splitlines()
            self.assertIn("Host: preprod.yoowii.com", args)
            self.assertIn("X-Forwarded-Host: preprod.yoowii.com", args)
            self.assertIn("X-Forwarded-Proto: https", args)
            self.assertIn("http://127.0.0.1:8086/fr_FR/", args)
            self.assertNotIn("--location", args)

    def test_http_200_passes(self):
        self.check_case("200", "", 0, "RESULT failures=0 warnings=0")

    def test_known_relative_redirect_warns(self):
        self.check_case("302", "/en_US/", 1, "RESULT failures=0 warnings=1")

    def test_known_absolute_redirect_warns(self):
        self.check_case("302", "http://127.0.0.1:8086/en_US/", 1, "RESULT failures=0 warnings=1")

    def test_unknown_redirect_warns(self):
        self.check_case("302", "/unknown/", 1, "RESULT failures=0 warnings=1")

    def test_http_500_fails(self):
        self.check_case("500", "", 2, "RESULT failures=1 warnings=0")

    def test_http_503_fails(self):
        self.check_case("503", "", 2, "RESULT failures=1 warnings=0")

    def test_missing_http_status_warns(self):
        self.check_case("", "", 1, "RESULT failures=0 warnings=1")


if __name__ == "__main__":
    unittest.main()
