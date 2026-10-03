<?php

namespace App\Notifications;

use App\Models\ConstructionContract;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ContractorInvitationSent extends Notification implements ShouldQueue
{
    use Queueable;

    protected ConstructionContract $contract;
    protected User $contractorUser;
    protected array $channels;

    /**
     * Create a new notification instance.
     */
    public function __construct(ConstructionContract $contract, User $contractorUser, array $channels)
    {
        $this->contract = $contract;
        $this->contractorUser = $contractorUser;
        $this->channels = $channels;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        // For admins, we typically send via mail and database
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $channelsList = implode(', ', $this->channels);
        
        return (new MailMessage)
            ->subject('Contractor Invitation Sent - ' . $this->contract->contract_number)
            ->greeting('Hello ' . $notifiable->name)
            ->line('A contractor invitation has been sent for the following contract:')
            ->line('**Contract Number:** ' . $this->contract->contract_number)
            ->line('**Contract Title:** ' . $this->contract->title)
            ->line('**Contractor Name:** ' . $this->contract->contractor_name)
            ->line('**Contractor Email:** ' . ($this->contractorUser->email ?? 'N/A'))
            ->line('**Contractor Phone:** ' . ($this->contractorUser->phone ?? 'N/A'))
            ->line('**Invitation Channels:** ' . $channelsList)
            ->line('**Invitation Sent At:** ' . now()->format('Y-m-d H:i:s'))
            ->action('View Contract', url('/admin/construction/contracts/' . $this->contract->id))
            ->line('The contractor will receive an invitation to set up their account and access the system.')
            ->line('Please ensure the contractor has received the invitation and can access the system.');
    }

    /**
     * Get the array representation of the notification for database storage.
     */
    public function toDatabase($notifiable): array
    {
        return [
            'contract_id' => $this->contract->id,
            'contract_number' => $this->contract->contract_number,
            'contract_title' => $this->contract->title,
            'contractor_user_id' => $this->contractorUser->id,
            'contractor_name' => $this->contractorUser->name,
            'contractor_email' => $this->contractorUser->email,
            'channels' => $this->channels,
            'message' => 'Contractor invitation sent for contract ' . $this->contract->contract_number,
            'type' => 'contractor_invitation_sent'
        ];
    }

    /**
     * Get the array representation of the notification for API responses.
     */
    public function toArray($notifiable): array
    {
        return [
            'contract_id' => $this->contract->id,
            'contract_number' => $this->contract->contract_number,
            'contractor_user_id' => $this->contractorUser->id,
            'channels' => $this->channels,
        ];
    }
}