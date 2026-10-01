<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::error('Unauthenticated', 401);
        }
        if (! $user->hasPermission($permission)) {
            return ApiResponse::error('Forbidden: missing permission '.$permission, 403);
        }

        return $next($request);
    }
}
