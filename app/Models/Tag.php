<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tag extends StylebiteModel
{
    public function postTags(): HasMany
    {
        return $this->hasMany(PostTag::class);
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_tags')
            ->using(PostTag::class)
            ->withPivot(['id', 'created_at']);
    }
}
