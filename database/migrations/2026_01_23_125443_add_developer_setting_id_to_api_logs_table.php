<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // In the migration file
public function up()
{
    Schema::table('api_logs', function (Blueprint $table) {
        $table->foreignId('developer_setting_id')
              ->nullable()
              ->constrained()
              ->onDelete('set null')
              ->after('user_id');
    });
}

public function down()
{
    Schema::table('api_logs', function (Blueprint $table) {
        $table->dropForeign(['developer_setting_id']);
        $table->dropColumn('developer_setting_id');
    });
}
};
