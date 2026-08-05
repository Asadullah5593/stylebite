<?php

namespace App\Http\Middleware;

use App\Models\UserDailyActivity;
use App\Models\UserSession;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SessionTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return $this->unauthorized('Authorization token is required.');
        }

        $session = UserSession::query()
            ->with('user.profile')
            ->where('session_token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $session || ! $session->user) {
            return $this->unauthorized('Invalid or expired token.');
        }

        $now = now();
        // Read before the overwrite below: this is what tells us whether the
        // user has already been counted for today.
        $previousSeenAt = $session->user->last_seen_at;

        $session->forceFill([
            'last_seen_at' => $now,
        ])->save();

        $session->user->forceFill([
            'last_seen_at' => $now,
        ])->save();

        $this->recordDailyActivity((int) $session->user_id, $previousSeenAt, $now);

        $request->setUserResolver(fn () => $session->user);
        $request->attributes->set('auth_session', $session);

        return $next($request);
    }

    /**
     * Record that this user was active today, for DAU/MAU reporting.
     *
     * The user's previous last_seen_at already tells us whether this is their
     * first request of the reporting day, so this costs one INSERT per user per
     * day and adds no extra read to the request path. insertOrIgnore covers the
     * race where two concurrent requests both see a stale last_seen_at.
     */
    private function recordDailyActivity(int $userId, ?CarbonInterface $previousSeenAt, CarbonInterface $now): void
    {
        $timezone = stylebite_reporting_timezone();
        $todayStart = $now->copy()->setTimezone($timezone)->startOfDay();

        if ($previousSeenAt !== null && $previousSeenAt->copy()->setTimezone($timezone)->greaterThanOrEqualTo($todayStart)) {
            return;
        }

        UserDailyActivity::insertOrIgnore([
            'user_id' => $userId,
            'activity_date' => $todayStart->toDateString(),
            'created_at' => $now,
        ]);

        // On login-based streaks this first request of the day is the thing that
        // keeps the streak alive, so it has to be reflected straight away. Still
        // only once per user per day — the guard above already returned for every
        // later request.
        if (stylebite_streak_mode() === 'login') {
            stylebite_recalculate_streak($userId);
        }
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'status_code' => 0,
            'message' => $message,
        ], Response::HTTP_UNAUTHORIZED);
    }
}
