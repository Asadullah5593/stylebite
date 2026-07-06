<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends StylebiteModel
{
    protected function casts(): array
    {
        return [
            'dark_mode' => 'boolean',
            'autoplay_videos' => 'boolean',
            'push_notifications_enabled' => 'boolean',
            'email_notifications_enabled' => 'boolean',
            'message_notifications_enabled' => 'boolean',
            'contest_notifications_enabled' => 'boolean',
            'show_activity_status' => 'boolean',
            'save_to_gallery_on_upload' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
