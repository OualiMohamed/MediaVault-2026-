<?php

use Illuminate\Database\Migrations\Migration;
// use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        DB::statement("ALTER TABLE games MODIFY COLUMN platform ENUM('PS5','PS4','PS3','PS Vita','Switch','Wii U','Wii','Nintendo DS','Xbox Series X','Xbox One','PC','Steam','Other') NOT NULL");
    }

    public function down()
    {
        DB::statement("ALTER TABLE games MODIFY COLUMN platform ENUM('PS5','PS4','PS3','PS Vita','Switch','Wii U','Wii','Xbox Series X','Xbox One','PC','Steam','Other') NOT NULL");
    }
};
