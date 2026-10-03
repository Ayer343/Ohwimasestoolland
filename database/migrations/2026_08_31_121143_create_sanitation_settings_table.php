<?php
// database/migrations/2024_01_XX_create_sanitation_settings_table.php

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
        Schema::create('sanitation_settings', function (Blueprint $table) {
            $table->id();
            
            // Company Details
            $table->string('company_name');
            $table->string('company_short_name')->nullable();
            $table->string('company_email')->nullable();
            $table->string('company_phone')->nullable();
            $table->text('company_address')->nullable();
            $table->string('company_logo')->nullable();
            
            // Registration Details
            $table->string('registration_number')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('license_number')->nullable();
            
            // Contact Persons
            $table->string('contact_person_name')->nullable();
            $table->string('contact_person_phone')->nullable();
            $table->string('contact_person_email')->nullable();
            
            // Operations
            $table->time('operational_start_time')->nullable();
            $table->time('operational_end_time')->nullable();
            $table->json('operational_days')->nullable(); // ['monday', 'tuesday', ...]
            
            // Service Areas
            $table->json('service_areas')->nullable(); // ['zone1', 'zone2', ...]
            
            // Collection Settings
            $table->json('default_collection_frequencies')->nullable(); // ['daily', 'weekly', ...]
            $table->json('default_waste_types')->nullable(); // ['general', 'recyclable', ...]
            $table->json('default_collection_days')->nullable(); // ['Monday', 'Tuesday', ...]
            
            // Pricing & Fees
            $table->decimal('default_collection_fee', 10, 2)->nullable();
            $table->decimal('emergency_collection_fee', 10, 2)->nullable();
            $table->decimal('late_fee_percentage', 5, 2)->nullable();
            
            // Vehicle Fleet
            $table->json('vehicle_types')->nullable(); // ['truck', 'van', 'compactor', ...]
            $table->integer('default_worker_count_per_vehicle')->default(2);
            
            // Notification Settings
            $table->json('notification_preferences')->nullable();
            $table->json('reminder_settings')->nullable();
            
            // Reporting
            $table->string('default_report_timezone')->nullable();
            $table->string('date_format')->nullable();
            $table->string('time_format')->nullable();
            
            // Branding
            $table->string('primary_color')->nullable();
            $table->string('secondary_color')->nullable();
            $table->text('company_description')->nullable();
            $table->string('website_url')->nullable();
            
            // Social Media
            $table->string('facebook_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('linkedin_url')->nullable();
            
            // Emergency Contacts
            $table->json('emergency_contacts')->nullable();
            
            // System
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->softDeletes();
            $table->timestamps();
            
            // Indexes
            $table->index('company_name');
            $table->index('registration_number');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sanitation_settings');
    }
};