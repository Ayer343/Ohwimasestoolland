{{-- resources/views/developer/billing/dashboard.blade.php --}}
@extends('layouts.dev')

@php
    use App\Models\User;

    $pageTitle = 'Developer Billing Dashboard';
    $user = auth()->user();

    // ============================================================
    // Currency formatter — as a closure, not a global function.
    // Declaring a top-level function here would blow up on second
    // render (e.g., when the layout also includes a partial that
    // defines the same name).
    // ============================================================
    $formatCurrency = function ($amount, $currency = 'GHS') {
        if (empty($amount) && $amount !== 0 && $amount !== 0.0) {
            $amount = 0;
        }
        $amount = (float) $amount;
        $symbol = $currency === 'GHS' ? 'GH₵' : $currency . ' ';
        return $symbol . number_format($amount, 2);
    };

    // ---------- SAFE DEFAULTS ----------
    $developerSettings      = $developerSettings      ?? null;
    $stats                  = $stats                  ?? [];
    $superAdmins            = $superAdmins            ?? collect();
    $primarySuperAdmin      = $primarySuperAdmin      ?? null;
    $primaryBillingContact  = $primaryBillingContact  ?? null;
    $activeAgreements       = $activeAgreements       ?? collect();
    $pendingAgreements      = $pendingAgreements      ?? collect();
    $pendingPayments        = $pendingPayments        ?? collect();
    $awaitingSignature      = $awaitingSignature      ?? collect();
    $recentlySigned         = $recentlySigned         ?? collect();

    // ---------- PAYMENT PROVIDERS ----------
    $developerProviders = [
        'paystack' => [
            'name'       => 'Paystack',
            'icon'       => 'fa-credit-card',
            'configured' => !empty(env('DEVELOPER_PAYSTACK_SECRET_KEY')) && !empty(env('DEVELOPER_PAYSTACK_PUBLIC_KEY')),
            'enabled'    => env('DEVELOPER_PAYSTACK_ENABLED', false),
            'color'      => '#3B82F6',
        ],
        'expresspay' => [
            'name'       => 'ExpressPay',
            'icon'       => 'fa-credit-card',
            'configured' => !empty(env('DEVELOPER_EXPRESSPAY_MERCHANT_ID')) && !empty(env('DEVELOPER_EXPRESSPAY_API_KEY')),
            'enabled'    => env('DEVELOPER_EXPRESSPAY_ENABLED', false),
            'color'      => '#0066CC',
        ],
        'flutterwave' => [
            'name'       => 'Flutterwave',
            'icon'       => 'fa-cloud-upload-alt',
            'configured' => !empty(env('DEVELOPER_FLUTTERWAVE_PUBLIC_KEY')) && !empty(env('DEVELOPER_FLUTTERWAVE_SECRET_KEY')),
            'enabled'    => env('DEVELOPER_FLUTTERWAVE_ENABLED', false),
            'color'      => '#F97316',
        ],
        'hubtel' => [
            'name'       => 'Hubtel',
            'icon'       => 'fa-phone-alt',
            'configured' => !empty(env('DEVELOPER_HUBTEL_CLIENT_ID')) && !empty(env('DEVELOPER_HUBTEL_CLIENT_SECRET')),
            'enabled'    => env('DEVELOPER_HUBTEL_ENABLED', false),
            'color'      => '#2563EB',
        ],
    ];

    $providerDisplayNames = [
        'paystack'    => 'Paystack',
        'expresspay'  => 'ExpressPay',
        'flutterwave' => 'Flutterwave',
        'hubtel'      => 'Hubtel',
    ];
    $providerIcons = [
        'paystack'    => 'fa-credit-card',
        'expresspay'  => 'fa-credit-card',
        'flutterwave' => 'fa-cloud-upload-alt',
        'hubtel'      => 'fa-phone-alt',
    ];
    $providerColors = [
        'paystack'    => '#3B82F6',
        'expresspay'  => '#0066CC',
        'flutterwave' => '#F97316',
        'hubtel'      => '#2563EB',
    ];

    $availablePaymentProviders = [];
    foreach ($developerProviders as $key => $provider) {
        if ($provider['configured'] && $provider['enabled']) {
            $availablePaymentProviders[$key] = [
                'name'       => $providerDisplayNames[$key],
                'icon'       => $providerIcons[$key],
                'color'      => $providerColors[$key],
                'configured' => true,
                'enabled'    => true,
            ];
        }
    }

    $hasBillingProvider = count($availablePaymentProviders) > 0;
    $activeProvider = $hasBillingProvider ? array_key_first($availablePaymentProviders) : null;

    $cacheKey = "developer_billing_dashboard_{$user->id}";
    $cacheTimestamp = Cache::has($cacheKey) ? Cache::get($cacheKey . '_timestamp', null) : null;

    // ---------- PENDING APPROVALS ----------
    $pendingApprovalsCount = 0;
    $pendingApprovalsList  = collect();

    if (isset($awaitingSignature) && $awaitingSignature->isNotEmpty()) {
        $pendingApprovalsCount += $awaitingSignature->count();
        $pendingApprovalsList = $pendingApprovalsList->merge($awaitingSignature);
    }

    if (isset($pendingAgreements) && $pendingAgreements->isNotEmpty()) {
        foreach ($pendingAgreements as $agreement) {
            $developerHasSigned = false;
            if (isset($agreement->signatures)) {
                foreach ($agreement->signatures as $signature) {
                    if ($signature->signature_type === 'developer') {
                        $developerHasSigned = true;
                        break;
                    }
                }
            }
            if (!$developerHasSigned && !$pendingApprovalsList->contains('id', $agreement->id)) {
                $pendingApprovalsCount++;
                $pendingApprovalsList->push($agreement);
            }
        }
    }

    $hasSignableAgreements  = $pendingApprovalsList->isNotEmpty();
    $firstSignableAgreement = $pendingApprovalsList->isNotEmpty() ? $pendingApprovalsList->first() : null;

    // ---------- WEBHOOK URLS ----------
    $webhookUrls = [
        'paystack'    => url('/api/payments/paystack/webhook'),
        'expresspay'  => url('/api/payments/expresspay/webhook'),
        'flutterwave' => url('/api/payments/flutterwave/webhook'),
        'hubtel'      => url('/api/payments/hubtel/webhook'),
    ];

    // ---------- GENERIC PAYMENT FALLBACKS ----------
    $genericPaymentMethods = [
        'bank_transfer' => ['name' => 'Bank Transfer', 'icon' => 'fa-university'],
        'mobile_money'  => ['name' => 'Mobile Money', 'icon' => 'fa-mobile-alt'],
        'cash'          => ['name' => 'Cash', 'icon' => 'fa-money-bill'],
        'check'         => ['name' => 'Check', 'icon' => 'fa-file-invoice'],
    ];

    // ---------- ACTIVE SA COUNT FOR HEADER ----------
    $activeSuperAdminCount = method_exists($superAdmins, 'where')
        ? $superAdmins->where('status', User::STATUS_ACTIVE)->count()
        : $superAdmins->count();

    if ($activeSuperAdminCount === 0 && $superAdmins->count() > 0) {
        $activeSuperAdminCount = $superAdmins->count();
    }
