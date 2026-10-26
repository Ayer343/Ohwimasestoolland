{{-- landlord/family-links/for-property.blade.php --}}
@php
    use App\Models\PropertyFamilyLink;

    $landlord = auth()->user();

    $pageTitle = 'Family Links';

    $successMessage = session('success');
    $errorMessage   = session('error');

    // ── Context detection ──
    // This blade serves BOTH landlord.family-links.index (global) and
    // landlord.properties.family-links (per-property). The controller
    // injects `$property` only on the per-property route, so we can
    // use its presence to decide whether to render the "back to index"
    // button in the header.
    $isPerPropertyView = isset($property) && $property instanceof \App\Models\Property;
    $currentPropertyId = $isPerPropertyView ? (int) $property->id : null;

    // ── Stats (two-stage aware) ──
    $totalCount          = $stats['total']             ?? 0;
    $awaitingLandlordCnt = $stats['awaiting_landlord'] ?? 0;
    $awaitingAdminCnt    = $stats['awaiting_admin']    ?? 0;
    $pendingCount        = $stats['pending']           ?? 0;
    $approvedCount       = $stats['approved']          ?? 0;
    $rejectedCount       = $stats['rejected']          ?? 0;
    $revokedCount        = $stats['revoked']           ?? 0;
    $cancelledCount      = $stats['cancelled']         ?? 0;

    $currentStatus = request('status');

    // ────────────────────────────────────────────────────────────
    // Permissions model — maps to the landlord's web.php routes
    // that are guarded by `family.link.active`. Each group below
    // corresponds to one or more of those routes, so this matrix
    // is the single source of truth for what a family member can do.
    // ────────────────────────────────────────────────────────────
    $permissionDefinitions = [
        'view' => [
            'label'       => 'View Property',
            'description' => 'See property details, photos, tenant info, and inspection history.',
            'icon'        => 'fa-eye',
            'route_hint'  => 'GET landlord/properties/{property}',
            'group'       => 'basic',
        ],
        'edit' => [
            'label'       => 'Edit Property',
            'description' => 'Update construction details, coordinates, and property metadata.',
            'icon'        => 'fa-pen',
            'route_hint'  => 'PUT landlord/properties/{property}/construction',
            'group'       => 'basic',
        ],
        'upload_photos' => [
            'label'       => 'Manage Photos',
            'description' => 'Upload, delete, and reorder property photos, including setting the primary photo.',
            'icon'        => 'fa-camera',
            'route_hint'  => 'POST/DELETE landlord/properties/{property}/upload-photos',
            'group'       => 'basic',
        ],
        'receive_notifications' => [
            'label'       => 'Receive Notifications',
            'description' => 'Get in-app notifications about activity on this property.',
            'icon'        => 'fa-bell',
            'route_hint'  => 'Notifications inbox',
            'group'       => 'basic',
        ],
        'manage_units' => [
            'label'       => 'Manage Units',
            'description' => 'Create, edit, archive, and delete property units.',
            'icon'        => 'fa-door-open',
            'route_hint'  => '/landlord/property-units/*',
            'group'       => 'operations',
        ],
        'manage_tenants' => [
            'label'       => 'Manage Tenants',
            'description' => 'Assign tenants to units, mark units vacated, terminate tenant leases.',
            'icon'        => 'fa-users',
            'route_hint'  => '/landlord/property-units/{id}/assign-tenant',
            'group'       => 'operations',
        ],
        'manage_leases' => [
            'label'       => 'Manage Leases',
            'description' => 'Create, renew, and terminate leases; sign on behalf of the landlord.',
            'icon'        => 'fa-file-signature',
            'route_hint'  => '/landlord/property-units/{id}/create-lease',
            'group'       => 'operations',
        ],
        'manage_maintenance' => [
            'label'       => 'Manage Maintenance',
            'description' => 'Create, update, and close maintenance requests for units.',
            'icon'        => 'fa-tools',
            'route_hint'  => '/landlord/property-units/{id}/maintenance-requests',
            'group'       => 'operations',
        ],
        'view_financials' => [
            'label'       => 'View Financials',
            'description' => 'See invoices, payment history, and financial reports for this property.',
            'icon'        => 'fa-chart-line',
            'route_hint'  => '/landlord/property-units/{id}/financials',
            'group'       => 'finance',
        ],
        'manage_invoices' => [
            'label'       => 'Manage Invoices',
            'description' => 'Generate, update, and void invoices for the property.',
            'icon'        => 'fa-file-invoice',
            'route_hint'  => 'POST /landlord/invoices/generate-manual',
            'group'       => 'finance',
        ],
        'make_payments' => [
            'label'       => 'Make Payments',
            'description' => 'Submit payments on behalf of the property, and confirm bank transfers.',
            'icon'        => 'fa-credit-card',
            'route_hint'  => 'GET/POST /landlord/payments/*',
            'group'       => 'finance',
        ],
        'manage_waste' => [
            'label'       => 'Manage Waste Collection',
            'description' => 'Approve or reject waste collection requests, report bin status.',
            'icon'        => 'fa-trash-alt',
            'route_hint'  => '/landlord/waste/*',
            'group'       => 'operations',
        ],
        'manage_contracts' => [
            'label'       => 'Manage Construction Contracts',
            'description' => 'View, create, and update construction contracts for this property.',
            'icon'        => 'fa-file-contract',
            'route_hint'  => '/landlord/construction/contracts/*',
            'group'       => 'operations',
        ],
    ];

    // Merge config-driven label overrides / additions
    $configPermissions = config('property_family_links.permissions', []);
    foreach ($configPermissions as $key => $label) {
        if (isset($permissionDefinitions[$key])) {
            $permissionDefinitions[$key]['label'] = $label;
        } else {
            $permissionDefinitions[$key] = [
                'label'       => $label,
                'description' => 'Custom permission.',
                'icon'        => 'fa-key',
                'route_hint'  => null,
                'group'       => 'custom',
            ];
        }
    }

    $permissionGroups = [
        'basic'      => ['label' => 'Basic Access',         'icon' => 'fa-shield-alt',   'color' => 'var(--info)'],
        'operations' => ['label' => 'Property Operations',  'icon' => 'fa-cogs',         'color' => 'var(--primary)'],
        'finance'    => ['label' => 'Financial Access',     'icon' => 'fa-dollar-sign',  'color' => 'var(--success)'],
        'custom'     => ['label' => 'Custom Permissions',   'icon' => 'fa-key',          'color' => 'var(--secondary)'],
    ];

    $permissionPresets = [
        'readonly' => [
            'label'       => 'Read-only',
            'description' => 'View everything, change nothing.',
            'icon'        => 'fa-eye',
            'permissions' => ['view', 'view_financials', 'receive_notifications'],
        ],
        'standard' => [
            'label'       => 'Standard',
            'description' => 'View and manage day-to-day operations.',
            'icon'        => 'fa-user-check',
            'permissions' => [
                'view', 'edit', 'upload_photos', 'receive_notifications',
                'manage_units', 'manage_tenants', 'manage_maintenance',
                'view_financials', 'manage_waste',
            ],
        ],
        'full' => [
            'label'       => 'Full access',
            'description' => 'Everything except ownership changes.',
            'icon'        => 'fa-user-shield',
            'permissions' => array_keys($permissionDefinitions),
        ],
    ];

    $relationshipOptions = config('property_family_links.relationship_options', []);
    $defaultPermissions  = config('property_family_links.default_permissions', ['view', 'receive_notifications']);

    // ─────────────────────────────────────────────────────────────
    // Bulk-link + existing-member support.
    //
    // `$existingFamilyMembers` is passed by the controller as a
    // collection of arrays — each representing a distinct approved
    // family member, with the list of properties they're already
    // linked to.
    // ─────────────────────────────────────────────────────────────
    $existingFamilyMembers = $existingFamilyMembers ?? collect();
@endphp

