<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVacancyRequestsTable extends Migration
{
    public function up()
    {
        Schema::create('vacancy_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('property_units')->onDelete('cascade');
            $table->foreignId('property_id')->constrained('properties')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('landlord_id')->constrained('users')->onDelete('cascade');
            $table->date('vacate_date');
            $table->text('vacate_reason');
            $table->string('forwarding_address')->nullable();
            $table->string('new_contact_number')->nullable();
            $table->boolean('has_lease_penalty')->default(false);
            $table->json('penalty_details')->nullable();
            $table->json('documents')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->text('landlord_notes')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->string('property_condition')->nullable();
            $table->boolean('cleaning_required')->default(false);
            $table->text('damages_noted')->nullable();
            $table->boolean('refund_deposit')->default(false);
            $table->decimal('deposit_refund_amount', 10, 2)->nullable();
            $table->text('deduction_reason')->nullable();
            $table->boolean('schedule_inspection')->default(false);
            $table->date('inspection_date')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['unit_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index(['landlord_id', 'status']);
            $table->index(['submitted_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('vacancy_requests');
    }
}