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
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_item_id')->constrained()->cascadeOnDelete();
            $table->enum('format', ['DVD', 'Blu-ray', '4K UHD', 'Digital', 'VHS']);
            $table->integer('runtime_minutes')->nullable();
            $table->string('director')->nullable();
            $table->string('genre')->nullable();
            $table->tinyInteger('personal_rating')->unsigned()->nullable(); // 1-10
            $table->year('release_year')->nullable();
            $table->string('imdb_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
