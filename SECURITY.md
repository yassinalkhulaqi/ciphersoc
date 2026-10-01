# Security Policy

## Supported versions
`main` is supported. Security fixes are backported to the latest tagged release only.

## Reporting
Report vulnerabilities via GitHub Security Advisories (private). Do not open public issues for sensitive findings.
Include: affected version/commit, reproduction steps, impact, logs (scrubbed of secrets).

## Baseline controls (cipherSOC)
- Passwords bcrypt-hashed, rate-limited auth (`throttle:30,1`), Sanctum tokens revoked on logout.
- RBAC enforced server-side (`perm:` middleware + `SocPolicy`); frontend guards are UX only.
- Agent auth: enrollment token + per-agent sha256 bearer, `≤500` events/batch, 5MB nginx cap, TLS verify on.
- Threat-intel: SSRF-safe (indicator strings only, allowlisted hosts, 8s timeouts, no arbitrary URL fetch).
- Audit on sensitive actions; secrets scrubbed via `AuditLogger::scrub`.
- Security headers enforced at nginx + Laravel middleware; CORS allowlist only.

## Hardening checklist for production
- [ ] `APP_KEY`, `DB_PASSWORD`, enrollment token rotated, Reverb creds unique per env
- [ ] Real TI keys in vault (never in repo), `CORS_ALLOWED_ORIGINS` pinned
- [ ] TLS at edge, HSTS, backups for `pgdata`, log retention configured
- [ ] `composer audit`, `npm audit` clean in CI
