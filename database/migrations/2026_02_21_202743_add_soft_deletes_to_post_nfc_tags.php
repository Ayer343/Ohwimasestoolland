<?php
// database/migrations/xxxx_xx_xx_add_soft_deletes_to_post_nfc_tags.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSoftDeletesToPostNfcTags extends Migration
{
    public function up()
    {
        Schema::table('post_nfc_tags', function (Blueprint $table) {
            if (!Schema::hasColumn('post_nfc_tags', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });
    }

    public function down()
    {
        Schema::table('post_nfc_tags', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
}