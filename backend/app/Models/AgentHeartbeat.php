<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentHeartbeat extends Model
{
    protected $fillable = ['agent_id', 'status', 'payload'];

    protected $casts = ['payload' => 'array'];
}
