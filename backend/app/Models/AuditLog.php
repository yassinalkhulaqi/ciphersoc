<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['actor_id', 'action', 'resource_type', 'resource_id', 'ip_address', 'user_agent', 'old_values', 'new_values', 'metadata'];

    protected $casts = ['old_values' => 'array', 'new_values' => 'array', 'metadata' => 'array'];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
