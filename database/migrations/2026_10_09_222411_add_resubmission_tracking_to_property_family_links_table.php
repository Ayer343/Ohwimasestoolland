<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_resubmission_tracking_to_property_family_links_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_family_links', function (Blueprint $table) {
            // Self-reference to the original (rejected) proposal this
            // one is a resubmission of. Nullable — most links have no
            // predecessor.
            $table->unsignedBigInteger('resubmitted_from_id')
                  ->nullable()
                  ->after('id');

            $table->foreign('resubmitted_from_id')
                  ->references('id')
                  ->on('property_family_links')
                  ->nullOnDelete();

            // Optional: capture the reason the previous attempt was
            // rejected, snapshotted at resubmit time. Purely for
            // admin context — the source of truth remains the original
            // row's admin_notes.
            $table->text('previous_rejection_reason')
                  ->nullable()
                  ->after('admin_notes');
        });
    }

    public function down(): void
    {
        Schema::table('property_family_links', function (Blueprint $table) {
            $table->dropForeign(['resubmitted_from_id']);
            $table->dropColumn(['resubmitted_from_id', 'previous_rejection_reason']);
        });
    }
};