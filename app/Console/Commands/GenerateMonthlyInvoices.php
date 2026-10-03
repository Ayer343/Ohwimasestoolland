<?php

namespace App\Console\Commands;

use App\Models\AdminBillingRecord;
use App\Models\BillingInvoice;
use App\Models\DeveloperSetting;
use App\Models\InvoiceReminder;
use App\Models\SuperAdminPaymentRecord;
use App\Services\DeveloperBillingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:generate-monthly-invoices 
                            {--date= : The billing date (Y-m-d format, defaults to today)}
                            {--force : Force regenerate invoices even if they exist}
                            {--dry-run : Preview what would be generated without actually creating}
                            {--developer= : Specific developer setting ID to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate monthly invoices ONLY for primary super admin agreements (shared billing model)';

    /**
     * @var DeveloperBillingService
     */
    protected DeveloperBillingService $billingService;

    /**
     * Create a new command instance.
     */
    public function __construct(DeveloperBillingService $billingService)
    {
        parent::__construct();
        $this->billingService = $billingService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting monthly invoice generation for primary super admins...');
        $this->newLine();

        $billingDate = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::now();

        $billingMonth = $billingDate->format('Y-m');
        $isDryRun = $this->option('dry-run');
        $forceRegenerate = $this->option('force');
        $specificDeveloper = $this->option('developer');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE: No invoices will be created');
            $this->newLine();
        }

        // Get all active developer settings
        $developerQuery = DeveloperSetting::where('billing_status', 'active');

        if ($specificDeveloper) {
            $developerQuery->where('id', $specificDeveloper);
            $this->info("Processing only developer ID: {$specificDeveloper}");
        }

        $developers = $developerQuery->get();

        if ($developers->isEmpty()) {
            $this->error('No active developer settings found.');
            return self::FAILURE;
        }

        $this->info("Found {$developers->count()} active developer(s) to process for billing month: {$billingMonth}");
        $this->newLine();

        $stats = [
            'total_developers'      => $developers->count(),
            'invoices_generated'    => 0,
            'invoices_skipped'      => 0,
            'invoices_zero_balance' => 0,
            'errors'                => 0,
        ];

        foreach ($developers as $developer) {
            $this->info("Processing developer: {$developer->developer_name} (ID: {$developer->id})");
            $this->line(str_repeat('-', 50));

            try {
                // Get the primary agreement for this developer
                $primaryAgreement = AdminBillingRecord::where('developer_setting_id', $developer->id)
                    ->where('is_primary_for_billing', true)
                    ->where('status', 'active')
                    ->first();

                if (!$primaryAgreement) {
                    $this->warn("  ⚠  No primary super admin assigned for developer {$developer->developer_name}. Skipping...");
                    $stats['invoices_skipped']++;
                    $this->newLine();
                    continue;
                }

                $this->line("  Primary Super Admin: {$primaryAgreement->superAdmin?->name} (ID: {$primaryAgreement->super_admin_id})");
                $this->line("  Agreement Number: {$primaryAgreement->agreement_number}");
                $this->line("  Monthly Amount: {$primaryAgreement->currency} " . number_format($primaryAgreement->amount, 2));

                // Check if invoice already exists for this billing month
                $existingInvoice = $this->findExistingInvoice($developer->id, $primaryAgreement->id, $billingMonth);

                if ($existingInvoice && !$forceRegenerate) {
                    $this->line("  ℹ Invoice already exists for {$billingMonth}: {$existingInvoice->invoice_number}");
                    $stats['invoices_skipped']++;
                    $this->newLine();
                    continue;
                }

                // --force: remove the existing invoice + its reminders so the
                // service can recreate them cleanly (otherwise we'd orphan reminders).
                if ($existingInvoice && $forceRegenerate) {
                    $this->warn("  Force regenerating invoice for {$billingMonth}...");
                    if (!$isDryRun) {
                        $this->deleteInvoiceAndReminders($existingInvoice);
                    }
                }

                // Calculate total paid this month from all super admins
                $totalPaidThisMonth = SuperAdminPaymentRecord::where('developer_setting_id', $developer->id)
                    ->where('billing_month', $billingMonth)
                    ->sum('amount_paid');

                $amountDue = max(0, $primaryAgreement->amount - $totalPaidThisMonth);

                $this->line("  Total paid this month: {$primaryAgreement->currency} " . number_format($totalPaidThisMonth, 2));
                $this->line("  Amount due: {$primaryAgreement->currency} " . number_format($amountDue, 2));

                if ($amountDue == 0) {
                    $this->line("  ✓ Zero balance - No invoice needed");
                    $stats['invoices_zero_balance']++;
                    $this->newLine();
                    continue;
                }

                if ($isDryRun) {
                    $previewNumber = $this->previewInvoiceNumber($developer->id, $billingDate);
                    $this->info("  [DRY RUN] Would create invoice: {$previewNumber} for {$amountDue} {$primaryAgreement->currency}");
                    $this->info("  [DRY RUN] Would schedule reminders per billing_rules and dispatch invoice email.");
                    $stats['invoices_generated']++;
                    $this->newLine();
                    continue;
                }

                /*
                 * ────────────────────────────────────────────────────────
                 *  DELEGATE TO THE SERVICE
                 *
                 *  The service is the single source of truth. It will:
                 *    - enforce idempotency (returns the existing invoice if any)
                 *    - create the BillingInvoice with the correct schema columns
                 *    - dispatch SendInvoiceEmailJob to the billing contact
                 *    - schedule one InvoiceReminder per configured interval
                 *      (using channels vs channel schema detection)
                 * ────────────────────────────────────────────────────────
                 */
                $invoice = $this->billingService->generateRecurringInvoice(
                    $developer,
                    $billingMonth,
                    null // system-generated, no authenticated user
                );

                // Advance the agreement's billing tracking so the scheduler
                // doesn't re-fire this month.
                $primaryAgreement->update([
                    'last_invoice_generated_at' => now(),
                    'next_invoice_date'         => $billingDate->copy()->startOfMonth()->addMonth(),
                    'billing_month'             => $billingMonth,
                ]);

                $this->info("  ✓ Invoice created: {$invoice->invoice_number} for {$primaryAgreement->currency} " . number_format($invoice->amount, 2));

                // Report reminder count so operators can see it worked.
                $reminderCount = InvoiceReminder::where('invoice_id', $invoice->id)->count();
                if ($reminderCount > 0) {
                    $this->line("  ✓ Scheduled {$reminderCount} reminder(s) for this invoice.");
                } else {
                    $this->warn("  ⚠ No reminders scheduled — check billing_rules.send_payment_reminders.");
                }

                $stats['invoices_generated']++;

                Log::info('Monthly invoice generated', [
                    'developer_id'     => $developer->id,
                    'developer_name'   => $developer->developer_name,
                    'agreement_id'     => $primaryAgreement->id,
                    'agreement_number' => $primaryAgreement->agreement_number,
                    'invoice_number'   => $invoice->invoice_number,
                    'billing_month'    => $billingMonth,
                    'amount'           => $invoice->amount,
                    'total_paid'       => $totalPaidThisMonth,
                    'reminder_count'   => $reminderCount,
                    'command'          => 'billing:generate-monthly-invoices',
                ]);

                $this->newLine();

            } catch (\Throwable $e) {
                $this->error("  ✗ Error processing developer {$developer->developer_name}: " . $e->getMessage());
                Log::error('Failed to generate monthly invoice', [
                    'developer_id'   => $developer->id,
                    'developer_name' => $developer->developer_name,
                    'billing_month'  => $billingMonth,
                    'error'          => $e->getMessage(),
                    'trace'          => $e->getTraceAsString(),
                ]);
                $stats['errors']++;
                $this->newLine();
            }
        }

        // Display summary
        $this->newLine();
        $this->line(str_repeat('=', 60));
        $this->info('INVOICE GENERATION SUMMARY');
        $this->line(str_repeat('=', 60));
        $this->line("Billing Month: {$billingMonth}");
        $this->line("Total Developers Processed: {$stats['total_developers']}");
        $this->line("Invoices Generated: {$stats['invoices_generated']}");
        $this->line("Invoices Skipped (already exist): {$stats['invoices_skipped']}");
        $this->line("Zero Balance (no invoice needed): {$stats['invoices_zero_balance']}");
        $this->line("Errors: {$stats['errors']}");
        $this->line(str_repeat('-', 60));

        if ($isDryRun) {
            $this->newLine();
            $this->warn('DRY RUN COMPLETE: No actual invoices were created.');
            $this->info('Run without --dry-run to create invoices.');
        }

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /* ============================================================
     | HELPERS
     * ============================================================ */

    /**
     * Find an existing recurring invoice for the given developer + agreement + month.
     *
     * Schema-aware: billing_invoices may use agreement_id OR
     * admin_billing_record_id depending on migration state.
     */
    private function findExistingInvoice(int $developerId, int $agreementId, string $billingMonth): ?BillingInvoice
    {
        $query = BillingInvoice::where('developer_setting_id', $developerId)
            ->where('billing_month', $billingMonth)
            ->where('is_recurring', true);

        if (\Illuminate\Support\Facades\Schema::hasColumn('billing_invoices', 'agreement_id')) {
            $query->where('agreement_id', $agreementId);
        } elseif (\Illuminate\Support\Facades\Schema::hasColumn('billing_invoices', 'admin_billing_record_id')) {
            $query->where('admin_billing_record_id', $agreementId);
        }

        return $query->first();
    }

    /**
     * Delete an invoice plus any associated reminders so a forced
     * regeneration doesn't leave orphans pointing at a dead invoice.
     */
    private function deleteInvoiceAndReminders(BillingInvoice $invoice): void
    {
        InvoiceReminder::where('invoice_id', $invoice->id)->delete();
        $invoice->delete();
    }

    /**
     * Preview the invoice number that WOULD be generated.
     * Mirrors the service's numbering so --dry-run output is realistic.
     */
    private function previewInvoiceNumber(int $developerId, Carbon $date): string
    {
        $count = BillingInvoice::where('developer_setting_id', $developerId)
            ->whereYear('created_at', $date->year)
            ->whereMonth('created_at', $date->month)
            ->count();

        $sequence = str_pad($count + 1, 4, '0', STR_PAD_LEFT);

        return "INV-{$date->format('Ym')}-{$sequence}";
    }

    /**
     * Generate a preview of what would be generated.
     * Useful for testing before actual execution.
     *
     * @param int|null $developerId
     * @return array
     */
    public function preview(?int $developerId = null): array
    {
        $preview = [];
        $billingMonth = Carbon::now()->format('Y-m');

        $developerQuery = DeveloperSetting::where('billing_status', 'active');

        if ($developerId) {
            $developerQuery->where('id', $developerId);
        }

        $developers = $developerQuery->get();

        foreach ($developers as $developer) {
            $primaryAgreement = AdminBillingRecord::where('developer_setting_id', $developer->id)
                ->where('is_primary_for_billing', true)
                ->where('status', 'active')
                ->first();

            if (!$primaryAgreement) {
                $preview[] = [
                    'developer'   => $developer->developer_name,
                    'has_primary' => false,
                    'message'     => 'No primary super admin assigned',
                ];
                continue;
            }

            $existingInvoice = $this->findExistingInvoice($developer->id, $primaryAgreement->id, $billingMonth);

            $totalPaidThisMonth = SuperAdminPaymentRecord::where('developer_setting_id', $developer->id)
                ->where('billing_month', $billingMonth)
                ->sum('amount_paid');

            $amountDue = max(0, $primaryAgreement->amount - $totalPaidThisMonth);

            $preview[] = [
                'developer'           => $developer->developer_name,
                'developer_id'        => $developer->id,
                'has_primary'         => true,
                'primary_super_admin' => $primaryAgreement->superAdmin?->name,
                'agreement_number'    => $primaryAgreement->agreement_number,
                'billing_month'       => $billingMonth,
                'total_amount'        => $primaryAgreement->amount,
                'total_paid'          => $totalPaidThisMonth,
                'amount_due'          => $amountDue,
                'invoice_exists'      => $existingInvoice !== null,
                'will_generate'       => !$existingInvoice && $amountDue > 0,
            ];
        }

        return $preview;
    }
}