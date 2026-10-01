<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDetectionRuleRequest;
use App\Models\DetectionRule;
use App\Models\DetectionRuleVersion;
use App\Models\Event;
use App\Services\Detection\DetectionEngine;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RuleController extends Controller
{
    public function index(Request $r)
    {
        $q = DetectionRule::orderBy('priority');
        if ($r->filled('search')) {
            $q->where('name', 'like', '%'.$r->get('search').'%');
        }
        if ($r->filled('enabled')) {
            $q->where('enabled', filter_var($r->get('enabled'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($r->filled('severity')) {
            $q->where('severity', $r->get('severity'));
        }

        return ApiResponse::paginated($q->paginate(min(200, (int) $r->get('per_page', 25))));
    }

    public function store(StoreDetectionRuleRequest $r)
    {
        $data = $r->validated();
        $data['rule_id'] = 'RL-'.strtoupper(Str::random(6));
        $data['created_by'] = $r->user()->id;
        $rule = DetectionRule::create($data);
        DetectionRuleVersion::create(['detection_rule_id' => $rule->id, 'version' => 1, 'snapshot' => $rule->toArray(), 'changed_by' => $r->user()->id, 'change_note' => 'created']);
        AuditLogger::log('rule.create', 'rule', $rule->id, null, $rule->toArray());

        return ApiResponse::ok($rule, 'Rule created');
    }

    public function show(DetectionRule $rule)
    {
        return ApiResponse::ok(['rule' => $rule->load('versions'), 'versions' => $rule->versions()->orderByDesc('version')->limit(20)->get()]);
    }

    public function update(Request $r, DetectionRule $rule)
    {
        $data = $this->validateRule($r, true);
        $old = $rule->toArray();
        $data['version'] = $rule->version + 1;
        $rule->update($data);
        DetectionRuleVersion::create(['detection_rule_id' => $rule->id, 'version' => $rule->version, 'snapshot' => $rule->fresh()->toArray(), 'changed_by' => $r->user()->id, 'change_note' => $r->get('change_note', 'updated')]);
        AuditLogger::log('rule.update', 'rule', $rule->id, $old, $rule->fresh()->toArray());

        return ApiResponse::ok($rule->fresh(), 'Rule updated');
    }

    public function destroy(Request $r, DetectionRule $rule)
    {
        $rule->delete();
        AuditLogger::log('rule.delete', 'rule', $rule->id, $rule->toArray());

        return ApiResponse::ok(null, 'Rule deleted');
    }

    public function test(Request $r, DetectionRule $rule)
    {
        // dry-run rule against recent events (preview capability)
        $engine = new DetectionEngine;
        $events = Event::orderByDesc('event_timestamp')->limit(200)->get();
        $matched = 0;
        $samples = [];
        foreach ($events as $e) {
            if ($rule->event_type && $rule->event_type !== '*' && $rule->event_type !== $e->event_type) {
                continue;
            }
            if ($engine->conditionsMatch($rule->conditions ?? [], $e)) {
                $matched++;
                if (count($samples) < 5) {
                    $samples[] = ['id' => $e->id, 'event_type' => $e->event_type, 'message' => substr((string) $e->message, 0, 200)];
                }
            }
        }

        return ApiResponse::ok(['scanned' => $events->count(), 'matched' => $matched, 'samples' => $samples]);
    }

    private function validateRule(Request $r, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return $r->validate([
            'name' => "$req|string|max:255", 'description' => 'sometimes|nullable|string',
            'severity' => "$req|in:info,low,medium,high,critical", 'enabled' => 'sometimes|boolean', 'status' => 'sometimes|in:active,disabled,draft',
            'event_type' => 'sometimes|nullable|string', 'rule_type' => 'sometimes|in:threshold,correlation,ioc_match,anomaly',
            'conditions' => "$req|array", 'threshold' => 'sometimes|integer|min:1|max:100000', 'time_window_minutes' => 'sometimes|integer|min:1|max:10080',
            'group_by' => 'sometimes|nullable|string', 'cooldown_minutes' => 'sometimes|integer|min:0|max:10080',
            'suppression_enabled' => 'sometimes|boolean', 'mitre_technique_id' => 'sometimes|nullable|string', 'mitre_tactic' => 'sometimes|nullable|string',
            'tags' => 'sometimes|nullable|array', 'priority' => 'sometimes|integer|min:0|max:100',
        ]);
    }
}
