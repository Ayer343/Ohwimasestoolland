<?php
// database/migrations/2026_09_01_000001_create_sanitation_personnels_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanitation_personnels', function (Blueprint $table) {
            // -----------------------------------------------------------------
            // Primary key — BIGINT UNSIGNED AUTO_INCREMENT
            // This is the column that sanitation_schedules.personnel_id and
            // sanitation_workers.supervisor_id MUST match exactly (type + signedness)
            // -----------------------------------------------------------------
            $table->id();

            // -----------------------------------------------------------------
            // Relations
            // foreignId()->constrained() already creates an index on user_id.
            // Do NOT add $table->index('user_id') on top of it.
            // -----------------------------------------------------------------
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // -----------------------------------------------------------------
            // Employee identification
            // ->unique() already creates a UNIQUE index for each of these,
            // so no extra ->index() calls are needed (that was the 1061 bug).
            // -----------------------------------------------------------------
            $table->string('employee_id')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone')->unique();
            $table->string('email')->nullable()->unique();
            $table->text('address')->nullable();

            // -----------------------------------------------------------------
            // Role and status
            // -----------------------------------------------------------------
            $table->enum('role', ['supervisor', 'worker', 'driver'])->default('worker');
            $table->enum('status', ['active', 'inactive', 'on_leave', 'suspended'])->default('active');

            // -----------------------------------------------------------------
            // Vehicle information
            // -----------------------------------------------------------------
            $table->string('vehicle_number')->nullable();
            $table->string('vehicle_type')->nullable();

            // -----------------------------------------------------------------
            // Emergency contact
            // -----------------------------------------------------------------
            $table->string('emergency_contact')->nullable();

            // -----------------------------------------------------------------
            // Employment details
            // -----------------------------------------------------------------
            $table->date('hire_date')->nullable();
            $table->string('shift_preference')->nullable();

            // -----------------------------------------------------------------
            // JSON fields — flexible data
            // -----------------------------------------------------------------
            $table->json('certifications')->nullable();
            $table->json('availability_schedule')->nullable();
            $table->json('assigned_zone')->nullable();

            // -----------------------------------------------------------------
            // Location tracking
            // -----------------------------------------------------------------
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamp('last_location_update')->nullable();

            // -----------------------------------------------------------------
            // Profile photo
            // -----------------------------------------------------------------
            $table->string('profile_photo')->nullable();

            // -----------------------------------------------------------------
            // Metadata / audit
            // -----------------------------------------------------------------
            $table->json('metadata')->nullable();

            // -----------------------------------------------------------------
            // Timestamps + soft deletes
            // -----------------------------------------------------------------
            $table->timestamps();
            $table->softDeletes();

            // -----------------------------------------------------------------
            // Indexes — ONLY for columns that don't already have one.
            //
            // Skipped (already indexed automatically):
            //   • id                  → PRIMARY
            //   • user_id             → from foreignId()->constrained()
            //   • employee_id         → from unique()
            //   • phone               → from unique()
            //   • email               → from unique()
            //
            // Explicit index names avoid MySQL's 64-char identifier limit
            // and prevent "Duplicate key name" (1061) collisions.
            // -----------------------------------------------------------------
            $table->index(['status', 'role'], 'sp_status_role_index');
            $table->index(['latitude', 'longitude'], 'sp_lat_lng_index');
            $table->index('role', 'sp_role_index');
            $table->index('status', 'sp_status_index');
            $table->index('last_location_update', 'sp_last_location_update_index');
            $table->index('deleted_at', 'sp_deleted_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanitation_personnels');
    }
};