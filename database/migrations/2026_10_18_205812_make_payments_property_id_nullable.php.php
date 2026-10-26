<?php
// database/migrations/2026_10_18_000001_make_payments_property_id_nullable.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Drop the FK first (if any) then re-add as nullable.
            // If there's no FK, the dropForeign call will fail — wrap in try/catch.
            try {
                $table->dropForeign(['property_id']);
            } catch (\Throwable $e) {
                // No FK existed, that's fine.
            }

            $table->unsignedBigInteger('property_id')->nullable()->change();
        });

        // Re-add the FK with ON DELETE SET NULL so removing a property
        // doesn't orphan historical ad payments.
        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('property_id')
                ->references('id')
                ->on('properties')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            try {
                $table->dropForeign(['property_id']);
            } catch (\Throwable $e) {}

            $table->unsignedBigInteger('property_id')->nullable(false)->change();

            $table->foreign('property_id')
                ->references('id')
                ->on('properties')
                ->onDelete('cascade');
        });
    }
};