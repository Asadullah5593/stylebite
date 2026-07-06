<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserFollow extends Pivot
{
    use SoftDeletes;

    protected $table = 'user_follows';

    protected $guarded = [];

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_user_id');
    }

    public function following(): BelongsTo
    {
        return $this->belongsTo(User::class, 'following_user_id');
    }
}
