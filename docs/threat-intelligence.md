# Threat Intelligence

Adapters implement `ThreatIntelProvider::lookup(type, value)` → `{verdict, score, malicious/suspicious/harmless, raw, error}`: `VirusTotalProvider`, `AbuseIpDbProvider`, `OtxProvider`, `UrlhausProvider`. `EnrichmentService::enrichIoc` caches per-provider 24h (`ioc_enrichments` + `threat_intel_results`), rolls up `threat_score/reputation`.

Keys: `VIRUSTOTAL_API_KEY`, `ABUSEIPDB_API_KEY`, `OTX_API_KEY` (env only). URLhaus needs no key. Without keys the provider reports `configuration_required` and returns `mock` results — UI labels them, never fake-live. Demo seeds (`203.0.113.45`, `malicious-test.example`) return deterministic mock-malicious so the portfolio demo works offline.

Safety: no user input becomes a server-side URL fetch; only allowlisted provider endpoints; private-IP short-circuit; 8s timeouts.
