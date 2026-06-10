#!/usr/bin/env python3
"""GitHub push webhook → production deploy. Listens on 127.0.0.1:9876."""
from __future__ import annotations

import hashlib
import hmac
import json
import subprocess
import sys
from http.server import BaseHTTPRequestHandler, HTTPServer
from pathlib import Path

APP_DIR = Path("/www/wwwroot/innovatealabama.c3tech.app")
SECRET_FILE = APP_DIR / ".deploy-webhook-secret"
DEPLOY_SCRIPT = APP_DIR / "bin/deploy.sh"
HOST = "127.0.0.1"
PORT = 9876


def read_secret() -> bytes:
    return SECRET_FILE.read_bytes().strip()


class Handler(BaseHTTPRequestHandler):
    def log_message(self, fmt: str, *args) -> None:
        sys.stdout.write("[webhook] " + (fmt % args) + "\n")
        sys.stdout.flush()

    def do_POST(self) -> None:
        length = int(self.headers.get("Content-Length", 0))
        body = self.rfile.read(length)
        signature = self.headers.get("X-Hub-Signature-256", "")

        if not signature.startswith("sha256="):
            self.send_error(401, "missing signature")
            return

        expected = "sha256=" + hmac.new(read_secret(), body, hashlib.sha256).hexdigest()
        if not hmac.compare_digest(signature, expected):
            self.send_error(401, "invalid signature")
            return

        payload = json.loads(body.decode("utf-8"))
        ref = payload.get("ref", "")
        if ref != "refs/heads/main":
            self.send_response(200)
            self.end_headers()
            self.wfile.write(b"ignored: not main")
            return

        self.log_message("deploy triggered for %s", ref)
        subprocess.Popen(
            ["/bin/bash", str(DEPLOY_SCRIPT)],
            cwd=APP_DIR,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )

        self.send_response(202)
        self.end_headers()
        self.wfile.write(b"deploy started")

    def do_GET(self) -> None:
        self.send_response(200)
        self.end_headers()
        self.wfile.write(b"ok")


if __name__ == "__main__":
    HTTPServer((HOST, PORT), Handler).serve_forever()
