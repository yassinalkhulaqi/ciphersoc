"""Journald/secure fallback + process snapshot collectors (stdlib only)."""
from __future__ import annotations
import glob
import os
import re
import time

AUTH_PATTERNS = [
    (re.compile(r"Failed password for (?:invalid user )?(\S+) from ([0-9.]+)"), "authentication_failure", "medium"),
    (re.compile(r"Accepted (?:password|publickey) for (\S+) from ([0-9.]+)"), "authentication_success", "info"),
]


def collect_auth_fallback(cfg, tail_state: dict, batch: int) -> list[dict]:
    from .tail import FileTailer
    from ..parser.normalize import structure_line
    events: list[dict] = []
    for path in ("/var/log/secure", "/var/log/messages", "/var/log/auth.log"):
        if not os.path.isfile(path):
            continue
        if path in (cfg.auth_log, cfg.syslog):
            continue
        tailer = FileTailer(path, tail_state)
        for line in tailer.read_new_lines(max_lines=batch):
            if line.strip():
                events.append(structure_line(line, cfg.hostname))
        if len(events) >= batch:
            break
    return events


def process_snapshot(hostname: str, limit: int = 25) -> list[dict]:
    """Best-effort /proc snapshot: suspicious cmdlines (nc, nmap, mimikatz strings)."""
    events: list[dict] = []
    suspicious = ("nmap", "nc -", "netcat", "mimikatz", "powershell -enc", "curl http", "wget http")
    try:
        pids = [p for p in os.listdir("/proc") if p.isdigit()][:200]
    except OSError:
        return []
    for pid in pids:
        try:
            with open(f"/proc/{pid}/cmdline", "rb") as f:
                raw = f.read(2000).replace(b"\x00", b" ").decode(errors="ignore").strip()
            if not raw:
                continue
            low = raw.lower()
            if any(s in low for s in suspicious):
                events.append({
                    "event_type": "process_creation",
                    "process_id": int(pid),
                    "command_line": raw[:2000],
                    "hostname": hostname,
                    "severity": "high",
                    "timestamp": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
                    "message": f"suspicious process {pid}: {raw[:200]}",
                })
                if len(events) >= limit:
                    break
        except (OSError, ValueError):
            continue
    return events


def journald_available() -> bool:
    return any(glob.glob("/var/log/journal/*/*") + glob.glob("/run/log/journal/*/*"))
