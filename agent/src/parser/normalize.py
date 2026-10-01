"""Client-side light structuring — server is authoritative for normalization."""
from __future__ import annotations
import datetime
import re

SSH_FAIL = re.compile(r"Failed password for (?:invalid user )?(\S+) from ([0-9.]+)")
SSH_OK = re.compile(r"Accepted (?:password|publickey) for (\S+) from ([0-9.]+)")


def structure_line(line: str, hostname: str) -> dict:
    event: dict = {"message": line[:2000], "hostname": hostname,
                   "timestamp": datetime.datetime.now(datetime.timezone.utc).isoformat()}
    m = SSH_FAIL.search(line)
    if m:
        event.update({"event_type": "authentication_failure", "username": m.group(1),
                      "source_ip": m.group(2), "action": "login", "status": "failure", "severity": "medium"})
        return event
    m = SSH_OK.search(line)
    if m:
        event.update({"event_type": "authentication_success", "username": m.group(1),
                      "source_ip": m.group(2), "action": "login", "status": "success", "severity": "info"})
        return event
    if "sudo:" in line:
        event.update({"event_type": "privilege_escalation", "severity": "medium"})
        return event
    event.update({"event_type": "system", "severity": "info"})
    return event
