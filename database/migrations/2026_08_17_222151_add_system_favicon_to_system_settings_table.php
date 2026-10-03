<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->string('system_favicon', 191)->nullable()->after('system_logo');
        });
    }

    public function down()
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('system_favicon');
        });
    }
};