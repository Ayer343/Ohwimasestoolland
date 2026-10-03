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
        Schema::create('agreement_signatures', function (Blueprint $table) {
            $table->id();
            
            // Foreign key to the agreement
            $table->foreignId('agreement_id')
                  ->constrained('admin_billing_records')
                  ->onDelete('cascade');
            
            // Foreign key to the user who signed
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            
            // Signature type: 'developer' or 'super_admin'
            $table->string('signature_type', 50);
            
            // The actual signature data (base64 encoded image or text)
            $table->text('signature_data')->nullable();
            
            // Signature format: 'typed', 'draw', or 'upload'
            $table->string('signature_format', 20)->default('typed');
            
            // The name as signed
            $table->string('signature_name', 255);
            
            // Date when signed
            $table->dateTime('signature_date');
            
            // Path to stored signature image (if any)
            $table->string('signature_path', 500)->nullable();
            
            // Audit information
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            
            // Status: 'pending', 'verified', 'revoked'
            $table->string('status', 20)->default('pending');
            
            // Timestamps
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['agreement_id', 'signature_type']);
            $table->index(['user_id', 'signature_date']);
            $table->index('status');
            $table->index('signature_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agreement_signatures');
    }
};