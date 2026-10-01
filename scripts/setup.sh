#!/usr/bin/env bash
set -euo pipefail
# cipherSOC local setup (without Docker)
echo "== backend =="
(cd backend && composer install && php -r "file_exists('.env') || copy('.env.example','.env');")
(cd backend && php artisan key:generate && php artisan migrate --force && php artisan db:seed --force)
echo "== frontend =="
(cd frontend && npm install)
echo "== agent =="
(python3 -m pip install -r agent/requirements.txt)
echo "Done. Start backend: (cd backend && php artisan serve). Frontend: (cd frontend && npm run dev)."
