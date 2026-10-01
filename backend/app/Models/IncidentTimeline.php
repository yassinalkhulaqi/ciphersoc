<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentTimeline extends Model
{
    protected $table = 'incident_timeline';

    protected $fillable = ['incident_id', 'entry_type', 'title', 'detail', 'created_by', 'metadata'];

    protected $casts = ['metadata' => 'array'];
}
