<?php

namespace App\Jobs;

use App\Models\Report;
use App\Services\Reporting\ReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateReportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $reportId) {}

    public function handle(ReportService $svc): void
    {
        $report = Report::find($this->reportId);
        if ($report) {
            $svc->generate($report);
        }
    }
}
