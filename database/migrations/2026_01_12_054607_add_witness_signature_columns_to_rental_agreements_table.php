<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWitnessSignatureColumnsToRentalAgreementsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rental_agreements', function (Blueprint $table) {
            // Landlord signature columns (if not already present)
            if (!Schema::hasColumn('rental_agreements', 'landlord_signed_at')) {
                $table->timestamp('landlord_signed_at')->nullable()->after('signed_at');
            }
            
            if (!Schema::hasColumn('rental_agreements', 'landlord_signed_by')) {
                $table->unsignedBigInteger('landlord_signed_by')->nullable()->after('landlord_signed_at');
            }
            
            if (!Schema::hasColumn('rental_agreements', 'landlord_signature')) {
                $table->text('landlord_signature')->nullable()->after('landlord_signed_by');
            }
            
            if (!Schema::hasColumn('rental_agreements', 'landlord_signature_type')) {
                $table->string('landlord_signature_type', 50)->nullable()->after('landlord_signature');
            }
            
            // Tenant signature columns (if not already present)
            if (!Schema::hasColumn('rental_agreements', 'tenant_signed_at')) {
                $table->timestamp('tenant_signed_at')->nullable()->after('landlord_signature_type');
            }
            
            if (!Schema::hasColumn('rental_agreements', 'tenant_signed_by')) {
                $table->unsignedBigInteger('tenant_signed_by')->nullable()->after('tenant_signed_at');
            }
            
            if (!Schema::hasColumn('rental_agreements', 'tenant_signature')) {
                $table->text('tenant_signature')->nullable()->after('tenant_signed_by');
            }
            
            if (!Schema::hasColumn('rental_agreements', 'tenant_signature_type')) {
                $table->string('tenant_signature_type', 50)->nullable()->after('tenant_signature');
            }
            
            // ========== NEW WITNESS COLUMNS ==========
            
            // Landlord witness columns
            $table->json('landlord_witness_signature')->nullable()->after('tenant_signature_type');
            $table->timestamp('landlord_witness_signed_at')->nullable()->after('landlord_witness_signature');
            
            // Tenant witness columns
            $table->json('tenant_witness_signature')->nullable()->after('landlord_witness_signed_at');
            $table->timestamp('tenant_witness_signed_at')->nullable()->after('tenant_witness_signature');
            
            // Existing witness columns (if any, for backward compatibility)
            if (!Schema::hasColumn('rental_agreements', 'witnesses')) {
                $table->json('witnesses')->nullable()->after('signatures');
            }
            
            if (!Schema::hasColumn('rental_agreements', 'guarantors')) {
                $table->json('guarantors')->nullable()->after('witnesses');
            }
            
            // Add foreign key constraints (if columns were just added)
            if (!Schema::hasColumn('rental_agreements', 'landlord_signed_by')) {
                $table->foreign('landlord_signed_by')->references('id')->on('users')->onDelete('set null');
            }
            
            if (!Schema::hasColumn('rental_agreements', 'tenant_signed_by')) {
                $table->foreign('tenant_signed_by')->references('id')->on('users')->onDelete('set null');
            }
            
            // Update 'status' column length if needed for longer status values
            if (Schema::hasColumn('rental_agreements', 'status')) {
                $table->string('status', 50)->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rental_agreements', function (Blueprint $table) {
            // Drop foreign keys if they exist
            if (Schema::hasColumn('rental_agreements', 'landlord_signed_by')) {
                $table->dropForeign(['landlord_signed_by']);
            }
            
            if (Schema::hasColumn('rental_agreements', 'tenant_signed_by')) {
                $table->dropForeign(['tenant_signed_by']);
            }
            
            // ========== REMOVE WITNESS COLUMNS ==========
            
            // Drop new witness columns
            $table->dropColumn([
                'landlord_witness_signature',
                'landlord_witness_signed_at',
                'tenant_witness_signature',
                'tenant_witness_signed_at'
            ]);
            
            // Optionally drop main signature columns if this migration created them
            // (Uncomment if you want to completely remove signature columns in rollback)
            /*
            $table->dropColumn([
                'landlord_signed_at',
                'landlord_signed_by',
                'landlord_signature',
                'landlord_signature_type',
                'tenant_signed_at',
                'tenant_signed_by',
                'tenant_signature',
                'tenant_signature_type'
            ]);
            */
            
            // Revert status column length (optional)
            $table->string('status', 20)->nullable()->change();
        });
    }
}