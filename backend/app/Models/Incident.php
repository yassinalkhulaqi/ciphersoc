<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    protected $fillable = ['incident_id', 'title', 'description', 'severity', 'priority', 'status', 'assignee_id', 'team', 'resolved_at', 'closed_at', 'tags', 'mitre_techniques'];

    protected $casts = ['resolved_at' => 'datetime', 'closed_at' => 'datetime', 'tags' => 'array', 'mitre_techniques' => 'array'];

    public function alerts()
    {
        return $this->belongsToMany(Alert::class, 'incident_alerts', 'incident_id', 'alert_id');
    }

    public function events()
    {
        return $this->belongsToMany(Event::class, 'incident_events', 'incident_id', 'event_id');
    }

    public function iocs()
    {
        return $this->belongsToMany(Ioc::class, 'incident_iocs', 'incident_id', 'ioc_id');
    }

    public function comments()
    {
        return $this->hasMany(IncidentComment::class);
    }

    public function timeline()
    {
        return $this->hasMany(IncidentTimeline::class)->orderBy('created_at');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }
}
