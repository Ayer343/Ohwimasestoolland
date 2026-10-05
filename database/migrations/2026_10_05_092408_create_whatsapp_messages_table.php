<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('to')->nullable()->index();
            $table->string('from')->nullable();
            $table->text('message')->nullable();
            $table->string('message_type')->nullable();
            $table->string('template')->nullable();
            $table->string('media_url')->nullable();
            $table->text('caption')->nullable();
            $table->json('parameters')->nullable();
            $table->string('provider')->nullable();
            $table->string('message_id')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->boolean('is_incoming')->default(false)->index();
            $table->json('response')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('last_attempt')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};