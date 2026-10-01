# cipherSOC Agent

`agent/src/`: `main.py` (loop) · `config.py` (env+flags) · `client.py` (register/heartbeat/ingest, exp-backoff, 0600 state) · `heartbeat.py` (thread) · `collector/` (linux tail auth.log/syslog, windows pywin32-or-export fallback) · `parser/` (SSH structuring; server authoritative) · `buffers/spool.py` (JSONL offline spool, corrupt-line quarantine) · `utils/sysinfo.py`.

Never executes server commands — telemetry upload only. Enrollment: `CIPHERSOC_ENROLLMENT_TOKEN` must match server. Replay protection: short-lived batch POSTs over TLS; per-agent bearer rotate by re-register (old token hash replaced).
