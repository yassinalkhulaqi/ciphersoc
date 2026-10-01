<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('alerts.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(['new', 'acknowledged', 'investigating', 'escalated', 'resolved', 'closed', 'false_positive'])],
            'severity' => ['sometimes', Rule::in(['info', 'low', 'medium', 'high', 'critical'])],
            'assignee_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'confidence' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
