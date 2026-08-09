<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Per-request audit bookkeeping (bound as a singleton, so one instance per
 * request). Two jobs:
 *
 *  - hand out the request id that ties together every row written while
 *    handling one request;
 *  - remember whether a controller already wrote a domain-specific row, so the
 *    catch-all middleware only adds a generic row when nothing richer exists.
 */
class ActivityContext
{
    private ?string $requestId = null;

    /** @var array<int, int> ids of activity_logs rows written this request */
    private array $loggedIds = [];

    public function requestId(): string
    {
        return $this->requestId ??= (string) Str::uuid();
    }

    public function recordLoggedId(int $id): void
    {
        $this->loggedIds[] = $id;
    }

    /**
     * @return array<int, int>
     */
    public function loggedIds(): array
    {
        return $this->loggedIds;
    }

    public function hasDomainLog(): bool
    {
        return $this->loggedIds !== [];
    }
}