@extends('layouts.landlord')

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- ============================================================
         HEADER
         ============================================================ --}}
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-4">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center flex-wrap gap-2" style="color: var(--text-primary);">
                        <i class="fas fa-users mr-2" style="color: var(--primary);"></i>
                        Family Links
                        @if($isPerPropertyView)
                            <span class="text-sm font-normal px-2 py-1 rounded-full"
                                  style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                <i class="fas fa-building mr-1"></i> {{ $property->property_name }}
                            </span>
                        @endif
                        @if($awaitingLandlordCnt > 0)
                            <span class="px-3 py-1 text-xs font-semibold rounded-full"
                                  style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                                <i class="fas fa-hand-pointer mr-1"></i> {{ $awaitingLandlordCnt }} Awaiting Your Confirmation
                            </span>
                        @endif
                        @if($awaitingAdminCnt > 0)
                            <span class="px-3 py-1 text-xs font-semibold rounded-full"
                                  style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                <i class="fas fa-hourglass-half mr-1"></i> {{ $awaitingAdminCnt }} Awaiting Admin Review
                            </span>
                        @endif
                    </h2>
                    <div class="text-sm flex items-center flex-wrap gap-2 mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>
                            @if($isPerPropertyView)
                                Family members linked to this property
                            @else
                                Manage family members linked to your properties
                            @endif
                        </span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-chart-bar mr-1"></i>
                        <span>{{ number_format($totalCount) }} total</span>
                        @if($existingFamilyMembers->isNotEmpty())
                            <span class="mx-1">•</span>
                            <i class="fas fa-user-check mr-1"></i>
                            <span>{{ $existingFamilyMembers->count() }} existing {{ Str::plural('member', $existingFamilyMembers->count()) }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3 flex-wrap gap-2">
                {{-- Back to All Family Links — only shown on the
                     per-property view, since on the index page it
                     would just link to itself. --}}
                @if($isPerPropertyView)
                    <a href="{{ route('landlord.family-links.index') }}"
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-arrow-left mr-1"></i> Back to All Family Links
                    </a>
                @endif

                @if($properties->isNotEmpty())
                    <button type="button"
                            onclick="showAddMemberModal()"
                            class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center btn-primary text-white">
                        <i class="fas fa-user-plus mr-1"></i> Add Family Member
                    </button>
                @endif
                <a href="{{ route('landlord.properties.index') }}"
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-building mr-1"></i> My Properties
                </a>
            </div>
        </div>
    </div>

    {{-- ============================================================
         NOTIFICATIONS
         ============================================================ --}}
    <div id="notificationContainer"></div>

    @if($successMessage)
    <div class="success-message" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3); border-radius: 0.5rem; padding: 1rem;">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                <span class="font-medium" style="color: var(--success);">{{ $successMessage }}</span>
            </div>
            <button type="button" onclick="this.parentElement.parentElement.style.display='none'" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    @if($errorMessage)
    <div class="error-message" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3); border-radius: 0.5rem; padding: 1rem;">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2" style="color: var(--danger);"></i>
                <span class="font-medium" style="color: var(--danger);">{{ $errorMessage }}</span>
            </div>
            <button type="button" onclick="this.parentElement.parentElement.style.display='none'" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    {{-- ============================================================
         STAT CARDS
         ============================================================ --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Total</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($totalCount) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-users text-lg" style="color: var(--primary);"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Awaiting You</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($awaitingLandlordCnt) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-hand-pointer text-lg" style="color: var(--primary);"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Awaiting Admin</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($awaitingAdminCnt) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-hourglass-half text-lg" style="color: var(--warning);"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Approved</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($approvedCount) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1);">
                        <i class="fas fa-check-circle text-lg" style="color: var(--success);"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Rejected</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($rejectedCount) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--danger-rgb), 0.1);">
                        <i class="fas fa-times-circle text-lg" style="color: var(--danger);"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Revoked</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($revokedCount) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--secondary-rgb), 0.1);">
                        <i class="fas fa-ban text-lg" style="color: var(--secondary);"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================
         FILTERS + LIST
         ============================================================ --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

        {{-- Filters --}}
        <div class="lg:col-span-1">
            <div class="card mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filters
                    </h3>

                    <form method="GET" action="{{ $isPerPropertyView ? route('landlord.properties.family-links', $property->id) : route('landlord.family-links.index') }}" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-tag mr-1"></i> Status
                            </label>
                            <select name="status" class="index-custom-dropdown w-full">
                                <option value="">All Status</option>
                                <option value="{{ PropertyFamilyLink::STATUS_PENDING_LANDLORD }}" {{ request('status') == PropertyFamilyLink::STATUS_PENDING_LANDLORD ? 'selected' : '' }}>Awaiting Your Confirmation</option>
                                <option value="{{ PropertyFamilyLink::STATUS_PENDING_ADMIN }}"    {{ request('status') == PropertyFamilyLink::STATUS_PENDING_ADMIN    ? 'selected' : '' }}>Awaiting Admin Review</option>
                                <option value="{{ PropertyFamilyLink::STATUS_PENDING }}"          {{ request('status') == PropertyFamilyLink::STATUS_PENDING          ? 'selected' : '' }}>Pending (legacy)</option>
                                <option value="{{ PropertyFamilyLink::STATUS_APPROVED }}"         {{ request('status') == PropertyFamilyLink::STATUS_APPROVED         ? 'selected' : '' }}>Approved</option>
                                <option value="{{ PropertyFamilyLink::STATUS_REJECTED }}"         {{ request('status') == PropertyFamilyLink::STATUS_REJECTED         ? 'selected' : '' }}>Rejected</option>
                                <option value="{{ PropertyFamilyLink::STATUS_REVOKED }}"          {{ request('status') == PropertyFamilyLink::STATUS_REVOKED          ? 'selected' : '' }}>Revoked</option>
                                <option value="{{ PropertyFamilyLink::STATUS_CANCELLED }}"        {{ request('status') == PropertyFamilyLink::STATUS_CANCELLED        ? 'selected' : '' }}>Cancelled</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-building mr-1"></i> Property
                            </label>
                            <select name="property_id" class="index-custom-dropdown w-full">
                                <option value="">All Properties</option>
                                @foreach($properties as $p)
                                    <option value="{{ $p->id }}" {{ request('property_id') == $p->id ? 'selected' : '' }}>
                                        {{ $p->property_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <div class="space-y-2">
                                <button type="submit" class="block w-full text-center btn-primary px-3 py-2 rounded-lg font-medium text-white">
                                    <i class="fas fa-filter mr-2"></i> Apply Filters
                                </button>
                                <a href="{{ $isPerPropertyView ? route('landlord.properties.family-links', $property->id) : route('landlord.family-links.index') }}"
                                   class="block w-full text-center btn-secondary px-3 py-2 rounded-lg font-medium">
                                    <i class="fas fa-redo mr-2"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Permissions legend --}}
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-key mr-2" style="color: var(--primary);"></i> Permission Legend
                    </h3>
                    <div class="space-y-2">
                        @foreach($permissionGroups as $groupKey => $group)
                            @php
                                $groupPerms = collect($permissionDefinitions)->filter(fn ($p) => ($p['group'] ?? 'custom') === $groupKey);
                            @endphp
                            @if($groupPerms->isNotEmpty())
                                <div class="text-xs font-semibold uppercase tracking-wider mt-2 first:mt-0" style="color: {{ $group['color'] }};">
                                    <i class="fas {{ $group['icon'] }} mr-1"></i> {{ $group['label'] }}
                                </div>
                                @foreach($groupPerms as $key => $perm)
                                    <div class="flex items-start gap-2 text-xs pl-1">
                                        <i class="fas {{ $perm['icon'] }} mt-0.5" style="color: var(--text-secondary); width: 14px;"></i>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">{{ $perm['label'] }}</div>
                                            <div style="color: var(--text-secondary);">{{ $perm['description'] }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Links list --}}
        <div class="lg:col-span-3">
            <div class="card p-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            Family Links
                            @if($isPerPropertyView)
                                <span class="text-sm font-normal ml-2 px-2 py-1 rounded-full"
                                      style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                    {{ $property->property_name }}
                                </span>
                            @endif
                            @if($currentStatus)
                                <span class="text-sm font-normal ml-2 px-2 py-1 rounded-full badge-primary">
                                    {{ data_get([
                                        PropertyFamilyLink::STATUS_PENDING_LANDLORD => 'Awaiting Your Confirmation',
                                        PropertyFamilyLink::STATUS_PENDING_ADMIN    => 'Awaiting Admin Review',
                                        PropertyFamilyLink::STATUS_PENDING          => 'Pending (legacy)',
                                        PropertyFamilyLink::STATUS_APPROVED         => 'Approved',
                                        PropertyFamilyLink::STATUS_REJECTED         => 'Rejected',
                                        PropertyFamilyLink::STATUS_REVOKED          => 'Revoked',
                                        PropertyFamilyLink::STATUS_CANCELLED        => 'Cancelled',
                                    ], $currentStatus, ucfirst(str_replace('_', ' ', $currentStatus))) }}
                                </span>
                            @endif
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Showing {{ $links->firstItem() }} to {{ $links->lastItem() }} of {{ $links->total() }} entries
                        </p>
                    </div>
                    <div class="flex items-center space-x-3 mt-4 md:mt-0 flex-wrap gap-2">
                        {{-- Back to All Family Links — repeated here so it's
                             reachable without scrolling back to the header. --}}
                        @if($isPerPropertyView)
                            <a href="{{ route('landlord.family-links.index') }}"
                               class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                <i class="fas fa-arrow-left mr-2"></i> All Family Links
                            </a>
                        @endif
                        @if($properties->isNotEmpty())
                            <button type="button"
                                    onclick="showAddMemberModal()"
                                    class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                                <i class="fas fa-plus mr-2"></i> Add Family Member
                            </button>
                        @endif
                    </div>
                </div>

                @if($links->isEmpty())
                    <div class="text-center py-12">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4"
                             style="background-color: rgba(var(--primary-rgb), 0.1);">
                            <i class="fas fa-users text-2xl" style="color: var(--primary);"></i>
                        </div>
                        <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No family links yet</h4>
                        <p class="text-sm mb-4" style="color: var(--text-secondary);">
                            @if($properties->isEmpty())
                                You don't have any properties yet.
                            @else
                                Add a family member to one of your properties, or open a property to manage its links.
                            @endif
                        </p>
                        <div class="flex items-center justify-center gap-2 flex-wrap">
                            @if($isPerPropertyView)
                                <a href="{{ route('landlord.family-links.index') }}"
                                   class="inline-flex items-center btn-secondary px-4 py-2 rounded-lg font-medium">
                                    <i class="fas fa-arrow-left mr-2"></i> All Family Links
                                </a>
                            @endif
                            @if($properties->isNotEmpty())
                                <button type="button"
                                        onclick="showAddMemberModal()"
                                        class="inline-flex items-center btn-primary px-4 py-2 rounded-lg font-medium text-white">
                                    <i class="fas fa-user-plus mr-2"></i> Add Family Member
                                </button>
                            @endif
                            <a href="{{ route('landlord.properties.index') }}"
                               class="inline-flex items-center btn-secondary px-4 py-2 rounded-lg font-medium">
                                <i class="fas fa-building mr-2"></i> View My Properties
                            </a>
                        </div>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1100px]" id="linksTable">
                            <thead>
                                <tr>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider"
                                        style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 200px;">
                                        Member
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider"
                                        style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 180px;">
                                        Property
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider"
                                        style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 140px;">
                                        Relationship
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider"
                                        style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 260px;">
                                        Access & Permissions
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider"
                                        style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 200px;">
                                        Status
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider"
                                        style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 200px;">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($links as $link)
                                @php
                                    $statusConfigs = [
                                        PropertyFamilyLink::STATUS_PENDING_LANDLORD => ['class' => 'badge-primary',   'icon' => 'hand-pointer',   'text' => 'Awaiting You'],
                                        PropertyFamilyLink::STATUS_PENDING_ADMIN    => ['class' => 'badge-warning',   'icon' => 'hourglass-half', 'text' => 'Awaiting Admin'],
                                        PropertyFamilyLink::STATUS_PENDING          => ['class' => 'badge-warning',   'icon' => 'clock',          'text' => 'Pending'],
                                        PropertyFamilyLink::STATUS_APPROVED         => ['class' => 'badge-success',   'icon' => 'check-circle',   'text' => 'Approved'],
                                        PropertyFamilyLink::STATUS_REJECTED         => ['class' => 'badge-danger',    'icon' => 'times-circle',   'text' => 'Rejected'],
                                        PropertyFamilyLink::STATUS_REVOKED          => ['class' => 'badge-secondary', 'icon' => 'ban',            'text' => 'Revoked'],
                                        PropertyFamilyLink::STATUS_CANCELLED        => ['class' => 'badge-secondary', 'icon' => 'times',          'text' => 'Cancelled'],
                                    ];
                                    $sc = $statusConfigs[$link->status]
                                        ?? ['class' => 'badge-secondary', 'icon' => 'question-circle', 'text' => ucfirst($link->status)];

                                    $memberName  = $link->display_name ?? $link->proposed_name ?? 'this member';
                                    $reasonId    = "reason-{$link->id}";
                                    $isRejected  = $link->status === PropertyFamilyLink::STATUS_REJECTED;
                                    $hasReason   = $isRejected && !empty($link->admin_notes);
                                    $granted     = $link->permissions ?? [];
                                    $grantedCount = is_array($granted) ? count($granted) : 0;
                                    $isLinkedUser = !empty($link->linked_user_id);
                                    $rejectionReason = $hasReason ? $link->admin_notes : '';
                                @endphp
                                <tr data-link-id="{{ $link->id }}"
                                    data-status="{{ $link->status }}"
                                    data-member-name="{{ $memberName }}"
                                    data-property-id="{{ $link->property_id }}"
                                    data-property-name="{{ $link->property->property_name ?? '' }}"
                                    data-relationship="{{ $link->relationship }}"
                                    data-phone="{{ $link->proposed_phone }}"
                                    data-email="{{ $link->proposed_email }}"
                                    data-landlord-notes="{{ $link->landlord_notes }}"
                                    data-permissions="{{ implode(',', $granted) }}"
                                    data-rejection-reason="{{ $rejectionReason }}"
                                    data-linked-user-id="{{ $link->linked_user_id ?? '' }}">

                                    {{-- Member --}}
                                    <td class="p-3 align-top">
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 mr-2 mt-0.5">
                                                <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                                     style="background-color: rgba(var(--primary-rgb), 0.1);">
                                                    <i class="fas fa-user text-xs" style="color: var(--primary);"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="font-semibold text-sm flex items-center gap-1 flex-wrap" style="color: var(--text-primary);">
                                                    {{ $memberName }}
                                                    @if($isLinkedUser)
                                                        <span class="text-xs px-1.5 py-0.5 rounded-full font-normal"
                                                              title="This person has an account in the system"
                                                              style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                                            <i class="fas fa-user-check mr-0.5"></i> In system
                                                        </span>
                                                    @endif
                                                </div>
                                                @if($link->proposed_phone)
                                                <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                                    <i class="fas fa-phone mr-1"></i> {{ $link->proposed_phone }}
                                                </div>
                                                @endif
                                                @if($link->proposed_email)
                                                <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                                    <i class="fas fa-envelope mr-1"></i> {{ $link->proposed_email }}
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Property --}}
                                    <td class="p-3 align-top">
                                        <div class="text-sm font-medium" style="color: var(--text-primary);">
                                            <i class="fas fa-building mr-1" style="color: var(--secondary);"></i>
                                            {{ $link->property->property_name ?? 'N/A' }}
                                        </div>
                                        @if($link->property->digital_address ?? false)
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            <i class="fas fa-map-marker-alt mr-1"></i> {{ $link->property->digital_address }}
                                        </div>
                                        @endif
                                    </td>

                                    {{-- Relationship --}}
                                    <td class="p-3 align-top">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                              style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                            <i class="fas fa-heart mr-1"></i> {{ $link->relationship_label }}
                                        </span>
                                    </td>

                                    {{-- Access & Permissions --}}
                                    <td class="p-3 align-top">
                                        @if($grantedCount === 0)
                                            <span class="text-xs" style="color: var(--text-secondary);">No permissions granted</span>
                                        @else
                                            <div class="text-xs mb-1" style="color: var(--text-secondary);">
                                                <i class="fas fa-key mr-1"></i>
                                                {{ $grantedCount }} permission{{ $grantedCount > 1 ? 's' : '' }}
                                                @if($grantedCount === count($permissionDefinitions))
                                                    <span class="ml-1 font-semibold" style="color: var(--success);">• Full access</span>
                                                @endif
                                            </div>
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($granted as $perm)
                                                    @php $permDef = $permissionDefinitions[$perm] ?? null; @endphp
                                                    @if($permDef)
                                                        <span class="text-xs px-2 py-0.5 rounded-full"
                                                              title="{{ $permDef['description'] }}"
                                                              style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                                            <i class="fas {{ $permDef['icon'] }} mr-1 text-[10px]"></i>
                                                            {{ $permDef['label'] }}
                                                        </span>
                                                    @else
                                                        <span class="text-xs px-2 py-0.5 rounded-full"
                                                              style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                                                            {{ $perm }}
                                                        </span>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Status --}}
                                    <td class="p-3 align-top">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $sc['class'] }}">
                                            <i class="fas fa-{{ $sc['icon'] }} mr-1 text-xs"></i> {{ $sc['text'] }}
                                        </span>

                                        @if($link->isAwaitingLandlordConfirmation())
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                <i class="fas fa-arrow-right mr-1"></i>Confirm to send to admins
                                            </div>
                                        @elseif($link->isAwaitingAdminReview())
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                Waiting on administrator review
                                            </div>
                                        @endif

                                        @if($hasReason)
                                            <div class="text-xs mt-1.5">
                                                <button type="button"
                                                        onclick="toggleReason('{{ $reasonId }}')"
                                                        class="inline-flex items-center font-medium"
                                                        style="color: var(--danger); background: none; border: none; cursor: pointer; padding: 0;">
                                                    <i class="fas fa-comment mr-1"></i>
                                                    <span id="{{ $reasonId }}-short">
                                                        {{ Str::limit($link->admin_notes, 40) }}
                                                    </span>
                                                    <i class="fas fa-chevron-down ml-1" id="{{ $reasonId }}-icon" style="transition: transform .15s ease;"></i>
                                                </button>
                                                <div id="{{ $reasonId }}-full"
                                                     class="hidden mt-1 p-2 rounded text-xs"
                                                     style="background-color: rgba(var(--danger-rgb), 0.06); border: 1px solid rgba(var(--danger-rgb), 0.2); color: var(--text-primary); max-width: 320px;">
                                                    <div class="font-medium mb-0.5" style="color: var(--danger);">
                                                        <i class="fas fa-comment-dots mr-1"></i> Why this was rejected
                                                    </div>
                                                    <div style="white-space: pre-wrap;">{{ $link->admin_notes }}</div>
                                                    <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                                                        <i class="fas fa-lightbulb mr-1"></i>
                                                        Fix the issue and use <strong>Resubmit</strong> below to try again.
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Actions --}}
                                    <td class="p-3 align-top">
                                        <div class="flex flex-wrap items-center gap-1">
                                            <a href="{{ route('landlord.properties.family-links', $link->property_id) }}"
                                               class="action-btn view" data-tooltip="View Property Links">
                                                <i class="fas fa-eye"></i>
                                            </a>

                                            @if($link->isAwaitingLandlordConfirmation())
                                                <button type="button"
                                                        onclick="showConfirmModal({{ $link->id }})"
                                                        class="action-btn assign" data-tooltip="Confirm & Send to Admin">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button"
                                                        onclick="showCancelModal({{ $link->id }})"
                                                        class="action-btn delete" data-tooltip="Cancel Proposal">
                                                    <i class="fas fa-times"></i>
                                                </button>

                                            @elseif($link->isAwaitingAdminReview())
                                                <button type="button"
                                                        onclick="showCancelModal({{ $link->id }})"
                                                        class="action-btn delete" data-tooltip="Cancel Proposal">
                                                    <i class="fas fa-times"></i>
                                                </button>

                                            @elseif($link->isApproved())
                                                <button type="button"
                                                        onclick="showEditPermissionsModal({{ $link->id }})"
                                                        class="action-btn view" data-tooltip="Edit Permissions">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                <button type="button"
                                                        onclick="showRevokeModal({{ $link->id }})"
                                                        class="action-btn delete" data-tooltip="Revoke Access">
                                                    <i class="fas fa-ban"></i>
                                                </button>

                                            @elseif($link->status === PropertyFamilyLink::STATUS_REJECTED)
                                                <button type="button"
                                                        onclick="showResubmitModal({{ $link->id }})"
                                                        class="action-btn assign" data-tooltip="Edit & Resubmit">
                                                    <i class="fas fa-redo"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-col md:flex-row justify-between items-center pt-6 mt-6 border-t"
                         style="border-color: var(--border-color);">
                        <div class="text-sm mb-4 md:mb-0" style="color: var(--text-secondary);">
                            Showing {{ $links->firstItem() }} to {{ $links->lastItem() }} of {{ $links->total() }} entries
                        </div>
                        <div class="pagination">
                            {{ $links->appends(request()->except('page'))->links() }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ============================================================
     ADD MEMBER MODAL — new member OR add to existing member
     ============================================================ --}}
