<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThreatIntelProvider extends Model
{
    protected $fillable = ['name', 'slug', 'status', 'enabled', 'mock_mode', 'config', 'last_check_at', 'last_error'];

    protected $casts = ['enabled' => 'boolean', 'mock_mode' => 'boolean', 'config' => 'array', 'last_check_at' => 'datetime'];

    protected $hidden = ['config'];
}
