{{-- resources/views/superadmin/billing/payment-history.blade.php --}}
@extends('layouts.app')

@php
    $pageTitle = 'Payment History';

    if (!function_exists('formatCurrency')) {
        function formatCurrency($amount, $currency = 'GHS') {
            if (empty($amount)) return 'GH₵0.00';
            if ($currency === 'GHS') {
                return 'GH₵' . number_format((float)$amount, 2);
            }
            return $currency . ' ' . number_format((float)$amount, 2);
        }
    }

    if (!function_exists('getPaymentStatusColor')) {
        function getPaymentStatusColor($status) {
            $colors = [
                'confirmed'            => 'success',
                'pending_confirmation' => 'warning',
                'cancelled'            => 'danger',
                'refunded'             => 'info',
            ];
            return $colors[$status] ?? 'secondary';
        }
    }

    if (!function_exists('getPaymentMethodDisplay')) {
        function getPaymentMethodDisplay($method) {
            $methods = [
                'mtn'           => 'MTN Mobile Money',
                'telecel'       => 'Telecel (Vodafone) Cash',
                'airteltigo'    => 'AirtelTigo Money',
                'bank_transfer' => 'Bank Transfer',
                'paystack'      => 'Paystack',
                'expresspay'    => 'ExpressPay',
                'flutterwave'   => 'Flutterwave',
                'hubtel'        => 'Hubtel',
                'cash'          => 'Cash',
                'mobile_money'  => 'Mobile Money',
            ];
            return $methods[$method] ?? ucfirst(str_replace('_', ' ', (string) $method));
        }
    }

    if (!function_exists('getPaymentMethodIcon')) {
        function getPaymentMethodIcon($method) {
            return match ($method) {
                'bank_transfer'                        => 'fas fa-university',
                'mtn', 'telecel', 'airteltigo',
                'mobile_money'                         => 'fas fa-mobile-alt',
                'cash'                                 => 'fas fa-money-bill',
                'paystack', 'expresspay',
                'flutterwave', 'hubtel'                => 'fas fa-credit-card',
                default                                => 'fas fa-money-bill-wave',
            };
        }
    }

    $isDarkMode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] === 'true' : false;

    // ---------- Defensive defaults ----------
    $payments = $payments ?? new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
    $stats    = $stats    ?? ['total_payments' => 0, 'confirmed_payments' => 0, 'pending_payments' => 0, 'total_amount_paid' => 0, 'pending_amount' => 0];

    // ---------- Totals (server-provided stats, not page-scoped) ----------
    $totalConfirmed = (float) ($stats['total_amount_paid']  ?? 0);
    $totalPending   = (float) ($stats['pending_amount']     ?? 0);
    $totalAmount    = $totalConfirmed + $totalPending;
