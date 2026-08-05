<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Channel authorisation for the mobile clients.
 *
 * Laravel's stock /broadcasting/auth route sits behind the web guard and expects
 * a session cookie. The app authenticates with an opaque bearer token instead, so
 * this route is registered inside the api session.auth group and hands the already
 * resolved user to the broadcaster.
 */
class BroadcastAuthController extends Controller
{
    public function __invoke(Request $request): mixed
    {
        $request->validate([
            'channel_name' => ['required', 'string'],
            'socket_id' => ['required', 'string'],
        ]);

        try {
            return Broadcast::auth($request);
        } catch (AccessDeniedHttpException) {
            // Keep the app's envelope instead of leaking an HTML error page.
            return response()->json([
                'status_code' => 0,
                'message' => 'You are not allowed to subscribe to this channel.',
            ], Response::HTTP_FORBIDDEN);
        }
    }
}
