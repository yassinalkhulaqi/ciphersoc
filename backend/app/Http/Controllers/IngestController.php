<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessIngestedEvent;
use App\Models\Event;
use App\Services\Ingestion\EventNormalizer;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IngestController extends Controller
{
    public function ingest(Request $r, EventNormalizer $normalizer)
    {
        $agent = $r->attributes->get('agent');
        $data = $r->validate(['events' => 'required|array|min:1|max:500', 'events.*' => 'required', 'source_type' => 'sometimes|string']);
        $stored = 0;
        $ids = [];
        foreach ($data['events'] as $raw) {
            try {
                $n = $normalizer->normalize($raw, $data['source_type'] ?? 'auto');
                $event = Event::create([
                    'event_id' => (string) Str::uuid(), 'event_timestamp' => $n['event_timestamp'] ?? now(),
                    'source' => $n['source'] ?? ($agent?->hostname ?? 'agent'), 'source_type' => $n['source_type'] ?? 'generic',
                    'parser' => $n['parser'] ?? 'generic_json', 'host_id' => $agent?->host_id, 'agent_id' => $agent?->id,
                    'event_type' => $n['event_type'] ?? 'generic', 'severity' => $this->safeSev($n['severity'] ?? 'info'),
                    'message' => substr((string) ($n['message'] ?? ''), 0, 4000), 'username' => $n['username'] ?? null,
                    'source_ip' => $n['source_ip'] ?? null, 'destination_ip' => $n['destination_ip'] ?? null,
                    'source_port' => $n['source_port'] ?? null, 'destination_port' => $n['destination_port'] ?? null,
                    'protocol' => $n['protocol'] ?? null, 'process_name' => $n['process_name'] ?? null, 'process_id' => $n['process_id'] ?? null,
                    'parent_process' => $n['parent_process'] ?? null, 'file_path' => $n['file_path'] ?? null, 'command_line' => $n['command_line'] ?? null,
                    'hostname' => $n['hostname'] ?? $agent?->hostname, 'domain' => $n['domain'] ?? null, 'url' => $n['url'] ?? null,
                    'hash' => $n['hash'] ?? null, 'hash_type' => $n['hash_type'] ?? null, 'action' => $n['action'] ?? null, 'status' => $n['status'] ?? null,
                    'raw_log' => is_string($n['raw_log'] ?? null) ? substr($n['raw_log'], 0, 16000) : json_encode($raw),
                    'normalized' => $n, 'metadata' => ['agent_id' => $agent?->agent_id], 'processing_status' => 'stored',
                ]);
                $stored++;
                $ids[] = $event->id;
                // sync queue driver executes inline (tests/dev); redis defers to workers (prod)
                ProcessIngestedEvent::dispatch($event->id);
            } catch (\Throwable $e) {
                \Log::warning('ingest item failed: '.$e->getMessage());
            }
        }
        if ($agent) {
            $agent->update(['last_heartbeat_at' => now(), 'status' => 'online']);
            $agent->host?->update(['last_seen_at' => now(), 'status' => 'online']);
        }

        return ApiResponse::ok(['accepted' => $stored, 'event_ids' => $ids], 'Events ingested');
    }

    private function safeSev(mixed $s): string
    {
        $s = strtolower((string) $s);

        return in_array($s, ['info', 'low', 'medium', 'high', 'critical']) ? $s : 'info';
    }
}
