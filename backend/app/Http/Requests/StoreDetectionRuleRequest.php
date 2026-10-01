<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDetectionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('rules.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'severity' => ['required', Rule::in(['info', 'low', 'medium', 'high', 'critical'])],
            'enabled' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'disabled', 'draft'])],
            'event_type' => ['sometimes', 'nullable', 'string', 'max:128'],
            'rule_type' => ['sometimes', Rule::in(['threshold', 'correlation', 'ioc_match', 'anomaly'])],
            'conditions' => ['required', 'array'],
            'conditions.logic' => ['sometimes', 'string', 'max:16'],
            'conditions.items' => ['sometimes', 'array'],
            'conditions.items.*.field' => ['required_with:conditions.items', 'string', 'max:64'],
            'conditions.items.*.op' => ['required_with:conditions.items', Rule::in(['equals', 'not_equals', 'contains', 'contains_b64', 'in', 'regex', 'exists', 'gt', 'gte', 'powershell_encoded'])],
            'conditions.steps' => ['sometimes', 'array', 'min:2', 'max:5'],
            'conditions.within_minutes' => ['sometimes', 'integer', 'min:1', 'max:10080'],
            'threshold' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'time_window_minutes' => ['sometimes', 'integer', 'min:1', 'max:10080'],
            'group_by' => ['sometimes', 'nullable', Rule::in(['source_ip', 'destination_ip', 'hostname', 'username', 'event_type'])],
            'cooldown_minutes' => ['sometimes', 'integer', 'min:0', 'max:10080'],
            'suppression_enabled' => ['sometimes', 'boolean'],
            'mitre_technique_id' => ['sometimes', 'nullable', 'string', 'max:32'],
            'mitre_tactic' => ['sometimes', 'nullable', 'string', 'max:64'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:100'],
        ];
    }
}
