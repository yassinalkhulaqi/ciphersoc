"""Linux collectors: auth.log (SSH) + syslog (generic)."""
from __future__ import annotations
from .tail import FileTailer
from ..parser.normalize import structure_line


def collect_linux(cfg, tail_state: dict) -> list[dict]:
    events: list[dict] = []
    for path in (cfg.auth_log, cfg.syslog):
        tailer = FileTailer(path, tail_state)
        for line in tailer.read_new_lines(max_lines=cfg.batch_size):
            if line.strip():
                events.append(structure_line(line, cfg.hostname))
        if len(events) >= cfg.batch_size:
            break
    return events[: cfg.batch_size]
