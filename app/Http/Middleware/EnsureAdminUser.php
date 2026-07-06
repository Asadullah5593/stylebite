<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            ! Auth::check() ||
            Auth::user()->role !== 'admin' ||
            Auth::user()->status !== 'active'
        ) {
            Auth::logout();

            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
