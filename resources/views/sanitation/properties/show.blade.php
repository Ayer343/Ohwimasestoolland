{{-- resources/views/sanitation/properties/show.blade.php --}}

@php
    use Illuminate\Support\Str;

    $user    = auth()->user();
    $isAdmin = $user->isAdmin() || $user->isSuperAdmin();
    $layout  = $isAdmin ? 'layouts.app' : 'layouts.san';

    // -----------------------------------------------------------------
    // ✅ FIXED: Determine whether the CURRENT user may LINK properties.
    //
    // Rule (mirrors SanitationController::canCurrentUserLinkProperties):
    //   - Admin / Super Admin → allowed
    //   - Sanitation personnel → must be a SUPERVISOR (root OR sub)
    //     whose creator is either an admin OR another supervisor.
    //   - Workers / drivers / others → denied.
    //
    // Computed inline as fallback so the Blade renders correctly even
    // if the controller forgets to pass $canLinkProperties.
    // -----------------------------------------------------------------
    if (!isset($canLinkProperties)) {
        $personnel = $user->sanitationPersonnel;

        $canLinkProperties = false;

        if ($user->isAdmin() || $user->isSuperAdmin()) {
            $canLinkProperties = true;
        } elseif ($personnel && method_exists($personnel, 'isSupervisor') && $personnel->isSupervisor()) {
            $meta = $personnel->metadata ?? [];
            if (is_string($meta)) {
                $meta = json_decode($meta, true) ?: [];
            }

            $createdById = is_array($meta) ? ($meta['created_by'] ?? null) : null;

            if ($createdById) {
                $creator = \App\Models\User::find($createdById);

                if ($creator) {
                    if ($creator->isAdmin() || $creator->isSuperAdmin()) {
                        $canLinkProperties = true;
                    } elseif ($creator->sanitationPersonnel
                        && method_exists($creator->sanitationPersonnel, 'isSupervisor')
                        && $creator->sanitationPersonnel->isSupervisor()) {
                        $canLinkProperties = true;
                    }
                }
            }
        }
    }

    // -----------------------------------------------------------------
    // Normalize the property status once so the badge can't render empty.
    // -----------------------------------------------------------------
    $propertyStatus = $property->status;
    if ($propertyStatus === null || $propertyStatus === '' || $propertyStatus === 'unknown') {
        $propertyStatus = 'unknown';
    }

    $propertyStatusMap = [
        'active'             => ['label' => 'Active',             'class' => 'status-active'],
        'inactive'           => ['label' => 'Inactive',           'class' => 'status-inactive'],
        'pending'            => ['label' => 'Pending',            'class' => 'status-pending'],
        'under_maintenance'  => ['label' => 'Under Maintenance',  'class' => 'status-maintenance'],
        'vacant'             => ['label' => 'Vacant',             'class' => 'status-vacant'],
        'under_construction' => ['label' => 'Under Construction', 'class' => 'status-construction'],
        'archived'           => ['label' => 'Archived',           'class' => 'status-archived'],
    ];

    $propertyStatusInfo  = $propertyStatusMap[$propertyStatus] ?? [
        'label' => ucfirst(str_replace('_', ' ', $propertyStatus)),
        'class' => 'status-unknown',
    ];

    // -----------------------------------------------------------------
    // Normalize the approval status once so the badge can't render empty.
    // -----------------------------------------------------------------
    $approvalStatusRaw = $stats['approval_status'] ?? 'Not Requested';

    $approvalStatusMap = [
        'Pending Approval' => 'status-approval-pending',
        'Approved'         => 'status-approval-approved',
        'Auto-Approved'    => 'status-approval-auto',
        'Rejected'         => 'status-approval-rejected',
        'Expired'          => 'status-approval-expired',
        'Not Requested'    => 'status-unknown',
    ];

    $approvalStatusClass = $approvalStatusMap[$approvalStatusRaw] ?? 'status-unknown';

    // -----------------------------------------------------------------
    // Request status → class map (used in both Active Requests and History)
    // -----------------------------------------------------------------
    $requestStatusMap = [
        'pending'     => ['label' => 'Pending',     'class' => 'status-pending'],
        'assigned'    => ['label' => 'Assigned',    'class' => 'status-assigned'],
        'en_route'    => ['label' => 'En Route',    'class' => 'status-enroute'],
        'arrived'     => ['label' => 'Arrived',     'class' => 'status-arrived'],
        'in_progress' => ['label' => 'In Progress', 'class' => 'status-inprogress'],
        'completed'   => ['label' => 'Completed',   'class' => 'status-completed'],
        'cancelled'   => ['label' => 'Cancelled',   'class' => 'status-cancelled'],
        'missed'      => ['label' => 'Missed',      'class' => 'status-missed'],
    ];

    // -----------------------------------------------------------------
    // Priority → class map
    // -----------------------------------------------------------------
    $priorityMap = [
        'emergency' => ['label' => 'Emergency', 'class' => 'status-priority-emergency'],
        'high'      => ['label' => 'High',      'class' => 'status-priority-high'],
        'medium'    => ['label' => 'Medium',    'class' => 'status-priority-medium'],
        'low'       => ['label' => 'Low',       'class' => 'status-priority-low'],
    ];

    // -----------------------------------------------------------------
    // Waste type → class map
    // -----------------------------------------------------------------
    $wasteTypeMap = [
        'general'    => ['label' => 'General',    'class' => 'status-waste-general'],
        'recyclable' => ['label' => 'Recyclable', 'class' => 'status-waste-recyclable'],
        'organic'    => ['label' => 'Organic',    'class' => 'status-waste-organic'],
        'hazardous'  => ['label' => 'Hazardous',  'class' => 'status-waste-hazardous'],
        'bulk'       => ['label' => 'Bulk',       'class' => 'status-waste-bulk'],
    ];
