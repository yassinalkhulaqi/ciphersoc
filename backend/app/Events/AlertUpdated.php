<?php

namespace App\Events;

use App\Models\Alert;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlertUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Alert $alert, public array $changes = []) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('soc.alerts')];
    }

    public function broadcastAs(): string
    {
        return 'AlertUpdated';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->alert->id, 'changes' => $this->changes, 'status' => $this->alert->status, 'severity' => $this->alert->severity];
    }
}
