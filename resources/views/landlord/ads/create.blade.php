{{-- resources/views/landlord/ads/create.blade.php --}}
@extends('layouts.landlord')

@section('title', 'Create Ad Campaign')

@php
    // ⭐ Preselect a sensible default so the form can always be submitted.
    //    1. If old() has a plan → keep it (validation-failure path)
    //    2. Otherwise → preselect the first available plan
    //    3. If neither → leave null (empty plans case is handled separately)
    $defaultPlanId = old('ad_pricing_plan_id')
        ?? ($plans->isNotEmpty() ? $plans->first()->id : null);

    $defaultUnitId = old('property_unit_id');

    // ⭐ Channels default: homepage if it's enabled and available,
    //    otherwise the first enabled channel.
    $enabledChannels = collect($config['channels'] ?? [])
        ->filter(fn ($on) => !empty($on))
        ->keys()
        ->values()
        ->all();

    $defaultChannels = old('channels')
        ? (array) old('channels')
        : (in_array('homepage', $enabledChannels, true)
            ? ['homepage']
            : array_slice($enabledChannels, 0, 1));
@endphp

@section('content')
<div class="max-w-3xl mx-auto py-8 px-4">
    <h1 class="text-2xl font-bold mb-6" style="color: var(--text-primary);">Create Ad Campaign</h1>

    {{-- ============ FLASH + VALIDATION ERRORS ============ --}}
    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg"
             style="background: rgba(var(--danger-rgb), 0.1); border-left: 4px solid var(--danger);">
            <div class="flex items-start">
                <i class="fas fa-exclamation-circle mr-2 mt-0.5" style="color: var(--danger);"></i>
                <span class="text-sm" style="color: var(--text-primary);">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 p-4 rounded-lg"
             style="background: rgba(var(--danger-rgb), 0.1); border-left: 4px solid var(--danger);">
            <div class="flex items-start">
                <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                <div>
                    <p class="font-semibold text-sm mb-1" style="color: var(--danger);">Please fix the following:</p>
                    <ul class="list-disc list-inside text-sm" style="color: var(--text-primary);">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- ============ CLIENT-SIDE VALIDATION BANNER (hidden until needed) ============ --}}
    <div id="clientValidationBanner"
         class="mb-4 p-4 rounded-lg hidden"
         style="background: rgba(var(--warning-rgb), 0.1); border-left: 4px solid var(--warning);">
        <div class="flex items-start">
            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--warning);"></i>
            <div>
                <p class="font-semibold text-sm mb-1" style="color: var(--warning);">Almost there</p>
                <ul id="clientValidationErrors" class="list-disc list-inside text-sm"
                    style="color: var(--text-primary);"></ul>
            </div>
        </div>
    </div>

    @if($availableUnits->isEmpty())
        <div class="card p-8 text-center">
            <i class="fas fa-home text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.4;"></i>
            <p class="mb-2 font-medium" style="color: var(--text-primary);">
                You don't have any available units to advertise right now.
            </p>
            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                Add a unit or mark one as available, then come back to create a campaign.
            </p>
            <a href="{{ route('landlord.property-units.index') }}"
               class="inline-block px-4 py-2 rounded-lg text-sm font-medium"
               style="background: var(--primary); color: white;">
                <i class="fas fa-building mr-2"></i> Manage Units
            </a>
        </div>
    @elseif($plans->isEmpty())
        <div class="card p-8 text-center">
            <i class="fas fa-tags text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.4;"></i>
            <p class="font-medium mb-2" style="color: var(--text-primary);">
                No pricing plans are currently available.
            </p>
            <p class="text-sm" style="color: var(--text-secondary);">
                Contact an administrator to have ad pricing plans configured.
            </p>
        </div>
    @else
        <form method="POST"
              action="{{ route('landlord.ads.store') }}"
              enctype="multipart/form-data"
              class="card p-6 space-y-6"
              id="createAdCampaignForm"
              novalidate>
            @csrf

            {{-- ============ PROPERTY UNIT ============ --}}
            <div>
                <label for="property_unit_id"
                       class="block text-sm font-medium mb-2"
                       style="color: var(--text-primary);">
                    <i class="fas fa-home mr-1" style="color: var(--primary);"></i>
                    Property Unit <span style="color: var(--danger);">*</span>
                </label>
                <select name="property_unit_id"
                        id="property_unit_id"
                        required
                        class="w-full px-3 py-2 rounded-lg border @error('property_unit_id') border-red-500 @enderror"
                        style="background: var(--bg-primary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="">Select a unit</option>
                    @foreach($availableUnits as $unit)
                        <option value="{{ $unit->id }}" @selected((string) $defaultUnitId === (string) $unit->id)>
                            {{ $unit->property->property_name }} — {{ $unit->unit_name ?? $unit->unit_number }}
                            ({{ $unit->rent_formatted ?? '₵' . number_format($unit->monthly_rent, 2) }}/month)
                        </option>
                    @endforeach
                </select>
                @error('property_unit_id')
                    <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                @enderror
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    Only units marked as <strong>available</strong> can be advertised.
                </p>
            </div>

            {{-- ============ PRICING PLAN ============ --}}
            <div>
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                    <i class="fas fa-tags mr-1" style="color: var(--primary);"></i>
                    Pricing Plan <span style="color: var(--danger);">*</span>
                </label>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3" id="planCards">
                    @foreach($plans as $plan)
                        @php
                            $isSelected = ((string) $defaultPlanId === (string) $plan->id);
                        @endphp
                        <label class="block p-4 rounded-lg border-2 cursor-pointer transition-all hover:shadow-md plan-card"
                               data-plan-id="{{ $plan->id }}"
                               style="border-color: {{ $isSelected ? 'var(--primary)' : 'var(--border-color)' }};
                                      background: {{ $isSelected ? 'rgba(var(--primary-rgb), 0.05)' : 'var(--bg-primary)' }};">
                            {{-- Radio is visually hidden but still clickable via the label --}}
                            <input type="radio"
                                   name="ad_pricing_plan_id"
                                   id="plan_{{ $plan->id }}"
                                   value="{{ $plan->id }}"
                                   @checked($isSelected)
                                   required
                                   class="sr-only plan-radio">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="font-semibold" style="color: var(--text-primary);">
                                        {{ $plan->name }}
                                    </span>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        {{ $plan->duration_months }} month(s) · {{ ucfirst($plan->billing_period) }}
                                    </p>
                                </div>
                                <span class="w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0 plan-indicator"
                                      style="border-color: {{ $isSelected ? 'var(--primary)' : 'var(--border-color)' }};">
                                    <span class="w-2.5 h-2.5 rounded-full plan-indicator-dot"
                                          style="background: {{ $isSelected ? 'var(--primary)' : 'transparent' }};"></span>
                                </span>
                            </div>
                            <p class="text-2xl font-bold mt-3" style="color: var(--primary);">
                                {{ $plan->formatted_price }}
                            </p>
                            @if($plan->description)
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    {{ $plan->description }}
                                </p>
                            @endif
                        </label>
                    @endforeach
                </div>
                @error('ad_pricing_plan_id')
                    <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                @enderror
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    Click anywhere on a plan card to select it.
                </p>
            </div>

            {{-- ============ CHANNELS ============ --}}
            <div>
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                    <i class="fas fa-broadcast-tower mr-1" style="color: var(--primary);"></i>
                    Channels <span style="color: var(--danger);">*</span>
                </label>

                @if(empty($enabledChannels))
                    <div class="p-4 rounded-lg"
                         style="background: rgba(var(--warning-rgb), 0.08); border-left: 4px solid var(--warning);">
                        <p class="text-sm" style="color: var(--text-primary);">
                            <i class="fas fa-exclamation-triangle mr-1" style="color: var(--warning);"></i>
                            No channels are enabled by the administrator. Contact support.
                        </p>
                    </div>
                @else
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                        @foreach($config['channels'] as $channel => $enabled)
                            @if($enabled)
                                @php
                                    $isChecked = in_array($channel, $defaultChannels, true);
                                    $icon = match ($channel) {
                                        'homepage'  => 'fas fa-home',
                                        'facebook'  => 'fab fa-facebook-f',
                                        'whatsapp'  => 'fab fa-whatsapp',
                                        'twitter'   => 'fab fa-x-twitter',
                                        'linkedin'  => 'fab fa-linkedin-in',
                                        'instagram' => 'fab fa-instagram',
                                        default     => 'fas fa-share-alt',
                                    };
                                    $label = ucfirst($channel);
                                @endphp
                                <label class="flex items-center p-3 rounded-lg border cursor-pointer transition-all hover:shadow-sm channel-card"
                                       style="border-color: {{ $isChecked ? 'var(--primary)' : 'var(--border-color)' }};
                                              background: {{ $isChecked ? 'rgba(var(--primary-rgb), 0.05)' : 'var(--bg-primary)' }};">
                                    <input type="checkbox"
                                           name="channels[]"
                                           value="{{ $channel }}"
                                           @checked($isChecked)
                                           class="mr-2 channel-checkbox"
                                           style="accent-color: var(--primary);">
                                    <i class="{{ $icon }} mr-2" style="color: var(--primary);"></i>
                                    <span class="text-sm" style="color: var(--text-primary);">{{ $label }}</span>
                                </label>
                            @endif
                        @endforeach
                    </div>
                    @error('channels')
                        <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        <strong>Homepage</strong> is required for the ad to appear on the site. Social channels
                        are for share links on the campaign detail page.
                    </p>
                @endif
            </div>

            {{-- ============ INFO BANNER ============ --}}
            <div class="p-4 rounded-lg"
                 style="background: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                <p class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1" style="color: var(--info);"></i>
                    <strong>How billing works:</strong> You pay upfront for the full period. If your unit is rented
                    before the period ends, you can request to stop the ad — no refund for the current period, but
                    no future charges. An admin will review and confirm.
                </p>
            </div>

            {{-- ============ ACTIONS ============ --}}
            <div class="flex justify-end gap-3">
                <a href="{{ route('landlord.ads.index') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium"
                   style="background: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                    Cancel
                </a>
                <button type="submit"
                        id="createSubmitBtn"
                        class="px-6 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                        style="background: var(--primary); color: white;">
                    <i class="fas fa-arrow-right mr-2"></i>
                    Continue to Payment
                </button>
            </div>
        </form>
    @endif
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form            = document.getElementById('createAdCampaignForm');
    if (!form) return;

    const planCards       = document.querySelectorAll('.plan-card');
    const channelCards    = document.querySelectorAll('.channel-card');
    const banner          = document.getElementById('clientValidationBanner');
    const bannerList      = document.getElementById('clientValidationErrors');
    const submitBtn       = document.getElementById('createSubmitBtn');

    /* ---------------------------------------------------------
       Plan card visual state
       --------------------------------------------------------- */
    function refreshPlanCards() {
        planCards.forEach(function (card) {
            const radio = card.querySelector('.plan-radio');
            const indicator = card.querySelector('.plan-indicator');
            const dot = card.querySelector('.plan-indicator-dot');
            const isChecked = radio && radio.checked;

            card.style.borderColor = isChecked ? 'var(--primary)' : 'var(--border-color)';
            card.style.background  = isChecked ? 'rgba(var(--primary-rgb), 0.05)' : 'var(--bg-primary)';
            if (indicator) indicator.style.borderColor = isChecked ? 'var(--primary)' : 'var(--border-color)';
            if (dot) dot.style.background = isChecked ? 'var(--primary)' : 'transparent';
        });
    }

    planCards.forEach(function (card) {
        const radio = card.querySelector('.plan-radio');
        if (radio) {
            radio.addEventListener('change', refreshPlanCards);
        }
    });
    refreshPlanCards();

    /* ---------------------------------------------------------
       Channel card visual state
       --------------------------------------------------------- */
    function refreshChannelCards() {
        channelCards.forEach(function (card) {
            const cb = card.querySelector('.channel-checkbox');
            const isChecked = cb && cb.checked;
            card.style.borderColor = isChecked ? 'var(--primary)' : 'var(--border-color)';
            card.style.background  = isChecked ? 'rgba(var(--primary-rgb), 0.05)' : 'var(--bg-primary)';
        });
    }

    channelCards.forEach(function (card) {
        const cb = card.querySelector('.channel-checkbox');
        if (cb) {
            cb.addEventListener('change', refreshChannelCards);
        }
    });
    refreshChannelCards();

    /* ---------------------------------------------------------
       Client-side validation on submit
       --------------------------------------------------------- */
    function showClientErrors(errors) {
        if (!banner || !bannerList) return;

        if (errors.length === 0) {
            banner.classList.add('hidden');
            bannerList.innerHTML = '';
            return;
        }

        bannerList.innerHTML = errors.map(e => '<li>' + e + '</li>').join('');
        banner.classList.remove('hidden');

        // Scroll to banner so the user sees it
        banner.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    form.addEventListener('submit', function (e) {
        const errors = [];

        const unitSelect = document.getElementById('property_unit_id');
        if (!unitSelect || !unitSelect.value) {
            errors.push('Please select a property unit.');
            unitSelect?.focus();
        }

        const planSelected = form.querySelector('input[name="ad_pricing_plan_id"]:checked');
        if (!planSelected) {
            errors.push('Please select a pricing plan.');
        }

        const channelSelected = form.querySelectorAll('input[name="channels[]"]:checked');
        if (channelSelected.length === 0) {
            errors.push('Please select at least one channel.');
        }

        if (errors.length > 0) {
            e.preventDefault();
            e.stopPropagation();
            showClientErrors(errors);
            return false;
        }

        // Clear the banner if we're submitting successfully
        showClientErrors([]);

        // Prevent double-submits
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.7';
            submitBtn.style.cursor = 'wait';
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Please wait...';
        }
    });

    // Restore the button if the user navigates back
    window.addEventListener('pageshow', function () {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.style.opacity = '';
            submitBtn.style.cursor = '';
            submitBtn.innerHTML = '<i class="fas fa-arrow-right mr-2"></i>Continue to Payment';
        }
    });
});
</script>
@endsection