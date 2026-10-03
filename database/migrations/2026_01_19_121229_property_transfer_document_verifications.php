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
        Schema::create('property_transfer_document_verifications', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignId('transfer_id')
                  ->constrained('property_ownership_transfers')
                  ->onDelete('cascade');
            
            $table->foreignId('verified_by_id')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null')
                  ->comment('Admin who verified the document');
            
            // Verification details
            $table->enum('verification_status', [
                'pending',      // Not yet verified
                'verified',     // Document verified as valid
                'rejected',     // Document rejected
                'needs_review', // Needs further review
            ])->default('pending');
            
            $table->text('verification_notes')
                  ->nullable()
                  ->comment('Notes from verification process');
            
            $table->text('rejection_reason')
                  ->nullable()
                  ->comment('Reason for document rejection');
            
            $table->date('document_expiry_date')
                  ->nullable()
                  ->comment('Document expiry date if applicable');
            
            $table->string('document_issuing_authority')
                  ->nullable()
                  ->comment('Authority that issued the document');
            
            $table->date('document_issue_date')
                  ->nullable()
                  ->comment('When document was issued');
            
            // Verification timestamps
            $table->timestamp('verification_started_at')
                  ->nullable()
                  ->comment('When verification process started');
            
            $table->timestamp('verification_completed_at')
                  ->nullable()
                  ->comment('When verification was completed');
            
            // Document scan/upload details
            $table->string('document_front_url')
                  ->nullable()
                  ->comment('Front side of document');
            
            $table->string('document_back_url')
                  ->nullable()
                  ->comment('Back side of document');
            
            $table->string('additional_document_url')
                  ->nullable()
                  ->comment('Additional supporting documents');
            
            // Metadata
            $table->json('metadata')
                  ->nullable()
                  ->comment('Additional metadata');
            
            $table->timestamps();
            $table->softDeletes();
            
            // 🔥 FIXED: Custom, shorter index names (under 64 chars)
            // Composite index for transfer_id + verification_status
            $table->index(
                ['transfer_id', 'verification_status'], 
                'ptdv_transfer_status_idx'  // 25 chars
            );
            
            // Single column indexes
            $table->index('transfer_id', 'ptdv_transfer_idx');        // 17 chars
            $table->index('verified_by_id', 'ptdv_verified_by_idx');  // 22 chars
            $table->index('verification_status', 'ptdv_status_idx');  // 17 chars
            
            // Timestamp indexes
            $table->index('verification_completed_at', 'ptdv_completed_at_idx');  // 24 chars
            $table->index('created_at', 'ptdv_created_at_idx');                   // 21 chars
            
            // For soft deletes
            $table->index('deleted_at', 'ptdv_deleted_at_idx');                   // 20 chars
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_transfer_document_verifications');
    }
};