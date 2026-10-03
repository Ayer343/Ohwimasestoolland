<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\YearEndArchiveService;
use App\Models\SystemSetting;

class ProcessYearEndArchive extends Command
{
    protected $signature = 'invoices:year-end-archive 
                            {--type=both : Type of invoices to archive (landlord, tenant, both)}
                            {--year= : Year to archive (defaults to previous year)}
                            {--force : Force run even if not scheduled}
                            {--dry-run : Preview without making changes}';
    
    protected $description = 'Process year-end archiving of paid invoices for landlords and/or tenants';
    
    protected $archiveService;
    
    public function __construct(YearEndArchiveService $archiveService)
    {
        parent::__construct();
        $this->archiveService = $archiveService;
    }
    
    public function handle()
    {
        $type = $this->option('type');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');
        $year = $this->option('year') ?? now()->subYear()->year;
        
        // Validate type
        if (!in_array($type, ['landlord', 'tenant', 'both'])) {
            $this->error('Invalid type. Use: landlord, tenant, or both');
            return 1;
        }
        
        $settings = SystemSetting::getSettings();
        
        // Check if archiving is enabled for each type
        $landlordEnabled = $settings->enable_year_end_archive_landlord ?? true;
        $tenantEnabled = $settings->enable_year_end_archive_tenant ?? true;
        
        if ($type === 'landlord' && !$landlordEnabled && !$force) {
            $this->error('Landlord year-end archiving is disabled in system settings');
            return 1;
        }
        
        if ($type === 'tenant' && !$tenantEnabled && !$force) {
            $this->error('Tenant year-end archiving is disabled in system settings');
            return 1;
        }
        
        if ($type === 'both' && !$landlordEnabled && !$tenantEnabled && !$force) {
            $this->error('Both landlord and tenant year-end archiving are disabled in system settings');
            return 1;
        }
        
        $results = [];
        
        // Process Landlord Invoices
        if ($type === 'landlord' || $type === 'both') {
            if ($dryRun) {
                $this->previewLandlordArchive($year);
            } else {
                $results['landlord'] = $this->processLandlordArchive($year, $force);
            }
        }
        
        // Process Tenant Invoices
        if ($type === 'tenant' || $type === 'both') {
            if ($dryRun) {
                $this->previewTenantArchive($year);
            } else {
                $results['tenant'] = $this->processTenantArchive($year, $force);
            }
        }
        
        // Display summary if both types were processed
        if ($type === 'both' && !$dryRun && count($results) > 0) {
            $this->displayCombinedSummary($results, $year);
        }
        
        return 0;
    }
    
