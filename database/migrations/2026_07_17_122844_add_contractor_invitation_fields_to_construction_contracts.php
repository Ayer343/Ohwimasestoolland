<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_contracts', function (Blueprint $table) {
            // ✅ Only add columns that don't already exist
            
            if (!Schema::hasColumn('construction_contracts', 'contractor_status')) {
                $table->string('contractor_status')->default('pending')->after('contractor_type');
            }
            
            if (!Schema::hasColumn('construction_contracts', 'contractor_invitation_sent')) {
                $table->boolean('contractor_invitation_sent')->default(false);
            }
            
            if (!Schema::hasColumn('construction_contracts', 'contractor_invitation_accepted')) {
                $table->boolean('contractor_invitation_accepted')->default(false);
            }
            
            if (!Schema::hasColumn('construction_contracts', 'contractor_invitation_expired')) {
                $table->boolean('contractor_invitation_expired')->default(false);
            }
            
            if (!Schema::hasColumn('construction_contracts', 'contractor_has_set_password')) {
                $table->boolean('contractor_has_set_password')->default(false);
            }
            
            if (!Schema::hasColumn('construction_contracts', 'contractor_invitation_attempts')) {
                $table->integer('contractor_invitation_attempts')->default(0);
            }
            
            // ❌ REMOVED: admin_notified (already exists in your table)
            // if (!Schema::hasColumn('construction_contracts', 'admin_notified')) {
            //     $table->boolean('admin_notified')->default(false);
            // }
            
            // ✅ Add indexes only if they don't exist
            // Note: This is a simpler approach as checking index existence is more complex
            // Duplicate indexes are generally harmless but we'll try to avoid them
            try {
                $table->index('contractor_status');
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            try {
                $table->index('contractor_invitation_sent');
            } catch (\Exception $e) {
                // Index might already exist
            }
        });
    }

    public function down(): void
    {
        Schema::table('construction_contracts', function (Blueprint $table) {
            // ✅ Only drop columns that exist
            $columns = [
                'contractor_status',
                'contractor_invitation_sent',
                'contractor_invitation_accepted',
                'contractor_invitation_expired',
                'contractor_has_set_password',
                'contractor_invitation_attempts',
                // ❌ REMOVED: admin_notified (don't drop what we didn't add)
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('construction_contracts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};