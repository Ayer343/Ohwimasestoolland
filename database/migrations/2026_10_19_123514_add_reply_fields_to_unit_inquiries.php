<?php
// database/migrations/2026_10_19_000001_add_reply_fields_to_unit_inquiries.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_inquiries', function (Blueprint $table) {

            // ── Reply payload ───────────────────────────────────────
            // The text of the reply. NULL until someone replies.
            // Kept as TEXT (not STRING) so replies aren't arbitrarily
            // truncated and can include formatted content.
            if (!Schema::hasColumn('unit_inquiries', 'reply_message')) {
                $table->text('reply_message')
                    ->nullable()
                    ->after('message');
            }

            // ── Who replied ─────────────────────────────────────────
            // FK to users, nullable so deleting an admin doesn't cascade
            // the inquiry away (the reply is still valid audit history).
            if (!Schema::hasColumn('unit_inquiries', 'replied_by')) {
                $table->foreignId('replied_by')
                    ->nullable()
                    ->after('reply_message')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            // ── Role of the replier ─────────────────────────────────
            // 'admin' | 'landlord' — lets us distinguish "the landlord
            // replied through the platform" from "an admin replied on
            // behalf of the landlord". Stored as a small string rather
            // than an enum so it stays flexible.
            if (!Schema::hasColumn('unit_inquiries', 'replied_by_role')) {
                $table->string('replied_by_role', 20)
                    ->nullable()
                    ->after('replied_by');
            }

            // ── replied_at (in case the base migration didn't add it) ─
            // The controller stamps this the first time the inquiry
            // transitions to `replied`. Guarded with hasColumn() so a
            // second run is a no-op.
            if (!Schema::hasColumn('unit_inquiries', 'replied_at')) {
                $table->timestamp('replied_at')
                    ->nullable()
                    ->after('replied_by_role');
            }

            // ── Admin / landlord inbox indexes ──────────────────────
            // The two inbox views sort by "newest first" and filter by
            // landlord + status. Without these, the inbox degrades to
            // full table scans once the table grows past a few thousand
            // rows.
            $table->index(
                ['landlord_id', 'status', 'created_at'],
                'unit_inquiries_landlord_status_created_idx'
            );

            $table->index(
                ['status', 'created_at'],
                'unit_inquiries_status_created_idx'
            );

            $table->index('replied_at', 'unit_inquiries_replied_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('unit_inquiries', function (Blueprint $table) {

            // Drop indexes first (names must match exactly)
            $table->dropIndex('unit_inquiries_landlord_status_created_idx');
            $table->dropIndex('unit_inquiries_status_created_idx');
            $table->dropIndex('unit_inquiries_replied_at_idx');

            // Drop FK before the column so SQLite/MySQL don't complain
            if (Schema::hasColumn('unit_inquiries', 'replied_by')) {
                $table->dropForeign(['replied_by']);
            }

            $table->dropColumn([
                'reply_message',
                'replied_by',
                'replied_by_role',
                'replied_at',
            ]);
        });
    }
};