<div id="addMemberModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideAddMemberModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-3xl">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-user-plus mr-2" style="color: var(--primary);"></i>
                    Link a Family Member
                </h3>
                <button type="button" onclick="hideAddMemberModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="addMemberForm" method="POST" action="{{ route('landlord.family-links.store') }}">
                @csrf

                <div class="modal-body">

                    {{-- Mode toggle --}}
                    <div class="mb-4">
                        <div class="flex items-center gap-2 p-1 rounded-lg"
                             style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                            <button type="button"
                                    class="mode-tab flex-1 px-3 py-2 rounded-md text-sm font-medium transition"
                                    data-mode="new"
                                    style="background-color: var(--primary); color: white;">
                                <i class="fas fa-user-plus mr-1"></i> New family member
                            </button>
                            <button type="button"
                                    class="mode-tab flex-1 px-3 py-2 rounded-md text-sm font-medium transition"
                                    data-mode="existing"
                                    @if($existingFamilyMembers->isEmpty()) disabled title="You haven't linked any approved family members yet" @endif
                                    style="background-color: transparent; color: {{ $existingFamilyMembers->isEmpty() ? 'var(--text-secondary)' : 'var(--text-secondary)' }}; opacity: {{ $existingFamilyMembers->isEmpty() ? '0.5' : '1' }}; cursor: {{ $existingFamilyMembers->isEmpty() ? 'not-allowed' : 'pointer' }};">
                                <i class="fas fa-user-check mr-1"></i> Existing family member
                                @if($existingFamilyMembers->isNotEmpty())
                                    <span class="ml-1 text-xs px-1.5 py-0.5 rounded-full"
                                          style="background-color: rgba(var(--primary-rgb), 0.15);">
                                        {{ $existingFamilyMembers->count() }}
                                    </span>
                                @endif
                            </button>
                        </div>
                        <input type="hidden" name="link_mode" id="linkMode" value="new">
                    </div>

                    {{-- Existing member selector --}}
                    <div id="existingMemberWrap" class="hidden mb-4">
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Family Member <span class="text-red-500">*</span>
                        </label>
                        <select name="existing_user_id" id="existingUserId"
                                class="index-custom-dropdown w-full">
                            <option value="">Select a family member…</option>
                            @foreach($existingFamilyMembers as $member)
                                <option value="{{ $member['user_id'] }}"
                                        data-name="{{ $member['name'] }}"
                                        data-email="{{ $member['email'] }}"
                                        data-phone="{{ $member['phone'] }}"
                                        data-relationship="{{ $member['relationship'] }}"
                                        data-properties="{{ collect($member['properties'])->pluck('id')->implode(',') }}">
                                    {{ $member['name'] }}
                                    @if(!empty($member['email'])) — {{ $member['email'] }} @endif
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1" style="color: var(--info);"></i>
                            They're already in the system — <strong>no invitation will be sent</strong>.
                            Just pick which properties to add.
                        </p>

                        <div class="rounded-lg p-3 mt-2 text-xs"
                             style="background-color: rgba(var(--info-rgb), 0.08); border: 1px solid rgba(var(--info-rgb), 0.2); color: var(--text-secondary);">
                            <i class="fas fa-lightbulb mr-1" style="color: var(--info);"></i>
                            Properties they're already linked to are pre-selected and locked.
                            Just tick the new ones you want to add.
                        </div>
                    </div>

                    {{-- Property picker (multi-select) --}}
                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-1 flex-wrap gap-2">
                            <label class="block text-sm font-medium" style="color: var(--text-primary);">
                                Properties <span class="text-red-500">*</span>
                            </label>
                            <button type="button" id="toggleAllProperties"
                                    class="text-xs font-medium"
                                    style="color: var(--primary); background: none; border: none; cursor: pointer; padding: 0;">
                                <i class="fas fa-check-double mr-1"></i>
                                <span id="toggleAllLabel">Select all</span>
                            </button>
                        </div>

                        <div id="propertyCheckboxList"
                             class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-56 overflow-y-auto p-2 rounded-lg"
                             style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                            @forelse($properties as $p)
                                @php $isCurrentContext = $isPerPropertyView && (int) $p->id === $currentPropertyId; @endphp
                                <label class="property-checkbox-label flex items-start gap-2 p-2 rounded cursor-pointer transition"
                                       data-property-id="{{ $p->id }}"
                                       @if($isCurrentContext) data-current-context="1" @endif
                                       style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                                    <input type="checkbox"
                                           name="property_ids[]"
                                           value="{{ $p->id }}"
                                           class="property-checkbox mt-0.5 w-4 h-4 flex-shrink-0"
                                           style="accent-color: var(--primary);">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-medium flex items-center gap-1 flex-wrap" style="color: var(--text-primary);">
                                            {{ $p->property_name ?: ('Property #' . $p->id) }}
                                            @if($isCurrentContext)
                                                <span class="text-xs px-1.5 py-0.5 rounded-full font-normal"
                                                      title="This is the property you're currently viewing"
                                                      style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                                    <i class="fas fa-map-pin mr-0.5"></i> This page
                                                </span>
                                            @endif
                                        </div>
                                        @if($p->digital_address)
                                            <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                                <i class="fas fa-map-marker-alt mr-1"></i> {{ $p->digital_address }}
                                            </div>
                                        @endif
                                        <div class="already-linked-badge hidden text-xs mt-0.5"
                                             style="color: var(--success);">
                                            <i class="fas fa-check-circle mr-1"></i> Already linked
                                        </div>
                                    </div>
                                </label>
                            @empty
                                <div class="col-span-2 p-3 text-sm text-center" style="color: var(--text-secondary);">
                                    You don't have any properties.
                                </div>
                            @endforelse
                        </div>
                        <div id="propertySelectionHint"
                             class="text-xs mt-1"
                             style="color: var(--text-secondary);">
                            Select one or more properties. Each one becomes its own proposal.
                        </div>
                    </div>

                    {{-- Personal details (new-member mode only) --}}
                    <div id="newMemberDetails">
                        <div class="rounded-lg p-3 mb-4"
                             style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3);">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--info);"></i>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    Your proposal will be reviewed by an administrator before the family member gains access.
                                    Choose exactly what they'll be able to do on these properties.
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                    Full Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="proposed_name" required maxlength="255"
                                       class="index-custom-input w-full"
                                       placeholder="e.g., Jane Doe">
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                    Relationship <span class="text-red-500">*</span>
                                </label>
                                <select name="relationship" required class="index-custom-dropdown w-full"
                                        id="relationshipSelect">
                                    <option value="">Select…</option>
                                    @foreach($relationshipOptions as $key => $label)
                                        @php
                                            $value = is_int($key) ? $label : $key;
                                            $text  = is_int($key) ? ucfirst($label) : $label;
                                        @endphp
                                        <option value="{{ $value }}">{{ $text }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="hidden" id="relationshipOtherWrap">
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                    Specify Relationship
                                </label>
                                <input type="text" name="relationship_other" maxlength="100"
                                       class="index-custom-input w-full"
                                       placeholder="e.g., Uncle, Cousin...">
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                    Phone
                                </label>
                                <input type="text" name="proposed_phone" maxlength="20"
                                       class="index-custom-input w-full"
                                       placeholder="+233...">
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    Provide phone or email.
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                    Email
                                </label>
                                <input type="email" name="proposed_email" maxlength="255"
                                       class="index-custom-input w-full"
                                       placeholder="name@example.com">
                            </div>
                        </div>
                    </div>

                    {{-- Permissions matrix (both modes) --}}
                    <div class="mt-6">
                        <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                            <label class="block text-sm font-semibold" style="color: var(--text-primary);">
                                <i class="fas fa-key mr-1"></i> What can this person do?
                            </label>
                            <div class="flex items-center gap-2 flex-wrap">
                                @foreach($permissionPresets as $presetKey => $preset)
                                    <button type="button"
                                            class="preset-permission-btn text-xs px-3 py-1.5 rounded-full font-medium transition"
                                            data-preset="{{ $presetKey }}"
                                            data-permissions="{{ implode(',', $preset['permissions']) }}"
                                            data-target-form="addMemberForm"
                                            title="{{ $preset['description'] }}"
                                            style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                                        <i class="fas {{ $preset['icon'] }} mr-1"></i>
                                        {{ $preset['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="permissions-matrix space-y-4" data-form="addMemberForm">
                            @foreach($permissionGroups as $groupKey => $group)
                                @php
                                    $groupPerms = collect($permissionDefinitions)->filter(fn ($p) => ($p['group'] ?? 'custom') === $groupKey);
                                @endphp
                                @if($groupPerms->isNotEmpty())
                                    <div class="rounded-lg p-3" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                        <div class="text-xs font-semibold uppercase tracking-wider mb-2 flex items-center" style="color: {{ $group['color'] }};">
                                            <i class="fas {{ $group['icon'] }} mr-2"></i> {{ $group['label'] }}
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                            @foreach($groupPerms as $key => $perm)
                                                <label class="flex items-start gap-2 p-2 rounded cursor-pointer transition hover:bg-opacity-50"
                                                       style="background-color: var(--card-bg); border: 1px solid var(--border-color);"
                                                       title="{{ $perm['route_hint'] ? 'Route: ' . $perm['route_hint'] : '' }}">
                                                    <input type="checkbox"
                                                           name="permissions[]"
                                                           value="{{ $key }}"
                                                           data-permission="{{ $key }}"
                                                           data-form="addMemberForm"
                                                           class="perm-checkbox mt-0.5 w-4 h-4 flex-shrink-0"
                                                           style="accent-color: var(--primary);"
                                                           {{ in_array($key, $defaultPermissions) ? 'checked' : '' }}>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="text-sm font-medium flex items-center gap-1" style="color: var(--text-primary);">
                                                            <i class="fas {{ $perm['icon'] }} text-xs" style="color: {{ $group['color'] }};"></i>
                                                            {{ $perm['label'] }}
                                                        </div>
                                                        <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                                            {{ $perm['description'] }}
                                                        </div>
                                                    </div>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-5">
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Notes for Administrator (Optional)
                        </label>
                        <textarea name="landlord_notes" rows="3" maxlength="1000"
                                  class="index-custom-textarea w-full"
                                  placeholder="Any context for the admin reviewing this proposal..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" onclick="hideAddMemberModal()"
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit"
                            class="btn-primary px-4 py-2 rounded-lg font-medium text-white"
                            id="addMemberSubmitBtn">
                        <i class="fas fa-paper-plane mr-2"></i> Submit Proposal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============================================================
     CONFIRM MODAL
     ============================================================ --}}
<div id="confirmModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideConfirmModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                    Confirm Proposal
                </h3>
                <button type="button" onclick="hideConfirmModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="confirmForm" method="POST" action="">
                @csrf
                <div class="modal-body">
                    <p class="text-sm mb-4" style="color: var(--text-primary);">
                        Confirm the proposal for <strong id="confirmMemberName"></strong>?
                    </p>
                    <div class="rounded-lg p-4"
                         style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                Once confirmed, this proposal will be sent to administrators for review. You can still cancel it while it's pending admin review.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideConfirmModal()"
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit"
                            class="btn-primary px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-check mr-2"></i> Confirm & Send
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============================================================
     CANCEL MODAL
     ============================================================ --}}
