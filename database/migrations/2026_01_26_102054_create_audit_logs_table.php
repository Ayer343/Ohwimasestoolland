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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 100);
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('loggable_type', 100)->nullable();
            $table->unsignedBigInteger('loggable_id')->nullable();
            $table->unsignedBigInteger('developer_setting_id')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['action', 'created_at']);
            $table->index(['loggable_type', 'loggable_id']);
            $table->index('developer_setting_id');
            $table->index('created_at');

            // Foreign key constraint
            $table->foreign('developer_setting_id')
                ->references('id')
                ->on('developer_settings')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};