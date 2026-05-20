<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('movies', fn(Blueprint $t) => $t->string('original_title', 255)->nullable()->after('director'));
        Schema::table('tv_shows', fn(Blueprint $t) => $t->string('original_title', 255)->nullable()->after('network'));
        Schema::table('books', fn(Blueprint $t) => $t->string('original_title', 255)->nullable()->after('author'));
    }

    public function down(): void
    {
        Schema::table('movies', fn(Blueprint $t) => $t->dropColumn('original_title'));
        Schema::table('tv_shows', fn(Blueprint $t) => $t->dropColumn('original_title'));
        Schema::table('books', fn(Blueprint $t) => $t->dropColumn('original_title'));
    }
};