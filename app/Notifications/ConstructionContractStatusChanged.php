<?php

namespace App\Notifications;

use App\Models\ConstructionContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConstructionContractStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    protected $contract;
    protected $action;
    protected $data;

    /**
     * Create a new notification instance.
     */
    public function __construct(ConstructionContract $contract, $action, array $data = [])
    {
        $this->contract = $contract;
        $this->action = $action;
        $this->data = $data;
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
        $statusMessages = [
            'started' => 'Work has started on your construction contract.',
            'paused' => 'Work has been paused on your construction contract.',
            'resumed' => 'Work has been resumed on your construction contract.',
            'completed' => 'Your construction contract has been marked as completed.',
            'in_progress' => 'Your construction contract is now in progress.',
            'on_hold' => 'Your construction contract has been put on hold.',
        ];

        $message = $statusMessages[$this->action] ?? 'The status of your contract has been updated.';

        $mail = (new MailMessage)
            ->subject('Construction Contract Status Updated - ' . $this->contract->contract_number)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line($message)
            ->line('**Contract Number:** ' . $this->contract->contract_number)
            ->line('**Contract Title:** ' . $this->contract->title)
            ->line('**New Status:** ' . ucfirst(str_replace('_', ' ', $this->contract->status)));

        // Add additional data based on action
        if ($this->action === 'paused' && isset($this->data['reason'])) {
            $mail->line('**Pause Reason:** ' . $this->data['reason']);
        }

        if ($this->action === 'completed' && isset($this->data['notes'])) {
            $mail->line('**Completion Notes:** ' . $this->data['notes']);
        }

        $mail->action('View Contract', route('landlord.construction.contract.show', $this->contract))
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
            'action' => $this->action,
            'message' => 'Contract status changed to: ' . ucfirst(str_replace('_', ' ', $this->contract->status)),
            'status' => $this->contract->status,
            'type' => 'contract_status_changed',
            'data' => $this->data
        ];
    }
}