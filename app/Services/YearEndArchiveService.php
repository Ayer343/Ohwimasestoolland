<?php

namespace App\Services;

use App\Models\TenantInvoice;
use App\Models\TenantInvoiceArchive;
use App\Models\Invoice;
use App\Models\InvoiceArchive;
use App\Models\User;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class YearEndArchiveService
{
    protected $settings;
    protected $notificationService;
    
    // Archive types
    const TYPE_LANDLORD = 'landlord';
    const TYPE_TENANT = 'tenant';
    
    public function __construct(SystemSetting $settings, NotificationService $notificationService)
    {
        $this->settings = $settings;
        $this->notificationService = $notificationService;
    }
    
    // ==================== LANDLORD INVOICE ARCHIVING ====================
    
    /**
     * Process year-end archiving for landlord invoices
     */
    public function processYearEndArchiveLandlord(?int $year = null): array
    {
        if (!$this->settings->enable_year_end_archive_landlord ?? true) {
            return [
                'success' => false,
                'message' => 'Landlord year-end archiving is disabled in system settings',
                'type' => self::TYPE_LANDLORD
            ];
        }
        
        // If no year specified, archive previous year
        if (!$year) {
            $year = now()->subYear()->year;
        }
        
        $this->logArchiveStart($year, self::TYPE_LANDLORD);
        
        $results = [
            'year' => $year,
            'type' => self::TYPE_LANDLORD,
            'processed_at' => now()->toDateTimeString(),
            'total_invoices' => 0,
            'paid_archived' => 0,
            'unpaid_kept' => 0,
            'errors' => 0,
            'details' => [
                'paid_invoices' => [],
                'unpaid_invoices' => []
            ]
        ];
        
        try {
            DB::beginTransaction();
            
            // Get all landlord invoices from the specified year
            $allInvoices = Invoice::fromYear($year)
                ->whereNull('deleted_at')
                ->with(['property', 'property.landlord'])
                ->get();
            
            $results['total_invoices'] = $allInvoices->count();
            
            // Separate paid and unpaid
            $paidInvoices = $allInvoices->where('status', Invoice::STATUS_PAID);
            $unpaidInvoices = $allInvoices->where('status', '!=', Invoice::STATUS_PAID);
            
            // Process paid invoices - move to archive
            foreach ($paidInvoices as $invoice) {
                try {
                    $archiveResult = $this->archivePaidLandlordInvoice($invoice, $year);
                    
                    if ($archiveResult['success']) {
                        $results['paid_archived']++;
                        $results['details']['paid_invoices'][] = [
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'property_name' => $invoice->property->property_name ?? $invoice->property->street_name,
                            'landlord_name' => $invoice->property->landlord->name ?? 'Unknown',
                            'amount' => $invoice->total_amount,
                            'archive_id' => $archiveResult['archive_id']
                        ];
                    } else {
                        $results['errors']++;
                        Log::error('Failed to archive paid landlord invoice', [
                            'invoice_id' => $invoice->id,
                            'error' => $archiveResult['message']
                        ]);
                    }
                } catch (\Exception $e) {
                    $results['errors']++;
                    Log::error('Error archiving landlord invoice: ' . $e->getMessage(), [
                        'invoice_id' => $invoice->id
                    ]);
                }
            }
            
            // Mark unpaid invoices as year-end processed but keep them active
            foreach ($unpaidInvoices as $invoice) {
                try {
                    $invoice->update([
                        'year_end_archived_at' => now(),
                        'year_end_archive_year' => $year,
                        'original_year' => $year,
                        'metadata' => array_merge($invoice->metadata ?? [], [
                            'year_end_processed' => true,
                            'year_end_processed_at' => now()->toDateTimeString(),
                            'year_end_status' => 'unpaid_retained',
                            'archive_type' => 'landlord_year_end',
                            'notes' => 'Kept active for payment collection'
                        ])
                    ]);
                    
                    $results['unpaid_kept']++;
                    $results['details']['unpaid_invoices'][] = [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'property_name' => $invoice->property->property_name ?? $invoice->property->street_name,
                        'landlord_name' => $invoice->property->landlord->name ?? 'Unknown',
                        'amount' => $invoice->total_amount,
                        'due_date' => $invoice->due_date->format('Y-m-d')
                    ];
                    
                    // Send reminder to landlords with unpaid invoices
                    $this->sendLandlordUnpaidReminder($invoice, $year);
                    
                } catch (\Exception $e) {
                    $results['errors']++;
                    Log::error('Failed to mark unpaid landlord invoice: ' . $e->getMessage(), [
                        'invoice_id' => $invoice->id
                    ]);
                }
            }
            
            DB::commit();
            
            // Send archive report to admins
            if ($this->settings->send_yearly_archive_report_landlord ?? true) {
                $this->sendYearEndReportLandlord($results);
            }
            
            $this->logArchiveCompletion($results, self::TYPE_LANDLORD);
            
            return $results;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Landlord year-end archive process failed: ' . $e->getMessage(), [
                'year' => $year,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Landlord year-end archiving failed: ' . $e->getMessage(),
                'type' => self::TYPE_LANDLORD,
                'year' => $year,
                'errors' => $results['errors']
            ];
        }
    }
    
    /**
     * Archive a paid landlord invoice
     */
    private function archivePaidLandlordInvoice(Invoice $invoice, int $year): array
    {
        try {
            // Create archive record
            $archive = InvoiceArchive::create([
                'original_invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'property_id' => $invoice->property_id,
                'property_name' => $invoice->property->property_name ?? $invoice->property->street_name,
                'landlord_id' => $invoice->property->landlord_id,
                'landlord_name' => $invoice->property->landlord->name ?? 'Unknown',
                'period' => $invoice->period,
                'month_name' => $invoice->period ? Carbon::parse($invoice->period . '-01')->format('F Y') : null,
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
                'bulk_payment_id' => $invoice->bulk_payment_id,
                'covers_periods' => $invoice->covers_periods,
                'bulk_coverage_start' => $invoice->bulk_coverage_start,
                'bulk_coverage_end' => $invoice->bulk_coverage_end,
                'description' => $invoice->description,
                'notes' => $invoice->notes,
                'metadata' => array_merge($invoice->metadata ?? [], [
                    'archive_type' => 'landlord_year_end',
                    'archive_year' => $year,
                    'original_status' => $invoice->status,
                    'archived_at' => now()->toDateTimeString()
                ]),
                'original_created_at' => $invoice->created_at,
                'original_created_by' => $invoice->created_by,
                'original_updated_at' => $invoice->updated_at,
                'original_updated_by' => $invoice->updated_by,
                'deleted_at' => now(),
                'deleted_by' => null,
                'deleted_by_name' => 'System (Year-End Archive)',
                'deletion_reason' => "Year-end archiving for {$year}",
                'archive_type' => 'year_end',
                'grace_period_days' => $this->settings->grace_period_days ?? null,
                'late_payment_percentage' => $this->settings->late_payment_percentage ?? null,
                'fixed_penalty_amount' => $this->settings->fixed_penalty_amount ?? null
            ]);
            
            // Update and soft delete the invoice
            $invoice->update([
                'year_end_archived_at' => now(),
                'year_end_archive_year' => $year,
                'archive_type' => 'year_end',
                'original_year' => $year,
                'metadata' => array_merge($invoice->metadata ?? [], [
                    'year_end_archived' => true,
                    'archive_id' => $archive->id,
                    'archived_at' => now()->toDateTimeString()
                ])
            ]);
            
            $invoice->delete();
            
            // Send notification to landlord
            try {
                $this->sendLandlordArchiveNotification($invoice, $year);
            } catch (\Exception $e) {
                Log::warning('Failed to send landlord archive notification: ' . $e->getMessage(), [
                    'invoice_id' => $invoice->id
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'Landlord invoice archived successfully',
                'archive_id' => $archive->id
            ];
            
        } catch (\Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Process post-payment archiving for landlord invoices
     */
    public function processPostPaymentArchiveLandlord(): array
    {
        if (!($this->settings->auto_archive_paid_after_retention_landlord ?? true)) {
            return [
                'success' => false,
                'message' => 'Landlord post-payment archiving is disabled',
                'type' => self::TYPE_LANDLORD
            ];
        }
        
        $retentionMonths = $this->settings->paid_invoice_retention_months_landlord ?? 3;
        
        // Get invoices that were year-end processed (kept unpaid) but now paid
        // and have passed retention period
        $invoices = Invoice::where('status', Invoice::STATUS_PAID)
            ->whereNotNull('year_end_archived_at') // Was kept from year-end
            ->whereNull('deleted_at')
            ->whereNotNull('payment_date')
            ->where('payment_date', '<=', now()->subMonths($retentionMonths))
            ->with(['property', 'property.landlord'])
            ->get();
        
        $results = [
            'processed_at' => now()->toDateTimeString(),
            'type' => self::TYPE_LANDLORD,
            'total' => $invoices->count(),
            'archived' => 0,
            'errors' => 0,
            'details' => []
        ];
        
        foreach ($invoices as $invoice) {
            try {
                DB::beginTransaction();
                
                $year = $invoice->year_end_archive_year ?? $invoice->created_at->year;
                
                // Create archive record
                $archive = InvoiceArchive::create([
                    'original_invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'property_id' => $invoice->property_id,
                    'property_name' => $invoice->property->property_name ?? $invoice->property->street_name,
                    'landlord_id' => $invoice->property->landlord_id,
                    'landlord_name' => $invoice->property->landlord->name ?? 'Unknown',
                    'period' => $invoice->period,
                    'month_name' => $invoice->period ? Carbon::parse($invoice->period . '-01')->format('F Y') : null,
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
                    'bulk_payment_id' => $invoice->bulk_payment_id,
                    'covers_periods' => $invoice->covers_periods,
                    'bulk_coverage_start' => $invoice->bulk_coverage_start,
                    'bulk_coverage_end' => $invoice->bulk_coverage_end,
                    'description' => $invoice->description,
                    'notes' => $invoice->notes,
                    'metadata' => array_merge($invoice->metadata ?? [], [
                        'archive_type' => 'landlord_post_payment',
                        'original_year' => $year,
                        'paid_after_year_end' => true,
                        'payment_date' => $invoice->payment_date->toDateTimeString(),
                        'archived_at' => now()->toDateTimeString()
                    ]),
                    'original_created_at' => $invoice->created_at,
                    'original_created_by' => $invoice->created_by,
                    'original_updated_at' => $invoice->updated_at,
                    'original_updated_by' => $invoice->updated_by,
                    'deleted_at' => now(),
                    'deleted_by' => null,
                    'deleted_by_name' => 'System (Post-Payment Archive)',
                    'deletion_reason' => "Post-payment archiving after {$retentionMonths} months retention",
                    'archive_type' => 'post_payment',
                    'grace_period_days' => $this->settings->grace_period_days ?? null,
                    'late_payment_percentage' => $this->settings->late_payment_percentage ?? null,
                    'fixed_penalty_amount' => $this->settings->fixed_penalty_amount ?? null
                ]);
                
                $invoice->delete();
                
                $results['archived']++;
                $results['details'][] = [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'property_name' => $invoice->property->property_name ?? $invoice->property->street_name,
                    'landlord_name' => $invoice->property->landlord->name ?? 'Unknown',
                    'amount' => $invoice->total_amount,
                    'original_year' => $year,
                    'payment_date' => $invoice->payment_date->format('Y-m-d')
                ];
                
                DB::commit();
                
                // Send post-archive notification
                $this->sendLandlordPostArchiveNotification($invoice);
                
            } catch (\Exception $e) {
                DB::rollBack();
                $results['errors']++;
                Log::error('Landlord post-payment archiving failed: ' . $e->getMessage(), [
                    'invoice_id' => $invoice->id
                ]);
            }
        }
        
        return $results;
    }
    
    /**
     * Send year-end reminders to landlords
     */
    public function sendYearEndRemindersLandlord(): array
    {
        if (!($this->settings->notify_landlords_before_archive ?? true)) {
            return ['success' => false, 'message' => 'Landlord reminders are disabled', 'type' => self::TYPE_LANDLORD];
        }
        
        $currentYear = now()->year;
        
        // Get all invoices from current year that are paid (will be archived)
        $paidInvoices = Invoice::fromYear($currentYear)
            ->where('status', Invoice::STATUS_PAID)
            ->whereNull('year_end_archived_at')
            ->with(['property', 'property.landlord'])
            ->get();
        
        // Get all unpaid invoices
        $unpaidInvoices = Invoice::fromYear($currentYear)
            ->where('status', '!=', Invoice::STATUS_PAID)
            ->whereNull('year_end_archived_at')
            ->with(['property', 'property.landlord'])
            ->get();
        
        $results = [
            'reminders_sent' => 0,
            'paid_reminders' => 0,
            'unpaid_reminders' => 0,
            'type' => self::TYPE_LANDLORD,
            'details' => []
        ];
        
        // Send reminders for paid invoices (will be archived)
        foreach ($paidInvoices as $invoice) {
            $this->sendLandlordPreArchiveReminder($invoice);
            $results['paid_reminders']++;
            $results['reminders_sent']++;
        }
        
        // Send reminders for unpaid invoices (need payment)
        foreach ($unpaidInvoices as $invoice) {
            $this->sendLandlordPreArchiveUnpaidReminder($invoice);
            $results['unpaid_reminders']++;
            $results['reminders_sent']++;
        }
        
        return $results;
    }
    
    // ==================== TENANT INVOICE ARCHIVING ====================
    
    /**
     * Process year-end archiving for tenant invoices
     */
    public function processYearEndArchiveTenant(?int $year = null): array
    {
        if (!$this->settings->enable_year_end_archive_tenant ?? true) {
            return [
                'success' => false,
                'message' => 'Tenant year-end archiving is disabled in system settings',
                'type' => self::TYPE_TENANT
            ];
        }
        
        // If no year specified, archive previous year
        if (!$year) {
            $year = now()->subYear()->year;
        }
        
        $this->logArchiveStart($year, self::TYPE_TENANT);
        
        $results = [
            'year' => $year,
            'type' => self::TYPE_TENANT,
            'processed_at' => now()->toDateTimeString(),
            'total_invoices' => 0,
            'paid_archived' => 0,
            'unpaid_kept' => 0,
            'errors' => 0,
            'details' => [
                'paid_invoices' => [],
                'unpaid_invoices' => []
            ]
        ];
        
        try {
            DB::beginTransaction();
            
            // Get all tenant invoices from the specified year
            $allInvoices = TenantInvoice::fromYear($year)
                ->notYearEndArchived()
                ->with(['tenant', 'propertyUnit.property'])
                ->get();
            
            $results['total_invoices'] = $allInvoices->count();
            
            // Separate paid and unpaid
            $paidInvoices = $allInvoices->where('status', TenantInvoice::STATUS_PAID);
            $unpaidInvoices = $allInvoices->where('status', '!=', TenantInvoice::STATUS_PAID);
            
            // Process paid invoices - move to trash/archive
            foreach ($paidInvoices as $invoice) {
                try {
                    $archiveResult = $this->archivePaidTenantInvoice($invoice, $year);
                    
                    if ($archiveResult['success']) {
                        $results['paid_archived']++;
                        $results['details']['paid_invoices'][] = [
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'tenant_name' => $invoice->tenant->name,
                            'amount' => $invoice->total_amount,
                            'archive_id' => $archiveResult['archive_id']
                        ];
                    } else {
                        $results['errors']++;
                        Log::error('Failed to archive paid tenant invoice', [
                            'invoice_id' => $invoice->id,
                            'error' => $archiveResult['message']
                        ]);
                    }
                } catch (\Exception $e) {
                    $results['errors']++;
                    Log::error('Error archiving tenant invoice: ' . $e->getMessage(), [
                        'invoice_id' => $invoice->id
                    ]);
                }
            }
            
            // Mark unpaid invoices as year-end processed but keep them active
            foreach ($unpaidInvoices as $invoice) {
                try {
                    $invoice->update([
                        'year_end_archived_at' => now(),
                        'year_end_archive_year' => $year,
                        'original_year' => $year,
                        'metadata' => array_merge($invoice->metadata ?? [], [
                            'year_end_processed' => true,
                            'year_end_processed_at' => now()->toDateTimeString(),
                            'year_end_status' => 'unpaid_retained',
                            'archive_type' => 'tenant_year_end',
                            'notes' => 'Kept active for payment collection'
                        ])
                    ]);
                    
                    $results['unpaid_kept']++;
                    $results['details']['unpaid_invoices'][] = [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'tenant_name' => $invoice->tenant->name,
                        'amount' => $invoice->total_amount,
                        'due_date' => $invoice->due_date->format('Y-m-d')
                    ];
                    
                    // Send reminder to tenants with unpaid invoices
                    $this->sendTenantUnpaidReminder($invoice, $year);
                    
                } catch (\Exception $e) {
                    $results['errors']++;
                    Log::error('Failed to mark unpaid tenant invoice: ' . $e->getMessage(), [
                        'invoice_id' => $invoice->id
                    ]);
                }
            }
            
            DB::commit();
            
            // Send archive report to admins
            if ($this->settings->send_yearly_archive_report_tenant ?? true) {
                $this->sendYearEndReportTenant($results);
            }
            
            $this->logArchiveCompletion($results, self::TYPE_TENANT);
            
            return $results;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tenant year-end archive process failed: ' . $e->getMessage(), [
                'year' => $year,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Tenant year-end archiving failed: ' . $e->getMessage(),
                'type' => self::TYPE_TENANT,
                'year' => $year,
                'errors' => $results['errors']
            ];
        }
    }
    
    /**
     * Archive a paid tenant invoice
     */
    private function archivePaidTenantInvoice(TenantInvoice $invoice, int $year): array
    {
        try {
            // Create archive record
            $archive = TenantInvoiceArchive::create([
                'original_invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'tenant_id' => $invoice->tenant_id,
                'tenant_name' => $invoice->tenant->name,
                'property_unit_id' => $invoice->property_unit_id,
                'period' => $invoice->period,
                'due_date' => $invoice->due_date,
                'community_dues' => $invoice->community_dues,
                'additional_charges' => $invoice->additional_charges ?? 0,
                'total_amount' => $invoice->total_amount,
                'paid_amount' => $invoice->paid_amount,
                'balance' => $invoice->balance,
                'status' => $invoice->status,
                'payment_method' => $invoice->payment_method,
                'payment_reference' => $invoice->payment_reference,
                'payment_date' => $invoice->payment_date,
                'penalty_amount' => $invoice->penalty_amount ?? 0,
                'metadata' => array_merge($invoice->metadata ?? [], [
                    'archive_type' => 'tenant_year_end',
                    'archive_year' => $year,
                    'original_status' => $invoice->status,
                    'archived_at' => now()->toDateTimeString()
                ]),
                'original_created_at' => $invoice->created_at,
                'original_created_by' => $invoice->created_by,
                'deleted_at' => now(),
                'deleted_by' => null,
                'deleted_by_name' => 'System (Year-End Archive)',
                'deletion_reason' => "Year-end archiving for {$year}",
                'archive_type' => 'year_end',
                'archive_approved_by_tenant' => false
            ]);
            
            // Update and soft delete the invoice
            $invoice->update([
                'year_end_archived_at' => now(),
                'year_end_archive_year' => $year,
                'archive_type' => 'year_end',
                'original_year' => $year,
                'metadata' => array_merge($invoice->metadata ?? [], [
                    'year_end_archived' => true,
                    'archive_id' => $archive->id,
                    'archived_at' => now()->toDateTimeString()
                ])
            ]);
            
            $invoice->delete();
            
            // Send notification to tenant
            try {
                $this->sendTenantArchiveNotification($invoice, $year);
            } catch (\Exception $e) {
                Log::warning('Failed to send tenant archive notification: ' . $e->getMessage(), [
                    'invoice_id' => $invoice->id
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'Tenant invoice archived successfully',
                'archive_id' => $archive->id
            ];
            
        } catch (\Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Process post-payment archiving for tenant invoices
     */
    public function processPostPaymentArchiveTenant(): array
    {
        if (!($this->settings->auto_archive_paid_after_retention_tenant ?? true)) {
            return [
                'success' => false,
                'message' => 'Tenant post-payment archiving is disabled',
                'type' => self::TYPE_TENANT
            ];
        }
        
        $retentionMonths = $this->settings->paid_invoice_retention_months_tenant ?? 3;
        
        // Get invoices that were year-end processed (kept unpaid) but now paid
        // and have passed retention period
        $invoices = TenantInvoice::where('status', TenantInvoice::STATUS_PAID)
            ->whereNotNull('year_end_archived_at') // Was kept from year-end
            ->whereNull('deleted_at')
            ->whereNotNull('payment_date')
            ->where('payment_date', '<=', now()->subMonths($retentionMonths))
            ->with(['tenant', 'propertyUnit.property'])
            ->get();
        
        $results = [
            'processed_at' => now()->toDateTimeString(),
            'type' => self::TYPE_TENANT,
            'total' => $invoices->count(),
            'archived' => 0,
            'errors' => 0,
            'details' => []
        ];
        
        foreach ($invoices as $invoice) {
            try {
                DB::beginTransaction();
                
                $year = $invoice->year_end_archive_year ?? $invoice->created_at->year;
                
                // Create archive record
                $archive = TenantInvoiceArchive::create([
                    'original_invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'tenant_id' => $invoice->tenant_id,
                    'tenant_name' => $invoice->tenant->name,
                    'property_unit_id' => $invoice->property_unit_id,
                    'period' => $invoice->period,
                    'due_date' => $invoice->due_date,
                    'community_dues' => $invoice->community_dues,
                    'additional_charges' => $invoice->additional_charges ?? 0,
                    'total_amount' => $invoice->total_amount,
                    'paid_amount' => $invoice->paid_amount,
                    'balance' => $invoice->balance,
                    'status' => $invoice->status,
                    'payment_method' => $invoice->payment_method,
                    'payment_reference' => $invoice->payment_reference,
                    'payment_date' => $invoice->payment_date,
                    'penalty_amount' => $invoice->penalty_amount ?? 0,
                    'metadata' => array_merge($invoice->metadata ?? [], [
                        'archive_type' => 'tenant_post_payment',
                        'original_year' => $year,
                        'paid_after_year_end' => true,
                        'payment_date' => $invoice->payment_date->toDateTimeString(),
                        'archived_at' => now()->toDateTimeString()
                    ]),
                    'original_created_at' => $invoice->created_at,
                    'original_created_by' => $invoice->created_by,
                    'deleted_at' => now(),
                    'deleted_by' => null,
                    'deleted_by_name' => 'System (Post-Payment Archive)',
                    'deletion_reason' => "Post-payment archiving after {$retentionMonths} months retention",
                    'archive_type' => 'post_payment'
                ]);
                
                $invoice->delete();
                
                $results['archived']++;
                $results['details'][] = [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'tenant_name' => $invoice->tenant->name,
                    'amount' => $invoice->total_amount,
                    'original_year' => $year,
                    'payment_date' => $invoice->payment_date->format('Y-m-d')
                ];
                
                DB::commit();
                
                // Send post-archive notification
                $this->sendTenantPostArchiveNotification($invoice);
                
            } catch (\Exception $e) {
                DB::rollBack();
                $results['errors']++;
                Log::error('Tenant post-payment archiving failed: ' . $e->getMessage(), [
                    'invoice_id' => $invoice->id
                ]);
            }
        }
        
        return $results;
    }
    
    /**
     * Send year-end reminders to tenants
     */
    public function sendYearEndRemindersTenant(): array
    {
        if (!($this->settings->notify_tenants_before_archive ?? true)) {
            return ['success' => false, 'message' => 'Tenant reminders are disabled', 'type' => self::TYPE_TENANT];
        }
        
        $currentYear = now()->year;
        
        // Get all invoices from current year that are paid (will be archived)
        $paidInvoices = TenantInvoice::fromYear($currentYear)
            ->where('status', TenantInvoice::STATUS_PAID)
            ->whereNull('year_end_archived_at')
            ->with(['tenant', 'propertyUnit.property'])
            ->get();
        
        // Get all unpaid invoices
        $unpaidInvoices = TenantInvoice::fromYear($currentYear)
            ->where('status', '!=', TenantInvoice::STATUS_PAID)
            ->whereNull('year_end_archived_at')
            ->with(['tenant', 'propertyUnit.property'])
            ->get();
        
        $results = [
            'reminders_sent' => 0,
            'paid_reminders' => 0,
            'unpaid_reminders' => 0,
            'type' => self::TYPE_TENANT,
            'details' => []
        ];
        
        // Send reminders for paid invoices (will be archived)
        foreach ($paidInvoices as $invoice) {
            $this->sendTenantPreArchiveReminder($invoice);
            $results['paid_reminders']++;
            $results['reminders_sent']++;
        }
        
        // Send reminders for unpaid invoices (need payment)
        foreach ($unpaidInvoices as $invoice) {
            $this->sendTenantPreArchiveUnpaidReminder($invoice);
            $results['unpaid_reminders']++;
            $results['reminders_sent']++;
        }
        
        return $results;
    }
    
    // ==================== HELPER METHODS ====================
    
    /**
     * Check if year-end archive should run for a specific type
     */
    public function shouldRunYearEndArchive(string $type = self::TYPE_LANDLORD): bool
    {
        if ($type === self::TYPE_LANDLORD) {
            if (!($this->settings->enable_year_end_archive_landlord ?? true)) {
                return false;
            }
            $archiveMonth = $this->settings->year_end_archive_month_landlord ?? 1;
            $archiveDay = $this->settings->year_end_archive_day_landlord ?? 15;
        } else {
            if (!($this->settings->enable_year_end_archive_tenant ?? true)) {
                return false;
            }
            $archiveMonth = $this->settings->year_end_archive_month_tenant ?? 1;
            $archiveDay = $this->settings->year_end_archive_day_tenant ?? 16;
        }
        
        $today = now();
        $archiveDate = Carbon::create($today->year, $archiveMonth, $archiveDay);
        
        // Run on or after the archive date, but only once per year
        $lastRunYear = $this->getLastArchiveRunYear($type);
        
        return $today->greaterThanOrEqualTo($archiveDate) && 
               $lastRunYear < $today->year;
    }
    
    /**
     * Get last archive run year for a specific type
     */
    private function getLastArchiveRunYear(string $type): int
    {
        if ($type === self::TYPE_LANDLORD) {
            $lastArchive = Invoice::whereNotNull('year_end_archived_at')
                ->orderBy('year_end_archived_at', 'desc')
                ->first();
        } else {
            $lastArchive = TenantInvoice::whereNotNull('year_end_archived_at')
                ->orderBy('year_end_archived_at', 'desc')
                ->first();
        }
        
        if ($lastArchive && $lastArchive->year_end_archive_year) {
            return $lastArchive->year_end_archive_year;
        }
        
        return now()->subYear()->year;
    }
    
    /**
     * Get year-end archive statistics for both types
     */
    public function getYearEndStatistics(?int $year = null, ?string $type = null): array
    {
        $year = $year ?? now()->year;
        $results = [];
        
        if ($type === null || $type === self::TYPE_LANDLORD) {
            $results[self::TYPE_LANDLORD] = $this->getLandlordYearEndStatistics($year);
        }
        
        if ($type === null || $type === self::TYPE_TENANT) {
            $results[self::TYPE_TENANT] = $this->getTenantYearEndStatistics($year);
        }
        
        if ($type !== null) {
            return $results[$type];
        }
        
        return $results;
    }
    
    /**
     * Get landlord year-end statistics
     */
    private function getLandlordYearEndStatistics(int $year): array
    {
        $totalFromYear = Invoice::fromYear($year)->count();
        $paidFromYear = Invoice::fromYear($year)
            ->where('status', Invoice::STATUS_PAID)
            ->count();
        $unpaidFromYear = Invoice::fromYear($year)
            ->where('status', '!=', Invoice::STATUS_PAID)
            ->count();
        
        $yearEndArchived = Invoice::fromYear($year)
            ->whereNotNull('year_end_archived_at')
            ->count();
        
        $archivedPaid = InvoiceArchive::where('archive_type', 'year_end')
            ->whereYear('deleted_at', $year)
            ->count();
        
        $postPaymentArchived = InvoiceArchive::where('archive_type', 'post_payment')
            ->whereYear('deleted_at', $year)
            ->count();
        
        return [
            'type' => self::TYPE_LANDLORD,
            'year' => $year,
            'total_invoices' => $totalFromYear,
            'paid_invoices' => $paidFromYear,
            'unpaid_invoices' => $unpaidFromYear,
            'year_end_archived' => $yearEndArchived,
            'archived_in_archive' => $archivedPaid,
            'post_payment_archived' => $postPaymentArchived,
            'retention_period_months' => $this->settings->paid_invoice_retention_months_landlord ?? 3,
            'next_archive_date' => $this->getNextArchiveDate(self::TYPE_LANDLORD)
        ];
    }
    
    /**
     * Get tenant year-end statistics
     */
    private function getTenantYearEndStatistics(int $year): array
    {
        $totalFromYear = TenantInvoice::fromYear($year)->count();
        $paidFromYear = TenantInvoice::fromYear($year)
            ->where('status', TenantInvoice::STATUS_PAID)
            ->count();
        $unpaidFromYear = TenantInvoice::fromYear($year)
            ->where('status', '!=', TenantInvoice::STATUS_PAID)
            ->count();
        
        $yearEndArchived = TenantInvoice::fromYear($year)
            ->whereNotNull('year_end_archived_at')
            ->count();
        
        $archivedPaid = TenantInvoiceArchive::where('archive_type', 'year_end')
            ->whereYear('deleted_at', $year)
            ->count();
        
        $postPaymentArchived = TenantInvoiceArchive::where('archive_type', 'post_payment')
            ->whereYear('deleted_at', $year)
            ->count();
        
        return [
            'type' => self::TYPE_TENANT,
            'year' => $year,
            'total_invoices' => $totalFromYear,
            'paid_invoices' => $paidFromYear,
            'unpaid_invoices' => $unpaidFromYear,
            'year_end_archived' => $yearEndArchived,
            'archived_in_archive' => $archivedPaid,
            'post_payment_archived' => $postPaymentArchived,
            'retention_period_months' => $this->settings->paid_invoice_retention_months_tenant ?? 3,
            'next_archive_date' => $this->getNextArchiveDate(self::TYPE_TENANT)
        ];
    }
    
    /**
     * Get next scheduled archive date for a specific type
     */
    private function getNextArchiveDate(string $type): string
    {
        if ($type === self::TYPE_LANDLORD) {
            $archiveMonth = $this->settings->year_end_archive_month_landlord ?? 1;
            $archiveDay = $this->settings->year_end_archive_day_landlord ?? 15;
        } else {
            $archiveMonth = $this->settings->year_end_archive_month_tenant ?? 1;
            $archiveDay = $this->settings->year_end_archive_day_tenant ?? 16;
        }
        
        $nextDate = Carbon::create(now()->year, $archiveMonth, $archiveDay);
        
        if ($nextDate->isPast()) {
            $nextDate->addYear();
        }
        
        return $nextDate->format('F j, Y');
    }
    
    // ==================== NOTIFICATION METHODS ====================
    
    // Landlord Notifications
    private function sendLandlordArchiveNotification(Invoice $invoice, int $year)
    {
        try {
            Mail::send('emails.landlord-invoice-year-end-archived', [
                'invoice' => $invoice,
                'year' => $year,
                'settings' => $this->settings
            ], function ($message) use ($invoice) {
                $message->to($invoice->property->landlord->email)
                        ->subject("Invoice #{$invoice->invoice_number} Archived - {$this->settings->system_name}");
            });
            
            Log::info('Landlord year-end archive notification sent', [
                'invoice_id' => $invoice->id,
                'landlord_id' => $invoice->property->landlord_id,
                'year' => $year
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send landlord archive notification: ' . $e->getMessage());
        }
    }
    
    private function sendLandlordUnpaidReminder(Invoice $invoice, int $year)
    {
        try {
            Mail::send('emails.landlord-invoice-year-end-unpaid', [
                'invoice' => $invoice,
                'year' => $year,
                'settings' => $this->settings
            ], function ($message) use ($invoice) {
                $message->to($invoice->property->landlord->email)
                        ->subject("Action Required: Unpaid Invoice from {$year} - {$this->settings->system_name}");
            });
        } catch (\Exception $e) {
            Log::error('Failed to send landlord unpaid reminder: ' . $e->getMessage());
        }
    }
    
    private function sendLandlordPreArchiveReminder(Invoice $invoice)
    {
        try {
            $archiveDate = $this->getNextArchiveDate(self::TYPE_LANDLORD);
            
            Mail::send('emails.landlord-invoice-pre-archive', [
                'invoice' => $invoice,
                'archive_date' => $archiveDate,
                'settings' => $this->settings
            ], function ($message) use ($invoice) {
                $message->to($invoice->property->landlord->email)
                        ->subject("Upcoming Invoice Archiving - {$this->settings->system_name}");
            });
        } catch (\Exception $e) {
            Log::error('Failed to send landlord pre-archive reminder: ' . $e->getMessage());
        }
    }
    
    private function sendLandlordPreArchiveUnpaidReminder(Invoice $invoice)
    {
        try {
            Mail::send('emails.landlord-invoice-pre-archive-unpaid', [
                'invoice' => $invoice,
                'settings' => $this->settings
            ], function ($message) use ($invoice) {
                $message->to($invoice->property->landlord->email)
                        ->subject("IMPORTANT: Unpaid Invoice from Previous Year - {$this->settings->system_name}");
            });
        } catch (\Exception $e) {
            Log::error('Failed to send landlord pre-archive unpaid reminder: ' . $e->getMessage());
        }
    }
    
    private function sendLandlordPostArchiveNotification(Invoice $invoice)
    {
        try {
            Mail::send('emails.landlord-invoice-post-payment-archived', [
                'invoice' => $invoice,
                'settings' => $this->settings
            ], function ($message) use ($invoice) {
                $message->to($invoice->property->landlord->email)
                        ->subject("Invoice #{$invoice->invoice_number} Archived After Payment - {$this->settings->system_name}");
            });
        } catch (\Exception $e) {
            Log::error('Failed to send landlord post-archive notification: ' . $e->getMessage());
        }
    }
    
    // Tenant Notifications
    private function sendTenantArchiveNotification(TenantInvoice $invoice, int $year)
    {
        try {
            Mail::send('emails.tenant-invoice-year-end-archived', [
                'invoice' => $invoice,
                'year' => $year,
                'settings' => $this->settings
            ], function ($message) use ($invoice) {
                $message->to($invoice->tenant->email)
                        ->subject("Invoice #{$invoice->invoice_number} Archived - {$this->settings->system_name}");
            });
            
            Log::info('Tenant year-end archive notification sent', [
                'invoice_id' => $invoice->id,
                'tenant_id' => $invoice->tenant_id,
                'year' => $year
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send tenant archive notification: ' . $e->getMessage());
        }
    }
    
    private function sendTenantUnpaidReminder(TenantInvoice $invoice, int $year)
    {
        try {
            Mail::send('emails.tenant-invoice-year-end-unpaid', [
                'invoice' => $invoice,
                'year' => $year,
                'settings' => $this->settings
            ], function ($message) use ($invoice) {
                $message->to($invoice->tenant->email)
                        ->subject("Action Required: Unpaid Invoice from {$year} - {$this->settings->system_name}");
            });
        } catch (\Exception $e) {
            Log::error('Failed to send tenant unpaid reminder: ' . $e->getMessage());
        }
    }
    
    private function sendTenantPreArchiveReminder(TenantInvoice $invoice)
    {
        try {
            $archiveDate = $this->getNextArchiveDate(self::TYPE_TENANT);
            
            Mail::send('emails.tenant-invoice-pre-archive', [
                'invoice' => $invoice,
                'archive_date' => $archiveDate,
                'settings' => $this->settings
            ], function ($message) use ($invoice) {
                $message->to($invoice->tenant->email)
                        ->subject("Upcoming Invoice Archiving - {$this->settings->system_name}");
            });
        } catch (\Exception $e) {
            Log::error('Failed to send tenant pre-archive reminder: ' . $e->getMessage());
        }
    }
    
    private function sendTenantPreArchiveUnpaidReminder(TenantInvoice $invoice)
    {
        try {
            Mail::send('emails.tenant-invoice-pre-archive-unpaid', [
                'invoice' => $invoice,
                'settings' => $this->settings
            ], function ($message) use ($invoice) {
                $message->to($invoice->tenant->email)
                        ->subject("IMPORTANT: Unpaid Invoice from Previous Year - {$this->settings->system_name}");
            });
        } catch (\Exception $e) {
            Log::error('Failed to send tenant pre-archive unpaid reminder: ' . $e->getMessage());
        }
    }
    
    private function sendTenantPostArchiveNotification(TenantInvoice $invoice)
    {
        try {
            Mail::send('emails.tenant-invoice-post-payment-archived', [
                'invoice' => $invoice,
                'settings' => $this->settings
            ], function ($message) use ($invoice) {
                $message->to($invoice->tenant->email)
                        ->subject("Invoice #{$invoice->invoice_number} Archived After Payment - {$this->settings->system_name}");
            });
        } catch (\Exception $e) {
            Log::error('Failed to send tenant post-archive notification: ' . $e->getMessage());
        }
    }
    
    // Admin Reports
    private function sendYearEndReportLandlord(array $results)
    {
        $admins = User::whereIn('type', ['super_admin', 'admin'])->get();
        $year = $results['year'] ?? now()->subYear()->year;
        
        foreach ($admins as $admin) {
            try {
                Mail::send('emails.landlord-year-end-archive-report', [
                    'results' => $results,
                    'settings' => $this->settings,
                    'year' => $year
                ], function ($message) use ($admin) {
                    $message->to($admin->email)
                            ->subject("Landlord Year-End Archive Report - {$this->settings->system_name}");
                });
                
                Log::info('Landlord year-end report sent to admin', [
                    'admin_email' => $admin->email,
                    'year' => $year
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send landlord year-end report to admin: ' . $e->getMessage(), [
                    'admin_email' => $admin->email,
                    'year' => $year
                ]);
            }
        }
    }
    
    private function sendYearEndReportTenant(array $results)
    {
        $admins = User::whereIn('type', ['super_admin', 'admin'])->get();
        $year = $results['year'] ?? now()->subYear()->year;
        
        foreach ($admins as $admin) {
            try {
                Mail::send('emails.tenant-year-end-archive-report', [
                    'results' => $results,
                    'settings' => $this->settings,
                    'year' => $year
                ], function ($message) use ($admin) {
                    $message->to($admin->email)
                            ->subject("Tenant Year-End Archive Report - {$this->settings->system_name}");
                });
                
                Log::info('Tenant year-end report sent to admin', [
                    'admin_email' => $admin->email,
                    'year' => $year
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send tenant year-end report to admin: ' . $e->getMessage(), [
                    'admin_email' => $admin->email,
                    'year' => $year
                ]);
            }
        }
    }
    
    // Logging
    private function logArchiveStart(int $year, string $type)
    {
        $typeLabel = $type === self::TYPE_LANDLORD ? 'Landlord' : 'Tenant';
        Log::info("{$typeLabel} year-end archive process started", [
            'year' => $year,
            'started_at' => now()->toDateTimeString(),
            'type' => $type,
            'triggered_by' => 'system'
        ]);
    }
    
    private function logArchiveCompletion(array $results, string $type)
    {
        $typeLabel = $type === self::TYPE_LANDLORD ? 'Landlord' : 'Tenant';
        Log::info("{$typeLabel} year-end archive process completed", [
            'year' => $results['year'],
            'type' => $type,
            'completed_at' => now()->toDateTimeString(),
            'total_invoices' => $results['total_invoices'],
            'paid_archived' => $results['paid_archived'],
            'unpaid_kept' => $results['unpaid_kept'],
            'errors' => $results['errors']
        ]);
    }
    
    /**
     * Get archive information for an invoice (works for both types)
     */
    public function getArchiveInfo(int $invoiceId, string $type = self::TYPE_TENANT): array
    {
        try {
            if ($type === self::TYPE_LANDLORD) {
                $invoice = Invoice::withTrashed()->find($invoiceId);
                $archive = InvoiceArchive::where('original_invoice_id', $invoiceId)->first();
            } else {
                $invoice = TenantInvoice::withTrashed()->find($invoiceId);
                $archive = TenantInvoiceArchive::where('original_invoice_id', $invoiceId)->first();
            }
            
            if (!$invoice) {
                return [
                    'success' => false,
                    'message' => 'Invoice not found',
                    'type' => $type
                ];
            }
            
            return [
                'success' => true,
                'type' => $type,
                'invoice' => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'is_archived' => !is_null($invoice->deleted_at),
                    'is_year_end_archived' => !is_null($invoice->year_end_archived_at),
                    'year_end_archive_year' => $invoice->year_end_archive_year,
                    'original_year' => $invoice->original_year,
                    'archive_status' => $invoice->archive_status,
                    'archive_type' => $invoice->archive_type,
                    'archive_reason' => $invoice->archive_reason,
                    'archived_at' => $invoice->archived_at?->format('Y-m-d H:i:s'),
                    'deleted_at' => $invoice->deleted_at?->format('Y-m-d H:i:s'),
                    'archive_record_exists' => !is_null($archive),
                    'archive_record' => $archive ? [
                        'id' => $archive->id,
                        'archive_type' => $archive->archive_type,
                        'deleted_at' => $archive->deleted_at?->format('Y-m-d H:i:s'),
                        'deleted_by_name' => $archive->deleted_by_name
                    ] : null
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get archive info: ' . $e->getMessage(), [
                'invoice_id' => $invoiceId,
                'type' => $type
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'type' => $type
            ];
        }
    }
}