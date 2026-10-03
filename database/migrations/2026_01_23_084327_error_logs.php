<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('level', ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency']);
            $table->text('message');
            $table->string('file')->nullable();
            $table->integer('line')->nullable();
            $table->string('code')->nullable();
            $table->text('trace')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->text('url')->nullable();
            $table->string('method', 10)->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
            
            $table->index('level');
            $table->index('created_at');
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('error_logs');
    }
};