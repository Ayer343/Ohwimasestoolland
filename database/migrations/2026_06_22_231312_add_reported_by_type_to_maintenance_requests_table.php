<?php
// database/migrations/xxxx_xx_xx_add_reported_by_type_to_maintenance_requests_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReportedByTypeToMaintenanceRequestsTable extends Migration
{
    public function up()
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->string('reported_by_type')->nullable()->after('reported_by_user_id');
        });
    }

    public function down()
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropColumn('reported_by_type');
        });
    }
}