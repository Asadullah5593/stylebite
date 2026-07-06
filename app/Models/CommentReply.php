<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommentReply extends StylebiteModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'like_count' => 'integer',
            'reply_count' => 'integer',
            'is_reported' => 'boolean',
            'is_blocked' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    public function parentReply(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_reply_id');
    }

    public function childReplies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_reply_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(ReplyLike::class, 'reply_id');
    }
}
