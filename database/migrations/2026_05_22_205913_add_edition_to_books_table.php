<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->enum('edition', ['Hardcover', 'Paperback', 'Special Edition', 'Deluxe Edition', 'Box Set', 'Digital', 'Other'])->default('Paperback')->after('author');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('edition');
        });
    }
};