<?php
// database/migrations/2026_10_03_000002_add_primary_billing_flag_to_admin_billing_records.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_billing_records', function (Blueprint $table) {
            // Add the column if it doesn't already exist
            if (!Schema::hasColumn('admin_billing_records', 'is_primary_for_billing')) {
                $table->boolean('is_primary_for_billing')
                      ->default(false)
                      ->after('developer_setting_id');
            }
        });

        // Add the composite index with a SHORT name
        // (the auto-generated name was 87 chars → MySQL 64-char limit → error 1059)
        Schema::table('admin_billing_records', function (Blueprint $table) {
            $table->index(
                ['developer_setting_id', 'is_primary_for_billing'],
                'abr_dev_primary_idx'   // ← 19 chars, safely under 64
            );
        });
    }

    public function down(): void
    {
        Schema::table('admin_billing_records', function (Blueprint $table) {
            $table->dropIndex('abr_dev_primary_idx');
            $table->dropColumn('is_primary_for_billing');
        });
    }
};