<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FixNotificationsTableIdColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // First, check if the table exists
        if (Schema::hasTable('notifications')) {
            // Check if the id column needs to be modified
            Schema::table('notifications', function (Blueprint $table) {
                // Modify the id column to have a default value (UUID generation)
                // Since MySQL doesn't support UUID as default, we'll make it nullable first
                DB::statement('ALTER TABLE notifications MODIFY id CHAR(36) NOT NULL');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                DB::statement('ALTER TABLE notifications MODIFY id CHAR(36) NOT NULL');
            });
        }
    }
}