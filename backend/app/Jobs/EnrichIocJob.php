<?php

namespace App\Jobs;

use App\Models\Ioc;
use App\Services\ThreatIntel\EnrichmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnrichIocJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $iocId, public bool $force = false) {}

    public function handle(EnrichmentService $svc): void
    {
        $ioc = Ioc::find($this->iocId);
        if ($ioc) {
            try {
                $svc->enrichIoc($ioc, $this->force);
            } catch (\Throwable $e) {
                \Log::warning('ioc enrich failed: '.$e->getMessage());
            }
        }
    }
}
