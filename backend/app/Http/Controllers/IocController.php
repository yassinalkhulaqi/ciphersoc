<?php

namespace App\Http\Controllers;

use App\Jobs\EnrichIocJob;
use App\Models\Ioc;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;

class IocController extends Controller
{
    public function index(Request $r)
    {
        $q = Ioc::with('enrichments')->orderByDesc('threat_score');
        foreach (['type', 'status', 'reputation', 'severity'] as $f) {
            if ($r->filled($f)) {
                $q->where($f, $r->get($f));
            }
        }
        if ($r->filled('search')) {
            $s = $r->get('search');
            $q->where(fn ($qq) => $qq->where('value', 'like', "%$s%")->orWhere('normalized_value', 'like', "%$s%"));
        }

        return ApiResponse::paginated($q->paginate(min(200, (int) $r->get('per_page', 25))));
    }

    public function store(Request $r)
    {
        $data = $r->validate(['type' => 'required|in:ipv4,ipv6,ip,domain,hostname,url,file_hash,hash,email', 'value' => 'required|string|max:2000', 'confidence' => 'sometimes|integer|min:0|max:100', 'severity' => 'sometimes|in:info,low,medium,high,critical', 'source' => 'sometimes|string', 'status' => 'sometimes|in:active,monitoring,malicious,suspicious,benign,expired', 'tags' => 'sometimes|array', 'notes' => 'sometimes|nullable|string', 'expires_at' => 'sometimes|nullable|date']);
        $norm = Ioc::normalize($data['type'], $data['value']);
        if (Ioc::where('type', $data['type'])->where('normalized_value', $norm)->exists()) {
            return ApiResponse::error('Duplicate IOC', 422);
        }
        $ioc = Ioc::create([...$data, 'value' => $data['value'], 'normalized_value' => $norm, 'first_seen_at' => now(), 'last_seen_at' => now(), 'created_by' => $r->user()->id]);
        AuditLogger::log('ioc.create', 'ioc', $ioc->id, null, $ioc->toArray());
        EnrichIocJob::dispatch($ioc->id);

        return ApiResponse::ok($ioc, 'IOC created');
    }

    public function show(Ioc $ioc)
    {
        return ApiResponse::ok($ioc->load(['enrichments']));
    }

    public function update(Request $r, Ioc $ioc)
    {
        $data = $r->validate(['confidence' => 'sometimes|integer|min:0|max:100', 'severity' => 'sometimes|in:info,low,medium,high,critical', 'status' => 'sometimes|in:active,monitoring,malicious,suspicious,benign,expired', 'tags' => 'sometimes|array', 'notes' => 'sometimes|nullable|string', 'expires_at' => 'sometimes|nullable|date']);
        $old = $ioc->toArray();
        $ioc->update($data);
        AuditLogger::log('ioc.update', 'ioc', $ioc->id, $old, $ioc->fresh()->toArray());

        return ApiResponse::ok($ioc->fresh(), 'IOC updated');
    }

    public function destroy(Request $r, Ioc $ioc)
    {
        $ioc->delete();
        AuditLogger::log('ioc.delete', 'ioc', $ioc->id);

        return ApiResponse::ok(null, 'IOC deleted');
    }

    public function enrich(Request $r, Ioc $ioc)
    {
        EnrichIocJob::dispatch($ioc->id, true);

        return ApiResponse::ok(null,'Enrichment queued');
    }
}
