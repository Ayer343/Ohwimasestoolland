{{-- landlord/family-links/for-property.blade.php --}}
@php
    use App\Models\PropertyFamilyLink;

    $landlord = auth()->user();

    $pageTitle = 'Family Links — ' . ($property->property_name ?? 'Property');

    $successMessage = session('success');
    $errorMessage = session('error');

    $pendingCount  = $links->where('status', 'pending')->count();
    $approvedCount = $links->where('status', 'approved')->count();
    $rejectedCount = $links->where('status', 'rejected')->count();
    $revokedCount  = $links->where('status', 'revoked')->count();

    $maxLinks = config('property_family_links.max_links_per_property', 5);
    $activeLinkCount = $pendingCount + $approvedCount;
    $remainingSlots = max(0, $maxLinks - $activeLinkCount);
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
                        <span class="text-sm font-normal px-2 py-1 rounded-full badge-secondary">
                            {{ $activeLinkCount }} / {{ $maxLinks }}
                        </span>
                    </h2>
                    <div class="text-sm flex items-center flex-wrap gap-2 mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-building mr-1"></i>
                        <span>{{ $property->property_name }}</span>
                        @if($property->digital_address)
                            <span class="mx-1">•</span>
                            <i class="fas fa-map-marker-alt mr-1"></i>
                            <span>{{ $property->digital_address }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('landlord.properties.show', $property->id) }}"
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Property
                </a>
                <a href="{{ route('landlord.family-links.index') }}"
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-list mr-1"></i> All Family Links
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
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Pending</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($pendingCount) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-clock text-lg" style="color: var(--warning);"></i>
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
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Slots Remaining</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $remainingSlots }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1);">
                        <i class="fas fa-user-plus text-lg" style="color: var(--info);"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================
         ACTION BAR
         ============================================================ --}}
    <div class="card">
        <div class="p-4 flex justify-between items-center flex-wrap gap-3">
            <div class="flex items-center gap-2">
                <span class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1"></i>
                    Proposals require admin approval before they take effect.
                </span>
            </div>
            @if($remainingSlots > 0)
            <button type="button" onclick="showAddMemberModal()"
                    class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                <i class="fas fa-user-plus mr-2"></i> Add Family Member
            </button>
            @else
            <span class="text-sm px-3 py-2 rounded-lg"
                  style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                Maximum of {{ $maxLinks }} family members reached.
            </span>
            @endif
        </div>
    </div>

    {{-- ============================================================
         LINKS TABLE
         ============================================================ --}}
    <div class="card p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-list mr-2" style="color: var(--primary);"></i>
                Linked Family Members
            </h3>
        </div>

        @if($links->isEmpty())
            <div class="text-center py-12">
                <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4"
                     style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-users text-2xl" style="color: var(--primary);"></i>
                </div>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No family members linked yet</h4>
                <p class="text-sm mb-4" style="color: var(--text-secondary);">
                    Add a family member to give them access to this property.
                </p>
                <button type="button" onclick="showAddMemberModal()"
                        class="inline-flex items-center btn-primary px-4 py-2 rounded-lg font-medium text-white">
                    <i class="fas fa-user-plus mr-2"></i> Add First Member
                </button>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px]" id="linksTable">
                    <thead>
                        <tr>
                            <th class="text-left p-3 font-medium text-xs uppercase tracking-wider"
                                style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 200px;">
                                Member
                            </th>
                            <th class="text-left p-3 font-medium text-xs uppercase tracking-wider"
                                style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 140px;">
                                Relationship
                            </th>
                            <th class="text-left p-3 font-medium text-xs uppercase tracking-wider"
                                style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 220px;">
                                Permissions
                            </th>
                            <th class="text-left p-3 font-medium text-xs uppercase tracking-wider"
                                style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 120px;">
                                Status
                            </th>
                            <th class="text-left p-3 font-medium text-xs uppercase tracking-wider"
                                style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 140px;">
                                Added
                            </th>
                            <th class="text-left p-3 font-medium text-xs uppercase tracking-wider"
                                style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 140px;">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($links as $link)
                        @php
                            $statusConfigs = [
                                'pending'  => ['class' => 'badge-warning',   'icon' => 'clock',        'text' => 'Pending'],
                                'approved' => ['class' => 'badge-success',   'icon' => 'check-circle', 'text' => 'Approved'],
                                'rejected' => ['class' => 'badge-danger',    'icon' => 'times-circle', 'text' => 'Rejected'],
                                'revoked'  => ['class' => 'badge-secondary', 'icon' => 'ban',          'text' => 'Revoked'],
                            ];
                            $sc = $statusConfigs[$link->status] ?? ['class' => 'badge-secondary', 'icon' => 'question-circle', 'text' => ucfirst($link->status)];
                            $granted = $link->permissions ?? [];
                        @endphp
                        <tr data-link-id="{{ $link->id }}"
                            data-status="{{ $link->status }}"
                            data-member-name="{{ addslashes($link->display_name) }}">

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
                                            {{ $link->display_name }}
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

                            {{-- Relationship --}}
                            <td class="p-3 align-top">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                      style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                    <i class="fas fa-heart mr-1"></i> {{ $link->relationship_label }}
                                </span>
                            </td>

                            {{-- Permissions --}}
                            <td class="p-3 align-top">
                                <div class="flex flex-wrap gap-1">
                                    @forelse($granted as $perm)
                                        <span class="text-xs px-2 py-0.5 rounded-full"
                                              style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                            {{ $permissions[$perm] ?? $perm }}
                                        </span>
                                    @empty
                                        <span class="text-xs" style="color: var(--text-secondary);">—</span>
                                    @endforelse
                                </div>
                            </td>

                            {{-- Status --}}
                            <td class="p-3 align-top">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $sc['class'] }}">
                                    <i class="fas fa-{{ $sc['icon'] }} mr-1 text-xs"></i> {{ $sc['text'] }}
                                </span>
                                @if($link->isPending())
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                    Awaiting admin review
                                </div>
                                @endif
                                @if($link->admin_notes && in_array($link->status, ['rejected', 'revoked']))
                                <div class="text-xs mt-1" style="color: var(--danger);">
                                    <i class="fas fa-comment mr-0.5"></i> {{ Str::limit($link->admin_notes, 40) }}
                                </div>
                                @endif
                            </td>

                            {{-- Added --}}
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
                                    @if($link->isPending())
                                        <button type="button"
                                                onclick="showCancelModal('{{ $link->id }}', '{{ addslashes($link->display_name) }}')"
                                                class="action-btn delete" data-tooltip="Cancel Proposal">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    @endif

                                    @if($link->isApproved())
                                        <button type="button"
                                                onclick="showRevokeModal('{{ $link->id }}', '{{ addslashes($link->display_name) }}')"
                                                class="action-btn delete" data-tooltip="Revoke Access">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    @endif

                                    @if(!$link->isPending() && !$link->isApproved())
                                        <span class="text-xs px-2 py-1 rounded" style="color: var(--text-secondary);">
                                            No actions available
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- ============================================================
     ADD MEMBER MODAL
     ============================================================ --}}
