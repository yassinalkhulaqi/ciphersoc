<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $r)
    {
        $user = User::where('email', $r->validated('email'))->first();
        if (! $user || ! Hash::check($r->password, $user->password) || ! $user->is_active) {
            AuditLogger::log('login.failed', 'user', null, null, ['email' => $r->email]);
            throw ValidationException::withMessages(['email' => ['Invalid credentials']]);
        }
        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('ciphersoc-ui', ['*'])->plainTextToken;
        AuditLogger::log('login', 'user', $user->id);
        $user->load('roles.permissions');

        return ApiResponse::ok(['user' => $this->shape($user), 'token' => $token], 'Authenticated');
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()?->delete();
        AuditLogger::log('logout', 'user', $r->user()->id);

        return ApiResponse::ok(null, 'Logged out');
    }

    public function tokens(Request $r)
    {
        $tokens = $r->user()->tokens()->orderByDesc('id')->get(['id', 'name', 'abilities', 'last_used_at', 'created_at']);

        return ApiResponse::ok($tokens);
    }

    public function createToken(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:255', 'abilities' => 'sometimes|array']);
        $token = $r->user()->createToken($data['name'], $data['abilities'] ?? ['*'])->plainTextToken;
        AuditLogger::log('token.create', 'user', $r->user()->id, null, ['name' => $data['name']]);

        return ApiResponse::ok(['token' => $token], 'API token created');
    }

    public function revokeToken(Request $r, string $id)
    {
        $r->user()->tokens()->where('id', $id)->delete();
        AuditLogger::log('token.revoke', 'user', $r->user()->id, null, ['token_id' => $id]);

        return ApiResponse::ok(null, 'Token revoked');
    }

    public function me(Request $r)
    {
        $u = $r->user()->load('roles.permissions');

        return ApiResponse::ok(['user' => $this->shape($u), 'permissions' => $u->permissionsList()]);
    }

    public function updateProfile(Request $r)
    {
        $u = $r->user();
        $data = $r->validate(['name' => 'sometimes|string|max:255', 'timezone' => 'sometimes|string|max:64']);
        $u->update($data);
        AuditLogger::log('profile.update', 'user', $u->id, null, $data);

        return ApiResponse::ok($this->shape($u->fresh()), 'Profile updated');
    }

    public function changePassword(Request $r)
    {
        $r->validate(['current_password' => 'required|string', 'password' => 'required|string|min:8|confirmed']);
        $u = $r->user();
        if (! Hash::check($r->current_password, $u->password)) {
            return ApiResponse::error('Current password incorrect', 422);
        }
        $u->update(['password' => $r->password]);
        AuditLogger::log('password.change', 'user', $u->id);

        return ApiResponse::ok(null, 'Password changed');
    }

    public function forgotPassword(Request $r)
    {
        $r->validate(['email' => 'required|email']);
        Password::sendResetLink($r->only('email'));

        return ApiResponse::ok(null, 'If the account exists, a reset link was sent');
    }

    public function resetPassword(Request $r)
    {
        $r->validate(['token' => 'required', 'email' => 'required|email', 'password' => 'required|min:8|confirmed']);
        $status = Password::reset($r->only('email', 'password', 'password_confirmation', 'token'), function ($u, $p) {
            $u->forceFill(['password' => $p])->save();
        });

        return $status === Password::PASSWORD_RESET ? ApiResponse::ok(null, 'Password reset') : ApiResponse::error('Reset failed', 422);
    }

    private function shape(User $u): array
    {
        return ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'timezone' => $u->timezone, 'is_active' => $u->is_active, 'roles' => $u->roles->pluck('name'), 'last_login_at' => $u->last_login_at];
    }
}
