<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('earning_transactions', function (Blueprint $table) {
            // Audit trail: proves how `amount` (in the wallet's currency) was
            // derived from the reward's base-currency value at credit time.
            // Null on legacy rows credited before FX conversion existed.
            $table->decimal('base_amount', 14, 2)->nullable()->after('amount');
            $table->char('base_currency_code', 3)->nullable()->after('base_amount');
            $table->decimal('fx_rate', 20, 10)->nullable()->after('base_currency_code');
            $table->dateTime('fx_rate_at')->nullable()->after('fx_rate');
        });
    }

    public function down(): void
    {
        Schema::table('earning_transactions', function (Blueprint $table) {
            $table->dropColumn(['base_amount', 'base_currency_code', 'fx_rate', 'fx_rate_at']);
        });
    }
};
