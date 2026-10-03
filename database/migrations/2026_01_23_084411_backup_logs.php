<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('backup_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('backup_type', ['manual', 'auto', 'scheduled'])->default('manual');
            $table->string('filename')->nullable();
            $table->bigInteger('size')->default(0);
            $table->enum('status', ['started', 'completed', 'failed', 'cancelled'])->default('started');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration')->nullable()->comment('Duration in seconds');
            $table->text('error_message')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('cleaned_up')->default(false);
            $table->timestamp('cleaned_up_at')->nullable();
            $table->timestamps();
            
            $table->index('status');
            $table->index('created_at');
            $table->index('backup_type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('backup_logs');
    }
};