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
        Schema::table('construction_contracts', function (Blueprint $table) {
            // Add completed_at column if it doesn't exist
            if (!Schema::hasColumn('construction_contracts', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('estimated_completion_date');
                $table->index('completed_at');
            }
            
            // Also add actual_completion_date if it doesn't exist (for consistency)
            if (!Schema::hasColumn('construction_contracts', 'actual_completion_date')) {
                $table->timestamp('actual_completion_date')->nullable()->after('estimated_completion_date');
                $table->index('actual_completion_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('construction_contracts', function (Blueprint $table) {
            if (Schema::hasColumn('construction_contracts', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
            if (Schema::hasColumn('construction_contracts', 'actual_completion_date')) {
                $table->dropColumn('actual_completion_date');
            }
        });
    }
};