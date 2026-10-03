<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\YearEndArchiveService;
use App\Models\SystemSetting;

class ProcessPostPaymentArchive extends Command
{
    protected $signature = 'invoices:post-payment-archive 
                            {--type=both : Type of invoices to archive (landlord, tenant, both)}
                            {--dry-run : Preview without making changes}
                            {--force : Force run even if auto-archiving is disabled}';
    
    protected $description = 'Archive invoices that were paid after year-end processing for landlords and/or tenants';
    
    protected $archiveService;
    
    public function __construct(YearEndArchiveService $archiveService)
    {
        parent::__construct();
        $this->archiveService = $archiveService;
    }
    
    public function handle()
    {
        $type = $this->option('type');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        
        // Validate type
        if (!in_array($type, ['landlord', 'tenant', 'both'])) {
            $this->error('Invalid type. Use: landlord, tenant, or both');
            return 1;
        }
        
        $settings = SystemSetting::getSettings();
        
        // Check if post-payment archiving is enabled for each type
        $landlordEnabled = $settings->auto_archive_paid_after_retention_landlord ?? true;
        $tenantEnabled = $settings->auto_archive_paid_after_retention_tenant ?? true;
        
        if ($type === 'landlord' && !$landlordEnabled && !$force) {
            $this->error('Landlord post-payment archiving is disabled in system settings');
            return 1;
        }
        
        if ($type === 'tenant' && !$tenantEnabled && !$force) {
            $this->error('Tenant post-payment archiving is disabled in system settings');
            return 1;
        }
        
        if ($type === 'both' && !$landlordEnabled && !$tenantEnabled && !$force) {
            $this->error('Both landlord and tenant post-payment archiving are disabled in system settings');
            return 1;
        }
        
        $results = [];
        
        // Process Landlord Invoices
        if ($type === 'landlord' || $type === 'both') {
            if ($dryRun) {
                $this->previewLandlordPostPaymentArchive();
            } else {
                $results['landlord'] = $this->processLandlordPostPaymentArchive($force);
            }
        }
        
        // Process Tenant Invoices
        if ($type === 'tenant' || $type === 'both') {
            if ($dryRun) {
                $this->previewTenantPostPaymentArchive();
            } else {
                $results['tenant'] = $this->processTenantPostPaymentArchive($force);
            }
        }
        
        // Display summary if both types were processed
        if ($type === 'both' && !$dryRun && count($results) > 0) {
            $this->displayCombinedSummary($results);
        }
        
        return 0;
    }
    
    /**
     * Preview landlord post-payment archiving without making changes
     */
    private function previewLandlordPostPaymentArchive(): void
    {
        $this->info("📊 DRY RUN - Previewing landlord post-payment archiving");
        $this->line("");
        
        $settings = SystemSetting::getSettings();
        $retentionMonths = $settings->paid_invoice_retention_months_landlord ?? 3;
        $cutoffDate = now()->subMonths($retentionMonths);
        
        $this->info("Retention period: {$retentionMonths} months");
        $this->info("Cutoff date: {$cutoffDate->format('Y-m-d')}");
        $this->line("");
        
        $invoices = \App\Models\Invoice::where('status', \App\Models\Invoice::STATUS_PAID)
            ->whereNotNull('year_end_archived_at')
            ->whereNull('deleted_at')
            ->whereNotNull('payment_date')
            ->where('payment_date', '<=', $cutoffDate)
            ->with(['property', 'property.landlord'])
            ->get();
        
        $totalAmount = $invoices->sum('total_amount');
        
        $this->info("Found {$invoices->count()} landlord invoices eligible for post-payment archiving");
        $this->info("Total amount: " . $settings->formatAmount($totalAmount));
        $this->line("");
        
        if ($invoices->count() > 0) {
            // Group by year for better organization
            $groupedByYear = $invoices->groupBy('year_end_archive_year');
            
            foreach ($groupedByYear as $year => $yearInvoices) {
                $this->info("Year {$year}: {$yearInvoices->count()} invoices");
                
                $sampleData = [];
                foreach ($yearInvoices->take(10) as $invoice) {
                    $sampleData[] = [
                        $invoice->invoice_number,
                        $invoice->property->property_name ?? $invoice->property->street_name,
                        $invoice->property->landlord->name ?? 'Unknown',
                        $invoice->period,
                        $invoice->total_amount,
                        $invoice->payment_date->format('Y-m-d')
                    ];
                }
                
                $this->table(
                    ['Invoice #', 'Property', 'Landlord', 'Period', 'Amount', 'Payment Date'],
                    $sampleData
                );
                
                if ($yearInvoices->count() > 10) {
                    $this->line("... and " . ($yearInvoices->count() - 10) . " more invoices from {$year}");
                }
                $this->line("");
            }
            
            // Show summary statistics
            $this->info("Summary by year:");
            $summaryData = [];
            foreach ($groupedByYear as $year => $yearInvoices) {
                $summaryData[] = [
                    $year,
                    $yearInvoices->count(),
                    $settings->formatAmount($yearInvoices->sum('total_amount'))
                ];
            }
            
            $this->table(
                ['Year', 'Count', 'Total Amount'],
                $summaryData
            );
        } else {
            $this->info("No landlord invoices found matching the criteria.");
            $this->line("");
            $this->info("To be eligible, invoices must:");
            $this->info("  ✓ Be paid");
            $this->info("  ✓ Have been year-end processed (kept unpaid)");
            $this->info("  ✓ Have payment date older than {$retentionMonths} months");
            $this->info("  ✓ Not already archived");
        }
        
        $this->line("");
        $this->warn("This is a DRY RUN. No changes were made.");
    }
    
