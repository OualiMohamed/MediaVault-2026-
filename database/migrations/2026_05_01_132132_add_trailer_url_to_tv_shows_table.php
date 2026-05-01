<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tv_shows', function (Blueprint $table) {
            $table->string('trailer_url')->nullable()->after('current_episode');
        });
    }

    public function down(): void
    {
        Schema::table('tv_shows', function (Blueprint $table) {
            $table->dropColumn('trailer_url');
        });
    }
};
