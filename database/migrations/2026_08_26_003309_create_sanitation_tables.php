<?php
// database/migrations/2024_01_02_create_sanitation_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Sanitation Personnel Table
        Schema::create('sanitation_personnel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('employee_id')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('profile_photo')->nullable();
            $table->enum('status', ['active', 'inactive', 'on_leave', 'suspended'])->default('active');
            $table->enum('role', ['supervisor', 'worker', 'driver'])->default('worker');
            $table->string('vehicle_number')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamp('last_location_update')->nullable();
            $table->json('availability_schedule')->nullable();
            $table->json('assigned_zone')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->timestamp('hire_date')->nullable();
            $table->json('certifications')->nullable();
            $table->string('shift_preference')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index('employee_id');
            $table->index(['latitude', 'longitude']);
            $table->index('role');
            $table->index('status');
        });

        // 2. Sanitation Workers Table
        Schema::create('sanitation_workers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervisor_id')->constrained('sanitation_personnel')->onDelete('cascade');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('profile_photo')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('address')->nullable();
            $table->json('availability')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->json('certifications')->nullable();
            $table->json('skills')->nullable();
            $table->timestamp('hire_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supervisor_id', 'status']);
            $table->index('status');
        });

        // 3. Waste Collection Requests Table
        Schema::create('waste_collection_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->onDelete('cascade');
            $table->foreignId('unit_id')->nullable()->constrained('property_units')->onDelete('set null');
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('assigned_to')->nullable()->constrained('sanitation_personnel')->onDelete('set null');
            $table->foreignId('worker_id')->nullable()->constrained('sanitation_workers')->onDelete('set null');

            // Request details
            $table->enum('waste_type', ['general', 'recyclable', 'organic', 'hazardous', 'bulk']);
            $table->enum('priority', ['low', 'medium', 'high', 'emergency'])->default('medium');
            $table->text('description')->nullable();
            $table->text('special_instructions')->nullable();

            // Location
            $table->string('digital_address')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // Status tracking
            $table->enum('status', [
                'pending', 'assigned', 'en_route', 'arrived',
                'in_progress', 'completed', 'cancelled', 'missed'
            ])->default('pending');

            // Timestamps for each status
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('en_route_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Google Maps tracking
            $table->json('route_coordinates')->nullable();
            $table->json('checkpoints')->nullable();

            // Completion details
            $table->text('completion_notes')->nullable();
            $table->text('collection_notes')->nullable();
            $table->string('before_photo')->nullable();
            $table->string('after_photo')->nullable();
            $table->decimal('waste_weight_kg', 8, 2)->nullable();
            $table->integer('completion_time')->nullable(); // in minutes

            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['property_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['latitude', 'longitude']);
            $table->index('priority');
            $table->index('waste_type');
            $table->index('status');
            $table->index(['status', 'priority']);
            $table->index(['requested_by', 'status']);
            $table->index(['created_at', 'status']);
        });

        // 4. Sanitation Routes Table (for predefined routes)
        Schema::create('sanitation_routes', function (Blueprint $table) {
            $table->id();
            $table->string('route_name');
            $table->json('waypoints')->nullable();
            $table->json('polyline')->nullable();
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->integer('estimated_time_minutes')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index('status');
        });

        // 5. Sanitation Schedule Table
        Schema::create('sanitation_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('sanitation_personnel')->onDelete('cascade');
            $table->date('scheduled_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('shift_type', ['morning', 'afternoon', 'night'])->default('morning');
            $table->json('assigned_areas')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
            $table->timestamps();

            $table->index(['personnel_id', 'scheduled_date']);
            $table->index(['scheduled_date', 'status']);
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sanitation_schedules');
        Schema::dropIfExists('sanitation_routes');
        Schema::dropIfExists('waste_collection_requests');
        Schema::dropIfExists('sanitation_workers');
        Schema::dropIfExists('sanitation_personnel');
    }
};