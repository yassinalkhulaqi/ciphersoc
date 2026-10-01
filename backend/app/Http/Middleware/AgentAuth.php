<?php

namespace App\Http\Middleware;

use App\Models\Agent;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AgentAuth
{
    public function handle(Request $request, Closure $next)
    {
        $auth = $request->header('Authorization', '');
        $agentId = $request->header('X-Agent-ID', $request->input('agent_id'));
        if (! str_starts_with($auth, 'Bearer ') || ! $agentId) {
            return ApiResponse::error('Missing agent credentials', 401);
        }
        $token = substr($auth, 7);
        $agent = Agent::where('agent_id', $agentId)->first();
        if (! $agent || ! $agent->api_token_hash) {
            return ApiResponse::error('Invalid agent credentials', 401);
        }
        // token stored as sha256 hash for constant-time compare (enrollment returns raw once)
        if (! hash_equals($agent->api_token_hash, hash('sha256', $token))) {
            return ApiResponse::error('Invalid agent credentials', 401);
        }
        $request->setUserResolver(fn () => null);
        $request->attributes->set('agent', $agent);

        return $next($request);
    }
}
