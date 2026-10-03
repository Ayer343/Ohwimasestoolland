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
            // Add has_plans column
            $table->boolean('has_plans')->default(false)->after('estimated_completion');
            
            // Add construction_documents column (JSON array to store file paths)
            $table->json('construction_documents')->nullable()->after('has_plans');
            
            // Add construction_notes column
            $table->text('construction_notes')->nullable()->after('construction_documents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('has_plans');
            $table->dropColumn('construction_documents');
            $table->dropColumn('construction_notes');
        });
    }
};