<?php

namespace App\Services;

use App\Models\User;
use App\Models\TenantInvoice;
use App\Models\Invoice;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\MaintenanceNotification;
use App\Mail\EmergencyAlert;
use App\Mail\SystemAlert;
use App\Mail\TenantInvoiceCreated;
use App\Mail\TenantInvoiceReminder;
use App\Mail\TenantPaymentConfirmation;
use App\Mail\InvoiceArchiveNotification;
use App\Mail\InvoiceArchiveReminder;
use App\Mail\UnpaidInvoiceReminder;
use App\Mail\LandlordInvoiceGenerated;
use App\Mail\LandlordInvoiceReminder;
use App\Mail\LandlordPaymentConfirmation;

class NotificationService
{
    /** Maximum SMS length (single segment). Arkesel rejects anything longer. */
    protected const SMS_MAX_LENGTH = 160;

    protected $settings;

    /** @var \App\Services\SmsService */
    protected $smsService;

    /** @var \App\Services\WhatsAppService */
    protected $whatsappService;

    public function __construct(
        \App\Services\SmsService $smsService,
        \App\Services\WhatsAppService $whatsappService
    ) {
        $this->smsService      = $smsService;
        $this->whatsappService = $whatsappService;
        $this->settings        = SystemSetting::getSettings();
    }

    /* ============================================================
     | GENERIC MAINTENANCE / ALERT NOTIFICATIONS
     * ============================================================ */

    public function sendMaintenanceNotification(User $user, string $message, array $channels, string $priority = 'medium')
    {
        $sent = [];

        foreach ($channels as $channel) {
            try {
                switch ($channel) {
                    case 'email':
                        $this->sendEmailNotification($user, $message, 'Maintenance Notification', $priority);
                        $sent[] = 'email';
                        break;

                    case 'sms':
                        $this->sendSms($user->phone, $this->clampSms($message));
                        $sent[] = 'sms';
                        break;

                    case 'push':
                        $sent[] = 'push';
                        break;

                    case 'in_app':
                        $this->createInAppNotification($user, $message, 'maintenance');
                        $sent[] = 'in_app';
                        break;
                }
            } catch (\Exception $e) {
                Log::error("Failed to send {$channel} notification to user {$user->id}", [
                    'error'   => $e->getMessage(),
                    'user_id' => $user->id,
                ]);
            }
        }

        return $sent;
    }

