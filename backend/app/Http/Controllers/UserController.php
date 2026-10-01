<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        return ApiResponse::ok(User::with('roles')->orderBy('name')->paginate(50));
    }

    public function store(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email', 'password' => 'required|min:8', 'roles' => 'sometimes|array', 'roles.*' => 'string']);
        $u = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        if (! empty($data['roles'])) {
            $ids = Role::whereIn('name', $data['roles'])->pluck('id');
            $u->roles()->sync($ids);
        }
        AuditLogger::log('user.create', 'user', $u->id, null, $u->only(['name', 'email']));

        return ApiResponse::ok($u->load('roles'), 'User created');
    }

    public function show(User $user)
    {
        return ApiResponse::ok(new UserResource($user->load('roles')));
    }

    public function update(Request $r, User $user)
    {
        $data = $r->validate(['name' => 'sometimes|string', 'is_active' => 'sometimes|boolean', 'roles' => 'sometimes|array', 'roles.*' => 'string']);
        $old = $user->only(['name', 'is_active']);
        if (isset($data['name'])) {
            $user->name = $data['name'];
        }
        if (isset($data['is_active'])) {
            $user->is_active = $data['is_active'];
        }
        $user->save();
        if (isset($data['roles'])) {
            $user->roles()->sync(Role::whereIn('name', $data['roles'])->pluck('id'));
        }
        AuditLogger::log('user.update', 'user', $user->id, $old, $user->fresh()->only(['name', 'is_active']));

        return ApiResponse::ok($user->fresh()->load('roles'), 'User updated');
    }

    public function roles()
    {
        return ApiResponse::ok(Role::with('permissions')->get());
    }
}
