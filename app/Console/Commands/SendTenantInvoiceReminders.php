<?php

namespace App\Console\Commands;

use App\Http\Controllers\TenantInvoiceController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendTenantInvoiceReminders extends Command
{
    protected $signature = 'tenant-invoices:send-reminders
                            {--dry-run : Show what would be sent without sending}';

    protected $description = 'Send scheduled payment reminders for tenant invoices';

    public function handle(TenantInvoiceController $controller): int
    {
        $this->info('Dispatching tenant invoice reminders...');

        try {
            $result = $controller->sendScheduledPaymentReminders(
                (bool) $this->option('dry-run')
            );

            $this->info("✅ Sent {$result['sent']} reminder(s).");
            $this->line("   Skipped: {$result['skipped']}");
            $this->line("   Failed:  {$result['failed']}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Exception: ' . $e->getMessage());
            Log::error('[tenant-invoices:send-reminders] Exception', [
                'error' => $e->getMessage(),
            ]);
            return self::FAILURE;
        }
    }
}