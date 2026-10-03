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
        Schema::create('shared_billing_logs', function (Blueprint $table) {
            $table->id();
            
            // FIXED: Use unsignedBigInteger with separate foreign key for better control
            $table->unsignedBigInteger('shared_billing_id')
                ->comment('Reference to the shared billing');
            
            $table->unsignedBigInteger('user_id')
                ->nullable()
                ->comment('User who performed the action');
            
            // Log details
            $table->string('action', 100)->comment('Action performed: created, updated, paid, cancelled, etc.');
            $table->string('ip_address', 45)->nullable()->comment('IP address of user');
            $table->string('user_agent', 500)->nullable()->comment('User agent/browser info');
            
            // Changes tracking
            $table->json('old_values')->nullable()->comment('Values before change');
            $table->json('new_values')->nullable()->comment('Values after change');
            
            // Context
            $table->string('model_type', 200)->nullable()->comment('Related model if any');
            $table->unsignedBigInteger('model_id')->nullable()->comment('Related model ID if any');
            
            // Additional data
            $table->text('description')->nullable()->comment('Description of the action');
            $table->text('metadata')->nullable()->comment('Additional metadata in JSON format');
            
            // Timestamps
            $table->timestamps();
            
            // Indexes
            $table->index(['shared_billing_id', 'action']);
            $table->index(['user_id', 'created_at']);
            $table->index(['model_type', 'model_id']);
            $table->index('action');
            $table->index('created_at');
        });
        
        // FIXED: Add foreign keys AFTER table creation to ensure referenced table exists
        Schema::table('shared_billing_logs', function (Blueprint $table) {
            // Check if the referenced table exists first
            if (Schema::hasTable('shared_super_admin_billings')) {
                $table->foreign('shared_billing_id')
                    ->references('id')
                    ->on('shared_super_admin_billings')
                    ->onDelete('cascade');
            }
            
            if (Schema::hasTable('users')) {
                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign keys first
        Schema::table('shared_billing_logs', function (Blueprint $table) {
            $table->dropForeign(['shared_billing_id']);
            $table->dropForeign(['user_id']);
        });
        
        Schema::dropIfExists('shared_billing_logs');
    }
};