<?php

namespace App\Http\Controllers;

use App\Models\RegistrationPlan;
use App\Models\User;
use App\Rules\FieldAgentExists;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\RegistrationPlansExport;

class RegistrationPlanExportController extends Controller
{
    /**
     * Export registration plans to Excel
     */
    public function export(Request $request)
    {
        $validated = $request->validate([
            'format' => 'required|in:excel,csv,pdf',
            'status' => 'nullable|in:all,draft,assigned,in_progress,completed,cancelled',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        $fileName = 'registration-plans-' . date('Y-m-d') . '.' . $validated['format'];

        if ($validated['format'] === 'pdf') {
            return back()->with('warning', 'PDF export feature is not yet implemented.');
        }

        return Excel::download(new RegistrationPlansExport($validated), $fileName);
    }

    /**
     * Bulk actions for registration plans
     */
    public function bulkActions(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|in:delete,assign_agent,change_status,export',
            'plan_ids' => 'required|array',
            'plan_ids.*' => 'exists:registration_plans,id',
            'assigned_agent_ids' => 'required_if:action,assign_agent|array',
            'assigned_agent_ids.*' => ['required', new FieldAgentExists],
            'status' => 'required_if:action,change_status|in:draft,assigned,in_progress,completed,cancelled',
        ]);

        DB::beginTransaction();

        try {
            $plans = RegistrationPlan::whereIn('id', $validated['plan_ids'])->get();
            $processed = 0;
            $errors = [];

            foreach ($plans as $plan) {
                try {
                    switch ($validated['action']) {
                        case 'delete':
                            if (!$plan->properties()->exists() && !in_array($plan->status, ['assigned', 'in_progress'])) {
                                $plan->delete();
                                $processed++;
                            } else {
                                $errors[] = "Plan {$plan->id} cannot be deleted (has properties or is active)";
                            }
                            break;

                        case 'assign_agent':
                            // Remove existing assignments using model method
                            $assignments = PlanAgentAssignment::where('plan_id', $plan->id)
                                ->where('is_active', true)
                                ->get();
                            
                            foreach ($assignments as $assignment) {
                                $assignment->markAsInactive('Bulk action reassignment');
                            }
                            
                            // Add new assignments
                            foreach ($validated['assigned_agent_ids'] as $agentId) {
                                PlanAgentAssignment::create([
                                    'plan_id' => $plan->id,
                                    'agent_id' => $agentId,
                                    'assigned_by' => Auth::id(),
                                ]);
                            }
                            $processed++;
                            break;

                        case 'change_status':
                            $plan->update(['status' => $validated['status']]);
                            $processed++;
                            break;

                        case 'export':
                            // Handle export separately
                            break;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Failed to process plan {$plan->id}: " . $e->getMessage();
                }
            }

            DB::commit();

            $message = "Bulk action completed. Processed: {$processed} plans.";
            if (!empty($errors)) {
                $message .= " Errors: " . implode(', ', array_slice($errors, 0, 5));
                if (count($errors) > 5) {
                    $message .= " and " . (count($errors) - 5) . " more";
                }
            }

            return back()->with(
                empty($errors) ? 'success' : 'warning',
                $message
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Bulk action failed: ' . $e->getMessage());
        }
    }
}