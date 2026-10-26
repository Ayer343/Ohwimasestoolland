<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeveloperDatabaseController extends Controller
{
    private const AUTHORIZED_USER_TYPES = [
        User::TYPE_SUPER_ADMIN,
        User::TYPE_DEVELOPER,
    ];

    private function authorizeOrAbort(): ?JsonResponse
    {
        $user = auth()->user();

        if ($user && in_array((int) $user->type, self::AUTHORIZED_USER_TYPES, true)) {
            return null;
        }

        Log::warning('Database management access denied', [
            'user_id'   => auth()->id(),
            'user_type' => $user->type ?? null,
            'ip'        => request()->ip(),
        ]);

        return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
    }

    /* ============================================================
     | INDEX
     * ============================================================ */

    public function index()
    {
        if (!$this->authorizeOrAbort()) {
            return view('developer.database.index');
        }

        abort(403);
    }

    /* ============================================================
     | STATS — DB size, table count, connections, avg query time
     * ============================================================ */

    public function stats(): JsonResponse
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        try {
            $driver = DB::connection()->getDriverName();
            $cacheKey = "developer.database.stats.{$driver}";

            $stats = Cache::remember($cacheKey, 30, function () use ($driver) {
                return [
                    'driver'             => $driver,
                    'db_size_bytes'      => $this->databaseSizeBytes($driver),
                    'db_size_formatted'  => $this->formatBytes($this->databaseSizeBytes($driver)),
                    'table_count'        => $this->tableCount($driver),
                    'active_connections' => $this->activeConnections($driver),
                    'avg_query_time_ms'  => $this->averageQueryTimeMs(),
                    'slow_queries_count' => $this->slowQueryCount(),
                ];
            });

            return response()->json([
                'success'   => true,
                'stats'     => $stats,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch database stats', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch stats: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ============================================================
     | TABLES — list + sizes
     * ============================================================ */

    public function tables(): JsonResponse
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        try {
            $driver = DB::connection()->getDriverName();
            $cacheKey = "developer.database.tables.{$driver}";

            $tables = Cache::remember($cacheKey, 60, fn () => match ($driver) {
                'mysql', 'mariadb' => $this->mysqlTables(),
                'pgsql'            => $this->postgresTables(),
                'sqlite'           => $this->sqliteTables(),
                default            => [],
            });

            return response()->json([
                'success'   => true,
                'driver'    => $driver,
                'tables'    => $tables,
                'count'     => count($tables),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to introspect database tables', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to introspect tables: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ============================================================
     | OPTIMIZE a single table
     * ============================================================ */

    public function optimizeTable(Request $request): JsonResponse
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $data = $request->validate([
            'table' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'],
        ]);

        try {
            $driver = DB::connection()->getDriverName();
            $table  = $data['table'];

            match ($driver) {
                'mysql', 'mariadb' => DB::statement("OPTIMIZE TABLE `{$table}`"),
                'pgsql'            => DB::statement("VACUUM ANALYZE \"{$table}\""),
                default            => null,
            };

            Cache::forget("developer.database.tables.{$driver}");
            Cache::forget("developer.database.stats.{$driver}");

            return response()->json([
                'success' => true,
                'message' => "Table {$table} optimized.",
            ]);
        } catch (\Throwable $e) {
            Log::error('Table optimize failed', [
                'table' => $data['table'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Optimize failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ============================================================
     | SLOW QUERIES
     *
     * Reads from a `slow_queries` log table if it exists, OR from a
     * cache key populated by a DB::listen() middleware. Returns an
     * empty list if neither is present — the Blade renders an
     * informative empty state.
     * ============================================================ */

    public function slowQueries(): JsonResponse
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        try {
            $queries = [];

            // Preferred source: cached entries recorded by a listener.
            $cached = Cache::get('developer.database.slow_queries', []);

            if (is_array($cached) && !empty($cached)) {
                $queries = $cached;
            }

            // Secondary source: a dedicated table if the app maintains one.
            if (empty($queries) && $this->tableExists('slow_queries')) {
                $queries = DB::table('slow_queries')
                    ->orderByDesc('duration_ms')
                    ->limit(50)
                    ->get()
                    ->map(fn ($row) => [
                        'query'      => $row->query ?? '',
                        'time'       => $row->duration_ms ?? 0,
                        'count'      => $row->occurrences ?? 1,
                        'created_at' => $row->created_at ?? null,
                    ])
                    ->all();
            }

            return response()->json([
                'success'      => true,
                'slow_queries' => $queries,
                'count'        => count($queries),
                'timestamp'    => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch slow queries', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch slow queries: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ============================================================
     | PRIVATE HELPERS
     * ============================================================ */

    private function databaseSizeBytes(string $driver): int
    {
        try {
            return match ($driver) {
                'mysql', 'mariadb' => (int) (DB::selectOne("
                    SELECT SUM(DATA_LENGTH + INDEX_LENGTH) AS bytes
                    FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE()
                ")?->bytes ?? 0),

                'pgsql' => (int) (DB::selectOne("
                    SELECT pg_database_size(current_database()) AS bytes
                ")?->bytes ?? 0),

                'sqlite' => (function () {
                    $path = DB::connection()->getDatabaseName();
                    return is_file($path) ? filesize($path) : 0;
                })(),

                default => 0,
            };
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function tableCount(string $driver): int
    {
        return match ($driver) {
            'mysql', 'mariadb' => (int) (DB::selectOne("
                SELECT COUNT(*) AS c
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
            ")?->c ?? 0),

            'pgsql' => (int) (DB::selectOne("
                SELECT COUNT(*) AS c FROM information_schema.tables
                WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
            ")?->c ?? 0),

            'sqlite' => (int) (DB::selectOne("
                SELECT COUNT(*) AS c FROM sqlite_master
                WHERE type = 'table' AND name NOT LIKE 'sqlite_%'
            ")?->c ?? 0),

            default => 0,
        };
    }

    private function activeConnections(string $driver): int
    {
        try {
            return match ($driver) {
                'mysql', 'mariadb' => (int) (DB::selectOne("
                    SELECT COUNT(*) AS c FROM information_schema.PROCESSLIST
                    WHERE DB = DATABASE()
                ")?->c ?? 0),

                'pgsql' => (int) (DB::selectOne("
                    SELECT COUNT(*) AS c FROM pg_stat_activity WHERE datname = current_database()
                ")?->c ?? 0),

                default => 1,
            };
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function averageQueryTimeMs(): float
    {
        // Without a query-log table we can't compute this precisely.
        // Fall back to the app-level cache the listener populates.
        $samples = Cache::get('developer.database.query_times_ms', []);

        if (empty($samples) || !is_array($samples)) {
            return 0.0;
        }

        return round(array_sum($samples) / count($samples), 2);
    }

    private function slowQueryCount(): int
    {
        $cached = Cache::get('developer.database.slow_queries', []);
        return is_array($cached) ? count($cached) : 0;
    }

    private function tableExists(string $name): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($name);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function mysqlTables(): array
    {
        $rows = DB::select("
            SELECT
                TABLE_NAME                                            AS name,
                COALESCE(TABLE_ROWS, 0)                               AS `rows`,
                COALESCE(DATA_LENGTH, 0)                              AS data_bytes,
                COALESCE(INDEX_LENGTH, 0)                             AS index_bytes,
                COALESCE(DATA_LENGTH, 0) + COALESCE(INDEX_LENGTH, 0)  AS total_bytes,
                ENGINE                                                AS engine,
                TABLE_COLLATION                                       AS collation
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_TYPE = 'BASE TABLE'
            ORDER BY total_bytes DESC
        ");

        return array_map(fn ($r) => [
            'name'       => $r->name,
            'rows'       => (int) $r->rows,
            'data_size'  => $this->formatBytes((int) $r->data_bytes),
            'index_size' => $this->formatBytes((int) $r->index_bytes),
            'total_size' => $this->formatBytes((int) $r->total_bytes),
            'engine'     => $r->engine,
            'collation'  => $r->collation,
        ], $rows);
    }

    private function postgresTables(): array
    {
        $rows = DB::select("
            SELECT
                relname                       AS name,
                n_live_tup                    AS `rows`,
                pg_total_relation_size(relid) AS total_bytes,
                pg_relation_size(relid)       AS data_bytes,
                pg_indexes_size(relid)        AS index_bytes
            FROM pg_stat_user_tables
            JOIN pg_class ON pg_class.oid = relid
            ORDER BY total_bytes DESC
        ");

        return array_map(fn ($r) => [
            'name'       => $r->name,
            'rows'       => (int) $r->rows,
            'data_size'  => $this->formatBytes((int) $r->data_bytes),
            'index_size' => $this->formatBytes((int) $r->index_bytes),
            'total_size' => $this->formatBytes((int) $r->total_bytes),
            'engine'     => 'pgsql',
        ], $rows);
    }

    private function sqliteTables(): array
    {
        $rows = DB::select("
            SELECT name FROM sqlite_master
            WHERE type = 'table' AND name NOT LIKE 'sqlite_%'
            ORDER BY name
        ");

        return array_map(function ($r) {
            $count = DB::table($r->name)->count();

            return [
                'name'       => $r->name,
                'rows'       => $count,
                'data_size'  => '—',
                'index_size' => '—',
                'total_size' => '—',
                'engine'     => 'sqlite',
            ];
        }, $rows);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i     = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
    }
}