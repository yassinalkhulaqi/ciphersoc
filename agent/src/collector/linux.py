"""Linux collectors: auth.log (SSH) + syslog (generic) + FIM + host metrics."""
from __future__ import annotations
from .fim import check_paths
from .system_metrics import collect_metrics
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
    # FIM watchlist (comma-separated). Baseline silently on first run.
    fim_raw = getattr(cfg, "fim_paths", "")
    if fim_raw:
        paths = [p.strip() for p in fim_raw.split(",") if p.strip()]
        try:
            events.extend(check_paths(paths, getattr(cfg, "fim_state_path", "./state/fim.json")))
        except Exception:
            pass
    # Host metrics heartbeat (1 event per cycle, cheap).
    if getattr(cfg, "metrics_enabled", True):
        try:
            m = collect_metrics(cfg.hostname)
            if m:
                events.append(m)
        except Exception:
            pass
    return events[: cfg.batch_size]
