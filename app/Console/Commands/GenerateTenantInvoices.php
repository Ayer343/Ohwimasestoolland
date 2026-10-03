<?php

namespace App\Console\Commands;

use App\Http\Controllers\TenantInvoiceController;
use App\Models\SystemSetting;
use App\Services\TenantInvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateTenantInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Usage:
     *   php artisan tenant-invoices:generate
     *   php artisan tenant-invoices:generate --period=2026-03
     *   php artisan tenant-invoices:generate --force
     *   php artisan tenant-invoices:generate --no-notifications
     */
    protected $signature = 'tenant-invoices:generate
                            {--period= : The billing period in Y-m format (defaults to current month)}
                            {--force : Generate even if auto_generate_tenant_invoices is disabled}
                            {--no-notifications : Skip sending creation notifications}';

    protected $description = 'Generate monthly tenant invoices based on system settings';

    public function handle(TenantInvoiceService $service): int
    {
        $settings = SystemSetting::getSettings();

        if (!$settings) {
            $this->error('System settings not found. Aborting.');
            return self::FAILURE;
        }

        // Gate 1: tenant invoicing must be enabled
        if (!$settings->enable_tenant_invoicing) {
            $this->warn('Tenant invoicing is disabled in system settings. Skipping.');
            Log::info('[tenant-invoices:generate] Skipped — enable_tenant_invoicing = false');
            return self::SUCCESS;
        }

        // Gate 2: auto-generation must be enabled unless --force
        $force = (bool) $this->option('force');
        if (!$settings->auto_generate_tenant_invoices && !$force) {
            $this->warn('Auto-generation is disabled. Use --force to override.');
            Log::info('[tenant-invoices:generate] Skipped — auto_generate_tenant_invoices = false');
            return self::SUCCESS;
        }

        $period = $this->option('period') ?: now()->startOfMonth()->format('Y-m');

        // Validate period format
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error("Invalid --period format: {$period}. Expected Y-m (e.g. 2026-03).");
            return self::INVALID;
        }

        $sendNotifications = !$this->option('no-notifications')
            && (bool) ($settings->send_tenant_payment_reminders ?? false);

        $this->info("Generating tenant invoices for period {$period}...");
        $this->line('   Auto-generation enabled: ' . ($settings->auto_generate_tenant_invoices ? 'yes' : 'no'));
        $this->line('   Force: ' . ($force ? 'yes' : 'no'));
        $this->line('   Send notifications: ' . ($sendNotifications ? 'yes' : 'no'));

        try {
            $result = $service->generateMonthlyTenantInvoices(
                $period,
                $sendNotifications,
                'community_dues',
                $force
            );

            if (!($result['success'] ?? false)) {
                $this->error('Generation failed: ' . ($result['message'] ?? 'Unknown error'));
                Log::error('[tenant-invoices:generate] Service returned failure', [
                    'period' => $period,
                    'result' => $result,
                ]);
                return self::FAILURE;
            }

            $generated = $result['details']['generated_count'] ?? 0;
            $skipped   = $result['details']['skipped_count']   ?? 0;
            $total     = $result['details']['total_amount']    ?? 0;

            $this->info("✅ Generated {$generated} invoice(s).");
            $this->line("   Skipped (already exist): {$skipped}");
            $this->line('   Total amount: ' . $settings->formatAmount($total));

            Log::info('[tenant-invoices:generate] Completed', [
                'period'      => $period,
                'generated'   => $generated,
                'skipped'     => $skipped,
                'total'       => $total,
                'triggered_by' => 'console',
            ]);

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Exception: ' . $e->getMessage());
            Log::error('[tenant-invoices:generate] Exception', [
                'period' => $period,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }
}