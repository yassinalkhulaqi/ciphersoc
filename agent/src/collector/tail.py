"""Incremental file tailer with inode rotation handling."""
from __future__ import annotations
import os


class FileTailer:
    def __init__(self, path: str, state: dict):
        self.path = path
        self.state = state  # shared dict persisted per-run: {path: {"ino":..,"pos":..}}

    def read_new_lines(self, max_lines: int = 1000) -> list[str]:
        try:
            st = os.stat(self.path)
        except OSError:
            return []
        key = self.path
        prev = self.state.get(key, {})
        if prev.get("ino") != st.st_ino:
            prev = {"ino": st.st_ino, "pos": 0}
        lines: list[str] = []
        try:
            with open(self.path, "r", encoding="utf-8", errors="replace") as f:
                f.seek(prev.get("pos", 0))
                for _ in range(max_lines):
                    line = f.readline()
                    if not line:
                        break
                    lines.append(line.rstrip("\n"))
                prev["pos"] = f.tell()
        except OSError:
            return []
        self.state[key] = prev
        return lines
