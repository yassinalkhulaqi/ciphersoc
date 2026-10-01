# cipherSOC Roadmap — big development plan

Status: `feat/soc-hardening-roadmap-phase0-1` implements Phase 0 + Phase 1 starters.
See `docs/architecture.md`, `docs/api.md`, `docs/detection-engine.md`.

## Phase 0 — Baseline hardening [DONE in this branch]
- [x] CI (backend pint+test, frontend oxlint+vitest+build, agent pytest, compose config)
- [x] SECURITY.md, CONTRIBUTING.md, ROADMAP.md, runbook, onboarding
- [x] scripts/check.sh, scripts/dev.sh, .dockerignore, nginx security headers, compose restart/logging/healthchecks
- [x] .gitignore hygiene (pycache, pytest, phpunit cache)
- [x] Retention: `events:prune` + schedule, SearchParser grammar

## Phase 1 — Backend v2 [STARTER in this branch, remainder TODO]
- [x] ECS / Sysmon / Suricata normalizers wired into EventNormalizer
- [x] DetectionEngine: lt,lte,not_contains,startswith,endswith + hardened regex/in
- [x] SearchParser: `source_ip: hostname: severity: status: rule: mitre: ioc:` for events/alerts
- [x] SecurityHeaders middleware + metrics in /health
- [ ] Sigma importer, graph correlator (`Correlators/*`), anomaly baselines
- [ ] MFA TOTP, OIDC SSO, API key rotation, per-agent mTLS
- [ ] Immutable audit trail, OpenAPI contract tests

## Phase 2 — Frontend SOC console [STARTER]
- [x] search.ts grammar utils + debounce hook + ErrorBoundary
- [x] Events/Alerts use debounced grammar-aware search
- [ ] Investigation workbench (alert+events+IOCs+timeline single view)
- [ ] Virtualized tables, saved views, keyboard triage

## Phase 3 — Agent EDR-lite [STARTER]
- [x] FIM watcher + system metrics collector + config flags
- [ ] journald/auditd, Sysmon channel, auto-update, spool encryption

## Phase 4 — TI + MITRE [TODO]
- MISP/STIX/TAXII, custom feeds cron, confidence decay, MITRE coverage heatmap

## Phase 5 — SOAR [TODO]
- Playbooks (enrich→block→isolate→ticket→close), Slack/Jira/TheHive, SLA

## Phase 6 — Reporting/Compliance [TODO]
- Scheduled reports, NIST/ISO/SOC2 mapping, CSV/STIX export

## Phase 7 — Prod ops [STARTER]
- [x] nginx hardening, compose resilience
- [ ] TLS/HSTS, vault, pg PITR, OTel/Prometheus/Loki/Sentry, Helm/HPA, 10k EPS load test
