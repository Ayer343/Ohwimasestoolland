<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_registration_activity_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('landlord_construction_registrations')->cascadeOnDelete();
            $table->string('action'); // created, assigned, reviewed, approved, rejected, etc.
            $table->text('description');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->timestamps();
            
            $table->index('registration_id');
            $table->index('action');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_activity_logs');
    }
};