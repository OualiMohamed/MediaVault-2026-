<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->string('format', 50)->change();
        });

        Schema::table('tv_shows', function (Blueprint $table) {
            $table->string('format', 50)->change();
        });
    }

    public function down(): void
    {
        // If you ever need to rollback, set back to ENUM
        DB::statement("ALTER TABLE movies MODIFY COLUMN format ENUM('DVD','Blu-ray','4K UHD','Digital','VHS') DEFAULT NULL");
        DB::statement("ALTER TABLE tv_shows MODIFY COLUMN format ENUM('Digital','DVD','Blu-ray','4K UHD','VHS') DEFAULT NULL");
    }
};