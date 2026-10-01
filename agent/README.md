# cipherSOC Agent

Lightweight Python telemetry agent for the cipherSOC platform (Linux + Windows).

## What it does (telemetry only — never executes server commands)

1. Registers with cipherSOC (`POST /api/v1/agents/register`) using an enrollment token
2. Authenticates with the issued bearer token (`X-Agent-ID` + `Authorization`)
3. Sends heartbeats every N seconds
4. Tails log sources, batches structured events, POSTs to `/api/v1/ingest/events`
5. Buffers to a local JSONL spool when offline; replays on reconnect with retries

## Quick start

```bash
pip install -r requirements.txt
export CIPHERSOC_URL=http://localhost:8000/api/v1
export CIPHERSOC_ENROLLMENT_TOKEN=ciphersoc-enroll-dev-token
python -m src.main --once        # single collection pass (demo)
python -m src.main               # daemon loop
pytest -q                        # unit tests
```

## Configuration (env wins over flags)

| Env | Flag | Default |
|---|---|---|
| `CIPHERSOC_URL` | `--server` | `http://localhost:8000/api/v1` |
| `CIPHERSOC_ENROLLMENT_TOKEN` | `--enrollment-token` | — (required) |
| `CIPHERSOC_HOSTNAME` | `--hostname` | system hostname |
| `CIPHERSOC_INTERVAL` | `--interval` | `30` seconds |
| `CIPHERSOC_BATCH` | `--batch-size` | `100` |
| `CIPHERSOC_VERIFY_TLS` | `--insecure` (invert) | `true` |
| `CIPHERSOC_SPOOL` | `--spool` | `./spool/events.jsonl` |
| `CIPHERSOC_AUTH_LOG` | `--auth-log` | `/var/log/auth.log` |
| `CIPHERSOC_SYSLOG` | `--syslog` | `/var/log/syslog` |
| `CIPHERSOC_FIM_PATHS` | `--fim-paths` | `` (comma-separated watchlist, e.g. `/etc/passwd,/etc/shadow`) |
| `CIPHERSOC_FIM_STATE` | `--fim-state` | `./state/fim.json` |
| `CIPHERSOC_METRICS` | `--no-metrics` (invert) | `true` |

FIM baselines silently on first run, then emits `file_modified` on SHA256 change.
Host metrics (`host_metrics` with load + mem) are sent once per cycle when enabled.

State (agent id + api token) is stored in `./state/agent.json` with `0600` permissions.
