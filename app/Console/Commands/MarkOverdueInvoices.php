<?php

namespace App\Console\Commands;

use App\Models\PropertyUnitInvoice;
use Illuminate\Console\Command;

class MarkOverdueInvoices extends Command
{
    protected $signature = 'invoices:mark-overdue';
    protected $description = 'Mark past-due pending/partial invoices as overdue';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('leases.invoice.mark_overdue_after_days', 1));

        $count = PropertyUnitInvoice::whereIn('status', [
                PropertyUnitInvoice::STATUS_PENDING,
                PropertyUnitInvoice::STATUS_PARTIAL,
            ])
            ->whereNotNull('due_date')
            ->where('due_date', '<', $cutoff)
            ->update(['status' => PropertyUnitInvoice::STATUS_OVERDUE]);

        $this->info("Marked {$count} invoice(s) as overdue.");

        return self::SUCCESS;
    }
}