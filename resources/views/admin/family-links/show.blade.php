{{-- admin/family-links/show.blade.php --}}
@php
    use App\Models\PropertyFamilyLink;

    $layout = 'layouts.app';
    $pageTitle = 'Family Link Proposal — ' . $link->proposed_name;

    // ── Status presentation config ──
    $statusConfigs = [
        PropertyFamilyLink::STATUS_PENDING_ADMIN => [
            'class' => 'badge-warning',
            'icon'  => 'hourglass-half',
            'text'  => 'Awaiting Admin Review',
        ],
        PropertyFamilyLink::STATUS_PENDING_LANDLORD => [
            'class' => 'badge-primary',
            'icon'  => 'hand-pointer',
            'text'  => 'Awaiting Landlord Confirmation',
        ],
        PropertyFamilyLink::STATUS_PENDING => [
            'class' => 'badge-warning',
            'icon'  => 'clock',
            'text'  => 'Pending (Legacy)',
        ],
        PropertyFamilyLink::STATUS_APPROVED => [
            'class' => 'badge-success',
            'icon'  => 'check-circle',
            'text'  => 'Approved',
        ],
        PropertyFamilyLink::STATUS_REJECTED => [
            'class' => 'badge-danger',
            'icon'  => 'times-circle',
            'text'  => 'Rejected',
        ],
        PropertyFamilyLink::STATUS_REVOKED => [
            'class' => 'badge-secondary',
            'icon'  => 'ban',
            'text'  => 'Revoked',
        ],
        PropertyFamilyLink::STATUS_CANCELLED => [
            'class' => 'badge-secondary',
            'icon'  => 'times',
            'text'  => 'Cancelled',
        ],
    ];
    $sc = $statusConfigs[$link->status]
        ?? ['class' => 'badge-secondary', 'icon' => 'question-circle', 'text' => ucfirst($link->status)];

    // Can the admin act on this link right now?
    $canReview = $link->isAwaitingAdminReview()
              || $link->status === PropertyFamilyLink::STATUS_PENDING;

    $canRevoke = $link->isApproved();
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6">

    {{-- ============================================================
         HEADER
         ============================================================ --}}
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-4">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-user-check text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center flex-wrap gap-2" style="color: var(--text-primary);">
                        <i class="fas fa-user-check mr-2" style="color: var(--primary);"></i>
                        {{ $link->proposed_name }}
                        <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $sc['class'] }}">
                            <i class="fas fa-{{ $sc['icon'] }} mr-1"></i> {{ $sc['text'] }}
                        </span>
                    </h2>
                    <div class="text-sm flex items-center flex-wrap gap-2 mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-heart mr-1"></i>
                        <span>{{ $link->relationship_label }}</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-building mr-1"></i>
                        <span>{{ $link->property->property_name ?? 'N/A' }}</span>

                        @if($link->isAwaitingLandlordConfirmation())
                            <span class="mx-1">•</span>
                            <span style="color: var(--primary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Not yet confirmable by admin
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.family-links.index') }}"
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Queue
                </a>
            </div>
        </div>
    </div>

    {{-- ============================================================
         MAIN CONTENT — split into columns
         ============================================================ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left: Proposal Details --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Proposed Member --}}
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user mr-2" style="color: var(--primary);"></i> Proposed Member
                    </h3>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Full Name</dt>
                            <dd class="text-sm font-medium" style="color: var(--text-primary);">{{ $link->proposed_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Relationship</dt>
                            <dd class="text-sm" style="color: var(--text-primary);">{{ $link->relationship_label }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Phone</dt>
                            <dd class="text-sm" style="color: var(--text-primary);">
                                @if($link->proposed_phone)
                                    <i class="fas fa-phone mr-1" style="color: var(--success);"></i> {{ $link->proposed_phone }}
                                @else
                                    <span style="color: var(--text-secondary);">—</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Email</dt>
                            <dd class="text-sm" style="color: var(--text-primary);">
                                @if($link->proposed_email)
                                    <i class="fas fa-envelope mr-1" style="color: var(--info);"></i> {{ $link->proposed_email }}
                                @else
                                    <span style="color: var(--text-secondary);">—</span>
                                @endif
                            </dd>
                        </div>
                    </dl>

                    @if($link->linkedUser)
                    <div class="mt-4 p-3 rounded-lg"
                         style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <div class="flex items-center">
                            <i class="fas fa-link mr-2" style="color: var(--success);"></i>
                            <span class="text-sm" style="color: var(--text-primary);">
                                Linked to existing user account: <strong>{{ $link->linkedUser->name }}</strong> (ID: {{ $link->linkedUser->id }})
                            </span>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Property & Landlord --}}
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-building mr-2" style="color: var(--primary);"></i> Property & Landlord
                    </h3>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Property</dt>
                            <dd class="text-sm font-medium" style="color: var(--text-primary);">
                                {{ $link->property->property_name ?? 'N/A' }}
                            </dd>
                            @if($link->property->digital_address ?? false)
                            <dd class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-map-marker-alt mr-1"></i> {{ $link->property->digital_address }}
                            </dd>
                            @endif
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Landlord</dt>
                            <dd class="text-sm font-medium" style="color: var(--text-primary);">
                                {{ $link->landlord->name ?? 'N/A' }}
                            </dd>
                            @if($link->landlord)
                            <dd class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-phone mr-1"></i> {{ $link->landlord->phone ?? '—' }}
                            </dd>
                            @endif
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Permissions Requested --}}
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-key mr-2" style="color: var(--primary);"></i> Permissions
                    </h3>
                    @php $granted = $link->permissions ?? []; @endphp
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        @foreach($permissions as $key => $label)
                            <div class="flex items-center p-2 rounded"
                                 style="background-color: {{ in_array($key, $granted) ? 'rgba(var(--success-rgb), 0.1)' : 'var(--bg-secondary)' }};">
                                <i class="fas {{ in_array($key, $granted) ? 'fa-check-circle' : 'fa-circle' }} mr-2"
                                   style="color: {{ in_array($key, $granted) ? 'var(--success)' : 'var(--text-secondary)' }};"></i>
                                <span class="text-sm" style="color: var(--text-primary);">{{ $label }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Notes --}}
            @if($link->landlord_notes || $link->admin_notes)
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-comments mr-2" style="color: var(--primary);"></i> Notes
                    </h3>
                    @if($link->landlord_notes)
                    <div class="mb-3">
                        <div class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Landlord Notes</div>
                        <div class="p-3 rounded-lg text-sm"
                             style="background-color: rgba(var(--info-rgb), 0.1); color: var(--text-primary);">
                            {{ $link->landlord_notes }}
                        </div>
                    </div>
                    @endif
                    @if($link->admin_notes)
                    <div>
                        <div class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Admin Notes</div>
                        <div class="p-3 rounded-lg text-sm"
                             style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--text-primary);">
                            {{ $link->admin_notes }}
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>

        {{-- Right: Actions + Timeline --}}
        <div class="lg:col-span-1 space-y-6">

            {{-- ============================================================
                 DECISION / ACTIONS CARD — state-aware
                 ============================================================ --}}
            @if($canReview)
                {{-- Gate 2: admin can approve or reject --}}
                <div class="card">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-gavel mr-2" style="color: var(--primary);"></i> Admin Decision
                        </h3>
                        <form action="{{ route('admin.family-links.review', $link->id) }}" method="POST">
                            @csrf

                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Grant Permissions
                                </label>
                                @foreach($permissions as $key => $label)
                                <div class="flex items-center mb-1">
                                    <input type="checkbox" name="permissions[]" value="{{ $key }}"
                                           id="perm-{{ $key }}"
                                           {{ in_array($key, $link->permissions ?? []) ? 'checked' : '' }}
                                           class="mr-2 w-4 h-4" style="accent-color: var(--primary);">
                                    <label for="perm-{{ $key }}" class="text-sm" style="color: var(--text-primary);">
                                        {{ $label }}
                                    </label>
                                </div>
                                @endforeach
                            </div>

                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Admin Notes
                                </label>
                                <textarea name="admin_notes" rows="3" class="index-custom-textarea w-full"
                                          placeholder="Optional notes..."></textarea>
                            </div>

                            <div class="rounded-lg p-3 mb-4"
                                 style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1" style="color: var(--success);"></i>
                                    On approval, the family member receives an invitation to set up their account. The landlord is notified of the decision.
                                </p>
                            </div>

                            <div class="d-grid gap-2 space-y-2">
                                <button name="decision" value="approve"
                                        class="w-full btn-primary px-4 py-2 rounded-lg font-medium text-white">
                                    <i class="fas fa-check mr-2"></i> Approve & Invite
                                </button>
                                <button name="decision" value="reject"
                                        class="w-full btn-danger px-4 py-2 rounded-lg font-medium text-white">
                                    <i class="fas fa-times mr-2"></i> Reject
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            @elseif($link->isAwaitingLandlordConfirmation())
                {{-- Gate 1: landlord has not yet confirmed; admin cannot act --}}
                <div class="card">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-hand-pointer mr-2" style="color: var(--primary);"></i> Awaiting Landlord
                        </h3>
                        <div class="rounded-lg p-4 mb-3"
                             style="background-color: rgba(var(--primary-rgb), 0.1); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--primary);"></i>
                                <div>
                                    <p class="text-sm font-medium mb-1" style="color: var(--primary);">
                                        Landlord confirmation required
                                    </p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        This proposal was submitted by <strong>{{ $link->landlord->name ?? 'the landlord' }}</strong>
                                        but has not yet been confirmed by them.
                                        Administrators cannot review the proposal until the landlord confirms their own intent.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-lg p-3"
                             style="background-color: var(--bg-secondary);">
                            <p class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-clock mr-1"></i>
                                Submitted {{ $link->created_at->diffForHumans() }}
                                @if($link->landlord_confirmed_at)
                                    • Landlord confirmed {{ $link->landlord_confirmed_at->diffForHumans() }}
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

            @elseif($canRevoke)
                {{-- Approved: admin can revoke --}}
                <div class="card">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-gavel mr-2" style="color: var(--primary);"></i> Actions
                        </h3>
                        <form action="{{ route('admin.family-links.revoke', $link->id) }}" method="POST">
                            @csrf
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Reason for Revocation
                                </label>
                                <textarea name="reason" rows="3" class="index-custom-textarea w-full"
                                          placeholder="Reason..."></textarea>
                            </div>
                            <button type="submit"
                                    class="w-full btn-danger px-4 py-2 rounded-lg font-medium text-white"
                                    onclick="return confirm('Revoke this link? The user will lose access immediately.')">
                                <i class="fas fa-ban mr-2"></i> Revoke Access
                            </button>
                        </form>
                    </div>
                </div>

            @else
                {{-- Rejected / Revoked / Cancelled: no action available --}}
                <div class="card">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> No Actions Available
                        </h3>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            @switch($link->status)
                                @case(PropertyFamilyLink::STATUS_REJECTED)
                                    This proposal was rejected and cannot be re-reviewed. The landlord can submit a new proposal.
                                    @break
                                @case(PropertyFamilyLink::STATUS_REVOKED)
                                    This link was revoked. Access has been removed and cannot be restored from this screen.
                                    @break
                                @case(PropertyFamilyLink::STATUS_CANCELLED)
                                    This proposal was cancelled by the landlord.
                                    @break
                                @default
                                    No further action is available for this proposal.
                            @endswitch
                        </p>
                    </div>
                </div>
            @endif

            {{-- ============================================================
                 TIMELINE — reflects the two-stage flow
                 ============================================================ --}}
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-history mr-2" style="color: var(--primary);"></i> Timeline
                    </h3>
                    <div class="space-y-3">

                        {{-- Step 1: Proposed --}}
                        <div class="flex items-start">
                            <div class="w-2 h-2 rounded-full mt-2 mr-3" style="background-color: var(--primary);"></div>
                            <div>
                                <div class="text-sm font-medium" style="color: var(--text-primary);">
                                    Proposed by {{ $link->landlord->name ?? 'landlord' }}
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    {{ $link->created_at->format('M j, Y g:i A') }}
                                </div>
                            </div>
                        </div>

                        {{-- Step 2: Landlord confirmation (only if reached) --}}
                        @if($link->landlord_confirmed_at)
                            <div class="flex items-start">
                                <div class="w-2 h-2 rounded-full mt-2 mr-3" style="background-color: var(--primary);"></div>
                                <div>
                                    <div class="text-sm font-medium" style="color: var(--text-primary);">
                                        Confirmed by landlord
                                        @if($link->landlordConfirmer)
                                            ({{ $link->landlordConfirmer->name }})
                                        @endif
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ $link->landlord_confirmed_at->format('M j, Y g:i A') }}
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Step 3: Admin review (only if reviewed_at set) --}}
                        @if($link->reviewed_at)
                            @php
                                $reviewColor = match ($link->status) {
                                    PropertyFamilyLink::STATUS_APPROVED => 'var(--success)',
                                    PropertyFamilyLink::STATUS_REJECTED => 'var(--danger)',
                                    default => 'var(--secondary)',
                                };
                            @endphp
                            <div class="flex items-start">
                                <div class="w-2 h-2 rounded-full mt-2 mr-3"
                                     style="background-color: {{ $reviewColor }};"></div>
                                <div>
                                    <div class="text-sm font-medium" style="color: var(--text-primary);">
                                        {{ ucfirst($link->status) }}
                                        @if($link->reviewer)
                                            by {{ $link->reviewer->name }}
                                        @endif
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ $link->reviewed_at->format('M j, Y g:i A') }}
                                    </div>
                                    @if($link->status === PropertyFamilyLink::STATUS_APPROVED)
                                        <div class="text-xs mt-0.5" style="color: var(--success);">
                                            <i class="fas fa-envelope mr-1"></i>
                                            Invitation sent to {{ $link->proposed_email ?? $link->proposed_phone ?? 'the family member' }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- Step 4: Revoked (only if revoked_at set) --}}
                        @if($link->revoked_at)
                            <div class="flex items-start">
                                <div class="w-2 h-2 rounded-full mt-2 mr-3" style="background-color: var(--secondary);"></div>
                                <div>
                                    <div class="text-sm font-medium" style="color: var(--text-primary);">
                                        Revoked
                                        @if($link->revoker)
                                            by {{ $link->revoker->name }}
                                        @endif
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ $link->revoked_at->format('M j, Y g:i A') }}
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Step 5: Cancelled (if cancelled — soft-deleted rows may not show, but guard anyway) --}}
                        @if($link->status === PropertyFamilyLink::STATUS_CANCELLED && $link->updated_at)
                            <div class="flex items-start">
                                <div class="w-2 h-2 rounded-full mt-2 mr-3" style="background-color: var(--secondary);"></div>
                                <div>
                                    <div class="text-sm font-medium" style="color: var(--text-primary);">
                                        Cancelled by landlord
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ $link->updated_at->format('M j, Y g:i A') }}
                                    </div>
                                </div>
                            </div>
                        @endif

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<style>
.index-custom-textarea {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
}
.index-custom-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

/* Badges */
.badge-success   { background-color: rgba(var(--success-rgb), 0.1);   color: var(--success);   border: 1px solid rgba(var(--success-rgb), 0.3); }
.badge-warning   { background-color: rgba(var(--warning-rgb), 0.1);   color: var(--warning);   border: 1px solid rgba(var(--warning-rgb), 0.3); }
.badge-danger    { background-color: rgba(var(--danger-rgb), 0.1);    color: var(--danger);    border: 1px solid rgba(var(--danger-rgb), 0.3); }
.badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3); }
.badge-primary   { background-color: rgba(var(--primary-rgb), 0.1);   color: var(--primary);   border: 1px solid rgba(var(--primary-rgb), 0.3); }

/* Buttons */
.btn-primary   { background-color: var(--primary); color: white; border: 1px solid var(--primary); }
.btn-primary:hover { background-color: var(--secondary); border-color: var(--secondary); }
.btn-danger    { background-color: var(--danger); color: white; border: 1px solid var(--danger); }
.btn-danger:hover { background-color: #c82333; }
</style>
@endpush
@endsection