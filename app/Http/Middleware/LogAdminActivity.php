<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Support\ActivityContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Catch-all audit trail for the admin panel: every state-changing request made
 * by anyone who can reach the panel lands in activity_logs, whether it
 * succeeded, was blocked by permissions, or was rejected by validation.
 *
 * Controllers that write their own richer row (old status → new status, the
 * reason, the amount) still do; this only fills the gap when nothing else
 * logged the request, so each action produces one row — not two. Either way the
 * response status is stamped on afterwards, so "attempted" and "applied" can
 * never be confused.
 */
class LogAdminActivity
{
    private const IGNORED_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            // Most failures are turned into responses further down the pipeline
            // and land in the normal path below; anything that escapes that far
            // still has to be audited before it is re-thrown.
            $this->audit(
                $request,
                $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500,
                $exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 403
                    ? ActivityLog::OUTCOME_BLOCKED
                    : ActivityLog::OUTCOME_FAILED,
            );

            throw $exception;
        }

        $this->audit($request, $response->getStatusCode(), $this->resolveOutcome($request, $response->getStatusCode()));

        return $response;
    }

    private function audit(Request $request, int $status, string $outcome): void
    {
        $route = $request->route();

        if (! $route || ! $this->shouldAudit($request, $route->getName())) {
            return;
        }

        $context = app(ActivityContext::class);

        // A controller already described this action in detail — just record
        // how the request ended on the rows it wrote.
        if ($context->hasDomainLog()) {
            ActivityLog::query()
                ->whereKey($context->loggedIds())
                ->whereNull('response_status')
                ->update([
                    'response_status' => $status,
                    'outcome' => $outcome,
                ]);

            return;
        }

        $routeName = $route->getName();

        ActivityLog::create([
            'user_id' => auth()->id(),
            'actor_type' => 'admin',
            'event_name' => $routeName ?: Str::lower($request->method()).'_'.$request->path(),
            'description' => $this->describe($request, $routeName, $outcome),
            'entity_type' => $this->entityType($routeName),
            'entity_id' => $this->entityId($route->parameters()),
            'metadata_json' => array_filter([
                'route_parameters' => $this->scalarParameters($route->parameters()) ?: null,
                'input' => $this->sanitizedInput($request) ?: null,
                'validation_errors' => $this->validationErrorKeys($request) ?: null,
            ]),
            'response_status' => $status,
            'outcome' => $outcome,
            'created_at' => now(),
        ]);
    }

    /**
     * A rejected web form comes back as a 302 with errors flashed to the
     * session, so the status code alone cannot tell "saved" from "refused".
     */
    private function resolveOutcome(Request $request, int $status): string
    {
        return match (true) {
            $status === 403 => ActivityLog::OUTCOME_BLOCKED,
            $status >= 500 => ActivityLog::OUTCOME_FAILED,
            $status === 422, $this->validationErrorKeys($request) !== [] => ActivityLog::OUTCOME_REJECTED,
            $status >= 400 => ActivityLog::OUTCOME_REJECTED,
            default => ActivityLog::OUTCOME_APPLIED,
        };
    }

    /**
     * Which fields were refused, if any — the names only, never the values.
     *
     * Only errors flashed by *this* request count: a failure leaves its errors
     * readable for one more request, which would otherwise mislabel the next
     * successful action as rejected.
     *
     * @return array<int, string>
     */
    private function validationErrorKeys(Request $request): array
    {
        if (! $request->hasSession()) {
            return [];
        }

        $session = $request->session();

        if (! in_array('errors', $session->get('_flash.new', []), true)) {
            return [];
        }

        $errors = $session->get('errors');

        return $errors ? array_keys($errors->getBag('default')->messages()) : [];
    }

    private function shouldAudit(Request $request, ?string $routeName): bool
    {
        if (! in_array($request->method(), self::IGNORED_METHODS, true)) {
            return true;
        }

        // Reads are audited only where looking is itself sensitive.
        return $routeName !== null
            && Str::is(config('audit.sensitive_read_routes', []), $routeName);
    }

    private function describe(Request $request, ?string $routeName, string $outcome): string
    {
        $action = $routeName
            ? Str::of($routeName)->after('admin.')->replace(['.', '_'], ' ')->title()->toString()
            : $request->method().' '.$request->path();

        $suffix = match (true) {
            $outcome === ActivityLog::OUTCOME_BLOCKED => ' — blocked, no permission',
            $outcome === ActivityLog::OUTCOME_REJECTED => ' — rejected, invalid input',
            $outcome === ActivityLog::OUTCOME_FAILED => ' — failed with an error',
            in_array($request->method(), self::IGNORED_METHODS, true) => ' — viewed',
            default => '',
        };

        return $action.$suffix;
    }

    /**
     * Best-effort subject of the action, taken from the route group:
     * admin.users.status → user, admin.contests.update → contest.
     */
    private function entityType(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }

        $segment = Str::of($routeName)->after('admin.')->before('.')->toString();

        return $segment === '' ? null : Str::singular($segment);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function entityId(array $parameters): ?int
    {
        foreach ($parameters as $value) {
            if (is_object($value) && isset($value->id) && is_numeric($value->id)) {
                return (int) $value->id;
            }

            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, int|string>
     */
    private function scalarParameters(array $parameters): array
    {
        $flattened = [];

        foreach ($parameters as $key => $value) {
            if (is_object($value)) {
                $flattened[$key] = isset($value->id) ? (int) $value->id : class_basename($value);

                continue;
            }

            if (is_scalar($value)) {
                $flattened[$key] = Str::limit((string) $value, 100, '');
            }
        }

        return $flattened;
    }

    /**
     * Submitted fields, minus credentials and framework noise, each truncated
     * so a long announcement body cannot bloat the audit table.
     *
     * @return array<string, mixed>
     */
    private function sanitizedInput(Request $request): array
    {
        $input = $request->except(config('audit.never_log_keys', []));
        $limit = (int) config('audit.max_value_length', 300);

        array_walk_recursive($input, function (&$value) use ($limit): void {
            if (is_string($value)) {
                $value = Str::limit($value, $limit);
            }
        });

        return $input;
    }
}
