@extends('layouts.dev')

@section('title', 'Billing Invoices')

@section('content')
<div class="container-fluid p-6">

    {{-- Header --}}
    <div class="card p-6 mb-6">
        <div class="flex justify-between items-center flex-wrap gap-3">
            <h1 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-file-invoice mr-2" style="color: var(--primary);"></i>
                Billing Invoices
            </h1>
            <div class="flex gap-2">
                <a href="{{ route('developer.billing.invoices', ['status' => 'pending']) }}"
                   class="text-xs px-3 py-1 rounded-lg"
                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-clock mr-1"></i> Pending
                </a>
                <a href="{{ route('developer.billing.invoices', ['status' => 'overdue']) }}"
                   class="text-xs px-3 py-1 rounded-lg"
                   style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <i class="fas fa-exclamation-triangle mr-1"></i> Overdue
                </a>
                <a href="{{ route('developer.settings.index', ['section' => 'billing']) }}"
                   class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Billing
                </a>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card p-6 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-3">
            <div class="md:col-span-2">
                <label class="block mb-1 text-sm font-medium" style="color: var(--text-primary);">Search</label>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Invoice #, reference..."
                       class="form-input w-full p-2 rounded-lg border"
                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
            </div>
            <div>
                <label class="block mb-1 text-sm font-medium" style="color: var(--text-primary);">Status</label>
                <select name="status" class="form-select w-full p-2 rounded-lg border"
                        style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                    <option value="">All</option>
                    @foreach(['paid','pending','overdue','cancelled'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block mb-1 text-sm font-medium" style="color: var(--text-primary);">Type</label>
                <select name="invoice_type" class="form-select w-full p-2 rounded-lg border"
                        style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                    <option value="">All</option>
                    @foreach(['regular','custom','recurring','auto'] as $t)
                        <option value="{{ $t }}" @selected(request('invoice_type') === $t)>{{ ucfirst($t) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block mb-1 text-sm font-medium" style="color: var(--text-primary);">From</label>
                <input type="date" name="from" value="{{ request('from') }}"
                       class="form-input w-full p-2 rounded-lg border"
                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
            </div>
            <div>
                <label class="block mb-1 text-sm font-medium" style="color: var(--text-primary);">To</label>
                <input type="date" name="to" value="{{ request('to') }}"
                       class="form-input w-full p-2 rounded-lg border"
                       style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
            </div>
            <div class="md:col-span-6 flex gap-2">
                <button type="submit" class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
                <a href="{{ route('developer.billing.invoices') }}" class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                    <i class="fas fa-redo mr-2"></i> Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="card p-4">
            <p class="text-xs" style="color: var(--text-secondary);">Total Invoices</p>
            <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $stats['total'] }}</p>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                {{ $stats['paid'] }} paid · {{ $stats['pending'] }} pending · {{ $stats['overdue'] }} overdue
            </p>
        </div>
        <div class="card p-4">
            <p class="text-xs" style="color: var(--text-secondary);">Total Collected</p>
            <p class="text-2xl font-bold" style="color: var(--success);">
                {{ number_format($stats['sum_collected'], 2) }}
                <span class="text-xs font-normal" style="color: var(--text-secondary);">
                    {{ $settings->billing_currency ?? 'GHS' }}
                </span>
            </p>
        </div>
        <div class="card p-4">
            <p class="text-xs" style="color: var(--text-secondary);">Outstanding</p>
            <p class="text-2xl font-bold" style="color: var(--warning);">
                {{ number_format($stats['sum_outstanding'], 2) }}
                <span class="text-xs font-normal" style="color: var(--text-secondary);">
                    {{ $settings->billing_currency ?? 'GHS' }}
                </span>
            </p>
        </div>
        <div class="card p-4">
            <p class="text-xs" style="color: var(--text-secondary);">Paid Amount (sum)</p>
            <p class="text-2xl font-bold" style="color: var(--primary);">
                {{ number_format($stats['sum_paid'], 2) }}
                <span class="text-xs font-normal" style="color: var(--text-secondary);">
                    {{ $settings->billing_currency ?? 'GHS' }}
                </span>
            </p>
        </div>
    </div>

    {{-- Table --}}
    <div class="card p-6">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <th class="text-left py-2" style="color: var(--text-secondary);">Invoice #</th>
                        <th class="text-left py-2" style="color: var(--text-secondary);">Issued</th>
                        <th class="text-left py-2" style="color: var(--text-secondary);">Due</th>
                        <th class="text-right py-2" style="color: var(--text-secondary);">Amount</th>
                        <th class="text-right py-2" style="color: var(--text-secondary);">Paid</th>
                        <th class="text-center py-2" style="color: var(--text-secondary);">Status</th>
                        <th class="text-right py-2" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        @php
                            $outstanding = max(0, (float) $invoice->amount - (float) ($invoice->paid_amount ?? 0));
                        @endphp
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td class="py-2" style="color: var(--text-primary);">
                                <span class="font-mono text-xs">{{ $invoice->invoice_number }}</span>
                                @if($invoice->invoice_type)
                                    <span class="ml-2 text-xs px-1.5 py-0.5 rounded"
                                          style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                        {{ ucfirst($invoice->invoice_type) }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-2" style="color: var(--text-secondary);">
                                {{ $invoice->issue_date ? \Carbon\Carbon::parse($invoice->issue_date)->format('M j, Y') : '—' }}
                            </td>
                            <td class="py-2" style="color: var(--text-secondary);">
                                {{ $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->format('M j, Y') : '—' }}
                            </td>
                            <td class="py-2 text-right" style="color: var(--text-primary);">
                                {{ number_format((float) $invoice->amount, 2) }}
                                <span class="text-xs" style="color: var(--text-secondary);">
                                    {{ $invoice->currency ?? $settings->billing_currency ?? 'GHS' }}
                                </span>
                            </td>
                            <td class="py-2 text-right" style="color: var(--success);">
                                {{ number_format((float) ($invoice->paid_amount ?? 0), 2) }}
                                @if($outstanding > 0)
                                    <div class="text-xs" style="color: var(--warning);">
                                        {{ number_format($outstanding, 2) }} due
                                    </div>
                                @endif
                            </td>
                            <td class="py-2 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium badge-{{
                                    $invoice->status === 'paid' ? 'success' :
                                    ($invoice->status === 'overdue' ? 'danger' :
                                    ($invoice->status === 'cancelled' ? 'secondary' : 'warning'))
                                }}">
                                    {{ ucfirst($invoice->status ?? 'unknown') }}
                                </span>
                            </td>
                            <td class="py-2 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    {{-- Invoice PDF/Details link — adjust to your route --}}
                                    <button type="button"
                                            onclick="alert('Invoice detail page not yet implemented for #{{ $invoice->invoice_number }}')"
                                            class="text-xs px-2 py-1 rounded"
                                            style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    @if($invoice->status !== 'paid' && $invoice->status !== 'cancelled')
                                        <button type="button"
                                                onclick="alert('Mark-as-paid flow not yet implemented')"
                                                class="text-xs px-2 py-1 rounded"
                                                style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center" style="color: var(--text-secondary);">
                                <i class="fas fa-file-invoice text-3xl mb-3 block opacity-50"></i>
                                <p class="font-medium">No invoices found</p>
                                <p class="text-xs mt-1">
                                    @if(request()->hasAny(['status','search','from','to','invoice_type']))
                                        Try adjusting your filters.
                                    @else
                                        Invoices will appear here once agreements are signed and billing cycles begin.
                                    @endif
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($invoices->hasPages())
            <div class="mt-4">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>

</div>
@endsection