<div id="cancelModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideCancelModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i> Cancel Proposal
                </h3>
                <button type="button" onclick="hideCancelModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="cancelForm" method="POST" action="">
                @csrf
                @method('DELETE')
                <div class="modal-body">
                    <p class="text-sm mb-4" style="color: var(--text-primary);">
                        Cancel the pending proposal for <strong id="cancelMemberName"></strong>?
                    </p>
                    <div class="rounded-lg p-4"
                         style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--warning);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                The proposal will be withdrawn. You can submit a new one at any time.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideCancelModal()"
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">Keep</button>
                    <button type="submit"
                            class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-times mr-2"></i> Cancel Proposal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============================================================
     REVOKE MODAL
     ============================================================ --}}
<div id="revokeModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideRevokeModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-ban mr-2" style="color: var(--danger);"></i> Revoke Family Link
                </h3>
                <button type="button" onclick="hideRevokeModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="revokeForm" method="POST" action="">
                @csrf
                <div class="modal-body">
                    <p class="text-sm mb-4" style="color: var(--text-primary);">
                        Revoke access for <strong id="revokeMemberName"></strong>?
                    </p>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Reason (Optional)
                        </label>
                        <textarea name="reason" rows="3" class="index-custom-textarea w-full"
                                  placeholder="Reason for revoking access..."></textarea>
                    </div>
                    <div class="rounded-lg p-4"
                         style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                The family member will <strong>immediately lose access</strong> to this property.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideRevokeModal()"
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit"
                            class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-ban mr-2"></i> Revoke Access
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============================================================
     EDIT PERMISSIONS MODAL
     ============================================================ --}}
