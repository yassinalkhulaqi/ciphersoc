<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertStatusHistory extends Model
{
    protected $table = 'alert_status_history';

    protected $fillable = ['alert_id', 'from_status', 'to_status', 'changed_by', 'note'];
}