@endphp

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- ============================================================
         HEADER
    ============================================================ --}}
    <div class="card">
        <div class="flex flex-col md:flex-row md:justify-between md:items-center p-6 gap-4">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-money-bill-wave mr-2"></i>
                    Billing Management Dashboard
                </h2>
                <div class="text-sm flex flex-wrap items-center mt-1 gap-2" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1"></i>
                    <span>Manage agreements, invoices, and super admin payments</span>
                    @if($developerSettings)
                        <span>•</span>
                        <i class="fas fa-money-bill-wave mr-1"></i>
                        <span class="font-medium">
                            {{ $formatCurrency($developerSettings->monthly_billing_amount, $developerSettings->billing_currency ?? 'GHS') }}/{{ $developerSettings->billing_cycle ?? 'monthly' }}
                        </span>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <div class="flex items-center" style="color: var(--text-secondary);">
                    <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                </div>
                @if($cacheTimestamp)
                    <div class="flex items-center text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-database mr-1"></i>
                        Cached: {{ \Carbon\Carbon::parse($cacheTimestamp)->diffForHumans() }}
                    </div>
                @endif
                @if(Route::has('developer.payment-providers.index'))
                    <a href="{{ route('developer.payment-providers.index') }}"
                       class="px-3 py-1.5 rounded-lg text-sm transition-all duration-200 hover:bg-gray-100 flex items-center"
                       style="color: var(--info); background-color: rgba(var(--info-rgb), 0.1);">
                        <i class="fas fa-credit-card mr-1"></i> Providers
                    </a>
                @endif
                @if(Route::has('developer.settings.index'))
                    <a href="{{ route('developer.settings.index', ['section' => 'billing']) }}"
                       class="px-3 py-1.5 rounded-lg text-sm transition-all duration-200 hover:bg-gray-100 flex items-center"
                       style="color: var(--primary); background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-cog mr-1"></i> Settings
                    </a>
                @endif
                <a href="{{ route('developer.billing.dashboard') }}"
                   class="px-3 py-1.5 rounded-lg text-sm transition-all duration-200 hover:bg-gray-100"
                   style="color: var(--primary); background-color: rgba(var(--primary-rgb), 0.1);"
                   onclick="event.preventDefault(); location.reload();">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </a>
            </div>
        </div>
    </div>

    {{-- ============================================================
         PROVIDER STATUS BANNER
    ============================================================ --}}
    @if(!$hasBillingProvider)
        <div class="card" style="background: linear-gradient(135deg, rgba(var(--warning-rgb), 0.08) 0%, rgba(var(--warning-rgb), 0.02) 100%); border-left: 4px solid var(--warning);">
            <div class="p-4">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-2xl mr-3" style="color: var(--warning);"></i>
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">No Payment Provider Configured</p>
                            <p class="text-sm mt-0.5" style="color: var(--text-secondary);">
                                You need to configure a payment provider to receive payments from super admins.
                            </p>
                        </div>
                    </div>
                    @if(Route::has('developer.payment-providers.index'))
                        <a href="{{ route('developer.payment-providers.index') }}"
                           class="px-4 py-2 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-md"
                           style="background-color: var(--warning); color: white;">
                            <i class="fas fa-credit-card mr-2"></i> Configure Provider
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="card" style="background: linear-gradient(135deg, rgba(var(--success-rgb), 0.08) 0%, rgba(var(--success-rgb), 0.02) 100%); border-left: 4px solid var(--success);">
            <div class="p-4">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-2xl mr-3" style="color: var(--success);"></i>
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">Payment Provider Ready</p>
                            <p class="text-sm mt-0.5" style="color: var(--text-secondary);">
                                @php
                                    $activeProviderName = $availablePaymentProviders[$activeProvider]['name'] ?? 'Unknown';
                                @endphp
                                <strong>{{ $activeProviderName }}</strong> is configured and enabled for billing.
                                @if(count($availablePaymentProviders) > 1)
                                    <span class="ml-1 text-xs" style="color: var(--text-secondary);">
                                        (+{{ count($availablePaymentProviders) - 1 }} more provider{{ count($availablePaymentProviders) - 1 > 1 ? 's' : '' }})
                                    </span>
                                @endif
                            </p>
                        </div>
                    </div>
                    @if(Route::has('developer.payment-providers.index'))
                        <a href="{{ route('developer.payment-providers.index') }}"
                           class="px-4 py-2 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-md"
                           style="background-color: var(--success); color: white;">
                            <i class="fas fa-credit-card mr-2"></i> Manage Providers
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================================
         PRIMARY SUPER ADMIN BANNER
    ============================================================ --}}
    @if($primarySuperAdmin)
        <div class="card" style="background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.08) 0%, rgba(var(--primary-rgb), 0.02) 100%); border-left: 4px solid var(--primary);">
            <div class="p-4">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center">
                        <i class="fas fa-crown text-2xl mr-3" style="color: var(--primary);"></i>
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">Primary Super Admin for Billing</p>
                            <p class="text-sm mt-0.5" style="color: var(--text-secondary);">
                                <strong>{{ $primarySuperAdmin->name }}</strong> ({{ $primarySuperAdmin->email }})
                                @if($primaryBillingContact && !empty($primaryBillingContact['name']))
                                    • Billing Contact: {{ $primaryBillingContact['name'] }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <button onclick="showChangePrimaryAdminModal()"
                            class="px-4 py-2 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-1"
                            style="background-color: var(--primary); color: white;">
                        <i class="fas fa-exchange-alt mr-2"></i> Change Primary
                    </button>
                </div>
            </div>
        </div>
    @elseif($superAdmins->count() > 0)
        <div class="card" style="background: linear-gradient(135deg, rgba(var(--warning-rgb), 0.08) 0%, rgba(var(--warning-rgb), 0.02) 100%); border-left: 4px solid var(--warning);">
            <div class="p-4">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-2xl mr-3" style="color: var(--warning);"></i>
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">No Primary Super Admin Assigned</p>
                            <p class="text-sm mt-0.5" style="color: var(--text-secondary);">
                                Please select a Primary Super Admin to receive billing invoices
                            </p>
                        </div>
                    </div>
                    <button onclick="showChangePrimaryAdminModal()"
                            class="px-4 py-2 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-1"
                            style="background-color: var(--warning); color: white;">
                        <i class="fas fa-plus mr-2"></i> Assign Primary
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================================
         BILLING NAVIGATION TABS
    ============================================================ --}}
    <div class="card">
        <div class="border-b" style="border-color: var(--border-color);">
            <nav class="flex flex-wrap -mb-px overflow-x-auto billing-navigation">
                <a href="{{ route('developer.billing.dashboard') }}"
                   class="py-4 px-6 text-sm font-medium border-b-2 transition-colors duration-200 active-tab"
                   style="border-color: var(--primary); color: var(--primary);">
                    <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                </a>
                <a href="{{ route('developer.billing.agreements-list') }}"
                   class="py-4 px-6 text-sm font-medium border-b-2 border-transparent hover:border-gray-300 transition-colors duration-200"
                   style="color: var(--text-secondary);">
                    <i class="fas fa-handshake mr-2"></i> Agreements
                </a>
                <a href="{{ route('developer.billing.superadmin-payments') }}"
                   class="py-4 px-6 text-sm font-medium border-b-2 border-transparent hover:border-gray-300 transition-colors duration-200"
                   style="color: var(--text-secondary);">
                    <i class="fas fa-user-shield mr-2"></i> Super Admin Payments
                </a>
                <a href="{{ route('developer.billing.history') }}"
                   class="py-4 px-6 text-sm font-medium border-b-2 border-transparent hover:border-gray-300 transition-colors duration-200"
                   style="color: var(--text-secondary);">
                    <i class="fas fa-history mr-2"></i> Billing History
                </a>
                <a href="{{ route('developer.billing.reports') }}"
                   class="py-4 px-6 text-sm font-medium border-b-2 border-transparent hover:border-gray-300 transition-colors duration-200"
                   style="color: var(--text-secondary);">
                    <i class="fas fa-chart-bar mr-2"></i> Reports
                </a>
                <a href="{{ route('developer.billing.export') }}"
                   class="py-4 px-6 text-sm font-medium border-b-2 border-transparent hover:border-gray-300 transition-colors duration-200"
                   style="color: var(--text-secondary);">
                    <i class="fas fa-download mr-2"></i> Export
                </a>
            </nav>
        </div>
    </div>

    {{-- ============================================================
         STAT CARDS
    ============================================================ --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <a href="{{ route('developer.billing.agreements-list') }}?status=active" class="card p-5 hover:transform hover:-translate-y-1 transition-all duration-200">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Active Agreements</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ number_format($stats['total_active_agreements'] ?? 0) }}</h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-handshake text-xl" style="color: var(--success);"></i>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                <div class="text-sm flex justify-between items-center">
                    <span style="color: var(--success);"><i class="fas fa-check-circle mr-1"></i> Currently Active</span>
                    <span style="color: var(--text-secondary);">{{ $formatCurrency($stats['total_amount_agreed'] ?? 0, $developerSettings->billing_currency ?? 'GHS') }}</span>
                </div>
            </div>
        </a>

        <a href="{{ route('developer.billing.agreements-list') }}?status=pending&needs_signature=1"
           class="card p-5 hover:transform hover:-translate-y-1 transition-all duration-200 {{ $pendingApprovalsCount > 0 ? 'animate-pulse' : '' }}">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Pending Approval</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ number_format($pendingApprovalsCount) }}</h3>
                </div>
                <div class="p-3 rounded-full {{ $pendingApprovalsCount > 0 ? 'animate-pulse' : '' }}"
                     style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-clock text-xl" style="color: var(--warning);"></i>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                <div class="text-sm flex justify-between items-center">
                    <span style="color: var(--warning);"><i class="fas fa-hourglass-half mr-1"></i> Need Your Signature</span>
                    <span style="color: var(--text-secondary);">{{ number_format($pendingApprovalsCount) }} waiting</span>
                </div>
            </div>
        </a>

        <div class="card p-5">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Total Revenue</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);">
                        {{ $formatCurrency($stats['total_amount_received'] ?? 0, $developerSettings->billing_currency ?? 'GHS') }}
                    </h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-chart-bar text-xl" style="color: var(--info);"></i>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                <div class="text-sm flex justify-between items-center">
                    <span style="color: var(--success);"><i class="fas fa-coins mr-1"></i> Received</span>
                    <a href="{{ route('developer.billing.history') }}?status=paid" class="text-sm" style="color: var(--primary);">View History →</a>
                </div>
            </div>
        </div>

        <a href="#pending-payments-section" class="card p-5 hover:transform hover:-translate-y-1 transition-all duration-200">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Pending Payments</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ number_format($stats['total_pending_payments'] ?? 0) }}</h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-exclamation-circle text-xl" style="color: var(--danger);"></i>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                <div class="text-sm flex justify-between items-center">
                    <span style="color: var(--danger);"><i class="fas fa-money-check mr-1"></i> Unconfirmed</span>
                    <span style="color: var(--text-secondary);">{{ $formatCurrency($stats['pending_payment_amount'] ?? 0, $developerSettings->billing_currency ?? 'GHS') }}</span>
                </div>
            </div>
        </a>
    </div>

    {{-- ============================================================
         PROVIDER STATUS CARDS
    ============================================================ --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($developerProviders as $key => $provider)
            <div class="card p-4 {{ $provider['configured'] && $provider['enabled'] ? 'border-l-4' : '' }}"
                 style="{{ $provider['configured'] && $provider['enabled'] ? 'border-left-color: ' . $provider['color'] . ';' : '' }}">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                             style="background-color: {{ $provider['configured'] ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--danger-rgb), 0.1)' }};">
                            <i class="fas {{ $provider['icon'] }}"
                               style="color: {{ $provider['configured'] ? 'var(--success)' : 'var(--danger)' }};"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium" style="color: var(--text-primary);">{{ $provider['name'] }}</p>
                            <p class="text-xs" style="color: {{ $provider['configured'] && $provider['enabled'] ? 'var(--success)' : 'var(--danger)' }};">
                                @if($provider['configured'] && $provider['enabled'])
                                    <i class="fas fa-check-circle mr-1"></i> Active
                                @elseif($provider['configured'] && !$provider['enabled'])
                                    <i class="fas fa-pause-circle mr-1"></i> Disabled
                                @else
                                    <i class="fas fa-times-circle mr-1"></i> Not Configured
                                @endif
                            </p>
                        </div>
                    </div>
                    @if((!$provider['configured'] || !$provider['enabled']) && Route::has('developer.payment-providers.index'))
                        <a href="{{ route('developer.payment-providers.index') }}"
                           class="text-xs px-2 py-1 rounded hover:opacity-80"
                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            Setup
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- ============================================================
         WEBHOOK URLS
    ============================================================ --}}
    <div class="card">
        <div class="p-4 border-b" style="border-color: var(--border-color);">
            <h3 class="text-sm font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-link mr-2" style="color: var(--info);"></i> Webhook URLs
            </h3>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">Copy these URLs to your payment provider dashboards</p>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($webhookUrls as $provider => $url)
                    <div class="flex items-center justify-between p-2 rounded-lg border" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <div>
                            <span class="text-xs font-medium" style="color: var(--text-primary);">{{ $providerDisplayNames[$provider] }}</span>
                            <code class="block text-xs mt-0.5" style="color: var(--text-secondary); word-break: break-all;">{{ $url }}</code>
                        </div>
                        <button onclick="copyWebhookUrl('{{ $provider }}', '{{ $url }}')"
                                class="px-2 py-1 text-xs rounded hover:opacity-80"
                                style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ============================================================
         SIGNATURE STATUS BANNER
    ============================================================ --}}
    @if($pendingApprovalsCount > 0 && $hasSignableAgreements)
        <div class="card" style="background: linear-gradient(135deg, rgba(var(--warning-rgb), 0.1) 0%, rgba(var(--warning-rgb), 0.02) 100%); border-left: 4px solid var(--warning);">
            <div class="p-5">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full mr-4" style="background-color: rgba(var(--warning-rgb), 0.2);">
                            <i class="fas fa-signature text-2xl" style="color: var(--warning);"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-lg" style="color: var(--text-primary);">
                                {{ number_format($pendingApprovalsCount) }} Agreement(s) Need Your Signature
                            </p>
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                Please review and sign to activate them.
                            </p>
                        </div>
                    </div>
                    @if($firstSignableAgreement)
                        <a href="{{ route('developer.billing.view-agreement-for-signing', $firstSignableAgreement->id) }}"
                           class="px-6 py-3 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-md font-medium"
                           style="background-color: var(--warning); color: white;">
                            <i class="fas fa-pen mr-2"></i> Sign Agreements Now
                        </a>
                    @else
                        <a href="{{ route('developer.billing.agreements-list') }}?status=pending&needs_signature=1"
                           class="px-6 py-3 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-md font-medium"
                           style="background-color: var(--warning); color: white;">
                            <i class="fas fa-pen mr-2"></i> View Pending Agreements
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================================
         MAIN CONTENT
    ============================================================ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- LEFT COLUMN --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Create New Agreement --}}
            <div class="card">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            <i class="fas fa-plus-circle mr-2"></i> Create New Billing Agreement
                        </h3>
                        <div class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-users mr-1"></i>
                            Will be sent to all active super admins ({{ $activeSuperAdminCount }})
                        </div>
                    </div>

                    <form method="POST" action="{{ route('developer.billing.create-agreement') }}" class="space-y-4" id="createAgreementForm">
                        @csrf

                        <div class="p-3 rounded-lg mb-4" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.2);">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--info);"></i>
                                <div>
                                    <p class="font-medium" style="color: var(--info);">Mass Agreement Creation</p>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                        This agreement will be created for ALL active super admins. Each super admin will receive their own agreement.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-crown mr-1" style="color: var(--primary);"></i>
                                    Primary Super Admin for Billing *
                                </label>
                                <select name="primary_super_admin_id"
                                        class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                        required>
                                    <option value="">-- Select Primary Super Admin --</option>
                                    @foreach($superAdmins as $superAdmin)
                                        <option value="{{ $superAdmin->id }}"
                                            {{ old('primary_super_admin_id') == $superAdmin->id || ($primarySuperAdmin && $primarySuperAdmin->id == $superAdmin->id) ? 'selected' : '' }}>
                                            {{ $superAdmin->name }} ({{ $superAdmin->email }})
                                        </option>
                                    @endforeach
                                </select>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    This super admin will receive all billing invoices.
                                </p>
                            </div>

                            <div class="md:col-span-2">
                                <div class="p-3 rounded-lg mb-2" style="background-color: rgba(var(--primary-rgb), 0.05); border-left: 3px solid var(--primary);">
                                    <p class="text-sm font-medium mb-2" style="color: var(--text-primary);">
                                        <i class="fas fa-address-card mr-1"></i> Billing Contact Details (for Primary Super Admin)
                                    </p>
                                </div>
                            </div>

                            <div>
                                <label class="block mb-2 text-sm" style="color: var(--text-secondary);">Billing Contact Name</label>
                                <input type="text" name="billing_contact_name" value="{{ old('billing_contact_name') }}"
                                       class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="e.g., Accounts Payable">
                            </div>

                            <div>
                                <label class="block mb-2 text-sm" style="color: var(--text-secondary);">Billing Contact Email</label>
                                <input type="email" name="billing_contact_email" value="{{ old('billing_contact_email') }}"
                                       class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="billing@company.com">
                            </div>

                            <div>
                                <label class="block mb-2 text-sm" style="color: var(--text-secondary);">Billing Contact Phone</label>
                                <input type="tel" name="billing_contact_phone" value="{{ old('billing_contact_phone') }}"
                                       class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="+233 XX XXX XXXX">
                            </div>

                            <div>
                                <label class="block mb-2 text-sm" style="color: var(--text-secondary);">
                                    <i class="fas fa-money-bill-wave mr-1"></i> Amount *
                                </label>
                                <input type="number" name="amount" value="{{ old('amount', $developerSettings->monthly_billing_amount ?? '') }}"
                                       class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       min="100" max="100000" step="0.01" required>
                            </div>

                            <div>
                                <label class="block mb-2 text-sm" style="color: var(--text-secondary);">
                                    <i class="fas fa-globe mr-1"></i> Currency *
                                </label>
                                <select name="currency" class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" required>
                                    <option value="GHS" {{ old('currency', $developerSettings->billing_currency ?? 'GHS') == 'GHS' ? 'selected' : '' }}>GHS - Ghanaian Cedi</option>
                                    <option value="USD" {{ old('currency', $developerSettings->billing_currency ?? '') == 'USD' ? 'selected' : '' }}>USD - US Dollar</option>
                                    <option value="EUR" {{ old('currency') == 'EUR' ? 'selected' : '' }}>EUR - Euro</option>
                                    <option value="GBP" {{ old('currency') == 'GBP' ? 'selected' : '' }}>GBP - British Pound</option>
                                </select>
                            </div>

                            <div>
                                <label class="block mb-2 text-sm" style="color: var(--text-secondary);">
                                    <i class="fas fa-calendar-alt mr-1"></i> Billing Frequency *
                                </label>
                                <select name="billing_frequency" class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" required>
                                    <option value="monthly" {{ old('billing_frequency', $developerSettings->billing_cycle ?? 'monthly') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                    <option value="quarterly" {{ old('billing_frequency') == 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                                    <option value="yearly" {{ old('billing_frequency') == 'yearly' ? 'selected' : '' }}>Yearly</option>
                                    <option value="one_time" {{ old('billing_frequency') == 'one_time' ? 'selected' : '' }}>One Time</option>
                                </select>
                            </div>

                            <div>
                                <label class="block mb-2 text-sm" style="color: var(--text-secondary);">
                                    <i class="fas fa-calendar-plus mr-1"></i> Start Date *
                                </label>
                                <input type="date" name="start_date" value="{{ old('start_date', now()->format('Y-m-d')) }}"
                                       class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" required>
                            </div>

                            {{-- Payment Method --}}
                            <div class="md:col-span-2">
                                <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-credit-card mr-1"></i>
                                    Payment Method *
                                </label>

                                @if($availablePaymentProviders && count($availablePaymentProviders) > 0)
                                    <div class="space-y-2">
                                        <select name="payment_method" id="payment_method"
                                                class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                required>
                                            <option value="">-- Select Payment Provider --</option>
                                            <optgroup label="Your Payment Providers" style="font-weight: bold; color: var(--primary);">
                                                @foreach($availablePaymentProviders as $key => $provider)
                                                    <option value="{{ $key }}"
                                                            data-icon="{{ $provider['icon'] }}"
                                                            data-color="{{ $provider['color'] }}"
                                                            {{ old('payment_method', $developerSettings->payment_method ?? '') == $key ? 'selected' : '' }}
                                                            style="color: {{ $provider['color'] }}; font-weight: 500;">
                                                        {{ $provider['name'] }} ✅ Active
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        </select>

                                        <div id="selectedProviderInfo" class="hidden p-3 rounded-lg border"
                                             style="background-color: rgba(var(--success-rgb), 0.05); border-color: rgba(var(--success-rgb), 0.2);">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                                     id="selectedProviderIcon"
                                                     style="background-color: rgba(var(--success-rgb), 0.1);">
                                                    <i class="fas fa-credit-card" style="color: var(--success);"></i>
                                                </div>
                                                <div>
                                                    <p class="font-medium" id="selectedProviderName" style="color: var(--text-primary);">Paystack</p>
                                                    <p class="text-xs" style="color: var(--text-secondary);">
                                                        <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                                                        Provider is configured and ready for payments
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap gap-2 mt-2">
                                            @foreach($availablePaymentProviders as $key => $provider)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs"
                                                      style="background-color: {{ $provider['color'] }}20; color: {{ $provider['color'] }};">
                                                    <i class="fas {{ $provider['icon'] }} mr-1"></i>
                                                    {{ $provider['name'] }}
                                                    <span class="ml-1 text-[8px]" style="color: var(--success);">●</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <div class="space-y-2">
                                        <select name="payment_method" id="payment_method"
                                                class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                                required>
                                            <option value="">-- Select Payment Method --</option>
                                            @foreach($genericPaymentMethods as $key => $method)
                                                <option value="{{ $key }}"
                                                    {{ old('payment_method', $developerSettings->payment_method ?? '') == $key ? 'selected' : '' }}>
                                                    {{ $method['name'] }}
                                                </option>
                                            @endforeach
                                        </select>

                                        @if(!$hasBillingProvider)
                                            <div class="p-2 rounded-lg text-xs" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                No payment providers configured.
                                                @if(Route::has('developer.payment-providers.index'))
                                                    <a href="{{ route('developer.payment-providers.index') }}"
                                                       style="color: var(--primary); text-decoration: underline;">
                                                        Configure your payment provider
                                                    </a>
                                                    to enable online payments.
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    @if($availablePaymentProviders && count($availablePaymentProviders) > 0)
                                        <span style="color: var(--success);">✅ {{ count($availablePaymentProviders) }} provider(s) configured.</span>
                                        Select a provider above for online payment processing.
                                    @else
                                        <span style="color: var(--warning);">⚠️ No payment providers configured.</span>
                                        @if(Route::has('developer.payment-providers.index'))
                                            <a href="{{ route('developer.payment-providers.index') }}" style="color: var(--primary); text-decoration: underline;">
                                                Go to Payment Providers
                                            </a>
                                            to set up your billing credentials.
                                        @endif
                                    @endif
                                </p>
                            </div>

                            <div class="flex items-center">
                                <label class="flex items-center">
                                    <input type="checkbox" name="generate_pdf" value="1" class="mr-2 rounded border-gray-300 text-primary focus:ring-primary" checked>
                                    <span class="text-sm" style="color: var(--text-secondary);">
                                        <i class="fas fa-file-pdf mr-1"></i> Generate PDF automatically
                                    </span>
                                </label>
                            </div>

                            <div class="md:col-span-2">
                                <label class="flex items-center">
                                    <input type="checkbox" name="auto_send_for_signature" value="1" class="mr-2 rounded border-gray-300 text-primary focus:ring-primary" checked>
                                    <span class="text-sm" style="color: var(--text-secondary);">
                                        <i class="fas fa-signature mr-1"></i> Automatically send for electronic signature
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="block mb-2 text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-file-alt mr-1"></i> Description *
                            </label>
                            <textarea name="description" class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                      rows="3" placeholder="Describe the service or agreement terms" required>{{ old('description') }}</textarea>
                        </div>

                        <div>
                            <label class="block mb-2 text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-sticky-note mr-1"></i> Notes (Optional)
                            </label>
                            <textarea name="notes" class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                      rows="2" placeholder="Additional notes or instructions">{{ old('notes') }}</textarea>
                        </div>

                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <div class="flex justify-between items-center flex-wrap gap-3">
                                <div class="text-sm" style="color: var(--text-secondary);">
                                    <i class="fas fa-users mr-1"></i>
                                    Will be sent to all active super admins ({{ $activeSuperAdminCount }})
                                    @if(!$hasBillingProvider)
                                        <span class="ml-2 px-2 py-0.5 rounded text-xs" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                            <i class="fas fa-exclamation-triangle mr-1"></i> No payment provider configured
                                        </span>
                                    @endif
                                </div>
                                <button type="submit"
                                        class="px-6 py-3 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-md {{ !$hasBillingProvider ? 'opacity-50 cursor-not-allowed' : '' }}"
                                        style="background-color: var(--primary); color: white;"
                                        {{ !$hasBillingProvider ? 'disabled' : '' }}
                                        title="{{ !$hasBillingProvider ? 'Please configure a payment provider first' : '' }}">
                                    <i class="fas fa-paper-plane mr-2"></i> Send to All Super Admins
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Active Agreements --}}
            <div class="card">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            <i class="fas fa-handshake mr-2"></i> Active Billing Agreements
                        </h3>
                        <div class="flex items-center gap-2">
                            <span class="text-sm" style="color: var(--text-secondary);">{{ $activeAgreements->count() }} active</span>
                            <a href="{{ route('developer.billing.agreements-list') }}?status=active"
                               class="text-sm flex items-center" style="color: var(--primary);">View All →</a>
                        </div>
                    </div>

                    @if($activeAgreements->isNotEmpty())
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                                        <th class="text-left p-4" style="color: var(--text-secondary);">Agreement</th>
                                        <th class="text-left p-4" style="color: var(--text-secondary);">Super Admin</th>
                                        <th class="text-left p-4" style="color: var(--text-secondary);">Primary</th>
                                        <th class="text-left p-4" style="color: var(--text-secondary);">Amount</th>
                                        <th class="text-left p-4" style="color: var(--text-secondary);">Status</th>
                                        <th class="text-left p-4" style="color: var(--text-secondary);">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($activeAgreements->take(5) as $agreement)
                                        <tr class="border-b transition-colors duration-200"
                                            style="border-color: var(--border-color);"
                                            onmouseover="this.style.backgroundColor='rgba(var(--primary-rgb), 0.04)'"
                                            onmouseout="this.style.backgroundColor='transparent'">
                                            <td class="p-4">
                                                <div>
                                                    <a href="{{ route('developer.billing.view-agreement', $agreement->id) }}"
                                                       class="font-medium hover:text-primary transition-colors" style="color: var(--text-primary);">
                                                        {{ $agreement->agreement_number }}
                                                    </a>
                                                    <div class="text-sm mt-1" style="color: var(--text-secondary); max-width: 200px;">
                                                        {{ Str::limit($agreement->description, 50) }}
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="p-4">
                                                <div style="color: var(--text-primary);">{{ $agreement->superAdmin->name ?? 'N/A' }}</div>
                                                <div class="text-sm mt-1" style="color: var(--text-secondary);">{{ $agreement->superAdmin->email ?? '' }}</div>
                                            </td>
                                            <td class="p-4">
                                                @if($agreement->is_primary_for_billing)
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs"
                                                          style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                                        <i class="fas fa-crown mr-1"></i> Primary
                                                    </span>
                                                @else
                                                    <span class="text-xs" style="color: var(--text-secondary);">—</span>
                                                @endif
                                            </td>
                                            <td class="p-4">
                                                <div style="color: var(--text-primary); font-weight: 500;">
                                                    {{ $formatCurrency($agreement->amount, $agreement->currency) }}
                                                </div>
                                                <div class="text-sm mt-1 {{ $agreement->payment_status == 'paid' ? 'text-success' : ($agreement->payment_status == 'partial' ? 'text-warning' : 'text-danger') }}">
                                                    <i class="fas {{ $agreement->payment_status == 'paid' ? 'fa-check-circle' : ($agreement->payment_status == 'partial' ? 'fa-hourglass-half' : 'fa-times-circle') }} mr-1"></i>
                                                    {{ ucfirst($agreement->payment_status) }}
                                                </div>
                                            </td>
                                            <td class="p-4">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm"
                                                      style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                    <div class="w-2 h-2 rounded-full mr-2" style="background-color: var(--success);"></div>
                                                    Active
                                                </span>
                                            </td>
                                            <td class="p-4">
                                                <div class="flex items-center gap-2">
                                                    <a href="{{ route('developer.billing.view-agreement', $agreement->id) }}"
                                                       class="action-btn" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    @if($agreement->agreement_pdf_path)
                                                        <a href="{{ route('developer.billing.generate-agreement-pdf', $agreement->id) }}"
                                                           class="action-btn" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);" title="Download PDF">
                                                            <i class="fas fa-file-pdf"></i>
                                                        </a>
                                                    @endif
                                                    @if($agreement->signed_agreement_pdf_path)
                                                        <a href="{{ route('developer.billing.download-signed-agreement', $agreement->id) }}"
                                                           class="action-btn" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);" title="Download Signed Agreement">
                                                            <i class="fas fa-file-signature"></i>
                                                        </a>
                                                    @endif
                                                    @if($agreement->is_primary_for_billing && $agreement->signed_agreement_pdf_path)
                                                        <a href="{{ route('developer.billing.download-agreement-invoice', $agreement->id) }}"
                                                           class="action-btn" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);" title="Download Invoice">
                                                            <i class="fas fa-file-invoice"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if($activeAgreements->count() > 5)
                            <div class="mt-4 pt-4 border-t text-center" style="border-color: var(--border-color);">
                                <a href="{{ route('developer.billing.agreements-list') }}?status=active"
                                   class="px-4 py-2 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-1"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                                    <i class="fas fa-list mr-2"></i> View All {{ $activeAgreements->count() }} Active Agreements
                                </a>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-8" style="color: var(--text-secondary);">
                            <i class="fas fa-handshake text-4xl mb-4 opacity-50"></i>
                            <p class="mb-2">No active agreements yet</p>
                            <p class="text-sm">Create your first billing agreement above</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Awaiting Signature --}}
            @if($pendingApprovalsList->isNotEmpty())
                <div class="card">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                                <i class="fas fa-signature mr-2"></i> Need Your Signature
                            </h3>
                            <span class="text-sm" style="color: var(--warning);">{{ $pendingApprovalsList->count() }} pending</span>
                        </div>

                        <div class="space-y-4">
                            @foreach($pendingApprovalsList->take(5) as $agreement)
                                <div class="p-4 rounded-lg border transition-all duration-200 hover:shadow-md"
                                     style="border-color: var(--border-color); background-color: rgba(var(--warning-rgb), 0.05);">
                                    <div class="flex justify-between items-start flex-wrap gap-3">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                <a href="{{ route('developer.billing.view-agreement-for-signing', $agreement->id) }}"
                                                   class="font-medium hover:text-primary transition-colors" style="color: var(--text-primary);">
                                                    {{ $agreement->agreement_number }}
                                                </a>
                                                @if($agreement->is_primary_for_billing)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs"
                                                          style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                                        <i class="fas fa-crown mr-1"></i> Primary
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="text-sm mt-1" style="color: var(--text-secondary);">
                                                <i class="fas fa-user-shield mr-1"></i> {{ $agreement->superAdmin->name ?? 'N/A' }}
                                            </div>
                                            <div class="text-sm font-medium mt-2" style="color: var(--primary);">
                                                {{ $formatCurrency($agreement->amount, $agreement->currency) }}
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            @if($agreement->agreement_pdf_path)
                                                <a href="{{ route('developer.billing.view-agreement-for-signing', $agreement->id) }}"
                                                   class="inline-block px-5 py-2.5 rounded-lg text-sm font-medium transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-md"
                                                   style="background-color: var(--warning); color: white;">
                                                    <i class="fas fa-pen mr-2"></i> Sign Now
                                                </a>
                                            @else
                                                <button onclick="generateAndSign({{ $agreement->id }})"
                                                        class="inline-block px-5 py-2.5 rounded-lg text-sm font-medium transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-md"
                                                        style="background-color: var(--primary); color: white;">
                                                    <i class="fas fa-file-pdf mr-2"></i> Generate & Sign
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            @if($pendingApprovalsList->count() > 5)
                                <div class="text-center pt-2">
                                    <a href="{{ route('developer.billing.agreements-list') }}?status=pending&needs_signature=1"
                                       class="text-sm" style="color: var(--primary);">
                                        View All {{ $pendingApprovalsList->count() }} Agreements →
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- Recent Activity --}}
            <div class="card">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            <i class="fas fa-chart-line mr-2"></i> Recent Activity
                        </h3>
                        <a href="{{ route('developer.billing.history') }}" class="text-sm" style="color: var(--primary);">View All →</a>
                    </div>

                    <div class="space-y-3">
                        @php
                            $recentActivities = collect();

                            if ($activeAgreements->isNotEmpty()) {
                                foreach ($activeAgreements->take(3) as $agreement) {
                                    $recentActivities->push([
                                        'icon'        => 'handshake',
                                        'color'       => 'success',
                                        'title'       => 'Agreement Created',
                                        'description' => "Agreement #{$agreement->agreement_number} created",
                                        'time'        => $agreement->created_at->diffForHumans(),
                                        'timestamp'   => $agreement->created_at,
                                    ]);
                                }
                            }

                            if (isset($recentlySigned) && $recentlySigned->isNotEmpty()) {
                                foreach ($recentlySigned->take(2) as $agreement) {
                                    $recentActivities->push([
                                        'icon'        => 'signature',
                                        'color'       => 'info',
                                        'title'       => 'Agreement Signed',
                                        'description' => "Agreement #{$agreement->agreement_number} signed",
                                        'time'        => \Carbon\Carbon::parse($agreement->signing_completed_at)->diffForHumans(),
                                        'timestamp'   => $agreement->signing_completed_at,
                                    ]);
                                }
                            }

                            $recentActivities = $recentActivities->sortByDesc('timestamp')->take(5);
                        @endphp

                        @if($recentActivities->isNotEmpty())
                            @foreach($recentActivities as $activity)
                                <div class="flex items-start p-3 rounded-lg transition-colors duration-200"
                                     style="border: 1px solid transparent;"
                                     onmouseover="this.style.borderColor='var(--border-color)'; this.style.backgroundColor='rgba(var(--primary-rgb), 0.03)'"
                                     onmouseout="this.style.borderColor='transparent'; this.style.backgroundColor='transparent'">
                                    <div class="p-2 rounded-full mr-3" style="background-color: rgba(var(--{{ $activity['color'] }}-rgb), 0.1);">
                                        <i class="fas fa-{{ $activity['icon'] }} text-sm" style="color: var(--{{ $activity['color'] }});"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium" style="color: var(--text-primary);">{{ $activity['title'] }}</p>
                                        <p class="text-xs mt-0.5" style="color: var(--text-secondary);">{{ $activity['description'] }}</p>
                                        <p class="text-xs mt-1" style="color: var(--text-secondary); opacity: 0.7;">{{ $activity['time'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="text-center py-4" style="color: var(--text-secondary);">
                                <i class="fas fa-inbox text-2xl mb-2 opacity-50"></i>
                                <p class="text-sm">No recent activity</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT COLUMN --}}
        <div class="lg:col-span-1 space-y-6">

            {{-- Quick Actions --}}
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                        <i class="fas fa-bolt mr-2"></i> Quick Actions
                    </h3>

                    <div class="space-y-3">
                        @if(Route::has('developer.payment-providers.index'))
                            <a href="{{ route('developer.payment-providers.index') }}"
                               class="flex items-center px-4 py-3 rounded-lg border transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-md"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                <div class="p-2 rounded-lg mr-3" style="background-color: rgba(var(--info-rgb), 0.1);">
                                    <i class="fas fa-credit-card" style="color: var(--info);"></i>
                                </div>
                                <div class="flex-1">
                                    <div style="color: var(--text-primary); font-weight: 500;">Payment Providers</div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        @if($hasBillingProvider)
                                            <span style="color: var(--success);">✅ {{ count($availablePaymentProviders) }} provider(s) configured</span>
                                        @else
                                            <span style="color: var(--warning);">⚠️ No provider configured</span>
                                        @endif
                                    </div>
                                </div>
                                <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                            </a>
                        @endif

                        <a href="{{ route('developer.billing.process-recurring') }}"
                           onclick="return confirm('Process recurring billing now? This will generate invoices for all active agreements.')"
                           class="flex items-center px-4 py-3 rounded-lg border transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-md"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <div class="p-2 rounded-lg mr-3" style="background-color: rgba(var(--primary-rgb), 0.1);">
                                <i class="fas fa-sync-alt" style="color: var(--primary);"></i>
                            </div>
                            <div class="flex-1">
                                <div style="color: var(--text-primary); font-weight: 500;">Process Recurring Billing</div>
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">Generate invoices for all active agreements</div>
                            </div>
                        </a>

                        @if(Route::has('developer.settings.index'))
                            <a href="{{ route('developer.settings.index', ['section' => 'billing']) }}"
                               class="flex items-center px-4 py-3 rounded-lg border transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-md"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                <div class="p-2 rounded-lg mr-3" style="background-color: rgba(var(--warning-rgb), 0.1);">
                                    <i class="fas fa-cog" style="color: var(--warning);"></i>
                                </div>
                                <div class="flex-1">
                                    <div style="color: var(--text-primary); font-weight: 500;">Update Billing Settings</div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Configure billing rules and payment methods</div>
                                </div>
                            </a>
                        @endif

                        <button onclick="showCustomInvoiceModal()"
                                class="w-full text-left flex items-center px-4 py-3 rounded-lg border transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-md"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <div class="p-2 rounded-lg mr-3" style="background-color: rgba(var(--info-rgb), 0.1);">
                                <i class="fas fa-file-invoice-dollar" style="color: var(--info);"></i>
                            </div>
                            <div class="flex-1">
                                <div style="color: var(--text-primary); font-weight: 500;">Generate Custom Invoice</div>
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">Create one-time charges or adjustments</div>
                            </div>
                        </button>

                        <button onclick="showChangePrimaryAdminModal()"
                                class="w-full text-left flex items-center px-4 py-3 rounded-lg border transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-md"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <div class="p-2 rounded-lg mr-3" style="background-color: rgba(var(--primary-rgb), 0.1);">
                                <i class="fas fa-crown" style="color: var(--primary);"></i>
                            </div>
                            <div class="flex-1">
                                <div style="color: var(--text-primary); font-weight: 500;">Change Primary Super Admin</div>
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">Change who receives billing invoices</div>
                            </div>
                        </button>

                        <a href="{{ route('developer.billing.export') }}"
                           class="flex items-center px-4 py-3 rounded-lg border transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-md"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <div class="p-2 rounded-lg mr-3" style="background-color: rgba(var(--success-rgb), 0.1);">
                                <i class="fas fa-download" style="color: var(--success);"></i>
                            </div>
                            <div class="flex-1">
                                <div style="color: var(--text-primary); font-weight: 500;">Export Billing Data</div>
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">Download CSV reports</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Billing Overview --}}
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                        <i class="fas fa-chart-pie mr-2"></i> Billing Overview
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-sm mb-2" style="color: var(--text-secondary);">
                                <span>Collection Rate</span>
                                <span>
                                    @php
                                        $totalAgreed = $stats['total_amount_agreed'] ?? 0;
                                        $totalReceived = $stats['total_amount_received'] ?? 0;
                                        $collectionRate = $totalAgreed > 0 ? ($totalReceived / $totalAgreed) * 100 : 0;
                                    @endphp
                                    {{ number_format($collectionRate, 1) }}%
                                </span>
                            </div>
                            <div class="w-full rounded-full h-2" style="background-color: rgba(var(--success-rgb), 0.2);">
                                <div class="rounded-full h-2 transition-all duration-500"
                                     style="width: {{ $collectionRate }}%; background-color: var(--success);"></div>
                            </div>
                        </div>

                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <div class="flex justify-between items-center py-2">
                                <span class="text-sm" style="color: var(--text-secondary);">Total Amount Agreed</span>
                                <span class="font-semibold" style="color: var(--text-primary);">
                                    {{ $formatCurrency($stats['total_amount_agreed'] ?? 0, $developerSettings->billing_currency ?? 'GHS') }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center py-2">
                                <span class="text-sm" style="color: var(--text-secondary);">Total Amount Received</span>
                                <span class="font-semibold" style="color: var(--success);">
                                    {{ $formatCurrency($stats['total_amount_received'] ?? 0, $developerSettings->billing_currency ?? 'GHS') }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center py-2">
                                <span class="text-sm" style="color: var(--text-secondary);">Outstanding Balance</span>
                                <span class="font-semibold" style="color: {{ ($stats['total_amount_agreed'] ?? 0) > ($stats['total_amount_received'] ?? 0) ? 'var(--warning)' : 'var(--success)' }};">
                                    {{ $formatCurrency(($stats['total_amount_agreed'] ?? 0) - ($stats['total_amount_received'] ?? 0), $developerSettings->billing_currency ?? 'GHS') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Pending Payments --}}
            <div id="pending-payments-section" class="card">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            <i class="fas fa-clock mr-2"></i> Pending Payment Confirmations
                        </h3>
                        <span class="text-sm" style="color: var(--danger);">{{ $pendingPayments->count() }} pending</span>
                    </div>

                    @if($pendingPayments->isNotEmpty())
                        <div class="space-y-4">
                            @foreach($pendingPayments->take(3) as $payment)
                                <div class="p-4 rounded-lg border" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                                    <div class="flex justify-between items-start flex-wrap gap-3">
                                        <div>
                                            <div style="color: var(--text-primary); font-weight: 500;">{{ $payment->super_admin_name }}</div>
                                            <div class="text-sm mt-1" style="color: var(--text-secondary);">{{ $payment->agreement_number }}</div>
                                            <div class="text-sm font-medium mt-2" style="color: var(--primary);">
                                                {{ $formatCurrency($payment->amount_paid, $payment->currency ?? 'GHS') }}
                                            </div>
                                            <div class="text-xs mt-2" style="color: var(--text-secondary);">
                                                Paid: {{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <button onclick="showConfirmPaymentModal({{ $payment->id }}, {{ $payment->amount_paid }})"
                                                    class="px-3 py-1 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-md"
                                                    style="background-color: var(--success); color: white; font-size: 0.875rem;">
                                                <i class="fas fa-check mr-1"></i> Confirm
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            @if($pendingPayments->count() > 3)
                                <div class="text-center pt-2">
                                    <a href="{{ route('developer.billing.superadmin-payments') }}"
                                       class="text-sm" style="color: var(--primary);">
                                        View All {{ $pendingPayments->count() }} Pending Payments →
                                    </a>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="text-center py-4" style="color: var(--text-secondary);">
                            <i class="fas fa-check-circle text-2xl mb-2 opacity-50"></i>
                            <p>No pending payments</p>
                            <p class="text-sm mt-1">All payments have been confirmed</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================
     MODALS
============================================================ --}}

{{-- Change Primary Super Admin --}}
<div id="changePrimaryAdminModal" class="modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Change Primary Super Admin</h3>
            <button type="button" class="modal-close" onclick="closeModal('changePrimaryAdminModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="changePrimaryAdminForm" method="POST" action="{{ route('developer.billing.change-primary-super-admin') }}">
                @csrf
                <div class="space-y-4">
                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <p class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            The Primary Super Admin receives all billing invoices and is the main billing contact.
                        </p>
                    </div>

                    <div>
                        <label class="block mb-2 text-sm" style="color: var(--text-secondary);">Select Primary Super Admin *</label>
                        <select name="new_primary_super_admin_id"
                                class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" required>
                            <option value="">-- Select Super Admin --</option>
                            @foreach($superAdmins as $superAdmin)
                                <option value="{{ $superAdmin->id }}" {{ ($primarySuperAdmin && $primarySuperAdmin->id == $superAdmin->id) ? 'selected' : '' }}>
                                    {{ $superAdmin->name }} ({{ $superAdmin->email }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block mb-2 text-sm" style="color: var(--text-secondary);">Billing Contact Name</label>
                        <input type="text" name="billing_contact_name"
                               class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="e.g., Accounts Payable">
                    </div>

                    <div>
                        <label class="block mb-2 text-sm" style="color: var(--text-secondary);">Billing Contact Email</label>
                        <input type="email" name="billing_contact_email"
                               class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="billing@company.com">
                    </div>

                    <div>
                        <label class="block mb-2 text-sm" style="color: var(--text-secondary);">Billing Contact Phone</label>
                        <input type="tel" name="billing_contact_phone"
                               class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="+233 XX XXX XXXX">
                    </div>

                    <div>
                        <label class="block mb-2 text-sm" style="color: var(--text-secondary);">Reason for Change (Optional)</label>
                        <textarea name="reason" class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  rows="2" placeholder="Why are you changing the primary super admin?"></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="flex-1 py-3 rounded-lg border transition-all duration-200"
                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                    onclick="closeModal('changePrimaryAdminModal')">Cancel</button>
            <button type="submit" form="changePrimaryAdminForm"
                    class="flex-1 py-3 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-md"
                    style="background-color: var(--primary); color: white;">
                <i class="fas fa-save mr-2"></i> Save Changes
            </button>
        </div>
    </div>
</div>

{{-- Confirm Payment --}}
<div id="confirmPaymentModal" class="modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Confirm Payment</h3>
            <button type="button" class="modal-close" onclick="closeModal('confirmPaymentModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="confirmPaymentForm" method="POST" action="{{ route('developer.billing.confirm-payment') }}">
                @csrf
                <input type="hidden" name="payment_id" id="paymentId">
                <div class="space-y-4">
                    <div>
                        <label class="block mb-2 text-sm" style="color: var(--text-secondary);">Payment Amount *</label>
                        <input type="number" name="amount_paid" id="paymentAmount"
                               class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               min="0" step="0.01" required>
                    </div>

                    <div>
                        <label class="block mb-2 text-sm" style="color: var(--text-secondary);">Payment Date *</label>
                        <input type="date" name="payment_date"
                               class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               max="{{ now()->format('Y-m-d') }}" value="{{ now()->format('Y-m-d') }}" required>
                    </div>

                    <div>
                        <label class="block mb-2 text-sm" style="color: var(--text-secondary);">Payment Method</label>
                        <select name="payment_method" class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            @if($availablePaymentProviders && count($availablePaymentProviders) > 0)
                                @foreach($availablePaymentProviders as $key => $provider)
                                    <option value="{{ $key }}">{{ $provider['name'] }}</option>
                                @endforeach
                            @endif
                            @foreach($genericPaymentMethods as $key => $method)
                                <option value="{{ $key }}">{{ $method['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block mb-2 text-sm" style="color: var(--text-secondary);">Transaction Reference</label>
                        <input type="text" name="transaction_reference"
                               class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="Bank reference or transaction ID">
                    </div>

                    <div>
                        <label class="block mb-2 text-sm" style="color: var(--text-secondary);">Notes</label>
                        <textarea name="notes" class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  rows="2" placeholder="Any additional notes about this payment"></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="flex-1 py-3 rounded-lg border transition-all duration-200"
                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                    onclick="closeModal('confirmPaymentModal')">Cancel</button>
            <button type="submit" form="confirmPaymentForm"
                    class="flex-1 py-3 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-md"
                    style="background-color: var(--success); color: white;">
                <i class="fas fa-check mr-2"></i> Confirm Payment
            </button>
        </div>
    </div>
</div>

{{-- Custom Invoice --}}
<div id="customInvoiceModal" class="modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Generate Custom Invoice</h3>
            <button type="button" class="modal-close" onclick="closeModal('customInvoiceModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="customInvoiceForm" method="POST" action="{{ route('developer.billing.generate-custom-invoice') }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block mb-2 text-sm" style="color: var(--text-secondary);">Amount *</label>
                        <input type="number" name="amount"
                               class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               min="1" max="100000" step="0.01" required>
                    </div>

                    <div>
                        <label class="block mb-2 text-sm" style="color: var(--text-secondary);">Description *</label>
                        <textarea name="description" class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  rows="3" placeholder="Describe the charge" required></textarea>
                    </div>

                    <div>
                        <label class="block mb-2 text-sm" style="color: var(--text-secondary);">Invoice Type *</label>
                        <select name="invoice_type" class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" required>
                            <option value="one_time">One Time Charge</option>
                            <option value="additional">Additional Service</option>
                            <option value="penalty">Penalty/Fine</option>
                            <option value="adjustment">Adjustment</option>
                        </select>
                    </div>

                    <div>
                        <label class="block mb-2 text-sm" style="color: var(--text-secondary);">Due Date *</label>
                        <input type="date" name="due_date"
                               class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               min="{{ now()->format('Y-m-d') }}" value="{{ now()->addDays(30)->format('Y-m-d') }}" required>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="flex-1 py-3 rounded-lg border transition-all duration-200"
                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                    onclick="closeModal('customInvoiceModal')">Cancel</button>
            <button type="submit" form="customInvoiceForm"
                    class="flex-1 py-3 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-md"
                    style="background-color: var(--primary); color: white;">
                <i class="fas fa-file-invoice-dollar mr-2"></i> Generate Invoice
            </button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// ============================================
// GLOBAL VARIABLES
// ============================================
let currentAgreementId = null;

// ============================================
// PAYMENT PROVIDER SELECTION DISPLAY
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethodSelect = document.getElementById('payment_method');
    const providerInfo = document.getElementById('selectedProviderInfo');
    const providerIcon = document.getElementById('selectedProviderIcon');
    const providerName = document.getElementById('selectedProviderName');

    if (paymentMethodSelect) {
        paymentMethodSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const isProvider = selectedOption.dataset.icon !== undefined;

            if (isProvider && this.value && providerInfo) {
                const icon = selectedOption.dataset.icon || 'fa-credit-card';
                const color = selectedOption.dataset.color || 'var(--success)';
                const name = selectedOption.text.replace(/✅ Active/g, '').trim();

                providerInfo.style.display = 'block';
                providerIcon.innerHTML = `<i class="fas ${icon}" style="color: ${color};"></i>`;
                providerIcon.style.backgroundColor = color + '20';
                providerName.textContent = name;
                providerInfo.style.animation = 'fadeIn 0.3s ease-out';
            } else if (providerInfo) {
                providerInfo.style.display = 'none';
            }
        });

        const event = new Event('change');
        paymentMethodSelect.dispatchEvent(event);
    }

    // Close modal on overlay click
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            const modal = e.target.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
    });

    // Close modal on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal').forEach(modal => {
                if (modal.style.display === 'block') {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
        }
    });

    // Form loading states
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const btn = this.querySelector('button[type="submit"]');
            if (btn) {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
                btn.disabled = true;
            }
        });
    });

    // Show flash messages
    @if(session('success'))
        showToast("{{ session('success') }}", 'success');
    @endif
    @if(session('error'))
        showToast("{{ session('error') }}", 'error');
    @endif
    @if(session('warning'))
        showToast("{{ session('warning') }}", 'warning');
    @endif
});

