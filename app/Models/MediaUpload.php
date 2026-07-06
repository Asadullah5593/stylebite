<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MediaUpload extends StylebiteModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration_seconds' => 'integer',
            'uploaded_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function postMedia(): HasMany
    {
        return $this->hasMany(PostMedia::class, 'upload_id');
    }

    public function messageAttachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class, 'upload_id');
    }

    public function memoryMedia(): HasMany
    {
        return $this->hasMany(MemoryMedia::class, 'upload_id');
    }
}
