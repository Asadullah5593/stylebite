<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('profiles', 'monetization_status')) {
                if (Schema::hasColumn('profiles', 'verification_admin_note')) {
                    $table->string('monetization_status', 20)->default('none')->after('verification_admin_note');
                } else {
                    $table->string('monetization_status', 20)->default('none');
                }
                $table->index('monetization_status');
            }

            if (! Schema::hasColumn('profiles', 'monetization_requested_at')) {
                $table->dateTime('monetization_requested_at')->nullable()->after('monetization_status');
            }
            if (! Schema::hasColumn('profiles', 'monetization_reviewed_at')) {
                $table->dateTime('monetization_reviewed_at')->nullable()->after('monetization_requested_at');
            }
            if (! Schema::hasColumn('profiles', 'monetization_reviewed_by')) {
                $table->foreignId('monetization_reviewed_by')->nullable()->after('monetization_reviewed_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('profiles', 'monetization_admin_note')) {
                $table->string('monetization_admin_note', 500)->nullable()->after('monetization_reviewed_by');
            }
            if (! Schema::hasColumn('profiles', 'ads_enabled')) {
                $table->boolean('ads_enabled')->default(false)->after('monetization_admin_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            if (Schema::hasColumn('profiles', 'monetization_reviewed_by')) {
                $table->dropConstrainedForeignId('monetization_reviewed_by');
            }

            $drop = [];
            foreach ([
                'monetization_status',
                'monetization_requested_at',
                'monetization_reviewed_at',
                'monetization_admin_note',
                'ads_enabled',
            ] as $column) {
                if (Schema::hasColumn('profiles', $column)) {
                    $drop[] = $column;
                }
            }

            if (in_array('monetization_status', $drop, true)) {
                try {
                    $table->dropIndex(['monetization_status']);
                } catch (\Throwable) {
                }
            }

            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
