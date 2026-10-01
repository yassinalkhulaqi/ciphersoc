<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'timezone', 'is_active', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['email_verified_at' => 'datetime', 'password' => 'hashed', 'last_login_at' => 'datetime', 'is_active' => 'boolean'];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function hasRole(string ...$names): bool
    {
        $have = $this->roles()->pluck('name')->map(fn ($r) => strtolower($r))->all();
        foreach ($names as $n) {
            if (in_array(strtolower($n), $have, true)) {
                return true;
            }
        }

        return false;
    }

    public function hasPermission(string $perm): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->hasRole('admin')) {
            return true;
        }

        return $this->roles()->whereHas('permissions', fn ($q) => $q->where('name', $perm))->exists();
    }

    public function permissionsList(): array
    {
        if ($this->hasRole('admin')) {
            return Permission::pluck('name')->all();
        }

        return Permission::whereHas('roles', fn ($q) => $q->whereIn('roles.id', $this->roles()->pluck('roles.id')))->pluck('name')->unique()->values()->all();
    }
}
