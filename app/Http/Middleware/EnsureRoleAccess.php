<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoleAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  $allowedRoles  A pipe-separated list of allowed roles
     */
    public function handle(Request $request, Closure $next, string $allowedRoles): Response
    {
        $userRole = Auth::user()?->role;

        if (! $userRole) {
            return redirect()->route('login');
        }

        if (! in_array($userRole, config('mudalaya.modules.rfi.roles', []), true)) {
            abort(403);
        }

        // Admin dapat akses semua halaman
        if ($userRole === 'admin') {
            return $next($request);
        }

        if (! in_array($userRole, explode('|', $allowedRoles), true)) {
            return redirect()->route('dashboard')->with('error', 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
