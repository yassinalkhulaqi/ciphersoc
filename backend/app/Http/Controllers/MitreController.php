<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\DetectionRule;
use App\Models\Incident;
use App\Models\MitreTactic;
use App\Models\MitreTechnique;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MitreController extends Controller
{
    public function tactics()
    {
        return ApiResponse::ok(MitreTactic::withCount('techniques')->get());
    }

    public function techniques(Request $r)
    {
        $q = MitreTechnique::query();
        if ($r->filled('tactic')) {
            $q->where('tactic', $r->get('tactic'));
        }
        if ($r->filled('search')) {
            $s = $r->get('search');
            $q->where(fn ($qq) => $qq->where('technique_id', 'like', "%$s%")->orWhere('name', 'like', "%$s%"));
        }
        $techs = $q->orderBy('technique_id')->paginate(min(200, (int) $r->get('per_page', 50)));
        $items = collect($techs->items())->map(function ($t) {
            $t->alerts_count = Alert::where('mitre', 'like', '%'.$t->technique_id.'%')->count();

            return $t;
        });

        return ApiResponse::ok($items, null, ['current_page' => $techs->currentPage(), 'per_page' => $techs->perPage(), 'total' => $techs->total(), 'last_page' => $techs->lastPage()]);
    }

    public function show(string $id)
    {
        $t = MitreTechnique::where('technique_id', $id)->firstOrFail();
        $alerts = Alert::where('mitre', 'like', '%'.$id.'%')->orderByDesc('created_at')->limit(10)->get();
        $incidents = Incident::where('mitre_techniques', 'like', '%'.$id.'%')->limit(10)->get();

        return ApiResponse::ok(['technique' => $t, 'alerts_count' => $alerts->count(), 'alerts' => $alerts, 'incidents' => $incidents]);
    }

    public function coverage()
    {
        $techs = MitreTechnique::orderBy('technique_id')->get();
        $rules = DetectionRule::where('enabled', true)->get();
        $covered = [];
        foreach ($rules as $r) {
            if ($r->mitre_technique_id) {
                $covered[$r->mitre_technique_id] = ($covered[$r->mitre_technique_id] ?? 0) + 1;
            }
        }
        $items = $techs->map(function ($t) use ($covered) {
            $alerts = Alert::where('mitre', 'like', '%'.$t->technique_id.'%')->count();

            return ['technique_id' => $t->technique_id, 'name' => $t->name, 'tactic' => $t->tactic, 'rules' => $covered[$t->technique_id] ?? 0, 'alerts' => $alerts, 'covered' => ($covered[$t->technique_id] ?? 0) > 0];
        });
        $total = max(1, $items->count());
        $withRules = $items->where('covered', true)->count();

        return ApiResponse::ok(['coverage_pct' => round(100 * $withRules / $total, 1), 'techniques_total' => $total, 'techniques_covered' => $withRules, 'items' => $items]);
    }
}
