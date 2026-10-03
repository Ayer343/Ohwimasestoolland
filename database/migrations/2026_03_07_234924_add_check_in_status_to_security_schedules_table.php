<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_check_in_status_to_security_schedules_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('security_schedules', function (Blueprint $table) {
            $table->string('check_in_status')->nullable()->after('status')->default('pending');
            $table->index('check_in_status');
        });
    }

    public function down()
    {
        Schema::table('security_schedules', function (Blueprint $table) {
            $table->dropColumn('check_in_status');
        });
    }
};