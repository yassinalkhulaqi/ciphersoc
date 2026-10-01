<?php

namespace App\Services\Detection;

use App\Models\DetectionRule;
use App\Models\Event;

/**
 * True sequence correlation: the current event must match the LAST step,
 * and each prior step must have at least one matching event earlier inside
 * the window, chained backwards in time. Optional group_by pins every step
 * to the same dimension value (e.g. source_ip) as the current event.
 */
class SequenceCorrelator
{
    public function matches(DetectionRule $rule, Event $event, DetectionEngine $engine): bool
    {
        $conds = $rule->conditions ?? [];
        $steps = $conds['steps'] ?? [];
        if (count($steps) < 2) {
            return false;
        }
        $within = max(1, (int) ($conds['within_minutes'] ?? $rule->time_window_minutes ?? 15));
        $groupBy = $conds['group_by'] ?? $rule->group_by;

        $last = array_pop($steps);
        if (! $engine->conditionsMatch(['logic' => 'AND', 'items' => [$last]], $event)) {
            return false;
        }
        if ($groupBy && empty($event->{$groupBy})) {
            return false;
        }

        $cursor = $event->event_timestamp ?? now();
        $windowStart = (clone $cursor)->subMinutes($within);

        foreach (array_reverse($steps) as $step) {
            // Same-second bursts must correlate: include equal timestamps but
            // never the event currently being evaluated itself.
            $candidates = Event::where('event_timestamp', '>=', $windowStart)
                ->where('event_timestamp', '<=', $cursor)
                ->when($event->exists, fn ($q) => $q->where('id', '!=', $event->id))
                ->orderByDesc('event_timestamp')
                ->limit(200)
                ->get();
            $found = null;
            foreach ($candidates as $cand) {
                if (! $engine->conditionsMatch(['logic' => 'AND', 'items' => [$step]], $cand)) {
                    continue;
                }
                if ($groupBy && (string) ($cand->{$groupBy} ?? '') !== (string) ($event->{$groupBy} ?? '')) {
                    continue;
                }
                $found = $cand;
                break;
            }
            if (! $found) {
                return false;
            }
            $cursor = $found->event_timestamp;
        }

        return true;
    }
}
