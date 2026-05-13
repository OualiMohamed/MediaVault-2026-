<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('franchises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('cover_image')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        // Add franchise FK to all 5 media tables
        foreach (['movies', 'books', 'games', 'tv_shows', 'music'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('franchise_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedSmallInteger('franchise_position')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['movies', 'books', 'games', 'tv_shows', 'music'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('franchise_id');
                $table->dropColumn('franchise_position');
            });
        }

        Schema::dropIfExists('franchises');
    }
};