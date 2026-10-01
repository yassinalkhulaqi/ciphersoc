# cipherSOC API

Base: `/api/v1` · Auth: `Authorization: Bearer <sanctum>` (UI) or `X-Agent-ID` + Bearer (agent).
Envelope: `{success, data, message, meta}` · Errors: `{success:false, message, errors}`.
OpenAPI snapshot: `GET /api/openapi.json`.

## Families

| Prefix | Notes |
|---|---|
| `/auth/*` | login/logout/me/profile/change-password/forgot/reset (throttle 30/min) |
| `/dashboard/overview?range=15m\|1h\|24h\|7d` | totals, severity/status, alerts-over-time, top IPs/rules/MITRE, recents |
| `/events`, `/events/{id}` | filters: event_type, severity, hostname, username, source_ip, search, from/to |
| `/alerts`, `/alerts/{id}`, `/alerts/bulk`, `/{id}/assign`, `/{id}/comments` | statuses: new→acknowledged→investigating→escalated→resolved/closed/false_positive |
| `/incidents…` | open→investigating→containment→eradication→recovery→resolved→closed; attach alerts/IOCs, comments, timeline |
| `/rules`, `/rules/{id}/test` | rule builder + dry-run preview vs last 200 events; versions tracked |
| `/iocs`, `/iocs/{id}/enrich` | dedup normalized; async enrichment |
| `/threat-intel/providers`, `/lookup`, `/results` | mock-flagged when keys absent |
| `/mitre/tactics`, `/techniques`, `/techniques/{id}` | alert/incident counts per technique |
| `/agents`, `/agents/{id}`, `/hosts` | inventory + heartbeat health |
| `/agents/register`, `/agents/heartbeat`, `/ingest/events` | agent auth, ≤500 events/batch |
| `/users`, `/roles` | admin/manager |
| `/audit-logs` | filter action/resource/actor/date |
| `/reports`, `/reports/{id}/download` | queued PDF |
| `/notifications`, `/notifications/preferences` | in-app + WS |
| `/settings` | key/value groups |
| `GET /health`, `GET /openapi.json` | ops + docs (no auth) |

## Search grammar (events/alerts)

Structured filters compose: `source_ip:`, `hostname:`, `severity:`, `status:`, `rule:`, `mitre:T1110`, `ioc:` — today implemented as query params + full-text `search`; indexed columns keep it fast, designed for a query-parser upgrade later.
