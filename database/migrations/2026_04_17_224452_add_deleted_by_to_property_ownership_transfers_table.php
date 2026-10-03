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
    Schema::table('property_ownership_transfers', function (Blueprint $table) {
        $table->unsignedBigInteger('deleted_by')->nullable()->after('deleted_at');
        $table->index('deleted_by');
    });
}

public function down()
{
    Schema::table('property_ownership_transfers', function (Blueprint $table) {
        $table->dropColumn('deleted_by');
    });
}

};
