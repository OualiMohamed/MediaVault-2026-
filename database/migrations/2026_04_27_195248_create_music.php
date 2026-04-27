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
        Schema::create('music', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_item_id')->constrained()->cascadeOnDelete();
            $table->enum('format', ['CD', 'Vinyl', 'Digital', 'Cassette', '8-Track']);
            $table->string('artist');
            $table->string('genre')->nullable();
            $table->string('label')->nullable();
            $table->integer('track_count')->nullable();
            $table->tinyInteger('personal_rating')->unsigned()->nullable();
            $table->year('release_year')->nullable();
            $table->enum('vinyl_speed', ['33', '45', '78'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music');
    }
};
