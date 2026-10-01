<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateReportJob;
use App\Models\Report;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function index()
    {
        return ApiResponse::ok(Report::orderByDesc('created_at')->paginate(25));
    }

    public function store(Request $r)
    {
        $data = $r->validate(['type' => 'required|in:soc_summary,incident,alert,threatintel', 'title' => 'required|string|max:255', 'parameters' => 'sometimes|array']);
        if (str_contains(json_encode($data['parameters'] ?? []), '..')) {
            return ApiResponse::error('Invalid parameters', 422);
        }
        $rep = Report::create(['report_id' => 'RPT-'.strtoupper(Str::random(8)), 'type' => $data['type'], 'title' => $data['title'], 'parameters' => $data['parameters'] ?? [], 'status' => 'queued', 'generated_by' => $r->user()->id]);
        AuditLogger::log('report.generate', 'report', $rep->id, null, $rep->toArray());
        GenerateReportJob::dispatch($rep->id);

        return ApiResponse::ok($rep, 'Report queued');
    }

    public function show(Report $report)
    {
        return ApiResponse::ok($report);
    }

    public function download(Report $report)
    {
        if (! $report->file_path || ! \Storage::disk('local')->exists($report->file_path)) {
            return ApiResponse::error('Report file not ready', 404);
        }
        AuditLogger::log('report.download', 'report', $report->id);
        $tmp = tempnam(sys_get_temp_dir(), 'rpt').'.pdf';
        file_put_contents($tmp, \Storage::disk('local')->get($report->file_path));

        return response()->download($tmp, 'cipherSOC-'.$report->report_id.'.pdf')->deleteFileAfterSend(true);
    }

    /**
     * Issue a short-lived signed download URL. The signature (not a session)
     * authorizes the actual file fetch, so browsers can download directly.
     * Permission is enforced here, at issuance time.
     */
    public function link(Request $r, Report $report)
    {
        if ($report->status !== 'completed') {
            return ApiResponse::error('Report file not ready', 409);
        }
        AuditLogger::log('report.download_link', 'report', $report->id);
        $url = URL::temporarySignedRoute('report.download', now()->addMinutes(5), ['report' => $report->id]);

        return ApiResponse::ok(['url' => $url, 'expires_in' => 300]);
    }
}
