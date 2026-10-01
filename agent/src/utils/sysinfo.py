"""Host metadata for registration."""
from __future__ import annotations
import platform
import socket


def host_info() -> dict:
    ip = ""
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.connect(("8.8.8.8", 80))
        ip = s.getsockname()[0]
        s.close()
    except OSError:
        ip = ""
    return {"os": platform.system(), "os_version": platform.version(), "arch": platform.machine(), "ip": ip}
