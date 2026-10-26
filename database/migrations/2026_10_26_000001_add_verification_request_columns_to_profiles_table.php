<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('verification_status', 20)->default('none')->after('is_verified_badge');
            $table->dateTime('verification_requested_at')->nullable()->after('verification_status');
            $table->dateTime('verification_reviewed_at')->nullable()->after('verification_requested_at');
            $table->foreignId('verification_reviewed_by')->nullable()->after('verification_reviewed_at')
                ->constrained('users')->nullOnDelete();
            $table->string('verification_admin_note', 500)->nullable()->after('verification_reviewed_by');

            $table->index('verification_status');
        });

        DB::table('profiles')
            ->where('is_verified_badge', true)
            ->where('verification_status', 'none')
            ->update([
                'verification_status' => 'approved',
                'verification_reviewed_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verification_reviewed_by');
            $table->dropIndex(['verification_status']);
            $table->dropColumn([
                'verification_status',
                'verification_requested_at',
                'verification_reviewed_at',
                'verification_admin_note',
            ]);
        });
    }
};
