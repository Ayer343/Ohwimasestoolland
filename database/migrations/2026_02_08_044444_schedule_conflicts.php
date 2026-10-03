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
        Schema::create('schedule_conflicts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule1_id');
            $table->unsignedBigInteger('schedule2_id');
            $table->string('conflict_type'); // time_overlap, double_booking, capacity_exceeded, etc.
            $table->json('conflict_details');
            $table->integer('overlap_minutes')->nullable();
            $table->date('conflict_date');
            $table->string('severity')->default('warning'); // info, warning, critical
            $table->boolean('resolved')->default(false);
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('schedule1_id');
            $table->index('schedule2_id');
            $table->index(['conflict_date', 'severity']);
            $table->index(['resolved', 'created_at']);
            $table->index('conflict_type');
            
            // Foreign keys
            $table->foreign('schedule1_id')
                  ->references('id')
                  ->on('security_schedules')
                  ->onDelete('cascade');
                  
            $table->foreign('schedule2_id')
                  ->references('id')
                  ->on('security_schedules')
                  ->onDelete('cascade');
                  
            $table->foreign('resolved_by')
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
        Schema::dropIfExists('schedule_conflicts');
    }
};