{{-- resources/views/superadmin/billing/dashboard.blade.php --}}
@extends('layouts.app')

@php
    use Carbon\Carbon;

    $pageTitle = 'Super Admin Billing Dashboard';

    if (!function_exists('formatCurrency')) {
        function formatCurrency($amount, $currency = 'GHS') {
            if (empty($amount)) return 'GH₵0.00';
            if ($currency === 'GHS') {
                return 'GH₵' . number_format((float)$amount, 2);
            }
            return $currency . ' ' . number_format((float)$amount, 2);
        }
    }

    if (!function_exists('getPaymentMethodIcon')) {
        function getPaymentMethodIcon($method) {
            return match($method) {
                'mtn' => 'fas fa-mobile-alt',
                'telecel' => 'fas fa-mobile-alt',
                'airteltigo' => 'fas fa-mobile-alt',
                'bank_transfer' => 'fas fa-university',
                'cash' => 'fas fa-money-bill-wave',
                default => 'fas fa-credit-card'
            };
        }
    }

    if (!function_exists('getPaymentMethodName')) {
        function getPaymentMethodName($method) {
            return match($method) {
                'mtn' => 'MTN Mobile Money',
                'telecel' => 'Telecel Cash',
                'airteltigo' => 'AirtelTigo Money',
                'bank_transfer' => 'Bank Transfer',
                'cash' => 'Cash',
                default => ucfirst(str_replace('_', ' ', $method))
            };
        }
    }

    $isDarkMode   = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] === 'true' : false;
    $currentMonth = Carbon::now()->format('F Y');
    $currentDate  = Carbon::now()->format('F j, Y');

    // Safe references to values the controller may or may not provide
    $developerSettings  = $developerSettings  ?? null;
    $paymentMethods     = $paymentMethods     ?? [];
    $onlineProviders    = $onlineProviders    ?? [];
    $agreements         = $agreements         ?? collect();
    $pendingPayments    = $pendingPayments    ?? collect();
    $recentInvoices     = $recentInvoices     ?? collect();
    $awaitingSignature  = $awaitingSignature  ?? collect();
    $isPrimaryForBilling = $isPrimaryForBilling ?? false;
    $primaryBillingInfo  = $primaryBillingInfo  ?? null;
    $stats               = $stats               ?? [];
