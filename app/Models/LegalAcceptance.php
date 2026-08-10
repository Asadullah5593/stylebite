<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalAcceptance extends StylebiteModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'document_version' => 'integer',
            'accepted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'legal_document_id');
    }
}
