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
        Schema::create('security_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_number')->unique()->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            
            // Relationships
            $table->foreignId('security_post_id')->nullable()->constrained('security_posts')->onDelete('set null');
            $table->foreignId('security_schedule_id')->nullable()->constrained('security_schedules')->onDelete('set null');
            $table->foreignId('reported_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('closed_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Report details
            $table->string('report_type')->default('incident'); // incident, daily_log, inspection, equipment_check, maintenance
            $table->string('category')->nullable(); // security_breach, unauthorized_entry, equipment_failure, safety_hazard, theft, vandalism, other
            $table->string('priority')->default('medium'); // low, medium, high, critical
            $table->string('status')->default('pending'); // pending, under_investigation, resolved, closed, cancelled
            
            // Incident details
            $table->dateTime('incident_date')->nullable();
            $table->dateTime('report_date')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->string('location')->nullable();
            $table->string('digital_address')->nullable();
            
            // Personnel involved
            $table->json('personnel_involved')->nullable(); // Array of user IDs
            $table->json('witnesses')->nullable(); // Array of names/contacts
            
            // Impact & response
            $table->text('impact_assessment')->nullable();
            $table->text('immediate_actions')->nullable();
            $table->text('recommendations')->nullable();
            $table->text('resolution_details')->nullable();
            
            // Verification
            $table->dateTime('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            
            // Closure
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->text('closure_notes')->nullable();
            
            // Evidence
            $table->json('evidence_photos')->nullable(); // Array of photo paths
            $table->json('evidence_documents')->nullable(); // Array of document paths
            
            // Metadata
            $table->boolean('requires_followup')->default(false);
            $table->dateTime('followup_date')->nullable();
            $table->boolean('is_confidential')->default(false);
            $table->boolean('notify_stakeholders')->default(false);
            $table->json('notified_stakeholders')->nullable();
            
            // Statistics (for quick reporting)
            $table->integer('severity_level')->nullable(); // 1-10 scale
            $table->decimal('estimated_damage', 15, 2)->nullable();
            $table->integer('personnel_injured')->default(0);
            $table->integer('personnel_deceased')->default(0);
            $table->integer('civilians_affected')->default(0);
            
            // Audit trail
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['report_type', 'status']);
            $table->index(['priority', 'status']);
            $table->index(['security_post_id', 'incident_date']);
            $table->index(['reported_by', 'created_at']);
            $table->index('report_number');
            $table->index('incident_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_reports');
    }
};