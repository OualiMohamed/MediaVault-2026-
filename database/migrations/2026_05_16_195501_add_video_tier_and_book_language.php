<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->string('video_tier')->nullable()->after('video_quality');
        });

        Schema::table('tv_shows', function (Blueprint $table) {
            // No top-level tier — tiers are per-season inside the JSON
        });

        Schema::table('books', function (Blueprint $table) {
            $table->string('language')->nullable()->after('genre');
        });
    }

    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropColumn('video_tier');
        });

        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }
};