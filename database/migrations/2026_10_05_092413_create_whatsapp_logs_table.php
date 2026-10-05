<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->nullable()->index();
            $table->string('to')->nullable()->index();
            $table->string('from')->nullable();
            $table->text('message')->nullable();
            $table->string('type')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('message_id')->nullable()->index();
            $table->json('response')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_logs');
    }
};