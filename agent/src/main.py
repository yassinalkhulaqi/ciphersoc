"""cipherSOC agent entrypoint."""
from __future__ import annotations
import logging
import sys
import time

from .buffers.spool import EventSpool
from .client import CipherSocClient
from .collector import collect
from .config import AgentConfig
from .heartbeat import HeartbeatLoop
from .utils.sysinfo import host_info

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(name)s %(levelname)s %(message)s")
log = logging.getLogger("ciphersoc-agent")


def run_once(cfg: AgentConfig, client: CipherSocClient, spool: EventSpool, tail_state: dict) -> int:
    # 1. replay buffered events first
    buffered = spool.drain(limit=cfg.batch_size)
    if buffered:
        try:
            accepted, _ = client.send_events(buffered)
            log.info("replayed %d/%d buffered events", accepted, len(buffered))
            if accepted < len(buffered):
                spool.append(buffered[accepted:])
        except PermissionError:
            raise
        except Exception as e:
            log.warning("replay failed, keeping spool: %s", e)
            spool.append(buffered)
            return 0
    # 2. collect fresh
    events = collect(cfg, tail_state)
    if not events:
        return 0
    try:
        accepted, _ = client.send_events(events)
        log.info("sent %d/%d events", accepted, len(events))
        if accepted < len(events):
            spool.append(events[accepted:])
        return accepted
    except PermissionError:
        raise
    except Exception as e:
        log.warning("send failed, spooling %d events: %s", len(events), e)
        spool.append(events)
        return 0


def main(argv: list[str] | None = None) -> int:
    once = "--once" in (argv or sys.argv)
    cfg = AgentConfig.from_env_and_args(argv)
    client = CipherSocClient(cfg)
    try:
        client.register(host_info())
    except Exception as e:
        log.error("registration failed: %s", e)
        return 2
    spool = EventSpool(cfg.spool_path)
    tail_state: dict = {}
    hb = HeartbeatLoop(client, interval=min(60, cfg.interval))
    hb.start()
    try:
        if once:
            run_once(cfg, client, spool, tail_state)
            return 0
        failures = 0
        while True:
            try:
                run_once(cfg, client, spool, tail_state)
                failures = 0
            except PermissionError as e:
                log.error("authentication failure: %s", e)
                return 3
            except Exception as e:
                failures += 1
                log.warning("cycle failed (%d): %s", failures, e)
            time.sleep(cfg.interval)
    except KeyboardInterrupt:
        return 0
    finally:
        hb.stop()


if __name__ == "__main__":
    raise SystemExit(main())