@endphp

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    {{-- Header Card with Primary Badge --}}
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-3">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-chart-line text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center flex-wrap gap-2" style="color: var(--text-primary);">
                        <i class="fas fa-chart-line" style="color: var(--primary);"></i>
                        Billing Dashboard
                        @if($isPrimaryForBilling)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium"
                              style="background-color: rgba(var(--success-rgb), 0.15); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                            <i class="fas fa-star mr-1"></i> Primary Billing Contact
                        </span>
                        @endif
                    </h2>
                    <div class="text-sm flex items-center mt-1 flex-wrap gap-2" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle"></i>
                        <span>Manage your billing agreements, payments, and reports</span>
                        <span>•</span>
                        <i class="fas fa-circle" style="color: var(--success); font-size: 0.5rem;"></i>
                        <span class="font-medium">{{ number_format($stats['total_agreements'] ?? 0) }} total agreements</span>
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ $currentDate }}
                <div class="flex items-center space-x-2 mt-2">
                    @if($developerSettings && $developerSettings->developer_name)
                    <span class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center badge-primary">
                        <i class="fas fa-code mr-1"></i> {{ $developerSettings->developer_name }}
                    </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Primary Billing Info Banner --}}
    @if($isPrimaryForBilling && $primaryBillingInfo)
    <div class="card">
        <div class="flex items-center p-4" style="background: linear-gradient(135deg, rgba(var(--success-rgb), 0.1) 0%, rgba(var(--primary-rgb), 0.05) 100%); border-left: 4px solid var(--success); border-radius: 12px;">
            <div class="flex-shrink-0">
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.2);">
                    <i class="fas fa-star-of-life" style="color: var(--success);"></i>
                </div>
            </div>
            <div class="ml-3">
                <p class="text-sm font-semibold" style="color: var(--success);">Primary Billing Contact Information</p>
                <p class="text-xs" style="color: var(--text-secondary);">
                    <strong>Contact:</strong> {{ $primaryBillingInfo['contact_name'] ?? 'N/A' }}
                    ({{ $primaryBillingInfo['contact_email'] ?? 'N/A' }})
                    @if(!empty($primaryBillingInfo['contact_phone'])) • {{ $primaryBillingInfo['contact_phone'] }} @endif
                    <br>
                    <strong>Agreement:</strong> {{ $primaryBillingInfo['agreement_number'] ?? 'N/A' }}
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium badge-success">
                        <i class="fas fa-check-circle mr-1" style="font-size: 0.625rem;"></i> Active
                    </span>
                </p>
            </div>
        </div>
    </div>
    @endif

    {{-- Flash Messages --}}
    @foreach(['success' => 'check-circle', 'error' => 'exclamation-circle', 'warning' => 'exclamation-triangle', 'info' => 'info-circle'] as $type => $icon)
        @if(session($type))
        <div class="card">
            <div class="flex items-center p-4"
                 style="background-color: rgba(var(--{{ $type === 'error' ? 'danger' : $type }}-rgb), 0.1);
                        border: 1px solid rgba(var(--{{ $type === 'error' ? 'danger' : $type }}-rgb), 0.3);
                        border-radius: 12px;">
                <div class="flex-shrink-0">
                    <i class="fas fa-{{ $icon }} text-xl" style="color: var(--{{ $type === 'error' ? 'danger' : $type }});"></i>
                </div>
                <div class="ml-3 flex-1">
                    <p style="color: var(--{{ $type === 'error' ? 'danger' : $type }}); font-weight: 500;">{{ session($type) }}</p>
                </div>
                <button type="button" onclick="this.closest('.card').remove()" class="ml-auto">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
        </div>
        @endif
    @endforeach

    {{-- Signature Completion Detail (extra info) --}}
    @if(session('signature_complete'))
    <div class="card">
        <div class="flex items-center p-4"
             style="background-color: rgba(var(--success-rgb), 0.15); border: 1px solid rgba(var(--success-rgb), 0.4); border-radius: 12px;">
            <div class="flex-shrink-0">
                <i class="fas fa-signature text-xl" style="color: var(--success);"></i>
            </div>
            <div class="ml-3 flex-1">
                @if(session('signature_id'))
                <p class="text-xs" style="color: var(--text-secondary);">
                    Signature ID: {{ session('signature_id') }}
                </p>
                @endif
                @if(session('invoice_generated') && session('invoice_number'))
                <p class="text-xs" style="color: var(--success);">
                    <i class="fas fa-file-invoice mr-1"></i> Invoice #{{ session('invoice_number') }} generated
                </p>
                @endif
            </div>
            <button type="button" onclick="this.closest('.card').remove()" class="ml-auto">
                <i class="fas fa-times" style="color: var(--text-secondary);"></i>
            </button>
        </div>
    </div>
    @endif

    {{-- Navigation Card --}}
    <div class="card p-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center space-x-3 flex-wrap gap-2">
                <a href="{{ route('superadmin.dashboard') }}"
                   class="inline-flex items-center text-sm font-medium"
                   style="color: var(--primary);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>

                <a href="{{ route('superadmin.billing.agreements-list') }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-list-alt mr-1"></i> All Agreements
                </a>

                <a href="{{ route('superadmin.billing.reports') }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-chart-bar mr-1"></i> Reports
                </a>

                <a href="{{ route('superadmin.billing.history') }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-history mr-1"></i> Payment History
                </a>
            </div>

            @if(!$developerSettings)
            <div class="text-xs px-3 py-1 rounded-full"
                 style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                <i class="fas fa-exclamation-triangle mr-1"></i> System billing not configured
            </div>
            @endif
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {{-- Total Agreements --}}
        <div class="card stat-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-file-contract text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Agreements</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($stats['total_agreements'] ?? 0) }}</p>
                    <div class="flex flex-wrap gap-1 mt-1">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium badge-success">
                            <i class="fas fa-check mr-1" style="font-size: 0.625rem;"></i>
                            {{ number_format($stats['active_agreements'] ?? 0) }} Active
                        </span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium badge-warning">
                            <i class="fas fa-clock mr-1" style="font-size: 0.625rem;"></i>
                            {{ number_format($stats['pending_agreements'] ?? 0) }} Pending
                        </span>
                        @if(($stats['completed_agreements'] ?? 0) > 0)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium badge-info">
                            <i class="fas fa-check-double mr-1" style="font-size: 0.625rem;"></i>
                            {{ number_format($stats['completed_agreements']) }} Completed
                        </span>
                        @endif
                        @if(($stats['terminated_agreements'] ?? 0) > 0)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium badge-danger">
                            <i class="fas fa-ban mr-1" style="font-size: 0.625rem;"></i>
                            {{ number_format($stats['terminated_agreements']) }} Terminated
                        </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Total Amount --}}
        <div class="card stat-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-hand-holding-usd text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Amount</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ formatCurrency($stats['total_amount_agreed'] ?? 0) }}</p>
                    <div class="flex flex-col items-start mt-1">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium badge-success mb-0.5">
                            <i class="fas fa-money-bill-wave mr-1" style="font-size: 0.625rem;"></i>
                            Paid: {{ formatCurrency($stats['total_amount_paid'] ?? 0) }}
                        </span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium badge-info">
                            <i class="fas fa-hourglass-half mr-1" style="font-size: 0.625rem;"></i>
                            Remaining: {{ formatCurrency(($stats['total_amount_agreed'] ?? 0) - ($stats['total_amount_paid'] ?? 0)) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Pending Actions --}}
        <div class="card stat-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-tasks text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Pending Actions</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format(($stats['pending_payments'] ?? 0) + ($stats['awaiting_signature'] ?? 0)) }}</p>
                    <div class="flex flex-col items-start mt-1">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium badge-warning mb-0.5">
                            <i class="fas fa-clock mr-1" style="font-size: 0.625rem;"></i>
                            {{ number_format($stats['pending_payments'] ?? 0) }} Pending Payments
                        </span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium badge-info">
                            <i class="fas fa-signature mr-1" style="font-size: 0.625rem;"></i>
                            {{ number_format($stats['awaiting_signature'] ?? 0) }} Awaiting Signature
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Signed Agreements --}}
        <div class="card stat-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-file-signature text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Signed Agreements</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($stats['signed_agreements'] ?? 0) }}</p>
                    <div class="flex flex-wrap gap-1 mt-1">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium badge-success">
                            <i class="fas fa-file-signature mr-1" style="font-size: 0.625rem;"></i>
                            {{ number_format($stats['signed_agreements'] ?? 0) }} Signed
                        </span>
                        @if(($stats['signed_agreements'] ?? 0) > 0)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium badge-success">
                            <i class="fas fa-check-circle mr-1" style="font-size: 0.625rem;"></i> Verified
                        </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Content Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Recent Invoices --}}
        <div class="card">
            <div class="flex justify-between items-center p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-receipt mr-2" style="color: var(--primary);"></i> Recent Invoices
                </h3>
                <div class="flex items-center space-x-2">
                    <span class="text-sm" style="color: var(--text-secondary);">
                        {{ $recentInvoices->count() }} total
                    </span>
                    <a href="{{ route('superadmin.billing.invoice-list') }}"
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                       style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-eye mr-1"></i> View All
                    </a>
                </div>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="table w-full">
                        <thead>
                            <tr>
                                <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Invoice #</th>
                                <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Amount</th>
                                <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Due Date</th>
                                <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Status</th>
                                <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentInvoices as $invoice)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="p-3 border-b" style="border-color: var(--border-color);">
                                    <div class="font-mono text-sm font-medium" style="color: var(--text-primary);">
                                        @if($invoice->is_primary_invoice ?? false)
                                        <i class="fas fa-star text-xs mr-1" style="color: var(--success);" title="Primary Billing Invoice"></i>
                                        @endif
                                        {{ $invoice->invoice_number }}
                                    </div>
                                </td>
                                <td class="p-3 border-b" style="border-color: var(--border-color);">
                                    <div class="font-medium" style="color: var(--text-primary);">
                                        {{ formatCurrency($invoice->amount, $invoice->currency ?? 'GHS') }}
                                    </div>
                                </td>
                                <td class="p-3 border-b" style="border-color: var(--border-color);">
                                    <div style="color: var(--text-secondary);">
                                        {{ $invoice->due_date instanceof \DateTime
                                            ? $invoice->due_date->format('M d, Y')
                                            : ($invoice->due_date ? date('M d, Y', strtotime($invoice->due_date)) : '—') }}
                                    </div>
                                    @if($invoice->due_date instanceof \DateTime && $invoice->due_date->isPast() && $invoice->status !== 'paid')
                                    <span class="text-xs" style="color: var(--danger);">Overdue</span>
                                    @endif
                                </td>
                                <td class="p-3 border-b" style="border-color: var(--border-color);">
                                    @php
                                        $statusClass = match($invoice->status) {
                                            'paid' => 'badge-success',
                                            'pending' => 'badge-warning',
                                            'overdue' => 'badge-danger',
                                            default => 'badge-secondary'
                                        };
                                        $statusText = match($invoice->status) {
                                            'paid' => 'Paid',
                                            'pending' => 'Pending',
                                            'overdue' => 'Overdue',
                                            default => ucfirst($invoice->status ?? 'unknown')
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $statusClass }}">
                                        <i class="fas fa-circle mr-1" style="font-size: 0.5rem;"></i>
                                        {{ $statusText }}
                                    </span>
                                    @if($invoice->invoice_type)
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">{{ ucfirst($invoice->invoice_type) }}</div>
                                    @endif
                                </td>
                                <td class="p-3 border-b" style="border-color: var(--border-color);">
                                    <div class="flex flex-wrap gap-1">
                                        {{-- View Invoice --}}
                                        <a href="{{ route('superadmin.billing.invoice-view', $invoice->id) }}"
                                           class="px-2 py-1 rounded text-xs font-medium inline-flex items-center"
                                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                            <i class="fas fa-eye text-xs mr-1"></i> View
                                        </a>

                                        @php
                                            $agreementId = $invoice->admin_billing_record_id
                                                ?? $invoice->agreement_id
                                                ?? ($invoice->agreement->id ?? null)
                                                ?? ($invoice->adminBillingRecord->id ?? null);
                                        @endphp

                                        {{-- Pay Now --}}
                                        @if($invoice->status !== 'paid' && $agreementId)
                                        <a href="{{ route('superadmin.billing.record-payment-form', $agreementId) }}"
                                           class="px-2 py-1 rounded text-xs font-medium inline-flex items-center text-white"
                                           style="background-color: var(--success); border: 1px solid var(--success);">
                                            <i class="fas fa-credit-card text-xs mr-1"></i> Pay Now
                                        </a>
                                        @endif

                                        @if($invoice->status === 'paid')
                                        <span class="px-2 py-1 rounded text-xs font-medium inline-flex items-center badge-success">
                                            <i class="fas fa-check-circle text-xs mr-1"></i> Paid
                                        </span>
                                        @endif

                                        {{-- Download --}}
                                        <a href="{{ route('superadmin.billing.invoice-download', $invoice->id) }}"
                                           class="px-2 py-1 rounded text-xs font-medium inline-flex items-center"
                                           style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                                            <i class="fas fa-download text-xs mr-1"></i> PDF
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center" style="border-color: var(--border-color);">
                                    <i class="fas fa-inbox text-3xl mb-2" style="color: var(--text-secondary);"></i>
                                    <p style="color: var(--text-secondary);">No invoices found</p>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">Invoices will appear here when generated</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Awaiting Signature --}}
        <div class="card">
            <div class="flex justify-between items-center p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-signature mr-2" style="color: var(--warning);"></i> Agreements Awaiting Signature
                </h3>
                <div class="flex items-center space-x-2">
                    <span class="text-sm" style="color: var(--text-secondary);">
                        {{ $awaitingSignature->count() }} pending
                    </span>
                    <a href="{{ route('superadmin.billing.agreements-list') }}?status=pending"
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                       style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-list-alt mr-1"></i> View All
                    </a>
                </div>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="table w-full">
                        <thead>
                            <tr>
                                <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Agreement #</th>
                                <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Developer</th>
                                <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Amount</th>
                                <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Invited At</th>
                                <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($awaitingSignature as $agreement)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="p-3 border-b" style="border-color: var(--border-color);">
                                    <div class="font-mono text-sm font-medium" style="color: var(--text-primary);">
                                        @if($agreement->is_primary_for_billing ?? false)
                                        <i class="fas fa-star text-xs mr-1" style="color: var(--success);" title="Primary Billing Agreement"></i>
                                        @endif
                                        {{ $agreement->agreement_number }}
                                    </div>
                                </td>
                                <td class="p-3 border-b" style="border-color: var(--border-color);">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                             style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                            <i class="fas fa-code text-xs"></i>
                                        </div>
                                        <div>
                                            <div style="color: var(--text-primary);">{{ $agreement->developerSetting->developer_name ?? 'Developer' }}</div>
                                            @if($agreement->developerSetting->developer_email ?? false)
                                            <div class="text-xs" style="color: var(--text-secondary);">{{ $agreement->developerSetting->developer_email }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="p-3 border-b" style="border-color: var(--border-color);">
                                    <div class="font-medium" style="color: var(--text-primary);">{{ formatCurrency($agreement->amount, $agreement->currency) }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">{{ ucfirst($agreement->billing_frequency ?? 'Monthly') }}</div>
                                </td>
                                <td class="p-3 border-b" style="border-color: var(--border-color);">
                                    <div style="color: var(--text-secondary);">
                                        @if($agreement->signing_invitation_sent_at)
                                        {{ \Carbon\Carbon::parse($agreement->signing_invitation_sent_at)->diffForHumans() }}
                                        @else
                                        Not sent yet
                                        @endif
                                    </div>
                                </td>
                                <td class="p-3 border-b" style="border-color: var(--border-color);">
                                    <a href="{{ route('superadmin.billing.view-agreement-signing', $agreement->id) }}"
                                       class="px-3 py-1 rounded text-xs font-medium inline-flex items-center text-white btn-primary">
                                        <i class="fas fa-signature text-xs mr-1"></i> Sign Now
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center" style="border-color: var(--border-color);">
                                    <i class="fas fa-check-circle text-3xl mb-2" style="color: var(--success);"></i>
                                    <p style="color: var(--text-secondary);">No agreements awaiting signature</p>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">All agreements are signed</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Pending Payments --}}
    <div class="card mb-6">
        <div class="flex justify-between items-center p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-clock mr-2" style="color: var(--warning);"></i> Pending Payment Confirmations
            </h3>
            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-warning">
                <i class="fas fa-clock mr-1"></i>
                {{ $pendingPayments->count() }} pending
            </span>
        </div>
        <div class="p-6">
            @if($pendingPayments->count() > 0)
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Payment Date</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Agreement #</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Amount</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Method</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Reference</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Recorded At</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pendingPayments as $payment)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <div style="color: var(--text-secondary);">{{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}</div>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <div class="font-mono text-sm font-medium" style="color: var(--text-primary);">{{ $payment->agreement_number }}</div>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <div class="font-medium" style="color: var(--text-primary);">{{ formatCurrency($payment->amount_paid, 'GHS') }}</div>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-info">
                                    <i class="{{ getPaymentMethodIcon($payment->payment_method) }} mr-1"></i>
                                    {{ getPaymentMethodName($payment->payment_method) }}
                                </span>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <code class="text-xs" style="color: var(--text-secondary);">{{ $payment->transaction_reference ?? 'N/A' }}</code>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <div style="color: var(--text-secondary);">{{ \Carbon\Carbon::parse($payment->created_at)->format('M d, Y g:i A') }}</div>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <a href="{{ route('superadmin.billing.payment-details', $payment->id) }}"
                                   class="px-2 py-1 rounded text-xs font-medium inline-flex items-center"
                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                    <i class="fas fa-eye text-xs mr-1"></i> View
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-8">
                <i class="fas fa-check-circle text-4xl mb-4" style="color: var(--success);"></i>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No pending payments</h4>
                <p class="text-sm" style="color: var(--text-secondary);">All payments have been confirmed by the developer</p>
            </div>
            @endif
        </div>
    </div>

    {{-- Your Agreements Summary --}}
    <div class="card mb-6">
        <div class="flex justify-between items-center p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-file-contract mr-2" style="color: var(--primary);"></i> Your Agreements
            </h3>
            <a href="{{ route('superadmin.billing.agreements-list') }}"
               class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
               style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                <i class="fas fa-arrow-right mr-1"></i> View All ({{ $agreements->count() }})
            </a>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Agreement #</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Description</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Amount</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Status</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Payment Status</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Signed Date</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($agreements->take(5) as $agreement)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <div class="font-mono text-sm font-medium flex items-center" style="color: var(--text-primary);">
                                    @if($agreement->is_primary_for_billing ?? false)
                                    <i class="fas fa-star text-xs mr-1" style="color: var(--success);" title="Primary Billing Agreement"></i>
                                    @endif
                                    {{ $agreement->agreement_number }}
                                </div>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <div class="text-sm" style="color: var(--text-secondary);">{{ Str::limit($agreement->description ?? 'No description', 50) }}</div>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <div class="font-medium" style="color: var(--text-primary);">{{ formatCurrency($agreement->amount, $agreement->currency) }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">{{ ucfirst($agreement->billing_frequency ?? 'Monthly') }}</div>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                @php
                                    $statusClass = match($agreement->status) {
                                        'active' => 'badge-success',
                                        'pending' => 'badge-warning',
                                        'completed' => 'badge-info',
                                        'terminated' => 'badge-danger',
                                        'rejected' => 'badge-danger',
                                        default => 'badge-secondary'
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $statusClass }}">
                                    <i class="fas fa-circle mr-1" style="font-size: 0.5rem;"></i>
                                    {{ ucfirst($agreement->status) }}
                                </span>
                                @if($agreement->status === 'rejected' && $agreement->rejection_reason)
                                <div class="text-xs mt-1" style="color: var(--danger);" title="{{ $agreement->rejection_reason }}">
                                    <i class="fas fa-comment mr-1"></i> Rejected
                                </div>
                                @endif
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                @php
                                    $paidPercent = $agreement->amount > 0 ? ($agreement->amount_received / $agreement->amount) * 100 : 0;
                                    $paymentClass = $paidPercent >= 100 ? 'badge-success' : ($paidPercent > 0 ? 'badge-warning' : 'badge-secondary');
                                    $paymentText  = $paidPercent >= 100 ? 'Paid' : ($paidPercent > 0 ? 'Partial' : 'Unpaid');
                                @endphp
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $paymentClass }}">
                                    <i class="fas fa-circle mr-1" style="font-size: 0.5rem;"></i>
                                    {{ $paymentText }}
                                </span>
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                    {{ formatCurrency($agreement->amount_received) }} / {{ formatCurrency($agreement->amount) }}
                                </div>
                                @if($agreement->payment_status && $agreement->payment_status !== 'unpaid')
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    Status: {{ ucfirst(str_replace('_', ' ', $agreement->payment_status)) }}
                                </div>
                                @endif
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                @if($agreement->signing_completed_at)
                                <div style="color: var(--text-secondary);">{{ \Carbon\Carbon::parse($agreement->signing_completed_at)->format('M d, Y') }}</div>
                                @elseif($agreement->agreed_at)
                                <div style="color: var(--text-secondary);">{{ \Carbon\Carbon::parse($agreement->agreed_at)->format('M d, Y') }}</div>
                                @else
                                <div style="color: var(--text-secondary);">Not signed</div>
                                @endif
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <div class="flex flex-wrap gap-1">
                                    <a href="{{ route('superadmin.billing.view-agreement', $agreement->id) }}"
                                       class="px-2 py-1 rounded text-xs font-medium inline-flex items-center"
                                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                        <i class="fas fa-eye text-xs mr-1"></i> View
                                    </a>
                                    @if($agreement->status === 'active' && $agreement->signed_agreement_pdf_path)
                                    <a href="{{ route('superadmin.billing.download-signed-agreement', $agreement->id) }}"
                                       class="px-2 py-1 rounded text-xs font-medium inline-flex items-center"
                                       style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                        <i class="fas fa-download text-xs mr-1"></i> PDF
                                    </a>
                                    @endif
                                    @if($agreement->status === 'active' && $agreement->amount_received < $agreement->amount)
                                    <a href="{{ route('superadmin.billing.record-payment-form', $agreement->id) }}"
                                       class="px-2 py-1 rounded text-xs font-medium inline-flex items-center text-white"
                                       style="background-color: var(--success); border: 1px solid var(--success);">
                                        <i class="fas fa-credit-card text-xs mr-1"></i> Pay
                                    </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center" style="border-color: var(--border-color);">
                                <i class="fas fa-file-contract text-3xl mb-2" style="color: var(--text-secondary);"></i>
                                <p style="color: var(--text-secondary);">No agreements found</p>
                                @if(!$developerSettings)
                                <p class="text-sm mt-1" style="color: var(--warning);">System billing not configured yet</p>
                                @endif
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Payment Methods & Online Providers --}}
    @if(count($paymentMethods) > 0 || count($onlineProviders) > 0)
    <div class="card mb-6">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-credit-card mr-2" style="color: var(--info);"></i> Accepted Payment Methods
            </h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                @foreach($paymentMethods as $key => $method)
                <div class="flex items-center p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                    <i class="{{ getPaymentMethodIcon($key) }} mr-2" style="color: var(--info);"></i>
                    <span class="text-sm" style="color: var(--text-primary);">{{ $method['name'] ?? getPaymentMethodName($key) }}</span>
                </div>
                @endforeach

                @foreach($onlineProviders as $key => $provider)
                    @if(($provider['available'] ?? false) && ($provider['enabled'] ?? false))
                    <div class="flex items-center p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
                        <i class="{{ $provider['icon'] ?? 'fas fa-credit-card' }} mr-2" style="color: {{ $provider['color'] ?? 'var(--success)' }};"></i>
                        <span class="text-sm" style="color: var(--text-primary);">{{ $provider['name'] ?? ucfirst($key) }}</span>
                    </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Quick Actions --}}
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-bolt mr-2" style="color: var(--primary);"></i> Quick Actions
            </h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="{{ route('superadmin.billing.agreements-list') }}"
                   class="card h-full border-primary hover:border-primary-dark transition-all duration-200 hover:shadow-lg">
                    <div class="card-body flex flex-col items-center text-center p-6">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center mb-4"
                             style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 2px solid rgba(var(--primary-rgb), 0.3);">
                            <i class="fas fa-file-contract text-xl"></i>
                        </div>
                        <h5 class="font-semibold mb-2" style="color: var(--text-primary);">View Agreements</h5>
                        <p class="text-sm mb-0" style="color: var(--text-secondary);">View all your billing agreements</p>
                    </div>
                </a>

                <a href="{{ route('superadmin.billing.reports') }}"
                   class="card h-full border-success hover:border-success-dark transition-all duration-200 hover:shadow-lg">
                    <div class="card-body flex flex-col items-center text-center p-6">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center mb-4"
                             style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 2px solid rgba(var(--success-rgb), 0.3);">
                            <i class="fas fa-chart-bar text-xl"></i>
                        </div>
                        <h5 class="font-semibold mb-2" style="color: var(--text-primary);">View Reports</h5>
                        <p class="text-sm mb-0" style="color: var(--text-secondary);">Detailed billing reports with analytics</p>
                    </div>
                </a>

                <a href="{{ route('superadmin.billing.history') }}"
                   class="card h-full border-info hover:border-info-dark transition-all duration-200 hover:shadow-lg">
                    <div class="card-body flex flex-col items-center text-center p-6">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center mb-4"
                             style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 2px solid rgba(var(--info-rgb), 0.3);">
                            <i class="fas fa-history text-xl"></i>
                        </div>
                        <h5 class="font-semibold mb-2" style="color: var(--text-primary);">Payment History</h5>
                        <p class="text-sm mb-0" style="color: var(--text-secondary);">View all payment history and transactions</p>
                    </div>
                </a>

                <a href="{{ route('superadmin.billing.export') }}?export_type=agreements"
                   class="card h-full border-warning hover:border-warning-dark transition-all duration-200 hover:shadow-lg">
                    <div class="card-body flex flex-col items-center text-center p-6">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center mb-4"
                             style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 2px solid rgba(var(--warning-rgb), 0.3);">
                            <i class="fas fa-download text-xl"></i>
                        </div>
                        <h5 class="font-semibold mb-2" style="color: var(--text-primary);">Export Data</h5>
                        <p class="text-sm mb-0" style="color: var(--text-secondary);">Export billing data (CSV format)</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Toast Container --}}
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@section('scripts')
<script>
// Payment methods configuration from controller
const paymentMethods = @json($paymentMethods ?? []);

