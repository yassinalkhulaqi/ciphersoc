<?php

namespace App\Services\ThreatIntel;

use Illuminate\Support\Facades\Http;

class AbuseIpDbProvider extends ThreatIntelProvider
{
    public function slug(): string
    {
        return 'abuseipdb';
    }

    public function lookup(string $type, string $value): array
    {
        if (! in_array($type, ['ipv4', 'ipv6', 'ip'])) {
            return $this->mockResult('unknown', 0, ['reason' => 'ip_only']);
        }
        if ($this->isPrivateIp($value)) {
            return $this->mockResult('benign', 0, ['reason' => 'private_ip']);
        }
        $key = config('services.threatintel.abuseipdb_key');
        if (! $key) {
            // deterministic demo heuristic: TEST-NET + known bad demo IP
            if (in_array($value, ['203.0.113.45', '198.51.100.23'])) {
                return $this->mockResult('malicious', 92, ['reason' => 'demo_seed_match']);
            }

            return [...$this->mockResult('unknown', 0, ['reason' => 'not_configured']), 'error' => 'ABUSEIPDB_API_KEY not configured'];
        }
        try {
            $resp = Http::timeout(8)->withHeaders(['Key' => $key, 'Accept' => 'application/json'])->get('https://api.abuseipdb.com/api/v2/check', ['ipAddress' => $value, 'maxAgeInDays' => 90]);
            if ($resp->failed()) {
                return [...$this->mockResult('unknown', 0), 'error' => 'AbuseIPDB HTTP '.$resp->status()];
            }
            $score = (int) ($resp->json('data.abuseConfidenceScore') ?? 0);
            $verdict = $score >= 75 ? 'malicious' : ($score >= 25 ? 'suspicious' : 'benign');

            return ['verdict' => $verdict, 'score' => $score, 'malicious' => $score >= 75 ? 2 : 0, 'suspicious' => $score >= 25 ? 1 : 0, 'harmless' => $score < 25 ? 1 : 0, 'raw' => ['mock' => false, 'data' => $resp->json('data')], 'error' => null];
        } catch (\Throwable $e) {
            return [...$this->mockResult('unknown', 0), 'error' => $e->getMessage()];
        }
    }
}
