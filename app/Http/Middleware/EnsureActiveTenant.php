<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->active || ! $user->tenant_id) {
            abort(403, 'Your account is not active for a tenant workspace.');
        }

        return $next($request);
    }
}