// Toast notification function
function showToast(message, type = 'info') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(toastContainer);
    }

    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full ${
        type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
    }`;

    const messageEl = document.createElement('span');
    messageEl.className = 'text-sm font-medium flex-1';
    messageEl.textContent = message;

    const closeBtn = document.createElement('button');
    closeBtn.className = 'ml-4 transition-colors duration-200 hover:opacity-70';
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.onclick = () => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    };

    toast.appendChild(messageEl);
    toast.appendChild(closeBtn);
    toastContainer.appendChild(toast);

    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 10);

    setTimeout(() => {
        if (toast.parentNode === toastContainer) {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

// Counter animation for stats
document.addEventListener('DOMContentLoaded', function() {
    const counters = document.querySelectorAll('.text-2xl.font-bold');
    counters.forEach(counter => {
        const text = counter.textContent;
        const isCurrency = text.includes('GH₵');
        const isNumber = /^[\d,]+$/.test(text.replace('GH₵', '').replace(/,/g, '').trim());

        if (isNumber || isCurrency) {
            let target;
            if (isCurrency) {
                target = parseFloat(text.replace('GH₵', '').replace(/,/g, '').trim());
            } else {
                target = parseInt(text.replace(/,/g, '').trim());
            }

            if (isNaN(target)) target = 0;

            const duration = 1500;
            const increment = target / (duration / 16);
            let current = 0;

            const updateCounter = () => {
                current += increment;
                if (current >= target) {
                    counter.textContent = isCurrency
                        ? 'GH₵' + target.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})
                        : target.toLocaleString();
                } else {
                    counter.textContent = isCurrency
                        ? 'GH₵' + Math.floor(current).toLocaleString()
                        : Math.floor(current).toLocaleString();
                    requestAnimationFrame(updateCounter);
                }
            };

            if (target > 0) {
                requestAnimationFrame(updateCounter);
            }
        }
    });

    // Close alert buttons
    document.querySelectorAll('.card .flex.items-center.p-4 button').forEach(btn => {
        btn.addEventListener('click', function() {
            const parentCard = this.closest('.card');
            if (parentCard) parentCard.remove();
        });
    });

    initializeTooltips();
    startAutoRefresh();

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoRefresh();
        } else {
            startAutoRefresh();
        }
    });
});

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal:not(.hidden)').forEach(modal => {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        });
    }
});

// Initialize tooltips
function initializeTooltips() {
    document.querySelectorAll('[title]').forEach(tooltip => {
        tooltip.addEventListener('mouseenter', function() {
            const title = this.getAttribute('title');
            if (title) {
                const tooltipEl = document.createElement('div');
                tooltipEl.className = 'fixed bg-gray-800 text-white text-xs rounded px-2 py-1 z-50 pointer-events-none';
                tooltipEl.textContent = title;
                document.body.appendChild(tooltipEl);

                const rect = this.getBoundingClientRect();
                tooltipEl.style.left = Math.max(5, rect.left + rect.width / 2 - tooltipEl.offsetWidth / 2) + 'px';
                tooltipEl.style.top = (rect.top - tooltipEl.offsetHeight - 5) + 'px';

                this.setAttribute('data-tooltip-id', Date.now().toString());
                tooltipEl.id = this.getAttribute('data-tooltip-id');
            }
        });

        tooltip.addEventListener('mouseleave', function() {
            const tooltipId = this.getAttribute('data-tooltip-id');
            if (tooltipId) {
                const tooltipEl = document.getElementById(tooltipId);
                if (tooltipEl) tooltipEl.remove();
            }
        });
    });
}

// Auto-refresh every 5 minutes
let refreshInterval = null;

function startAutoRefresh() {
    if (refreshInterval) clearInterval(refreshInterval);
    refreshInterval = setInterval(() => {
        if (!document.hidden) {
            fetch(window.location.href, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => {
                if (response.ok) location.reload();
            })
            .catch(err => console.log('Auto-refresh failed:', err));
        }
    }, 300000);
}

function stopAutoRefresh() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
}

window.addEventListener('beforeunload', function() {
    stopAutoRefresh();
});
</script>

<style>
.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
}

.stat-card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    padding: 1.5rem !important;
    transition: transform 0.2s ease, box-shadow 0.2s ease !important;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
}

.badge-success {
    background-color: rgba(var(--success-rgb), 0.1) !important;
    color: var(--success) !important;
    border: 1px solid rgba(var(--success-rgb), 0.3) !important;
}

.badge-warning {
    background-color: rgba(var(--warning-rgb), 0.1) !important;
    color: var(--warning) !important;
    border: 1px solid rgba(var(--warning-rgb), 0.3) !important;
}

.badge-danger {
    background-color: rgba(var(--danger-rgb), 0.1) !important;
    color: var(--danger) !important;
    border: 1px solid rgba(var(--danger-rgb), 0.3) !important;
}

.badge-info {
    background-color: rgba(var(--info-rgb), 0.1) !important;
    color: var(--info) !important;
    border: 1px solid rgba(var(--info-rgb), 0.3) !important;
}

.badge-primary {
    background-color: rgba(var(--primary-rgb), 0.1) !important;
    color: var(--primary) !important;
    border: 1px solid rgba(var(--primary-rgb), 0.3) !important;
}

.badge-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
}

.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: 1px solid var(--primary) !important;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
    transform: translateY(-1px);
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table tr:hover {
    background-color: rgba(var(--primary-rgb), 0.02) !important;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.fa-spinner.fa-spin {
    animation: spin 1s linear infinite;
}

.form-input, .form-select {
    background-color: var(--card-bg) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 8px !important;
    transition: all 0.2s ease !important;
}

.form-input:focus, .form-select:focus {
    outline: none !important;
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1) !important;
}

@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-2.lg\:grid-cols-4 { grid-template-columns: 1fr !important; }
    .grid.grid-cols-1.lg\:grid-cols-2 { grid-template-columns: 1fr !important; }
    .table { font-size: 0.75rem; }
    .table th, .table td { padding: 0.5rem !important; }
    .card .p-6 { padding: 1rem !important; }
    .text-2xl { font-size: 1.25rem !important; }
}

@media (max-width: 640px) {
    .card { margin: 0.5rem; }
    .p-6 { padding: 1rem !important; }
    .text-2xl { font-size: 1.5rem !important; }
    .grid { gap: 1rem !important; }
}

.card.h-full {
    transition: all 0.3s ease;
    cursor: pointer;
    text-decoration: none !important;
}

.card.h-full:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1) !important;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

.card > .flex.items-center.p-4 { animation: slideIn 0.3s ease-out; }

::-webkit-scrollbar { width: 8px; height: 8px; }
::-webkit-scrollbar-track { background: rgba(var(--primary-rgb), 0.05); border-radius: 4px; }
::-webkit-scrollbar-thumb { background: rgba(var(--primary-rgb), 0.2); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: rgba(var(--primary-rgb), 0.3); }

.loading-spinner {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: white;
    animation: spin 0.6s linear infinite;
}

@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

.progress-bar-animate {
    background: linear-gradient(90deg, var(--primary) 25%, var(--secondary) 50%, var(--primary) 75%);
    background-size: 200% 100%;
    animation: shimmer 2s infinite;
}

@media print {
    .no-print { display: none !important; }
    .card { break-inside: avoid; page-break-inside: avoid; }
    body { background-color: white !important; }
}
</style>
@endsection