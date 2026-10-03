<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;

class AdminPaymentAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public $payment;
    public $alertType;
    public $message;

    const TYPE_NEW_PAYMENT = 'new_payment';
    const TYPE_VERIFICATION_REQUIRED = 'verification_required';
    const TYPE_PAYMENT_COMPLETED = 'payment_completed';
    const TYPE_PAYMENT_FAILED = 'payment_failed';
    const TYPE_SUSPICIOUS_ACTIVITY = 'suspicious_activity';

    /**
     * Create a new notification instance.
     */
    public function __construct(Payment $payment, string $alertType, ?string $message = null)
    {
        $this->payment = $payment;
        $this->alertType = $alertType;
        $this->message = $message ?? $this->getDefaultMessage($alertType);
    }

    /**
     * Get default message based on alert type.
     */
    protected function getDefaultMessage(string $alertType): string
    {
        return match($alertType) {
            self::TYPE_NEW_PAYMENT => 'New payment requires attention',
            self::TYPE_VERIFICATION_REQUIRED => 'Payment verification required',
            self::TYPE_PAYMENT_COMPLETED => 'Payment completed successfully',
            self::TYPE_PAYMENT_FAILED => 'Payment failed',
            self::TYPE_SUSPICIOUS_ACTIVITY => 'Suspicious payment activity detected',
            default => 'Payment alert'
        };
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast', 'slack'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->getSubject())
            ->greeting('Admin Alert: ' . $this->getSubject());

        switch ($this->alertType) {
            case self::TYPE_NEW_PAYMENT:
                $mail->line('A new payment requires admin attention.')
                    ->action('Review Payment', route('admin.payments.show', $this->payment->id));
                break;

            case self::TYPE_VERIFICATION_REQUIRED:
                $mail->line('A payment requires verification.')
                    ->action('Verify Payment', route('admin.payments.show', $this->payment->id));
                break;

            case self::TYPE_SUSPICIOUS_ACTIVITY:
                $mail->error()
                    ->line('SUSPICIOUS ACTIVITY DETECTED!')
                    ->line('Please review this payment immediately.')
                    ->action('Investigate Now', route('admin.payments.show', $this->payment->id));
                break;

            default:
                $mail->line($this->message);
        }

        $mail->line('')
            ->line('**Transaction Details:**')
            ->line('- Transaction ID: ' . $this->payment->transaction_id)
            ->line('- Amount: ' . $this->payment->formatted_amount)
            ->line('- Method: ' . ucfirst($this->payment->payment_method))
            ->line('- Landlord: ' . $this->payment->landlord->name)
            ->line('- Property: ' . $this->payment->property->getAddress());

        return $mail;
    }

    /**
     * Get the subject line for the email.
     */
    protected function getSubject(): string
    {
        return match($this->alertType) {
            self::TYPE_NEW_PAYMENT => 'New Payment - Requires Attention',
            self::TYPE_VERIFICATION_REQUIRED => 'Payment Verification Required',
            self::TYPE_PAYMENT_COMPLETED => 'Payment Completed Successfully',
            self::TYPE_PAYMENT_FAILED => 'Payment Failed',
            self::TYPE_SUSPICIOUS_ACTIVITY => '🚨 SUSPICIOUS PAYMENT ACTIVITY',
            default => 'Payment Alert'
        };
    }

    /**
     * Get the broadcast representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'title' => $this->getSubject(),
            'message' => $this->message,
            'transaction_id' => $this->payment->transaction_id,
            'alert_type' => $this->alertType,
            'urgency' => $this->getUrgencyLevel(),
            'url' => route('admin.payments.show', $this->payment->id)
        ]);
    }

    /**
     * Get urgency level based on alert type.
     */
    protected function getUrgencyLevel(): string
    {
        return match($this->alertType) {
            self::TYPE_SUSPICIOUS_ACTIVITY => 'critical',
            self::TYPE_PAYMENT_FAILED => 'high',
            self::TYPE_VERIFICATION_REQUIRED => 'medium',
            default => 'normal'
        };
    }

    /**
     * Get the Slack representation of the notification.
     */
    public function toSlack(object $notifiable): SlackMessage
    {
        $slack = (new SlackMessage)
            ->content($this->getSubject());

        if ($this->alertType === self::TYPE_SUSPICIOUS_ACTIVITY) {
            $slack->error();
        }

        return $slack->attachment(function ($attachment) {
            $attachment->title('Payment Alert Details')
                ->fields([
                    'Transaction ID' => $this->payment->transaction_id,
                    'Amount' => $this->payment->formatted_amount,
                    'Landlord' => $this->payment->landlord->name,
                    'Property' => $this->payment->property->getAddress(),
                    'Alert Type' => ucfirst(str_replace('_', ' ', $this->alertType)),
                    'Message' => $this->message
                ]);
        });
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'admin_payment_alert',
            'alert_type' => $this->alertType,
            'transaction_id' => $this->payment->transaction_id,
            'payment_id' => $this->payment->id,
            'landlord_id' => $this->payment->landlord_id,
            'property_id' => $this->payment->property_id,
            'amount' => $this->payment->amount,
            'message' => $this->message,
            'urgency' => $this->getUrgencyLevel(),
            'action_required' => in_array($this->alertType, [
                self::TYPE_NEW_PAYMENT,
                self::TYPE_VERIFICATION_REQUIRED,
                self::TYPE_SUSPICIOUS_ACTIVITY
            ])
        ];
    }
}