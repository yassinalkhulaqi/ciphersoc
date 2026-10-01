<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MitreTechnique extends Model
{
    protected $fillable = ['technique_id', 'name', 'description', 'tactic_id', 'tactic', 'is_subtechnique', 'parent_id', 'platforms'];

    protected $casts = ['is_subtechnique' => 'boolean', 'platforms' => 'array'];
}
