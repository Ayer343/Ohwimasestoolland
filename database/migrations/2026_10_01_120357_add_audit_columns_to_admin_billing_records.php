<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the audit columns that AdminBillingRecord's action methods
     * write to but that were never created in the schema.
     *
     * Context:
     *   The model declares $fillable entries and calls $this->save()
     *   on these attributes from markAsPaid(), markAsCancelled(), etc.
     *   Under loose MySQL, the writes silently failed (unknown column
     *   errors were swallowed or the columns were ignored). Under
     *   strict mode, they throw — which is how this drift was found.
     *
     * Columns NOT added here (deliberately):
     *
     *   - received_date         → use `payment_date` (already exists)
     *   - transaction_reference → use `transaction_id` (already exists)
     *   - completed_at          → use `paid_at`          (already exists)
     *
     *   Adding duplicates would create two sources of truth for the
     *   same data. The model is being updated to use the existing
     *   columns for those three; this migration only adds the four
     *   columns that have no equivalent.
     */
    public function up(): void
    {
        if (!Schema::hasTable('admin_billing_records')) {
            return;
        }

        Schema::table('admin_billing_records', function (Blueprint $table) {
            if (!Schema::hasColumn('admin_billing_records', 'completed_by')) {
                $table->unsignedBigInteger('completed_by')
                    ->nullable()
                    ->after('paid_at')
                    ->comment('User ID who marked the invoice as paid/completed');
            }

            if (!Schema::hasColumn('admin_billing_records', 'cancelled_by')) {
                $table->unsignedBigInteger('cancelled_by')
                    ->nullable()
                    ->after('completed_by')
                    ->comment('User ID who cancelled the invoice');
            }

            if (!Schema::hasColumn('admin_billing_records', 'cancelled_at')) {
                $table->timestamp('cancelled_at')
                    ->nullable()
                    ->after('cancelled_by')
                    ->comment('When the invoice was cancelled');
            }

            if (!Schema::hasColumn('admin_billing_records', 'cancellation_reason')) {
                $table->text('cancellation_reason')
                    ->nullable()
                    ->after('cancelled_at')
                    ->comment('Free-text reason provided when cancelling');
            }
        });

        // Optional: add foreign keys on completed_by and cancelled_by
        // pointing at users.id. Skip if you have a hard-delete policy
        // on users (a deleted user would orphan the reference).
        //
        // Schema::table('admin_billing_records', function (Blueprint $table) {
        //     $table->foreign('completed_by')->references('id')->on('users')->nullOnDelete();
        //     $table->foreign('cancelled_by')->references('id')->on('users')->nullOnDelete();
        // });

        // Indexes to speed up audit queries
        Schema::table('admin_billing_records', function (Blueprint $table) {
            if (!$this->indexExists('admin_billing_records', 'admin_billing_records_completed_by_index')) {
                $table->index('completed_by', 'admin_billing_records_completed_by_index');
            }
            if (!$this->indexExists('admin_billing_records', 'admin_billing_records_cancelled_at_index')) {
                $table->index('cancelled_at', 'admin_billing_records_cancelled_at_index');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('admin_billing_records')) {
            return;
        }

        Schema::table('admin_billing_records', function (Blueprint $table) {
            if ($this->indexExists('admin_billing_records', 'admin_billing_records_completed_by_index')) {
                $table->dropIndex('admin_billing_records_completed_by_index');
            }
            if ($this->indexExists('admin_billing_records', 'admin_billing_records_cancelled_at_index')) {
                $table->dropIndex('admin_billing_records_cancelled_at_index');
            }

            foreach (['completed_by', 'cancelled_by', 'cancelled_at', 'cancellation_reason'] as $col) {
                if (Schema::hasColumn('admin_billing_records', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    /**
     * Helper — safely check whether an index exists.
     *
     * Uses information_schema directly so the check works on both
     * MySQL and MariaDB without pulling in an extra dependency.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $result = \DB::selectOne(
                "SELECT COUNT(*) AS c
                 FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                   AND index_name = ?",
                [$table, $indexName]
            );

            return $result && (int) $result->c > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
};