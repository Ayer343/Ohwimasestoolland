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
        Schema::create('shared_billing_attachments', function (Blueprint $table) {
            $table->id();
            
            // FIXED: Use unsignedBigInteger for better control
            $table->unsignedBigInteger('shared_billing_id')
                ->comment('Reference to the shared billing');
            
            $table->unsignedBigInteger('uploaded_by')
                ->nullable()
                ->comment('User who uploaded the file');
            
            // File details
            $table->string('filename', 255)->comment('Original filename');
            $table->string('filepath', 500)->comment('Path to stored file');
            $table->string('filetype', 100)->comment('File MIME type');
            $table->integer('filesize')->comment('File size in bytes');
            
            // File info
            $table->string('title', 200)->nullable()->comment('Display title');
            $table->text('description')->nullable()->comment('File description');
            $table->enum('type', ['invoice', 'receipt', 'proof', 'contract', 'other'])
                ->default('other')
                ->comment('Type of attachment');
            
            // Access control
            $table->boolean('is_public')->default(false)->comment('Whether file is publicly accessible');
            $table->json('permissions')->nullable()->comment('JSON of user IDs who can access');
            
            // Status
            $table->boolean('is_active')->default(true)->comment('Whether file is active');
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['shared_billing_id', 'type']);
            $table->index(['uploaded_by', 'created_at']);
            $table->index('is_active');
            $table->index('type');
        });
        
        // FIXED: Add foreign keys AFTER table creation
        Schema::table('shared_billing_attachments', function (Blueprint $table) {
            // Check if referenced tables exist first
            if (Schema::hasTable('shared_super_admin_billings')) {
                $table->foreign('shared_billing_id')
                    ->references('id')
                    ->on('shared_super_admin_billings')
                    ->onDelete('cascade');
            }
            
            if (Schema::hasTable('users')) {
                $table->foreign('uploaded_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign keys first
        Schema::table('shared_billing_attachments', function (Blueprint $table) {
            $table->dropForeign(['shared_billing_id']);
            $table->dropForeign(['uploaded_by']);
        });
        
        Schema::dropIfExists('shared_billing_attachments');
    }
};