<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // First, check if table already exists
        if (Schema::hasTable('maintenance_requests')) {
            Schema::dropIfExists('maintenance_requests');
        }

        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            
            // Identification
            $table->string('reference_id')->unique();
            
            // Property and Unit Information - Check if tables exist
            if (Schema::hasTable('properties')) {
                $table->foreignId('property_id')->constrained()->onDelete('cascade');
            } else {
                $table->unsignedBigInteger('property_id');
            }
            
            if (Schema::hasTable('property_units')) {
                $table->foreignId('unit_id')->nullable()->constrained('property_units')->onDelete('cascade');
            } else {
                $table->unsignedBigInteger('unit_id')->nullable();
            }
            
            // User Information - Check if users table exists
            if (Schema::hasTable('users')) {
                $table->foreignId('tenant_id')->nullable()->constrained('users')->onDelete('set null');
                $table->foreignId('landlord_id')->nullable()->constrained('users')->onDelete('set null');
                $table->foreignId('reported_by_user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->onDelete('set null');
            } else {
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('landlord_id')->nullable();
                $table->unsignedBigInteger('reported_by_user_id');
                $table->unsignedBigInteger('assigned_to_user_id')->nullable();
            }
            
            // Category and Classification - Make nullable and handle foreign key later
            $table->unsignedBigInteger('category_id')->nullable();
            
            $table->enum('priority', [
                'emergency',
                'urgent',
                'high',
                'medium',
                'low'
            ])->default('medium');
            
            $table->enum('status', [
                'pending',
                'awaiting_approval',
                'approved',
                'scheduled',
                'in_progress',
                'on_hold',
                'awaiting_parts',
                'completed',
                'cancelled',
                'rejected',
                'reopened',
                'follow_up_required'
            ])->default('pending');
            
            // Description
            $table->string('title');
            $table->text('description');
            $table->text('location_description')->nullable();
            $table->enum('issue_type', [
                'plumbing',
                'electrical',
                'hvac',
                'appliance',
                'structural',
                'painting',
                'cleaning',
                'pest_control',
                'landscaping',
                'security',
                'general'
            ])->default('general');
            
            // Cost and Duration
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->decimal('actual_cost', 10, 2)->nullable();
            $table->integer('estimated_duration_minutes')->nullable();
            $table->integer('actual_duration_minutes')->nullable();
            
            // Scheduling
            $table->timestamp('scheduled_date')->nullable();
            $table->timestamp('completed_date')->nullable();
            
            // Flags
            $table->boolean('emergency')->default(false);
            $table->boolean('permission_granted')->default(false);
            
            // Attachments
            $table->json('images')->nullable();
            $table->json('documents')->nullable();
            
            // Notes and Feedback
            $table->text('notes')->nullable();
            $table->integer('rating')->nullable()->comment('1-5 star rating');
            $table->text('feedback')->nullable();
            $table->text('resolution_details')->nullable();
            
            // Maintenance Details
            $table->enum('maintenance_type', [
                'preventive',
                'corrective',
                'renovation',
                'inspection',
                'upgrade'
            ])->default('corrective');
            
            $table->text('access_instructions')->nullable();
            $table->json('tenant_availability')->nullable();
            
            // Vendor Information - Check if vendors table exists
            if (Schema::hasTable('vendors')) {
                $table->foreignId('vendor_id')->nullable()->constrained('vendors')->onDelete('set null');
            } else {
                $table->unsignedBigInteger('vendor_id')->nullable();
            }
            $table->decimal('vendor_quote', 10, 2)->nullable();
            
            // Approval
            $table->boolean('approved_by_landlord')->default(false);
            $table->timestamp('approved_date')->nullable();
            
            // Payment
            $table->enum('payment_status', [
                'pending',
                'quoted',
                'approved',
                'paid',
                'partial',
                'disputed',
                'refunded'
            ])->default('pending');
            
            // Inspection
            $table->boolean('inspection_required')->default(false);
            $table->timestamp('inspection_date')->nullable();
            $table->text('inspection_result')->nullable();
            
            // Recurring Maintenance
            $table->boolean('recurring')->default(false);
            $table->enum('recurring_interval', [
                'weekly',
                'biweekly',
                'monthly',
                'quarterly',
                'biannually',
                'annually'
            ])->nullable();
            $table->timestamp('next_recurring_date')->nullable();
            
            // Source and Reference
            $table->enum('source', [
                'tenant',
                'landlord',
                'manager',
                'inspection',
                'system'
            ])->default('tenant');
            $table->string('external_reference')->nullable();
            
            // Notification Preferences
            $table->boolean('notify_tenant')->default(true);
            $table->boolean('notify_landlord')->default(true);
            $table->boolean('notify_technician')->default(true);
            
            // Tracking - Check if users table exists
            if (Schema::hasTable('users')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            } else {
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
            }
            
            $table->softDeletes();
            $table->timestamps();
            
            // Indexes
            $table->index(['property_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index(['landlord_id', 'status']);
            $table->index(['assigned_to_user_id', 'status']);
            $table->index(['priority', 'status']);
            $table->index(['scheduled_date', 'status']);
            $table->index('emergency');
            $table->index('created_at');
        });

        // Add foreign key constraints in a separate statement after table creation
        // This ensures the referenced tables exist
        $this->addForeignKeysAfterCreation();
    }

    public function down(): void
    {
        // Drop foreign keys first
        Schema::table('maintenance_requests', function (Blueprint $table) {
            // Drop all foreign key constraints
            $foreignKeys = [
                'maintenance_requests_property_id_foreign',
                'maintenance_requests_unit_id_foreign',
                'maintenance_requests_tenant_id_foreign',
                'maintenance_requests_landlord_id_foreign',
                'maintenance_requests_reported_by_user_id_foreign',
                'maintenance_requests_assigned_to_user_id_foreign',
                'maintenance_requests_category_id_foreign',
                'maintenance_requests_vendor_id_foreign',
                'maintenance_requests_created_by_foreign',
                'maintenance_requests_updated_by_foreign',
            ];
            
            foreach ($foreignKeys as $key) {
                if (Schema::hasColumn('maintenance_requests', substr($key, 19, -8))) {
                    try {
                        $table->dropForeign([substr($key, 19, -8)]);
                    } catch (\Exception $e) {
                        // Ignore if foreign key doesn't exist
                    }
                }
            }
        });
        
        Schema::dropIfExists('maintenance_requests');
    }

    /**
     * Add foreign key constraints after table creation
     */
    private function addForeignKeysAfterCreation(): void
    {
        // Wait a bit to ensure other migrations have run
        sleep(1);
        
        // Add foreign key for category_id if maintenance_categories table exists
        if (Schema::hasTable('maintenance_categories')) {
            Schema::table('maintenance_requests', function (Blueprint $table) {
                $table->foreign('category_id')
                    ->references('id')
                    ->on('maintenance_categories')
                    ->onDelete('set null');
            });
        }
        
        // Add foreign key for property_id if not already added
        if (Schema::hasTable('properties') && !Schema::hasColumn('maintenance_requests', 'property_id_constraint')) {
            try {
                Schema::table('maintenance_requests', function (Blueprint $table) {
                    $table->foreign('property_id')
                        ->references('id')
                        ->on('properties')
                        ->onDelete('cascade');
                });
            } catch (\Exception $e) {
                // Ignore if already exists
            }
        }
        
        // Add foreign key for unit_id if not already added
        if (Schema::hasTable('property_units') && !Schema::hasColumn('maintenance_requests', 'unit_id_constraint')) {
            try {
                Schema::table('maintenance_requests', function (Blueprint $table) {
                    $table->foreign('unit_id')
                        ->references('id')
                        ->on('property_units')
                        ->onDelete('cascade');
                });
            } catch (\Exception $e) {
                // Ignore if already exists
            }
        }
        
        // Add foreign key for vendor_id if vendors table exists
        if (Schema::hasTable('vendors') && Schema::hasColumn('maintenance_requests', 'vendor_id')) {
            try {
                Schema::table('maintenance_requests', function (Blueprint $table) {
                    $table->foreign('vendor_id')
                        ->references('id')
                        ->on('vendors')
                        ->onDelete('set null');
                });
            } catch (\Exception $e) {
                // Ignore if already exists
            }
        }
    }
};