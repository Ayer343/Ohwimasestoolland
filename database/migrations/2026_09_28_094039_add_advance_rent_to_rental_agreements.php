<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_agreements', function (Blueprint $table) {
            // ========== ✅ GHANA: Advance rent columns ==========
            if (!Schema::hasColumn('rental_agreements', 'advance_rent_months')) {
                $table->unsignedTinyInteger('advance_rent_months')->default(1)->after('monthly_rent');
            }
            if (!Schema::hasColumn('rental_agreements', 'advance_rent_amount')) {
                $table->decimal('advance_rent_amount', 14, 2)->default(0)->after('advance_rent_months');
            }
            if (!Schema::hasColumn('rental_agreements', 'advance_rent_paid_at')) {
                $table->timestamp('advance_rent_paid_at')->nullable()->after('advance_rent_amount');
            }
            if (!Schema::hasColumn('rental_agreements', 'advance_rent_period_start')) {
                $table->date('advance_rent_period_start')->nullable()->after('advance_rent_paid_at');
            }
            if (!Schema::hasColumn('rental_agreements', 'advance_rent_period_end')) {
                $table->date('advance_rent_period_end')->nullable()->after('advance_rent_period_start');
            }

            // ========== Payment frequency & monthly phase ==========
            if (!Schema::hasColumn('rental_agreements', 'payment_frequency')) {
                $table->enum('payment_frequency', ['monthly', 'advance_only'])
                      ->default('monthly')->after('advance_rent_period_end');
            }
            if (!Schema::hasColumn('rental_agreements', 'first_monthly_payment_date')) {
                $table->date('first_monthly_payment_date')->nullable()->after('payment_frequency');
            }

            // ========== Compliance ==========
            if (!Schema::hasColumn('rental_agreements', 'advance_rent_compliance_status')) {
                $table->enum('advance_rent_compliance_status', ['compliant', 'exceeds_legal_limit'])
                      ->default('compliant')->after('first_monthly_payment_date');
            }
            if (!Schema::hasColumn('rental_agreements', 'advance_rent_acknowledged_at')) {
                $table->timestamp('advance_rent_acknowledged_at')->nullable()
                      ->after('advance_rent_compliance_status');
            }

            // ========== Lease type ==========
            if (!Schema::hasColumn('rental_agreements', 'lease_type')) {
                $table->enum('lease_type', ['fixed', 'month_to_month'])
                      ->default('fixed')->after('duration_months');
            }

            // ========== Deposit refund tracking ==========
            if (!Schema::hasColumn('rental_agreements', 'deposit_refunded_amount')) {
                $table->decimal('deposit_refunded_amount', 14, 2)->default(0)->after('deposit_fully_paid_at');
            }
            if (!Schema::hasColumn('rental_agreements', 'deposit_refunded_at')) {
                $table->timestamp('deposit_refunded_at')->nullable()->after('deposit_refunded_amount');
            }

            // ========== Termination data JSON ==========
            if (!Schema::hasColumn('rental_agreements', 'termination_data')) {
                $table->json('termination_data')->nullable()->after('terminated_by');
            }

            // ========== Renewal links ==========
            if (!Schema::hasColumn('rental_agreements', 'previous_lease_id')) {
                $table->foreignId('previous_lease_id')->nullable()
                      ->after('notes')
                      ->constrained('rental_agreements')
                      ->nullOnDelete();
            }
            if (!Schema::hasColumn('rental_agreements', 'previous_agreement_id')) {
                $table->foreignId('previous_agreement_id')->nullable()
                      ->after('previous_lease_id')
                      ->constrained('rental_agreements')
                      ->nullOnDelete();
            }
        });

        // ========== ✅ FIX: INDEXES (short explicit names) ==========
        // Indexes are added in a separate Schema::table call so they can be
        // skipped individually if they already exist (MySQL 8 has no
        // "IF NOT EXISTS" for indexes on older versions, so we wrap in try/catch).
        Schema::table('rental_agreements', function (Blueprint $table) {
            // ✅ Use short explicit names — MySQL identifier limit is 64 chars.
            $this->addIndexSafely($table, ['payment_frequency', 'first_monthly_payment_date'], 'ra_payfreq_fmpd_idx');
            $this->addIndexSafely($table, ['advance_rent_period_end'], 'ra_adv_period_end_idx');
            $this->addIndexSafely($table, ['advance_rent_compliance_status'], 'ra_adv_compliance_idx');
            $this->addIndexSafely($table, ['advance_rent_months'], 'ra_adv_months_idx');
            $this->addIndexSafely($table, ['lease_type'], 'ra_lease_type_idx');
        });
    }

    /**
     * Add an index, skipping if it already exists.
     */
    protected function addIndexSafely(Blueprint $table, array $columns, string $indexName): void
    {
        try {
            $table->index($columns, $indexName);
        } catch (\Throwable $e) {
            // Index already exists or name collision — safe to ignore
        }
    }

    public function down(): void
    {
        // Drop indexes first
        Schema::table('rental_agreements', function (Blueprint $table) {
            $this->dropIndexSafely($table, 'ra_payfreq_fmpd_idx');
            $this->dropIndexSafely($table, 'ra_adv_period_end_idx');
            $this->dropIndexSafely($table, 'ra_adv_compliance_idx');
            $this->dropIndexSafely($table, 'ra_adv_months_idx');
            $this->dropIndexSafely($table, 'ra_lease_type_idx');
        });

        // Drop foreign keys before columns
        Schema::table('rental_agreements', function (Blueprint $table) {
            if (Schema::hasColumn('rental_agreements', 'previous_lease_id')) {
                $table->dropForeign(['previous_lease_id']);
            }
            if (Schema::hasColumn('rental_agreements', 'previous_agreement_id')) {
                $table->dropForeign(['previous_agreement_id']);
            }
        });

        // Drop columns
        Schema::table('rental_agreements', function (Blueprint $table) {
            $columns = [
                'advance_rent_months',
                'advance_rent_amount',
                'advance_rent_paid_at',
                'advance_rent_period_start',
                'advance_rent_period_end',
                'payment_frequency',
                'first_monthly_payment_date',
                'advance_rent_compliance_status',
                'advance_rent_acknowledged_at',
                'lease_type',
                'deposit_refunded_amount',
                'deposit_refunded_at',
                'termination_data',
                'previous_lease_id',
                'previous_agreement_id',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('rental_agreements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Drop an index, ignoring errors if it doesn't exist.
     */
    protected function dropIndexSafely(Blueprint $table, string $indexName): void
    {
        try {
            $table->dropIndex($indexName);
        } catch (\Throwable $e) {
            // Index didn't exist — safe to ignore
        }
    }
};