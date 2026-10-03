<?php
// app/Http/Controllers/Tenant/TenantMaintenanceController.php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\PropertyUnit;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Models\ActivityLog;
use App\Notifications\MaintenanceRequestNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;                       // ✅ NEW
use Barryvdh\DomPDF\Facade\Pdf;                            // ✅ NEW
use Carbon\Carbon;                                         // ✅ NEW

class TenantMaintenanceController extends Controller
{
    // ========== CONSTANTS ==========
    private const ALLOWED_PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    private const ALLOWED_CATEGORIES = [
        'plumbing', 'electrical', 'appliance',
        'structural', 'cleaning', 'other',
    ];

    private const DELETABLE_STATUSES = ['completed', 'cancelled'];

    // ========== TENANT RESOLUTION ==========

    /**
     * ✅ FIXED: Resolves the authenticated tenant's currently assigned unit.
     *
     * Uses the most recent assignment so that during unit transfers the
     * controller operates on the correct (latest) unit. Requires the
     * tenant to be approved.
     */
    private function getTenantUnit(): PropertyUnit
    {
        $user = auth()->user();

        $isTenant = $user->isTenant() || $user->hasRole('tenant');
        if (!$isTenant) {
            abort(403, 'Only tenants can access this resource.');
        }

        $unit = PropertyUnit::where('tenant_id', $user->id)
            ->where('tenant_status', PropertyUnit::TENANT_STATUS_APPROVED)
            ->with(['property.landlord'])
            ->latest('tenant_assigned_at')   // ✅ FIXED: deterministic, prefers most recent
            ->first();

        if (!$unit) {
            abort(404, 'You do not have an assigned unit.');
        }

        return $unit;
    }

    // ========== LIST ==========

    /**
     * Display a paginated listing of maintenance requests.
     */
    public function index(Request $request)
    {
        $unit = $this->getTenantUnit();

        $query = MaintenanceRequest::where('unit_id', $unit->id);

        $this->applyFilters($query, $request);

        $requests = $query
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $stats = $this->buildStats($unit);

        return view('tenant.maintenance.index', compact('unit', 'requests', 'stats'));
    }

    // ========== CREATE ==========

    public function create()
    {
        $unit = $this->getTenantUnit();
        return view('tenant.maintenance.create', compact('unit'));
    }

    // ========== STORE ==========

