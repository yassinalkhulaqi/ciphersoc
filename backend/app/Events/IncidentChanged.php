<?php

namespace App\Events;

use App\Models\Incident;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncidentChanged implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Incident $incident, public string $kind = 'IncidentUpdated') {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('soc.incidents')];
    }

    public function broadcastAs(): string
    {
        return $this->kind;
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->incident->id, 'incident_id' => $this->incident->incident_id, 'title' => $this->incident->title, 'severity' => $this->incident->severity, 'status' => $this->incident->status];
    }
}
