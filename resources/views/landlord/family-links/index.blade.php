{{-- landlord/family-links/index.blade.php --}}
@php
    use App\Models\PropertyFamilyLink;

    $landlord = auth()->user();

    $pageTitle = 'Family Links';

    $successMessage = session('success');
    $errorMessage   = session('error');

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

    // Permission labels + relationship options for the resubmit modal
    $permissionLabels    = config('property_family_links.permissions', []);
    $relationshipOptions = config('property_family_links.relationship_options', []);

    // The "current property" context for the per-property page. On the
    // index page this is null; on for-property it's the Property model.
    $currentPropertyId = isset($property) ? (int) $property->id : null;
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
                        <span>Manage family members linked to your properties</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-chart-bar mr-1"></i>
                        <span>{{ number_format($totalCount) }} total</span>
                        @if(!empty($properties) && $properties->count() > 0)
                            <span class="mx-1">•</span>
                            <i class="fas fa-building mr-1"></i>
                            <span>{{ $properties->count() }} {{ Str::plural('property', $properties->count()) }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
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

                    <form method="GET" action="{{ route('landlord.family-links.index') }}" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-tag mr-1"></i> Status
                            </label>
                            <select name="status" class="index-custom-dropdown w-full">
                                <option value="">All Status</option>
                                <option value="{{ PropertyFamilyLink::STATUS_PENDING_LANDLORD }}" {{ request('status') == PropertyFamilyLink::STATUS_PENDING_LANDLORD ? 'selected' : '' }}>Awaiting Your Confirmation</option>
                                <option value="{{ PropertyFamilyLink::STATUS_PENDING_ADMIN }}"    {{ request('status') == PropertyFamilyLink::STATUS_PENDING_ADMIN    ? 'selected' : '' }}>Awaiting Admin Review</option>
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
                                <a href="{{ route('landlord.family-links.index') }}"
                                   class="block w-full text-center btn-secondary px-3 py-2 rounded-lg font-medium">
                                    <i class="fas fa-redo mr-2"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
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
                            @if($currentStatus)
                                <span class="text-sm font-normal ml-2 px-2 py-1 rounded-full badge-primary">
                                    {{ data_get([
                                        PropertyFamilyLink::STATUS_PENDING_LANDLORD => 'Awaiting Your Confirmation',
                                        PropertyFamilyLink::STATUS_PENDING_ADMIN    => 'Awaiting Admin Review',
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
                    <div class="flex items-center space-x-3 mt-4 md:mt-0">
                        <a href="{{ route('landlord.properties.index') }}"
                           class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-plus mr-2"></i> Manage Property Links
                        </a>
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
                            Open one of your properties to add a family member.
                        </p>
                        <a href="{{ route('landlord.properties.index') }}"
                           class="inline-flex items-center btn-primary px-4 py-2 rounded-lg font-medium text-white">
                            <i class="fas fa-building mr-2"></i> View My Properties
                        </a>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[960px]" id="linksTable">
                            <thead>
                                <tr>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider"
                                        style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 200px;">
                                        Member
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider"
                                        style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 200px;">
                                        Property
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider"
                                        style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 140px;">
                                        Relationship
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider"
                                        style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 200px;">
                                        Status
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider"
                                        style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 140px;">
                                        Submitted
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
                                    data-rejection-reason="{{ $hasReason ? $link->admin_notes : '' }}">

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
                                                <div class="font-semibold text-sm" style="color: var(--text-primary);">
                                                    {{ $memberName }}
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

                                        {{-- Rejection reason disclosure --}}
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
                                                        Fix the issue and use <strong>Edit &amp; Resubmit</strong> below to update this proposal.
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Submitted --}}
                                    <td class="p-3 align-top">
                                        <div class="text-sm" style="color: var(--text-primary);">
                                            {{ $link->created_at->format('M j, Y') }}
                                        </div>
                                        <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                            <i class="fas fa-clock mr-1"></i> {{ $link->created_at->diffForHumans() }}
                                        </div>
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
                                                        onclick="showRevokeModal({{ $link->id }})"
                                                        class="action-btn delete" data-tooltip="Revoke Access">
                                                    <i class="fas fa-ban"></i>
                                                </button>

                                            @elseif($link->status === PropertyFamilyLink::STATUS_REJECTED)
                                                {{-- Edit & Resubmit — updates THIS row in place --}}
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
     CONFIRM MODAL
     ============================================================ --}}
<div id="confirmModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideConfirmModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-check-circle mr-2" style="color: var(--primary);"></i> Confirm Proposal
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
                         style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--info);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                Once confirmed, your proposal is sent to administrators for review. You will be notified of their decision, and the family member will be invited only after admin approval.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideConfirmModal()"
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit"
                            class="btn-primary px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-check mr-2"></i> Confirm Proposal
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
                                The pending proposal will be withdrawn. You can submit a new one at any time.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideCancelModal()"
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">Keep Proposal</button>
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
                                The family member will <strong>immediately lose access</strong> to this property. Admins will be notified.
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
     RESUBMIT MODAL — updates the EXISTING proposal in place
     ============================================================ --}}
