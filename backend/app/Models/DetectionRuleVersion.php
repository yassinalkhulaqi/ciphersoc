<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetectionRuleVersion extends Model
{
    protected $fillable = ['detection_rule_id', 'version', 'snapshot', 'changed_by', 'change_note'];

    protected $casts = ['snapshot' => 'array'];
}
