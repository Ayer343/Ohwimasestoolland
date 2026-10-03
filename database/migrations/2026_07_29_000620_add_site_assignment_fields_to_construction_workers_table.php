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
        Schema::table('construction_workers', function (Blueprint $table) {
            // ✅ Add site assignment fields
            $table->boolean('is_assigned_to_site')->default(false)->after('status');
            $table->timestamp('assigned_to_site_at')->nullable()->after('is_assigned_to_site');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('construction_workers', function (Blueprint $table) {
            $table->dropColumn(['is_assigned_to_site', 'assigned_to_site_at']);
        });
    }
};