// ============================================
// MODAL FUNCTIONS
// ============================================
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

function showCustomInvoiceModal() { showModal('customInvoiceModal'); }
function showChangePrimaryAdminModal() { showModal('changePrimaryAdminModal'); }

function showConfirmPaymentModal(paymentId, amount) {
    const idInput = document.getElementById('paymentId');
    const amountInput = document.getElementById('paymentAmount');
    if (idInput) idInput.value = paymentId;
    if (amountInput) amountInput.value = amount;
    showModal('confirmPaymentModal');
}

// ============================================
// GENERATE AND SIGN
// ============================================
function generateAndSign(agreementId) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!csrfToken) {
        showToast('CSRF token missing', 'error');
        return;
    }

    fetch('/developer/billing/agreement/' + agreementId + '/generate-pdf', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = '/developer/billing/agreement/' + agreementId + '/view-signing';
        } else {
            showToast(data.message || 'Failed to generate PDF', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error generating PDF', 'error');
    });
}

// ============================================
// COPY WEBHOOK URL
// ============================================
function copyWebhookUrl(provider, url) {
    const prettyName = provider.charAt(0).toUpperCase() + provider.slice(1);
    navigator.clipboard.writeText(url).then(() => {
        showToast(prettyName + ' webhook URL copied!', 'success');
    }).catch(() => {
        const textarea = document.createElement('textarea');
        textarea.value = url;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showToast(prettyName + ' webhook URL copied!', 'success');
    });
}

