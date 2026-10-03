<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Messages\SlackMessage;

class PaymentVerificationRequired extends Notification implements ShouldQueue
{
    use Queueable;

    public $payment;
    public $verificationCode;

    /**
     * Create a new notification instance.
     */
    public function __construct(Payment $payment, string $verificationCode)
    {
        $this->payment = $payment;
        $this->verificationCode = $verificationCode;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail', 'database'];
        
        // Add SMS if phone number exists
        if ($notifiable->phone) {
            $channels[] = 'vonage';
        }
        
        // Add broadcast if user is online
        $channels[] = 'broadcast';
        
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Verification Required - ' . config('app.name'))
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your mobile money payment requires verification.')
            ->line('**Transaction Details:**')
            ->line('- Amount: ' . $this->payment->formatted_amount)
            ->line('- Transaction ID: ' . $this->payment->transaction_id)
            ->line('- Property: ' . $this->payment->property->getAddress())
            ->line('')
            ->line('**Verification Code:**')
            ->line('## ' . $this->verificationCode . ' ##')
            ->line('')
            ->line('This code will expire in 5 minutes.')
            ->action('Verify Payment Now', route('landlord.payments.verify.form', $this->payment->transaction_id))
            ->line('If you did not initiate this payment, please contact support immediately.');
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toVonage(object $notifiable): VonageMessage
    {
        return (new VonageMessage)
            ->content(
                "Your payment verification code: {$this->verificationCode}\n" .
                "For transaction: {$this->payment->transaction_id}\n" .
                "Amount: {$this->payment->formatted_amount}\n" .
                "Code expires in 5 minutes."
            );
    }

    /**
     * Get the broadcast representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'title' => 'Payment Verification Required',
            'message' => 'Please verify your mobile money payment',
            'transaction_id' => $this->payment->transaction_id,
            'amount' => $this->payment->formatted_amount,
            'url' => route('landlord.payments.verify.form', $this->payment->transaction_id)
        ]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_verification_required',
            'transaction_id' => $this->payment->transaction_id,
            'amount' => $this->payment->amount,
            'property_id' => $this->payment->property_id,
            'verification_code' => $this->verificationCode,
            'expires_at' => now()->addMinutes(5),
            'message' => 'Mobile money payment requires verification. Code: ' . $this->verificationCode
        ];
    }

    /**
     * Get the Slack representation of the notification.
     */
    public function toSlack(object $notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->content('Payment verification required for ' . $notifiable->name)
            ->attachment(function ($attachment) {
                $attachment->title('Transaction Details')
                    ->fields([
                        'Transaction ID' => $this->payment->transaction_id,
                        'Amount' => $this->payment->formatted_amount,
                        'Property' => $this->payment->property->getAddress(),
                        'Verification Code' => $this->verificationCode
                    ]);
            });
    }
}