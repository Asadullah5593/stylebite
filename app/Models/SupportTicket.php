<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends StylebiteModel
{
    public const CATEGORIES = [
        'bug' => 'Bug report',
        'payment' => 'Payment or payout',
        'account' => 'Account and login',
        'content_appeal' => 'Content appeal',
        'other' => 'Something else',
    ];

    public const STATUSES = [
        'open' => 'Open',
        'in_progress' => 'In progress',
        'waiting_on_user' => 'Waiting on user',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];

    public const PRIORITIES = [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'urgent' => 'Urgent',
    ];

    /** Statuses where nobody is waiting on staff any more. */
    public const FINISHED_STATUSES = ['resolved', 'closed'];

    protected function casts(): array
    {
        return [
            'messages_count' => 'integer',
            'last_reply_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class);
    }

    /**
     * Messages the ticket owner is allowed to see — internal staff notes are
     * excluded here so no API path can leak them by forgetting a filter.
     */
    public function visibleMessages(): HasMany
    {
        return $this->messages()->where('is_internal', false);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, self::FINISHED_STATUSES, true);
    }

    /**
     * Sequential, quotable handle. Generated from the id after insert so it
     * cannot collide, unlike a random string.
     */
    public static function referenceFor(int $id): string
    {
        return 'TK-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
