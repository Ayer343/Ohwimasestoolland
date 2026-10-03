<?php
// database/migrations/2026_03_20_051654_update_tenant_invoices_safe.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // First, check if the table exists
        if (!Schema::hasTable('tenant_invoices')) {
            // Table doesn't exist, nothing to update
            return;
        }
        
        // Step 1: Add new columns without dropping old ones
        Schema::table('tenant_invoices', function (Blueprint $table) {
            // Add property_unit_id
            if (!Schema::hasColumn('tenant_invoices', 'property_unit_id')) {
                $table->foreignId('property_unit_id')
                    ->after('tenant_id')
                    ->nullable()
                    ->constrained('property_units')
                    ->onDelete('set null');
            }
            
            // Add community_dues
            if (!Schema::hasColumn('tenant_invoices', 'community_dues')) {
                $table->decimal('community_dues', 10, 2)->default(0)->after('property_unit_id');
            }
            
            // Add additional_charges
            if (!Schema::hasColumn('tenant_invoices', 'additional_charges')) {
                $table->decimal('additional_charges', 10, 2)->default(0)->after('community_dues');
            }
            
            // Add total_amount
            if (!Schema::hasColumn('tenant_invoices', 'total_amount')) {
                $table->decimal('total_amount', 10, 2)->default(0)->after('additional_charges');
            }
            
            // Add balance
            if (!Schema::hasColumn('tenant_invoices', 'balance')) {
                $table->decimal('balance', 10, 2)->default(0)->after('paid_amount');
            }
            
            // Add description
            if (!Schema::hasColumn('tenant_invoices', 'description')) {
                $table->text('description')->nullable()->after('penalty_reason');
            }
        });
        
        // Step 2: Copy data from old columns to new ones (if they exist)
        
        // Copy amount to community_dues
        if (Schema::hasColumn('tenant_invoices', 'amount') && Schema::hasColumn('tenant_invoices', 'community_dues')) {
            DB::statement("UPDATE tenant_invoices SET community_dues = amount WHERE community_dues = 0");
        }
        
        // Copy notes to description
        if (Schema::hasColumn('tenant_invoices', 'notes') && Schema::hasColumn('tenant_invoices', 'description')) {
            DB::statement("UPDATE tenant_invoices SET description = notes WHERE description IS NULL");
        }
        
        // Step 3: Calculate totals
        if (Schema::hasColumn('tenant_invoices', 'total_amount') && 
            Schema::hasColumn('tenant_invoices', 'community_dues') && 
            Schema::hasColumn('tenant_invoices', 'additional_charges') && 
            Schema::hasColumn('tenant_invoices', 'penalty_amount')) {
            DB::statement("UPDATE tenant_invoices SET total_amount = COALESCE(community_dues, 0) + COALESCE(additional_charges, 0) + COALESCE(penalty_amount, 0) WHERE total_amount = 0");
        }
        
        // Calculate balance
        if (Schema::hasColumn('tenant_invoices', 'balance') && 
            Schema::hasColumn('tenant_invoices', 'total_amount') && 
            Schema::hasColumn('tenant_invoices', 'paid_amount')) {
            DB::statement("UPDATE tenant_invoices SET balance = COALESCE(total_amount, 0) - COALESCE(paid_amount, 0) WHERE balance = 0");
        }
        
        // Step 4: Update status enum
        try {
            DB::statement("ALTER TABLE tenant_invoices MODIFY COLUMN status ENUM('pending', 'paid', 'overdue', 'cancelled') DEFAULT 'pending'");
        } catch (\Exception $e) {
            // Status enum might not exist or already modified
        }
        
        // Step 5: Add indexes
        Schema::table('tenant_invoices', function (Blueprint $table) {
            try {
                if (!Schema::hasIndex('tenant_invoices', 'tenant_invoices_property_unit_id_index')) {
                    $table->index('property_unit_id', 'tenant_invoices_property_unit_id_index');
                }
            } catch (\Exception $e) {}
            
            try {
                if (!Schema::hasIndex('tenant_invoices', 'tenant_invoices_unit_status_index')) {
                    $table->index(['property_unit_id', 'status'], 'tenant_invoices_unit_status_index');
                }
            } catch (\Exception $e) {}
            
            try {
                if (!Schema::hasIndex('tenant_invoices', 'tenant_invoices_status_due_date_index')) {
                    $table->index(['status', 'due_date'], 'tenant_invoices_status_due_date_index');
                }
            } catch (\Exception $e) {}
            
            try {
                if (!Schema::hasIndex('tenant_invoices', 'tenant_invoices_period_status_index')) {
                    $table->index(['period', 'status'], 'tenant_invoices_period_status_index');
                }
            } catch (\Exception $e) {}
        });
        
        // Step 6: Add unique constraint
        try {
            if (!Schema::hasIndex('tenant_invoices', 'unique_tenant_unit_period')) {
                DB::statement("ALTER TABLE tenant_invoices ADD UNIQUE INDEX unique_tenant_unit_period (tenant_id, property_unit_id, period)");
            }
        } catch (\Exception $e) {
            // Constraint might already exist or some records might be duplicates
        }
        
        // Step 7: Optionally, drop old columns after verifying data migration
        // Commented out for safety - run after verifying data is correct
        /*
        Schema::table('tenant_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_invoices', 'amount')) {
                $table->dropColumn('amount');
            }
            if (Schema::hasColumn('tenant_invoices', 'notes')) {
                $table->dropColumn('notes');
            }
            if (Schema::hasColumn('tenant_invoices', 'property_id')) {
                $table->dropColumn('property_id');
            }
        });
        */
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        if (!Schema::hasTable('tenant_invoices')) {
            return;
        }
        
        // Step 1: Remove unique constraint
        try {
            DB::statement("ALTER TABLE tenant_invoices DROP INDEX unique_tenant_unit_period");
        } catch (\Exception $e) {}
        
        // Step 2: Drop indexes
        Schema::table('tenant_invoices', function (Blueprint $table) {
            try {
                $table->dropIndex('tenant_invoices_property_unit_id_index');
            } catch (\Exception $e) {}
            
            try {
                $table->dropIndex('tenant_invoices_unit_status_index');
            } catch (\Exception $e) {}
            
            try {
                $table->dropIndex('tenant_invoices_status_due_date_index');
            } catch (\Exception $e) {}
            
            try {
                $table->dropIndex('tenant_invoices_period_status_index');
            } catch (\Exception $e) {}
        });
        
        // Step 3: Restore status enum
        try {
            DB::statement("ALTER TABLE tenant_invoices MODIFY COLUMN status ENUM('pending', 'paid', 'overdue', 'partial', 'cancelled') DEFAULT 'pending'");
        } catch (\Exception $e) {}
        
        // Step 4: Drop new columns
        Schema::table('tenant_invoices', function (Blueprint $table) {
            // Drop foreign key first
            if (Schema::hasColumn('tenant_invoices', 'property_unit_id')) {
                try {
                    $table->dropForeign(['property_unit_id']);
                } catch (\Exception $e) {}
                $table->dropColumn('property_unit_id');
            }
            
            // Drop other new columns
            if (Schema::hasColumn('tenant_invoices', 'community_dues')) {
                $table->dropColumn('community_dues');
            }
            
            if (Schema::hasColumn('tenant_invoices', 'additional_charges')) {
                $table->dropColumn('additional_charges');
            }
            
            if (Schema::hasColumn('tenant_invoices', 'total_amount')) {
                $table->dropColumn('total_amount');
            }
            
            if (Schema::hasColumn('tenant_invoices', 'balance')) {
                $table->dropColumn('balance');
            }
            
            if (Schema::hasColumn('tenant_invoices', 'description')) {
                $table->dropColumn('description');
            }
        });
        
        // Step 5: Restore property_id if it was dropped
        if (!Schema::hasColumn('tenant_invoices', 'property_id')) {
            Schema::table('tenant_invoices', function (Blueprint $table) {
                $table->foreignId('property_id')
                    ->after('tenant_id')
                    ->constrained('properties')
                    ->onDelete('cascade');
            });
        }
    }
};