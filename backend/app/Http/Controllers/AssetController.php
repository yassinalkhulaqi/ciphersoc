<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Asset;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function index(Request $r)
    {
        $q = Asset::withCount('vulns')->orderBy('hostname');
        if ($r->filled('search')) {
            $s = $r->get('search');
            $q->where(fn ($qq) => $qq->where('hostname', 'like', "%$s%")->orWhere('ip_address', 'like', "%$s%")->orWhere('os', 'like', "%$s%"));
        }
        if ($r->filled('criticality')) {
            $q->where('criticality', $r->get('criticality'));
        }
        if ($r->filled('status')) {
            $q->where('status', $r->get('status'));
        }

        return ApiResponse::paginated($q->paginate(min(200, (int) $r->get('per_page', 25))));
    }

    public function show(Asset $asset)
    {
        return ApiResponse::ok($asset->load('vulns'));
    }

    public function vulns(Asset $asset)
    {
        return ApiResponse::ok($asset->vulns()->orderByDesc('cvss')->get());
    }

    public function store(Request $r)
    {
        $data = $r->validate(['hostname' => 'required|string|max:255|unique:assets,hostname', 'ip_address' => 'nullable|string|max:64', 'os' => 'nullable|string|max:255', 'criticality' => 'sometimes|in:low,medium,high,critical', 'status' => 'sometimes|string|max:32', 'tags' => 'nullable|array']);
        $a = Asset::create($data);
        AuditLogger::log('asset.create', 'asset', $a->id, null, $a->toArray());

        return ApiResponse::ok($a, 'Asset created');
    }

    public function topology()
    {
        // Lightweight topology: assets as nodes, recent alert edges asset<->source_ip.
        $assets = Asset::orderBy('hostname')->limit(100)->get(['id', 'hostname', 'ip_address', 'os', 'criticality', 'status']);
        $edges = Alert::where('created_at', '>=', now()->subDay())->limit(100)->get()->map(fn ($a) => ['from' => $a->context['source_ip'] ?? 'internet', 'to' => $a->host->hostname ?? ($a->context['hostname'] ?? 'unknown'), 'severity' => $a->severity])->values();

        return ApiResponse::ok(['nodes' => $assets, 'edges' => $edges, 'geo' => $this->geo()]);
    }

    private function geo(): array
    {
        // Deterministic pseudo-geo for demo/offline attack map (no external IP geolocation).
        $ips = Alert::where('created_at', '>=', now()->subDay())->whereNotNull('context->source_ip')->limit(50)->pluck('context')->map(fn ($c) => $c['source_ip'] ?? null)->filter()->unique()->values();
        $out = [];
        foreach ($ips as $ip) {
            $h = crc32((string) $ip);
            $out[] = ['ip' => $ip, 'lat' => ($h % 140) - 70, 'lng' => (($h >> 8) % 340) - 170, 'count' => 1];
        }

        return $out;
    }
}
