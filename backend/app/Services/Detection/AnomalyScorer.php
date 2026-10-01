<?php

namespace App\Services\Detection;

use App\Models\Event;

// Lightweight anomaly scorer: compares current hour volume per (event_type, hostname)
// against 7-day same-hour median. No ML deps, transparent score 0-100.
class AnomalyScorer
{
    /**
     * @return array{score:int, baseline:float, current:int, factors:string[]}
     */
    public function score(string $eventType, ?string $hostname = null): array
    {
        $hour = (int) now()->format('H');
        $current = Event::where('event_type', $eventType)
            ->when($hostname, fn ($q) => $q->where('hostname', $hostname))
            ->where('event_timestamp', '>=', now()->subHour())
            ->count();

        // Baseline: same hour over past 7 days (driver-agnostic, hourly buckets in PHP).
        $samples = [];
        for ($d = 1; $d <= 7; $d++) {
            $start = now()->subDays($d)->setHour($hour)->setMinute(0)->setSecond(0);
            $end = (clone $start)->addHour();
            try {
                $samples[] = Event::where('event_type', $eventType)
                    ->when($hostname, fn ($q) => $q->where('hostname', $hostname))
                    ->where('event_timestamp', '>=', $start)
                    ->where('event_timestamp', '<', $end)
                    ->count();
            } catch (\Throwable) {
                $samples[] = 0;
            }
        }
        sort($samples);
        $median = $samples[intdiv(count($samples), 2)] ?? 0;
        $baseline = max(1.0, (float) $median);

        $ratio = $current / $baseline;
        $score = match (true) {
            $ratio >= 10 => 90,
            $ratio >= 5 => 75,
            $ratio >= 3 => 60,
            $ratio >= 2 => 40,
            $ratio >= 1.5 => 20,
            default => 5,
        };

        return [
            'score' => $score,
            'baseline' => $baseline,
            'current' => $current,
            'factors' => ["hourly volume x{$ratio} vs 7d median {$median}"],
        ];
    }
}
