<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\RegistrationPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PropertyAdminController extends Controller
{
    /**
     * Display a listing of trashed properties.
     */
    public function trash(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only administrators can view trashed properties.'
                ], 403);
            }
            return redirect()->back()->with('error', 'Unauthorized. Only administrators can view trashed properties.');
        }

        $query = Property::onlyTrashed()->with(['landlord', 'registrationPlan', 'propertyType']);
        
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
        
        return view('admin.properties.trash', compact('trashedProperties'));
    }

    /**
     * Restore a trashed property.
     */
    public function restore($id, Request $request)
    {
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

            if ($property->registration_plan_id) {
                $plan = RegistrationPlan::find($property->registration_plan_id);
                if ($plan) {
                    app(\App\Http\Controllers\PropertyRegistrationController::class)
                        ->updateRegistrationPlanProgress($plan);
                }
            }

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Property restored successfully'
                ]);
            }

            return redirect()->route('admin.properties.trash')
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
     * Permanently delete a property.
     */
    public function forceDelete($id, Request $request)
    {
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

            if ($planId) {
                $plan = RegistrationPlan::find($planId);
                if ($plan) {
                    app(\App\Http\Controllers\PropertyRegistrationController::class)
                        ->updateRegistrationPlanProgress($plan);
                }
            }

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Property permanently deleted successfully'
                ]);
            }

            return redirect()->route('admin.properties.trash')
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
     * Restore all trashed properties.
     */
    public function restoreAll(Request $request)
    {
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
                if ($property->registration_plan_id) {
                    $plan = RegistrationPlan::find($property->registration_plan_id);
                    if ($plan) {
                        app(\App\Http\Controllers\PropertyRegistrationController::class)
                            ->updateRegistrationPlanProgress($plan);
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

            return redirect()->route('admin.properties.trash')
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
     * Permanently delete all trashed properties.
     */
    public function emptyTrash(Request $request)
    {
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
                if ($planId) {
                    $plan = RegistrationPlan::find($planId);
                    if ($plan) {
                        app(\App\Http\Controllers\PropertyRegistrationController::class)
                            ->updateRegistrationPlanProgress($plan);
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

            return redirect()->route('admin.properties.trash')
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

    /**
     * Export properties to CSV.
     */
    public function export(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only administrators can export properties.'
                ], 403);
            }
            return redirect()->back()->with('error', 'Unauthorized. Only administrators can export properties.');
        }

        $query = Property::with(['landlord', 'registrationPlan', 'propertyType']);

        // Apply filters
        $filters = [
            'street_name', 'landlord_id', 'zone', 'status', 'digital_address',
            'registration_plan_id', 'house_number', 'block_number', 'property_type_id',
            'registration_pattern', 'property_name'
        ];

        foreach ($filters as $filter) {
            if ($request->filled($filter)) {
                if (in_array($filter, ['street_name', 'zone', 'digital_address', 'house_number', 'block_number', 'registration_pattern', 'property_name'])) {
                    $query->where($filter, 'like', '%' . $request->$filter . '%');
                } else {
                    $query->where($filter, $request->$filter);
                }
            }
        }

        if ($request->has('has_digital_address') && $request->has_digital_address !== '') {
            if ($request->has_digital_address === '1') {
                $query->hasDigitalAddress();
            } else if ($request->has_digital_address === '0') {
                $query->missingDigitalAddress();
            }
        }

        $properties = $query->orderBy('zone')
            ->orderBy('street_name')
            ->orderBy('house_number')
            ->get();

        $fileName = 'properties_export_' . date('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function() use ($properties) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            
            fputcsv($file, [
                'ID', 'Property Name', 'Registration Pattern', 'Property Type', 'Custom Property Type',
                'House Number', 'Block Number', 'Street Name', 'Zone', 'Section', 'Digital Address',
                'Landlord Name', 'Landlord Phone', 'Landlord Email', 'Registration Plan',
                'Registration Date', 'Last Inspection Date', 'Status', 'Description', 'Created At', 'Updated At'
            ]);

            foreach ($properties as $property) {
                fputcsv($file, [
                    $property->id,
                    $property->property_name,
                    $property->registration_pattern,
                    $property->propertyType->name ?? 'N/A',
                    $property->custom_property_type ?? 'N/A',
                    $property->house_number,
                    $property->block_number,
                    $property->street_name,
                    $property->zone,
                    $property->section,
                    $property->digital_address,
                    $property->landlord->name ?? 'N/A',
                    $property->landlord->phone ?? 'N/A',
                    $property->landlord->email ?? 'N/A',
                    $property->registrationPlan ? 
                        $property->registrationPlan->zone . ' - ' . ($property->registrationPlan->section ?? 'No Section') . 
                        ' (' . $property->registrationPlan->naming_pattern . ')' : 'N/A',
                    $property->registration_date?->format('Y-m-d') ?? 'N/A',
                    $property->last_inspection_date?->format('Y-m-d') ?? 'N/A',
                    ucfirst(str_replace('_', ' ', $property->status)),
                    $property->description ?? '',
                    $property->created_at->format('Y-m-d H:i:s'),
                    $property->updated_at->format('Y-m-d H:i:s')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get global sequence statistics
     */
    public function globalSequenceStats(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only administrators can view global sequence statistics.'
                ], 403);
            }
            return redirect()->back()->with('error', 'Unauthorized. Only administrators can view global sequence statistics.');
        }

        $stats = RegistrationPlan::getGlobalSequenceStats();
        
        $propertiesInGlobalSequences = Property::whereHas('registrationPlan', function($query) {
            $query->where('is_global_sequence', true);
        })->count();

        $stats['total_properties_in_global_sequences'] = $propertiesInGlobalSequences;

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'stats' => $stats,
                'message' => 'Global sequence statistics retrieved successfully'
            ]);
        }

        return view('admin.properties.global-sequence-stats', compact('stats'));
    }
}