<?php

namespace App\Notifications;

use App\Models\SecuritySupervisorAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class HeadOfSecurityResigned extends Notification
{
    use Queueable;

    public function __construct(
        public SecuritySupervisorAssignment $assignment,
        public string $officerName,
        public string $reason
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type'          => 'head_of_security_resigned',
            'assignment_id' => $this->assignment->id,
            'officer_name'  => $this->officerName,
            'reason'        => $this->reason,
            'title'         => 'Head of Security resigned',
            'message'       => "{$this->officerName} has resigned as Head of Security. Please appoint a successor.",
            'url'           => route('admin.supervisor-assignments.show', $this->assignment->id),
        ];
    }
}