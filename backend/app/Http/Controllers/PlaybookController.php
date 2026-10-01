<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Playbook;
use App\Services\Automation\PlaybookRunner;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;

class PlaybookController extends Controller
{
    public function index()
    {
        return ApiResponse::ok(Playbook::orderByDesc('id')->get());
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:255', 'description' => 'nullable|string',
            'enabled' => 'sometimes|boolean', 'trigger' => 'nullable|array',
            'actions' => 'required|array|min:1', 'actions.*.type' => 'required|in:assign,status,comment,create_incident',
        ]);
        $data['created_by'] = $r->user()->id;
        $pb = Playbook::create($data);
        AuditLogger::log('playbook.create', 'playbook', $pb->id, null, $pb->toArray());

        return ApiResponse::ok($pb, 'Playbook created');
    }

    public function show(Playbook $playbook)
    {
        return ApiResponse::ok($playbook->load('runs'));
    }

    public function update(Request $r, Playbook $playbook)
    {
        $data = $r->validate(['name' => 'sometimes|string|max:255', 'description' => 'nullable|string', 'enabled' => 'sometimes|boolean', 'trigger' => 'nullable|array', 'actions' => 'sometimes|array|min:1']);
        $playbook->update($data);

        return ApiResponse::ok($playbook->fresh(), 'Playbook updated');
    }

    public function destroy(Playbook $playbook)
    {
        $playbook->delete();

        return ApiResponse::ok(null, 'Playbook deleted');
    }

    public function run(Request $r, Playbook $playbook, PlaybookRunner $runner)
    {
        $data = $r->validate(['alert_ids' => 'required|array|min:1|max:200', 'alert_ids.*' => 'integer|exists:alerts,id', 'dry_run' => 'sometimes|boolean']);
        // Filter to matching alerts only (transparent partial apply).
        $matched = Alert::whereIn('id', $data['alert_ids'])->get()->filter(fn ($a) => $runner->matches($playbook, $a))->pluck('id')->values()->all();
        $res = $runner->run($playbook, $matched, $r->user()->id, (bool) ($data['dry_run'] ?? false));
        AuditLogger::log('playbook.run', 'playbook', $playbook->id, null, ['alert_ids' => $matched, 'dry_run' => $data['dry_run'] ?? false]);

        return ApiResponse::ok(array_merge($res, ['matched' => $matched, 'skipped' => array_values(array_diff($data['alert_ids'], $matched))]));
    }
}
