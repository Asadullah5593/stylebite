<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->foreignId('messaging_stopped_by_user_id')
                ->nullable()
                ->after('last_message_at')
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->dateTime('messaging_stopped_at')->nullable()->after('messaging_stopped_by_user_id');

            $table->index('messaging_stopped_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropIndex(['messaging_stopped_at']);
            $table->dropConstrainedForeignId('messaging_stopped_by_user_id');
            $table->dropColumn('messaging_stopped_at');
        });
    }
};
