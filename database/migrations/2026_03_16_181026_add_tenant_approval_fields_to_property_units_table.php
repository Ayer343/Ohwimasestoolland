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
        Schema::table('property_units', function (Blueprint $table) {
            // Add missing tenant approval fields
            if (!Schema::hasColumn('property_units', 'tenant_approved_by')) {
                $table->unsignedBigInteger('tenant_approved_by')->nullable()->after('tenant_requested_at');
            }
            
            if (!Schema::hasColumn('property_units', 'tenant_approved_at')) {
                $table->timestamp('tenant_approved_at')->nullable()->after('tenant_approved_by');
            }
            
            if (!Schema::hasColumn('property_units', 'tenant_approval_notes')) {
                $table->text('tenant_approval_notes')->nullable()->after('tenant_approved_at');
            }
            
            if (!Schema::hasColumn('property_units', 'tenant_details')) {
                $table->json('tenant_details')->nullable()->after('tenant_approval_notes');
            }
            
            // Add foreign key constraint for tenant_approved_by if it doesn't exist
            if (Schema::hasColumn('property_units', 'tenant_approved_by') && 
                !$this->foreignKeyExists('property_units', 'property_units_tenant_approved_by_foreign')) {
                $table->foreign('tenant_approved_by')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_units', function (Blueprint $table) {
            // Drop foreign key first if it exists
            if ($this->foreignKeyExists('property_units', 'property_units_tenant_approved_by_foreign')) {
                $table->dropForeign('property_units_tenant_approved_by_foreign');
            }
            
            // Drop columns
            $columns = ['tenant_approved_by', 'tenant_approved_at', 'tenant_approval_notes', 'tenant_details'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('property_units', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Check if a foreign key exists
     */
    private function foreignKeyExists(string $tableName, string $foreignKeyName): bool
    {
        // For MySQL
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        $result = $connection->selectOne("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = ? 
            AND CONSTRAINT_NAME = ?
        ", [$databaseName, $tableName, $foreignKeyName]);
        
        return !is_null($result);
    }
};