<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('properties')) {
            return;
        }

        $definitions = [
            ['column' => 'property_name',        'index' => 'properties_name_search_idx'],
            ['column' => 'digital_address',      'index' => 'properties_digital_search_idx'],
            ['column' => 'registration_pattern', 'index' => 'properties_pattern_search_idx'],
        ];

        foreach ($definitions as $def) {
            // Check the 64-index ceiling first
            $count = DB::selectOne(
                'SELECT COUNT(DISTINCT index_name) c
                 FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = "properties"
                   AND index_name != "PRIMARY"'
            )->c;

            if ($count >= 64) {
                Log::warning("properties at {$count} indexes — stopping.");
                return;
            }

            // Skip if the index already exists
            $exists = DB::selectOne(
                'SELECT COUNT(*) c FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = "properties"
                   AND index_name = ?',
                [$def['index']]
            )->c;

            if ($exists) {
                continue;
            }

            if (!Schema::hasColumn('properties', $def['column'])) {
                continue;
            }

            try {
                DB::statement(
                    "ALTER TABLE `properties` ADD INDEX `{$def['index']}` (`{$def['column']}`)"
                );
                Log::info("Added {$def['index']} on properties.");
            } catch (\Throwable $e) {
                Log::warning("Failed {$def['index']}: " . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        foreach ([
            'properties_name_search_idx',
            'properties_digital_search_idx',
            'properties_pattern_search_idx',
        ] as $index) {
            try {
                $exists = DB::selectOne(
                    'SELECT COUNT(*) c FROM information_schema.statistics
                     WHERE table_schema = DATABASE()
                       AND table_name = "properties"
                       AND index_name = ?',
                    [$index]
                )->c;

                if ($exists) {
                    DB::statement("ALTER TABLE `properties` DROP INDEX `{$index}`");
                }
            } catch (\Throwable $e) {
                // Already gone — ignore
            }
        }
    }
};