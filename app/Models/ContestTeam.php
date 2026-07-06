<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContestTeam extends StylebiteModel
{
    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
        ];
    }

    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ContestTeamMember::class, 'contest_team_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ContestSubmission::class, 'contest_team_id');
    }
}
