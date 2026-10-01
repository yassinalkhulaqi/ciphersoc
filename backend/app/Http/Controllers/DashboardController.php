<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Alert;
use App\Models\Event;
use App\Models\Incident;
use App\Models\ThreatIntelResult;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function overview(Request $r)
    {
        $range = $r->get('range', '24h');
        [$from,$to] = $this->window($range, $r->get('from'), $r->get('to'));
        $alerts = Alert::whereBetween('created_at', [$from, $to]);
        $bySev = (clone $alerts)->select('severity', DB::raw('count(*) c'))->groupBy('severity')->pluck('c', 'severity');
        $byStatus = (clone $alerts)->select('status', DB::raw('count(*) c'))->groupBy('status')->pluck('c', 'status');
        $eventsTotal = Event::whereBetween('event_timestamp', [$from, $to])->count();
        $epm = $eventsTotal / max(1, (int) abs($to->diffInMinutes($from)));
        $incidentsOpen = Incident::whereNotIn('status', ['resolved', 'closed'])->count();
        $agentsOnline = Agent::where('status', 'online')->count();
        $agentsOffline = Agent::where('status', '!=', 'online')->count();
        $driver = DB::connection()->getDriverName();
        $hourExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m-%d %H:00', created_at)"
            : "date_trunc('hour', created_at)";
        $alertsOver = Alert::whereBetween('created_at', [$from, $to])->select(DB::raw($hourExpr.' h'), DB::raw('count(*) c'))->groupBy('h')->orderBy('h')->limit(48)->get();
        $topSrc = Event::whereBetween('event_timestamp', [$from, $to])->whereNotNull('source_ip')->select('source_ip', DB::raw('count(*) c'))->groupBy('source_ip')->orderByDesc('c')->limit(10)->get();
        $topRules = Alert::whereBetween('alerts.created_at', [$from, $to])->join('detection_rules', 'detection_rules.id', '=', 'alerts.detection_rule_id')->select('detection_rules.name', DB::raw('count(*) c'))->groupBy('detection_rules.name')->orderByDesc('c')->limit(10)->get();
        // DB-agnostic MITRE bucketing (works on sqlite + pgsql without json operators)
        $topMitre = Alert::whereBetween('created_at', [$from, $to])->whereNotNull('mitre')->limit(2000)->get(['mitre'])
            ->map(fn ($a) => is_array($a->mitre) ? ($a->mitre['technique'] ?? null) : null)
            ->filter()->countBy()->sortDesc()->take(10)
            ->map(fn ($c, $t) => ['t' => $t, 'c' => $c])->values();
        $recentAlerts = Alert::with('rule')->orderByDesc('created_at')->limit(8)->get();
        $recentIncidents = Incident::orderByDesc('created_at')->limit(5)->get();
        $tiCount = ThreatIntelResult::whereBetween('checked_at', [$from, $to])->count();

        return ApiResponse::ok([
            'totals' => ['alerts' => (clone $alerts)->count(), 'critical' => $bySev['critical'] ?? 0, 'high' => $bySev['high'] ?? 0, 'medium' => $bySev['medium'] ?? 0, 'low' => $bySev['low'] ?? 0, 'open_incidents' => $incidentsOpen, 'agents_online' => $agentsOnline, 'agents_offline' => $agentsOffline, 'events' => $eventsTotal, 'events_per_minute' => round($epm, 1), 'threatintel_lookups' => $tiCount],
            'by_severity' => $bySev, 'by_status' => $byStatus,
            'alerts_over_time' => $alertsOver, 'top_source_ips' => $topSrc, 'top_rules' => $topRules, 'top_mitre' => $topMitre,
            'recent_alerts' => $recentAlerts, 'recent_incidents' => $recentIncidents,
        ]);
    }

    private function window(string $range, ?string $from, ?string $to): array
    {
        $toC = $to ? Carbon::parse($to) : now();
        $fromC = match ($range) {
            '15m' => $toC->copy()->subMinutes(15), '1h' => $toC->copy()->subHour(), '7d' => $toC->copy()->subDays(7), 'custom' => $from ? Carbon::parse($from) : $toC->copy()->subDay(), default => $toC->copy()->subDay()
        };

        return [$fromC, $toC];
    }
}
