<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * MySQL hard limit: 64 secondary indexes per table.
     * We keep a small safety buffer to avoid edge cases.
     */
    private const MAX_INDEXES_PER_TABLE = 64;

    public function up(): void
    {
        // =====================================================================
        // USERS
        // =====================================================================
        $this->addIndexesOneByOne('users', [
            ['column' => 'name', 'index' => 'users_name_search_idx'],
        ]);

        // =====================================================================
        // PROPERTIES
        // =====================================================================
        $this->addIndexesOneByOne('properties', [
            ['column' => 'property_name',        'index' => 'properties_name_search_idx'],
            ['column' => 'digital_address',      'index' => 'properties_digital_search_idx'],
            ['column' => 'registration_pattern', 'index' => 'properties_pattern_search_idx'],
        ]);

        // =====================================================================
        // PROPERTY UNITS
        // =====================================================================
        $this->addIndexesOneByOne('property_units', [
            ['column' => 'unit_number', 'index' => 'units_number_search_idx'],
        ]);

        // =====================================================================
        // LANDLORD INVOICES
        // =====================================================================
        $this->addIndexesOneByOne('invoices', [
            ['column'  => 'invoice_number',         'index' => 'invoices_number_search_idx'],
            ['columns' => ['status', 'created_at'], 'index' => 'invoices_status_created_idx'],
        ]);

        // =====================================================================
        // TENANT INVOICES
        // =====================================================================
        $this->addIndexesOneByOne('tenant_invoices', [
            ['column' => 'invoice_number', 'index' => 'tinvoices_number_search_idx'],
        ]);

        // =====================================================================
        // PAYMENTS
        // =====================================================================
        $this->addIndexesOneByOne('payments', [
            ['column' => 'transaction_reference', 'index' => 'payments_ref_search_idx'],
            ['column' => 'transaction_id',        'index' => 'payments_txid_search_idx'],
            ['column' => 'status',                'index' => 'payments_status_search_idx'],
        ]);

        // =====================================================================
        // CONSTRUCTION CONTRACTS
        // =====================================================================
        $this->addIndexesOneByOne('construction_contracts', [
            ['column' => 'contract_number', 'index' => 'contracts_number_search_idx'],
            ['column' => 'title',           'index' => 'contracts_title_search_idx'],
            ['column' => 'status',          'index' => 'contracts_status_search_idx'],
        ]);

        // =====================================================================
        // LANDLORD CONSTRUCTION REGISTRATIONS
        // =====================================================================
        $this->addIndexesOneByOne('landlord_construction_registrations', [
            ['column' => 'submission_hash', 'index' => 'lcr_hash_search_idx'],
            ['column' => 'property_name',   'index' => 'lcr_property_search_idx'],
            ['column' => 'name',            'index' => 'lcr_name_search_idx'],
            ['column' => 'email',           'index' => 'lcr_email_search_idx'],
            ['column' => 'status',          'index' => 'lcr_status_search_idx'],
        ]);

        // =====================================================================
        // SECURITY POSTS
        // =====================================================================
        $this->addIndexesOneByOne('security_posts', [
            ['column' => 'name', 'index' => 'posts_name_search_idx'],
            ['column' => 'code', 'index' => 'posts_code_search_idx'],
            ['column' => 'type', 'index' => 'posts_type_search_idx'],
        ]);

        // =====================================================================
        // SECURITY SCHEDULES
        // =====================================================================
        $this->addIndexesOneByOne('security_schedules', [
            ['column' => 'assignment_date', 'index' => 'schedules_date_search_idx'],
            ['column' => 'status',          'index' => 'schedules_status_search_idx'],
        ]);

        // =====================================================================
        // OWNERSHIP TRANSFERS
        // =====================================================================
        $this->addIndexesOneByOne('property_ownership_transfers', [
            ['column' => 'document_reference', 'index' => 'pot_docref_search_idx'],
            ['column' => 'status',             'index' => 'pot_status_search_idx'],
        ]);

        // =====================================================================
        // REGISTRATION PLANS
        // =====================================================================
        $this->addIndexesOneByOne('registration_plans', [
            ['column' => 'plan_code', 'index' => 'plans_code_search_idx'],
            ['column' => 'zone',      'index' => 'plans_zone_search_idx'],
            ['column' => 'section',   'index' => 'plans_section_search_idx'],
            ['column' => 'status',    'index' => 'plans_status_search_idx'],
        ]);

        // =====================================================================
        // ADMIN BILLING RECORDS
        // =====================================================================
        $this->addIndexesOneByOne('admin_billing_records', [
            ['column' => 'agreement_number', 'index' => 'abr_number_search_idx'],
            ['column' => 'invoice_number',   'index' => 'abr_invoice_search_idx'],
        ]);
    }

    public function down(): void
    {
        $drops = [
            'users'                                => ['users_name_search_idx'],
            'properties'                           => ['properties_name_search_idx', 'properties_digital_search_idx', 'properties_pattern_search_idx'],
            'property_units'                       => ['units_number_search_idx'],
            'invoices'                             => ['invoices_number_search_idx', 'invoices_status_created_idx'],
            'tenant_invoices'                      => ['tinvoices_number_search_idx'],
            'payments'                             => ['payments_ref_search_idx', 'payments_txid_search_idx', 'payments_status_search_idx'],
            'construction_contracts'               => ['contracts_number_search_idx', 'contracts_title_search_idx', 'contracts_status_search_idx'],
            'landlord_construction_registrations'  => ['lcr_hash_search_idx', 'lcr_property_search_idx', 'lcr_name_search_idx', 'lcr_email_search_idx', 'lcr_status_search_idx'],
            'security_posts'                       => ['posts_name_search_idx', 'posts_code_search_idx', 'posts_type_search_idx'],
            'security_schedules'                   => ['schedules_date_search_idx', 'schedules_status_search_idx'],
            'property_ownership_transfers'         => ['pot_docref_search_idx', 'pot_status_search_idx'],
            'registration_plans'                   => ['plans_code_search_idx', 'plans_zone_search_idx', 'plans_section_search_idx', 'plans_status_search_idx'],
            'admin_billing_records'                => ['abr_number_search_idx', 'abr_invoice_search_idx'],
        ];

        foreach ($drops as $table => $indexes) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $index) {
                if (!$this->indexExists($table, $index)) {
                    continue;
                }

                try {
                    DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
                } catch (\Throwable $e) {
                    Log::warning("Failed to drop index {$index} on {$table}: " . $e->getMessage());
                }
            }
        }
    }

    // =========================================================================
    // ✅ FIXED HELPER: adds each index via its own ALTER TABLE statement
    // =========================================================================

    /**
     * Add indexes one at a time via raw ALTER TABLE.
     *
     * Why not Schema::table()?
     *   Laravel batches all ->index() calls into a single ALTER TABLE. MySQL
     *   then evaluates the 64-index limit against the batch as a whole, so
     *   if we try to add 3 indexes to a table with 63, the batch fails even
     *   though "63 → 66" would only exceed on the last one.
     *
     * By running each index as its own statement, we can:
     *   - Check the limit before each addition
     *   - Catch a "Too many keys" error on a single index and skip it
     *   - Continue with the next one instead of aborting the entire migration
     */
    private function addIndexesOneByOne(string $table, array $definitions): void
    {
        if (!Schema::hasTable($table)) {
            Log::info("Search index migration: table `{$table}` doesn't exist — skipping.");
            return;
        }

        foreach ($definitions as $def) {
            // ---- 1. Check limit BEFORE attempting ----
            $currentCount = $this->countIndexes($table);
            if ($currentCount >= self::MAX_INDEXES_PER_TABLE) {
                Log::warning(
                    "Search index migration: `{$table}` is at {$currentCount} indexes " .
                    "(limit " . self::MAX_INDEXES_PER_TABLE . ") — skipping `{$def['index']}`."
                );
                continue; // try the next one — the table might not be relevant
            }

            // ---- 2. Skip if already exists ----
            if ($this->indexExists($table, $def['index'])) {
                continue;
            }

            // ---- 3. Resolve the column list ----
            $columns = [];
            if (isset($def['column'])) {
                if (!Schema::hasColumn($table, $def['column'])) {
                    continue; // column missing — skip silently
                }
                $columns = [$def['column']];
            } elseif (isset($def['columns'])) {
                $missing = false;
                foreach ($def['columns'] as $col) {
                    if (!Schema::hasColumn($table, $col)) {
                        $missing = true;
                        break;
                    }
                }
                if ($missing) {
                    continue; // at least one column missing — skip silently
                }
                $columns = $def['columns'];
            } else {
                continue;
            }

            // ---- 4. Build a safe, quoted SQL fragment ----
            $quotedColumns = implode('`, `', array_map(
                fn ($c) => str_replace('`', '``', $c),
                $columns
            ));
            $safeTable  = str_replace('`', '``', $table);
            $safeIndex  = str_replace('`', '``', $def['index']);

            $sql = "ALTER TABLE `{$safeTable}` ADD INDEX `{$safeIndex}` (`{$quotedColumns}`)";

            // ---- 5. Execute + catch ----
            try {
                DB::statement($sql);
                Log::info("Search index migration: added `{$def['index']}` on `{$table}`.");
            } catch (\Throwable $e) {
                Log::warning(
                    "Search index migration: failed to add `{$def['index']}` on `{$table}` — " .
                    $e->getMessage()
                );
                // Do NOT re-throw — let the migration continue with other indexes/tables.
            }
        }
    }

    // =========================================================================
    // UTILITY HELPERS
    // =========================================================================

    /**
     * Total number of indexes on a table (excluding PRIMARY).
     */
    private function countIndexes(string $table): int
    {
        try {
            $db = DB::connection()->getDatabaseName();
            $row = DB::selectOne(
                'SELECT COUNT(DISTINCT index_name) AS c
                 FROM information_schema.statistics
                 WHERE table_schema = ?
                   AND table_name = ?
                   AND index_name != "PRIMARY"',
                [$db, $table]
            );
            return (int) ($row->c ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Does an index with this name already exist?
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            $db = DB::connection()->getDatabaseName();
            $row = DB::selectOne(
                'SELECT COUNT(*) AS c
                 FROM information_schema.statistics
                 WHERE table_schema = ?
                   AND table_name = ?
                   AND index_name = ?',
                [$db, $table, $index]
            );
            return (($row->c ?? 0) > 0);
        } catch (\Throwable $e) {
            return false;
        }
    }
};