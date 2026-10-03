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
        Schema::create('user_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('activity_type', 50);
            $table->text('description');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('device_info')->nullable();
            $table->string('location')->nullable();
            $table->json('metadata')->nullable();
            $table->string('level', 20)->default('info');
            $table->string('scope', 20)->default('user');
            $table->foreignId('performed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('related_model')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['user_id', 'created_at']);
            $table->index('activity_type');
            $table->index('level');
            $table->index('scope');
            $table->index('is_system');
            $table->index('read_at');
            $table->index('archived_at');
            $table->index(['related_model', 'related_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_activities');
    }
};