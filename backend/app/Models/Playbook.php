<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Playbook extends Model
{
    protected $fillable = ['name', 'description', 'enabled', 'trigger', 'actions', 'created_by'];

    protected $casts = ['enabled' => 'boolean', 'trigger' => 'array', 'actions' => 'array'];

    public function runs()
    {
        return $this->hasMany(PlaybookRun::class);
    }
}
