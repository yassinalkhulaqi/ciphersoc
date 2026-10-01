# Development

```bash
bash scripts/setup.sh
(cd backend && php artisan serve)  # :8000, sqlite dev DB
(cd backend && php artisan reverb:start && php artisan queue:work)
(cd frontend && npm run dev)       # :5173
```

Demo accounts (seeded, dev only): `admin@ciphersoc.local / CipherSOC!admin123`, `manager@… / CipherSOC!manager123`, `analyst@… / CipherSOC!analyst123`, `viewer@… / CipherSOC!viewer123`. Reset: `bash scripts/demo.sh`.

Tests: `(cd backend && php artisan test)` · `(cd frontend && npx vitest run && npm run build)` · `(cd agent && python -m pytest -q)`. Lint: `(cd backend && vendor/bin/pint --test)`.
