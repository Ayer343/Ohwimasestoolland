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
        Schema::table('properties', function (Blueprint $table) {
            // Previous landlord reference
            if (!Schema::hasColumn('properties', 'previous_landlord_id')) {
                $table->foreignId('previous_landlord_id')
                      ->nullable()
                      ->after('landlord_id')
                      ->constrained('users')
                      ->onDelete('set null')
                      ->comment('Previous property owner (for ownership history)');
            }
            
            // Ownership transfer tracking
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
            
            // Check if property_ownership_transfers table exists before adding foreign key
            if (!Schema::hasColumn('properties', 'last_transfer_id') && Schema::hasTable('property_ownership_transfers')) {
                $table->foreignId('last_transfer_id')
                      ->nullable()
                      ->after('ownership_transfer_count')
                      ->constrained('property_ownership_transfers')
                      ->onDelete('set null')
                      ->comment('Reference to the last ownership transfer');
            } elseif (!Schema::hasColumn('properties', 'last_transfer_id')) {
                $table->foreignId('last_transfer_id')
                      ->nullable()
                      ->after('ownership_transfer_count')
                      ->comment('Reference to the last ownership transfer (table not available)');
            }
            
            // Current transfer status
            if (!Schema::hasColumn('properties', 'current_transfer_status')) {
                $table->enum('current_transfer_status', [
                    'none',         // No active transfer
                    'pending',      // Transfer request submitted
                    'approved',     // Transfer approved, waiting for completion
                    'in_progress',  // Transfer in progress
                ])->default('none')
                  ->after('last_transfer_id')
                  ->comment('Current status of any ownership transfer');
            }
            
            // Original registration information
            if (!Schema::hasColumn('properties', 'original_landlord_id')) {
                $table->foreignId('original_landlord_id')
                      ->nullable()
                      ->after('current_transfer_status')
                      ->constrained('users')
                      ->onDelete('set null')
                      ->comment('First/Original property owner');
            }
            
            if (!Schema::hasColumn('properties', 'original_registration_date')) {
                $table->timestamp('original_registration_date')
                      ->nullable()
                      ->after('original_landlord_id')
                      ->comment('Date when property was first registered');
            }
            
            // Ownership chain tracking
            if (!Schema::hasColumn('properties', 'ownership_generation')) {
                $table->integer('ownership_generation')
                      ->default(1)
                      ->after('original_registration_date')
                      ->comment('Ownership generation (1st owner, 2nd owner, etc.)');
            }
            
            // Current owner tenure
            if (!Schema::hasColumn('properties', 'current_owner_tenure_days')) {
                $table->integer('current_owner_tenure_days')
                      ->default(0)
                      ->after('ownership_generation')
                      ->comment('Number of days current owner has owned the property');
            }
            
            // Summary fields for quick access
            if (!Schema::hasColumn('properties', 'ownership_history_summary')) {
                $table->json('ownership_history_summary')
                      ->nullable()
                      ->after('current_owner_tenure_days')
                      ->comment('Summary of ownership history (last 5 transfers)');
            }
            
            if (!Schema::hasColumn('properties', 'current_ownership_details')) {
                $table->json('current_ownership_details')
                      ->nullable()
                      ->after('ownership_history_summary')
                      ->comment('Details of current ownership');
            }
            
            if (!Schema::hasColumn('properties', 'previous_ownership_details')) {
                $table->json('previous_ownership_details')
                      ->nullable()
                      ->after('current_ownership_details')
                      ->comment('Details of previous ownership');
            }
            
            // Transfer restrictions/flags
            if (!Schema::hasColumn('properties', 'is_transfer_restricted')) {
                $table->boolean('is_transfer_restricted')
                      ->default(false)
                      ->after('previous_ownership_details')
                      ->comment('Whether property has transfer restrictions');
            }
            
            if (!Schema::hasColumn('properties', 'transfer_restriction_reason')) {
                $table->text('transfer_restriction_reason')
                      ->nullable()
                      ->after('is_transfer_restricted')
                      ->comment('Reason for transfer restriction');
            }
            
            if (!Schema::hasColumn('properties', 'transfer_restriction_until')) {
                $table->date('transfer_restriction_until')
                      ->nullable()
                      ->after('transfer_restriction_reason')
                      ->comment('Date until which transfer is restricted');
            }
            
            // Indexes for performance - only add if columns exist
            if (Schema::hasColumn('properties', 'previous_landlord_id')) {
                $table->index('previous_landlord_id', 'properties_prev_landlord_idx');
            }
            
            if (Schema::hasColumn('properties', 'original_landlord_id')) {
                $table->index('original_landlord_id', 'properties_original_landlord_idx');
            }
            
            if (Schema::hasColumn('properties', 'current_transfer_status')) {
                $table->index('current_transfer_status', 'properties_transfer_status_idx');
            }
            
            if (Schema::hasColumn('properties', 'ownership_transferred_at')) {
                $table->index('ownership_transferred_at', 'properties_transferred_at_idx');
            }
            
            if (Schema::hasColumn('properties', 'landlord_id') && Schema::hasColumn('properties', 'current_transfer_status')) {
                $table->index(['landlord_id', 'current_transfer_status'], 'properties_landlord_status_idx');
            }
            
            if (Schema::hasColumn('properties', 'previous_landlord_id') && Schema::hasColumn('properties', 'ownership_transferred_at')) {
                $table->index(['previous_landlord_id', 'ownership_transferred_at'], 'properties_prev_owner_transfer_idx');
            }
        });
        
        // Update existing properties to set original values
        // Only run if columns were just added
        if (Schema::hasColumn('properties', 'original_landlord_id') && 
            Schema::hasColumn('properties', 'original_registration_date') &&
            Schema::hasColumn('properties', 'current_ownership_details')) {
            
            DB::statement("
                UPDATE properties 
                SET 
                    original_landlord_id = landlord_id,
                    original_registration_date = registration_date,
                    current_ownership_details = JSON_OBJECT(
                        'owner_id', landlord_id,
                        'owner_name', (SELECT name FROM users WHERE users.id = properties.landlord_id),
                        'ownership_start_date', registration_date,
                        'is_original_owner', TRUE
                    )
                WHERE original_landlord_id IS NULL
            ");
        }
        
        // Add table comment
        DB::statement("ALTER TABLE properties COMMENT = 'Properties table with enhanced ownership tracking'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndexIfExists('properties_prev_landlord_idx');
            $table->dropIndexIfExists('properties_original_landlord_idx');
            $table->dropIndexIfExists('properties_transfer_status_idx');
            $table->dropIndexIfExists('properties_transferred_at_idx');
            $table->dropIndexIfExists('properties_landlord_status_idx');
            $table->dropIndexIfExists('properties_prev_owner_transfer_idx');
            
            // Drop foreign key constraints if they exist
            $table->dropForeignIfExists(['previous_landlord_id']);
            $table->dropForeignIfExists(['last_transfer_id']);
            $table->dropForeignIfExists(['original_landlord_id']);
            
            // Drop columns if they exist
            $columnsToDrop = [
                'transfer_restriction_until',
                'transfer_restriction_reason',
                'is_transfer_restricted',
                'previous_ownership_details',
                'current_ownership_details',
                'ownership_history_summary',
                'current_owner_tenure_days',
                'ownership_generation',
                'original_registration_date',
                'original_landlord_id',
                'current_transfer_status',
                'last_transfer_id',
                'ownership_transfer_count',
                'ownership_transferred_at',
                'previous_landlord_id'
            ];
            
            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('properties', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};