<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 32)->unique();
            $table->unsignedBigInteger('ad_campaign_id');
            $table->unsignedBigInteger('landlord_id');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('GHS');
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded', 'cancelled'])->default('pending');
            $table->string('gateway', 40)->nullable();
            $table->string('gateway_reference', 120)->nullable();
            $table->json('gateway_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('ad_campaign_id')->references('id')->on('ad_campaigns')->onDelete('cascade');
            $table->foreign('landlord_id')->references('id')->on('users')->onDelete('cascade');

            $table->index(['status', 'due_at']);
            $table->index('landlord_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_invoices');
    }
};