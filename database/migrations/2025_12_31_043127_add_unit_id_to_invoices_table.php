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
    Schema::table('invoices', function (Blueprint $table) {
        $table->foreignId('unit_id')->nullable()->constrained('property_units')->onDelete('set null');
    });
}

public function down()
{
    Schema::table('invoices', function (Blueprint $table) {
        $table->dropForeign(['unit_id']);
        $table->dropColumn('unit_id');
    });
}

};
