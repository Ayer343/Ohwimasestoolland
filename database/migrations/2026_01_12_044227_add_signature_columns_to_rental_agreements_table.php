<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSignatureColumnsToRentalAgreementsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rental_agreements', function (Blueprint $table) {
            // Landlord signature columns
            $table->timestamp('landlord_signed_at')->nullable()->after('signed_at');
            $table->unsignedBigInteger('landlord_signed_by')->nullable()->after('landlord_signed_at');
            $table->text('landlord_signature')->nullable()->after('landlord_signed_by');
            $table->string('landlord_signature_type', 50)->nullable()->after('landlord_signature');
            
            // Tenant signature columns  
            $table->timestamp('tenant_signed_at')->nullable()->after('landlord_signature_type');
            $table->unsignedBigInteger('tenant_signed_by')->nullable()->after('tenant_signed_at');
            $table->text('tenant_signature')->nullable()->after('tenant_signed_by');
            $table->string('tenant_signature_type', 50)->nullable()->after('tenant_signature');
            
            // Add foreign key constraints
            $table->foreign('landlord_signed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('tenant_signed_by')->references('id')->on('users')->onDelete('set null');
            
            // Also update the 'status' column length to ensure it can handle the new status values
            $table->string('status', 50)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rental_agreements', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['landlord_signed_by']);
            $table->dropForeign(['tenant_signed_by']);
            
            // Drop columns
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
            
            // Revert status column change (optional, you might need to adjust this)
            $table->string('status', 20)->nullable()->change();
        });
    }
}