    /**
     * ✅ FIXED: Notifications are now sent *after* the transaction commits,
     * so a rollback doesn't leave orphan notifications behind.
     *
     * ✅ FIXED: Reference ID uses a short random suffix to avoid collisions.
     */
    public function store(Request $request)
    {
        $unit = $this->getTenantUnit();
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255',
            'description' => 'required|string|min:20|max:1000',
            'priority'    => 'required|in:' . implode(',', self::ALLOWED_PRIORITIES),
            'category'    => 'required|in:' . implode(',', self::ALLOWED_CATEGORIES),
            'photos'      => 'nullable|array|max:5',
            'photos.*'    => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();

        try {
            // ========== PHOTO UPLOADS ==========
            $photos = [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $photos[] = $photo->store("maintenance_requests/{$unit->id}", 'public');
                }
            }

            // ========== LANDLORD RESOLUTION ==========
            $landlord = $unit->property->landlord ?? null;
            if (!$landlord) {
                throw new \RuntimeException(
                    'No landlord is associated with this property. Please contact support.'
                );
            }

            // ========== REFERENCE ID ==========
            // ✅ FIXED: random suffix avoids collisions under concurrent submissions
            $referenceId = 'MR'
                . now()->format('ymd')
                . '-'
                . strtoupper(Str::random(6));

            // ========== CREATE ==========
            $maintenanceRequest = MaintenanceRequest::create([
                'unit_id'              => $unit->id,
                'property_id'          => $unit->property_id,
                'tenant_id'            => $user->id,
                'landlord_id'          => $landlord->id,
                'reported_by_user_id'  => $user->id,
                // ✅ FIXED: derive role label from role helpers, not raw ->type
                'reported_by_type'     => $this->resolveUserTypeLabel($user),
                'title'                => $request->title,
                'description'          => $request->description,
                'priority'             => $request->priority,
                'category'             => $request->category,
                'images'               => $photos,
                'status'               => 'pending',
                'reference_id'         => $referenceId,
                'created_by'           => $user->id,
                'source'               => 'tenant',
            ]);

            // ========== ACTIVITY LOG ==========
            $this->logActivity(
                $user->id,
                'maintenance_request_created',
                'Maintenance request created',
                $unit->id,
                [
                    'request_id'     => $maintenanceRequest->id,
                    'reference_id'   => $referenceId,
                    'priority'       => $request->priority,
                    'category'       => $request->category,
                    'photo_count'    => count($photos),
                    'landlord_id'    => $landlord->id,
                    'landlord_name'  => $landlord->name,
                ]
            );

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();

            // Clean up any photos that were stored before the failure
            foreach ($photos as $path) {
                try {
                    Storage::disk('public')->delete($path);
                } catch (\Throwable $cleanupError) {
                    Log::warning('Failed to clean up orphaned photo after request failure', [
                        'path' => $path,
                    ]);
                }
            }

            Log::error('Failed to create maintenance request', [
                'tenant_id'  => $user->id,
                'unit_id'    => $unit->id,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to submit maintenance request. Please try again.')
                ->withInput();
        }

        // ========== NOTIFICATIONS (AFTER COMMIT) ==========
        // ✅ FIXED: Moved outside the transaction so they only fire on success.
        try {
            $landlord->notify(new MaintenanceRequestNotification(
                $maintenanceRequest,
                $unit,
                'submitted',
                $user,
                [
                    'tenant_name'  => $user->name,
                    'tenant_email' => $user->email,
                    'tenant_phone' => $user->phone ?? 'N/A',
                    'reference_id' => $referenceId,
                ]
            ));

            $user->notify(new MaintenanceRequestNotification(
                $maintenanceRequest,
                $unit,
                'submitted',
                $user,
                [
                    'message' => 'Your maintenance request has been submitted successfully. '
                               . 'The landlord has been notified.',
                    'reference_id' => $referenceId,
                ]
            ));
        } catch (\Throwable $e) {
            Log::warning('Notifications failed after request creation', [
                'request_id' => $maintenanceRequest->id,
                'error'      => $e->getMessage(),
            ]);
        }

        // ========== CACHE INVALIDATION ==========
        $this->clearMaintenanceCaches($unit);

        return redirect()
            ->route('tenant.maintenance.index')
            ->with('success', "Maintenance request {$referenceId} submitted successfully. The landlord has been notified.");
    }

    // ========== SHOW ==========

    /**
     * ✅ FIXED: renamed `$request` local to `$maintenanceRequest` to avoid
     * shadowing the Request parameter if it's ever added in future.
     */
    public function show($id)
    {
        $unit = $this->getTenantUnit();

        $maintenanceRequest = MaintenanceRequest::with([
                'reporter',
                'assignedTo',
                'unit',
                'property',
                'landlord',
            ])
            ->where('unit_id', $unit->id)
            ->findOrFail($id);

        return view('tenant.maintenance.show', [
            'unit'    => $unit,
            'request' => $maintenanceRequest,   // keep view name for BC
        ]);
    }

    // ========== CANCEL ==========

    /**
     * ✅ FIXED: Notifications moved after commit.
     * ✅ FIXED: Uses the landlord relation instead of a direct User::find().
     */
    public function cancel(Request $request, $id)
    {
        $unit = $this->getTenantUnit();
        $user = auth()->user();

        $maintenanceRequest = MaintenanceRequest::where('unit_id', $unit->id)
            ->whereIn('status', ['pending'])
            ->findOrFail($id);

        DB::beginTransaction();

        try {
            $cancellationReason = $request->input('reason', 'Cancelled by tenant');

            $maintenanceRequest->update([
                'status'     => 'cancelled',
                'updated_by' => $user->id,
                'notes'      => ($maintenanceRequest->notes
                                    ? $maintenanceRequest->notes . "\n"
                                    : '')
                                . '[CANCELLED] By: ' . $user->name
                                . ' | Reason: ' . $cancellationReason
                                . ' | Date: ' . now()->toDateTimeString(),
            ]);

            $this->logActivity(
                $user->id,
                'maintenance_request_cancelled',
                'Maintenance request cancelled by tenant',
                $unit->id,
                [
                    'request_id'   => $maintenanceRequest->id,
                    'reference_id' => $maintenanceRequest->reference_id,
                    'cancelled_by' => $user->name,
                    'reason'       => $cancellationReason,
                ]
            );

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to cancel maintenance request', [
                'request_id' => $id,
                'tenant_id'  => $user->id,
                'error'      => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to cancel maintenance request.');
        }

        // ========== NOTIFICATIONS (AFTER COMMIT) ==========
        try {
            $landlord = $maintenanceRequest->landlord
                     ?? User::find($maintenanceRequest->landlord_id);

            if ($landlord) {
                $landlord->notify(new MaintenanceRequestNotification(
                    $maintenanceRequest,
                    $unit,
                    'cancelled',
                    $user,
                    [
                        'cancellation_reason' => $cancellationReason,
                        'tenant_name'         => $user->name,
                        'message'             => 'Maintenance request has been cancelled by the tenant.',
                        'reference_id'        => $maintenanceRequest->reference_id,
                    ]
                ));
            }

            $user->notify(new MaintenanceRequestNotification(
                $maintenanceRequest,
                $unit,
                'cancelled',
                $user,
                ['message' => 'Your maintenance request has been cancelled successfully.']
            ));
        } catch (\Throwable $e) {
            Log::warning('Notifications failed after cancellation', [
                'request_id' => $maintenanceRequest->id,
                'error'      => $e->getMessage(),
            ]);
        }

        $this->clearMaintenanceCaches($unit);

        return redirect()
            ->route('tenant.maintenance.index')
            ->with('success', 'Maintenance request cancelled successfully.');
    }

    // ========== SOFT DELETE ==========

    /**
     * ✅ FIXED: Uses role helpers with fallback.
     * ✅ FIXED: `$isOwner` renamed to `$isReporter` for semantic accuracy.
     */
    public function destroy($id)
    {
        $unit = $this->getTenantUnit();
        $user = auth()->user();

        $maintenanceRequest = MaintenanceRequest::where('unit_id', $unit->id)
            ->findOrFail($id);

        // Only allow deletion of completed or cancelled requests
        if (!in_array($maintenanceRequest->status, self::DELETABLE_STATUSES, true)) {
            return redirect()->back()
                ->with('error', 'Only completed or cancelled requests can be deleted.');
        }

        if (!$this->canModifyRequest($maintenanceRequest, $user, $unit)) {
            return redirect()->back()
                ->with('error', 'You are not authorized to delete this request.');
        }

        DB::beginTransaction();

        try {
            $maintenanceRequest->delete();

            $this->logActivity(
                $user->id,
                'maintenance_request_deleted',
                'Maintenance request moved to trash',
                $unit->id,
                [
                    'request_id'   => $maintenanceRequest->id,
                    'reference_id' => $maintenanceRequest->reference_id,
                    'deleted_by'   => $user->name,
                ]
            );

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to soft delete maintenance request', [
                'request_id' => $id,
                'error'      => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to delete maintenance request.');
        }

        $this->clearMaintenanceCaches($unit);

        return redirect()
            ->route('tenant.maintenance.index')
            ->with('success', 'Maintenance request moved to trash successfully.');
    }

    // ========== FORCE DELETE ==========

    /**
     * ✅ FIXED: Now requires the request to be trashed first.
     * ✅ FIXED: Uses role helpers with fallback.
     *
     * This prevents bypassing the soft-delete workflow from the index
     * by hitting the force-delete endpoint directly.
     */
    public function forceDelete($id)
    {
        $unit = $this->getTenantUnit();
        $user = auth()->user();

        $maintenanceRequest = MaintenanceRequest::withTrashed()
            ->where('unit_id', $unit->id)
            ->findOrFail($id);

        // ✅ FIXED: must be trashed first
        if (!$maintenanceRequest->trashed()) {
            return redirect()->back()
                ->with('error', 'Request must be moved to trash before it can be permanently deleted.');
        }

        if (!$this->canModifyRequest($maintenanceRequest, $user, $unit)) {
            return redirect()->back()
                ->with('error', 'You are not authorized to permanently delete this request.');
        }

        DB::beginTransaction();

        try {
            // ========== DELETE ASSOCIATED FILES ==========
            if (is_array($maintenanceRequest->images)) {
                foreach ($maintenanceRequest->images as $image) {
                    try {
                        if (Storage::disk('public')->exists($image)) {
                            Storage::disk('public')->delete($image);
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Failed to delete maintenance photo', [
                            'path' => $image,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            $snapshot = [
                'id'           => $maintenanceRequest->id,
                'reference_id' => $maintenanceRequest->reference_id,
                'title'        => $maintenanceRequest->title,
                'status'       => $maintenanceRequest->status,
            ];

            $maintenanceRequest->forceDelete();

            $this->logActivity(
                $user->id,
                'maintenance_request_permanently_deleted',
                'Maintenance request permanently deleted',
                $unit->id,
                [
                    'request_data' => $snapshot,
                    'deleted_by'   => $user->name,
                ]
            );

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to permanently delete maintenance request', [
                'request_id' => $id,
                'error'      => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to permanently delete maintenance request.');
        }

        $this->clearMaintenanceCaches($unit);

        return redirect()
            ->route('tenant.maintenance.index')
            ->with('success', 'Maintenance request permanently deleted.');
    }

    // ========== ✅ NEW: RESTORE ==========

    /**
     * ✅ NEW: Restore a soft-deleted maintenance request from the trash.
     *
     * Only the tenant who reported it, the landlord, or an admin can restore.
     */
    public function restore($id)
    {
        $unit = $this->getTenantUnit();
        $user = auth()->user();

        $maintenanceRequest = MaintenanceRequest::onlyTrashed()
            ->where('unit_id', $unit->id)
            ->findOrFail($id);

        if (!$this->canModifyRequest($maintenanceRequest, $user, $unit)) {
            return redirect()->back()
                ->with('error', 'You are not authorized to restore this request.');
        }

        DB::beginTransaction();

        try {
            $maintenanceRequest->restore();

            $this->logActivity(
                $user->id,
                'maintenance_request_restored',
                'Maintenance request restored from trash',
                $unit->id,
                [
                    'request_id'   => $maintenanceRequest->id,
                    'reference_id' => $maintenanceRequest->reference_id,
                    'restored_by'  => $user->name,
                ]
            );

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to restore maintenance request', [
                'request_id' => $id,
                'error'      => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to restore maintenance request.');
        }

        $this->clearMaintenanceCaches($unit);

        return redirect()
            ->route('tenant.maintenance.trashed')
            ->with('success', 'Maintenance request restored successfully.');
    }

    // ========== TRASHED LIST ==========

    public function trashed(Request $request)
    {
        $unit = $this->getTenantUnit();

        $query = MaintenanceRequest::onlyTrashed()->where('unit_id', $unit->id);

        $this->applyFilters($query, $request);

        $trashedRequests = $query
            ->orderBy('deleted_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total' => MaintenanceRequest::onlyTrashed()
                ->where('unit_id', $unit->id)
                ->count(),
        ];

        return view('tenant.maintenance.trashed', compact('unit', 'trashedRequests', 'stats'));
    }

    // ========== EXPORTS ==========

    /**
     * ✅ FIXED: Real PDF export using DomPDF.
     *
     * Requires view: `resources/views/pdf/maintenance-request.blade.php`
     */
    public function exportSingle($id)
    {
        $unit = $this->getTenantUnit();

        $maintenanceRequest = MaintenanceRequest::with(['tenant', 'landlord', 'unit.property'])
            ->where('unit_id', $unit->id)
            ->findOrFail($id);

        // Tenant can only export their own request
        if ($maintenanceRequest->tenant_id !== auth()->id()
            && $maintenanceRequest->reported_by_user_id !== auth()->id()) {
            abort(403, 'You can only export your own requests.');
        }

        try {
            $pdf = Pdf::loadView('pdf.maintenance-request', [
                'request'         => $maintenanceRequest,
                'unit'            => $unit,
                'generated_date'  => now()->format('F j, Y'),
                'currency_symbol' => config('leases.ghana.currency.symbol', 'GH₵'),
                'governing_law'   => config('leases.ghana.governing_law', 'Rent Act, 1963 (Act 220)'),
            ]);

            $pdf->setPaper('A4', 'portrait');

            $safeRef = preg_replace('/[^A-Za-z0-9\-_]/', '-', $maintenanceRequest->reference_id ?? $maintenanceRequest->id);
            $filename = "Maintenance-{$safeRef}-{$unit->unit_number}.pdf";

            $this->logActivity(
                auth()->id(),
                'maintenance_request_pdf_exported',
                'Maintenance request PDF exported',
                $unit->id,
                [
                    'request_id'   => $maintenanceRequest->id,
                    'reference_id' => $maintenanceRequest->reference_id,
                ]
            );

            return $pdf->download($filename);

        } catch (\Throwable $e) {
            Log::error('Failed to export maintenance request PDF', [
                'request_id' => $id,
                'error'      => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to export PDF. Please try again.');
        }
    }

    /**
     * ✅ FIXED: Now respects pagination and returns a real PDF.
     * Exports only the filtered requests (matching the on-screen page).
     */
    public function exportCurrentPage(Request $request)
    {
        $unit = $this->getTenantUnit();

        $query = MaintenanceRequest::where('unit_id', $unit->id);
        $this->applyFilters($query, $request);

        // Match the on-screen page — pull only the current page's worth
        $perPage = (int) $request->input('per_page', 20);
        $page    = (int) $request->input('page', 1);

        $requests = $query
            ->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return $this->streamMaintenancePdf($unit, $requests, 'current-page');
    }

    /**
     * ✅ FIXED: Real PDF export of all filtered requests.
     */
    public function exportAll(Request $request)
    {
        $unit = $this->getTenantUnit();

        $query = MaintenanceRequest::where('unit_id', $unit->id);
        $this->applyFilters($query, $request);

        $requests = $query->orderBy('created_at', 'desc')->get();

        return $this->streamMaintenancePdf($unit, $requests, 'all');
    }

    /**
     * ✅ NEW: shared PDF streaming helper for list exports.
     */
    private function streamMaintenancePdf(PropertyUnit $unit, $requests, string $scope)
    {
        try {
            $pdf = Pdf::loadView('pdf.maintenance-requests-list', [
                'requests'        => $requests,
                'unit'            => $unit,
                'scope'           => $scope,
                'generated_date'  => now()->format('F j, Y'),
                'currency_symbol' => config('leases.ghana.currency.symbol', 'GH₵'),
                'governing_law'   => config('leases.ghana.governing_law', 'Rent Act, 1963 (Act 220)'),
            ]);

            $pdf->setPaper('A4', 'landscape');

            $filename = 'Maintenance-Requests-' . $scope . '-' . $unit->unit_number
                      . '-' . now()->format('Ymd-His') . '.pdf';

            return $pdf->download($filename);

        } catch (\Throwable $e) {
            Log::error('Failed to export maintenance list PDF', [
                'unit_id' => $unit->id,
                'scope'   => $scope,
                'count'   => $requests->count(),
                'error'   => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to export PDF. Please try again.');
        }
    }

    // ========== API ==========

    public function apiRequests(Request $request)
    {
        $unit = $this->getTenantUnit();

        $query = MaintenanceRequest::where('unit_id', $unit->id);
        $this->applyFilters($query, $request);

        $requests = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success'  => true,
            'requests' => $requests->map(fn ($r) => [
                'id'                 => $r->id,
                'reference_id'       => $r->reference_id,
                'title'              => $r->title,
                'description'        => $r->description,
                'priority'           => $r->priority,
                'priority_color'     => $r->priority_color ?? null,
                'category'           => $r->category,
                'status'             => $r->status,
                'status_color'       => $r->status_color ?? null,
                'display_status'     => $r->display_status ?? ucfirst(str_replace('_', ' ', $r->status)),
                'created_at'         => optional($r->created_at)->toDateTimeString(),
                'created_at_human'   => optional($r->created_at)->diffForHumans(),
                'has_photos'         => !empty($r->images),
                'can_delete'         => in_array($r->status, self::DELETABLE_STATUSES, true),
                'can_force_delete'   => method_exists($r, 'trashed') && $r->trashed(),
            ]),
            'stats' => array_merge(
                $this->buildStats($unit),
                ['trashed' => MaintenanceRequest::onlyTrashed()->where('unit_id', $unit->id)->count()]
            ),
        ]);
    }

    // ========== HELPERS ==========

    /**
     * ✅ NEW: centralised filter application for index / trashed / exports.
     */
    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('reference_id', 'like', "%{$search}%");
            });
        }
    }

    /**
     * ✅ NEW: builds the shared stats array for the index and API.
     */
    private function buildStats(PropertyUnit $unit): array
    {
        $base = MaintenanceRequest::where('unit_id', $unit->id);

        return [
            'total'       => (clone $base)->count(),
            'pending'     => (clone $base)->where('status', 'pending')->count(),
            'in_progress' => (clone $base)->where('status', 'in_progress')->count(),
            'completed'   => (clone $base)->where('status', 'completed')->count(),
            'cancelled'   => (clone $base)->where('status', 'cancelled')->count(),
            'urgent'      => (clone $base)->where('priority', 'urgent')->count(),
        ];
    }

    /**
     * ✅ NEW: authorization helper for destroy / forceDelete / restore.
     *
     * Grants access to:
     *   - Super admin
     *   - Admin
     *   - The landlord who owns the property
     *   - The tenant who reported the request
     */
    private function canModifyRequest(
        MaintenanceRequest $maintenanceRequest,
        User $user,
        PropertyUnit $unit
    ): bool {
        if ($user->isSuperAdmin() || $user->hasRole('super-admin')) return true;
        if ($user->isAdmin()    || $user->hasRole('admin'))         return true;

        $isLandlord = ($user->isLandlord() || $user->hasRole('landlord'))
            && $unit->property->landlord_id === $user->id;
        if ($isLandlord) return true;

        $isReporter = $maintenanceRequest->tenant_id === $user->id
            || $maintenanceRequest->reported_by_user_id === $user->id;
        if ($isReporter) return true;

        return false;
    }

    /**
     * ✅ FIXED: derive a stable type label from role helpers, not raw ->type.
     */
    private function resolveUserTypeLabel(User $user): string
    {
        if ($user->isSuperAdmin() || $user->hasRole('super-admin')) return 'super_admin';
        if ($user->isAdmin()      || $user->hasRole('admin'))       return 'admin';
        if ($user->isLandlord()   || $user->hasRole('landlord'))    return 'landlord';
        if ($user->isTenant()     || $user->hasRole('tenant'))      return 'tenant';
        if ($user->isDeveloper()  || $user->hasRole('developer'))   return 'developer';

        return $user->type ?? 'user';
    }

    /**
     * ✅ NEW: invalidate the caches used elsewhere in the app.
     */
    private function clearMaintenanceCaches(PropertyUnit $unit): void
    {
        $keys = [
            'property_unit_' . $unit->id . '_with_relations',
            'unit_stats_' . $unit->id,
            'dashboard_stats_' . ($unit->property->landlord_id ?? 0),
            'property_stats_' . $unit->property_id,
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * ✅ FIXED: safe activity log writer — no exception escapes.
     */
    private function logActivity(
        int $userId,
        string $type,
        string $description,
        ?int $unitId = null,
        array $metadata = []
    ): void {
        try {
            ActivityLog::create([
                'user_id'     => $userId,
                'type'        => $type,
                'description' => $description,
                'unit_id'     => $unitId,
                'metadata'    => $metadata,
                'ip_address'  => request()->ip(),
                'user_agent'  => request()->userAgent(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log maintenance activity', [
                'type'    => $type,
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}