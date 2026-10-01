# cipherSOC 🛡️ — mini Security Operations Center

**cipherSOC** is a production-oriented mini-SOC platform: log ingestion → normalization → detection → alerts → enrichment → incidents → audit → PDF reports, with real-time WebSocket updates and a lightweight Python endpoint agent. Dark analyst-console UI, Laravel API, PostgreSQL, Redis queues, Reverb WebSockets, Docker deploy.

```
Agent → Ingestion → Normalize → Store → Detect → Correlate → Alert → Broadcast → UI
                       reinforces: enrich (TI) → triage → investigate → contain → resolve → audit
```

## Quick start (Docker)

```bash
cp .env.example .env   # fill APP_KEY (php artisan key:generate --show) + enrollment token
docker compose up -d --build
docker compose exec backend php artisan migrate --force --seed
# UI: http://localhost:8081   API: http://localhost:8081/api
```

Demo logins (dev/seed only): `admin@ciphersoc.local`, `manager@ciphersoc.local`, `analyst@ciphersoc.local`, `viewer@ciphersoc.local` — passwords in `docs/development.md`.

## Local development

```bash
bash scripts/setup.sh
(cd backend && php artisan serve)          # :8000
(cd backend && php artisan reverb:start)   # :8080 websockets
(cd backend && php artisan queue:work)     # jobs: detection/enrichment/reports
(cd frontend && npm run dev)               # :5173 (VITE_API_URL=http://localhost:8000/api/v1)
CIPHERSOC_URL=http://localhost:8000/api/v1 CIPHERSOC_ENROLLMENT_TOKEN=ciphersoc-enroll-dev-token python -m src.main --once  # from agent/
```

## Repo layout

```
cipherSOC/
  frontend/   React+TS+Vite SOC console (14 modules)
  backend/    Laravel API: auth/RBAC, ingestion, detection, alerts, incidents, IOC, TI, MITRE, agents, audit, reports, WS
  agent/      Python endpoint agent (Linux/Windows) + pytest suite
  nginx/      reverse proxy (API + WS upgrade + SPA)
  docs/       architecture, api, detection-engine, agent, threat-intelligence, security, deployment, development
  scripts/    setup.sh, demo.sh
```

## API (curl)

```bash
# login
curl -s localhost:8000/api/v1/auth/login -H 'Content-Type: application/json' \
  -d '{"email":"admin@ciphersoc.local","password":"..."}'
# agent ingest
curl -X POST localhost:8000/api/v1/ingest/events \
  -H "Authorization: Bearer <AGENT_TOKEN>" -H "X-Agent-ID: <AGENT_ID>" -H 'Content-Type: application/json' \
  -d '{"events":[{"message":"Nov 12 10:11:12 web-01 sshd[1]: Failed password for root from 10.0.0.1 port 22 ssh2"}]}'
```

Full docs: [`docs/`](docs/) · OpenAPI JSON: `GET /api/openapi.json`.
