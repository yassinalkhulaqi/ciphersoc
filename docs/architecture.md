# cipherSOC Architecture

## Topology

```
                ┌──────────────────────┐
                │  React SPA (Vite+TS) │  cipherSOC UI — 14 modules, TanStack Query, Echo/Reverb WS
                └──────────┬───────────┘
                   REST /api/v1 + WS /app
                ┌──────────▼───────────┐
                │  Nginx reverse proxy │  SPA + /api/* → backend:8000, /app/* → websocket:8080 (Upgrade)
                └──────────┬───────────┘
                ┌──────────▼───────────┐
                │  Laravel 13 API      │  Sanctum auth · CheckPermission RBAC · AgentAuth (sha256 bearer)
                │  Services: Ingestion │  EventNormalizer (generic/syslog/CEF/Windows parsers)
                │  DetectionEngine     │  DB rules, threshold/correlation/ioc_match, dedup+aggregation
                │  RiskScorer          │  transparent factors (severity/TI/repeat/hosts/confidence/MITRE)
                │  EnrichmentService   │  VT/AbuseIPDB/OTX/URLhaus adapters, 24h cache, mock-when-unconfigured
                │  ReportService       │  DomPDF, queued
                └──────────┬───────────┘
        ┌──────────────────┼──────────────────┐
        ▼                  ▼                  ▼
  PostgreSQL          Redis               Reverb WS
  (events/alerts/…;   (queue/cache/       (private soc.alerts/
   jsonb, indexes)     sessions)           incidents/agents)
        ▲
  queue-worker (detection/enrich/reports) · scheduler (health/expire/prune)
        ▲
  Python agent → POST /ingest/events (batched, spooled offline, heartbeats)
```

## Request pipeline (INGEST→AUDIT)

`AgentAuth` → validate (≤500/batch, throttle) → `EventNormalizer` → store (raw+normalized, processing_status) → `ProcessIngestedEvent` job → `DetectionEngine::evaluateEvent` → dedup/aggregate by `dedup_key` (rule+event+host+ip+user) → `AlertCreated` broadcast (best-effort via `Support\Broadcasts`) → `EnrichAlertIndicators` job → audit.

## Key decisions

- RBAC native (`roles/permissions` + `perm:` middleware + `SocPolicy`); admin bypass. Frontend hiding only for UX.
- PostgreSQL in Docker; SQLite for local dev/tests (dashboard uses driver-agnostic bucketing).
- Broadcasts are best-effort (`Support\Broadcasts::fire`) — a down WS server never 500s the API (§62).
- Threat intel is SSRF-safe: indicator strings only, allowlisted provider hosts, 8s timeouts, no arbitrary URL fetch.
- IOC values normalized (IP/domain/hash/URL) with unique `(type, normalized_value)`.
