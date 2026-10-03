<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\SystemSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class InvoiceGeneratedNotification extends Notification
{
    use Queueable;

    protected $invoice;
    protected $settings;

    public function __construct(Invoice $invoice)
    {
        $this->invoice  = $invoice;
        $this->settings = SystemSetting::getSettings();
    }

    /**
     * Delivery channels for this notification.
     *
     * IMPORTANT — Do NOT add 'mail' or 'sms' back to this array.
     *
     * Email, SMS, and WhatsApp for the "invoice created" event are now
     * dispatched by App\Services\NotificationService::sendLandlordInvoiceCreated(),
     * which reads the channel arrays from System Settings
     * (invoice_notification_channels, sms_notifications_enabled,
     * enable_whatsapp_notifications, etc.).
     *
     * This notification class now handles ONLY:
     *   - 'database'  → the in-app notifications table (bell icon)
     *   - 'broadcast' → real-time delivery via Pusher/Echo
     *
     * Adding 'mail' or 'sms' here would cause duplicate emails/SMS.
     */
    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /* ============================================================
     | EMAIL — retained for reference only; not currently dispatched
     | because 'mail' is not in via().
     |
     | If you ever want to move email back into this notification,
     | re-add 'mail' to via() AND remove the NotificationService call
     | from InvoiceService::sendInvoiceNotification() so you don't
     | double-send.
     * ============================================================ */

    public function toMail($notifiable): MailMessage
    {
        $formattedAmount = $this->settings->formatAmount($this->invoice->amount);
        $propertyName    = $this->getPropertyName();

        return (new MailMessage)
            ->subject("💰 New Invoice Generated - {$this->invoice->period}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A new invoice has been generated for your property.")
            ->line("**Property:** {$propertyName}")
            ->line("**Period:** {$this->invoice->period}")
            ->line("**Amount:** {$formattedAmount}")
            ->line("**Due Date:** {$this->invoice->due_date->format('F d, Y')}")
            ->line("")
            ->line("**Payment Instructions:**")
            ->line("All payments must be sent to:")
            ->line("**Recipient:** {$this->settings->payment_account_name}")
            ->line("**Phone:** {$this->settings->payment_mobile_number}")
            ->line("**Network:** {$this->settings->payment_network}")
            ->action('View Invoice', route('landlord.invoices.show', $this->invoice))
            ->line('Thank you for your prompt payment!');
    }

    /* ============================================================
     | DATABASE — the in-app notification record
     * ============================================================ */

    public function toArray($notifiable): array
    {
        return [
            'invoice_id'  => $this->invoice->id,
            'property_id' => $this->invoice->property_id,
            'period'      => $this->invoice->period,
            'amount'      => $this->invoice->amount,
            'due_date'    => $this->invoice->due_date,
            'message'     => "New invoice generated for {$this->getPropertyName()} - {$this->invoice->period}",
            'action_url'  => route('landlord.invoices.show', $this->invoice),
            'type'        => 'invoice_generated',
            'icon'        => 'fas fa-file-invoice text-success',
            'category'    => 'invoices',
            'priority'    => 2,
        ];
    }

    /* ============================================================
     | BROADCAST — real-time payload (Pusher/Echo)
     * ============================================================ */

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /* ============================================================
     | HELPERS
     * ============================================================ */

    /**
     * Resolve the property display name safely.
     *
     * Property exposes `property_name` (the actual DB column) but no
     * universal `->name` accessor. Fall back through the safe options
     * instead of crashing on the notification payload build.
     */
    protected function getPropertyName(): string
    {
        $property = $this->invoice->property;

        if (!$property) {
            return 'your property';
        }

        return $property->property_name
            ?? $property->street_name
            ?? $property->name
            ?? 'your property';
    }
}