@endphp

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- Header --}}
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-3">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-history text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-history mr-2" style="color: var(--primary);"></i>
                        Payment History
                    </h2>
                    <div class="text-sm flex items-center mt-1 flex-wrap gap-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle"></i>
                        <span>View all your payment transactions</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-circle" style="color: var(--success); font-size: 0.5rem;"></i>
                        <span class="font-medium">{{ $payments->total() }} total payments</span>
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                <div class="flex items-center space-x-2 mt-2 flex-wrap gap-2">
                    <a href="{{ route('superadmin.billing.dashboard') }}"
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                       style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-arrow-left mr-1"></i> Dashboard
                    </a>
                    <button type="button" onclick="exportPayments()"
                            class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                            style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <i class="fas fa-download mr-1"></i> Export
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Navigation --}}
    <div class="card p-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center space-x-3 flex-wrap gap-2">
                <a href="{{ route('superadmin.dashboard') }}"
                   class="inline-flex items-center text-sm font-medium"
                   style="color: var(--primary);">
                    <i class="fas fa-arrow-left mr-2"></i> Main Dashboard
                </a>

                <a href="{{ route('superadmin.billing.dashboard') }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-chart-line mr-1"></i> Billing Dashboard
                </a>

                <a href="{{ route('superadmin.billing.agreements-list') }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-list-alt mr-1"></i> All Agreements
                </a>

                <a href="{{ route('superadmin.billing.reports') }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-chart-bar mr-1"></i> Reports
                </a>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="card stat-card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                     style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                    <i class="fas fa-money-check-alt text-lg"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Payments</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ formatCurrency($totalAmount) }}</p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        {{ $stats['total_payments'] ?? 0 }} record(s)
                    </p>
                </div>
            </div>
        </div>

        <div class="card stat-card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                     style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                    <i class="fas fa-check-circle text-lg"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Confirmed</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ formatCurrency($totalConfirmed) }}</p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        {{ $stats['confirmed_payments'] ?? 0 }} confirmed
                    </p>
                </div>
            </div>
        </div>

        <div class="card stat-card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                     style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                    <i class="fas fa-clock text-lg"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Pending</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ formatCurrency($totalPending) }}</p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        {{ $stats['pending_payments'] ?? 0 }} pending
                    </p>
                </div>
            </div>
        </div>

        <div class="card stat-card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                     style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                    <i class="fas fa-percentage text-lg"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Confirmation Rate</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        @php
                            $totalRecords = (int) ($stats['total_payments'] ?? 0);
                            $confirmedRecords = (int) ($stats['confirmed_payments'] ?? 0);
                            $confirmationRate = $totalRecords > 0
                                ? round(($confirmedRecords / $totalRecords) * 100, 1)
                                : 0;
                        @endphp
                        {{ $confirmationRate }}%
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
            <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filter Payments
        </h3>

        <form method="GET" action="{{ route('superadmin.billing.history') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Status</label>
                    <select name="status"
                            class="form-select w-full p-2.5 rounded-lg border"
                            style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                        <option value="">All Statuses</option>
                        <option value="confirmed"            {{ request('status') === 'confirmed'            ? 'selected' : '' }}>Confirmed</option>
                        <option value="pending_confirmation" {{ request('status') === 'pending_confirmation' ? 'selected' : '' }}>Pending Confirmation</option>
                        <option value="cancelled"            {{ request('status') === 'cancelled'            ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">From Date</label>
                    <input type="date" name="start_date" value="{{ request('start_date') }}"
                           class="form-input w-full p-2.5 rounded-lg border"
                           style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">To Date</label>
                    <input type="date" name="end_date" value="{{ request('end_date') }}"
                           class="form-input w-full p-2.5 rounded-lg border"
                           style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                </div>
            </div>

            <div class="flex justify-between items-center pt-4 flex-wrap gap-3"
                 style="border-top: 1px solid var(--border-color);">
                <a href="{{ route('superadmin.billing.history') }}"
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-redo mr-2"></i> Reset Filters
                </a>
                <button type="submit"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div class="card p-6">
        <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-table mr-2" style="color: var(--primary);"></i> Payment Records
            </h3>
            <div class="text-sm" style="color: var(--text-secondary);">
                @if($payments->count() > 0)
                    Showing {{ $payments->firstItem() }} - {{ $payments->lastItem() }} of {{ $payments->total() }}
                @else
                    No records
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table payment-table w-full">
                <thead>
                    <tr>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Date</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Billing Month</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Agreement</th>
                        <th class="text-right p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Amount</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Method</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Reference</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Status</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Recorded</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                    @php
                        $billingMonth     = \Carbon\Carbon::parse($payment->payment_date)->format('F Y');
                        $isPrimaryInvoice = (bool) ($payment->is_primary_for_billing ?? false);
                        $statusClass      = getPaymentStatusColor($payment->status ?? 'pending_confirmation');
                        $statusText       = match ($payment->status ?? '') {
                            'confirmed'            => 'Confirmed',
                            'pending_confirmation' => 'Pending',
                            'cancelled'            => 'Cancelled',
                            'refunded'             => 'Refunded',
                            default                => ucfirst(str_replace('_', ' ', (string) ($payment->status ?? 'unknown'))),
                        };
                    @endphp
                    <tr>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <div style="color: var(--text-secondary);">{{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}</div>
                            <div class="text-xs" style="color: var(--text-secondary);">
                                {{ \Carbon\Carbon::parse($payment->payment_date)->format('h:i A') }}
                            </div>
                        </td>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-primary">
                                <i class="fas fa-calendar-alt mr-1"></i>
                                {{ $billingMonth }}
                            </span>
                        </td>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <div class="font-mono text-sm font-medium flex items-center" style="color: var(--text-primary);">
                                @if($isPrimaryInvoice)
                                    <i class="fas fa-star text-xs mr-1" style="color: var(--success);" title="Primary Billing Agreement"></i>
                                @endif
                                {{ $payment->agreement_number ?? 'N/A' }}
                            </div>
                            @if(!empty($payment->description))
                            <div class="text-xs truncate max-w-xs" style="color: var(--text-secondary);">
                                {{ \Illuminate\Support\Str::limit($payment->description, 40) }}
                            </div>
                            @endif
                        </td>
                        <td class="p-3 border-b text-right" style="border-color: var(--border-color);">
                            <div class="font-medium" style="color: var(--text-primary);">
                                {{ formatCurrency($payment->amount_paid ?? 0, 'GHS') }}
                            </div>
                        </td>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-info">
                                <i class="{{ getPaymentMethodIcon($payment->payment_method ?? '') }} mr-1"></i>
                                {{ getPaymentMethodDisplay($payment->payment_method ?? '') }}
                            </span>
                        </td>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <code class="text-xs" style="color: var(--text-secondary);">
                                {{ !empty($payment->transaction_reference) ? \Illuminate\Support\Str::limit($payment->transaction_reference, 15) : 'N/A' }}
                            </code>
                        </td>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-{{ $statusClass }}">
                                <i class="fas fa-circle mr-1" style="font-size: 0.5rem;"></i>
                                {{ $statusText }}
                            </span>
                            @if(!empty($payment->confirmed_at))
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                {{ \Carbon\Carbon::parse($payment->confirmed_at)->format('M d, Y') }}
                            </div>
                            @endif
                        </td>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            @if(!empty($payment->recorded_at))
                                <div style="color: var(--text-primary);">{{ \Carbon\Carbon::parse($payment->recorded_at)->format('M d, Y') }}</div>
                            @else
                                <span class="text-xs" style="color: var(--text-secondary);">—</span>
                            @endif
                        </td>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <div class="flex flex-wrap gap-1">
                                <a href="{{ route('superadmin.billing.payment-details', $payment->id) }}"
                                   class="px-2 py-1 rounded text-xs font-medium inline-flex items-center"
                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);"
                                   title="View Details">
                                    <i class="fas fa-eye text-xs"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="p-8 text-center" style="border-color: var(--border-color);">
                            <i class="fas fa-inbox text-3xl mb-2" style="color: var(--text-secondary);"></i>
                            <p style="color: var(--text-secondary);">No payments found</p>
                            @if(request()->hasAny(['status', 'start_date', 'end_date']))
                                <p class="text-sm mt-1" style="color: var(--text-secondary);">Try adjusting your filters</p>
                                <a href="{{ route('superadmin.billing.history') }}"
                                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center mt-2"
                                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                                    <i class="fas fa-redo mr-1"></i> Clear Filters
                                </a>
                            @else
                                <p class="text-sm mt-1" style="color: var(--text-secondary);">You haven't made any payments yet</p>
                                <a href="{{ route('superadmin.billing.agreements-list') }}"
                                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center mt-2"
                                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                                    <i class="fas fa-list-alt mr-1"></i> View Agreements
                                </a>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($payments->hasPages())
        <div class="mt-6 pt-6 flex flex-col md:flex-row items-center justify-between gap-4"
             style="border-top: 1px solid var(--border-color);">
            <div class="text-sm" style="color: var(--text-secondary);">
                Showing {{ $payments->firstItem() }} - {{ $payments->lastItem() }} of {{ $payments->total() }}
            </div>

            <div class="flex items-center space-x-2 flex-wrap gap-2">
                @if($payments->onFirstPage())
                    <span class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center opacity-50 cursor-not-allowed"
                          style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-chevron-left mr-1"></i> Previous
                    </span>
                @else
                    <a href="{{ $payments->previousPageUrl() }}"
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-chevron-left mr-1"></i> Previous
                    </a>
                @endif

                <div class="hidden md:flex items-center space-x-1">
                    @foreach($payments->getUrlRange(1, $payments->lastPage()) as $page => $url)
                        @if($page == $payments->currentPage())
                            <span class="w-8 h-8 flex items-center justify-center text-sm font-medium btn-primary text-white rounded-full">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}"
                               class="w-8 h-8 flex items-center justify-center border rounded-full text-sm font-medium"
                               style="color: var(--text-secondary); border-color: var(--border-color);">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                </div>

                <div class="md:hidden text-sm font-medium" style="color: var(--text-primary);">
                    Page {{ $payments->currentPage() }} of {{ $payments->lastPage() }}
                </div>

                @if($payments->hasMorePages())
                    <a href="{{ $payments->nextPageUrl() }}"
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        Next <i class="fas fa-chevron-right ml-1"></i>
                    </a>
                @else
                    <span class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center opacity-50 cursor-not-allowed"
                          style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        Next <i class="fas fa-chevron-right ml-1"></i>
                    </span>
                @endif

                <select onchange="updatePerPage(this.value)"
                        class="form-select p-2 text-sm rounded-lg border"
                        style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                    @foreach([10, 20, 50, 100] as $n)
                        <option value="{{ $n }}" {{ request('per_page', 20) == $n ? 'selected' : '' }}>{{ $n }} per page</option>
                    @endforeach
                </select>
            </div>
        </div>
        @endif
    </div>

    {{-- Payment Analysis --}}
    <div class="card p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-chart-pie mr-2" style="color: var(--primary);"></i> Payment Analysis
            </h3>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Payment Method Breakdown --}}
            <div>
                <h4 class="font-medium mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-credit-card mr-2" style="color: var(--info);"></i> By Payment Method
                </h4>

                @php
                    $methods = $payments->getCollection()->groupBy('payment_method')->map(function ($group) {
                        return [
                            'count'  => $group->count(),
                            'amount' => $group->sum('amount_paid'),
                        ];
                    });
                    $totalByMethod = $methods->sum('amount');
                @endphp

                @if($methods->count() > 0)
                    <div class="space-y-3">
                        @foreach($methods as $method => $data)
                        @php $percentage = $totalByMethod > 0 ? ($data['amount'] / $totalByMethod) * 100 : 0; @endphp
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span style="color: var(--text-primary);">{{ getPaymentMethodDisplay($method) }}</span>
                                <span style="color: var(--text-secondary);">
                                    {{ formatCurrency($data['amount']) }} ({{ round($percentage, 1) }}%)
                                </span>
                            </div>
                            <div class="h-2 rounded-full overflow-hidden" style="background-color: rgba(var(--primary-rgb), 0.1);">
                                <div class="h-full rounded-full"
                                     style="width: {{ $percentage }}%; background-color: {{ $loop->index % 2 === 0 ? 'var(--primary)' : 'var(--info)' }};"></div>
                            </div>
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                {{ $data['count'] }} payment{{ $data['count'] !== 1 ? 's' : '' }}
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-4">
                        <i class="fas fa-chart-pie text-2xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                        <p style="color: var(--text-secondary);">No payment data available</p>
                    </div>
                @endif
            </div>

            {{-- Monthly Summary --}}
            <div>
                <h4 class="font-medium mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-calendar-alt mr-2" style="color: var(--success);"></i> Monthly Summary
                </h4>

                @php
                    $months = collect();
                    for ($i = 5; $i >= 0; $i--) {
                        $month = now()->subMonths($i);
                        $months->push([
                            'month'     => $month->format('F Y'),
                            'month_key' => $month->format('Y-m'),
                            'amount'    => 0,
                            'count'     => 0,
                        ]);
                    }

                    foreach ($payments->getCollection()->where('status', 'confirmed') as $payment) {
                        $paymentMonth = \Carbon\Carbon::parse($payment->payment_date)->format('Y-m');
                        $foundMonth   = $months->firstWhere('month_key', $paymentMonth);
                        if ($foundMonth) {
                            $foundMonth['amount'] += (float) $payment->amount_paid;
                            $foundMonth['count']++;
                        }
                    }

                    $maxAmount = $months->max('amount');
                @endphp

                <div class="space-y-4">
                    @foreach($months as $monthData)
                    @php $percentage = $maxAmount > 0 ? ($monthData['amount'] / $maxAmount) * 100 : 0; @endphp
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span style="color: var(--text-primary);">{{ $monthData['month'] }}</span>
                            <span style="color: var(--text-secondary);">
                                {{ formatCurrency($monthData['amount']) }}
                                @if($monthData['count'] > 0)
                                    <span class="text-xs">({{ $monthData['count'] }} payment{{ $monthData['count'] !== 1 ? 's' : '' }})</span>
                                @endif
                            </span>
                        </div>
                        <div class="h-3 rounded-full overflow-hidden" style="background-color: rgba(var(--primary-rgb), 0.1);">
                            <div class="h-full rounded-full"
                                 style="width: {{ $percentage }}%; background-color: var(--success);"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Toast Container --}}
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@section('scripts')
<script>
function updatePerPage(perPage) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', perPage);
    window.location.href = url.toString();
}

