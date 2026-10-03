<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Create property_types table
        Schema::create('property_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Resident, School, Church, Hospital, Police Station, etc.
            $table->string('slug')->unique();
            $table->string('icon')->default('fas fa-home'); // Font Awesome icons
            $table->string('color')->default('#6b7280'); // Hex color for UI
            $table->boolean('is_active')->default(true);
            $table->boolean('is_custom')->default(false); // User-created vs system types
            $table->integer('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->softDeletes(); // For soft deletion
            $table->timestamps();

            // Indexes for better performance
            $table->index(['is_active', 'is_custom']);
            $table->index('sort_order');
            $table->index(['is_active', 'sort_order']);
        });

        // Update properties table to include property_type
        Schema::table('properties', function (Blueprint $table) {
            $table->foreignId('property_type_id')->nullable()->after('status')
                  ->constrained('property_types')->onDelete('set null');
            $table->string('custom_property_type')->nullable()->after('property_type_id');
            
            // Index for better query performance
            $table->index('property_type_id');
        });

        // Create a table for tracking property type usage statistics (optional but useful)
        Schema::create('property_type_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_type_id')->constrained()->onDelete('cascade');
            $table->integer('usage_count')->default(0);
            $table->date('tracking_date');
            $table->timestamps();

            $table->unique(['property_type_id', 'tracking_date']);
            $table->index('tracking_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('property_type_usage');
        
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['property_type_id']);
            $table->dropForeign(['property_type_id']);
            $table->dropColumn(['property_type_id', 'custom_property_type']);
        });
        
        Schema::dropIfExists('property_types');
    }
};