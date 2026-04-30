<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tv_shows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_item_id')->constrained()->cascadeOnDelete();
            $table->enum('format', ['Digital', 'DVD', 'Blu-ray', '4K UHD', 'VHS']);
            $table->integer('total_seasons')->nullable();
            $table->integer('total_episodes')->nullable();
            $table->string('network')->nullable();
            $table->string('genre')->nullable();
            $table->tinyInteger('personal_rating')->unsigned()->nullable();
            $table->year('release_year')->nullable();
            $table->enum('watch_status', ['watching', 'completed', 'dropped', 'plan_to_watch'])->default('plan_to_watch');
            $table->integer('current_season')->nullable();
            $table->integer('current_episode')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tv_shows');
    }
};