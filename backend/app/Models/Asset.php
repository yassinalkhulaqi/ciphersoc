<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $fillable = ['hostname', 'ip_address', 'os', 'criticality', 'status', 'last_seen_at', 'tags'];

    protected $casts = ['last_seen_at' => 'datetime', 'tags' => 'array'];

    public function vulns()
    {
        return $this->hasMany(AssetVulnerability::class);
    }
}
