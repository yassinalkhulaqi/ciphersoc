<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThreatIntelResult extends Model
{
    protected $fillable = ['indicator_type', 'indicator_value', 'provider', 'raw_result', 'verdict', 'score', 'checked_at', 'expires_at'];

    protected $casts = ['raw_result' => 'array', 'checked_at' => 'datetime', 'expires_at' => 'datetime'];
}
