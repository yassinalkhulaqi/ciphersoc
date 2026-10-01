#!/usr/bin/env bash
set -euo pipefail
echo "== backend =="
(cd backend && composer install --no-interaction --prefer-dist)
(cd backend && vendor/bin/pint --test)
(cd backend && php artisan test)
echo "== frontend =="
(cd frontend && npm ci)
(cd frontend && npx oxlint)
(cd frontend && npx vitest run)
echo "== agent =="
(python3 -m pip install -r agent/requirements.txt)
(python3 -m pytest agent -q)
echo "== compose =="
docker compose config >/dev/null
echo "All checks passed."
