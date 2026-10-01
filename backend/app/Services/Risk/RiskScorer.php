<?php

namespace App\Services\Risk;

class RiskScorer
{
    private const SEV = ['info' => 5, 'low' => 15, 'medium' => 30, 'high' => 45, 'critical' => 60];

    public function score(array $ctx): array
    {
        $factors = [];
        $total = 0;
        $sev = strtolower($ctx['severity'] ?? 'medium');
        $s = self::SEV[$sev] ?? 30;
        $factors[] = ['factor' => 'Severity ('.$sev.')', 'points' => $s];
        $total += $s;
        $ti = (int) ($ctx['threat_intel_score'] ?? 0);
        if ($ti > 0) {
            $p = min(25, (int) ($ti / 4));
            $factors[] = ['factor' => 'Threat Intel (score '.$ti.')', 'points' => $p];
            $total += $p;
        }
        $rep = (int) ($ctx['repeat_count'] ?? 1);
        if ($rep >= 5) {
            $p = min(15, 5 + (int) ($rep / 10));
            $factors[] = ['factor' => 'Repeated Activity (x'.$rep.')', 'points' => $p];
            $total += $p;
        }
        $hosts = (int) ($ctx['affected_hosts'] ?? 1);
        if ($hosts > 1) {
            $p = min(10, ($hosts - 1) * 3);
            $factors[] = ['factor' => 'Affected Hosts ('.$hosts.')', 'points' => $p];
            $total += $p;
        }
        $conf = (int) ($ctx['confidence'] ?? 70);
        $p = (int) ($conf / 10);
        $factors[] = ['factor' => 'Confidence ('.$conf.'%)', 'points' => $p];
        $total += $p;
        $sensitive = ['T1003', 'T1059', 'T1078', 'T1110'];
        foreach ((array) ($ctx['mitre'] ?? []) as $m) {
            if (in_array($m, $sensitive)) {
                $factors[] = ['factor' => 'Sensitive technique '.$m, 'points' => 8];
                $total += 8;
                break;
            }
        }
        $score = max(0, min(100, $total));

        return ['score' => $score, 'factors' => $factors];
    }
}
