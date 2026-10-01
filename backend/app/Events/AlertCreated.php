<?php

namespace App\Events;

use App\Models\Alert;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlertCreated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Alert $alert) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('soc.alerts')];
    }

    public function broadcastAs(): string
    {
        return 'AlertCreated';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->alert->id, 'alert_id' => $this->alert->alert_id, 'title' => $this->alert->title, 'severity' => $this->alert->severity, 'status' => $this->alert->status, 'risk_score' => $this->alert->risk_score, 'created_at' => $this->alert->created_at];
    }
}
