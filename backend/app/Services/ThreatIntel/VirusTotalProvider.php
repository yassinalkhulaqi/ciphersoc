<?php

namespace App\Services\ThreatIntel;

use Illuminate\Support\Facades\Http;

class VirusTotalProvider extends ThreatIntelProvider
{
    public function slug(): string
    {
        return 'virustotal';
    }

    public function lookup(string $type, string $value): array
    {
        $key = config('services.threatintel.virustotal_key');
        if (! $key) {
            return [...$this->mockResult('unknown', 0, ['reason' => 'not_configured']), 'error' => 'VIRUSTOTAL_API_KEY not configured'];
        }
        try {
            $resp = Http::timeout(8)->withHeaders(['x-apikey' => $key])->get('https://www.virustotal.com/api/v3/search', ['query' => $value]);
            if ($resp->failed()) {
                return [...$this->mockResult('unknown', 0), 'error' => 'VT HTTP '.$resp->status()];
            }
            $stats = $resp->json('data.0.attributes.last_analysis_stats') ?? [];
            $mal = (int) ($stats['malicious'] ?? 0);
            $susp = (int) ($stats['suspicious'] ?? 0);
            $verdict = $mal >= 3 ? 'malicious' : ($mal >= 1 || $susp >= 2 ? 'suspicious' : 'benign');

            return ['verdict' => $verdict, 'score' => min(100, $mal * 12 + $susp * 5), 'malicious' => $mal, 'suspicious' => $susp, 'harmless' => (int) ($stats['harmless'] ?? 0), 'raw' => ['mock' => false, 'stats' => $stats], 'error' => null];
        } catch (\Throwable $e) {
            return [...$this->mockResult('unknown', 0), 'error' => $e->getMessage()];
        }
    }
}