@endphp

@extends($layout)

@section('title', 'Property Details: ' . $property->property_name)

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-building mr-2" style="color: var(--primary);"></i>
                    {{ $property->property_name }}
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Waste collection details for this property
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                @if($pendingApproval)
                    <span class="status-badge status-approval-pending">
                        <i class="fas fa-clock mr-1"></i> Awaiting Landlord Approval
                    </span>
                @endif
                <a href="{{ route('sanitation.properties.linked') }}" class="btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </div>
        </div>

        {{-- ✅ View-only notice for users without linking rights --}}
        @unless($canLinkProperties)
            <div class="card p-3 mb-4" style="background-color: rgba(59, 130, 246, 0.08); border-color: #3b82f6;">
                <div class="flex items-start gap-2" style="color: #2563eb;">
                    <i class="fas fa-eye mt-0.5"></i>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-primary);">
                            View-only mode
                        </p>
                        <p class="text-xs mt-0.5" style="color: var(--text-secondary);">
                            Only sanitation supervisors created by an administrator or another supervisor
                            can modify collection settings, link, or unlink this property.
                        </p>
                    </div>
                </div>
            </div>
        @endunless

        <!-- Approval Notice -->
        @if($pendingApproval)
            <div class="card p-4 mb-6" style="background-color: rgba(245, 158, 11, 0.1); border-color: #f59e0b;">
                <div class="flex items-start gap-3">
                    <i class="fas fa-clock text-yellow-500 text-xl mt-1"></i>
                    <div>
                        <h4 class="font-semibold text-sm" style="color: var(--text-primary);">
                            <i class="fas fa-user-check mr-1"></i> Pending Landlord Approval
                        </h4>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            This property is waiting for landlord approval.
                            @if($stats['days_until_expiry'] !== null && $stats['days_until_expiry'] > 0)
                                <span class="font-medium" style="color: var(--text-primary);">
                                    {{ $stats['days_until_expiry'] }} days left to respond.
                                </span>
                            @elseif($stats['days_until_expiry'] !== null && $stats['days_until_expiry'] <= 0)
                                <span class="font-medium text-red-500">Approval has expired!</span>
                            @else
                                <span class="font-medium" style="color: var(--text-secondary);">Awaiting response.</span>
                            @endif
                        </p>
                        <div class="flex items-center gap-4 mt-2 text-xs">
                            @if($pendingApproval->approval_requested_at)
                                <span style="color: var(--text-secondary);">
                                    <i class="fas fa-calendar mr-1" style="color: var(--primary);"></i>
                                    Requested: {{ $pendingApproval->approval_requested_at->format('M d, Y') }}
                                </span>
                            @endif
                            @if($pendingApproval->approval_expires_at)
                                <span style="color: var(--text-secondary);">
                                    <i class="fas fa-clock mr-1" style="color: var(--warning);"></i>
                                    Expires: {{ $pendingApproval->approval_expires_at->format('M d, Y') }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Property Info -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Left Column -->
            <div class="card p-6">
                <h3 class="font-semibold text-sm mb-3" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-2"></i> Property Information
                </h3>
                <div class="space-y-2">
                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Property Name</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $property->property_name }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Property Type</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $property->propertyType->name ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Digital Address</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $property->digital_address ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Street</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $property->street_name ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Zone</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $property->zone ?? 'Unassigned' }}</span>
                    </div>
                    <div class="flex justify-between py-2 items-center">
                        <span class="text-sm" style="color: var(--text-secondary);">Status</span>
                        <span class="status-badge {{ $propertyStatusInfo['class'] }}" data-status="{{ $propertyStatus }}">
                            {{ $propertyStatusInfo['label'] }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="card p-6">
                <h3 class="font-semibold text-sm mb-3" style="color: var(--text-secondary);">
                    <i class="fas fa-user mr-2"></i> Landlord Information
                </h3>
                <div class="space-y-2">
                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Name</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $property->landlord->name ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Phone</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $property->landlord->phone ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Email</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $property->landlord->email ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between py-2 items-center">
                        <span class="text-sm" style="color: var(--text-secondary);">Approval Status</span>
                        <span class="status-badge {{ $approvalStatusClass }}" data-approval-status="{{ $approvalStatusRaw }}">
                            {{ $approvalStatusRaw }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Collection Statistics -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="card p-3 text-center">
                <div class="text-xl font-bold" style="color: var(--text-primary);">{{ $stats['total'] }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Total Requests</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-green-500">{{ $stats['completed'] }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Completed</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-yellow-500">{{ $stats['pending'] }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Pending</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-bold text-blue-500">{{ $stats['active'] }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Active</div>
            </div>
        </div>

        <!-- Collection Settings -->
        @if(!empty($collectionSettings))
            <div class="card p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-cog mr-2"></i> Collection Settings
                    </h3>
                    {{-- ✅ Edit Settings gated by linking permission --}}
                    @if($canLinkProperties)
                        <a href="{{ route('sanitation.properties.settings', $property) }}" class="btn-info text-sm">
                            <i class="fas fa-edit mr-1"></i> Edit Settings
                        </a>
                    @else
                        <span class="btn-secondary text-sm" style="cursor: not-allowed; opacity: 0.65;"
                              title="Only supervisors created by admin or another supervisor can edit">
                            <i class="fas fa-lock mr-1"></i> Edit Locked
                        </span>
                    @endif
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <span class="text-xs" style="color: var(--text-secondary);">Frequency</span>
                        <p class="font-medium" style="color: var(--text-primary);">{{ ucfirst($collectionSettings['collection_frequency'] ?? 'Not set') }}</p>
                    </div>
                    <div>
                        <span class="text-xs" style="color: var(--text-secondary);">Collection Days</span>
                        <p class="font-medium" style="color: var(--text-primary);">
                            @if(!empty($collectionSettings['collection_days']))
                                {{ implode(', ', $collectionSettings['collection_days']) }}
                            @else
                                Not set
                            @endif
                        </p>
                    </div>
                    <div>
                        <span class="text-xs" style="color: var(--text-secondary);">Preferred Time</span>
                        <p class="font-medium" style="color: var(--text-primary);">{{ $collectionSettings['preferred_time'] ?? 'Not set' }}</p>
                    </div>
                    <div>
                        <span class="text-xs" style="color: var(--text-secondary);">Waste Types</span>
                        <p class="font-medium" style="color: var(--text-primary);">
                            @if(!empty($collectionSettings['waste_types']))
                                {{ implode(', ', array_map('ucfirst', $collectionSettings['waste_types'])) }}
                            @else
                                Not set
                            @endif
                        </p>
                    </div>
                </div>
                @if(!empty($collectionSettings['special_instructions']))
                    <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                        <span class="text-xs" style="color: var(--text-secondary);">Special Instructions</span>
                        <p class="text-sm mt-1" style="color: var(--text-primary);">{{ $collectionSettings['special_instructions'] }}</p>
                    </div>
                @endif
            </div>
        @endif

        <!-- Active Requests -->
        @if($activeRequests->count() > 0)
            <div class="card p-6 mb-6">
                <h3 class="font-semibold text-sm mb-3" style="color: var(--text-secondary);">
                    <i class="fas fa-clock mr-2"></i> Active Requests ({{ $activeRequests->count() }})
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead style="background-color: var(--bg-secondary);">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">ID</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Priority</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Assigned To</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($activeRequests as $request)
                                @php
                                    $reqStatusInfo = $requestStatusMap[$request->status] ?? [
                                        'label' => $request->status_label ?? ucfirst($request->status ?? 'Unknown'),
                                        'class' => 'status-unknown',
                                    ];

                                    $reqPriorityInfo = $priorityMap[$request->priority] ?? [
                                        'label' => ucfirst($request->priority ?? 'Normal'),
                                        'class' => 'status-priority-low',
                                    ];
                                @endphp
                                <tr>
                                    <td class="px-3 py-2" style="color: var(--text-primary);">#{{ $request->id }}</td>
                                    <td class="px-3 py-2">
                                        <span class="status-badge {{ $reqStatusInfo['class'] }}" data-status="{{ $request->status }}">
                                            {{ $reqStatusInfo['label'] }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="status-badge {{ $reqPriorityInfo['class'] }}" data-priority="{{ $request->priority }}">
                                            {{ $reqPriorityInfo['label'] }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2" style="color: var(--text-secondary);">{{ $request->assignedTo->full_name ?? 'Unassigned' }}</td>
                                    <td class="px-3 py-2 text-xs" style="color: var(--text-secondary);">{{ $request->created_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Collection History -->
        <div class="card p-6">
            <h3 class="font-semibold text-sm mb-3" style="color: var(--text-secondary);">
                <i class="fas fa-history mr-2"></i> Collection History ({{ $collectionHistory->total() }})
            </h3>
            @if($collectionHistory->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead style="background-color: var(--bg-secondary);">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">ID</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Waste Type</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Completed</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Assigned To</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($collectionHistory as $request)
                                @php
                                    $histStatusInfo = $requestStatusMap[$request->status] ?? [
                                        'label' => $request->status_label ?? ucfirst($request->status ?? 'Unknown'),
                                        'class' => 'status-unknown',
                                    ];

                                    $histWasteInfo = $wasteTypeMap[$request->waste_type] ?? [
                                        'label' => ucfirst($request->waste_type ?? 'General'),
                                        'class' => 'status-waste-general',
                                    ];
                                @endphp
                                <tr>
                                    <td class="px-3 py-2" style="color: var(--text-primary);">#{{ $request->id }}</td>
                                    <td class="px-3 py-2">
                                        <span class="status-badge {{ $histWasteInfo['class'] }}" data-waste-type="{{ $request->waste_type }}">
                                            {{ $histWasteInfo['label'] }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="status-badge {{ $histStatusInfo['class'] }}" data-status="{{ $request->status }}">
                                            {{ $histStatusInfo['label'] }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-xs" style="color: var(--text-secondary);">
                                        @if($request->completed_at)
                                            {{ $request->completed_at->format('M d, Y') }}
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td class="px-3 py-2" style="color: var(--text-secondary);">{{ $request->assignedTo->full_name ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($collectionHistory->hasPages())
                    <div class="mt-3">
                        {{ $collectionHistory->appends(request()->query())->links() }}
                    </div>
                @endif
            @else
                <p class="text-sm" style="color: var(--text-secondary);">No collection history for this property.</p>
            @endif
        </div>

        <!-- Actions -->
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('sanitation.properties.available') }}" class="btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i> Back to Available
            </a>

            @if($canLinkProperties)
                {{-- ✅ Link action gated by linking permission --}}
                @if($stats['total'] == 0)
                    <a href="{{ route('sanitation.properties.link', $property) }}" class="btn-primary">
                        <i class="fas fa-link mr-2"></i> Link to Collection
                    </a>
                @endif

                {{-- ✅ Unlink action gated by linking permission --}}
                <form action="{{ route('sanitation.properties.unlink', $property) }}" method="POST" class="inline"
                      onsubmit="return confirm('Are you sure you want to unlink this property from waste collection?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger">
                        <i class="fas fa-unlink mr-2"></i> Unlink Property
                    </button>
                </form>
            @else
                {{-- Read-only hint instead of buttons --}}
                <span class="btn-secondary" style="cursor: not-allowed; opacity: 0.65;"
                      title="Only supervisors created by an administrator or another supervisor can perform these actions">
                    <i class="fas fa-lock mr-2"></i> Actions Locked
                </span>
            @endif
        </div>

        {{-- Admin debug — mirrors available.blade.php --}}
        @if($isAdmin)
            <div class="card p-3 mt-6" style="background-color: #fef3c7; border-color: #f59e0b;">
                <p class="text-xs" style="color: var(--text-secondary);">
                    <i class="fas fa-bug mr-1 text-yellow-600"></i>
                    Linking rights for current user:
                    <strong style="color: {{ $canLinkProperties ? '#16a34a' : '#dc2626' }};">
                        {{ $canLinkProperties ? 'GRANTED (admin, or supervisor created by admin/supervisor)' : 'DENIED' }}
                    </strong>
                </p>
            </div>
        @endif
    </div>
</div>
@endsection

@push('styles')
<style>
    /* ----------------------------------------------------------------- */
    /* Buttons                                                           */
    /* ----------------------------------------------------------------- */
    .btn-primary, .btn-secondary, .btn-info, .btn-danger {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
        text-decoration: none;
    }
    .btn-primary { background-color: var(--primary); color: white; }
    .btn-info    { background-color: var(--info);    color: white; }
    .btn-danger  { background-color: #ef4444;        color: white; }
    .btn-secondary {
        background-color: var(--bg-secondary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }
    .btn-primary:hover, .btn-info:hover, .btn-danger:hover {
        opacity: 0.9;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        color: white;
        text-decoration: none;
    }
    .btn-secondary:hover {
        opacity: 0.8;
        text-decoration: none;
        color: var(--text-primary);
    }

    /* ----------------------------------------------------------------- */
    /* Cards                                                             */
    /* ----------------------------------------------------------------- */
    .card {
        background-color: var(--card-bg);
        border-radius: 0.75rem;
        border: 1px solid var(--border-color);
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    }

    /* ----------------------------------------------------------------- */
    /* STATUS BADGES — theme-aware, always visible, no Tailwind JIT      */
    /* ----------------------------------------------------------------- */
    .status-badge {
        display: inline-flex !important;
        align-items: center;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 600;
        line-height: 1.4;
        white-space: nowrap;
        visibility: visible !important;
        opacity: 1 !important;
        border: 1px solid transparent;
    }

    /* ---------- Property status ---------- */
    .status-active {
        background-color: rgba(34, 197, 94, 0.15);
        color: #16a34a;
        border-color: rgba(34, 197, 94, 0.3);
    }
    [data-theme="dark"] .status-active {
        background-color: rgba(34, 197, 94, 0.2);
        color: #4ade80;
        border-color: rgba(34, 197, 94, 0.4);
    }

    .status-inactive {
        background-color: rgba(239, 68, 68, 0.15);
        color: #dc2626;
        border-color: rgba(239, 68, 68, 0.3);
    }
    [data-theme="dark"] .status-inactive {
        background-color: rgba(239, 68, 68, 0.2);
        color: #f87171;
        border-color: rgba(239, 68, 68, 0.4);
    }

    .status-pending {
        background-color: rgba(245, 158, 11, 0.15);
        color: #b45309;
        border-color: rgba(245, 158, 11, 0.3);
    }
    [data-theme="dark"] .status-pending {
        background-color: rgba(245, 158, 11, 0.2);
        color: #fbbf24;
        border-color: rgba(245, 158, 11, 0.4);
    }

    .status-maintenance {
        background-color: rgba(168, 85, 247, 0.15);
        color: #7e22ce;
        border-color: rgba(168, 85, 247, 0.3);
    }
    [data-theme="dark"] .status-maintenance {
        background-color: rgba(168, 85, 247, 0.2);
        color: #c084fc;
        border-color: rgba(168, 85, 247, 0.4);
    }

    .status-vacant {
        background-color: rgba(59, 130, 246, 0.15);
        color: #2563eb;
        border-color: rgba(59, 130, 246, 0.3);
    }
    [data-theme="dark"] .status-vacant {
        background-color: rgba(59, 130, 246, 0.2);
        color: #60a5fa;
        border-color: rgba(59, 130, 246, 0.4);
    }

    .status-construction {
        background-color: rgba(249, 115, 22, 0.15);
        color: #ea580c;
        border-color: rgba(249, 115, 22, 0.3);
    }
    [data-theme="dark"] .status-construction {
        background-color: rgba(249, 115, 22, 0.2);
        color: #fb923c;
        border-color: rgba(249, 115, 22, 0.4);
    }

    .status-archived {
        background-color: rgba(71, 85, 105, 0.15);
        color: #475569;
        border-color: rgba(71, 85, 105, 0.3);
    }
    [data-theme="dark"] .status-archived {
        background-color: rgba(148, 163, 184, 0.15);
        color: #cbd5e1;
        border-color: rgba(148, 163, 184, 0.3);
    }

    .status-unknown {
        background-color: rgba(107, 114, 128, 0.15);
        color: var(--text-secondary);
        border-color: rgba(107, 114, 128, 0.25);
    }
    [data-theme="dark"] .status-unknown {
        background-color: rgba(148, 163, 184, 0.18);
        color: #cbd5e1;
        border-color: rgba(148, 163, 184, 0.3);
    }

    /* ---------- Approval status ---------- */
    .status-approval-pending {
        background-color: rgba(245, 158, 11, 0.18);
        color: #b45309;
        border-color: rgba(245, 158, 11, 0.35);
    }
    [data-theme="dark"] .status-approval-pending {
        background-color: rgba(245, 158, 11, 0.25);
        color: #fbbf24;
        border-color: rgba(245, 158, 11, 0.45);
    }

    .status-approval-approved {
        background-color: rgba(34, 197, 94, 0.15);
        color: #16a34a;
        border-color: rgba(34, 197, 94, 0.3);
    }
    [data-theme="dark"] .status-approval-approved {
        background-color: rgba(34, 197, 94, 0.2);
        color: #4ade80;
        border-color: rgba(34, 197, 94, 0.4);
    }

    .status-approval-auto {
        background-color: rgba(59, 130, 246, 0.15);
        color: #2563eb;
        border-color: rgba(59, 130, 246, 0.3);
    }
    [data-theme="dark"] .status-approval-auto {
        background-color: rgba(59, 130, 246, 0.2);
        color: #60a5fa;
        border-color: rgba(59, 130, 246, 0.4);
    }

    .status-approval-rejected {
        background-color: rgba(239, 68, 68, 0.15);
        color: #dc2626;
        border-color: rgba(239, 68, 68, 0.3);
    }
    [data-theme="dark"] .status-approval-rejected {
        background-color: rgba(239, 68, 68, 0.2);
        color: #f87171;
        border-color: rgba(239, 68, 68, 0.4);
    }

    .status-approval-expired {
        background-color: rgba(107, 114, 128, 0.15);
        color: var(--text-secondary);
        border-color: rgba(107, 114, 128, 0.3);
    }
    [data-theme="dark"] .status-approval-expired {
        background-color: rgba(148, 163, 184, 0.15);
        color: #cbd5e1;
        border-color: rgba(148, 163, 184, 0.3);
    }

    /* ---------- Request status ---------- */
    .status-assigned {
        background-color: rgba(59, 130, 246, 0.15);
        color: #2563eb;
        border-color: rgba(59, 130, 246, 0.3);
    }
    [data-theme="dark"] .status-assigned {
        background-color: rgba(59, 130, 246, 0.2);
        color: #60a5fa;
        border-color: rgba(59, 130, 246, 0.4);
    }

    .status-enroute {
        background-color: rgba(99, 102, 241, 0.15);
        color: #4f46e5;
        border-color: rgba(99, 102, 241, 0.3);
    }
    [data-theme="dark"] .status-enroute {
        background-color: rgba(99, 102, 241, 0.2);
        color: #a5b4fc;
        border-color: rgba(99, 102, 241, 0.4);
    }

    .status-arrived {
        background-color: rgba(168, 85, 247, 0.15);
        color: #7e22ce;
        border-color: rgba(168, 85, 247, 0.3);
    }
    [data-theme="dark"] .status-arrived {
        background-color: rgba(168, 85, 247, 0.2);
        color: #c084fc;
        border-color: rgba(168, 85, 247, 0.4);
    }

    .status-inprogress {
        background-color: rgba(6, 182, 212, 0.15);
        color: #0891b2;
        border-color: rgba(6, 182, 212, 0.3);
    }
    [data-theme="dark"] .status-inprogress {
        background-color: rgba(6, 182, 212, 0.2);
        color: #22d3ee;
        border-color: rgba(6, 182, 212, 0.4);
    }

    .status-completed {
        background-color: rgba(34, 197, 94, 0.15);
        color: #16a34a;
        border-color: rgba(34, 197, 94, 0.3);
    }
    [data-theme="dark"] .status-completed {
        background-color: rgba(34, 197, 94, 0.2);
        color: #4ade80;
        border-color: rgba(34, 197, 94, 0.4);
    }

    .status-cancelled {
        background-color: rgba(239, 68, 68, 0.15);
        color: #dc2626;
        border-color: rgba(239, 68, 68, 0.3);
    }
    [data-theme="dark"] .status-cancelled {
        background-color: rgba(239, 68, 68, 0.2);
        color: #f87171;
        border-color: rgba(239, 68, 68, 0.4);
    }

    .status-missed {
        background-color: rgba(120, 113, 108, 0.15);
        color: #57534e;
        border-color: rgba(120, 113, 108, 0.3);
    }
    [data-theme="dark"] .status-missed {
        background-color: rgba(168, 162, 158, 0.15);
        color: #d6d3d1;
        border-color: rgba(168, 162, 158, 0.3);
    }

    /* ---------- Priority ---------- */
    .status-priority-emergency {
        background-color: rgba(239, 68, 68, 0.18);
        color: #b91c1c;
        border-color: rgba(239, 68, 68, 0.35);
    }
    [data-theme="dark"] .status-priority-emergency {
        background-color: rgba(239, 68, 68, 0.25);
        color: #fca5a5;
        border-color: rgba(239, 68, 68, 0.45);
    }

    .status-priority-high {
        background-color: rgba(249, 115, 22, 0.15);
        color: #c2410c;
        border-color: rgba(249, 115, 22, 0.3);
    }
    [data-theme="dark"] .status-priority-high {
        background-color: rgba(249, 115, 22, 0.2);
        color: #fb923c;
        border-color: rgba(249, 115, 22, 0.4);
    }

    .status-priority-medium {
        background-color: rgba(245, 158, 11, 0.15);
        color: #b45309;
        border-color: rgba(245, 158, 11, 0.3);
    }
    [data-theme="dark"] .status-priority-medium {
        background-color: rgba(245, 158, 11, 0.2);
        color: #fbbf24;
        border-color: rgba(245, 158, 11, 0.4);
    }

    .status-priority-low {
        background-color: rgba(107, 114, 128, 0.15);
        color: var(--text-secondary);
        border-color: rgba(107, 114, 128, 0.25);
    }
    [data-theme="dark"] .status-priority-low {
        background-color: rgba(148, 163, 184, 0.18);
        color: #cbd5e1;
        border-color: rgba(148, 163, 184, 0.3);
    }

    /* ---------- Waste types ---------- */
    .status-waste-general {
        background-color: rgba(107, 114, 128, 0.15);
        color: var(--text-secondary);
        border-color: rgba(107, 114, 128, 0.25);
    }
    [data-theme="dark"] .status-waste-general {
        background-color: rgba(148, 163, 184, 0.18);
        color: #cbd5e1;
        border-color: rgba(148, 163, 184, 0.3);
    }

    .status-waste-recyclable {
        background-color: rgba(34, 197, 94, 0.15);
        color: #16a34a;
        border-color: rgba(34, 197, 94, 0.3);
    }
    [data-theme="dark"] .status-waste-recyclable {
        background-color: rgba(34, 197, 94, 0.2);
        color: #4ade80;
        border-color: rgba(34, 197, 94, 0.4);
    }

    .status-waste-organic {
        background-color: rgba(245, 158, 11, 0.15);
        color: #b45309;
        border-color: rgba(245, 158, 11, 0.3);
    }
    [data-theme="dark"] .status-waste-organic {
        background-color: rgba(245, 158, 11, 0.2);
        color: #fbbf24;
        border-color: rgba(245, 158, 11, 0.4);
    }

    .status-waste-hazardous {
        background-color: rgba(239, 68, 68, 0.15);
        color: #dc2626;
        border-color: rgba(239, 68, 68, 0.3);
    }
    [data-theme="dark"] .status-waste-hazardous {
        background-color: rgba(239, 68, 68, 0.2);
        color: #f87171;
        border-color: rgba(239, 68, 68, 0.4);
    }

    .status-waste-bulk {
        background-color: rgba(168, 85, 247, 0.15);
        color: #7e22ce;
        border-color: rgba(168, 85, 247, 0.3);
    }
    [data-theme="dark"] .status-waste-bulk {
        background-color: rgba(168, 85, 247, 0.2);
        color: #c084fc;
        border-color: rgba(168, 85, 247, 0.4);
    }
</style>
@endpush