{{-- resources/views/landlord/properties/partials/family-links-panel.blade.php --}}

<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-users me-2"></i>
            Linked Family Members
            <span class="badge bg-secondary" id="familyLinkCountBadge">
                {{ $property->familyLinks->count() }}
            </span>
        </h5>
        <div class="d-flex gap-2">
            <a href="{{ route('landlord.properties.family-links', $property->id) }}"
               class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-external-link-alt me-1"></i> Manage
            </a>
            <button class="btn btn-sm btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#addFamilyMemberModal">
                <i class="fas fa-plus me-1"></i> Add Family Member
            </button>
        </div>
    </div>

    <div class="card-body">
        @php
            // Hide revoked and cancelled links from the inline panel — they're
            // visible on the dedicated management page.
            $visibleLinks = $property->familyLinks
                ->whereNotIn('status', ['revoked', 'cancelled'])
                ->sortByDesc('created_at');
        @endphp

        @forelse($visibleLinks as $link)
            <div class="d-flex align-items-start justify-content-between border-bottom py-3"
                 data-family-link-row="{{ $link->id }}">

                {{-- ───────── Left: member info ───────── --}}
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                        <strong>{{ $link->display_name }}</strong>

                        <span class="badge bg-light text-dark">
                            {{ $link->relationship_label }}
                        </span>

                        <span class="badge
                            @if($link->isAwaitingLandlordConfirmation()) bg-primary
                            @elseif($link->isAwaitingAdminReview())      bg-warning text-dark
                            @elseif($link->isPending())                  bg-warning text-dark
                            @elseif($link->isApproved())                 bg-success
                            @elseif($link->isRejected())                 bg-danger
                            @elseif($link->isCancelled())                bg-secondary
                            @else                                        bg-secondary
                            @endif">
                            {{ $link->status_label }}
                        </span>

                        @if($link->isAwaitingLandlordConfirmation())
                            <small class="text-primary">
                                <i class="fas fa-hand-pointer me-1"></i>Confirm to send to admins
                            </small>
                        @elseif($link->isAwaitingAdminReview())
                            <small class="text-muted">
                                <i class="fas fa-hourglass-half me-1"></i>Awaiting admin review
                            </small>
                        @endif
                    </div>

                    <div class="small text-muted">
                        @if($link->proposed_phone)
                            <span class="me-3">
                                <i class="fas fa-phone me-1"></i>{{ $link->proposed_phone }}
                            </span>
                        @endif

                        @if($link->proposed_email)
                            <span class="me-3">
                                <i class="fas fa-envelope me-1"></i>{{ $link->proposed_email }}
                            </span>
                        @endif

                        <span>
                            <i class="fas fa-clock me-1"></i>
                            Added {{ $link->created_at->diffForHumans() }}
                        </span>
                    </div>

                    @if($link->status === 'rejected' && $link->admin_notes)
                        <div class="small text-danger mt-1">
                            <i class="fas fa-comment me-1"></i>
                            Rejected: {{ Str::limit($link->admin_notes, 80) }}
                        </div>
                    @endif
                </div>

                {{-- ───────── Right: actions ───────── --}}
                <div class="d-flex gap-2 ms-3 flex-shrink-0">

                    @if($link->isAwaitingLandlordConfirmation())
                        {{-- Gate 1: Landlord must confirm --}}
                        <button type="button"
                                class="btn btn-sm btn-primary"
                                data-confirm-link="{{ $link->id }}"
                                data-member-name="{{ addslashes($link->proposed_name) }}">
                            <i class="fas fa-check me-1"></i> Confirm
                        </button>
                        <button type="button"
                                class="btn btn-sm btn-outline-danger"
                                data-cancel-link="{{ $link->id }}"
                                data-member-name="{{ addslashes($link->proposed_name) }}">
                            <i class="fas fa-times me-1"></i> Cancel
                        </button>

                    @elseif($link->isAwaitingAdminReview())
                        {{-- Waiting on admin — landlord can only cancel --}}
                        <span class="badge bg-warning text-dark align-self-center">
                            <i class="fas fa-hourglass-half me-1"></i> Awaiting admin review
                        </span>
                        <button type="button"
                                class="btn btn-sm btn-outline-danger"
                                data-cancel-link="{{ $link->id }}"
                                data-member-name="{{ addslashes($link->proposed_name) }}">
                            <i class="fas fa-times me-1"></i> Cancel
                        </button>

                    @elseif($link->isApproved())
                        {{-- Approved — landlord can revoke --}}
                        <span class="badge bg-success align-self-center">
                            <i class="fas fa-check-circle me-1"></i> Approved
                        </span>
                        <button type="button"
                                class="btn btn-sm btn-outline-danger"
                                data-revoke-link="{{ $link->id }}"
                                data-link-name="{{ addslashes($link->display_name) }}">
                            <i class="fas fa-ban me-1"></i> Revoke
                        </button>

                    @elseif($link->isRejected())
                        <span class="badge bg-danger align-self-center">
                            <i class="fas fa-times-circle me-1"></i> Rejected
                        </span>

                    @elseif($link->isCancelled())
                        <span class="badge bg-secondary align-self-center">
                            <i class="fas fa-ban me-1"></i> Cancelled
                        </span>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-muted mb-0">
                <i class="fas fa-info-circle me-1"></i>
                No family members linked to this property yet.
            </p>
        @endforelse
    </div>
</div>