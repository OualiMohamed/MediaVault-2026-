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
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_item_id')->constrained()->cascadeOnDelete();
            $table->enum('platform', ['PS5', 'PS4', 'PS3', 'PS Vita', 'Switch', 'Wii U', 'Wii', 'Xbox Series X', 'Xbox One', 'PC', 'Steam', 'Other']);
            $table->enum('format', ['Physical', 'Digital']);
            $table->string('genre')->nullable();
            $table->string('publisher')->nullable();
            $table->tinyInteger('personal_rating')->unsigned()->nullable();
            $table->year('release_year')->nullable();
            $table->boolean('completed')->default(false);
            $table->date('completion_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
