# Runbook

## Symptoms → actions

### Queue growing (`queue_pending` rising in GET /api/health)
1. `docker compose logs -f queue-worker`
2. `docker compose exec backend php artisan queue:failed`
3. Scale: `docker compose up -d --scale queue-worker=3` (compose) or HPA (k8s).
4. Check Redis: `docker compose exec redis redis-cli info`.

### No events from agent
1. Check agent logs, `spool/events.jsonl` size (offline buffering normal).
2. `POST /api/v1/agents/heartbeat` 401/403 → re-register (enrollment token rotated?).
3. Backend: `docker compose logs backend | grep ingest`.
4. Validate: `curl -X POST /api/v1/ingest/events` with `X-Agent-ID` + Bearer.

### WS not updating UI
Broadcasts are best-effort (`Support\Broadcasts::fire`). Check `websocket` service, then `nginx /app/` proxy, then Reverb creds.

### Disk full (postgres)
Run retention: `php artisan events:prune --days=90 --chunk=5000`. Check `pgdata` volume, enable PITR backups.

### TI lookup mock-flagged
Keys absent → `configuration_required` + `mock` results. Set `VIRUSTOTAL_API_KEY`, `ABUSEIPDB_API_KEY`, `OTX_API_KEY`.

## Useful commands
```bash
docker compose logs -f backend queue-worker scheduler websocket
docker compose exec backend php artisan migrate --force
docker compose exec backend php artisan events:prune --days=90
docker compose exec backend php artisan queue:failed
bash scripts/check.sh
```
