<?php

namespace App\Services\Detection;

use App\Models\Alert;
use Illuminate\Support\Collection;

// Entity-graph correlation: alerts sharing IPs/hosts/users/MITRE inside 7d.
// Used for investigation pivot + incident suggestions (no auto-merge, analyst decides).
class GraphCorrelator
{
    /**
     * @return array{items: array, edges: array}
     */
    public function related(Alert $alert, int $limit = 20): array
    {
        $ctx = $alert->context ?? [];
        $ip = $ctx['source_ip'] ?? null;
        $user = $ctx['username'] ?? null;
        $hostId = $alert->host_id;
        $mitre = $alert->mitre['technique'] ?? ($alert->rule->mitre_technique_id ?? null);
        $since = now()->subDays(7);

        $cands = Alert::where('id', '!=', $alert->id)
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(300)
            ->get();

        $scored = [];
        foreach ($cands as $c) {
            $score = 0;
            $reasons = [];
            $cctx = $c->context ?? [];
            if ($ip && ($cctx['source_ip'] ?? null) === $ip) {
                $score += 40;
                $reasons[] = "shared ip {$ip}";
            }
            if ($hostId && $c->host_id === $hostId) {
                $score += 30;
                $reasons[] = 'same host';
            }
            if ($user && ($cctx['username'] ?? null) === $user) {
                $score += 20;
                $reasons[] = "shared user {$user}";
            }
            $ct = $c->mitre['technique'] ?? null;
            if ($mitre && $ct === $mitre) {
                $score += 15;
                $reasons[] = "shared {$mitre}";
            }
            if ($c->detection_rule_id && $c->detection_rule_id === $alert->detection_rule_id) {
                $score += 10;
                $reasons[] = 'same rule';
            }
            if ($score > 0) {
                $scored[] = ['alert' => $c, 'score' => $score, 'reasons' => $reasons];
            }
        }
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $top = array_slice($scored, 0, $limit);
        $edges = array_map(fn ($r) => ['from' => $alert->id, 'to' => $r['alert']->id, 'score' => $r['score'], 'reasons' => $r['reasons']], $top);

        return [
            'items' => array_map(fn ($r) => ['id' => $r['alert']->id, 'title' => $r['alert']->title, 'severity' => $r['alert']->severity, 'status' => $r['alert']->status, 'score' => $r['score'], 'reasons' => $r['reasons'], 'created_at' => $r['alert']->created_at], $top),
            'edges' => $edges,
        ];
    }

    public function incidentSuggestions(int $limit = 10): Collection
    {
        // Clusters: unlinked 'new/acknowledged' alerts grouped by source_ip in last 24h.
        return Alert::whereIn('status', ['new', 'acknowledged'])
            ->where('created_at', '>=', now()->subDay())
            ->whereDoesntHave('incidents')
            ->get()
            ->groupBy(fn ($a) => $a->context['source_ip'] ?? 'unknown')
            ->filter(fn ($g) => $g->count() >= 3)
            ->map(fn ($g, $ip) => ['key' => $ip, 'count' => $g->count(), 'top_severity' => $g->max('severity'), 'alert_ids' => $g->pluck('id')->take(25)->values(), 'sample_titles' => $g->pluck('title')->unique()->take(3)->values()])
            ->sortByDesc('count')
            ->take($limit)
            ->values();
    }
}
