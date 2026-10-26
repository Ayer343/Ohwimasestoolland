{{-- resources/views/admin/ads/settings.blade.php --}}
@extends('layouts.app')

@section('title', 'AD Space Settings')

@php
    use App\Models\AdCampaign;
    use App\Models\AdInvoice;
    use App\Models\AdPricingPlan;
    use App\Models\SystemSetting;
    use Illuminate\Support\Str;

    // Defensive defaults so the view never breaks
    $config = $config ?? SystemSetting::AD_DEFAULTS;
    $plans  = $plans  ?? collect();
    $stats  = $stats  ?? [
        'active'          => 0,
        'pending_payment' => 0,
        'rented_early'    => 0,
        'expired'         => 0,
        'pending_rented'  => 0,
        'total_revenue'   => 0,
    ];

    $channels = $config['channels'] ?? [];

    $channelMeta = [
        'homepage'  => ['Homepage',  'fas fa-home',         'success'],
        'facebook'  => ['Facebook',  'fab fa-facebook-f',   'info'],
        'whatsapp'  => ['WhatsApp',  'fab fa-whatsapp',     'success'],
        'twitter'   => ['Twitter/X', 'fab fa-x-twitter',    'secondary'],
        'linkedin'  => ['LinkedIn',  'fab fa-linkedin-in',  'info'],
        'instagram' => ['Instagram', 'fab fa-instagram',    'danger'],
    ];

    // Preset plans — safe if the method is missing (defensive)
    $planPresets = method_exists(SystemSetting::class, 'getAdPlanPresets')
        ? SystemSetting::getAdPlanPresets()
        : [];
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
                        AD Space Settings
                        @if(!empty($config['enabled']))
                            <span class="px-3 py-1 rounded-full text-xs font-medium badge-success">Enabled</span>
                        @else
                            <span class="px-3 py-1 rounded-full text-xs font-medium badge-danger">Disabled</span>
                        @endif
                    </h2>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-sliders-h mr-2"></i>
                        Control availability, pricing plans, and campaign limits
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('admin.homepage-content.ads.campaigns') }}"
                   class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                    <i class="fas fa-list mr-2"></i> Campaigns
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

    {{-- ==================== QUICK STATS ==================== --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        @php
            $statCards = [
                ['Active',           $stats['active']          ?? 0, 'success',  'fas fa-play-circle'],
                ['Awaiting Payment', $stats['pending_payment'] ?? 0, 'warning',  'fas fa-clock'],
                ['Rented (Early)',   $stats['rented_early']    ?? 0, 'info',     'fas fa-handshake'],
                ['Expired',          $stats['expired']         ?? 0, 'secondary','fas fa-calendar-times'],
                ['Review Pending',   $stats['pending_rented']  ?? 0, 'danger',   'fas fa-exclamation-circle'],
                ['Revenue',          '₵' . number_format($stats['total_revenue'] ?? 0, 2), 'primary', 'fas fa-coins'],
            ];
        @endphp
        @foreach($statCards as [$label, $value, $color, $icon])
            <div class="card p-4">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-medium" style="color: var(--text-secondary);">{{ $label }}</span>
                    <i class="{{ $icon }}" style="color: var(--{{ $color }});"></i>
                </div>
                <p class="text-xl font-bold" style="color: var(--{{ $color }});">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    {{-- ==================== GLOBAL AD SETTINGS ==================== --}}
    <div class="card">
        <div class="p-6" style="border-top: 4px solid var(--primary);">
            <div class="flex items-center mb-6">
                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                     style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-cog text-xl" style="color: var(--primary);"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        Global Settings
                    </h3>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        Master switch, approval workflow, limits, and channel availability
                    </p>
                </div>
            </div>

            <form action="{{ route('admin.homepage-content.ads.settings.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    {{-- Master toggle --}}
                    <label class="flex items-center justify-between p-4 rounded-lg cursor-pointer"
                           style="background: rgba(var(--secondary-rgb), 0.04);
                                  border: 1px solid var(--border-color);">
                        <div class="pr-3">
                            <div class="text-sm font-semibold" style="color: var(--text-primary);">
                                Enable AD Space
                            </div>
                            <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                Master switch — when off, landlords cannot create campaigns and no ads appear on the homepage.
                            </div>
                        </div>
                        <input type="hidden" name="enabled" value="0">
                        <input type="checkbox" name="enabled" value="1" class="w-5 h-5 flex-shrink-0"
                               {{ !empty($config['enabled']) ? 'checked' : '' }}>
                    </label>

                    {{-- Require admin approval --}}
                    <label class="flex items-center justify-between p-4 rounded-lg cursor-pointer"
                           style="background: rgba(var(--secondary-rgb), 0.04);
                                  border: 1px solid var(--border-color);">
                        <div class="pr-3">
                            <div class="text-sm font-semibold" style="color: var(--text-primary);">
                                Require Admin Approval
                            </div>
                            <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                Campaigns must be reviewed before going live on the homepage.
                            </div>
                        </div>
                        <input type="hidden" name="require_admin_approval" value="0">
                        <input type="checkbox" name="require_admin_approval" value="1" class="w-5 h-5 flex-shrink-0"
                               {{ !empty($config['require_admin_approval']) ? 'checked' : '' }}>
                    </label>

                    {{-- Auto-approve "mark as rented" --}}
                    <label class="flex items-center justify-between p-4 rounded-lg cursor-pointer"
                           style="background: rgba(var(--secondary-rgb), 0.04);
                                  border: 1px solid var(--border-color);">
                        <div class="pr-3">
                            <div class="text-sm font-semibold" style="color: var(--text-primary);">
                                Auto-Approve "Mark as Rented"
                            </div>
                            <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                Skip admin review for stop-requests. Use only if you trust your landlords.
                            </div>
                        </div>
                        <input type="hidden" name="auto_approve_mark_rented" value="0">
                        <input type="checkbox" name="auto_approve_mark_rented" value="1" class="w-5 h-5 flex-shrink-0"
                               {{ !empty($config['auto_approve_mark_rented']) ? 'checked' : '' }}>
                    </label>

                    {{-- Show sponsored badge --}}
                    <label class="flex items-center justify-between p-4 rounded-lg cursor-pointer"
                           style="background: rgba(var(--secondary-rgb), 0.04);
                                  border: 1px solid var(--border-color);">
                        <div class="pr-3">
                            <div class="text-sm font-semibold" style="color: var(--text-primary);">
                                Show "Sponsored" Badge
                            </div>
                            <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                Display a small "Sponsored" badge on ad cards on the homepage.
                            </div>
                        </div>
                        <input type="hidden" name="show_sponsored_badge" value="0">
                        <input type="checkbox" name="show_sponsored_badge" value="1" class="w-5 h-5 flex-shrink-0"
                               {{ !empty($config['show_sponsored_badge']) ? 'checked' : '' }}>
                    </label>

                    {{-- Max active per landlord --}}
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Max Active per Landlord
                        </label>
                        <input type="number"
                               name="max_active_per_landlord"
                               min="1" max="50"
                               value="{{ old('max_active_per_landlord', $config['max_active_per_landlord'] ?? 3) }}"
                               class="form-input w-full px-3 py-2 rounded-lg">
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Includes campaigns with pending payment.
                        </p>
                    </div>

                    {{-- Max on homepage --}}
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Max Ads on Homepage
                        </label>
                        <input type="number"
                               name="max_on_homepage"
                               min="1" max="20"
                               value="{{ old('max_on_homepage', $config['max_on_homepage'] ?? 6) }}"
                               class="form-input w-full px-3 py-2 rounded-lg">
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Maximum number of sponsored cards displayed on the homepage.
                        </p>
                    </div>
                </div>

                {{-- Channels --}}
                <div class="p-4 rounded-lg mb-4"
                     style="background: rgba(var(--info-rgb), 0.04);
                            border: 1px solid rgba(var(--info-rgb), 0.15);">
                    <p class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                        <i class="fas fa-broadcast-tower mr-2" style="color: var(--info);"></i>
                        Available Channels
                    </p>
                    <p class="text-xs mb-3" style="color: var(--text-secondary);">
                        Landlords can choose from these when creating a campaign. The homepage channel is
                        required for ads to display on your site.
                    </p>

                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                        @foreach($channelMeta as $key => [$label, $icon, $color])
                            @php $on = !empty($channels[$key]); @endphp
                            <label class="flex items-center justify-between p-3 rounded-lg cursor-pointer"
                                   style="background: var(--card-bg);
                                          border: 1px solid var(--border-color);">
                                <span class="flex items-center gap-2 text-sm"
                                      style="color: var(--text-primary);">
                                    <i class="{{ $icon }}" style="color: var(--{{ $color }});"></i>
                                    {{ $label }}
                                </span>
                                <input type="hidden" name="channels[{{ $key }}]" value="0">
                                <input type="checkbox" name="channels[{{ $key }}]" value="1" class="w-5 h-5"
                                       {{ $on ? 'checked' : '' }}>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            class="btn-primary px-5 py-2 rounded-lg font-medium inline-flex items-center">
                        <i class="fas fa-save mr-2"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ==================== PRICING PLANS ==================== --}}
    <div class="card">
        <div class="p-6" style="border-top: 4px solid var(--success);">
            <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                         style="background-color: rgba(var(--success-rgb), 0.1);">
                        <i class="fas fa-tags text-xl" style="color: var(--success);"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            Pricing Plans
                        </h3>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Landlords choose from these plans when creating a campaign
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    {{-- Quick-add preset dropdown --}}
                    @if(!empty($planPresets))
                        <details class="inline-block relative">
                            <summary class="btn-outline px-4 py-2 rounded-lg font-medium inline-flex items-center cursor-pointer list-none"
                                     style="border: 1px solid var(--border-color);">
                                <i class="fas fa-bolt mr-2"></i> Quick Add Preset
                            </summary>
                            <div class="absolute right-0 mt-2 p-2 rounded-lg shadow-xl z-20"
                                 style="background: var(--card-bg);
                                        border: 1px solid var(--border-color);
                                        min-width: 260px;">
                                @foreach($planPresets as $key => $preset)
                                    <form method="POST"
                                          action="{{ route('admin.homepage-content.ads.plans.store') }}"
                                          class="mb-1 last:mb-0">
                                        @csrf
                                        <input type="hidden" name="name"            value="{{ $preset['name'] }}">
                                        <input type="hidden" name="billing_period"  value="{{ $preset['billing_period'] }}">
                                        <input type="hidden" name="duration_months" value="{{ $preset['duration_months'] }}">
                                        <input type="hidden" name="price"           value="{{ $preset['price'] }}">
                                        <input type="hidden" name="currency"        value="{{ $preset['currency'] }}">
                                        <input type="hidden" name="description"     value="{{ $preset['description'] }}">
                                        <input type="hidden" name="sort_order"      value="{{ $preset['sort_order'] }}">
                                        <input type="hidden" name="is_active"       value="1">
                                        <button type="submit"
                                                class="w-full text-left px-3 py-2 rounded text-sm hover:bg-opacity-10"
                                                style="color: var(--text-primary);">
                                            <div class="font-medium">{{ $preset['name'] }}</div>
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                {{ '₵' . number_format($preset['price'], 2) }}
                                                · {{ $preset['duration_months'] }} mo
                                            </div>
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        </details>
                    @endif

                    <button type="button" id="openAdPlanModal"
                            class="btn-primary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                        <i class="fas fa-plus mr-2"></i> New Plan
                    </button>
                </div>
            </div>

            @if($plans->isEmpty())
                <div class="text-center py-10 rounded-lg"
                     style="background-color: rgba(var(--secondary-rgb), 0.03);
                            border: 1px dashed var(--border-color);">
                    <i class="fas fa-tags text-4xl mb-3"
                       style="color: var(--text-secondary); opacity: 0.4;"></i>
                    <p class="text-sm mb-3" style="color: var(--text-secondary);">
                        No pricing plans yet. Create one to start selling ad space.
                    </p>
                    <button type="button" id="openAdPlanModalEmpty"
                            class="btn-primary px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center">
                        <i class="fas fa-plus mr-2"></i> Create First Plan
                    </button>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border-color);">
                                <th class="text-left py-3 px-2 font-semibold" style="color: var(--text-primary);">Plan</th>
                                <th class="text-left py-3 px-2 font-semibold" style="color: var(--text-primary);">Period</th>
                                <th class="text-left py-3 px-2 font-semibold" style="color: var(--text-primary);">Duration</th>
                                <th class="text-right py-3 px-2 font-semibold" style="color: var(--text-primary);">Price</th>
                                <th class="text-left py-3 px-2 font-semibold" style="color: var(--text-primary);">Status</th>
                                <th class="text-right py-3 px-2 font-semibold" style="color: var(--text-primary);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($plans as $plan)
                                @php
                                    // Pre-compute the payload — SAFE, no @json() parsing issues.
                                    // json_encode() output is escaped by {{ }} so quotes survive HTML attributes.
                                    $planPayload = [
                                        'id'              => $plan->id,
                                        'name'            => $plan->name,
                                        'billing_period'  => $plan->billing_period,
                                        'duration_months' => $plan->duration_months,
                                        'price'           => (float) $plan->price,
                                        'currency'        => $plan->currency,
                                        'description'     => $plan->description,
                                        'is_active'       => (bool) $plan->is_active,
                                    ];
                                @endphp
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td class="py-3 px-2">
                                        <div class="font-medium" style="color: var(--text-primary);">{{ $plan->name }}</div>
                                        @if($plan->description)
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                {{ Str::limit($plan->description, 60) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-3 px-2 capitalize" style="color: var(--text-secondary);">
                                        {{ $plan->billing_period }}
                                    </td>
                                    <td class="py-3 px-2" style="color: var(--text-secondary);">
                                        {{ $plan->duration_months }} mo
                                    </td>
                                    <td class="py-3 px-2 text-right font-semibold" style="color: var(--primary);">
                                        {{ $plan->currency === 'GHS' ? '₵' : $plan->currency . ' ' }}{{ number_format((float) $plan->price, 2) }}
                                    </td>
                                    <td class="py-3 px-2">
                                        @if($plan->is_active)
                                            <span class="px-2 py-1 rounded-full text-xs font-medium badge-success">Active</span>
                                        @else
                                            <span class="px-2 py-1 rounded-full text-xs font-medium badge-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-2 text-right">
                                        <div class="inline-flex gap-1 flex-wrap justify-end">
                                            <button type="button"
                                                    class="px-2 py-1 rounded text-xs font-medium edit-plan-btn"
                                                    data-plan="{{ json_encode($planPayload) }}"
                                                    style="background: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                                    title="Edit plan">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form action="{{ route('admin.homepage-content.ads.plans.toggle', $plan->id) }}"
                                                  method="POST" class="inline">
                                                @csrf
                                                <button type="submit"
                                                        class="px-2 py-1 rounded text-xs font-medium"
                                                        style="background: rgba(var(--warning-rgb), 0.1); color: var(--warning);"
                                                        title="{{ $plan->is_active ? 'Deactivate' : 'Activate' }}">
                                                    <i class="fas fa-{{ $plan->is_active ? 'pause' : 'play' }}"></i>
                                                </button>
                                            </form>
                                            <form action="{{ route('admin.homepage-content.ads.plans.destroy', $plan->id) }}"
                                                  method="POST" class="inline"
                                                  onsubmit="return confirm('Delete this plan?\n\nExisting campaigns keep their snapshot, so historical revenue is safe.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="px-2 py-1 rounded text-xs font-medium"
                                                        style="background: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                                                        title="Delete plan">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
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
</div>

{{-- ============================================================
     CREATE / EDIT PLAN MODAL
     ============================================================ --}}
<div id="adPlanModal"
     role="dialog"
     aria-modal="true"
     style="position: fixed; inset: 0; z-index: 9999;
            background-color: rgba(0,0,0,0.7);
            display: none; align-items: center; justify-content: center; padding: 1rem;">
    <div style="width: 100%; max-width: 520px;
                background-color: var(--card-bg);
                border-radius: var(--radius-lg);
                overflow: hidden;
                box-shadow: 0 25px 60px rgba(0,0,0,0.4);">
        <div class="flex items-center justify-between p-4"
             style="border-bottom: 1px solid var(--border-color);">
            <h3 class="font-semibold" id="adPlanModalTitle" style="color: var(--text-primary);">
                New Pricing Plan
            </h3>
            <button type="button" id="closeAdPlanModal"
                    class="w-8 h-8 rounded-full inline-flex items-center justify-center"
                    style="background: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('admin.homepage-content.ads.plans.store') }}"
              id="adPlanForm" class="p-4">
            @csrf
            <input type="hidden" name="_method" id="adPlanFormMethod" value="POST">
            <input type="hidden" name="plan_id" id="adPlanId" value="">

            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                        Name <span style="color: var(--danger);">*</span>
                    </label>
                    <input type="text" name="name" id="adPlanName" required maxlength="100"
                           placeholder="e.g. Standard Monthly"
                           class="form-input w-full px-3 py-2 rounded-lg">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                            Billing Period <span style="color: var(--danger);">*</span>
                        </label>
                        <select name="billing_period" id="adPlanPeriod" required
                                class="form-input w-full px-3 py-2 rounded-lg">
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                            Duration (months) <span style="color: var(--danger);">*</span>
                        </label>
                        <input type="number" name="duration_months" id="adPlanDuration"
                               required min="1" max="24" value="1"
                               class="form-input w-full px-3 py-2 rounded-lg">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                            Price <span style="color: var(--danger);">*</span>
                        </label>
                        <input type="number" name="price" id="adPlanPrice"
                               required min="1" step="0.01"
                               placeholder="50.00"
                               class="form-input w-full px-3 py-2 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                            Currency
                        </label>
                        <input type="text" name="currency" id="adPlanCurrency"
                               maxlength="3" value="GHS"
                               class="form-input w-full px-3 py-2 rounded-lg">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                        Description (optional)
                    </label>
                    <textarea name="description" id="adPlanDescription"
                              rows="2" maxlength="500"
                              class="form-input w-full px-3 py-2 rounded-lg"
                              placeholder="Short summary shown to landlords"></textarea>
                </div>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" id="adPlanActive" checked class="w-5 h-5">
                    <span class="text-sm" style="color: var(--text-primary);">Active</span>
                </label>
            </div>

            <div class="flex justify-end gap-2 mt-5">
                <button type="button" id="cancelAdPlanModal"
                        class="px-4 py-2 rounded-lg text-sm font-medium"
                        style="background: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                    Cancel
                </button>
                <button type="submit"
                        class="btn-primary px-5 py-2 rounded-lg text-sm font-medium inline-flex items-center">
                    <i class="fas fa-save mr-2"></i> <span id="adPlanSubmitLabel">Create Plan</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ============================================================
    // Close any open <details> dropdowns when clicking outside
    // ============================================================
    document.addEventListener('click', function (e) {
        document.querySelectorAll('details[open]').forEach(function (details) {
            if (!details.contains(e.target)) {
                details.removeAttribute('open');
            }
        });
    });

    // ============================================================
    // Pricing plan modal
    // ============================================================
    const adPlanModal       = document.getElementById('adPlanModal');
    const adPlanForm        = document.getElementById('adPlanForm');
    const adPlanFormMethod  = document.getElementById('adPlanFormMethod');
    const adPlanId          = document.getElementById('adPlanId');
    const adPlanModalTitle  = document.getElementById('adPlanModalTitle');
    const adPlanSubmitLabel = document.getElementById('adPlanSubmitLabel');
    const adPlanName        = document.getElementById('adPlanName');
    const adPlanPeriod      = document.getElementById('adPlanPeriod');
    const adPlanDuration    = document.getElementById('adPlanDuration');
    const adPlanPrice       = document.getElementById('adPlanPrice');
    const adPlanCurrency    = document.getElementById('adPlanCurrency');
    const adPlanDescription = document.getElementById('adPlanDescription');
    const adPlanActive      = document.getElementById('adPlanActive');

    const STORE_URL  = @json(route('admin.homepage-content.ads.plans.store'));
    const UPDATE_URL = @json(url('admin/homepage-content/ads/plans')) + '/__ID__';

    function openAdPlanModal(plan) {
        if (!adPlanModal || !adPlanForm) return;

        if (plan && plan.id) {
            // Edit mode
            adPlanModalTitle.textContent  = 'Edit Pricing Plan';
            adPlanSubmitLabel.textContent = 'Save Changes';
            adPlanForm.action = UPDATE_URL.replace('__ID__', plan.id);
            adPlanFormMethod.value = 'PATCH';
            adPlanId.value          = plan.id;
            adPlanName.value        = plan.name || '';
            adPlanPeriod.value      = plan.billing_period || 'monthly';
            adPlanDuration.value    = plan.duration_months || 1;
            adPlanPrice.value       = plan.price || '';
            adPlanCurrency.value    = plan.currency || 'GHS';
            adPlanDescription.value = plan.description || '';
            adPlanActive.checked    = !!plan.is_active;

            // Lock period + duration on edit — changing them would corrupt
            // historical campaigns. Admin should create a new plan instead.
            adPlanPeriod.disabled   = true;
            adPlanDuration.disabled = true;
            adPlanPeriod.title      = 'Period cannot be changed after creation. Create a new plan instead.';
            adPlanDuration.title    = 'Duration cannot be changed after creation. Create a new plan instead.';
        } else {
            // Create mode
            adPlanModalTitle.textContent  = 'New Pricing Plan';
            adPlanSubmitLabel.textContent = 'Create Plan';
            adPlanForm.action = STORE_URL;
            adPlanFormMethod.value = 'POST';
            adPlanId.value = '';
            adPlanForm.reset();
            adPlanCurrency.value    = 'GHS';
            adPlanActive.checked    = true;
            adPlanPeriod.disabled   = false;
            adPlanDuration.disabled = false;
            adPlanPeriod.title      = '';
            adPlanDuration.title    = '';
        }

        adPlanModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeAdPlanModal() {
        if (!adPlanModal) return;
        adPlanModal.style.display = 'none';
        document.body.style.overflow = '';
        adPlanPeriod.disabled   = false;
        adPlanDuration.disabled = false;
        adPlanPeriod.title      = '';
        adPlanDuration.title    = '';
    }

    const openBtn      = document.getElementById('openAdPlanModal');
    const openEmptyBtn = document.getElementById('openAdPlanModalEmpty');
    if (openBtn)      openBtn.addEventListener('click', () => openAdPlanModal(null));
    if (openEmptyBtn) openEmptyBtn.addEventListener('click', () => openAdPlanModal(null));

    const closeBtn  = document.getElementById('closeAdPlanModal');
    const cancelBtn = document.getElementById('cancelAdPlanModal');
    if (closeBtn)  closeBtn.addEventListener('click', closeAdPlanModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeAdPlanModal);

    if (adPlanModal) {
        adPlanModal.addEventListener('click', (e) => {
            if (e.target === adPlanModal) closeAdPlanModal();
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && adPlanModal && adPlanModal.style.display !== 'none') {
            closeAdPlanModal();
        }
    });

    // Edit plan buttons — safe because data-plan is a plain escaped JSON string
    document.querySelectorAll('.edit-plan-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            try {
                const plan = JSON.parse(btn.dataset.plan);
                openAdPlanModal(plan);
            } catch (err) {
                console.error('Failed to parse plan data:', err, btn.dataset.plan);
            }
        });
    });
});
</script>
@endsection