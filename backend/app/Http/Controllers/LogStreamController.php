<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

// SSE live log stream: GET /logs/stream?search=&event_type=&severity=
class LogStreamController extends Controller
{
    public function stream(Request $r): StreamedResponse
    {
        $search = (string) $r->get('search', '');
        $eventType = (string) $r->get('event_type', '');
        $severity = (string) $r->get('severity', '');
        $lastId = (int) $r->get('last_id', 0);

        return response()->stream(function () use ($search, $eventType, $severity, $lastId) {
            $cursor = $lastId;
            $ticks = 0;
            while ($ticks < 120 && ! connection_aborted()) {
                $q = Event::orderBy('id')->where('id', '>', $cursor)->limit(20);
                if ($eventType !== '') {
                    $q->where('event_type', $eventType);
                }
                if ($severity !== '') {
                    $q->where('severity', $severity);
                }
                if ($search !== '') {
                    $q->where(fn ($qq) => $qq->where('message', 'like', "%$search%")->orWhere('raw_log', 'like', "%$search%"));
                }
                $rows = $q->get();
                foreach ($rows as $e) {
                    $cursor = max($cursor, $e->id);
                    echo 'id: '.$e->id."\n";
                    echo 'data: '.json_encode(['id' => $e->id, 'event_type' => $e->event_type, 'severity' => $e->severity, 'hostname' => $e->hostname, 'message' => substr((string) $e->message, 0, 500), 'event_timestamp' => $e->event_timestamp])."\n\n";
                }
                if ($rows->isEmpty()) {
                    echo ": heartbeat\n\n";
                }
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                $ticks++;
                sleep(2);
            }
        }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache', 'X-Accel-Buffering' => 'no']);
    }
}
