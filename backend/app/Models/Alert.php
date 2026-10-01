<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $fillable = ['alert_id', 'title', 'description', 'severity', 'status', 'source', 'detection_rule_id', 'host_id', 'agent_id', 'assignee_id', 'occurrence_count', 'first_seen_at', 'last_seen_at', 'last_notified_at', 'acknowledged_at', 'resolved_at', 'closed_at', 'confidence', 'risk_score', 'risk_factors', 'mitre', 'matched_iocs', 'tags', 'dedup_key', 'superseded_by', 'context'];

    protected $casts = ['first_seen_at' => 'datetime', 'last_seen_at' => 'datetime', 'last_notified_at' => 'datetime', 'acknowledged_at' => 'datetime', 'resolved_at' => 'datetime', 'closed_at' => 'datetime', 'risk_factors' => 'array', 'mitre' => 'array', 'matched_iocs' => 'array', 'tags' => 'array', 'context' => 'array'];

    public function rule()
    {
        return $this->belongsTo(DetectionRule::class, 'detection_rule_id');
    }

    public function host()
    {
        return $this->belongsTo(Host::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function events()
    {
        return $this->belongsToMany(Event::class, 'alert_events', 'alert_id', 'event_id');
    }

    public function comments()
    {
        return $this->hasMany(AlertComment::class);
    }

    public function incidents()
    {
        return $this->belongsToMany(Incident::class, 'incident_alerts', 'alert_id', 'incident_id');
    }
}
