<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('collection_items', function (Blueprint $table) {
            $table->string('borrowed_to')->nullable()->after('status');
            $table->date('due_back_date')->nullable()->after('borrowed_to');
        });
    }

    public function down(): void
    {
        Schema::table('collection_items', function (Blueprint $table) {
            $table->dropColumn(['borrowed_to', 'due_back_date']);
        });
    }
};