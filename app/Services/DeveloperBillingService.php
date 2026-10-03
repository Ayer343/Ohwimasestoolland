<?php

namespace App\Services;

use App\Models\AdminBillingRecord;
use App\Models\AgreementSignature;
use App\Models\BillingInvoice;
use App\Models\DeveloperBillingRecord;
use App\Models\DeveloperSetting;
use App\Models\InvoiceReminder;
use App\Models\SuperAdminPaymentRecord;
use App\Models\User;
use App\Jobs\SendInvoiceEmailJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Mail\DeveloperInvoiceMail;

class DeveloperBillingService
{
    /* ============================================================
     | LEGACY: DeveloperBillingRecord–based methods (unchanged)
     | Keep for backwards compatibility with existing views and
     | controllers that still use the old DeveloperBillingRecord.
     * ============================================================ */

    public function getBillingOverview()
    {
        try {
            $settings = DeveloperSetting::first();
            if (!$settings) {
                return $this->getEmptyBillingOverview();
            }

            $totalPaid = DeveloperBillingRecord::where('developer_setting_id', $settings->id)
                ->where('payment_status', 'paid')
                ->sum('amount');

            $pendingAmount = DeveloperBillingRecord::where('developer_setting_id', $settings->id)
                ->where('payment_status', 'pending')
                ->sum('amount');

            $lastPayment = DeveloperBillingRecord::where('developer_setting_id', $settings->id)
                ->where('payment_status', 'paid')
                ->orderBy('paid_date', 'desc')
                ->first();

            $nextBillingDate = $this->calculateNextBillingDate($settings);

            return [
                'current_plan' => [
                    'amount'   => $settings->monthly_billing_amount,
                    'currency' => $settings->billing_currency,
                    'cycle'    => $settings->billing_cycle,
                    'status'   => $settings->billing_status,
                ],
                'totals' => [
                    'paid'           => $totalPaid,
                    'pending'        => $pendingAmount,
                    'total_invoiced' => $totalPaid + $pendingAmount,
                ],
                'last_payment' => $lastPayment ? [
                    'amount'         => $lastPayment->amount,
                    'date'           => $lastPayment->paid_date,
                    'method'         => $lastPayment->payment_method,
                    'invoice_number' => $lastPayment->invoice_number,
                ] : null,
                'next_billing' => [
                    'date'             => $nextBillingDate,
                    'estimated_amount' => $settings->monthly_billing_amount,
                    'days_until_due'   => $nextBillingDate
                        ? now()->diffInDays(Carbon::parse($nextBillingDate), false)
                        : null,
                ],
                'payment_method' => [
                    'type'          => $settings->payment_method,
                    'mobile_number' => $settings->payment_mobile_number,
                    'account_name'  => $settings->payment_account_name,
                    'bank_name'     => $settings->payment_bank_name,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get billing overview: ' . $e->getMessage());
            return $this->getEmptyBillingOverview();
        }
    }

    private function getEmptyBillingOverview()
    {
        return [
            'current_plan' => [
                'amount'   => 0,
                'currency' => 'GHS',
                'cycle'    => 'monthly',
                'status'   => 'pending',
            ],
            'totals' => [
                'paid'           => 0,
                'pending'        => 0,
                'total_invoiced' => 0,
            ],
            'last_payment'   => null,
            'next_billing'   => null,
            'payment_method' => null,
        ];
    }

    public function getUpcomingInvoice()
    {
        try {
            $settings = DeveloperSetting::first();
            if (!$settings) {
                return null;
            }

            $pendingInvoice = DeveloperBillingRecord::where('developer_setting_id', $settings->id)
                ->where('payment_status', 'pending')
                ->where('invoice_date', '<=', now()->addDays(7))
                ->orderBy('invoice_date', 'asc')
                ->first();

            if ($pendingInvoice) {
                return [
                    'id'             => $pendingInvoice->id,
                    'invoice_number' => $pendingInvoice->invoice_number,
                    'amount'         => $pendingInvoice->amount,
                    'currency'       => $pendingInvoice->currency,
                    'status'         => $pendingInvoice->payment_status,
                    'invoice_date'   => $pendingInvoice->invoice_date,
                    'due_date'       => $pendingInvoice->due_date,
                    'days_until_due' => now()->diffInDays(
                        Carbon::parse($pendingInvoice->due_date),
                        false
                    ),
                ];
            }

            return $this->generateUpcomingInvoice($settings);
        } catch (\Exception $e) {
            Log::error('Failed to get upcoming invoice: ' . $e->getMessage());
            return null;
        }
    }

    private function generateUpcomingInvoice(DeveloperSetting $settings)
    {
        $nextBillingDate = $this->calculateNextBillingDate($settings);
        if (!$nextBillingDate) {
            return null;
        }

        $invoiceDate = Carbon::parse($nextBillingDate)->subDays(3);
        $dueDate     = Carbon::parse($nextBillingDate);

        return [
            'id'             => null,
            'invoice_number' => 'UPCOMING-' . Str::random(6),
            'amount'         => $settings->monthly_billing_amount,
            'currency'       => $settings->billing_currency,
            'status'         => 'upcoming',
            'invoice_date'   => $invoiceDate,
            'due_date'       => $dueDate,
            'days_until_due' => now()->diffInDays($dueDate, false),
        ];
    }

    /**
     * Calculate the next billing date for a developer.
     *
     * FIX: The original implementation reused `$startDate->copy()`
     * inside a loop and returned a **string** that could be null.
     * This version:
     *   - always returns a Carbon or null
     *   - clones before mutating
     *   - respects `next_billing_date` as the authoritative source
     *     when it is already in the future
     */
    public function calculateNextBillingDate(DeveloperSetting $settings): ?Carbon
    {
        // If next_billing_date is already set and in the future, use it.
        if (!empty($settings->next_billing_date)) {
            $next = Carbon::parse($settings->next_billing_date);
            if ($next->isFuture()) {
                return $next;
            }
        }

        if (empty($settings->billing_start_date)) {
            return null;
        }

        $start = Carbon::parse($settings->billing_start_date);
        $now   = Carbon::now();

        $cycle = $settings->billing_cycle ?? 'monthly';
        $next  = $start->copy();

        // Advance until strictly after today.
        $guard = 0;
        while ($next->lte($now) && $guard < 500) {
            $next = match ($cycle) {
                'weekly'    => $next->addWeek(),
                'monthly'   => $next->addMonth(),
                'quarterly' => $next->addMonths(3),
                'yearly'    => $next->addYear(),
                default     => $next->addMonth(),
            };
            $guard++;
        }

        return $next;
    }

    public function getPaymentHistory($limit = 12)
    {
        try {
            $settings = DeveloperSetting::first();
            if (!$settings) {
                return collect();
            }

            return DeveloperBillingRecord::where('developer_setting_id', $settings->id)
                ->orderBy('invoice_date', 'desc')
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get payment history: ' . $e->getMessage());
            return collect();
        }
    }

    public function getInvoiceDetails(DeveloperBillingRecord $invoice)
    {
        try {
            $settings = DeveloperSetting::find($invoice->developer_setting_id);
            if (!$settings) {
                throw new \Exception('Developer settings not found');
            }

            $invoiceItems = $this->parseInvoiceItems($invoice->invoice_items);

            return [
                'invoice' => $invoice,
                'developer' => [
                    'name'    => $settings->developer_name,
                    'email'   => $settings->developer_email,
                    'company' => $settings->developer_company,
                    'address' => $settings->developer_address,
                ],
                'items'   => $invoiceItems,
                'summary' => [
                    'subtotal' => $invoice->amount,
                    'tax'      => $invoice->tax_amount ?? 0,
                    'total'    => $invoice->amount + ($invoice->tax_amount ?? 0),
                    'currency' => $invoice->currency,
                ],
                'payment_details' => $invoice->payment_details
                    ? json_decode($invoice->payment_details, true)
                    : null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get invoice details: ' . $e->getMessage());
            throw $e;
        }
    }

    private function parseInvoiceItems($itemsJson)
    {
        if (!$itemsJson) {
            return [];
        }

        $items = json_decode($itemsJson, true);
        if (!is_array($items)) {
            return [];
        }

        $parsed = [];
        foreach ($items as $item) {
            $parsed[] = [
                'description' => $item['description'] ?? 'Service Charge',
                'quantity'    => $item['quantity']    ?? 1,
                'unit_price'  => $item['unit_price']  ?? 0,
                'amount'      => $item['amount']      ?? 0,
            ];
        }

        return $parsed;
    }

    public function getConfiguration()
    {
        try {
            $settings = DeveloperSetting::first();

            if (!$settings) {
                return $this->getDefaultConfiguration();
            }

            return [
                'general' => [
                    'monthly_billing_amount' => $settings->monthly_billing_amount,
                    'billing_currency'       => $settings->billing_currency,
                    'billing_cycle'          => $settings->billing_cycle,
                    'billing_start_date'     => $settings->billing_start_date,
                    'next_billing_date'      => $settings->next_billing_date,
                    'billing_status'         => $settings->billing_status,
                ],
                'payment' => [
                    'method'         => $settings->payment_method,
                    'mobile_number'  => $settings->payment_mobile_number,
                    'account_name'   => $settings->payment_account_name,
                    'account_number' => $settings->payment_account_number,
                    'bank_name'      => $settings->payment_bank_name,
                    'bank_branch'    => $settings->payment_bank_branch,
                ],
                'invoicing' => [
                    'auto_generate'       => $settings->isAutoInvoiceGenerationEnabled(),
                    'send_reminders'      => $settings->shouldSendPaymentReminders(),
                    'reminder_days'       => $settings->getReminderDaysBefore(),
                    'late_fee_percentage' => $settings->getBillingRulesArray()['late_fee_percentage'] ?? 5,
                    'grace_period_days'   => $settings->getBillingRulesArray()['grace_period_days'] ?? 3,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get billing configuration: ' . $e->getMessage());
            return $this->getDefaultConfiguration();
        }
    }

    private function getDefaultConfiguration()
    {
        return [
            'general' => [
                'monthly_billing_amount' => 0,
                'billing_currency'       => 'GHS',
                'billing_cycle'          => 'monthly',
                'billing_start_date'     => null,
                'next_billing_date'      => null,
                'billing_status'         => 'pending',
            ],
            'payment' => [
                'method'         => null,
                'mobile_number'  => null,
                'account_name'   => null,
                'account_number' => null,
                'bank_name'      => null,
                'bank_branch'    => null,
            ],
            'invoicing' => [
                'auto_generate'       => true,
                'send_reminders'      => true,
                'reminder_days'       => [7, 3, 1],
                'late_fee_percentage' => 5,
                'grace_period_days'   => 3,
            ],
        ];
    }

    public function createBillingProposal(array $data)
    {
        DB::beginTransaction();

        try {
            $settings = DeveloperSetting::first();
            if (!$settings) {
                throw new \Exception('Developer settings not found');
            }

            $proposal = DB::table('developer_billing_proposals')->insertGetId([
                'developer_setting_id' => $settings->id,
                'old_amount'           => $data['old_amount'],
                'new_amount'           => $data['new_amount'],
                'currency'             => $data['currency'],
                'cycle'                => $data['cycle'],
                'proposed_by'          => $data['proposed_by'],
                'reason'               => $data['reason'],
                'status'               => 'pending',
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            $settings->update([
                'billing_status' => 'pending_approval',
                'updated_at'     => now(),
            ]);

            DB::commit();

            $this->notifyAdminsAboutProposal($settings, $data);

            return $proposal;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create billing proposal: ' . $e->getMessage());
            throw $e;
        }
    }

    /* ============================================================
     | NEW: Generate a recurring invoice for the current month
     | This is THE single source of truth for invoice creation.
     * ============================================================ */

    /**
     * Create (or return existing) recurring invoice for a developer.
     *
     * This method does NOT check the auto-generation flag. Callers must gate
     * appropriately:
     *
     *   - Scheduled command      → call generateRecurringInvoiceForDeveloper()
     *                              which checks the flag.
     *   - On-signing             → call generateInvoiceAfterSigning()
     *                              which checks the flag.
     *   - Manual (user action)   → call this method directly, bypassing the
     *                              flag intentionally.
     *
     * @param DeveloperSetting $settings
     * @param string|null      $billingMonth  YYYY-MM, defaults to current month
     * @param int|null         $createdBy     User ID to attribute; falls back
     *                                        to auth()->id() then null
     *
     * @throws \Exception when no active primary agreement exists.
     */
    public function generateRecurringInvoice(
        DeveloperSetting $settings,
        ?string $billingMonth = null,
        ?int $createdBy = null
    ): BillingInvoice {
        $primaryAgreement = AdminBillingRecord::where('developer_setting_id', $settings->id)
            ->where('is_primary_for_billing', true)
            ->where('status', 'active')
            ->first();

        if (!$primaryAgreement) {
            throw new \Exception(
                'No active primary super admin assigned for billing.'
            );
        }

        $billingMonth = $billingMonth ?: Carbon::now()->format('Y-m');
        $targetDate   = Carbon::createFromFormat('Y-m', $billingMonth);

        // ---- Idempotency check ----
        $existing = $this->findExistingRecurringInvoice(
            $settings->id,
            $primaryAgreement->id,
            $billingMonth
        );
        if ($existing) {
            return $existing;
        }

        // ---- Amount due = agreed - already paid this month ----
        $amountAlreadyPaid = Schema::hasTable('super_admin_payment_records')
            ? (float) SuperAdminPaymentRecord::where('developer_setting_id', $settings->id)
                ->where('billing_month', $billingMonth)
                ->sum('amount_paid')
            : 0.0;

        $amountDue = max(0, (float) $primaryAgreement->amount - $amountAlreadyPaid);

        // ---- Invoice number ----
        $invoiceNumber = 'INV-' . $targetDate->format('Ym') . '-' . str_pad(
            BillingInvoice::where('developer_setting_id', $settings->id)
                ->whereYear('created_at', $targetDate->year)
                ->whereMonth('created_at', $targetDate->month)
                ->count() + 1,
            4,
            '0',
            STR_PAD_LEFT
        );

        // Resolve the correct FK column once (cached per-request).
        $agreementColumn = $this->agreementColumnForInvoices();

        $invoiceData = [
            'developer_setting_id' => $settings->id,
            'super_admin_id'       => $primaryAgreement->super_admin_id,
            'invoice_number'       => $invoiceNumber,
            'amount'               => $amountDue,
            'original_amount'      => $primaryAgreement->amount,
            'amount_paid_already'  => $amountAlreadyPaid,
            'currency'             => $primaryAgreement->currency,
            'description'          => "Monthly system fee for {$billingMonth}",
            'issue_date'           => $targetDate->copy()->startOfMonth(),
            'due_date'             => $targetDate->copy()->startOfMonth()->addDays(
                (int) ($settings->getBillingRulesArray()['invoice_due_days'] ?? 30)
            ),
            'status'               => $amountDue > 0 ? 'pending' : 'paid',
            'is_auto_generated'    => true,
            'is_recurring'         => true,
            'billing_month'        => $billingMonth,
            'created_by'           => $createdBy ?? auth()->id() ?? null,
        ];

        if ($agreementColumn) {
            $invoiceData[$agreementColumn] = $primaryAgreement->id;
        }

        $invoice = BillingInvoice::create($invoiceData);

        if ($amountDue <= 0) {
            $invoice->update([
                'status'      => 'paid',
                'paid_at'     => now(),
                'paid_amount' => 0,
            ]);
        }

        // Email the invoice to the primary billing contact
        $email = $primaryAgreement->billing_contact_email
            ?? optional($primaryAgreement->superAdmin)->email
            ?? null;

        if ($email) {
            try {
                SendInvoiceEmailJob::dispatch($invoice, $email);
            } catch (\Throwable $e) {
                Log::warning('Failed to dispatch invoice email', [
                    'invoice_id' => $invoice->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        // Schedule reminders unless the invoice is already paid
        if ($amountDue > 0 && $settings->shouldSendPaymentReminders()) {
            try {
                $this->scheduleInvoiceReminders($invoice, $settings, $primaryAgreement);
            } catch (\Throwable $e) {
                Log::error('Failed to schedule invoice reminders', [
                    'invoice_id' => $invoice->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $invoice;
    }

    private function findExistingRecurringInvoice(
        int $developerSettingId,
        int $primaryAgreementId,
        string $billingMonth
    ): ?BillingInvoice {
        $q = BillingInvoice::where('developer_setting_id', $developerSettingId)
            ->where('billing_month', $billingMonth)
            ->where('is_recurring', true); // explicit — excludes custom invoices

        $agreementColumn = $this->agreementColumnForInvoices();
        if ($agreementColumn) {
            $q->where($agreementColumn, $primaryAgreementId);
        }

        return $q->first();
    }

    /**
     * Resolve the FK column on billing_invoices that points at
     * admin_billing_records. Prefers `agreement_id` when present, falls back
     * to `admin_billing_record_id`, and returns null if neither exists.
     *
     * Cached per-request (static) to avoid repeated schema introspection.
     */
    private function agreementColumnForInvoices(): ?string
    {
        static $column = false; // false = not yet resolved, null = no column

        if ($column !== false) {
            return $column;
        }

        if (Schema::hasColumn('billing_invoices', 'agreement_id')) {
            $column = 'agreement_id';
        } elseif (Schema::hasColumn('billing_invoices', 'admin_billing_record_id')) {
            $column = 'admin_billing_record_id';
        } else {
            $column = null;
        }

        return $column;
    }

    /* ============================================================
     | NEW: Schedule reminders for an invoice
     * ============================================================ */

    /**
     * Create one InvoiceReminder per configured interval.
     *
     * Respects per-interval channel overrides via
     * billing_rules.reminder_channel_by_days.
     *
     * NOTE ON COLUMN NAME:
     *   The service writes to whichever column exists on invoice_reminders:
     *   `channels` (plural) if present, otherwise `channel` (singular).
     *   The SendPaymentReminderJob reads both via null-coalescing.
     */
    public function scheduleInvoiceReminders(
        BillingInvoice $invoice,
        DeveloperSetting $settings,
        AdminBillingRecord $primaryAgreement
    ): void {
        if (!Schema::hasTable('invoice_reminders')) {
            Log::warning('invoice_reminders table missing — skipping reminder scheduling.');
            return;
        }

        if (!$settings->shouldSendPaymentReminders()) {
            return;
        }

        $rules        = $settings->getBillingRulesArray();
        $reminderDays = $rules['reminder_days_before'] ?? [7, 3, 1];
        if (!is_array($reminderDays) || empty($reminderDays)) {
            $reminderDays = [7, 3, 1];
        }

        // Global channel preference, e.g. ['email','sms']
        $globalChannels = $rules['reminder_channels'] ?? ['email'];
        if (!is_array($globalChannels) || empty($globalChannels)) {
            $globalChannels = ['email'];
        }

        // Per-interval override: ['7' => ['email'], '1' => ['email','sms']]
        $perDayChannels = $rules['reminder_channel_by_days'] ?? [];

        $dueDate = $invoice->due_date
            ? Carbon::parse($invoice->due_date)
            : Carbon::now()->addDays(30);

        $today = Carbon::today();

        // Choose the right column name once.
        $channelColumn = Schema::hasColumn('invoice_reminders', 'channels')
            ? 'channels'
            : 'channel';

        foreach ($reminderDays as $days) {
            $days = (int) $days;
            if ($days <= 0) {
                continue;
            }

            $scheduledFor = $dueDate->copy()->subDays($days);

            // If already past, fire tomorrow so it isn't lost.
            if ($scheduledFor->lt($today)) {
                $scheduledFor = $today->copy()->addDay();
            }

            $channels = $perDayChannels[$days]
                ?? $perDayChannels[(string) $days]
                ?? $globalChannels;

            if (!is_array($channels) || empty($channels)) {
                $channels = ['email'];
            }

            InvoiceReminder::updateOrCreate(
                [
                    'invoice_id'      => $invoice->id,
                    'days_before_due' => $days,
                ],
                [
                    'developer_setting_id' => $settings->id,
                    'super_admin_id'       => $primaryAgreement->super_admin_id,
                    'scheduled_for'        => $scheduledFor->toDateString(),
                    $channelColumn         => implode(',', $channels),
                    'status'               => 'pending',
                    'sent_at'              => null,
                    'failure_reason'       => null,
                ]
            );
        }
    }

    /**
     * Cancel all pending reminders for an invoice.
     * Called when an invoice is paid/cancelled/voided.
     */
    public function cancelRemindersForInvoice(int $invoiceId): int
    {
        if (!Schema::hasTable('invoice_reminders')) {
            return 0;
        }

        $count = InvoiceReminder::where('invoice_id', $invoiceId)
            ->where('status', 'pending')
            ->update([
                'status'         => 'skipped',
                'failure_reason' => 'Invoice no longer needs reminders',
            ]);

        Log::info('Cancelled invoice reminders', [
            'invoice_id' => $invoiceId,
            'count'      => $count,
        ]);

        return $count;
    }

    /**
     * Dispatch any reminders whose scheduled_for date has arrived.
     * Called by the console command.
     */
    public function dispatchDueReminders(): int
    {
        if (!Schema::hasTable('invoice_reminders')) {
            return 0;
        }

        $due = InvoiceReminder::where('status', 'pending')
            ->whereDate('scheduled_for', '<=', now()->toDateString())
            ->pluck('id');

        foreach ($due as $id) {
            \App\Jobs\SendPaymentReminderJob::dispatch($id);
        }

        Log::info('Dispatched due reminder jobs', ['count' => $due->count()]);

        return $due->count();
    }

    /**
     * Called by SendPaymentReminderJob to record success.
     */
    public function markReminderSent(int $reminderId, array $deliveryResults = []): void
    {
        if (!Schema::hasTable('invoice_reminders')) {
            return;
        }

        InvoiceReminder::where('id', $reminderId)->update([
            'status'           => 'sent',
            'sent_at'          => now(),
            'delivery_results' => $deliveryResults ?: null,
        ]);
    }

    /**
     * Called by SendPaymentReminderJob to record failure.
     */
    public function markReminderFailed(int $reminderId, string $reason, array $deliveryResults = []): void
    {
        if (!Schema::hasTable('invoice_reminders')) {
            return;
        }

        InvoiceReminder::where('id', $reminderId)->update([
            'status'           => 'failed',
            'failure_reason'   => $reason,
            'delivery_results' => $deliveryResults ?: null,
        ]);
    }

    /* ============================================================
     | NEW: Custom invoice generation
     * ============================================================ */

    public function generateCustomInvoice(
        DeveloperSetting $settings,
        float $amount,
        string $description,
        Carbon $dueDate,
        string $invoiceType = 'custom'
    ): BillingInvoice {
        $primaryAgreement = AdminBillingRecord::where('developer_setting_id', $settings->id)
            ->where('is_primary_for_billing', true)
            ->first();

        if (!$primaryAgreement) {
            throw new \Exception('No primary super admin assigned. Set up billing first.');
        }

        $invoiceData = [
            'developer_setting_id' => $settings->id,
            'super_admin_id'       => $primaryAgreement->super_admin_id,
            'invoice_number'       => 'INV-' . strtoupper(uniqid()),
            'amount'               => $amount,
            'currency'             => $settings->billing_currency,
            'description'          => strip_tags(trim($description)),
            'invoice_type'         => $invoiceType,
            'issue_date'           => now(),
            'due_date'             => $dueDate,
            'payment_due_days'     => (int) ($settings->getBillingRulesArray()['invoice_due_days'] ?? 30),
            'status'               => 'pending',
            'is_recurring'         => false,
            'is_custom'            => true,
            'created_by'           => auth()->id() ?? null,
            'billing_month'        => now()->format('Y-m'),
        ];

        $agreementColumn = $this->agreementColumnForInvoices();
        if ($agreementColumn) {
            $invoiceData[$agreementColumn] = $primaryAgreement->id;
        }

        $invoice = BillingInvoice::create($invoiceData);

        if ($settings->shouldSendPaymentReminders()) {
            try {
                $this->scheduleInvoiceReminders($invoice, $settings, $primaryAgreement);
            } catch (\Throwable $e) {
                Log::warning('Failed to schedule reminders for custom invoice', [
                    'invoice_id' => $invoice->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $invoice;
    }

    /* ============================================================
     | NEW: Post-signing invoice generation
     | Used by SuperAdminBillingController::generateInvoiceAfterSigning
     * ============================================================ */

    /**
     * Generate an invoice right after a primary agreement is signed by both parties.
     *
     * Respects:
     *   - is_primary_for_billing          → only the primary agreement produces invoices
     *   - isAutoInvoiceGenerationEnabled() → honours the developer's automation toggle
     *
     * If the toggle is OFF, no invoice is created. The signing flow continues
     * normally; the developer can generate the invoice manually from the Billing
     * Dashboard if needed.
     */
    public function generateInvoiceAfterSigning(
        DeveloperSetting $settings,
        AdminBillingRecord $agreement
    ): ?BillingInvoice {
        // Only the primary agreement produces invoices.
        if (!$agreement->is_primary_for_billing) {
            Log::info('Skipping on-signing invoice — agreement is not primary', [
                'agreement_id'         => $agreement->id,
                'developer_setting_id' => $settings->id,
            ]);
            return null;
        }

        // Honour the developer's automation toggle.
        if (!$settings->isAutoInvoiceGenerationEnabled()) {
            Log::info('Skipping on-signing invoice — auto-invoice disabled by developer', [
                'agreement_id'         => $agreement->id,
                'developer_setting_id' => $settings->id,
            ]);
            return null;
        }

        try {
            return $this->generateRecurringInvoice(
                $settings,
                null,
                auth()->id() ?? null
            );
        } catch (\Throwable $e) {
            Log::error('Failed to generate invoice after signing', [
                'agreement_id' => $agreement->id,
                'error'        => $e->getMessage(),
            ]);
            return null;
        }
    }

    /* ============================================================
     | NEW: Advance next_billing_date after generating an invoice
     * ============================================================ */

    /**
     * Advance next_billing_date by one cycle interval.
     *
     * If next_billing_date is already in the past (scheduler was down, manual
     * catch-up, etc.), start from today instead of the stale date to avoid a
     * cascade of immediate re-fires.
     */
    public function advanceNextBillingDate(DeveloperSetting $settings): Carbon
    {
        $current = $settings->next_billing_date
            ? Carbon::parse($settings->next_billing_date)
            : Carbon::today();

        // Never advance from a date in the past — anchor to today.
        if ($current->isPast()) {
            $current = Carbon::today();
        }

        $cycle = $settings->billing_cycle ?? 'monthly';

        $next = match ($cycle) {
            'weekly'    => $current->copy()->addWeek(),
            'monthly'   => $current->copy()->addMonth(),
            'quarterly' => $current->copy()->addMonths(3),
            'yearly'    => $current->copy()->addYear(),
            default     => $current->copy()->addMonth(),
        };

        $settings->update([
            'next_billing_date' => $next->toDateString(),
        ]);

        return $next;
    }

    /* ============================================================
     | NEW: Recurring invoice generation for the console command
     | (RunRecurringBilling calls this)
     * ============================================================ */

    public function generateRecurringInvoiceForDeveloper(DeveloperSetting $settings): ?BillingInvoice
    {
        if (!$settings->isBillingActive()) {
            Log::info('Skipping developer billing — not active', [
                'developer_setting_id' => $settings->id,
            ]);
            return null;
        }

        if (!$settings->isAutoInvoiceGenerationEnabled()) {
            Log::info('Skipping developer billing — auto-invoice disabled', [
                'developer_setting_id' => $settings->id,
            ]);
            return null;
        }

        $invoice = $this->generateRecurringInvoice(
            $settings,
            null,
            null  // system-generated — no authenticated user
        );

        $this->advanceNextBillingDate($settings);

        return $invoice;
    }

    /* ============================================================
     | LEGACY: generateInvoice (DeveloperBillingRecord version)
     * ============================================================ */

    public function generateInvoice($developerSettingId, $amount, $description = 'Monthly Subscription')
    {
        DB::beginTransaction();

        try {
            $settings = DeveloperSetting::find($developerSettingId);
            if (!$settings) {
                throw new \Exception('Developer settings not found');
            }

            $invoiceNumber = $this->generateInvoiceNumber($settings);

            $invoice = DeveloperBillingRecord::create([
                'developer_setting_id' => $settings->id,
                'invoice_number'       => $invoiceNumber,
                'amount'               => $amount,
                'currency'             => $settings->billing_currency,
                'invoice_date'         => now(),
                'due_date'             => now()->addDays(30),
                'payment_status'       => 'pending',
                'invoice_items'        => json_encode([[
                    'description' => $description,
                    'quantity'    => 1,
                    'unit_price'  => $amount,
                    'amount'      => $amount,
                ]]),
            ]);

            $nextBillingDate = $this->calculateNextBillingDate($settings);
            $settings->update([
                'next_billing_date' => $nextBillingDate?->toDateString(),
            ]);

            DB::commit();

            $this->sendInvoiceEmail($settings, $invoice);

            return $invoice;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to generate invoice: ' . $e->getMessage());
            throw $e;
        }
    }

    private function generateInvoiceNumber(DeveloperSetting $settings): string
    {
        $year        = now()->year;
        $month       = str_pad((string) now()->month, 2, '0', STR_PAD_LEFT);
        $developerId = str_pad((string) $settings->id, 4, '0', STR_PAD_LEFT);

        $count = DeveloperBillingRecord::whereYear('created_at', $year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $sequence = str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);

        return "DEV-{$developerId}-{$year}{$month}-{$sequence}";
    }

    private function sendInvoiceEmail(DeveloperSetting $settings, DeveloperBillingRecord $invoice): void
    {
        try {
            if (!$settings->developer_email) {
                Log::warning('No email address for developer: ' . $settings->developer_name);
                return;
            }

            $mailData = [
                'developer_name'  => $settings->developer_name,
                'invoice_number'  => $invoice->invoice_number,
                'invoice_date'    => optional($invoice->invoice_date)->format('F d, Y'),
                'due_date'        => optional($invoice->due_date)->format('F d, Y'),
                'amount'          => number_format($invoice->amount, 2),
                'currency'        => $invoice->currency,
                'invoice_items'   => json_decode($invoice->invoice_items, true),
                'payment_method'  => $settings->payment_method,
                'payment_details' => $this->getPaymentDetails($settings),
                'view_url'        => route('developer.billing.invoice', $invoice->id),
            ];

            Mail::to($settings->developer_email)
                ->send(new DeveloperInvoiceMail($mailData));

            Log::info('Invoice email sent', [
                'to'             => $settings->developer_email,
                'invoice_number' => $invoice->invoice_number,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send invoice email: ' . $e->getMessage(), [
                'developer' => $settings->developer_email,
                'invoice'   => $invoice->invoice_number,
            ]);
        }
    }

    public function getPaymentDetails(DeveloperSetting $settings): array
    {
        switch ($settings->payment_method) {
            case 'mtn':
            case 'telecel':
            case 'airteltigo':
                return [
                    'method'       => ucfirst($settings->payment_method) . ' Mobile Money',
                    'number'       => $settings->payment_mobile_number,
                    'instructions' => 'Send payment to the mobile number above.',
                ];

            case 'bank_transfer':
                return [
                    'method'         => 'Bank Transfer',
                    'bank_name'      => $settings->payment_bank_name,
                    'account_name'   => $settings->payment_account_name,
                    'account_number' => $settings->payment_account_number,
                    'branch'         => $settings->payment_bank_branch,
                    'instructions'   => 'Transfer to the account details above.',
                ];

            case 'paystack':
                return [
                    'method'       => 'Paystack',
                    'instructions' => 'Pay online via Paystack payment link.',
                ];

            default:
                return [
                    'method'       => 'To be determined',
                    'instructions' => 'Payment details will be provided.',
                ];
        }
    }

    public function processPaymentConfirmation(DeveloperBillingRecord $invoice, array $paymentData): bool
    {
        DB::beginTransaction();

        try {
            $invoice->update([
                'payment_method'      => $paymentData['payment_method'],
                'payment_reference'   => $paymentData['payment_reference'],
                'paid_date'           => $paymentData['payment_date'],
                'transaction_id'      => $paymentData['transaction_id'] ?? null,
                'payment_status'      => 'paid',
                'payment_details'     => json_encode([
                    'confirmed_by'      => auth()->user()?->email,
                    'confirmed_at'      => now()->toISOString(),
                    'payment_proof'     => $paymentData['payment_proof_path'] ?? null,
                    'payment_reference' => $paymentData['payment_reference'],
                ]),
                'developer_confirmed' => true,
                'confirmed_at'        => now(),
            ]);

            $settings = DeveloperSetting::find($invoice->developer_setting_id);
            if ($settings) {
                $settings->update([
                    'billing_status'    => 'active',
                    'last_payment_date' => now(),
                ]);
            }

            DB::commit();

            $this->sendPaymentConfirmationEmail($invoice);
            $this->notifyAdminsAboutPayment($invoice);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process payment confirmation: ' . $e->getMessage());
            throw $e;
        }
    }

    private function sendPaymentConfirmationEmail(DeveloperBillingRecord $invoice): void
    {
        try {
            $settings = DeveloperSetting::find($invoice->developer_setting_id);
            if (!$settings || !$settings->developer_email) {
                return;
            }

            $mailData = [
                'developer_name'    => $settings->developer_name,
                'invoice_number'    => $invoice->invoice_number,
                'amount'            => number_format($invoice->amount, 2),
                'currency'          => $invoice->currency,
                'payment_date'      => optional($invoice->paid_date)->format('F d, Y'),
                'payment_method'    => $invoice->payment_method,
                'payment_reference' => $invoice->payment_reference,
                'next_billing_date' => $settings->next_billing_date
                    ? Carbon::parse($settings->next_billing_date)->format('F d, Y')
                    : 'Not set',
            ];

            Mail::to($settings->developer_email)
                ->send(new \App\Mail\PaymentConfirmationMail($mailData));
        } catch (\Exception $e) {
            Log::error('Failed to send payment confirmation email: ' . $e->getMessage());
        }
    }

    private function notifyAdminsAboutProposal(DeveloperSetting $settings, array $proposalData): void
    {
        Log::info('Billing proposal created', [
            'developer'   => $settings->developer_name,
            'old_amount'  => $proposalData['old_amount'],
            'new_amount'  => $proposalData['new_amount'],
            'proposed_by' => User::find($proposalData['proposed_by'])?->email ?? 'Unknown',
        ]);
    }

    private function notifyAdminsAboutPayment(DeveloperBillingRecord $invoice): void
    {
        Log::info('Payment confirmed by developer', [
            'invoice_number' => $invoice->invoice_number,
            'amount'         => $invoice->amount,
            'developer'      => $invoice->developerSetting->developer_name ?? 'Unknown',
        ]);
    }

    public function checkOverdueInvoices(): int
    {
        try {
            $overdue = DeveloperBillingRecord::where('payment_status', 'pending')
                ->where('due_date', '<', now())
                ->with('developerSetting')
                ->get();

            foreach ($overdue as $invoice) {
                $this->processOverdueInvoice($invoice);
            }

            return count($overdue);
        } catch (\Exception $e) {
            Log::error('Failed to check overdue invoices: ' . $e->getMessage());
            return 0;
        }
    }

    private function processOverdueInvoice(DeveloperBillingRecord $invoice): void
    {
        try {
            $settings          = DeveloperSetting::find($invoice->developer_setting_id);
            $lateFeePercentage = $settings->getBillingRulesArray()['late_fee_percentage'] ?? 5;
            $lateFee           = $invoice->amount * ($lateFeePercentage / 100);

            $invoice->update([
                'late_fee'     => $lateFee,
                'total_amount' => $invoice->amount + $lateFee,
                'is_overdue'   => true,
                'overdue_days' => now()->diffInDays($invoice->due_date),
            ]);

            $this->sendOverdueNotification($invoice);

            Log::warning('Invoice marked as overdue', [
                'invoice_number' => $invoice->invoice_number,
                'late_fee'       => $lateFee,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process overdue invoice: ' . $e->getMessage());
        }
    }

    private function sendOverdueNotification(DeveloperBillingRecord $invoice): void
    {
        // Intentionally left blank — this is a hook for future notifications.
    }

    public function getBillingStatistics($period = 'month'): array
    {
        try {
            $startDate = $this->getStartDateForPeriod($period);

            return [
                'total_revenue'  => DeveloperBillingRecord::where('payment_status', 'paid')
                    ->where('paid_date', '>=', $startDate)
                    ->sum('amount'),
                'pending_amount' => DeveloperBillingRecord::where('payment_status', 'pending')
                    ->where('invoice_date', '>=', $startDate)
                    ->sum('amount'),
                'invoice_count'  => DeveloperBillingRecord::where('invoice_date', '>=', $startDate)->count(),
                'paid_count'     => DeveloperBillingRecord::where('payment_status', 'paid')
                    ->where('paid_date', '>=', $startDate)
                    ->count(),
                'overdue_count'  => DeveloperBillingRecord::where('payment_status', 'pending')
                    ->where('due_date', '<', now())
                    ->where('invoice_date', '>=', $startDate)
                    ->count(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get billing statistics: ' . $e->getMessage());
            return [];
        }
    }

    private function getStartDateForPeriod($period): Carbon
    {
        return match ($period) {
            'day'   => now()->startOfDay(),
            'week'  => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year'  => now()->startOfYear(),
            default => now()->startOfMonth(),
        };
    }

    public function validatePaymentMethodData(array $data): array
    {
        $rules    = [];
        $messages = [];

        switch ($data['payment_method'] ?? null) {
            case 'mtn':
            case 'telecel':
            case 'airteltigo':
                $rules = ['payment_mobile_number' => 'required|string|regex:/^0[0-9]{9}$/'];
                $messages = [
                    'payment_mobile_number.regex' => 'Please enter a valid Ghanaian mobile number (e.g., 0241234567)',
                ];
                break;

            case 'bank_transfer':
                $rules = [
                    'payment_account_name'   => 'required|string|max:255',
                    'payment_account_number' => 'required|string|max:50',
                    'payment_bank_name'      => 'required|string|max:255',
                ];
                break;
        }

        return ['rules' => $rules, 'messages' => $messages];
    }

    public function getPaymentInstructions($paymentMethod): array
    {
        $instructions = [
            'mtn' => [
                'title' => 'MTN Mobile Money',
                'steps' => [
                    'Dial *170# on your MTN line',
                    'Choose "Send Money"',
                    'Enter recipient number',
                    'Enter amount',
                    'Enter reference',
                    'Confirm transaction',
                ],
                'note' => 'Use your invoice number as the reference.',
            ],
            'telecel' => [
                'title' => 'Telecel Cash',
                'steps' => [
                    'Dial *110# on your Telecel line',
                    'Choose "Send Money"',
                    'Enter recipient number',
                    'Enter amount',
                    'Enter reference',
                    'Confirm transaction',
                ],
                'note' => 'Use your invoice number as the reference.',
            ],
            'airteltigo' => [
                'title' => 'AirtelTigo Money',
                'steps' => [
                    'Dial *110# on your AirtelTigo line',
                    'Choose "Send Money"',
                    'Enter recipient number',
                    'Enter amount',
                    'Enter reference',
                    'Confirm transaction',
                ],
                'note' => 'Use your invoice number as the reference.',
            ],
            'bank_transfer' => [
                'title' => 'Bank Transfer',
                'steps' => [
                    'Log into your bank\'s mobile app or internet banking',
                    'Add beneficiary with provided account details',
                    'Make transfer for invoice amount',
                    'Use invoice number as reference',
                    'Save transaction receipt',
                ],
                'note' => 'Transfer may take 1-2 business days to reflect.',
            ],
            'paystack' => [
                'title' => 'Paystack',
                'steps' => [
                    'Click the "Pay Now" button in your invoice',
                    'You will be redirected to Paystack',
                    'Choose your preferred payment method',
                    'Complete the payment',
                    'Save the transaction reference',
                ],
                'note' => 'Instant payment confirmation.',
            ],
        ];

        return $instructions[$paymentMethod] ?? [
            'title' => 'Payment Instructions',
            'steps' => ['Payment details will be provided.'],
            'note'  => '',
        ];
    }
}