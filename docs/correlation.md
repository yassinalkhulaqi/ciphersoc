# Correlation & Investigation

`GraphCorrelator::related(alert)` scores candidates in 7d by:
shared source_ip +40, same host +30, shared user +20, shared MITRE +15, same rule +10.

API:
- `GET /correlations/alerts/{alert}` → `{items[{id,title,severity,score,reasons}], edges[]}`
- `GET /correlations/suggestions` → clusters of ≥3 unlinked new/acknowledged alerts by IP (24h) for one-click incident creation.

UI: Alert detail → `related` tab (`RelatedAlerts`), MITRE coverage heatmap (`MitreCoverage`).
Anomaly: `AnomalyScorer` compares current-hour volume vs 7d same-hour median → 0-100 + factors.
Feeds: `threatintel:import-feed {file.jsonl|csv} --source=` → `iocs` (used by cron for MISP/STIX exports converted to JSONL).
Sigma: `POST /rules/import-sigma {yaml, dry_run}` → native rule + warnings (no extra deps).
