<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            
            // Relationships
            $table->foreignId('landlord_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('registration_plan_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            
            // ✅ ADDED: Field agent relationship
            $table->foreignId('registered_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Property Identification - Enhanced with naming pattern support
            $table->string('property_name'); // Required: Site allocation name (from Blade template)
            $table->string('registration_pattern'); // Auto-generated registration pattern
            $table->string('house_number')->nullable(); // Made nullable (matches Blade template)
            $table->string('street_name'); // Required (matches Blade template validation)
            $table->string('block_number')->nullable(); // Optional
            
            // Digital Address (Ghana Post GPS Address)
            $table->string('digital_address')->nullable(); // e.g., "GA-543-9876"
            
            // Internal Zoning - Now auto-filled from registration plan but can be overridden
            $table->string('zone'); // Required: Either from plan or manual override
            $table->string('section')->nullable(); // Optional: From plan or manual override
            
            // Property Details
            $table->text('description')->nullable();
            
            // Status and Tracking
            $table->enum('status', ['active', 'inactive', 'under_maintenance', 'vacant'])->default('active');
            $table->date('registration_date'); // Required (matches Blade template)
            $table->timestamp('last_inspection_date')->nullable();
            
            // Global Sequence Metadata - For better tracking and reporting
            $table->integer('sequence_position')->nullable()->default(null); // Position in registration plan sequence
            $table->boolean('is_global_sequence')->default(false); // Whether part of global sequence
            
            // ✅ ADDED: Field Agent Metadata
            $table->boolean('is_field_agent_registered')->default(false); // Whether registered by field agent
            $table->timestamp('field_agent_registered_at')->nullable(); // When field agent registered this property
            
            // Soft deletes and timestamps
            $table->softDeletes();
            $table->timestamps();
            
            // Indexes for better query performance - Enhanced indexes
            $table->index(['property_name']); // Index for property name searches
            $table->index(['registration_pattern']); // Index for registration pattern searches
            $table->index(['zone', 'section']); // Combined zone/section index
            $table->index(['street_name', 'house_number']);
            $table->index('landlord_id');
            $table->index('status');
            $table->index('digital_address');
            $table->index('registration_plan_id');
            $table->index('created_by');
            $table->index(['registration_plan_id', 'status']);
            $table->index('registration_date');
            $table->index('last_inspection_date');
            $table->index(['created_at']);
            $table->index(['updated_at']);
            $table->index(['deleted_at']); // Index for soft delete queries
            
            // ✅ ADDED: Field Agent Indexes
            $table->index('registered_by');
            $table->index('is_field_agent_registered');
            $table->index('field_agent_registered_at');
            $table->index(['registered_by', 'status']); // For field agent performance queries
            $table->index(['registered_by', 'registration_date']); // For field agent timeline
            $table->index(['is_field_agent_registered', 'registration_date']); // For field agent registration analysis
            
            // Global Sequence Indexes
            $table->index('is_global_sequence');
            $table->index('sequence_position');
            $table->index(['registration_plan_id', 'sequence_position']); // For sequence order queries
            $table->index(['is_global_sequence', 'registration_date']); // For global sequence timeline
            $table->index(['zone', 'is_global_sequence']); // For zone-based global sequence analysis
            
            // ✅ ADDED: Combined Field Agent + Global Sequence Indexes
            $table->index(['registered_by', 'is_global_sequence']); // For field agent global sequence performance
            $table->index(['is_field_agent_registered', 'is_global_sequence']); // For global sequence field agent analysis
            
            // Combined indexes for common query patterns
            $table->index(['zone', 'status']); // For zone-based status queries
            $table->index(['landlord_id', 'status']); // For landlord property management
            $table->index(['registration_plan_id', 'zone']); // For plan-zone analysis
            $table->index(['is_global_sequence', 'status']); // For global sequence status management
            
            // ✅ ADDED: Field Agent Combined Indexes
            $table->index(['registered_by', 'zone']); // For field agent zone performance
            $table->index(['registered_by', 'registration_plan_id']); // For field agent plan assignments
            $table->index(['is_field_agent_registered', 'zone', 'status']); // For field agent zone status analysis
            
            // Unique constraints where appropriate
            $table->unique(['registration_plan_id', 'registration_pattern'], 'unique_plan_registration_pattern'); // Ensure unique patterns per plan
            $table->unique(['landlord_id', 'property_name'], 'unique_landlord_property_name'); // Ensure unique property names per landlord
            
            // Ensure sequence position uniqueness within plan (when position is set)
            $table->unique(['registration_plan_id', 'sequence_position'], 'unique_plan_sequence_position')
                  ->whereNotNull('sequence_position');
                  
            // ✅ ADDED: Ensure field agent doesn't duplicate registrations in same plan
            $table->unique(['registered_by', 'registration_plan_id', 'registration_pattern'], 'unique_field_agent_plan_pattern')
                  ->whereNotNull('registered_by');
        });

        // Add column comments for MySQL/MariaDB
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE properties MODIFY property_name VARCHAR(255) NOT NULL COMMENT 'Site allocation name that belongs to landlord (required)'");
            DB::statement("ALTER TABLE properties MODIFY registration_pattern VARCHAR(255) NOT NULL COMMENT 'Auto-generated registration pattern based on naming pattern (required)'");
            DB::statement("ALTER TABLE properties MODIFY house_number VARCHAR(50) NULL COMMENT 'Physical house number (optional if using naming pattern)'");
            DB::statement("ALTER TABLE properties MODIFY street_name VARCHAR(255) NOT NULL COMMENT 'Street name where property is located (required)'");
            DB::statement("ALTER TABLE properties MODIFY block_number VARCHAR(50) NULL COMMENT 'Block number for large developments'");
            DB::statement("ALTER TABLE properties MODIFY created_by BIGINT UNSIGNED NULL COMMENT 'User who created/registered the property'");
            DB::statement("ALTER TABLE properties MODIFY registered_by BIGINT UNSIGNED NULL COMMENT 'Field agent who registered this property (if applicable)'");
            DB::statement("ALTER TABLE properties MODIFY zone VARCHAR(100) NOT NULL COMMENT 'Geographic zone, auto-filled from registration plan or manual override (required)'");
            DB::statement("ALTER TABLE properties MODIFY section VARCHAR(100) NULL COMMENT 'Geographic section, auto-filled from registration plan or manual override'");
            DB::statement("ALTER TABLE properties MODIFY registration_plan_id BIGINT UNSIGNED NULL COMMENT 'Reference to the registration plan this property belongs to'");
            DB::statement("ALTER TABLE properties MODIFY digital_address VARCHAR(255) NULL COMMENT 'Ghana Post GPS digital address'");
            DB::statement("ALTER TABLE properties MODIFY status ENUM('active', 'inactive', 'under_maintenance', 'vacant') DEFAULT 'active' COMMENT 'Current status of the property'");
            DB::statement("ALTER TABLE properties MODIFY registration_date DATE NOT NULL COMMENT 'Date when property was registered in the system'");
            DB::statement("ALTER TABLE properties MODIFY sequence_position INT NULL COMMENT 'Position of this property in the registration plan sequence'");
            DB::statement("ALTER TABLE properties MODIFY is_global_sequence BOOLEAN DEFAULT FALSE COMMENT 'Whether this property is part of a global sequence registration plan'");
            DB::statement("ALTER TABLE properties MODIFY is_field_agent_registered BOOLEAN DEFAULT FALSE COMMENT 'Whether this property was registered by a field agent'");
            DB::statement("ALTER TABLE properties MODIFY field_agent_registered_at TIMESTAMP NULL COMMENT 'Timestamp when field agent registered this property'");
        }

        // Add column comments for PostgreSQL
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("COMMENT ON COLUMN properties.property_name IS 'Site allocation name that belongs to landlord (required)'");
            DB::statement("COMMENT ON COLUMN properties.registration_pattern IS 'Auto-generated registration pattern based on naming pattern (required)'");
            DB::statement("COMMENT ON COLUMN properties.house_number IS 'Physical house number (optional if using naming pattern)'");
            DB::statement("COMMENT ON COLUMN properties.street_name IS 'Street name where property is located (required)'");
            DB::statement("COMMENT ON COLUMN properties.block_number IS 'Block number for large developments'");
            DB::statement("COMMENT ON COLUMN properties.created_by IS 'User who created/registered the property'");
            DB::statement("COMMENT ON COLUMN properties.registered_by IS 'Field agent who registered this property (if applicable)'");
            DB::statement("COMMENT ON COLUMN properties.zone IS 'Geographic zone, auto-filled from registration plan or manual override (required)'");
            DB::statement("COMMENT ON COLUMN properties.section IS 'Geographic section, auto-filled from registration plan or manual override'");
            DB::statement("COMMENT ON COLUMN properties.registration_plan_id IS 'Reference to the registration plan this property belongs to'");
            DB::statement("COMMENT ON COLUMN properties.digital_address IS 'Ghana Post GPS digital address'");
            DB::statement("COMMENT ON COLUMN properties.status IS 'Current status of the property'");
            DB::statement("COMMENT ON COLUMN properties.registration_date IS 'Date when property was registered in the system'");
            DB::statement("COMMENT ON COLUMN properties.sequence_position IS 'Position of this property in the registration plan sequence'");
            DB::statement("COMMENT ON COLUMN properties.is_global_sequence IS 'Whether this property is part of a global sequence registration plan'");
            DB::statement("COMMENT ON COLUMN properties.is_field_agent_registered IS 'Whether this property was registered by a field agent'");
            DB::statement("COMMENT ON COLUMN properties.field_agent_registered_at IS 'Timestamp when field agent registered this property'");
        }

        // ✅ ADDED: Create a separate migration for field agent performance views (optional but recommended)
        $this->createFieldAgentPerformanceViews();
    }

    public function down()
    {
        // ✅ ADDED: Drop performance views first
        $this->dropFieldAgentPerformanceViews();
        
        Schema::dropIfExists('properties');
    }

    /**
     * ✅ NEW: Create performance views for field agent analytics
     */
    private function createFieldAgentPerformanceViews(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            // View for field agent monthly performance
            DB::statement("
                CREATE OR REPLACE VIEW field_agent_monthly_performance AS
                SELECT 
                    u.id as field_agent_id,
                    u.name as field_agent_name,
                    YEAR(p.created_at) as year,
                    MONTH(p.created_at) as month,
                    COUNT(p.id) as properties_registered,
                    COUNT(CASE WHEN p.digital_address IS NOT NULL AND p.digital_address != '' THEN 1 END) as with_digital_address,
                    COUNT(CASE WHEN p.is_global_sequence = TRUE THEN 1 END) as global_sequence_properties,
                    AVG(CASE WHEN p.digital_address IS NOT NULL AND p.digital_address != '' THEN 1 ELSE 0 END) * 100 as digital_address_completion_rate
                FROM users u
                LEFT JOIN properties p ON u.id = p.registered_by
                WHERE u.type = 'field_agent' AND p.deleted_at IS NULL
                GROUP BY u.id, u.name, YEAR(p.created_at), MONTH(p.created_at)
                ORDER BY year DESC, month DESC, properties_registered DESC
            ");

            // View for field agent zone performance
            DB::statement("
                CREATE OR REPLACE VIEW field_agent_zone_performance AS
                SELECT 
                    u.id as field_agent_id,
                    u.name as field_agent_name,
                    p.zone,
                    COUNT(p.id) as properties_registered,
                    COUNT(CASE WHEN p.status = 'active' THEN 1 END) as active_properties,
                    COUNT(CASE WHEN p.digital_address IS NOT NULL AND p.digital_address != '' THEN 1 END) as with_digital_address,
                    COUNT(CASE WHEN p.is_global_sequence = TRUE THEN 1 END) as global_sequence_properties
                FROM users u
                LEFT JOIN properties p ON u.id = p.registered_by
                WHERE u.type = 'field_agent' AND p.deleted_at IS NULL
                GROUP BY u.id, u.name, p.zone
                ORDER BY properties_registered DESC
            ");

            // View for field agent registration plan performance
            DB::statement("
                CREATE OR REPLACE VIEW field_agent_plan_performance AS
                SELECT 
                    u.id as field_agent_id,
                    u.name as field_agent_name,
                    rp.id as plan_id,
                    rp.zone as plan_zone,
                    rp.section as plan_section,
                    COUNT(p.id) as properties_registered,
                    rp.estimated_houses as plan_target,
                    (COUNT(p.id) / rp.estimated_houses) * 100 as completion_rate
                FROM users u
                LEFT JOIN properties p ON u.id = p.registered_by
                LEFT JOIN registration_plans rp ON p.registration_plan_id = rp.id
                WHERE u.type = 'field_agent' AND p.deleted_at IS NULL AND rp.id IS NOT NULL
                GROUP BY u.id, u.name, rp.id, rp.zone, rp.section, rp.estimated_houses
                ORDER BY completion_rate DESC
            ");

        } elseif (DB::connection()->getDriverName() === 'pgsql') {
            // PostgreSQL versions of the views
            DB::statement("
                CREATE OR REPLACE VIEW field_agent_monthly_performance AS
                SELECT 
                    u.id as field_agent_id,
                    u.name as field_agent_name,
                    EXTRACT(YEAR FROM p.created_at) as year,
                    EXTRACT(MONTH FROM p.created_at) as month,
                    COUNT(p.id) as properties_registered,
                    COUNT(CASE WHEN p.digital_address IS NOT NULL AND p.digital_address != '' THEN 1 END) as with_digital_address,
                    COUNT(CASE WHEN p.is_global_sequence = TRUE THEN 1 END) as global_sequence_properties,
                    AVG(CASE WHEN p.digital_address IS NOT NULL AND p.digital_address != '' THEN 1 ELSE 0 END) * 100 as digital_address_completion_rate
                FROM users u
                LEFT JOIN properties p ON u.id = p.registered_by
                WHERE u.type = 'field_agent' AND p.deleted_at IS NULL
                GROUP BY u.id, u.name, EXTRACT(YEAR FROM p.created_at), EXTRACT(MONTH FROM p.created_at)
                ORDER BY year DESC, month DESC, properties_registered DESC
            ");

            DB::statement("
                CREATE OR REPLACE VIEW field_agent_zone_performance AS
                SELECT 
                    u.id as field_agent_id,
                    u.name as field_agent_name,
                    p.zone,
                    COUNT(p.id) as properties_registered,
                    COUNT(CASE WHEN p.status = 'active' THEN 1 END) as active_properties,
                    COUNT(CASE WHEN p.digital_address IS NOT NULL AND p.digital_address != '' THEN 1 END) as with_digital_address,
                    COUNT(CASE WHEN p.is_global_sequence = TRUE THEN 1 END) as global_sequence_properties
                FROM users u
                LEFT JOIN properties p ON u.id = p.registered_by
                WHERE u.type = 'field_agent' AND p.deleted_at IS NULL
                GROUP BY u.id, u.name, p.zone
                ORDER BY properties_registered DESC
            ");

            DB::statement("
                CREATE OR REPLACE VIEW field_agent_plan_performance AS
                SELECT 
                    u.id as field_agent_id,
                    u.name as field_agent_name,
                    rp.id as plan_id,
                    rp.zone as plan_zone,
                    rp.section as plan_section,
                    COUNT(p.id) as properties_registered,
                    rp.estimated_houses as plan_target,
                    (COUNT(p.id)::decimal / rp.estimated_houses) * 100 as completion_rate
                FROM users u
                LEFT JOIN properties p ON u.id = p.registered_by
                LEFT JOIN registration_plans rp ON p.registration_plan_id = rp.id
                WHERE u.type = 'field_agent' AND p.deleted_at IS NULL AND rp.id IS NOT NULL
                GROUP BY u.id, u.name, rp.id, rp.zone, rp.section, rp.estimated_houses
                ORDER BY completion_rate DESC
            ");
        }
    }

    /**
     * ✅ NEW: Drop performance views
     */
    private function dropFieldAgentPerformanceViews(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("DROP VIEW IF EXISTS field_agent_monthly_performance");
            DB::statement("DROP VIEW IF EXISTS field_agent_zone_performance");
            DB::statement("DROP VIEW IF EXISTS field_agent_plan_performance");
        } elseif (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("DROP VIEW IF EXISTS field_agent_monthly_performance");
            DB::statement("DROP VIEW IF EXISTS field_agent_zone_performance");
            DB::statement("DROP VIEW IF EXISTS field_agent_plan_performance");
        }
    }
};