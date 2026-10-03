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
        Schema::table('invoices', function (Blueprint $table) {
            // First add reminder_count if it doesn't exist
            if (!Schema::hasColumn('invoices', 'reminder_count')) {
                $table->integer('reminder_count')->default(0)->after('metadata');
            }
            
            // Then add last_reminder_sent_at after reminder_count
            if (!Schema::hasColumn('invoices', 'last_reminder_sent_at')) {
                $table->timestamp('last_reminder_sent_at')->nullable()->after('reminder_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'last_reminder_sent_at')) {
                $table->dropColumn('last_reminder_sent_at');
            }
            if (Schema::hasColumn('invoices', 'reminder_count')) {
                $table->dropColumn('reminder_count');
            }
        });
    }
};