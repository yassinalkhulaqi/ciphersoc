<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $r)
    {
        $q = Event::with(['host', 'agent'])->orderByDesc('event_timestamp');
        foreach (['event_type', 'severity', 'hostname', 'username', 'source_ip', 'destination_ip', 'source', 'parser'] as $f) {
            if ($r->filled($f)) {
                $q->where($f, $r->get($f));
            }
        }
        if ($r->filled('search')) {
            $s = $r->get('search');
            $q->where(fn ($qq) => $qq->where('message', 'like', "%$s%")->orWhere('raw_log', 'like', "%$s%")->orWhere('command_line', 'like', "%$s%")->orWhere('hostname', 'like', "%$s%"));
        }
        if ($r->filled('from')) {
            $q->where('event_timestamp', '>=', $r->get('from'));
        }
        if ($r->filled('to')) {
            $q->where('event_timestamp', '<=', $r->get('to'));
        }
        if ($r->filled('host_id')) {
            $q->where('host_id', $r->get('host_id'));
        }

        return ApiResponse::paginated($q->paginate(min(200, (int) $r->get('per_page', 25))));
    }

    public function show(Event $event)
    {
        $event->load(['host', 'agent', 'alerts.rule']);
        $relatedIocs = [];

        return ApiResponse::ok(['event' => $event, 'related_alerts' => $event->alerts, 'host' => $event->host]);
    }
}
