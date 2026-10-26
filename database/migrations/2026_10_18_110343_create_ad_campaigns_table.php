<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('property_unit_id');
            $table->unsignedBigInteger('landlord_id');
            $table->unsignedBigInteger('ad_pricing_plan_id')->nullable();

            // Snapshot of the plan at purchase time (grandfathering)
            $table->string('plan_name');
            $table->enum('billing_period', ['monthly', 'yearly']);
            $table->unsignedInteger('duration_months');
            $table->decimal('unit_price_snapshot', 12, 2);
            $table->decimal('total_paid', 12, 2)->default(0);
            $table->string('currency', 3)->default('GHS');

            // Lifecycle
            $table->enum('status', [
                'draft',
                'pending_payment',
                'active',
                'paused',
                'rented_early',
                'expired',
                'cancelled',
                'rejected',
            ])->default('draft');

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();

            // Admin review for "mark as rented"
            $table->timestamp('rented_requested_at')->nullable();
            $table->unsignedBigInteger('rented_reviewed_by')->nullable();
            $table->timestamp('rented_approved_at')->nullable();
            $table->string('rented_proof_path')->nullable(); // optional uploaded proof

            // Channels
            $table->json('channels')->nullable(); // ['homepage','facebook','whatsapp']

            // Display / tracking
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('clicks_count')->default(0);
            $table->unsignedInteger('sort_weight')->default(0);

            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('property_unit_id')->references('id')->on('property_units')->onDelete('cascade');
            $table->foreign('landlord_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('ad_pricing_plan_id')->references('id')->on('ad_pricing_plans')->onDelete('set null');

            $table->index(['status', 'ends_at']);
            $table->index(['landlord_id', 'status']);
            $table->index(['property_unit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_campaigns');
    }
};