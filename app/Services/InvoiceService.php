<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Property;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\InvoiceGeneratedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class InvoiceService
{
    /** @var \App\Services\NotificationService */
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Generate monthly invoices for all properties with auto-generation check
     * ✅ FIXED: Now generates invoices for ALL property statuses (active, inactive,
     * under_maintenance, vacant, under_construction)
     * ✅ FIXED: Uses system setting value directly (no multipliers)
     * ✅ FIXED: Routes notifications through NotificationService (email + SMS + WhatsApp)
     */
    public function generateMonthlyInvoices(?string $period = null, ?bool $forceSendNotifications = null): array
    {
        try {
            $settings = SystemSetting::getSettings();

            Log::info('Invoice generation started with settings', [
                'monthly_dues_amount'   => $settings->monthly_dues_amount,
                'calculation_method'    => $settings->calculation_method,
                'auto_generate_enabled' => $settings->isAutoInvoiceGenerationEnabled(),
                'currency_symbol'       => $settings->currency_symbol,
            ]);

            if (!$settings->isAutoInvoiceGenerationEnabled()) {
                return [
                    'success' => false,
                    'message' => 'Auto invoice generation is disabled in system settings.',
                    'count'   => 0,
                ];
            }

            $readiness = $settings->isReadyForAutoInvoiceGeneration();
            if (!$readiness['ready']) {
                return [
                    'success' => false,
                    'message' => 'System not ready for auto-invoice generation: ' . implode(', ', $readiness['issues']),
                    'count'   => 0,
                    'issues'  => $readiness['issues'],
                ];
            }

            DB::beginTransaction();

            if ($period) {
                $generationPeriod = $period;
                $periodDate       = Carbon::parse($period . '-01');
            } else {
                $periodDate       = Carbon::now()->addMonth();
                $generationPeriod = $periodDate->format('Y-m');
            }

            $dueDate = $settings->calculateDueDateForPeriod($periodDate);

            $properties = Property::all();

            Log::info('Processing invoice generation for all properties', [
                'period'           => $generationPeriod,
                'total_properties' => $properties->count(),
                'status_breakdown' => $properties->groupBy('status')->map->count()->toArray(),
            ]);

            $generatedCount          = 0;
            $skippedCount            = 0;
            $skippedDueToBulkCount   = 0;
            $failedCount             = 0;
            $updatedCount            = 0;
            $notificationsSent       = 0;
            $failedNotifications     = [];
            $generatedInvoices       = [];
            $updatedInvoices         = [];

            $shouldSendNotifications = $forceSendNotifications ?? $settings->shouldSendPaymentReminders();

            foreach ($properties as $property) {
                try {
                    if ($this->isPeriodCoveredByBulkPayment($property, $generationPeriod)) {
                        $skippedDueToBulkCount++;
                        Log::info("Skipping invoice generation for property {$property->id} - period {$generationPeriod} covered by bulk payment", [
                            'property_status' => $property->status,
                            'property_name'   => $property->property_name,
                        ]);
                        continue;
                    }

                    $existingInvoice = Invoice::where('property_id', $property->id)
                        ->where('period', $generationPeriod)
                        ->first();

                    if ($existingInvoice && $existingInvoice->status === 'paid') {
                        $skippedCount++;
                        Log::info("Skipping invoice generation for property {$property->id} - already paid", [
                            'property_status' => $property->status,
                            'invoice_id'      => $existingInvoice->id,
                        ]);
                        continue;
                    }

                    $amount = $this->calculateInvoiceAmountForProperty($property, $settings);

                    if ($amount <= 0) {
                        $skippedCount++;
                        Log::info("Skipping invoice generation for property {$property->id} - zero amount calculated", [
                            'property_status'   => $property->status,
                            'calculated_amount' => $amount,
                        ]);
                        continue;
                    }

                    $penaltyInfo   = $this->getPenaltyInformation($settings);
                    $periodDisplay = Carbon::parse($generationPeriod)->format('F Y');
                    $description   = "Monthly dues for {$periodDisplay} - Status: " . ucfirst($property->status) . $penaltyInfo;

                    if ($existingInvoice) {
                        $existingInvoice->update([
                            'amount'      => $amount,
                            'due_date'    => $dueDate,
                            'description' => $description,
                            'updated_by'  => auth()->id(),
                        ]);
                        $updatedCount++;
                        $updatedInvoices[] = $existingInvoice->id;

                        Log::info("Updated existing invoice for property", [
                            'property_id'     => $property->id,
                            'invoice_id'      => $existingInvoice->id,
                            'amount'          => $amount,
                            'status'          => $property->status,
                        ]);
                    } else {
                        $invoice = Invoice::create([
                            'property_id' => $property->id,
                            'amount'      => $amount,
                            'period'      => $generationPeriod,
                            'due_date'    => $dueDate,
                            'status'      => 'pending',
                            'description' => $description,
                            'created_by'  => auth()->id(),
                            'notes'       => "Auto-generated for period {$generationPeriod}. Property status: {$property->status}",
                        ]);
                        $generatedCount++;
                        $generatedInvoices[] = $invoice->id;

                        Log::info("Generated new invoice for property", [
                            'property_id' => $property->id,
                            'invoice_id'  => $invoice->id,
                            'amount'      => $amount,
                            'status'      => $property->status,
                        ]);

                        if ($shouldSendNotifications) {
                            $notificationResult = $this->sendInvoiceNotification($property, $invoice);
                            if ($notificationResult['sent']) {
                                $notificationsSent++;
                            } else {
                                $failedNotifications[] = [
                                    'property_id' => $property->id,
                                    'landlord_id' => $property->landlord_id,
                                    'reason'      => $notificationResult['reason'],
                                ];
                            }
                        }
                    }

                } catch (\Exception $e) {
                    $failedCount++;
                    Log::error("Failed to generate invoice for property {$property->id}: " . $e->getMessage(), [
                        'property_status' => $property->status,
                        'trace'           => $e->getTraceAsString(),
                    ]);
                }
            }

            DB::commit();

            $overdueResult = $this->markOverdueInvoices();

            $message = "Auto-invoice generation completed for {$generationPeriod}: "
                     . "{$generatedCount} generated, {$updatedCount} updated, "
                     . "{$skippedCount} skipped, {$skippedDueToBulkCount} skipped (bulk coverage)";

            if ($notificationsSent > 0) {
                $message .= ", {$notificationsSent} notifications sent";
            }

            Log::info($message, [
                'period'             => $generationPeriod,
                'generated'          => $generatedCount,
                'updated'            => $updatedCount,
                'skipped'            => $skippedCount,
                'skipped_bulk'       => $skippedDueToBulkCount,
                'failed'             => $failedCount,
                'notifications_sent' => $notificationsSent,
                'amount_per_invoice' => $settings->monthly_dues_amount,
            ]);

            return [
                'success'  => true,
                'message'  => $message,
                'count'    => $generatedCount + $updatedCount,
                'period'   => $generationPeriod,
                'due_date' => $dueDate->format('Y-m-d'),
                'details'  => [
                    'generated'              => $generatedCount,
                    'updated'                => $updatedCount,
                    'skipped'                => $skippedCount,
                    'skipped_bulk_coverage'  => $skippedDueToBulkCount,
                    'failed'                 => $failedCount,
                    'notifications_sent'     => $notificationsSent,
                    'overdue_marked'         => $overdueResult['count'] ?? 0,
                    'overdue_notified'       => $overdueResult['notified_count'] ?? 0,
                    'generated_invoices'     => $generatedInvoices,
                    'updated_invoices'       => $updatedInvoices,
                    'failed_notifications'   => $failedNotifications,
                    'amount_per_invoice'     => $settings->monthly_dues_amount,
                ],
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Monthly invoice generation failed: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to generate monthly invoices: ' . $e->getMessage(),
                'count'   => 0,
            ];
        }
    }

    /**
     * ✅ FIXED: Calculate invoice amount based on system setting
     * ✅ NO MULTIPLIERS - All properties get the same amount from system settings
     */
    private function calculateInvoiceAmountForProperty(Property $property, SystemSetting $settings): float
    {
        $amount = $settings->calculateMonthlyDuesForProperty($property);

        if ($amount === null || $amount <= 0) {
            Log::warning('Invalid amount from calculateMonthlyDuesForProperty, using monthly_dues_amount directly', [
                'property_id'          => $property->id,
                'property_status'      => $property->status,
                'calculated_amount'    => $amount,
                'monthly_dues_amount'  => $settings->monthly_dues_amount,
                'calculation_method'   => $settings->calculation_method,
            ]);
            $amount = (float) $settings->monthly_dues_amount;
        }

        Log::debug('Invoice amount calculated from system settings', [
            'property_id'          => $property->id,
            'property_status'      => $property->status,
            'amount'               => $amount,
            'source'               => $settings->calculation_method,
            'monthly_dues_amount'  => $settings->monthly_dues_amount,
        ]);

        return $amount;
    }

    /**
     * Generate bulk payment invoice for multiple months
     */
    public function generateBulkPaymentInvoice(Property $property, int $months, ?string $startMonth = null, array $invoiceIds = []): array
    {
        try {
            $settings = SystemSetting::getSettings();

            if (!$settings->isBulkPaymentEnabled()) {
                return [
                    'success' => false,
                    'message' => 'Bulk payments are currently disabled.',
                ];
            }

            if (!$settings->validateBulkMonths($months)) {
                return [
                    'success' => false,
                    'message' => "Bulk payment must be between 1 and {$settings->getMaxBulkMonths()} months.",
                ];
            }

            DB::beginTransaction();

            $startMonth = $startMonth ?? now()->startOfMonth()->format('Y-m');
            $startDate  = Carbon::createFromFormat('Y-m', $startMonth)->startOfMonth();

            $periods         = [];
            $coveragePeriods = [];

            for ($i = 0; $i < $months; $i++) {
                $periodMonth = $startDate->copy()->addMonths($i)->format('Y-m');
                $periods[]   = $periodMonth;

                $existingPaidInvoice = Invoice::where('property_id', $property->id)
                    ->where('period', $periodMonth)
                    ->where('status', 'paid')
                    ->first();

                if ($existingPaidInvoice) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => "Cannot create bulk payment. Invoice for period {$periodMonth} is already paid.",
                    ];
                }

                if ($this->isPeriodCoveredByBulkPayment($property, $periodMonth)) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => "Cannot create bulk payment. Period {$periodMonth} is already covered by an existing bulk payment.",
                    ];
                }

                $coveragePeriods[] = $periodMonth;
            }

            $existingInvoices = Invoice::where('property_id', $property->id)
                ->whereIn('period', $periods)
                ->whereIn('status', ['pending', 'overdue'])
                ->where('is_bulk_payment', false)
                ->whereNull('bulk_parent_id')
                ->get();

            if (!empty($invoiceIds)) {
                $additionalInvoices = Invoice::whereIn('id', $invoiceIds)
                    ->where('property_id', $property->id)
                    ->whereIn('status', ['pending', 'overdue'])
                    ->where('is_bulk_payment', false)
                    ->whereNull('bulk_parent_id')
                    ->whereNotIn('id', $existingInvoices->pluck('id')->toArray())
                    ->get();

                $existingInvoices = $existingInvoices->concat($additionalInvoices);
            }

            $existingInvoicesByPeriod = $existingInvoices->keyBy('period');

            $totalAmount          = 0;
            $consolidatedInvoices = [];

            $monthlyAmount = $settings->calculateMonthlyDuesForProperty($property);
            if ($monthlyAmount <= 0) {
                $monthlyAmount = (float) $settings->monthly_dues_amount;
            }

            $periodsWithExisting = [];
            $periodsNeedingNew   = [];

            foreach ($periods as $period) {
                if (isset($existingInvoicesByPeriod[$period])) {
                    $invoice              = $existingInvoicesByPeriod[$period];
                    $totalAmount         += $invoice->amount;
                    $consolidatedInvoices[] = $invoice->id;
                    $periodsWithExisting[]  = $period;
                } else {
                    $totalAmount        += $monthlyAmount;
                    $periodsNeedingNew[] = $period;
                }
            }

            $coverageStart = $startMonth;
            $coverageEnd   = Carbon::createFromFormat('Y-m', $startMonth)
                ->addMonths($months - 1)
                ->format('Y-m');

            $bulkInvoice = Invoice::create([
                'property_id'          => $property->id,
                'period'               => $startMonth . '_to_' . $coverageEnd,
                'amount'               => $totalAmount,
                'due_date'             => Carbon::now()->addDays($settings->grace_period_days ?? 14),
                'status'               => 'pending',
                'is_bulk_payment'      => true,
                'bulk_months'          => $months,
                'bulk_start_month'     => $coverageStart,
                'bulk_end_month'       => $coverageEnd,
                'covers_periods'       => json_encode($coveragePeriods),
                'bulk_coverage_start'  => $coverageStart,
                'bulk_coverage_end'    => $coverageEnd,
                'description'          => "Bulk payment for {$months} months covering: " . implode(', ', array_map(function ($p) {
                    return Carbon::parse($p . '-01')->format('M Y');
                }, $coveragePeriods)),
                'created_by'           => auth()->id(),
                'notes'                => "📦 Bulk payment created for {$months} months",
            ]);

            if (!empty($consolidatedInvoices)) {
                Invoice::whereIn('id', $consolidatedInvoices)->update([
                    'status'                  => 'consolidated',
                    'bulk_payment_id'         => $bulkInvoice->id,
                    'bulk_payment_reference'  => $bulkInvoice->id . '-BULK',
                    'payment_date'            => null,
                    'payment_method'          => null,
                    'payment_reference'       => null,
                    'notes'                   => DB::raw("CONCAT(IFNULL(notes, ''), '\n📦 Consolidated into bulk payment #{$bulkInvoice->id} on " . now()->format('Y-m-d') . "')"),
                ]);
            }

            DB::commit();

            $message = "Bulk payment invoice created for {$months} months";
            if (!empty($periodsWithExisting)) {
                $message .= " including " . count($periodsWithExisting) . " existing month(s)";
            }
            if (!empty($periodsNeedingNew)) {
                $message .= " and " . count($periodsNeedingNew) . " new month(s)";
            }

            Log::info("Bulk payment invoice created", [
                'property_id'       => $property->id,
                'months'            => $months,
                'coverage_periods'  => $coveragePeriods,
                'amount'            => $totalAmount,
                'invoice_id'        => $bulkInvoice->id,
                'property_status'   => $property->status,
                'monthly_amount'    => $monthlyAmount,
            ]);

            return [
                'success'            => true,
                'message'            => $message,
                'invoice'            => $bulkInvoice,
                'coverage_periods'   => $coveragePeriods,
                'months'             => $months,
                'consolidated_count' => count($consolidatedInvoices),
                'new_count'          => count($periodsNeedingNew),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to generate bulk payment invoice: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to create bulk payment: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Process bulk payment after payment is completed
     */
    public function processBulkPayment(Invoice $bulkInvoice, string $transactionId): array
    {
        try {
            DB::beginTransaction();

            $settings        = SystemSetting::getSettings();
            $coveragePeriods = $bulkInvoice->covers_periods;

            if (is_string($coveragePeriods)) {
                $coveragePeriods = json_decode($coveragePeriods, true);
            }

            if (empty($coveragePeriods) && $bulkInvoice->bulk_coverage_start && $bulkInvoice->bulk_coverage_end) {
                $coveragePeriods = [];
                $start           = Carbon::parse($bulkInvoice->bulk_coverage_start . '-01');
                $end             = Carbon::parse($bulkInvoice->bulk_coverage_end . '-01');
                $current         = clone $start;

                while ($current <= $end) {
                    $coveragePeriods[] = $current->format('Y-m');
                    $current->addMonth();
                }
            }

            if (empty($coveragePeriods)) {
                throw new \Exception('No coverage periods found in bulk invoice');
            }

            $bulkInvoice->update([
                'status'            => 'paid',
                'payment_date'      => now(),
                'payment_method'    => 'bulk_payment',
                'payment_reference' => $transactionId,
                'notes'             => ($bulkInvoice->notes ? $bulkInvoice->notes . "\n" : '')
                                     . "✅ Bulk payment completed on " . now()->format('Y-m-d H:i:s'),
            ]);

            $consolidatedCount = Invoice::where('bulk_payment_id', $bulkInvoice->id)
                ->update([
                    'payment_date'        => null,
                    'payment_method'      => null,
                    'payment_reference'   => $transactionId . '-CONSOLIDATED',
                    'notes'               => DB::raw("CONCAT(IFNULL(notes, ''), '\n✅ Covered by bulk payment #{$bulkInvoice->id} on " . now()->format('Y-m-d') . "')"),
                ]);

            DB::commit();

            Log::info("Bulk payment processed successfully", [
                'bulk_invoice_id'    => $bulkInvoice->id,
                'transaction_id'     => $transactionId,
                'coverage_periods'   => $coveragePeriods,
                'consolidated_count' => $consolidatedCount,
            ]);

            return [
                'success'            => true,
                'message'            => "Bulk payment processed successfully!",
                'coverage_periods'   => $coveragePeriods,
                'consolidated_count' => $consolidatedCount,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process bulk payment: ' . $e->getMessage(), [
                'bulk_invoice_id' => $bulkInvoice->id,
                'transaction_id'  => $transactionId,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to process bulk payment: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check if a period is covered by an active bulk payment
     */
    public function isPeriodCoveredByBulkPayment(Property $property, string $period): bool
    {
        try {
            $bulkInvoices = Invoice::where('property_id', $property->id)
                ->where('is_bulk_payment', true)
                ->where('status', 'paid')
                ->get();

            foreach ($bulkInvoices as $invoice) {
                if ($invoice->coversPeriod($period)) {
                    return true;
                }
            }

            return false;

        } catch (\Exception $e) {
            Log::error("Error checking bulk coverage: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all active bulk coverages for a property
     */
    public function getActiveBulkCoverages(Property $property): array
    {
        try {
            $bulkInvoices = Invoice::where('property_id', $property->id)
                ->where('is_bulk_payment', true)
                ->where('status', 'paid')
                ->orderBy('bulk_coverage_start', 'desc')
                ->get();

            $coverages = [];

            foreach ($bulkInvoices as $invoice) {
                $periods = $invoice->getCoveredPeriods();

                if (!empty($periods)) {
                    $coverages[] = [
                        'invoice_id'             => $invoice->id,
                        'invoice_number'         => $invoice->invoice_number ?? 'INV-' . str_pad($invoice->id, 6, '0', STR_PAD_LEFT),
                        'periods'                => $periods,
                        'formatted_periods'      => collect($periods)->map(function ($p) {
                            return $p ? Carbon::parse($p . '-01')->format('M Y') : '';
                        })->filter()->values()->toArray(),
                        'start'                  => $invoice->bulk_coverage_start,
                        'end'                    => $invoice->bulk_coverage_end,
                        'formatted_start'        => $invoice->bulk_coverage_start ? Carbon::parse($invoice->bulk_coverage_start . '-01')->format('M Y') : null,
                        'formatted_end'          => $invoice->bulk_coverage_end ? Carbon::parse($invoice->bulk_coverage_end . '-01')->format('M Y') : null,
                        'payment_date'           => $invoice->payment_date?->format('Y-m-d'),
                        'formatted_payment_date' => $invoice->payment_date?->format('M d, Y'),
                        'months_covered'         => count($periods),
                    ];
                }
            }

            return $coverages;

        } catch (\Exception $e) {
            Log::error("Error getting active bulk coverages: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get property coverage summary
     */
    public function getPropertyCoverageSummary(Property $property): array
    {
        try {
            $activeCoverages = $this->getActiveBulkCoverages($property);

            $coveredPeriods = [];
            foreach ($activeCoverages as $coverage) {
                $coveredPeriods = array_merge($coveredPeriods, $coverage['periods']);
            }

            $currentMonth = now()->format('Y-m');
            $nextMonth    = now()->addMonth()->format('Y-m');

            return [
                'property_id'           => $property->id,
                'has_active_coverage'   => !empty($activeCoverages),
                'active_coverages'      => $activeCoverages,
                'covered_periods'       => $coveredPeriods,
                'total_months_covered'  => count($coveredPeriods),
                'current_month_covered' => in_array($currentMonth, $coveredPeriods),
                'next_month_covered'    => in_array($nextMonth, $coveredPeriods),
            ];

        } catch (\Exception $e) {
            Log::error("Error getting property coverage summary: " . $e->getMessage());
            return [
                'property_id'           => $property->id,
                'has_active_coverage'   => false,
                'active_coverages'      => [],
                'covered_periods'       => [],
                'total_months_covered'  => 0,
                'current_month_covered' => false,
                'next_month_covered'    => false,
            ];
        }
    }

    /**
     * Send invoice notification to landlord.
     *
     * ✅ REWRITTEN: Now delegates to NotificationService so email + SMS +
     * WhatsApp all fire according to the System Setting channel arrays.
     */
    private function sendInvoiceNotification(Property $property, Invoice $invoice): array
    {
        try {
            $landlord = $property->landlord;

            if (!$landlord) {
                return ['sent' => false, 'reason' => 'No landlord found'];
            }

            $settings = SystemSetting::getSettings();

            $hasEmail = !empty(trim($landlord->email ?? ''));
            $hasPhone = !empty(trim($landlord->phone ?? ''));

            if (!$hasEmail && !$hasPhone) {
                return ['sent' => false, 'reason' => 'Landlord has neither email nor phone'];
            }

            $result = $this->notificationService->sendLandlordInvoiceCreated($invoice);

            $dispatched = $result['dispatched'] ?? [];
            $sent       = !empty(array_filter($dispatched));

            if ($sent) {
                $invoice->addNote(
                    "Invoice generated notification dispatched to landlord on "
                    . now()->format('Y-m-d H:i:s')
                    . " via: " . implode(', ', array_keys(array_filter($dispatched)))
                );

                Log::info('[InvoiceService] Landlord invoice-created notification dispatched', [
                    'invoice_id'  => $invoice->id,
                    'landlord_id' => $landlord->id,
                    'channels'    => $result['channels']   ?? [],
                    'dispatched'  => $dispatched,
                    'skipped'     => $result['skipped']    ?? [],
                ]);

                return ['sent' => true, 'reason' => 'Success', 'dispatched' => $dispatched];
            }

            Log::warning('[InvoiceService] Landlord invoice-created notification dispatched to zero channels', [
                'invoice_id'  => $invoice->id,
                'landlord_id' => $landlord->id,
                'channels'    => $result['channels'] ?? [],
                'skipped'     => $result['skipped']  ?? [],
            ]);

            return [
                'sent'   => false,
                'reason' => 'Notification dispatched to zero channels',
                'result' => $result,
            ];

        } catch (\Throwable $e) {
            Log::error('[InvoiceService] Failed to send invoice notification: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
                'property_id' => $property->id,
            ]);
            return ['sent' => false, 'reason' => $e->getMessage()];
        }
    }

    /**
     * Mark all overdue invoices - ✅ FIXED: Exclude consolidated invoices
     * ✅ NEW: Sends overdue notification to landlord via NotificationService
     */
    public function markOverdueInvoices(): array
    {
        try {
            $overdueCount        = 0;
            $penaltyAppliedCount = 0;
            $notifiedCount       = 0;
            $failedNotifications = [];
            $settings            = SystemSetting::getSettings();

            $overdueInvoices = Invoice::where('status', 'pending')
                ->where('status', '!=', Invoice::STATUS_CONSOLIDATED)
                ->whereDate('due_date', '<', now()->startOfDay())
                ->with(['property.landlord'])
                ->get();

            foreach ($overdueInvoices as $invoice) {
                $daysOverdue = abs(now()->diffInDays($invoice->due_date, false));

                if ($daysOverdue > $settings->grace_period_days && $invoice->penalty_amount == 0) {
                    $penaltyAmount = $settings->calculatePenalty($invoice->amount, $daysOverdue);

                    if ($penaltyAmount > 0) {
                        $invoice->update([
                            'penalty_amount'       => $penaltyAmount,
                            'penalty_applied_date' => now(),
                            'status'               => 'overdue',
                        ]);
                        $penaltyAppliedCount++;

                        Log::info("Applied penalty of {$penaltyAmount} to invoice #{$invoice->id}", [
                            'days_overdue' => $daysOverdue,
                            'grace_period' => $settings->grace_period_days,
                        ]);
                    } else {
                        $invoice->update(['status' => 'overdue']);
                    }
                } else {
                    $invoice->update(['status' => 'overdue']);
                }

                $overdueCount++;

                // ── Notify the landlord via every configured channel ──
                try {
                    $result = $this->notificationService->sendLandlordInvoiceOverdue($invoice->fresh());

                    $dispatched = $result['dispatched'] ?? [];

                    if (!empty(array_filter($dispatched))) {
                        $notifiedCount++;
                        $invoice->addNote(
                            "Overdue notification sent on " . now()->format('Y-m-d H:i:s')
                            . " via: " . implode(', ', array_keys(array_filter($dispatched)))
                        );
                    } else {
                        $failedNotifications[] = [
                            'invoice_id' => $invoice->id,
                            'reason'     => 'Dispatched to zero channels',
                            'channels'   => $result['channels'] ?? [],
                            'skipped'    => $result['skipped']  ?? [],
                        ];
                    }
                } catch (\Throwable $e) {
                    $failedNotifications[] = [
                        'invoice_id' => $invoice->id,
                        'reason'     => $e->getMessage(),
                    ];
                    Log::error('[InvoiceService] Failed to send overdue notification', [
                        'invoice_id' => $invoice->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            Log::info("Marked {$overdueCount} invoices as overdue, applied penalties to {$penaltyAppliedCount}, notified {$notifiedCount} landlords");

            return [
                'success'                => true,
                'count'                  => $overdueCount,
                'penalty_applied_count'  => $penaltyAppliedCount,
                'notified_count'         => $notifiedCount,
                'failed_notifications'   => $failedNotifications,
                'message'                => "Marked {$overdueCount} invoices as overdue, applied penalties to {$penaltyAppliedCount}, notified {$notifiedCount} landlords",
            ];
        } catch (\Exception $e) {
            Log::error("Failed to mark overdue invoices: " . $e->getMessage());
            return [
                'success' => false,
                'count'   => 0,
                'message' => 'Failed to mark overdue invoices: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get landlord's outstanding invoices
     */
    public function getLandlordOutstandingInvoices(int $landlordId): array
    {
        try {
            $propertyIds = Property::where('landlord_id', $landlordId)->pluck('id');

            $invoices = Invoice::whereIn('property_id', $propertyIds)
                ->whereIn('status', ['pending', 'overdue'])
                ->whereNull('deleted_at')
                ->with(['property'])
                ->orderBy('due_date', 'asc')
                ->get();

            $totalDue = $invoices->sum('total_amount');

            return [
                'success'            => true,
                'total_due'          => $totalDue,
                'outstanding_count'  => $invoices->count(),
                'overdue_count'      => $invoices->where('status', 'overdue')->count(),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get landlord outstanding invoices: ' . $e->getMessage());
            return [
                'success'            => false,
                'total_due'          => 0,
                'outstanding_count'  => 0,
                'overdue_count'      => 0,
            ];
        }
    }

    /**
     * Get payment summary for selected invoices
     */
    public function getPaymentSummary(array $invoiceIds): array
    {
        try {
            $settings = SystemSetting::getSettings();

            $invoices = Invoice::with(['property', 'property.landlord'])
                ->whereIn('id', $invoiceIds)
                ->where('status', '!=', 'consolidated')
                ->where('status', '!=', 'paid')
                ->get();

            if ($invoices->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No valid invoices found for payment.',
                    'total'   => 0,
                    'count'   => 0,
                ];
            }

            $totalAmount = $invoices->sum('total_amount');

            $groupedByProperty = $invoices->groupBy('property_id')->map(function ($propertyInvoices) use ($settings) {
                $property = $propertyInvoices->first()->property;
                return [
                    'property_name'    => $property->property_name ?? $property->street_name,
                    'property_address' => $property->house_number . ' ' . $property->street_name,
                    'invoices'         => $propertyInvoices->map(function ($invoice) use ($settings) {
                        return [
                            'id'                => $invoice->id,
                            'invoice_number'    => $invoice->invoice_number,
                            'period'            => $invoice->period,
                            'formatted_period'  => $this->formatPeriodForDisplay($invoice),
                            'amount'            => $invoice->amount,
                            'penalty_amount'    => $invoice->penalty_amount ?? 0,
                            'total_amount'      => $invoice->total_amount,
                            'formatted_amount'  => $settings->formatAmount($invoice->total_amount),
                            'due_date'          => $invoice->due_date->format('Y-m-d'),
                            'status'            => $invoice->status,
                        ];
                    }),
                    'total'            => $propertyInvoices->sum('total_amount'),
                    'formatted_total'  => $settings->formatAmount($propertyInvoices->sum('total_amount')),
                    'count'            => $propertyInvoices->count(),
                ];
            });

            return [
                'success'                => true,
                'total'                  => $totalAmount,
                'formatted_total'        => $settings->formatAmount($totalAmount),
                'count'                  => $invoices->count(),
                'invoices'               => $invoices->map(function ($invoice) use ($settings) {
                    return [
                        'id'                => $invoice->id,
                        'invoice_number'    => $invoice->invoice_number,
                        'property_name'     => $invoice->property->property_name ?? $invoice->property->street_name,
                        'period'            => $invoice->period,
                        'formatted_period'  => $this->formatPeriodForDisplay($invoice),
                        'amount'            => $invoice->amount,
                        'penalty_amount'    => $invoice->penalty_amount ?? 0,
                        'total_amount'      => $invoice->total_amount,
                        'formatted_amount'  => $settings->formatAmount($invoice->total_amount),
                        'due_date'          => $invoice->due_date->format('Y-m-d'),
                        'status'            => $invoice->status,
                        'is_overdue'        => $invoice->isOverdue(),
                    ];
                }),
                'grouped_by_property'    => $groupedByProperty,
                'has_penalties'          => $invoices->where('penalty_amount', '>', 0)->count() > 0,
                'total_penalty_amount'   => $invoices->sum('penalty_amount'),
                'formatted_total_penalty'=> $settings->formatAmount($invoices->sum('penalty_amount')),
                'currency_symbol'        => $settings->currency_symbol,
                'settings'               => [
                    'grace_period'  => $settings->grace_period_days,
                    'late_penalty'  => $settings->late_payment_percentage,
                    'fixed_penalty' => $settings->fixed_penalty_amount,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get payment summary: ' . $e->getMessage(), [
                'invoice_ids' => $invoiceIds,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to get payment summary: ' . $e->getMessage(),
                'total'   => 0,
                'count'   => 0,
            ];
        }
    }

    /**
     * Format period for display, handling both regular and bulk invoices
     */
    private function formatPeriodForDisplay(Invoice $invoice): string
    {
        if ($invoice->is_bulk_payment && $invoice->bulk_coverage_start && $invoice->bulk_coverage_end) {
            try {
                $start = Carbon::parse($invoice->bulk_coverage_start . '-01');
                $end   = Carbon::parse($invoice->bulk_coverage_end . '-01');
                return $start->format('M Y') . ' - ' . $end->format('M Y');
            } catch (\Exception $e) {
                return $this->formatPeriodFromString($invoice->period);
            }
        }

        return $this->formatPeriodFromString($invoice->period);
    }

    /**
     * Safely format a period string (YYYY-MM) to a readable month/year
     */
    private function formatPeriodFromString(string $period): string
    {
        if (empty($period)) {
            return 'N/A';
        }

        try {
            if (strpos($period, '_to_') !== false) {
                $parts = explode('_to_', $period);
                if (count($parts) === 2) {
                    try {
                        $start = Carbon::parse($parts[0] . '-01');
                        $end   = Carbon::parse($parts[1] . '-01');
                        return $start->format('M Y') . ' - ' . $end->format('M Y');
                    } catch (\Exception $e) {
                        return $period;
                    }
                }
                return $period;
            }

            if (preg_match('/^\d{4}-\d{2}$/', $period)) {
                return Carbon::parse($period . '-01')->format('F Y');
            }

            return $period;
        } catch (\Exception $e) {
            Log::warning("Failed to format period: {$period}", ['error' => $e->getMessage()]);
            return $period;
        }
    }

    /**
     * Get penalty information for descriptions
     */
    private function getPenaltyInformation(SystemSetting $settings, bool $forDisplay = false): string
    {
        $penaltyInfo = "";

        if ($settings->fixed_penalty_amount > 0) {
            $penaltyAmount = $settings->formatAmount($settings->fixed_penalty_amount);
            if ($forDisplay) {
                $penaltyInfo = "Fixed penalty: {$penaltyAmount} after {$settings->grace_period_days} days grace period";
            } else {
                $penaltyInfo = ". Late payment penalty: {$penaltyAmount} after {$settings->grace_period_days} days";
            }
        } elseif ($settings->late_payment_percentage > 0) {
            $percentage = $settings->late_payment_percentage;
            if ($forDisplay) {
                $penaltyInfo = "Late fee: {$percentage}% after {$settings->grace_period_days} days grace period";
            } else {
                $penaltyInfo = ". Late payment fee: {$percentage}% after {$settings->grace_period_days} days";
            }
        }

        return $penaltyInfo;
    }

    /**
     * Validate period format (YYYY-MM)
     */
    private function isValidPeriod(string $period): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}$/', $period);
    }

    /**
     * Check if invoice should be generated for a property and period
     */
    public function shouldGenerateInvoice(int $propertyId, string $period): bool
    {
        $property = Property::find($propertyId);

        if (!$property) {
            return false;
        }

        if ($this->isPeriodCoveredByBulkPayment($property, $period)) {
            return false;
        }

        $existingInvoice = Invoice::where('property_id', $propertyId)
            ->where('period', $period)
            ->first();

        if ($existingInvoice && $existingInvoice->status === 'paid') {
            return false;
        }

        return true;
    }

    /**
     * Get bulk payment options for a property
     */
    public function getBulkPaymentOptions(Property $property): array
    {
        $settings = SystemSetting::getSettings();

        if (!$settings->isBulkPaymentEnabled()) {
            return [
                'enabled' => false,
                'message' => 'Bulk payments are disabled',
                'options' => [],
            ];
        }

        $activeCoverages = $this->getActiveBulkCoverages($property);

        $availableMonths = [];
        $currentMonth    = now()->startOfMonth();

        for ($i = 0; $i < 12; $i++) {
            $checkMonth = $currentMonth->copy()->addMonths($i)->format('Y-m');
            if (!$this->isPeriodCoveredByBulkPayment($property, $checkMonth)) {
                $availableMonths[] = $checkMonth;
            }
        }

        return [
            'enabled'                   => true,
            'has_active_coverage'       => !empty($activeCoverages),
            'active_coverages'          => $activeCoverages,
            'available_months'          => $availableMonths,
            'max_months'                => $settings->getMaxBulkMonths(),
            'monthly_amount'            => $settings->calculateMonthlyDuesForProperty($property),
            'formatted_monthly_amount'  => $settings->formatAmount($settings->calculateMonthlyDuesForProperty($property)),
            'discount_percentage'       => $settings->bulk_payment_discount ?? 0,
        ];
    }

    /**
     * Manually generate a single invoice
     * ✅ UPDATED: Now supports all property statuses with system setting value
     */
    public function generateManualInvoice(int $propertyId, string $period, ?float $amount = null, Carbon $dueDate, ?bool $sendNotification = null): array
    {
        try {
            DB::beginTransaction();

            if (!$this->isValidPeriod($period)) {
                return [
                    'success' => false,
                    'message' => 'Invalid period format. Please use YYYY-MM format.',
                ];
            }

            $property = Property::find($propertyId);
            if (!$property) {
                return [
                    'success' => false,
                    'message' => 'Property not found.',
                ];
            }

            if ($this->isPeriodCoveredByBulkPayment($property, $period)) {
                return [
                    'success' => false,
                    'message' => 'Cannot generate manual invoice. This period is already covered by an active bulk payment.',
                ];
            }

            $existingInvoice = Invoice::where('property_id', $propertyId)
                ->where('period', $period)
                ->first();

            if ($existingInvoice && $existingInvoice->status === 'paid') {
                return [
                    'success' => false,
                    'message' => 'An invoice for this property and period already exists and has been paid.',
                ];
            }

            $settings = SystemSetting::getSettings();
            if ($amount === null || $amount <= 0) {
                $amount = $this->calculateInvoiceAmountForProperty($property, $settings);
            }

            $penaltyInfo            = $this->getPenaltyInformation($settings);
            $periodDisplay          = Carbon::parse($period)->format('F Y');
            $description            = "Manual invoice for {$periodDisplay} - Status: " . ucfirst($property->status) . $penaltyInfo;
            $shouldSendNotification = $sendNotification ?? $settings->shouldSendPaymentReminders();

            $invoice = Invoice::create([
                'property_id' => $propertyId,
                'amount'      => $amount,
                'period'      => $period,
                'due_date'    => $dueDate,
                'status'      => 'pending',
                'description' => $description,
                'created_by'  => auth()->id(),
                'notes'       => "Manual invoice generated for period {$period}. Property status: {$property->status}",
            ]);

            DB::commit();

            $notificationResult = ['sent' => false];
            if ($shouldSendNotification) {
                $notificationResult = $this->sendInvoiceNotification($property, $invoice);
            }

            return [
                'success'           => true,
                'message'           => "Invoice generated successfully.",
                'invoice_id'        => $invoice->id,
                'notification_sent' => $notificationResult['sent'],
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Manual invoice generation failed: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to generate invoice: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get invoices with applied filters
     */
    public function getInvoicesWithFilters(array $filters, ?User $user = null)
    {
        $query = Invoice::with(['property', 'property.landlord', 'bulkPayment', 'childInvoices'])
            ->whereNull('deleted_at');

        if (!empty($filters['property_id'])) {
            $query->where('property_id', $filters['property_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            if (empty($filters['status']) || $filters['status'] !== 'consolidated') {
                $query->where('status', '!=', 'consolidated');
            }
        }

        if (!empty($filters['period'])) {
            $query->where('period', $filters['period']);
        }

        if (!empty($filters['type'])) {
            if ($filters['type'] === 'bulk') {
                $query->where('is_bulk_payment', true);
            } elseif ($filters['type'] === 'regular') {
                $query->where('is_bulk_payment', false)
                      ->whereNull('bulk_payment_id');
            }
        }

        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('invoice_number', 'LIKE', $searchTerm)
                  ->orWhere('payment_reference', 'LIKE', $searchTerm)
                  ->orWhereHas('property', function ($propertyQuery) use ($searchTerm) {
                      $propertyQuery->where('street_name', 'LIKE', $searchTerm)
                                    ->orWhere('house_number', 'LIKE', $searchTerm);
                  });
            });
        }

        if ($user && !$user->isSuperAdmin() && !$user->isAdmin() && $user->isLandlord()) {
            $propertyIds = Property::where('landlord_id', $user->id)->pluck('id');
            $query->whereIn('property_id', $propertyIds);
        }

        $query->orderBy('due_date', 'desc');

        return $query->paginate(15);
    }

    /**
     * Get invoice statistics for dashboard
     */
    public function getInvoiceStatistics(?User $user = null): array
    {
        try {
            $query = Invoice::query()->whereNull('deleted_at');

            if ($user) {
                if ($user->isLandlord()) {
                    $propertyIds = Property::where('landlord_id', $user->id)->pluck('id');
                    $query->whereIn('property_id', $propertyIds);
                }
            }

            $totalInvoices      = (clone $query)->where('status', '!=', 'consolidated')->count();
            $paidInvoices       = (clone $query)->where('status', 'paid')->where('status', '!=', 'consolidated')->count();
            $pendingInvoices    = (clone $query)->where('status', 'pending')->where('status', '!=', 'consolidated')->count();
            $overdueInvoices    = (clone $query)->where('status', 'overdue')->where('status', '!=', 'consolidated')->count();
            $totalPenalties     = (clone $query)->where('status', '!=', 'consolidated')->where('penalty_amount', '>', 0)->sum('penalty_amount');
            $totalDue           = (clone $query)->whereIn('status', ['pending', 'overdue'])->where('status', '!=', 'consolidated')->sum('total_amount');
            $totalRevenue       = (clone $query)->where('status', 'paid')->where('status', '!=', 'consolidated')->sum('total_amount');
            $consolidatedCount  = Invoice::where('status', 'consolidated')->whereNull('deleted_at')->count();

            $collectionRate = $totalInvoices > 0 ? round(($paidInvoices / $totalInvoices) * 100, 2) : 0;

            return [
                'total_invoices'         => $totalInvoices,
                'paid_invoices'          => $paidInvoices,
                'pending_invoices'       => $pendingInvoices,
                'overdue_invoices'       => $overdueInvoices,
                'consolidated_invoices'  => $consolidatedCount,
                'total_due'              => $totalDue,
                'total_revenue'          => $totalRevenue,
                'total_penalties'        => $totalPenalties,
                'collection_rate'        => $collectionRate,
            ];

        } catch (\Exception $e) {
            Log::error("Failed to get invoice statistics: " . $e->getMessage());
            return [
                'total_invoices'         => 0,
                'paid_invoices'          => 0,
                'pending_invoices'       => 0,
                'overdue_invoices'       => 0,
                'consolidated_invoices'  => 0,
                'total_due'              => 0,
                'total_revenue'          => 0,
                'total_penalties'        => 0,
                'collection_rate'        => 0,
            ];
        }
    }

    /**
     * Get notification status for an invoice
     */
    public function getInvoiceNotificationStatus(int $invoiceId): array
    {
        try {
            $invoice  = Invoice::with('property.landlord')->findOrFail($invoiceId);
            $landlord = $invoice->property->landlord;

            if (!$landlord) {
                return [
                    'generated_notification_sent' => false,
                    'generated_sent_at'           => null,
                    'reminder_sent'               => false,
                    'last_reminder_sent_at'       => null,
                    'overdue_notification_sent'   => false,
                    'overdue_sent_at'             => null,
                    'has_reminder_schedule'       => false,
                    'next_reminder_date'          => null,
                ];
            }

            $settings = SystemSetting::getSettings();

            $notificationSent = $landlord->notifications()
                ->where('type', 'App\Notifications\InvoiceGeneratedNotification')
                ->where('data->invoice_id', $invoiceId)
                ->exists();

            $sentAt = null;
            if ($notificationSent) {
                $notification = $landlord->notifications()
                    ->where('type', 'App\Notifications\InvoiceGeneratedNotification')
                    ->where('data->invoice_id', $invoiceId)
                    ->first();
                $sentAt = $notification ? $notification->created_at : null;
            }

            $reminderSent = $landlord->notifications()
                ->where('type', 'App\Notifications\PaymentReminderNotification')
                ->where('data->invoice_id', $invoiceId)
                ->exists();

            $lastReminderAt = null;
            if ($reminderSent) {
                $reminder = $landlord->notifications()
                    ->where('type', 'App\Notifications\PaymentReminderNotification')
                    ->where('data->invoice_id', $invoiceId)
                    ->latest()
                    ->first();
                $lastReminderAt = $reminder ? $reminder->created_at : null;
            }

            $overdueSent = $landlord->notifications()
                ->where('type', 'App\Notifications\OverdueNotification')
                ->where('data->invoice_id', $invoiceId)
                ->exists();

            $overdueSentAt = null;
            if ($overdueSent) {
                $overdue = $landlord->notifications()
                    ->where('type', 'App\Notifications\OverdueNotification')
                    ->where('data->invoice_id', $invoiceId)
                    ->first();
                $overdueSentAt = $overdue ? $overdue->created_at : null;
            }

            $hasReminderSchedule = false;
            $nextReminderDate    = null;

            if ($invoice->status == 'pending' && $invoice->due_date > now()) {
                $hasReminderSchedule = true;
                $reminderDays        = $settings->getReminderDaysBefore();
                $nextReminderDate    = $invoice->due_date->copy()->subDays($reminderDays);

                if ($nextReminderDate < now()) {
                    $nextReminderDate = null;
                }
            }

            return [
                'generated_notification_sent' => $notificationSent,
                'generated_sent_at'           => $sentAt,
                'reminder_sent'               => $reminderSent,
                'last_reminder_sent_at'       => $lastReminderAt,
                'overdue_notification_sent'   => $overdueSent,
                'overdue_sent_at'             => $overdueSentAt,
                'has_reminder_schedule'       => $hasReminderSchedule,
                'next_reminder_date'          => $nextReminderDate,
            ];

        } catch (\Exception $e) {
            Log::error("Failed to get notification status for invoice {$invoiceId}: " . $e->getMessage());

            return [
                'generated_notification_sent' => false,
                'generated_sent_at'           => null,
                'reminder_sent'               => false,
                'last_reminder_sent_at'       => null,
                'overdue_notification_sent'   => false,
                'overdue_sent_at'             => null,
                'has_reminder_schedule'       => false,
                'next_reminder_date'          => null,
            ];
        }
    }

    /**
     * Validate invoice configuration
     */
    public function validateInvoiceConfiguration(): array
    {
        try {
            $settings = SystemSetting::getSettings();

            $config = [
                'auto_generate_invoices'        => $settings->auto_generate_invoices,
                'send_payment_reminders'        => $settings->send_payment_reminders,
                'reminder_days_before'          => $settings->reminder_days_before,
                'grace_period_days'             => $settings->grace_period_days,
                'monthly_dues_amount'           => $settings->monthly_dues_amount,
                'calculation_method'            => $settings->calculation_method,
                'payment_recipient_configured'  => $settings->isPaymentRecipientConfigured(),
                'payment_methods_enabled'       => $settings->hasEnabledPaymentMethods(),
            ];

            $issues = [];

            if ($config['auto_generate_invoices'] && !$config['payment_recipient_configured']) {
                $issues[] = "Auto-generation is enabled but payment recipient is not configured";
            }

            if ($config['auto_generate_invoices'] && !$config['payment_methods_enabled']) {
                $issues[] = "Auto-generation is enabled but no payment methods are enabled";
            }

            if ($config['send_payment_reminders'] && $config['reminder_days_before'] < 1) {
                $issues[] = "reminder_days_before must be at least 1 when reminders are enabled";
            }

            if ($config['calculation_method'] === 'fixed' && $config['monthly_dues_amount'] <= 0) {
                $issues[] = "monthly_dues_amount must be greater than 0 for fixed calculation method";
            }

            if ($config['grace_period_days'] < 0 || $config['grace_period_days'] > 30) {
                $issues[] = "grace_period_days should be between 0 and 30";
            }

            $isValid = empty($issues);

            if ($isValid) {
                Log::info("Invoice configuration validation passed", $config);
            } else {
                Log::warning("Invoice configuration validation failed", [
                    'issues'         => $issues,
                    'current_config' => $config,
                ]);
            }

            return [
                'valid'  => $isValid,
                'issues' => $issues,
                'config' => $config,
            ];

        } catch (\Exception $e) {
            Log::error("Failed to validate invoice configuration: " . $e->getMessage());

            return [
                'valid'  => false,
                'issues' => ['Validation error: ' . $e->getMessage()],
                'config' => [],
            ];
        }
    }

    /**
     * Process payment for selected invoices
     */
    public function processPaymentForInvoices(array $invoiceIds, string $transactionId, string $paymentMethod): array
    {
        try {
            DB::beginTransaction();

            $invoices = Invoice::with(['property', 'property.landlord'])
                ->whereIn('id', $invoiceIds)
                ->where('status', '!=', 'consolidated')
                ->where('status', '!=', 'paid')
                ->get();

            if ($invoices->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No valid invoices found for payment.',
                ];
            }

            $totalAmount   = $invoices->sum('total_amount');
            $updatedCount  = 0;
            $bulkInvoiceId = null;

            if ($invoices->count() > 1) {
                $firstInvoice = $invoices->first();
                $periods      = $invoices->pluck('period')->unique()->toArray();

                $bulkInvoice = Invoice::create([
                    'property_id'          => $firstInvoice->property_id,
                    'period'               => min($periods) . '_to_' . max($periods),
                    'amount'               => $totalAmount,
                    'due_date'             => now()->addDays(14),
                    'status'               => 'paid',
                    'is_bulk_payment'      => true,
                    'bulk_months'          => count($periods),
                    'bulk_start_month'     => min($periods),
                    'bulk_end_month'       => max($periods),
                    'covers_periods'       => json_encode($periods),
                    'bulk_coverage_start'  => min($periods),
                    'bulk_coverage_end'    => max($periods),
                    'payment_date'         => now(),
                    'payment_method'       => $paymentMethod,
                    'payment_reference'    => $transactionId,
                    'description'          => "Bulk payment covering: " . implode(', ', array_map(function ($p) {
                        return Carbon::parse($p . '-01')->format('M Y');
                    }, $periods)),
                    'created_by'           => auth()->id(),
                    'notes'                => "📦 Bulk payment created from " . count($invoices) . " individual invoices",
                ]);

                $bulkInvoiceId = $bulkInvoice->id;

                foreach ($invoices as $invoice) {
                    $invoice->update([
                        'status'                  => 'consolidated',
                        'bulk_payment_id'         => $bulkInvoice->id,
                        'bulk_payment_reference'  => $transactionId,
                        'payment_date'            => null,
                        'payment_method'          => null,
                        'payment_reference'       => null,
                        'notes'                   => ($invoice->notes ? $invoice->notes . "\n" : '')
                                                     . "📦 Consolidated into bulk payment #{$bulkInvoice->id}",
                    ]);
                    $updatedCount++;
                }

                DB::commit();

                return [
                    'success'         => true,
                    'message'         => "Payment processed successfully. {$updatedCount} invoices consolidated into bulk payment.",
                    'bulk_invoice_id' => $bulkInvoiceId,
                    'total_amount'    => $totalAmount,
                    'count'           => $updatedCount,
                    'is_bulk'         => true,
                ];
            }

            $invoice = $invoices->first();
            $invoice->update([
                'status'            => 'paid',
                'payment_date'      => now(),
                'payment_method'    => $paymentMethod,
                'payment_reference' => $transactionId,
                'notes'             => ($invoice->notes ? $invoice->notes . "\n" : '')
                                     . "✅ Payment received via {$paymentMethod} on " . now()->format('Y-m-d H:i:s'),
            ]);

            DB::commit();

            return [
                'success'      => true,
                'message'      => "Payment processed successfully for invoice #{$invoice->invoice_number}",
                'invoice_id'   => $invoice->id,
                'total_amount' => $totalAmount,
                'count'        => 1,
                'is_bulk'      => false,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process payment for invoices: ' . $e->getMessage(), [
                'invoice_ids'    => $invoiceIds,
                'transaction_id' => $transactionId,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to process payment: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send invoice status update notification
     */
    public function sendInvoiceStatusUpdateNotification(int $invoiceId, string $status): array
    {
        try {
            $invoice  = Invoice::with(['property', 'property.landlord'])->findOrFail($invoiceId);
            $landlord = $invoice->property->landlord;

            if (!$landlord) {
                return ['sent' => false, 'reason' => 'No landlord found'];
            }

            $settings = SystemSetting::getSettings();
            if (!$settings->shouldSendPaymentReminders()) {
                return ['sent' => false, 'reason' => 'Notifications disabled'];
            }

            $result = match ($status) {
                'paid'    => $this->notificationService->sendLandlordPaymentConfirmation($invoice),
                'overdue' => $this->notificationService->sendLandlordInvoiceOverdue($invoice),
                'pending' => $this->notificationService->sendLandlordInvoiceReminder($invoice),
                default   => null,
            };

            if ($result === null) {
                return ['sent' => false, 'reason' => 'Unknown status'];
            }

            $dispatched = $result['dispatched'] ?? [];
            $sent       = !empty(array_filter($dispatched));

            if ($sent) {
                $invoice->addNote(
                    "Status update notification dispatched to landlord on "
                    . now()->format('Y-m-d H:i:s')
                    . " via: " . implode(', ', array_keys(array_filter($dispatched)))
                );
            }

            return [
                'sent'       => $sent,
                'reason'     => $sent ? 'Success' : 'Dispatched to zero channels',
                'dispatched' => $dispatched,
                'channels'   => $result['channels'] ?? [],
                'skipped'    => $result['skipped']  ?? [],
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send invoice status update notification: ' . $e->getMessage());
            return ['sent' => false, 'reason' => $e->getMessage()];
        }
    }
}