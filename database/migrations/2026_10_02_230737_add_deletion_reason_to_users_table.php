<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_deletion_reason_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'deletion_reason')) {
                $table->text('deletion_reason')->nullable()->after('deleted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'deletion_reason')) {
                $table->dropColumn('deletion_reason');
            }
        });
    }
};