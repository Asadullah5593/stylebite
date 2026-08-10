<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();

            // Stable machine key the code sends against, e.g. auth.login_code.
            $table->string('key', 100)->unique();
            $table->string('name', 191);
            $table->string('category', 40)->default('transactional');
            $table->string('description', 500)->nullable();

            $table->string('subject', 191);
            $table->string('heading', 191);
            $table->text('body');
            $table->string('action_text', 60)->nullable();
            $table->string('action_url', 1024)->nullable();

            // A deactivated template falls back to the built-in copy rather than
            // sending nothing — an admin must never be able to break login mail.
            $table->boolean('is_active')->default(true);

            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamps();

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
