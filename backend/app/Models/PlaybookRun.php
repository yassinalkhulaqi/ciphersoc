<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlaybookRun extends Model
{
    protected $fillable = ['playbook_id', 'alert_ids', 'result', 'run_by'];

    protected $casts = ['alert_ids' => 'array', 'result' => 'array'];
}
