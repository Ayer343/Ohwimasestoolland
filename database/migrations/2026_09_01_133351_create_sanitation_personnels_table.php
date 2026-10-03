<?php
// database/migrations/2026_09_01_000001_create_sanitation_personnels_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSanitationPersonnelsTable extends Migration
{
    public function up()
    {
        Schema::create('sanitation_personnels', function (Blueprint $table) {
            $table->id();
            
            // Foreign key to users table
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Employee identification
            $table->string('employee_id')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone')->unique();
            $table->string('email')->nullable()->unique();
            $table->text('address')->nullable();
            
            // Role and status
            $table->enum('role', ['supervisor', 'worker', 'driver'])->default('worker');
            $table->enum('status', ['active', 'inactive', 'on_leave', 'suspended'])->default('active');
            
            // Vehicle information
            $table->string('vehicle_number')->nullable();
            $table->string('vehicle_type')->nullable();
            
            // Contact and emergency
            $table->string('emergency_contact')->nullable();
            
            // Employment details
            $table->date('hire_date')->nullable();
            $table->string('shift_preference')->nullable();
            
            // JSON fields for flexible data
            $table->json('certifications')->nullable();
            $table->json('availability_schedule')->nullable();
            $table->json('assigned_zone')->nullable();
            
            // Location tracking
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamp('last_location_update')->nullable();
            
            // Profile photo
            $table->string('profile_photo')->nullable();
            
            // Metadata and audit
            $table->json('metadata')->nullable();
            
            // Soft deletes and timestamps
            $table->softDeletes();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['status', 'role']);
            $table->index('employee_id');
            $table->index('phone');
            $table->index('email');
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('sanitation_personnels');
    }
}