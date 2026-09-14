<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostMedia extends StylebiteModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration_seconds' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function upload(): BelongsTo
    {
        return $this->belongsTo(MediaUpload::class, 'upload_id');
    }

    /**
     * A URL that actually loads today.
     *
     * `file_url` is absolute and was baked at upload time, so rows created
     * under an earlier host still carry it — stylebiteapp.com, a dev machine —
     * and stylebite_asset_url() leaves any non-local host alone. `file_path` is
     * relative and never goes stale, so it wins whenever it is present and the
     * absolute URL is only the fallback for rows predating that column.
     */
    protected function displayUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => stylebite_asset_url(
            $this->file_path ?: $this->file_url ?: $this->upload?->file_url
        ));
    }

    protected function displayThumbnailUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => stylebite_asset_url(
            $this->thumbnail_url ?: $this->upload?->thumbnail_url
        ) ?? ($this->media_type === 'image' ? $this->display_url : null));
    }
}
