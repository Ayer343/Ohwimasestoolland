<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_pricing_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->enum('billing_period', ['monthly', 'yearly']);
            $table->unsignedInteger('duration_months'); // 1 for monthly, 12 for yearly
            $table->decimal('price', 12, 2);
            $table->string('currency', 3)->default('GHS');
            $table->decimal('discount_percent', 5, 2)->default(0); // display only
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'billing_period']);
            $table->index('effective_from');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_pricing_plans');
    }
};