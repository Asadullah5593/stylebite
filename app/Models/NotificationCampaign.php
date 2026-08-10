<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationCampaign extends StylebiteModel
{
    protected function casts(): array
    {
        return [
            'audience_payload' => 'array',
            'total_recipients' => 'integer',
            'processed_count' => 'integer',
            'sent_count' => 'integer',
            'skipped_count' => 'integer',
            'failed_count' => 'integer',
            'last_user_id' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'cancelled'], true);
    }

    /**
     * Whole-number progress for the admin UI. Falls back to 100 on a finished
     * campaign so a zero-recipient send doesn't sit at 0% forever.
     */
    public function progressPercent(): int
    {
        if ($this->total_recipients < 1) {
            return $this->isFinished() ? 100 : 0;
        }

        return (int) min(100, round($this->processed_count / $this->total_recipients * 100));
    }
}
