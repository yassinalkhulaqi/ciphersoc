<?php

namespace App\Support;

use Illuminate\Broadcasting\BroadcastException;

class Broadcasts
{
    /** Fire a broadcast event best-effort: a down WebSocket server must never 500 an API request. */
    public static function fire(object $event): void
    {
        try {
            event($event);
        } catch (BroadcastException $e) {
            \Log::warning('broadcast failed (best-effort): '.$e->getMessage());
        }
    }
}
