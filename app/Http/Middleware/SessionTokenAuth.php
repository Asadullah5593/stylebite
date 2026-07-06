<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
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

        $session->forceFill([
            'last_seen_at' => now(),
        ])->save();

        $session->user->forceFill([
            'last_seen_at' => now(),
        ])->save();

        $request->setUserResolver(fn () => $session->user);
        $request->attributes->set('auth_session', $session);

        return $next($request);
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'status_code' => 0,
            'message' => $message,
        ], Response::HTTP_UNAUTHORIZED);
    }
}
