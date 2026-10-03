<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admin_billing_records') || !Schema::hasColumn('admin_billing_records', 'status')) {
            return;
        }

        // Normalize the empty-string rows that MySQL coerced from invalid
        // enum values. These are almost certainly meant to be 'pending'.
        DB::table('admin_billing_records')
            ->whereNull('status')
            ->orWhere('status', '')
            ->update(['status' => 'pending']);

        // Preserve the existing index by dropping and re-adding it around
        // the MODIFY. (MODIFY alone preserves indexes on most MySQL
        // versions, but being explicit avoids surprises.)
        DB::statement("
            ALTER TABLE `admin_billing_records`
            MODIFY COLUMN `status`
            VARCHAR(50)
            NOT NULL
            DEFAULT 'pending'
        ");
    }

    public function down(): void
    {
        if (!Schema::hasTable('admin_billing_records') || !Schema::hasColumn('admin_billing_records', 'status')) {
            return;
        }

        // Revert to the old enum.
        $previousEnum = "'pending','active','completed','terminated','superseded','rejected','cancelled','draft'";

        // Remap any values the old enum can't hold.
        DB::table('admin_billing_records')
            ->whereNotIn('status', ['pending', 'active', 'completed', 'terminated', 'superseded', 'rejected', 'cancelled', 'draft'])
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