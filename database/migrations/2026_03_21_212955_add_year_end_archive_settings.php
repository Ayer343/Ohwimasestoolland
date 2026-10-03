<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('system_settings', function (Blueprint $table) {
            // Check if columns exist before adding to prevent duplicate errors
            
            // Year-end archive settings
            if (!Schema::hasColumn('system_settings', 'enable_year_end_archive')) {
                $table->boolean('enable_year_end_archive')->default(true);
            }
            
            if (!Schema::hasColumn('system_settings', 'year_end_archive_month')) {
                $table->integer('year_end_archive_month')->default(1)->comment('Month to run year-end archive (1 = January)');
            }
            
            if (!Schema::hasColumn('system_settings', 'year_end_archive_day')) {
                $table->integer('year_end_archive_day')->default(15)->comment('Day to run year-end archive');
            }
            
            if (!Schema::hasColumn('system_settings', 'archive_paid_invoices_only')) {
                $table->boolean('archive_paid_invoices_only')->default(true);
            }
            
            if (!Schema::hasColumn('system_settings', 'keep_unpaid_invoices')) {
                $table->boolean('keep_unpaid_invoices')->default(true);
            }
            
            // Post-payment archiving settings
            if (!Schema::hasColumn('system_settings', 'paid_invoice_retention_months')) {
                $table->integer('paid_invoice_retention_months')->default(3)->comment('Months after payment to archive paid invoices');
            }
            
            if (!Schema::hasColumn('system_settings', 'auto_archive_paid_after_retention')) {
                $table->boolean('auto_archive_paid_after_retention')->default(true);
            }
            
            // Archive notification settings - Using correct column names from model
            if (!Schema::hasColumn('system_settings', 'notify_landlords_before_archive')) {
                $table->boolean('notify_landlords_before_archive')->default(true);
            }
            
            if (!Schema::hasColumn('system_settings', 'archive_notification_days_landlord')) {
                $table->integer('archive_notification_days_landlord')->default(30);
            }
            
            if (!Schema::hasColumn('system_settings', 'send_yearly_archive_report_landlord')) {
                $table->boolean('send_yearly_archive_report_landlord')->default(true);
            }
            
            // Tenant-specific archive settings
            if (!Schema::hasColumn('system_settings', 'enable_year_end_archive_tenant')) {
                $table->boolean('enable_year_end_archive_tenant')->default(true);
            }
            
            if (!Schema::hasColumn('system_settings', 'year_end_archive_month_tenant')) {
                $table->integer('year_end_archive_month_tenant')->default(1);
            }
            
            if (!Schema::hasColumn('system_settings', 'year_end_archive_day_tenant')) {
                $table->integer('year_end_archive_day_tenant')->default(16);
            }
            
            if (!Schema::hasColumn('system_settings', 'paid_invoice_retention_months_tenant')) {
                $table->integer('paid_invoice_retention_months_tenant')->default(3);
            }
            
            if (!Schema::hasColumn('system_settings', 'auto_archive_paid_after_retention_tenant')) {
                $table->boolean('auto_archive_paid_after_retention_tenant')->default(true);
            }
            
            if (!Schema::hasColumn('system_settings', 'notify_tenants_before_archive')) {
                $table->boolean('notify_tenants_before_archive')->default(true);
            }
            
            if (!Schema::hasColumn('system_settings', 'archive_notification_days_tenant')) {
                $table->integer('archive_notification_days_tenant')->default(30);
            }
            
            if (!Schema::hasColumn('system_settings', 'send_yearly_archive_report_tenant')) {
                $table->boolean('send_yearly_archive_report_tenant')->default(true);
            }
        });
    }

    public function down()
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $columns = [
                // Landlord archive settings
                'enable_year_end_archive',
                'year_end_archive_month',
                'year_end_archive_day',
                'archive_paid_invoices_only',
                'keep_unpaid_invoices',
                'paid_invoice_retention_months',
                'auto_archive_paid_after_retention',
                'notify_landlords_before_archive',
                'archive_notification_days_landlord',
                'send_yearly_archive_report_landlord',
                
                // Tenant archive settings
                'enable_year_end_archive_tenant',
                'year_end_archive_month_tenant',
                'year_end_archive_day_tenant',
                'paid_invoice_retention_months_tenant',
                'auto_archive_paid_after_retention_tenant',
                'notify_tenants_before_archive',
                'archive_notification_days_tenant',
                'send_yearly_archive_report_tenant',
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('system_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};