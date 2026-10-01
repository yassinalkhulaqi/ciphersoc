<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MitreTactic extends Model
{
    protected $fillable = ['tactic_id', 'name', 'description'];

    public function techniques()
    {
        return $this->hasMany(MitreTechnique::class, 'tactic_id');
    }
}
