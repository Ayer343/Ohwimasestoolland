<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop existing table
        Schema::dropIfExists('role_user');
        
        // Create new table with all needed columns
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->string('assigned_via')->nullable()->default('manual');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            
            $table->boolean('is_active')->default(true);
            $table->boolean('is_primary')->default(false);
            
            $table->string('context_type')->nullable();
            $table->unsignedBigInteger('context_id')->nullable();
            
            $table->text('assignment_reason')->nullable();
            $table->text('revocation_reason')->nullable();
            
            $table->json('metadata')->nullable();
            $table->json('permissions_override')->nullable();
            $table->text('notes')->nullable();
            
            $table->softDeletes();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['role_id', 'user_id']);
            $table->index(['user_id', 'is_active']);
            $table->index(['context_type', 'context_id']);
            $table->index('expires_at');
            $table->index('assigned_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};