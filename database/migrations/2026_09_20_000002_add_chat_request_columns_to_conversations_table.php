<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('request_status', 32)->nullable()->after('messaging_stopped_at');
            $table->foreignId('requested_by_user_id')
                ->nullable()
                ->after('request_status')
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index('request_status');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropIndex(['request_status']);
            $table->dropConstrainedForeignId('requested_by_user_id');
            $table->dropColumn('request_status');
        });
    }
};
