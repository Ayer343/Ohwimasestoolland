<?php

namespace App\Notifications;

use App\Models\ConstructionContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConstructionContractApproved extends Notification implements ShouldQueue
{
    use Queueable;

    protected $contract;
    protected $adminNotes;

    /**
     * Create a new notification instance.
     */
    public function __construct(ConstructionContract $contract, $adminNotes = null)
    {
        $this->contract = $contract;
        $this->adminNotes = $adminNotes;
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
        $mail = (new MailMessage)
            ->subject('Construction Contract Approved - ' . $this->contract->contract_number)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your construction contract has been approved by the admin.')
            ->line('**Contract Number:** ' . $this->contract->contract_number)
            ->line('**Contract Title:** ' . $this->contract->title)
            ->line('**Property:** ' . ($this->contract->property->property_name ?? 'N/A'))
            ->line('**Contractor:** ' . $this->contract->contractor_name)
            ->line('**Contract Amount:** ₵' . number_format($this->contract->contract_amount, 2))
            ->line('**Start Date:** ' . ($this->contract->contract_start_date ? $this->contract->contract_start_date->format('F d, Y') : 'N/A'))
            ->line('**Estimated Completion:** ' . ($this->contract->estimated_completion_date ? $this->contract->estimated_completion_date->format('F d, Y') : 'N/A'));

        if ($this->adminNotes) {
            $mail->line('**Admin Notes:** ' . $this->adminNotes);
        }

        $mail->action('View Contract', route('landlord.construction.contract.show', $this->contract))
             ->line('You can now proceed with the construction work.')
             ->line('Thank you for using our platform!');

        return $mail;
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
            'message' => 'Your construction contract has been approved.',
            'admin_notes' => $this->adminNotes,
            'type' => 'contract_approved',
            'status' => 'approved'
        ];
    }
}