<div id="editPermissionsModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideEditPermissionsModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-3xl">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-key mr-2" style="color: var(--primary);"></i>
                    Edit Permissions
                </h3>
                <button type="button" onclick="hideEditPermissionsModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="editPermissionsForm" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <p class="text-sm mb-4" style="color: var(--text-primary);">
                        Update what <strong id="editPermMemberName"></strong> can do on this property.
                        Changes take effect immediately.
                    </p>

                    <div class="flex items-center gap-2 mb-3 flex-wrap">
                        @foreach($permissionPresets as $presetKey => $preset)
                            <button type="button"
                                    class="preset-permission-btn text-xs px-3 py-1.5 rounded-full font-medium transition"
                                    data-preset="{{ $presetKey }}"
                                    data-permissions="{{ implode(',', $preset['permissions']) }}"
                                    data-target-form="editPermissionsForm"
                                    title="{{ $preset['description'] }}"
                                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                                <i class="fas {{ $preset['icon'] }} mr-1"></i>
                                {{ $preset['label'] }}
                            </button>
                        @endforeach
                    </div>

                    <div class="permissions-matrix space-y-4" data-form="editPermissionsForm">
                        @foreach($permissionGroups as $groupKey => $group)
                            @php
                                $groupPerms = collect($permissionDefinitions)->filter(fn ($p) => ($p['group'] ?? 'custom') === $groupKey);
                            @endphp
                            @if($groupPerms->isNotEmpty())
                                <div class="rounded-lg p-3" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                    <div class="text-xs font-semibold uppercase tracking-wider mb-2 flex items-center" style="color: {{ $group['color'] }};">
                                        <i class="fas {{ $group['icon'] }} mr-2"></i> {{ $group['label'] }}
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                        @foreach($groupPerms as $key => $perm)
                                            <label class="flex items-start gap-2 p-2 rounded cursor-pointer transition"
                                                   style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                                                <input type="checkbox"
                                                       name="permissions[]"
                                                       value="{{ $key }}"
                                                       data-permission="{{ $key }}"
                                                       data-form="editPermissionsForm"
                                                       class="perm-checkbox mt-0.5 w-4 h-4 flex-shrink-0"
                                                       style="accent-color: var(--primary);">
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-sm font-medium flex items-center gap-1" style="color: var(--text-primary);">
                                                        <i class="fas {{ $perm['icon'] }} text-xs" style="color: {{ $group['color'] }};"></i>
                                                        {{ $perm['label'] }}
                                                    </div>
                                                    <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                                        {{ $perm['description'] }}
                                                    </div>
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideEditPermissionsModal()"
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit"
                            class="btn-primary px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-save mr-2"></i> Save Permissions
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============================================================
     RESUBMIT MODAL
     ============================================================ --}}
