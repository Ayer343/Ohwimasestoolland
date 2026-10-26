<?php
// database/migrations/2026_10_18_000002_add_ad_campaign_id_to_payments.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'ad_campaign_id')) {
                $table->unsignedBigInteger('ad_campaign_id')->nullable()->after('property_id');

                $table->foreign('ad_campaign_id')
                    ->references('id')
                    ->on('ad_campaigns')
                    ->onDelete('set null');

                $table->index('ad_campaign_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'ad_campaign_id')) {
                try {
                    $table->dropForeign(['ad_campaign_id']);
                } catch (\Throwable $e) {}

                $table->dropIndex(['ad_campaign_id']);
                $table->dropColumn('ad_campaign_id');
            }
        });
    }
};