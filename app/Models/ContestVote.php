<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContestVote extends StylebiteModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ContestSubmission::class, 'submission_id');
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voter_user_id');
    }
}
