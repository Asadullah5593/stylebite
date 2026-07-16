<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->char('base_currency_code', 3);
            $table->char('target_currency_code', 3);
            // Rates can be tiny (e.g. USD->KWD 0.306) or large (USD->IDR 16000+).
            $table->decimal('rate', 20, 10);
            $table->string('source', 60)->default('open.er-api.com');
            $table->dateTime('rate_at')->nullable();
            $table->dateTime('fetched_at');
            $table->timestamps();

            $table->unique(['base_currency_code', 'target_currency_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};
