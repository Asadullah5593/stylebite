<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Turns activity_logs into a full audit trail: alongside the domain event
     * it now records who acted in what capacity, the exact request they made,
     * and how the app answered — so a denied or failed attempt is as visible
     * as a successful one.
     */
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            // Panel role held at the time of the action (roles can change later).
            $table->string('actor_role', 60)->nullable()->after('actor_type');
            // One-line human summary, e.g. "Banned user #42".
            $table->string('description', 255)->nullable()->after('event_name');
            $table->string('http_method', 10)->nullable()->after('metadata_json');
            $table->string('route_name', 150)->nullable()->after('http_method');
            $table->string('url', 512)->nullable()->after('route_name');
            $table->unsignedSmallInteger('response_status')->nullable()->after('url');
            // What became of the attempt: applied | blocked | rejected | failed.
            // Kept separate from the HTTP status because a rejected web form
            // redirects with a 302, which says nothing on its own.
            $table->string('outcome', 20)->nullable()->after('response_status');
            // Correlates every row written during the same request.
            $table->uuid('request_id')->nullable()->after('response_status');

            $table->index('route_name');
            $table->index('request_id');
            $table->index('outcome');
            $table->index(['actor_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['route_name']);
            $table->dropIndex(['request_id']);
            $table->dropIndex(['outcome']);
            $table->dropIndex(['actor_type', 'created_at']);

            $table->dropColumn([
                'actor_role',
                'description',
                'http_method',
                'route_name',
                'url',
                'response_status',
                'outcome',
                'request_id',
            ]);
        });
    }
};
