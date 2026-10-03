<?php
// database/migrations/YYYY_MM_DD_HHMMSS_create_sanitation_service_requests_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanitation_service_requests', function (Blueprint $table) {
            $table->id();

            // ------------------------------------------------------------- //
            // Relationships
            // ------------------------------------------------------------- //
            $table->foreignId('property_id')
                ->constrained('properties')
                ->cascadeOnDelete();

            $table->foreignId('landlord_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Preferred supervisor chosen by the landlord
            $table->foreignId('sanitation_personnel_id')
                ->nullable()
                ->constrained('sanitation_personnels')
                ->nullOnDelete();

            // Who actually responded (could be the supervisor, their own supervisor, or an admin)
            $table->foreignId('responded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // ------------------------------------------------------------- //
            // Lifecycle
            // ------------------------------------------------------------- //
            $table->enum('status', ['pending', 'accepted', 'rejected', 'cancelled', 'expired'])
                ->default('pending');

            $table->enum('priority', ['low', 'medium', 'high'])
                ->default('medium');

            $table->text('message')->nullable();
            $table->text('response_notes')->nullable();

            $table->timestamp('responded_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // ------------------------------------------------------------- //
            // Denormalized snapshot — makes the landlord listing fast
            // without joins and preserves what was true at request time
            // ------------------------------------------------------------- //
            $table->string('property_name')->nullable();
            $table->string('digital_address')->nullable();
            $table->string('landlord_name')->nullable();
            $table->string('landlord_phone')->nullable();
            $table->string('landlord_email')->nullable();
            $table->string('preferred_supervisor_name')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // ------------------------------------------------------------- //
            // Indexes
            // ------------------------------------------------------------- //
            $table->index(['landlord_id', 'status']);
            $table->index(['property_id', 'status']);
            $table->index(['sanitation_personnel_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('priority');

            // Prevent duplicate pending requests for the same property
            $table->unique(
                ['property_id', 'status'],
                'unique_property_pending_status'
            )->where('status', 'pending');
        });

        // Comments for MySQL / MariaDB
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sanitation_service_requests
                MODIFY property_id BIGINT UNSIGNED NOT NULL
                COMMENT 'Property that the landlord wants linked for waste collection'");

            DB::statement("ALTER TABLE sanitation_service_requests
                MODIFY landlord_id BIGINT UNSIGNED NOT NULL
                COMMENT 'Landlord who initiated the request'");

            DB::statement("ALTER TABLE sanitation_service_requests
                MODIFY sanitation_personnel_id BIGINT UNSIGNED NULL
                COMMENT 'Preferred sanitation supervisor chosen by the landlord'");

            DB::statement("ALTER TABLE sanitation_service_requests
                MODIFY responded_by BIGINT UNSIGNED NULL
                COMMENT 'User who accepted/rejected the request'");

            DB::statement("ALTER TABLE sanitation_service_requests
                MODIFY status ENUM('pending','accepted','rejected','cancelled','expired')
                DEFAULT 'pending'
                COMMENT 'Current state of the service request'");

            DB::statement("ALTER TABLE sanitation_service_requests
                MODIFY priority ENUM('low','medium','high')
                DEFAULT 'medium'
                COMMENT 'Landlord-declared urgency'");

            DB::statement("ALTER TABLE sanitation_service_requests
                MODIFY message TEXT NULL
                COMMENT 'Landlord-provided message to the sanitation team'");

            DB::statement("ALTER TABLE sanitation_service_requests
                MODIFY response_notes TEXT NULL
                COMMENT 'Notes from the sanitation team when responding'");

            DB::statement("ALTER TABLE sanitation_service_requests
                MODIFY responded_at TIMESTAMP NULL
                COMMENT 'When the sanitation team responded'");

            DB::statement("ALTER TABLE sanitation_service_requests
                MODIFY expires_at TIMESTAMP NULL
                COMMENT 'After this time a pending request can be auto-expired'");
        }

        // Comments for PostgreSQL
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("COMMENT ON COLUMN sanitation_service_requests.property_id IS 'Property that the landlord wants linked for waste collection'");
            DB::statement("COMMENT ON COLUMN sanitation_service_requests.landlord_id IS 'Landlord who initiated the request'");
            DB::statement("COMMENT ON COLUMN sanitation_service_requests.sanitation_personnel_id IS 'Preferred sanitation supervisor chosen by the landlord'");
            DB::statement("COMMENT ON COLUMN sanitation_service_requests.responded_by IS 'User who accepted/rejected the request'");
            DB::statement("COMMENT ON COLUMN sanitation_service_requests.status IS 'Current state of the service request'");
            DB::statement("COMMENT ON COLUMN sanitation_service_requests.priority IS 'Landlord-declared urgency'");
            DB::statement("COMMENT ON COLUMN sanitation_service_requests.message IS 'Landlord-provided message to the sanitation team'");
            DB::statement("COMMENT ON COLUMN sanitation_service_requests.response_notes IS 'Notes from the sanitation team when responding'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sanitation_service_requests');
    }
};