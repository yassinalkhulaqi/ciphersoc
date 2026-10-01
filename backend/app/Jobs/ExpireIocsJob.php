<?php

namespace App\Jobs;

use App\Models\Ioc;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireIocsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Ioc::whereNotNull('expires_at')->where('expires_at', '<=', now())->where('status', '!=', 'expired')->update(['status' => 'expired']);
    }
}
