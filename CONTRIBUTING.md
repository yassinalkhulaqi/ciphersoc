# Contributing

## Quick start
```bash
cp .env.example .env
bash scripts/setup.sh
bash scripts/check.sh
```

## Branches
- `main` is protected. Create `feat/<scope>-<short>` branches.
- One logical change per PR. Run `scripts/check.sh` before pushing.

## Checks (also in CI)
- Backend: `composer install && vendor/bin/pint --test && php artisan test`
- Frontend: `npm ci && npx oxlint && npx vitest run && npm run build`
- Agent: `pip install -r agent/requirements.txt && python -m pytest agent -q`
- Compose: `docker compose config`

## Conventions
- Laravel: Pint style, `ApiResponse` envelope `{success,data,message,meta}`, `perm:` middleware for new routes, audit sensitive actions.
- Frontend: TanStack Query + `getPage`, `Guard perm=` for new routes, dark console styles in `styles.css`.
- Agent: never execute server commands, telemetry upload only, spool offline on failure.
- Docs: update `docs/` + `ROADMAP.md` when behavior changes.
