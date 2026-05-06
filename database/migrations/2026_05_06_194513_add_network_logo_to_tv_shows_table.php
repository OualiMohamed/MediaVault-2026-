<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('tv_shows', function (Blueprint $table) {
            $table->string('network_logo')->nullable()->after('network');
        });
    }

    public function down()
    {
        Schema::table('tv_shows', function (Blueprint $table) {
            $table->dropColumn('network_logo');
        });
    }
};
