<?php
// database/migrations/2026_08_29_000001_create_collection_zones_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('region')->nullable();
            $table->string('district')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->json('boundary_coordinates')->nullable();
            $table->string('status')->default('active');
            $table->integer('priority')->default(1);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            
            $table->index('status');
            $table->index('code');
            $table->index('region');
            $table->index('district');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_zones');
    }
};