<div id="resubmitModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideResubmitModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-3xl">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-redo mr-2" style="color: var(--primary);"></i>
                    Edit & Resubmit Proposal
                </h3>
                <button type="button" onclick="hideResubmitModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="resubmitForm" method="POST" action="{{ route('landlord.family-links.store') }}">
                @csrf
                <input type="hidden" name="resubmit_from" id="resubmitFromId" value="">
                <input type="hidden" name="property_id"   id="resubmitPropertyId" value="">
                <input type="hidden" name="link_mode"     value="new">
                {{-- resubmit always targets exactly one property --}}
                <input type="hidden" name="property_ids[]" id="resubmitPropertyIds" value="">

                <div class="modal-body">
                    <div id="resubmitReasonBanner"
                         class="rounded-lg p-3 mb-4 hidden"
                         style="background-color: rgba(var(--danger-rgb), 0.08); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--danger);"></i>
                            <div class="flex-1">
                                <div class="text-xs font-semibold mb-0.5" style="color: var(--danger);">
                                    Original rejection reason
                                </div>
                                <div class="text-xs" id="resubmitReasonText" style="color: var(--text-primary); white-space: pre-wrap;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg p-3 mb-4"
                         style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--info);"></i>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                This will update your <strong>existing rejected proposal</strong> and reset it to
                                <em>Awaiting Your Confirmation</em>. Once you confirm it, it goes to admins for a fresh review.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                Full Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="proposed_name" id="resubmitName" required maxlength="255"
                                   class="index-custom-input w-full"
                                   placeholder="e.g., Jane Doe">
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                Relationship <span class="text-red-500">*</span>
                            </label>
                            <select name="relationship" id="resubmitRelationship" required
                                    class="index-custom-dropdown w-full">
                                <option value="">Select…</option>
                                @foreach($relationshipOptions as $key => $label)
                                    @php
                                        $value = is_int($key) ? $label : $key;
                                        $text  = is_int($key) ? ucfirst($label) : $label;
                                    @endphp
                                    <option value="{{ $value }}">{{ $text }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                Phone
                            </label>
                            <input type="text" name="proposed_phone" id="resubmitPhone" maxlength="20"
                                   class="index-custom-input w-full"
                                   placeholder="+233...">
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">Provide phone or email.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                Email
                            </label>
                            <input type="email" name="proposed_email" id="resubmitEmail" maxlength="255"
                                   class="index-custom-input w-full"
                                   placeholder="name@example.com">
                        </div>
                    </div>

                    <div class="mt-6">
                        <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                            <label class="block text-sm font-semibold" style="color: var(--text-primary);">
                                <i class="fas fa-key mr-1"></i> What can this person do?
                            </label>
                            <div class="flex items-center gap-2 flex-wrap">
                                @foreach($permissionPresets as $presetKey => $preset)
                                    <button type="button"
                                            class="preset-permission-btn text-xs px-3 py-1.5 rounded-full font-medium transition"
                                            data-preset="{{ $presetKey }}"
                                            data-permissions="{{ implode(',', $preset['permissions']) }}"
                                            data-target-form="resubmitForm"
                                            title="{{ $preset['description'] }}"
                                            style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                                        <i class="fas {{ $preset['icon'] }} mr-1"></i>
                                        {{ $preset['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="permissions-matrix space-y-4" data-form="resubmitForm">
                            @foreach($permissionGroups as $groupKey => $group)
                                @php
                                    $groupPerms = collect($permissionDefinitions)->filter(fn ($p) => ($p['group'] ?? 'custom') === $groupKey);
                                @endphp
                                @if($groupPerms->isNotEmpty())
                                    <div class="rounded-lg p-3" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                        <div class="text-xs font-semibold uppercase tracking-wider mb-2 flex items-center" style="color: {{ $group['color'] }};">
                                            <i class="fas {{ $group['icon'] }} mr-2"></i> {{ $group['label'] }}
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                            @foreach($groupPerms as $key => $perm)
                                                <label class="flex items-start gap-2 p-2 rounded cursor-pointer transition"
                                                       style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                                                    <input type="checkbox"
                                                           name="permissions[]"
                                                           value="{{ $key }}"
                                                           data-permission="{{ $key }}"
                                                           data-form="resubmitForm"
                                                           class="perm-checkbox mt-0.5 w-4 h-4 flex-shrink-0"
                                                           style="accent-color: var(--primary);">
                                                    <div class="flex-1 min-w-0">
                                                        <div class="text-sm font-medium flex items-center gap-1" style="color: var(--text-primary);">
                                                            <i class="fas {{ $perm['icon'] }} text-xs" style="color: {{ $group['color'] }};"></i>
                                                            {{ $perm['label'] }}
                                                        </div>
                                                        <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                                            {{ $perm['description'] }}
                                                        </div>
                                                    </div>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-5">
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Notes for Administrator (Optional)
                        </label>
                        <textarea name="landlord_notes" id="resubmitNotes" rows="3" maxlength="1000"
                                  class="index-custom-textarea w-full"
                                  placeholder="Add any context — e.g. what changed since the original rejection..."></textarea>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-lightbulb mr-1"></i>
                            Explaining what you fixed makes review faster.
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" onclick="hideResubmitModal()"
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit"
                            class="btn-primary px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-redo mr-2"></i> Resubmit Proposal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    autoHideMessages();
    initTooltips();
    initAddMemberModal();
    initResubmitModal();

    // Preset permission buttons
    document.querySelectorAll('.preset-permission-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const formId = this.getAttribute('data-target-form');
            const permissions = (this.getAttribute('data-permissions') || '').split(',').filter(Boolean);

            document.querySelectorAll(`.perm-checkbox[data-form="${formId}"]`).forEach(cb => {
                cb.checked = permissions.includes(cb.value);
            });

            const siblings = this.parentElement.querySelectorAll('.preset-permission-btn');
            siblings.forEach(s => {
                s.style.backgroundColor = 'rgba(var(--secondary-rgb), 0.1)';
                s.style.color = 'var(--secondary)';
            });
            this.style.backgroundColor = 'var(--primary)';
            this.style.color = 'white';
        });
    });

    // Bind forms
    bindForm('addMemberForm', submitAddMember);
    bindForm('confirmForm', () => submitForm('confirmForm', 'confirm'));
    bindForm('cancelForm',  () => submitForm('cancelForm',  'cancel'));
    bindForm('revokeForm',  () => submitForm('revokeForm',  'revoke'));
    bindForm('editPermissionsForm', () => submitForm('editPermissionsForm', 'editPermissions'));
    bindForm('resubmitForm', () => submitForm('resubmitForm', 'resubmit'));
});

function bindForm(id, handler) {
    const form = document.getElementById(id);
    if (form) form.addEventListener('submit', function (e) {
        e.preventDefault();
        handler(form);
    });
}

// ── Row lookup helper ──
function rowFor(id) {
    return document.querySelector(`tr[data-link-id="${id}"]`);
}
function rowName(id) {
    return rowFor(id)?.getAttribute('data-member-name') || 'this member';
}

// ── Reason disclosure toggle ──
function toggleReason(id) {
    const full  = document.getElementById(`${id}-full`);
    const icon  = document.getElementById(`${id}-icon`);
    const short = document.getElementById(`${id}-short`);
    if (!full) return;

    const isHidden = full.classList.contains('hidden');
    full.classList.toggle('hidden', !isHidden);
    if (short) short.classList.toggle('hidden', isHidden);
    if (icon)  icon.style.transform = isHidden ? 'rotate(180deg)' : '';
}

