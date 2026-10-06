<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete()->cascadeOnUpdate();
            $table->string('restaurant', 191);
            $table->string('restaurant_key', 191);
            $table->decimal('food_rating', 2, 1);
            $table->decimal('service_rating', 2, 1);
            $table->decimal('staff_rating', 2, 1);
            $table->decimal('ambience_rating', 2, 1);
            $this->addTimestamps($table);

            $table->unique(['user_id', 'restaurant_key']);
            $table->index('restaurant_key');
            $table->index('post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_reviews');
    }

    private function addTimestamps(Blueprint $table): void
    {
        $table->dateTime('created_at')->useCurrent();
        $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
    }
};
