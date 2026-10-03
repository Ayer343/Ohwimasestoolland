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
        Schema::table('maintenance_requests', function (Blueprint $table) {
            // Add category column if it doesn't exist
            if (!Schema::hasColumn('maintenance_requests', 'category')) {
                $table->string('category', 50)->default('other')->after('priority');
            }
            
            // Add actual_cost column if it doesn't exist
            if (!Schema::hasColumn('maintenance_requests', 'actual_cost')) {
                $table->decimal('actual_cost', 10, 2)->nullable()->after('cost_estimate');
            }
            
            // Add updated_by column if it doesn't exist
            if (!Schema::hasColumn('maintenance_requests', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropColumn(['category', 'actual_cost', 'updated_by']);
        });
    }
};