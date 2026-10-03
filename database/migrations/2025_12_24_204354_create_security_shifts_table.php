<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSecurityShiftsTable extends Migration
{
    public function up()
    {
        // Create security_shift_templates table FIRST
        Schema::create('security_shift_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->json('shift_configuration')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('usage_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users', 'id', 'fk_tmpl_created_by')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users', 'id', 'fk_tmpl_updated_by')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });

        // Now create the main security_shifts table
        Schema::create('security_shifts', function (Blueprint $table) {
            $table->id();
            
            // Basic Identification
            $table->string('name');
            $table->string('code')->unique();
            $table->string('slug')->unique()->nullable();
            
            // Time Configuration
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('duration_hours', 5, 2);
            $table->boolean('is_overnight')->default(false);
            
            // Shift Category & Type
            $table->enum('category', ['day', 'night', 'evening', 'special', 'holiday'])->default('day');
            $table->enum('day_type', ['all_days', 'weekday', 'weekend', 'custom'])->default('all_days');
            $table->json('applicable_days')->nullable(); // Should store day numbers like [1,2,3] for Monday,Tue,Wed
            
            // Day of Week Configuration - ADD THIS NEW COLUMN
            $table->tinyInteger('day_of_week')->nullable()->comment('1=Monday, 7=Sunday');
            
            // Personnel Requirements
            $table->integer('required_personnel')->default(1);
            $table->integer('minimum_personnel')->default(1);
            $table->integer('maximum_personnel')->default(10);
            
            // Rotation System
            $table->enum('rotation_type', ['fixed', 'rotating'])->default('fixed');
            $table->json('rotation_config')->nullable();
            
            // Break Configuration
            $table->json('break_schedule')->nullable();
            
            // Handover Configuration
            $table->json('handover_config')->nullable();
            
            // Overtime & Late Rules
            $table->json('overtime_config')->nullable();
            $table->json('late_policy')->nullable();
            
            // Compliance & Legal
            $table->json('compliance_config')->nullable();
            
            // Shift Specific Requirements
            $table->json('requirements')->nullable();
            
            // Special Conditions
            $table->boolean('is_active_on_holidays')->default(false);
            $table->boolean('requires_supervisor')->default(false);
            $table->boolean('is_24x7_shift')->default(false);
            $table->boolean('allow_partial_assignment')->default(false);
            $table->boolean('is_emergency_shift')->default(false);
            
            // Shift Relationships - Define column WITHOUT foreign key constraint yet
            $table->unsignedBigInteger('parent_shift_id')->nullable();
            $table->unsignedBigInteger('template_id')->nullable();
            
            // Cost & Budgeting
            $table->decimal('base_hourly_rate', 10, 2)->nullable();
            $table->decimal('night_shift_premium', 10, 2)->nullable();
            $table->decimal('holiday_multiplier', 5, 2)->default(2.0);
            $table->json('additional_allowances')->nullable();
            
            // Description & Metadata
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->text('reporting_location')->nullable();
            $table->text('emergency_procedures')->nullable();
            
            // Status & Tracking
            $table->boolean('is_active')->default(true);
            $table->boolean('is_template')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->integer('usage_count')->default(0);
            
            // Audit Information
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->json('version_history')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for Performance with CUSTOM SHORT NAMES
            $table->index(['is_active', 'category'], 'idx_shifts_active_category');
            $table->index(['start_time', 'end_time'], 'idx_shifts_times');
            $table->index(['rotation_type', 'is_active'], 'idx_shifts_rotation_active');
            $table->index(['day_type', 'is_active'], 'idx_shifts_daytype_active');
            $table->index(['day_of_week', 'is_active'], 'idx_shifts_dayofweek_active'); // NEW INDEX
            $table->index(['is_overnight', 'duration_hours'], 'idx_shifts_overnight_duration');
            $table->index(['last_used_at', 'usage_count'], 'idx_shifts_usage_stats');
            $table->index(['is_active', 'last_used_at'], 'idx_shifts_active_last_used');
            $table->index(['category', 'required_personnel'], 'idx_shifts_category_personnel');
            $table->index(['day_of_week', 'start_time'], 'idx_shifts_schedule'); // NEW INDEX
        });
        
        // Add ALL foreign key constraints AFTER table creation with custom short names
        Schema::table('security_shifts', function (Blueprint $table) {
            // Self-referencing foreign key
            $table->foreign('parent_shift_id', 'fk_shifts_parent')
                  ->references('id')
                  ->on('security_shifts')
                  ->onDelete('set null');
                  
            // Template foreign key
            $table->foreign('template_id', 'fk_shifts_template')
                  ->references('id')
                  ->on('security_shift_templates')
                  ->onDelete('set null');
                  
            // User foreign keys
            $table->foreign('created_by', 'fk_shifts_created_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
                  
            $table->foreign('updated_by', 'fk_shifts_updated_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });

        // Create supporting tables
        Schema::create('security_shift_break_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->json('break_pattern')->nullable();
            $table->integer('total_minutes');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            
            $table->index(['is_active', 'is_default'], 'idx_break_status');
        });

        Schema::create('security_shift_requirements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('security_shift_id');
            $table->string('requirement_type');
            $table->string('requirement_key');
            $table->string('requirement_name');
            $table->text('description')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->integer('priority')->default(0);
            $table->timestamps();
            
            // Use SHORT custom index names
            $table->index(['security_shift_id', 'requirement_type'], 'idx_req_shift_type');
            $table->unique(['security_shift_id', 'requirement_key'], 'unq_req_shift_key');
        });
        
        // Add foreign key separately with custom name
        Schema::table('security_shift_requirements', function (Blueprint $table) {
            $table->foreign('security_shift_id', 'fk_req_shift')
                  ->references('id')
                  ->on('security_shifts')
                  ->onDelete('cascade');
        });

        Schema::create('security_shift_template_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('security_shift_id');
            $table->unsignedBigInteger('security_shift_template_id');
            $table->timestamps();
            
            // Short unique constraint name
            $table->unique(['security_shift_id', 'security_shift_template_id'], 'unq_shift_template');
        });
        
        // Add foreign keys separately with custom names
        Schema::table('security_shift_template_assignments', function (Blueprint $table) {
            $table->foreign('security_shift_id', 'fk_assign_shift')
                  ->references('id')
                  ->on('security_shifts')
                  ->onDelete('cascade');
                  
            $table->foreign('security_shift_template_id', 'fk_assign_template')
                  ->references('id')
                  ->on('security_shift_templates')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        // Drop in reverse order (child tables first)
        
        // Drop foreign keys first
        Schema::table('security_shift_template_assignments', function (Blueprint $table) {
            $table->dropForeign('fk_assign_shift');
            $table->dropForeign('fk_assign_template');
        });
        
        Schema::table('security_shift_requirements', function (Blueprint $table) {
            $table->dropForeign('fk_req_shift');
        });
        
        Schema::table('security_shifts', function (Blueprint $table) {
            $table->dropForeign('fk_shifts_parent');
            $table->dropForeign('fk_shifts_template');
            $table->dropForeign('fk_shifts_created_by');
            $table->dropForeign('fk_shifts_updated_by');
        });
        
        // Now drop the tables
        Schema::dropIfExists('security_shift_template_assignments');
        Schema::dropIfExists('security_shift_requirements');
        Schema::dropIfExists('security_shift_break_templates');
        Schema::dropIfExists('security_shifts');
        Schema::dropIfExists('security_shift_templates');
    }
}