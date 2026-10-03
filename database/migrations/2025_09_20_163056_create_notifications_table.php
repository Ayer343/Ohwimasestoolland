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
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            
            // ✅ ADD THESE FIELDS FOR ENHANCED FUNCTIONALITY
            $table->timestamp('seen_at')->nullable(); // When user views notification list
            $table->string('action_url')->nullable(); // URL to redirect when clicked
            $table->string('icon')->nullable(); // Icon class for UI
            $table->string('category')->nullable(); // Notification category
            $table->integer('priority')->default(0); // 0=normal, 1=important, 2=urgent
            $table->json('metadata')->nullable(); // Additional data
            $table->timestamp('scheduled_at')->nullable(); // For scheduled notifications
            $table->timestamp('expires_at')->nullable(); // Auto-expiry
            $table->timestamps();
            
            // Add indexes for better performance
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
            $table->index(['notifiable_type', 'notifiable_id', 'created_at']);
            $table->index(['category', 'priority', 'created_at']);
            $table->index(['scheduled_at', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};