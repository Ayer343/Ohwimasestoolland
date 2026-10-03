<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{
    Schema::create('rentals', function (Blueprint $table) {
        $table->id();
        $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
        $table->foreignId('landlord_id')->constrained('users')->onDelete('cascade'); // ADDED
        $table->foreignId('property_id')->constrained()->onDelete('cascade');
        $table->foreignId('unit_id')->constrained('property_units')->onDelete('cascade');
        $table->date('start_date');
        $table->date('end_date');
        $table->decimal('monthly_rent', 10, 2);
        $table->enum('status', ['active', 'pending', 'completed', 'cancelled'])->default('pending');
        $table->json('agreement_details')->nullable();
        $table->timestamps();
        $table->softDeletes();
        
        // Indexes for performance
        $table->index('tenant_id');
        $table->index('landlord_id'); // ADDED
        $table->index('property_id');
        $table->index('unit_id');
        $table->index('status');
        $table->index(['start_date', 'end_date']);
    });
}

    public function down()
    {
        Schema::dropIfExists('rentals');
    }
};