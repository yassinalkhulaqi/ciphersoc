<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    protected $fillable = ['agent_id', 'host_id', 'hostname', 'os', 'agent_version', 'status', 'enrollment_token_hash', 'api_token_hash', 'last_heartbeat_at', 'enrolled_at', 'capabilities', 'metadata'];

    protected $hidden = ['enrollment_token_hash', 'api_token_hash'];

    protected $casts = ['last_heartbeat_at' => 'datetime', 'enrolled_at' => 'datetime', 'capabilities' => 'array', 'metadata' => 'array'];

    public function host()
    {
        return $this->belongsTo(Host::class);
    }

    public function heartbeats()
    {
        return $this->hasMany(AgentHeartbeat::class);
    }
}
