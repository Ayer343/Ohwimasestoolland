{{-- resources/views/sanitation/properties/linked.blade.php --}}

@php
    $user    = auth()->user();
    $isAdmin = $user->isAdmin() || $user->isSuperAdmin();
    $layout  = $isAdmin ? 'layouts.app' : 'layouts.san';

    // -----------------------------------------------------------------
    // ✅ Determine whether the CURRENT user may LINK properties.
    // Mirrors SanitationController::canCurrentUserLinkProperties():
    //   - Admin / Super Admin → allowed
    //   - Sanitation supervisor (root OR sub) whose creator is an
    //     admin OR another supervisor → allowed
    //   - Everyone else → denied
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
@endphp

@extends($layout)

@section('title', 'Linked Properties')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-7xl mx-auto">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-link mr-2" style="color: var(--primary);"></i>
                    Linked Properties
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Properties linked to waste collection services
                </p>
            </div>
            <div class="flex items-center space-x-3">
                {{-- ✅ Show "Link New Property" only when permitted --}}
                @if($canLinkProperties)
                    <a href="{{ route('sanitation.properties.available') }}" class="btn-primary">
                        <i class="fas fa-plus mr-2"></i> Link New Property
                    </a>
                @endif
                <a href="{{ route('sanitation.dashboard') }}" class="btn-secondary">
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
                            can link new properties. You can still browse linked properties below.
                        </p>
                    </div>
                </div>
            </div>
        @endunless

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold" style="color: var(--text-primary);">{{ $stats['total'] }}</div>
                <div class="text-sm" style="color: var(--text-secondary);">Total Linked</div>
            </div>
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold text-yellow-500">{{ $stats['pending_collection'] }}</div>
                <div class="text-sm" style="color: var(--text-secondary);">Pending Collection</div>
            </div>
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold text-blue-500">{{ $stats['active'] }}</div>
                <div class="text-sm" style="color: var(--text-secondary);">Active Requests</div>
            </div>
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold text-green-500">{{ $stats['completed_today'] }}</div>
                <div class="text-sm" style="color: var(--text-secondary);">Completed Today</div>
            </div>
        </div>

        <!-- Properties Table -->
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead style="background-color: var(--bg-secondary);">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Property</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Landlord</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Location</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Active Requests</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" style="border-color: var(--border-color);">
                        @forelse($properties as $property)
                            <tr class="hover:bg-opacity-5" style="background-color: var(--bg-secondary);">
                                <td class="px-4 py-3">
                                    <div class="font-medium" style="color: var(--text-primary);">
                                        {{ $property->property_name }}
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ $property->propertyType->name ?? 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm" style="color: var(--text-primary);">
                                    {{ $property->landlord->name ?? 'N/A' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm" style="color: var(--text-primary);">
                                        {{ $property->digital_address ?? 'N/A' }}
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ $property->street_name }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-lg font-bold" style="color: var(--text-primary);">
                                        {{ $property->waste_collection_requests_count ?? 0 }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded text-xs font-medium
                                        @if($property->waste_collection_requests_count > 0) bg-blue-100 text-blue-600
                                        @else bg-gray-100 text-gray-600 @endif">
                                        {{ $property->waste_collection_requests_count > 0 ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('sanitation.properties.show', $property) }}"
                                       class="btn-secondary btn-sm">
                                        <i class="fas fa-eye mr-1"></i> View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center" style="color: var(--text-secondary);">
                                    <i class="fas fa-link text-4xl mb-3 block" style="color: var(--text-secondary); opacity: 0.3;"></i>
                                    <p>No linked properties found</p>

                                    {{-- ✅ Only show "Link a Property" CTA when permitted --}}
                                    @if($canLinkProperties)
                                        <a href="{{ route('sanitation.properties.available') }}" class="btn-primary mt-3 inline-block">
                                            <i class="fas fa-plus mr-2"></i> Link a Property
                                        </a>
                                    @else
                                        <p class="text-xs mt-2" style="color: var(--text-secondary); opacity: 0.8;">
                                            Linking rights are required to add new properties.
                                        </p>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($properties->hasPages())
                <div class="px-4 py-3 border-t" style="border-color: var(--border-color);">
                    {{ $properties->links() }}
                </div>
            @endif
        </div>

        {{-- Admin debug footer --}}
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