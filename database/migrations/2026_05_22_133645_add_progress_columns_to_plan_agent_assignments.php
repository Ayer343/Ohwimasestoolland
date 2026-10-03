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
        Schema::table('plan_agent_assignments', function (Blueprint $table) {
            // Add completion_percentage column if it doesn't exist
            if (!Schema::hasColumn('plan_agent_assignments', 'completion_percentage')) {
                $table->decimal('completion_percentage', 5, 2)->default(0)->after('properties_registered');
            }
            
            // Add is_completed column if it doesn't exist
            if (!Schema::hasColumn('plan_agent_assignments', 'is_completed')) {
                $table->boolean('is_completed')->default(false)->after('completion_percentage');
            }
            
            // Add completed_at column if it doesn't exist
            if (!Schema::hasColumn('plan_agent_assignments', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('is_completed');
            }
            
            // Add last_activity_at column if it doesn't exist
            if (!Schema::hasColumn('plan_agent_assignments', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable()->after('completed_at');
            }
            
            // Add indexes for better performance
            $table->index('completion_percentage');
            $table->index('is_completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_agent_assignments', function (Blueprint $table) {
            $table->dropColumnIfExists('completion_percentage');
            $table->dropColumnIfExists('is_completed');
            $table->dropColumnIfExists('completed_at');
            $table->dropColumnIfExists('last_activity_at');
        });
    }
};