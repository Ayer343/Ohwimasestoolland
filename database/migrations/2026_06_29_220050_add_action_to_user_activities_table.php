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
        Schema::table('user_activities', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('user_activities', 'action')) {
                $table->string('action')->after('user_id');
            }
            if (!Schema::hasColumn('user_activities', 'description')) {
                $table->text('description')->nullable()->after('action');
            }
            if (!Schema::hasColumn('user_activities', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('description');
            }
            if (!Schema::hasColumn('user_activities', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }
            if (!Schema::hasColumn('user_activities', 'metadata')) {
                $table->json('metadata')->nullable()->after('user_agent');
            }
            if (!Schema::hasColumn('user_activities', 'performed_at')) {
                $table->timestamp('performed_at')->nullable()->after('metadata');
            }
            if (!Schema::hasColumn('user_activities', 'is_suspicious')) {
                $table->boolean('is_suspicious')->default(false)->after('performed_at');
            }
            
            // Add indexes
            $table->index('action', 'idx_user_activities_action');
            $table->index('performed_at', 'idx_user_activities_performed');
            $table->index('is_suspicious', 'idx_user_activities_suspicious');
            $table->index(['user_id', 'action'], 'idx_user_activities_user_action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_activities', function (Blueprint $table) {
            $table->dropColumn(['action', 'description', 'ip_address', 'user_agent', 'metadata', 'performed_at', 'is_suspicious']);
            $table->dropIndex('idx_user_activities_action');
            $table->dropIndex('idx_user_activities_performed');
            $table->dropIndex('idx_user_activities_suspicious');
            $table->dropIndex('idx_user_activities_user_action');
        });
    }
};