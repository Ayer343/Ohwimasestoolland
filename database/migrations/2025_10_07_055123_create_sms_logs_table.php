<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSmsLogsTable extends Migration
{
    public function up()
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // arkesel, twilio, etc.
            $table->string('phone_number');
            $table->text('message');
            $table->enum('status', ['success', 'failed']);
            $table->json('response')->nullable();
            $table->string('message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['provider', 'status']);
            $table->index('created_at');
            $table->index('phone_number');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sms_logs');
    }
}