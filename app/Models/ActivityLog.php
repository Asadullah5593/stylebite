<?php

namespace App\Models;

use App\Support\ActivityContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ActivityLog extends StylebiteModel
{
    /** The action went through. */
    public const OUTCOME_APPLIED = 'applied';

    /** Turned away for lack of permission. */
    public const OUTCOME_BLOCKED = 'blocked';

    /** Reached the app but was refused — invalid input or a guard rule. */
    public const OUTCOME_REJECTED = 'rejected';

    /** Blew up with a server error. */
    public const OUTCOME_FAILED = 'failed';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'metadata_json' => 'array',
            'response_status' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Every row gets the request context filled in automatically, whichever
     * controller wrote it — so the older hand-written logActivity() calls
     * gain route/method/actor-role without touching each call site. Anything
     * a caller set explicitly is left alone.
     */
    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            $log->fillRequestContext();
        });

        static::created(function (self $log): void {
            // Tells the audit middleware a domain row already covers this
            // request, so it does not add a generic duplicate.
            app(ActivityContext::class)->recordLoggedId($log->id);
        });
    }

    /**
     * Write an audit row. Preferred entry point for new code — request context,
     * actor, and timestamps are handled here.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function record(
        string $eventName,
        ?string $entityType = null,
        ?int $entityId = null,
        array $metadata = [],
        ?string $description = null,
        ?int $userId = null,
        string $actorType = 'admin',
    ): self {
        return self::create([
            'user_id' => $userId ?? auth()->id(),
            'actor_type' => $actorType,
            'event_name' => $eventName,
            'description' => $description ? Str::limit($description, 250, '') : null,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata_json' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether the audited request was actually carried out. Blocked and
     * rejected attempts are kept on purpose — they matter in an audit trail —
     * so they must read as "not applied".
     */
    public function wasApplied(): bool
    {
        return $this->outcome === null || $this->outcome === self::OUTCOME_APPLIED;
    }

    public function outcomeLabel(): string
    {
        return ucfirst($this->outcome ?? self::OUTCOME_APPLIED);
    }

    private function fillRequestContext(): void
    {
        $request = request();
        $route = $request->route();

        // A resolved route is what distinguishes a real HTTP request from a
        // console run (where request() exists but is a synthetic placeholder).
        if ($route !== null) {
            $this->request_id ??= app(ActivityContext::class)->requestId();
            $this->http_method ??= $request->method();
            $this->route_name ??= $route->getName();
            $this->url ??= Str::limit($request->fullUrl(), 500, '');
            $this->ip_address ??= $request->ip();
            $this->user_agent ??= $request->userAgent() ? Str::limit($request->userAgent(), 250, '') : null;
        }

        $this->created_at ??= now();

        if ($this->actor_role === null) {
            $actor = $this->user_id === auth()->id() ? auth()->user() : null;

            $this->actor_role = $actor
                ? ($actor->roles->pluck('name')->implode(', ') ?: $actor->role)
                : null;
        }
    }
}
