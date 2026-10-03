<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('archived_construction_registrations', function (Blueprint $table) {
            $table->id();
            
            // Original registration data (all columns from main table)
            $table->string('registration_type');
            $table->string('purpose')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('primary_phone');
            $table->json('additional_phones')->nullable();
            $table->foreignId('landlord_id')->nullable();
            $table->string('property_name')->nullable();
            $table->string('plot_number')->nullable();
            $table->string('street_name')->nullable();
            $table->string('digital_address')->nullable();
            $table->text('land_description')->nullable();
            $table->string('land_ownership_document')->nullable();
            $table->string('zone')->nullable();
            $table->string('section')->nullable();
            
            // Construction fields
            $table->string('property_type')->nullable();
            $table->string('custom_property_type')->nullable();
            $table->string('property_status')->nullable();
            $table->integer('estimated_bedrooms')->nullable();
            $table->boolean('has_plans')->default(false);
            $table->date('estimated_completion')->nullable();
            $table->json('construction_documents')->nullable();
            
            // Property capture fields
            $table->string('existing_property_type')->nullable();
            $table->string('existing_custom_property_type')->nullable();
            $table->string('existing_property_status')->nullable();
            $table->integer('existing_bedrooms')->nullable();
            $table->integer('existing_bathrooms')->nullable();
            $table->integer('year_built')->nullable();
            $table->json('property_photos')->nullable();
            $table->json('property_documents')->nullable();
            
            // Tenant data
            $table->boolean('has_tenants')->default(false);
            $table->integer('tenant_count')->default(0);
            $table->json('tenant_data')->nullable();
            
            // Status and tracking
            $table->string('status');
            $table->string('access_token')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable();
            $table->foreignId('approved_property_id')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('info_requested')->nullable();
            $table->foreignId('assigned_to')->nullable();
            $table->timestamp('assigned_at')->nullable();
            
            // Archive metadata
            $table->timestamp('archived_at');
            $table->integer('archive_year');
            $table->text('archive_reason')->nullable();
            $table->foreignId('archived_by')->nullable();
            $table->integer('original_id'); // Reference to original record ID
            
            // Original timestamps
            $table->timestamp('original_created_at');
            $table->timestamp('original_updated_at');
            $table->timestamp('original_deleted_at')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index('archive_year');
            $table->index('status');
            $table->index('registration_type');
            $table->index('original_id');
            $table->index('archived_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('archived_construction_registrations');
    }
};