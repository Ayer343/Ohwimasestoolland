{{-- resources/views/landlord/ads/index.blade.php --}}
@extends('layouts.landlord')

@section('title', 'My Ad Campaigns')

@php
    use App\Models\AdCampaign;
    use App\Models\AdInvoice;

    $campaigns = $campaigns ?? collect();
@endphp

@section('content')
<div class="max-w-7xl mx-auto py-8 px-4">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">My Ad Campaigns</h1>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Promote your available units on the homepage and social media.
            </p>
        </div>
        <a href="{{ route('landlord.ads.create') }}"
           class="px-4 py-2 rounded-lg text-sm font-medium"
           style="background: var(--primary); color: white;">
            <i class="fas fa-plus mr-2"></i>New Campaign
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg" style="background: rgba(var(--success-rgb), 0.1); color: var(--success);">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg" style="background: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
            {{ session('error') }}
        </div>
    @endif

    {{-- Informational banner: how many campaigns are awaiting payment? --}}
    @php
        $unpaidCount = $campaigns->filter(function ($c) {
            return $c->status === AdCampaign::STATUS_PENDING_PAYMENT
                && $c->invoices->contains('status', AdInvoice::STATUS_PENDING);
        })->count();
    @endphp

    @if($unpaidCount > 0)
        <div class="mb-4 p-4 rounded-lg"
             style="background: rgba(var(--warning-rgb), 0.1); border-left: 4px solid var(--warning);">
            <div class="flex items-start">
                <i class="fas fa-hourglass-half mr-2 mt-0.5" style="color: var(--warning);"></i>
                <div class="flex-1">
                    <p class="text-sm font-semibold" style="color: var(--text-primary);">
                        {{ $unpaidCount }} campaign{{ $unpaidCount > 1 ? 's' : '' }} awaiting payment
                    </p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        Complete payment to make your ad go live. Unpaid campaigns don't appear on the homepage.
                    </p>
                </div>
            </div>
        </div>
    @endif

    @if($campaigns->isEmpty())
        <div class="text-center py-16 card">
            <i class="fas fa-bullhorn text-5xl mb-4" style="color: var(--text-secondary); opacity: 0.3;"></i>
            <p class="text-lg font-medium mb-2" style="color: var(--text-primary);">No campaigns yet</p>
            <p class="text-sm mb-6" style="color: var(--text-secondary);">
                Create your first campaign to start advertising your available units.
            </p>
            <a href="{{ route('landlord.ads.create') }}"
               class="px-6 py-2 rounded-lg text-sm font-medium"
               style="background: var(--primary); color: white;">
                Create Campaign
            </a>
        </div>
    @else
        <div class="space-y-4">
            @foreach($campaigns as $campaign)
                @php
                    // ============================================================
                    // ✅ FIXED: DELETE GUARDS — delegate to the model.
                    //
                    //    `canBeDeletedByLandlord()` is the single source of
                    //    truth, used by BOTH:
                    //      - AdCampaignController::destroy() (the guard)
                    //      - this blade (the button visibility)
                    //
                    //    The rules are:
                    //      1. Status ∈ {draft, cancelled, rejected}
                    //      2. No FINANCIAL invoice (paid/refunded/failed)
                    //         — pending & cancelled invoices are ignored
                    //      3. No public views or clicks
                    //
                    //    That's why a cancelled campaign with only a pending
                    //    invoice is now deletable — matching the controller.
                    // ============================================================
                    $canLandlordDelete = $campaign->canBeDeletedByLandlord();
                    $deleteBlockReason = $campaign->landlordDeleteBlockReason();

                    // ============================================================
                    // ✅ Payment-state resolution (unchanged).
                    //    Uses the eager-loaded invoices collection and
                    //    STATUS_PENDING_PAYMENT — mirrors show.blade.php.
                    // ============================================================
                    $pendingInvoice = $campaign->invoices->firstWhere('status', AdInvoice::STATUS_PENDING);

                    $canPay = $campaign->status === AdCampaign::STATUS_PENDING_PAYMENT
                           && $pendingInvoice !== null;

                    // Expired-campaign detection (defensive).
                    $isExpired = false;
                    if (!empty($campaign->ends_at) && $campaign->ends_at->isPast()) {
                        $isExpired = true;
                    }
                    if (defined(AdCampaign::class . '::STATUS_EXPIRED')
                        && $campaign->status === AdCampaign::STATUS_EXPIRED) {
                        $isExpired = true;
                    }

                    $canRepay = $isExpired
                        && $campaign->status !== AdCampaign::STATUS_CANCELLED
                        && $campaign->status !== AdCampaign::STATUS_REJECTED;

                    $showPayButton   = $canPay;
                    $showRepayButton = !$canPay && $canRepay;

                    $repayNeedsNewInvoice = $showRepayButton && $pendingInvoice === null;
                @endphp

                <div class="card p-6">
                    <div class="flex justify-between items-start flex-wrap gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2 flex-wrap">
                                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                                    {{ $campaign->unit->unit_name ?? $campaign->unit->unit_number }}
                                </h3>
                                @include('landlord.ads.partials.status-badge', ['status' => $campaign->status])

                                @if($showPayButton)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
                                          style="background: rgba(var(--warning-rgb), 0.15); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                        <i class="fas fa-hourglass-half mr-1"></i> Awaiting payment
                                    </span>
                                @endif

                                @if($showRepayButton)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
                                          style="background: rgba(var(--info-rgb), 0.15); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                        <i class="fas fa-history mr-1"></i> Expired — renew to reactivate
                                    </span>
                                @endif
                            </div>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                {{ $campaign->plan_name }} · {{ $campaign->duration_months }} month(s)
                                @if($campaign->ends_at)
                                    · Ends {{ $campaign->ends_at->format('M d, Y') }}
                                @endif
                            </p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-eye mr-1"></i>{{ $campaign->views_count }} views ·
                                <i class="fas fa-mouse-pointer mr-1"></i>{{ $campaign->clicks_count }} clicks
                            </p>

                            @if(($showPayButton || $showRepayButton) && $pendingInvoice)
                                <p class="text-sm mt-2 font-semibold" style="color: var(--primary);">
                                    <i class="fas fa-file-invoice-dollar mr-1"></i>
                                    Amount due: {{ $pendingInvoice->formatted_amount ?? ($pendingInvoice->currency . ' ' . number_format($pendingInvoice->amount, 2)) }}
                                    <span class="text-xs font-normal ml-1" style="color: var(--text-secondary);">
                                        · Invoice {{ $pendingInvoice->invoice_number }}
                                    </span>
                                </p>
                            @endif
                        </div>

                        <div class="flex gap-2 flex-wrap">
                            @if($showPayButton)
                                <a href="{{ route('landlord.ads.pay', $campaign->uuid) }}"
                                   class="px-4 py-1.5 rounded-lg text-sm font-semibold inline-flex items-center"
                                   style="background: var(--primary); color: white;">
                                    <i class="fas fa-credit-card mr-1"></i> Pay Now
                                </a>
                            @endif

                            @if($showRepayButton)
                                @if(!$repayNeedsNewInvoice)
                                    <a href="{{ route('landlord.ads.pay', $campaign->uuid) }}"
                                       class="px-4 py-1.5 rounded-lg text-sm font-semibold inline-flex items-center"
                                       style="background: var(--primary); color: white;">
                                        <i class="fas fa-rotate-right mr-1"></i> Repay Now
                                    </a>
                                @else
                                    <a href="{{ route('landlord.ads.create', ['property_unit_id' => $campaign->property_unit_id]) }}"
                                       class="px-4 py-1.5 rounded-lg text-sm font-semibold inline-flex items-center"
                                       title="Create a new campaign for the same unit to renew the ad"
                                       style="background: var(--primary); color: white;">
                                        <i class="fas fa-rotate-right mr-1"></i> Renew Ad
                                    </a>
                                @endif
                            @endif

                            <a href="{{ route('landlord.ads.show', $campaign->uuid) }}"
                               class="px-3 py-1.5 rounded-lg text-sm font-medium"
                               style="background: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                View
                            </a>

                            @if($campaign->canBeMarkedRented())
                                <form method="POST" action="{{ route('landlord.ads.mark-rented', $campaign->uuid) }}"
                                      onsubmit="return confirm('Request to mark this campaign as rented? An admin will review.');">
                                    @csrf
                                    <button type="submit"
                                            class="px-3 py-1.5 rounded-lg text-sm font-medium"
                                            style="background: var(--success); color: white;">
                                        Mark as Rented
                                    </button>
                                </form>
                            @endif

                            @if($campaign->canBeCancelledByLandlord())
                                <form method="POST" action="{{ route('landlord.ads.cancel', $campaign->uuid) }}"
                                      onsubmit="return confirm('Cancel this campaign? No refund for the current period.');">
                                    @csrf
                                    <button type="submit"
                                            class="px-3 py-1.5 rounded-lg text-sm font-medium"
                                            style="background: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                        Cancel
                                    </button>
                                </form>
                            @endif

                            {{-- ✅ FIXED: Delete button uses the model's decision. --}}
                            @if($canLandlordDelete)
                                <form method="POST" action="{{ route('landlord.ads.destroy', $campaign->uuid) }}"
                                      onsubmit="return confirm('Permanently delete this campaign?\n\nThis cannot be undone. The campaign and any associated data will be removed.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="px-3 py-1.5 rounded-lg text-sm font-medium"
                                            style="background: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                        <i class="fas fa-trash-alt mr-1"></i> Delete
                                    </button>
                                </form>
                            @elseif(!empty($deleteBlockReason))
                                <button type="button"
                                        disabled
                                        title="{{ $deleteBlockReason }}"
                                        class="px-3 py-1.5 rounded-lg text-sm font-medium cursor-not-allowed"
                                        style="background: rgba(var(--danger-rgb), 0.05);
                                               color: var(--danger);
                                               opacity: 0.4;
                                               border: 1px dashed rgba(var(--danger-rgb), 0.3);">
                                    <i class="fas fa-trash-alt mr-1"></i> Delete
                                </button>
                            @endif
                        </div>
                    </div>

                    @if($showPayButton)
                        <div class="mt-4 pt-4 border-t text-xs"
                             style="border-color: var(--border-color); color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1" style="color: var(--info);"></i>
                            This campaign is not live yet. It will stay in awaiting-payment until you complete payment.
                            You can also delete it entirely if you've changed your mind.
                        </div>
                    @endif

                    @if($showRepayButton)
                        <div class="mt-4 pt-4 border-t text-xs"
                             style="border-color: var(--border-color); color: var(--text-secondary);">
                            <i class="fas fa-clock mr-1" style="color: var(--info);"></i>
                            This campaign ended on
                            <strong style="color: var(--text-primary);">{{ $campaign->ends_at?->format('M d, Y') }}</strong>.
                            Renew it to make the ad live again and get a fresh run on the homepage.
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $campaigns->links() }}
        </div>
    @endif
</div>
@endsection