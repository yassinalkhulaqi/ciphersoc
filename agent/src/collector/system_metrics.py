"""Lightweight host metrics via /proc (Linux) — no third-party deps."""
from __future__ import annotations
import os
import time


def _loadavg() -> float | None:
    try:
        return os.getloadavg()[0]
    except OSError:
        return None


def _meminfo() -> dict:
    out: dict = {}
    try:
        with open("/proc/meminfo") as f:
            for line in f:
                if line.startswith("MemTotal:"):
                    out["mem_total_kb"] = int(line.split()[1])
                elif line.startswith("MemAvailable:"):
                    out["mem_available_kb"] = int(line.split()[1])
    except OSError:
        pass
    return out


def collect_metrics(hostname: str) -> dict | None:
    mem = _meminfo()
    evt: dict = {
        "event_type": "host_metrics",
        "hostname": hostname,
        "severity": "info",
        "timestamp": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
        "message": f"host metrics load={_loadavg()}",
        "load_1m": _loadavg(),
    }
    evt.update(mem)
    if mem.get("mem_total_kb") and mem.get("mem_available_kb"):
        used_pct = 100.0 * (mem["mem_total_kb"] - mem["mem_available_kb"]) / mem["mem_total_kb"]
        evt["mem_used_pct"] = round(used_pct, 1)
        if used_pct > 90:
            evt["severity"] = "medium"
    return evt
