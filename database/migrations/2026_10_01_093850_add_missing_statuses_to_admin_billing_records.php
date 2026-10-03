<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The complete list of statuses the AdminBillingRecord model supports.
     *
     * Must stay in sync with the STATUS_* constants on
     * App\Models\AdminBillingRecord. If you add a constant there, add it
     * here and write a new migration to ALTER the enum.
     */
    private array $statuses = [
        'pending',
        'approved',
        'active',
        'overdue',
        'completed',
        'disputed',
        'cancelled',
        'rejected',
        'superseded',
        'terminated',
        'draft',
    ];

    public function up(): void
    {
        // Safety: bail out if the table/column doesn't exist (e.g. fresh install)
        if (!Schema::hasTable('admin_billing_records') || !Schema::hasColumn('admin_billing_records', 'status')) {
            return;
        }

        // Safety: any rows currently holding a value outside the new set
        // would be truncated by the MODIFY. Report them but don't block —
        // the previous enum already excluded these values, so any such rows
        // would have been coerced to '' by MySQL. Normalize '' → 'pending'
        // to avoid NULLs sneaking into business logic.
        DB::table('admin_billing_records')
            ->whereNull('status')
            ->orWhere('status', '')
            ->update(['status' => 'pending']);

        $enum = "'" . implode("','", $this->statuses) . "'";

        DB::statement("
            ALTER TABLE `admin_billing_records`
            MODIFY COLUMN `status`
            ENUM({$enum})
            NULL
            DEFAULT 'pending'
        ");
    }

    public function down(): void
    {
        if (!Schema::hasTable('admin_billing_records') || !Schema::hasColumn('admin_billing_records', 'status')) {
            return;
        }

        // Revert to the previous enum (this will coerce any 'approved',
        // 'overdue', or 'disputed' rows to '' — see warning below).
        $previousEnum = "'pending','active','completed','terminated','superseded','rejected','cancelled','draft'";

        // First, remap any values that would be lost.
        DB::table('admin_billing_records')
            ->whereIn('status', ['approved', 'overdue', 'disputed'])
            ->update(['status' => 'pending']);

        DB::statement("
            ALTER TABLE `admin_billing_records`
            MODIFY COLUMN `status`
            ENUM({$previousEnum})
            NULL
            DEFAULT 'pending'
        ");
    }
};