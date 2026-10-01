#!/usr/bin/env bash
set -euo pipefail
# Simple ingestion load test: N batches x M events to /ingest/events.
URL="${CIPHERSOC_URL:-http://localhost:8000/api/v1}"
TOKEN="${CIPHERSOC_AGENT_TOKEN:?set CIPHERSOC_AGENT_TOKEN}"
AGENT="${CIPHERSOC_AGENT_ID:?set CIPHERSOC_AGENT_ID}"
BATCHES="${1:-10}"
PER="${2:-100}"
for i in $(seq 1 "$BATCHES"); do
  python3 -c "import json; print(json.dumps({'events':[{'message':'Nov 12 10:11:12 load-$i sshd[1]: Failed password for root from 10.9.9.9 port 22 ssh2'} for _ in range($PER)]}))" > /tmp/load.json
  curl -s -o /dev/null -w "batch $i: %{http_code} %{time_total}s\n" -X POST "$URL/ingest/events" -H "Authorization: Bearer $TOKEN" -H "X-Agent-ID: $AGENT" -H 'Content-Type: application/json' -d @/tmp/load.json
done
