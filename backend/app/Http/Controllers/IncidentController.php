<?php

namespace App\Http\Controllers;

use App\Events\IncidentChanged;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\IncidentComment;
use App\Models\IncidentTimeline;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\Broadcasts;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IncidentController extends Controller
{
    public function index(Request $r)
    {
        $q = Incident::with(['assignee'])->orderByDesc('created_at');
        foreach (['severity', 'status', 'priority'] as $f) {
            if ($r->filled($f)) {
                $q->where($f, $r->get($f));
            }
        }
        if ($r->filled('search')) {
            $s = $r->get('search');
            $q->where(fn ($qq) => $qq->where('title', 'like', "%$s%")->orWhere('description', 'like', "%$s%"));
        }

        return ApiResponse::paginated($q->paginate(min(200, (int) $r->get('per_page', 25))));
    }

    public function store(Request $r)
    {
        $data = $r->validate(['title' => 'required|string|max:255', 'description' => 'nullable|string', 'severity' => 'required|in:info,low,medium,high,critical', 'priority' => 'sometimes|in:p1,p2,p3,p4', 'assignee_id' => 'nullable|exists:users,id', 'team' => 'nullable|string', 'tags' => 'nullable|array', 'alert_ids' => 'nullable|array', 'alert_ids.*' => 'integer']);
        $inc = Incident::create(['incident_id' => 'INC-'.strtoupper(Str::random(8)), 'title' => $data['title'], 'description' => $data['description'] ?? null, 'severity' => $data['severity'], 'priority' => $data['priority'] ?? 'p3', 'status' => 'open', 'assignee_id' => $data['assignee_id'] ?? null, 'team' => $data['team'] ?? null, 'tags' => $data['tags'] ?? null]);
        if (! empty($data['alert_ids'])) {
            $inc->alerts()->sync($data['alert_ids']);
        }
        IncidentTimeline::create(['incident_id' => $inc->id, 'entry_type' => 'created', 'title' => 'Incident created', 'detail' => $data['title'], 'created_by' => $r->user()->id]);
        AuditLogger::log('incident.create', 'incident', $inc->id, null, $inc->toArray());
        Broadcasts::fire(new IncidentChanged($inc, 'IncidentCreated'));

        return ApiResponse::ok($inc->load(['alerts', 'assignee']), 'Incident created', ['code' => 201]);
    }

    public function show(Incident $incident)
    {
        $incident->load(['alerts.rule', 'events' => fn ($q) => $q->limit(50), 'iocs', 'comments.user', 'timeline', 'assignee']);
        $audits = AuditLog::where('resource_type', 'incident')->where('resource_id', (string) $incident->id)->orderByDesc('created_at')->limit(50)->get();

        return ApiResponse::ok(['incident' => $incident, 'audit_history' => $audits]);
    }

    public function update(Request $r, Incident $incident)
    {
        $data = $r->validate(['title' => 'sometimes|string|max:255', 'description' => 'sometimes|nullable|string', 'severity' => 'sometimes|in:info,low,medium,high,critical', 'priority' => 'sometimes|in:p1,p2,p3,p4', 'status' => 'sometimes|in:open,investigating,containment,eradication,recovery,resolved,closed', 'assignee_id' => 'sometimes|nullable|exists:users,id', 'team' => 'sometimes|nullable|string', 'tags' => 'sometimes|nullable|array']);
        $old = $incident->only(array_keys($data));
        if (isset($data['status']) && in_array($data['status'], ['resolved'])) {
            $data['resolved_at'] = now();
        }
        if (isset($data['status']) && $data['status'] === 'closed') {
            $data['closed_at'] = now();
        }
        $incident->update($data);
        IncidentTimeline::create(['incident_id' => $incident->id, 'entry_type' => 'status', 'title' => 'Status: '.$incident->status, 'detail' => $r->get('note'), 'created_by' => $r->user()->id]);
        AuditLogger::log('incident.update', 'incident', $incident->id, $old, $data);
        Broadcasts::fire(new IncidentChanged($incident->fresh(), 'IncidentUpdated'));

        return ApiResponse::ok($incident->fresh(), 'Incident updated');
    }

    public function attachAlerts(Request $r, Incident $incident)
    {
        $data = $r->validate(['alert_ids' => 'required|array', 'alert_ids.*' => 'integer|exists:alerts,id']);
        $incident->alerts()->syncWithoutDetaching($data['alert_ids']);
        IncidentTimeline::create(['incident_id' => $incident->id, 'entry_type' => 'evidence', 'title' => count($data['alert_ids']).' alert(s) attached', 'created_by' => $r->user()->id]);
        AuditLogger::log('incident.attach_alerts', 'incident', $incident->id, null, $data);

        return ApiResponse::ok($incident->load('alerts'), 'Alerts attached');
    }

    public function attachIocs(Request $r, Incident $incident)
    {
        $data = $r->validate(['ioc_ids' => 'required|array', 'ioc_ids.*' => 'integer|exists:iocs,id']);
        $incident->iocs()->syncWithoutDetaching($data['ioc_ids']);
        AuditLogger::log('incident.attach_iocs', 'incident', $incident->id, null, $data);

        return ApiResponse::ok($incident->load('iocs'), 'IOCs attached');
    }

    public function comment(Request $r, Incident $incident)
    {
        $data = $r->validate(['body' => 'required|string|max:5000']);
        $c = IncidentComment::create(['incident_id' => $incident->id, 'user_id' => $r->user()->id, 'body' => $data['body']]);
        IncidentTimeline::create(['incident_id' => $incident->id, 'entry_type' => 'note', 'title' => 'Analyst note', 'detail' => substr($data['body'], 0, 500), 'created_by' => $r->user()->id]);

        return ApiResponse::ok($c->load('user'), 'Comment added');
    }

    public function timeline(Request $r, Incident $incident)
    {
        $data = $r->validate(['title' => 'required|string|max:255', 'detail' => 'nullable|string', 'entry_type' => 'sometimes|in:note,evidence,status,containment,custom']);
        $t = IncidentTimeline::create(['incident_id' => $incident->id, 'entry_type' => $data['entry_type'] ?? 'note', 'title' => $data['title'], 'detail' => $data['detail'] ?? null, 'created_by' => $r->user()->id]);

        return ApiResponse::ok($t, 'Timeline entry added');
    }

    public function escalate(Request $r, Incident $incident)
    {
        $data = $r->validate(['priority' => 'sometimes|in:p1,p2,p3,p4', 'note' => 'nullable|string|max:2000']);
        $old = $incident->status;
        $incident->update(['status' => 'containment', 'priority' => $data['priority'] ?? 'p1']);
        IncidentTimeline::create(['incident_id' => $incident->id, 'entry_type' => 'status', 'title' => "Escalated {$old} → containment", 'detail' => $data['note'] ?? null, 'created_by' => $r->user()->id]);
        AuditLogger::log('incident.escalate', 'incident', $incident->id, ['status' => $old], $incident->fresh()->toArray());
        Broadcasts::fire(new IncidentChanged($incident->fresh(), 'IncidentEscalated'));

        return ApiResponse::ok($incident->fresh(), 'Incident escalated to containment');
    }
}
