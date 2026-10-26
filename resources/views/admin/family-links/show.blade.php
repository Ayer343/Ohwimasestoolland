{{-- admin/family-links/show.blade.php --}}
@php
    use App\Models\PropertyFamilyLink;

    $layout    = 'layouts.app';
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

    // ── Permissions config (defensive against a missing config file) ──
    $permissions = $permissions ?? config('property_family_links.permissions', []);
    $granted     = is_array($link->permissions) ? $link->permissions : [];

    // ── Action capability ──
    // Prefer the context the controller prepared (single source of truth);
    // fall back to computing it here if an older controller is still deployed.
    $reviewContext = $reviewContext ?? [];
    $canReview = $reviewContext['is_admin_actionable']
        ?? ($link->isAwaitingAdminReview() || $link->status === PropertyFamilyLink::STATUS_PENDING);
    $canRevoke = $link->isApproved();

    // ── Terminal-state reason (rejection / revocation) ──
    $isRejected = $link->status === PropertyFamilyLink::STATUS_REJECTED;
    $isRevoked  = $link->status === PropertyFamilyLink::STATUS_REVOKED;
    $hasReason  = ($isRejected || $isRevoked) && !empty($link->admin_notes);

    // ── Preset rejection reasons — same set the index page uses. ──
    $presetReasons = [
        'Insufficient documentation provided.',
        'Relationship could not be verified.',
        'Duplicate of an existing proposal.',
        'Property ownership could not be confirmed.',
        'Suspected fraudulent or inaccurate information.',
        'Contact details are unreachable or invalid.',
    ];

    // ── Human-readable status label for the timeline. ──
    $timelineStatusLabel = match ($link->status) {
        PropertyFamilyLink::STATUS_APPROVED => 'Approved',
        PropertyFamilyLink::STATUS_REJECTED => 'Rejected',
        PropertyFamilyLink::STATUS_REVOKED  => 'Revoked',
        default                              => ucfirst($link->status),
    };
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
         DECISION REASON BANNER — only for rejected / revoked
         ============================================================ --}}
    @if($hasReason)
    <div class="card" style="border-left: 4px solid {{ $isRejected ? 'var(--danger)' : 'var(--secondary)' }};">
        <div class="p-5">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--{{ $isRejected ? 'danger' : 'secondary' }}-rgb), 0.12);">
                        <i class="fas fa-comment-dots"
                           style="color: var(--{{ $isRejected ? 'danger' : 'secondary' }});"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <h3 class="text-sm font-semibold mb-1"
                        style="color: var(--{{ $isRejected ? 'danger' : 'secondary' }});">
                        {{ $isRejected ? 'Rejection Reason' : 'Revocation Reason' }}
                    </h3>
                    <p class="text-sm" style="color: var(--text-primary); white-space: pre-wrap;">{{ $link->admin_notes }}</p>
                    <div class="text-xs mt-2" style="color: var(--text-secondary);">
                        @if($link->reviewer)
                            <i class="fas fa-user-shield mr-1"></i>
                            {{ $link->reviewer->name ?? 'Admin' }}
                        @endif
                        @if($link->reviewed_at)
                            <span class="mx-1">•</span>
                            <i class="fas fa-clock mr-1"></i>
                            {{ $link->reviewed_at->format('M j, Y g:i A') }}
                            <span class="mx-1">({{ $link->reviewed_at->diffForHumans() }})</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

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
                                Linked to existing user account:
                                <strong>{{ $link->linkedUser->name }}</strong> (ID: {{ $link->linkedUser->id }})
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
                        <span class="ml-2 text-xs font-normal" style="color: var(--text-secondary);">
                            ({{ count($granted) }} of {{ count($permissions) }} granted)
                        </span>
                    </h3>

                    @if(empty($permissions))
                        <p class="text-sm" style="color: var(--text-secondary);">
                            No permission catalog is configured.
                        </p>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            @foreach($permissions as $key => $label)
                                @php $isGranted = in_array($key, $granted, true); @endphp
                                <div class="flex items-center p-2 rounded"
                                     style="background-color: {{ $isGranted ? 'rgba(var(--success-rgb), 0.1)' : 'var(--bg-secondary)' }};">
                                    <i class="fas {{ $isGranted ? 'fa-check-circle' : 'fa-circle' }} mr-2"
                                       style="color: {{ $isGranted ? 'var(--success)' : 'var(--text-secondary)' }};"></i>
                                    <span class="text-sm" style="color: var(--text-primary);">{{ $label }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Landlord Notes --}}
            @if($link->landlord_notes)
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-comments mr-2" style="color: var(--primary);"></i> Landlord Notes
                    </h3>
                    <div class="p-3 rounded-lg text-sm"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--text-primary); white-space: pre-wrap;">{{ $link->landlord_notes }}</div>
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

                        {{-- Approve --}}
                        <form id="approveForm" action="{{ route('admin.family-links.review', $link->id) }}" method="POST">
                            @csrf
                            <input type="hidden" name="decision" value="approve">

                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-key mr-1"></i> Grant Permissions
                                </label>
                                @if(empty($permissions))
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        No permission catalog configured.
                                    </p>
                                @else
                                    @foreach($permissions as $key => $label)
                                    <div class="flex items-center mb-1">
                                        <input type="checkbox" name="permissions[]" value="{{ $key }}"
                                               id="perm-{{ $key }}"
                                               {{ in_array($key, $granted, true) ? 'checked' : '' }}
                                               class="mr-2 w-4 h-4" style="accent-color: var(--primary);">
                                        <label for="perm-{{ $key }}" class="text-sm" style="color: var(--text-primary);">
                                            {{ $label }}
                                        </label>
                                    </div>
                                    @endforeach
                                @endif
                            </div>

                            {{-- Delivery channels — only show what the landlord provided --}}
                            @php
                                $hasEmail = !empty(trim((string) $link->proposed_email));
                                $hasPhone = !empty(trim((string) $link->proposed_phone));
                            @endphp
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-paper-plane mr-1"></i> Delivery Channels
                                </label>
                                <p class="text-xs mb-2" style="color: var(--text-secondary);">
                                    How the invitation will be sent. Only channels the landlord provided are selectable.
                                </p>

                                <label id="showChannelEmailWrap"
                                       class="flex items-center p-2 rounded-lg mb-1"
                                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); opacity: {{ $hasEmail ? '1' : '0.55' }}; cursor: {{ $hasEmail ? 'pointer' : 'not-allowed' }};">
                                    <input type="checkbox" name="channels[]" value="email"
                                           id="showChannelEmail"
                                           class="mr-3 w-4 h-4"
                                           style="accent-color: var(--primary);"
                                           {{ $hasEmail ? 'checked' : 'disabled' }}>
                                    <span class="text-sm flex-1" style="color: var(--text-primary);">
                                        <i class="fas fa-envelope mr-1" style="color: var(--info);"></i> Email
                                        <span class="block text-xs mt-0.5" style="color: var(--text-secondary);">
                                            {{ $hasEmail ? $link->proposed_email : 'Not provided' }}
                                        </span>
                                    </span>
                                    @unless($hasEmail)
                                        <span class="text-xs px-2 py-0.5 rounded-full"
                                              style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                            Not provided
                                        </span>
                                    @endunless
                                </label>

                                <label id="showChannelSmsWrap"
                                       class="flex items-center p-2 rounded-lg mb-1"
                                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); opacity: {{ $hasPhone ? '1' : '0.55' }}; cursor: {{ $hasPhone ? 'pointer' : 'not-allowed' }};">
                                    <input type="checkbox" name="channels[]" value="sms"
                                           id="showChannelSms"
                                           class="mr-3 w-4 h-4"
                                           style="accent-color: var(--primary);"
                                           {{ $hasPhone ? 'checked' : 'disabled' }}>
                                    <span class="text-sm flex-1" style="color: var(--text-primary);">
                                        <i class="fas fa-sms mr-1" style="color: var(--success);"></i> SMS
                                        <span class="block text-xs mt-0.5" style="color: var(--text-secondary);">
                                            {{ $hasPhone ? $link->proposed_phone : 'Not provided' }}
                                        </span>
                                    </span>
                                    @unless($hasPhone)
                                        <span class="text-xs px-2 py-0.5 rounded-full"
                                              style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                            Not provided
                                        </span>
                                    @endunless
                                </label>

                                <label id="showChannelWhatsappWrap"
                                       class="flex items-center p-2 rounded-lg"
                                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); opacity: {{ $hasPhone ? '1' : '0.55' }}; cursor: {{ $hasPhone ? 'pointer' : 'not-allowed' }};">
                                    <input type="checkbox" name="channels[]" value="whatsapp"
                                           id="showChannelWhatsapp"
                                           class="mr-3 w-4 h-4"
                                           style="accent-color: var(--primary);"
                                           {{ $hasPhone ? '' : 'disabled' }}>
                                    <span class="text-sm flex-1" style="color: var(--text-primary);">
                                        <i class="fab fa-whatsapp mr-1" style="color: #25D366;"></i> WhatsApp
                                        <span class="block text-xs mt-0.5" style="color: var(--text-secondary);">
                                            {{ $hasPhone ? $link->proposed_phone : 'Not provided' }}
                                        </span>
                                    </span>
                                    @unless($hasPhone)
                                        <span class="text-xs px-2 py-0.5 rounded-full"
                                              style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                            Not provided
                                        </span>
                                    @endunless
                                </label>

                                @unless($hasEmail || $hasPhone)
                                    <div class="mt-2 p-2 rounded text-xs"
                                         style="background-color: rgba(var(--danger-rgb), 0.08); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.25);">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        No contact method was provided. Cannot approve without a channel to send the invitation to.
                                    </div>
                                @endunless
                            </div>

                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Admin Notes (Optional)
                                </label>
                                <textarea name="admin_notes" rows="3" class="index-custom-textarea w-full"
                                          placeholder="Optional notes for the record..."></textarea>
                            </div>

                            <div class="rounded-lg p-3 mb-4"
                                 style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1" style="color: var(--success);"></i>
                                    On approval, the family member receives an invitation to set up their account. The landlord is notified of the decision.
                                </p>
                            </div>

                            <button type="submit"
                                    class="w-full btn-primary px-4 py-2 rounded-lg font-medium text-white">
                                <i class="fas fa-check mr-2"></i> Approve & Invite
                            </button>
                        </form>

                        <hr class="my-4" style="border-color: var(--border-color);">

                        {{-- Reject --}}
                        <form id="rejectForm" action="{{ route('admin.family-links.review', $link->id) }}" method="POST">
                            @csrf
                            <input type="hidden" name="decision" value="reject">

                            <div class="mb-3">
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-times-circle mr-1" style="color: var(--danger);"></i>
                                    Reason for Rejection <span class="text-red-500">*</span>
                                </label>

                                {{-- Preset reason chips --}}
                                <div class="mb-2">
                                    <div class="text-xs mb-1.5" style="color: var(--text-secondary);">
                                        Common reasons (click to use)
                                    </div>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($presetReasons as $preset)
                                            <button type="button"
                                                    class="text-xs px-2 py-1 rounded-full preset-reason"
                                                    data-reason="{{ $preset }}"
                                                    data-target="rejectReasonInput"
                                                    style="background-color: rgba(var(--danger-rgb), 0.08); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.25); cursor: pointer;">
                                                {{ $preset }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                <textarea name="admin_notes" id="rejectReasonInput" rows="4"
                                          class="index-custom-textarea w-full"
                                          placeholder="Explain why this proposal is being rejected..." required></textarea>
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    The landlord will see this reason so they can correct and resubmit if appropriate.
                                </div>
                            </div>

                            <button type="submit"
                                    class="w-full btn-danger px-4 py-2 rounded-lg font-medium text-white">
                                <i class="fas fa-times mr-2"></i> Reject Proposal
                            </button>
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
                                        This proposal was submitted by
                                        <strong>{{ $link->landlord->name ?? 'the landlord' }}</strong>
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
                                Submitted {{ optional($link->created_at)->diffForHumans() ?? 'recently' }}
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
                        <form id="revokeForm" action="{{ route('admin.family-links.revoke', $link->id) }}" method="POST">
                            @csrf
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Reason for Revocation
                                </label>
                                <textarea name="reason" id="revokeReasonInput" rows="3" class="index-custom-textarea w-full"
                                          placeholder="Reason for revoking access..."></textarea>
                            </div>
                            <div class="rounded-lg p-3 mb-3"
                                 style="background-color: rgba(var(--danger-rgb), 0.08); border: 1px solid rgba(var(--danger-rgb), 0.25);">
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-exclamation-triangle mr-1" style="color: var(--danger);"></i>
                                    The family member will lose access immediately.
                                </p>
                            </div>
                            <button type="submit"
                                    class="w-full btn-danger px-4 py-2 rounded-lg font-medium text-white">
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
                                    {{ optional($link->created_at)->format('M j, Y g:i A') ?? '—' }}
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
                                    default                             => 'var(--secondary)',
                                };
                            @endphp
                            <div class="flex items-start">
                                <div class="w-2 h-2 rounded-full mt-2 mr-3"
                                     style="background-color: {{ $reviewColor }};"></div>
                                <div>
                                    <div class="text-sm font-medium" style="color: var(--text-primary);">
                                        {{ $timelineStatusLabel }}
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
                                            Invitation sent to
                                            {{ $link->proposed_email ?? $link->proposed_phone ?? 'the family member' }}
                                        </div>
                                    @elseif($link->status === PropertyFamilyLink::STATUS_REJECTED && $link->admin_notes)
                                        <div class="text-xs mt-1 p-2 rounded"
                                             style="background-color: rgba(var(--danger-rgb), 0.08); color: var(--text-primary); max-width: 260px;">
                                            <i class="fas fa-comment mr-1" style="color: var(--danger);"></i>
                                            {{ \Illuminate\Support\Str::limit($link->admin_notes, 120) }}
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

                        {{-- Step 5: Cancelled --}}
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
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Preset reason chips ──
    // Append the chosen reason to the target textarea, respecting data-target.
    document.querySelectorAll('.preset-reason').forEach(btn => {
        btn.addEventListener('click', function () {
            const reason   = this.getAttribute('data-reason') || '';
            const targetId = this.getAttribute('data-target') || 'rejectReasonInput';
            const input    = document.getElementById(targetId);
            if (!input) return;

            input.value = input.value
                ? (input.value.trimEnd() + ' ' + reason)
                : reason;

            input.focus();
            input.setSelectionRange(input.value.length, input.value.length);
        });
    });

    // ── Approve form — guard the channel requirement and confirm ──
    const approveForm = document.getElementById('approveForm');
    if (approveForm) {
        approveForm.addEventListener('submit', function (e) {
            const channels = Array.from(
                approveForm.querySelectorAll('input[name="channels[]"]:checked')
            );
            if (channels.length === 0) {
                e.preventDefault();
                alert('Select at least one delivery channel for the invitation.');
                return;
            }
            if (!confirm('Approve this family link? The family member will be invited and the landlord notified.')) {
                e.preventDefault();
            }
        });
    }

    // ── Reject form — require a reason ──
    const rejectForm = document.getElementById('rejectForm');
    if (rejectForm) {
        rejectForm.addEventListener('submit', function (e) {
            const reason = document.getElementById('rejectReasonInput')?.value.trim();
            if (!reason) {
                e.preventDefault();
                alert('Please provide a reason for rejecting this proposal.');
                document.getElementById('rejectReasonInput')?.focus();
            }
        });
    }

    // ── Revoke form — confirm ──
    const revokeForm = document.getElementById('revokeForm');
    if (revokeForm) {
        revokeForm.addEventListener('submit', function (e) {
            if (!confirm('Revoke this link? The family member will lose access immediately.')) {
                e.preventDefault();
            }
        });
    }
});
</script>

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

.preset-reason:hover {
    background-color: rgba(var(--danger-rgb), 0.18) !important;
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