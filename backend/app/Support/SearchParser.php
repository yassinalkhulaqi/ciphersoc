<?php

namespace App\Support;

// Structured search grammar: `source_ip:10.0.0.1 hostname:web-01 severity:critical status:new rule:RL-SSH001 mitre:T1110 ioc:evil.com free text...`
// Returns ['filters'=>[field=>value], 'free'=>string]. Used by Event/Alert index endpoints.
class SearchParser
{
    private const EVENT_FIELDS = ['event_type', 'severity', 'hostname', 'username', 'source_ip', 'destination_ip', 'source', 'parser', 'status'];

    private const ALERT_FIELDS = ['severity', 'status', 'rule', 'mitre', 'ioc', 'hostname', 'source_ip'];

    /**
     * @return array{filters: array<string,string>, free: string}
     */
    public static function parse(?string $input, string $context = 'events'): array
    {
        $input = trim((string) $input);
        if ($input === '') {
            return ['filters' => [], 'free' => ''];
        }
        $allowed = $context === 'alerts' ? self::ALERT_FIELDS : self::EVENT_FIELDS;
        $filters = [];
        $freeParts = [];
        // Match key:value tokens, values may be quoted.
        preg_match_all('/(\w+):(?:"([^"]+)"|(\S+))|(\S+)/', $input, $m, PREG_SET_ORDER);
        foreach ($m as $tok) {
            if (! empty($tok[1])) {
                $k = strtolower($tok[1]);
                $v = $tok[2] !== '' ? $tok[2] : $tok[3];
                if (in_array($k, $allowed, true) && $v !== '') {
                    // Last wins for repeated keys.
                    $filters[$k] = $v;

                    continue;
                }
                $freeParts[] = $tok[0];

                continue;
            }
            $freeParts[] = $tok[4] ?? $tok[0];
        }

        return ['filters' => $filters, 'free' => trim(implode(' ', $freeParts))];
    }
}
