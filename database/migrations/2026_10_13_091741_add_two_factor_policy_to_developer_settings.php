<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Add the 2FA policy columns to `developer_settings`.
     *
     * Why we keep `enable_two_factor`:
     *   - Existing rows already have it as a boolean.
     *   - Existing code (DeveloperSettingsController::updateSecuritySettings)
     *     still writes it in some paths.
     *   - Zero-downtime migration: the new columns are additive, the old
     *     column becomes a derived/legacy mirror.
     *
     * Semantic mapping (applied as a data migration below):
     *   enable_two_factor = 0  →  two_factor_policy = 'optional'
     *   enable_two_factor = 1  →  two_factor_policy = 'encouraged'
     *
     * After this migration, the model's `twoFactorPolicy()` reads the new
     * column and falls back to the boolean for any row that hasn't been
     * touched yet.
     */
    public function up(): void
    {
        // ── Step 1: add the new columns ───────────────────────────────
        Schema::table('developer_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('developer_settings', 'two_factor_policy')) {
                $table->string('two_factor_policy', 16)
                    ->default('optional')
                    ->after('enable_two_factor')
                    ->comment('optional | encouraged | required');
            }

            if (!Schema::hasColumn('developer_settings', 'two_factor_required_roles')) {
                $table->json('two_factor_required_roles')
                    ->nullable()
                    ->after('two_factor_policy')
                    ->comment('Array of User type integers that MUST use 2FA when policy=required');
            }
        });

        // ── Step 2: backfill existing rows ────────────────────────────
        //
        // We do this explicitly rather than relying on the column default
        // so that rows which already had `enable_two_factor = 1` become
        // 'encouraged' instead of silently downgrading to 'optional'.
        //
        // Wrapped in try/catch so a fresh install with zero rows doesn't
        // blow up, and so an unexpected DB error doesn't leave the schema
        // half-migrated.
        try {
            DB::table('developer_settings')
                ->where('enable_two_factor', true)
                ->update([
                    'two_factor_policy' => 'encouraged',
                ]);

            DB::table('developer_settings')
                ->where(function ($q) {
                    $q->whereNull('enable_two_factor')
                      ->orWhere('enable_two_factor', false);
                })
                ->update([
                    'two_factor_policy' => 'optional',
                ]);

            // Seed the default required roles only for rows that are
            // currently enforcing 2FA globally. Everyone else gets NULL
            // and will fall back to the model's default role set
            // (Developer + Super Admin) when policy is flipped to 'required'.
            DB::table('developer_settings')
                ->where('two_factor_policy', 'encouraged')
                ->whereNull('two_factor_required_roles')
                ->update([
                    'two_factor_required_roles' => json_encode([5, 0]),
                ]);

            Log::info('[Migration] developer_settings.two_factor_policy backfilled');
        } catch (\Throwable $e) {
            Log::warning('[Migration] 2FA policy backfill skipped', [
                'error' => $e->getMessage(),
            ]);
            // Don't fail the migration — the columns exist and the model
            // has safe fallbacks.
        }

        // ── Step 3: indexes ───────────────────────────────────────────
        //
        // The policy is read on every login via LoginController, so an
        // index lets the query planner pick a covering path even if the
        // table grows. Not strictly necessary today (single row), but
        // cheap insurance.
        Schema::table('developer_settings', function (Blueprint $table) {
            $table->index('two_factor_policy', 'idx_dev_settings_2fa_policy');
        });
    }

    /**
     * Reverse: drop the columns and the index.
     *
     * We do NOT restore `enable_two_factor` from the new policy, because
     * the reverse direction is lossy ('required' has no boolean
     * equivalent). Leave that to whoever rolls forward next.
     */
    public function down(): void
    {
        Schema::table('developer_settings', function (Blueprint $table) {
            if (Schema::hasColumn('developer_settings', 'two_factor_required_roles')) {
                $table->dropColumn('two_factor_required_roles');
            }

            if (Schema::hasColumn('developer_settings', 'two_factor_policy')) {
                $table->dropIndex('idx_dev_settings_2fa_policy');
                $table->dropColumn('two_factor_policy');
            }
        });
    }
};