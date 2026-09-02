<?php

namespace App\Models;

use App\Services\VerifiedBadgeSynchronizer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileBadge extends StylebiteModel
{
    /**
     * The verified badge is mirrored onto profiles.is_verified_badge, which is what
     * the API returns. Syncing here rather than at each call site means granting or
     * revoking the badge cannot leave the app disagreeing with the admin panel —
     * however the row was written.
     */
    protected static function booted(): void
    {
        $sync = function (self $badge): void {
            if ($badge->badge_key === VerifiedBadgeSynchronizer::BADGE_KEY
                || $badge->getOriginal('badge_key') === VerifiedBadgeSynchronizer::BADGE_KEY) {
                app(VerifiedBadgeSynchronizer::class)->sync((int) $badge->user_id);
            }
        };

        static::created($sync);
        static::updated($sync);
        static::deleted($sync);
    }

    protected function casts(): array
    {
        return [
            'earned_at' => 'datetime',
            'metadata_json' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
