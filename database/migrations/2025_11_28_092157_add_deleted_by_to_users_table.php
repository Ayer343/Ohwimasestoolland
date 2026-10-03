<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   // In the migration file:
public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->unsignedBigInteger('deleted_by')->nullable()->after('deleted_at');
        $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
    });
}

public function down()
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropForeign(['deleted_by']);
        $table->dropColumn('deleted_by');
    });
}

};
