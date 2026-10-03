<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateSecuritySchedulesTable extends Migration
{
    public function up()
    {
        // FIRST: Check if table exists and drop it to start fresh
        if (Schema::hasTable('security_schedules')) {
            // Drop foreign keys first
            Schema::table('security_schedules', function (Blueprint $table) {
                // Drop all foreign keys that might exist
                $foreignKeys = [
                    'fk_sched_post',
                    'fk_sched_shift', 
                    'fk_sched_user',
                    'fk_sched_assigned_by',
                    'fk_sched_rotation_group',
                    'fk_sched_rotated_from',
                    'fk_sched_handover_by',
                    'fk_sched_approved_by',
                    'fk_sched_supervisor'
                ];
                
                foreach ($foreignKeys as $fk) {
                    try {
                        DB::statement("ALTER TABLE security_schedules DROP FOREIGN KEY {$fk}");
                    } catch (\Exception $e) {
                        // Foreign key might not exist, continue
                    }
                }
            });
            
            // Drop the table
            Schema::dropIfExists('security_schedules');
        }

        // FIRST: Create rotation_groups table (needed for foreign key)
        if (!Schema::hasTable('rotation_groups')) {
            Schema::create('rotation_groups', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('type'); // 'day', 'night', 'evening', 'mixed'
                $table->text('description')->nullable();
                $table->longText('configuration')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                
                $table->index('type', 'idx_rot_group_type');
                $table->index('is_active', 'idx_rot_group_active');
            });
            
            Schema::table('rotation_groups', function (Blueprint $table) {
                $table->foreign('created_by', 'fk_rot_group_created_by')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');
            });
        }

        // THEN: Create main security_schedules table
        Schema::create('security_schedules', function (Blueprint $table) {
            $table->id();
            
            // Foreign Keys - Define as unsigned integers with NOT NULL directly
            $table->unsignedBigInteger('security_post_id');
            $table->unsignedBigInteger('security_shift_id');
            $table->unsignedBigInteger('security_user_id');
            $table->unsignedBigInteger('assigned_by')->nullable();
            
            // Rotation foreign keys
            $table->unsignedBigInteger('rotation_group_id')->nullable();
            $table->unsignedBigInteger('rotated_from_user_id')->nullable();
            
            // ========== CORE SCHEDULE INFORMATION ==========
            $table->date('assignment_date');
            $table->enum('status', ['scheduled', 'active', 'completed', 'cancelled', 'absent'])->default('scheduled');
            
            // ========== SMART CHECK-IN/OUT FIELDS ==========
            
            // Check-in fields
            $table->timestamp('checkin_time')->nullable();
            $table->json('checkin_location')->nullable();
            $table->float('checkin_accuracy')->nullable();
            $table->json('checkin_verification')->nullable();
            $table->string('checkin_photo')->nullable();
            $table->string('checkin_selfie')->nullable();
            $table->string('checkin_device_id')->nullable();
            $table->string('checkin_ip')->nullable();
            $table->string('checkin_user_agent')->nullable();
            $table->json('checkin_sensor_data')->nullable();
            
            // Check-out fields
            $table->timestamp('checkout_time')->nullable();
            $table->json('checkout_location')->nullable();
            $table->float('checkout_accuracy')->nullable();
            $table->json('checkout_verification')->nullable();
            $table->string('checkout_photo')->nullable();
            $table->string('checkout_device_id')->nullable();
            $table->string('checkout_ip')->nullable();
            
            // Late/Overtime Tracking
            $table->integer('late_minutes')->default(0);
            $table->boolean('late_flagged')->default(false);
            $table->string('late_reason')->nullable();
            $table->integer('overtime_minutes')->default(0);
            $table->integer('total_minutes')->nullable();
            
            // ========== BREAK MANAGEMENT ==========
            $table->boolean('include_breaks')->default(false);
            $table->integer('current_break_id')->nullable();
            $table->timestamp('break_start_time')->nullable();
            $table->json('break_start_location')->nullable();
            $table->timestamp('break_end_time')->nullable();
            $table->json('break_end_location')->nullable();
            $table->integer('break_duration')->default(0)->nullable();
            $table->enum('break_status', ['active', 'completed', 'skipped'])->nullable();
            $table->json('break_history')->nullable();
            $table->text('break_notes')->nullable();
            
            // ========== HANDOVER MANAGEMENT ==========
            $table->json('handover_info')->nullable();
            $table->boolean('handover_completed')->default(false);
            $table->timestamp('handover_completed_at')->nullable();
            $table->unsignedBigInteger('handover_completed_by')->nullable();
            $table->text('handover_notes')->nullable();
            $table->json('handover_checklist')->nullable();
            
            // ========== ROTATION DATA ==========
            $table->json('rotation_data')->nullable();
            $table->string('rotation_group_type')->nullable();
            $table->integer('rotation_sequence_number')->nullable();
            $table->date('rotation_cycle_start_date')->nullable();
            $table->date('rotation_cycle_end_date')->nullable();
            $table->integer('rotation_swap_count')->default(0);
            $table->integer('rotation_preference_score')->default(5);
            $table->boolean('is_rotated')->default(false);
            $table->timestamp('rotated_at')->nullable();
            $table->json('rotation_history')->nullable();
            
            // ========== APPROVAL TRACKING ==========
            $table->boolean('is_approved')->default(false);
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->json('approval_metadata')->nullable();
            
            // ========== ADDITIONAL INFORMATION ==========
            $table->text('notes')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->text('special_instructions')->nullable();
            $table->text('checkin_notes')->nullable();
            $table->text('checkout_notes')->nullable();
            
            // ========== PERFORMANCE METRICS ==========
            $table->decimal('attendance_score', 5, 2)->nullable();
            $table->decimal('punctuality_score', 5, 2)->nullable();
            $table->decimal('performance_score', 5, 2)->nullable();
            $table->json('performance_metrics')->nullable();
            
            // ========== AUDIT & METADATA ==========
            $table->json('audit_log')->nullable();
            $table->json('metadata')->nullable();
            
            // ========== SMART FEATURES ==========
            $table->boolean('offline_mode')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->json('verification_attempts')->nullable();
            $table->json('anomaly_flags')->nullable();
            $table->boolean('supervisor_override')->default(false);
            $table->unsignedBigInteger('supervisor_id')->nullable();
            $table->text('override_reason')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // ========== UNIQUE CONSTRAINTS ==========
            $table->unique(['security_post_id', 'security_shift_id', 'security_user_id', 'assignment_date'], 'unq_schedule_post_shift_user_date');
            
            // ========== INDEXES FOR PERFORMANCE ==========
            
            // Basic indexes
            $table->index(['assignment_date', 'status'], 'idx_sched_date_status');
            $table->index(['security_user_id', 'assignment_date'], 'idx_sched_user_date');
            $table->index(['security_post_id', 'assignment_date'], 'idx_sched_post_date');
            $table->index(['security_shift_id', 'assignment_date'], 'idx_sched_shift_date');
            $table->index(['status', 'assignment_date'], 'idx_sched_status_date');
            $table->index(['assigned_by', 'created_at'], 'idx_sched_assigned_created');
            
            // Check-in/out indexes
            $table->index(['checkin_time', 'checkout_time'], 'idx_sched_check_times');
            $table->index('checkin_device_id', 'idx_sched_checkin_device');
            $table->index('checkin_ip', 'idx_sched_checkin_ip');
            $table->index('checkout_device_id', 'idx_sched_checkout_device');
            
            // Late/overtime indexes
            $table->index(['late_minutes', 'overtime_minutes'], 'idx_sched_late_overtime');
            $table->index('late_flagged', 'idx_sched_late_flagged');
            
            // Break indexes
            $table->index(['include_breaks', 'assignment_date'], 'idx_sched_breaks_date');
            $table->index('break_status', 'idx_sched_break_status');
            
            // Rotation indexes
            $table->index('rotation_group_id', 'idx_sched_rotation_group');
            $table->index('rotation_group_type', 'idx_sched_rotation_type');
            $table->index(['rotation_cycle_start_date', 'rotation_cycle_end_date'], 'idx_sched_rotation_cycle');
            $table->index('rotation_preference_score', 'idx_sched_preference_score');
            $table->index('is_rotated', 'idx_sched_is_rotated');
            $table->index('rotated_from_user_id', 'idx_sched_rotated_from');
            $table->index('rotated_at', 'idx_sched_rotated_at');
            
            // Approval indexes
            $table->index(['is_approved', 'approved_at'], 'idx_sched_approval');
            
            // Smart feature indexes
            $table->index('offline_mode', 'idx_sched_offline');
            $table->index('synced_at', 'idx_sched_synced');
            $table->index('supervisor_override', 'idx_sched_override');
            
            // ========== ADD FOREIGN KEY CONSTRAINTS HERE (inside the create) ==========
            $table->foreign('security_post_id', 'fk_sched_post')
                  ->references('id')
                  ->on('security_posts')
                  ->onDelete('cascade');
                  
            $table->foreign('security_shift_id', 'fk_sched_shift')
                  ->references('id')
                  ->on('security_shifts')
                  ->onDelete('cascade');
                  
            $table->foreign('security_user_id', 'fk_sched_user')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
                  
            $table->foreign('assigned_by', 'fk_sched_assigned_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
                  
            $table->foreign('rotation_group_id', 'fk_sched_rotation_group')
                  ->references('id')
                  ->on('rotation_groups')
                  ->onDelete('set null');
                  
            $table->foreign('rotated_from_user_id', 'fk_sched_rotated_from')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
                  
            $table->foreign('handover_completed_by', 'fk_sched_handover_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
                  
            $table->foreign('approved_by', 'fk_sched_approved_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
                  
            $table->foreign('supervisor_id', 'fk_sched_supervisor')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });

        // ========== CREATE POST QR CODES TABLE ==========
        Schema::create('post_qr_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->string('code')->unique();
            $table->timestamp('expires_at');
            $table->boolean('used')->default(false);
            $table->timestamp('used_at')->nullable();
            $table->unsignedBigInteger('used_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['post_id', 'code'], 'idx_qr_post_code');
            $table->index(['post_id', 'expires_at'], 'idx_qr_post_expires');
            $table->index('used', 'idx_qr_used');
            
            $table->foreign('post_id', 'fk_qr_post')
                  ->references('id')
                  ->on('security_posts')
                  ->onDelete('cascade');
                  
            $table->foreign('used_by', 'fk_qr_used_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
        
        // ========== CREATE POST NFC TAGS TABLE ==========
        Schema::create('post_nfc_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->string('tag_id')->unique();
            $table->string('name')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedBigInteger('last_used_by')->nullable();
            $table->timestamp('registered_at')->useCurrent();
            $table->timestamps();
            
            $table->index(['post_id', 'is_active'], 'idx_nfc_post_active');
            $table->index('last_used_at', 'idx_nfc_last_used');
            
            $table->foreign('post_id', 'fk_nfc_post')
                  ->references('id')
                  ->on('security_posts')
                  ->onDelete('cascade');
                  
            $table->foreign('last_used_by', 'fk_nfc_used_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
        
        // ========== CREATE USER DEVICES TABLE ==========
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('device_id');
            $table->string('device_name')->nullable();
            $table->string('device_model')->nullable();
            $table->string('os_version')->nullable();
            $table->string('app_version')->nullable();
            $table->json('capabilities')->nullable();
            $table->boolean('is_trusted')->default(false);
            $table->boolean('requires_verification')->default(true);
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->json('verification_history')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'device_id'], 'unq_user_device');
            $table->index('device_id', 'idx_device_id');
            $table->index('is_trusted', 'idx_device_trusted');
            $table->index('last_seen_at', 'idx_device_last_seen');
            
            $table->foreign('user_id', 'fk_device_user')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
        
        // ========== CREATE VERIFICATION LOGS TABLE ==========
        Schema::create('verification_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('schedule_id')->nullable();
            $table->string('action');
            $table->string('method');
            $table->string('status');
            $table->json('results')->nullable();
            $table->json('location')->nullable();
            $table->float('accuracy')->nullable();
            $table->string('device_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'created_at'], 'idx_verify_user_time');
            $table->index(['schedule_id', 'action'], 'idx_verify_schedule_action');
            $table->index(['method', 'status'], 'idx_verify_method_status');
            $table->index('device_id', 'idx_verify_device');
            $table->index('ip_address', 'idx_verify_ip');
            
            $table->foreign('user_id', 'fk_verify_user')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
                  
            $table->foreign('schedule_id', 'fk_verify_schedule')
                  ->references('id')
                  ->on('security_schedules')
                  ->onDelete('cascade');
        });
        
        // ========== CREATE SECURITY SCHEDULE AUDITS TABLE ==========
        Schema::create('security_schedule_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('security_schedule_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('performed_at')->useCurrent();
            
            $table->index(['security_schedule_id', 'action'], 'idx_audit_sched_action');
            $table->index(['user_id', 'performed_at'], 'idx_audit_user_time');
            $table->index(['action', 'performed_at'], 'idx_audit_action_time');
            
            $table->foreign('security_schedule_id', 'fk_audit_schedule')
                  ->references('id')
                  ->on('security_schedules')
                  ->onDelete('cascade');
                  
            $table->foreign('user_id', 'fk_audit_user')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
        
        // ========== CREATE SECURITY SCHEDULE TEMPLATES TABLE ==========
        Schema::create('security_schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->json('template_data')->nullable();
            $table->json('recurrence_pattern')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->string('rotation_type')->nullable();
            $table->json('rotation_config')->nullable();
            $table->boolean('enable_shift_group_rotation')->default(false);
            $table->string('default_rotation_frequency')->nullable();
            $table->string('default_rotation_pattern')->nullable();
            
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('is_active', 'idx_template_active');
            $table->index(['start_date', 'end_date'], 'idx_template_dates');
            $table->index('rotation_type', 'idx_template_rotation_type');
            $table->index('enable_shift_group_rotation', 'idx_template_group_rotation');
            
            $table->foreign('created_by', 'fk_template_created_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
        
        // ========== CREATE SECURITY SCHEDULE EXCEPTIONS TABLE ==========
        Schema::create('security_schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('security_schedule_id');
            $table->unsignedBigInteger('replacing_user_id')->nullable();
            $table->enum('type', ['holiday', 'sick_leave', 'emergency', 'training', 'other', 'rotation_conflict']);
            $table->date('exception_date');
            $table->text('reason');
            
            $table->unsignedBigInteger('original_user_id')->nullable();
            $table->string('rotation_group_conflict')->nullable();
            $table->json('rotation_metadata')->nullable();
            
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            
            $table->index(['exception_date', 'type'], 'idx_except_date_type');
            $table->index(['security_schedule_id', 'exception_date'], 'idx_except_sched_date');
            $table->index('original_user_id', 'idx_except_original_user');
            $table->index('rotation_group_conflict', 'idx_except_rot_group');
            
            $table->foreign('security_schedule_id', 'fk_except_schedule')
                  ->references('id')
                  ->on('security_schedules')
                  ->onDelete('cascade');
                  
            $table->foreign('replacing_user_id', 'fk_except_replacing_user')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
                  
            $table->foreign('approved_by', 'fk_except_approved_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
                  
            $table->foreign('original_user_id', 'fk_except_original_user')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
        
        // ========== CREATE SECURITY SCHEDULE ROTATION HISTORY TABLE ==========
        Schema::create('security_schedule_rotation_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('security_schedule_id');
            $table->unsignedBigInteger('from_user_id')->nullable();
            $table->unsignedBigInteger('to_user_id')->nullable();
            $table->string('rotation_type');
            $table->string('rotation_pattern');
            $table->string('frequency');
            $table->json('rotation_data')->nullable();
            $table->json('verification_data')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('performed_at')->useCurrent();
            
            $table->index(['security_schedule_id', 'performed_at'], 'idx_rot_hist_schedule');
            $table->index(['from_user_id', 'to_user_id'], 'idx_rot_hist_users');
            $table->index('rotation_type', 'idx_rot_hist_type');
            
            $table->foreign('security_schedule_id', 'fk_rot_hist_schedule')
                  ->references('id')
                  ->on('security_schedules')
                  ->onDelete('cascade');
                  
            $table->foreign('from_user_id', 'fk_rot_hist_from')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
                  
            $table->foreign('to_user_id', 'fk_rot_hist_to')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
                  
            $table->foreign('performed_by', 'fk_rot_hist_performed')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    public function down()
    {
        // Drop tables in reverse order
        Schema::dropIfExists('security_schedule_rotation_history');
        Schema::dropIfExists('security_schedule_exceptions');
        Schema::dropIfExists('security_schedule_templates');
        Schema::dropIfExists('security_schedule_audits');
        Schema::dropIfExists('verification_logs');
        Schema::dropIfExists('user_devices');
        Schema::dropIfExists('post_nfc_tags');
        Schema::dropIfExists('post_qr_codes');
        Schema::dropIfExists('security_schedules');
        Schema::dropIfExists('rotation_groups');
    }
}