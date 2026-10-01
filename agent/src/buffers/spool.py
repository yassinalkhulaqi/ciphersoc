"""Durable disk spool: newline-delimited JSON. Corrupt lines are quarantined, never fatal."""
from __future__ import annotations
import json
import os


class EventSpool:
    def __init__(self, path: str):
        self.path = path
        parent = os.path.dirname(os.path.abspath(path))
        if parent:
            os.makedirs(parent, exist_ok=True)

    def append(self, events: list[dict]) -> int:
        if not events:
            return 0
        with open(self.path, "a", encoding="utf-8") as f:
            for e in events:
                f.write(json.dumps(e, default=str) + "\n")
        return len(events)

    def drain(self, limit: int = 500) -> list[dict]:
        """Read up to `limit` valid events; quarantines corrupt lines aside."""
        if not os.path.exists(self.path):
            return []
        kept: list[str] = []
        out: list[dict] = []
        bad = 0
        with open(self.path, encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                if not line:
                    continue
                if len(out) >= limit:
                    kept.append(line)
                    continue
                try:
                    out.append(json.loads(line))
                except ValueError:
                    bad += 1
        if kept or bad or out:
            tmp = self.path + ".tmp"
            with open(tmp, "w", encoding="utf-8") as f:
                for line in kept:
                    f.write(line + "\n")
            os.replace(tmp, self.path)
        return out

    def size(self) -> int:
        try:
            return os.path.getsize(self.path)
        except OSError:
            return 0
