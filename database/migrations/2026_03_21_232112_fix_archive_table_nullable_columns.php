<?php
// database/migrations/2026_03_21_fix_archive_table_nullable_columns.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixArchiveTableNullableColumns extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_invoice_archives', function (Blueprint $table) {
            // Make columns nullable
            $table->unsignedBigInteger('deleted_by')->nullable()->change();
            $table->string('deleted_by_name')->nullable()->change();
            $table->text('deletion_reason')->nullable()->change();
            $table->string('deletion_ip')->nullable()->change();
            $table->string('deletion_user_agent')->nullable()->change();
            $table->unsignedBigInteger('original_created_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_invoice_archives', function (Blueprint $table) {
            // Revert changes if needed
        });
    }
}