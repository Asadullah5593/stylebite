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

    /**
     * The URL to actually play or open in a browser.
     *
     * For video this is the rendition, not the upload. The original is whatever
     * the phone produced — most of ours are .mov/video/quicktime, and iPhones
     * record HEVC by default — and browsers play almost none of that: HEVC
     * decodes its AAC track and shows a black frame, .mkv has no mime mapping
     * and downloads instead. The rendition is always H.264/AAC in MP4 with
     * +faststart, so it plays everywhere and starts without buffering the whole
     * file. Images render fine as uploaded and are worth inspecting at full
     * fidelity, so they keep the original.
     *
     * Falls back to the original when no rendition exists (transcode failed, or
     * the row predates the pipeline) — a sound-only preview still beats none.
     */
    protected function previewUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->media_type === 'video' && filled($this->optimized_path ?: $this->optimized_url)) {
                return stylebite_asset_url($this->optimized_path ?: $this->optimized_url);
            }

            return $this->display_url;
        });
    }

    /**
     * Whether preview_url points at a rendition rather than the upload itself,
     * so the UI can offer the original as a separate, labelled link.
     */
    protected function hasSeparateOriginal(): Attribute
    {
        return Attribute::get(fn (): bool => $this->preview_url !== null
            && $this->display_url !== null
            && $this->preview_url !== $this->display_url);
    }
}