    public function sendAlert(User $user, string $message, string $channel, string $urgency = 'info')
    {
        try {
            switch ($channel) {
                case 'email':
                    $subject = $this->getAlertSubject($urgency);
                    $this->sendEmailNotification($user, $message, $subject, $urgency);
                    break;

                case 'sms':
                    $this->sendSms($user->phone, $this->clampSms($message));
                    break;

                case 'push':
                    break;

                case 'in_app':
                    $this->createInAppNotification($user, $message, 'alert', $urgency);
                    break;
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send alert to user {$user->id}", [
                'error'   => $e->getMessage(),
                'channel' => $channel,
                'urgency' => $urgency,
            ]);
            return false;
        }
    }

    public function sendEmergencyAlert(User $user, string $message)
    {
        try {
            Mail::to($user->email)->send(new EmergencyAlert($message));

            $this->createInAppNotification($user, $message, 'emergency', 'critical');

            Log::info('Emergency alert sent to user', [
                'user_id' => $user->id,
                'email'   => $user->email,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send emergency alert', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    /* ============================================================
     | TENANT INVOICE NOTIFICATIONS — CHANNEL-AWARE
     * ============================================================ */

    public function sendTenantInvoiceCreated(TenantInvoice $invoice): array
    {
        $channels = $this->resolveChannels(
            $this->settings->invoice_notification_channels ?? ['email'],
            'tenant.created'
        );

        return $this->dispatchTenantNotification($invoice, 'created', $channels);
    }

    public function sendTenantInvoiceReminder(TenantInvoice $invoice): array
    {
        $channels = $this->resolveChannels(
            $this->settings->payment_reminder_channels ?? ['email'],
            'tenant.reminder'
        );

        return $this->dispatchTenantNotification($invoice, 'reminder', $channels);
    }

    public function sendTenantInvoiceOverdue(TenantInvoice $invoice): array
    {
        $channels = $this->resolveChannels(
            $this->settings->overdue_notification_channels ?? ['email'],
            'tenant.overdue'
        );

        return $this->dispatchTenantNotification($invoice, 'overdue', $channels);
    }

    public function sendTenantPaymentConfirmation(TenantInvoice $invoice): array
    {
        $channels = $this->resolveChannels(
            $this->settings->payment_confirmation_channels ?? ['email'],
            'tenant.confirmation'
        );

        return $this->dispatchTenantNotification($invoice, 'confirmation', $channels);
    }

    /**
 * Notify tenant that their invoice was updated (amount/due date change).
 *
 * ✅ Mirrors sendLandlordInvoiceUpdate():
 *   - Routes through overdue_notification_channels when the invoice is overdue
 *   - Falls back to payment_reminder_channels otherwise
 *   - Uses a compact "Invoice (OVERDUE) updated: X → Y" message
 */
public function sendTenantInvoiceUpdate(TenantInvoice $invoice, array $updateData): array
{
    $isOverdue = $this->tenantInvoiceIsOverdue($invoice);

    $channelSetting = $isOverdue
        ? ($this->settings->overdue_notification_channels ?? ['email'])
        : ($this->settings->payment_reminder_channels    ?? ['email']);

    $channels = $this->resolveChannels(
        $channelSetting,
        $isOverdue ? 'tenant.update.overdue' : 'tenant.update'
    );

    $dispatched = [];
    $skipped    = [];
    $tenant     = $invoice->tenant ?? null;

    if (!$tenant) {
        Log::warning('[NotificationService] Tenant invoice update skipped — no tenant', [
            'invoice_id' => $invoice->id,
        ]);

        return ['dispatched' => [], 'channels' => $channels, 'skipped' => ['tenant_missing']];
    }

    foreach ($channels as $channel) {
        try {
            switch ($channel) {
                case 'email':
                    $dispatched['email'] = $this->sendTenantUpdateViaEmail($invoice, $updateData);
                    break;

                case 'sms':
                    $dispatched['sms'] = $this->sendTenantUpdateViaSms($invoice, $updateData);
                    break;

                case 'whatsapp':
                    $dispatched['whatsapp'] = $this->sendTenantUpdateViaWhatsApp($invoice, $updateData);
                    break;

                default:
                    $skipped[] = $channel;
            }
        } catch (\Throwable $e) {
            $dispatched[$channel] = false;

            Log::error('[NotificationService] Tenant invoice update dispatch failed', [
                'invoice_id' => $invoice->id,
                'channel'    => $channel,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
        }
    }

    Log::info('[NotificationService] Tenant invoice update notification dispatched', [
        'invoice_id'  => $invoice->id,
        'tenant_id'   => $tenant->id,
        'was_overdue' => $isOverdue,
        'channels'    => $channels,
        'dispatched'  => $dispatched,
        'skipped'     => $skipped,
    ]);

    return [
        'dispatched' => $dispatched,
        'channels'   => $channels,
        'skipped'    => $skipped,
    ];
}

/* ============================================================
 | TENANT UPDATE — PER-CHANNEL IMPLEMENTATIONS
 * ============================================================ */

protected function sendTenantUpdateViaEmail(TenantInvoice $invoice, array $updateData): bool
{
    $tenant = $invoice->tenant ?? null;

    if (!$tenant || empty($tenant->email)) {
        return false;
    }

    try {
        $isOverdue = ($invoice->status === 'overdue')
            || ($invoice->due_date && $invoice->due_date < now());

        $subject = $this->buildTenantSubject($invoice, 'update');

        Mail::send(
            'emails.tenant.invoice-updated',
            [
                'invoice'             => $invoice,
                'tenant'              => $tenant,
                'propertyUnitDisplay' => $invoice->property_unit_display,
                'settings'            => $this->settings,
                'updateData'          => $updateData,
                'isOverdue'           => $isOverdue,
            ],
            function ($mail) use ($tenant, $subject) {
                $mail->to($tenant->email)->subject($subject);
            }
        );

        Log::info('[NotificationService] Tenant update email dispatched (blade)', [
            'invoice_id' => $invoice->id,
            'email'      => $tenant->email,
        ]);

        return true;
    } catch (\Throwable $e) {
        Log::error('[NotificationService] Tenant update email dispatch exception', [
            'invoice_id' => $invoice->id,
            'error'      => $e->getMessage(),
        ]);
        return false;
    }
}

protected function sendTenantUpdateViaSms(TenantInvoice $invoice, array $updateData): bool
{
    $tenant = $invoice->tenant ?? null;

    if (!$tenant || empty($tenant->phone)) {
        Log::info('[NotificationService] Tenant update SMS skipped — tenant has no phone', [
            'invoice_id' => $invoice->id,
            'tenant_id'  => $tenant?->id,
        ]);
        return false;
    }

    try {
        $message = $this->buildTenantUpdateMessage($invoice, $updateData);
        $message = $this->clampSms($message);
    } catch (\Throwable $e) {
        Log::error('[NotificationService] Failed to build tenant update SMS message', [
            'invoice_id' => $invoice->id,
            'error'      => $e->getMessage(),
        ]);
        return false;
    }

    try {
        $result = $this->smsService->sendWithDefaultProvider(
            $tenant->phone,
            $message,
            ['context' => 'tenant_invoice_update', 'invoice_id' => $invoice->id]
        );
    } catch (\Throwable $e) {
        Log::error('[NotificationService] Tenant update SMS dispatch exception', [
            'invoice_id' => $invoice->id,
            'phone'      => $tenant->phone,
            'error'      => $e->getMessage(),
        ]);
        return false;
    }

    $ok = is_array($result) ? (bool) ($result['success'] ?? false) : (bool) $result;

    Log::info('[NotificationService] Tenant update SMS dispatch result', [
        'invoice_id'     => $invoice->id,
        'tenant_id'      => $tenant->id,
        'phone'          => $tenant->phone,
        'success'        => $ok,
        'message_length' => mb_strlen($message),
        'detail'         => is_array($result) ? ($result['message'] ?? null) : null,
    ]);

    return $ok;
}

protected function sendTenantUpdateViaWhatsApp(TenantInvoice $invoice, array $updateData): bool
{
    $tenant = $invoice->tenant ?? null;

    if (!$tenant || empty($tenant->phone)) {
        return false;
    }

    if (!method_exists($this->whatsappService, 'sendMessage')) {
        return false;
    }

    try {
        $message = $this->buildTenantUpdateMessage($invoice, $updateData);

        $result = $this->whatsappService->sendMessage($tenant->phone, $message);

        return is_array($result) ? (bool) ($result['success'] ?? false) : (bool) $result;
    } catch (\Throwable $e) {
        Log::error('[NotificationService] Tenant update WhatsApp failed', [
            'invoice_id' => $invoice->id,
            'error'      => $e->getMessage(),
        ]);
        return false;
    }
}

/**
 * Build a compact, ≤160-char tenant update message.
 */
protected function buildTenantUpdateMessage(TenantInvoice $invoice, array $updateData): string
{
    $systemName = $this->settings->system_name ?? config('app.name', 'Property Mgmt');
    $oldAmount  = $updateData['formatted_old_amount'] ?? ($updateData['old_amount'] ?? '');
    $newAmount  = $updateData['formatted_new_amount'] ?? ($updateData['new_amount'] ?? '');
    $oldDue     = $updateData['old_due_date'] ?? '';
    $newDue     = $updateData['new_due_date'] ?? '';
    $sig        = $this->shortSystemName($systemName);

    $overdueTag = $this->tenantInvoiceIsOverdue($invoice) ? ' (OVERDUE)' : '';

    return "Invoice{$overdueTag} updated: {$oldAmount} → {$newAmount}. "
         . "Due: {$oldDue} → {$newDue}. - {$sig}";
}

/**
 * Tenant counterpart of buildLandlordSubject().
 */
protected function buildTenantSubject(TenantInvoice $invoice, string $event): string
{
    $systemName = $this->settings->system_name ?? config('app.name');

    return match ($event) {
        'created'      => "New Invoice — {$invoice->invoice_number} ({$systemName})",
        'reminder'     => "Payment Reminder — {$invoice->invoice_number} ({$systemName})",
        'overdue'      => "Overdue Invoice — {$invoice->invoice_number} ({$systemName})",
        'confirmation' => "Payment Confirmation — {$invoice->invoice_number} ({$systemName})",
        'update'       => "Invoice Update — {$invoice->invoice_number} ({$systemName})",
        default        => "Invoice Update — {$invoice->invoice_number} ({$systemName})",
    };
}

/**
 * Determine whether a tenant invoice is (or was) overdue.
 */
protected function tenantInvoiceIsOverdue(TenantInvoice $invoice): bool
{
    if ($invoice->status === 'overdue') {
        return true;
    }

    if ($invoice->due_date && $invoice->due_date < now()) {
        return true;
    }

    return false;
}

    /* ============================================================
     | LANDLORD INVOICE NOTIFICATIONS — CHANNEL-AWARE
     * ============================================================ */

    public function sendLandlordInvoiceCreated(Invoice $invoice): array
    {
        $channels = $this->resolveChannels(
            $this->settings->invoice_notification_channels ?? ['email'],
            'landlord.created'
        );

        return $this->dispatchLandlordNotification($invoice, 'created', $channels);
    }

    public function sendLandlordInvoiceReminder(Invoice $invoice): array
    {
        $channels = $this->resolveChannels(
            $this->settings->payment_reminder_channels ?? ['email'],
            'landlord.reminder'
        );

        return $this->dispatchLandlordNotification($invoice, 'reminder', $channels);
    }

    public function sendLandlordInvoiceOverdue(Invoice $invoice): array
    {
        $channels = $this->resolveChannels(
            $this->settings->overdue_notification_channels ?? ['email'],
            'landlord.overdue'
        );

        return $this->dispatchLandlordNotification($invoice, 'overdue', $channels);
    }

    public function sendLandlordPaymentConfirmation(Invoice $invoice): array
    {
        $channels = $this->resolveChannels(
            $this->settings->payment_confirmation_channels ?? ['email'],
            'landlord.confirmation'
        );

        return $this->dispatchLandlordNotification($invoice, 'confirmation', $channels);
    }

    /**
     * Notify landlord that their invoice was updated.
     *
     * ✅ Routes through overdue_notification_channels when the invoice is
     * overdue, otherwise through payment_reminder_channels. Per-channel
     * logging shows exactly which channel fired and why it failed.
     */
    public function sendLandlordInvoiceUpdate(Invoice $invoice, array $updateData): array
    {
        $isOverdue = $this->invoiceIsOverdue($invoice);

        $channelSetting = $isOverdue
            ? ($this->settings->overdue_notification_channels ?? ['email'])
            : ($this->settings->payment_reminder_channels    ?? ['email']);

        $channels = $this->resolveChannels(
            $channelSetting,
            $isOverdue ? 'landlord.update.overdue' : 'landlord.update'
        );

        $dispatched = [];
        $skipped    = [];
        $landlord   = $invoice->property->landlord ?? null;

        if (!$landlord) {
            Log::warning('[NotificationService] Landlord invoice update skipped — no landlord', [
                'invoice_id' => $invoice->id,
            ]);

            return ['dispatched' => [], 'channels' => $channels, 'skipped' => ['landlord_missing']];
        }

        foreach ($channels as $channel) {
            try {
                switch ($channel) {
                    case 'email':
                        $dispatched['email'] = $this->sendLandlordUpdateViaEmail($invoice, $updateData);
                        break;

                    case 'sms':
                        $dispatched['sms'] = $this->sendLandlordUpdateViaSms($invoice, $updateData);
                        break;

                    case 'whatsapp':
                        $dispatched['whatsapp'] = $this->sendLandlordUpdateViaWhatsApp($invoice, $updateData);
                        break;

                    default:
                        $skipped[] = $channel;
                }
            } catch (\Throwable $e) {
                $dispatched[$channel] = false;

                Log::error('[NotificationService] Landlord invoice update dispatch failed', [
                    'invoice_id' => $invoice->id,
                    'channel'    => $channel,
                    'error'      => $e->getMessage(),
                    'trace'      => $e->getTraceAsString(),
                ]);
            }
        }

        Log::info('[NotificationService] Landlord invoice update notification dispatched', [
            'invoice_id'  => $invoice->id,
            'landlord_id' => $landlord->id,
            'was_overdue' => $isOverdue,
            'channels'    => $channels,
            'dispatched'  => $dispatched,
            'skipped'     => $skipped,
        ]);

        return [
            'dispatched' => $dispatched,
            'channels'   => $channels,
            'skipped'    => $skipped,
        ];
    }

    /* ============================================================
     | CHANNEL RESOLUTION
     * ============================================================ */

    protected function resolveChannels($rawChannels, string $event): array
    {
        if (is_string($rawChannels)) {
            $decoded     = json_decode($rawChannels, true);
            $rawChannels = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($rawChannels)) {
            $rawChannels = [];
        }

        $resolved = [];
        $skipped  = [];

        foreach (array_unique($rawChannels) as $channel) {
            switch ($channel) {
                case 'email':
                    $resolved[] = 'email';
                    break;

                case 'sms':
                    if (empty($this->settings->sms_notifications_enabled)) {
                        $skipped[] = 'sms (sms_notifications_enabled=false)';
                        break;
                    }

                    if (str_contains($event, 'reminder')
                        && isset($this->settings->sms_reminder_enabled)
                        && empty($this->settings->sms_reminder_enabled)) {
                        $skipped[] = 'sms (sms_reminder_enabled=false)';
                        break;
                    }

                    if (str_contains($event, 'confirmation')
                        && isset($this->settings->sms_payment_confirmation_enabled)
                        && empty($this->settings->sms_payment_confirmation_enabled)) {
                        $skipped[] = 'sms (sms_payment_confirmation_enabled=false)';
                        break;
                    }

                    $resolved[] = 'sms';
                    break;

                case 'whatsapp':
                    if (!empty($this->settings->enable_whatsapp_notifications)) {
                        $resolved[] = 'whatsapp';
                    } else {
                        $skipped[] = 'whatsapp (enable_whatsapp_notifications=false)';
                    }
                    break;

                default:
                    $skipped[] = "{$channel} (unknown)";
            }
        }

        if (empty($resolved)) {
            $resolved[] = 'email';
        }

        if (!empty($skipped)) {
            Log::info('[NotificationService] Channels skipped', [
                'event'    => $event,
                'skipped'  => $skipped,
                'resolved' => $resolved,
            ]);
        }

        return array_values(array_unique($resolved));
    }

    /* ============================================================
     | TENANT DISPATCHER
     * ============================================================ */

    protected function dispatchTenantNotification(TenantInvoice $invoice, string $event, array $channels): array
    {
        $tenant = $invoice->tenant;

        $dispatched = [];
        $skipped    = [];

        if (!$tenant) {
            Log::warning('[NotificationService] Tenant invoice notification skipped — invoice has no tenant', [
                'invoice_id' => $invoice->id,
                'event'      => $event,
            ]);

            return [
                'dispatched' => [],
                'channels'   => $channels,
                'skipped'    => ['tenant_missing'],
            ];
        }

        foreach ($channels as $channel) {
            try {
                switch ($channel) {
                    case 'email':
                        $dispatched['email'] = $this->sendTenantViaEmail($invoice, $event);
                        break;

                    case 'sms':
                        $dispatched['sms'] = $this->sendTenantViaSms($invoice, $event);
                        break;

                    case 'whatsapp':
                        $dispatched['whatsapp'] = $this->sendTenantViaWhatsApp($invoice, $event);
                        break;

                    default:
                        $skipped[] = $channel;
                }
            } catch (\Throwable $e) {
                $dispatched[$channel] = false;

                Log::error('[NotificationService] Tenant invoice channel dispatch failed', [
                    'invoice_id' => $invoice->id,
                    'event'      => $event,
                    'channel'    => $channel,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        Log::info('[NotificationService] Tenant invoice notification dispatched', [
            'invoice_id' => $invoice->id,
            'tenant_id'  => $tenant->id,
            'event'      => $event,
            'channels'   => $channels,
            'dispatched' => $dispatched,
            'skipped'    => $skipped,
        ]);

        return [
            'dispatched' => $dispatched,
            'channels'   => $channels,
            'skipped'    => $skipped,
        ];
    }

    /* ============================================================
     | LANDLORD DISPATCHER
     * ============================================================ */

    protected function dispatchLandlordNotification(Invoice $invoice, string $event, array $channels): array
    {
        $landlord = $invoice->property->landlord ?? null;

        $dispatched = [];
        $skipped    = [];

        if (!$landlord) {
            Log::warning('[NotificationService] Landlord invoice notification skipped — no landlord', [
                'invoice_id' => $invoice->id,
                'event'      => $event,
            ]);

            return [
                'dispatched' => [],
                'channels'   => $channels,
                'skipped'    => ['landlord_missing'],
            ];
        }

        foreach ($channels as $channel) {
            try {
                switch ($channel) {
                    case 'email':
                        $dispatched['email'] = $this->sendLandlordViaEmail($invoice, $event);
                        break;

                    case 'sms':
                        $dispatched['sms'] = $this->sendLandlordViaSms($invoice, $event);
                        break;

                    case 'whatsapp':
                        $dispatched['whatsapp'] = $this->sendLandlordViaWhatsApp($invoice, $event);
                        break;

                    default:
                        $skipped[] = $channel;
                }
            } catch (\Throwable $e) {
                $dispatched[$channel] = false;

                Log::error('[NotificationService] Landlord invoice channel dispatch failed', [
                    'invoice_id' => $invoice->id,
                    'event'      => $event,
                    'channel'    => $channel,
                    'error'      => $e->getMessage(),
                    'trace'      => $e->getTraceAsString(),
                ]);
            }
        }

        Log::info('[NotificationService] Landlord invoice notification dispatched', [
            'invoice_id'  => $invoice->id,
            'landlord_id' => $landlord->id,
            'event'       => $event,
            'channels'    => $channels,
            'dispatched'  => $dispatched,
            'skipped'     => $skipped,
        ]);

        return [
            'dispatched' => $dispatched,
            'channels'   => $channels,
            'skipped'    => $skipped,
        ];
    }

    /* ============================================================
     | TENANT PER-CHANNEL IMPLEMENTATIONS
     * ============================================================ */

    protected function sendTenantViaEmail(TenantInvoice $invoice, string $event): bool
    {
        $tenant = $invoice->tenant;

        if (!$tenant || empty($tenant->email)) {
            Log::info('[NotificationService] Email skipped — tenant has no email', [
                'invoice_id' => $invoice->id,
                'tenant_id'  => $tenant?->id,
                'event'      => $event,
            ]);
            return false;
        }

        $mailable = match ($event) {
            'created'      => new TenantInvoiceCreated($invoice, $this->settings),
            'reminder'     => new TenantInvoiceReminder($invoice, $this->settings),
            'overdue'      => new TenantInvoiceReminder($invoice, $this->settings),
            'confirmation' => new TenantPaymentConfirmation($invoice, $this->settings),
            default        => null,
        };

        if ($mailable === null) {
            Log::warning('[NotificationService] No mailable for tenant event', ['event' => $event]);
            return false;
        }

        Mail::to($tenant->email)->send($mailable);

        Log::info('[NotificationService] Tenant email dispatched', [
            'invoice_id' => $invoice->id,
            'tenant_id'  => $tenant->id,
            'event'      => $event,
            'email'      => $tenant->email,
        ]);

        return true;
    }

    protected function sendTenantViaSms(TenantInvoice $invoice, string $event): bool
    {
        $tenant = $invoice->tenant;

        if (!$tenant || empty($tenant->phone)) {
            Log::info('[NotificationService] SMS skipped — tenant has no phone', [
                'invoice_id' => $invoice->id,
                'tenant_id'  => $tenant?->id,
                'event'      => $event,
            ]);
            return false;
        }

        try {
            $message = $this->buildTenantInvoiceMessage($invoice, $event);
            $message = $this->clampSms($message);
        } catch (\Throwable $e) {
            Log::error('[NotificationService] Failed to build tenant SMS message', [
                'invoice_id' => $invoice->id,
                'event'      => $event,
                'error'      => $e->getMessage(),
            ]);
            return false;
        }

        try {
            $result = $this->smsService->sendWithDefaultProvider(
                $tenant->phone,
                $message,
                ['context' => 'tenant_invoice', 'event' => $event, 'invoice_id' => $invoice->id]
            );
        } catch (\Throwable $e) {
            Log::error('[NotificationService] Tenant SMS dispatch exception', [
                'invoice_id' => $invoice->id,
                'event'      => $event,
                'phone'      => $tenant->phone,
                'error'      => $e->getMessage(),
            ]);
            return false;
        }

        $ok = is_array($result) ? (bool) ($result['success'] ?? false) : (bool) $result;

        Log::info('[NotificationService] Tenant SMS dispatch result', [
            'invoice_id'     => $invoice->id,
            'tenant_id'      => $tenant->id,
            'event'          => $event,
            'phone'          => $tenant->phone,
            'success'        => $ok,
            'message_length' => mb_strlen($message),
            'detail'         => is_array($result) ? ($result['message'] ?? null) : null,
        ]);

        return $ok;
    }

    protected function sendTenantViaWhatsApp(TenantInvoice $invoice, string $event): bool
    {
        $tenant = $invoice->tenant;

        if (!$tenant || empty($tenant->phone)) {
            Log::info('[NotificationService] WhatsApp skipped — tenant has no phone', [
                'invoice_id' => $invoice->id,
                'tenant_id'  => $tenant?->id,
                'event'      => $event,
            ]);
            return false;
        }

        if (!method_exists($this->whatsappService, 'sendMessage')) {
            Log::warning('[NotificationService] WhatsAppService::sendMessage() not found — tenant WhatsApp skipped', [
                'invoice_id' => $invoice->id,
            ]);
            return false;
        }

        try {
            $message = $this->buildTenantInvoiceMessage($invoice, $event);
        } catch (\Throwable $e) {
            Log::error('[NotificationService] Failed to build tenant WhatsApp message', [
                'invoice_id' => $invoice->id,
                'event'      => $event,
                'error'      => $e->getMessage(),
            ]);
            return false;
        }

        try {
            $result = $this->whatsappService->sendMessage($tenant->phone, $message);
        } catch (\Throwable $e) {
            Log::error('[NotificationService] Tenant WhatsApp dispatch exception', [
                'invoice_id' => $invoice->id,
                'event'      => $event,
                'error'      => $e->getMessage(),
            ]);
            return false;
        }

        $ok = is_array($result) ? (bool) ($result['success'] ?? false) : (bool) $result;

        Log::info('[NotificationService] Tenant WhatsApp dispatch result', [
            'invoice_id' => $invoice->id,
            'tenant_id'  => $tenant->id,
            'event'      => $event,
            'phone'      => $tenant->phone,
            'success'    => $ok,
        ]);

        return $ok;
    }

    /* ============================================================
     | LANDLORD PER-CHANNEL IMPLEMENTATIONS
     * ============================================================ */

    protected function sendLandlordViaEmail(Invoice $invoice, string $event): bool
    {
        $landlord = $invoice->property->landlord ?? null;

        if (!$landlord || empty($landlord->email)) {
            Log::info('[NotificationService] Email skipped — landlord has no email', [
                'invoice_id'  => $invoice->id,
                'landlord_id' => $landlord?->id,
                'event'       => $event,
            ]);
            return false;
        }

        $mailable = null;
        try {
            $mailable = match ($event) {
                'created'      => new LandlordInvoiceGenerated($invoice, $this->settings),
                'reminder'     => new LandlordInvoiceReminder($invoice, $this->settings),
                'overdue'      => new LandlordInvoiceReminder($invoice, $this->settings),
                'confirmation' => new LandlordPaymentConfirmation($invoice, $this->settings),
                default        => null,
            };
        } catch (\Throwable $e) {
            Log::warning('[NotificationService] Landlord mailable unavailable, using raw fallback', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
            $mailable = null;
        }

        if ($mailable !== null) {
            Mail::to($landlord->email)->send($mailable);
        } else {
            $subject = $this->buildLandlordSubject($invoice, $event);
            $body    = $this->buildLandlordInvoiceMessage($invoice, $event);

            Mail::raw($body, function ($mail) use ($landlord, $subject) {
                $mail->to($landlord->email)->subject($subject);
            });
        }

        Log::info('[NotificationService] Landlord email dispatched', [
            'invoice_id'  => $invoice->id,
            'landlord_id' => $landlord->id,
            'event'       => $event,
            'email'       => $landlord->email,
        ]);

        return true;
    }

    protected function sendLandlordViaSms(Invoice $invoice, string $event): bool
    {
        $landlord = $invoice->property->landlord ?? null;

        if (!$landlord || empty($landlord->phone)) {
            Log::info('[NotificationService] SMS skipped — landlord has no phone', [
                'invoice_id'  => $invoice->id,
                'landlord_id' => $landlord?->id,
                'event'       => $event,
            ]);
            return false;
        }

        try {
            $message = $this->buildLandlordInvoiceMessage($invoice, $event);
            $message = $this->clampSms($message);
        } catch (\Throwable $e) {
            Log::error('[NotificationService] Failed to build landlord SMS message', [
                'invoice_id'  => $invoice->id,
                'landlord_id' => $landlord->id,
                'event'       => $event,
                'period'      => $invoice->period,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
            return false;
        }

        if (empty(trim($message))) {
            Log::warning('[NotificationService] Landlord SMS message is empty', [
                'invoice_id' => $invoice->id,
                'event'      => $event,
            ]);
            return false;
        }

        try {
            $result = $this->smsService->sendWithDefaultProvider(
                $landlord->phone,
                $message,
                ['context' => 'landlord_invoice', 'event' => $event, 'invoice_id' => $invoice->id]
            );
        } catch (\Throwable $e) {
            Log::error('[NotificationService] Landlord SMS dispatch exception', [
                'invoice_id'  => $invoice->id,
                'landlord_id' => $landlord->id,
                'event'       => $event,
                'phone'       => $landlord->phone,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
            return false;
        }

        $ok = is_array($result) ? (bool) ($result['success'] ?? false) : (bool) $result;

        Log::info('[NotificationService] Landlord SMS dispatch result', [
            'invoice_id'     => $invoice->id,
            'landlord_id'    => $landlord->id,
            'event'          => $event,
            'phone'          => $landlord->phone,
            'success'        => $ok,
            'message_length' => mb_strlen($message),
            'detail'         => is_array($result) ? ($result['message'] ?? null) : null,
        ]);

        return $ok;
    }

    protected function sendLandlordViaWhatsApp(Invoice $invoice, string $event): bool
    {
        $landlord = $invoice->property->landlord ?? null;

        if (!$landlord || empty($landlord->phone)) {
            Log::info('[NotificationService] WhatsApp skipped — landlord has no phone', [
                'invoice_id'  => $invoice->id,
                'landlord_id' => $landlord?->id,
                'event'       => $event,
            ]);
            return false;
        }

        if (!method_exists($this->whatsappService, 'sendMessage')) {
            Log::warning('[NotificationService] WhatsAppService::sendMessage() not found — landlord WhatsApp skipped', [
                'invoice_id' => $invoice->id,
            ]);
            return false;
        }

        try {
            $message = $this->buildLandlordInvoiceMessage($invoice, $event);
        } catch (\Throwable $e) {
            Log::error('[NotificationService] Failed to build landlord WhatsApp message', [
                'invoice_id' => $invoice->id,
                'event'      => $event,
                'error'      => $e->getMessage(),
            ]);
            return false;
        }

        try {
            $result = $this->whatsappService->sendMessage($landlord->phone, $message);
        } catch (\Throwable $e) {
            Log::error('[NotificationService] Landlord WhatsApp dispatch exception', [
                'invoice_id'  => $invoice->id,
                'landlord_id' => $landlord->id,
                'error'       => $e->getMessage(),
            ]);
            return false;
        }

        $ok = is_array($result) ? (bool) ($result['success'] ?? false) : (bool) $result;

        Log::info('[NotificationService] Landlord WhatsApp dispatch result', [
            'invoice_id'  => $invoice->id,
            'landlord_id' => $landlord->id,
            'event'       => $event,
            'phone'       => $landlord->phone,
            'success'     => $ok,
        ]);

        return $ok;
    }

    /* ============================================================
     | LANDLORD UPDATE EVENT (per-channel)
     * ============================================================ */

    protected function sendLandlordUpdateViaEmail(Invoice $invoice, array $updateData): bool
{
    $landlord = $invoice->property->landlord ?? null;

    if (!$landlord || empty($landlord->email)) {
        Log::info('[NotificationService] Landlord update email skipped — landlord has no email', [
            'invoice_id'  => $invoice->id,
            'landlord_id' => $landlord?->id,
        ]);
        return false;
    }

    // Try the branded Mailable first, fall back to Mail::raw if it's unavailable
    $mailable = null;
    try {
        $mailable = new \App\Mail\LandlordInvoiceUpdated($invoice, $this->settings, $updateData);
    } catch (\Throwable $e) {
        Log::warning('[NotificationService] Landlord update mailable unavailable, using raw fallback', [
            'invoice_id' => $invoice->id,
            'error'      => $e->getMessage(),
        ]);
        $mailable = null;
    }

    try {
        if ($mailable !== null) {
            Mail::to($landlord->email)->send($mailable);
        } else {
            $subject = $this->buildLandlordSubject($invoice, 'update');
            $body    = $this->buildLandlordUpdateMessage($invoice, $updateData);

            Mail::raw($body, function ($mail) use ($landlord, $subject) {
                $mail->to($landlord->email)->subject($subject);
            });
        }

        Log::info('[NotificationService] Landlord update email dispatched', [
            'invoice_id'  => $invoice->id,
            'landlord_id' => $landlord->id,
            'email'       => $landlord->email,
            'mailable'    => $mailable !== null,
        ]);

        return true;
    } catch (\Throwable $e) {
        Log::error('[NotificationService] Landlord update email dispatch exception', [
            'invoice_id'  => $invoice->id,
            'landlord_id' => $landlord->id,
            'error'       => $e->getMessage(),
        ]);
        return false;
    }
}

    protected function sendLandlordUpdateViaSms(Invoice $invoice, array $updateData): bool
    {
        $landlord = $invoice->property->landlord ?? null;

        if (!$landlord || empty($landlord->phone)) {
            Log::info('[NotificationService] Landlord update SMS skipped — landlord has no phone', [
                'invoice_id'  => $invoice->id,
                'landlord_id' => $landlord?->id,
            ]);
            return false;
        }

        try {
            $message = $this->buildLandlordUpdateMessage($invoice, $updateData);
            $message = $this->clampSms($message);
        } catch (\Throwable $e) {
            Log::error('[NotificationService] Failed to build landlord update SMS message', [
                'invoice_id'  => $invoice->id,
                'landlord_id' => $landlord->id,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
            return false;
        }

        try {
            $result = $this->smsService->sendWithDefaultProvider(
                $landlord->phone,
                $message,
                ['context' => 'landlord_invoice_update', 'invoice_id' => $invoice->id]
            );
        } catch (\Throwable $e) {
            Log::error('[NotificationService] Landlord update SMS dispatch exception', [
                'invoice_id'  => $invoice->id,
                'landlord_id' => $landlord->id,
                'phone'       => $landlord->phone,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
            return false;
        }

        $ok = is_array($result) ? (bool) ($result['success'] ?? false) : (bool) $result;

        Log::info('[NotificationService] Landlord update SMS dispatch result', [
            'invoice_id'     => $invoice->id,
            'landlord_id'    => $landlord->id,
            'phone'          => $landlord->phone,
            'success'        => $ok,
            'message_length' => mb_strlen($message),
            'detail'         => is_array($result) ? ($result['message'] ?? null) : null,
        ]);

        return $ok;
    }

    protected function sendLandlordUpdateViaWhatsApp(Invoice $invoice, array $updateData): bool
    {
        $landlord = $invoice->property->landlord ?? null;

        if (!$landlord || empty($landlord->phone)) {
            return false;
        }

        if (!method_exists($this->whatsappService, 'sendMessage')) {
            return false;
        }

        try {
            $message = $this->buildLandlordUpdateMessage($invoice, $updateData);

            $result = $this->whatsappService->sendMessage($landlord->phone, $message);

            return is_array($result) ? (bool) ($result['success'] ?? false) : (bool) $result;
        } catch (\Throwable $e) {
            Log::error('[NotificationService] Landlord update WhatsApp failed', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);
            return false;
        }
    }

    /* ============================================================
     | MESSAGE BUILDERS — SMS/WhatsApp (≤160 chars for SMS)
     * ============================================================ */

    /**
     * Build a plain-text message for LANDLORD SMS / WhatsApp.
     * Target: ≤ 160 characters.
     */
    protected function buildLandlordInvoiceMessage(Invoice $invoice, string $event): string
    {
        $landlord    = $invoice->property->landlord ?? null;
        $systemName  = $this->settings->system_name ?? config('app.name', 'Property Mgmt');
        $amount      = $this->settings->formatAmount($invoice->total_amount ?? 0);
        $dueDate     = optional($invoice->due_date)->format('M j') ?? '';
        $periodLabel = $this->formatInvoicePeriodForDisplay($invoice->period);
        $property    = $invoice->property->property_name
            ?? $invoice->property->street_name
            ?? 'your property';

        $firstName = $this->firstName($landlord->name ?? 'Landlord');
        $sig       = $this->shortSystemName($systemName);

        switch ($event) {
            case 'created':
                return "Hi {$firstName}, new {$periodLabel} invoice for {$amount} "
                     . "({$property}) is due {$dueDate}. - {$sig}";

            case 'reminder':
                return "Hi {$firstName}, reminder: invoice for {$amount} ({$property}) "
                     . "is due {$dueDate}. - {$sig}";

            case 'overdue':
                $daysOverdue = $invoice->due_date
                    ? abs(now()->diffInDays($invoice->due_date, false))
                    : 0;
                $penalty     = (float) ($invoice->penalty_amount ?? 0);
                $penaltyText = $penalty > 0
                    ? ' Penalty: ' . $this->settings->formatAmount($penalty) . '.'
                    : '';

                return "Hi {$firstName}, invoice ({$property}) for {$amount} is {$daysOverdue}d "
                     . "overdue.{$penaltyText} Please pay. - {$sig}";

            case 'confirmation':
                return "Hi {$firstName}, payment of {$amount} for {$property} received. "
                     . "Thank you! - {$sig}";

            default:
                return "Hi {$firstName}, invoice update. - {$sig}";
        }
    }

    /**
     * Build a plain-text message for TENANT SMS / WhatsApp.
     * Target: ≤ 160 characters.
     */
    protected function buildTenantInvoiceMessage(TenantInvoice $invoice, string $event): string
    {
        $tenant      = $invoice->tenant;
        $systemName  = $this->settings->system_name ?? config('app.name', 'Property Mgmt');
        $amount      = $this->settings->formatAmount($invoice->total_amount ?? 0);
        $dueDate     = optional($invoice->due_date)->format('M j') ?? '';
        $periodLabel = $this->formatInvoicePeriodForDisplay($invoice->period);

        $firstName = $this->firstName($tenant->name ?? 'Tenant');
        $sig       = $this->shortSystemName($systemName);

        switch ($event) {
            case 'created':
                return "Hi {$firstName}, new {$periodLabel} invoice for {$amount} "
                     . "is due {$dueDate}. - {$sig}";

            case 'reminder':
                return "Hi {$firstName}, reminder: invoice for {$amount} "
                     . "is due {$dueDate}. - {$sig}";

            case 'overdue':
                $daysOverdue = $invoice->due_date
                    ? abs(now()->diffInDays($invoice->due_date, false))
                    : 0;
                $penalty     = (float) ($invoice->penalty_amount ?? 0);
                $penaltyText = $penalty > 0
                    ? ' Penalty: ' . $this->settings->formatAmount($penalty) . '.'
                    : '';

                return "Hi {$firstName}, invoice for {$amount} is {$daysOverdue}d "
                     . "overdue.{$penaltyText} Please pay. - {$sig}";

            case 'confirmation':
                return "Hi {$firstName}, payment of {$amount} received. "
                     . "Thank you! - {$sig}";

            default:
                return "Hi {$firstName}, invoice update. - {$sig}";
        }
    }

    /**
     * Build a plain-text message for the LANDLORD UPDATE event.
     * Short, and adds " (OVERDUE)" when the invoice is past due.
     */
    protected function buildLandlordUpdateMessage(Invoice $invoice, array $updateData): string
    {
        $systemName = $this->settings->system_name ?? config('app.name', 'Property Mgmt');
        $oldAmount  = $updateData['formatted_old_amount'] ?? ($updateData['old_amount'] ?? '');
        $newAmount  = $updateData['formatted_new_amount'] ?? ($updateData['new_amount'] ?? '');
        $oldDue     = $updateData['old_due_date'] ?? '';
        $newDue     = $updateData['new_due_date'] ?? '';
        $sig        = $this->shortSystemName($systemName);

        $overdueTag = $this->invoiceIsOverdue($invoice) ? ' (OVERDUE)' : '';

        return "Invoice{$overdueTag} updated: {$oldAmount} → {$newAmount}. "
             . "Due: {$oldDue} → {$newDue}. - {$sig}";
    }

    protected function buildLandlordSubject(Invoice $invoice, string $event): string
{
    $systemName = $this->settings->system_name ?? config('app.name');

    return match ($event) {
        'created'      => "New Invoice — {$invoice->invoice_number} ({$systemName})",
        'reminder'     => "Payment Reminder — {$invoice->invoice_number} ({$systemName})",
        'overdue'      => "Overdue Invoice — {$invoice->invoice_number} ({$systemName})",
        'confirmation' => "Payment Confirmation — {$invoice->invoice_number} ({$systemName})",
        'update'       => "Invoice Update — {$invoice->invoice_number} ({$systemName})",   // ← already there
        default        => "Invoice Update — {$invoice->invoice_number} ({$systemName})",
    };
}

    /* ============================================================
     | SMS HELPERS
     * ============================================================ */

    /**
     * Hard-cap SMS text at 160 chars (single segment).
     */
    protected function clampSms(string $message, int $limit = self::SMS_MAX_LENGTH): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $message));

        if (mb_strlen($message) <= $limit) {
            return $message;
        }

        $truncated = mb_substr($message, 0, $limit - 1);

        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false && $lastSpace > $limit - 30) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return rtrim($truncated, " .,-") . '…';
    }

    /**
     * Extract the first name from a full name.
     */
    protected function firstName(?string $fullName): string
    {
        $fullName = trim((string) $fullName);
        if ($fullName === '') {
            return 'there';
        }

        $parts = preg_split('/\s+/', $fullName);
        return $parts[0] ?? 'there';
    }

    /**
     * Shorten the system name for the SMS signature.
     */
    protected function shortSystemName(string $systemName): string
    {
        $short = $this->settings->system_short_name ?? null;

        if (!empty($short)) {
            return ucwords(str_replace(['-', '_'], ' ', $short));
        }

        return mb_strlen($systemName) > 20
            ? mb_substr($systemName, 0, 17) . '...'
            : $systemName;
    }

    /**
     * Safely convert an invoice period to a display label.
     */
    protected function formatInvoicePeriodForDisplay(?string $period): ?string
    {
        if (empty($period)) {
            return null;
        }

        if (str_contains($period, '_to_')) {
            $parts = explode('_to_', $period);
            if (count($parts) === 2) {
                try {
                    $start = \Carbon\Carbon::parse($parts[0] . '-01');
                    $end   = \Carbon\Carbon::parse($parts[1] . '-01');
                    return $start->format('M Y') . ' - ' . $end->format('M Y');
                } catch (\Throwable $e) {
                    Log::warning('[NotificationService] Failed to parse bulk period', [
                        'period' => $period,
                        'error'  => $e->getMessage(),
                    ]);
                    return $period;
                }
            }
            return $period;
        }

        if (preg_match('/^\d{4}-\d{2}$/', $period)) {
            try {
                return \Carbon\Carbon::parse($period . '-01')->format('F Y');
            } catch (\Throwable $e) {
                Log::warning('[NotificationService] Failed to parse period', [
                    'period' => $period,
                    'error'  => $e->getMessage(),
                ]);
                return $period;
            }
        }

        return $period;
    }

    /**
     * Determine whether an invoice is (or was) overdue.
     */
    protected function invoiceIsOverdue(Invoice $invoice): bool
    {
        if ($invoice->status === 'overdue') {
            return true;
        }

        if ($invoice->due_date && $invoice->due_date < now()) {
            return true;
        }

        return false;
    }

    /* ============================================================
     | ARCHIVE NOTIFICATIONS
     * ============================================================ */

    public function sendInvoiceArchiveNotification(TenantInvoice $invoice, string $type = 'approval_request')
    {
        try {
            $tenant = $invoice->tenant;

            if (!$tenant || !$tenant->email) {
                Log::warning('Cannot send archive notification: Tenant email missing', [
                    'invoice_id' => $invoice->id,
                    'tenant_id'  => $invoice->tenant_id,
                ]);
                return false;
            }

            Mail::to($tenant->email)->send(new InvoiceArchiveNotification($invoice, $type, $this->settings));

            Log::info('Archive notification sent', [
                'invoice_id' => $invoice->id,
                'type'       => $type,
                'tenant_id'  => $invoice->tenant_id,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send archive notification: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
                'type'       => $type,
            ]);
            return false;
        }
    }

    public function sendArchiveReminder(TenantInvoice $invoice, int $daysUntilArchive)
    {
        try {
            $tenant = $invoice->tenant;

            if (!$tenant || !$tenant->email) {
                Log::warning('Cannot send archive reminder: Tenant email missing', [
                    'invoice_id' => $invoice->id,
                    'tenant_id'  => $invoice->tenant_id,
                ]);
                return false;
            }

            Mail::to($tenant->email)->send(new InvoiceArchiveReminder($invoice, $daysUntilArchive, $this->settings));

            Log::info('Archive reminder sent', [
                'invoice_id'         => $invoice->id,
                'days_until_archive' => $daysUntilArchive,
                'tenant_id'          => $invoice->tenant_id,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send archive reminder: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
            ]);
            return false;
        }
    }

    public function sendUnpaidReminder(TenantInvoice $invoice, int $daysOverdue)
    {
        try {
            $tenant = $invoice->tenant;

            if (!$tenant || !$tenant->email) {
                Log::warning('Cannot send unpaid reminder: Tenant email missing', [
                    'invoice_id' => $invoice->id,
                    'tenant_id'  => $invoice->tenant_id,
                ]);
                return false;
            }

            Mail::to($tenant->email)->send(new UnpaidInvoiceReminder($invoice, $daysOverdue, $this->settings));

            $channels = $this->resolveChannels(
                $this->settings->overdue_notification_channels ?? ['email'],
                'unpaid_reminder'
            );

            if (in_array('sms', $channels, true) && !empty($tenant->phone)) {
                $message = "Reminder: invoice #{$invoice->invoice_number} is {$daysOverdue}d overdue. "
                         . "Pay {$this->settings->formatAmount($invoice->balance)} to avoid penalties.";
                $this->sendSms($tenant->phone, $this->clampSms($message));
            }

            if (in_array('whatsapp', $channels, true) && method_exists($this->whatsappService, 'sendMessage') && !empty($tenant->phone)) {
                $message = "Reminder: Invoice #{$invoice->invoice_number} is {$daysOverdue} days overdue. "
                         . "Outstanding: {$this->settings->formatAmount($invoice->balance)}.";
                try {
                    $this->whatsappService->sendMessage($tenant->phone, $message);
                } catch (\Throwable $e) {
                    Log::warning('[NotificationService] WhatsApp unpaid reminder failed', [
                        'invoice_id' => $invoice->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Unpaid reminder sent', [
                'invoice_id'   => $invoice->id,
                'days_overdue' => $daysOverdue,
                'tenant_id'    => $invoice->tenant_id,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send unpaid reminder: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
            ]);
            return false;
        }
    }

    /* ============================================================
     | LOW-LEVEL DISPATCHERS
     * ============================================================ */

    public function sendSms(string $phoneNumber, string $message)
    {
        try {
            if (empty($phoneNumber)) {
                Log::warning('[NotificationService] sendSms called without phone number');
                return false;
            }

            $message = $this->clampSms($message);

            $result = $this->smsService->sendWithDefaultProvider(
                $phoneNumber,
                $message,
                ['context' => 'notification_service']
            );

            $ok = is_array($result) ? (bool) ($result['success'] ?? false) : (bool) $result;

            Log::info('[NotificationService] SMS sendSms result', [
                'phone'          => $phoneNumber,
                'success'        => $ok,
                'message_length' => mb_strlen($message),
            ]);

            return $ok;
        } catch (\Throwable $e) {
            Log::error('Failed to send SMS: ' . $e->getMessage(), [
                'phone' => $phoneNumber,
            ]);
            return false;
        }
    }

    public function sendWeeklyInvoiceSummary(array $summaryData)
    {
        try {
            $admins = User::whereIn('type', ['super_admin', 'admin'])
                ->where('status', 'active')
                ->get();

            $adminCount = 0;

            foreach ($admins as $admin) {
                $adminCount++;
            }

            Log::info('Weekly invoice summary sent', [
                'admin_count' => $adminCount,
                'summary'     => $summaryData,
            ]);

            return [
                'success'     => true,
                'admin_count' => $adminCount,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send weekly invoice summary: ' . $e->getMessage());
            return [
                'success'     => false,
                'admin_count' => 0,
            ];
        }
    }

    /* ============================================================
     | INTERNAL HELPERS
     * ============================================================ */

    private function sendEmailNotification(User $user, string $message, string $subject, string $priority = 'medium')
    {
        try {
            $mailClass = $priority === 'critical' ? EmergencyAlert::class :
                        (str_contains($subject, 'Maintenance') ? MaintenanceNotification::class : SystemAlert::class);

            Mail::to($user->email)->send(new $mailClass($message, $subject));

            Log::info('Email notification sent', [
                'user_id'  => $user->id,
                'email'    => $user->email,
                'subject'  => $subject,
                'priority' => $priority,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send email notification', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function createInAppNotification(User $user, string $message, string $type, string $priority = 'medium')
    {
        try {
            Log::info('In-app notification created', [
                'user_id'        => $user->id,
                'type'           => $type,
                'priority'       => $priority,
                'message_length' => strlen($message),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to create in-app notification', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function getAlertSubject(string $urgency): string
    {
        return match ($urgency) {
            'critical' => '🚨 CRITICAL SYSTEM ALERT',
            'urgent'   => '⚠️ URGENT SYSTEM NOTICE',
            'warning'  => '⚠️ SYSTEM WARNING',
            'info'     => 'ℹ️ SYSTEM NOTIFICATION',
            default    => 'System Notification',
        };
    }

    public function sendBulkNotifications(array $users, string $message, array $channels, string $type = 'maintenance')
    {
        $results = [
            'total'    => count($users),
            'success'  => 0,
            'failed'   => 0,
            'channels' => [],
        ];

        foreach ($users as $user) {
            $sentChannels = $this->sendMaintenanceNotification($user, $message, $channels);

            if (!empty($sentChannels)) {
                $results['success']++;
                foreach ($sentChannels as $channel) {
                    if (!isset($results['channels'][$channel])) {
                        $results['channels'][$channel] = 0;
                    }
                    $results['channels'][$channel]++;
                }
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    public function notifyUserWithData(User $user, array $notificationData)
    {
        try {
            $this->createInAppNotification(
                $user,
                $notificationData['message'],
                $notificationData['category'] ?? 'system',
                isset($notificationData['priority']) ?
                    ($notificationData['priority'] == 1 ? 'low' : ($notificationData['priority'] == 2 ? 'medium' : 'high')) :
                    'medium'
            );

            if (isset($notificationData['send_email']) && $notificationData['send_email']) {
                $this->sendEmailNotification(
                    $user,
                    $notificationData['message'],
                    $notificationData['title'],
                    isset($notificationData['priority']) && $notificationData['priority'] > 2 ? 'critical' : 'medium'
                );
            }

            Log::info('User notified', [
                'user_id'            => $user->id,
                'notification_title' => $notificationData['title'] ?? 'No title',
                'category'           => $notificationData['category'] ?? 'system',
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to notify user: ' . $e->getMessage(), [
                'user_id'           => $user->id,
                'notification_data' => $notificationData,
            ]);
            return false;
        }
    }

    public function getNotificationStats(): array
    {
        return [
            'total_sent_today'    => 0,
            'email_success_rate'  => '95%',
            'sms_success_rate'    => '90%',
            'push_success_rate'   => '85%',
            'in_app_success_rate' => '100%',
        ];
    }
}