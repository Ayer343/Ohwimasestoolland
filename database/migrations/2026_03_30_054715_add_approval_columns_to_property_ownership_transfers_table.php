<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddApprovalColumnsToPropertyOwnershipTransfersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('property_ownership_transfers', function (Blueprint $table) {
            // Add missing columns
            if (!Schema::hasColumn('property_ownership_transfers', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
            
            if (!Schema::hasColumn('property_ownership_transfers', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable();
            }
            
            if (!Schema::hasColumn('property_ownership_transfers', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
            
            if (!Schema::hasColumn('property_ownership_transfers', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable();
            }
            
            if (!Schema::hasColumn('property_ownership_transfers', 'admin_approved_by_id')) {
                $table->foreignId('admin_approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            }
            
            if (!Schema::hasColumn('property_ownership_transfers', 'completed_by_id')) {
                $table->foreignId('completed_by_id')->nullable()->constrained('users')->nullOnDelete();
            }
            
            if (!Schema::hasColumn('property_ownership_transfers', 'admin_notes')) {
                $table->text('admin_notes')->nullable();
            }
            
            if (!Schema::hasColumn('property_ownership_transfers', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }
            
            if (!Schema::hasColumn('property_ownership_transfers', 'approval_workflow_step')) {
                $table->string('approval_workflow_step')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_ownership_transfers', function (Blueprint $table) {
            $table->dropColumn([
                'approved_at',
                'rejected_at',
                'completed_at',
                'cancelled_at',
                'admin_approved_by_id',
                'completed_by_id',
                'admin_notes',
                'rejection_reason',
                'approval_workflow_step'
            ]);
        });
    }
}