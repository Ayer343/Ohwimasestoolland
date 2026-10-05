<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Indexes to remove from `properties`.
     *
     * Rationale for each is documented inline. Every drop is
     * guarded by `indexExists()` so re-runs never fail.
     */
    private array $redundantIndexes = [

        // -----------------------------------------------------------------
        // A — Exact duplicates (same column, two names)
        // -----------------------------------------------------------------
        'properties_name_index'            => 'duplicate of properties_property_name_index',
        'properties_pattern_index'         => 'duplicate of properties_registration_pattern_index',
        'properties_digital_index'         => 'duplicate of properties_digital_address_index',
        'properties_landlord_index'        => 'duplicate of properties_landlord_id_index',

        // -----------------------------------------------------------------
        // B — Single-column indexes covered by a composite prefix
        // -----------------------------------------------------------------
        'properties_landlord_id_index'                    => 'covered by properties_landlord_id_status_index',
        'properties_registration_plan_id_index'           => 'covered by properties_registration_plan_id_status_index',
        'properties_registered_by_index'                  => 'covered by properties_registered_by_status_index',
        'properties_property_type_id_index'               => 'covered by properties_property_type_zone_index',
        'properties_is_rented_index'                      => 'covered by properties_is_rented_zone_index',
        'properties_construction_status_index'            => 'covered by properties_const_status_zone_index',

        // -----------------------------------------------------------------
        // C — Low-cardinality boolean singles
        // (Keep the composites, drop the standalone bools.)
        // -----------------------------------------------------------------
        'properties_is_global_sequence_index'             => 'boolean low-cardinality; full scan cheaper',
        'properties_is_field_agent_registered_index'      => 'boolean low-cardinality; full scan cheaper',

        // -----------------------------------------------------------------
        // D — Timestamp singles (rarely useful alone)
        // -----------------------------------------------------------------
        'properties_created_at_index'                     => 'rarely queried alone',
        'properties_updated_at_index'                     => 'rarely queried alone',
        'properties_deleted_at_index'                     => 'MySQL ignores IS NULL on mostly-null cols',

        // -----------------------------------------------------------------
        // F — Over-indexed `registered_by` — drop the least useful composites
        // Keep: unique_field_agent_plan_pattern, registered_by_status_index,
        //       registered_by_registration_plan_id_index
        // -----------------------------------------------------------------
        'properties_registered_by_is_global_sequence_index'  => 'bool in middle of composite = rarely used',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('properties')) {
            Log::info('Clean properties indexes: table missing — skipping.');
            return;
        }

        $before = $this->countIndexes('properties');

        foreach (array_keys($this->redundantIndexes) as $index) {
            if (!$this->indexExists('properties', $index)) {
                continue; // already gone, fine
            }

            try {
                DB::statement("ALTER TABLE `properties` DROP INDEX `{$index}`");
                Log::info("Dropped properties index: {$index} — {$this->redundantIndexes[$index]}");
            } catch (\Throwable $e) {
                Log::warning("Could not drop properties index {$index}: " . $e->getMessage());
            }
        }

        $after = $this->countIndexes('properties');

        Log::info("Properties index cleanup: {$before} → {$after} indexes.");
    }

    public function down(): void
    {
        // Intentionally empty — re-creating these requires knowing their
        // original column order and uniqueness, which we deliberately lost.
        // If you need to reverse, restore from DB backup.
        Log::warning('clean_properties_redundant_indexes was rolled back — indexes NOT recreated.');
    }

    private function countIndexes(string $table): int
    {
        try {
            $row = DB::selectOne(
                'SELECT COUNT(DISTINCT index_name) AS c
                 FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                   AND index_name != "PRIMARY"',
                [$table]
            );
            return (int) ($row->c ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            $row = DB::selectOne(
                'SELECT COUNT(*) AS c
                 FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                   AND index_name = ?',
                [$table, $index]
            );
            return (($row->c ?? 0) > 0);
        } catch (\Throwable $e) {
            return false;
        }
    }
};