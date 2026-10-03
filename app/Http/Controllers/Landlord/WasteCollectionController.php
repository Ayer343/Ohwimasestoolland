<?php
// app/Http/Controllers/Landlord/WasteCollectionController.php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\WasteCollectionRequest;
use App\Models\Property;
use App\Models\SanitationPersonnel;
use App\Models\SanitationServiceRequest;
use App\Models\SanitationSetting;
use App\Models\User;
use App\Notifications\GeneralNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WasteCollectionController extends Controller
{
    /**
     * Status of a request that is "in flight" (not finished).
     */
    private const ACTIVE_STATUSES = ['pending', 'assigned', 'en_route', 'arrived', 'in_progress'];

    /**
     * Fallback fee matrix used when SanitationSetting doesn't have
     * `frequency_pricing` configured yet.
     */
    private const FALLBACK_FREQUENCY_PRICING = [
        'daily'    => ['per_visit' => 30.0,  'per_month' => 650.0, 'emergency' => 90.0],
        'weekly'   => ['per_visit' => 50.0,  'per_month' => 200.0, 'emergency' => 90.0],
        'biweekly' => ['per_visit' => 55.0,  'per_month' => 110.0, 'emergency' => 90.0],
        'monthly'  => ['per_visit' => 60.0,  'per_month' =>  65.0, 'emergency' => 90.0],
    ];

    /**
     * Approval statuses on WasteCollectionRequest that count as
     * "the property has been linked/serviced".
     */
    private const LINKED_APPROVAL_STATUSES = [
        WasteCollectionRequest::APPROVAL_PENDING,
        WasteCollectionRequest::APPROVAL_APPROVED,
        WasteCollectionRequest::APPROVAL_AUTO_APPROVED,
    ];

    // ================================================================ //
    // 📊 LISTING                                                      //
    // ================================================================ //

    public function pendingApprovals(Request $request)
    {
        $propertyIds = $this->landlordPropertyIds();

        $pendingRequests = WasteCollectionRequest::with(['property', 'requestedBy', 'assignedTo'])
            ->whereIn('property_id', $propertyIds)
            ->where('approval_status', WasteCollectionRequest::APPROVAL_PENDING)
            ->orderBy('approval_expires_at', 'asc')
            ->paginate(20);

        $stats = $this->getApprovalStats($propertyIds);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data'    => $pendingRequests,
                'stats'   => $stats,
            ]);
        }

        return view('landlord.waste.approvals', compact('pendingRequests', 'stats'));
    }

    public function myRequests(Request $request)
    {
        $propertyIds = $this->landlordPropertyIds();

        $query = WasteCollectionRequest::with(['property', 'requestedBy', 'assignedTo', 'approvedBy'])
            ->whereIn('property_id', $propertyIds);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->approval_status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhereHas('property', function ($pq) use ($search) {
                      $pq->where('property_name', 'like', "%{$search}%")
                         ->orWhere('digital_address', 'like', "%{$search}%");
                  });
            });
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(20);

        $statuses         = WasteCollectionRequest::getStatuses();
        $approvalStatuses = WasteCollectionRequest::getApprovalStatuses();

        $myProperties = Property::where('landlord_id', Auth::id())
            ->whereHas('wasteCollectionRequests', function ($q) {
                $q->whereNotIn('approval_status', [
                    WasteCollectionRequest::APPROVAL_REJECTED,
                ]);
            })
            ->orderBy('property_name')
            ->get(['id', 'property_name', 'digital_address']);

        $unlinkedProperties = $this->unlinkedPropertiesForLandlord();

        // -----------------------------------------------------------------
        // ✅ FIX: Pending service requests — self-healing query
        //
        // A service request is considered "still pending" ONLY IF:
        //   1. status = pending
        //   2. no one has responded (responded_at / responded_by are null)
        //   3. the property has NOT yet been linked to a waste collection request
        //
        // Point 3 is the key defense: once the sanitation team links the
        // property, the service request is functionally resolved, even if
        // the sanitation side forgot to flip the status column.
        // -----------------------------------------------------------------
        $pendingServiceRequests = SanitationServiceRequest::query()
            ->forLandlord(Auth::id())
            ->pending()
            ->whereNull('responded_at')
            ->whereNull('responded_by')
            ->whereDoesntHave('property.wasteCollectionRequests', function ($q) {
                $q->whereIn('approval_status', self::LINKED_APPROVAL_STATUSES);
            })
            ->with('property:id,property_name,digital_address')
            ->orderBy('created_at', 'desc')
            ->get();

        // Belt-and-braces: filter once more in PHP in case the relationship
        // name on the model is different or eager-loading missed something.
        $pendingServiceRequests = $pendingServiceRequests->filter(function ($sr) {
            if (!empty($sr->responded_at) || !empty($sr->responded_by)) {
                return false;
            }

            $property = $sr->property;
            if ($property && method_exists($property, 'wasteCollectionRequests')) {
                $alreadyLinked = $property->wasteCollectionRequests()
                    ->whereIn('approval_status', self::LINKED_APPROVAL_STATUSES)
                    ->exists();

                if ($alreadyLinked) {
                    return false;
                }
            }

            return true;
        })->values();

        $hasSanitationSupervisors = $this->hasActiveSupervisors();

        $sanitationSettings = $this->resolveSanitationSettings();

        if ($request->expectsJson()) {
            return response()->json([
                'success'                    => true,
                'data'                       => $requests,
                'linked_properties'          => $myProperties,
                'unlinked_properties'        => $unlinkedProperties,
                'has_sanitation_supervisors' => $hasSanitationSupervisors,
                'pending_service_requests'   => $pendingServiceRequests,
                'frequency_pricing'          => $this->resolveFrequencyPricing($sanitationSettings),
            ]);
        }

        return view('landlord.waste.requests', compact(
            'requests',
            'statuses',
            'approvalStatuses',
            'myProperties',
            'unlinkedProperties',
            'hasSanitationSupervisors',
            'pendingServiceRequests',
            'sanitationSettings'
        ));
    }

    public function history(Request $request)
    {
        $propertyIds = $this->landlordPropertyIds();

        $history = WasteCollectionRequest::with(['property', 'requestedBy', 'assignedTo'])
            ->whereIn('property_id', $propertyIds)
            ->where('status', WasteCollectionRequest::STATUS_COMPLETED)
            ->orderBy('completed_at', 'desc')
            ->paginate(20);

        $baseCompleted = WasteCollectionRequest::whereIn('property_id', $propertyIds)
            ->where('status', WasteCollectionRequest::STATUS_COMPLETED);

        $stats = [
            'total_collections' => (clone $baseCompleted)->count(),
            'total_weight'      => (clone $baseCompleted)->sum('waste_weight_kg'),
            'this_month'        => (clone $baseCompleted)
                ->whereMonth('completed_at', now()->month)
                ->whereYear('completed_at', now()->year)
                ->count(),
            'last_collection'   => (clone $baseCompleted)
                ->latest('completed_at')
                ->first(),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data'    => $history,
                'stats'   => $stats,
            ]);
        }

        return view('landlord.waste.history', compact('history', 'stats'));
    }

    // ================================================================ //
    // 🔍 SHOW                                                         //
    // ================================================================ //

    public function showRequest(Request $request, $wasteCollectionRequest)
    {
        $model = $this->findOwnedRequest($wasteCollectionRequest);

        if (!$model) {
            return $this->notFound($request, 'Request not found or you do not own this property.');
        }

        $model->load(['property', 'requestedBy', 'assignedTo', 'approvedBy', 'worker']);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data'    => $model,
            ]);
        }

        return view('landlord.waste.show', ['wasteCollectionRequest' => $model]);
    }

    // ================================================================ //
    // ✅ APPROVE / REJECT                                             //
    // ================================================================ //

    public function approveRequest(Request $request, $wasteCollectionRequest)
    {
        $model = $this->findOwnedRequest($wasteCollectionRequest);

        if (!$model) {
            return $this->notFound($request, 'Request not found or you do not own this property.');
        }

        if ($model->approval_status !== WasteCollectionRequest::APPROVAL_PENDING) {
            return $this->fail($request, 'This request is no longer pending approval.', 400);
        }

        try {
            DB::beginTransaction();

            $model->approve(Auth::id(), $request->input('notes'));
            $this->notifySanitationTeam($model, 'approved');

            // ✅ Close any matching SanitationServiceRequest now that
            //    the property has an approved waste collection request.
            $this->resolveServiceRequestsForProperty($model->property_id);

            DB::commit();

            Log::info('Landlord approved waste collection request', [
                'request_id'  => $model->id,
                'landlord_id' => Auth::id(),
            ]);

            return $this->ok($request, 'Request approved successfully!', $model, 'landlord.waste.approvals');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve request: ' . $e->getMessage(), [
                'request_id'  => $model->id,
                'landlord_id' => Auth::id(),
            ]);

            return $this->fail($request, 'Failed to approve request.', 500);
        }
    }

    public function rejectRequest(Request $request, $wasteCollectionRequest)
    {
        $model = $this->findOwnedRequest($wasteCollectionRequest);

        if (!$model) {
            return $this->notFound($request, 'Request not found or you do not own this property.');
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        if ($model->approval_status !== WasteCollectionRequest::APPROVAL_PENDING) {
            return $this->fail($request, 'This request is no longer pending approval.', 400);
        }

        try {
            DB::beginTransaction();

            $model->reject(Auth::id(), $request->rejection_reason);
            $this->notifySanitationTeam($model, 'rejected');

            DB::commit();

            Log::info('Landlord rejected waste collection request', [
                'request_id'  => $model->id,
                'landlord_id' => Auth::id(),
                'reason'      => $request->rejection_reason,
            ]);

            return $this->ok($request, 'Request rejected successfully.', $model, 'landlord.waste.approvals');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reject request: ' . $e->getMessage(), [
                'request_id'  => $model->id,
                'landlord_id' => Auth::id(),
            ]);

            return $this->fail($request, 'Failed to reject request.', 500);
        }
    }

    // ================================================================ //
    // ✅ BULK APPROVE                                                 //
    // ================================================================ //

    public function bulkApprove(Request $request)
    {
        $propertyIds = $this->landlordPropertyIds();

        $validator = Validator::make($request->all(), [
            'request_ids'   => 'required|array|min:1',
            'request_ids.*' => [
                'integer',
                Rule::exists('waste_collection_requests', 'id')
                    ->whereIn('property_id', $propertyIds),
            ],
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }
            return redirect()->back()->withErrors($validator);
        }

        try {
            DB::beginTransaction();

            $approved = 0;
            $skipped  = 0;
            $affectedPropertyIds = [];

            $ids = array_unique($request->request_ids);

            foreach ($ids as $id) {
                $model = WasteCollectionRequest::whereIn('property_id', $propertyIds)->find($id);

                if (!$model || $model->approval_status !== WasteCollectionRequest::APPROVAL_PENDING) {
                    $skipped++;
                    continue;
                }

                $model->approve(Auth::id(), 'Bulk approval');
                $this->notifySanitationTeam($model, 'approved');

                $affectedPropertyIds[] = $model->property_id;
                $approved++;
            }

            // ✅ Close any matching service requests for the affected properties
            foreach (array_unique($affectedPropertyIds) as $propertyId) {
                $this->resolveServiceRequestsForProperty($propertyId);
            }

            DB::commit();

            Log::info('Landlord bulk approved waste collection requests', [
                'landlord_id' => Auth::id(),
                'approved'    => $approved,
                'skipped'     => $skipped,
            ]);

            $message = "Successfully approved {$approved} request(s)."
                . ($skipped > 0 ? " Skipped {$skipped} (already processed)." : '');

            if ($request->expectsJson()) {
                return response()->json([
                    'success'        => true,
                    'message'        => $message,
                    'approved_count' => $approved,
                    'skipped_count'  => $skipped,
                ]);
            }

            return redirect()->route('landlord.waste.approvals')->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to bulk approve: ' . $e->getMessage(), [
                'landlord_id' => Auth::id(),
            ]);

            return $this->fail($request, 'Failed to bulk approve.', 500);
        }
    }

    // ================================================================ //
    // 🗑️ BIN FULL                                                    //
    // ================================================================ //

    public function reportBinFull(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|integer|exists:properties,id',
            'waste_type'  => 'nullable|in:general,recyclable,organic,hazardous,bulk',
            'priority'    => 'nullable|in:low,medium,high,emergency',
            'notes'       => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->fail($request, $validator->errors()->first(), 422);
        }

        $property = Property::where('id', $request->property_id)
            ->where('landlord_id', Auth::id())
            ->first();

        if (!$property) {
            return $this->notFound($request, 'Property not found or you do not own it.');
        }

        if (WasteCollectionRequest::hasFreshBinFullReport($property->id)) {
            return $this->fail(
                $request,
                'A bin-full report was already submitted recently. Please wait '
                . WasteCollectionRequest::BIN_FULL_COOLDOWN_HOURS
                . ' hours before reporting again.',
                429
            );
        }

        try {
            DB::beginTransaction();

            $payload = [
                'property_id'           => $property->id,
                'requested_by'          => Auth::id(),
                'waste_type'            => $request->input('waste_type', 'general'),
                'priority'              => $request->input('priority', 'high'),
                'status'                => 'pending',
                'approval_status'       => WasteCollectionRequest::APPROVAL_AUTO_APPROVED,
                'approved_by'           => Auth::id(),
                'approval_requested_at' => now(),
                'approval_responded_at' => now(),
                'approval_expires_at'   => now(),
                'digital_address'       => $property->digital_address,
                'latitude'              => $property->latitude ?? null,
                'longitude'             => $property->longitude ?? null,
                'source'                => 'bin_full',
                'bin_full_reported_at'  => now(),
                'metadata'              => [
                    'collection_linked'    => true,
                    'reported_via'         => 'landlord_bin_full_button',
                    'reported_by_name'     => Auth::user()->name,
                    'reported_by_phone'    => Auth::user()->phone,
                    'reported_at'          => now()->toISOString(),
                    'landlord_notes'       => $request->input('notes'),
                    'auto_approved'        => true,
                    'auto_approved_reason' => 'Landlord initiated bin-full report',
                ],
            ];

            if (!empty($property->collection_zone_id)) {
                $payload['collection_zone_id'] = $property->collection_zone_id;
            }

            $collectionRequest = WasteCollectionRequest::create($payload);

            $this->notifySanitationTeam($collectionRequest, 'bin_full');

            DB::commit();

            Log::info('Landlord reported bin full', [
                'request_id'  => $collectionRequest->id,
                'property_id' => $property->id,
                'landlord_id' => Auth::id(),
            ]);

            return $this->ok(
                $request,
                'Bin-full report submitted. Sanitation team has been notified.',
                $collectionRequest,
                'landlord.waste.requests'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to report bin full: ' . $e->getMessage(), [
                'property_id' => $property->id,
                'landlord_id' => Auth::id(),
            ]);
            return $this->fail($request, 'Failed to submit bin-full report. Please try again.', 500);
        }
    }

    // ================================================================ //
    // 🆕 REQUEST SANITATION SERVICE                                   //
    // ================================================================ //

    public function requestSanitationService(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => [
                'required',
                'integer',
                Rule::exists('properties', 'id')
                    ->where('landlord_id', Auth::id()),
            ],
            'collection_frequency' => [
                'required',
                'string',
                Rule::in(SanitationSetting::COLLECTION_FREQUENCIES),
            ],
            'message' => 'nullable|string|max:500',
            'agreement_accepted' => ['accepted'],
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'errors'  => $validator->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $property = Property::find($request->property_id);

        if (!$property) {
            return $this->notFound($request, 'Property not found or you do not own it.');
        }

        // -----------------------------------------------------------------
        // 2. Business guards
        // -----------------------------------------------------------------

        $alreadyLinked = $property->wasteCollectionRequests()
            ->whereIn('approval_status', self::LINKED_APPROVAL_STATUSES)
            ->exists();

        if ($alreadyLinked) {
            return $this->fail(
                $request,
                'This property already has an active waste collection request.',
                422
            );
        }

        if (SanitationServiceRequest::hasPendingForProperty($property->id)) {
            return $this->fail(
                $request,
                'A sanitation service request for this property is already pending.',
                422
            );
        }

        // -----------------------------------------------------------------
        // 3. Resolve target supervisors
        // -----------------------------------------------------------------
        $supervisors = $this->getActiveSanitationSupervisorModels();

        if ($supervisors->isEmpty()) {
            return $this->fail(
                $request,
                'No sanitation supervisors are currently available. Please try again later.',
                422
            );
        }

        // -----------------------------------------------------------------
        // 4. Resolve the agreed pricing (snapshot)
        // -----------------------------------------------------------------
        $settings        = $this->resolveSanitationSettings();
        $pricing         = $this->resolveFrequencyPricing($settings);
        $frequency       = $request->input('collection_frequency');

        $quotedMonthly   = (float) ($pricing[$frequency]['per_month'] ?? 0);
        $quotedEmergency = (float) ($pricing[$frequency]['emergency'] ?? $settings->emergency_collection_fee ?? 0);

        if ($quotedMonthly <= 0) {
            return $this->fail(
                $request,
                'Pricing for the selected collection frequency is not configured. Please contact the administrator.',
                422
            );
        }

        // -----------------------------------------------------------------
        // 5. Persist + notify supervisors only
        // -----------------------------------------------------------------
        try {
            DB::beginTransaction();

            $primarySupervisor = $supervisors->first();

            $serviceRequest = SanitationServiceRequest::create([
                'property_id'               => $property->id,
                'landlord_id'               => Auth::id(),
                'sanitation_personnel_id'   => $primarySupervisor->id,
                'status'                    => SanitationServiceRequest::STATUS_PENDING,
                'priority'                  => SanitationServiceRequest::PRIORITY_MEDIUM,
                'message'                   => $request->input('message'),
                'expires_at'                => SanitationServiceRequest::defaultExpiry(),

                'collection_frequency'      => $frequency,
                'quoted_monthly_fee'        => $quotedMonthly,
                'quoted_emergency_fee'      => $quotedEmergency,
                'quoted_currency'           => 'GHS',
                'agreement_accepted_at'     => now(),
                'agreement_accepted_ip'     => $request->ip(),
                'agreement_terms_snapshot'  => [
                    'late_fee_percentage' => (float) ($settings->late_fee_percentage ?? 5),
                    'company_name'        => $settings->company_name ?? null,
                    'frequencies'         => SanitationSetting::COLLECTION_FREQUENCIES,
                    'accepted_at'         => now()->toIso8601String(),
                ],

                'property_name'             => $property->property_name,
                'digital_address'           => $property->digital_address,
                'landlord_name'             => Auth::user()->name,
                'landlord_phone'            => Auth::user()->phone,
                'landlord_email'            => Auth::user()->email,
                'preferred_supervisor_name' => $primarySupervisor->full_name,
            ]);

            $this->notifyAllSupervisorsServiceRequest($property, $supervisors, $serviceRequest);

            DB::commit();

            Log::info('Landlord requested sanitation service', [
                'service_request_id'   => $serviceRequest->id,
                'property_id'          => $property->id,
                'landlord_id'          => Auth::id(),
                'supervisors_notified' => $supervisors->pluck('id')->all(),
                'frequency'            => $frequency,
                'quoted_monthly_fee'   => $quotedMonthly,
            ]);

            return $this->ok(
                $request,
                'Service request sent! The sanitation team will contact you shortly.',
                $serviceRequest,
                'landlord.waste.requests'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to submit sanitation service request: ' . $e->getMessage(), [
                'property_id' => $property->id,
                'landlord_id' => Auth::id(),
            ]);
            return $this->fail($request, 'Failed to send service request. Please try again.', 500);
        }
    }

    /**
     * Cancel a pending service request that the landlord submitted.
     */
    public function cancelSanitationServiceRequest(Request $request, $propertyId)
    {
        $property = Property::where('id', $propertyId)
            ->where('landlord_id', Auth::id())
            ->first();

        if (!$property) {
            return $this->notFound($request, 'Property not found or you do not own it.');
        }

        $serviceRequest = SanitationServiceRequest::query()
            ->forLandlord(Auth::id())
            ->forProperty($property->id)
            ->pending()
            ->first();

        if (!$serviceRequest) {
            return $this->fail($request, 'No pending service request to cancel.', 422);
        }

        try {
            DB::beginTransaction();

            $serviceRequest->update([
                'status'       => SanitationServiceRequest::STATUS_CANCELLED,
                'responded_at' => now(),
                'responded_by' => Auth::id(),
            ]);

            DB::commit();

            Log::info('Landlord cancelled sanitation service request', [
                'service_request_id' => $serviceRequest->id,
                'property_id'        => $property->id,
                'landlord_id'        => Auth::id(),
            ]);

            return $this->ok(
                $request,
                'Service request cancelled.',
                $serviceRequest,
                'landlord.waste.requests'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel sanitation service request: ' . $e->getMessage(), [
                'property_id' => $property->id,
                'landlord_id' => Auth::id(),
            ]);
            return $this->fail($request, 'Failed to cancel service request.', 500);
        }
    }

    // ================================================================ //
    // 🔧 HELPERS                                                      //
    // ================================================================ //

    /**
     * ✅ NEW: Close any pending SanitationServiceRequest rows for a property
     * once it has been linked to waste collection.
     *
     * Called after a landlord approve/bulk-approve, and also safe to call
     * from the sanitation side after they link a property.
     */
    private function resolveServiceRequestsForProperty(int $propertyId): int
    {
        return SanitationServiceRequest::query()
            ->where('property_id', $propertyId)
            ->where('status', SanitationServiceRequest::STATUS_PENDING)
            ->whereNull('responded_at')
            ->update([
                'status'       => SanitationServiceRequest::STATUS_APPROVED,
                'responded_at' => now(),
                'responded_by' => Auth::id(),
            ]);
    }

    private function landlordPropertyIds(): Collection
    {
        return Property::where('landlord_id', Auth::id())->pluck('id');
    }

    private function findOwnedRequest($wasteCollectionRequest): ?WasteCollectionRequest
    {
        $id = $wasteCollectionRequest instanceof WasteCollectionRequest
            ? $wasteCollectionRequest->id
            : $wasteCollectionRequest;

        if (!$id) {
            return null;
        }

        return WasteCollectionRequest::whereIn('property_id', $this->landlordPropertyIds())
            ->with('property')
            ->find($id);
    }

    private function getApprovalStats(Collection $propertyIds): array
    {
        $row = WasteCollectionRequest::whereIn('property_id', $propertyIds)
            ->selectRaw("
                COUNT(*) as total_requests,
                SUM(CASE WHEN approval_status = ? THEN 1 ELSE 0 END) as total_pending,
                SUM(CASE WHEN approval_status = ? THEN 1 ELSE 0 END) as total_approved,
                SUM(CASE WHEN approval_status = ? THEN 1 ELSE 0 END) as total_rejected,
                SUM(CASE WHEN approval_status = ?
                         AND approval_expires_at BETWEEN NOW() AND ?
                         THEN 1 ELSE 0 END) as expiring_soon
            ", [
                WasteCollectionRequest::APPROVAL_PENDING,
                WasteCollectionRequest::APPROVAL_APPROVED,
                WasteCollectionRequest::APPROVAL_REJECTED,
                WasteCollectionRequest::APPROVAL_PENDING,
                now()->addDays(2),
            ])
            ->first();

        return [
            'total_requests' => (int) ($row->total_requests ?? 0),
            'total_pending'  => (int) ($row->total_pending  ?? 0),
            'total_approved' => (int) ($row->total_approved ?? 0),
            'total_rejected' => (int) ($row->total_rejected ?? 0),
            'expiring_soon'  => (int) ($row->expiring_soon  ?? 0),
        ];
    }

    private function unlinkedPropertiesForLandlord(): Collection
    {
        return Property::where('landlord_id', Auth::id())
            ->whereDoesntHave('wasteCollectionRequests', function ($q) {
                $q->whereIn('approval_status', self::LINKED_APPROVAL_STATUSES);
            })
            ->orderBy('property_name')
            ->get(['id', 'property_name', 'digital_address', 'zone']);
    }

    private function getActiveSanitationSupervisorModels(): Collection
    {
        try {
            $supervisorRoles = defined(SanitationPersonnel::class . '::SUPERVISOR_ROLES')
                ? SanitationPersonnel::SUPERVISOR_ROLES
                : ['supervisor', 'team_lead', 'section_lead', 'post_commander'];

            return SanitationPersonnel::query()
                ->where(function ($q) use ($supervisorRoles) {
                    $q->whereIn('role', $supervisorRoles)
                      ->orWhere('can_be_supervisor', true);
                })
                ->where('status', 'active')
                ->with('user')
                ->orderBy('first_name')
                ->get();

        } catch (\Exception $e) {
            Log::warning('Failed to load sanitation supervisors', [
                'landlord_id' => Auth::id(),
                'error'       => $e->getMessage(),
            ]);
            return collect();
        }
    }

    private function hasActiveSupervisors(): bool
    {
        try {
            $supervisorRoles = defined(SanitationPersonnel::class . '::SUPERVISOR_ROLES')
                ? SanitationPersonnel::SUPERVISOR_ROLES
                : ['supervisor', 'team_lead', 'section_lead', 'post_commander'];

            return SanitationPersonnel::query()
                ->where(function ($q) use ($supervisorRoles) {
                    $q->whereIn('role', $supervisorRoles)
                      ->orWhere('can_be_supervisor', true);
                })
                ->where('status', 'active')
                ->exists();

        } catch (\Exception $e) {
            Log::warning('Failed to check for active sanitation supervisors', [
                'landlord_id' => Auth::id(),
                'error'       => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function resolveSanitationSettings(): SanitationSetting
    {
        try {
            return SanitationSetting::getActiveSettings()
                ?? SanitationSetting::getSettings();
        } catch (\Exception $e) {
            Log::warning('Failed to load sanitation settings for landlord', [
                'landlord_id' => Auth::id(),
                'error'       => $e->getMessage(),
            ]);
            return new SanitationSetting();
        }
    }

    private function resolveFrequencyPricing(SanitationSetting $settings): array
    {
        $configured = $settings->frequency_pricing ?? [];
        $fallback   = self::FALLBACK_FREQUENCY_PRICING;

        $defaultFee     = (float) ($settings->default_collection_fee   ?? 0);
        $emergencyFee   = (float) ($settings->emergency_collection_fee ?? 0);

        $matrix = [];

        foreach (SanitationSetting::COLLECTION_FREQUENCIES as $frequency) {
            $row = is_array($configured[$frequency] ?? null) ? $configured[$frequency] : [];

            $perMonth = (float) ($row['per_month'] ?? 0);
            $perVisit = (float) ($row['per_visit'] ?? 0);
            $emerg    = (float) ($row['emergency'] ?? 0);

            if ($perMonth <= 0) {
                $perMonth = $defaultFee > 0
                    ? $defaultFee
                    : ($fallback[$frequency]['per_month'] ?? 0);
            }
            if ($perVisit <= 0) {
                $perVisit = $fallback[$frequency]['per_visit'] ?? $perMonth;
            }
            if ($emerg <= 0) {
                $emerg = $emergencyFee > 0
                    ? $emergencyFee
                    : ($fallback[$frequency]['emergency'] ?? 0);
            }

            $matrix[$frequency] = [
                'per_visit' => round($perVisit, 2),
                'per_month' => round($perMonth, 2),
                'emergency' => round($emerg, 2),
            ];
        }

        return $matrix;
    }

    private function getSanitationNotificationRecipients(WasteCollectionRequest $model): Collection
    {
        if ($model->assignedTo) {
            $recipients = $model->assignedTo->notifiableUsers();

            if ($recipients->isNotEmpty()) {
                return $recipients;
            }
        }

        $sanitationUsers = User::query()
            ->where(function ($q) {
                $q->whereHas('roles', function ($rq) {
                    $rq->whereIn('slug', ['sanitation-personnel', 'sanitation-supervisor']);
                })
                ->orWhere('type', User::TYPE_SANITATION_PERSONNEL);
            })
            ->where('status', User::STATUS_ACTIVE)
            ->where('notifications_enabled', true)
            ->whereNull('deleted_at')
            ->get();

        if ($sanitationUsers->isNotEmpty()) {
            return $sanitationUsers->unique('id')->values();
        }

        return User::query()
            ->where(function ($q) {
                $q->whereHas('roles', fn ($rq) => $rq->whereIn('slug', ['admin', 'super-admin']))
                  ->orWhereIn('type', [
                      User::TYPE_SUPER_ADMIN,
                      User::TYPE_ADMIN,
                  ]);
            })
            ->where('status', User::STATUS_ACTIVE)
            ->where('notifications_enabled', true)
            ->whereNull('deleted_at')
            ->get()
            ->unique('id')
            ->values();
    }

    private function notifySanitationTeam(WasteCollectionRequest $model, string $action): void
    {
        try {
            $property = $model->property;

            $recipients = $this->getSanitationNotificationRecipients($model);

            if ($recipients->isEmpty()) {
                Log::warning('notifySanitationTeam: no recipients found', [
                    'request_id' => $model->id,
                    'action'     => $action,
                ]);
                return;
            }

            [$title, $icon, $message, $priority] = match ($action) {
                'approved' => [
                    '✅ Landlord Approved Collection',
                    'fas fa-check-circle text-success',
                    "The landlord approved waste collection for {$property->property_name}.",
                    2,
                ],
                'rejected' => [
                    '❌ Landlord Rejected Collection',
                    'fas fa-times-circle text-danger',
                    "The landlord rejected waste collection for {$property->property_name}."
                        . ($model->rejection_reason ? " Reason: {$model->rejection_reason}" : ''),
                    1,
                ],
                'bin_full' => [
                    '🗑️ Bin Full — Collection Needed',
                    'fas fa-trash-alt text-warning',
                    "The landlord of {$property->property_name} has reported a full bin. "
                        . "Please schedule a pickup as soon as possible.",
                    3,
                ],
                default => [
                    'Waste Collection Update',
                    'fas fa-bell text-info',
                    "Request #{$model->id} was updated.",
                    1,
                ],
            };

            $targetRoles = ['sanitation-personnel', 'sanitation-supervisor', 'admin', 'super-admin'];

            $assignedPersonnel = $model->assignedTo;
            $supervisorUser    = $assignedPersonnel?->supervisorUser;

            foreach ($recipients as $user) {
                $isSupervisorRecipient = $supervisorUser && $user->id === $supervisorUser->id;

                $user->notify(new GeneralNotification(
                    title:     $title,
                    message:   $message,
                    icon:      $icon,
                    category:  'waste_collection_' . $action,
                    priority:  $priority,
                    actionUrl: route('sanitation.requests.show', $model),
                    data: [
                        'request_id'              => $model->id,
                        'property_id'             => $property->id ?? null,
                        'property_name'           => $property->property_name ?? null,
                        'action'                  => $action,
                        'source'                  => $model->source ?? 'scheduled',
                        'notes'                   => $model->metadata['landlord_notes'] ?? null,
                        'assigned_personnel_id'   => $assignedPersonnel?->id,
                        'assigned_personnel_name' => $assignedPersonnel?->full_name,
                        'supervisor_id'           => $assignedPersonnel?->supervisor_id,
                        'is_supervisor_recipient' => (bool) $isSupervisorRecipient,
                    ],
                    roles: $targetRoles,
                ));
            }

            Log::info('notifySanitationTeam: notifications sent', [
                'request_id'      => $model->id,
                'action'          => $action,
                'count'           => $recipients->count(),
                'user_ids'        => $recipients->pluck('id')->all(),
                'assigned_to'     => $assignedPersonnel?->id,
                'supervisor_id'   => $assignedPersonnel?->supervisor_id,
                'supervisor_user' => $supervisorUser?->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to notify sanitation team: ' . $e->getMessage(), [
                'request_id' => $model->id,
                'action'     => $action,
            ]);
        }
    }

    private function notifyAllSupervisorsServiceRequest(
        Property $property,
        Collection $supervisors,
        SanitationServiceRequest $serviceRequest
    ): void {
        foreach ($supervisors as $supervisor) {
            $this->notifySupervisorServiceRequest($property, $supervisor, $serviceRequest);
        }
    }

    private function notifySupervisorServiceRequest(
        Property $property,
        SanitationPersonnel $supervisor,
        SanitationServiceRequest $serviceRequest
    ): void {
        try {
            $supervisorUser = $supervisor->user;

            if (!$supervisorUser) {
                Log::warning('Supervisor has no linked user account', [
                    'supervisor_id' => $supervisor->id,
                    'property_id'   => $property->id,
                ]);
                return;
            }

            $frequency     = $serviceRequest->collection_frequency;
            $monthlyFee    = $serviceRequest->quoted_monthly_fee;
            $feeDisplay    = $monthlyFee !== null
                ? 'GH₵ ' . number_format((float) $monthlyFee, 2) . '/month'
                : null;

            $messageParts = [
                "Landlord {$serviceRequest->landlord_name} requested waste collection for {$property->property_name}.",
            ];
            if ($frequency) {
                $messageParts[] = "Frequency: " . ucfirst($frequency) . ".";
            }
            if ($feeDisplay) {
                $messageParts[] = "Agreed rate: {$feeDisplay}.";
            }

            $supervisorUser->notify(new GeneralNotification(
                title:     '🏠 New Sanitation Service Request',
                message:   implode(' ', $messageParts),
                icon:      'fas fa-hands-helping text-warning',
                category:  'sanitation_service_request',
                priority:  3,
                actionUrl: route('sanitation.properties.show', $property),
                data: [
                    'service_request_id'        => $serviceRequest->id,
                    'property_id'               => $property->id,
                    'property_name'             => $property->property_name,
                    'digital_address'           => $property->digital_address,
                    'landlord_id'               => $serviceRequest->landlord_id,
                    'landlord_name'             => $serviceRequest->landlord_name,
                    'landlord_phone'            => $serviceRequest->landlord_phone,
                    'landlord_email'            => $serviceRequest->landlord_email,
                    'priority'                  => $serviceRequest->priority,
                    'message'                   => $serviceRequest->message,
                    'supervisor_id'             => $supervisor->id,
                    'supervisor_name'           => $supervisor->full_name,
                    'collection_frequency'      => $serviceRequest->collection_frequency,
                    'quoted_monthly_fee'        => $serviceRequest->quoted_monthly_fee,
                    'quoted_emergency_fee'      => $serviceRequest->quoted_emergency_fee,
                    'quoted_currency'           => $serviceRequest->quoted_currency,
                    'agreement_accepted_at'     => optional($serviceRequest->agreement_accepted_at)->toIso8601String(),
                    'requested_at'              => optional($serviceRequest->created_at)->toIso8601String(),
                    'expires_at'                => optional($serviceRequest->expires_at)->toIso8601String(),
                ],
                roles: ['sanitation-supervisor', 'sanitation-personnel'],
            ));

            Log::info('Supervisor notified of sanitation service request', [
                'service_request_id' => $serviceRequest->id,
                'supervisor_id'      => $supervisor->id,
                'property_id'        => $property->id,
                'landlord_id'        => $serviceRequest->landlord_id,
                'frequency'          => $serviceRequest->collection_frequency,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to notify supervisor of service request: ' . $e->getMessage(), [
                'supervisor_id' => $supervisor->id ?? null,
                'property_id'   => $property->id,
            ]);
        }
    }

    // ================================================================ //
    // 📋 RESPONSE METHODS                                             //
    // ================================================================ //

    private function notFound(Request $request, string $message)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => false, 'message' => $message], 404);
        }
        return redirect()->route('landlord.waste.approvals')->with('error', $message);
    }

    private function ok(Request $request, string $message, $model, string $redirectRoute)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'request' => $model->fresh(),
            ]);
        }
        return redirect()->route($redirectRoute)->with('success', $message);
    }

    private function fail(Request $request, string $message, int $status = 500)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => false, 'message' => $message], $status);
        }
        return redirect()->back()->with('error', $message);
    }
}