# Onboarding (5 minutes)

1. `cp .env.example .env` — fill `APP_KEY` (`php artisan key:generate --show`), `DB_PASSWORD`, enrollment token.
2. `bash scripts/setup.sh`
3. `bash scripts/check.sh` — backend + frontend + agent tests.
4. Docker: `docker compose up -d --build && docker compose exec backend php artisan migrate --force --seed`
5. UI `http://localhost:8081`, API `http://localhost:8081/api`, health `GET /api/health`.
6. Demo logins in `docs/development.md` (dev/seed only).
7. Agent once: `CIPHERSOC_URL=http://localhost:8000/api/v1 CIPHERSOC_ENROLLMENT_TOKEN=... python -m src.main --once` from `agent/`.

Next: `ROADMAP.md`, `docs/architecture.md`, `docs/api.md`, `CONTRIBUTING.md`.
