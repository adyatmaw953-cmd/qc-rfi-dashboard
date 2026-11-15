<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $allowedRoles = config("mudalaya.modules.{$module}.roles", []);

        if (! in_array($request->user()?->role, $allowedRoles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
