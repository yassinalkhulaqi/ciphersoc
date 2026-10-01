<?php

namespace App\Services\ThreatIntel;

use App\Models\Alert;
use App\Models\Ioc;
use App\Models\IocEnrichment;
use App\Models\ThreatIntelResult;

class EnrichmentService
{
    public function providers(): array
    {
        return [new VirusTotalProvider, new AbuseIpDbProvider, new OtxProvider, new UrlhausProvider];
    }

    public function enrichIoc(Ioc $ioc, bool $force = false): array
    {
        $out = [];
        foreach ($this->providers() as $p) {
            $cached = IocEnrichment::where('ioc_id', $ioc->id)->where('provider', $p->slug())
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->latest('checked_at')->first();
            if ($cached && ! $force) {
                $out[] = $cached;

                continue;
            }
            $r = $p->lookup($ioc->type, $ioc->value);
            $en = IocEnrichment::create(['ioc_id' => $ioc->id, 'provider' => $p->slug(), 'result' => $r['raw'] ?? [],
                'verdict' => $r['verdict'] ?? 'unknown', 'malicious_count' => $r['malicious'] ?? 0, 'suspicious_count' => $r['suspicious'] ?? 0,
                'harmless_count' => $r['harmless'] ?? 0, 'error' => $r['error'] ?? null, 'checked_at' => now(), 'expires_at' => now()->addHours(24)]);
            ThreatIntelResult::create(['indicator_type' => $ioc->type, 'indicator_value' => $ioc->normalized_value, 'provider' => $p->slug(),
                'raw_result' => $r['raw'] ?? [], 'verdict' => $r['verdict'] ?? 'unknown', 'score' => $r['score'] ?? 0, 'checked_at' => now(), 'expires_at' => now()->addHours(24)]);
            $out[] = $en;
        }
        $this->rollupIoc($ioc);

        return $out;
    }

    public function rollupIoc(Ioc $ioc): void
    {
        $ens = $ioc->enrichments()->latest('checked_at')->get()->groupBy('provider')->map->first();
        $score = 0;
        $rep = 'unknown';
        foreach ($ens as $e) {
            if ($e->verdict === 'malicious') {
                $score = max($score, 85);
                $rep = 'malicious';
            } elseif ($e->verdict === 'suspicious' && $rep !== 'malicious') {
                $score = max($score, 55);
                $rep = 'suspicious';
            } elseif ($e->verdict === 'benign' && $rep === 'unknown') {
                $score = max($score, 5);
                $rep = 'benign';
            }
        }
        $ioc->update(['threat_score' => $score, 'reputation' => $rep, 'last_seen_at' => now()]);
    }

    public function enrichAlertIndicators(Alert $alert): void
    {
        $ctx = $alert->context ?? [];
        $candidates = [];
        foreach (['source_ip' => 'ipv4', 'destination_ip' => 'ipv4', 'domain' => 'domain', 'url' => 'url', 'hash' => 'file_hash'] as $k => $t) {
            if (! empty($ctx[$k])) {
                $candidates[] = [$t, (string) $ctx[$k]];
            }
        }
        foreach ($candidates as [$t,$v]) {
            $norm = Ioc::normalize($t, $v);
            $ioc = Ioc::firstOrCreate(['type' => $t, 'normalized_value' => $norm], ['value' => $v, 'source' => 'auto-extract', 'status' => 'monitoring', 'first_seen_at' => now(), 'last_seen_at' => now()]);
            $this->enrichIoc($ioc);
        }
    }
}
