<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\AgentInvitation;
use App\Models\LandlordInvitation;
use App\Models\RegistrationPlan;
use App\Models\User;
use App\Models\SystemSetting;
use App\Models\Invoice;
use App\Models\TenantInvoice;
use App\Models\PropertyUnitInvoice;
use App\Models\RentalAgreement;
use App\Services\NotificationService;
use App\Services\AgentInvitationService;
use App\Services\InvoiceService;
use App\Services\TenantInvoiceService;
use App\Services\YearEndArchiveService;
use App\Services\TransferArchiveService;
use App\Services\SystemSettingsReminderService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // ==================== SYSTEM SETTINGS REMINDERS ====================

        /**
         * Send reminders to super admins to create system settings
         * Runs every 6 hours to ensure timely reminders without spamming
         */
        $schedule->call(function () {
            try {
                if (SystemSetting::exists()) {
                    return;
                }

                $reminderService = app(SystemSettingsReminderService::class);
                $reminderService->checkAndSendReminders();
            } catch (\Exception $e) {
                Log::error("Failed to send system settings reminders: " . $e->getMessage(), [
                    'timestamp' => now()->setTimezone('UTC')->toISOString(),
                ]);
            }
        })
        ->everySixHours()
        ->name('send-system-settings-reminders')
        ->onOneServer()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/system-settings-reminders.log'));

        $schedule->call(function () {
            try {
                if (SystemSetting::exists()) {
                    return;
                }
                $reminderService = app(SystemSettingsReminderService::class);
                $reminderService->checkAndSendReminders();
                Log::info("Morning system settings reminder check completed", [
                    'timestamp' => now()->setTimezone('UTC')->toISOString(),
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to send morning system settings reminders: " . $e->getMessage());
            }
        })
        ->dailyAt('09:00')
        ->name('morning-system-settings-reminders')
        ->onOneServer()
        ->withoutOverlapping();

        $schedule->call(function () {
            try {
                if (SystemSetting::exists()) {
                    return;
                }
                $reminderService = app(SystemSettingsReminderService::class);
                $reminderService->checkAndSendReminders();
                Log::info("Evening system settings reminder check completed", [
                    'timestamp' => now()->setTimezone('UTC')->toISOString(),
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to send evening system settings reminders: " . $e->getMessage());
            }
        })
        ->dailyAt('18:00')
        ->name('evening-system-settings-reminders')
        ->onOneServer()
        ->withoutOverlapping();

        $schedule->call(function () {
            try {
                if (SystemSetting::exists()) {
                    return;
                }

                $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
                    ->where('status', User::STATUS_ACTIVE)
                    ->get();

                if ($superAdmins->isEmpty()) {
                    return;
                }

                $firstUser = User::orderBy('created_at', 'asc')->first();
                $daysWithoutSettings = $firstUser ? $firstUser->created_at->diffInDays(now()) : 0;

                Log::info("Weekly system settings reminder summary", [
                    'super_admins_count'    => $superAdmins->count(),
                    'days_without_settings' => $daysWithoutSettings,
                ]);

                foreach ($superAdmins as $admin) {
                    try {
                        $admin->notify(new \App\Notifications\SystemSettingsReminderNotification(
                            $daysWithoutSettings >= 7 ? 'urgent' : 'weekly',
                            $daysWithoutSettings
                        ));
                    } catch (\Exception $e) {
                        Log::error("Failed to send weekly reminder to admin: {$admin->email}", [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error("Failed to send weekly system settings summary: " . $e->getMessage());
            }
        })
        ->weekly()
        ->mondays()
        ->at('08:00')
        ->name('weekly-system-settings-summary')
        ->onOneServer()
        ->withoutOverlapping();

        $schedule->call(function () {
            try {
                $settingsExist = SystemSetting::exists();
                if (!$settingsExist) {
                    $firstUser = User::orderBy('created_at', 'asc')->first();
                    $daysWithout = $firstUser ? $firstUser->created_at->diffInDays(now()) : 0;

                    Log::warning("⚠️ System settings are missing", [
                        'days_without_settings' => $daysWithout,
                        'severity'              => $daysWithout >= 7 ? 'CRITICAL' : ($daysWithout >= 3 ? 'HIGH' : 'MEDIUM'),
                        'timestamp'             => now()->setTimezone('UTC')->toISOString(),
                        'action_required'       => 'Create system settings immediately',
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("System settings health check failed: " . $e->getMessage());
            }
        })
        ->hourly()
        ->name('system-settings-health-check')
        ->onOneServer();

        // ==================== RECURRING BILLING — PRIMARY SUPER ADMIN ====================
        //
        // ⚠️ IMPORTANT: The command name MUST match the artisan signature
        // declared in your RunRecurringBilling command class. If your command
        // signature is `billing:run-recurring`, update the entries below to
        // use that name. If you prefer to keep `billing:generate-monthly-invoices`,
        // rename the command's $signature property instead.
        //
        // The command should:
        //   - Query every active DeveloperSetting whose next_billing_date <= today
        //   - Call DeveloperBillingService::generateRecurringInvoice($settings)
        //   - Advance next_billing_date by the cycle interval
        //   - Schedule InvoiceReminder rows for the newly generated invoice
        //
        // It MUST be idempotent — running it twice in the same day must not
        // create duplicate invoices for the same (developer, agreement, billing_month).
        // The dedupe check lives inside generateRecurringInvoice().

        /**
         * Primary run — 1st of each month at 02:00.
         * Generates the recurring invoice for the primary-SA agreement.
         */
        $schedule->command('billing:generate-monthly-invoices')
            ->monthlyOn(1, '02:00')
            ->name('generate-monthly-billing-invoices')
            ->onOneServer()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/billing-invoices.log'));

        /**
         * Emergency safety net — daily at 01:00.
         * If the 1st-of-month run failed for any reason (queue down, DB deadlock,
         * server offline), this catches up. The service-level dedupe makes it
         * safe to run every day.
         */
        $schedule->command('billing:generate-monthly-invoices --force')
            ->dailyAt('01:00')
            ->name('emergency-billing-invoices')
            ->onOneServer()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/billing-invoices-emergency.log'));

        /**
         * Dry-run — daily at 12:00 for monitoring / dashboards.
         * Must not write anything. Purely observational.
         */
        $schedule->command('billing:generate-monthly-invoices --dry-run')
            ->dailyAt('12:00')
            ->name('billing-invoices-dry-run')
            ->onOneServer()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/billing-invoices-dry-run.log'));

        /**
         * Weekly health check — every Monday at 08:30.
         * Compares generated invoices vs. active primary agreements. Alerts if
         * fewer invoices exist than expected.
         */
        $schedule->call(function () {
            try {
                $currentMonth = Carbon::now()->format('Y-m');
                $invoiceCount = \App\Models\BillingInvoice::where('billing_month', $currentMonth)->count();
                $primaryAgreements = \App\Models\AdminBillingRecord::where('is_primary_for_billing', true)
                    ->where('status', 'active')
                    ->count();

                Log::info("📊 Billing Invoice Health Check", [
                    'current_month'      => $currentMonth,
                    'invoices_generated' => $invoiceCount,
                    'primary_agreements' => $primaryAgreements,
                    'expected_invoices'  => $primaryAgreements,
                    'status'             => $invoiceCount >= $primaryAgreements
                        ? 'HEALTHY'
                        : ($invoiceCount > 0 ? 'PARTIAL' : 'CRITICAL'),
                    'timestamp'          => now()->setTimezone('UTC')->toISOString(),
                ]);

                if ($invoiceCount < $primaryAgreements) {
                    Log::warning("⚠️ Billing invoice generation may have issues", [
                        'invoices_generated' => $invoiceCount,
                        'expected'           => $primaryAgreements,
                        'missing'            => $primaryAgreements - $invoiceCount,
                        'action'             => 'Check billing:generate-monthly-invoices logs',
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("Billing invoice health check failed: " . $e->getMessage());
            }
        })
        ->weekly()
        ->mondays()
        ->at('08:30')
        ->name('billing-invoices-health-check')
        ->onOneServer();

        // ==================== INVOICE REMINDER DISPATCH ====================
        //
        // ⚠️ THIS IS THE MISSING LINK.
        //
        // scheduleInvoiceReminders() (in both billing controllers) creates
        // InvoiceReminder rows with status='pending' and scheduled_for=<date>.
        // Nothing dispatches them until we run the dispatcher below.
        //
        // We dispatch hourly so reminders fire close to their scheduled time
        // without overwhelming the queue. The job itself is idempotent —
        // it skips reminders that are no longer 'pending'.

        $schedule->call(function () {
            try {
                if (!\Illuminate\Support\Facades\Schema::hasTable('invoice_reminders')) {
                    return;
                }

                $dueCount = \App\Models\InvoiceReminder::where('status', 'pending')
                    ->whereDate('scheduled_for', '<=', now())
                    ->count();

                if ($dueCount === 0) {
                    return;
                }

                Log::info("📨 Dispatching due invoice reminders", [
                    'due_count' => $dueCount,
                    'timestamp' => now()->setTimezone('UTC')->toISOString(),
                ]);

                \App\Models\InvoiceReminder::where('status', 'pending')
                    ->whereDate('scheduled_for', '<=', now())
                    ->orderBy('scheduled_for')
                    ->limit(500)
                    ->get()
                    ->each(function ($reminder) {
                        try {
                            \App\Jobs\SendPaymentReminderJob::dispatch($reminder->id);
                        } catch (\Throwable $e) {
                            Log::error('Failed to dispatch invoice reminder job', [
                                'reminder_id' => $reminder->id,
                                'error'       => $e->getMessage(),
                            ]);
                        }
                    });
            } catch (\Exception $e) {
                Log::error("Invoice reminder dispatcher failed: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        })
        ->hourly()
        ->name('dispatch-invoice-reminders')
        ->onOneServer()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/invoice-reminders-dispatch.log'));

        /**
         * Daily cleanup — mark stuck reminders as failed.
         * A reminder that has been 'pending' past its due date by more than
         * a week is likely orphaned (invoice deleted, super admin deactivated,
         * etc.). Log and mark as failed so it stops cluttering the queue.
         */
        $schedule->call(function () {
            try {
                if (!\Illuminate\Support\Facades\Schema::hasTable('invoice_reminders')) {
                    return;
                }

                $stale = \App\Models\InvoiceReminder::where('status', 'pending')
                    ->where('scheduled_for', '<', now()->subDays(7))
                    ->count();

                if ($stale === 0) {
                    return;
                }

                $marked = \App\Models\InvoiceReminder::where('status', 'pending')
                    ->where('scheduled_for', '<', now()->subDays(7))
                    ->update([
                        'status'         => 'failed',
                        'failure_reason' => 'Auto-marked stale by daily cleanup',
                    ]);

                Log::warning("⚠️ Marked stale invoice reminders as failed", [
                    'count'     => $marked,
                    'timestamp' => now()->setTimezone('UTC')->toISOString(),
                ]);
            } catch (\Exception $e) {
                Log::error("Stale reminder cleanup failed: " . $e->getMessage());
            }
        })
        ->dailyAt('03:15')
        ->name('cleanup-stale-invoice-reminders')
        ->onOneServer()
        ->withoutOverlapping();

        // ==================== LANDLORD INVOICE GENERATION ====================

        $schedule->call(function () {
            try {
                $settings = SystemSetting::getSettings();

                if (!$settings->isAutoInvoiceGenerationEnabled()) {
                    Log::info("💰 Auto invoice generation (landlord) is disabled in settings - skipping scheduled run", [
                        'timestamp'              => now()->setTimezone('UTC')->toISOString(),
                        'auto_generate_invoices' => $settings->auto_generate_invoices,
                    ]);
                    return;
                }

                $readiness = $settings->isReadyForAutoInvoiceGeneration();
                if (!$readiness['ready']) {
                    Log::warning("💰 System not ready for auto invoice generation (landlord)", [
                        'issues'    => $readiness['issues'],
                        'timestamp' => now()->setTimezone('UTC')->toISOString(),
                    ]);
                    $this->notifyAdminsAboutReadinessIssues($readiness['issues'], 'landlord');
                    return;
                }

                $invoiceService = app(InvoiceService::class);
                $nextMonth = now()->addMonth();

                Log::info("💰 Starting automatic monthly invoice generation (landlord)", [
                    'timestamp'             => now()->setTimezone('UTC')->toISOString(),
                    'generating_for_month'  => $nextMonth->format('F Y'),
                    'due_date'              => $settings->calculateDueDateForPeriod($nextMonth)->format('Y-m-d'),
                    'reminders_enabled'     => $settings->shouldSendPaymentReminders(),
                    'reminder_days'         => $settings->getReminderDaysBefore(),
                ]);

                $result = $invoiceService->generateMonthlyInvoices();

                if ($result['success']) {
                    Log::info("✅ Automatic invoice generation (landlord) completed successfully", [
                        'period'             => $result['period'] ?? null,
                        'due_date'           => $result['due_date'] ?? null,
                        'generated'          => $result['details']['generated'] ?? 0,
                        'updated'            => $result['details']['updated'] ?? 0,
                        'notifications_sent' => $result['details']['notifications_sent'] ?? 0,
                        'reminders_enabled'  => $result['reminders_enabled'] ?? false,
                        'timestamp'          => now()->setTimezone('UTC')->toISOString(),
                    ]);

                    if ($result['details']['generated'] > 0 || $result['details']['updated'] > 0) {
                        $this->notifyAdminAboutInvoiceGeneration($result, 'landlord');
                    }
                } else {
                    Log::error("❌ Automatic invoice generation (landlord) failed", [
                        'message'   => $result['message'] ?? 'Unknown error',
                        'timestamp' => now()->setTimezone('UTC')->toISOString(),
                    ]);
                    $this->notifyAdminAboutInvoiceGenerationFailure($result, 'landlord');
                }
            } catch (\Exception $e) {
                Log::error("💥 Critical error in automatic invoice generation (landlord)", [
                    'error'     => $e->getMessage(),
                    'trace'     => $e->getTraceAsString(),
                    'timestamp' => now()->setTimezone('UTC')->toISOString(),
                ]);
                $this->sendCriticalFailureAlert($e, 'landlord');
            }
        })
        ->monthlyOn(25, '02:00')
        ->name('generate-monthly-invoices')
        ->onOneServer()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/invoice-generation.log'));

        // ==================== TENANT INVOICE GENERATION ====================

        $schedule->call(function () {
            try {
                $settings = SystemSetting::getSettings();

                if (!$settings->enable_tenant_invoicing) {
                    Log::info("👥 Tenant invoicing is disabled in settings - skipping scheduled run", [
                        'timestamp'                => now()->setTimezone('UTC')->toISOString(),
                        'enable_tenant_invoicing'  => $settings->enable_tenant_invoicing,
                    ]);
                    return;
                }

                if (!$settings->auto_generate_tenant_invoices) {
                    Log::info("👥 Auto tenant invoice generation is disabled - skipping scheduled run", [
                        'timestamp'                     => now()->setTimezone('UTC')->toISOString(),
                        'auto_generate_tenant_invoices' => $settings->auto_generate_tenant_invoices,
                    ]);
                    return;
                }

                $tenantInvoiceService = app(TenantInvoiceService::class);
                $nextMonth = now()->addMonth()->format('Y-m');

                Log::info("👥 Starting automatic monthly tenant invoice generation", [
                    'timestamp'             => now()->setTimezone('UTC')->toISOString(),
                    'generating_for_month'  => $nextMonth,
                    'calculation_method'    => $settings->tenant_calculation_method,
                    'tenant_monthly_dues'   => $settings->tenant_monthly_dues_amount,
                    'reminders_enabled'     => $settings->send_tenant_payment_reminders,
                    'grace_period_days'     => $settings->tenant_grace_period_days,
                ]);

                $result = $tenantInvoiceService->generateMonthlyTenantInvoices($nextMonth, $settings->send_tenant_payment_reminders);

                if ($result['success']) {
                    Log::info("✅ Automatic tenant invoice generation completed successfully", [
                        'generated_count'    => $result['generated_count'] ?? 0,
                        'skipped_count'      => $result['skipped_count'] ?? 0,
                        'period'             => $nextMonth,
                        'calculation_method' => $settings->tenant_calculation_method,
                        'errors'             => $result['errors'] ?? [],
                        'timestamp'          => now()->setTimezone('UTC')->toISOString(),
                    ]);

                    if ($result['generated_count'] > 0) {
                        $this->notifyAdminAboutTenantInvoiceGeneration($result);
                    }
                } else {
                    Log::error("❌ Automatic tenant invoice generation failed", [
                        'message'   => $result['message'] ?? 'Unknown error',
                        'timestamp' => now()->setTimezone('UTC')->toISOString(),
                    ]);
                    $this->notifyAdminAboutTenantInvoiceGenerationFailure($result);
                }
            } catch (\Exception $e) {
                Log::error("💥 Critical error in automatic tenant invoice generation", [
                    'error'     => $e->getMessage(),
                    'trace'     => $e->getTraceAsString(),
                    'timestamp' => now()->setTimezone('UTC')->toISOString(),
                ]);
                $this->sendCriticalFailureAlert($e, 'tenant');
            }
        })
        ->monthlyOn(5, '03:00')
        ->name('generate-monthly-tenant-invoices')
        ->onOneServer()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/tenant-invoice-generation.log'));

        // ==================== TENANT INVOICE — COMMAND RUNNERS ====================

        $schedule->command('tenant-invoices:generate')
            ->monthlyOn(1, '02:00')
            ->name('tenant-invoices-generate-command')
            ->onOneServer()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/tenant-invoices-generate.log'));

        $schedule->command('tenant-invoices:send-reminders')
            ->dailyAt('08:00')
            ->name('tenant-invoices-send-reminders-command')
            ->onOneServer()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/tenant-invoices-reminders.log'));

        // ==================== GHANA: LEASE PHASE MANAGEMENT ====================

        $schedule->call(function () {
            try {
                $today = now()->startOfDay();

                $newlyTransitioned = RentalAgreement::where('status', RentalAgreement::STATUS_ACTIVE)
                    ->where('payment_frequency', RentalAgreement::PAYMENT_FREQUENCY_MONTHLY)
                    ->whereNotNull('advance_rent_period_end')
                    ->where('advance_rent_period_end', '<', $today)
                    ->whereNull('metadata->advance_to_monthly_logged_at')
                    ->get();

                if ($newlyTransitioned->isEmpty()) {
                    Log::info("✅ Lease phase transition check: no leases to transition", [
                        'timestamp' => now()->setTimezone('UTC')->toISOString(),
                    ]);
                    return;
                }

                $transitionedCount = 0;
                foreach ($newlyTransitioned as $lease) {
                    try {
                        $metadata = $lease->metadata ?? [];
                        $metadata['advance_to_monthly_logged_at'] = now()->toISOString();
                        $metadata['advance_to_monthly_transition'] = [
                            'transitioned_at'            => now()->toISOString(),
                            'advance_rent_months'        => $lease->advance_rent_months,
                            'advance_rent_amount'        => $lease->advance_rent_amount,
                            'first_monthly_payment_date' => optional($lease->first_monthly_payment_date)->toDateString(),
                        ];

                        $lease->update(['metadata' => $metadata]);
                        $transitionedCount++;

                        if ($lease->tenant) {
                            try {
                                $lease->tenant->notify(new \App\Notifications\GeneralNotification(
                                    title: 'Advance Rent Period Ended',
                                    message: "Your advance rent for Unit {$lease->unit->unit_number} at "
                                        . "{$lease->property->property_name} has ended. Monthly rent of "
                                        . "GHS " . number_format($lease->monthly_rent, 2) . " is now due on the "
                                        . "{$lease->payment_due_day}" . $this->getDaySuffix($lease->payment_due_day)
                                        . " of each month.",
                                    icon: 'fas fa-calendar-check text-info',
                                    category: 'lease_phase_transition',
                                    actionUrl: route('tenant.property-units.lease-details', [$lease->unit_id, $lease->id]),
                                    priority: 1,
                                    data: [
                                        'lease_id' => $lease->id,
                                        'phase'    => 'monthly',
                                    ]
                                ));
                            } catch (\Exception $e) {
                                Log::warning('Failed to notify tenant of phase transition', [
                                    'lease_id' => $lease->id,
                                    'error'    => $e->getMessage(),
                                ]);
                            }
                        }

                        Log::info("📅 Lease transitioned to monthly phase", [
                            'lease_id'                    => $lease->id,
                            'tenant_id'                   => $lease->tenant_id,
                            'advance_period_end'          => optional($lease->advance_rent_period_end)->toDateString(),
                            'first_monthly_payment_date'  => optional($lease->first_monthly_payment_date)->toDateString(),
                        ]);
                    } catch (\Exception $e) {
                        Log::error("Failed to transition lease {$lease->id} to monthly phase", [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                Log::info("✅ Lease phase transition completed", [
                    'transitioned_count' => $transitionedCount,
                    'timestamp'          => now()->setTimezone('UTC')->toISOString(),
                ]);
            } catch (\Exception $e) {
                Log::error("💥 Lease phase transition failed: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        })
        ->dailyAt('00:30')
        ->name('lease-phase-transition')
        ->onOneServer()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/lease-phase-transition.log'));

        $schedule->call(function () {
            try {
                $reminderDays = (int) config('leases.ghana.advance_end_reminder_days', 14);
                $windowEnd = now()->addDays($reminderDays)->endOfDay();

                $leasesNearingEnd = RentalAgreement::where('status', RentalAgreement::STATUS_ACTIVE)
                    ->where('payment_frequency', RentalAgreement::PAYMENT_FREQUENCY_MONTHLY)
                    ->whereNotNull('advance_rent_period_end')
                    ->whereBetween('advance_rent_period_end', [now()->startOfDay(), $windowEnd])
                    ->with(['tenant', 'unit', 'property'])
                    ->get();

                $sentCount = 0;
                foreach ($leasesNearingEnd as $lease) {
                    if (!$lease->tenant) continue;

                    try {
                        $daysLeft = (int) now()->diffInDays($lease->advance_rent_period_end, false);
                        $lease->tenant->notify(new \App\Notifications\GeneralNotification(
                            title: 'Advance Rent Ending Soon',
                            message: "Your advance rent period for Unit {$lease->unit->unit_number} at "
                                . "{$lease->property->property_name} ends in {$daysLeft} day(s). "
                                . "Monthly rent of GHS " . number_format($lease->monthly_rent, 2)
                                . " will begin on " . optional($lease->first_monthly_payment_date)->format('F j, Y') . ".",
                            icon: 'fas fa-clock text-warning',
                            category: 'advance_rent_ending',
                            actionUrl: route('tenant.property-units.lease-details', [$lease->unit_id, $lease->id]),
                            priority: 1,
                            data: [
                                'lease_id'                   => $lease->id,
                                'days_left'                  => $daysLeft,
                                'first_monthly_payment_date' => optional($lease->first_monthly_payment_date)->toDateString(),
                            ]
                        ));
                        $sentCount++;
                    } catch (\Exception $e) {
                        Log::warning('Failed to send advance-ending reminder', [
                            'lease_id' => $lease->id,
                            'error'    => $e->getMessage(),
                        ]);
                    }
                }

                Log::info("✅ Advance-rent-ending reminders processed", [
                    'leases_in_window' => $leasesNearingEnd->count(),
                    'sent'             => $sentCount,
                    'reminder_days'    => $reminderDays,
                ]);
            } catch (\Exception $e) {
                Log::error("Advance-rent-ending reminders failed: " . $e->getMessage());
            }
        })
        ->dailyAt('08:30')
        ->name('advance-rent-ending-reminders')
        ->onOneServer()
        ->withoutOverlapping();

        $schedule->call(function () {
            try {
                $exceeding = RentalAgreement::where('status', RentalAgreement::STATUS_ACTIVE)
                    ->where('advance_rent_compliance_status', RentalAgreement::COMPLIANCE_EXCEEDS_LEGAL_LIMIT)
                    ->with(['landlord', 'tenant', 'unit', 'property'])
                    ->get();

                if ($exceeding->isEmpty()) {
                    Log::info("✅ Ghana advance-rent compliance check: all leases compliant", [
                        'timestamp' => now()->setTimezone('UTC')->toISOString(),
                    ]);
                    return;
                }

                Log::warning("⚠️ Ghana advance-rent compliance issues detected", [
                    'non_compliant_count' => $exceeding->count(),
                    'leases'              => $exceeding->take(10)->map(fn ($l) => [
                        'lease_id'            => $l->id,
                        'unit'                => $l->unit->unit_number ?? null,
                        'landlord_id'         => $l->landlord_id,
                        'advance_rent_months' => $l->advance_rent_months,
                        'compliance_status'   => $l->advance_rent_compliance_status,
                    ])->toArray(),
                    'timestamp' => now()->setTimezone('UTC')->toISOString(),
                ]);

                $admins = User::whereIn('type', [
                    User::TYPE_ADMIN,
                    User::TYPE_SUPER_ADMIN,
                ])->get();

                foreach ($admins as $admin) {
                    try {
                        $admin->notify(new \App\Notifications\GeneralNotification(
                            title: 'Advance Rent Compliance Alert',
                            message: "{$exceeding->count()} active lease(s) exceed the Rent Act 1963 advance-rent cap. "
                                . "Please review and take corrective action.",
                            icon: 'fas fa-exclamation-triangle text-warning',
                            category: 'ghana_compliance_alert',
                            actionUrl: route('property-units.index'),
                            priority: 2,
                            data: ['non_compliant_count' => $exceeding->count()],
                        ));
                    } catch (\Exception $e) {
                        Log::warning('Failed to send compliance alert', [
                            'admin_id' => $admin->id,
                            'error'    => $e->getMessage(),
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error("Ghana compliance check failed: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        })
        ->weekly()
        ->mondays()
        ->at('09:30')
        ->name('ghana-advance-rent-compliance-check')
        ->onOneServer()
        ->withoutOverlapping();

        // ==================== INVOICE: PROPERTY UNIT INVOICE TASKS ====================

        $schedule->call(function () {
            try {
                $cutoffDays = (int) config('leases.invoice.mark_overdue_after_days', 1);
                $cutoff = now()->subDays($cutoffDays)->startOfDay();

                $count = PropertyUnitInvoice::whereIn('status', [
                        PropertyUnitInvoice::STATUS_PENDING,
                        PropertyUnitInvoice::STATUS_PARTIAL,
                    ])
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', $cutoff)
                    ->update([
                        'status' => PropertyUnitInvoice::STATUS_OVERDUE,
                    ]);

                Log::info("✅ Marked property unit invoices as overdue", [
                    'marked_count' => $count,
                    'cutoff_date'  => $cutoff->toDateString(),
                    'cutoff_days'  => $cutoffDays,
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to mark property unit invoices as overdue: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        })
        ->dailyAt('02:30')
        ->name('mark-property-unit-invoices-overdue')
        ->onOneServer()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/property-unit-invoices-overdue.log'));

        $schedule->call(function () {
            try {
                $reminderDays = (int) config('leases.invoice.reminder_days_before', 7);
                $windowEnd = now()->addDays($reminderDays)->endOfDay();

                $upcomingInvoices = PropertyUnitInvoice::whereIn('status', [
                        PropertyUnitInvoice::STATUS_PENDING,
                        PropertyUnitInvoice::STATUS_PARTIAL,
                    ])
                    ->whereBetween('due_date', [now()->startOfDay(), $windowEnd])
                    ->with(['tenant', 'unit', 'property', 'lease'])
                    ->get();

                $sentCount = 0;
                $failedCount = 0;

                foreach ($upcomingInvoices as $invoice) {
                    if (!$invoice->tenant) continue;

                    $metadata = $invoice->metadata ?? [];
                    $lastReminder = $metadata['last_reminder_sent_at'] ?? null;
                    if ($lastReminder && now()->diffInHours(Carbon::parse($lastReminder)) < 24) {
                        continue;
                    }

                    try {
                        $balance = max(0, $invoice->amount - $invoice->amount_paid);

                        $invoice->tenant->notify(new \App\Notifications\GeneralNotification(
                            title: 'Upcoming Rent Payment',
                            message: "Invoice #{$invoice->reference} for GHS " . number_format($balance, 2)
                                . " is due on " . $invoice->due_date->format('F j, Y')
                                . " for Unit {$invoice->unit->unit_number}.",
                            icon: 'fas fa-file-invoice text-info',
                            category: 'invoice_reminder',
                            actionUrl: route('tenant.property-units.lease-details', [$invoice->unit_id, $invoice->lease_id]),
                            priority: 1,
                            data: [
                                'invoice_id' => $invoice->id,
                                'amount_due' => $balance,
                                'due_date'   => $invoice->due_date->toDateString(),
                            ]
                        ));

                        $metadata['last_reminder_sent_at'] = now()->toISOString();
                        $metadata['reminders_sent'] = array_merge(
                            $metadata['reminders_sent'] ?? [],
                            [['sent_at' => now()->toISOString(), 'days_before_due' => $reminderDays]]
                        );
                        $invoice->update(['metadata' => $metadata]);

                        $sentCount++;
                    } catch (\Exception $e) {
                        $failedCount++;
                        Log::warning('Failed to send invoice reminder', [
                            'invoice_id' => $invoice->id,
                            'error'      => $e->getMessage(),
                        ]);
                    }
                }

                Log::info("✅ Property unit invoice reminders processed", [
                    'total_candidates' => $upcomingInvoices->count(),
                    'sent'             => $sentCount,
                    'failed'           => $failedCount,
                    'reminder_days'    => $reminderDays,
                ]);
            } catch (\Exception $e) {
                Log::error("Property unit invoice reminders failed: " . $e->getMessage());
            }
        })
        ->dailyAt('09:15')
        ->name('property-unit-invoice-reminders')
        ->onOneServer()
        ->withoutOverlapping();

        $schedule->call(function () {
            try {
                $terminatedLeases = RentalAgreement::where('status', RentalAgreement::STATUS_TERMINATED)
                    ->whereNotNull('terminated_at')
                    ->where('terminated_at', '>=', now()->subDays(7))
                    ->pluck('id');

                if ($terminatedLeases->isEmpty()) {
                    return;
                }

                $voidedCount = PropertyUnitInvoice::whereIn('lease_id', $terminatedLeases)
                    ->whereIn('status', [
                        PropertyUnitInvoice::STATUS_PENDING,
                        PropertyUnitInvoice::STATUS_PARTIAL,
                        PropertyUnitInvoice::STATUS_OVERDUE,
                    ])
                    ->where('due_date', '>', now())
                    ->update([
                        'status'      => PropertyUnitInvoice::STATUS_VOID,
                        'void_reason' => 'Lease terminated — safety net void',
                        'voided_at'   => now(),
                    ]);

                if ($voidedCount > 0) {
                    Log::info("✅ Safety-net void of future invoices for terminated leases", [
                        'voided_count'      => $voidedCount,
                        'terminated_leases' => $terminatedLeases->count(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("Safety-net void of terminated-lease invoices failed: " . $e->getMessage());
            }
        })
        ->hourly()
        ->name('void-future-invoices-on-termination')
        ->onOneServer()
        ->withoutOverlapping();

        // ==================== LANDLORD INVOICE YEAR-END ARCHIVING ====================

        $schedule->call(function () {
            try {
                $settings = SystemSetting::getSettings();

                if (!($settings->enable_year_end_archive_landlord ?? true)) {
                    Log::info("📦 Landlord year-end archiving is disabled in settings - skipping scheduled run", [
                        'timestamp' => now()->setTimezone('UTC')->toISOString(),
                    ]);
                    return;
                }

                $archiveService = new YearEndArchiveService($settings, app(NotificationService::class));

                $archiveMonth = $settings->year_end_archive_month_landlord ?? 1;
                $archiveDay   = $settings->year_end_archive_day_landlord ?? 15;

                $shouldRun = now()->month == $archiveMonth && now()->day == $archiveDay;

                if (!$shouldRun) {
                    return;
                }

                $previousYear = now()->subYear()->year;

                Log::info("📦 Starting scheduled landlord year-end archiving", [
                    'year'         => $previousYear,
                    'archive_date' => now()->format('Y-m-d'),
                    'archive_type' => 'landlord',
                    'timestamp'    => now()->setTimezone('UTC')->toISOString(),
                ]);

                $results = $archiveService->processYearEndArchiveLandlord($previousYear);

                Log::info("✅ Landlord year-end archiving completed", [
                    'year'          => $previousYear,
                    'paid_archived' => $results['paid_archived'],
                    'unpaid_kept'   => $results['unpaid_kept'],
                    'errors'        => $results['errors'],
                    'timestamp'     => now()->setTimezone('UTC')->toISOString(),
                ]);

                if ($settings->send_yearly_archive_report_landlord ?? true) {
                    $this->sendYearEndArchiveSummaryLandlord($results);
                }
            } catch (\Exception $e) {
                Log::error("💥 Landlord year-end archiving failed", [
                    'error'     => $e->getMessage(),
                    'trace'     => $e->getTraceAsString(),
                    'timestamp' => now()->setTimezone('UTC')->toISOString(),
                ]);
            }
        })
        ->dailyAt('01:00')
        ->name('year-end-archive-landlord')
        ->onOneServer()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/year-end-archive-landlord.log'));

        // ==================== TENANT INVOICE YEAR-END ARCHIVING ====================

        $schedule->call(function () {
            try {
                $settings = SystemSetting::getSettings();

                if (!$settings->enable_tenant_invoicing) {
                    Log::info("👥 Tenant invoicing disabled - skipping tenant year-end archiving", [
                        'timestamp' => now()->setTimezone('UTC')->toISOString(),
                    ]);
                    return;
                }

                if (!($settings->enable_year_end_archive_tenant ?? true)) {
                    Log::info("📦 Tenant year-end archiving is disabled in settings - skipping scheduled run", [
                        'timestamp' => now()->setTimezone('UTC')->toISOString(),
                    ]);
                    return;
                }

                $archiveService = new YearEndArchiveService($settings, app(NotificationService::class));

                $archiveMonth = $settings->year_end_archive_month_tenant ?? 1;
                $archiveDay   = $settings->year_end_archive_day_tenant ?? 16;

                $shouldRun = now()->month == $archiveMonth && now()->day == $archiveDay;

                if (!$shouldRun) {
                    return;
                }

                $previousYear = now()->subYear()->year;

                Log::info("📦 Starting scheduled tenant year-end archiving", [
                    'year'         => $previousYear,
                    'archive_date' => now()->format('Y-m-d'),
                    'archive_type' => 'tenant',
                    'timestamp'    => now()->setTimezone('UTC')->toISOString(),
                ]);

                $results = $archiveService->processYearEndArchiveTenant($previousYear);

                Log::info("✅ Tenant year-end archiving completed", [
                    'year'          => $previousYear,
                    'paid_archived' => $results['paid_archived'],
                    'unpaid_kept'   => $results['unpaid_kept'],
                    'errors'        => $results['errors'],
                    'timestamp'     => now()->setTimezone('UTC')->toISOString(),
                ]);

                if ($settings->send_yearly_archive_report_tenant ?? true) {
                    $this->sendYearEndArchiveSummaryTenant($results);
                }
            } catch (\Exception $e) {
                Log::error("💥 Tenant year-end archiving failed", [
                    'error'     => $e->getMessage(),
                    'trace'     => $e->getTraceAsString(),
                    'timestamp' => now()->setTimezone('UTC')->toISOString(),
                ]);
            }
        })
        ->dailyAt('01:30')
        ->name('year-end-archive-tenant')
        ->onOneServer()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/year-end-archive-tenant.log'));

        // ==================== REGISTRATIONS YEAR-END ARCHIVING ====================

        $schedule->command('registrations:archive --year=' . now()->subYear()->year)
            ->yearlyOn(1, 1, '00:01')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/registration-archive.log'));

        $schedule->command('registrations:archive --dry-run --year=' . now()->subYear()->year)
            ->yearlyOn(1, 2, '00:01')
            ->appendOutputTo(storage_path('logs/registration-archive-dry-run.log'));

        $schedule->command('registrations:cleanup-archive --years=7')
            ->yearlyOn(1, 15, '02:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/archive-cleanup.log'));

        $schedule->command('registrations:cleanup-archive --years=7')
            ->monthlyOn(1, '03:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/monthly-archive-cleanup.log'));

        $schedule->command('registrations:archive --year=' . now()->subYears(3)->year)
            ->quarterly()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/quarterly-registration-archive.log'));

        // ==================== PROPERTY OWNERSHIP TRANSFER ARCHIVING ====================

        $schedule->command('transfers:archive --force')
            ->cron('0 2 2 1 *')
            ->name('archive-ownership-transfers')
            ->onOneServer()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/transfer-archive.log'));

        $schedule->command('transfers:archive --year=' . now()->subYear()->year . ' --force')
            ->quarterly()
            ->name('quarterly-transfer-archive')
            ->onOneServer()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/quarterly-transfer-archive.log'));

        $schedule->call(function () {
            try {
                $settings = SystemSetting::getSettings();
                $archiveService = app(TransferArchiveService::class);
                $retentionYears = $settings->transfer_archive_retention_years ?? 7;

                Log::info("📦 Starting cleanup of old transfer archives", [
                    'retention_years' => $retentionYears,
                    'timestamp'       => now()->setTimezone('UTC')->toISOString(),
                ]);

                $result = $archiveService->deleteOldArchives($retentionYears);

                Log::info("✅ Old transfer archive cleanup completed", [
                    'deleted_count' => $result['deleted_count'],
                    'cutoff_year'   => $result['cutoff_year'],
                    'timestamp'     => now()->setTimezone('UTC')->toISOString(),
                ]);
            } catch (\Exception $e) {
                Log::error("💥 Failed to clean up old transfer archives", [
                    'error'     => $e->getMessage(),
                    'trace'     => $e->getTraceAsString(),
                    'timestamp' => now()->setTimezone('UTC')->toISOString(),
                ]);
            }
        })
        ->cron('0 4 15 1 *')
        ->name('cleanup-old-transfer-archives')
        ->onOneServer()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/transfer-archive-cleanup.log'));

        $schedule->call(function () {
            try {
                $settings = SystemSetting::getSettings();

                if (!($settings->send_transfer_archive_reports ?? true)) {
                    return;
                }

                $archiveService = app(TransferArchiveService::class);
                $stats = $archiveService->getArchiveStatistics();
                $previousYear = now()->subYear()->year;

                Log::info("📊 Transfer Archive Summary Report", [
                    'year'           => $previousYear,
                    'total_archived' => $stats['total_archived'] ?? 0,
                    'by_status'      => $stats['by_status'] ?? [],
                    'by_year'        => $stats['by_year'] ?? [],
                    'total_value'    => $stats['total_value'] ?? 0,
                    'timestamp'      => now()->setTimezone('UTC')->toISOString(),
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to send transfer archive report: " . $e->getMessage());
            }
        })
        ->cron('0 8 3 1 *')
        ->name('send-transfer-archive-report')
        ->onOneServer();

        // ==================== TRANSFER REVERSAL EXPIRATION CHECK ====================

        $schedule->call(function () {
            try {
                Log::info("🔄 Starting transfer reversal expiration check", [
                    'timestamp' => now()->setTimezone('UTC')->toISOString(),
                ]);

                $expiredRequests = App\Models\PropertyOwnershipTransfer::where('reversal_status', 'pending')
                    ->where('reversal_deadline', '<', now())
                    ->get();

                if ($expiredRequests->isEmpty()) {
                    Log::info("✅ No expired reversal requests found", [
                        'timestamp' => now()->setTimezone('UTC')->toISOString(),
                    ]);
                    return;
                }

                Log::info("⚠️ Found expired reversal requests", [
                    'count'     => $expiredRequests->count(),
                    'timestamp' => now()->setTimezone('UTC')->toISOString(),
                ]);

                $processedCount = 0;
                $failedCount = 0;

                foreach ($expiredRequests as $transfer) {
                    DB::beginTransaction();

                    try {
                        $transfer->update([
                            'reversal_status'        => 'expired',
                            'reversal_processed_at'  => now(),
                            'reversal_processed_by'  => null,
                            'metadata'               => array_merge($transfer->metadata ?? [], [
                                'reversal_expiry' => [
                                    'expired_at'         => now()->toISOString(),
                                    'deadline'           => $transfer->reversal_deadline->toISOString(),
                                    'reason'             => 'Request expired before admin review',
                                    'auto_processed_by'  => 'scheduled_job',
                                ],
                            ]),
                        ]);

                        if ($transfer->currentLandlord) {
                            try {
                                $notification = new App\Notifications\TransferReversalExpired($transfer);
                                $transfer->currentLandlord->notify($notification);
                            } catch (\Exception $e) {
                                Log::warning("Failed to notify landlord about expired request", [
                                    'transfer_id' => $transfer->id,
                                    'error'       => $e->getMessage(),
                                ]);
                            }
                        }

                        if ($transfer->newLandlord) {
                            try {
                                $notification = new App\Notifications\TransferReversalExpired($transfer, 'new_owner');
                                $transfer->newLandlord->notify($notification);
                            } catch (\Exception $e) {
                                Log::warning("Failed to notify current owner about expired request", [
                                    'transfer_id' => $transfer->id,
                                    'error'       => $e->getMessage(),
                                ]);
                            }
                        }

                        $admins = App\Models\User::whereIn('type', [
                            App\Models\User::TYPE_ADMIN,
                            App\Models\User::TYPE_SUPER_ADMIN,
                        ])->get();

                        foreach ($admins as $admin) {
                            try {
                                $notification = new App\Notifications\TransferReversalExpired($transfer, 'admin');
                                $admin->notify($notification);
                            } catch (\Exception $e) {
                                Log::warning("Failed to notify admin about expired request", [
                                    'transfer_id' => $transfer->id,
                                    'admin_id'    => $admin->id,
                                    'error'       => $e->getMessage(),
                                ]);
                            }
                        }

                        Log::info("📝 Reversal request expired", [
                            'transfer_id'        => $transfer->id,
                            'document_reference' => $transfer->document_reference,
                            'property_name'      => $transfer->property->property_name ?? 'Unknown',
                            'deadline'           => $transfer->reversal_deadline->toISOString(),
                            'expired_at'         => now()->toISOString(),
                        ]);

                        DB::commit();
                        $processedCount++;
                    } catch (\Exception $e) {
                        DB::rollBack();
                        $failedCount++;

                        Log::error("❌ Failed to process expired reversal request", [
                            'transfer_id' => $transfer->id,
                            'error'       => $e->getMessage(),
                            'trace'       => $e->getTraceAsString(),
                        ]);
                    }
                }

                Log::info("✅ Transfer reversal expiration check completed", [
                    'expired_found'          => $expiredRequests->count(),
                    'processed_successfully' => $processedCount,
                    'failed'                 => $failedCount,
                    'timestamp'              => now()->setTimezone('UTC')->toISOString(),
                ]);
            } catch (\Exception $e) {
                Log::error("💥 Critical error in transfer reversal expiration check", [
                    'error'     => $e->getMessage(),
                    'trace'     => $e->getTraceAsString(),
                    'timestamp' => now()->setTimezone('UTC')->toISOString(),
                ]);
            }
        })
        ->hourly()
        ->name('check-expired-reversal-requests')
        ->onOneServer()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/reversal-expiration.log'));

        $schedule->call(function () {
            try {
                $pendingReversals = App\Models\PropertyOwnershipTransfer::where('reversal_status', 'pending')
                    ->where('status', App\Models\PropertyOwnershipTransfer::STATUS_COMPLETED)
                    ->where('is_reversed', false)
                    ->with(['property', 'currentLandlord'])
                    ->get();

                $expiringSoon = $pendingReversals->filter(function ($transfer) {
                    return $transfer->reversal_deadline
                        && $transfer->reversal_deadline->diffInDays(now()) <= 2
                        && $transfer->reversal_deadline > now();
                });

                $expiredToday = App\Models\PropertyOwnershipTransfer::where('reversal_status', 'expired')
                    ->whereDate('reversal_processed_at', now()->toDateString())
                    ->count();

                Log::info("📊 Daily Reversal Request Summary", [
                    'pending_count'    => $pendingReversals->count(),
                    'expiring_soon_48h'=> $expiringSoon->count(),
                    'expired_today'    => $expiredToday,
                    'timestamp'        => now()->setTimezone('UTC')->toISOString(),
                ]);

                if ($expiringSoon->isNotEmpty()) {
                    Log::warning("⚠️ Reversal requests expiring soon", [
                        'count'   => $expiringSoon->count(),
                        'details' => $expiringSoon->map(function ($transfer) {
                            return [
                                'transfer_id'  => $transfer->id,
                                'property'     => $transfer->property->property_name ?? 'Unknown',
                                'deadline'     => $transfer->reversal_deadline->toISOString(),
                                'requested_by' => $transfer->currentLandlord->name ?? 'Unknown',
                            ];
                        })->toArray(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("Failed to generate daily reversal summary: " . $e->getMessage());
            }
        })
        ->dailyAt('08:00')
        ->name('daily-reversal-summary')
        ->onOneServer();

        // ==================== LANDLORD INVOICE POST-PAYMENT ARCHIVING ====================

        $schedule->call(function () {
            try {
                $settings = SystemSetting::getSettings();

                if (!($settings->auto_archive_paid_after_retention_landlord ?? true)) {
                    Log::info("📦 Landlord post-payment archiving is disabled - skipping scheduled run", [
                        'timestamp' => now()->setTimezone('UTC')->toISOString(),
                    ]);
                    return;
                }

                $archiveService = new YearEndArchiveService($settings, app(NotificationService::class));

                Log::info("📦 Starting landlord post-payment archiving", [
                    'retention_months' => $settings->paid_invoice_retention_months_landlord ?? 3,
                    'timestamp'        => now()->setTimezone('UTC')->toISOString(),
                ]);

                $results = $archiveService->processPostPaymentArchiveLandlord();

                Log::info("✅ Landlord post-payment archiving completed", [
                    'processed' => $results['total'],
                    'archived'  => $results['archived'],
                    'errors'    => $results['errors'],
                    'timestamp' => now()->setTimezone('UTC')->toISOString(),
                ]);
            } catch (\Exception $e) {
                Log::error("💥 Landlord post-payment archiving failed", [
                    'error'     => $e->getMessage(),
                    'trace'     => $e->getTraceAsString(),
                    'timestamp' => now()->setTimezone('UTC')->toISOString(),
                ]);
            }
        })
        ->weekly()
        ->mondays()
        ->at('02:00')
        ->name('post-payment-archive-landlord')
        ->onOneServer()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/post-payment-archive-landlord.log'));

        // ==================== TENANT INVOICE POST-PAYMENT ARCHIVING ====================

        $schedule->call(function () {
            try {
                $settings = SystemSetting::getSettings();

                if (!$settings->enable_tenant_invoicing) {
                    return;
                }

                if (!($settings->auto_archive_paid_after_retention_tenant ?? true)) {
                    Log::info("📦 Tenant post-payment archiving is disabled - skipping scheduled run", [
                        'timestamp' => now()->setTimezone('UTC')->toISOString(),
                    ]);
                    return;
                }

                $archiveService = new YearEndArchiveService($settings, app(NotificationService::class));

                Log::info("📦 Starting tenant post-payment archiving", [
                    'retention_months' => $settings->paid_invoice_retention_months_tenant ?? 3,
                    'timestamp'        => now()->setTimezone('UTC')->toISOString(),
                ]);

                $results = $archiveService->processPostPaymentArchiveTenant();

                Log::info("✅ Tenant post-payment archiving completed", [
                    'processed' => $results['total'],
                    'archived'  => $results['archived'],
                    'errors'    => $results['errors'],
                    'timestamp' => now()->setTimezone('UTC')->toISOString(),
                ]);
            } catch (\Exception $e) {
                Log::error("💥 Tenant post-payment archiving failed", [
                    'error'     => $e->getMessage(),
                    'trace'     => $e->getTraceAsString(),
                    'timestamp' => now()->setTimezone('UTC')->toISOString(),
                ]);
            }
        })
        ->weekly()
        ->tuesdays()
        ->at('02:30')
        ->name('post-payment-archive-tenant')
        ->onOneServer()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/post-payment-archive-tenant.log'));

        // ==================== ARCHIVE REMINDERS (Landlord) ====================

        $schedule->call(function () {
            try {
                $settings = SystemSetting::getSettings();

                if (!($settings->notify_landlords_before_archive ?? true)) {
                    return;
                }

                $archiveService = new YearEndArchiveService($settings, app(NotificationService::class));
                $reminderDays = $settings->archive_notification_days_landlord ?? 30;
                $archiveMonth = $settings->year_end_archive_month_landlord ?? 1;
                $archiveDay   = $settings->year_end_archive_day_landlord ?? 15;

                $archiveDate = Carbon::create(now()->year, $archiveMonth, $archiveDay);
                $daysUntilArchive = now()->diffInDays($archiveDate);

                if ($daysUntilArchive == $reminderDays) {
                    Log::info("📧 Sending landlord archive reminders", [
                        'days_until_archive' => $reminderDays,
                        'archive_date'       => $archiveDate->format('Y-m-d'),
                        'timestamp'          => now()->setTimezone('UTC')->toISOString(),
                    ]);

                    $results = $archiveService->sendYearEndRemindersLandlord();

                    Log::info("✅ Landlord archive reminders sent", [
                        'sent'             => $results['reminders_sent'],
                        'paid_reminders'   => $results['paid_reminders'],
                        'unpaid_reminders' => $results['unpaid_reminders'],
                        'timestamp'        => now()->setTimezone('UTC')->toISOString(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("Failed to send landlord archive reminders: " . $e->getMessage());
            }
        })
        ->dailyAt('08:00')
        ->name('send-archive-reminders-landlord')
        ->onOneServer();

        // ==================== ARCHIVE REMINDERS (Tenant) ====================

        $schedule->call(function () {
            try {
                $settings = SystemSetting::getSettings();

                if (!$settings->enable_tenant_invoicing) {
                    return;
                }

                if (!($settings->notify_tenants_before_archive ?? true)) {
                    return;
                }

                $archiveService = new YearEndArchiveService($settings, app(NotificationService::class));
                $reminderDays = $settings->archive_notification_days_tenant ?? 30;
                $archiveMonth = $settings->year_end_archive_month_tenant ?? 1;
                $archiveDay   = $settings->year_end_archive_day_tenant ?? 16;

                $archiveDate = Carbon::create(now()->year, $archiveMonth, $archiveDay);
                $daysUntilArchive = now()->diffInDays($archiveDate);

                if ($daysUntilArchive == $reminderDays) {
                    Log::info("📧 Sending tenant archive reminders", [
                        'days_until_archive' => $reminderDays,
                        'archive_date'       => $archiveDate->format('Y-m-d'),
                        'timestamp'          => now()->setTimezone('UTC')->toISOString(),
                    ]);

                    $results = $archiveService->sendYearEndRemindersTenant();

                    Log::info("✅ Tenant archive reminders sent", [
                        'sent'             => $results['reminders_sent'],
                        'paid_reminders'   => $results['paid_reminders'],
                        'unpaid_reminders' => $results['unpaid_reminders'],
                        'timestamp'        => now()->setTimezone('UTC')->toISOString(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("Failed to send tenant archive reminders: " . $e->getMessage());
            }
        })
        ->dailyAt('08:30')
        ->name('send-archive-reminders-tenant')
        ->onOneServer();

        // ==================== PAYMENT REMINDERS (Landlord) ====================

        $schedule->call(function () {
            try {
                $settings = SystemSetting::getSettings();

                if (!$settings->shouldSendPaymentReminders()) {
                    Log::info("📧 Payment reminders (landlord) are disabled in settings - skipping scheduled run", [
                        'timestamp'              => now()->setTimezone('UTC')->toISOString(),
                        'send_payment_reminders' => $settings->send_payment_reminders,
                    ]);
                    return;
                }

                $reminderDays = $settings->getReminderDaysBefore();
                $invoiceService = app(InvoiceService::class);

                Log::info("📧 Sending payment due date reminders (landlord)", [
                    'timestamp'            => now()->setTimezone('UTC')->toISOString(),
                    'reminder_days_before' => $reminderDays,
                ]);

                $invoicesNeedingReminders = Invoice::needsReminder($reminderDays)
                    ->with('property.landlord')
                    ->get();

                $sentCount = 0;
                $failedCount = 0;

                foreach ($invoicesNeedingReminders as $invoice) {
                    try {
                        if ($invoice->shouldSendReminder($reminderDays)) {
                            $notificationService = app(NotificationService::class);
                            $result = $notificationService->sendInvoiceReminder($invoice);

                            if ($result) {
                                $invoice->markReminderSent();
                                $sentCount++;

                                Log::info("Payment reminder sent (landlord)", [
                                    'invoice_id'     => $invoice->id,
                                    'landlord_id'    => $invoice->property->landlord_id,
                                    'days_until_due' => $invoice->days_until_due,
                                ]);
                            } else {
                                $failedCount++;
                            }
                        }
                    } catch (\Exception $e) {
                        $failedCount++;
                        Log::error("Failed to send reminder for invoice {$invoice->id}: " . $e->getMessage());
                    }
                }

                Log::info("✅ Payment reminders (landlord) processed", [
                    'sent_count'     => $sentCount,
                    'failed_count'   => $failedCount,
                    'total_invoices' => $invoicesNeedingReminders->count(),
                    'reminder_days'  => $reminderDays,
                    'timestamp'      => now()->setTimezone('UTC')->toISOString(),
                ]);
            } catch (\Exception $e) {
                Log::error("❌ Failed to send payment reminders (landlord): " . $e->getMessage());
            }
        })
        ->dailyAt('08:00')
        ->name('send-payment-reminders')
        ->onOneServer();

        // ==================== TENANT PAYMENT REMINDERS ====================

        $schedule->call(function () {
            try {
                $settings = SystemSetting::getSettings();

                if (!$settings->enable_tenant_invoicing) {
                    Log::info("📧 Tenant invoicing is disabled - skipping tenant reminders", [
                        'timestamp' => now()->setTimezone('UTC')->toISOString(),
                    ]);
                    return;
                }

                if (!$settings->send_tenant_payment_reminders) {
                    Log::info("📧 Tenant payment reminders are disabled in settings - skipping scheduled run", [
                        'timestamp'                      => now()->setTimezone('UTC')->toISOString(),
                        'send_tenant_payment_reminders'  => $settings->send_tenant_payment_reminders,
                    ]);
                    return;
                }

                $reminderDays = $settings->reminder_days_before ?? 7;

                Log::info("📧 Sending tenant payment due date reminders", [
                    'timestamp'            => now()->setTimezone('UTC')->toISOString(),
                    'reminder_days_before' => $reminderDays,
                ]);

                $targetDate = now()->addDays($reminderDays)->toDateString();

                $invoicesNeedingReminders = TenantInvoice::with(['tenant', 'property'])
                    ->where('status', TenantInvoice::STATUS_PENDING)
                    ->whereDate('due_date', $targetDate)
                    ->get();

                $sentCount = 0;
                $failedCount = 0;

                foreach ($invoicesNeedingReminders as $invoice) {
                    try {
                        $notificationService = app(NotificationService::class);
                        $result = $notificationService->sendTenantInvoiceReminder($invoice);

                        if ($result) {
                            $metadata = $invoice->metadata ?? [];
                            $reminders = $metadata['reminders_sent'] ?? [];
                            $reminders[] = [
                                'sent_at'         => now()->toDateTimeString(),
                                'days_before_due' => $reminderDays,
                            ];
                            $metadata['reminders_sent'] = $reminders;
                            $metadata['last_reminder_sent_at'] = now()->toDateTimeString();
                            $invoice->update(['metadata' => $metadata]);

                            $sentCount++;

                            Log::info("Tenant payment reminder sent", [
                                'invoice_id'     => $invoice->id,
                                'tenant_id'      => $invoice->tenant_id,
                                'days_until_due' => $reminderDays,
                            ]);
                        } else {
                            $failedCount++;
                        }
                    } catch (\Exception $e) {
                        $failedCount++;
                        Log::error("Failed to send tenant reminder for invoice {$invoice->id}: " . $e->getMessage());
                    }
                }

                Log::info("✅ Tenant payment reminders processed", [
                    'sent_count'     => $sentCount,
                    'failed_count'   => $failedCount,
                    'total_invoices' => $invoicesNeedingReminders->count(),
                    'reminder_days'  => $reminderDays,
                    'timestamp'      => now()->setTimezone('UTC')->toISOString(),
                ]);
            } catch (\Exception $e) {
                Log::error("❌ Failed to send tenant payment reminders: " . $e->getMessage());
            }
        })
        ->dailyAt('09:00')
        ->name('send-tenant-payment-reminders')
        ->onOneServer();

        // ==================== OVERDUE INVOICES (Landlord) ====================

        $schedule->call(function () {
            try {
                $settings = SystemSetting::getSettings();
                $invoiceService = app(InvoiceService::class);

                Log::info("⚠️ Starting daily overdue invoice check (landlord)", [
                    'timestamp'          => now()->setTimezone('UTC')->toISOString(),
                    'grace_period_days'  => $settings->grace_period_days,
                ]);

                $result = $invoiceService->markOverdueInvoices();

                Log::info("✅ Overdue invoice check (landlord) completed", [
                    'marked_overdue'      => $result['count'] ?? 0,
                    'penalties_applied'   => $result['penalty_applied_count'] ?? 0,
                    'total_overdue'       => $result['total_overdue'] ?? 0,
                    'timestamp'           => now()->setTimezone('UTC')->toISOString(),
                ]);

                if (($result['penalty_applied_count'] ?? 0) > 0) {
                    $this->notifyAdminsAboutPenaltiesApplied($result, 'landlord');
                }
            } catch (\Exception $e) {
                Log::error("❌ Failed to check overdue invoices (landlord): " . $e->getMessage());
            }
        })
        ->dailyAt('03:00')
        ->name('check-overdue-invoices')
        ->onOneServer();

        // ==================== OVERDUE TENANT INVOICES ====================

        $schedule->call(function () {
            try {
                $settings = SystemSetting::getSettings();

                if (!$settings->enable_tenant_invoicing) {
                    Log::info("⚠️ Tenant invoicing is disabled - skipping overdue tenant check");
                    return;
                }

                $tenantInvoiceService = app(TenantInvoiceService::class);

                Log::info("⚠️ Starting daily overdue tenant invoice check", [
                    'timestamp'               => now()->setTimezone('UTC')->toISOString(),
                    'grace_period_days'       => $settings->tenant_grace_period_days ?? 7,
                    'late_payment_percentage' => $settings->tenant_late_payment_percentage ?? 0,
                    'fixed_penalty'           => $settings->tenant_fixed_penalty_amount ?? 0,
                ]);

                $result = $tenantInvoiceService->markOverdueTenantInvoices();

                Log::info("✅ Overdue tenant invoice check completed", [
                    'marked_overdue'    => $result['count'] ?? 0,
                    'penalties_applied' => $result['penalty_applied_count'] ?? 0,
                    'timestamp'         => now()->setTimezone('UTC')->toISOString(),
                ]);

                if (($result['penalty_applied_count'] ?? 0) > 0) {
                    $this->notifyAdminsAboutTenantPenaltiesApplied($result);
                }
            } catch (\Exception $e) {
                Log::error("❌ Failed to check overdue tenant invoices: " . $e->getMessage());
            }
        })
        ->dailyAt('03:30')
        ->name('check-overdue-tenant-invoices')
        ->onOneServer();

        // ==================== WEEKLY SUMMARIES ====================

        $schedule->call(function () {
            try {
                $settings = SystemSetting::getSettings();
                $notificationService = app(NotificationService::class);

                Log::info("📊 Generating weekly invoice summaries (landlord)", [
                    'timestamp'          => now()->setTimezone('UTC')->toISOString(),
                    'reminders_enabled'  => $settings->shouldSendPaymentReminders(),
                ]);

                $summaryData = $this->generateWeeklySummary();
                $result = $notificationService->sendWeeklyInvoiceSummary($summaryData);

                Log::info("✅ Weekly invoice summaries (landlord) sent", [
                    'admin_count' => $result['admin_count'] ?? 0,
                    'timestamp'   => now()->setTimezone('UTC')->toISOString(),
                ]);
            } catch (\Exception $e) {
                Log::error("❌ Failed to send weekly summaries (landlord): " . $e->getMessage());
            }
        })
        ->weeklyOn(1, '09:00')
        ->name('send-weekly-summaries')
        ->onOneServer();

        $schedule->call(function () {
            try {
                $settings = SystemSetting::getSettings();

                if (!$settings->enable_tenant_invoicing) {
                    return;
                }

                Log::info("📊 Generating weekly tenant invoice summaries", [
                    'timestamp' => now()->setTimezone('UTC')->toISOString(),
                ]);

                $summaryData = $this->generateWeeklyTenantSummary();
                Log::info("✅ Weekly tenant invoice summary generated", $summaryData);
                $this->sendWeeklyTenantSummaryToAdmins($summaryData);
            } catch (\Exception $e) {
                Log::error("❌ Failed to generate weekly tenant summaries: " . $e->getMessage());
            }
        })
        ->weeklyOn(2, '09:30')
        ->name('send-weekly-tenant-summaries')
        ->onOneServer();

        // ==================== MONTHLY SUMMARIES ====================

        $schedule->call(function () {
            try {
                $settings = SystemSetting::getSettings();
                $invoiceService = app(InvoiceService::class);
                $currentMonth = now()->subMonth()->format('Y-m');

                Log::info("📊 Generating end-of-month invoice summary (landlord)", [
                    'month'     => $currentMonth,
                    'timestamp' => now()->setTimezone('UTC')->toISOString(),
                ]);

                $summary = $invoiceService->getGenerationSummary($currentMonth);

                Log::info("📈 End-of-month invoice summary (landlord)", [
                    'month'              => $currentMonth,
                    'total_properties'   => $summary['total_properties'] ?? 0,
                    'existing_invoices'  => $summary['existing_invoices'] ?? 0,
                    'collection_rate'    => $this->calculateCollectionRate($currentMonth),
                    'timestamp'          => now()->setTimezone('UTC')->toISOString(),
                ]);

                $this->sendMonthlyInvoiceSummaryToAdmins($summary, $currentMonth, 'landlord');
            } catch (\Exception $e) {
                Log::error("❌ Failed to generate monthly summary (landlord): " . $e->getMessage());
            }
        })
        ->monthlyOn(1, '05:00')
        ->name('monthly-invoice-summary')
        ->onOneServer();

        $schedule->call(function () {
            try {
                $settings = SystemSetting::getSettings();

                if (!$settings->enable_tenant_invoicing) {
                    return;
                }

                $tenantInvoiceService = app(TenantInvoiceService::class);
                $lastMonth = now()->subMonth()->format('Y-m');

                Log::info("📊 Generating end-of-month tenant invoice summary", [
                    'month'     => $lastMonth,
                    'timestamp' => now()->setTimezone('UTC')->toISOString(),
                ]);

                $statistics = $tenantInvoiceService->getTenantInvoiceStatistics();

                $lastMonthInvoices = TenantInvoice::where('period', $lastMonth)->get();
                $lastMonthPaid = $lastMonthInvoices->where('status', TenantInvoice::STATUS_PAID)->count();
                $lastMonthTotal = $lastMonthInvoices->count();
                $lastMonthCollectionRate = $lastMonthTotal > 0 ? round(($lastMonthPaid / $lastMonthTotal) * 100, 2) : 0;

                Log::info("📈 End-of-month tenant invoice summary", [
                    'month'                       => $lastMonth,
                    'total_tenant_invoices'       => $statistics['total_invoices'] ?? 0,
                    'paid_tenant_invoices'        => $statistics['paid_invoices'] ?? 0,
                    'pending_tenant_invoices'     => $statistics['pending_invoices'] ?? 0,
                    'overdue_tenant_invoices'     => $statistics['overdue_invoices'] ?? 0,
                    'last_month_invoices'         => $lastMonthTotal,
                    'last_month_paid'             => $lastMonthPaid,
                    'last_month_collection_rate'  => $lastMonthCollectionRate . '%',
                    'total_tenant_revenue'        => $settings->formatAmount($statistics['paid_amount'] ?? 0),
                    'total_tenant_penalties'      => $settings->formatAmount($statistics['total_penalties'] ?? 0),
                    'overall_collection_rate'     => $statistics['collection_rate'] . '%',
                    'timestamp'                   => now()->setTimezone('UTC')->toISOString(),
                ]);

                $this->sendMonthlyTenantSummaryToAdmins($statistics, $lastMonth);
            } catch (\Exception $e) {
                Log::error("❌ Failed to generate monthly tenant summary: " . $e->getMessage());
            }
        })
        ->monthlyOn(2, '05:30')
        ->name('monthly-tenant-invoice-summary')
        ->onOneServer();

        // ==================== INVOICE: MONTHLY PROPERTY-UNIT INVOICE SUMMARY ====================

        $schedule->call(function () {
            try {
                $lastMonth = now()->subMonth();
                $startOfLastMonth = $lastMonth->copy()->startOfMonth();
                $endOfLastMonth = $lastMonth->copy()->endOfMonth();

                $invoices = PropertyUnitInvoice::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->get();

                $summary = [
                    'month'          => $lastMonth->format('F Y'),
                    'total_invoices' => $invoices->count(),
                    'total_invoiced' => $invoices->sum('amount'),
                    'total_paid'     => $invoices->sum('amount_paid'),
                    'outstanding'    => $invoices->whereIn('status', [
                            PropertyUnitInvoice::STATUS_PENDING,
                            PropertyUnitInvoice::STATUS_PARTIAL,
                            PropertyUnitInvoice::STATUS_OVERDUE,
                        ])
                        ->sum(fn ($inv) => $inv->amount - $inv->amount_paid),
                    'paid_count'     => $invoices->where('status', PropertyUnitInvoice::STATUS_PAID)->count(),
                    'overdue_count'  => $invoices->where('status', PropertyUnitInvoice::STATUS_OVERDUE)->count(),
                    'voided_count'   => $invoices->where('status', PropertyUnitInvoice::STATUS_VOID)->count(),
                    'by_type' => [
                        'advance_rent'     => $invoices->where('invoice_type', PropertyUnitInvoice::TYPE_ADVANCE_RENT)->sum('amount'),
                        'monthly_rent'     => $invoices->where('invoice_type', PropertyUnitInvoice::TYPE_MONTHLY_RENT)->sum('amount'),
                        'security_deposit' => $invoices->where('invoice_type', PropertyUnitInvoice::TYPE_SECURITY_DEPOSIT)->sum('amount'),
                    ],
                ];

                Log::info("📊 Monthly Property Unit Invoice Summary", $summary);

                $admins = User::whereIn('type', [
                    User::TYPE_ADMIN,
                    User::TYPE_SUPER_ADMIN,
                ])->get();

                foreach ($admins as $admin) {
                    try {
                        $admin->notify(new \App\Notifications\GeneralNotification(
                            title: 'Monthly Property Unit Invoice Summary',
                            message: "{$summary['total_invoices']} invoices totaling GHS "
                                . number_format($summary['total_invoiced'], 2)
                                . " were created in {$summary['month']}. "
                                . "Collected: GHS " . number_format($summary['total_paid'], 2)
                                . " | Outstanding: GHS " . number_format($summary['outstanding'], 2),
                            icon: 'fas fa-file-invoice-dollar text-primary',
                            category: 'invoice_summary',
                            actionUrl: route('property-units.index'),
                            priority: 3,
                            data: $summary,
                        ));
                    } catch (\Exception $e) {
                        Log::warning('Failed to send monthly invoice summary to admin', [
                            'admin_id' => $admin->id,
                            'error'    => $e->getMessage(),
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error("Monthly Property Unit Invoice Summary failed: " . $e->getMessage());
            }
        })
        ->monthlyOn(3, '06:00')
        ->name('monthly-property-unit-invoice-summary')
        ->onOneServer()
        ->withoutOverlapping();

        // ==================== CONFIGURATION VALIDATION ====================

        $schedule->call(function () {
            $this->validateInvoiceConfiguration();
        })
        ->dailyAt('03:30')
        ->name('validate-invoice-config')
        ->onOneServer();

        $schedule->call(function () {
            $this->validateTenantInvoiceConfiguration();
        })
        ->dailyAt('04:00')
        ->name('validate-tenant-invoice-config')
        ->onOneServer();

        // ==================== LANDLORD INVITATION MANAGEMENT ====================

        $schedule->call(function () {
            $nowUtc = now()->setTimezone('UTC');

            Log::info("🏠 Starting landlord invitation cleanup procedure", [
                'current_time_utc' => $nowUtc->toISOString(),
                'app_timezone'     => config('app.timezone'),
            ]);

            try {
                $results = DB::select('CALL cleanup_expired_invitations()');

                Log::info("🏠 Landlord invitation cleanup completed successfully", [
                    'procedure_result' => $results[0]->result ?? 'Unknown',
                    'timestamp_utc'    => $nowUtc->toISOString(),
                ]);
            } catch (\Exception $e) {
                Log::error("🏠 Failed to run landlord invitation cleanup procedure: " . $e->getMessage(), [
                    'error'         => $e->getMessage(),
                    'timestamp_utc' => $nowUtc->toISOString(),
                ]);

                $this->fallbackLandlordInvitationCleanup();
            }
        })->dailyAt('02:00')->name('landlord-invitation-cleanup')->onOneServer();

        $schedule->call(function () {
            $nowUtc = now()->setTimezone('UTC');

            Log::info("📧 Starting landlord invitation expiration warnings", [
                'current_time_utc' => $nowUtc->toISOString(),
                'warning_days'     => 2,
            ]);

            $expiringSoon = LandlordInvitation::active()
                ->where('expires_at', '<=', $nowUtc->copy()->addDays(2)->toDateTimeString())
                ->where('expires_at', '>', $nowUtc->toDateTimeString())
                ->with(['landlord', 'property', 'inviter'])
                ->get();

            $sentCount = 0;
            $failedCount = 0;

            foreach ($expiringSoon as $invitation) {
                try {
                    if ($this->sendLandlordExpirationWarning($invitation)) {
                        $sentCount++;

                        Log::info("Landlord expiration warning sent", [
                            'invitation_id'      => $invitation->id,
                            'landlord_id'        => $invitation->landlord_id,
                            'property_id'        => $invitation->property_id,
                            'days_until_expiry'  => $invitation->days_until_expiration,
                            'expires_at'         => $invitation->expires_at->toISOString(),
                        ]);
                    } else {
                        $failedCount++;
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    Log::error("Failed to send landlord expiration warning: " . $e->getMessage(), [
                        'invitation_id' => $invitation->id,
                        'error'         => $e->getMessage(),
                    ]);
                }
            }

            Log::info("Landlord expiration warnings completed", [
                'total_expiring'  => $expiringSoon->count(),
                'warnings_sent'   => $sentCount,
                'warnings_failed' => $failedCount,
                'timestamp_utc'   => $nowUtc->toISOString(),
            ]);
        })->dailyAt('10:00')->name('landlord-expiration-warnings')->onOneServer();

        $schedule->call(function () {
            $this->sendLandlordInvitationReport();
        })->dailyAt('18:00')->name('landlord-invitation-report')->onOneServer();

        $schedule->call(function () {
            $this->landlordInvitationHealthCheck();
        })->hourly()->name('landlord-invitation-health-check')->withoutOverlapping();

        // ==================== AGENT INVITATION MANAGEMENT ====================

        $schedule->call(function () {
            $nowUtc = now()->setTimezone('UTC');

            $autoExpiryEnabled = config('app.invitation_auto_expiry', true);
            if (!$autoExpiryEnabled) {
                Log::info("🔄 Auto-expiry is disabled, skipping expiration check", [
                    'current_time_utc'    => $nowUtc->toISOString(),
                    'app_timezone'        => config('app.timezone'),
                    'auto_expiry_enabled' => $autoExpiryEnabled,
                ]);
                return;
            }

            Log::info("🔄 SAFE DAILY expiration check starting", [
                'current_time_utc'    => $nowUtc->toISOString(),
                'app_timezone'        => config('app.timezone'),
                'auto_expiry_enabled' => $autoExpiryEnabled,
                'expiry_days'         => config('app.invitation_expiry_days', 7),
            ]);

            $expiryDays = config('app.invitation_expiry_days', 7);
            $safeExpiredInvitations = AgentInvitation::where('status', AgentInvitation::STATUS_SENT)
                ->where('expires_at', '<', $nowUtc->toDateTimeString())
                ->where('created_at', '<', $nowUtc->copy()->subDays($expiryDays - 1)->toDateTimeString())
                ->get();

            $processedCount = 0;
            $failedCount = 0;

            foreach ($safeExpiredInvitations as $invitation) {
                try {
                    $hoursSinceCreation = $invitation->created_at->setTimezone('UTC')->diffInHours($nowUtc);
                    if ($hoursSinceCreation < 24) {
                        Log::critical('CRITICAL: Invitation too new to expire - SKIPPING', [
                            'invitation_id'      => $invitation->id,
                            'created_at_utc'     => $invitation->created_at->setTimezone('UTC')->toISOString(),
                            'expires_at_utc'     => $invitation->expires_at?->toISOString(),
                            'hours_old'          => $hoursSinceCreation,
                            'current_time_utc'   => $nowUtc->toISOString(),
                            'expiry_days'        => $expiryDays,
                        ]);
                        continue;
                    }

                    $validation = $invitation->validateExpiration();

                    if (!$validation['is_valid']) {
                        Log::warning('Skipping invitation with expiration issues', [
                            'invitation_id' => $invitation->id,
                            'issues'        => $validation['issues'],
                            'expiry_days'   => $expiryDays,
                        ]);
                        continue;
                    }

                    if ($invitation->markAsExpired()) {
                        $processedCount++;

                        Log::info("Invitation auto-expired via DAILY scheduler", [
                            'invitation_id'       => $invitation->id,
                            'plan_id'             => $invitation->plan_id,
                            'agent_id'            => $invitation->agent_id,
                            'created_at_utc'      => $invitation->created_at->setTimezone('UTC')->toISOString(),
                            'expires_at_utc'      => $invitation->expires_at?->toISOString(),
                            'days_since_creation' => $invitation->created_at->setTimezone('UTC')->diffInDays($nowUtc),
                            'expired_at_utc'      => $nowUtc->toISOString(),
                            'expiry_days'         => $expiryDays,
                            'auto_expiry_enabled' => $autoExpiryEnabled,
                        ]);
                    } else {
                        $failedCount++;
                        Log::error('Failed to mark invitation as expired', [
                            'invitation_id' => $invitation->id,
                            'expiry_days'   => $expiryDays,
                        ]);
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    Log::error("Error processing invitation expiration: " . $e->getMessage(), [
                        'invitation_id' => $invitation->id,
                        'error'         => $e->getMessage(),
                        'expiry_days'   => $expiryDays,
                    ]);
                }
            }

            Log::info("SAFE DAILY expiration check completed", [
                'total_found'         => $safeExpiredInvitations->count(),
                'processed'           => $processedCount,
                'failed'              => $failedCount,
                'timestamp_utc'       => $nowUtc->toISOString(),
                'expiry_days'         => $expiryDays,
                'auto_expiry_enabled' => $autoExpiryEnabled,
            ]);
        })->dailyAt('06:00')->name('safe-daily-expiration-check')->onOneServer();

        $schedule->call(function () {
            Log::info("📊 Starting READ-ONLY expiration analysis", [
                'expiry_days'         => config('app.invitation_expiry_days', 7),
                'warning_days'        => config('app.invitation_warning_days', 2),
                'auto_expiry_enabled' => config('app.invitation_auto_expiry', true),
            ]);

            $analysis = app(AgentInvitationService::class)->getExpirationAnalysis();

            if (isset($analysis['invalid_expirations']) && $analysis['invalid_expirations'] > 0) {
                Log::warning("❌ Found invitations with expiration issues (READ ONLY)", [
                    'invalid_count' => $analysis['invalid_expirations'],
                    'details'       => array_slice($analysis['details'] ?? [], 0, 3),
                    'expiry_days'   => config('app.invitation_expiry_days', 7),
                ]);
            }

            $problematicNewInvitations = AgentInvitation::where('status', AgentInvitation::STATUS_EXPIRED)
                ->where('created_at', '>', now()->setTimezone('UTC')->subDay()->toDateTimeString())
                ->get();

            if ($problematicNewInvitations->count() > 0) {
                Log::critical("🚨 NEW INVITATIONS MARKED AS EXPIRED - MANUAL INTERVENTION NEEDED", [
                    'count'       => $problematicNewInvitations->count(),
                    'expiry_days' => config('app.invitation_expiry_days', 7),
                    'examples'    => $problematicNewInvitations->take(3)->map(function ($inv) {
                        return [
                            'id'             => $inv->id,
                            'created_at_utc' => $inv->created_at->setTimezone('UTC')->toISOString(),
                            'expires_at_utc' => $inv->expires_at?->toISOString(),
                            'hours_old'      => $inv->created_at->setTimezone('UTC')->diffInHours(now()->setTimezone('UTC')),
                        ];
                    })->toArray(),
                ]);
            }

            Log::info("READ-ONLY expiration analysis completed", [
                'total_invitations'   => $analysis['total'] ?? 0,
                'valid_expirations'   => $analysis['valid_expirations'] ?? 0,
                'invalid_expirations' => $analysis['invalid_expirations'] ?? 0,
                'analysis_time_utc'   => now()->setTimezone('UTC')->toISOString(),
                'expiry_days'         => config('app.invitation_expiry_days', 7),
                'warning_days'        => config('app.invitation_warning_days', 2),
            ]);
        })->dailyAt('04:00')->name('read-only-expiration-analysis')->onOneServer();

        $schedule->call(function () {
            Log::info("📧 Starting expiration warnings", [
                'warning_days' => config('app.invitation_warning_days', 2),
                'expiry_days'  => config('app.invitation_expiry_days', 7),
            ]);

            $warningDays = config('app.invitation_warning_days', 2);
            $expiryDays  = config('app.invitation_expiry_days', 7);
            $minAgeDays  = $expiryDays - $warningDays;
            $cutoffDate  = now()->setTimezone('UTC')->subDays($minAgeDays);

            $expiringSoon = AgentInvitation::where('status', AgentInvitation::STATUS_SENT)
                ->where('expires_at', '<=', now()->setTimezone('UTC')->addDays($warningDays)->toDateTimeString())
                ->where('expires_at', '>', now()->setTimezone('UTC')->toDateTimeString())
                ->where('created_at', '<', $cutoffDate->toDateTimeString())
                ->get();

            $sentCount = 0;
            $failedCount = 0;

            foreach ($expiringSoon as $invitation) {
                try {
                    $validation = $invitation->validateExpiration();
                    if (!$validation['is_valid']) {
                        Log::warning('Skipping expiration warning for invalid invitation', [
                            'invitation_id' => $invitation->id,
                            'issues'        => $validation['issues'],
                            'warning_days'  => $warningDays,
                        ]);
                        $failedCount++;
                        continue;
                    }

                    $agentService = app(AgentInvitationService::class);
                    if (method_exists($agentService, 'sendExpirationWarning')) {
                        $agentService->sendExpirationWarning($invitation);
                    } else {
                        $this->sendExpirationWarning($invitation);
                    }
                    $sentCount++;

                    Log::info("Expiration warning sent via scheduler", [
                        'invitation_id'     => $invitation->id,
                        'agent_id'          => $invitation->agent_id,
                        'days_until_expiry' => $invitation->getDaysUntilExpiry(),
                        'warning_days'      => $warningDays,
                        'expiry_days'       => $expiryDays,
                    ]);
                } catch (\Exception $e) {
                    $failedCount++;
                    Log::error("Failed to send expiration warning via scheduler: " . $e->getMessage(), [
                        'invitation_id' => $invitation->id,
                        'error'         => $e->getMessage(),
                        'warning_days'  => $warningDays,
                    ]);
                }
            }

            Log::info("Expiration warnings completed", [
                'total_expiring'  => $expiringSoon->count(),
                'warnings_sent'   => $sentCount,
                'warnings_failed' => $failedCount,
                'timestamp_utc'   => now()->setTimezone('UTC')->toISOString(),
                'warning_days'    => $warningDays,
                'expiry_days'     => $expiryDays,
            ]);
        })->dailyAt('09:00')->name('send-expiration-warnings')->onOneServer();

        $schedule->call(function () {
            $nowUtc = now()->setTimezone('UTC');

            $prematurelyExpired = AgentInvitation::where('status', AgentInvitation::STATUS_EXPIRED)
                ->where('created_at', '>', $nowUtc->copy()->subHours(24)->toDateTimeString())
                ->where('expires_at', '>', $nowUtc->toDateTimeString())
                ->get();

            if ($prematurelyExpired->count() > 0) {
                Log::warning("⚠️  Found invitations that were prematurely expired", [
                    'count'       => $prematurelyExpired->count(),
                    'expiry_days' => config('app.invitation_expiry_days', 7),
                    'examples'    => $prematurelyExpired->take(2)->map(function ($inv) use ($nowUtc) {
                        return [
                            'id'                => $inv->id,
                            'created_at_utc'    => $inv->created_at->setTimezone('UTC')->toISOString(),
                            'expires_at_utc'    => $inv->expires_at?->toISOString(),
                            'hours_old'         => $inv->created_at->setTimezone('UTC')->diffInHours($nowUtc),
                            'should_be_expired' => $inv->expires_at?->lt($nowUtc) ? 'YES' : 'NO',
                        ];
                    })->toArray(),
                ]);
            }

            $totalActive     = AgentInvitation::active()->count();
            $totalExpired    = AgentInvitation::expired()->count();
            $recentlyCreated = AgentInvitation::where('created_at', '>=', $nowUtc->copy()->subHours(1)->toDateTimeString())->count();

            Log::debug("Hourly invitation health check", [
                'total_active'         => $totalActive,
                'total_expired'        => $totalExpired,
                'recently_created'     => $recentlyCreated,
                'prematurely_expired'  => $prematurelyExpired->count(),
                'check_time_utc'       => $nowUtc->toISOString(),
                'expiry_days'          => config('app.invitation_expiry_days', 7),
                'warning_days'         => config('app.invitation_warning_days', 2),
                'auto_expiry_enabled'  => config('app.invitation_auto_expiry', true),
            ]);
        })->hourly()->name('safe-hourly-monitor')->withoutOverlapping();

        $schedule->call(function () {
            $cutoffUtc = now()->setTimezone('UTC')->subDays(30);
            $oldInvitations = AgentInvitation::onlyTrashed()
                ->where('deleted_at', '<', $cutoffUtc->toDateTimeString())
                ->orWhere(function ($query) use ($cutoffUtc) {
                    $query->whereIn('status', [AgentInvitation::STATUS_EXPIRED, AgentInvitation::STATUS_REVOKED])
                          ->where('updated_at', '<', $cutoffUtc->toDateTimeString());
                })
                ->get();

            $deletedCount = 0;
            $failedCount = 0;

            foreach ($oldInvitations as $invitation) {
                try {
                    if (!$invitation->isAccepted()) {
                        $invitation->forceDelete();
                        $deletedCount++;
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    Log::warning("Could not clean up invitation {$invitation->id}: " . $e->getMessage(), [
                        'expiry_days' => config('app.invitation_expiry_days', 7),
                    ]);
                }
            }

            Log::info("Old invitations cleanup completed", [
                'deleted_count' => $deletedCount,
                'failed_count'  => $failedCount,
                'total_found'   => $oldInvitations->count(),
                'cutoff_utc'    => $cutoffUtc->toISOString(),
                'timestamp_utc' => now()->setTimezone('UTC')->toISOString(),
                'expiry_days'   => config('app.invitation_expiry_days', 7),
            ]);
        })->weekly()->name('cleanup-old-invitations')->onOneServer();

        $schedule->call(function () {
            $this->sendDailyInvitationReport();
        })->dailyAt('17:00')->name('send-invitation-reports')->onOneServer();

        $schedule->call(function () {
            $this->validateInvitationConfiguration();
        })->dailyAt('03:00')->name('validate-invitation-config')->onOneServer();

        // ==================== ACCOUNT ARCHIVAL SYSTEM SCHEDULED TASKS ====================

        $schedule->command('accounts:process-archival')
            ->dailyAt('02:00')
            ->name('process-account-archival')
            ->onOneServer()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/account-archival.log'));

        $schedule->call(function () {
            try {
                $settings = SystemSetting::getSettings();

                if (!($settings->account_archival['enabled'] ?? true)) {
                    Log::info("📧 Account archival is disabled - skipping archival warnings", [
                        'timestamp' => now()->setTimezone('UTC')->toISOString(),
                    ]);
                    return;
                }

                $warningDays = $settings->account_archival['warning_days'] ?? [30, 14, 7, 3, 1];
                $archiveDelayDays = $settings->account_archival['archive_delay_days'] ?? 30;

                Log::info("📧 Checking for landlords needing archival warnings", [
                    'warning_days'       => $warningDays,
                    'archive_delay_days' => $archiveDelayDays,
                    'timestamp'          => now()->setTimezone('UTC')->toISOString(),
                ]);

                $landlordsToWarn = User::where('type', User::TYPE_LANDLORD)
                    ->whereNotNull('deletion_scheduled_at')
                    ->where('deletion_scheduled_at', '>', now())
                    ->where('status', '!=', User::STATUS_ARCHIVED)
                    ->get();

                $warningsSent = 0;
                $warningsFailed = 0;

                foreach ($landlordsToWarn as $landlord) {
                    $daysUntilArchive = now()->diffInDays($landlord->deletion_scheduled_at);

                    if (in_array($daysUntilArchive, $warningDays)) {
                        try {
                            $this->sendArchivalWarningNotification($landlord, $daysUntilArchive);
                            $warningsSent++;

                            Log::info("Archival warning sent to landlord", [
                                'landlord_id'        => $landlord->id,
                                'days_until_archive' => $daysUntilArchive,
                                'scheduled_date'     => $landlord->deletion_scheduled_at->toISOString(),
                            ]);

                            $metadata = $landlord->metadata ?? [];
                            $metadata['archival_warnings_sent'] = $metadata['archival_warnings_sent'] ?? [];
                            $metadata['archival_warnings_sent'][] = [
                                'sent_at'            => now()->toISOString(),
                                'days_until_archive' => $daysUntilArchive,
                            ];
                            $landlord->update(['metadata' => $metadata]);
                        } catch (\Exception $e) {
                            $warningsFailed++;
                            Log::error("Failed to send archival warning to landlord", [
                                'landlord_id' => $landlord->id,
                                'error'       => $e->getMessage(),
                            ]);
                        }
                    }
                }

                Log::info("📧 Archival warnings completed", [
                    'warnings_sent'           => $warningsSent,
                    'warnings_failed'         => $warningsFailed,
                    'total_landlords_checked' => $landlordsToWarn->count(),
                    'timestamp'               => now()->setTimezone('UTC')->toISOString(),
                ]);
            } catch (\Exception $e) {
                Log::error("❌ Failed to send archival warnings: " . $e->getMessage());
            }
        })
        ->dailyAt('09:00')
        ->name('send-archival-warnings')
        ->onOneServer()
        ->withoutOverlapping();

        $schedule->call(function () {
            try {
                $settings = SystemSetting::getSettings();

                if (!($settings->account_archival['enabled'] ?? true)) {
                    return;
                }

                $permanentDeletionDays = $settings->account_archival['permanent_deletion_days'] ?? 365;

                if ($permanentDeletionDays <= 0) {
                    return;
                }

                Log::info("⚠️ Checking for accounts scheduled for permanent deletion", [
                    'permanent_deletion_days' => $permanentDeletionDays,
                    'timestamp'               => now()->setTimezone('UTC')->toISOString(),
                ]);

                $accountsToWarn = User::where('status', User::STATUS_ARCHIVED)
                    ->whereNotNull('deletion_scheduled_at')
                    ->where('deletion_scheduled_at', '<=', now()->addDays(7))
                    ->where('deletion_scheduled_at', '>', now())
                    ->get();

                $warningsSent = 0;
                $warningsFailed = 0;

                foreach ($accountsToWarn as $account) {
                    $daysUntilDeletion = now()->diffInDays($account->deletion_scheduled_at);

                    if ($daysUntilDeletion <= 7) {
                        try {
                            $this->sendFinalDeletionWarning($account, $daysUntilDeletion);
                            $warningsSent++;

                            Log::info("Final deletion warning sent", [
                                'user_id'             => $account->id,
                                'days_until_deletion' => $daysUntilDeletion,
                                'scheduled_date'      => $account->deletion_scheduled_at->toISOString(),
                            ]);
                        } catch (\Exception $e) {
                            $warningsFailed++;
                            Log::error("Failed to send final deletion warning", [
                                'user_id' => $account->id,
                                'error'   => $e->getMessage(),
                            ]);
                        }
                    }
                }

                Log::info("⚠️ Final deletion warnings completed", [
                    'warnings_sent'   => $warningsSent,
                    'warnings_failed' => $warningsFailed,
                    'timestamp'       => now()->setTimezone('UTC')->toISOString(),
                ]);
            } catch (\Exception $e) {
                Log::error("❌ Failed to send final deletion warnings: " . $e->getMessage());
            }
        })
        ->dailyAt('10:00')
        ->name('send-final-deletion-warnings')
        ->onOneServer()
        ->withoutOverlapping();

        $schedule->command('accounts:cleanup-archived --older-than=365')
            ->dailyAt('03:00')
            ->name('cleanup-archived-accounts')
            ->onOneServer()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/archived-accounts-cleanup.log'));

        $schedule->call(function () {
            try {
                $settings = SystemSetting::getSettings();

                if (!($settings->account_archival['admin_notifications']['enabled'] ?? true)) {
                    return;
                }

                Log::info("📊 Generating weekly archival summary report", [
                    'timestamp' => now()->setTimezone('UTC')->toISOString(),
                ]);

                $archivedCount = User::where('status', User::STATUS_ARCHIVED)->count();
                $scheduledForArchival = User::where('type', User::TYPE_LANDLORD)
                    ->whereNotNull('deletion_scheduled_at')
                    ->where('deletion_scheduled_at', '>', now())
                    ->count();
                $restoredThisWeek = User::where('metadata->restored->restored_at', '>=', now()->startOfWeek()->toISOString())->count();
                $archivedThisWeek = User::where('archived_at', '>=', now()->startOfWeek())->count();

                $report = [
                    'total_archived'          => $archivedCount,
                    'scheduled_for_archival'  => $scheduledForArchival,
                    'archived_this_week'      => $archivedThisWeek,
                    'restored_this_week'      => $restoredThisWeek,
                    'week_start'              => now()->startOfWeek()->format('Y-m-d'),
                    'week_end'                => now()->endOfWeek()->format('Y-m-d'),
                    'report_time'             => now()->setTimezone('UTC')->toISOString(),
                ];

                Log::info("📊 Weekly archival summary", $report);
            } catch (\Exception $e) {
                Log::error("❌ Failed to generate weekly archival summary: " . $e->getMessage());
            }
        })
        ->weekly()
        ->mondays()
        ->at('07:00')
        ->name('weekly-archival-summary')
        ->onOneServer();

        $schedule->call(function () {
            try {
                $settings = SystemSetting::getSettings();

                if (!($settings->account_archival['admin_notifications']['enabled'] ?? true)) {
                    return;
                }

                $lastMonth = now()->subMonth();
                $startOfLastMonth = $lastMonth->copy()->startOfMonth();
                $endOfLastMonth = $lastMonth->copy()->endOfMonth();

                Log::info("📊 Generating monthly archival statistics", [
                    'month'     => $lastMonth->format('F Y'),
                    'timestamp' => now()->setTimezone('UTC')->toISOString(),
                ]);

                $archivedLastMonth = User::where('status', User::STATUS_ARCHIVED)
                    ->whereBetween('archived_at', [$startOfLastMonth, $endOfLastMonth])
                    ->count();

                $restoredLastMonth = User::where('metadata->restored->restored_at', '>=', $startOfLastMonth->toISOString())
                    ->where('metadata->restored->restored_at', '<=', $endOfLastMonth->toISOString())
                    ->count();

                $scheduledLastMonth = User::where('type', User::TYPE_LANDLORD)
                    ->whereBetween('deletion_scheduled_at', [$startOfLastMonth, $endOfLastMonth])
                    ->count();

                $stats = [
                    'month'                    => $lastMonth->format('F Y'),
                    'archived_last_month'      => $archivedLastMonth,
                    'restored_last_month'      => $restoredLastMonth,
                    'scheduled_last_month'     => $scheduledLastMonth,
                    'total_archived'           => User::where('status', User::STATUS_ARCHIVED)->count(),
                    'total_former_landlords'   => User::where('type', User::TYPE_FORMER_LANDLORD)->count(),
                    'avg_archival_age_days'    => User::where('status', User::STATUS_ARCHIVED)
                        ->whereNotNull('archived_at')
                        ->avg(\DB::raw('DATEDIFF(NOW(), archived_at)')) ?? 0,
                    'report_time'              => now()->setTimezone('UTC')->toISOString(),
                ];

                Log::info("📊 Monthly archival statistics", $stats);
            } catch (\Exception $e) {
                Log::error("❌ Failed to generate monthly archival statistics: " . $e->getMessage());
            }
        })
        ->monthlyOn(1, '06:00')
        ->name('monthly-archival-stats')
        ->onOneServer();
    }

    /* ============================================================
     | HELPERS
     | ============================================================ */

    /**
     * Ghana: Ordinal suffix for a day number (1st, 2nd, 3rd, 4th...).
     */
    private function getDaySuffix(int $day): string
    {
        if ($day >= 11 && $day <= 13) return 'th';
        switch ($day % 10) {
            case 1: return 'st';
            case 2: return 'nd';
            case 3: return 'rd';
            default: return 'th';
        }
    }

    private function sendYearEndArchiveSummaryLandlord(array $results): void
    {
        try {
            Log::info("Landlord year-end archive summary", [
                'year'          => $results['year'] ?? null,
                'total_invoices'=> $results['total_invoices'] ?? 0,
                'paid_archived' => $results['paid_archived'] ?? 0,
                'unpaid_kept'   => $results['unpaid_kept'] ?? 0,
                'errors'        => $results['errors'] ?? 0,
                'timestamp'     => now()->setTimezone('UTC')->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send landlord year-end archive summary: " . $e->getMessage());
        }
    }

    private function sendYearEndArchiveSummaryTenant(array $results): void
    {
        try {
            Log::info("Tenant year-end archive summary", [
                'year'           => $results['year'] ?? null,
                'total_invoices' => $results['total_invoices'] ?? 0,
                'paid_archived'  => $results['paid_archived'] ?? 0,
                'unpaid_kept'    => $results['unpaid_kept'] ?? 0,
                'errors'         => $results['errors'] ?? 0,
                'timestamp'      => now()->setTimezone('UTC')->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send tenant year-end archive summary: " . $e->getMessage());
        }
    }

    private function generateWeeklySummary(): array
    {
        try {
            $startOfWeek = now()->startOfWeek();
            $endOfWeek = now()->endOfWeek();

            $generatedThisWeek = Invoice::whereBetween('created_at', [$startOfWeek, $endOfWeek])->count();
            $paidThisWeek = Invoice::whereBetween('payment_date', [$startOfWeek, $endOfWeek])
                ->where('status', Invoice::STATUS_PAID)
                ->count();

            $totalDue = Invoice::whereIn('status', [Invoice::STATUS_PENDING, Invoice::STATUS_OVERDUE])->sum('total_amount');
            $pendingCount = Invoice::where('status', Invoice::STATUS_PENDING)->count();
            $overdueCount = Invoice::where('status', Invoice::STATUS_OVERDUE)->count();
            $processingCount = Invoice::where('status', Invoice::STATUS_PROCESSING)->count();

            $settings = SystemSetting::getSettings();

            return [
                'generated_this_week' => $generatedThisWeek,
                'paid_this_week'      => $paidThisWeek,
                'pending_count'       => $pendingCount,
                'overdue_count'       => $overdueCount,
                'processing_count'    => $processingCount,
                'total_due'           => $settings->formatAmount($totalDue),
                'week_start'          => $startOfWeek->format('Y-m-d'),
                'week_end'            => $endOfWeek->format('Y-m-d'),
                'reminders_enabled'   => $settings->shouldSendPaymentReminders(),
                'reminder_days'       => $settings->getReminderDaysBefore(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate weekly summary: ' . $e->getMessage());
            return [];
        }
    }

    private function generateWeeklyTenantSummary(): array
    {
        try {
            $settings = SystemSetting::getSettings();
            $startOfWeek = now()->startOfWeek();
            $endOfWeek = now()->endOfWeek();

            $generatedThisWeek = TenantInvoice::whereBetween('created_at', [$startOfWeek, $endOfWeek])->count();
            $paidThisWeek = TenantInvoice::whereBetween('payment_date', [$startOfWeek, $endOfWeek])
                ->where('status', TenantInvoice::STATUS_PAID)
                ->count();

            $totalDue = TenantInvoice::whereIn('status', [TenantInvoice::STATUS_PENDING, TenantInvoice::STATUS_OVERDUE])
                ->sum(\DB::raw('total_amount + COALESCE(penalty_amount, 0)'));
            $pendingCount = TenantInvoice::where('status', TenantInvoice::STATUS_PENDING)->count();
            $overdueCount = TenantInvoice::where('status', TenantInvoice::STATUS_OVERDUE)->count();
            $totalPenalties = TenantInvoice::sum('penalty_amount');

            return [
                'generated_this_week' => $generatedThisWeek,
                'paid_this_week'      => $paidThisWeek,
                'pending_count'       => $pendingCount,
                'overdue_count'       => $overdueCount,
                'total_due'           => $settings->formatAmount($totalDue),
                'total_penalties'     => $settings->formatAmount($totalPenalties),
                'week_start'          => $startOfWeek->format('Y-m-d'),
                'week_end'            => $endOfWeek->format('Y-m-d'),
                'reminders_enabled'   => $settings->send_tenant_payment_reminders,
                'grace_period_days'   => $settings->tenant_grace_period_days ?? 7,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate weekly tenant summary: ' . $e->getMessage());
            return [];
        }
    }

    private function sendWeeklyTenantSummaryToAdmins(array $summary): void
    {
        try {
            Log::info("Weekly tenant summary generated", $summary);
        } catch (\Exception $e) {
            Log::error("Failed to send weekly tenant summary: " . $e->getMessage());
        }
    }

    private function calculateCollectionRate(string $month): float
    {
        try {
            $totalInvoices = Invoice::where('period', $month)->count();
            $paidInvoices = Invoice::where('period', $month)
                ->where('status', Invoice::STATUS_PAID)
                ->count();

            if ($totalInvoices === 0) {
                return 0;
            }

            return round(($paidInvoices / $totalInvoices) * 100, 2);
        } catch (\Exception $e) {
            Log::error("Failed to calculate collection rate: " . $e->getMessage());
            return 0;
        }
    }

    private function validateInvoiceConfiguration(): void
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

            if (empty($issues)) {
                Log::info("✅ Invoice configuration validation passed", $config);
            } else {
                Log::warning("❌ Invoice configuration validation failed", [
                    'issues'         => $issues,
                    'current_config' => $config,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Failed to validate invoice configuration: " . $e->getMessage());
        }
    }

    private function validateTenantInvoiceConfiguration(): void
    {
        try {
            $settings = SystemSetting::getSettings();

            $config = [
                'enable_tenant_invoicing'                    => $settings->enable_tenant_invoicing ?? false,
                'auto_generate_tenant_invoices'              => $settings->auto_generate_tenant_invoices ?? true,
                'send_tenant_payment_reminders'              => $settings->send_tenant_payment_reminders ?? false,
                'tenant_calculation_method'                  => $settings->tenant_calculation_method ?? 'fixed',
                'tenant_monthly_dues_amount'                 => $settings->tenant_monthly_dues_amount ?? 0,
                'tenant_grace_period_days'                   => $settings->tenant_grace_period_days ?? 7,
                'tenant_late_payment_percentage'             => $settings->tenant_late_payment_percentage ?? 0,
                'tenant_fixed_penalty_amount'                => $settings->tenant_fixed_penalty_amount ?? 0,
                'enable_year_end_archive_tenant'             => $settings->enable_year_end_archive_tenant ?? true,
                'auto_archive_paid_after_retention_tenant'   => $settings->auto_archive_paid_after_retention_tenant ?? true,
                'paid_invoice_retention_months_tenant'       => $settings->paid_invoice_retention_months_tenant ?? 3,
            ];

            $issues = [];

            if ($config['enable_tenant_invoicing']) {
                $activeTenantsCount = User::where('type', User::TYPE_TENANT)
                    ->whereHas('rentals', function ($q) {
                        $q->where('status', 'active');
                    })
                    ->count();

                if ($activeTenantsCount === 0) {
                    $issues[] = "Tenant invoicing is enabled but no active tenants found";
                }

                if ($config['tenant_calculation_method'] === 'fixed' && $config['tenant_monthly_dues_amount'] <= 0) {
                    $issues[] = "tenant_monthly_dues_amount must be greater than 0 for fixed calculation method";
                }

                if ($config['tenant_grace_period_days'] < 0 || $config['tenant_grace_period_days'] > 30) {
                    $issues[] = "tenant_grace_period_days should be between 0 and 30";
                }

                $hasPercentage = $config['tenant_late_payment_percentage'] > 0;
                $hasFixed = $config['tenant_fixed_penalty_amount'] > 0;

                if ($hasPercentage && $hasFixed) {
                    $issues[] = "Both percentage and fixed penalty are set. Only one should be used.";
                }

                if ($config['auto_archive_paid_after_retention_tenant'] && $config['paid_invoice_retention_months_tenant'] < 1) {
                    $issues[] = "paid_invoice_retention_months_tenant must be at least 1 when auto-archiving is enabled";
                }

                if ($config['send_tenant_payment_reminders'] && $config['tenant_grace_period_days'] < 3) {
                    $issues[] = "Reminders enabled but grace period is very short";
                }
            }

            if (empty($issues)) {
                Log::info("✅ Tenant invoice configuration validation passed", $config);
            } else {
                Log::warning("❌ Tenant invoice configuration validation failed", [
                    'issues'         => $issues,
                    'current_config' => $config,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Failed to validate tenant invoice configuration: " . $e->getMessage());
        }
    }

    private function notifyAdminAboutInvoiceGeneration(array $result, string $type = 'landlord'): void
    {
        try {
            $typeLabel = $type === 'landlord' ? 'Landlord' : 'Tenant';
            Log::info("Admin notification: Monthly {$typeLabel} invoices generated", [
                'period'   => $result['period'] ?? null,
                'due_date' => $result['due_date'] ?? null,
                'details'  => $result['details'] ?? [],
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send admin notification: " . $e->getMessage());
        }
    }

    private function notifyAdminAboutTenantInvoiceGeneration(array $result): void
    {
        try {
            Log::info("Admin tenant notification: Monthly Tenant invoices generated", [
                'generated_count' => $result['generated_count'] ?? 0,
                'skipped_count'   => $result['skipped_count'] ?? 0,
                'errors'          => $result['errors'] ?? [],
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send tenant admin notification: " . $e->getMessage());
        }
    }

    private function notifyAdminAboutInvoiceGenerationFailure(array $result, string $type = 'landlord'): void
    {
        try {
            Log::error("Admin alert: Monthly {$type} invoice generation FAILED", [
                'message'   => $result['message'] ?? 'Unknown',
                'timestamp' => now()->setTimezone('UTC')->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send failure notification: " . $e->getMessage());
        }
    }

    private function notifyAdminAboutTenantInvoiceGenerationFailure(array $result): void
    {
        try {
            Log::error("Admin tenant alert: Monthly Tenant invoice generation FAILED", [
                'message'   => $result['message'] ?? 'Unknown',
                'timestamp' => now()->setTimezone('UTC')->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send tenant failure notification: " . $e->getMessage());
        }
    }

    private function notifyAdminsAboutReadinessIssues(array $issues, string $type = 'landlord'): void
    {
        try {
            Log::warning("Admin alert: System not ready for auto {$type} invoice generation", [
                'issues'    => $issues,
                'timestamp' => now()->setTimezone('UTC')->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send readiness notification: " . $e->getMessage());
        }
    }

    private function notifyAdminsAboutPenaltiesApplied(array $result, string $type = 'landlord'): void
    {
        try {
            Log::info("Admin notification: Penalties applied to overdue {$type} invoices", [
                'penalty_applied_count' => $result['penalty_applied_count'] ?? 0,
                'total_overdue'         => $result['total_overdue'] ?? 0,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send penalties notification: " . $e->getMessage());
        }
    }

    private function notifyAdminsAboutTenantPenaltiesApplied(array $result): void
    {
        try {
            $settings = SystemSetting::getSettings();

            Log::info("Admin tenant notification: Penalties applied to overdue Tenant invoices", [
                'penalty_applied_count' => $result['penalty_applied_count'] ?? 0,
                'late_payment_percentage' => $settings->tenant_late_payment_percentage,
                'fixed_penalty_amount'  => $settings->tenant_fixed_penalty_amount,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send tenant penalties notification: " . $e->getMessage());
        }
    }

    private function sendCriticalFailureAlert(\Exception $e, string $type = 'landlord'): void
    {
        try {
            Log::critical("CRITICAL: {$type} invoice generation system failure", [
                'error'     => $e->getMessage(),
                'file'      => $e->getFile() . ':' . $e->getLine(),
                'timestamp' => now()->setTimezone('UTC')->toISOString(),
            ]);
        } catch (\Exception $notificationException) {
            Log::error("Failed to send critical alert: " . $notificationException->getMessage());
        }
    }

    private function sendMonthlyInvoiceSummaryToAdmins(array $summary, string $month, string $type = 'landlord'): void
    {
        try {
            $settings = SystemSetting::getSettings();
            $collectionRate = $this->calculateCollectionRate($month);

            Log::info("Monthly {$type} invoice summary", [
                'month'              => $month,
                'total_properties'   => $summary['total_properties'] ?? 0,
                'existing_invoices'  => $summary['existing_invoices'] ?? 0,
                'collection_rate'    => $collectionRate,
                'reminders_enabled'  => $settings->shouldSendPaymentReminders(),
                'auto_generation'    => $settings->isAutoInvoiceGenerationEnabled(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send monthly summary: " . $e->getMessage());
        }
    }

    private function sendMonthlyTenantSummaryToAdmins(array $statistics, string $month): void
    {
        try {
            $settings = SystemSetting::getSettings();

            Log::info("Monthly tenant invoice summary", [
                'month'                     => $month,
                'total_invoices'            => $statistics['total_invoices'] ?? 0,
                'paid_invoices'             => $statistics['paid_invoices'] ?? 0,
                'pending_invoices'          => $statistics['pending_invoices'] ?? 0,
                'overdue_invoices'          => $statistics['overdue_invoices'] ?? 0,
                'total_amount'              => $settings->formatAmount($statistics['total_amount'] ?? 0),
                'paid_amount'               => $settings->formatAmount($statistics['paid_amount'] ?? 0),
                'total_penalties'           => $settings->formatAmount($statistics['total_penalties'] ?? 0),
                'collection_rate'           => $statistics['collection_rate'] ?? 0,
                'tenant_invoicing_enabled'  => $statistics['tenant_invoicing_enabled'] ?? false,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send monthly tenant summary: " . $e->getMessage());
        }
    }

    private function fallbackLandlordInvitationCleanup(): void
    {
        try {
            $nowUtc = now()->setTimezone('UTC');
            $results = LandlordInvitation::cleanupExpired();

            Log::info("🏠 Fallback landlord invitation cleanup completed", [
                'processed'     => $results['processed'] ?? 0,
                'successful'    => $results['successful'] ?? 0,
                'failed'        => $results['failed'] ?? 0,
                'timestamp_utc' => $nowUtc->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error("🏠 Fallback landlord invitation cleanup failed: " . $e->getMessage());
        }
    }

    private function sendLandlordExpirationWarning(LandlordInvitation $invitation): bool
    {
        try {
            $landlord = $invitation->landlord;
            $property = $invitation->property;
            $daysLeft = $invitation->days_until_expiration;

            Log::info("LANDLORD EXPIRATION WARNING", [
                'invitation_id'     => $invitation->id,
                'landlord_id'       => $landlord->id,
                'property_id'       => $property->id,
                'days_until_expiry' => $daysLeft,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send landlord expiration warning: " . $e->getMessage(), [
                'invitation_id' => $invitation->id,
            ]);
            return false;
        }
    }

    private function sendLandlordInvitationReport(): void
    {
        try {
            $todayUtc = now()->setTimezone('UTC')->toDateString();
            $stats = LandlordInvitation::getDashboardStats();

            Log::info("🏠 Daily Landlord Invitation Report", [
                'date_utc'                        => $todayUtc,
                'total_landlord_invitations'      => $stats['total'] ?? 0,
                'pending_landlord_invitations'    => $stats['pending'] ?? 0,
                'sent_landlord_invitations'       => $stats['sent'] ?? 0,
                'accepted_landlord_invitations'   => $stats['accepted'] ?? 0,
                'expired_landlord_invitations'    => $stats['expired'] ?? 0,
                'failed_landlord_invitations'     => $stats['failed'] ?? 0,
                'cancelled_landlord_invitations'  => $stats['cancelled'] ?? 0,
                'acceptance_rate'                 => $stats['acceptance_rate'] ?? 0,
                'success_rate'                    => $stats['success_rate'] ?? 0,
                'report_time_utc'                 => now()->setTimezone('UTC')->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to generate landlord invitation report: " . $e->getMessage());
        }
    }

    private function landlordInvitationHealthCheck(): void
    {
        try {
            $nowUtc = now()->setTimezone('UTC');

            $expiringSoon = LandlordInvitation::active()
                ->where('expires_at', '<=', $nowUtc->copy()->addHours(24)->toDateTimeString())
                ->count();

            $stuckInvitations = LandlordInvitation::sent()
                ->where('sent_at', '<', $nowUtc->copy()->subDays(3)->toDateTimeString())
                ->where('accepted_at', null)
                ->count();

            $invalidTokens = LandlordInvitation::whereNull('token')->count();

            $healthStats = [
                'expiring_soon_24h'      => $expiringSoon,
                'stuck_invitations_3d'   => $stuckInvitations,
                'invalid_tokens'         => $invalidTokens,
                'check_time_utc'         => $nowUtc->toISOString(),
            ];

            if ($expiringSoon > 0 || $stuckInvitations > 0 || $invalidTokens > 0) {
                Log::warning("🏠 Landlord Invitation Health Check - Issues Found", $healthStats);
            } else {
                Log::debug("🏠 Landlord Invitation Health Check - All Good", $healthStats);
            }
        } catch (\Exception $e) {
            Log::error("Landlord invitation health check failed: " . $e->getMessage());
        }
    }

    private function sendExpirationWarning(AgentInvitation $invitation): bool
    {
        try {
            $daysLeft = $invitation->getDaysUntilExpiry();
            $agent = $invitation->agent;
            $plan = $invitation->plan;

            Log::info("Expiration warning would be sent", [
                'invitation_id'     => $invitation->id,
                'agent_id'          => $agent->id,
                'days_until_expiry' => $daysLeft,
                'expiry_days'       => config('app.invitation_expiry_days', 7),
                'warning_days'      => config('app.invitation_warning_days', 2),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send expiration warning: " . $e->getMessage(), [
                'invitation_id' => $invitation->id,
                'expiry_days'   => config('app.invitation_expiry_days', 7),
            ]);
            return false;
        }
    }

    private function sendDailyInvitationReport(): void
    {
        try {
            $todayUtc = now()->setTimezone('UTC')->toDateString();
            $nowUtc = now()->setTimezone('UTC');

            $stats = [
                'date_utc'                        => $todayUtc,
                'total_invitations'               => AgentInvitation::count(),
                'active_invitations'              => AgentInvitation::active()->count(),
                'accepted_today'                  => AgentInvitation::accepted()
                    ->whereDate('accepted_at', $todayUtc)
                    ->count(),
                'expired_today'                   => AgentInvitation::expired()
                    ->whereDate('updated_at', $todayUtc)
                    ->count(),
                'expiring_tomorrow'               => AgentInvitation::expiringSoon(1)->count(),
                'failed_invitations'              => AgentInvitation::failed()->count(),
                'with_expiration_issues'          => AgentInvitation::withExpirationIssues()->count(),
                'new_invitations_expired_today'   => AgentInvitation::expired()
                    ->whereDate('created_at', $todayUtc)
                    ->count(),
                'report_time_utc'                 => $nowUtc->toISOString(),
                'configuration' => [
                    'expiry_days'              => config('app.invitation_expiry_days', 7),
                    'warning_days'             => config('app.invitation_warning_days', 2),
                    'auto_expiry_enabled'      => config('app.invitation_auto_expiry', true),
                    'resend_extends_expiry'    => config('app.invitation_resend_extends_expiry', true),
                ],
            ];

            if ($stats['new_invitations_expired_today'] > 0) {
                Log::critical("🚨 CRITICAL: New invitations expired today - SYSTEM ISSUE", $stats);
            }

            Log::info("📈 Daily Invitation Report", array_merge($stats, [
                'app_timezone' => config('app.timezone'),
            ]));
        } catch (\Exception $e) {
            Log::error("Failed to generate daily invitation report: " . $e->getMessage(), [
                'expiry_days' => config('app.invitation_expiry_days', 7),
            ]);
        }
    }

    private function validateInvitationConfiguration(): void
    {
        try {
            $config = [
                'expiry_days'            => config('app.invitation_expiry_days', 7),
                'warning_days'           => config('app.invitation_warning_days', 2),
                'auto_expiry_enabled'    => config('app.invitation_auto_expiry', true),
                'resend_extends_expiry'  => config('app.invitation_resend_extends_expiry', true),
            ];

            $issues = [];

            if ($config['expiry_days'] < 1 || $config['expiry_days'] > 30) {
                $issues[] = "INVITATION_EXPIRY_DAYS must be between 1 and 30, got: {$config['expiry_days']}";
            }

            if ($config['warning_days'] < 1 || $config['warning_days'] > 7) {
                $issues[] = "INVITATION_WARNING_DAYS must be between 1 and 7, got: {$config['warning_days']}";
            }

            if ($config['warning_days'] >= $config['expiry_days']) {
                $issues[] = "INVITATION_WARNING_DAYS ({$config['warning_days']}) cannot be greater than or equal to INVITATION_EXPIRY_DAYS ({$config['expiry_days']})";
            }

            if (empty($issues)) {
                Log::info("✅ Invitation configuration validation passed", $config);
            } else {
                Log::warning("❌ Invitation configuration validation failed", [
                    'issues'         => $issues,
                    'current_config' => $config,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Failed to validate invitation configuration: " . $e->getMessage());
        }
    }

    private function sendArchivalWarningNotification(User $landlord, int $daysUntilArchive): void
    {
        try {
            $originalEmail = $landlord->metadata['archived']['original_data']['email'] ?? $landlord->email;

            if ($originalEmail) {
                Log::info("📧 Sending archival warning to landlord", [
                    'landlord_id'        => $landlord->id,
                    'email'              => $originalEmail,
                    'days_until_archive' => $daysUntilArchive,
                    'scheduled_date'     => $landlord->deletion_scheduled_at?->toISOString(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Failed to send archival warning: " . $e->getMessage(), [
                'landlord_id' => $landlord->id,
            ]);
        }
    }

    private function sendFinalDeletionWarning(User $user, int $daysUntilDeletion): void
    {
        try {
            $originalEmail = $user->metadata['archived']['original_data']['email'] ?? $user->email;

            if ($originalEmail) {
                Log::info("⚠️ Sending final deletion warning to user", [
                    'user_id'             => $user->id,
                    'email'               => $originalEmail,
                    'days_until_deletion' => $daysUntilDeletion,
                    'scheduled_date'      => $user->deletion_scheduled_at?->toISOString(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Failed to send final deletion warning: " . $e->getMessage(), [
                'user_id' => $user->id,
            ]);
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}