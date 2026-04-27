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
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_item_id')->constrained()->cascadeOnDelete();
            $table->string('author');
            $table->string('isbn')->nullable();
            $table->integer('page_count')->nullable();
            $table->string('publisher')->nullable();
            $table->string('genre')->nullable();
            $table->tinyInteger('personal_rating')->unsigned()->nullable();
            $table->year('release_year')->nullable();
            $table->boolean('read')->default(false);
            $table->date('date_finished')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
