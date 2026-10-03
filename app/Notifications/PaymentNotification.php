<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $payment;
    protected $userType;

    public function __construct($payment, $userType = 'tenant')
    {
        $this->payment = $payment;
        $this->userType = $userType;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $amount = $this->payment->amount ?? 0;
        $status = $this->payment->status ?? 'pending';
        $reference = $this->payment->reference ?? 'N/A';
        
        $title = $this->userType === 'tenant' 
            ? 'Payment Successful' 
            : 'New Payment Received';
            
        $message = $this->userType === 'tenant'
            ? "Your payment of GH₵{$amount} has been processed successfully. Reference: {$reference}"
            : "You have received a payment of GH₵{$amount} from tenant. Reference: {$reference}";

        return [
            'title' => $title,
            'message' => $message,
            'payment' => [
                'id' => $this->payment->id,
                'amount' => $amount,
                'currency' => 'GHS',
                'status' => $status,
                'reference' => $reference,
                'date' => $this->payment->created_at?->format('M j, Y'),
            ],
            'action_url' => "/payments/{$this->payment->id}",
            'icon' => 'fas fa-money-bill-wave',
            'category' => 'payments',
            'priority' => $status === 'success' ? 0 : 1,
            'metadata' => [
                'type' => 'payment',
                'user_type' => $this->userType,
                'payment_status' => $status,
                'amount' => $amount,
            ]
        ];
    }
}