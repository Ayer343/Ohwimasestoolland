<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class PlanAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $planId,
        public string $zone,
        public ?string $section,
        public ?string $startDate,
        public ?string $endDate,
        public string $namingPattern,
        public int $estimatedHouses,
        public string $assignedByName,
        public ?string $instructions = null,
    ) {}

    /**
     * Database only — the invitation is delivered via SMS/Email.
     * This entry is for the in-app notification bell.
     */
    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $location = $this->zone . ($this->section ? " - {$this->section}" : '');

        return [
            // Blade "row" fields
            'title'      => '📍 New Registration Plan Assigned',
            'message'    => "You have been assigned to plan #{$this->planId} ({$location})."
                            . " Plan runs from {$this->startDate} to {$this->endDate}.",
            'icon'       => 'fas fa-map-marked-alt',
            'category'   => 'plan_assignment',
            'priority'   => 2, // 2 = Medium/High (renders a warning flag)
            'action_url' => route('field-agent.properties.create', ['plan_id' => $this->planId]),

            // Role filter — the shared controller matches this via whereJsonContains
            'roles'      => 'field-agent',

            // Extra metadata (optional, for audit / deep links)
            'data' => [
                'type'              => 'plan_assignment',
                'plan_id'           => $this->planId,
                'zone'              => $this->zone,
                'section'           => $this->section,
                'naming_pattern'    => $this->namingPattern,
                'estimated_houses'  => $this->estimatedHouses,
                'assigned_by_name'  => $this->assignedByName,
                'instructions'      => $this->instructions,
            ],
        ];
    }
}