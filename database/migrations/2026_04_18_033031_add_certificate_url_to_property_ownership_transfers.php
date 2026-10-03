<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('property_ownership_transfers', function (Blueprint $table) {
            $table->string('certificate_url')->nullable()->after('document_url');
        });
    }

    public function down()
    {
        Schema::table('property_ownership_transfers', function (Blueprint $table) {
            $table->dropColumn('certificate_url');
        });
    }
};