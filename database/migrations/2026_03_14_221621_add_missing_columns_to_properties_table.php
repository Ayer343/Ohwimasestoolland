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
        Schema::table('properties', function (Blueprint $table) {
            // ===== TENANT RELATED COLUMNS =====
            // Add tenant_count column if it doesn't exist
            if (!Schema::hasColumn('properties', 'tenant_count')) {
                $table->integer('tenant_count')->default(0)->after('status');
            }
            
            // Add active_tenant_count column if it doesn't exist
            if (!Schema::hasColumn('properties', 'active_tenant_count')) {
                $table->integer('active_tenant_count')->default(0)->after('tenant_count');
            }
            
            // Add is_rented column if it doesn't exist
            if (!Schema::hasColumn('properties', 'is_rented')) {
                $table->boolean('is_rented')->default(false)->after('status');
            }

            // ===== PROPERTY TYPE COLUMNS =====
            // Add property_type_id if it doesn't exist
            if (!Schema::hasColumn('properties', 'property_type_id')) {
                $table->foreignId('property_type_id')->nullable()->constrained()->onDelete('set null')->after('registration_plan_id');
            }
            
            // Add custom_property_type column if it doesn't exist
            if (!Schema::hasColumn('properties', 'custom_property_type')) {
                $table->string('custom_property_type')->nullable()->after('property_type_id');
            }

            // ===== PROPERTY DETAILS COLUMNS =====
            // Add bedrooms column if it doesn't exist
            if (!Schema::hasColumn('properties', 'bedrooms')) {
                $table->integer('bedrooms')->nullable()->after('description');
            }
            
            // Add bathrooms column if it doesn't exist
            if (!Schema::hasColumn('properties', 'bathrooms')) {
                $table->integer('bathrooms')->nullable()->after('bedrooms');
            }
            
            // Add construction_status column if it doesn't exist
            if (!Schema::hasColumn('properties', 'construction_status')) {
                $table->enum('construction_status', ['under_construction', 'completed', 'planned'])->nullable()->after('status');
            }
            
            // Add estimated_completion column if it doesn't exist
            if (!Schema::hasColumn('properties', 'estimated_completion')) {
                $table->date('estimated_completion')->nullable()->after('construction_status');
            }
            
            // Add year_built column if it doesn't exist
            if (!Schema::hasColumn('properties', 'year_built')) {
                $table->integer('year_built')->nullable()->after('estimated_completion');
            }

            // ===== REGISTRATION PATTERN COLUMN =====
            // Add registration_pattern column if it doesn't exist
            if (!Schema::hasColumn('properties', 'registration_pattern')) {
                $table->string('registration_pattern')->nullable()->after('property_name');
            }

            // ===== LAST INSPECTION DATE COLUMN =====
            // Add last_inspection_date column if it doesn't exist
            if (!Schema::hasColumn('properties', 'last_inspection_date')) {
                $table->timestamp('last_inspection_date')->nullable()->after('status');
            }

            // ===== GLOBAL SEQUENCE COLUMNS =====
            // Add is_global_sequence column if it doesn't exist
            if (!Schema::hasColumn('properties', 'is_global_sequence')) {
                $table->boolean('is_global_sequence')->default(false)->after('created_by');
            }
            
            // Add sequence_position column if it doesn't exist
            if (!Schema::hasColumn('properties', 'sequence_position')) {
                $table->integer('sequence_position')->nullable()->after('is_global_sequence');
            }

            // ===== FIELD AGENT COLUMNS =====
            // Add is_field_agent_registered column if it doesn't exist
            if (!Schema::hasColumn('properties', 'is_field_agent_registered')) {
                $table->boolean('is_field_agent_registered')->default(false)->after('is_rented');
            }
            
            // Add field_agent_registered_at column if it doesn't exist
            if (!Schema::hasColumn('properties', 'field_agent_registered_at')) {
                $table->timestamp('field_agent_registered_at')->nullable()->after('is_field_agent_registered');
            }

            // ===== SOURCE REGISTRATION COLUMN =====
            // Add source_registration_id column if it doesn't exist
            if (!Schema::hasColumn('properties', 'source_registration_id')) {
                $table->unsignedBigInteger('source_registration_id')->nullable()->after('id');
                $table->foreign('source_registration_id')
                      ->references('id')
                      ->on('landlord_construction_registrations')
                      ->onDelete('set null');
            }
        });

        // ===== ADD INDEXES FOR BETTER PERFORMANCE (WITH EXISTENCE CHECKS) =====
        Schema::table('properties', function (Blueprint $table) {
            // Get existing indexes
            $indexes = $this->getExistingIndexes('properties');
            
            // Tenant related indexes
            if (Schema::hasColumn('properties', 'tenant_count') && !in_array('properties_tenant_count_index', $indexes)) {
                $table->index('tenant_count', 'properties_tenant_count_index');
            }
            
            if (Schema::hasColumn('properties', 'active_tenant_count') && !in_array('properties_active_tenant_count_index', $indexes)) {
                $table->index('active_tenant_count', 'properties_active_tenant_count_index');
            }
            
            if (Schema::hasColumn('properties', 'is_rented') && !in_array('properties_is_rented_index', $indexes)) {
                $table->index('is_rented', 'properties_is_rented_index');
            }
            
            // Property type indexes
            if (Schema::hasColumn('properties', 'property_type_id') && !in_array('properties_property_type_id_index', $indexes)) {
                $table->index('property_type_id', 'properties_property_type_id_index');
            }
            
            // Construction related indexes
            if (Schema::hasColumn('properties', 'construction_status') && !in_array('properties_construction_status_index', $indexes)) {
                $table->index('construction_status', 'properties_construction_status_index');
            }
            
            if (Schema::hasColumn('properties', 'estimated_completion') && !in_array('properties_estimated_completion_index', $indexes)) {
                $table->index('estimated_completion', 'properties_estimated_completion_index');
            }
            
            if (Schema::hasColumn('properties', 'year_built') && !in_array('properties_year_built_index', $indexes)) {
                $table->index('year_built', 'properties_year_built_index');
            }
            
            // Registration pattern index
            if (Schema::hasColumn('properties', 'registration_pattern') && !in_array('properties_registration_pattern_index', $indexes)) {
                $table->index('registration_pattern', 'properties_registration_pattern_index');
            }
            
            // Global sequence indexes
            if (Schema::hasColumn('properties', 'is_global_sequence') && !in_array('properties_is_global_sequence_index', $indexes)) {
                $table->index('is_global_sequence', 'properties_is_global_sequence_index');
            }
            
            if (Schema::hasColumn('properties', 'sequence_position') && !in_array('properties_sequence_position_index', $indexes)) {
                $table->index('sequence_position', 'properties_sequence_position_index');
            }
            
            // Field agent indexes
            if (Schema::hasColumn('properties', 'is_field_agent_registered') && !in_array('properties_is_field_agent_registered_index', $indexes)) {
                $table->index('is_field_agent_registered', 'properties_is_field_agent_registered_index');
            }
            
            if (Schema::hasColumn('properties', 'field_agent_registered_at') && !in_array('properties_field_agent_registered_at_index', $indexes)) {
                $table->index('field_agent_registered_at', 'properties_field_agent_registered_at_index');
            }
            
            // Source registration index
            if (Schema::hasColumn('properties', 'source_registration_id') && !in_array('properties_source_registration_id_index', $indexes)) {
                $table->index('source_registration_id', 'properties_source_registration_id_index');
            }
            
            // Bedrooms and bathrooms indexes
            if (Schema::hasColumn('properties', 'bedrooms') && !in_array('properties_bedrooms_index', $indexes)) {
                $table->index('bedrooms', 'properties_bedrooms_index');
            }
            
            if (Schema::hasColumn('properties', 'bathrooms') && !in_array('properties_bathrooms_index', $indexes)) {
                $table->index('bathrooms', 'properties_bathrooms_index');
            }
            
            // Combined indexes for common queries
            if (Schema::hasColumn('properties', 'construction_status') && 
                Schema::hasColumn('properties', 'zone') && 
                !in_array('properties_const_status_zone_index', $indexes)) {
                $table->index(['construction_status', 'zone'], 'properties_const_status_zone_index');
            }
            
            if (Schema::hasColumn('properties', 'is_rented') && 
                Schema::hasColumn('properties', 'zone') && 
                !in_array('properties_is_rented_zone_index', $indexes)) {
                $table->index(['is_rented', 'zone'], 'properties_is_rented_zone_index');
            }
            
            if (Schema::hasColumn('properties', 'property_type_id') && 
                Schema::hasColumn('properties', 'zone') && 
                !in_array('properties_property_type_zone_index', $indexes)) {
                $table->index(['property_type_id', 'zone'], 'properties_property_type_zone_index');
            }
        });
    }

    /**
     * Get existing indexes for a table
     */
    private function getExistingIndexes($tableName)
    {
        $indexes = [];
        try {
            $connection = Schema::getConnection();
            if ($connection->getDriverName() === 'mysql') {
                $result = $connection->select("SHOW INDEX FROM `{$tableName}`");
                foreach ($result as $row) {
                    $indexes[] = $row->Key_name;
                }
            } elseif ($connection->getDriverName() === 'pgsql') {
                $result = $connection->select("
                    SELECT indexname FROM pg_indexes 
                    WHERE tablename = '{$tableName}'
                ");
                foreach ($result as $row) {
                    $indexes[] = $row->indexname;
                }
            }
        } catch (\Exception $e) {
            // If we can't get indexes, return empty array
        }
        return array_unique($indexes);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Drop indexes first (with existence checks)
            $indexes = $this->getExistingIndexes('properties');
            
            $indexesToDrop = [
                'properties_tenant_count_index',
                'properties_active_tenant_count_index',
                'properties_is_rented_index',
                'properties_property_type_id_index',
                'properties_construction_status_index',
                'properties_estimated_completion_index',
                'properties_year_built_index',
                'properties_registration_pattern_index',
                'properties_is_global_sequence_index',
                'properties_sequence_position_index',
                'properties_is_field_agent_registered_index',
                'properties_field_agent_registered_at_index',
                'properties_source_registration_id_index',
                'properties_bedrooms_index',
                'properties_bathrooms_index',
                'properties_const_status_zone_index',
                'properties_is_rented_zone_index',
                'properties_property_type_zone_index'
            ];
            
            foreach ($indexesToDrop as $index) {
                if (in_array($index, $indexes)) {
                    try {
                        $table->dropIndex($index);
                    } catch (\Exception $e) {
                        // Index might not exist, continue
                    }
                }
            }
            
            // Drop foreign keys first
            if (Schema::hasColumn('properties', 'source_registration_id')) {
                try {
                    $table->dropForeign(['source_registration_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist
                }
            }
            if (Schema::hasColumn('properties', 'property_type_id')) {
                try {
                    $table->dropForeign(['property_type_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist
                }
            }
            
            // Drop columns (only those that exist)
            $columns = [];
            
            $possibleColumns = [
                'tenant_count',
                'active_tenant_count',
                'is_rented',
                'property_type_id',
                'custom_property_type',
                'bedrooms',
                'bathrooms',
                'construction_status',
                'estimated_completion',
                'year_built',
                'registration_pattern',
                'last_inspection_date',
                'is_global_sequence',
                'sequence_position',
                'is_field_agent_registered',
                'field_agent_registered_at',
                'source_registration_id'
            ];
            
            foreach ($possibleColumns as $column) {
                if (Schema::hasColumn('properties', $column)) {
                    $columns[] = $column;
                }
            }
            
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};