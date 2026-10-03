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
        Schema::create('property_unit_invoices', function (Blueprint $table) {
            $table->id();

            // ========== RELATIONSHIPS ==========
            $table->foreignId('lease_id')
                  ->constrained('rental_agreements')
                  ->cascadeOnDelete();

            $table->foreignId('unit_id')
                  ->constrained('property_units')
                  ->cascadeOnDelete();

            $table->foreignId('property_id')
                  ->constrained('properties')
                  ->cascadeOnDelete();

            $table->foreignId('tenant_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->foreignId('landlord_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // ========== IDENTIFICATION ==========
            $table->string('invoice_type', 40);   // advance_rent, monthly_rent, security_deposit, utility_deposit, late_fee, early_termination, other
            $table->string('reference', 40)->nullable()->unique();
            $table->text('description')->nullable();

            // ========== AMOUNTS ==========
            $table->decimal('amount', 14, 2);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('balance', 14, 2)->storedAs('amount - amount_paid'); // computed column (MySQL 5.7+/MariaDB 10.2+/Postgres 12+)

            // ========== DATES ==========
            $table->date('due_date')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->date('issue_date')->nullable();     // when the invoice was issued
            $table->timestamp('paid_at')->nullable();   // when fully paid

            // ========== STATUS ==========
            $table->string('status', 20)->default('pending'); // pending, partial, paid, overdue, void

            // ========== VOID TRACKING ==========
            $table->string('void_reason')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // ========== PAYMENT METHOD / REFERENCE (for audit) ==========
            $table->string('payment_method', 30)->nullable();       // cash, bank_transfer, card, cheque, mobile_money
            $table->string('payment_reference', 100)->nullable();   // bank ref, cheque no., momo txn id
            $table->timestamp('last_payment_at')->nullable();

            // ========== ✅ GHANA: ADVANCE RENT CONTEXT ==========
            // Denormalized snapshot so we can query invoices without joining leases
            $table->unsignedTinyInteger('advance_rent_months')->nullable();
            $table->boolean('is_advance_rent')->default(false);     // quick filter flag

            // ========== OPTIONAL META ==========
            $table->json('metadata')->nullable();   // arbitrary tags, batch info, etc.
            $table->text('notes')->nullable();

            // ========== TIMESTAMPS / SOFT DELETES ==========
            $table->timestamps();
            $table->softDeletes();

            // ========== INDEXES ==========
            // Primary query paths
            $table->index(['lease_id', 'status'], 'pui_lease_status_idx');
            $table->index(['unit_id', 'status'], 'pui_unit_status_idx');
            $table->index(['tenant_id', 'status'], 'pui_tenant_status_idx');
            $table->index(['landlord_id', 'status'], 'pui_landlord_status_idx');
            $table->index(['property_id', 'status'], 'pui_property_status_idx');

            // Date-based reporting
            $table->index(['due_date', 'status'], 'pui_due_status_idx');
            $table->index(['period_start', 'period_end'], 'pui_period_idx');

            // Type-based reporting
            $table->index('invoice_type', 'pui_type_idx');
            $table->index(['invoice_type', 'status'], 'pui_type_status_idx');

            // Advance rent filtering
            $table->index('is_advance_rent', 'pui_is_advance_idx');

            // Outstanding balance queries (partial index on MySQL/Postgres via WHERE clause is not portable,
            // so we index by status only — the app filters amount > amount_paid)
            $table->index(['status', 'due_date'], 'pui_status_due_idx');

            // Soft deletes
            $table->index('deleted_at', 'pui_deleted_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_unit_invoices');
    }
};