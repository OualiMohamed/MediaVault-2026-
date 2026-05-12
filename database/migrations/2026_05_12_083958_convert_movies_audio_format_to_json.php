<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Convert existing single strings to JSON arrays
        DB::table('movies')
            ->whereNotNull('audio_format')
            ->where('audio_format', 'NOT LIKE', '[%')
            ->update([
                'audio_format' => DB::raw('JSON_ARRAY(audio_format)'),
            ]);

        Schema::table('movies', function (Blueprint $table) {
            $table->json('audio_format')->nullable()->change();
        });
    }

    public function down(): void
    {
        $movies = DB::table('movies')->whereNotNull('audio_format')->get();
        foreach ($movies as $movie) {
            $formats = json_decode($movie->audio_format, true);
            if (is_array($formats)) {
                DB::table('movies')->where('id', $movie->id)
                    ->update(['audio_format' => implode(', ', $formats)]);
            }
        }

        Schema::table('movies', function (Blueprint $table) {
            $table->string('audio_format', 50)->nullable()->change();
        });
    }
};