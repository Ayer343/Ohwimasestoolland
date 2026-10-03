<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_progress_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('construction_contracts')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('notes')->nullable();
            $table->string('photo_path')->nullable();
            $table->integer('progress_percentage')->default(0);
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('submitted');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('contract_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_progress_updates');
    }
};