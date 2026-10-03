<?php

namespace App\Http\Controllers;

use App\Models\RegistrationPlan;
use App\Notifications\RegistrationPlanStatusNotification;
use App\Services\SmsService;
use App\Services\SmsTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RegistrationPlanStatusController extends Controller
{
    protected $smsService;
    protected $smsTemplateService;

    public function __construct(
        SmsService $smsService,
        SmsTemplateService $smsTemplateService
    ) {
        $this->smsService = $smsService;
        $this->smsTemplateService = $smsTemplateService;
    }

    /**
     * Mark plan as in progress.
     */
    public function markInProgress($id)
    {
        $registrationPlan = RegistrationPlan::findOrFail($id);

        if ($registrationPlan->status !== 'assigned') {
            return back()->with('warning', 'Only assigned plans can be marked as in progress.');
        }

        DB::beginTransaction();

        try {
            $registrationPlan->update(['status' => 'in_progress']);

            // Send SMS notification to all assigned agents (keep for in-progress)
            $smsResults = $this->sendPlanStatusSMSToAllAgents($registrationPlan, 'in_progress');
            
            DB::commit();

            $successCount = count(array_filter($smsResults, fn($result) => $result['success']));
            $totalAgents = count($smsResults);
            
            $smsInfo = $successCount > 0 ? 
                " SMS sent to {$successCount}/{$totalAgents} agents." : 
                " SMS failed to send to all agents.";

            return back()->with('success', 'Plan marked as in progress.' . $smsInfo);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to mark plan as in progress. Please try again.');
        }
    }

    /**
     * Mark plan as completed.
     */
    public function markCompleted($id)
    {
        $registrationPlan = RegistrationPlan::findOrFail($id);

        if (!in_array($registrationPlan->status, ['assigned', 'in_progress'])) {
            return back()->with('warning', 'Only assigned or in-progress plans can be completed.');
        }

        DB::beginTransaction();

        try {
            // Verify all estimated houses are registered before completion
            $registeredCount = $registrationPlan->properties()->count();
            $estimatedCount = $registrationPlan->estimated_houses;
            
            if ($registeredCount < $estimatedCount) {
                return back()->with('warning', 
                    "Cannot complete plan. Only {$registeredCount} out of {$estimatedCount} houses have been registered.");
            }

            $registrationPlan->update(['status' => 'completed']);

            // Send in-app notifications for all assigned agents instead of SMS
            $this->sendInAppNotifications($registrationPlan, 'completed');

            DB::commit();

            return back()->with('success', 'Plan marked as completed successfully! In-app notifications sent to assigned agents.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()->with('error', 'Failed to complete plan. Please try again.');
        }
    }

    /**
     * Cancel the registration plan.
     */
    public function cancel($id)
    {
        $registrationPlan = RegistrationPlan::findOrFail($id);

        if (in_array($registrationPlan->status, ['completed', 'cancelled'])) {
            return back()->with('warning', 'Plan is already completed or cancelled.');
        }

        DB::beginTransaction();

        try {
            $registrationPlan->update(['status' => 'cancelled']);

            // Send in-app notifications for all assigned agents instead of SMS
            $this->sendInAppNotifications($registrationPlan, 'cancelled');

            DB::commit();

            return back()->with('success', 'Plan cancelled successfully. In-app notifications sent to assigned agents.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to cancel plan. Please try again.');
        }
    }

    /**
     * Reactivate a cancelled plan.
     */
    public function reactivate($id)
    {
        $registrationPlan = RegistrationPlan::findOrFail($id);

        if ($registrationPlan->status !== 'cancelled') {
            return back()->with('warning', 'Only cancelled plans can be reactivated.');
        }

        // Check if there's an active plan for the same zone/section
        $existingActivePlan = RegistrationPlan::where('zone', $registrationPlan->zone)
            ->where('section', $registrationPlan->section)
            ->where('id', '!=', $registrationPlan->id)
            ->whereIn('status', ['draft', 'assigned', 'in_progress'])
            ->exists();

        if ($existingActivePlan) {
            return back()->with('error', 'Cannot reactivate this plan. There is already an active plan for the same zone and section.');
        }

        $registrationPlan->update(['status' => 'assigned']);

        return back()->with('success', 'Plan reactivated successfully.');
    }

    /**
     * Send in-app notifications to all assigned agents using Laravel's notification system
     */
    private function sendInAppNotifications(RegistrationPlan $plan, $status)
    {
        $activeAssignments = $plan->assignedAgents->where('is_active', true);

        foreach ($activeAssignments as $assignment) {
            $agent = $assignment->agent;
            if (!$agent) {
                continue;
            }

            // Determine notification message based on status
            $message = $this->getStatusNotificationMessage($status, $plan);
            
            // Send notification using Laravel's built-in notification system
            $agent->notify(new RegistrationPlanStatusNotification(
                $plan, 
                $status, 
                $message
            ));

            Log::info("In-app notification sent to agent {$agent->id}", [
                'plan_id' => $plan->id,
                'status' => $status,
                'notification_type' => 'database'
            ]);
        }
    }

    /**
     * Get notification message based on status
     */
    private function getStatusNotificationMessage($status, RegistrationPlan $plan)
    {
        $zoneSection = $plan->zone . ($plan->section ? " - {$plan->section}" : '');
        
        switch ($status) {
            case 'completed':
                return "Registration plan for {$zoneSection} has been marked as completed. All properties have been successfully registered.";
            
            case 'cancelled':
                return "Registration plan for {$zoneSection} has been cancelled. No further action is required.";
            
            case 'in_progress':
                return "Registration plan for {$zoneSection} is now in progress. Please continue with property registrations.";
            
            default:
                return "Registration plan for {$zoneSection} status has been updated to {$status}.";
        }
    }

    /**
     * Send plan status SMS to all assigned agents (only used for in-progress)
     */
    private function sendPlanStatusSMSToAllAgents(RegistrationPlan $plan, $status)
    {
        $results = [];
        $activeAssignments = $plan->assignedAgents->where('is_active', true);

        foreach ($activeAssignments as $assignment) {
            $agent = $assignment->agent;
            if (!$agent || !$agent->phone) {
                $results[] = [
                    'success' => false,
                    'message' => 'Agent phone number not found',
                    'agent_id' => $agent->id ?? null
                ];
                continue;
            }

            // Use SMS template service for status messages
            $message = $this->smsTemplateService->generateStatusMessage($status, $plan);
            
            // Log message info
            $messageInfo = $this->smsTemplateService->getMessageInfo($message);
            Log::info("Status SMS generated for agent {$agent->id}", [
                'status' => $status,
                'length' => $messageInfo['length'],
                'fits' => $messageInfo['fits']
            ]);

            $smsResult = $this->smsService->sendWithDefaultProvider($agent->phone, $message, [
                'is_test' => false,
                'plan_id' => $plan->id,
                'status_update' => $status,
                'agent_id' => $agent->id,
                'message_length' => $messageInfo['length']
            ]);

            $results[] = array_merge($smsResult, [
                'agent_id' => $agent->id,
                'message_length' => $messageInfo['length'],
                'message_fits' => $messageInfo['fits']
            ]);
        }

        return $results;
    }
}