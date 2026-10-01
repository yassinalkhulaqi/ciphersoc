<?php

namespace App\Services\Detection;

use App\Models\Alert;
use App\Models\DetectionRule;
use App\Models\Event;
use App\Services\Risk\RiskScorer;
use Illuminate\Support\Str;

class DetectionEngine
{
    /**
     * Evaluate one event against all active rules.
     *
     * @return array<int, array{alert: Alert, created: bool, notify: bool}>
     */
    public function evaluateEvent(Event $event): array
    {
        $results = [];
        $rules = DetectionRule::where('enabled', true)->where('status', 'active')->get();
        foreach ($rules as $rule) {
            if (! $this->ruleMatches($rule, $event)) {
                continue;
            }
            $results[] = $this->createRuleAlert($rule, $event);
        }

        return $results;
    }

    private function ruleMatches(DetectionRule $rule, Event $event): bool
    {
        // ioc_match rules are evaluated exclusively by EventIocMatcher (indicator join),
        // never by generic condition matching.
        if ($rule->rule_type === 'ioc_match') {
            return false;
        }
        if ($rule->event_type && $rule->event_type !== '*' && $rule->event_type !== $event->event_type) {
            return false;
        }
        $conds = $rule->conditions ?? [];
        if ($rule->rule_type === 'correlation' && isset($conds['steps'])) {
            return (new SequenceCorrelator)->matches($rule, $event, $this);
        }
        if (! $this->conditionsMatch($conds, $event)) {
            return false;
        }
        if (($rule->rule_type === 'threshold' || $rule->rule_type === 'correlation') && ! $this->thresholdMet($rule, $event)) {
            return false;
        }

        return true;
    }

    public function conditionsMatch(array $conds, Event $e): bool
    {
        if (empty($conds)) {
            return true;
        }
        $logic = strtoupper($conds['logic'] ?? 'AND');
        $items = $conds['items'] ?? (isset($conds['field']) ? [$conds] : []);
        $results = [];
        foreach ($items as $c) {
            if (isset($c['logic'])) {
                $results[] = $this->conditionsMatch($c, $e);

                continue;
            }
            $results[] = $this->singleMatch($c, $e);
        }
        if ($logic === 'OR') {
            return in_array(true, $results, true);
        }

        return ! in_array(false, $results, true);
    }

    private function singleMatch(array $c, Event $e): bool
    {
        $field = $c['field'] ?? null;
        $op = strtolower($c['op'] ?? 'equals');
        $want = $c['value'] ?? null;
        if (! $field) {
            return false;
        }
        $got = $e->{$field} ?? ($e->normalized[$field] ?? null);
        $gotS = (string) $got;
        $wantS = (string) $want;
        switch ($op) {
            case 'equals': return strtolower($gotS) === strtolower($wantS);
            case 'not_equals': return strtolower($gotS) !== strtolower($wantS);
            case 'contains': return stripos($gotS, $wantS) !== false;
            case 'not_contains': return stripos($gotS, $wantS) === false;
            case 'startswith': return str_starts_with(strtolower($gotS), strtolower($wantS));
            case 'endswith': return str_ends_with(strtolower($gotS), strtolower($wantS));
            case 'contains_b64': return $this->containsBase64($gotS);
            case 'in': return in_array(strtolower((string) $got), array_map(fn ($v) => strtolower((string) $v), (array) $want), true);
            case 'regex': try {
                return (bool) preg_match((string) $want, (string) $got);
            } catch (\Throwable) {
                return false;
            }
            case 'exists': return $got !== null && $got !== '';
            case 'gt': return is_numeric($got) && is_numeric($want) && (float) $got > (float) $want;
            case 'gte': return is_numeric($got) && is_numeric($want) && (float) $got >= (float) $want;
            case 'lt': return is_numeric($got) && is_numeric($want) && (float) $got < (float) $want;
            case 'lte': return is_numeric($got) && is_numeric($want) && (float) $got <= (float) $want;
            case 'powershell_encoded': return $this->isEncodedPowershell((string) ($e->command_line ?? $got ?? ''));
            default: return false;
        }
    }

    public function containsBase64(string $s): bool
    {
        return (bool) preg_match('/(?:[A-Za-z0-9+\/]{40,}={0,2})/', $s);
    }

