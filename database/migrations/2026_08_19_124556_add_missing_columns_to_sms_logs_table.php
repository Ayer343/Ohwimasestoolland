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
        Schema::table('sms_logs', function (Blueprint $table) {
            // Add missing columns
            if (!Schema::hasColumn('sms_logs', 'user_name')) {
                $table->string('user_name')->nullable()->after('user_id');
            }
            
            if (!Schema::hasColumn('sms_logs', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('execution_time');
                $table->foreign('created_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            }
            
            if (!Schema::hasColumn('sms_logs', 'metadata')) {
                $table->json('metadata')->nullable()->after('created_by');
            }
            
            if (!Schema::hasColumn('sms_logs', 'deleted_at')) {
                $table->softDeletes()->after('metadata');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['user_name', 'created_by', 'metadata', 'deleted_at']);
        });
    }
};