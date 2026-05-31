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

        // ── Movies: add watch_status ──
        Schema::table('movies', function (Blueprint $table) {
            if (!Schema::hasColumn('movies', 'watch_status')) {
                $table->enum('watch_status', ['not_seen', 'to_be_seen', 'seen'])->default('not_seen');
            }
        });

        // ── Migrate old data (ONLY if 'seen' column exists) ──
        if (Schema::hasColumn('movies', 'seen')) {
            DB::table('movies')->where('seen', true)->update(['watch_status' => 'seen']);
            DB::table('movies')->where('seen', false)->orWhereNull('seen')->update(['watch_status' => 'not_seen']);

            // Only drop it if it actually exists
            Schema::table('movies', function (Blueprint $table) {
                $table->dropColumn('seen');
            });
        }
    }

    public function down(): void
    {
        // ── Movies: restore seen ──
        Schema::table('movies', function (Blueprint $table) {
            if (!Schema::hasColumn('movies', 'seen')) {
                $table->boolean('seen')->default(false);
            }
        });

        if (Schema::hasColumn('movies', 'watch_status')) {
            DB::table('movies')->where('watch_status', 'seen')->update(['seen' => true]);
            DB::table('movies')->where('watch_status', '!=', 'seen')->update(['seen' => false]);

            Schema::table('movies', function (Blueprint $table) {
                $table->dropColumn('watch_status');
            });
        }

        // ── Books: revert enum ──
        DB::statement("ALTER TABLE books MODIFY COLUMN reading_status ENUM('not_started', 'reading', 'read') DEFAULT 'not_started'");
    }
};