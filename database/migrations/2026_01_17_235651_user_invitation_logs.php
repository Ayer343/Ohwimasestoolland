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
        Schema::create('user_invitation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_invitation_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('action'); // invitation_created, email_sent, sms_sent, invitation_accepted, etc.
            $table->string('channel')->nullable(); // email, sms, whatsapp, system
            $table->string('token')->nullable();
            $table->string('status')->default('pending'); // success, failed, pending
            $table->text('message')->nullable();
            $table->json('data')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            // Indexes
            $table->index(['user_invitation_id', 'action']);
            $table->index(['user_id', 'created_at']);
            $table->index('channel');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_invitation_logs');
    }
};