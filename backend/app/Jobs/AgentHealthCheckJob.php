<?php

namespace App\Jobs;

use App\Models\Agent;
use App\Models\Host;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AgentHealthCheckJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $threshold = now()->subMinutes(5);
        Agent::where('status', 'online')->where('last_heartbeat_at', '<', $threshold)->update(['status' => 'offline']);
        Host::where('status', 'online')->where('last_seen_at', '<', $threshold)->update(['status' => 'offline']);
    }
}
