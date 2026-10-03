<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyPhoto;
use App\Models\User;
use App\Models\RegistrationPlan;
use App\Models\PropertyType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use App\Http\Traits\PropertyAuthorizationTrait;
use App\Http\Traits\PropertyViewTrait;

class PropertyTrashController extends Controller
{
    use PropertyAuthorizationTrait, PropertyViewTrait;

    /**
     * Display trashed (soft deleted) properties
     */
    public function index(Request $request)
    {
        // Authorization check - only admins can view trash
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            abort(403, 'Unauthorized. Only administrators can view trashed properties.');
        }

        $query = Property::onlyTrashed()->with(['landlord', 'registrationPlan', 'propertyType', 'photos']);
        
        // Apply filters
        $this->applyFilters($query, $request);
        
        // Add photo count to the query
        $query->withCount('photos');
        
        // Order by deletion date (newest first)
        $trashedProperties = $query->latest('deleted_at')->paginate(20);
        
        // Get data for filter dropdowns
        $registrationPlans = RegistrationPlan::whereNull('deleted_at')->get();
        $landlords = User::where(function($query) {
            $query->where('type', User::TYPE_LANDLORD)
                  ->orWhereHas('roles', function($q) {
                      $q->where('slug', 'landlord');
                  });
        })->get();
        $propertyTypes = PropertyType::active()->ordered()->get();
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'trashed_properties' => $trashedProperties,
                'total' => $trashedProperties->total(),
                'message' => 'Trashed properties retrieved successfully'
            ]);
        }
        
        // =============================================
        // ✅ FIXED: Try multiple view paths
        // =============================================
        $viewPaths = [
            'super-admin.properties.trashed',
            'super-admin.properties.trash',
            'admin.properties.trashed',
            'admin.properties.trash',
            'properties.trashed',
            'properties.trash',
            'super-admin.trash',
            'admin.trash'
        ];
        
        $viewFound = false;
        foreach ($viewPaths as $viewPath) {
            if (View::exists($viewPath)) {
                $viewFound = true;
                return view($viewPath, compact('trashedProperties', 'registrationPlans', 'landlords', 'propertyTypes'));
            }
        }
        
        // If no view found, create a fallback view
        if (!$viewFound) {
            Log::warning('Trash view not found. Tried: ' . implode(', ', $viewPaths));
            return $this->renderFallbackView($trashedProperties, $registrationPlans, $landlords, $propertyTypes);
        }
    }

    /**
     * Render a fallback view if the blade file doesn't exist
     */
    private function renderFallbackView($trashedProperties, $registrationPlans, $landlords, $propertyTypes)
    {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <title>Trashed Properties</title>
            <style>
                body { font-family: sans-serif; padding: 20px; background: #f5f7fa; }
                .container { max-width: 1200px; margin: 0 auto; }
                .card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; }
                .header { display: flex; justify-content: space-between; align-items: center; }
                .btn { padding: 8px 16px; border-radius: 4px; text-decoration: none; display: inline-block; margin: 0 4px; }
                .btn-primary { background: #3490dc; color: white; }
                .btn-success { background: #38c172; color: white; }
                .btn-danger { background: #e3342f; color: white; }
                .btn-warning { background: #f6993f; color: white; }
                table { width: 100%; border-collapse: collapse; }
                th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
                th { background: #f7fafc; font-weight: 600; }
                .badge { padding: 4px 8px; border-radius: 9999px; font-size: 12px; }
                .badge-success { background: #d4edda; color: #155724; }
                .badge-warning { background: #fff3cd; color: #856404; }
                .badge-danger { background: #f8d7da; color: #721c24; }
                .badge-info { background: #d1ecf1; color: #0c5460; }
                .text-center { text-align: center; }
                .mt-4 { margin-top: 16px; }
                .flex { display: flex; }
                .gap-2 { gap: 8px; }
                .text-muted { color: #6c757d; }
                .empty-state { padding: 60px 20px; text-align: center; }
                .empty-state i { font-size: 48px; color: #cbd5e0; }
                .empty-state h3 { margin: 16px 0 8px; color: #4a5568; }
                .empty-state p { color: #718096; }
                .alert { padding: 12px 20px; border-radius: 4px; margin-bottom: 16px; }
                .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
                .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="card">
                    <div class="header">
                        <h2>🗑️ Trashed Properties</h2>
                        <div class="flex gap-2">
                            <a href="' . route('properties.index') . '" class="btn btn-primary">← Back to Properties</a>
                            ' . ($trashedProperties->count() > 0 ? '
                            <form action="' . route('properties.trash.restore-all') . '" method="POST" style="display:inline;">
                                ' . csrf_field() . '
                                <button type="submit" class="btn btn-success" onclick="return confirm(\'Restore all properties?\')">↩ Restore All</button>
                            </form>
                            <form action="' . route('properties.trash.empty') . '" method="POST" style="display:inline;">
                                ' . csrf_field() . '
                                ' . method_field('DELETE') . '
                                <button type="submit" class="btn btn-danger" onclick="return confirm(\'PERMANENTLY DELETE ALL? This cannot be undone!\')">🗑 Empty Trash</button>
                            </form>
                            ' : '') . '
                        </div>
                    </div>
                </div>

                ' . (session('success') ? '
                <div class="alert alert-success">✅ ' . session('success') . '</div>
                ' : '') . '

                ' . (session('error') ? '
                <div class="alert alert-danger">❌ ' . session('error') . '</div>
                ' : '') . '

                <div class="card">
                    ' . ($trashedProperties->count() > 0 ? '
                    <p style="color: #6c757d; margin-bottom: 16px;">Showing ' . ($trashedProperties->firstItem() ?? 0) . ' to ' . ($trashedProperties->lastItem() ?? 0) . ' of ' . $trashedProperties->total() . ' trashed properties</p>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Property</th>
                                    <th>Street</th>
                                    <th>Digital Address</th>
                                    <th>Landlord</th>
                                    <th>Status</th>
                                    <th>Deleted At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ' . $trashedProperties->map(function($property) {
                                    $statusColors = [
                                        'active' => 'success',
                                        'inactive' => 'secondary',
                                        'under_maintenance' => 'warning',
                                        'vacant' => 'info',
                                        'under_construction' => 'primary'
                                    ];
                                    $statusColor = $statusColors[$property->status] ?? 'secondary';
                                    return '
                                    <tr>
                                        <td><strong>' . e($property->property_name) . '</strong><br><small>' . e($property->house_number ?? 'N/A') . '</small></td>
                                        <td>' . e($property->street_name ?? 'N/A') . '</td>
                                        <td>' . ($property->digital_address ? '<span class="badge badge-success">' . e($property->digital_address) . '</span>' : '<span class="badge badge-warning">Not Assigned</span>') . '</td>
                                        <td>' . e($property->landlord->name ?? 'N/A') . '<br><small>' . e($property->landlord->phone ?? '') . '</small></td>
                                        <td><span class="badge badge-' . $statusColor . '">' . ucfirst(str_replace('_', ' ', $property->status ?? 'Unknown')) . '</span></td>
                                        <td>' . ($property->deleted_at ? $property->deleted_at->format('M d, Y') : 'N/A') . '<br><small>' . ($property->deleted_at ? $property->deleted_at->diffForHumans() : '') . '</small></td>
                                        <td>
                                            <form action="' . route('properties.trash.restore', $property->id) . '" method="POST" style="display:inline;">
                                                ' . csrf_field() . '
                                                <button type="submit" class="btn btn-success" style="padding:4px 8px;" onclick="return confirm(\'Restore this property?\')">↩</button>
                                            </form>
                                            <form action="' . route('properties.trash.force-delete', $property->id) . '" method="POST" style="display:inline;">
                                                ' . csrf_field() . '
                                                ' . method_field('DELETE') . '
                                                <button type="submit" class="btn btn-danger" style="padding:4px 8px;" onclick="return confirm(\'PERMANENTLY DELETE? This cannot be undone!\')">🗑</button>
                                            </form>
                                        </td>
                                    </tr>
                                    ';
                                })->implode('') . '
                            </tbody>
                        </table>
                    </div>
                    ' . ($trashedProperties->hasPages() ? '<div class="mt-4">' . $trashedProperties->links() . '</div>' : '') . '
                    ' : '
                    <div class="empty-state">
                        <div>🗑️</div>
                        <h3>No trashed properties</h3>
                        <p>The trash is empty. Deleted properties will appear here.</p>
                    </div>
                    ') . '
                </div>
            </div>
        </body>
        </html>';
        
        return response($html);
    }

    /**
     * Restore a single soft deleted property
     */
    public function restore($id, Request $request)
    {
        // Authorization check - only admins can restore
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return $this->unauthorizedResponse($request, 'Unauthorized. Only administrators can restore properties.');
        }

        $property = Property::onlyTrashed()->findOrFail($id);
        
        try {
            DB::beginTransaction();
            
            // Restore the property
            $property->restore();
            
            // Try to restore related photos if the model uses SoftDeletes
            try {
                if (method_exists(PropertyPhoto::class, 'onlyTrashed')) {
                    PropertyPhoto::onlyTrashed()->where('property_id', $property->id)->restore();
                }
            } catch (\Exception $e) {
                // Log but don't fail the operation if photo restore fails
                Log::warning('Could not restore photos for property: ' . $property->id, [
                    'error' => $e->getMessage()
                ]);
            }
            
            DB::commit();
            
            Log::info('Property restored', [
                'property_id' => $property->id,
                'property_name' => $property->property_name,
                'restored_by' => auth()->id(),
                'restored_at' => now()
            ]);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Property restored successfully',
                    'property' => $property->fresh(),
                    'trash_count' => Property::onlyTrashed()->count()
                ]);
            }
            
            return redirect()->route('properties.trash.index')
                ->with('success', 'Property "' . $property->property_name . '" restored successfully.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to restore property: ' . $e->getMessage(), [
                'property_id' => $id,
                'user_id' => auth()->id()
            ]);
            
            return $this->errorResponse($request, 'Failed to restore property. Please try again.');
        }
    }

    /**
     * Restore all trashed properties
     */
    public function restoreAll(Request $request)
    {
        // Authorization check - only admins can restore all
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return $this->unauthorizedResponse($request, 'Unauthorized. Only administrators can restore all properties.');
        }

        try {
            DB::beginTransaction();
            
            $trashedProperties = Property::onlyTrashed()->get();
            $restoredCount = $trashedProperties->count();
            
            // Restore all trashed properties
            Property::onlyTrashed()->restore();
            
            // Try to restore associated photos if the model uses SoftDeletes
            try {
                $propertyIds = $trashedProperties->pluck('id')->toArray();
                if (!empty($propertyIds) && method_exists(PropertyPhoto::class, 'onlyTrashed')) {
                    PropertyPhoto::onlyTrashed()->whereIn('property_id', $propertyIds)->restore();
                }
            } catch (\Exception $e) {
                // Log but don't fail the operation if photo restore fails
                Log::warning('Could not restore photos for properties', [
                    'property_ids' => $propertyIds ?? [],
                    'error' => $e->getMessage()
                ]);
            }
            
            DB::commit();
            
            Log::info('All trashed properties restored', [
                'restored_count' => $restoredCount,
                'restored_by' => auth()->id(),
                'restored_at' => now()
            ]);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "{$restoredCount} property(s) restored successfully",
                    'restored_count' => $restoredCount,
                    'trash_count' => Property::onlyTrashed()->count()
                ]);
            }
            
            return redirect()->route('properties.trash.index')
                ->with('success', "{$restoredCount} property(s) restored successfully.");
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to restore all properties: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse($request, 'Failed to restore all properties. Please try again.');
        }
    }

    /**
     * Permanently delete a single property (force delete)
     */
    public function forceDelete($id, Request $request)
    {
        // Authorization check - only admins can force delete
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return $this->unauthorizedResponse($request, 'Unauthorized. Only administrators can permanently delete properties.');
        }

        $property = Property::onlyTrashed()->findOrFail($id);
        $propertyName = $property->property_name;
        
        try {
            DB::beginTransaction();
            
            // Delete all associated photos from storage
            foreach ($property->photos as $photo) {
                if (method_exists($photo, 'deletePhotoFile')) {
                    $photo->deletePhotoFile();
                }
            }
            
            // Force delete the property (permanently removes from database)
            $property->forceDelete();
            
            DB::commit();
            
            Log::info('Property permanently deleted', [
                'property_id' => $id,
                'property_name' => $propertyName,
                'deleted_by' => auth()->id(),
                'deleted_at' => now()
            ]);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Property permanently deleted successfully',
                    'trash_count' => Property::onlyTrashed()->count()
                ]);
            }
            
            return redirect()->route('properties.trash.index')
                ->with('success', 'Property "' . $propertyName . '" permanently deleted.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to permanently delete property: ' . $e->getMessage(), [
                'property_id' => $id,
                'user_id' => auth()->id()
            ]);
            
            return $this->errorResponse($request, 'Failed to permanently delete property. Please try again.');
        }
    }

    /**
     * Empty entire trash (permanently delete all soft deleted properties)
     */
    public function emptyTrash(Request $request)
    {
        // Authorization check - only admins can empty trash
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return $this->unauthorizedResponse($request, 'Unauthorized. Only administrators can empty the trash.');
        }

        try {
            DB::beginTransaction();
            
            $trashedProperties = Property::onlyTrashed()->get();
            $deletedCount = $trashedProperties->count();
            
            // Delete all photo files from storage for each property
            foreach ($trashedProperties as $property) {
                foreach ($property->photos as $photo) {
                    if (method_exists($photo, 'deletePhotoFile')) {
                        $photo->deletePhotoFile();
                    }
                }
            }
            
            // Force delete all trashed properties
            Property::onlyTrashed()->forceDelete();
            
            DB::commit();
            
            Log::info('Trash emptied (all properties permanently deleted)', [
                'deleted_count' => $deletedCount,
                'deleted_by' => auth()->id(),
                'deleted_at' => now()
            ]);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "{$deletedCount} property(s) permanently deleted successfully",
                    'deleted_count' => $deletedCount,
                    'trash_count' => 0
                ]);
            }
            
            return redirect()->route('properties.trash.index')
                ->with('success', "Trash emptied. {$deletedCount} property(s) permanently deleted.");
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to empty trash: ' . $e->getMessage(), [
                'user_id' => auth()->id()
            ]);
            
            return $this->errorResponse($request, 'Failed to empty trash. Please try again.');
        }
    }

    /**
     * Get count of trashed properties (for AJAX badge updates)
     */
    public function getTrashCount(Request $request)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        $count = Property::onlyTrashed()->count();
        
        return response()->json([
            'success' => true,
            'count' => $count,
            'message' => 'Trash count retrieved successfully'
        ]);
    }

    // ==================== HELPER METHODS ====================

    private function applyFilters($query, Request $request)
    {
        $filters = [
            'street_name' => 'like',
            'landlord_id' => 'exact',
            'zone' => 'like',
            'status' => 'exact',
            'digital_address' => 'like',
            'registration_plan_id' => 'exact',
            'house_number' => 'like',
            'block_number' => 'like',
            'property_type_id' => 'exact',
            'custom_property_type' => 'like'
        ];

        foreach ($filters as $field => $type) {
            if ($request->filled($field)) {
                if ($type === 'like') {
                    $query->where($field, 'like', '%' . $request->$field . '%');
                } else {
                    $query->where($field, $request->$field);
                }
            }
        }

        if ($request->filled('has_photos')) {
            if ($request->has_photos === '1') {
                $query->has('photos');
            } elseif ($request->has_photos === '0') {
                $query->doesntHave('photos');
            }
        }

        if ($request->filled('is_rented')) {
            if ($request->is_rented == '1') {
                $query->whereHas('tenants');
            } else {
                $query->whereDoesntHave('tenants');
            }
        }

        if ($request->has('has_digital_address') && $request->has_digital_address !== '') {
            if ($request->has_digital_address === '1') {
                $query->hasDigitalAddress();
            } else if ($request->has_digital_address === '0') {
                $query->missingDigitalAddress();
            }
        }
        
        // Filter by deletion date range
        if ($request->filled('deleted_from')) {
            $query->whereDate('deleted_at', '>=', $request->deleted_from);
        }
        
        if ($request->filled('deleted_to')) {
            $query->whereDate('deleted_at', '<=', $request->deleted_to);
        }
    }

    private function unauthorizedResponse(Request $request, $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }
        return redirect()->back()->with('error', $message);
    }

    private function errorResponse(Request $request, $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 500);
        }
        return redirect()->back()->with('error', $message);
    }
}