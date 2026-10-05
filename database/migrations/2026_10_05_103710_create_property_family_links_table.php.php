<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_family_links', function (Blueprint $table) {
            $table->id();

            // The property this link belongs to
            $table->foreignId('property_id')
                  ->constrained('properties')
                  ->cascadeOnDelete();

            // The landlord who initiated the request
            $table->foreignId('landlord_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // The proposed / linked user (nullable until admin creates the account)
            $table->foreignId('linked_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // ── Proposed user data (captured before account exists) ──
            $table->string('proposed_name');
            $table->string('proposed_phone', 20)->nullable();
            $table->string('proposed_email')->nullable();
            $table->enum('relationship', [
                'spouse', 'son', 'daughter', 'parent',
                'sibling', 'relative', 'caretaker', 'other',
            ])->default('other');
            $table->string('relationship_other')->nullable();

            // ── Permissions granted on the property ──
            $table->json('permissions')->nullable();
            // e.g. ["view", "edit", "receive_notifications", "manage_tenants"]

            // ── Approval workflow ──
            $table->enum('status', [
                'pending', 'approved', 'rejected', 'revoked',
            ])->default('pending')->index();

            $table->text('landlord_notes')->nullable();
            $table->text('admin_notes')->nullable();

            $table->foreignId('reviewed_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            // ── Lifecycle timestamps ──
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // ── Constraints & indexes ──
            $table->index(['property_id', 'status']);
            $table->index(['landlord_id', 'status']);

            // A landlord cannot have two pending requests for the
            // same phone number on the same property
            $table->unique(
                ['property_id', 'proposed_phone', 'status'],
                'uniq_property_phone_status'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_family_links');
    }
};