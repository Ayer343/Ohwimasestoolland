<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('property_tenant', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('property_id');
            $table->unsignedBigInteger('user_id'); // Changed from 'tenant_id' to 'user_id'
            $table->unsignedBigInteger('added_by');
            $table->timestamp('added_at')->useCurrent();
            $table->text('notes')->nullable(); // Additional notes about this tenant-property relationship
            $table->enum('status', ['active', 'inactive', 'pending', 'terminated'])->default('active');
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Foreign key constraints - IMPORTANT: user_id references 'users' table, not 'tenants'
            $table->foreign('property_id')->references('id')->on('properties')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade'); // Changed to 'users'
            $table->foreign('added_by')->references('id')->on('users')->onDelete('cascade');

            // Unique constraint to prevent duplicate tenant-property relationships
            $table->unique(['property_id', 'user_id']); // Changed to 'user_id'

            // Indexes for performance
            $table->index('property_id');
            $table->index('user_id'); // Changed from 'tenant_id'
            $table->index('added_by');
            $table->index('status');
            $table->index(['start_date', 'end_date']);
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('property_tenant');
    }
};