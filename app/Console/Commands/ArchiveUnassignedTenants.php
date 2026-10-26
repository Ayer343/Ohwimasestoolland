<?php

namespace App\Console\Commands;

use App\Models\PropertyUnit;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArchiveUnassignedTenants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Usage examples:
     *   php artisan tenants:archive-unassigned
     *   php artisan tenants:archive-unassigned --days=45
     *   php artisan tenants:archive-unassigned --dry-run
     *   php artisan tenants:archive-unassigned --limit=50
     *   php artisan tenants:archive-unassigned --tenant-id=42
     */
    protected $signature = 'tenants:archive-unassigned
                            {--days=30 : Number of unassigned days before a tenant is eligible for archival}
                            {--dry-run : Show what WOULD be archived without touching the database}
                            {--limit=0 : Maximum number of tenants to archive in this run (0 = unlimited)}
                            {--tenant-id= : Archive a specific tenant by ID (ignores other filters)}';

    protected $description = 'Archive tenant accounts that have not been assigned to any property unit for N days (default: 30).';

    /**
     * Threshold for what counts as "assigned":
     *   • APPROVED units block archival
     *   • PENDING / REJECTED units do NOT block
     *   • Soft-deleted units (trashed) do NOT block
     *
     * This mirrors the eligibility rule used in the user index blade.
     */
    protected const BLOCKING_UNIT_STATUSES = [
        PropertyUnit::TENANT_STATUS_APPROVED,
    ];

    public function handle(): int
    {
        $startedAt = microtime(true);

        $days    = max(1, (int) $this->option('days'));
        $dryRun  = (bool) $this->option('dry-run');
        $limit   = max(0, (int) $this->option('limit'));
        $singleId = $this->option('tenant-id');

        // ─────────────────────────────────────────────────────────────
        // Header
        // ─────────────────────────────────────────────────────────────
        $this->newLine();
        $this->info('╔══════════════════════════════════════════════════════════╗');
        $this->info('║  Archive Unassigned Tenants                              ║');
        $this->info('╚══════════════════════════════════════════════════════════╝');
        $this->newLine();

        $this->line(sprintf('  <fg=cyan>Threshold (days):</>  %d', $days));
        $this->line(sprintf('  <fg=cyan>Dry run:          </>  %s', $dryRun ? '<fg=yellow>YES</>' : 'no'));
        $this->line(sprintf('  <fg=cyan>Limit:            </>  %s', $limit > 0 ? (string) $limit : 'unlimited'));
        $this->line(sprintf('  <fg=cyan>Single tenant ID: </>  %s', $singleId ?: '—'));
        $this->newLine();

        // ─────────────────────────────────────────────────────────────
        // Resolve archival retention from system settings
        // ─────────────────────────────────────────────────────────────
        $settings = SystemSetting::getSettings();
        $permanentDeletionDays = (int) ($settings->account_archival['permanent_deletion_days'] ?? 365);

        $this->line(sprintf('  <fg=cyan>Retention window: </>  %d days before permanent deletion', $permanentDeletionDays));
        $this->newLine();

        // ─────────────────────────────────────────────────────────────
        // Locate orphan tenants
        // ─────────────────────────────────────────────────────────────
        $cutoffDate = Carbon::now()->subDays($days);

        $query = User::query()
            ->where('type', User::TYPE_TENANT)
            ->whereNull('deleted_at')                            // not already archived/trashed
            ->where('created_at', '<=', $cutoffDate)             // old enough
            ->whereDoesntHave('units', function ($q) {           // no blocking unit
                $q->whereIn('tenant_status', self::BLOCKING_UNIT_STATUSES);
            });

        if (!empty($singleId)) {
            $query->where('id', (int) $singleId);
        }

        $totalEligible = (clone $query)->count();

        if ($totalEligible === 0) {
            $this->info('  ✅ No unassigned tenants eligible for archival.');
            $this->newLine();
            return self::SUCCESS;
        }

        $this->line(sprintf('  <fg=yellow>Eligible tenants:</>  %d', $totalEligible));

        if ($limit > 0 && $totalEligible > $limit) {
            $this->line(sprintf('  <fg=yellow>Applying limit:</>   processing only the first %d (oldest first)', $limit));
        }

        $this->newLine();

        // ─────────────────────────────────────────────────────────────
        // Oldest first — so we archive the most overdue tenants first
        // ─────────────────────────────────────────────────────────────
        $tenants = $query
            ->orderBy('created_at', 'asc')
            ->when($limit > 0, fn($q) => $q->limit($limit))
            ->get();

        // ─────────────────────────────────────────────────────────────
        // Dry-run path
        // ─────────────────────────────────────────────────────────────
        if ($dryRun) {
            $this->warn('  🔍 DRY RUN — no changes will be made.');
            $this->newLine();

            $rows = [];
            foreach ($tenants as $tenant) {
                $unassignedDays = (int) $tenant->created_at->diffInDays(now());

                $rows[] = [
                    $tenant->id,
                    mb_strimwidth($tenant->name ?? '—', 0, 30, '…'),
                    $tenant->phone ?? '—',
                    $tenant->email ? mb_strimwidth($tenant->email, 0, 28, '…') : '—',
                    $unassignedDays . 'd',
                    $tenant->created_at->format('Y-m-d'),
                    $tenant->status ?? '—',
                ];
            }

            $this->table(
                ['ID', 'Name', 'Phone', 'Email', 'Unassigned', 'Created', 'Status'],
                $rows
            );

            $this->newLine();
            $this->line(sprintf('  <fg=yellow>Would archive:</> %d tenant(s)', count($rows)));
            $this->newLine();

            return self::SUCCESS;
        }

        // ─────────────────────────────────────────────────────────────
        // Live run — wrap in a transaction per tenant so a failure on
        // one doesn't roll back the batch.
        // ─────────────────────────────────────────────────────────────
        $this->line('  Archiving tenants...');
        $this->newLine();

        $archivedCount  = 0;
        $skippedCount   = 0;
        $failedCount    = 0;
        $failures       = [];

        $bar = $this->output->createProgressBar($tenants->count());
        $bar->start();

        foreach ($tenants as $tenant) {
            $bar->advance();

            try {
                // Re-check eligibility inside the transaction — guards
                // against a race where a unit gets assigned between the
                // initial query and this iteration.
                $stillEligible = !$tenant->units()
                    ->whereIn('tenant_status', self::BLOCKING_UNIT_STATUSES)
                    ->exists();

                if (!$stillEligible) {
                    $skippedCount++;
                    continue;
                }

                $archived = $this->archiveTenant($tenant, $permanentDeletionDays);

                if ($archived) {
                    $archivedCount++;
                } else {
                    $failedCount++;
                }

            } catch (\Throwable $e) {
                $failedCount++;
                $failures[] = [
                    'id'      => $tenant->id,
                    'name'    => $tenant->name,
                    'error'   => $e->getMessage(),
                ];

                Log::error('Auto-archival failed for tenant', [
                    'tenant_id'   => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'error'       => $e->getMessage(),
                    'trace'       => $e->getTraceAsString(),
                ]);
            }
        }

        $bar->finish();
        $this->newLine(2);

        // ─────────────────────────────────────────────────────────────
        // Summary
        // ─────────────────────────────────────────────────────────────
        $duration = round(microtime(true) - $startedAt, 2);

        $this->line('  ┌─ Summary ────────────────────────────────────────────┐');
        $this->line(sprintf('  │  <fg=green>Archived:</>   %-8d                              │', $archivedCount));
        $this->line(sprintf('  │  <fg=yellow>Skipped:</>    %-8d  (no longer eligible)        │', $skippedCount));
        $this->line(sprintf('  │  <fg=red>Failed:</>     %-8d                              │', $failedCount));
        $this->line(sprintf('  │  <fg=cyan>Duration:</>   %-8ss                             │', $duration));
        $this->line('  └──────────────────────────────────────────────────────┘');
        $this->newLine();

        if (!empty($failures)) {
            $this->warn('  Failed tenants:');
            $this->table(
                ['ID', 'Name', 'Error'],
                array_map(fn($f) => [$f['id'], mb_strimwidth($f['name'] ?? '—', 0, 30, '…'), mb_strimwidth($f['error'], 0, 60, '…')], $failures)
            );
            $this->newLine();
        }

        Log::info('Auto-archival run completed', [
            'eligible'      => $totalEligible,
            'archived'      => $archivedCount,
            'skipped'       => $skippedCount,
            'failed'        => $failedCount,
            'threshold'     => $days,
            'duration_sec'  => $duration,
            'dry_run'       => false,
        ]);

        return $failedCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Archive a single tenant.
     *
     * Mirrors UserManagementController::archiveUser() so that both the
     * manual (UI) and automatic (CLI) archival paths produce identical
     * metadata, scheduled deletion dates, and restored-account behavior.
     *
     * @return bool  true on success, false on soft failure
     */
    protected function archiveTenant(User $tenant, int $permanentDeletionDays): bool
    {
        return DB::transaction(function () use ($tenant, $permanentDeletionDays) {

            $unassignedDays = (int) $tenant->created_at->diffInDays(now());

            $archivalReason = "Auto-archived: tenant unassigned for {$unassignedDays} days";

            $originalEmail    = $tenant->email;
            $originalUsername = $tenant->username;
            $originalPhone    = $tenant->phone;

            // ─────────────────────────────────────────────────────────
            // Merge into existing metadata (don't clobber other keys)
            // ─────────────────────────────────────────────────────────
            $existingMetadata = [];
            if ($tenant->metadata) {
                if (is_string($tenant->metadata)) {
                    $existingMetadata = json_decode($tenant->metadata, true) ?? [];
                } elseif (is_array($tenant->metadata)) {
                    $existingMetadata = $tenant->metadata;
                }
            }

            $deletionScheduledAt = now()->addDays($permanentDeletionDays);

            $existingMetadata['archival_info'] = [
                'archived_at'                   => now()->toISOString(),
                'archived_reason'               => $archivalReason,
                'archived_by'                   => null,
                'archived_by_name'              => 'System (auto)',
                'original_email'                => $originalEmail,
                'original_phone'                => $originalPhone,
                'original_username'             => $originalUsername,
                'archival_type'                 => 'automatic_scheduled',
                'user_type_at_archival'         => User::TYPE_TENANT,
                'unassigned_days_at_archival'   => $unassignedDays,
                'days_until_permanent_deletion' => $permanentDeletionDays,
            ];

            $existingMetadata['archived'] = [
                'archived_at'       => now()->toISOString(),
                'archived_by'       => null,
                'archived_by_name'  => 'System (auto)',
                'reason'            => $archivalReason,
                'original_email'    => $originalEmail,
                'original_username' => $originalUsername,
                'original_phone'    => $originalPhone,
                'user_type'         => User::TYPE_TENANT,
            ];

            $existingMetadata['deletion_info'] = [
                'scheduled_at' => $deletionScheduledAt->toISOString(),
                'reason'       => 'Auto-deletion after archival period',
                'notified'     => false,
            ];

            // ─────────────────────────────────────────────────────────
            // Tombstone email + username (same helper logic as controller)
            // ─────────────────────────────────────────────────────────
            $archivedEmail    = $this->getArchivedEmail($originalEmail, $tenant->id);
            $archivedUsername = $this->getArchivedUsername($originalUsername, $tenant->id);

            $tenant->update([
                'status'                => 'archived',
                'metadata'              => $existingMetadata,
                'deletion_scheduled_at' => $deletionScheduledAt,
                'deleted_by'            => null,
                'email'                 => $archivedEmail,
                'username'              => $archivedUsername,
            ]);

            $tenant->delete(); // soft delete

            Log::info('Tenant auto-archived', [
                'tenant_id'             => $tenant->id,
                'tenant_name'           => $tenant->name,
                'unassigned_days'       => $unassignedDays,
                'original_email'        => $originalEmail,
                'archived_email'        => $archivedEmail,
                'deletion_scheduled_at' => $deletionScheduledAt->toISOString(),
            ]);

            return true;
        });
    }

    /**
     * Tombstone the email so the original can be re-registered.
     * Idempotent — already-archived emails are returned unchanged.
     */
    protected function getArchivedEmail(?string $originalEmail, int $userId): ?string
    {
        if (empty($originalEmail)) {
            return null;
        }

        if (strpos($originalEmail, '.archived.') !== false) {
            return $originalEmail;
        }

        $parts    = explode('@', $originalEmail);
        $username = $parts[0];
        $domain   = $parts[1] ?? 'example.com';

        return $username . '.archived.' . $userId . '@' . $domain;
    }

    /**
     * Tombstone the username. Idempotent.
     */
    protected function getArchivedUsername(?string $originalUsername, int $userId): ?string
    {
        if (empty($originalUsername)) {
            return null;
        }

        if (strpos($originalUsername, '.archived.') !== false) {
            return $originalUsername;
        }

        return $originalUsername . '.archived.' . $userId;
    }
}