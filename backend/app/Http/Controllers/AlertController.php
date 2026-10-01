<?php

namespace App\Http\Controllers;

use App\Events\AlertUpdated;
use App\Http\Requests\UpdateAlertRequest;
use App\Models\Alert;
use App\Models\AlertComment;
use App\Models\AlertStatusHistory;
use App\Models\AuditLog;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\Broadcasts;
use App\Support\SearchParser;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $r)
    {
        $q = Alert::with(['rule', 'host', 'assignee'])->orderByDesc('created_at');
        foreach (['severity', 'status'] as $f) {
            if ($r->filled($f)) {
                $q->where($f, $r->get($f));
            }
        }
        if ($r->filled('assignee_id')) {
            $q->where('assignee_id', $r->get('assignee_id'));
        }
        if ($r->filled('rule_id')) {
            $q->where('detection_rule_id', $r->get('rule_id'));
        }
        if ($r->filled('host_id')) {
            $q->where('host_id', $r->get('host_id'));
        }
        if ($r->filled('search')) {
            $parsed = SearchParser::parse($r->get('search'), 'alerts');
            foreach ($parsed['filters'] as $k => $v) {
                match ($k) {
                    'severity', 'status' => $q->where($k, $v),
                    'hostname' => $q->whereHas('host', fn ($hq) => $hq->where('hostname', $v)),
                    'source_ip' => $q->where('context->source_ip', $v),
                    'rule' => $q->whereHas('rule', fn ($rq) => $rq->where('rule_id', $v)),
                    'mitre' => $q->where('mitre', 'like', '%'.$v.'%'),
                    'ioc' => $q->where('matched_iocs', 'like', '%'.$v.'%'),
                    default => null,
                };
            }
            $s = $parsed['free'];
            if ($s !== '') {
                $q->where(fn ($qq) => $qq->where('title', 'like', "%$s%")->orWhere('description', 'like', "%$s%"));
            }
        }
        if ($r->filled('from')) {
            $q->where('created_at', '>=', $r->get('from'));
        }
        if ($r->filled('to')) {
            $q->where('created_at', '<=', $r->get('to'));
        }
        if ($r->filled('mitre')) {
            $q->where('mitre', 'like', '%'.$r->get('mitre').'%');
        }
        $sort = $r->get('sort', '-created_at');
        if ($sort) {
            $dir = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $col = ltrim($sort, '-');
            if (in_array($col, ['created_at', 'severity', 'risk_score', 'status'])) {
                $q->orderBy($col, $dir);
            }
        }

        return ApiResponse::paginated($q->paginate(min(200, (int) $r->get('per_page', 25))));
    }

    public function show(Alert $alert)
    {
        $alert->load(['rule', 'host', 'agent', 'assignee', 'events' => fn ($q) => $q->orderByDesc('event_timestamp')->limit(50), 'comments.user', 'incidents']);
        $history = AlertStatusHistory::where('alert_id', $alert->id)->orderByDesc('created_at')->limit(50)->get();
        $audits = AuditLog::where('resource_type', 'alert')->where('resource_id', (string) $alert->id)->orderByDesc('created_at')->limit(50)->get();

        return ApiResponse::ok(['alert' => $alert, 'status_history' => $history, 'audit_history' => $audits]);
    }

    public function update(UpdateAlertRequest $r, Alert $alert)
    {
        $data = $r->validated();
        unset($data['note']);
        $old = $alert->only(array_keys($data));
        if (isset($data['status'])) {
            $from = $alert->status;
            $data += match ($data['status']) {
                'acknowledged' => ['acknowledged_at' => now()],'resolved' => ['resolved_at' => now()],'closed' => ['closed_at' => now()], default => []
            };
            AlertStatusHistory::create(['alert_id' => $alert->id, 'from_status' => $from, 'to_status' => $data['status'], 'changed_by' => $r->user()->id, 'note' => $r->get('note')]);
        }
        $alert->update($data);
        AuditLogger::log('alert.update', 'alert', $alert->id, $old, $data);
        Broadcasts::fire(new AlertUpdated($alert->fresh(), $data));

        return ApiResponse::ok($alert->fresh()->load(['assignee', 'rule']), 'Alert updated');
    }

    public function assign(Request $r, Alert $alert)
    {
        $data = $r->validate(['assignee_id' => 'required|exists:users,id']);
        $old = ['assignee_id' => $alert->assignee_id];
        $alert->update(['assignee_id' => $data['assignee_id']]);
        AuditLogger::log('alert.assign', 'alert', $alert->id, $old, $data);
        Broadcasts::fire(new AlertUpdated($alert->fresh(), $data));

        return ApiResponse::ok($alert->fresh(), 'Alert assigned');
    }

    public function bulk(Request $r)
    {
        $data = $r->validate(['ids' => 'required|array|min:1|max:200', 'ids.*' => 'integer', 'action' => 'required|in:acknowledge,resolve,close,assign', 'assignee_id' => 'sometimes|exists:users,id']);
        $alerts = Alert::whereIn('id', $data['ids'])->get();
        foreach ($alerts as $a) {
            $ns = match ($data['action']) {
                'acknowledge' => 'acknowledged','resolve' => 'resolved','close' => 'closed','assign' => $a->status
            };
            $upd = ['status' => $ns];
            if (isset($data['assignee_id'])) {
                $upd['assignee_id'] = $data['assignee_id'];
            }
            $a->update($upd);
            AuditLogger::log('alert.bulk', 'alert', $a->id, null, $upd);
        }

        return ApiResponse::ok(['updated' => $alerts->count()], 'Bulk action applied');
    }

    public function comment(Request $r, Alert $alert)
    {
        $data = $r->validate(['body' => 'required|string|max:5000']);
        $c = AlertComment::create(['alert_id' => $alert->id, 'user_id' => $r->user()->id, 'body' => $data['body']]);
        AuditLogger::log('alert.comment', 'alert', $alert->id);

        return ApiResponse::ok($c->load('user'), 'Comment added');
    }

    public function acknowledge(Request $r, Alert $alert)
    {
        $alert->update(['status' => 'acknowledged', 'acknowledged_at' => now(), 'assignee_id' => $alert->assignee_id ?? $r->user()->id]);
        AlertStatusHistory::create(['alert_id' => $alert->id, 'from_status' => 'new', 'to_status' => 'acknowledged', 'changed_by' => $r->user()->id, 'note' => 'acknowledged via API']);
        AuditLogger::log('alert.acknowledge', 'alert', $alert->id);
        Broadcasts::fire(new AlertUpdated($alert->fresh(), ['status' => 'acknowledged']));

        return ApiResponse::ok($alert->fresh(), 'Alert acknowledged');
    }
}
