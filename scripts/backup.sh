#!/usr/bin/env bash
set -euo pipefail
# Postgres PITR-friendly backup: pg_dump custom format + WAL note.
OUT="${1:-./backups/ciphersoc-$(date +%F-%H%M).dump}"
mkdir -p "$(dirname "$OUT")"
docker compose exec -T postgres pg_dump -U ciphersoc -Fc ciphersoc > "$OUT"
echo "Backup: $OUT ($(du -h "$OUT" | cut -f1))"
echo "Restore: pg_restore -U ciphersoc -d ciphersoc < $OUT"
