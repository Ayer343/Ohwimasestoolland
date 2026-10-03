<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            // Add admin_billing_record_id if it doesn't exist
            if (!Schema::hasColumn('billing_invoices', 'admin_billing_record_id')) {
                $table->foreignId('admin_billing_record_id')
                    ->nullable()
                    ->after('developer_setting_id')
                    ->constrained('admin_billing_records')
                    ->onDelete('cascade');
            }
            
            // Add super_admin_id if it doesn't exist
            if (!Schema::hasColumn('billing_invoices', 'super_admin_id')) {
                $table->foreignId('super_admin_id')
                    ->nullable()
                    ->after('admin_billing_record_id')
                    ->constrained('users')
                    ->onDelete('set null');
            }
            
            // Add indexes for better performance
            $table->index('admin_billing_record_id');
            $table->index('super_admin_id');
            $table->index(['admin_billing_record_id', 'status']);
            $table->index(['super_admin_id', 'status']);
        });
    }

    public function down()
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->dropForeign(['admin_billing_record_id']);
            $table->dropForeign(['super_admin_id']);
            $table->dropIndex(['admin_billing_record_id']);
            $table->dropIndex(['super_admin_id']);
            $table->dropIndex(['admin_billing_record_id', 'status']);
            $table->dropIndex(['super_admin_id', 'status']);
            
            $table->dropColumn(['admin_billing_record_id', 'super_admin_id']);
        });
    }
};