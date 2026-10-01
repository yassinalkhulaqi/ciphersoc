<?php

namespace App\Services\ThreatIntel;

abstract class ThreatIntelProvider
{
    abstract public function slug(): string;

    abstract public function lookup(string $type, string $value): array; // ['verdict'=>..,'score'=>..,'counts'=>[...],'raw'=>[...],'error'=>?]

    protected function mockResult(string $verdict, int $score, array $extra = []): array
    {
        return ['verdict' => $verdict, 'score' => $score, 'malicious' => $verdict === 'malicious' ? 3 : 0, 'suspicious' => $verdict === 'suspicious' ? 2 : 0, 'harmless' => $verdict === 'benign' ? 70 : 0, 'raw' => array_merge(['mock' => true, 'provider' => $this->slug()], $extra), 'error' => null];
    }

    protected function isPrivateIp(string $v): bool
    {
        if (! filter_var($v, FILTER_VALIDATE_IP)) {
            return false;
        }

        return ! filter_var($v, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
