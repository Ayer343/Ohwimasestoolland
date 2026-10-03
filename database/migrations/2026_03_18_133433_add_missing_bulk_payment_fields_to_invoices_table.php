<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingBulkPaymentFieldsToInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoices', function (Blueprint $table) {
            // ✅ ADD ONLY MISSING FIELDS (not in main table)
            
            // 1. Bulk parent relationship (missing)
            if (!Schema::hasColumn('invoices', 'bulk_parent_id')) {
                $table->unsignedBigInteger('bulk_parent_id')->nullable()->after('id');
                $table->foreign('bulk_parent_id')
                      ->references('id')
                      ->on('invoices')
                      ->onDelete('set null');
            }

            // 2. Missing period range fields (for bulk payments)
            if (!Schema::hasColumn('invoices', 'bulk_start_month')) {
                $table->string('bulk_start_month', 7)->nullable()->after('bulk_months');
            }

            if (!Schema::hasColumn('invoices', 'bulk_end_month')) {
                $table->string('bulk_end_month', 7)->nullable()->after('bulk_start_month');
            }

            // 3. Coverage tracking fields (missing)
            if (!Schema::hasColumn('invoices', 'covers_periods')) {
                $table->json('covers_periods')->nullable()->after('bulk_end_month');
            }

            if (!Schema::hasColumn('invoices', 'bulk_coverage_start')) {
                $table->string('bulk_coverage_start', 7)->nullable()->after('covers_periods');
            }

            if (!Schema::hasColumn('invoices', 'bulk_coverage_end')) {
                $table->string('bulk_coverage_end', 7)->nullable()->after('bulk_coverage_start');
            }

            // ✅ ADD ONLY MISSING INDEXES
            // bulk_parent_id index (missing)
            if (!$this->indexExists('invoices', 'invoices_bulk_parent_id_index')) {
                $table->index('bulk_parent_id', 'invoices_bulk_parent_id_index');
            }

            // bulk_start_month index (missing)
            if (!$this->indexExists('invoices', 'invoices_bulk_start_month_index')) {
                $table->index('bulk_start_month', 'invoices_bulk_start_month_index');
            }

            // bulk_end_month index (missing)
            if (!$this->indexExists('invoices', 'invoices_bulk_end_month_index')) {
                $table->index('bulk_end_month', 'invoices_bulk_end_month_index');
            }
        });
    }

    /**
     * Check if an index exists
     */
    private function indexExists($tableName, $indexName)
    {
        $exists = DB::select("
            SELECT COUNT(*) as count
            FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE table_schema = DATABASE() 
            AND table_name = ? 
            AND index_name = ?
        ", [$tableName, $indexName]);
        
        return $exists[0]->count > 0;
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Drop foreign key
            if (Schema::hasColumn('invoices', 'bulk_parent_id')) {
                try {
                    $table->dropForeign(['bulk_parent_id']);
                } catch (\Exception $e) {}
            }
            
            // Drop indexes (only the ones we added)
            $indexes = [
                'invoices_bulk_parent_id_index',
                'invoices_bulk_start_month_index',
                'invoices_bulk_end_month_index'
            ];
            
            foreach ($indexes as $indexName) {
                try {
                    $table->dropIndex($indexName);
                } catch (\Exception $e) {}
            }
            
            // Drop columns (only the ones we added)
            $columns = [
                'bulk_parent_id',
                'bulk_start_month',
                'bulk_end_month',
                'covers_periods',
                'bulk_coverage_start',
                'bulk_coverage_end'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('invoices', $column)) {
                    try {
                        $table->dropColumn($column);
                    } catch (\Exception $e) {}
                }
            }
        });
    }
}