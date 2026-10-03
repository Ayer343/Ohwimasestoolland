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
        Schema::create('schedule_approval_queues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('security_schedule_id')->unique();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->integer('priority')->default(1);
            $table->json('review_notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('security_schedule_id');
            $table->index(['status', 'requested_at']);
            $table->index('requested_by');
            $table->index('approved_by');
            $table->index(['priority', 'requested_at']);
            
            // Foreign keys
            $table->foreign('security_schedule_id')
                  ->references('id')
                  ->on('security_schedules')
                  ->onDelete('cascade');
                  
            $table->foreign('requested_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
                  
            $table->foreign('approved_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
                  
            $table->foreign('rejected_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_approval_queues');
    }
};