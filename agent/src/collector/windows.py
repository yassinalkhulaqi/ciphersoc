"""Windows Event Log abstraction.

Uses pywin32 (win32evtlog) when available; otherwise falls back to a
line-based export file (e.g. `wevtutil qe Security /f:text > sec.txt`)
pointed at by CIPHERSOC_WINDOWS_EXPORT. Never requires unsafe changes.
"""
from __future__ import annotations
import os

from ..parser.normalize import structure_line


def collect_windows(cfg, tail_state: dict) -> list[dict]:
    try:
        import win32evtlog  # type: ignore
        return _collect_win32(cfg, win32evtlog)
    except ImportError:
        export = os.getenv("CIPHERSOC_WINDOWS_EXPORT", "")
        if export and os.path.exists(export):
            from .tail import FileTailer
            return [structure_line(l, cfg.hostname) for l in FileTailer(export, tail_state).read_new_lines(cfg.batch_size) if l.strip()]
        return []


def _collect_win32(cfg, win32evtlog) -> list[dict]:
    events: list[dict] = []
    for logtype in ("Security", "System"):
        try:
            hand = win32evtlog.OpenEventLog(None, logtype)
            flags = win32evtlog.EVENTLOG_BACKWARDS_READ | win32evtlog.EVENTLOG_SEQUENTIAL_READ
            records = win32evtlog.ReadEventLog(hand, flags, 0)
            for r in (records or [])[: cfg.batch_size]:
                events.append({
                    "event_type": "windows_event",
                    "EventID": r.EventID & 0xFFFF,
                    "Computer": r.ComputerName,
                    "message": (r.StringInserts and " ".join(str(s) for s in r.StringInserts) or f"Event {r.EventID}")[:2000],
                    "hostname": cfg.hostname,
                })
        except Exception:
            continue
    return events[: cfg.batch_size]
