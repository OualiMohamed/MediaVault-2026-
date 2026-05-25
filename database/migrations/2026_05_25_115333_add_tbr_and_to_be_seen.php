<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        // ── Books: add 'tbr' to reading_status enum ──
        DB::statement("ALTER TABLE books MODIFY COLUMN reading_status ENUM('not_started', 'tbr', 'reading', 'read') DEFAULT 'not_started'");

        // ── Movies: add watch_status, migrate seen, drop seen ──
        Schema::table('movies', function (Blueprint $table) {
            $table->enum('watch_status', ['not_seen', 'to_be_seen', 'seen'])->default('not_seen')->after('date_seen');
        });

        DB::table('movies')->where('seen', true)->update(['watch_status' => 'seen']);
        DB::table('movies')->where('seen', false)->orWhereNull('seen')->update(['watch_status' => 'not_seen']);

        Schema::table('movies', function (Blueprint $table) {
            $table->dropColumn('seen');
        });
    }

    public function down(): void
    {
        // ── Movies: restore seen, migrate back, drop watch_status ──
        Schema::table('movies', function (Blueprint $table) {
            $table->boolean('seen')->default(false)->after('date_seen');
        });

        DB::table('movies')->where('watch_status', 'seen')->update(['seen' => true]);
        DB::table('movies')->where('watch_status', '!=', 'seen')->update(['seen' => false]);

        Schema::table('movies', function (Blueprint $table) {
            $table->dropColumn('watch_status');
        });

        // ── Books: revert enum ──
        DB::statement("ALTER TABLE books MODIFY COLUMN reading_status ENUM('not_started', 'reading', 'read') DEFAULT 'not_started'");
    }
};