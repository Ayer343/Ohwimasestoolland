<?php

namespace App\Console\Commands;

use App\Models\UserEmailAccount;
use App\Services\EmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncEmailAccounts extends Command
{
    protected $signature = 'email:sync
                            {--account= : Sync only this account ID}
                            {--limit=5 : Max messages per account}';

    protected $description = 'Sync IMAP email accounts for new messages';

    public function handle(EmailService $emailService): int
    {
        $query = UserEmailAccount::query()
            ->where('status', 'verified')
            ->where('is_connected', true);

        // Optional: only sync accounts whose next_sync_at is due
        $query->where(function ($q) {
            $q->whereNull('next_sync_at')
              ->orWhere('next_sync_at', '<=', now());
        });

        if ($accountId = $this->option('account')) {
            $query->where('id', $accountId);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->info('No accounts due for sync.');
            return self::SUCCESS;
        }

        $this->info("Syncing {$accounts->count()} account(s)…");

        foreach ($accounts as $account) {
            $this->line(" → {$account->email}");

            try {
                $result = $emailService->syncAccount($account);

                if ($result['success']) {
                    $this->info("   ✓ Fetched {$result['fetched']} new message(s)");
                } else {
                    $this->error("   ✗ {$result['message']}");
                }
            } catch (\Throwable $e) {
                Log::error('Scheduled sync failed', [
                    'account_id' => $account->id,
                    'error'      => $e->getMessage(),
                ]);
                $this->error("   ✗ {$e->getMessage()}");
            }

            // Update next_sync_at based on frequency
            $account->updateSyncTimestamp();
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}