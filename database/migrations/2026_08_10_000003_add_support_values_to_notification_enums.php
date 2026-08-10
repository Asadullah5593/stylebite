<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ticket and report notifications need their own type/entity so the app can
     * deep-link to the right screen. Both are additive; existing rows keep
     * their values. The mobile app must treat unknown values defensively, which
     * is why these ship documented in the mobile changelog.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE notifications
            MODIFY type ENUM('vibe_request','like','comment','reply','follow','contest','message','system','support') NOT NULL
        ");

        DB::statement("
            ALTER TABLE notifications
            MODIFY entity_type ENUM('post','comment','reply','contest','message','user','system','support_ticket','report') NOT NULL
        ");
    }

    public function down(): void
    {
        // Rows using the new values would violate the narrower enum, so they are
        // remapped to the generic system notification first.
        DB::table('notifications')->where('type', 'support')->update(['type' => 'system']);
        DB::table('notifications')->whereIn('entity_type', ['support_ticket', 'report'])->update([
            'entity_type' => 'system',
            'entity_id' => null,
        ]);

        DB::statement("
            ALTER TABLE notifications
            MODIFY entity_type ENUM('post','comment','reply','contest','message','user','system') NOT NULL
        ");

        DB::statement("
            ALTER TABLE notifications
            MODIFY type ENUM('vibe_request','like','comment','reply','follow','contest','message','system') NOT NULL
        ");
    }
};
