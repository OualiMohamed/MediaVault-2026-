<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Series table
        Schema::create('book_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        // Add FK + position to books
        Schema::table('books', function (Blueprint $table) {
            $table->foreignId('series_id')->nullable()->constrained('book_series')->nullOnDelete();
            $table->unsignedSmallInteger('series_position')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropConstrainedForeignId('series_id');
            $table->dropColumn('series_position');
        });

        Schema::dropIfExists('book_series');
    }
};
