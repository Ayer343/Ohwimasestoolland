<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('path');
            $table->string('type'); // id_proof, proof_of_income, etc.
            $table->integer('size')->default(0); // in bytes
            $table->text('description')->nullable();
            
            // User who owns the document
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            
            // Polymorphic relationship
            $table->nullableMorphs('documentable');
            
            // Upload information
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('status')->default('pending'); // pending, approved, rejected, archived
            
            // Metadata (JSON field for additional information)
            $table->json('metadata')->nullable();
            
            $table->softDeletes();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'status']);
            $table->index(['documentable_id', 'documentable_type']);
            $table->index(['type', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('documents');
    }
};