<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel gate: any active account holding at least one Spatie permission (via
 * role or direct grant) may enter; per-page access is then enforced by the
 * permission middleware on each route. Super-admin emails always pass.
 */
class EnsureAdminUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check() || ! Auth::user()->canAccessAdminPanel()) {
            Auth::logout();

            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
