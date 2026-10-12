<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contests', function (Blueprint $table) {
            $table->string('contest_type', 120)->change();
        });
    }

    public function down(): void
    {
        Schema::table('contests', function (Blueprint $table) {
            $table->enum('contest_type', ['one_vs_one', 'group', 'city', 'brand'])->change();
        });
    }
};
