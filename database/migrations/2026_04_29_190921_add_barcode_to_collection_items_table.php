// database/migrations/xxxx_add_barcode_to_collection_items_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('collection_items', function (Blueprint $table) {
            $table->string('barcode')->nullable()->after('cover_image');
        });
    }

    public function down(): void
    {
        Schema::table('collection_items', function (Blueprint $table) {
            $table->dropColumn('barcode');
        });
    }
};