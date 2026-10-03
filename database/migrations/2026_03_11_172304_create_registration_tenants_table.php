<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('landlord_construction_registrations')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users'); // if they become a system user
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('registration_id');
            $table->index('status');
            $table->index('phone');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_tenants');
    }
};