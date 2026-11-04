<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\UserSession;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class OptionalSessionTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! $token) {
            return $next($request);
        }

        $session = UserSession::query()
            ->with('user.profile')
            ->where('session_token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $session || ! $session->user) {
            return $next($request);
        }

        if ($restricted = $this->restrictedResponse($session->user)) {
            return $restricted;
        }

        $session->forceFill([
            'last_seen_at' => now(),
        ])->save();

        Auth::setUser($session->user);
        $request->setUserResolver(fn () => $session->user);
        $request->attributes->set('auth_session', $session);

        return $next($request);
    }

    private function restrictedResponse(User $user): ?JsonResponse
    {
        if ($user->status === 'active') {
            return null;
        }

        $banned = $user->status === 'banned';

        return response()->json([
            'status_code' => 0,
            'code' => $banned ? 'account_banned' : 'account_suspended',
            'message' => $banned
                ? 'Your account has been banned. Contact StyleBite support if you believe this is a mistake.'
                : 'Your account is suspended. Contact StyleBite support for help.',
            'reason' => $banned
                ? 'This account is banned and cannot use StyleBite.'
                : 'This account is inactive until an admin reactivates it.',
        ], Response::HTTP_FORBIDDEN);
    }
}
