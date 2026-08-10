<?php

namespace App\Http\Middleware;

use App\Http\Controllers\AdminAuthController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel gate: any active account holding at least one Spatie permission (via
 * role or direct grant) may enter; per-page access is then enforced by the
 * permission middleware on each route. Super-admin emails always pass.
 *
 * Also enforces the absolute session lifetime. Laravel's own session lifetime
 * is idle-based — it renews on every request — so a dashboard left open would
 * stay signed in indefinitely. Measuring from the recorded sign-in time gives a
 * hard cap instead.
 */
class EnsureAdminUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check() || ! Auth::user()->canAccessAdminPanel()) {
            Auth::logout();

            return redirect()->route('admin.login');
        }

        if ($this->sessionHasExpired($request)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Your session reached its '.$this->lifetimeHours().'-hour limit. Please sign in again.']);
        }

        return $next($request);
    }

    private function sessionHasExpired(Request $request): bool
    {
        $startedAt = $request->session()->get(AdminAuthController::LOGGED_IN_AT_KEY);

        // Sessions that predate this feature carry no stamp. Stamp them now so
        // the cap starts applying rather than signing everyone out on deploy.
        if (! is_numeric($startedAt)) {
            $request->session()->put(AdminAuthController::LOGGED_IN_AT_KEY, now()->timestamp);

            return false;
        }

        return now()->timestamp - (int) $startedAt >= $this->lifetimeHours() * 3600;
    }

    private function lifetimeHours(): int
    {
        return max(1, (int) config('auth.admin_session_hours', 24));
    }
}
