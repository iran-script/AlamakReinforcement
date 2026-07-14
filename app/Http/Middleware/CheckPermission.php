<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    public function handle(Request $request, Closure $next, $permission)
    {
        $user = auth()->user();

        if (!$user) {
            abort(401);
        }

        if ($user->roles && $user->roles->name === 'superadmin') {
            return $next($request);
        }

        // چک Permission خودت
        if (!$user->hasPermission($permission)) {
            abort(403);
        }

        return $next($request);
    }
}