<?php

namespace App\Console\Commands;

use App\Models\DeveloperSetting;
use App\Services\DeveloperBillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class RunRecurringBilling extends Command
{
    protected $signature   = 'billing:run-recurring {--force : Run even if not due yet}';
    protected $description = 'Generate invoices for all developers whose next_billing_date has passed.';

    public function handle(DeveloperBillingService $billingService): int
    {
        $settingsList = DeveloperSetting::query()
            ->where('billing_status', 'active')
            ->where('billing_active', true)
            ->get();

        if ($settingsList->isEmpty()) {
            $this->info('No active developer billing profiles.');
            return self::SUCCESS;
        }

        foreach ($settingsList as $settings) {
            $nextDate = $settings->next_billing_date
                ? Carbon::parse($settings->next_billing_date)
                : null;

            $isDue = $this->option('force')
                || !$nextDate
                || $nextDate->lte(Carbon::today());

            if (!$isDue) {
                $this->line(sprintf(
                    'Skipping developer #%d — next billing date %s not reached.',
                    $settings->id,
                    $nextDate->toDateString()
                ));
                continue;
            }

            try {
                // Delegate the actual invoice creation to the service.
                // This is the SINGLE place that generates recurring invoices.
                $invoice = $billingService->generateRecurringInvoice($settings);

                // Advance next_billing_date.
                $newNext = $this->advanceDate(
                    $nextDate ?? Carbon::today(),
                    $settings->billing_cycle ?? 'monthly'
                );

                $settings->update([
                    'next_billing_date' => $newNext,
                ]);

                $this->info(sprintf(
                    'Generated invoice %s for developer #%d — next billing date %s.',
                    $invoice->invoice_number ?? 'N/A',
                    $settings->id,
                    $newNext->toDateString()
                ));

                Log::info('Recurring invoice generated', [
                    'developer_setting_id' => $settings->id,
                    'invoice_id'           => $invoice->id ?? null,
                    'invoice_number'       => $invoice->invoice_number ?? null,
                    'next_billing_date'    => $newNext->toDateString(),
                ]);
            } catch (\Throwable $e) {
                $this->error(sprintf(
                    'Failed to generate invoice for developer #%d: %s',
                    $settings->id,
                    $e->getMessage()
                ));

                Log::error('Recurring invoice generation failed', [
                    'developer_setting_id' => $settings->id,
                    'error'                => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }

    private function advanceDate(Carbon $from, string $cycle): Carbon
    {
        return match ($cycle) {
            'weekly'    => $from->copy()->addWeek(),
            'monthly'   => $from->copy()->addMonth(),
            'quarterly' => $from->copy()->addMonths(3),
            'yearly'    => $from->copy()->addYear(),
            default     => $from->copy()->addMonth(),
        };
    }
}