// ============================================
// TOAST
// ============================================
function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed top-4 right-4 z-50 space-y-3';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = 'flex items-center p-4 rounded-lg shadow-lg transform transition-all duration-300 translate-x-full';

    const colors = {
        success: { bg: 'rgba(var(--success-rgb), 0.9)', text: 'white', icon: 'fa-check-circle' },
        error:   { bg: 'rgba(var(--danger-rgb), 0.9)',  text: 'white', icon: 'fa-exclamation-circle' },
        warning: { bg: 'rgba(var(--warning-rgb), 0.9)', text: 'white', icon: 'fa-exclamation-triangle' },
        info:    { bg: 'rgba(var(--info-rgb), 0.9)',    text: 'white', icon: 'fa-info-circle' }
    };

    const color = colors[type] || colors.info;
    toast.style.backgroundColor = color.bg;
    toast.style.color = color.text;

    toast.innerHTML = `
        <i class="fas ${color.icon} mr-3 text-lg"></i>
        <span class="flex-1">${message}</span>
        <button type="button" class="ml-4 text-lg opacity-70 hover:opacity-100" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;

    container.appendChild(toast);
    setTimeout(() => toast.style.transform = 'translateX(0)', 10);
    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}
</script>
@endpush

@push('styles')
<style>
.action-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999; }

.modal-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
}

.modal-container {
    position: relative;
    background-color: var(--card-bg);
    border-radius: 16px;
    margin: 2rem auto;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    border: 1px solid var(--border-color);
    animation: modalFadeIn 0.3s ease-out;
}

.modal-header {
    padding: 1.5rem 1.5rem 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title { font-size: 1.25rem; font-weight: 600; color: var(--text-primary); margin: 0; }

.modal-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 1.25rem;
    padding: 0.25rem;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.modal-close:hover { background-color: rgba(var(--primary-rgb), 0.1); color: var(--text-primary); }

.modal-body { padding: 1.5rem; }

.modal-footer {
    padding: 1rem 1.5rem 1.5rem 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    gap: 1rem;
}

@keyframes modalFadeIn {
    from { opacity: 0; transform: scale(0.9) translateY(-20px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-5px); }
    to   { opacity: 1; transform: translateY(0); }
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}

.fa-spinner { animation: spin 1s linear infinite; }

@media (max-width: 768px) {
    .modal-container { margin: 1rem; width: calc(100% - 2rem); }
    .modal-footer { flex-direction: column; }
    .billing-navigation { flex-wrap: nowrap; overflow-x: auto; white-space: nowrap; }
    .billing-navigation a { flex-shrink: 0; }
}

@media (max-width: 480px) {
    .grid-cols-1.md\:grid-cols-2 { grid-template-columns: 1fr; }
    .grid-cols-2 { grid-template-columns: 1fr; }
}
</style>
@endpush