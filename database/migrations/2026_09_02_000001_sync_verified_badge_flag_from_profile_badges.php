<?php

use App\Services\VerifiedBadgeSynchronizer;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * profiles.is_verified_badge and the verified_user row in profile_badges had
     * drifted apart: admins grant the badge through the panel, which only ever
     * wrote profile_badges, while the API reads the column. Every badge awarded
     * from the dashboard therefore showed as unverified in the app.
     *
     * Model events on ProfileBadge keep the two in step from now on; this brings
     * the existing rows into line.
     */
    public function up(): void
    {
        app(VerifiedBadgeSynchronizer::class)->syncAll();
    }

    public function down(): void
    {
        // Nothing to reverse: this only makes the column agree with the badges.
    }
};
