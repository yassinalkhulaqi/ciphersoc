<?php

namespace App\Services\ThreatIntel;

use Illuminate\Support\Facades\Http;

class OtxProvider extends ThreatIntelProvider
{
    public function slug(): string
    {
        return 'otx';
    }

    public function lookup(string $type, string $value): array
    {
        $key = config('services.threatintel.otx_key');
        if (! $key) {
            if (in_array(strtolower($value), ['malicious-test.example', 'evil-test.example'])) {
                return $this->mockResult('malicious', 88, ['reason' => 'demo_seed_match']);
            }

            return [...$this->mockResult('unknown', 0, ['reason' => 'not_configured']), 'error' => 'OTX_API_KEY not configured'];
        }
        try {
            $section = match ($type) {
                'ipv4','ipv6','ip' => 'IPv4', 'domain','hostname' => 'domain', 'url' => 'url', 'hash','file_hash' => 'file', default => 'general'
            };
            if ($section === 'general') {
                return $this->mockResult('unknown', 0, ['reason' => 'unsupported_type']);
            }
            $resp = Http::timeout(8)->withHeaders(['X-OTX-API-KEY' => $key])->get('https://otx.alienvault.com/api/v1/indicators/'.$section.'/'.urlencode($value).'/general');
            if ($resp->status() === 404) {
                return $this->mockResult('benign', 5, ['reason' => 'not_found']);
            }
            if ($resp->failed()) {
                return [...$this->mockResult('unknown', 0), 'error' => 'OTX HTTP '.$resp->status()];
            }
            $pulses = (int) ($resp->json('pulse_info.count') ?? 0);
            $verdict = $pulses >= 5 ? 'malicious' : ($pulses >= 1 ? 'suspicious' : 'benign');

            return ['verdict' => $verdict, 'score' => min(100, $pulses * 15), 'malicious' => $pulses >= 5 ? $pulses : 0, 'suspicious' => $pulses > 0 && $pulses < 5 ? $pulses : 0, 'harmless' => 0, 'raw' => ['mock' => false, 'pulses' => $pulses], 'error' => null];
        } catch (\Throwable $e) {
            return [...$this->mockResult('unknown', 0), 'error' => $e->getMessage()];
        }
    }
}
