<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            // The dashboard's Completed Payouts card counts by when a payout was
            // settled. `status` and `requested_at` were already indexed;
            // `processed_at` was not, so that count scanned the whole table.
            $table->index('processed_at', 'withdrawal_requests_processed_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->dropIndex('withdrawal_requests_processed_at_index');
        });
    }
};
