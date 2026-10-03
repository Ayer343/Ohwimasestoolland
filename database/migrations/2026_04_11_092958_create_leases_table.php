<?php
// database/migrations/xxxx_xx_xx_create_leases_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeasesTable extends Migration
{
    public function up()
    {
        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('property_unit_id');
            $table->unsignedBigInteger('tenant_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('rent_amount', 15, 2);
            $table->decimal('security_deposit', 15, 2)->nullable();
            $table->string('payment_frequency')->default('monthly');
            $table->integer('payment_due_day')->default(1);
            $table->string('status')->default('draft');
            $table->string('document_path')->nullable();
            $table->boolean('signed_by_landlord')->default(false);
            $table->boolean('signed_by_tenant')->default(false);
            $table->timestamp('landlord_signed_at')->nullable();
            $table->timestamp('tenant_signed_at')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->text('special_clauses')->nullable();
            $table->boolean('renewal_option')->default(false);
            $table->integer('notice_period_days')->default(30);
            $table->decimal('late_fee_amount', 15, 2)->nullable();
            $table->decimal('late_fee_percentage', 5, 2)->nullable();
            $table->integer('grace_period_days')->default(5);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('property_unit_id')->references('id')->on('property_units')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            
            $table->index(['property_unit_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('leases');
    }
}