{{-- resources/views/landlord/ads/show.blade.php --}}
@extends('layouts.landlord')

@section('title', 'Campaign — ' . ($campaign->unit->unit_name ?? $campaign->unit->unit_number ?? 'Unit'))

@php
    use App\Models\AdCampaign;
    use App\Models\AdInvoice;
    use Illuminate\Support\Str;

    $campaign = $campaign ?? null;

    if (!$campaign) {
        abort(404, 'Campaign not found.');
    }

    $unit     = $campaign->unit;
    $property = $unit?->property;
    $plan     = $campaign->plan;
    $invoices = $campaign->invoices ?? collect();

    $unitLabel    = $unit?->unit_name ?? $unit?->unit_number ?? 'Unit #' . $campaign->property_unit_id;
    $propertyName = $property?->property_name ?? 'Unknown property';
    $propertyAddr = $property?->address ?? null;
    $channels     = is_array($campaign->channels) ? $campaign->channels : [];

    $statusBadges = [
        AdCampaign::STATUS_DRAFT           => ['Draft',           'secondary', 'fas fa-pen'],
        AdCampaign::STATUS_PENDING_PAYMENT => ['Awaiting Payment','warning',   'fas fa-clock'],
        AdCampaign::STATUS_ACTIVE          => ['Active',          'success',   'fas fa-play-circle'],
        AdCampaign::STATUS_PAUSED          => ['Paused',          'secondary', 'fas fa-pause-circle'],
        AdCampaign::STATUS_RENTED_EARLY    => ['Rented',          'info',      'fas fa-handshake'],
        AdCampaign::STATUS_EXPIRED         => ['Expired',         'secondary', 'fas fa-calendar-times'],
        AdCampaign::STATUS_CANCELLED       => ['Cancelled',       'danger',    'fas fa-ban'],
        AdCampaign::STATUS_REJECTED        => ['Rejected',        'danger',    'fas fa-times-circle'],
    ];

    [$statusLabel, $statusColor, $statusIcon] = $statusBadges[$campaign->status] ?? [ucfirst($campaign->status), 'secondary', 'fas fa-info-circle'];

    $channelMeta = [
        'homepage'  => ['Homepage',  'fas fa-home',        'primary'],
        'facebook'  => ['Facebook',  'fab fa-facebook-f',  'info'],
        'whatsapp'  => ['WhatsApp',  'fab fa-whatsapp',    'success'],
        'twitter'   => ['Twitter/X', 'fab fa-x-twitter',   'secondary'],
        'linkedin'  => ['LinkedIn',  'fab fa-linkedin-in', 'info'],
        'instagram' => ['Instagram', 'fab fa-instagram',   'danger'],
    ];

    $invoiceStatusColors = [
        AdInvoice::STATUS_PENDING   => 'warning',
        AdInvoice::STATUS_PAID      => 'success',
        AdInvoice::STATUS_FAILED    => 'danger',
        AdInvoice::STATUS_REFUNDED  => 'info',
        AdInvoice::STATUS_CANCELLED => 'secondary',
    ];

    $currencySymbol = $campaign->currency === 'GHS' ? '₵' : ($campaign->currency . ' ');
    $hasPendingRented = $campaign->rented_requested_at && !$campaign->rented_approved_at;
    $daysRemaining = ($campaign->ends_at && $campaign->ends_at->isFuture())
        ? (int) now()->diffInDays($campaign->ends_at)
        : 0;

    $canMarkRented = $campaign->status === AdCampaign::STATUS_ACTIVE
        && $campaign->ends_at
        && $campaign->ends_at->isFuture()
        && !$campaign->rented_requested_at;

    $canCancel = in_array($campaign->status, [AdCampaign::STATUS_ACTIVE, AdCampaign::STATUS_PAUSED], true);
    $canPay    = $campaign->status === AdCampaign::STATUS_PENDING_PAYMENT
                 && $invoices->where('status', AdInvoice::STATUS_PENDING)->isNotEmpty();

    // Homepage ad image
    $coverPhoto = null;
    if ($unit && !empty($unit->photos)) {
        $photos     = $unit->photos;
        $coverPhoto = $photos->firstWhere('is_cover', true) ?? $photos->first();
    }

    $coverUrl = null;
    if ($coverPhoto) {
        try {
            $disk = $coverPhoto->disk ?? 'public';
            $path = $coverPhoto->path ?? null;
            if ($path) {
                $coverUrl = \Illuminate\Support\Facades\Storage::disk($disk)->url($path);
            }
        } catch (\Throwable $e) {
            $coverUrl = null;
        }
    }
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- ==================== HEADER ==================== --}}
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-4">
            <div class="flex items-center min-w-0">
                <div class="w-16 h-16 rounded-full flex items-center justify-center border-2 mr-4 flex-shrink-0"
                     style="background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border-color: var(--primary);">
                    <i class="fas fa-bullhorn text-xl"></i>
                </div>
                <div class="min-w-0">
                    <h2 class="text-xl font-semibold flex items-center gap-2 flex-wrap" style="color: var(--text-primary);">
                        <span class="truncate">{{ $unitLabel }}</span>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium flex-shrink-0"
                              style="background: rgba(var(--{{ $statusColor }}-rgb), 0.15); color: var(--{{ $statusColor }});">
                            <i class="{{ $statusIcon }} mr-1"></i> {{ $statusLabel }}
                        </span>
                    </h2>
                    <div class="text-sm mt-1 flex items-center gap-2 flex-wrap" style="color: var(--text-secondary);">
                        <span><i class="fas fa-building mr-1"></i>{{ $propertyName }}</span>
                        @if($propertyAddr)
                            <span class="truncate"><i class="fas fa-map-marker-alt mr-1"></i>{{ $propertyAddr }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('landlord.ads.index') }}"
                   class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> My Campaigns
                </a>
                @if($canPay)
                    <a href="{{ route('landlord.ads.pay', $campaign->uuid) }}"
                       class="btn-primary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                        <i class="fas fa-credit-card mr-2"></i> Complete Payment
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- ==================== FLASH ==================== --}}
    @if(session('success'))
        <div class="card">
            <div class="p-4 rounded-lg flex items-center"
                 style="background-color: rgba(var(--success-rgb), 0.1); border-left: 4px solid var(--success);">
                <i class="fas fa-check-circle mr-3 text-xl" style="color: var(--success);"></i>
                <span style="color: var(--text-primary);">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="card">
            <div class="p-4 rounded-lg flex items-center"
                 style="background-color: rgba(var(--danger-rgb), 0.1); border-left: 4px solid var(--danger);">
                <i class="fas fa-times-circle mr-3 text-xl" style="color: var(--danger);"></i>
                <span style="color: var(--text-primary);">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    {{-- ==================== PENDING PAYMENT BANNER ==================== --}}
    @if($campaign->status === AdCampaign::STATUS_PENDING_PAYMENT)
        <div class="card" style="border-left: 4px solid var(--warning);">
            <div class="p-5 flex items-center gap-3 flex-wrap">
                <i class="fas fa-exclamation-circle text-2xl" style="color: var(--warning);"></i>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold" style="color: var(--text-primary);">Awaiting payment</p>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Your campaign will go live as soon as payment is confirmed. Complete payment to start advertising.
                    </p>
                </div>
                @if($canPay)
                    <a href="{{ route('landlord.ads.pay', $campaign->uuid) }}"
                       class="btn-primary px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center">
                        <i class="fas fa-credit-card mr-2"></i> Pay Now
                    </a>
                @endif
            </div>
        </div>
    @endif

    {{-- ==================== PENDING "MARK AS RENTED" BANNER ==================== --}}
    @if($hasPendingRented)
        <div class="card" style="border-left: 4px solid var(--info);">
            <div class="p-5 flex items-center gap-3 flex-wrap">
                <i class="fas fa-hourglass-half text-2xl" style="color: var(--info);"></i>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold" style="color: var(--text-primary);">Mark as rented — pending admin review</p>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        You requested to stop this campaign early on
                        <strong>{{ $campaign->rented_requested_at->format('M d, Y \a\t g:i A') }}</strong>.
                        An admin will review and confirm shortly.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- ==================== MAIN GRID ==================== --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ============ LEFT: Campaign details ============ --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Unit preview --}}
            <div class="card overflow-hidden">
                @if($coverUrl)
                    <img src="{{ $coverUrl }}"
                         alt="{{ $unitLabel }}"
                         class="w-full h-56 object-cover">
                @else
                    <div class="w-full h-56 flex items-center justify-center"
                         style="background: linear-gradient(135deg, var(--primary), var(--secondary));">
                        <i class="fas fa-building text-white text-5xl opacity-40"></i>
                    </div>
                @endif

                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-1" style="color: var(--text-primary);">
                        {{ $unitLabel }}
                    </h3>
                    @if($propertyAddr)
                        <p class="text-sm mb-3" style="color: var(--text-secondary);">
                            <i class="fas fa-map-marker-alt mr-1"></i>{{ $propertyAddr }}
                        </p>
                    @endif

                    <div class="flex items-center gap-4 flex-wrap text-sm" style="color: var(--text-secondary);">
                        @if($unit && $unit->bedrooms)
                            <span><i class="fas fa-bed mr-1"></i>{{ $unit->bedrooms }} bed</span>
                        @endif
                        @if($unit && $unit->bathrooms)
                            <span><i class="fas fa-bath mr-1"></i>{{ $unit->bathrooms }} bath</span>
                        @endif
                        @if($unit && $unit->monthly_rent)
                            <span class="font-semibold" style="color: var(--primary);">
                                <i class="fas fa-tag mr-1"></i>{{ $systemSettings->formatAmount($unit->monthly_rent ?? 0) }}/month
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Period & channels --}}
            <div class="card p-6">
                <h3 class="text-base font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-calendar-alt mr-2" style="color: var(--primary);"></i>
                    Campaign Period
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {{-- Start --}}
                    <div class="p-3 rounded-lg"
                         style="background: rgba(var(--success-rgb), 0.05);
                                border: 1px solid rgba(var(--success-rgb), 0.15);">
                        <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Starts</p>
                        <p class="text-sm font-semibold" style="color: var(--text-primary);">
                            {{ $campaign->starts_at?->format('M d, Y') ?? 'Pending payment' }}
                        </p>
                    </div>

                    {{-- Ends --}}
                    <div class="p-3 rounded-lg"
                         style="background: rgba(var(--danger-rgb), 0.05);
                                border: 1px solid rgba(var(--danger-rgb), 0.15);">
                        <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Ends</p>
                        <p class="text-sm font-semibold" style="color: var(--text-primary);">
                            {{ $campaign->ends_at?->format('M d, Y') ?? 'After payment' }}
                        </p>
                    </div>

                    {{-- Remaining --}}
                    <div class="p-3 rounded-lg"
                         style="background: rgba(var(--primary-rgb), 0.05);
                                border: 1px solid rgba(var(--primary-rgb), 0.15);">
                        <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Days remaining</p>
                        <p class="text-sm font-semibold"
                           style="color: {{ $daysRemaining > 0 ? 'var(--primary)' : 'var(--text-secondary)' }};">
                            {{ $daysRemaining > 0 ? $daysRemaining . ' days' : '—' }}
                        </p>
                    </div>
                </div>

                {{-- Channels --}}
                <div class="mt-5 pt-5" style="border-top: 1px solid var(--border-color);">
                    <p class="text-sm font-medium mb-3" style="color: var(--text-primary);">
                        <i class="fas fa-broadcast-tower mr-2" style="color: var(--info);"></i>
                        Publishing Channels
                    </p>

                    @if(empty($channels))
                        <p class="text-sm" style="color: var(--text-secondary);">No channels selected.</p>
                    @else
                        <div class="flex flex-wrap gap-2">
                            @foreach($channels as $channel)
                                @php
                                    [$cLabel, $cIcon, $cColor] = $channelMeta[$channel] ?? [ucfirst($channel), 'fas fa-circle', 'secondary'];
                                @endphp
                                <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium"
                                      style="background: rgba(var(--{{ $cColor }}-rgb), 0.1); color: var(--{{ $cColor }});">
                                    <i class="{{ $cIcon }} mr-1.5"></i> {{ $cLabel }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Performance --}}
            <div class="card p-6">
                <h3 class="text-base font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-chart-line mr-2" style="color: var(--success);"></i>
                    Performance
                </h3>

                <div class="grid grid-cols-2 gap-4">
                    <div class="p-4 rounded-lg text-center"
                         style="background: rgba(var(--primary-rgb), 0.05);
                                border: 1px solid rgba(var(--primary-rgb), 0.15);">
                        <p class="text-3xl font-bold" style="color: var(--primary);">
                            {{ number_format($campaign->views_count ?? 0) }}
                        </p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-eye mr-1"></i> Views
                        </p>
                    </div>

                    <div class="p-4 rounded-lg text-center"
                         style="background: rgba(var(--success-rgb), 0.05);
                                border: 1px solid rgba(var(--success-rgb), 0.15);">
                        <p class="text-3xl font-bold" style="color: var(--success);">
                            {{ number_format($campaign->clicks_count ?? 0) }}
                        </p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-mouse-pointer mr-1"></i> Clicks
                        </p>
                    </div>
                </div>
            </div>

            {{-- Invoice history --}}
            <div class="card p-6">
                <h3 class="text-base font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-file-invoice-dollar mr-2" style="color: var(--warning);"></i>
                    Invoices
                </h3>

                @if($invoices->isEmpty())
                    <p class="text-sm" style="color: var(--text-secondary);">No invoices yet.</p>
                @else
                    <div class="space-y-2">
                        @foreach($invoices as $invoice)
                            @php
                                $iColor = $invoiceStatusColors[$invoice->status] ?? 'secondary';
                                $iSym   = $invoice->currency === 'GHS' ? '₵' : ($invoice->currency . ' ');
                            @endphp
                            <div class="flex items-center justify-between p-3 rounded-lg"
                                 style="background: rgba(var(--secondary-rgb), 0.03);
                                        border: 1px solid var(--border-color);">
                                <div class="min-w-0">
                                    <p class="text-sm font-mono font-medium" style="color: var(--text-primary);">
                                        {{ $invoice->invoice_number }}
                                    </p>
                                    <p class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                        Created {{ $invoice->created_at?->format('M d, Y') ?? '—' }}
                                        @if($invoice->paid_at)
                                            · Paid {{ $invoice->paid_at->format('M d, Y') }}
                                        @endif
                                    </p>
                                </div>
                                <div class="text-right flex-shrink-0 ml-3">
                                    <p class="font-bold text-sm" style="color: var(--text-primary);">
                                        {{ $iSym }}{{ number_format((float) $invoice->amount, 2) }}
                                    </p>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                          style="background: rgba(var(--{{ $iColor }}-rgb), 0.15); color: var(--{{ $iColor }});">
                                        {{ ucfirst($invoice->status) }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- ============ RIGHT: Summary + actions ============ --}}
        <div class="lg:col-span-1 space-y-4">

            {{-- Plan summary --}}
            <div class="card p-6">
                <h3 class="text-base font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-tags mr-2" style="color: var(--primary);"></i>
                    Plan
                </h3>

                <div class="space-y-3">
                    <div>
                        <p class="text-xs font-medium" style="color: var(--text-secondary);">Plan name</p>
                        <p class="text-sm font-semibold mt-0.5" style="color: var(--text-primary);">
                            {{ $campaign->plan_name }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-2"
                         style="border-top: 1px solid var(--border-color);">
                        <div>
                            <p class="text-xs font-medium" style="color: var(--text-secondary);">Billing</p>
                            <p class="text-sm capitalize mt-0.5" style="color: var(--text-primary);">
                                {{ $campaign->billing_period }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-medium" style="color: var(--text-secondary);">Duration</p>
                            <p class="text-sm mt-0.5" style="color: var(--text-primary);">
                                {{ $campaign->duration_months }} mo
                            </p>
                        </div>
                    </div>

                    <div class="pt-3" style="border-top: 1px solid var(--border-color);">
                        <div class="flex justify-between text-sm mb-1">
                            <span style="color: var(--text-secondary);">Unit price</span>
                            <span style="color: var(--text-primary);">
                                {{ $currencySymbol }}{{ number_format((float) $campaign->unit_price_snapshot, 2) }}
                            </span>
                        </div>
                        <div class="flex justify-between text-sm font-bold pt-2"
                             style="border-top: 1px solid var(--border-color);">
                            <span style="color: var(--text-primary);">Total paid</span>
                            <span style="color: var(--primary);">
                                {{ $currencySymbol }}{{ number_format((float) $campaign->total_paid, 2) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            @if($canMarkRented || $canCancel)
                <div class="card p-6">
                    <h3 class="text-base font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-bolt mr-2" style="color: var(--warning);"></i>
                        Actions
                    </h3>

                    <div class="space-y-2">
                        @if($canMarkRented)
                            <form method="POST"
                                  action="{{ route('landlord.ads.mark-rented', $campaign->uuid) }}"
                                  onsubmit="return confirm('Mark this campaign as rented?\n\nYour ad will stop immediately and no further charges will be made. An admin will review and confirm.');">
                                @csrf
                                <button type="submit"
                                        class="w-full px-4 py-2.5 rounded-lg text-sm font-medium inline-flex items-center justify-center"
                                        style="background: var(--success); color: white;">
                                    <i class="fas fa-handshake mr-2"></i> Mark as Rented
                                </button>
                            </form>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Stops the ad immediately. No refund for the current period, but no future charges.
                            </p>
                        @endif

                        @if($canCancel)
                            <details class="mt-2">
                                <summary class="w-full px-4 py-2.5 rounded-lg text-sm font-medium inline-flex items-center justify-center cursor-pointer list-none"
                                         style="background: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                    <i class="fas fa-ban mr-2"></i> Cancel Campaign
                                </summary>
                                <div class="mt-2 p-3 rounded-lg"
                                     style="background: var(--bg-secondary);
                                            border: 1px solid var(--border-color);">
                                    <form method="POST"
                                          action="{{ route('landlord.ads.cancel', $campaign->uuid) }}"
                                          onsubmit="return confirm('Cancel this campaign? This cannot be undone.');">
                                        @csrf
                                        <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                            Reason (optional)
                                        </label>
                                        <textarea name="reason"
                                                  rows="2"
                                                  maxlength="500"
                                                  placeholder="e.g. Property no longer available"
                                                  class="form-input w-full px-2 py-1.5 rounded text-sm mb-2"></textarea>
                                        <button type="submit"
                                                class="w-full px-3 py-2 rounded text-xs font-medium"
                                                style="background: var(--danger); color: white;">
                                            Confirm Cancel
                                        </button>
                                    </form>
                                </div>
                            </details>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Timeline --}}
            <div class="card p-6">
                <h3 class="text-base font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-history mr-2" style="color: var(--info);"></i>
                    Timeline
                </h3>

                <div class="space-y-3">
                    <div class="flex gap-3">
                        <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center"
                             style="background: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            <i class="fas fa-plus text-xs"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium" style="color: var(--text-primary);">
                                Campaign created
                            </p>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                {{ $campaign->created_at->format('M d, Y \a\t g:i A') }}
                            </p>
                        </div>
                    </div>

                    @if($campaign->approved_at)
                        <div class="flex gap-3">
                            <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center"
                                 style="background: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                <i class="fas fa-check text-xs"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium" style="color: var(--text-primary);">
                                    Payment confirmed
                                </p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    {{ $campaign->approved_at->format('M d, Y \a\t g:i A') }}
                                </p>
                            </div>
                        </div>
                    @endif

                    @if($campaign->rented_requested_at)
                        <div class="flex gap-3">
                            <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center"
                                 style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-hourglass-half text-xs"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium" style="color: var(--text-primary);">
                                    Mark-as-rented requested
                                </p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    {{ $campaign->rented_requested_at->format('M d, Y \a\t g:i A') }}
                                </p>
                            </div>
                        </div>
                    @endif

                    @if($campaign->rented_approved_at)
                        <div class="flex gap-3">
                            <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center"
                                 style="background: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                <i class="fas fa-handshake text-xs"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium" style="color: var(--text-primary);">
                                    Confirmed as rented
                                </p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    {{ $campaign->rented_approved_at->format('M d, Y \a\t g:i A') }}
                                </p>
                            </div>
                        </div>
                    @endif

                    @if($campaign->cancelled_at)
                        <div class="flex gap-3">
                            <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center"
                                 style="background: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                <i class="fas fa-ban text-xs"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium" style="color: var(--text-primary);">
                                    Campaign cancelled
                                </p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    {{ $campaign->cancelled_at->format('M d, Y \a\t g:i A') }}
                                </p>
                                @if($campaign->cancel_reason)
                                    <p class="text-xs mt-0.5 italic" style="color: var(--text-secondary);">
                                        "{{ $campaign->cancel_reason }}"
                                    </p>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Reference --}}
            <div class="card p-4">
                <p class="text-xs mb-1" style="color: var(--text-secondary);">
                    <i class="fas fa-fingerprint mr-1"></i> Campaign ID
                </p>
                <p class="font-mono text-xs break-all" style="color: var(--text-primary);">
                    {{ $campaign->uuid }}
                </p>
            </div>
        </div>
    </div>
</div>
@endsection