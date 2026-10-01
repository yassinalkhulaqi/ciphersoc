# Deployment

## Docker (production)

```bash
cp .env.example .env   # APP_KEY, DB_PASSWORD, enrollment token, reverb creds
docker compose up -d --build
docker compose exec backend php artisan migrate --force --seed
docker compose logs -f queue-worker scheduler websocket
```

Services: `backend(:8000)` `websocket(:8080 reverb)` `queue-worker` `scheduler` `postgres` `redis` `frontend` `nginx(:8081)`. Volumes persist pg/redis/storage. Healthchecks on pg/redis/backend.

## Production checklist

APP_KEY + strong DB password + rotated enrollment token · real TI keys · `CORS_ALLOWED_ORIGINS` pinned · TLS at edge (terminate before nginx or add cert mount) · backups for pgdata · log retention settings · `php artisan config:cache`.
