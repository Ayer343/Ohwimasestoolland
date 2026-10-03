<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landlord_phones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->onDelete('cascade');
            
            // Phone number fields optimized for SMS delivery
            $table->string('phone_number', 20); // Original input: +233595652410
            $table->string('phone_e164', 20)->nullable(); // Standardized: +233595652410
            $table->string('phone_national', 15)->nullable(); // Local format: 0595652410
            $table->string('phone_dialing', 5)->default('+233'); // Country code
            $table->string('phone_subscriber', 15); // Subscriber number: 595652410
            
            // SMS-specific fields
            $table->boolean('is_sms_capable')->default(true);
            $table->boolean('is_whatsapp_capable')->default(true);
            $table->enum('carrier_type', ['mobile', 'voip', 'landline', 'unknown'])->default('mobile');
            $table->timestamp('last_sms_delivery')->nullable();
            $table->timestamp('last_sms_failure')->nullable();
            $table->integer('sms_delivery_count')->default(0);
            $table->integer('sms_failure_count')->default(0);
            
            // Status fields
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_token')->nullable();
            
            $table->softDeletes();
            $table->timestamps();
            
            // Indexes optimized for SMS lookups
            $table->unique(['landlord_id', 'phone_e164']);
            $table->index(['phone_e164']); // Fast lookup for SMS sending
            $table->index(['phone_national']); // Fast lookup by local format
            $table->index(['landlord_id', 'is_primary']);
            $table->index(['is_sms_capable', 'carrier_type']); // For bulk SMS
            $table->index(['last_sms_failure']); // For retry logic
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landlord_phones');
    }
};