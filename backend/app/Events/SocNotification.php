<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SocNotification implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public ?int $userId, public string $type, public string $title, public ?string $body = null, public array $data = []) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('soc.notifications'.($this->userId ? '.'.$this->userId : ''))];
    }

    public function broadcastAs(): string
    {
        return 'SocNotification';
    }

    public function broadcastWith(): array
    {
        return ['type' => $this->type, 'title' => $this->title, 'body' => $this->body, 'data' => $this->data];
    }
}
