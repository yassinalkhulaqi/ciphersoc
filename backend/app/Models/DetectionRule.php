<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetectionRule extends Model
{
    protected $fillable = ['rule_id', 'name', 'description', 'severity', 'enabled', 'status', 'event_type', 'rule_type', 'conditions', 'threshold', 'time_window_minutes', 'group_by', 'cooldown_minutes', 'suppression_enabled', 'mitre_technique_id', 'mitre_tactic', 'tags', 'priority', 'version', 'created_by'];

    protected $casts = ['enabled' => 'boolean', 'suppression_enabled' => 'boolean', 'conditions' => 'array', 'tags' => 'array'];

    public function versions()
    {
        return $this->hasMany(DetectionRuleVersion::class);
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }
}
