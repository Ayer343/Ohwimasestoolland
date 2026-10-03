<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class PaymentConfirmation extends Notification implements ShouldQueue
{
    use Queueable;

    public $payment;

    /**
     * Create a new notification instance.
     */
    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Confirmed - ' . config('app.name'))
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your payment has been successfully confirmed.')
            ->line('**Payment Details:**')
            ->line('- Amount: ' . $this->payment->formatted_amount)
            ->line('- Transaction ID: ' . $this->payment->transaction_id)
            ->line('- Payment Method: ' . ucfirst($this->payment->payment_method))
            ->line('- Property: ' . $this->payment->property->getAddress())
            ->line('- Date: ' . $this->payment->payment_date->format('M d, Y H:i'))
            ->line('')
            ->line('**Invoices Paid:**')
            ->line($this->getInvoicesSummary())
            ->action('View Payment Details', route('landlord.payments.confirmation', $this->payment->transaction_id))
            ->line('Thank you for your payment!');
    }

    /**
     * Get invoices summary.
     */
    protected function getInvoicesSummary(): string
    {
        if ($this->payment->invoices->count() === 0) {
            return 'No specific invoices';
        }

        return $this->payment->invoices->map(function ($invoice) {
            return $invoice->period . ' - ' . $invoice->formatted_amount;
        })->implode("\n");
    }

    /**
     * Get the broadcast representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'title' => 'Payment Confirmed',
            'message' => 'Your payment of ' . $this->payment->formatted_amount . ' has been confirmed',
            'transaction_id' => $this->payment->transaction_id,
            'amount' => $this->payment->formatted_amount,
            'url' => route('landlord.payments.confirmation', $this->payment->transaction_id)
        ]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_confirmation',
            'transaction_id' => $this->payment->transaction_id,
            'amount' => $this->payment->amount,
            'payment_method' => $this->payment->payment_method,
            'property_id' => $this->payment->property_id,
            'invoices_count' => $this->payment->invoices->count(),
            'message' => 'Payment confirmed successfully'
        ];
    }
}