<?php

namespace App\Services;

use App\Models\TenantInvoice;
use App\Models\User;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantInvoiceService
{
    protected $systemSetting;

    public function __construct()
    {
        $this->systemSetting = SystemSetting::getSettings();
    }

    /**
     * Generate monthly tenant invoices for all active tenants
     */
    public function generateMonthlyTenantInvoices($period = null, $sendNotifications = true, $type = 'community_dues', $forceGeneration = false)
    {
        $period = $period ?? now()->startOfMonth()->format('Y-m');
        
        try {
            DB::beginTransaction();

            if (!$this->systemSetting->enable_tenant_invoicing) {
                return [
                    'success' => false,
                    'message' => 'Tenant invoicing is disabled in system settings',
                    'generated_count' => 0,
                    'skipped_count' => 0,
                    'errors' => ['Tenant invoicing is disabled']
                ];
            }

            $activeTenants = $this->getActiveTenants();

            if ($activeTenants->isEmpty()) {
                return [
                    'success' => true,
                    'message' => 'No active tenants found',
                    'generated_count' => 0,
                    'skipped_count' => 0,
                    'errors' => []
                ];
            }

            $generatedCount = 0;
            $skippedCount = 0;
            $errors = [];
            $totalAmount = 0;

            foreach ($activeTenants as $tenant) {
                try {
                    $propertyUnit = $this->getTenantPropertyUnit($tenant);
                    
                    if (!$propertyUnit) {
                        $skippedCount++;
                        Log::warning("Tenant {$tenant->id} has no property unit assigned", [
                            'tenant_id' => $tenant->id,
                            'tenant_name' => $tenant->name,
                            'period' => $period
                        ]);
                        $errors[] = "Tenant {$tenant->name} (ID: {$tenant->id}) has no property unit assigned";
                        continue;
                    }

                    $existingInvoice = TenantInvoice::where('tenant_id', $tenant->id)
                        ->where('property_unit_id', $propertyUnit->id)
                        ->where('period', $period)
                        ->first();

                    if ($existingInvoice && !$forceGeneration) {
                        $skippedCount++;
                        Log::info("Tenant invoice already exists for period {$period}", [
                            'tenant_id' => $tenant->id,
                            'tenant_name' => $tenant->name,
                            'period' => $period,
                            'invoice_id' => $existingInvoice->id
                        ]);
                        continue;
                    }

                    if ($existingInvoice && $forceGeneration) {
                        $invoice = $this->updateExistingInvoice($existingInvoice, $propertyUnit);
                        Log::info("Updated existing tenant invoice", [
                            'invoice_id' => $invoice->id,
                            'tenant_id' => $tenant->id,
                            'period' => $period
                        ]);
                    } else {
                        $communityDues = $this->calculateTenantDues($tenant, $propertyUnit);

                        if ($communityDues <= 0) {
                            $skippedCount++;
                            Log::warning("Calculated tenant dues are zero or negative", [
                                'tenant_id' => $tenant->id,
                                'community_dues' => $communityDues,
                                'calculation_method' => $this->systemSetting->tenant_calculation_method
                            ]);
                            continue;
                        }

                        $dueDate = $this->calculateDueDate($period);
                        $issueDate = now(); // Set issue date to current date/time
                        $invoiceNumber = $this->generateInvoiceNumber();
                        $gracePeriodDays = $this->systemSetting->tenant_grace_period_days ?? 7;
                        $originalYear = Carbon::parse($period . '-01')->year;
                        
                        // Prepare calculation details
                        $calculationDetails = [
                            'community_dues' => number_format($communityDues, 2),
                            'additional_charges' => 0,
                            'penalty_amount' => 0,
                            'total' => $communityDues,
                            'calculation_method' => $this->systemSetting->tenant_calculation_method ?? 'fixed',
                            'grace_period_days' => $gracePeriodDays,
                            'due_date' => $dueDate->toDateString(),
                            'generated_at' => now()->toDateTimeString()
                        ];

                        $invoice = TenantInvoice::create([
                            'invoice_number' => $invoiceNumber,
                            'tenant_id' => $tenant->id,
                            'property_unit_id' => $propertyUnit->id,
                            'period' => $period,
                            'issue_date' => $issueDate, // ← FIX: Added issue_date
                            'due_date' => $dueDate,
                            'community_dues' => $communityDues,
                            'additional_charges' => 0,
                            'total_amount' => $communityDues,
                            'balance' => $communityDues,
                            'status' => TenantInvoice::STATUS_PENDING,
                            'description' => "Monthly community dues for " . Carbon::parse($period . '-01')->format('F Y'),
                            'created_by' => auth()->id() ?? 1,
                            'grace_period_days' => $gracePeriodDays,
                            'original_year' => $originalYear,
                            'calculation_details' => $calculationDetails,
                            'archive_status' => 'pending',
                            'metadata' => [
                                'generation_method' => 'auto',
                                'calculation_method' => $this->systemSetting->tenant_calculation_method ?? 'fixed',
                                'landlord_dues' => $this->systemSetting->monthly_dues_amount ?? 0,
                                'tenant_percentage' => $this->systemSetting->tenant_dues_percentage ?? 50,
                                'unit_type' => $propertyUnit->unit_type ?? 'unknown',
                                'generated_at' => now()->toDateTimeString(),
                                'generated_by_system' => true,
                                'period' => $period,
                                'issue_date' => $issueDate->toDateString(),
                                'due_date' => $dueDate->toDateString(),
                                'system_settings_at_creation' => [
                                    'enable_tenant_invoicing' => $this->systemSetting->enable_tenant_invoicing,
                                    'tenant_monthly_dues_amount' => $this->systemSetting->tenant_monthly_dues_amount,
                                    'tenant_calculation_method' => $this->systemSetting->tenant_calculation_method,
                                    'tenant_dues_percentage' => $this->systemSetting->tenant_dues_percentage,
                                    'auto_generate_tenant_invoices' => $this->systemSetting->auto_generate_tenant_invoices,
                                    'send_tenant_payment_reminders' => $this->systemSetting->send_tenant_payment_reminders,
                                    'tenant_grace_period_days' => $this->systemSetting->tenant_grace_period_days,
                                    'tenant_late_payment_percentage' => $this->systemSetting->tenant_late_payment_percentage,
                                    'tenant_fixed_penalty_amount' => $this->systemSetting->tenant_fixed_penalty_amount,
                                    'currency_code' => $this->systemSetting->currency_code,
                                    'currency_symbol' => $this->systemSetting->currency_symbol,
                                    'currency_position' => $this->systemSetting->currency_position,
                                    'snapshot_taken_at' => now()->toDateTimeString()
                                ]
                            ]
                        ]);
                    }

                    $generatedCount++;
                    $totalAmount += $invoice->total_amount;

                    if ($sendNotifications && $this->systemSetting->send_tenant_payment_reminders) {
                        $this->sendInvoiceNotification($invoice, 'created');
                    }

                    Log::info("Tenant invoice generated successfully", [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'period' => $period,
                        'amount' => $invoice->total_amount,
                        'issue_date' => $issueDate->toDateString(),
                        'due_date' => $dueDate->toDateString()
                    ]);

                } catch (\Exception $e) {
                    $errorMsg = "Tenant ID {$tenant->id} ({$tenant->name}): " . $e->getMessage();
                    $errors[] = $errorMsg;
                    Log::error("Failed to generate tenant invoice", [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'period' => $period,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            DB::commit();

            $message = "Generated {$generatedCount} tenant invoices for period {$period}";
            if ($skippedCount > 0) {
                $message .= ", skipped {$skippedCount} tenants";
            }
            if (!empty($errors)) {
                $message .= ", encountered " . count($errors) . " errors";
            }

            return [
                'success' => true,
                'message' => $message,
                'generated_count' => $generatedCount,
                'skipped_count' => $skippedCount,
                'total_amount' => $totalAmount,
                'errors' => $errors,
                'details' => [
                    'period' => $period,
                    'send_notifications' => $sendNotifications,
                    'tenant_invoicing_enabled' => $this->systemSetting->enable_tenant_invoicing,
                    'calculation_method' => $this->systemSetting->tenant_calculation_method,
                    'auto_generation_enabled' => $this->systemSetting->auto_generate_tenant_invoices,
                    'force_generation' => $forceGeneration
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to generate monthly tenant invoices: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'period' => $period
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to generate tenant invoices: ' . $e->getMessage(),
                'generated_count' => 0,
                'skipped_count' => 0,
                'errors' => [$e->getMessage()]
            ];
        }
    }
    
    /**
     * Update existing invoice with current settings
     */
    protected function updateExistingInvoice(TenantInvoice $invoice, PropertyUnit $propertyUnit): TenantInvoice
    {
        $communityDues = $this->calculateTenantDues($invoice->tenant, $propertyUnit);
        $dueDate = $this->calculateDueDate($invoice->period);
        
        $invoice->update([
            'community_dues' => $communityDues,
            'total_amount' => $communityDues,
            'balance' => $communityDues - ($invoice->paid_amount ?? 0),
            'due_date' => $dueDate,
            'updated_by' => auth()->id() ?? 1,
            'metadata' => array_merge($invoice->metadata ?? [], [
                'updated_at' => now()->toDateTimeString(),
                'updated_by_system' => true,
                'previous_amount' => $invoice->total_amount,
                'new_amount' => $communityDues,
                'update_reason' => 'auto_regeneration'
            ])
        ]);
        
        return $invoice->refresh();
    }
    
    /**
     * Get tenant's property unit
     */
    protected function getTenantPropertyUnit(User $tenant): ?PropertyUnit
    {
        $propertyUnit = PropertyUnit::where('tenant_id', $tenant->id)
            ->where('tenant_status', 'approved')
            ->first();
            
        if ($propertyUnit) {
            return $propertyUnit;
        }
        
        $rental = $tenant->rentals()
            ->where('status', 'active')
            ->where('start_date', '<=', now())
            ->where(function($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            })
            ->first();
            
        if ($rental && $rental->property_unit_id) {
            return PropertyUnit::find($rental->property_unit_id);
        }
        
        $propertyTenant = DB::table('property_tenant')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where('start_date', '<=', now())
            ->where(function($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            })
            ->first();
            
        if ($propertyTenant && $propertyTenant->property_unit_id) {
            return PropertyUnit::find($propertyTenant->property_unit_id);
        }
        
        return null;
    }
    
    /**
     * Generate unique invoice number
     */
    protected function generateInvoiceNumber(): string
    {
        $prefix = 'TINV-' . date('Ymd');
        $lastInvoice = TenantInvoice::where('invoice_number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();
            
        if ($lastInvoice) {
            $lastNumber = intval(substr($lastInvoice->invoice_number, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }
        
        return $prefix . '-' . $newNumber;
    }

    /**
     * Get all active tenants
     */
    protected function getActiveTenants()
    {
        return User::where('type', User::TYPE_TENANT)
            ->where('status', User::STATUS_ACTIVE)
            ->where(function($query) {
                $query->whereHas('rentals', function($q) {
                    $q->where('status', 'active')
                      ->where('start_date', '<=', now())
                      ->where(function($sub) {
                          $sub->whereNull('end_date')
                              ->orWhere('end_date', '>=', now());
                      });
                })
                ->orWhereHas('propertyUnits', function($q) {
                    $q->where('tenant_status', 'approved');
                });
            })
            ->with(['propertyUnits.property'])
            ->get();
    }

    /**
     * Calculate tenant dues based on system settings
     */
    protected function calculateTenantDues(User $tenant, $propertyUnit = null): float
    {
        $method = $this->systemSetting->tenant_calculation_method ?? 'fixed';
        
        switch ($method) {
            case 'percentage_of_landlord':
                $landlordDues = $this->getLandlordDuesForProperty($propertyUnit);
                $percentage = $this->systemSetting->tenant_dues_percentage ?? 50;
                $amount = ($landlordDues * $percentage) / 100;
                return round($amount, 2);
                
            case 'per_property_unit':
                if ($propertyUnit) {
                    $baseAmount = $this->systemSetting->tenant_monthly_dues_amount ?? 50;
                    
                    if (isset($propertyUnit->tenant_monthly_dues) && $propertyUnit->tenant_monthly_dues > 0) {
                        return round($propertyUnit->tenant_monthly_dues, 2);
                    }
                    
                    $adjustmentFactor = $this->getUnitTypeAdjustment($propertyUnit->unit_type);
                    $amount = $baseAmount * $adjustmentFactor;
                    return round($amount, 2);
                }
                return round($this->systemSetting->tenant_monthly_dues_amount ?? 50, 2);
                
            case 'fixed':
            default:
                return round($this->systemSetting->tenant_monthly_dues_amount ?? 50, 2);
        }
    }
    
    /**
     * Get landlord dues for a property
     */
    protected function getLandlordDuesForProperty(?PropertyUnit $propertyUnit): float
    {
        if (!$propertyUnit || !$propertyUnit->property) {
            return $this->systemSetting->monthly_dues_amount ?? 0;
        }
        
        if (isset($propertyUnit->property->monthly_dues) && $propertyUnit->property->monthly_dues > 0) {
            return $propertyUnit->property->monthly_dues;
        }
        
        return $this->systemSetting->monthly_dues_amount ?? 0;
    }
    
    /**
     * Get adjustment factor based on unit type
     */
    protected function getUnitTypeAdjustment($unitType): float
    {
        $factors = [
            'studio' => 0.8,
            '1_bedroom' => 1.0,
            '2_bedroom' => 1.3,
            '3_bedroom' => 1.6,
            '4_bedroom' => 2.0,
            'penthouse' => 2.5,
            'villa' => 3.0,
            'townhouse' => 2.2,
            'duplex' => 2.4,
            'commercial' => 3.0,
        ];
        
        return $factors[$unitType] ?? 1.0;
    }

    /**
     * Calculate due date based on system settings
     */
    protected function calculateDueDate($period): Carbon
    {
        $dueDay = $this->systemSetting->tenant_due_day ?? 5;
        $dueDate = Carbon::parse($period . '-' . $dueDay);
        
        if ($dueDate->isPast()) {
            $dueDate = Carbon::parse($period . '-01')->addMonth()->day($dueDay);
        }
        
        return $dueDate;
    }

    /**
     * Send invoice notification to tenant
     */
    protected function sendInvoiceNotification(TenantInvoice $invoice, string $type = 'created'): bool
    {
        try {
            $tenant = $invoice->tenant;
            
            if (!$tenant || !$tenant->email) {
                Log::warning("Cannot send invoice notification: Tenant email missing", [
                    'invoice_id' => $invoice->id,
                    'tenant_id' => $invoice->tenant_id,
                    'type' => $type
                ]);
                return false;
            }
            
            if (!$this->systemSetting->send_tenant_payment_reminders) {
                Log::debug("Tenant payment reminders disabled, skipping notification", [
                    'invoice_id' => $invoice->id
                ]);
                return false;
            }
            
            $metadata = $invoice->metadata ?? [];
            $notifications = $metadata['notifications'] ?? [];
            $notifications[] = [
                'type' => $type,
                'sent_at' => now()->toDateTimeString(),
                'method' => 'email',
                'recipient' => $tenant->email
            ];
            $metadata['notifications'] = $notifications;
            $metadata['last_notification_at'] = now()->toDateTimeString();
            $invoice->update(['metadata' => $metadata]);
            
            Log::info("Tenant invoice notification sent", [
                'invoice_id' => $invoice->id,
                'tenant_id' => $invoice->tenant_id,
                'type' => $type,
                'email' => $tenant->email
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error("Failed to send tenant invoice notification: " . $e->getMessage(), [
                'invoice_id' => $invoice->id,
                'type' => $type
            ]);
            return false;
        }
    }

    /**
     * Mark overdue tenant invoices and apply penalties
     * This method now processes both:
     * 1. Pending invoices that are past due
     * 2. Overdue invoices that haven't had penalties applied yet
     */
    public function markOverdueTenantInvoices(): array
    {
        try {
            $gracePeriodDays = $this->systemSetting->tenant_grace_period_days ?? 7;
            $cutoffDate = now()->subDays($gracePeriodDays);
            
            // Get pending invoices that are past the cutoff date
            $pendingInvoices = TenantInvoice::where('status', TenantInvoice::STATUS_PENDING)
                ->where('due_date', '<', $cutoffDate)
                ->where('balance', '>', 0)
                ->get();
            
            // Get overdue invoices that don't have penalties applied yet
            $overdueInvoicesWithoutPenalty = TenantInvoice::where('status', TenantInvoice::STATUS_OVERDUE)
                ->where('penalty_amount', 0)
                ->where('balance', '>', 0)
                ->get();
            
            // Merge both collections
            $overdueInvoices = $pendingInvoices->merge($overdueInvoicesWithoutPenalty);
            
            $count = 0;
            $penaltyApplied = 0;
            $totalPenalty = 0;

            foreach ($overdueInvoices as $invoice) {
                try {
                    DB::beginTransaction();
                    
                    // Update status if it's still pending
                    if ($invoice->status === TenantInvoice::STATUS_PENDING) {
                        $invoice->update([
                            'status' => TenantInvoice::STATUS_OVERDUE,
                            'metadata' => array_merge($invoice->metadata ?? [], [
                                'marked_overdue_at' => now()->toDateTimeString(),
                                'days_overdue' => now()->diffInDays($invoice->due_date),
                                'grace_period_days' => $gracePeriodDays
                            ])
                        ]);
                    }
                    $count++;

                    // Calculate and apply penalty if configured
                    $penaltyAmount = $this->calculatePenaltyAmount($invoice);
                    
                    if ($penaltyAmount > 0) {
                        $invoice->update([
                            'penalty_amount' => ($invoice->penalty_amount ?? 0) + $penaltyAmount,
                            'total_amount' => $invoice->total_amount + $penaltyAmount,
                            'balance' => $invoice->balance + $penaltyAmount,
                            'penalty_applied_at' => now(),
                            'metadata' => array_merge($invoice->metadata ?? [], [
                                'penalty_applied' => [
                                    'amount' => $penaltyAmount,
                                    'applied_at' => now()->toDateTimeString(),
                                    'percentage' => $this->systemSetting->tenant_late_payment_percentage,
                                    'fixed' => $this->systemSetting->tenant_fixed_penalty_amount
                                ]
                            ])
                        ]);
                        
                        $penaltyApplied++;
                        $totalPenalty += $penaltyAmount;
                        
                        Log::info("Penalty applied to overdue tenant invoice", [
                            'invoice_id' => $invoice->id,
                            'tenant_id' => $invoice->tenant_id,
                            'penalty_amount' => $penaltyAmount,
                            'total_penalty' => $totalPenalty,
                            'was_pending' => $invoice->status === TenantInvoice::STATUS_PENDING
                        ]);
                    }
                    
                    DB::commit();
                    
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error("Failed to process overdue tenant invoice", [
                        'invoice_id' => $invoice->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return [
                'success' => true,
                'message' => "Processed {$count} overdue tenant invoices, applied penalties to {$penaltyApplied}",
                'count' => $count,
                'penalty_applied_count' => $penaltyApplied,
                'total_penalty_amount' => $totalPenalty,
                'formatted_penalty' => $this->systemSetting->formatAmount($totalPenalty)
            ];

        } catch (\Exception $e) {
            Log::error("Failed to mark overdue tenant invoices: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to mark overdue tenant invoices: ' . $e->getMessage(),
                'count' => 0,
                'penalty_applied_count' => 0,
                'total_penalty_amount' => 0
            ];
        }
    }
    
    /**
     * Calculate penalty amount for an invoice
     */
    protected function calculatePenaltyAmount(TenantInvoice $invoice): float
    {
        $percentage = $this->systemSetting->tenant_late_payment_percentage ?? 0;
        $fixed = $this->systemSetting->tenant_fixed_penalty_amount ?? 0;
        
        $penalty = 0;
        
        if ($percentage > 0) {
            $penalty = $invoice->balance * ($percentage / 100);
        } elseif ($fixed > 0) {
            $penalty = $fixed;
        }
        
        return round($penalty, $this->systemSetting->decimal_places ?? 2);
    }

    /**
     * Get tenant invoice statistics
     */
    public function getTenantInvoiceStatistics($user = null): array
    {
        try {
            $query = TenantInvoice::query();

            if ($user && $user->isTenant()) {
                $query->where('tenant_id', $user->id);
            }

            $totalInvoices = (clone $query)->count();
            $paidInvoices = (clone $query)->where('status', TenantInvoice::STATUS_PAID)->count();
            $pendingInvoices = (clone $query)->where('status', TenantInvoice::STATUS_PENDING)->count();
            $overdueInvoices = (clone $query)->where('status', TenantInvoice::STATUS_OVERDUE)->count();
            
            $totalAmount = (clone $query)->sum('total_amount');
            $paidAmount = (clone $query)->where('status', TenantInvoice::STATUS_PAID)->sum('total_amount');
            $totalPenalties = (clone $query)->sum('penalty_amount');
            $totalBalance = (clone $query)->sum('balance');
            
            $collectionRate = $totalAmount > 0 ? round(($paidAmount / $totalAmount) * 100, 2) : 0;
            
            $monthlyBreakdown = $this->getMonthlyBreakdown($user);
            $overdueSummary = $this->getOverdueSummary($user);

            return [
                'total_invoices' => $totalInvoices,
                'paid_invoices' => $paidInvoices,
                'pending_invoices' => $pendingInvoices,
                'overdue_invoices' => $overdueInvoices,
                'total_amount' => $totalAmount,
                'formatted_total_amount' => $this->systemSetting->formatAmount($totalAmount),
                'paid_amount' => $paidAmount,
                'formatted_paid_amount' => $this->systemSetting->formatAmount($paidAmount),
                'total_penalties' => $totalPenalties,
                'formatted_total_penalties' => $this->systemSetting->formatAmount($totalPenalties),
                'total_balance' => $totalBalance,
                'formatted_total_balance' => $this->systemSetting->formatAmount($totalBalance),
                'collection_rate' => $collectionRate,
                'monthly_breakdown' => $monthlyBreakdown,
                'overdue_summary' => $overdueSummary,
                'tenant_invoicing_enabled' => $this->systemSetting->enable_tenant_invoicing ?? false,
                'calculation_method' => $this->systemSetting->tenant_calculation_method ?? 'fixed',
                'currency_symbol' => $this->systemSetting->currency_symbol,
                'grace_period_days' => $this->systemSetting->tenant_grace_period_days ?? 7
            ];
            
        } catch (\Exception $e) {
            Log::error("Failed to get tenant invoice statistics: " . $e->getMessage());
            
            return [
                'total_invoices' => 0,
                'paid_invoices' => 0,
                'pending_invoices' => 0,
                'overdue_invoices' => 0,
                'total_amount' => 0,
                'paid_amount' => 0,
                'total_penalties' => 0,
                'total_balance' => 0,
                'collection_rate' => 0,
                'tenant_invoicing_enabled' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get monthly breakdown of invoices
     */
    protected function getMonthlyBreakdown($user = null): array
    {
        try {
            $query = TenantInvoice::select(
                DB::raw('DATE_FORMAT(period, "%Y-%m") as month'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "paid" THEN 1 ELSE 0 END) as paid'),
                DB::raw('SUM(total_amount) as amount'),
                DB::raw('SUM(CASE WHEN status = "paid" THEN total_amount ELSE 0 END) as paid_amount')
            );
            
            if ($user && $user->isTenant()) {
                $query->where('tenant_id', $user->id);
            }
            
            $query->groupBy('month')
                  ->orderBy('month', 'desc')
                  ->limit(12);
                  
            $results = $query->get();
            
            $breakdown = [];
            foreach ($results as $row) {
                $breakdown[] = [
                    'month' => $row->month,
                    'month_name' => Carbon::parse($row->month . '-01')->format('F Y'),
                    'total_invoices' => $row->total,
                    'paid_invoices' => $row->paid,
                    'total_amount' => $row->amount,
                    'paid_amount' => $row->paid_amount,
                    'collection_rate' => $row->total > 0 ? round(($row->paid / $row->total) * 100, 2) : 0
                ];
            }
            
            return $breakdown;
            
        } catch (\Exception $e) {
            Log::error("Failed to get monthly breakdown: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get overdue summary
     */
    protected function getOverdueSummary($user = null): array
    {
        try {
            $graceDays = $this->systemSetting->tenant_grace_period_days ?? 7;
            
            $query = TenantInvoice::where('status', TenantInvoice::STATUS_PENDING)
                ->where('due_date', '<', now())
                ->where('balance', '>', 0);
                
            if ($user && $user->isTenant()) {
                $query->where('tenant_id', $user->id);
            }
            
            $overdueInvoices = $query->get();
            
            $summary = [
                'total_overdue' => $overdueInvoices->count(),
                'total_amount' => $overdueInvoices->sum('balance'),
                'by_age' => [
                    '0-7_days' => 0,
                    '8-14_days' => 0,
                    '15-30_days' => 0,
                    '30+_days' => 0
                ]
            ];
            
            foreach ($overdueInvoices as $invoice) {
                $daysOverdue = now()->diffInDays($invoice->due_date);
                
                if ($daysOverdue <= 7) {
                    $summary['by_age']['0-7_days']++;
                } elseif ($daysOverdue <= 14) {
                    $summary['by_age']['8-14_days']++;
                } elseif ($daysOverdue <= 30) {
                    $summary['by_age']['15-30_days']++;
                } else {
                    $summary['by_age']['30+_days']++;
                }
            }
            
            $summary['formatted_total_amount'] = $this->systemSetting->formatAmount($summary['total_amount']);
            
            return $summary;
            
        } catch (\Exception $e) {
            Log::error("Failed to get overdue summary: " . $e->getMessage());
            return ['total_overdue' => 0, 'total_amount' => 0];
        }
    }
    
    /**
     * Get invoice details for a specific tenant
     */
    public function getTenantInvoices(int $tenantId, array $filters = []): array
    {
        try {
            $query = TenantInvoice::with(['propertyUnit.property'])
                ->where('tenant_id', $tenantId);
                
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            
            if (isset($filters['period_from'])) {
                $query->where('period', '>=', $filters['period_from']);
            }
            
            if (isset($filters['period_to'])) {
                $query->where('period', '<=', $filters['period_to']);
            }
            
            $invoices = $query->orderBy('period', 'desc')->get();
            
            return [
                'success' => true,
                'invoices' => $invoices,
                'total_count' => $invoices->count(),
                'total_outstanding' => $invoices->whereIn('status', [TenantInvoice::STATUS_PENDING, TenantInvoice::STATUS_OVERDUE])->sum('balance'),
                'total_paid' => $invoices->where('status', TenantInvoice::STATUS_PAID)->sum('total_amount')
            ];
            
        } catch (\Exception $e) {
            Log::error("Failed to get tenant invoices: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'invoices' => [],
                'total_count' => 0,
                'total_outstanding' => 0,
                'total_paid' => 0
            ];
        }
    }
    
    /**
     * Get generation summary for a specific period
     */
    public function getGenerationSummary(string $period): array
    {
        try {
            $invoices = TenantInvoice::where('period', $period)->get();
            
            $totalInvoices = $invoices->count();
            $totalAmount = $invoices->sum('total_amount');
            $totalPaid = $invoices->where('status', TenantInvoice::STATUS_PAID)->sum('total_amount');
            $pendingInvoices = $invoices->where('status', TenantInvoice::STATUS_PENDING)->count();
            $paidInvoices = $invoices->where('status', TenantInvoice::STATUS_PAID)->count();
            
            return [
                'period' => $period,
                'total_invoices' => $totalInvoices,
                'total_amount' => $totalAmount,
                'formatted_total_amount' => $this->systemSetting->formatAmount($totalAmount),
                'total_paid' => $totalPaid,
                'formatted_total_paid' => $this->systemSetting->formatAmount($totalPaid),
                'pending_invoices' => $pendingInvoices,
                'paid_invoices' => $paidInvoices,
                'collection_rate' => $totalInvoices > 0 ? round(($paidInvoices / $totalInvoices) * 100, 2) : 0,
                'calculation_method' => $this->systemSetting->tenant_calculation_method,
                'tenant_monthly_dues' => $this->systemSetting->tenant_monthly_dues_amount
            ];
            
        } catch (\Exception $e) {
            Log::error("Failed to get generation summary: " . $e->getMessage());
            
            return [
                'period' => $period,
                'total_invoices' => 0,
                'total_amount' => 0,
                'total_paid' => 0,
                'pending_invoices' => 0,
                'paid_invoices' => 0,
                'collection_rate' => 0
            ];
        }
    }
}