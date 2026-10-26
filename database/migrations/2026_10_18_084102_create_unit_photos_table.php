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
        Schema::create('unit_photos', function (Blueprint $table) {
            $table->id();

            // The unit this photo belongs to. Cascade-delete rows when the
            // unit is hard-deleted (soft-delete is handled in the model).
            $table->foreignId('property_unit_id')
                ->constrained('property_units')
                ->cascadeOnDelete();

            // Who uploaded it — kept for audit + family-member attribution.
            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->restrictOnDelete();

            // Storage location: which disk (public / s3 / media) and path.
            $table->string('disk', 32)->default('public');
            $table->string('path');
            $table->string('thumbnail_path')->nullable();

            // File metadata.
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();

            // Ordering + cover flag.
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_cover')->default(false);

            // Optional caption for accessibility / context.
            $table->string('caption', 255)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes tuned for the two hot queries:
            //   1. "All photos for a unit, ordered by cover + sort"
            //   2. "Find the cover photo for a unit" (dashboard lists)
            $table->index(['property_unit_id', 'is_cover', 'sort_order'], 'unit_photos_order_idx');
            $table->index(['property_unit_id', 'deleted_at'], 'unit_photos_unit_deleted_idx');

            // Enforce at most one cover per unit at the DB level would need a
            // partial unique index — not portable across MySQL/SQLite/Postgres
            // via the schema builder. We enforce it in the application layer
            // (setCoverPhoto() and deletePhoto() both maintain the invariant).
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_photos');
    }
};