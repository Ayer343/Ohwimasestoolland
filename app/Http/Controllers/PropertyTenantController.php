<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\RegistrationPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PropertyTrashController extends Controller
{
    /**
     * Display a listing of trashed properties
     */
    public function index(Request $request)
    {
        // Only super admin and admin can view trashed properties
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only administrators can view trashed properties.'
                ], 403);
            }
            return redirect()->back()->with('error', 'Unauthorized. Only administrators can view trashed properties.');
        }

        $query = Property::onlyTrashed()->with(['landlord', 'registrationPlan', 'propertyType', 'tenants']);
        
        // Apply filters
        if ($request->has('street_name')) {
            $query->where('street_name', 'like', '%' . $request->street_name . '%');
        }
        
        if ($request->has('landlord_id')) {
            $query->where('landlord_id', $request->landlord_id);
        }
        
        if ($request->has('zone')) {
            $query->where('zone', 'like', '%' . $request->zone . '%');
        }
        
        if ($request->has('property_type_id')) {
            $query->where('property_type_id', $request->property_type_id);
        }

        $trashedProperties = $query->latest('deleted_at')->paginate(20);
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'trashed_properties' => $trashedProperties,
                'message' => 'Trashed properties retrieved successfully'
            ]);
        }
        
        return view('super-admin.properties.trash', compact('trashedProperties'));
    }

    /**
     * Restore a trashed property
     */
    public function restore($id, Request $request)
    {
        // Only super admin and admin can restore properties
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only administrators can restore properties.'
                ], 403);
            }
            return redirect()->back()->with('error', 'Unauthorized. Only administrators can restore properties.');
        }

        DB::beginTransaction();

        try {
            $property = Property::onlyTrashed()->findOrFail($id);
            $property->restore();

            // Update registration plan progress after restoration
            if ($property->registration_plan_id) {
                $plan = RegistrationPlan::find($property->registration_plan_id);
                if ($plan) {
                    $plan->updateProgress();
                }
            }

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Property restored successfully'
                ]);
            }

            return redirect()->route('properties.trash')
                ->with('success', 'Property restored successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to restore property. Please try again.'
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to restore property. Please try again.');
        }
    }

    /**
     * Permanently delete a property
     */
    public function forceDelete($id, Request $request)
    {
        // Only super admin and admin can permanently delete properties
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only administrators can permanently delete properties.'
                ], 403);
            }
            return redirect()->back()->with('error', 'Unauthorized. Only administrators can permanently delete properties.');
        }

        DB::beginTransaction();

        try {
            $property = Property::onlyTrashed()->findOrFail($id);
            $planId = $property->registration_plan_id;
            $property->forceDelete();

            // Update registration plan progress after permanent deletion
            if ($planId) {
                $plan = RegistrationPlan::find($planId);
                if ($plan) {
                    $plan->updateProgress();
                }
            }

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Property permanently deleted successfully'
                ]);
            }

            return redirect()->route('properties.trash')
                ->with('success', 'Property permanently deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to permanently delete property. Please try again.'
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to permanently delete property. Please try again.');
        }
    }

    /**
     * Restore all trashed properties
     */
    public function restoreAll(Request $request)
    {
        // Only super admin and admin can restore all properties
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only administrators can restore properties.'
                ], 403);
            }
            return redirect()->back()->with('error', 'Unauthorized. Only administrators can restore properties.');
        }

        DB::beginTransaction();

        try {
            $trashedProperties = Property::onlyTrashed()->get();
            
            foreach ($trashedProperties as $property) {
                $property->restore();
                // Update registration plan progress after restoration
                if ($property->registration_plan_id) {
                    $plan = RegistrationPlan::find($property->registration_plan_id);
                    if ($plan) {
                        $plan->updateProgress();
                    }
                }
            }

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'All properties restored successfully'
                ]);
            }

            return redirect()->route('properties.trash')
                ->with('success', 'All properties restored successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to restore all properties. Please try again.'
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to restore all properties. Please try again.');
        }
    }

    /**
     * Permanently delete all trashed properties
     */
    public function emptyTrash(Request $request)
    {
        // Only super admin and admin can empty trash
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only administrators can empty trash.'
                ], 403);
            }
            return redirect()->back()->with('error', 'Unauthorized. Only administrators can empty trash.');
        }

        DB::beginTransaction();

        try {
            $trashedProperties = Property::onlyTrashed()->get();
            
            foreach ($trashedProperties as $property) {
                $planId = $property->registration_plan_id;
                $property->forceDelete();
                // Update registration plan progress after permanent deletion
                if ($planId) {
                    $plan = RegistrationPlan::find($planId);
                    if ($plan) {
                        $plan->updateProgress();
                    }
                }
            }

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Trash emptied successfully'
                ]);
            }

            return redirect()->route('properties.trash')
                ->with('success', 'Trash emptied successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to empty trash. Please try again.'
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to empty trash. Please try again.');
        }
    }
}