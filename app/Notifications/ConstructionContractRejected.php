<?php

namespace App\Notifications;

use App\Models\ConstructionContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConstructionContractRejected extends Notification implements ShouldQueue
{
    use Queueable;

    protected $contract;
    protected $reason;

    /**
     * Create a new notification instance.
     */
    public function __construct(ConstructionContract $contract, $reason = 'No reason provided')
    {
        $this->contract = $contract;
        $this->reason = $reason;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Construction Contract Rejected - ' . $this->contract->contract_number)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your construction contract has been rejected by the admin.')
            ->line('**Contract Number:** ' . $this->contract->contract_number)
            ->line('**Contract Title:** ' . $this->contract->title)
            ->line('**Rejection Reason:** ' . $this->reason)
            ->line('Please review the feedback and resubmit with the necessary changes.')
            ->action('View Contract', route('landlord.construction.contract.show', $this->contract))
            ->line('If you have questions, please contact support.')
            ->line('Thank you for using our platform!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable)
    {
        return [
            'contract_id' => $this->contract->id,
            'contract_number' => $this->contract->contract_number,
            'title' => $this->contract->title,
            'message' => 'Your construction contract has been rejected.',
            'reason' => $this->reason,
            'type' => 'contract_rejected',
            'status' => 'rejected'
        ];
    }
}