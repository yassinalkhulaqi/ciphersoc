<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Services\ThreatIntel\EnrichmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnrichAlertIndicators implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $alertId) {}

    public function handle(EnrichmentService $svc): void
    {
        $alert = Alert::find($this->alertId);
        if (! $alert) {
            return;
        }
        try {
            $svc->enrichAlertIndicators($alert);
        } catch (\Throwable $e) {
            \Log::warning('enrichment failed: '.$e->getMessage());
        }
    }
}
