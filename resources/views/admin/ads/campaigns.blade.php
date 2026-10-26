{{-- resources/views/admin/ads/campaigns.blade.php --}}
@extends('layouts.app')

@section('title', 'Ad Campaigns — Admin Review')

@php
    use App\Models\AdCampaign;
    use App\Models\AdInvoice;
    use Illuminate\Support\Str;

    // Defensive: the controller passes $campaigns (paginator) and $statusOptions (array).
    // If either is missing, fall back so the view never 500s.
    $campaigns     = $campaigns     ?? collect();
    $statusOptions = $statusOptions ?? [
        AdCampaign::STATUS_DRAFT           => 'Draft',
        AdCampaign::STATUS_PENDING_PAYMENT => 'Pending Payment',
        AdCampaign::STATUS_ACTIVE          => 'Active',
        AdCampaign::STATUS_PAUSED          => 'Paused',
        AdCampaign::STATUS_RENTED_EARLY    => 'Rented (Stopped Early)',
        AdCampaign::STATUS_EXPIRED         => 'Expired',
        AdCampaign::STATUS_CANCELLED       => 'Cancelled',
        AdCampaign::STATUS_REJECTED        => 'Rejected',
    ];

    // Status → badge colour map (mirrors the rest of the dashboard)
    $statusColors = [
        AdCampaign::STATUS_DRAFT           => 'secondary',
        AdCampaign::STATUS_PENDING_PAYMENT => 'warning',
        AdCampaign::STATUS_ACTIVE          => 'success',
        AdCampaign::STATUS_PAUSED          => 'secondary',
        AdCampaign::STATUS_RENTED_EARLY    => 'info',
        AdCampaign::STATUS_EXPIRED         => 'secondary',
        AdCampaign::STATUS_CANCELLED       => 'danger',
        AdCampaign::STATUS_REJECTED        => 'danger',
    ];

    $filterStatus   = request('status', '');
    $pendingRented  = request()->boolean('pending_rented');
    $searchTerm     = request('search', '');
    $totalCampaigns = method_exists($campaigns, 'total') ? $campaigns->total() : $campaigns->count();

    // 💰 INVOICES — defensive route check so a partially-migrated
    // deployment doesn't 500 when the route isn't registered yet.
    $canAccessInvoices = \Illuminate\Support\Facades\Route::has('admin.homepage-content.ads.invoices');

    // 💬 INQUIRIES — same defensive gate. Admins reach the platform-wide
    // inquiry inbox (all landlords) from this review page too.
    $canAccessInquiries = \Illuminate\Support\Facades\Route::has('admin.inquiries.index');
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- ==================== HEADER ==================== --}}
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-4">
            <div class="flex items-center">
                <div class="w-16 h-16 rounded-full flex items-center justify-center border-2 mr-4"
                     style="background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border-color: var(--primary);">
                    <i class="fas fa-bullhorn text-xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center gap-2" style="color: var(--text-primary);">
                        <i class="fas fa-bullhorn mr-2" style="color: var(--primary);"></i>
                        Ad Campaigns
                    </h2>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-list-check mr-2"></i>
                        Review, approve, and manage landlord ad campaigns
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">

                {{-- 💬 Admin inquiries inbox — platform-wide --}}
                @if($canAccessInquiries)
                    <a href="{{ route('admin.inquiries.index') }}"
                       class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       title="View every unit inquiry submitted across the platform">
                        <i class="fas fa-envelope-open-text mr-2"></i> Inquiries
                    </a>
                @endif

                {{-- 💰 Ad Invoices — secondary entry point from the review list --}}
                @if($canAccessInvoices)
                    <a href="{{ route('admin.homepage-content.ads.invoices') }}"
                       class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       title="View all ad invoices (revenue) across landlords">
                        <i class="fas fa-file-invoice-dollar mr-2"></i> Ad Invoices
                    </a>
                @endif

                <a href="{{ route('admin.homepage-content.ads.settings') }}?tab=ads"
                   class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                    <i class="fas fa-cog mr-2"></i> Ad Settings
                </a>
                <a href="{{ route('admin.homepage-content.index') }}?tab=ads"
                   class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </div>
        </div>
    </div>

    {{-- ==================== FLASH MESSAGES ==================== --}}
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

    @if($errors->any())
        <div class="card">
            <div class="p-4 rounded-lg"
                 style="background-color: rgba(var(--danger-rgb), 0.1); border-left: 4px solid var(--danger);">
                <p class="font-semibold mb-2" style="color: var(--danger);">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Please fix the following:
                </p>
                <ul class="text-sm list-disc pl-6" style="color: var(--text-primary);">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{-- ==================== FILTERS ==================== --}}
    <div class="card">
        <div class="p-5">
            <form method="GET" action="{{ route('admin.homepage-content.ads.campaigns') }}"
                  class="grid grid-cols-1 md:grid-cols-4 gap-3">

                {{-- Search --}}
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                        Search
                    </label>
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-sm"
                           style="color: var(--text-secondary);"></i>
                        <input type="text"
                               name="search"
                               value="{{ $searchTerm }}"
                               placeholder="Landlord name, unit number, unit name…"
                               class="form-input w-full pl-9 pr-3 py-2 rounded-lg">
                    </div>
                </div>

                {{-- Status --}}
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                        Status
                    </label>
                    <select name="status" class="form-input w-full px-3 py-2 rounded-lg">
                        <option value="">All statuses</option>
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filterStatus === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Pending review + submit --}}
                <div class="flex flex-col justify-end gap-2">
                    <label class="flex items-center gap-2 cursor-pointer text-sm"
                           style="color: var(--text-primary);">
                        <input type="hidden" name="pending_rented" value="0">
                        <input type="checkbox" name="pending_rented" value="1"
                               @checked($pendingRented) class="w-4 h-4">
                        Only pending "rented" requests
                    </label>
                    <button type="submit"
                            class="btn-primary px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center justify-center">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                </div>
            </form>

            @if($searchTerm || $filterStatus || $pendingRented)
                <div class="mt-3 flex items-center gap-2 flex-wrap">
                    <span class="text-xs" style="color: var(--text-secondary);">Active filters:</span>

                    @if($searchTerm)
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs"
                              style="background: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            <i class="fas fa-search"></i> "{{ $searchTerm }}"
                        </span>
                    @endif

                    @if($filterStatus)
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs"
                              style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            <i class="fas fa-tag"></i>
                            {{ $statusOptions[$filterStatus] ?? $filterStatus }}
                        </span>
                    @endif

                    @if($pendingRented)
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs"
                              style="background: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                            <i class="fas fa-clock"></i> Pending "rented" review
                        </span>
                    @endif

                    <a href="{{ route('admin.homepage-content.ads.campaigns') }}"
                       class="text-xs underline ml-2" style="color: var(--text-secondary);">
                        Clear
                    </a>
                </div>
            @endif
        </div>
    </div>

    {{-- ==================== CAMPAIGNS LIST ==================== --}}
    <div class="card">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-list mr-2"></i>
                    Campaigns
                    <span class="ml-2 px-2 py-0.5 rounded-full text-xs font-medium"
                          style="background: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        {{ $totalCampaigns }}
                    </span>
                </h3>

                <div class="flex items-center gap-2 flex-wrap">
                    {{-- 💬 Inquiries — quick access from the campaigns list --}}
                    @if($canAccessInquiries)
                        <a href="{{ route('admin.inquiries.index') }}"
                           class="text-xs font-medium inline-flex items-center px-2.5 py-1 rounded-lg"
                           style="background: rgba(var(--info-rgb), 0.08); color: var(--info);">
                            <i class="fas fa-envelope-open-text mr-1"></i> Inquiries
                        </a>
                    @endif

                    {{-- 💰 Quick invoices access (mirror of header button) --}}
                    @if($canAccessInvoices)
                        <a href="{{ route('admin.homepage-content.ads.invoices') }}"
                           class="text-xs font-medium inline-flex items-center px-2.5 py-1 rounded-lg"
                           style="background: rgba(var(--primary-rgb), 0.08); color: var(--primary);">
                            <i class="fas fa-file-invoice-dollar mr-1"></i> View invoices
                        </a>
                    @endif
                </div>
            </div>

            @if($campaigns->isEmpty())
                <div class="text-center py-16 rounded-lg"
                     style="background-color: rgba(var(--secondary-rgb), 0.03);
                            border: 1px dashed var(--border-color);">
                    <i class="fas fa-bullhorn text-5xl mb-4"
                       style="color: var(--text-secondary); opacity: 0.35;"></i>
                    <p class="text-base font-medium mb-1" style="color: var(--text-primary);">
                        No campaigns found
                    </p>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        @if($searchTerm || $filterStatus || $pendingRented)
                            Try clearing your filters to see all campaigns.
                        @else
                            Campaigns will appear here once landlords start advertising.
                        @endif
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border-color);">
                                <th class="text-left py-3 px-2 font-semibold" style="color: var(--text-primary);">Unit / Landlord</th>
                                <th class="text-left py-3 px-2 font-semibold" style="color: var(--text-primary);">Plan</th>
                                <th class="text-left py-3 px-2 font-semibold" style="color: var(--text-primary);">Channels</th>
                                <th class="text-right py-3 px-2 font-semibold" style="color: var(--text-primary);">Paid</th>
                                <th class="text-left py-3 px-2 font-semibold" style="color: var(--text-primary);">Period</th>
                                <th class="text-left py-3 px-2 font-semibold" style="color: var(--text-primary);">Status</th>
                                <th class="text-right py-3 px-2 font-semibold" style="color: var(--text-primary);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($campaigns as $campaign)
                                @php
                                    $unit         = $campaign->unit;
                                    $property     = $unit?->property;
                                    $landlord     = $campaign->landlord;
                                    $unitLabel    = $unit?->unit_name ?? $unit?->unit_number ?? 'Unit #' . $campaign->property_unit_id;
                                    $propertyName = $property?->property_name ?? 'Unknown property';
                                    $channels     = is_array($campaign->channels) ? $campaign->channels : [];
                                    $statusColor  = $statusColors[$campaign->status] ?? 'secondary';
                                    $statusLabel  = $statusOptions[$campaign->status] ?? ucfirst($campaign->status);
                                    $hasPendingRented = $campaign->rented_requested_at && !$campaign->rented_approved_at;

                                    // ✅ Deletion is only safe when the campaign has
                                    //    no financial footprint (no paid invoices) and
                                    //    isn't currently holding a unit hostage.
                                    $hasAnyInvoices    = $campaign->invoices()->exists();
                                    $hasPaidInvoices   = $campaign->invoices()
                                        ->where('status', AdInvoice::STATUS_PAID)
                                        ->exists();
                                    $isActiveOrPending = in_array($campaign->status, [
                                        AdCampaign::STATUS_ACTIVE,
                                        AdCampaign::STATUS_PENDING_PAYMENT,
                                    ], true);

                                    // Block reason (empty string = deletable)
                                    $deleteBlockReason = '';
                                    if ($hasPaidInvoices) {
                                        $deleteBlockReason = 'This campaign has paid invoices. Cancel it instead to preserve the financial record.';
                                    } elseif ($hasAnyInvoices) {
                                        $deleteBlockReason = 'This campaign has invoices. Cancel it instead to keep an audit trail.';
                                    } elseif ($isActiveOrPending) {
                                        $deleteBlockReason = 'Active or pending campaigns must be cancelled before they can be deleted.';
                                    }
                                    $canDelete = $deleteBlockReason === '';

                                    // 💰 Invoice lookup — reuse the already-loaded count
                                    //    above so we don't hit the DB twice.
                                    $invoiceCount = $hasAnyInvoices ? 1 : 0; // placeholder; not shown numerically
                                @endphp
                                <tr style="border-bottom: 1px solid var(--border-color); vertical-align: top;">
                                    {{-- Unit / Landlord --}}
                                    <td class="py-3 px-2">
                                        <div class="font-medium" style="color: var(--text-primary);">
                                            {{ $unitLabel }}
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-building mr-1"></i>{{ $propertyName }}
                                        </div>
                                        <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                            <i class="fas fa-user mr-1"></i>{{ $landlord?->name ?? 'Unknown landlord' }}
                                        </div>
                                    </td>

                                    {{-- Plan --}}
                                    <td class="py-3 px-2">
                                        <div class="text-sm" style="color: var(--text-primary);">
                                            {{ $campaign->plan_name }}
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            {{ $campaign->duration_months }} mo · {{ ucfirst($campaign->billing_period) }}
                                        </div>
                                    </td>

                                    {{-- Channels --}}
                                    <td class="py-3 px-2">
                                        <div class="flex flex-wrap gap-1">
                                            @forelse($channels as $channel)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium"
                                                      style="background: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                                                    @if($channel === 'homepage')<i class="fas fa-home mr-1"></i>
                                                    @elseif($channel === 'facebook')<i class="fab fa-facebook-f mr-1"></i>
                                                    @elseif($channel === 'whatsapp')<i class="fab fa-whatsapp mr-1"></i>
                                                    @elseif($channel === 'twitter')<i class="fab fa-x-twitter mr-1"></i>
                                                    @elseif($channel === 'linkedin')<i class="fab fa-linkedin-in mr-1"></i>
                                                    @elseif($channel === 'instagram')<i class="fab fa-instagram mr-1"></i>
                                                    @endif
                                                    {{ $channel }}
                                                </span>
                                            @empty
                                                <span class="text-xs" style="color: var(--text-secondary);">—</span>
                                            @endforelse
                                        </div>
                                    </td>

                                    {{-- Paid --}}
                                    <td class="py-3 px-2 text-right">
                                        <div class="font-semibold" style="color: var(--primary);">
                                            {{ $campaign->currency === 'GHS' ? '₵' : $campaign->currency . ' ' }}{{ number_format((float) $campaign->total_paid, 2) }}
                                        </div>
                                        <div class="text-[10px]" style="color: var(--text-secondary);">
                                            of {{ $campaign->currency === 'GHS' ? '₵' : '' }}{{ number_format((float) $campaign->unit_price_snapshot, 2) }}
                                        </div>
                                    </td>

                                    {{-- Period --}}
                                    <td class="py-3 px-2">
                                        @if($campaign->starts_at && $campaign->ends_at)
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                <i class="fas fa-play text-[10px] mr-1" style="color: var(--success);"></i>
                                                {{ $campaign->starts_at->format('M d, Y') }}
                                            </div>
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                <i class="fas fa-stop text-[10px] mr-1" style="color: var(--danger);"></i>
                                                {{ $campaign->ends_at->format('M d, Y') }}
                                            </div>
                                        @else
                                            <span class="text-xs" style="color: var(--text-secondary);">—</span>
                                        @endif
                                    </td>

                                    {{-- Status --}}
                                    <td class="py-3 px-2">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                              style="background: rgba(var(--{{ $statusColor }}-rgb), 0.15);
                                                     color: var(--{{ $statusColor }});">
                                            @if($campaign->status === AdCampaign::STATUS_ACTIVE)<i class="fas fa-play-circle mr-1"></i>
                                            @elseif($campaign->status === AdCampaign::STATUS_PENDING_PAYMENT)<i class="fas fa-clock mr-1"></i>
                                            @elseif($campaign->status === AdCampaign::STATUS_RENTED_EARLY)<i class="fas fa-handshake mr-1"></i>
                                            @elseif($campaign->status === AdCampaign::STATUS_EXPIRED)<i class="fas fa-calendar-times mr-1"></i>
                                            @elseif($campaign->status === AdCampaign::STATUS_CANCELLED)<i class="fas fa-ban mr-1"></i>
                                            @elseif($campaign->status === AdCampaign::STATUS_REJECTED)<i class="fas fa-times-circle mr-1"></i>
                                            @else<i class="fas fa-info-circle mr-1"></i>
                                            @endif
                                            {{ $statusLabel }}
                                        </span>

                                        @if($hasPendingRented)
                                            <div class="mt-1">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                                      style="background: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                                    Review required
                                                </span>
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Actions --}}
                                    <td class="py-3 px-2 text-right">
                                        <div class="inline-flex flex-col items-end gap-1">

                                            {{-- 💰 View invoices for this specific campaign --}}
                                            @if($canAccessInvoices && $hasAnyInvoices)
                                                <a href="{{ route('admin.homepage-content.ads.invoices', ['campaign_id' => $campaign->id]) }}"
                                                   class="px-2.5 py-1 rounded text-xs font-medium w-full text-center inline-flex items-center justify-center"
                                                   style="background: rgba(var(--primary-rgb), 0.08); color: var(--primary);"
                                                   title="View the financial trail for this campaign">
                                                    <i class="fas fa-file-invoice-dollar mr-1"></i> Invoices
                                                </a>
                                            @endif

                                            {{-- Approve Rented --}}
                                            @if($hasPendingRented)
                                                <form method="POST"
                                                      action="{{ route('admin.homepage-content.ads.campaigns.approve-rented', $campaign->id) }}"
                                                      onsubmit="return confirm('Approve mark-as-rented for this campaign?\n\nThe ad will stop immediately and the unit will be marked occupied.');">
                                                    @csrf
                                                    <button type="submit"
                                                            class="px-2.5 py-1 rounded text-xs font-medium w-full"
                                                            style="background: var(--success); color: white;">
                                                        <i class="fas fa-check mr-1"></i> Approve Rented
                                                    </button>
                                                </form>
                                            @endif

                                            {{-- Cancel --}}
                                            @php
                                                $terminalStatuses = [
                                                    AdCampaign::STATUS_CANCELLED,
                                                    AdCampaign::STATUS_EXPIRED,
                                                    AdCampaign::STATUS_RENTED_EARLY,
                                                    AdCampaign::STATUS_REJECTED,
                                                ];
                                                $canCancel = !in_array($campaign->status, $terminalStatuses, true);
                                            @endphp

                                            @if($canCancel)
                                                <details class="inline-block text-left w-full">
                                                    <summary class="px-2.5 py-1 rounded text-xs font-medium cursor-pointer list-none text-center"
                                                             style="background: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                        <i class="fas fa-ban mr-1"></i> Cancel
                                                    </summary>
                                                    <div class="mt-1 p-2 rounded-lg shadow-lg"
                                                         style="background: var(--card-bg);
                                                                border: 1px solid var(--border-color);
                                                                min-width: 220px;">
                                                        <form method="POST"
                                                              action="{{ route('admin.homepage-content.ads.campaigns.cancel', $campaign->id) }}"
                                                              onsubmit="return confirm('Cancel this campaign? This cannot be undone.');">
                                                            @csrf
                                                            <label class="block text-xs font-medium mb-1"
                                                                   style="color: var(--text-secondary);">
                                                                Reason (optional)
                                                            </label>
                                                            <textarea name="reason"
                                                                      rows="2"
                                                                      maxlength="500"
                                                                      placeholder="e.g. Policy violation"
                                                                      class="form-input w-full px-2 py-1 rounded text-xs mb-2"></textarea>
                                                            <button type="submit"
                                                                    class="w-full px-2 py-1 rounded text-xs font-medium"
                                                                    style="background: var(--warning); color: white;">
                                                                Confirm Cancel
                                                            </button>
                                                        </form>
                                                    </div>
                                                </details>
                                            @endif

                                            {{-- Delete --}}
                                            @if($canDelete)
                                                <form method="POST"
                                                      action="{{ route('admin.homepage-content.ads.campaigns.destroy', $campaign->id) }}"
                                                      onsubmit="return confirm('Permanently delete this campaign?\n\nThis cannot be undone. The unit is released and the audit trail for this campaign is removed.');"
                                                      class="w-full">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="px-2.5 py-1 rounded text-xs font-medium w-full"
                                                            style="background: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                                        <i class="fas fa-trash-alt mr-1"></i> Delete
                                                    </button>
                                                </form>
                                            @else
                                                {{-- Show a disabled button with tooltip explaining why --}}
                                                <button type="button"
                                                        disabled
                                                        title="{{ $deleteBlockReason }}"
                                                        class="px-2.5 py-1 rounded text-xs font-medium w-full cursor-not-allowed"
                                                        style="background: rgba(var(--danger-rgb), 0.05);
                                                               color: var(--danger);
                                                               opacity: 0.4;
                                                               border: 1px dashed rgba(var(--danger-rgb), 0.3);">
                                                    <i class="fas fa-trash-alt mr-1"></i> Delete
                                                </button>
                                                <p class="text-[10px] mt-0.5 text-right max-w-[180px]"
                                                   style="color: var(--text-secondary);"
                                                   title="{{ $deleteBlockReason }}">
                                                    {{ $deleteBlockReason }}
                                                </p>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="mt-6 flex justify-between items-center flex-wrap gap-3">
                    <div class="text-xs" style="color: var(--text-secondary);">
                        @if(method_exists($campaigns, 'firstItem'))
                            Showing {{ $campaigns->firstItem() }}–{{ $campaigns->lastItem() }}
                            of {{ $campaigns->total() }} campaigns
                        @else
                            {{ $campaigns->count() }} campaigns
                        @endif
                    </div>
                    <div>
                        {{ $campaigns->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Close any open <details> dropdowns when clicking outside
    document.addEventListener('click', function (e) {
        document.querySelectorAll('details[open]').forEach(function (details) {
            if (!details.contains(e.target)) {
                details.removeAttribute('open');
            }
        });
    });

    // Auto-hide flash messages after 5 seconds
    setTimeout(function () {
        document.querySelectorAll('.card').forEach(function (card) {
            var flash = card.querySelector('span[style*="color: var(--text-primary)"]');
            if (flash && (flash.textContent.includes('✅') || flash.textContent.includes('❌'))) {
                card.style.transition = 'opacity 0.4s ease';
                card.style.opacity = '0';
                setTimeout(function () { card.style.display = 'none'; }, 400);
            }
        });
    }, 5000);
});
</script>
@endsection