<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contest extends StylebiteModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'entry_fee' => 'decimal:2',
            'prize_pool' => 'decimal:2',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'result_at' => 'datetime',
            'enrollment_start_at' => 'datetime',
            'enrollment_end_at' => 'datetime',
            'voting_start_at' => 'datetime',
            'voting_end_at' => 'datetime',
            'is_reported' => 'boolean',
            'is_blocked' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    public function winnerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }

    public function winnerTeam(): BelongsTo
    {
        return $this->belongsTo(ContestTeam::class, 'winner_team_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ContestRule::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ContestParticipant::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(ContestTeam::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ContestSubmission::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(ContestInvitation::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ContestVote::class);
    }

    public function leaderboardSnapshots(): HasMany
    {
        return $this->hasMany(ContestLeaderboardSnapshot::class);
    }
}
