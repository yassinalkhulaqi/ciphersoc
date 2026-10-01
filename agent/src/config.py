"""Agent configuration — env vars with CLI overrides. No secrets in source."""
from __future__ import annotations
import argparse
import os
import socket
from dataclasses import dataclass


@dataclass
class AgentConfig:
    server_url: str
    enrollment_token: str
    hostname: str
    interval: int = 30
    batch_size: int = 100
    verify_tls: bool = True
    spool_path: str = "./spool/events.jsonl"
    state_path: str = "./state/agent.json"
    auth_log: str = "/var/log/auth.log"
    syslog: str = "/var/log/syslog"
    agent_version: str = "1.0.0"
    max_retries: int = 5
    fim_paths: str = ""
    fim_state_path: str = "./state/fim.json"
    metrics_enabled: bool = True

    @classmethod
    def from_env_and_args(cls, args: list[str] | None = None) -> "AgentConfig":
        p = argparse.ArgumentParser(prog="ciphersoc-agent")
        p.add_argument("--server", default=os.getenv("CIPHERSOC_URL", "http://localhost:8000/api/v1"))
        p.add_argument("--enrollment-token", default=os.getenv("CIPHERSOC_ENROLLMENT_TOKEN", ""))
        p.add_argument("--hostname", default=os.getenv("CIPHERSOC_HOSTNAME", socket.gethostname()))
        p.add_argument("--interval", type=int, default=int(os.getenv("CIPHERSOC_INTERVAL", "30")))
        p.add_argument("--batch-size", type=int, default=int(os.getenv("CIPHERSOC_BATCH", "100")))
        p.add_argument("--insecure", action="store_true", default=os.getenv("CIPHERSOC_VERIFY_TLS", "true").lower() in ("0", "false", "no"))
        p.add_argument("--spool", default=os.getenv("CIPHERSOC_SPOOL", "./spool/events.jsonl"))
        p.add_argument("--auth-log", default=os.getenv("CIPHERSOC_AUTH_LOG", "/var/log/auth.log"))
        p.add_argument("--syslog", default=os.getenv("CIPHERSOC_SYSLOG", "/var/log/syslog"))
        p.add_argument("--fim-paths", default=os.getenv("CIPHERSOC_FIM_PATHS", ""), help="comma-separated file watchlist for FIM")
        p.add_argument("--fim-state", default=os.getenv("CIPHERSOC_FIM_STATE", "./state/fim.json"))
        p.add_argument("--no-metrics", action="store_true", default=os.getenv("CIPHERSOC_METRICS", "true").lower() in ("0", "false", "no"))
        p.add_argument("--once", action="store_true", help="single collection pass then exit")
        ns = p.parse_args(args)
        if not ns.enrollment_token:
            p.error("enrollment token required (--enrollment-token or CIPHERSOC_ENROLLMENT_TOKEN)")
        return cls(
            server_url=ns.server.rstrip("/"),
            enrollment_token=ns.enrollment_token,
            hostname=ns.hostname,
            interval=max(5, ns.interval),
            batch_size=max(1, min(500, ns.batch_size)),
            verify_tls=not ns.insecure,
            spool_path=ns.spool,
            auth_log=ns.auth_log,
            syslog=ns.syslog,
            fim_paths=ns.fim_paths,
            fim_state_path=ns.fim_state,
            metrics_enabled=not ns.no_metrics,
        )
