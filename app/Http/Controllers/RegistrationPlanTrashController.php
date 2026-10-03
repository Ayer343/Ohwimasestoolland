<?php

namespace App\Http\Controllers;

use App\Models\RegistrationPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RegistrationPlanTrashController extends Controller
{
    /**
     * Display a listing of trashed registration plans.
     */
    public function index(Request $request)
    {
        // Check if user has permission to view trash
        if (!in_array(auth()->user()->type, [User::TYPE_SUPER_ADMIN, User::TYPE_ADMIN])) {
            abort(403, 'Unauthorized action.');
        }

        $query = RegistrationPlan::onlyTrashed()
            ->with(['creator', 'assignedAgents.agent'])
            ->withCount('properties')
            ->orderBy('deleted_at', 'desc');

        // Apply filters
        if ($request->has('status') && $request->status != 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('zone') && !empty($request->zone)) {
            $query->where('zone', 'like', '%' . $request->zone . '%');
        }

        if ($request->has('section') && !empty($request->section)) {
            $query->where('section', 'like', '%' . $request->section . '%');
        }

        if ($request->has('deleted_period') && $request->deleted_period != 'all') {
            switch ($request->deleted_period) {
                case 'today':
                    $query->whereDate('deleted_at', today());
                    break;
                case 'week':
                    $query->where('deleted_at', '>=', now()->subWeek());
                    break;
                case 'month':
                    $query->where('deleted_at', '>=', now()->subMonth());
                    break;
            }
        }

        $plans = $query->paginate(20);

        $statuses = ['draft', 'assigned', 'in_progress', 'completed', 'cancelled'];
        $totalTrashedCount = RegistrationPlan::onlyTrashed()->count();
        $recentlyDeletedCount = RegistrationPlan::onlyTrashed()
            ->where('deleted_at', '>=', now()->subWeek())
            ->count();

        return view('admin.registration-plans.trash', compact(
            'plans', 
            'statuses', 
            'totalTrashedCount', 
            'recentlyDeletedCount'
        ));
    }

    /**
     * Restore a soft-deleted registration plan.
     */
    public function restore($id)
    {
        // Check if user has permission to restore
        if (!in_array(auth()->user()->type, [User::TYPE_SUPER_ADMIN, User::TYPE_ADMIN])) {
            abort(403, 'Unauthorized action.');
        }

        $registrationPlan = RegistrationPlan::onlyTrashed()->findOrFail($id);

        // Check if the plan can be restored (no conflicts with existing active plans)
        $existingActivePlan = RegistrationPlan::where('zone', $registrationPlan->zone)
            ->where('section', $registrationPlan->section)
            ->where('id', '!=', $registrationPlan->id)
            ->whereIn('status', ['draft', 'assigned', 'in_progress'])
            ->exists();

        if ($existingActivePlan) {
            return redirect()->route('registration-plans.trash')
                ->with('error', 'Cannot restore this plan. There is already an active plan for the same zone and section.');
        }

        DB::beginTransaction();

        try {
            $registrationPlan->restore();

            DB::commit();

            return redirect()->route('registration-plans.show', $registrationPlan->id)
                ->with('success', 'Registration plan restored successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to restore plan: ' . $e->getMessage());
            
            return redirect()->route('registration-plans.trash')
                ->with('error', 'Failed to restore registration plan. Please try again.');
        }
    }

    /**
     * Force delete a registration plan (permanent deletion).
     */
    public function forceDestroy($id)
    {
        // Only allow admins to force delete
        if (!in_array(auth()->user()->type, [User::TYPE_SUPER_ADMIN, User::TYPE_ADMIN])) {
            abort(403, 'Unauthorized action.');
        }

        $registrationPlan = RegistrationPlan::onlyTrashed()->findOrFail($id);

        // Check if plan has properties
        if ($registrationPlan->properties()->exists()) {
            return redirect()->route('registration-plans.trash')
                ->with('error', 'Cannot permanently delete a plan that has registered properties. The plan contains ' . $registrationPlan->properties()->count() . ' properties.');
        }

        DB::beginTransaction();

        try {
            // Force delete the plan
            $registrationPlan->forceDelete();

            DB::commit();

            return redirect()->route('registration-plans.trash')
                ->with('success', "Plan #{$registrationPlan->id} ({$registrationPlan->zone}) permanently deleted successfully!");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to force delete plan: ' . $e->getMessage());
            
            return redirect()->route('registration-plans.trash')
                ->with('error', 'Failed to permanently delete registration plan. Please try again.');
        }
    }

    /**
     * Empty the trash (permanently delete all trashed plans).
     * Supports both:
     * 1. Empty entire trash (deletes all plans without properties)
     * 2. Delete only empty plans (deletes plans with zero properties)
     */
    public function emptyTrash(Request $request)
    {
        // Only allow admins to empty trash
        if (!in_array(auth()->user()->type, [User::TYPE_SUPER_ADMIN, User::TYPE_ADMIN])) {
            abort(403, 'Unauthorized action.');
        }

        DB::beginTransaction();

        try {
            // Get all trashed plans
            $trashedPlans = RegistrationPlan::onlyTrashed()->get();
            $deletedPlans = [];
            $failedPlans = [];
            $plansWithProperties = [];

            foreach ($trashedPlans as $plan) {
                $propertyCount = $plan->properties()->count();
                
                if ($propertyCount == 0) {
                    // Safe to delete - no properties
                    try {
                        $plan->forceDelete();
                        $deletedPlans[] = [
                            'id' => $plan->id,
                            'zone' => $plan->zone,
                            'section' => $plan->section
                        ];
                    } catch (\Exception $e) {
                        $failedPlans[] = [
                            'id' => $plan->id,
                            'zone' => $plan->zone,
                            'section' => $plan->section,
                            'error' => $e->getMessage()
                        ];
                        Log::error("Failed to delete plan {$plan->id}: " . $e->getMessage());
                    }
                } else {
                    // Has properties - cannot delete
                    $plansWithProperties[] = [
                        'id' => $plan->id,
                        'zone' => $plan->zone,
                        'section' => $plan->section,
                        'property_count' => $propertyCount
                    ];
                }
            }

            DB::commit();

            // Build detailed success message
            $deletedCount = count($deletedPlans);
            $failedCount = count($failedPlans);
            $protectedCount = count($plansWithProperties);
            
            if ($deletedCount > 0) {
                $message = "✅ Trash emptied successfully! {$deletedCount} plan(s) permanently deleted.\n\n";
                
                if ($deletedCount <= 5) {
                    $message .= "Deleted plans:\n";
                    foreach ($deletedPlans as $plan) {
                        $location = $plan['zone'] . ($plan['section'] ? " - {$plan['section']}" : '');
                        $message .= "• #{$plan['id']} - {$location}\n";
                    }
                }
            } else {
                $message = "No plans were deleted from trash.\n\n";
            }
            
            if ($protectedCount > 0) {
                $message .= "\n⚠️ {$protectedCount} plan(s) could not be deleted because they have registered properties:\n";
                foreach ($plansWithProperties as $plan) {
                    $location = $plan['zone'] . ($plan['section'] ? " - {$plan['section']}" : '');
                    $message .= "• #{$plan['id']} - {$location} ({$plan['property_count']} properties)\n";
                }
                $message .= "\n💡 To delete these plans, first remove their associated properties or cancel the plans.";
            }
            
            if ($failedCount > 0) {
                $message .= "\n❌ {$failedCount} plan(s) failed to delete due to errors. Please check the logs.";
            }

            // Store detailed info in session for display
            session()->flash('deleted_plans', $deletedPlans);
            session()->flash('failed_plans', $failedPlans);
            session()->flash('protected_plans', $plansWithProperties);

            return redirect()->route('registration-plans.trash')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to empty trash: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return redirect()->route('registration-plans.trash')
                ->with('error', 'Failed to empty trash: ' . $e->getMessage());
        }
    }

    /**
     * Delete only empty plans (plans with no properties).
     * This is called from the "Delete Empty Plans" button.
     */
    public function deleteEmptyPlans()
    {
        // Only allow admins to delete empty plans
        if (!in_array(auth()->user()->type, [User::TYPE_SUPER_ADMIN, User::TYPE_ADMIN])) {
            abort(403, 'Unauthorized action.');
        }

        DB::beginTransaction();

        try {
            // Get only trashed plans with no properties
            $emptyPlans = RegistrationPlan::onlyTrashed()
                ->whereDoesntHave('properties')
                ->get();
            
            $deletedCount = 0;
            $deletedPlans = [];

            foreach ($emptyPlans as $plan) {
                try {
                    $plan->forceDelete();
                    $deletedCount++;
                    $deletedPlans[] = [
                        'id' => $plan->id,
                        'zone' => $plan->zone,
                        'section' => $plan->section
                    ];
                } catch (\Exception $e) {
                    Log::error("Failed to delete empty plan {$plan->id}: " . $e->getMessage());
                }
            }

            DB::commit();

            $message = "✅ {$deletedCount} empty plan(s) permanently deleted from trash.";
            
            if ($deletedCount > 0 && $deletedCount <= 5) {
                $message .= "\n\nDeleted plans:\n";
                foreach ($deletedPlans as $plan) {
                    $location = $plan['zone'] . ($plan['section'] ? " - {$plan['section']}" : '');
                    $message .= "• #{$plan['id']} - {$location}\n";
                }
            }

            session()->flash('deleted_empty_plans', $deletedPlans);

            return redirect()->route('registration-plans.trash')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete empty plans: ' . $e->getMessage());
            
            return redirect()->route('registration-plans.trash')
                ->with('error', 'Failed to delete empty plans: ' . $e->getMessage());
        }
    }
}