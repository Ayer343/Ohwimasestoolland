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
        Schema::create('security_supervisor_assignments', function (Blueprint $table) {
            $table->id();
            
            // Core relationships
            $table->foreignId('user_id')
                  ->constrained()
                  ->onDelete('cascade');
            
            $table->foreignId('security_post_id')
                  ->nullable()
                  ->constrained()
                  ->onDelete('cascade');
            
            $table->foreignId('assigned_by')
                  ->constrained('users');
            
            // Assignment duration
            $table->date('start_date');
            $table->date('end_date')->nullable();
            
            // Supervisor type
            $table->enum('supervisor_type', [
                'post_supervisor',
                'shift_supervisor',
                'area_supervisor',
                'relief_supervisor',
                'training_supervisor',
            ])->default('post_supervisor');
            
            // Which shifts they supervise
            $table->json('shift_ids')->nullable();
            $table->json('applicable_days')->nullable();
            $table->json('permissions')->nullable();
            
            // Permission flags
            $table->boolean('can_override_checkins')->default(true);
            $table->boolean('can_approve_swaps')->default(true);
            $table->boolean('can_approve_overtime')->default(true);
            $table->boolean('can_review_incidents')->default(true);
            $table->boolean('can_verify_checkins')->default(true);
            $table->boolean('can_request_backup')->default(true);
            $table->boolean('can_approve_breaks')->default(true);
            $table->boolean('can_escalate_issues')->default(true);
            $table->boolean('can_view_all_schedules')->default(true);
            $table->boolean('can_edit_schedules')->default(false);
            
            // Additional config
            $table->json('supervision_config')->nullable();
            
            // Status flags
            $table->boolean('is_active')->default(true);
            $table->boolean('is_primary_supervisor')->default(false);
            
            // Handover config
            $table->json('handover_config')->nullable();
            
            // Notes and metadata
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // ✅ UNIQUE CONSTRAINT - with custom shorter name to avoid MySQL length limit
            $table->unique(['security_post_id', 'is_active'], 'ssa_post_active_unique');
            
            // Indexes - using shorter custom names for consistency and future safety
            $table->index('user_id', 'ssa_user_idx');
            $table->index('security_post_id', 'ssa_post_idx');
            $table->index('assigned_by', 'ssa_assigned_idx');
            $table->index('start_date', 'ssa_start_idx');
            $table->index('end_date', 'ssa_end_idx');
            $table->index('is_active', 'ssa_active_idx');
            $table->index('supervisor_type', 'ssa_type_idx');
            
            // Composite indexes with shorter names
            $table->index(['user_id', 'is_active'], 'ssa_user_active_idx');
            $table->index(['security_post_id', 'is_active'], 'ssa_post_active_idx');
            $table->index(['start_date', 'end_date'], 'ssa_dates_idx');
            $table->index(['user_id', 'security_post_id', 'is_active'], 'ssa_user_post_active_idx');
            $table->index(['security_post_id', 'is_active', 'supervisor_type'], 'ssa_post_active_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_supervisor_assignments');
    }
};