    /**
     * Preview landlord archive without making changes
     */
    private function previewLandlordArchive(int $year): void
    {
        $this->info("📊 DRY RUN - Previewing landlord year-end archive for {$year}");
        $this->line("");
        
        $stats = $this->archiveService->getYearEndStatistics($year, YearEndArchiveService::TYPE_LANDLORD);
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Type', 'Landlord Invoices'],
                ['Year', $stats['year']],
                ['Total Invoices', $stats['total_invoices']],
                ['Paid Invoices (to archive)', $stats['paid_invoices']],
                ['Unpaid Invoices (to keep)', $stats['unpaid_invoices']],
                ['Already Year-End Archived', $stats['year_end_archived']],
                ['Archived in Archive Table', $stats['archived_in_archive']],
                ['Post-Payment Archived', $stats['post_payment_archived']],
                ['Retention Period', "{$stats['retention_period_months']} months"],
                ['Next Archive Date', $stats['next_archive_date']]
            ]
        );
        
        // Get sample of invoices to be archived
        $invoicesToArchive = \App\Models\Invoice::fromYear($year)
            ->where('status', \App\Models\Invoice::STATUS_PAID)
            ->whereNull('year_end_archived_at')
            ->with(['property', 'property.landlord'])
            ->limit(10)
            ->get();
        
        if ($invoicesToArchive->count() > 0) {
            $this->line("");
            $this->info("Sample of invoices that will be archived:");
            
            $sampleData = [];
            foreach ($invoicesToArchive as $invoice) {
                $sampleData[] = [
                    $invoice->invoice_number,
                    $invoice->property->property_name ?? $invoice->property->street_name,
                    $invoice->property->landlord->name ?? 'Unknown',
                    $invoice->period,
                    $invoice->total_amount,
                    $invoice->payment_date?->format('Y-m-d') ?? 'N/A'
                ];
            }
            
            $this->table(
                ['Invoice #', 'Property', 'Landlord', 'Period', 'Amount', 'Payment Date'],
                $sampleData
            );
            
            if ($invoicesToArchive->count() === 10) {
                $this->line("... and more");
            }
        }
        
        // Get sample of invoices that will be kept (unpaid)
        $unpaidInvoices = \App\Models\Invoice::fromYear($year)
            ->where('status', '!=', \App\Models\Invoice::STATUS_PAID)
            ->whereNull('year_end_archived_at')
            ->with(['property', 'property.landlord'])
            ->limit(10)
            ->get();
        
        if ($unpaidInvoices->count() > 0) {
            $this->line("");
            $this->info("Sample of unpaid invoices that will be kept active:");
            
            $sampleData = [];
            foreach ($unpaidInvoices as $invoice) {
                $sampleData[] = [
                    $invoice->invoice_number,
                    $invoice->property->property_name ?? $invoice->property->street_name,
                    $invoice->property->landlord->name ?? 'Unknown',
                    $invoice->period,
                    $invoice->total_amount,
                    $invoice->due_date->format('Y-m-d')
                ];
            }
            
            $this->table(
                ['Invoice #', 'Property', 'Landlord', 'Period', 'Amount', 'Due Date'],
                $sampleData
            );
        }
        
        $this->line("");
        $this->warn("This is a DRY RUN. No changes were made.");
    }
    
    /**
     * Preview tenant archive without making changes
     */
    private function previewTenantArchive(int $year): void
    {
        $this->info("📊 DRY RUN - Previewing tenant year-end archive for {$year}");
        $this->line("");
        
        $stats = $this->archiveService->getYearEndStatistics($year, YearEndArchiveService::TYPE_TENANT);
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Type', 'Tenant Invoices'],
                ['Year', $stats['year']],
                ['Total Invoices', $stats['total_invoices']],
                ['Paid Invoices (to archive)', $stats['paid_invoices']],
                ['Unpaid Invoices (to keep)', $stats['unpaid_invoices']],
                ['Already Year-End Archived', $stats['year_end_archived']],
                ['Archived in Archive Table', $stats['archived_in_archive']],
                ['Post-Payment Archived', $stats['post_payment_archived']],
                ['Retention Period', "{$stats['retention_period_months']} months"],
                ['Next Archive Date', $stats['next_archive_date']]
            ]
        );
        
        // Get sample of invoices to be archived
        $invoicesToArchive = \App\Models\TenantInvoice::fromYear($year)
            ->where('status', \App\Models\TenantInvoice::STATUS_PAID)
            ->whereNull('year_end_archived_at')
            ->with(['tenant', 'propertyUnit.property'])
            ->limit(10)
            ->get();
        
        if ($invoicesToArchive->count() > 0) {
            $this->line("");
            $this->info("Sample of tenant invoices that will be archived:");
            
            $sampleData = [];
            foreach ($invoicesToArchive as $invoice) {
                $sampleData[] = [
                    $invoice->invoice_number,
                    $invoice->tenant->name ?? 'Unknown',
                    $invoice->propertyUnit->property->property_name ?? $invoice->propertyUnit->unit_number,
                    $invoice->period,
                    $invoice->total_amount,
                    $invoice->payment_date?->format('Y-m-d') ?? 'N/A'
                ];
            }
            
            $this->table(
                ['Invoice #', 'Tenant', 'Property Unit', 'Period', 'Amount', 'Payment Date'],
                $sampleData
            );
            
            if ($invoicesToArchive->count() === 10) {
                $this->line("... and more");
            }
        }
        
        // Get sample of invoices that will be kept (unpaid)
        $unpaidInvoices = \App\Models\TenantInvoice::fromYear($year)
            ->where('status', '!=', \App\Models\TenantInvoice::STATUS_PAID)
            ->whereNull('year_end_archived_at')
            ->with(['tenant', 'propertyUnit.property'])
            ->limit(10)
            ->get();
        
        if ($unpaidInvoices->count() > 0) {
            $this->line("");
            $this->info("Sample of unpaid tenant invoices that will be kept active:");
            
            $sampleData = [];
            foreach ($unpaidInvoices as $invoice) {
                $sampleData[] = [
                    $invoice->invoice_number,
                    $invoice->tenant->name ?? 'Unknown',
                    $invoice->propertyUnit->property->property_name ?? $invoice->propertyUnit->unit_number,
                    $invoice->period,
                    $invoice->total_amount,
                    $invoice->due_date->format('Y-m-d')
                ];
            }
            
            $this->table(
                ['Invoice #', 'Tenant', 'Property Unit', 'Period', 'Amount', 'Due Date'],
                $sampleData
            );
        }
        
        $this->line("");
        $this->warn("This is a DRY RUN. No changes were made.");
    }
    
    /**
     * Process landlord archive
     */
    private function processLandlordArchive(int $year, bool $force): array
    {
        $this->info("🏠 Processing landlord year-end archive for {$year}...");
        
        if ($force) {
            $this->warn("Force mode enabled - will run even if disabled in settings");
        }
        
        $results = $this->archiveService->processYearEndArchiveLandlord($year);
        
        $this->line("");
        $this->info("✅ Landlord year-end archiving completed for {$year}");
        $this->info("📊 Results:");
        $this->info("   - Total invoices processed: {$results['total_invoices']}");
        $this->info("   - Archived (paid): {$results['paid_archived']}");
        $this->info("   - Kept active (unpaid): {$results['unpaid_kept']}");
        
        if ($results['errors'] > 0) {
            $this->error("   - Errors: {$results['errors']}");
        }
        
        // Show detailed breakdown if verbose
        if ($this->getOutput()->isVerbose()) {
            $this->line("");
            $this->info("Detailed breakdown:");
            
            if (!empty($results['details']['paid_invoices'])) {
                $this->line("");
                $this->info("Archived invoices:");
                foreach ($results['details']['paid_invoices'] as $invoice) {
                    $this->line("   - Invoice #{$invoice['invoice_number']}: {$invoice['landlord_name']} - {$invoice['amount']}");
                }
            }
            
            if (!empty($results['details']['unpaid_invoices'])) {
                $this->line("");
                $this->info("Unpaid invoices kept active:");
                foreach ($results['details']['unpaid_invoices'] as $invoice) {
                    $this->line("   - Invoice #{$invoice['invoice_number']}: {$invoice['landlord_name']} - {$invoice['amount']} (due: {$invoice['due_date']})");
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Process tenant archive
     */
    private function processTenantArchive(int $year, bool $force): array
    {
        $this->info("👥 Processing tenant year-end archive for {$year}...");
        
        if ($force) {
            $this->warn("Force mode enabled - will run even if disabled in settings");
        }
        
        $results = $this->archiveService->processYearEndArchiveTenant($year);
        
        $this->line("");
        $this->info("✅ Tenant year-end archiving completed for {$year}");
        $this->info("📊 Results:");
        $this->info("   - Total invoices processed: {$results['total_invoices']}");
        $this->info("   - Archived (paid): {$results['paid_archived']}");
        $this->info("   - Kept active (unpaid): {$results['unpaid_kept']}");
        
        if ($results['errors'] > 0) {
            $this->error("   - Errors: {$results['errors']}");
        }
        
        // Show detailed breakdown if verbose
        if ($this->getOutput()->isVerbose()) {
            $this->line("");
            $this->info("Detailed breakdown:");
            
            if (!empty($results['details']['paid_invoices'])) {
                $this->line("");
                $this->info("Archived invoices:");
                foreach ($results['details']['paid_invoices'] as $invoice) {
                    $this->line("   - Invoice #{$invoice['invoice_number']}: {$invoice['tenant_name']} - {$invoice['amount']}");
                }
            }
            
            if (!empty($results['details']['unpaid_invoices'])) {
                $this->line("");
                $this->info("Unpaid invoices kept active:");
                foreach ($results['details']['unpaid_invoices'] as $invoice) {
                    $this->line("   - Invoice #{$invoice['invoice_number']}: {$invoice['tenant_name']} - {$invoice['amount']} (due: {$invoice['due_date']})");
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Display combined summary for both types
     */
    private function displayCombinedSummary(array $results, int $year): void
    {
        $this->line("");
        $this->info("📊 COMBINED YEAR-END ARCHIVE SUMMARY FOR {$year}");
        $this->line(str_repeat('=', 60));
        
        // Landlord Summary
        if (isset($results['landlord'])) {
            $landlord = $results['landlord'];
            $this->line("");
            $this->info("🏠 LANDLORD INVOICES:");
            $this->line("   Total processed: {$landlord['total_invoices']}");
            $this->line("   Archived (paid): {$landlord['paid_archived']}");
            $this->line("   Kept active (unpaid): {$landlord['unpaid_kept']}");
            if ($landlord['errors'] > 0) {
                $this->error("   Errors: {$landlord['errors']}");
            }
        }
        
        // Tenant Summary
        if (isset($results['tenant'])) {
            $tenant = $results['tenant'];
            $this->line("");
            $this->info("👥 TENANT INVOICES:");
            $this->line("   Total processed: {$tenant['total_invoices']}");
            $this->line("   Archived (paid): {$tenant['paid_archived']}");
            $this->line("   Kept active (unpaid): {$tenant['unpaid_kept']}");
            if ($tenant['errors'] > 0) {
                $this->error("   Errors: {$tenant['errors']}");
            }
        }
        
        // Combined Totals
        $totalProcessed = ($results['landlord']['total_invoices'] ?? 0) + ($results['tenant']['total_invoices'] ?? 0);
        $totalArchived = ($results['landlord']['paid_archived'] ?? 0) + ($results['tenant']['paid_archived'] ?? 0);
        $totalKept = ($results['landlord']['unpaid_kept'] ?? 0) + ($results['tenant']['unpaid_kept'] ?? 0);
        $totalErrors = ($results['landlord']['errors'] ?? 0) + ($results['tenant']['errors'] ?? 0);
        
        $this->line("");
        $this->line(str_repeat('-', 60));
        $this->info("📈 COMBINED TOTALS:");
        $this->line("   Total invoices processed: {$totalProcessed}");
        $this->line("   Total archived: {$totalArchived}");
        $this->line("   Total kept active: {$totalKept}");
        if ($totalErrors > 0) {
            $this->error("   Total errors: {$totalErrors}");
        }
        $this->line(str_repeat('=', 60));
    }
}