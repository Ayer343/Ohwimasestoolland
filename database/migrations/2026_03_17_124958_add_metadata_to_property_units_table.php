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
            // Add JSON column for metadata
            $table->json('metadata')->nullable()->after('tenant_documents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_units', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};