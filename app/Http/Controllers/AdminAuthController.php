<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function showLogin(): RedirectResponse|View
    {
        if ($this->isLoggedInAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = config('auth.providers.users.model')::query()
            ->where('email', $credentials['email'])
            ->first();

        if (
            ! $user ||
            ! Hash::check($credentials['password'], $user->password_hash) ||
            ! $user->canAccessAdminPanel()
        ) {
            Auth::logout();

            // Failed attempts are audited too — a run of these on one account
            // is exactly what an audit trail exists to surface. Actor is
            // "system" because nobody proved who they were.
            ActivityLog::record(
                eventName: 'admin_login_failed',
                entityType: 'user',
                entityId: $user?->id,
                metadata: [
                    'email' => $credentials['email'],
                    'reason' => match (true) {
                        ! $user => 'no_account',
                        ! Hash::check($credentials['password'], $user->password_hash) => 'wrong_password',
                        default => 'no_panel_access',
                    },
                ],
                description: 'Failed admin sign-in for '.$credentials['email'],
                userId: $user?->id,
                actorType: 'system',
            );

            return back()
                ->withErrors(['email' => 'Only active staff accounts can access the dashboard.'])
                ->onlyInput('email');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        ActivityLog::record(
            eventName: 'admin_signed_in',
            entityType: 'user',
            entityId: $user->id,
            metadata: ['email' => $user->email, 'remembered' => $request->boolean('remember')],
            description: 'Signed in to the admin panel',
        );

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        // Recorded before the session is torn down, while the actor is known.
        if ($user = Auth::user()) {
            ActivityLog::record(
                eventName: 'admin_signed_out',
                entityType: 'user',
                entityId: $user->id,
                description: 'Signed out of the admin panel',
            );
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function isLoggedInAdmin(): bool
    {
        return Auth::check() && Auth::user()->canAccessAdminPanel();
    }
}
