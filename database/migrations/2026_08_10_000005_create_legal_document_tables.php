<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Legal documents are versioned by insert, never by update: a published
     * version is immutable, and editing creates the next one. Without that you
     * cannot answer "what exactly did this user agree to", which is the whole
     * point of recording acceptance.
     */
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();

            // 'privacy_policy' | 'terms'
            $table->string('key', 40);
            $table->unsignedInteger('version');

            $table->string('title', 191);
            $table->longText('body');
            $table->string('summary_of_changes', 500)->nullable();

            $table->boolean('is_published')->default(false);
            $table->dateTime('published_at')->nullable();

            // Forces every user to accept again — used for material changes only.
            $table->boolean('requires_reacceptance')->default(false);

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamps();

            $table->unique(['key', 'version']);
            $table->index(['key', 'is_published']);
        });

        Schema::create('legal_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('legal_document_id')->constrained('legal_documents')->cascadeOnUpdate()->cascadeOnDelete();

            // Denormalised so the record survives independently of the document row.
            $table->string('document_key', 40);
            $table->unsignedInteger('document_version');

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->dateTime('accepted_at')->useCurrent();

            $table->unique(['user_id', 'legal_document_id']);
            $table->index(['document_key', 'document_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_acceptances');
        Schema::dropIfExists('legal_documents');
    }
};
