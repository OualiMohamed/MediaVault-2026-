<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── Books ──
        Schema::table('books', function (Blueprint $table) {
            $table->enum('reading_status', ['not_started', 'reading', 'read'])
                ->default('not_started')
                ->after('date_finished');
            $table->integer('current_page')->nullable()->after('reading_status');
        });

        DB::table('books')->where('read', true)->update(['reading_status' => 'read']);
        DB::table('books')->where('read', false)->orWhereNull('read')->update(['reading_status' => 'not_started']);

        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('read');
        });

        // ── Games ──
        Schema::table('games', function (Blueprint $table) {
            $table->enum('playing_status', ['not_started', 'playing', 'completed', 'dropped'])
                ->default('not_started')
                ->after('completion_date');
            $table->tinyInteger('progress_percent')->unsigned()->nullable()->after('playing_status');
        });

        DB::table('games')->where('completed', true)->update(['playing_status' => 'completed']);
        DB::table('games')->where('completed', false)->orWhereNull('completed')->update(['playing_status' => 'not_started']);

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('completed');
        });
    }

    public function down(): void
    {
        // ── Books ──
        Schema::table('books', function (Blueprint $table) {
            $table->boolean('read')->default(false)->after('date_finished');
        });
        DB::table('books')->where('reading_status', 'read')->update(['read' => true]);
        DB::table('books')->where('reading_status', '!=', 'read')->update(['read' => false]);
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['reading_status', 'current_page']);
        });

        // ── Games ──
        Schema::table('games', function (Blueprint $table) {
            $table->boolean('completed')->default(false)->after('completion_date');
        });
        DB::table('games')->where('playing_status', 'completed')->update(['completed' => true]);
        DB::table('games')->where('playing_status', '!=', 'completed')->update(['completed' => false]);
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['playing_status', 'progress_percent']);
        });
    }
};