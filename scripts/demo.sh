#!/usr/bin/env bash
set -euo pipefail
# Reset + reseed demo environment
(cd backend && php artisan migrate:fresh --force --seed)
echo "Demo users: admin@ciphersoc.local / manager@ciphersoc.local / analyst@ciphersoc.local / viewer@ciphersoc.local"
echo "Passwords: documented in docs/development.md (dev only)"
