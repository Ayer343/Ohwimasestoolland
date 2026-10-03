<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSoftDeletesToVerificationLogs extends Migration
{
    public function up()
    {
        Schema::table('verification_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('verification_logs', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });
    }

    public function down()
    {
        Schema::table('verification_logs', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
}