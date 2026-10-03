<?php

namespace App\Console\Commands;

use App\Models\TenantInvoice;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanOldDeletedInvoices extends Command
{
    protected $signature = 'invoices:clean-deleted 
                            {--type=both : Type of invoices to clean (landlord, tenant, both)}
                            {--months=6 : Delete invoices soft-deleted older than X months}
                            {--dry-run : Preview without making changes}
                            {--force : Force delete even if retention period not met}
                            {--archive-check : Check if archive records exist before deletion}';
    
    protected $description = 'Permanently delete soft-deleted invoices older than specified months for landlords and/or tenants';
    
    public function handle()
    {
        $type = $this->option('type');
        $months = (int) $this->option('months');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $archiveCheck = $this->option('archive-check');
        
        // Validate type
        if (!in_array($type, ['landlord', 'tenant', 'both'])) {
            $this->error('Invalid type. Use: landlord, tenant, or both');
            return 1;
        }
        
        $cutoffDate = now()->subMonths($months);
        
        $this->info("🧹 Starting clean-up of soft-deleted invoices");
        $this->line("");
        $this->info("Configuration:");
        $this->info("  - Type: {$type}");
        $this->info("  - Older than: {$months} months");
        $this->info("  - Cutoff date: {$cutoffDate->format('Y-m-d')}");
        $this->info("  - Dry run: " . ($dryRun ? 'Yes' : 'No'));
        $this->info("  - Force: " . ($force ? 'Yes' : 'No'));
        $this->info("  - Archive check: " . ($archiveCheck ? 'Yes' : 'No'));
        $this->line("");
        
        $results = [
            'landlord' => ['found' => 0, 'deleted' => 0, 'skipped' => 0, 'errors' => 0],
            'tenant' => ['found' => 0, 'deleted' => 0, 'skipped' => 0, 'errors' => 0]
        ];
        
        // Process Landlord Invoices
        if ($type === 'landlord' || $type === 'both') {
            $this->info("🏠 Processing landlord invoices...");
            $results['landlord'] = $this->processLandlordInvoices($cutoffDate, $months, $dryRun, $force, $archiveCheck);
        }
        
        // Process Tenant Invoices
        if ($type === 'tenant' || $type === 'both') {
            $this->info("👥 Processing tenant invoices...");
            $results['tenant'] = $this->processTenantInvoices($cutoffDate, $months, $dryRun, $force, $archiveCheck);
        }
        
        // Display summary
        $this->displaySummary($results, $type, $dryRun);
        
        return 0;
    }
    
    /**
     * Process landlord invoices
     */
    private function processLandlordInvoices($cutoffDate, $months, $dryRun, $force, $archiveCheck): array
    {
        $query = Invoice::onlyTrashed()
            ->with(['property', 'property.landlord']);
        
        if (!$force) {
            // Only delete if older than cutoff
            $query->where('deleted_at', '<', $cutoffDate);
        }
        
        $oldDeletedInvoices = $query->get();
        $count = $oldDeletedInvoices->count();
        
        if ($count === 0) {
            $this->info("  No landlord invoices found matching criteria.");
            return ['found' => 0, 'deleted' => 0, 'skipped' => 0, 'errors' => 0];
        }
        
        $this->info("  Found {$count} landlord invoices to process.");
        
        $deleted = 0;
        $skipped = 0;
        $errors = 0;
        $skippedInvoices = [];
        $errorInvoices = [];
        
        foreach ($oldDeletedInvoices as $invoice) {
            $daysInTrash = $invoice->deleted_at->diffInDays(now());
            $canDelete = $daysInTrash >= ($months * 30);
            
            if (!$canDelete && !$force) {
                $skipped++;
                $skippedInvoices[] = [
                    'id' => $invoice->id,
                    'number' => $invoice->invoice_number,
                    'reason' => "Only {$daysInTrash} days in trash (needs {$months} months)"
                ];
                continue;
            }
            
            // Check if archive record exists (optional)
            if ($archiveCheck) {
                $archiveExists = \App\Models\InvoiceArchive::where('original_invoice_id', $invoice->id)->exists();
                if (!$archiveExists) {
                    $this->warn("  Warning: No archive record found for invoice #{$invoice->invoice_number}");
                    if (!$dryRun) {
                        $this->warn("  Creating archive record before deletion...");
                        // Optionally create archive record
                        $this->createLandlordArchiveRecord($invoice);
                    }
                }
            }
            
            if ($dryRun) {
                $this->line("  [DRY RUN] Would delete: #{$invoice->invoice_number} - {$invoice->property->property_name} - {$invoice->period} (in trash for {$daysInTrash} days)");
                $deleted++;
                continue;
            }
            
            try {
                DB::beginTransaction();
                
                // Log the permanent deletion
                \Illuminate\Support\Facades\Log::warning('Invoice permanently deleted via cleanup command', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'property_id' => $invoice->property_id,
                    'period' => $invoice->period,
                    'amount' => $invoice->total_amount,
                    'deleted_at' => $invoice->deleted_at,
                    'days_in_trash' => $daysInTrash,
                    'months_retained' => $months
                ]);
                
                $invoice->forceDelete();
                DB::commit();
                
                $deleted++;
                $this->line("  ✅ Deleted: #{$invoice->invoice_number} - {$invoice->property->property_name} - {$invoice->period} (in trash for {$daysInTrash} days)");
                
            } catch (\Exception $e) {
                DB::rollBack();
                $errors++;
                $errorInvoices[] = [
                    'id' => $invoice->id,
                    'number' => $invoice->invoice_number,
                    'error' => $e->getMessage()
                ];
                $this->error("  ❌ Failed to delete #{$invoice->invoice_number}: " . $e->getMessage());
            }
        }
        
        // Show skipped invoices summary
        if ($skipped > 0 && $this->getOutput()->isVerbose()) {
            $this->line("");
            $this->warn("  Skipped invoices ({$skipped}):");
            foreach ($skippedInvoices as $invoice) {
                $this->line("    - #{$invoice['number']}: {$invoice['reason']}");
            }
        }
        
        // Show error summary
        if ($errors > 0 && $this->getOutput()->isVerbose()) {
            $this->line("");
            $this->error("  Failed invoices ({$errors}):");
            foreach ($errorInvoices as $invoice) {
                $this->line("    - #{$invoice['number']}: {$invoice['error']}");
            }
        }
        
        return [
            'found' => $count,
            'deleted' => $deleted,
            'skipped' => $skipped,
            'errors' => $errors
        ];
    }
    
    /**
     * Process tenant invoices
     */
    private function processTenantInvoices($cutoffDate, $months, $dryRun, $force, $archiveCheck): array
    {
        $query = TenantInvoice::onlyTrashed()
            ->with(['tenant', 'propertyUnit.property']);
        
        if (!$force) {
            // Only delete if older than cutoff
            $query->where('deleted_at', '<', $cutoffDate);
        }
        
        $oldDeletedInvoices = $query->get();
        $count = $oldDeletedInvoices->count();
        
        if ($count === 0) {
            $this->info("  No tenant invoices found matching criteria.");
            return ['found' => 0, 'deleted' => 0, 'skipped' => 0, 'errors' => 0];
        }
        
        $this->info("  Found {$count} tenant invoices to process.");
        
        $deleted = 0;
        $skipped = 0;
        $errors = 0;
        $skippedInvoices = [];
        $errorInvoices = [];
        
        foreach ($oldDeletedInvoices as $invoice) {
            $daysInTrash = $invoice->deleted_at->diffInDays(now());
            $canDelete = $daysInTrash >= ($months * 30);
            
            if (!$canDelete && !$force) {
                $skipped++;
                $skippedInvoices[] = [
                    'id' => $invoice->id,
                    'number' => $invoice->invoice_number,
                    'reason' => "Only {$daysInTrash} days in trash (needs {$months} months)"
                ];
                continue;
            }
            
            // Check if archive record exists (optional)
            if ($archiveCheck) {
                $archiveExists = \App\Models\TenantInvoiceArchive::where('original_invoice_id', $invoice->id)->exists();
                if (!$archiveExists) {
                    $this->warn("  Warning: No archive record found for invoice #{$invoice->invoice_number}");
                    if (!$dryRun) {
                        $this->warn("  Creating archive record before deletion...");
                        // Optionally create archive record
                        $this->createTenantArchiveRecord($invoice);
                    }
                }
            }
            
            if ($dryRun) {
                $propertyName = $invoice->propertyUnit->property->property_name ?? 'Unknown';
                $this->line("  [DRY RUN] Would delete: #{$invoice->invoice_number} - {$invoice->tenant->name} - {$propertyName} - {$invoice->period} (in trash for {$daysInTrash} days)");
                $deleted++;
                continue;
            }
            
            try {
                DB::beginTransaction();
                
                // Log the permanent deletion
                \Illuminate\Support\Facades\Log::warning('Tenant invoice permanently deleted via cleanup command', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'tenant_id' => $invoice->tenant_id,
                    'tenant_name' => $invoice->tenant->name ?? 'Unknown',
                    'period' => $invoice->period,
                    'amount' => $invoice->total_amount,
                    'deleted_at' => $invoice->deleted_at,
                    'days_in_trash' => $daysInTrash,
                    'months_retained' => $months
                ]);
                
                $invoice->forceDelete();
                DB::commit();
                
                $deleted++;
                $propertyName = $invoice->propertyUnit->property->property_name ?? 'Unknown';
                $this->line("  ✅ Deleted: #{$invoice->invoice_number} - {$invoice->tenant->name} - {$propertyName} - {$invoice->period} (in trash for {$daysInTrash} days)");
                
            } catch (\Exception $e) {
                DB::rollBack();
                $errors++;
                $errorInvoices[] = [
                    'id' => $invoice->id,
                    'number' => $invoice->invoice_number,
                    'error' => $e->getMessage()
                ];
                $this->error("  ❌ Failed to delete #{$invoice->invoice_number}: " . $e->getMessage());
            }
        }
        
        // Show skipped invoices summary
        if ($skipped > 0 && $this->getOutput()->isVerbose()) {
            $this->line("");
            $this->warn("  Skipped invoices ({$skipped}):");
            foreach ($skippedInvoices as $invoice) {
                $this->line("    - #{$invoice['number']}: {$invoice['reason']}");
            }
        }
        
        // Show error summary
        if ($errors > 0 && $this->getOutput()->isVerbose()) {
            $this->line("");
            $this->error("  Failed invoices ({$errors}):");
            foreach ($errorInvoices as $invoice) {
                $this->line("    - #{$invoice['number']}: {$invoice['error']}");
            }
        }
        
        return [
            'found' => $count,
            'deleted' => $deleted,
            'skipped' => $skipped,
            'errors' => $errors
        ];
    }
    
    /**
     * Create archive record for landlord invoice (optional)
     */
    private function createLandlordArchiveRecord($invoice): void
    {
        try {
            $settings = \App\Models\SystemSetting::getSettings();
            
            \App\Models\InvoiceArchive::create([
                'original_invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'property_id' => $invoice->property_id,
                'property_name' => $invoice->property->property_name ?? $invoice->property->street_name,
                'landlord_id' => $invoice->property->landlord_id,
                'landlord_name' => $invoice->property->landlord->name ?? 'Unknown',
                'period' => $invoice->period,
                'due_date' => $invoice->due_date,
                'amount' => $invoice->amount,
                'penalty_amount' => $invoice->penalty_amount ?? 0,
                'total_amount' => $invoice->total_amount,
                'paid_amount' => $invoice->paid_amount ?? 0,
                'balance' => $invoice->balance,
                'status' => $invoice->status,
                'payment_method' => $invoice->payment_method,
                'payment_reference' => $invoice->payment_reference,
                'payment_date' => $invoice->payment_date,
                'is_bulk_payment' => $invoice->is_bulk_payment,
                'description' => $invoice->description,
                'notes' => $invoice->notes,
                'metadata' => $invoice->metadata,
                'original_created_at' => $invoice->created_at,
                'original_created_by' => $invoice->created_by,
                'deleted_at' => $invoice->deleted_at,
                'deleted_by' => $invoice->metadata['deleted_by'] ?? null,
                'deleted_by_name' => $invoice->metadata['deleted_by_name'] ?? 'System',
                'deletion_reason' => 'Auto-created during cleanup',
                'archive_type' => 'cleanup_created',
                'grace_period_days' => $settings->grace_period_days ?? null,
                'late_payment_percentage' => $settings->late_payment_percentage ?? null,
                'fixed_penalty_amount' => $settings->fixed_penalty_amount ?? null
            ]);
            
            $this->line("  📦 Created archive record for #{$invoice->invoice_number}");
            
        } catch (\Exception $e) {
            $this->error("  Failed to create archive record: " . $e->getMessage());
        }
    }
    
    /**
     * Create archive record for tenant invoice (optional)
     */
    private function createTenantArchiveRecord($invoice): void
    {
        try {
            \App\Models\TenantInvoiceArchive::create([
                'original_invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'tenant_id' => $invoice->tenant_id,
                'tenant_name' => $invoice->tenant->name ?? 'Unknown',
                'property_unit_id' => $invoice->property_unit_id,
                'period' => $invoice->period,
                'due_date' => $invoice->due_date,
                'community_dues' => $invoice->community_dues,
                'additional_charges' => $invoice->additional_charges ?? 0,
                'total_amount' => $invoice->total_amount,
                'paid_amount' => $invoice->paid_amount ?? 0,
                'balance' => $invoice->balance,
                'status' => $invoice->status,
                'payment_method' => $invoice->payment_method,
                'payment_reference' => $invoice->payment_reference,
                'payment_date' => $invoice->payment_date,
                'penalty_amount' => $invoice->penalty_amount ?? 0,
                'metadata' => $invoice->metadata,
                'original_created_at' => $invoice->created_at,
                'original_created_by' => $invoice->created_by,
                'deleted_at' => $invoice->deleted_at,
                'deleted_by' => $invoice->metadata['deleted_by'] ?? null,
                'deleted_by_name' => $invoice->metadata['deleted_by_name'] ?? 'System',
                'deletion_reason' => 'Auto-created during cleanup',
                'archive_type' => 'cleanup_created'
            ]);
            
            $this->line("  📦 Created archive record for #{$invoice->invoice_number}");
            
        } catch (\Exception $e) {
            $this->error("  Failed to create archive record: " . $e->getMessage());
        }
    }
    
    /**
     * Display summary of results
     */
    private function displaySummary(array $results, string $type, bool $dryRun): void
    {
        $this->line("");
        $this->info("📊 CLEANUP SUMMARY");
        $this->line(str_repeat('=', 60));
        
        if ($type === 'landlord' || $type === 'both') {
            $landlord = $results['landlord'];
            $this->line("");
            $this->info("🏠 LANDLORD INVOICES:");
            $this->line("   Found: {$landlord['found']}");
            if ($dryRun) {
                $this->info("   Would delete: {$landlord['deleted']}");
            } else {
                $this->info("   Deleted: {$landlord['deleted']}");
            }
            if ($landlord['skipped'] > 0) {
                $this->warn("   Skipped: {$landlord['skipped']}");
            }
            if ($landlord['errors'] > 0) {
                $this->error("   Errors: {$landlord['errors']}");
            }
        }
        
        if ($type === 'tenant' || $type === 'both') {
            $tenant = $results['tenant'];
            $this->line("");
            $this->info("👥 TENANT INVOICES:");
            $this->line("   Found: {$tenant['found']}");
            if ($dryRun) {
                $this->info("   Would delete: {$tenant['deleted']}");
            } else {
                $this->info("   Deleted: {$tenant['deleted']}");
            }
            if ($tenant['skipped'] > 0) {
                $this->warn("   Skipped: {$tenant['skipped']}");
            }
            if ($tenant['errors'] > 0) {
                $this->error("   Errors: {$tenant['errors']}");
            }
        }
        
        // Combined totals
        if ($type === 'both') {
            $totalFound = $results['landlord']['found'] + $results['tenant']['found'];
            $totalDeleted = $results['landlord']['deleted'] + $results['tenant']['deleted'];
            $totalSkipped = $results['landlord']['skipped'] + $results['tenant']['skipped'];
            $totalErrors = $results['landlord']['errors'] + $results['tenant']['errors'];
            
            $this->line("");
            $this->line(str_repeat('-', 60));
            $this->info("📈 COMBINED TOTALS:");
            $this->line("   Total found: {$totalFound}");
            if ($dryRun) {
                $this->info("   Would delete: {$totalDeleted}");
            } else {
                $this->info("   Deleted: {$totalDeleted}");
            }
            if ($totalSkipped > 0) {
                $this->warn("   Skipped: {$totalSkipped}");
            }
            if ($totalErrors > 0) {
                $this->error("   Errors: {$totalErrors}");
            }
        }
        
        $this->line(str_repeat('=', 60));
        
        if ($dryRun) {
            $this->line("");
            $this->warn("This was a DRY RUN. No changes were made.");
            $this->info("To actually delete, run without --dry-run flag.");
        }
        
        if (!$dryRun && $results['landlord']['errors'] > 0 || $results['tenant']['errors'] > 0) {
            $this->line("");
            $this->error("Some invoices could not be deleted. Check the logs for details.");
        }
    }
}