<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->datetime('scheduled_start');
            $table->datetime('scheduled_end');
            $table->enum('status', ['pending', 'approved', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->integer('progress')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->json('affected_modules')->nullable();
            $table->boolean('notifications_sent')->default(false);
            $table->softDeletes();
            $table->timestamps();
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->index('status');
            $table->index(['scheduled_start', 'scheduled_end']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('maintenance_schedules');
    }
};