<?php
// database/migrations/2026_02_26_163000_add_qr_code_id_to_verification_logs.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddQrCodeIdToVerificationLogs extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        if (Schema::hasTable('verification_logs') && 
            !Schema::hasColumn('verification_logs', 'qr_code_id')) {
            
            Schema::table('verification_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('qr_code_id')->nullable()->after('id');
                $table->foreign('qr_code_id')
                      ->references('id')
                      ->on('post_qr_codes')
                      ->onDelete('set null');
                $table->index('qr_code_id', 'idx_verification_logs_qr_code_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        if (Schema::hasTable('verification_logs') && 
            Schema::hasColumn('verification_logs', 'qr_code_id')) {
            
            Schema::table('verification_logs', function (Blueprint $table) {
                $table->dropForeign(['qr_code_id']);
                $table->dropColumn('qr_code_id');
            });
        }
    }
}