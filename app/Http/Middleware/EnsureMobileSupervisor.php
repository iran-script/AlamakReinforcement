<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileSupervisor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole('supervisor', 'web') && ! $user->isSuperAdmin(), 403);

        $token = $user->currentAccessToken();
        if ($token && method_exists($token, 'can')) {
            abort_unless($token->can('mobile:supervisor'), 403);
        }

        return $next($request);
    }
}