function exportPayments() {
    const params = new URLSearchParams(window.location.search);
    params.set('export_type', 'payments');

    showToast('Preparing export...', 'info');

    const url = new URL('{{ route("superadmin.billing.export") }}', window.location.origin);
    url.searchParams.set('export_type', 'payments');
    if (params.get('start_date')) url.searchParams.set('start_date', params.get('start_date'));
    if (params.get('end_date'))   url.searchParams.set('end_date', params.get('end_date'));

    setTimeout(() => {
        window.location.href = url.toString();
    }, 500);
}

function showToast(message, type = 'info') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(toastContainer);
    }

    const colors = {
        success: { bg: 'bg-green-100',  text: 'text-green-800',  icon: 'fa-check-circle' },
        error:   { bg: 'bg-red-100',    text: 'text-red-800',    icon: 'fa-exclamation-circle' },
        warning: { bg: 'bg-yellow-100', text: 'text-yellow-800', icon: 'fa-exclamation-triangle' },
        info:    { bg: 'bg-blue-100',   text: 'text-blue-800',   icon: 'fa-info-circle' },
    };
    const cfg = colors[type] || colors.info;

    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full ${cfg.bg} ${cfg.text}`;

    const messageEl = document.createElement('span');
    messageEl.className = 'text-sm font-medium flex-1';
    messageEl.innerHTML = `<i class="fas ${cfg.icon} mr-2"></i>${message}`;

    const closeBtn = document.createElement('button');
    closeBtn.className = 'ml-4 hover:opacity-70';
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

document.addEventListener('DOMContentLoaded', function() {
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
</script>

<style>
.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08) !important;
}

.stat-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
}

/* Table hover — primary tint, no white flash */
.payment-table tbody tr {
    background-color: transparent !important;
    transition: background-color 0.15s ease !important;
}

.payment-table tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.04) !important;
}

html.dark .payment-table tbody tr:hover,
body.dark .payment-table tbody tr:hover,
body[data-theme="dark"] .payment-table tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.08) !important;
}

.payment-table tbody tr:hover > td {
    border-color: var(--border-color) !important;
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

.badge-success  { background-color: rgba(var(--success-rgb), 0.1) !important; color: var(--success) !important; border: 1px solid rgba(var(--success-rgb), 0.3) !important; }
.badge-warning  { background-color: rgba(var(--warning-rgb), 0.1) !important; color: var(--warning) !important; border: 1px solid rgba(var(--warning-rgb), 0.3) !important; }
.badge-danger   { background-color: rgba(var(--danger-rgb),  0.1) !important; color: var(--danger)  !important; border: 1px solid rgba(var(--danger-rgb),  0.3) !important; }
.badge-info     { background-color: rgba(var(--info-rgb),    0.1) !important; color: var(--info)    !important; border: 1px solid rgba(var(--info-rgb),    0.3) !important; }
.badge-primary  { background-color: rgba(var(--primary-rgb), 0.1) !important; color: var(--primary) !important; border: 1px solid rgba(var(--primary-rgb), 0.3) !important; }
.badge-secondary{ background-color: rgba(var(--secondary-rgb), 0.1) !important; color: var(--secondary) !important; border: 1px solid rgba(var(--secondary-rgb), 0.3) !important; }

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

.table {
    width: 100%;
    border-collapse: collapse;
}

code {
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    background-color: rgba(var(--primary-rgb), 0.05);
    padding: 2px 4px;
    border-radius: 3px;
    font-size: 0.875em;
}

@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-4 { grid-template-columns: 1fr; }
    .grid.grid-cols-1.md\:grid-cols-3 { grid-template-columns: 1fr; }
    .grid.grid-cols-1.md\:grid-cols-2 { grid-template-columns: 1fr; }
    .table { font-size: 0.75rem; }
    .table th, .table td { padding: 0.5rem !important; }
    .card .p-6 { padding: 1rem !important; }
    .text-2xl { font-size: 1.25rem !important; }
}
</style>
@endsection