// ─────────────────────────────────────────────────────────────
// ADD MEMBER MODAL — dual-mode (new / existing) + multi-property
// ─────────────────────────────────────────────────────────────
function initAddMemberModal() {
    // Mode toggle
    document.querySelectorAll('.mode-tab').forEach(tab => {
        tab.addEventListener('click', function () {
            if (this.disabled) return;
            switchAddMemberMode(this.getAttribute('data-mode'));
        });
    });

    // Existing-member dropdown
    document.getElementById('existingUserId')?.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        if (!opt || !opt.value) return applyExistingMemberState(null);

        const alreadyLinked = (opt.getAttribute('data-properties') || '')
            .split(',')
            .filter(Boolean)
            .map(String);

        applyExistingMemberState(alreadyLinked);
    });

    // Select-all toggle
    document.getElementById('toggleAllProperties')?.addEventListener('click', function () {
        const checkboxes = Array.from(
            document.querySelectorAll('.property-checkbox:not([disabled])')
        );
        const allChecked = checkboxes.length > 0 && checkboxes.every(cb => cb.checked);
        checkboxes.forEach(cb => { cb.checked = !allChecked; });
        syncToggleAllLabel();
    });

    // Keep the toggle label in sync when individual checkboxes change
    document.addEventListener('change', function (e) {
        if (!e.target.classList.contains('property-checkbox')) return;
        syncToggleAllLabel();
    });

    // Relationship "other" toggle
    const relSelect = document.getElementById('relationshipSelect');
    if (relSelect) {
        relSelect.addEventListener('change', function () {
            const wrap = document.getElementById('relationshipOtherWrap');
            if (this.value === 'other') {
                wrap.classList.remove('hidden');
            } else {
                wrap.classList.add('hidden');
            }
        });
    }
}

function switchAddMemberMode(mode) {
    document.getElementById('linkMode').value = mode;

    document.querySelectorAll('.mode-tab').forEach(t => {
        t.style.backgroundColor = 'transparent';
        t.style.color = 'var(--text-secondary)';
    });
    const activeTab = document.querySelector(`.mode-tab[data-mode="${mode}"]`);
    if (activeTab) {
        activeTab.style.backgroundColor = 'var(--primary)';
        activeTab.style.color = 'white';
    }

    const newDetails   = document.getElementById('newMemberDetails');
    const existingWrap = document.getElementById('existingMemberWrap');
    const submitBtn    = document.getElementById('addMemberSubmitBtn');

    if (mode === 'existing') {
        newDetails.classList.add('hidden');
        existingWrap.classList.remove('hidden');

        // Prevent hidden required inputs from blocking submit
        newDetails.querySelectorAll('[required]').forEach(el => {
            el.dataset.wasRequired = '1';
            el.required = false;
        });

        document.getElementById('existingUserId').required = true;
        submitBtn.innerHTML = '<i class="fas fa-link mr-2"></i> Add to Properties';

        // Reapply state for whatever is currently selected
        const sel = document.getElementById('existingUserId');
        if (sel && sel.value) {
            const opt = sel.options[sel.selectedIndex];
            const ids = (opt.getAttribute('data-properties') || '').split(',').filter(Boolean).map(String);
            applyExistingMemberState(ids);
        } else {
            applyExistingMemberState(null);
        }
    } else {
        newDetails.classList.remove('hidden');
        existingWrap.classList.add('hidden');

        newDetails.querySelectorAll('[data-was-required="1"]').forEach(el => {
            el.required = true;
            delete el.dataset.wasRequired;
        });

        document.getElementById('existingUserId').required = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i> Submit Proposal';

        applyExistingMemberState(null);
    }
}

function applyExistingMemberState(alreadyLinkedIds) {
    const set = new Set((alreadyLinkedIds || []).map(String));

    document.querySelectorAll('.property-checkbox-label').forEach(label => {
        const pid = String(label.getAttribute('data-property-id'));
        const cb  = label.querySelector('.property-checkbox');
        const bdg = label.querySelector('.already-linked-badge');

        if (set.has(pid)) {
            cb.checked  = true;
            cb.disabled = true;
            label.style.opacity = '0.6';
            label.style.cursor  = 'not-allowed';
            bdg?.classList.remove('hidden');
        } else {
            cb.disabled = false;
            label.style.opacity = '1';
            label.style.cursor  = 'pointer';
            bdg?.classList.add('hidden');
        }
    });

    syncToggleAllLabel();
}

function syncToggleAllLabel() {
    const checkboxes = Array.from(
        document.querySelectorAll('.property-checkbox:not([disabled])')
    );
    const allChecked = checkboxes.length > 0 && checkboxes.every(cb => cb.checked);
    const label = document.getElementById('toggleAllLabel');
    if (label) label.textContent = allChecked ? 'Unselect all' : 'Select all';
}

