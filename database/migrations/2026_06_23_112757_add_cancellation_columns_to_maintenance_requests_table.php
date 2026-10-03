<?php
// database/migrations/xxxx_xx_xx_add_cancellation_columns_to_maintenance_requests_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCancellationColumnsToMaintenanceRequestsTable extends Migration
{
    public function up()
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            // Check if columns exist before adding
            if (!Schema::hasColumn('maintenance_requests', 'cancelled_by')) {
                $table->unsignedBigInteger('cancelled_by')->nullable()->after('updated_by');
                $table->foreign('cancelled_by')->references('id')->on('users')->onDelete('set null');
            }
            
            if (!Schema::hasColumn('maintenance_requests', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            }
            
            if (!Schema::hasColumn('maintenance_requests', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            }
        });
    }

    public function down()
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            if (Schema::hasColumn('maintenance_requests', 'cancelled_by')) {
                $table->dropForeign(['cancelled_by']);
                $table->dropColumn('cancelled_by');
            }
            
            if (Schema::hasColumn('maintenance_requests', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
            
            if (Schema::hasColumn('maintenance_requests', 'cancellation_reason')) {
                $table->dropColumn('cancellation_reason');
            }
        });
    }
}