    /**
     * Preview tenant post-payment archiving without making changes
     */
    private function previewTenantPostPaymentArchive(): void
    {
        $this->info("📊 DRY RUN - Previewing tenant post-payment archiving");
        $this->line("");
        
        $settings = SystemSetting::getSettings();
        $retentionMonths = $settings->paid_invoice_retention_months_tenant ?? 3;
        $cutoffDate = now()->subMonths($retentionMonths);
        
        $this->info("Retention period: {$retentionMonths} months");
        $this->info("Cutoff date: {$cutoffDate->format('Y-m-d')}");
        $this->line("");
        
        $invoices = \App\Models\TenantInvoice::where('status', \App\Models\TenantInvoice::STATUS_PAID)
            ->whereNotNull('year_end_archived_at')
            ->whereNull('deleted_at')
            ->whereNotNull('payment_date')
            ->where('payment_date', '<=', $cutoffDate)
            ->with(['tenant', 'propertyUnit.property'])
            ->get();
        
        $totalAmount = $invoices->sum('total_amount');
        
        $this->info("Found {$invoices->count()} tenant invoices eligible for post-payment archiving");
        $this->info("Total amount: " . $settings->formatAmount($totalAmount));
        $this->line("");
        
        if ($invoices->count() > 0) {
            // Group by year for better organization
            $groupedByYear = $invoices->groupBy('year_end_archive_year');
            
            foreach ($groupedByYear as $year => $yearInvoices) {
                $this->info("Year {$year}: {$yearInvoices->count()} invoices");
                
                $sampleData = [];
                foreach ($yearInvoices->take(10) as $invoice) {
                    $sampleData[] = [
                        $invoice->invoice_number,
                        $invoice->tenant->name ?? 'Unknown',
                        $invoice->propertyUnit->property->property_name ?? $invoice->propertyUnit->unit_number,
                        $invoice->period,
                        $invoice->total_amount,
                        $invoice->payment_date->format('Y-m-d')
                    ];
                }
                
                $this->table(
                    ['Invoice #', 'Tenant', 'Property Unit', 'Period', 'Amount', 'Payment Date'],
                    $sampleData
                );
                
                if ($yearInvoices->count() > 10) {
                    $this->line("... and " . ($yearInvoices->count() - 10) . " more invoices from {$year}");
                }
                $this->line("");
            }
            
            // Show summary statistics
            $this->info("Summary by year:");
            $summaryData = [];
            foreach ($groupedByYear as $year => $yearInvoices) {
                $summaryData[] = [
                    $year,
                    $yearInvoices->count(),
                    $settings->formatAmount($yearInvoices->sum('total_amount'))
                ];
            }
            
            $this->table(
                ['Year', 'Count', 'Total Amount'],
                $summaryData
            );
        } else {
            $this->info("No tenant invoices found matching the criteria.");
            $this->line("");
            $this->info("To be eligible, invoices must:");
            $this->info("  ✓ Be paid");
            $this->info("  ✓ Have been year-end processed (kept unpaid)");
            $this->info("  ✓ Have payment date older than {$retentionMonths} months");
            $this->info("  ✓ Not already archived");
        }
        
        $this->line("");
        $this->warn("This is a DRY RUN. No changes were made.");
    }
    
