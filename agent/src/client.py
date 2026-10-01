"""HTTPS client for cipherSOC agent API — register / heartbeat / ingest with retries."""
from __future__ import annotations
import json
import logging
import os
import time
from dataclasses import dataclass

import requests

from .config import AgentConfig

log = logging.getLogger("ciphersoc-agent")


@dataclass
class AgentIdentity:
    agent_id: str
    api_token: str


class CipherSocClient:
    def __init__(self, cfg: AgentConfig):
        self.cfg = cfg
        self.session = requests.Session()
        self.session.verify = cfg.verify_tls
        self.session.headers.update({"Content-Type": "application/json", "User-Agent": f"cipherSOC-agent/{cfg.agent_version}"})
        self.identity: AgentIdentity | None = self._load_identity()

    # -- persistence (0600) --
    def _load_identity(self) -> AgentIdentity | None:
        try:
            with open(self.cfg.state_path) as f:
                d = json.load(f)
            return AgentIdentity(agent_id=d["agent_id"], api_token=d["api_token"])
        except (OSError, KeyError, ValueError):
            return None

    def _save_identity(self) -> None:
        os.makedirs(os.path.dirname(os.path.abspath(self.cfg.state_path)), exist_ok=True)
        fd = os.open(self.cfg.state_path, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
        with os.fdopen(fd, "w") as f:
            json.dump({"agent_id": self.identity.agent_id, "api_token": self.identity.api_token}, f)

    def _headers(self) -> dict:
        assert self.identity, "not registered"
        return {"X-Agent-ID": self.identity.agent_id, "Authorization": f"Bearer {self.identity.api_token}"}

    def _post(self, path: str, payload: dict, authenticated: bool = True) -> requests.Response:
        last: Exception | None = None
        for attempt in range(1, self.cfg.max_retries + 1):
            try:
                r = self.session.post(
                    self.cfg.server_url + path, json=payload,
                    headers=self._headers() if authenticated else None, timeout=10,
                )
                if r.status_code in (401, 403):
                    return r  # auth failures must not be retried blindly
                if r.status_code < 500:
                    return r
                last = RuntimeError(f"server {r.status_code}")
            except (requests.ConnectionError, requests.Timeout) as e:
                last = e
            time.sleep(min(2 ** attempt, 15))
        raise RuntimeError(f"request failed after retries: {last}")

    def register(self, os_info: dict) -> AgentIdentity:
        if self.identity:
            return self.identity
        r = self._post("/agents/register", {
            "hostname": self.cfg.hostname,
            "os": os_info.get("os"), "os_version": os_info.get("os_version"),
            "arch": os_info.get("arch"), "ip_address": os_info.get("ip"),
            "agent_version": self.cfg.agent_version,
            "enrollment_token": self.cfg.enrollment_token,
        }, authenticated=False)
        if r.status_code != 200 or not r.json().get("success"):
            raise RuntimeError(f"registration failed: {r.status_code} {r.text[:200]}")
        data = r.json()["data"]
        self.identity = AgentIdentity(agent_id=data["agent_id"], api_token=data["api_token"])
        self._save_identity()
        log.info("registered as %s", self.identity.agent_id)
        return self.identity

    def heartbeat(self, metadata: dict | None = None) -> bool:
        try:
            r = self._post("/agents/heartbeat", {"agent_version": self.cfg.agent_version, "metadata": metadata or {}})
            return r.status_code == 200
        except Exception as e:
            log.warning("heartbeat failed: %s", e)
            return False

    def send_events(self, events: list[dict]) -> tuple[int, str]:
        """Returns (accepted_count, raw_response). Raises on auth failure."""
        r = self._post("/ingest/events", {"events": events})
        if r.status_code in (401, 403):
            raise PermissionError(f"ingest rejected: {r.text[:200]}")
        body = r.json()
        return int(body.get("data", {}).get("accepted", 0)), r.text[:500]
