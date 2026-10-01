<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentComment extends Model
{
    protected $fillable = ['incident_id', 'user_id', 'body'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
