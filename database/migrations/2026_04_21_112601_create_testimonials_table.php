<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            
            // User relationship (for authenticated users who submit testimonials)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Basic information
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('role')->nullable(); // Property Owner, Landlord, Tenant, etc.
            
            // Testimonial content
            $table->text('content');
            $table->integer('rating')->default(5); // 1-5 stars
            
            // Media
            $table->string('avatar')->nullable(); // Optional profile picture
            
            // Location info
            $table->string('property_location')->nullable(); // Optional property location
            
            // Status flags
            $table->boolean('is_approved')->default(false); // Admin approval required
            $table->boolean('is_featured')->default(false); // Featured testimonials
            $table->integer('display_order')->default(0);
            
            // Approval tracking
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Additional data
            $table->json('metadata')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index('is_approved');
            $table->index('is_featured');
            $table->index('rating');
            $table->index('display_order');
            $table->index('user_id'); // Index for user relationship
            $table->index(['is_approved', 'is_featured']); // Composite index for common queries
            $table->index(['is_approved', 'rating']); // For sorting by rating
            $table->index(['is_approved', 'created_at']); // For sorting by date
        });
    }

    public function down()
    {
        Schema::dropIfExists('testimonials');
    }
};