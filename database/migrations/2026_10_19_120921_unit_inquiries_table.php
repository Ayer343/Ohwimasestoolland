<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_inquiries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Who the inquiry is about
            $table->foreignId('property_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('landlord_id')->nullable()->constrained('users')->nullOnDelete();

            // Who submitted it (guests are anonymous)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Guest contact details
            $table->string('name', 100);
            $table->string('phone', 20);
            $table->string('email', 150)->nullable();
            $table->text('message');

            // Delivery tracking
            $table->json('channels_requested')->nullable();   // ['sms','email','whatsapp']
            $table->json('channels_sent')->nullable();        // channels that actually went through
            $table->json('channels_failed')->nullable();      // channels that failed

            // Anti-spam / moderation
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('status', 20)->default('new');     // new, read, replied, closed, spam
            $table->timestamp('landlord_viewed_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['property_unit_id', 'created_at']);
            $table->index(['landlord_id', 'status']);
            $table->index('ip_address');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_inquiries');
    }
};