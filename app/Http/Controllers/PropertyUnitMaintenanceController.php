<?php

namespace App\Http\Controllers;

use App\Models\PropertyUnit;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Notifications\MaintenanceRequestNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class PropertyUnitMaintenanceController extends Controller
{
    // ========== MAINTENANCE REQUESTS ==========

    public function showMaintenanceRequests($id)
    {
        $unit = PropertyUnit::with([
            'maintenanceRequests' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
            'property.landlord',
            'tenant'
        ])->findOrFail($id);

        $this->checkUnitAccessWithError($unit);

        return view('property_units.maintenance-requests', compact('unit'));
    }

    /**
     * Show the form to create a maintenance request
     */
    public function createMaintenanceRequestForm($id)
    {
        $unit = PropertyUnit::with(['property.landlord'])->findOrFail($id);
        $this->checkUnitAccessWithError($unit);
        
        return view('property_units.create-maintenance-request', compact('unit'));
    }

    /**
     * Create a new maintenance request with notifications
     * 
     * ✅ FIXED: Only the landlord receives notifications, not admins
     */
    public function createMaintenanceRequest(Request $request, $id)
    {
        $unit = PropertyUnit::with(['property.landlord', 'tenant'])->findOrFail($id);
        $user = auth()->user();

        $this->checkUnitAccessWithError($unit);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string|min:20|max:1000',
            'priority' => 'required|in:low,medium,high,urgent',
            'category' => 'required|in:plumbing,electrical,appliance,structural,cleaning,other',
            'photos.*' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();
        try {
            $photos = [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store("maintenance_requests/{$unit->id}", 'public');
                    $photos[] = $path;
                }
            }

            // Get the landlord (property owner)
            $landlord = $unit->property->landlord;
            
            if (!$landlord) {
                throw new \Exception('No landlord found for this property. Please contact admin.');
            }

            // Generate reference ID
            $referenceId = 'MR' . date('ymd') . str_pad(
                MaintenanceRequest::whereDate('created_at', today())->count() + 1,
                4,
                '0',
                STR_PAD_LEFT
            );

            // Create maintenance request with landlord_id and tenant_id
            $maintenanceRequest = MaintenanceRequest::create([
                'unit_id' => $unit->id,
                'property_id' => $unit->property_id,
                'tenant_id' => $unit->tenant_id,
                'landlord_id' => $landlord->id,
                'reported_by_user_id' => $user->id,
                'reported_by_type' => $user->type,
                'title' => $request->title,
                'description' => $request->description,
                'priority' => $request->priority,
                'category' => $request->category,
                'images' => $photos,
                'status' => 'pending',
                'reference_id' => $referenceId,
                'created_by' => $user->id,
                'source' => 'tenant',
            ]);

            // ✅ Send notification ONLY to the landlord (property owner)
            $landlord->notify(new MaintenanceRequestNotification(
                $maintenanceRequest,
                $unit,
                'submitted',
                $user,
                [
                    'tenant_name' => $user->name,
                    'tenant_email' => $user->email,
                    'tenant_phone' => $user->phone ?? 'N/A'
                ]
            ));

            // ✅ Send confirmation ONLY to the tenant
            $user->notify(new MaintenanceRequestNotification(
                $maintenanceRequest,
                $unit,
                'submitted',
                $user,
                ['message' => 'Your maintenance request has been submitted successfully. The landlord has been notified.']
            ));

            // ❌ REMOVED: Admin notifications - they don't need to know about every maintenance request
            // Only the landlord should receive maintenance request notifications

            $this->logActivity(
                $user->id,
                'maintenance_request_created',
                'Maintenance request created',
                $unit->id,
                [
                    'request_id' => $maintenanceRequest->id,
                    'priority' => $request->priority,
                    'category' => $request->category,
                    'landlord_id' => $landlord->id,
                    'landlord_name' => $landlord->name,
                ]
            );

            Cache::forget('property_unit_' . $id . '_with_relations');

            DB::commit();

            // Log success for debugging
            Log::info('Maintenance request created and landlord notified', [
                'request_id' => $maintenanceRequest->id,
                'tenant_id' => $user->id,
                'landlord_id' => $landlord->id,
                'landlord_email' => $landlord->email,
                'source' => 'PropertyUnitMaintenanceController',
            ]);

            return redirect()->route('property-units.maintenance-requests', $unit->id)
                ->with('success', 'Maintenance request submitted successfully. The landlord has been notified.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating maintenance request: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Failed to submit maintenance request: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Mark maintenance complete with notifications
     */
    public function markMaintenanceComplete(Request $request, $id)
    {
        $unit = PropertyUnit::with(['property.landlord', 'tenant'])->findOrFail($id);
        $user = auth()->user();

        // Check if user is the landlord or admin
        $isLandlord = $user->isLandlord() && $unit->property->landlord_id === $user->id;
        $isAdmin = $user->isAdmin() || $user->isSuperAdmin();

        if (!$isLandlord && !$isAdmin) {
            return redirect()->back()
                ->with('error', 'Only the property owner or admin can mark maintenance as complete.')
                ->withInput();
        }

        if ($unit->status !== PropertyUnit::STATUS_UNDER_MAINTENANCE) {
            return redirect()->back()
                ->with('error', 'Unit is not under maintenance.')
                ->withInput();
        }

        $validator = Validator::make($request->all(), [
            'maintenance_notes' => 'required|string|min:20|max:1000',
            'maintenance_cost' => 'nullable|numeric|min:0',
            'completed_date' => 'required|date|before_or_equal:today',
            'photos.*' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();
        try {
            $photos = [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store("maintenance_completed/{$unit->id}", 'public');
                    $photos[] = $path;
                }
            }

            $unit->status = PropertyUnit::STATUS_AVAILABLE;
            $unit->is_available = true;
            $unit->maintenance_history = array_merge($unit->maintenance_history ?? [], [
                [
                    'completed_date' => $request->completed_date,
                    'notes' => $request->maintenance_notes,
                    'cost' => $request->maintenance_cost,
                    'images' => $photos,
                    'completed_by' => $user->id,
                    'completed_by_type' => $user->type,
                    'completed_at' => now()->toISOString()
                ]
            ]);
            $unit->save();

            // Get the maintenance request for this unit (if any)
            $maintenanceRequest = MaintenanceRequest::where('unit_id', $unit->id)
                ->where('status', '!=', 'completed')
                ->where('status', '!=', 'cancelled')
                ->orderBy('created_at', 'desc')
                ->first();

            // Notify tenant that maintenance is complete
            if ($unit->tenant_id) {
                $tenant = User::find($unit->tenant_id);
                if ($tenant) {
                    if ($maintenanceRequest) {
                        $tenant->notify(new MaintenanceRequestNotification(
                            $maintenanceRequest,
                            $unit,
                            'completed',
                            $user,
                            ['message' => 'The maintenance on your unit has been completed. Your unit is now available.']
                        ));
                    } else {
                        // Fallback notification without specific request
                        $this->sendMaintenanceCompleteNotification($unit, $user);
                    }
                }
            }

            // ✅ Notify landlord that maintenance is complete (if not the one completing it)
            if ($unit->property->landlord_id && $unit->property->landlord_id !== $user->id) {
                $landlord = User::find($unit->property->landlord_id);
                if ($landlord && $maintenanceRequest) {
                    $landlord->notify(new MaintenanceRequestNotification(
                        $maintenanceRequest,
                        $unit,
                        'completed',
                        $user,
                        ['message' => 'Maintenance has been completed on your property unit.']
                    ));
                }
            }

            $this->logActivity(
                $user->id,
                'maintenance_completed',
                'Maintenance completed, unit marked as available',
                $unit->id,
                [
                    'maintenance_cost' => $request->maintenance_cost,
                    'completed_date' => $request->completed_date
                ]
            );

            $this->clearUnitCaches($user->id);
            Cache::forget('property_unit_' . $id . '_with_relations');
            Cache::forget('unit_stats_' . $id);

            DB::commit();

            return redirect()->route('property-units.show', $unit->id)
                ->with('success', 'Maintenance completed. Unit is now available for rent.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error marking maintenance complete: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Failed to mark maintenance as complete: ' . $e->getMessage())
                ->withInput();
        }
    }

    // ========== MAINTENANCE REQUEST MANAGEMENT ==========

    /**
     * Update maintenance request with notifications
     */
    public function updateMaintenanceRequest(Request $request, $unitId, $requestId)
    {
        $unit = PropertyUnit::with(['property.landlord', 'tenant'])->findOrFail($unitId);
        $maintenanceRequest = MaintenanceRequest::findOrFail($requestId);
        $user = auth()->user();
        
        // Check if user is the landlord or admin
        $isLandlord = $user->isLandlord() && $unit->property->landlord_id === $user->id;
        $isAdmin = $user->isAdmin() || $user->isSuperAdmin();

        if (!$isLandlord && !$isAdmin) {
            return redirect()->back()
                ->with('error', 'Only the property owner or admin can update maintenance requests.')
                ->withInput();
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,assigned,in_progress,completed,cancelled',
            'assigned_to' => 'nullable|exists:users,id',
            'estimated_completion_date' => 'nullable|date|after:today',
            'notes' => 'nullable|string|max:1000',
            'cost_estimate' => 'nullable|numeric|min:0',
            'actual_cost' => 'nullable|numeric|min:0',
            'resolution_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();
        try {
            $oldStatus = $maintenanceRequest->status;
            
            $maintenanceRequest->update([
                'status' => $request->status,
                'assigned_to_user_id' => $request->assigned_to,
                'estimated_completion_date' => $request->estimated_completion_date,
                'notes' => $request->notes,
                'cost_estimate' => $request->cost_estimate,
                'actual_cost' => $request->actual_cost,
                'resolution_details' => $request->resolution_notes,
                'updated_by' => auth()->id(),
            ]);

            if ($request->status === 'completed' && !$maintenanceRequest->completed_date) {
                $maintenanceRequest->completed_date = now();
                $maintenanceRequest->save();
            }

            if ($oldStatus !== $request->status) {
                // Notify tenant about status change
                if ($maintenanceRequest->tenant_id) {
                    $tenant = User::find($maintenanceRequest->tenant_id);
                    if ($tenant) {
                        $tenant->notify(new MaintenanceRequestNotification(
                            $maintenanceRequest,
                            $unit,
                            'status_updated',
                            $user,
                            [
                                'old_status' => $oldStatus,
                                'new_status' => $request->status,
                                'message' => "Your maintenance request status has been updated to " . ucfirst(str_replace('_', ' ', $request->status)) . "."
                            ]
                        ));
                    }
                }

                // Notify landlord if not the one updating
                if ($unit->property->landlord_id && $unit->property->landlord_id !== $user->id) {
                    $landlord = User::find($unit->property->landlord_id);
                    if ($landlord) {
                        $landlord->notify(new MaintenanceRequestNotification(
                            $maintenanceRequest,
                            $unit,
                            'status_updated',
                            $user,
                            [
                                'old_status' => $oldStatus,
                                'new_status' => $request->status,
                                'tenant_name' => $maintenanceRequest->reporter?->name ?? 'Tenant'
                            ]
                        ));
                    }
                }

                // If status is 'completed', send completion notification
                if ($request->status === 'completed') {
                    if ($maintenanceRequest->tenant_id) {
                        $tenant = User::find($maintenanceRequest->tenant_id);
                        if ($tenant) {
                            $tenant->notify(new MaintenanceRequestNotification(
                                $maintenanceRequest,
                                $unit,
                                'completed',
                                $user,
                                ['message' => 'Your maintenance request has been completed.']
                            ));
                        }
                    }
                }

                $this->logActivity(
                    auth()->id(),
                    'maintenance_request_updated',
                    'Maintenance request status updated',
                    $unit->id,
                    [
                        'request_id' => $maintenanceRequest->id,
                        'old_status' => $oldStatus,
                        'new_status' => $request->status,
                        'assigned_to' => $request->assigned_to,
                    ]
                );
            }

            Cache::forget('property_unit_' . $unitId . '_with_relations');

            DB::commit();

            return redirect()->back()
                ->with('success', 'Maintenance request updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating maintenance request: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Failed to update maintenance request: ' . $e->getMessage());
        }
    }

    public function showMaintenanceRequestDetails($unitId, $requestId)
    {
        $unit = PropertyUnit::with(['property.landlord'])->findOrFail($unitId);
        $maintenanceRequest = MaintenanceRequest::with(['reporter', 'assignedTo', 'unit', 'property', 'landlord'])
            ->where('unit_id', $unitId)
            ->findOrFail($requestId);
        
        $this->checkUnitAccessWithError($unit);

        return view('property_units.maintenance-request-details', compact('unit', 'maintenanceRequest'));
    }

    /**
 * Cancel a maintenance request with notifications
 */
public function cancelMaintenanceRequest(Request $request, $unitId, $requestId)
{
    $unit = PropertyUnit::with(['property.landlord', 'tenant'])->findOrFail($unitId);
    $maintenanceRequest = MaintenanceRequest::findOrFail($requestId);
    $user = auth()->user();

    // Check if user is the creator (tenant) or landlord or admin
    $isCreator = $maintenanceRequest->reported_by_user_id === $user->id;
    $isLandlord = $user->isLandlord() && $unit->property->landlord_id === $user->id;
    $isAdmin = $user->isAdmin() || $user->isSuperAdmin();

    if (!$isCreator && !$isLandlord && !$isAdmin) {
        return redirect()->back()
            ->with('error', 'You are not authorized to cancel this request.');
    }

    if ($maintenanceRequest->status !== 'pending') {
        return redirect()->back()
            ->with('error', 'Only pending requests can be cancelled.');
    }

    DB::beginTransaction();
    try {
        $cancellationReason = $request->input('reason', 'Cancelled by user');
        
        // ✅ FIXED: Remove columns that don't exist
        $maintenanceRequest->update([
            'status' => 'cancelled',
            'updated_by' => $user->id,
            // ❌ REMOVED: 'cancelled_by' => $user->id,
            // ❌ REMOVED: 'cancelled_at' => now(),
            // ❌ REMOVED: 'cancellation_reason' => $cancellationReason,
        ]);
        
        // Store cancellation info in notes
        $notes = $maintenanceRequest->notes ?? '';
        $maintenanceRequest->notes = $notes . "\n[CANCELLED] By: {$user->name} | Reason: {$cancellationReason} | Date: " . now()->toDateTimeString();
        $maintenanceRequest->save();

        // Notify landlord if tenant cancelled
        if ($isCreator && $maintenanceRequest->landlord_id) {
            $landlord = User::find($maintenanceRequest->landlord_id);
            if ($landlord) {
                $landlord->notify(new MaintenanceRequestNotification(
                    $maintenanceRequest,
                    $unit,
                    'cancelled',
                    $user,
                    [
                        'cancellation_reason' => $cancellationReason,
                        'tenant_name' => $user->name,
                        'message' => "Maintenance request has been cancelled by the tenant."
                    ]
                ));
            }
        }

        // Notify tenant if landlord/admin cancelled
        if (($isLandlord || $isAdmin) && $maintenanceRequest->tenant_id) {
            $tenant = User::find($maintenanceRequest->tenant_id);
            if ($tenant) {
                $tenant->notify(new MaintenanceRequestNotification(
                    $maintenanceRequest,
                    $unit,
                    'cancelled',
                    $user,
                    [
                        'cancellation_reason' => $cancellationReason,
                        'message' => "Your maintenance request has been cancelled."
                    ]
                ));
            }
        }

        $this->logActivity(
            $user->id,
            'maintenance_request_cancelled',
            'Maintenance request cancelled',
            $unit->id,
            [
                'request_id' => $maintenanceRequest->id,
                'cancelled_by' => $user->name,
                'reason' => $cancellationReason,
            ]
        );

        DB::commit();

        return redirect()->back()
            ->with('success', 'Maintenance request cancelled successfully.');

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error cancelling maintenance request: ' . $e->getMessage());

        return redirect()->back()
            ->with('error', 'Failed to cancel maintenance request.');
    }
}

    // ========== NOTIFICATION METHODS (FALLBACK) ==========

    /**
     * Send notification to tenant when maintenance is complete (fallback)
     */
    private function sendMaintenanceCompleteNotification(PropertyUnit $unit, User $landlord): void
    {
        try {
            $tenant = User::find($unit->tenant_id);
            
            if (!$tenant) {
                return;
            }

            // Create a simple notification in database
            $notificationData = [
                'type' => 'maintenance_complete',
                'unit_id' => $unit->id,
                'unit_number' => $unit->unit_number,
                'property_name' => $unit->property->property_name ?? 'N/A',
                'completed_at' => now()->toDateTimeString(),
                'completed_by' => $landlord->name,
                'message' => 'The maintenance on your unit has been completed. The unit is now available.',
                'action_url' => route('property-units.show', $unit->id),
                'icon' => 'fas fa-check-circle text-success',
                'title' => '✅ Maintenance Completed',
                'roles' => ['tenant'],
            ];

            DB::table('notifications')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'type' => 'maintenance_complete',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id' => $tenant->id,
                'data' => json_encode($notificationData),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('Maintenance complete notification sent to tenant', [
                'tenant_id' => $tenant->id,
                'unit_id' => $unit->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send maintenance complete notification: ' . $e->getMessage());
        }
    }

    // ========== HELPER METHODS ==========

    private function checkUnitAccessWithError(PropertyUnit $unit): void
    {
        $user = auth()->user();
        
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return;
        }

        if ($user->isLandlord()) {
            if ($unit->property->landlord_id === $user->id) {
                return;
            }
            abort(403, 'You can only view units in your own properties.');
        }

        if ($user->isTenant()) {
            if ($unit->tenant_id === $user->id && $unit->tenant_status === PropertyUnit::TENANT_STATUS_APPROVED) {
                return;
            }
            abort(403, 'You can only view your assigned unit.');
        }

        abort(403, 'Unauthorized access to property unit.');
    }

    private function logActivity(int $userId, string $type, string $description, ?int $unitId = null, array $metadata = []): void
    {
        try {
            \App\Models\ActivityLog::create([
                'user_id' => $userId,
                'type' => $type,
                'description' => $description,
                'unit_id' => $unitId,
                'metadata' => $metadata,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log activity: ' . $e->getMessage());
        }
    }

    private function clearUnitCaches(int $userId): void
    {
        Cache::forget('property_unit_stats_' . $userId);
        Cache::forget('dashboard_stats_' . $userId);
        Cache::forget('tenant_unit_' . $userId);
    }
}