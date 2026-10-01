<?php

namespace App\Http\Controllers;

use App\Models\ThreatIntelProvider;
use App\Models\ThreatIntelResult;
use App\Services\ThreatIntel\EnrichmentService;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;

class ThreatIntelController extends Controller
{
    public function providers()
    {
        $providers = ThreatIntelProvider::all()->map(function ($p) {
            $hasKey = match ($p->slug) {
                'virustotal' => (bool) config('services.threatintel.virustotal_key'), 'abuseipdb' => (bool) config('services.threatintel.abuseipdb_key'), 'otx' => (bool) config('services.threatintel.otx_key'), 'urlhaus' => true, default => false
            };

            return ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'enabled' => $p->enabled, 'status' => $p->enabled ? ($hasKey || $p->slug === 'urlhaus' ? 'available' : 'configuration_required') : 'disabled', 'mock_mode' => ! $hasKey, 'last_check_at' => $p->last_check_at, 'last_error' => $p->last_error];
        });

        return ApiResponse::ok($providers);
    }

    public function lookup(Request $r)
    {
        // SSRF-safe: never fetch user-supplied URLs server-side; only treat value as opaque indicator string sent to allowlisted provider APIs.
        $data = $r->validate(['type' => 'required|in:ipv4,ipv6,ip,domain,hostname,url,file_hash,hash,email', 'value' => 'required|string|max:2000']);
        if (strlen($data['value']) > 2000) {
            return ApiResponse::error('Indicator too long', 422);
        }
        $cached = ThreatIntelResult::where('indicator_type', $data['type'])->where('indicator_value', $data['value'])->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->latest('checked_at')->first();
        $svc = new EnrichmentService;
        $results = [];
        foreach ($svc->providers() as $p) {
            $res = $p->lookup($data['type'], $data['value']);
            $results[] = ['provider' => $p->slug(), 'verdict' => $res['verdict'], 'score' => $res['score'] ?? 0, 'error' => $res['error'] ?? null, 'mock' => ($res['raw']['mock'] ?? false)];
        }
        AuditLogger::log('threatintel.lookup', 'indicator', null, null, $data);

        return ApiResponse::ok(['indicator' => $data, 'results' => $results, 'cached' => (bool) $cached]);
    }

    public function results(Request $r)
    {
        $q = ThreatIntelResult::orderByDesc('checked_at');
        if ($r->filled('provider')) {
            $q->where('provider', $r->get('provider'));
        }
        if ($r->filled('verdict')) {
            $q->where('verdict', $r->get('verdict'));
        }

        return ApiResponse::paginated($q->paginate(min(200, (int) $r->get('per_page', 25))));
    }
}
