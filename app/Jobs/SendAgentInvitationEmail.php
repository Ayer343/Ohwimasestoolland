<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\RegistrationPlan;
use App\Models\User;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendAgentInvitationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $planId;
    public $agentId;
    public $invitationMethod;
    public $agentData;
    public $instructions;

    public function __construct($planId, $agentId, $invitationMethod, $agentData = null, $instructions = null)
    {
        $this->planId = $planId;
        $this->agentId = $agentId;
        $this->invitationMethod = $invitationMethod;
        $this->agentData = $agentData;
        $this->instructions = $instructions;
    }

    public function handle()
    {
        try {
            $plan = RegistrationPlan::select([
                'id', 'zone', 'section', 'estimated_houses', 
                'registration_start_date', 'registration_end_date'
            ])->find($this->planId);
            
            $agent = User::select(['id', 'name', 'email', 'phone'])
                ->find($this->agentId);

            if (!$plan || !$agent) {
                Log::warning("Queue: Plan or agent not found for email: Plan {$this->planId}, Agent {$this->agentId}");
                return;
            }

            // Get system settings
            $systemSettings = SystemSetting::getSettings();
            
            // Prepare email data for your template
            $emailData = [
                'agentName' => $agent->name,
                'planDetails' => [
                    'zone' => $plan->zone,
                    'section' => $plan->section,
                    'estimated_houses' => $plan->estimated_houses,
                    'registration_period' => $plan->registration_start_date && $plan->registration_end_date 
                        ? $plan->registration_start_date->format('M d, Y') . ' to ' . $plan->registration_end_date->format('M d, Y')
                        : 'To be determined',
                ],
                'customMessage' => $this->instructions,
                'invitationLink' => route('agent.invitations.accept', ['token' => Str::random(64)]), // Generate actual token
                'expiryDate' => now()->addDays(7)->format('F j, Y'),
                'daysUntilExpiry' => 7,
                'totalExpiryDays' => 7,
                'systemName' => $systemSettings->system_name ?? config('app.name'),
                'systemEmail' => $systemSettings->system_email ?? config('mail.from.address'),
            ];

            Mail::send(
                'emails.agent_invitation', // Your existing template
                $emailData,
                function ($message) use ($agent, $systemSettings) {
                    $message->to($agent->email)
                        ->subject('Field Agent Invitation - ' . ($systemSettings->system_name ?? config('app.name')));
                }
            );

            Log::info("Queue: Email invitation sent to agent {$this->agentId} for plan {$this->planId}");

        } catch (\Exception $e) {
            Log::error("Queue: Email invitation failed for agent {$this->agentId}: " . $e->getMessage());
        }
    }
}