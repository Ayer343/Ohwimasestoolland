<?php
// database/migrations/2026_09_28_XXXXXX_add_scoping_and_cancellation_to_tenant_invitations.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_invitations', function (Blueprint $table) {
            // ✅ Scoping columns for lease invitations
            if (!Schema::hasColumn('tenant_invitations', 'unit_id')) {
                $table->foreignId('unit_id')->nullable()->after('property_id')
                      ->constrained('property_units')->nullOnDelete();
            }

            if (!Schema::hasColumn('tenant_invitations', 'lease_id')) {
                $table->foreignId('lease_id')->nullable()->after('unit_id')
                      ->constrained('rental_agreements')->nullOnDelete();
            }

            if (!Schema::hasColumn('tenant_invitations', 'invitation_type')) {
                $table->string('invitation_type', 40)->default('general')->after('lease_id');
            }

            // ✅ Cancellation tracking
            if (!Schema::hasColumn('tenant_invitations', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('completed_at');
            }

            if (!Schema::hasColumn('tenant_invitations', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')
                      ->constrained('users')->nullOnDelete();
            }

            // ✅ Indexes for the new query patterns
            // (explicit short names avoid the 64-char MySQL identifier limit)
            try { $table->index(['lease_id', 'status'], 'ti_lease_status_idx'); }     catch (\Throwable $e) {}
            try { $table->index(['unit_id', 'status'],  'ti_unit_status_idx'); }      catch (\Throwable $e) {}
            try { $table->index(['invitation_type', 'status'], 'ti_type_status_idx'); } catch (\Throwable $e) {}
        });
    }

    public function down(): void
    {
        Schema::table('tenant_invitations', function (Blueprint $table) {
            try { $table->dropIndex('ti_lease_status_idx'); }  catch (\Throwable $e) {}
            try { $table->dropIndex('ti_unit_status_idx'); }   catch (\Throwable $e) {}
            try { $table->dropIndex('ti_type_status_idx'); }   catch (\Throwable $e) {}

            foreach (['cancelled_by', 'lease_id', 'unit_id'] as $col) {
                if (Schema::hasColumn('tenant_invitations', $col)) {
                    $table->dropForeign([$col]);
                }
            }

            foreach ([
                'cancelled_at', 'cancelled_by',
                'invitation_type', 'lease_id', 'unit_id',
            ] as $col) {
                if (Schema::hasColumn('tenant_invitations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};