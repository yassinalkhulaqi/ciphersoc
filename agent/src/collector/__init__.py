"""Platform-dispatching collector."""
from __future__ import annotations
import platform


def collect(cfg, tail_state: dict) -> list[dict]:
    if platform.system().lower().startswith("win"):
        from .windows import collect_windows
        return collect_windows(cfg, tail_state)
    from .linux import collect_linux
    return collect_linux(cfg, tail_state)
