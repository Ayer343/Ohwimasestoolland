<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('property_ownership_transfers', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignId('property_id')
                  ->constrained('properties')
                  ->onDelete('cascade')
                  ->comment('The property being transferred');
            
            $table->foreignId('current_landlord_id')
                  ->constrained('users')
                  ->onDelete('cascade')
                  ->comment('Current property owner');
            
            $table->foreignId('new_landlord_id')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('cascade')
                  ->comment('New property owner (if existing user)');
            
            $table->foreignId('requested_by_id')
                  ->constrained('users')
                  ->onDelete('cascade')
                  ->comment('User who submitted the request');
            
            $table->foreignId('admin_approved_by_id')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('cascade')
                  ->comment('Admin who approved the transfer');
            
            // Status
            $table->enum('status', [
                'pending',     // Submitted, awaiting admin review
                'approved',    // Admin approved, invitation sent to new owner
                'rejected',    // Admin rejected the transfer
                'cancelled',   // Landlord cancelled the request
                'completed',   // Transfer completed successfully
            ])->default('pending')
              ->index('pot_status_idx')  // Custom index name
              ->comment('Current status of the transfer request');
            
            // Transfer details
            $table->date('transfer_date')
                  ->index('pot_transfer_date_idx')  // Custom index name
                  ->comment('Official date of ownership transfer');
            
            $table->decimal('sale_amount', 15, 2)
                  ->nullable()
                  ->comment('Sale amount (if applicable)');
            
            // Document details
            $table->enum('document_type', [
                'sale_deed',            // Sale deed
                'title_deed',           // Title deed
                'conveyance',           // Conveyance document
                'transfer_certificate', // Transfer certificate
                'gift_deed',            // Gift deed
                'will_probate',         // Will/Probate document
                'other',                // Other document type
            ])->comment('Type of transfer document');
            
            $table->string('document_reference', 100)
                  ->comment('Document reference number (e.g., deed number)');
            
            $table->string('document_url')
                  ->nullable()
                  ->comment('Path to uploaded document file');
            
            // New owner details (captured even if not yet a system user)
            $table->string('new_owner_name')
                  ->comment('Full name of new owner');
            
            $table->string('new_owner_phone')
                  ->comment('Phone number of new owner');
            
            $table->string('new_owner_email')
                  ->nullable()
                  ->comment('Email address of new owner');
            
            $table->text('new_owner_address')
                  ->nullable()
                  ->comment('Physical address of new owner');
            
            $table->string('new_owner_id_type')
                  ->nullable()
                  ->comment('ID type (e.g., National ID, Passport)');
            
            $table->string('new_owner_id_number')
                  ->nullable()
                  ->comment('ID number');
            
            // Additional information
            $table->text('reason_for_transfer')
                  ->nullable()
                  ->comment('Reason for ownership transfer');
            
            $table->text('notes')
                  ->nullable()
                  ->comment('Additional notes from current landlord');
            
            $table->text('admin_notes')
                  ->nullable()
                  ->comment('Notes from admin during review');
            
            $table->text('rejection_reason')
                  ->nullable()
                  ->comment('Reason for rejection if transfer was rejected');
            
            // Timestamps
            $table->timestamp('invitation_sent_at')
                  ->nullable()
                  ->comment('When invitation was sent to new owner');
            
            $table->timestamp('invitation_accepted_at')
                  ->nullable()
                  ->comment('When new owner accepted the invitation');
            
            $table->timestamp('completed_at')
                  ->nullable()
                  ->comment('When transfer was completed');
            
            $table->softDeletes();
            $table->timestamps();
            
            // Additional indexes for performance - with custom names
            $table->index(['property_id', 'status'], 'pot_property_status_idx');
            $table->index(['current_landlord_id', 'status'], 'pot_current_landlord_status_idx');
            $table->index(['new_landlord_id', 'status'], 'pot_new_landlord_status_idx');
            $table->index('created_at', 'pot_created_at_idx');
        });
        
        // Create property ownership history table
        Schema::create('property_ownership_history', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignId('property_id')
                  ->constrained('properties')
                  ->onDelete('cascade');
            
            $table->foreignId('previous_landlord_id')
                  ->constrained('users')
                  ->onDelete('cascade')
                  ->comment('Previous property owner');
            
            $table->foreignId('new_landlord_id')
                  ->constrained('users')
                  ->onDelete('cascade')
                  ->comment('New property owner');
            
            $table->foreignId('transfer_id')
                  ->constrained('property_ownership_transfers')
                  ->onDelete('cascade')
                  ->comment('Reference to the transfer record');
            
            // Transfer details
            $table->date('transfer_date')
                  ->index('poh_transfer_date_idx')  // Custom name to avoid conflict
                  ->comment('Date when ownership was transferred');
            
            $table->decimal('sale_amount', 15, 2)
                  ->nullable()
                  ->comment('Sale amount (if applicable)');
            
            $table->string('document_type')
                  ->comment('Type of transfer document used');
            
            $table->string('document_reference')
                  ->comment('Document reference number');
            
            $table->string('previous_owner_name')
                  ->comment('Name of previous owner');
            
            $table->string('new_owner_name')
                  ->comment('Name of new owner');
            
            $table->string('admin_name')
                  ->nullable()
                  ->comment('Admin who processed the transfer');
            
            // Metadata
            $table->json('metadata')
                  ->nullable()
                  ->comment('Additional metadata about the transfer');
            
            $table->timestamps();
            
            // Indexes with custom names
            $table->index('property_id', 'poh_property_id_idx');
            $table->index(['property_id', 'transfer_date'], 'poh_property_transfer_date_idx');
            
            // Note: Removed the duplicate transfer_date index since we already have one above
        });
        
        // Add ownership transfer columns to properties table
        Schema::table('properties', function (Blueprint $table) {
            // First check if the columns already exist
            if (!Schema::hasColumn('properties', 'previous_landlord_id')) {
                $table->foreignId('previous_landlord_id')
                      ->nullable()
                      ->after('landlord_id')
                      ->constrained('users')
                      ->onDelete('set null')
                      ->comment('Previous landlord (for ownership history)');
            }
            
            if (!Schema::hasColumn('properties', 'ownership_transferred_at')) {
                $table->timestamp('ownership_transferred_at')
                      ->nullable()
                      ->after('previous_landlord_id')
                      ->comment('When ownership was last transferred');
            }
            
            if (!Schema::hasColumn('properties', 'ownership_transfer_count')) {
                $table->integer('ownership_transfer_count')
                      ->default(0)
                      ->after('ownership_transferred_at')
                      ->comment('Number of times ownership has been transferred');
            }
            
            if (!Schema::hasColumn('properties', 'ownership_history_summary')) {
                $table->json('ownership_history_summary')
                      ->nullable()
                      ->after('ownership_transfer_count')
                      ->comment('Summary of ownership history');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop columns from properties table only if they exist
        Schema::table('properties', function (Blueprint $table) {
            $table->dropForeignIfExists(['previous_landlord_id']);
            
            if (Schema::hasColumn('properties', 'previous_landlord_id')) {
                $table->dropColumn('previous_landlord_id');
            }
            if (Schema::hasColumn('properties', 'ownership_transferred_at')) {
                $table->dropColumn('ownership_transferred_at');
            }
            if (Schema::hasColumn('properties', 'ownership_transfer_count')) {
                $table->dropColumn('ownership_transfer_count');
            }
            if (Schema::hasColumn('properties', 'ownership_history_summary')) {
                $table->dropColumn('ownership_history_summary');
            }
        });
        
        // Drop history table
        Schema::dropIfExists('property_ownership_history');
        
        // Drop transfers table
        Schema::dropIfExists('property_ownership_transfers');
    }
};