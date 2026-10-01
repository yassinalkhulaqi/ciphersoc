<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = ['event_id', 'event_timestamp', 'ingested_at', 'source', 'source_type', 'parser', 'host_id', 'agent_id', 'event_type', 'severity', 'message', 'username', 'source_ip', 'destination_ip', 'source_port', 'destination_port', 'protocol', 'process_name', 'process_id', 'parent_process', 'file_path', 'command_line', 'hostname', 'domain', 'url', 'hash', 'hash_type', 'action', 'status', 'raw_log', 'normalized', 'metadata', 'processing_status'];

    protected $casts = ['event_timestamp' => 'datetime', 'ingested_at' => 'datetime', 'normalized' => 'array', 'metadata' => 'array'];

    public function host()
    {
        return $this->belongsTo(Host::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function alerts()
    {
        return $this->belongsToMany(Alert::class, 'alert_events', 'event_id', 'alert_id');
    }
}
