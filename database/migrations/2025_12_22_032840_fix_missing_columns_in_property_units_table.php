<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('property_units', function (Blueprint $table) {
            // First, let's check if foreign key constraints exist but columns are missing
            
            // ========== TENANT REQUESTED BY COLUMN ==========
            if (!Schema::hasColumn('property_units', 'tenant_requested_by')) {
                $table->foreignId('tenant_requested_by')
                      ->nullable()
                      ->after('tenant_id')
                      ->constrained('users')
                      ->onDelete('set null')
                      ->comment('Landlord who requested tenant addition');
            }
            
            // ========== APPROVED BY COLUMN ==========
            if (!Schema::hasColumn('property_units', 'approved_by')) {
                $table->foreignId('approved_by')
                      ->nullable()
                      ->after('tenant_requested_by')
                      ->constrained('users')
                      ->onDelete('set null')
                      ->comment('Admin who approved/rejected tenant');
            }
            
            // ========== TENANT TYPE COLUMN ==========
            if (!Schema::hasColumn('property_units', 'tenant_type')) {
                $table->string('tenant_type', 20)
                      ->nullable()
                      ->after('tenant_status')
                      ->comment('Type of tenant: existing or new');
            }
            
            // ========== PROPOSED RENT COLUMN ==========
            if (!Schema::hasColumn('property_units', 'proposed_rent')) {
                $table->decimal('proposed_rent', 10, 2)
                      ->nullable()
                      ->after('monthly_rent')
                      ->comment('Proposed rent amount during tenant assignment');
            }
            
            // ========== INVITATION CHANNELS COLUMN ==========
            if (!Schema::hasColumn('property_units', 'invitation_channels')) {
                $table->json('invitation_channels')
                      ->nullable()
                      ->after('tenant_documents')
                      ->comment('Channels to send invitation to new tenant (email, sms, whatsapp)');
            }
            
            // ========== TENANT DETAILS COLUMN ==========
            if (!Schema::hasColumn('property_units', 'tenant_details')) {
                $table->json('tenant_details')
                      ->nullable()
                      ->after('tenant_documents')
                      ->comment('Complete tenant details for new tenant applications');
            }
            
            // ========== TENANT EMAIL COLUMN ==========
            if (!Schema::hasColumn('property_units', 'tenant_email')) {
                $table->string('tenant_email', 255)
                      ->nullable()
                      ->after('tenant_id')
                      ->comment('Email for new tenant (before account creation)');
            }
            
            // ========== TENANT PHONE COLUMN ==========
            if (!Schema::hasColumn('property_units', 'tenant_phone')) {
                $table->string('tenant_phone', 20)
                      ->nullable()
                      ->after('tenant_email')
                      ->comment('Phone for new tenant (before account creation)');
            }
            
            // ========== TENANT RESUBMISSION FIELDS ==========
            if (!Schema::hasColumn('property_units', 'tenant_resubmission_allowed')) {
                $table->boolean('tenant_resubmission_allowed')
                      ->nullable()
                      ->default(false)
                      ->after('tenant_approval_notes')
                      ->comment('Whether tenant application can be resubmitted after rejection');
            }
            
            if (!Schema::hasColumn('property_units', 'tenant_resubmission_notes')) {
                $table->text('tenant_resubmission_notes')
                      ->nullable()
                      ->after('tenant_resubmission_allowed')
                      ->comment('Notes for resubmission after rejection');
            }
            
            // ========== VACATING FIELDS ==========
            if (!Schema::hasColumn('property_units', 'tenant_vacate_reason')) {
                $table->text('tenant_vacate_reason')
                      ->nullable()
                      ->after('tenant_move_out_date')
                      ->comment('Reason for tenant vacating');
            }
            
            if (!Schema::hasColumn('property_units', 'property_condition')) {
                $table->string('property_condition', 50)
                      ->nullable()
                      ->after('tenant_vacate_reason')
                      ->comment('Property condition after tenant vacates (excellent, good, fair, poor)');
            }
            
            if (!Schema::hasColumn('property_units', 'cleaning_required')) {
                $table->boolean('cleaning_required')
                      ->nullable()
                      ->default(false)
                      ->after('property_condition')
                      ->comment('Whether cleaning is required after tenant vacates');
            }
            
            if (!Schema::hasColumn('property_units', 'damages_noted')) {
                $table->text('damages_noted')
                      ->nullable()
                      ->after('cleaning_required')
                      ->comment('Damages noted after tenant vacates');
            }
            
            // ========== SECURITY DEPOSIT REFUND FIELDS ==========
            if (!Schema::hasColumn('property_units', 'security_deposit_refunded')) {
                $table->decimal('security_deposit_refunded', 10, 2)
                      ->nullable()
                      ->after('security_deposit')
                      ->comment('Amount of security deposit refunded');
            }
            
            if (!Schema::hasColumn('property_units', 'security_deposit_deduction_reason')) {
                $table->text('security_deposit_deduction_reason')
                      ->nullable()
                      ->after('security_deposit_refunded')
                      ->comment('Reason for security deposit deduction');
            }
        });
        
        // Add column comments for MySQL/MariaDB
        if (DB::connection()->getDriverName() === 'mysql') {
            if (!Schema::hasColumn('property_units', 'tenant_requested_by')) {
                DB::statement("ALTER TABLE property_units MODIFY tenant_requested_by BIGINT UNSIGNED NULL COMMENT 'Landlord who requested tenant addition'");
            }
            
            if (!Schema::hasColumn('property_units', 'approved_by')) {
                DB::statement("ALTER TABLE property_units MODIFY approved_by BIGINT UNSIGNED NULL COMMENT 'Admin who approved/rejected tenant'");
            }
            
            if (!Schema::hasColumn('property_units', 'tenant_type')) {
                DB::statement("ALTER TABLE property_units MODIFY tenant_type VARCHAR(20) NULL COMMENT 'Type of tenant: existing or new'");
            }
            
            if (!Schema::hasColumn('property_units', 'proposed_rent')) {
                DB::statement("ALTER TABLE property_units MODIFY proposed_rent DECIMAL(10,2) NULL COMMENT 'Proposed rent amount during tenant assignment'");
            }
            
            if (!Schema::hasColumn('property_units', 'tenant_email')) {
                DB::statement("ALTER TABLE property_units MODIFY tenant_email VARCHAR(255) NULL COMMENT 'Email for new tenant (before account creation)'");
            }
            
            if (!Schema::hasColumn('property_units', 'tenant_phone')) {
                DB::statement("ALTER TABLE property_units MODIFY tenant_phone VARCHAR(20) NULL COMMENT 'Phone for new tenant (before account creation)'");
            }
            
            if (!Schema::hasColumn('property_units', 'tenant_resubmission_allowed')) {
                DB::statement("ALTER TABLE property_units MODIFY tenant_resubmission_allowed BOOLEAN NULL DEFAULT FALSE COMMENT 'Whether tenant application can be resubmitted after rejection'");
            }
            
            if (!Schema::hasColumn('property_units', 'property_condition')) {
                DB::statement("ALTER TABLE property_units MODIFY property_condition VARCHAR(50) NULL COMMENT 'Property condition after tenant vacates (excellent, good, fair, poor)'");
            }
            
            if (!Schema::hasColumn('property_units', 'cleaning_required')) {
                DB::statement("ALTER TABLE property_units MODIFY cleaning_required BOOLEAN NULL DEFAULT FALSE COMMENT 'Whether cleaning is required after tenant vacates'");
            }
            
            if (!Schema::hasColumn('property_units', 'security_deposit_refunded')) {
                DB::statement("ALTER TABLE property_units MODIFY security_deposit_refunded DECIMAL(10,2) NULL COMMENT 'Amount of security deposit refunded'");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We won't drop columns in the down method since they might be needed
        // But we'll provide a safe down method
        Schema::table('property_units', function (Blueprint $table) {
            // Only drop columns if they were added by this migration
            // You can check if they exist before dropping
            $columnsToDrop = [
                'tenant_type',
                'proposed_rent',
                'invitation_channels',
                'tenant_details',
                'tenant_email',
                'tenant_phone',
                'tenant_resubmission_allowed',
                'tenant_resubmission_notes',
                'tenant_vacate_reason',
                'property_condition',
                'cleaning_required',
                'damages_noted',
                'security_deposit_refunded',
                'security_deposit_deduction_reason',
            ];
            
            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('property_units', $column)) {
                    $table->dropColumn($column);
                }
            }
            
            // Note: We don't drop foreign key columns (tenant_requested_by, approved_by) 
            // as they might be needed by other parts of the system
        });
    }
};