    public function isEncodedPowershell(string $cmd): bool
    {
        if (stripos($cmd, 'powershell') === false && stripos($cmd, 'pwsh') === false) {
            return false;
        }
        $l = strtolower($cmd);

        return str_contains($l, '-enc') || str_contains($l, '-encodedcommand') || str_contains($l, 'frombase64string') || $this->containsBase64($cmd);
    }

    private function thresholdMet(DetectionRule $rule, Event $event): bool
    {
        $th = max(1, (int) $rule->threshold);
        if ($th <= 1) {
            return true;
        }
        $since = now()->subMinutes(max(1, (int) $rule->time_window_minutes));
        $q = Event::where('event_type', $event->event_type)->where('event_timestamp', '>=', $since);
        if ($rule->group_by && $event->{$rule->group_by}) {
            $q->where($rule->group_by, $event->{$rule->group_by});
        }

        return ($q->count() + 0) >= ($th - 1);
    }

    private function inCooldown(DetectionRule $rule, Event $event): bool
    {
        if (! ($rule->suppression_enabled ?? true)) {
            return false;
        }
        $key = $this->dedupKey($rule, $event);
        $cool = now()->subMinutes(max(1, (int) $rule->cooldown_minutes));

        return Alert::where('dedup_key', $key)->where('last_notified_at', '>=', $cool)->exists();
    }

    public function dedupKey(DetectionRule $rule, Event $event): string
    {
        $parts = [$rule->rule_id, $event->event_type, $event->host_id ?? $event->hostname ?? '-', $event->source_ip ?? '-', substr((string) ($event->username ?? ''), 0, 64)];

        return hash('sha256', implode('|', $parts));
    }

    /**
     * Create or aggregate an alert with formal suppression semantics:
     * - open alert + inside cooldown  -> aggregate silently (notify=false)
     * - open alert + cooldown expired -> aggregate + re-notify burst (notify=true)
     * - only closed alerts on key     -> brand-new alert linked via superseded_by
     *
     * @return array{alert: Alert, created: bool, notify: bool}
     */
    public function createRuleAlert(DetectionRule $rule, Event $event, array $extraContext = [], int $threatIntelScore = 0, array $matchedIocs = []): array
    {
        $key = $this->dedupKey($rule, $event);
        $existing = Alert::where('dedup_key', $key)->whereNotIn('status', ['resolved', 'closed', 'false_positive'])
            ->orderByDesc('last_seen_at')->first();
        if ($existing) {
            $existing->increment('occurrence_count');
            $existing->update(['last_seen_at' => now()]);
            $existing->events()->syncWithoutDetaching([$event->id]);
            if ($this->inCooldown($rule, $event)) {
                return ['alert' => $existing, 'created' => false, 'notify' => false];
            }
            $existing->update(['last_notified_at' => now()]);

            return ['alert' => $existing->fresh(), 'created' => false, 'notify' => true];
        }

        $superseded = Alert::where('dedup_key', $key)->orderByDesc('last_seen_at')->first();
        $mitre = $rule->mitre_technique_id ? [$rule->mitre_technique_id] : [];
        $risk = (new RiskScorer)->score(['severity' => $rule->severity, 'confidence' => 75, 'repeat_count' => $rule->threshold, 'affected_hosts' => 1, 'mitre' => $mitre, 'threat_intel_score' => $threatIntelScore]);
        $alert = Alert::create([
            'alert_id' => (string) Str::uuid(), 'title' => $rule->name, 'description' => $rule->description,
            'severity' => $rule->severity, 'status' => 'new', 'source' => 'detection-engine',
            'detection_rule_id' => $rule->id, 'host_id' => $event->host_id, 'agent_id' => $event->agent_id,
            'occurrence_count' => 1, 'first_seen_at' => now(), 'last_seen_at' => now(), 'last_notified_at' => now(),
            'confidence' => 75, 'risk_score' => $risk['score'], 'risk_factors' => $risk['factors'],
            'mitre' => $rule->mitre_technique_id ? ['technique' => $rule->mitre_technique_id, 'tactic' => $rule->mitre_tactic] : null,
            'matched_iocs' => $matchedIocs ?: null,
            'tags' => $rule->tags, 'dedup_key' => $key, 'superseded_by' => $superseded?->id,
            'context' => array_merge(['event_id' => $event->event_id, 'source_ip' => $event->source_ip, 'username' => $event->username, 'command_line' => $event->command_line], $extraContext),
        ]);
        $alert->events()->attach($event->id);

        return ['alert' => $alert, 'created' => true, 'notify' => true];
    }
}
