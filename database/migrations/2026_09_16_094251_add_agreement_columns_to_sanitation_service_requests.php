<?php
// database/migrations/2026_09_16_000002_add_agreement_columns_to_sanitation_service_requests.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the landlord-collection-agreement snapshot to the
     * sanitation_service_requests table. These columns are populated
     * when a landlord submits a service request after accepting the
     * terms and choosing a frequency.
     *
     *   collection_frequency       — the tier the landlord chose
     *   quoted_monthly_fee         — the monthly price at the time of acceptance
     *   quoted_emergency_fee       — the emergency fee at the time of acceptance
     *   quoted_currency            — ISO 4217 code (defaults to GHS)
     *   agreement_accepted_at      — timestamp the checkbox was ticked
     *   agreement_accepted_ip      — requester IP, for audit
     *   agreement_terms_snapshot   — a JSON capture of the terms the
     *                                landlord saw and agreed to
     */
    public function up(): void
    {
        Schema::table('sanitation_service_requests', function (Blueprint $table) {

            if (!Schema::hasColumn('sanitation_service_requests', 'collection_frequency')) {
                $table->string('collection_frequency', 20)
                    ->nullable()
                    ->after('priority');
            }

            if (!Schema::hasColumn('sanitation_service_requests', 'quoted_monthly_fee')) {
                $table->decimal('quoted_monthly_fee', 10, 2)
                    ->nullable()
                    ->after('collection_frequency');
            }

            if (!Schema::hasColumn('sanitation_service_requests', 'quoted_emergency_fee')) {
                $table->decimal('quoted_emergency_fee', 10, 2)
                    ->nullable()
                    ->after('quoted_monthly_fee');
            }

            if (!Schema::hasColumn('sanitation_service_requests', 'quoted_currency')) {
                $table->string('quoted_currency', 3)
                    ->default('GHS')
                    ->after('quoted_emergency_fee');
            }

            if (!Schema::hasColumn('sanitation_service_requests', 'agreement_accepted_at')) {
                $table->timestamp('agreement_accepted_at')
                    ->nullable()
                    ->after('quoted_currency');
            }

            if (!Schema::hasColumn('sanitation_service_requests', 'agreement_accepted_ip')) {
                $table->string('agreement_accepted_ip', 45)
                    ->nullable()
                    ->after('agreement_accepted_at');
            }

            if (!Schema::hasColumn('sanitation_service_requests', 'agreement_terms_snapshot')) {
                $table->json('agreement_terms_snapshot')
                    ->nullable()
                    ->after('agreement_accepted_ip');
            }

            // Composite index for reporting: "which properties agreed to what tier?"
            if (!$this->hasIndex('sanitation_service_requests', 'sanitation_service_requests_frequency_status_index')) {
                $table->index(['collection_frequency', 'status'], 'sanitation_service_requests_frequency_status_index');
            }
        });

        // ============================================================== //
        // Column comments — MySQL / MariaDB                              //
        // ============================================================== //
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sanitation_service_requests
                MODIFY collection_frequency VARCHAR(20) NULL
                COMMENT 'Landlord-chosen collection frequency: daily | weekly | biweekly | monthly'");

            DB::statement("ALTER TABLE sanitation_service_requests
                MODIFY quoted_monthly_fee DECIMAL(10,2) NULL
                COMMENT 'Monthly fee quoted at the moment the landlord accepted the terms'");

            DB::statement("ALTER TABLE sanitation_service_requests
                MODIFY quoted_emergency_fee DECIMAL(10,2) NULL
                COMMENT 'Emergency pick-up fee quoted at the moment of acceptance'");

            DB::statement("ALTER TABLE sanitation_service_requests
                MODIFY quoted_currency CHAR(3) NOT NULL DEFAULT 'GHS'
                COMMENT 'ISO 4217 currency code for the quoted fees'");

            DB::statement("ALTER TABLE sanitation_service_requests
                MODIFY agreement_accepted_at TIMESTAMP NULL
                COMMENT 'When the landlord accepted the terms and pricing'");

            DB::statement("ALTER TABLE sanitation_service_requests
                MODIFY agreement_accepted_ip VARCHAR(45) NULL
                COMMENT 'Requester IP (IPv4 or IPv6) for audit trail'");

            DB::statement("ALTER TABLE sanitation_service_requests
                MODIFY agreement_terms_snapshot JSON NULL
                COMMENT 'Snapshot of the terms the landlord agreed to (company name, late fee %, accepted_at, etc.)'");
        }

        // ============================================================== //
        // Column comments — PostgreSQL                                   //
        // ============================================================== //
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("COMMENT ON COLUMN sanitation_service_requests.collection_frequency IS 'Landlord-chosen collection frequency: daily | weekly | biweekly | monthly'");
            DB::statement("COMMENT ON COLUMN sanitation_service_requests.quoted_monthly_fee IS 'Monthly fee quoted at the moment the landlord accepted the terms'");
            DB::statement("COMMENT ON COLUMN sanitation_service_requests.quoted_emergency_fee IS 'Emergency pick-up fee quoted at the moment of acceptance'");
            DB::statement("COMMENT ON COLUMN sanitation_service_requests.quoted_currency IS 'ISO 4217 currency code for the quoted fees'");
            DB::statement("COMMENT ON COLUMN sanitation_service_requests.agreement_accepted_at IS 'When the landlord accepted the terms and pricing'");
            DB::statement("COMMENT ON COLUMN sanitation_service_requests.agreement_accepted_ip IS 'Requester IP (IPv4 or IPv6) for audit trail'");
            DB::statement("COMMENT ON COLUMN sanitation_service_requests.agreement_terms_snapshot IS 'Snapshot of the terms the landlord agreed to'");

            // Postgres-only CHECK constraint: frequency must be one of the four valid values
            DB::statement("ALTER TABLE sanitation_service_requests
                ADD CONSTRAINT chk_sanitation_service_requests_frequency
                CHECK (
                    collection_frequency IS NULL
                    OR collection_frequency IN ('daily', 'weekly', 'biweekly', 'monthly')
                )");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop Postgres CHECK constraint first (dropping the column would
        // drop it implicitly, but we're explicit for clarity / partial rollbacks).
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE sanitation_service_requests
                DROP CONSTRAINT IF EXISTS chk_sanitation_service_requests_frequency");
        }

        Schema::table('sanitation_service_requests', function (Blueprint $table) {
            if ($this->hasIndex('sanitation_service_requests', 'sanitation_service_requests_frequency_status_index')) {
                $table->dropIndex('sanitation_service_requests_frequency_status_index');
            }

            foreach ([
                'agreement_terms_snapshot',
                'agreement_accepted_ip',
                'agreement_accepted_at',
                'quoted_currency',
                'quoted_emergency_fee',
                'quoted_monthly_fee',
                'collection_frequency',
            ] as $column) {
                if (Schema::hasColumn('sanitation_service_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Native Laravel index check — works on Laravel 10.38+ without Doctrine DBAL.
     * Accepts either an index name or an array of columns.
     */
    private function hasIndex(string $table, string|array $index): bool
    {
        try {
            return Schema::hasIndex($table, $index);
        } catch (\Throwable $e) {
            // Very old Laravel: fall back to a query on information_schema
            try {
                $driver = DB::connection()->getDriverName();

                if ($driver === 'mysql') {
                    return DB::table('information_schema.statistics')
                        ->where('table_schema', DB::getDatabaseName())
                        ->where('table_name', $table)
                        ->where('index_name', is_string($index) ? $index : '')
                        ->exists();
                }

                if ($driver === 'pgsql') {
                    return DB::table('pg_indexes')
                        ->where('tablename', $table)
                        ->where('indexname', is_string($index) ? $index : '')
                        ->exists();
                }
            } catch (\Throwable $inner) {
                // Nothing we can do — assume the index doesn't exist
            }

            return false;
        }
    }
};