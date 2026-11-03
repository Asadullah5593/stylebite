<?php

namespace App\Http\Middleware;

use App\Support\AppVersionGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks API use when Admin → Mobile App force-update is enabled and the
 * client is below the configured minimum. Uses X-App-Version / body fields,
 * falling back to the user's latest device_tokens.app_version so older
 * installs that never check /app/version-check still get locked out.
 */
class EnforceMinAppVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/app/version-check')) {
            return $next($request);
        }

        $blocked = AppVersionGate::rejectIfRequired($request, $request->user());
        if ($blocked !== null) {
            return $blocked;
        }

        return $next($request);
    }
}
