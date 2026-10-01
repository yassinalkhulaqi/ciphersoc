# Security Model

- Passwords hashed (bcrypt via `hashed` cast); rate-limited auth; Sanctum tokens, logout revokes.
- RBAC: 29 permissions × admin/manager/analyst/viewer; `perm:` middleware + policies server-side; audit on all sensitive actions; secrets never logged (`AuditLogger::scrub`).
- Agent: enrollment token + per-agent sha256 bearer, TLS verify on, throttled, ≤500/batch, 5MB Nginx cap.
- TI: SSRF-safe (§45), timeouts, no arbitrary fetch. Reports: auth-gated, no path traversal (id-keyed storage).
- Headers/CORS allowlist; validation everywhere; queue failures logged, WS failures best-effort; `composer audit` / `npm audit` in CI建议.
