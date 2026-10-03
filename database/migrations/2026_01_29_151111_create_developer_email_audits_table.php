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
        Schema::create('developer_email_audits', function (Blueprint $table) {
            $table->id();
            
            // User who performed the action
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            
            // Job ID for tracking background jobs
            $table->string('job_id')->nullable()->index();
            
            // Action performed
            $table->string('action', 50)->index();
            
            // Status of the action
            $table->enum('status', ['pending', 'processing', 'success', 'failed', 'cancelled'])->default('pending')->index();
            
            // Message/description
            $table->string('message', 500)->nullable();
            
            // Configuration data (before and after)
            $table->json('old_configuration')->nullable();
            $table->json('new_configuration')->nullable();
            
            // Additional metadata
            $table->json('metadata')->nullable();
            
            // IP address and user agent for security
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            
            // Error details (if any)
            $table->text('error_details')->nullable();
            $table->string('error_type', 100)->nullable();
            
            // Performance metrics
            $table->integer('execution_time_ms')->nullable()->comment('Execution time in milliseconds');
            $table->integer('attempts')->default(0);
            
            // Timestamps
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('developer_email_audits');
    }
};