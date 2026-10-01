<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = ['report_id', 'type', 'title', 'parameters', 'status', 'file_path', 'generated_by'];

    protected $casts = ['parameters' => 'array'];
}
