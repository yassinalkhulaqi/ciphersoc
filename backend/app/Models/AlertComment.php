<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertComment extends Model
{
    protected $fillable = ['alert_id', 'user_id', 'body'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
