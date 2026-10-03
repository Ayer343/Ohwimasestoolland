<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('api_logs')) {
            Schema::create('api_logs', function (Blueprint $table) {
                $table->id();
                $table->string('method', 10);
                $table->string('endpoint');
                $table->integer('status_code')->nullable();
                $table->float('response_time')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->json('request_data')->nullable();
                $table->json('response_data')->nullable();
                $table->timestamps();
                
                $table->index('user_id');
                $table->index('method');
                $table->index('status_code');
                $table->index('created_at');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('api_logs');
    }
};