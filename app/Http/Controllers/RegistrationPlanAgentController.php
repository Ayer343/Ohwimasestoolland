<?php

namespace App\Http\Controllers;

use App\Models\RegistrationPlan;
use App\Models\PlanAgentAssignment;
use App\Models\User;
use App\Services\AgentInvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RegistrationPlanAgentController extends Controller
{
    protected $agentInvitationService;

    public function __construct(AgentInvitationService $agentInvitationService)
    {
        $this->agentInvitationService = $agentInvitationService;
    }

    /**
     * Add agent to plan
     */
    public function addAgent(Request $request, $id)
    {
        $registrationPlan = RegistrationPlan::findOrFail($id);

        $validated = $request->validate([
            'agent_id' => 'nullable|exists:users,id',
            'agent_phone' => 'nullable|string|max:20',
            'agent_name' => 'required_with:agent_phone|string|max:255',
            'agent_email' => 'nullable|email',
            'invitation_method' => 'required|in:sms,whatsapp,email,all_channels',
        ]);

        DB::beginTransaction();

        try {
            $agentId = null;
            $newAgentCreated = false;

            if (!empty($validated['agent_id'])) {
                // Use existing agent
                $agent = User::find($validated['agent_id']);
                if (!$agent || $agent->type != User::TYPE_FIELD_AGENT) {
                    return back()->with('error', 'The assigned user must be a field agent.');
                }
                $agentId = $validated['agent_id'];
            } elseif (!empty($validated['agent_phone'])) {
                // Create new field agent via invitation system
                $agentResult = $this->agentInvitationService->findOrCreateAgent([
                    'phone' => $validated['agent_phone'],
                    'name' => $validated['agent_name'],
                    'email' => $validated['agent_email'] ?? null,
                ]);
                
                if (!$agentResult['success']) {
                    return back()->with('error', $agentResult['message']);
                }
                
                $agentId = $agentResult['agent_id'];
                $newAgentCreated = true;
            } else {
                return back()->with('error', 'Either agent ID or phone number is required.');
            }

            // Check if agent is already assigned
            $existingAssignment = PlanAgentAssignment::where('plan_id', $registrationPlan->id)
                ->where('agent_id', $agentId)
                ->where('is_active', true)
                ->first();

            if ($existingAssignment) {
                return back()->with('warning', 'This agent is already assigned to the plan.');
            }

            // Create assignment
            $assignment = PlanAgentAssignment::create([
                'plan_id' => $registrationPlan->id,
                'agent_id' => $agentId,
                'assigned_by' => Auth::id(),
            ]);

            // Send invitation
            $invitationResult = $this->agentInvitationService->sendMultiChannelInvitationWithTokenConsistency(
                $registrationPlan->id,
                $agentId,
                [$validated['invitation_method']],
                null
            );

            DB::commit();

            $message = 'Agent added to plan successfully.';
            if ($invitationResult['success']) {
                $successCount = count($invitationResult['channels_successful']);
                $message .= " Invitations sent via {$successCount} channels.";
            } else {
                $message .= ' But all invitation channels failed.';
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to add agent to plan: ' . $e->getMessage());
            return back()->with('error', 'Failed to add agent to plan. Please try again.');
        }
    }

    /**
     * Remove agent from plan
     */
    public function removeAgent($planId, $agentId)
    {
        $registrationPlan = RegistrationPlan::findOrFail($planId);

        DB::beginTransaction();

        try {
            $assignment = PlanAgentAssignment::where('plan_id', $registrationPlan->id)
                ->where('agent_id', $agentId)
                ->where('is_active', true)
                ->firstOrFail();

            // Use model method to mark as inactive
            $assignment->markAsInactive('Manually removed by admin');

            DB::commit();

            return back()->with('success', 'Agent removed from plan successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to remove agent from plan: ' . $e->getMessage());
            return back()->with('error', 'Failed to remove agent from plan. Please try again.');
        }
    }

    /**
     * Reactivate agent assignment
     */
    public function reactivateAgent($planId, $agentId)
    {
        $registrationPlan = RegistrationPlan::findOrFail($planId);

        DB::beginTransaction();

        try {
            $assignment = PlanAgentAssignment::where('plan_id', $registrationPlan->id)
                ->where('agent_id', $agentId)
                ->where('is_active', false)
                ->firstOrFail();

            // Use model method to reactivate
            $assignment->reactivate();

            DB::commit();

            return back()->with('success', 'Agent assignment reactivated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reactivate agent assignment: ' . $e->getMessage());
            return back()->with('error', 'Failed to reactivate agent assignment. Please try again.');
        }
    }

    /**
     * Send invitation to specific assigned field agent
     */
    public function sendInvitation(Request $request, $id)
    {
        $registrationPlan = RegistrationPlan::findOrFail($id);
        
        $validated = $request->validate([
            'agent_id' => 'required|exists:users,id',
            'invitation_method' => 'required|in:sms,whatsapp,email,all_channels',
            'message' => 'nullable|string|max:500'
        ]);

        // Check if agent is assigned to the plan
        $isAssigned = PlanAgentAssignment::where('plan_id', $registrationPlan->id)
            ->where('agent_id', $validated['agent_id'])
            ->where('is_active', true)
            ->exists();

        if (!$isAssigned) {
            return back()->with('error', 'This agent is not assigned to the plan.');
        }

        $invitationResult = $this->agentInvitationService->sendMultiChannelInvitationWithTokenConsistency(
            $registrationPlan->id,
            $validated['agent_id'],
            [$validated['invitation_method']],
            $validated['message'] ?? null
        );

        $agent = User::find($validated['agent_id']);

        if ($invitationResult['success']) {
            $successCount = count($invitationResult['channels_successful']);
            return back()->with('success', "Invitations sent to {$agent->name} via {$successCount} channels successfully");
        } else {
            return back()->with('error', 'Failed to send invitations: ' . $invitationResult['message']);
        }
    }

    /**
     * Send invitation to all assigned agents
     */
    public function sendBulkInvitations(Request $request, $id)
    {
        $registrationPlan = RegistrationPlan::with(['assignedAgents.agent'])->findOrFail($id);
        
        $validated = $request->validate([
            'invitation_method' => 'required|in:sms,whatsapp,email,all_channels',
            'message' => 'nullable|string|max:500'
        ]);

        $activeAssignments = $registrationPlan->assignedAgents->where('is_active', true);
        
        if ($activeAssignments->isEmpty()) {
            return back()->with('error', 'No active agents assigned to this plan.');
        }

        $successCount = 0;
        $errorCount = 0;

        foreach ($activeAssignments as $assignment) {
            $agent = $assignment->agent;
            
            $invitationResult = $this->agentInvitationService->sendMultiChannelInvitationWithTokenConsistency(
                $registrationPlan->id,
                $agent->id,
                [$validated['invitation_method']],
                $validated['message'] ?? null
            );

            if ($invitationResult['success']) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }

        $message = "Bulk invitations sent. Success: {$successCount}, Failed: {$errorCount}";
        $type = $errorCount === 0 ? 'success' : ($successCount > 0 ? 'warning' : 'error');

        return back()->with($type, $message);
    }

    /**
     * Get agent assignment details
     */
    public function getAssignmentDetails($planId, $assignmentId)
    {
        try {
            $assignment = PlanAgentAssignment::with(['agent', 'plan', 'assigner'])
                ->where('plan_id', $planId)
                ->findOrFail($assignmentId);

            return response()->json([
                'success' => true,
                'assignment' => $assignment,
                'performance_metrics' => $assignment->getPerformanceMetrics(),
                'assignment_summary' => $assignment->getAssignmentSummary(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get assignment details: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get assignment details: ' . $e->getMessage()
            ], 500);
        }
    }
}