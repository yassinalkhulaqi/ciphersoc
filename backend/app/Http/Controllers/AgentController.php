<?php

namespace App\Http\Controllers;

use App\Events\AgentStatusChanged;
use App\Models\Agent;
use App\Models\AgentHeartbeat;
use App\Models\Alert;
use App\Models\Event;
use App\Models\Host;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\Broadcasts;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentController extends Controller
{
    public function index(Request $r)
    {
        $q = Agent::with('host')->orderByDesc('last_heartbeat_at');
        if ($r->filled('status')) {
            $q->where('status', $r->get('status'));
        }
        if ($r->filled('search')) {
            $q->where('hostname', 'like', '%'.$r->get('search').'%');
        }

        return ApiResponse::paginated($q->paginate(min(200, (int) $r->get('per_page', 25))));
    }

    public function show(Agent $agent)
    {
        $agent->load('host');
        $beats = AgentHeartbeat::where('agent_id', $agent->id)->orderByDesc('created_at')->limit(20)->get();
        $eventCount = Event::where('agent_id', $agent->id)->count();
        $alertCount = Alert::where('agent_id', $agent->id)->count();

        return ApiResponse::ok(['agent' => $agent, 'host' => $agent->host, 'heartbeats' => $beats, 'event_count' => $eventCount, 'alert_count' => $alertCount]);
    }

    public function hosts(Request $r)
    {
        $q = Host::orderByDesc('last_seen_at');
        if ($r->filled('status')) {
            $q->where('status', $r->get('status'));
        }
        if ($r->filled('search')) {
            $q->where('hostname', 'like', '%'.$r->get('search').'%');
        }

        return ApiResponse::paginated($q->paginate(min(200, (int) $r->get('per_page', 25))));
    }

    public function register(Request $r)
    {
        $data = $r->validate(['hostname' => 'required|string|max:255', 'os' => 'nullable|string', 'os_version' => 'nullable|string', 'arch' => 'nullable|string', 'ip_address' => 'nullable|ip', 'agent_version' => 'nullable|string', 'enrollment_token' => 'required|string']);
        if ($data['enrollment_token'] !== config('services.agent.enrollment_token')) {
            return ApiResponse::error('Invalid enrollment token', 401);
        }
        $agentId = 'agt-'.Str::lower(Str::random(12));
        $rawToken = Str::random(48);
        $host = Host::firstOrCreate(['hostname' => $data['hostname']], ['host_id' => 'hst-'.Str::lower(Str::random(8)), 'os' => $data['os'] ?? null, 'os_version' => $data['os_version'] ?? null, 'arch' => $data['arch'] ?? null, 'ip_address' => $data['ip_address'] ?? null, 'status' => 'online', 'first_seen_at' => now(), 'last_seen_at' => now()]);
        $host->update(['status' => 'online', 'last_seen_at' => now(), 'ip_address' => $data['ip_address'] ?? $host->ip_address]);
        $agent = Agent::create(['agent_id' => $agentId, 'host_id' => $host->id, 'hostname' => $data['hostname'], 'os' => $data['os'] ?? null, 'agent_version' => $data['agent_version'] ?? null, 'status' => 'online', 'api_token_hash' => hash('sha256', $rawToken), 'enrolled_at' => now(), 'last_heartbeat_at' => now()]);
        AuditLogger::log('agent.register', 'agent', $agent->id);
        Broadcasts::fire(new AgentStatusChanged($agent));

        return ApiResponse::ok(['agent_id' => $agentId, 'api_token' => $rawToken, 'host_id' => $host->host_id], 'Agent enrolled');
    }

    public function heartbeat(Request $r)
    {
        $agent = $r->attributes->get('agent');
        $data = $r->validate(['status' => 'sometimes|string', 'metadata' => 'sometimes|array', 'agent_version' => 'sometimes|string']);
        $agent->update(['status' => 'online', 'last_heartbeat_at' => now(), 'agent_version' => $data['agent_version'] ?? $agent->agent_version, 'metadata' => $data['metadata'] ?? $agent->metadata]);
        $agent->host?->update(['status' => 'online', 'last_seen_at' => now()]);
        AgentHeartbeat::create(['agent_id' => $agent->id, 'status' => 'online', 'payload' => $data['metadata'] ?? null]);

        return ApiResponse::ok(['status' => 'online', 'server_time' => now()]);
    }

    public function destroy(Request $r, Agent $agent)
    {
        $agent->update(['status' => 'offline']);
        AuditLogger::log('agent.remove', 'agent', $agent->id);

        return ApiResponse::ok(null,'Agent decommissioned');
    }
}
