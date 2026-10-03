<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('color')->default('#6c757d');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('default_priority')->nullable()->comment('Default priority for this category');
            $table->json('default_estimated_duration')->nullable()->comment('Default estimated duration in minutes');
            $table->json('default_estimated_cost_range')->nullable()->comment('Default cost range [min, max]');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_categories');
    }
};