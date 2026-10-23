<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('earning_transactions')) {
            return;
        }

        Schema::table('earning_transactions', function (Blueprint $table) {
            // Replace the non-unique lookup index with a uniqueness constraint
            // so contest_reward (and other typed sources) cannot double-post.
            $sm = Schema::getConnection()->getSchemaBuilder();
            $indexes = $sm->getIndexes('earning_transactions');
            foreach ($indexes as $index) {
                $cols = $index['columns'] ?? [];
                $name = $index['name'] ?? '';
                if ($cols === ['source_type', 'source_id'] && empty($index['unique'])) {
                    $table->dropIndex($name);
                }
            }

            $table->unique(['source_type', 'source_id'], 'earning_tx_source_type_source_id_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('earning_transactions')) {
            return;
        }

        Schema::table('earning_transactions', function (Blueprint $table) {
            $table->dropUnique('earning_tx_source_type_source_id_unique');
            $table->index(['source_type', 'source_id']);
        });
    }
};
