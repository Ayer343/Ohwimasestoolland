<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::table('post_qr_codes', function (Blueprint $table) {
        $table->string('name', 100)->after('id')->nullable(); // or whatever position you want
    });
}

public function down()
{
    Schema::table('post_qr_codes', function (Blueprint $table) {
        $table->dropColumn('name');
    });
}

};
