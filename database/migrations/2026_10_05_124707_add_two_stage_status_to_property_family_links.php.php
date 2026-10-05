<?php
// database/migrations/YYYY_MM_DD_HHMMSS_add_two_stage_status_to_property_family_links.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL needs a raw ALTER for enum changes; PostgreSQL/SQLite accept ->change()
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `property_family_links`
                MODIFY `status` ENUM(
                    'pending_landlord_confirmation',
                    'pending_admin_review',
                    'pending',
                    'approved',
                    'rejected',
                    'revoked',
                    'cancelled'
                ) NOT NULL DEFAULT 'pending_landlord_confirmation'");
        } else {
            Schema::table('property_family_links', function (Blueprint $table) {
                $table->string('status', 40)
                    ->default('pending_landlord_confirmation')
                    ->change();
            });
        }

        // Backfill existing 'pending' rows to the new pipeline
        DB::table('property_family_links')
            ->where('status', 'pending')
            ->update(['status' => 'pending_admin_review']);
    }

    public function down(): void
    {
        DB::table('property_family_links')
            ->whereIn('status', ['pending_landlord_confirmation', 'pending_admin_review'])
            ->update(['status' => 'pending']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `property_family_links`
                MODIFY `status` ENUM(
                    'pending', 'approved', 'rejected', 'revoked'
                ) NOT NULL DEFAULT 'pending'");
        } else {
            Schema::table('property_family_links', function (Blueprint $table) {
                $table->string('status', 40)->default('pending')->change();
            });
        }
    }
};