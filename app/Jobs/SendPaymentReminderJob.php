<?php

namespace App\Jobs;

use App\Models\InvoiceReminder;
use App\Models\BillingInvoice;
use App\Models\User;
use App\Services\EmailService;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPaymentReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Real retries are enabled by NOT marking the reminder as `failed`
     * inside handle() — see failed() below. handle() throws on total
     * failure, leaving the row `pending`, and Laravel retries up to $tries.
     * Only after the final attempt does failed() stamp the terminal state.
     */
    public int $tries   = 3;
    public int $backoff = 60;

    /** Cap runtime so a hung provider can't block the worker indefinitely. */
    public int $timeout = 30;

    public function __construct(public int $reminderId)
    {
    }

    public function handle(EmailService $emailService, SmsService $smsService): void
    {
        $reminder = InvoiceReminder::with(['invoice', 'superAdmin'])
            ->find($this->reminderId);

        // ---------- IDEMPOTENCY ----------
        // If the reminder is gone or no longer pending, another worker
        // already handled it (or a prior attempt succeeded). Exit silently.
        if (!$reminder || $reminder->status !== 'pending') {
            return;
        }

        $invoice = $reminder->invoice;
        if (!$invoice) {
            $this->markTerminalFailure($reminder, 'Invoice missing');
            return;
        }

        // Invoice already settled → no reminder needed.
        if (in_array($invoice->status, ['paid', 'cancelled'], true)) {
            $reminder->update([
                'status'         => 'skipped',
                'failure_reason' => 'Invoice already ' . $invoice->status,
            ]);
            return;
        }

        // ---------- DEVELOPER SETTINGS GATE ----------
        // Respect the automation flag written by DeveloperSettingsController.
        // If reminders are disabled OR the developer setting is gone/inactive,
        // mark the reminder as skipped rather than failing (it's not an error,
        // the operator turned it off).
        if (!$this->remindersAreEnabled($invoice)) {
            $reminder->update([
                'status'         => 'skipped',
                'failure_reason' => 'Payment reminders disabled by developer',
            ]);

            Log::info('Reminder skipped — reminders disabled for this developer', [
                'reminder_id' => $reminder->id,
                'invoice'     => $invoice->invoice_number,
            ]);
            return;
        }

        /** @var User|null $recipient */
        $recipient = $reminder->superAdmin;
        if (!$recipient) {
            $this->markTerminalFailure($reminder, 'Super admin missing');
            return;
        }

        // ---------- CHANNELS ----------
        // Schema uses a singular `channel` column, but we tolerate a
        // comma-separated list (and a legacy `channels` plural) for safety.
        $rawChannels = $reminder->channel
            ?? $reminder->channels
            ?? 'email';

        $channels = array_filter(array_map('trim', explode(',', (string) $rawChannels)));

        // Validate against the known set; drop unknowns silently.
        $channels = array_values(array_intersect($channels, ['email', 'sms', 'whatsapp']));

        if (empty($channels)) {
            $channels = ['email'];
        }

        $subject = sprintf(
            'Payment reminder: Invoice %s due in %d day(s)',
            $invoice->invoice_number,
            $reminder->days_before_due
        );
        $body = $this->buildReminderBody($invoice, $reminder, $recipient);

        $results = [
            'email'    => null,
            'sms'      => null,
            'whatsapp' => null,
        ];
        $anySuccess = false;

        // ---------- EMAIL ----------
        if (in_array('email', $channels, true) && !empty($recipient->email)) {
            try {
                $res = $emailService->sendEmail(
                    $recipient->email,
                    $subject,
                    'emails.billing.payment-reminder',
                    [
                        'recipientName' => $recipient->name,
                        'invoiceNumber' => $invoice->invoice_number,
                        'amount'        => $invoice->amount,
                        'currency'      => $invoice->currency,
                        'dueDate'       => optional($invoice->due_date)->format('F j, Y'),
                        'daysRemaining' => $reminder->days_before_due,
                    ]
                );

                $results['email'] = $res;
                if (!empty($res['success'])) {
                    $anySuccess = true;
                }
            } catch (\Throwable $e) {
                $results['email'] = ['success' => false, 'message' => $e->getMessage()];
                Log::warning('Reminder email failed', [
                    'reminder_id' => $reminder->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        // ---------- SMS ----------
        if (in_array('sms', $channels, true) && !empty($recipient->phone)) {
            try {
                // Use remaining balance, not the full invoice amount, so
                // partially-paid invoices show the correct figure.
                $outstanding = max(0, (float) $invoice->amount - (float) ($invoice->paid_amount ?? 0));

                $smsBody = sprintf(
                    '%s: Invoice %s for %s %s is due in %d day(s).',
                    config('app.name'),
                    $invoice->invoice_number,
                    $invoice->currency,
                    number_format($outstanding, 2),
                    $reminder->days_before_due
                );

                $res = $smsService->sendWithDefaultProvider($recipient->phone, $smsBody);

                $results['sms'] = $res;
                if (!empty($res['success'])) {
                    $anySuccess = true;
                }
            } catch (\Throwable $e) {
                $results['sms'] = ['success' => false, 'message' => $e->getMessage()];
                Log::warning('Reminder SMS failed', [
                    'reminder_id' => $reminder->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        // ---------- WHATSAPP ----------
        // Placeholder for when a WhatsAppService is wired up.
        // if (in_array('whatsapp', $channels, true) && $whatsappService) { ... }

        // ---------- SUCCESS ----------
        if ($anySuccess) {
            $reminder->update([
                'status'           => 'sent',
                'sent_at'          => now(),
                'delivery_results' => $results,
                'failure_reason'   => null,
            ]);

            Log::info('Payment reminder sent', [
                'reminder_id' => $reminder->id,
                'invoice'     => $invoice->invoice_number,
                'channels'    => $channels,
            ]);
            return;
        }

        // ---------- TOTAL FAILURE ----------
        // Store last-attempt results so operators can debug, but DO NOT
        // mark the reminder failed here. Leave it `pending` so Laravel
        // retries. The failed() hook marks it terminal after the last try.
        $reminder->update([
            'delivery_results' => $results,
        ]);

        Log::error('All reminder channels failed on this attempt', [
            'reminder_id' => $reminder->id,
            'invoice'     => $invoice->invoice_number,
            'attempt'     => $this->attempts(),
            'max_tries'   => $this->tries,
            'results'     => $results,
        ]);

        throw new \RuntimeException('All reminder channels failed');
    }

    /**
     * Called by Laravel after the final retry exhausts $tries.
     * This is where the reminder gets its terminal `failed` stamp.
     */
    public function failed(\Throwable $exception): void
    {
        try {
            $reminder = InvoiceReminder::find($this->reminderId);
            if (!$reminder) {
                return;
            }

            // Only stamp if it's still pending — a late success from a
            // concurrent worker shouldn't be clobbered.
            if ($reminder->status === 'pending') {
                $reminder->update([
                    'status'         => 'failed',
                    'failure_reason' => 'All reminder channels failed after ' . $this->tries . ' attempts',
                ]);
            }

            Log::error('Payment reminder permanently failed', [
                'reminder_id' => $this->reminderId,
                'attempts'    => $this->tries,
                'exception'   => $exception->getMessage(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to mark reminder as failed', [
                'reminder_id' => $this->reminderId,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check the developer's automation flags.
     *
     * Returns false when:
     *   - the developer setting is missing
     *   - billing_status is inactive
     *   - billing_rules.send_payment_reminders is false
     *
     * Returns true in every other case (conservative default).
     */
    private function remindersAreEnabled(BillingInvoice $invoice): bool
    {
        $settings = $invoice->developerSetting ?? null;

        if (!$settings) {
            // Without settings, we can't check the flag. Fail closed —
            // don't send a reminder for a system we can't identify.
            Log::warning('Reminder skipped — developer settings missing', [
                'invoice_id' => $invoice->id,
            ]);
            return false;
        }

        // Honor the top-level status gate.
        if (isset($settings->billing_status)
            && $settings->billing_status !== 'active') {
            Log::info('Reminder skipped — developer billing_status not active', [
                'invoice_id'      => $invoice->id,
                'billing_status'  => $settings->billing_status,
            ]);
            return false;
        }

        // Decode rules JSON.
        $rules = $settings->billing_rules;
        if (is_string($rules)) {
            $rules = json_decode($rules, true) ?: [];
        }
        if (!is_array($rules)) {
            $rules = [];
        }

        // Default to false when the key is missing — matches the settings
        // controller, which writes false unless the toggle is checked.
        return (bool) ($rules['send_payment_reminders'] ?? false);
    }

    /**
     * Mark a reminder as terminally failed (non-retryable conditions).
     */
    private function markTerminalFailure(InvoiceReminder $reminder, string $reason): void
    {
        $reminder->update([
            'status'         => 'failed',
            'failure_reason' => $reason,
        ]);

        Log::error('Payment reminder failed', [
            'reminder_id' => $reminder->id,
            'reason'      => $reason,
        ]);
    }

    /**
     * Build the plain-text fallback body.
     * Used when the email view doesn't render or when a text-only channel needs it.
     */
    private function buildReminderBody(BillingInvoice $invoice, InvoiceReminder $reminder, User $recipient): string
    {
        $amount  = number_format((float) $invoice->amount, 2);
        $dueDate = $invoice->due_date
            ? $invoice->due_date->format('F j, Y')
            : 'soon';

        return <<<TEXT
Hello {$recipient->name},

This is a friendly reminder that invoice {$invoice->invoice_number} will be due on {$dueDate}.

Invoice Details:
- Invoice Number: {$invoice->invoice_number}
- Amount Due: {$invoice->currency} {$amount}
- Due Date: {$dueDate}
- Days Remaining: {$reminder->days_before_due}

Please complete the payment before the due date to avoid any late fees.

Thank you,
Billing System
TEXT;
    }
}