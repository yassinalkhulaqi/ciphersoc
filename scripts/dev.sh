#!/usr/bin/env bash
set -euo pipefail
# Local dev: backend :8000 + reverb :8080 + queue + frontend :5173
trap 'kill 0' EXIT
(cd backend && php artisan serve --host=0.0.0.0 --port=8000) &
(cd backend && php artisan reverb:start --host=0.0.0.0 --port=8080) &
(cd backend && php artisan queue:work --sleep=3 --tries=3 --timeout=120) &
(cd frontend && npm run dev) &
wait