    /**
     * Process landlord post-payment archive
     */
    private function processLandlordPostPaymentArchive(bool $force): array
    {
        $this->info("🏠 Processing landlord post-payment archiving...");
        
        if ($force) {
            $this->warn("Force mode enabled - will run even if disabled in settings");
        }
        
        $results = $this->archiveService->processPostPaymentArchiveLandlord();
        
        $this->line("");
        $this->info("✅ Landlord post-payment archiving completed");
        $this->info("📊 Results:");
        $this->info("   - Invoices processed: {$results['total']}");
        $this->info("   - Archived: {$results['archived']}");
        
        if ($results['errors'] > 0) {
            $this->error("   - Errors: {$results['errors']}");
        }
        
        // Show detailed breakdown if verbose
        if ($this->getOutput()->isVerbose() && !empty($results['details'])) {
            $this->line("");
            $this->info("Archived invoices:");
            foreach ($results['details'] as $invoice) {
                $this->line("   - Invoice #{$invoice['invoice_number']}: {$invoice['landlord_name']} - {$invoice['amount']} (paid: {$invoice['payment_date']})");
            }
        }
        
        return $results;
    }
    
    /**
     * Process tenant post-payment archive
     */
    private function processTenantPostPaymentArchive(bool $force): array
    {
        $this->info("👥 Processing tenant post-payment archiving...");
        
        if ($force) {
            $this->warn("Force mode enabled - will run even if disabled in settings");
        }
        
        $results = $this->archiveService->processPostPaymentArchiveTenant();
        
        $this->line("");
        $this->info("✅ Tenant post-payment archiving completed");
        $this->info("📊 Results:");
        $this->info("   - Invoices processed: {$results['total']}");
        $this->info("   - Archived: {$results['archived']}");
        
        if ($results['errors'] > 0) {
            $this->error("   - Errors: {$results['errors']}");
        }
        
        // Show detailed breakdown if verbose
        if ($this->getOutput()->isVerbose() && !empty($results['details'])) {
            $this->line("");
            $this->info("Archived invoices:");
            foreach ($results['details'] as $invoice) {
                $this->line("   - Invoice #{$invoice['invoice_number']}: {$invoice['tenant_name']} - {$invoice['amount']} (paid: {$invoice['payment_date']})");
            }
        }
        
        return $results;
    }
    
    /**
     * Display combined summary for both types
     */
    private function displayCombinedSummary(array $results): void
    {
        $settings = SystemSetting::getSettings();
        
        $this->line("");
        $this->info("📊 COMBINED POST-PAYMENT ARCHIVE SUMMARY");
        $this->line(str_repeat('=', 60));
        
        // Landlord Summary
        if (isset($results['landlord'])) {
            $landlord = $results['landlord'];
            $this->line("");
            $this->info("🏠 LANDLORD INVOICES:");
            $this->line("   Processed: {$landlord['total']}");
            $this->line("   Archived: {$landlord['archived']}");
            if ($landlord['errors'] > 0) {
                $this->error("   Errors: {$landlord['errors']}");
            }
        }
        
        // Tenant Summary
        if (isset($results['tenant'])) {
            $tenant = $results['tenant'];
            $this->line("");
            $this->info("👥 TENANT INVOICES:");
            $this->line("   Processed: {$tenant['total']}");
            $this->line("   Archived: {$tenant['archived']}");
            if ($tenant['errors'] > 0) {
                $this->error("   Errors: {$tenant['errors']}");
            }
        }
        
        // Combined Totals
        $totalProcessed = ($results['landlord']['total'] ?? 0) + ($results['tenant']['total'] ?? 0);
        $totalArchived = ($results['landlord']['archived'] ?? 0) + ($results['tenant']['archived'] ?? 0);
        $totalErrors = ($results['landlord']['errors'] ?? 0) + ($results['tenant']['errors'] ?? 0);
        
        $this->line("");
        $this->line(str_repeat('-', 60));
        $this->info("📈 COMBINED TOTALS:");
        $this->line("   Total invoices processed: {$totalProcessed}");
        $this->line("   Total archived: {$totalArchived}");
        if ($totalErrors > 0) {
            $this->error("   Total errors: {$totalErrors}");
        }
        
        // Retention info
        $retentionMonthsLandlord = $settings->paid_invoice_retention_months_landlord ?? 3;
        $retentionMonthsTenant = $settings->paid_invoice_retention_months_tenant ?? 3;
        
        $this->line("");
        $this->info("📋 RETENTION POLICIES:");
        $this->line("   Landlord retention: {$retentionMonthsLandlord} months");
        $this->line("   Tenant retention: {$retentionMonthsTenant} months");
        
        $this->line(str_repeat('=', 60));
    }
}