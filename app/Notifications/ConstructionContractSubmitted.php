<?php

namespace App\Notifications;

use App\Models\ConstructionContract;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ConstructionContractSubmitted extends Notification
{
    use Queueable;

    protected $contract;

    public function __construct(ConstructionContract $contract)
    {
        $this->contract = $contract;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $mailMessage = (new MailMessage)
            ->subject('New Construction Contract Submitted for Approval')
            ->greeting('Hello Admin!')
            ->line('A new construction contract has been submitted by a landlord.')
            ->line('')
            ->line('**Contract Details:**')
            ->line('- Contract #: ' . $this->contract->contract_number)
            ->line('- Title: ' . $this->contract->title)
            ->line('- Landlord: ' . ($this->contract->landlord->name ?? 'N/A'))
            ->line('- Contractor: ' . $this->contract->contractor_name)
            ->line('- Property: ' . ($this->contract->property->property_name ?? 'N/A'))
            ->line('- Contract Amount: ' . ($this->contract->contract_amount ? '₵' . number_format($this->contract->contract_amount, 2) : 'Not specified'))
            ->line('- Start Date: ' . ($this->contract->contract_start_date ? $this->contract->contract_start_date->format('M d, Y') : 'N/A'))
            ->line('- Estimated Completion: ' . ($this->contract->estimated_completion_date ? $this->contract->estimated_completion_date->format('M d, Y') : 'N/A'))
            ->line('')
            ->action('View Contract', route('admin.construction.contracts.show', $this->contract))
            ->line('Please review and approve the contract.');

        // Add work scope summary if available
        if ($this->contract->work_scope) {
            $workScope = json_decode($this->contract->work_scope, true);
            if (!empty($workScope)) {
                $mailMessage->line('')
                    ->line('**Work Scope:**')
                    ->line('- ' . implode("\n- ", array_slice($workScope, 0, 5)));
                if (count($workScope) > 5) {
                    $mailMessage->line('... and ' . (count($workScope) - 5) . ' more items');
                }
            }
        }

        return $mailMessage;
    }

    public function toDatabase($notifiable)
    {
        $data = [
            'contract_id' => $this->contract->id,
            'contract_number' => $this->contract->contract_number,
            'title' => $this->contract->title,
            'landlord_name' => $this->contract->landlord->name ?? 'N/A',
            'contractor_name' => $this->contract->contractor_name,
            'message' => 'New contract ' . $this->contract->contract_number . ' submitted for approval',
            'url' => route('admin.construction.contracts.show', $this->contract),
        ];

        // Add work scope summary if available
        if ($this->contract->work_scope) {
            $workScope = json_decode($this->contract->work_scope, true);
            if (!empty($workScope)) {
                $data['work_scope_summary'] = implode(', ', array_slice($workScope, 0, 3));
                $data['total_work_items'] = count($workScope);
            }
        }

        return $data;
    }
}