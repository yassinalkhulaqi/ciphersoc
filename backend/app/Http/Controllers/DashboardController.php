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
    public function kpis(Request $r)
    {
        $data = $this->overview($r)->getData(true)['data'];
        $alerts24 = $data['totals']['alerts'] ?? 0;
        $critical = $data['totals']['critical'] ?? 0;
        // MTTR: avg acknowledge time for resolved alerts in window (hours).
        try {
            $mttr = Alert::whereNotNull('acknowledged_at')->whereNotNull('resolved_at')->limit(200)->get()
                ->map(fn ($a) => $a->acknowledged_at->diffInMinutes($a->resolved_at) / 60)->avg();
        } catch (\Throwable) {
            $mttr = null;
        }
        $total = max(1, ($data['totals']['alerts'] ?? 0));
        $fp = 0;
        try {
            $fp = 100 * Alert::where('status', 'false_positive')->count() / $total;
        } catch (\Throwable) {
        }
        $spark = collect($data['alerts_over_time'] ?? [])->pluck('c')->take(24)->values();

        return ApiResponse::ok([
            ['key' => 'alerts_24h', 'label' => 'Alerts 24h', 'value' => $alerts24, 'delta' => null, 'spark' => $spark],
            ['key' => 'critical_open', 'label' => 'Critical Open', 'value' => $critical, 'delta' => null, 'spark' => $spark],
            ['key' => 'mttr_hours', 'label' => 'MTTR (h)', 'value' => $mttr ? round($mttr, 1) : 0, 'delta' => null, 'spark' => $spark],
            ['key' => 'fp_pct', 'label' => 'False Positive %', 'value' => round($fp, 1), 'delta' => null, 'spark' => $spark],
        ]);
    }

    public function timeline(Request $r)
    {
        $hours = min(168, max(1, (int) $r->get('hours', 24)));
        $to = now();
        $from = now()->subHours($hours);
        $driver = DB::connection()->getDriverName();
        $hourExpr = $driver === 'sqlite' ? "strftime('%Y-%m-%d %H:00', created_at)" : "date_trunc('hour', created_at)";
        $rows = Alert::whereBetween('created_at', [$from, $to])->select(DB::raw($hourExpr.' h'), DB::raw('count(*) c'))->groupBy('h')->orderBy('h')->limit(168)->get();
        $sev = Alert::whereBetween('created_at', [$from, $to])->select('severity', DB::raw('count(*) c'))->groupBy('severity')->pluck('c', 'severity');

        return ApiResponse::ok(['hours' => $hours, 'points' => $rows, 'severity' => $sev]);
    }

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
