"""Heartbeat loop thread."""
from __future__ import annotations
import threading
import time


class HeartbeatLoop(threading.Thread):
    daemon = True

    def __init__(self, client, interval: int = 60):
        super().__init__(name="ciphersoc-heartbeat")
        self.client = client
        self.interval = interval
        self._stop = threading.Event()

    def run(self) -> None:
        while not self._stop.wait(self.interval):
            self.client.heartbeat()

    def stop(self) -> None:
        self._stop.set()
