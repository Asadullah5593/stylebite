<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalDocument extends StylebiteModel
{
    public const KEY_PRIVACY = 'privacy_policy';

    public const KEY_TERMS = 'terms';

    public const KEYS = [
        self::KEY_PRIVACY => 'Privacy Policy',
        self::KEY_TERMS => 'Terms of Service',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_published' => 'boolean',
            'requires_reacceptance' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(LegalAcceptance::class);
    }

    /**
     * The version users are currently bound by: the highest published one.
     */
    public static function current(string $key): ?self
    {
        return static::query()
            ->where('key', $key)
            ->where('is_published', true)
            ->orderByDesc('version')
            ->first();
    }

    public static function nextVersion(string $key): int
    {
        return (int) static::query()->where('key', $key)->max('version') + 1;
    }

    public function keyLabel(): string
    {
        return self::KEYS[$this->key] ?? $this->key;
    }

    /**
     * Paragraphs for display. Stored as plain text on purpose — no WYSIWYG, so
     * pasted legal prose can never inject markup into the public page.
     *
     * @return array<int, string>
     */
    public function paragraphs(): array
    {
        return array_values(array_filter(
            preg_split('/\R{2,}/', trim((string) $this->body)) ?: [],
            fn ($paragraph) => trim($paragraph) !== ''
        ));
    }
}
