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
        Schema::create('shift_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('color_code', 7)->nullable();
            $table->time('typical_start_time')->nullable();
            $table->time('typical_end_time')->nullable();
            $table->decimal('typical_duration_hours', 5, 2)->nullable();
            $table->boolean('is_overnight')->default(false);
            $table->boolean('requires_special_training')->default(false);
            $table->integer('default_personnel_required')->default(1);
            $table->decimal('pay_rate_multiplier', 5, 2)->default(1.0);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->json('applicable_posts')->nullable(); // Post types that can use this category
            $table->json('restrictions')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['code', 'is_active']);
            $table->index('is_overnight');
            $table->index('typical_start_time');
            
            // Foreign keys
            $table->foreign('created_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_categories');
    }
};