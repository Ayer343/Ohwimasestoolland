<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSoftDeletesToPostQrCodesTable extends Migration
{
    public function up()
    {
        Schema::table('post_qr_codes', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::table('post_qr_codes', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
}