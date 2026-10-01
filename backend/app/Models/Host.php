<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Host extends Model
{
    use SoftDeletes;

    protected $fillable = ['host_id', 'hostname', 'os', 'os_version', 'arch', 'ip_address', 'mac_address', 'environment', 'status', 'criticality', 'last_seen_at', 'first_seen_at', 'tags', 'metadata'];

    protected $casts = ['last_seen_at' => 'datetime', 'first_seen_at' => 'datetime', 'tags' => 'array', 'metadata' => 'array'];

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }

    public function agents()
    {
        return $this->hasMany(Agent::class);
    }
}
