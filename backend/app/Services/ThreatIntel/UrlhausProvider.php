<?php

namespace App\Services\ThreatIntel;

use Illuminate\Support\Facades\Http;

class UrlhausProvider extends ThreatIntelProvider
{
    public function slug(): string
    {
        return 'urlhaus';
    }

    public function lookup(string $type, string $value): array
    {
        if (! in_array($type, ['url', 'domain', 'hostname'])) {
            return $this->mockResult('unknown', 0, ['reason' => 'url_domain_only']);
        }
        try {
            $resp = Http::timeout(8)->asForm()->post('https://urlhaus-api.abuse.ch/v1/host/', ['host' => $value]);
            if ($resp->failed()) {
                return $this->mockResult('benign', 2, ['reason' => 'no_entry']);
            }
            $q = $resp->json('query_status') ?? '';
            if ($q === 'ok') {
                return ['verdict' => 'malicious', 'score' => 90, 'malicious' => 5, 'suspicious' => 0, 'harmless' => 0, 'raw' => ['mock' => false, 'urlhaus' => $resp->json()], 'error' => null];
            }

            return $this->mockResult('benign', 2, ['reason' => 'no_entry']);
        } catch (\Throwable $e) {
            return [...$this->mockResult('unknown', 0), 'error' => $e->getMessage()];
        }
    }
}