<div id="addMemberModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideAddMemberModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-2xl">
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
                <input type="hidden" name="property_id" value="{{ $property->id }}">

                <div class="modal-body">
                    <div class="rounded-lg p-3 mb-4"
                         style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--info);"></i>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                Your proposal will be reviewed by an administrator before the family member gains access.
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
                                @foreach($relationships as $rel)
                                    <option value="{{ $rel }}">{{ ucfirst($rel) }}</option>
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

                    <div class="mt-5">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            <i class="fas fa-key mr-1"></i> Permissions
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            @php $defaults = config('property_family_links.default_permissions', ['view']); @endphp
                            @foreach($permissions as $key => $label)
                            <div class="flex items-center p-2 rounded"
                                 style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <input type="checkbox" name="permissions[]" value="{{ $key }}"
                                       id="perm-{{ $key }}"
                                       class="mr-2 w-4 h-4" style="accent-color: var(--primary);"
                                       {{ in_array($key, $defaults) ? 'checked' : '' }}>
                                <label for="perm-{{ $key }}" class="text-sm cursor-pointer" style="color: var(--text-primary);">
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
                        <textarea name="landlord_notes" rows="3" maxlength="1000"
                                  class="index-custom-textarea w-full"
                                  placeholder="Any context for the admin reviewing this proposal..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" onclick="hideAddMemberModal()"
                            class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit"
                            class="btn-primary px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-paper-plane mr-2"></i> Submit Proposal
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    autoHideMessages();
    initTooltips();

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

    bindForm('addMemberForm', submitAddMember);
    bindForm('cancelForm', () => submitForm('cancelForm', 'cancel'));
    bindForm('revokeForm', () => submitForm('revokeForm', 'revoke'));
});

function bindForm(id, handler) {
    const form = document.getElementById(id);
    if (form) form.addEventListener('submit', function (e) {
        e.preventDefault();
        handler(form);
    });
}

function showAddMemberModal() {
    const form = document.getElementById('addMemberForm');
    form.reset();
    document.getElementById('relationshipOtherWrap').classList.add('hidden');
    document.getElementById('addMemberModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function hideAddMemberModal() {
    document.getElementById('addMemberModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showCancelModal(id, name) {
    document.getElementById('cancelForm').action = `/landlord/family-links/${id}`;
    document.getElementById('cancelMemberName').textContent = name;
    document.getElementById('cancelModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function hideCancelModal() {
    document.getElementById('cancelModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showRevokeModal(id, name) {
    document.getElementById('revokeForm').action = `/landlord/family-links/${id}/revoke`;
    document.getElementById('revokeMemberName').textContent = name;
    document.getElementById('revokeModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function hideRevokeModal() {
    document.getElementById('revokeModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function submitAddMember(form) {
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
        body: new FormData(form)
    })
    .then(r => r.json().then(data => ({ status: r.status, data })))
    .then(({ status, data }) => {
        if (data.success) {
            showNotification('success', data.message || 'Proposal submitted.');
            hideAddMemberModal();
            setTimeout(() => window.location.reload(), 1200);
        } else {
            // Surface validation errors if present
            let msg = data.message || 'Error submitting proposal.';
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
            if (action === 'cancel') hideCancelModal();
            if (action === 'revoke') hideRevokeModal();
            setTimeout(() => window.location.reload(), 1200);
        } else {
            showNotification('error', data.message || 'Error.');
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
.action-btn.view {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
    border-color: rgba(var(--info-rgb), 0.3);
}
.action-btn.delete {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
    border-color: rgba(var(--danger-rgb), 0.3);
}
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