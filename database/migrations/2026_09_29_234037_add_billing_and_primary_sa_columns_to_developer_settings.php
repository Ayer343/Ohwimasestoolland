<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('developer_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('developer_settings', 'primary_super_admin_id')) {
                $table->unsignedBigInteger('primary_super_admin_id')
                    ->nullable()
                    ->after('developer_secret_key');
                $table->index('primary_super_admin_id', 'dev_settings_primary_sa_idx');
            }

            if (!Schema::hasColumn('developer_settings', 'billing_rules')) {
                // Match existing wide-varchar convention — MySQL 5.7 safe
                $table->text('billing_rules')->nullable()->after('billing_status');
            }

            if (!Schema::hasColumn('developer_settings', 'billing_contact_name')) {
                $table->string('billing_contact_name', 255)->nullable()->after('billing_rules');
            }
            if (!Schema::hasColumn('developer_settings', 'billing_contact_email')) {
                $table->string('billing_contact_email', 255)->nullable()->after('billing_contact_name');
            }
            if (!Schema::hasColumn('developer_settings', 'billing_contact_phone')) {
                $table->string('billing_contact_phone', 30)->nullable()->after('billing_contact_email');
            }

            if (!Schema::hasColumn('developer_settings', 'payment_mobile_network')) {
                $table->string('payment_mobile_network', 30)->nullable()->after('payment_mobile_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('developer_settings', function (Blueprint $table) {
            // Drop index first if it exists
            try {
                $table->dropIndex('dev_settings_primary_sa_idx');
            } catch (\Throwable $e) {
                // index didn't exist — safe to ignore
            }

            foreach ([
                'primary_super_admin_id',
                'billing_rules',
                'billing_contact_name',
                'billing_contact_email',
                'billing_contact_phone',
                'payment_mobile_network',
            ] as $col) {
                if (Schema::hasColumn('developer_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};