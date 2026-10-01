<?php

namespace App\Services\Reporting;

use App\Models\Alert;
use App\Models\Incident;
use App\Models\Ioc;
use App\Models\Report;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReportService
{
    public function generate(Report $report): Report
    {
        $report->update(['status' => 'generating']);
        try {
            $p = $report->parameters ?? [];
            $from = isset($p['from']) ? Carbon::parse($p['from']) : now()->subDays(7);
            $to = isset($p['to']) ? Carbon::parse($p['to']) : now();
            $alerts = Alert::whereBetween('created_at', [$from, $to])->limit(500)->get();
            $incidents = Incident::whereBetween('created_at', [$from, $to])->limit(200)->get();
            $bySev = $alerts->groupBy('severity')->map->count();
            $data = ['report' => $report, 'from' => $from, 'to' => $to, 'alerts' => $alerts, 'incidents' => $incidents, 'bySev' => $bySev,
                'topRules' => $alerts->groupBy(fn ($a) => $a->rule?->name ?? 'manual')->map->count()->sortDesc()->take(10),
                'topIocs' => Ioc::orderByDesc('threat_score')->limit(10)->get()];
            $view = match ($report->type) {
                'incident' => 'reports.incident', 'alert' => 'reports.alert', 'threatintel' => 'reports.threatintel', default => 'reports.soc_summary'
            };
            $pdf = Pdf::loadView($view, $data)->setPaper('a4');
            $path = 'reports/'.$report->report_id.'.pdf';
            \Storage::disk('local')->put($path, $pdf->output());
            $report->update(['status' => 'completed', 'file_path' => $path]);
        } catch (\Throwable $e) {
            $report->update(['status' => 'failed']);
            \Log::error('report failed: '.$e->getMessage());
        }

        return $report->fresh();
    }
}
