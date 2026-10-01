<?php

namespace App\Events;

use App\Models\Agent;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentStatusChanged implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Agent $agent) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('soc.agents')];
    }

    public function broadcastAs(): string
    {
        return 'AgentStatusChanged';
    }

    public function broadcastWith(): array
    {
        return ['agent_id' => $this->agent->agent_id, 'status' => $this->agent->status, 'last_seen' => $this->agent->last_heartbeat_at];
    }
}
