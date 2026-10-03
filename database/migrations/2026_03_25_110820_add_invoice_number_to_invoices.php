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
        $table->string('invoice_number', 50)->nullable()->after('id');
        $table->index('invoice_number');
    });
}

public function down()
{
    Schema::table('invoices', function (Blueprint $table) {
        $table->dropColumn('invoice_number');
    });
}

};
