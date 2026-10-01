# Detection Engine

`App\Services\Detection\DetectionEngine::evaluateEvent(Event)` — deterministic, testable, sync-in-dev / queued in prod.

## Rule schema

`name, severity, enabled, event_type ('*' = any), rule_type (threshold|correlation|ioc_match|anomaly), conditions {logic, items[]}, threshold, time_window_minutes, group_by, cooldown_minutes, suppression_enabled, mitre_*, tags, priority, version`.

Condition ops: `equals, not_equals, contains, in, regex, exists, gt, gte, contains_b64, powershell_encoded`.

## Built-in rules (12)

RL-SSH001 brute force (5/5m by IP, T1110) · RL-SSH002 success-after-failures (T1078) · RL-PRIV01 privileged logins · RL-PS001 suspicious PowerShell · RL-PS002 encoded PowerShell (T1059.001) · RL-ACCT01 new admin (T1136) · RL-PROC01 LOLBins · RL-AUTH01 brute-force 10/5m · RL-CMD01 suspicious cmdline · RL-IOC01 IOC marker (T1071) · RL-CRED01 credential access (T1003) · RL-NET01 scan volume 30/5m.

## Dedup & risk

`dedup_key = sha256(rule|event_type|host|ip|user)` — repeat sightings increment `occurrence_count` + `last_seen_at` instead of new rows (10k logins → 1 alert). `RiskScorer` publishes `{score, factors[]}` shown in UI.

## Correlation hooks

`rule_type=correlation` reuses threshold machinery with wider windows; `IncidentController` links alerts sharing IPs/hosts/MITRE. Add `app/Services/Detection/Correlators/*` for advanced graphs later.
