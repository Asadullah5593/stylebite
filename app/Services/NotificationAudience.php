<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

/**
 * Resolves an admin-chosen audience into a User query.
 *
 * Every audience is constrained to active accounts, so a banned or suspended
 * user can never receive a campaign no matter which segment is picked. The
 * per-user "push notifications enabled" preference is honoured further down, in
 * stylebite_notify_user, which marks those recipients as skipped.
 *
 * "Creator" deliberately has two meanings, because the client wants both jobs:
 *   active_posters — behavioural: published something recently. No admin upkeep.
 *   creator_role   — curated: an admin designated this account a creator.
 *
 * University targeting is deliberately absent: there is no university data in
 * the schema yet (deferred by the client). Adding it later means one new case
 * here plus a profile column — nothing else in the pipeline changes.
 */
class NotificationAudience
{
    public const TYPES = ['all_active', 'city', 'active_posters', 'creator_role', 'specific'];

    public const DEFAULT_ACTIVE_POSTER_DAYS = 30;

    public function query(string $type, array $payload = []): Builder
    {
        $query = User::query()->where('status', 'active');

        return match ($type) {
            'city' => $query->whereHas('profile', fn ($profile) => $profile
                ->whereIn('city', $this->cities($payload))),

            'active_posters' => $query->whereHas('posts', fn ($posts) => $posts
                ->where('status', 'published')
                ->where('created_at', '>=', now()->subDays($this->posterDays($payload)))),

            'creator_role' => $query->where('role', 'creator'),

            'specific' => $query->whereIn('id', $this->userIds($payload)),

            default => $query,
        };
    }

    /**
     * Human-readable audience description, stored on the campaign so the log
     * still reads correctly after the underlying data changes.
     */
    public function label(string $type, array $payload = []): string
    {
        return match ($type) {
            'city' => 'City: '.implode(', ', $this->cities($payload)),
            'active_posters' => 'Active posters (last '.$this->posterDays($payload).' days)',
            'creator_role' => 'Creator accounts',
            'specific' => count($this->userIds($payload)).' specific user(s)',
            default => 'All active users',
        };
    }

    public function validationRules(): array
    {
        return [
            'audience_type' => ['required', 'string', 'in:'.implode(',', self::TYPES)],
            'cities' => ['required_if:audience_type,city', 'array', 'min:1'],
            'cities.*' => ['string', 'max:120'],
            'user_ids' => ['required_if:audience_type,specific', 'array', 'min:1', 'max:5000'],
            'user_ids.*' => ['integer'],
            'poster_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }

    /**
     * Only the keys the chosen audience actually uses, so a city campaign
     * doesn't persist a stale user_ids array from an earlier form submission.
     */
    public function payloadFor(string $type, array $input): array
    {
        return match ($type) {
            'city' => ['cities' => $this->cities($input)],
            'active_posters' => ['poster_days' => $this->posterDays($input)],
            'specific' => ['user_ids' => $this->userIds($input)],
            default => [],
        };
    }

    private function cities(array $payload): array
    {
        return array_values(array_filter(array_map(
            fn ($city) => trim((string) $city),
            Arr::wrap($payload['cities'] ?? [])
        ), fn ($city) => $city !== ''));
    }

    private function userIds(array $payload): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($id) => (int) $id,
            Arr::wrap($payload['user_ids'] ?? [])
        ))));
    }

    private function posterDays(array $payload): int
    {
        $days = (int) ($payload['poster_days'] ?? self::DEFAULT_ACTIVE_POSTER_DAYS);

        return max(1, min(365, $days));
    }
}
