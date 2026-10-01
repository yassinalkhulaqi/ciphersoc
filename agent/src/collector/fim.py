"""File integrity monitoring — SHA256 watchlist, telemetry-only."""
from __future__ import annotations
import hashlib
import json
import os


def _sha256(path: str) -> str | None:
    try:
        h = hashlib.sha256()
        with open(path, "rb") as f:
            for chunk in iter(lambda: f.read(65536), b""):
                h.update(chunk)
        return h.hexdigest()
    except OSError:
        return None


def check_paths(paths: list[str], state_path: str) -> list[dict]:
    """Compare current hashes vs persisted state; emit file_modified events on change."""
    prev: dict = {}
    try:
        with open(state_path) as f:
            prev = json.load(f)
    except (OSError, ValueError):
        prev = {}
    current: dict = {}
    events: list[dict] = []
    for p in paths:
        if not p or not os.path.isfile(p):
            continue
        digest = _sha256(p)
        if digest is None:
            continue
        current[p] = digest
        if p in prev and prev[p] != digest:
            events.append({
                "event_type": "file_modified",
                "file_path": p,
                "hash": digest,
                "hash_type": "sha256",
                "severity": "medium",
                "message": f"File modified: {p}",
            })
        elif p not in prev:
            # First sighting: baseline silently (no alert storm on rollout).
            pass
    try:
        os.makedirs(os.path.dirname(os.path.abspath(state_path)), exist_ok=True)
        with open(state_path, "w") as f:
            json.dump(current, f)
    except OSError:
        pass
    return events