<div id="resubmitModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideResubmitModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-2xl">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-redo mr-2" style="color: var(--primary);"></i>
                    Edit &amp; Resubmit Proposal
                </h3>
                <button type="button" onclick="hideResubmitModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="resubmitForm" method="POST" action="">
                @csrf
                {{-- Edit in place: the same row id is submitted back --}}
                <input type="hidden" name="resubmit_from" id="resubmitFromId" value="">
                <input type="hidden" name="property_id"   id="resubmitPropertyId" value="">

                <div class="modal-body">
                    {{-- Show the original rejection reason so the landlord knows what to fix --}}
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
                                The previous rejection reason is preserved in the audit log for reference.
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

                    <div class="mt-5">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            <i class="fas fa-key mr-1"></i> Permissions
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            @foreach($permissionLabels as $key => $label)
                            <div class="flex items-center p-2 rounded"
                                 style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <input type="checkbox" name="permissions[]" value="{{ $key }}"
                                       id="resubmit-perm-{{ $key }}"
                                       class="mr-2 w-4 h-4" style="accent-color: var(--primary);">
                                <label for="resubmit-perm-{{ $key }}" class="text-sm cursor-pointer" style="color: var(--text-primary);">
                                    {{ $label }}
                                </label>
                            </div>
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

    bindForm('confirmForm',  () => submitForm('confirmForm',  'confirm'));
    bindForm('cancelForm',   () => submitForm('cancelForm',   'cancel'));
    bindForm('revokeForm',   () => submitForm('revokeForm',   'revoke'));
    bindForm('resubmitForm', () => submitForm('resubmitForm', 'resubmit'));
});

function bindForm(id, handler) {
    const form = document.getElementById(id);
    if (form) form.addEventListener('submit', function (e) {
        e.preventDefault();
        handler();
    });
}

/* ---------- Row helpers ---------- */
function rowFor(id) {
    return document.querySelector(`tr[data-link-id="${id}"]`);
}
function rowName(id) {
    return rowFor(id)?.getAttribute('data-member-name') || 'this member';
}

/* ---------- Reason disclosure toggle ---------- */
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

/* ---------- CONFIRM ---------- */
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

/* ---------- CANCEL ---------- */
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

/* ---------- REVOKE ---------- */
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

/* ---------- RESUBMIT (edit in place) ---------- */
function showResubmitModal(id) {
    const row = rowFor(id);
    if (!row) return;

    // IMPORTANT: the form posts to the same store endpoint, but the
    // `resubmit_from` hidden field tells the backend to UPDATE this
    // row in place rather than create a new one.
    document.getElementById('resubmitForm').action = "{{ route('landlord.family-links.store') }}";
    document.getElementById('resubmitFromId').value     = id;
    document.getElementById('resubmitPropertyId').value = row.getAttribute('data-property-id') || '';
    document.getElementById('resubmitName').value       = row.getAttribute('data-member-name') || '';
    document.getElementById('resubmitPhone').value      = row.getAttribute('data-phone')        || '';
    document.getElementById('resubmitEmail').value      = row.getAttribute('data-email')        || '';
    document.getElementById('resubmitNotes').value      = row.getAttribute('data-landlord-notes') || '';

    // Relationship select — match value first, then fall back to label
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

    // Permissions checkboxes — reset only the ones in the resubmit modal
    const perms = (row.getAttribute('data-permissions') || '').split(',').filter(Boolean);
    document.querySelectorAll('input[name="permissions[]"]').forEach(cb => {
        if (cb.id.startsWith('resubmit-perm-')) {
            cb.checked = perms.includes(cb.value);
        }
    });

    // Rejection reason banner — read straight from the row's data attribute
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

/* ---------- SHARED SUBMIT HANDLER ---------- */
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
    .then(r => r.json().then(data => ({ status: r.status, data })))
    .then(({ status, data }) => {
        if (data.success) {
            showNotification('success', data.message || 'Action completed.');
            if (action === 'confirm')  hideConfirmModal();
            if (action === 'cancel')   hideCancelModal();
            if (action === 'revoke')   hideRevokeModal();
            if (action === 'resubmit') hideResubmitModal();
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

/* ---------- Misc ---------- */
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
            tip.style.top = r.top - tip.offsetHeight - 5 + 'px';
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