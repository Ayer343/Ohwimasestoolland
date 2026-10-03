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
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            
            // User relation (who performed the action)
            $table->foreignId('user_id')
                  ->constrained()
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
            
            // Activity details
            $table->string('action');              // e.g., 'login', 'create', 'update', 'delete'
            $table->string('subject_type')->nullable();  // Model type (e.g., 'App\Models\Product')
            $table->unsignedBigInteger('subject_id')->nullable(); // Model ID
            $table->string('subject_name')->nullable(); // Human-readable name of the subject
            
            // Request details
            $table->string('method')->nullable();   // HTTP method (GET, POST, PUT, DELETE)
            $table->string('url')->nullable();      // Full URL accessed
            $table->text('route_name')->nullable(); // Route name if available
            
            // Data tracking
            $table->longText('old_data')->nullable();  // Before changes (JSON)
            $table->longText('new_data')->nullable();  // After changes (JSON)
            $table->text('description')->nullable();   // Human-readable description
            
            // Technical details
            $table->string('ip_address', 45)->nullable();  // IPv6 compatible
            $table->text('user_agent')->nullable();        // Browser/device info
            $table->string('session_id')->nullable();      // Session identifier
            
            // Performance & additional info
            $table->integer('duration_ms')->nullable();    // How long the operation took
            $table->json('metadata')->nullable();          // Additional flexible data
            
            // Status tracking
            $table->boolean('is_successful')->default(true);
            $table->text('error_message')->nullable();     // If failed
            
            // Indexes for performance
            $table->index(['user_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('action');
            $table->index('created_at');
            $table->index('is_successful');
            
            $table->timestamps();
            
            // Composite index for common queries
            $table->index(['user_id', 'action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};