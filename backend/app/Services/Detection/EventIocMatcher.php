<?php

namespace App\Services\Detection;

use App\Models\Alert;
use App\Models\DetectionRule;
use App\Models\Event;
use App\Models\Ioc;

/**
 * Ingest-time IOC auto-match: extract indicators from a normalized event,
 * join them against active malicious/suspicious IOCs, and raise (or
 * aggregate) an ioc_match-rule alert carrying matched_iocs + TI score boost.
 */
class EventIocMatcher
{
    /**
     * @return array<int, array{alert: Alert, created: bool, notify: bool}>
     */
    public function match(Event $event, DetectionEngine $engine): array
    {
        $indicators = $this->extract($event);
        if (empty($indicators)) {
            return [];
        }

        $matched = [];
        $topScore = 0;
        foreach ($indicators as [$type, $value]) {
            $norm = Ioc::normalize($type, $value);
            $ioc = Ioc::where('type', $type)->where('normalized_value', $norm)
                ->whereIn('status', ['active', 'monitoring', 'malicious', 'suspicious'])
                ->where(function ($q) {
                    $q->whereIn('reputation', ['malicious', 'suspicious'])
                        ->orWhereIn('status', ['malicious', 'suspicious']);
                })
                ->orderByDesc('threat_score')
                ->first();
            if ($ioc) {
                $matched[] = ['type' => $ioc->type, 'value' => $ioc->value, 'reputation' => $ioc->reputation, 'threat_score' => $ioc->threat_score];
                $topScore = max($topScore, (int) $ioc->threat_score);
            }
        }
        if (empty($matched)) {
            return [];
        }

        $rule = DetectionRule::where('enabled', true)->where('status', 'active')
            ->where('rule_type', 'ioc_match')->orderBy('priority', 'asc')->first();
        if (! $rule) {
            return [];
        }

        return [$engine->createRuleAlert($rule, $event, ['matched_iocs' => $matched], $topScore, $matched)];
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function extract(Event $event): array
    {
        $out = [];
        foreach (['source_ip' => null, 'destination_ip' => null, 'domain' => 'domain', 'url' => 'url', 'hash' => 'file_hash'] as $field => $fixed) {
            $v = $event->{$field} ?? null;
            if (empty($v) || ! is_string($v)) {
                continue;
            }
            if ($fixed !== null) {
                $out[] = [$fixed, $v];
            } elseif (filter_var($v, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $out[] = ['ipv4', $v];
            } elseif (filter_var($v, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $out[] = ['ipv6', $v];
            }
        }

        return $out;
    }
}
