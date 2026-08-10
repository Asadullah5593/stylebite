<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\EmailVerificationToken;
use App\Models\User;
use App\Services\EmailTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    private const OTP_EXPIRY_MINUTES = 10;

    private const OTP_MAX_ATTEMPTS = 5;

    private const OTP_RESEND_COOLDOWN_SECONDS = 60;

    /** Session keys holding a password-verified, not-yet-2FA'd sign-in. */
    private const PENDING_USER_KEY = 'admin_2fa_pending_user_id';

    private const PENDING_REMEMBER_KEY = 'admin_2fa_pending_remember';

    /** Stamped at sign-in; the absolute session age is measured from it. */
    public const LOGGED_IN_AT_KEY = 'admin_logged_in_at';

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

        $user = User::query()->where('email', $credentials['email'])->first();

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

        if (! config('auth.admin_two_factor')) {
            $this->startAdminSession($request, $user, $request->boolean('remember'));

            return redirect()->intended(route('admin.dashboard'));
        }

        // Password is right, but the session stays unauthenticated until the
        // emailed code is confirmed. Only the pending id is remembered.
        $request->session()->put(self::PENDING_USER_KEY, $user->id);
        $request->session()->put(self::PENDING_REMEMBER_KEY, $request->boolean('remember'));

        $this->sendOtp($user);

        ActivityLog::record(
            eventName: 'admin_login_2fa_challenged',
            entityType: 'user',
            entityId: $user->id,
            metadata: ['email' => $user->email],
            description: 'Admin sign-in awaiting the emailed code',
            userId: $user->id,
            actorType: 'system',
        );

        return redirect()->route('admin.login.otp');
    }

    public function showOtp(Request $request): RedirectResponse|View
    {
        if ($this->isLoggedInAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Your sign-in expired. Please enter your password again.']);
        }

        return view('admin.auth.otp', [
            'maskedEmail' => $this->maskEmail($user->email),
        ]);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ], [
            'code.required' => 'Please enter the 6-digit code we emailed you.',
            'code.digits' => 'The code is 6 digits.',
        ]);

        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Your sign-in expired. Please enter your password again.']);
        }

        $challenge = $this->openChallenge($user);

        if (! $challenge) {
            return back()->withErrors(['code' => 'That code has expired. Request a new one.']);
        }

        if ($challenge->attempts >= self::OTP_MAX_ATTEMPTS) {
            $this->forgetPending($request);

            ActivityLog::record(
                eventName: 'admin_login_2fa_locked',
                entityType: 'user',
                entityId: $user->id,
                metadata: ['email' => $user->email],
                description: 'Admin sign-in blocked after too many wrong codes',
                userId: $user->id,
                actorType: 'system',
            );

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Too many incorrect codes. Please sign in again.']);
        }

        if (! Hash::check($data['code'], $challenge->token_hash)) {
            $challenge->increment('attempts');

            ActivityLog::record(
                eventName: 'admin_login_2fa_failed',
                entityType: 'user',
                entityId: $user->id,
                metadata: ['email' => $user->email, 'attempts' => $challenge->attempts],
                description: 'Wrong admin sign-in code',
                userId: $user->id,
                actorType: 'system',
            );

            return back()->withErrors(['code' => 'That code is not correct.']);
        }

        // The account may have been banned or stripped of access between the
        // password step and now.
        if (! $user->canAccessAdminPanel()) {
            $this->forgetPending($request);

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Only active staff accounts can access the dashboard.']);
        }

        $challenge->forceFill(['verified_at' => now()])->save();

        $remember = (bool) $request->session()->get(self::PENDING_REMEMBER_KEY, false);
        $this->forgetPending($request);
        $this->startAdminSession($request, $user, $remember);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function resendOtp(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Your sign-in expired. Please enter your password again.']);
        }

        $last = EmailVerificationToken::query()
            ->where('user_id', $user->id)
            ->where('purpose', 'admin_login')
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if ($last && $last->created_at && $last->created_at->gt(now()->subSeconds(self::OTP_RESEND_COOLDOWN_SECONDS))) {
            $wait = max(1, self::OTP_RESEND_COOLDOWN_SECONDS - (int) $last->created_at->diffInSeconds(now(), true));

            return back()->with('status', "Please wait {$wait} seconds before requesting another code.");
        }

        $this->sendOtp($user);

        return back()->with('status', 'A new code is on its way to your inbox.');
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

    /**
     * Authenticate and stamp the sign-in time, which is what makes the panel
     * session expire on a hard 24-hour schedule rather than on idleness.
     */
    private function startAdminSession(Request $request, User $user, bool $remember): void
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $request->session()->put(self::LOGGED_IN_AT_KEY, now()->timestamp);

        $user->forceFill(['last_login_at' => now()])->save();

        ActivityLog::record(
            eventName: 'admin_signed_in',
            entityType: 'user',
            entityId: $user->id,
            metadata: [
                'email' => $user->email,
                'remembered' => $remember,
                'two_factor' => (bool) config('auth.admin_two_factor'),
            ],
            description: 'Signed in to the admin panel',
        );
    }

    private function sendOtp(User $user): void
    {
        EmailVerificationToken::query()
            ->where('user_id', $user->id)
            ->where('purpose', 'admin_login')
            ->whereNull('verified_at')
            ->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        EmailVerificationToken::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'purpose' => 'admin_login',
            'token_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
            'created_at' => now(),
        ]);

        app(EmailTemplates::class)->send(
            'auth.admin_login_code',
            $user->email,
            $user->full_name ?: $user->username,
            [
                'username' => $user->username,
                'expiry_minutes' => self::OTP_EXPIRY_MINUTES,
            ],
            highlightCode: $code
        );
    }

    private function openChallenge(User $user): ?EmailVerificationToken
    {
        return EmailVerificationToken::query()
            ->where('user_id', $user->id)
            ->where('purpose', 'admin_login')
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get(self::PENDING_USER_KEY);

        return $id ? User::query()->find($id) : null;
    }

    private function forgetPending(Request $request): void
    {
        $request->session()->forget([self::PENDING_USER_KEY, self::PENDING_REMEMBER_KEY]);
    }

    /**
     * a****d@example.com — enough to confirm the right inbox without printing
     * the address to anyone who reaches the OTP screen.
     */
    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        $visible = mb_substr($local, 0, 1);
        $tail = mb_strlen($local) > 1 ? mb_substr($local, -1) : '';

        return $visible.str_repeat('*', max(1, mb_strlen($local) - 2)).$tail.($domain ? '@'.$domain : '');
    }

    private function isLoggedInAdmin(): bool
    {
        return Auth::check() && Auth::user()->canAccessAdminPanel();
    }
}
