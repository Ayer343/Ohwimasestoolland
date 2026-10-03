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
        // First, create the jobs table if it doesn't exist
        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
                
                // Add priority column for queue ordering
                $table->integer('priority')->default(0)->index();
            });
        } else {
            // Add priority column if it doesn't exist
            Schema::table('jobs', function (Blueprint $table) {
                if (!Schema::hasColumn('jobs', 'priority')) {
                    $table->integer('priority')->default(0)->index();
                }
            });
        }

        // Create failed jobs table if it doesn't exist
        if (!Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
                
                // Add metadata columns for better tracking
                $table->string('job_type')->nullable()->index();
                $table->integer('attempts')->default(0);
                $table->timestamp('last_attempt_at')->nullable();
            });
        }

        // Create job_batches table if it doesn't exist
        if (!Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
                
                // Add custom fields for security schedule batches
                $table->string('batch_type')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->json('metadata')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove priority column if we added it
        if (Schema::hasTable('jobs') && Schema::hasColumn('jobs', 'priority')) {
            Schema::table('jobs', function (Blueprint $table) {
                $table->dropColumn('priority');
            });
        }
        
        // Note: We don't drop the tables here as they might be used by other applications
    }
};