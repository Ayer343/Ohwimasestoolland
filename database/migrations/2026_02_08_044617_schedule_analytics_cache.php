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
        Schema::create('schedule_analytics_cache', function (Blueprint $table) {
            $table->id();
            $table->string('analytics_key')->unique();
            $table->string('analytics_type'); // daily_summary, weekly_report, personnel_stats, etc.
            $table->json('analytics_data');
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->json('filters')->nullable();
            $table->timestamp('generated_at')->nullable(); // Changed to nullable
            $table->timestamp('expires_at')->nullable(); // Changed to nullable
            $table->timestamps();
            
            // Indexes with short names
            $table->index('analytics_key', 'idx_analytics_key');
            $table->index(['analytics_type', 'expires_at'], 'idx_analytics_type_expires');
            $table->index(['date_from', 'date_to'], 'idx_analytics_dates');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_analytics_cache');
    }
};