// ── Add member ──
function showAddMemberModal() {
    const form = document.getElementById('addMemberForm');
    form.reset();

    // Reset mode to "new"
    switchAddMemberMode('new');

    // Reset all property checkboxes and badges
    document.querySelectorAll('.property-checkbox').forEach(cb => {
        cb.checked = false;
        cb.disabled = false;
        const wrap = cb.closest('.property-checkbox-label');
        if (wrap) {
            wrap.style.opacity = '1';
            wrap.style.cursor = 'pointer';
        }
    });
    document.querySelectorAll('.already-linked-badge').forEach(b => b.classList.add('hidden'));

    // Clear the existing-member dropdown (without firing change)
    const sel = document.getElementById('existingUserId');
    if (sel) {
        sel.value = '';
        applyExistingMemberState(null);
    }

    document.getElementById('relationshipOtherWrap').classList.add('hidden');
    syncToggleAllLabel();

    document.getElementById('addMemberModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideAddMemberModal() {
    document.getElementById('addMemberModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// ── Confirm ──
function showConfirmModal(id) {
    document.getElementById('confirmForm').action = `/landlord/family-links/${id}/confirm`;
    document.getElementById('confirmMemberName').textContent = rowName(id);
    document.getElementById('confirmModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function hideConfirmModal() {
    document.getElementById('confirmModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// ── Cancel ──
function showCancelModal(id) {
    document.getElementById('cancelForm').action = `/landlord/family-links/${id}`;
    document.getElementById('cancelMemberName').textContent = rowName(id);
    document.getElementById('cancelModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function hideCancelModal() {
    document.getElementById('cancelModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// ── Revoke ──
function showRevokeModal(id) {
    document.getElementById('revokeForm').action = `/landlord/family-links/${id}/revoke`;
    document.getElementById('revokeMemberName').textContent = rowName(id);
    document.getElementById('revokeModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function hideRevokeModal() {
    document.getElementById('revokeModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// ── Edit permissions ──
function showEditPermissionsModal(id) {
    const row = rowFor(id);
    if (!row) return;

    document.getElementById('editPermissionsForm').action = `/landlord/family-links/${id}/permissions`;
    document.getElementById('editPermMemberName').textContent = rowName(id);

    const currentPerms = (row.getAttribute('data-permissions') || '').split(',').filter(Boolean);
    document.querySelectorAll('.perm-checkbox[data-form="editPermissionsForm"]').forEach(cb => {
        cb.checked = currentPerms.includes(cb.value);
    });

    document.querySelectorAll('#editPermissionsModal .preset-permission-btn').forEach(s => {
        s.style.backgroundColor = 'rgba(var(--secondary-rgb), 0.1)';
        s.style.color = 'var(--secondary)';
    });

    document.getElementById('editPermissionsModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function hideEditPermissionsModal() {
    document.getElementById('editPermissionsModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// ─────────────────────────────────────────────────────────────
// RESUBMIT MODAL
// ─────────────────────────────────────────────────────────────
function initResubmitModal() {
    // Nothing special needed — setup happens in showResubmitModal.
}

function showResubmitModal(id) {
    const row = rowFor(id);
    if (!row) return;

    const propId = row.getAttribute('data-property-id') || '';

    document.getElementById('resubmitForm').action = "{{ route('landlord.family-links.store') }}";
    document.getElementById('resubmitFromId').value     = id;
    document.getElementById('resubmitPropertyId').value = propId;
    document.getElementById('resubmitPropertyIds').value = propId;

    document.getElementById('resubmitName').value  = row.getAttribute('data-member-name')   || '';
    document.getElementById('resubmitPhone').value = row.getAttribute('data-phone')         || '';
    document.getElementById('resubmitEmail').value = row.getAttribute('data-email')         || '';
    document.getElementById('resubmitNotes').value = row.getAttribute('data-landlord-notes')|| '';

    // Relationship select
    const rel = row.getAttribute('data-relationship') || '';
    const relSelect = document.getElementById('resubmitRelationship');
    let matched = false;
    for (const opt of relSelect.options) {
        if (opt.value === rel) { opt.selected = true; matched = true; break; }
    }
    if (!matched) {
        for (const opt of relSelect.options) {
            if (opt.text.trim().toLowerCase() === rel.toLowerCase()) { opt.selected = true; break; }
        }
    }

    // Permissions
    const perms = (row.getAttribute('data-permissions') || '').split(',').filter(Boolean);
    document.querySelectorAll('.perm-checkbox[data-form="resubmitForm"]').forEach(cb => {
        cb.checked = perms.includes(cb.value);
    });

    document.querySelectorAll('#resubmitModal .preset-permission-btn').forEach(s => {
        s.style.backgroundColor = 'rgba(var(--secondary-rgb), 0.1)';
        s.style.color = 'var(--secondary)';
    });

    // Rejection reason banner
    const reasonText = (row.getAttribute('data-rejection-reason') || '').trim();
    const banner     = document.getElementById('resubmitReasonBanner');
    const reasonBox  = document.getElementById('resubmitReasonText');
    if (reasonText) {
        reasonBox.textContent = reasonText;
        banner.classList.remove('hidden');
    } else {
        reasonBox.textContent = '';
        banner.classList.add('hidden');
    }

    document.getElementById('resubmitModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('resubmitName').focus(), 100);
}
function hideResubmitModal() {
    document.getElementById('resubmitModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// ─────────────────────────────────────────────────────────────
// SUBMIT HANDLERS
// ─────────────────────────────────────────────────────────────

function submitAddMember(form) {
    const mode = document.getElementById('linkMode').value;

    const selectedProperties = form.querySelectorAll('.property-checkbox:checked');
    if (selectedProperties.length === 0) {
        return showNotification('error', 'Select at least one property.');
    }

    if (mode === 'existing' && !document.getElementById('existingUserId').value) {
        return showNotification('error', 'Select an existing family member.');
    }

    const fd = new FormData(form);

    if (mode === 'existing') {
        ['proposed_name', 'proposed_phone', 'proposed_email',
         'relationship', 'relationship_other']
            .forEach(k => fd.delete(k));
    }

    const btn = form.querySelector('button[type="submit"]');
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...';
    btn.disabled = true;

    fetch(form.action, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: fd
    })
    .then(r => r.json().then(data => ({ status: r.status, data })))
    .then(({ data }) => {
        if (data.success) {
            showNotification('success', data.message || 'Submitted.');
            hideAddMemberModal();
            setTimeout(() => window.location.reload(), 1200);
        } else {
            let msg = data.message || 'Error submitting.';
            if (data.errors) {
                const first = Object.values(data.errors)[0];
                if (Array.isArray(first)) msg = first[0];
            }
            showNotification('error', msg);
            btn.innerHTML = original;
            btn.disabled = false;
        }
    })
    .catch(() => {
        showNotification('error', 'Request failed.');
        btn.innerHTML = original;
        btn.disabled = false;
    });
}

function submitForm(formId, action) {
    const form = document.getElementById(formId);
    const btn = form.querySelector('button[type="submit"]');
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    btn.disabled = true;

    fetch(form.action, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new FormData(form)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message || 'Action completed.');
            if (action === 'confirm')         hideConfirmModal();
            if (action === 'cancel')          hideCancelModal();
            if (action === 'revoke')          hideRevokeModal();
            if (action === 'editPermissions') hideEditPermissionsModal();
            if (action === 'resubmit')        hideResubmitModal();
            setTimeout(() => window.location.reload(), 1200);
        } else {
            let msg = data.message || 'Error.';
            if (data.errors) {
                const first = Object.values(data.errors)[0];
                if (Array.isArray(first)) msg = first[0];
            }
            showNotification('error', msg);
            btn.innerHTML = original;
            btn.disabled = false;
        }
    })
    .catch(() => {
        showNotification('error', 'Request failed.');
        btn.innerHTML = original;
        btn.disabled = false;
    });
}

// ── Helpers ──
function escapeHtml(t) {
    const d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
}

function showNotification(type, message) {
    const c = document.getElementById('notificationContainer');
    if (!c) return;
    c.innerHTML = '';
    const n = document.createElement('div');
    n.className = 'mb-4 p-4 rounded-lg shadow-lg';
    n.style.backgroundColor = type === 'success' ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--danger-rgb), 0.1)';
    n.style.border = type === 'success' ? '1px solid rgba(var(--success-rgb), 0.3)' : '1px solid rgba(var(--danger-rgb), 0.3)';
    n.innerHTML = `
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"
                   style="color: ${type === 'success' ? 'var(--success)' : 'var(--danger)'};"></i>
                <span style="color: ${type === 'success' ? 'var(--success)' : 'var(--danger)'};">${escapeHtml(message)}</span>
            </div>
            <button type="button" onclick="this.parentElement.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>`;
    c.appendChild(n);
    setTimeout(() => n.remove(), 5000);
}

function autoHideMessages() {
    setTimeout(() => {
        document.querySelectorAll('.success-message, .error-message').forEach(m => m.style.display = 'none');
    }, 5000);
}

function initTooltips() {
    document.querySelectorAll('[data-tooltip]').forEach(el => {
        el.addEventListener('mouseenter', function () {
            const tip = document.createElement('div');
            tip.className = 'tooltip';
            tip.textContent = this.getAttribute('data-tooltip');
            tip.style.cssText = `
                position: absolute; background: var(--text-primary); color: var(--card-bg);
                padding: 4px 8px; border-radius: 4px; font-size: 12px; z-index: 1000; white-space: nowrap;`;
            document.body.appendChild(tip);
            const r = this.getBoundingClientRect();
            tip.style.left = r.left + (r.width / 2) - (tip.offsetWidth / 2) + 'px';
            tip.style.top  = r.top - tip.offsetHeight - 5 + 'px';
            this._tip = tip;
        });
        el.addEventListener('mouseleave', function () {
            if (this._tip) { this._tip.remove(); this._tip = null; }
        });
    });
}
</script>

<style>
.index-custom-input,
.index-custom-dropdown,
.index-custom-textarea {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
}
.index-custom-input:focus,
.index-custom-dropdown:focus,
.index-custom-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.action-btn {
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    border: 1px solid transparent;
    cursor: pointer;
}
.action-btn.view   { background-color: rgba(var(--info-rgb), 0.1);    color: var(--info);    border-color: rgba(var(--info-rgb), 0.3); }
.action-btn.assign { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border-color: rgba(var(--success-rgb), 0.3); }
.action-btn.delete { background-color: rgba(var(--danger-rgb), 0.1);  color: var(--danger);  border-color: rgba(var(--danger-rgb), 0.3); }
.action-btn:hover { transform: translateY(-1px); }

.modal-container {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    max-height: 90vh;
    overflow-y: auto;
    border-radius: 16px;
}
.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    background: var(--card-bg);
    z-index: 5;
}
.modal-body { padding: 1.5rem; }
.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    position: sticky;
    bottom: 0;
    background: var(--card-bg);
}
.modal-close-btn {
    background: none; border: none; cursor: pointer;
    font-size: 1.25rem; padding: 0.5rem; border-radius: 50%;
}

.preset-permission-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

.property-checkbox-label:hover:not([style*="not-allowed"]) {
    box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.15);
}

.badge-success   { background-color: rgba(var(--success-rgb), 0.1) !important; color: var(--success) !important; border: 1px solid rgba(var(--success-rgb), 0.3) !important; }
.badge-warning   { background-color: rgba(var(--warning-rgb), 0.1) !important; color: var(--warning) !important; border: 1px solid rgba(var(--warning-rgb), 0.3) !important; }
.badge-danger    { background-color: rgba(var(--danger-rgb), 0.1) !important;  color: var(--danger) !important;  border: 1px solid rgba(var(--danger-rgb), 0.3) !important; }
.badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1) !important; color: var(--secondary) !important; border: 1px solid rgba(var(--secondary-rgb), 0.3) !important; }
.badge-primary   { background-color: rgba(var(--primary-rgb), 0.1) !important; color: var(--primary) !important; border: 1px solid rgba(var(--primary-rgb), 0.3) !important; }

.btn-primary   { background-color: var(--primary) !important; color: white !important; border: 1px solid var(--primary) !important; }
.btn-primary:hover { background-color: var(--secondary) !important; border-color: var(--secondary) !important; }
.btn-secondary { background-color: rgba(var(--secondary-rgb), 0.1) !important; color: var(--secondary) !important; border: 1px solid rgba(var(--secondary-rgb), 0.3) !important; }
.btn-danger    { background-color: var(--danger) !important; color: white !important; border: 1px solid var(--danger) !important; }
.btn-danger:hover { background-color: #c82333 !important; }

table { border-collapse: separate; border-spacing: 0; width: 100%; }
table th {
    font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;
    font-size: 0.75rem; padding: 0.75rem;
    border-bottom: 2px solid var(--border-color);
    background-color: var(--bg-secondary) !important;
}
table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: top;
    background-color: var(--card-bg) !important;
}

button:disabled { opacity: 0.6; cursor: not-allowed; }
.tooltip { pointer-events: none; }

@media (max-width: 768px) {
    .action-btn { padding: 0.25rem 0.5rem; }
    .modal-container { margin: 1rem; }
}
</style>
@endpush
@endsection