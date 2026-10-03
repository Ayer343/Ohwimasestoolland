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
        Schema::create('construction_workers', function (Blueprint $table) {
            $table->id();
            
            // Foreign key to construction contract
            $table->foreignId('contract_id')
                ->constrained('construction_contracts')
                ->onDelete('cascade');
            
            // Personal Information
            $table->string('full_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('id_number')->nullable();
            
            // Job Details
            $table->string('job_title')->nullable();
            $table->string('trade')->nullable();
            $table->string('specialization')->nullable();
            $table->json('skills')->nullable();
            $table->text('service_description')->nullable();
            
            // Financial
            $table->decimal('daily_rate', 10, 2)->nullable();
            $table->decimal('contract_rate', 10, 2)->nullable();
            
            // Documents
            $table->json('documents')->nullable();
            
            // Status
            $table->enum('status', ['active', 'inactive', 'completed', 'terminated'])
                ->default('active');
            
            // Dates
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            
            // Notes
            $table->text('notes')->nullable();
            
            // Who added this worker
            $table->foreignId('added_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index('status');
            $table->index('trade');
            $table->index(['contract_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('construction_workers');
    }
};