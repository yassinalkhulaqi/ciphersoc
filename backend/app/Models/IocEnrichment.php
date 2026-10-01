<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IocEnrichment extends Model
{
    protected $fillable = ['ioc_id', 'provider', 'result', 'verdict', 'malicious_count', 'suspicious_count', 'harmless_count', 'error', 'checked_at', 'expires_at'];

    protected $casts = ['result' => 'array', 'checked_at' => 'datetime', 'expires_at' => 'datetime'];
}
