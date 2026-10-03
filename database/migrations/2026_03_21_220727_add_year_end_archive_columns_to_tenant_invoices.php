<?php
// database/migrations/2026_03_21_add_year_end_archive_columns_to_tenant_invoices.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddYearEndArchiveColumnsToTenantInvoices extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenant_invoices', function (Blueprint $table) {
            // Year-end archiving fields
            $table->timestamp('archived_at')->nullable()->after('deleted_at');
            $table->unsignedBigInteger('archived_by')->nullable()->after('archived_at');
            $table->string('archive_reason')->nullable()->after('archived_by');
            $table->string('archive_type')->nullable()->after('archive_reason');
            $table->boolean('archive_approved_by_tenant')->default(false)->after('archive_type');
            $table->timestamp('archive_approved_at')->nullable()->after('archive_approved_by_tenant');
            $table->timestamp('archive_notification_sent_at')->nullable()->after('archive_approved_at');
            $table->timestamp('archive_reminder_sent_at')->nullable()->after('archive_notification_sent_at');
            $table->string('archive_status')->default('pending')->after('archive_reminder_sent_at');
            $table->timestamp('year_end_archived_at')->nullable()->after('archive_status');
            $table->integer('year_end_archive_year')->nullable()->after('year_end_archived_at');
            $table->integer('original_year')->nullable()->after('year_end_archive_year');
            
            // Add foreign key for archived_by if needed
            // $table->foreign('archived_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_invoices', function (Blueprint $table) {
            $table->dropColumn([
                'archived_at',
                'archived_by',
                'archive_reason',
                'archive_type',
                'archive_approved_by_tenant',
                'archive_approved_at',
                'archive_notification_sent_at',
                'archive_reminder_sent_at',
                'archive_status',
                'year_end_archived_at',
                'year_end_archive_year',
                'original_year'
            ]);
        });
    }
}