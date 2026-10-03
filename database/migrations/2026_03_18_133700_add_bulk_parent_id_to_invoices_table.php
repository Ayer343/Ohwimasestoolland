<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBulkParentIdToInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Check if column doesn't exist before adding
            if (!Schema::hasColumn('invoices', 'bulk_parent_id')) {
                $table->unsignedBigInteger('bulk_parent_id')->nullable()->after('id');
                $table->foreign('bulk_parent_id')
                      ->references('id')
                      ->on('invoices')
                      ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'bulk_parent_id')) {
                $table->dropForeign(['bulk_parent_id']);
                $table->dropColumn('bulk_parent_id');
            }
        });
    }
}