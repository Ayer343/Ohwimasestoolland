<?php
// database/migrations/2026_05_16_000001_create_device_tokens_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check if table exists, if not create it
        if (!Schema::hasTable('device_tokens')) {
            Schema::create('device_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('device_name');
                $table->string('device_type')->nullable();
                $table->string('device_platform')->nullable();
                $table->string('device_browser')->nullable();
                $table->string('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->string('token')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
                
                $table->index(['user_id', 'device_name']);
            });
        } else {
            // Add missing columns to existing table
            Schema::table('device_tokens', function (Blueprint $table) {
                if (!Schema::hasColumn('device_tokens', 'ip_address')) {
                    $table->string('ip_address')->nullable()->after('device_browser');
                }
                if (!Schema::hasColumn('device_tokens', 'token')) {
                    $table->string('token')->nullable()->after('user_agent');
                }
                if (!Schema::hasColumn('device_tokens', 'last_used_at')) {
                    $table->timestamp('last_used_at')->nullable()->after('token');
                }
                if (!Schema::hasColumn('device_tokens', 'created_at')) {
                    $table->timestamps();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};