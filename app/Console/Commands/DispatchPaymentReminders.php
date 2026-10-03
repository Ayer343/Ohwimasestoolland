<?php

namespace App\Console\Commands;

use App\Jobs\SendPaymentReminderJob;
use App\Models\InvoiceReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispatchPaymentReminders extends Command
{
    protected $signature   = 'billing:dispatch-reminders {--dry-run : Show what would be sent without sending}';
    protected $description = 'Send any payment reminders that are due today.';

    public function handle(): int
    {
        $query = InvoiceReminder::query()
            ->where('status', 'pending')
            ->whereDate('scheduled_for', '<=', now()->toDateString())
            ->with(['invoice', 'superAdmin']);

        $reminders = $query->get();

        if ($reminders->isEmpty()) {
            $this->info('No payment reminders due.');
            return self::SUCCESS;
        }

        $this->info("Found {$reminders->count()} reminder(s) due.");

        foreach ($reminders as $reminder) {
            if ($this->option('dry-run')) {
                $this->line(sprintf(
                    '[dry-run] Would send reminder #%d for invoice %s (%d days before due)',
                    $reminder->id,
                    $reminder->invoice->invoice_number ?? 'N/A',
                    $reminder->days_before_due
                ));
                continue;
            }

            SendPaymentReminderJob::dispatch($reminder->id);

            Log::info('Dispatched payment reminder job', [
                'reminder_id' => $reminder->id,
                'invoice_id'  => $reminder->invoice_id,
            ]);
        }

        $this->info('Reminder jobs dispatched.');
        return self::SUCCESS;
    }
}