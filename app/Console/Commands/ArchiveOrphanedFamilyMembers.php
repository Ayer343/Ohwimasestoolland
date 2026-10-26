<?php
// app/Console/Commands/ArchiveOrphanedFamilyMembers.php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FamilyLinkOrphanArchivalService;
use Illuminate\Console\Command;

class ArchiveOrphanedFamilyMembers extends Command
{
    protected $signature = 'family-links:archive-orphans
                            {--dry-run : Report what would be archived without doing it}';

    protected $description = 'Archive family members who lost all property links and own no properties.';

    public function handle(FamilyLinkOrphanArchivalService $service): int
    {
        $dryRun = $this->option('dry-run');

        // Candidates: users with the linked_family role, not trashed,
        // not privileged, no owned properties, no approved links.
        $candidates = User::query()
            ->whereNull('deleted_at')
            ->whereNotIn('type', [
                User::TYPE_SUPER_ADMIN,
                User::TYPE_ADMIN,
                User::TYPE_DEVELOPER,
            ])
            ->where(function ($q) {
                $q->whereHas('roles', fn ($r) => $r->where('slug', 'linked_family'))
                  ->orWhereHas('familyLinks');
            })
            ->whereDoesntHave('ownedProperties')
            ->whereDoesntHave('approvedFamilyLinks')
            ->get();

        $this->info("Found {$candidates->count()} orphan candidate(s).");

        $archived = 0;
        foreach ($candidates as $user) {
            if (!$service->isEligibleForOrphanArchival($user)) {
                continue;
            }

            if ($dryRun) {
                $this->line("  • [dry-run] Would archive: {$user->name} <{$user->email}> (id={$user->id})");
                continue;
            }

            if ($service->considerArchival($user)) {
                $archived++;
                $this->line("  ✓ Archived: {$user->name} <{$user->email}>");
            }
        }

        $this->info($dryRun
            ? 'Dry run complete.'
            : "Archived {$archived} of {$candidates->count()} candidate(s)."
        );

        return self::SUCCESS;
    }
}