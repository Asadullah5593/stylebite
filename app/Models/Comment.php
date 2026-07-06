<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends StylebiteModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_reported' => 'boolean',
            'is_blocked' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(CommentReply::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(CommentLike::class);
    }
}
