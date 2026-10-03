<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('landlord_construction_registrations')->cascadeOnDelete();
            $table->text('content');
            $table->foreignId('user_id')->constrained('users');
            $table->boolean('is_internal')->default(true); // internal notes for admins only
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('registration_id');
            $table->index('user_id');
            $table->index('is_internal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_notes');
    }
};