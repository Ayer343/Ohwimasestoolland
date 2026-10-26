{{-- resources/views/admin/ads/invoices.blade.php --}}
@extends('layouts.app')

@section('title', 'Ad Invoices — Revenue')

@php
    use App\Models\AdInvoice;
    use Illuminate\Support\Str;

    // Defensive defaults — the controller passes $invoices (paginator) and $totals (array).
    $invoices = $invoices ?? collect();
    $totals   = $totals   ?? [
        'paid'    => 0,
        'pending' => 0,
        'count'   => 0,
    ];

    $statusColors = [
        AdInvoice::STATUS_PENDING   => 'warning',
        AdInvoice::STATUS_PAID      => 'success',
        AdInvoice::STATUS_FAILED    => 'danger',
        AdInvoice::STATUS_REFUNDED  => 'info',
        AdInvoice::STATUS_CANCELLED => 'secondary',
    ];

    $statusLabels = [
        AdInvoice::STATUS_PENDING   => 'Pending',
        AdInvoice::STATUS_PAID      => 'Paid',
        AdInvoice::STATUS_FAILED    => 'Failed',
        AdInvoice::STATUS_REFUNDED  => 'Refunded',
        AdInvoice::STATUS_CANCELLED => 'Cancelled',
    ];

    $filterStatus = request('status', '');
    $searchTerm   = request('search', '');
    $totalRows    = method_exists($invoices, 'total') ? $invoices->total() : $invoices->count();

    $currencySymbol = '₵';
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- ==================== HEADER ==================== --}}
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-4">
            <div class="flex items-center">
                <div class="w-16 h-16 rounded-full flex items-center justify-center border-2 mr-4"
                     style="background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border-color: var(--primary);">
                    <i class="fas fa-file-invoice-dollar text-xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center gap-2" style="color: var(--text-primary);">
                        <i class="fas fa-file-invoice-dollar mr-2" style="color: var(--primary);"></i>
                        Ad Invoices
                    </h2>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-coins mr-2"></i>
                        Ad revenue stream — separate from rent invoices
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('admin.homepage-content.ads.settings') }}"
                   class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                    <i class="fas fa-cog mr-2"></i> Ad Settings
                </a>
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

    {{-- ==================== REVENUE SUMMARY ==================== --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        {{-- Total paid --}}
        <div class="card p-5"
             style="border-left: 4px solid var(--success); background-color: rgba(var(--success-rgb), 0.03);">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium" style="color: var(--text-secondary);">
                    <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                    Total Collected
                </span>
                <i class="fas fa-coins text-lg" style="color: var(--success);"></i>
            </div>
            <p class="text-2xl font-bold" style="color: var(--success);">
                {{ $currencySymbol }}{{ number_format((float) $totals['paid'], 2) }}
            </p>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                Revenue from all paid ad invoices
            </p>
        </div>

        {{-- Pending --}}
        <div class="card p-5"
             style="border-left: 4px solid var(--warning); background-color: rgba(var(--warning-rgb), 0.03);">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium" style="color: var(--text-secondary);">
                    <i class="fas fa-clock mr-1" style="color: var(--warning);"></i>
                    Outstanding
                </span>
                <i class="fas fa-hourglass-half text-lg" style="color: var(--warning);"></i>
            </div>
            <p class="text-2xl font-bold" style="color: var(--warning);">
                {{ $currencySymbol }}{{ number_format((float) $totals['pending'], 2) }}
            </p>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                Invoices awaiting payment
            </p>
        </div>

        {{-- Total invoices --}}
        <div class="card p-5"
             style="border-left: 4px solid var(--primary); background-color: rgba(var(--primary-rgb), 0.03);">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium" style="color: var(--text-secondary);">
                    <i class="fas fa-file-invoice mr-1" style="color: var(--primary);"></i>
                    Total Invoices
                </span>
                <i class="fas fa-list text-lg" style="color: var(--primary);"></i>
            </div>
            <p class="text-2xl font-bold" style="color: var(--primary);">
                {{ number_format((int) $totals['count']) }}
            </p>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                All-time ad invoices
            </p>
        </div>
    </div>

    {{-- ==================== FILTERS ==================== --}}
    <div class="card">
        <div class="p-5">
            <form method="GET" action="{{ route('admin.homepage-content.ads.invoices') }}"
                  class="grid grid-cols-1 md:grid-cols-3 gap-3">

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
                               placeholder="Invoice number, landlord name…"
                               class="form-input w-full pl-9 pr-3 py-2 rounded-lg">
                    </div>
                </div>

                {{-- Status --}}
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                        Status
                    </label>
                    <div class="flex gap-2">
                        <select name="status" class="form-input flex-1 px-3 py-2 rounded-lg">
                            <option value="">All statuses</option>
                            @foreach($statusLabels as $value => $label)
                                <option value="{{ $value }}" @selected($filterStatus === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit"
                                class="btn-primary px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center">
                            <i class="fas fa-filter"></i>
                        </button>
                    </div>
                </div>
            </form>

            @if($searchTerm || $filterStatus)
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
                            {{ $statusLabels[$filterStatus] ?? $filterStatus }}
                        </span>
                    @endif

                    <a href="{{ route('admin.homepage-content.ads.invoices') }}"
                       class="text-xs underline ml-2" style="color: var(--text-secondary);">
                        Clear
                    </a>
                </div>
            @endif
        </div>
    </div>

    {{-- ==================== INVOICES LIST ==================== --}}
    <div class="card">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-list mr-2"></i>
                    All Invoices
                    <span class="ml-2 px-2 py-0.5 rounded-full text-xs font-medium"
                          style="background: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        {{ $totalRows }}
                    </span>
                </h3>
            </div>

            @if($invoices->isEmpty())
                <div class="text-center py-16 rounded-lg"
                     style="background-color: rgba(var(--secondary-rgb), 0.03);
                            border: 1px dashed var(--border-color);">
                    <i class="fas fa-file-invoice-dollar text-5xl mb-4"
                       style="color: var(--text-secondary); opacity: 0.35;"></i>
                    <p class="text-base font-medium mb-1" style="color: var(--text-primary);">
                        No invoices found
                    </p>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        @if($searchTerm || $filterStatus)
                            Try clearing your filters.
                        @else
                            Invoices will appear here once landlords start paying for ad campaigns.
                        @endif
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border-color);">
                                <th class="text-left py-3 px-2 font-semibold" style="color: var(--text-primary);">Invoice</th>
                                <th class="text-left py-3 px-2 font-semibold" style="color: var(--text-primary);">Landlord</th>
                                <th class="text-left py-3 px-2 font-semibold" style="color: var(--text-primary);">Campaign</th>
                                <th class="text-right py-3 px-2 font-semibold" style="color: var(--text-primary);">Amount</th>
                                <th class="text-left py-3 px-2 font-semibold" style="color: var(--text-primary);">Gateway</th>
                                <th class="text-left py-3 px-2 font-semibold" style="color: var(--text-primary);">Status</th>
                                <th class="text-left py-3 px-2 font-semibold" style="color: var(--text-primary);">Dates</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoices as $invoice)
                                @php
                                    $campaign     = $invoice->campaign;
                                    $unit         = $campaign?->unit;
                                    $landlord     = $invoice->landlord ?? $campaign?->landlord;
                                    $unitLabel    = $unit?->unit_name ?? $unit?->unit_number ?? ($unit ? 'Unit #' . $unit->id : '—');
                                    $statusColor  = $statusColors[$invoice->status] ?? 'secondary';
                                    $statusLabel  = $statusLabels[$invoice->status] ?? ucfirst($invoice->status);
                                    $invoiceSym   = $invoice->currency === 'GHS' ? '₵' : ($invoice->currency . ' ');
                                @endphp
                                <tr style="border-bottom: 1px solid var(--border-color); vertical-align: top;">
                                    {{-- Invoice number --}}
                                    <td class="py-3 px-2">
                                        <div class="font-mono text-xs font-semibold" style="color: var(--text-primary);">
                                            {{ $invoice->invoice_number }}
                                        </div>
                                        <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                            <i class="fas fa-calendar-alt mr-1"></i>
                                            {{ $invoice->created_at?->format('M d, Y') ?? '—' }}
                                        </div>
                                    </td>

                                    {{-- Landlord --}}
                                    <td class="py-3 px-2">
                                        @if($landlord)
                                            <div class="text-sm" style="color: var(--text-primary);">
                                                {{ $landlord->name }}
                                            </div>
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                {{ $landlord->email }}
                                            </div>
                                        @else
                                            <span class="text-xs" style="color: var(--text-secondary);">—</span>
                                        @endif
                                    </td>

                                    {{-- Campaign --}}
                                    <td class="py-3 px-2">
                                        @if($campaign)
                                            <a href="{{ route('admin.homepage-content.ads.campaigns', ['search' => $unitLabel]) }}"
                                               class="text-sm font-medium hover:underline"
                                               style="color: var(--primary);">
                                                {{ $unitLabel }}
                                            </a>
                                            <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                                {{ $campaign->plan_name }}
                                            </div>
                                        @else
                                            <span class="text-xs" style="color: var(--text-secondary);">—</span>
                                        @endif
                                    </td>

                                    {{-- Amount --}}
                                    <td class="py-3 px-2 text-right">
                                        <div class="font-bold text-base" style="color: var(--text-primary);">
                                            {{ $invoiceSym }}{{ number_format((float) $invoice->amount, 2) }}
                                        </div>
                                    </td>

                                    {{-- Gateway --}}
                                    <td class="py-3 px-2">
                                        @if($invoice->gateway)
                                            <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium"
                                                 style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                @if($invoice->gateway === 'paystack')<i class="fas fa-credit-card"></i>
                                                @elseif($invoice->gateway === 'flutterwave')<i class="fas fa-cloud-upload-alt"></i>
                                                @elseif($invoice->gateway === 'hubtel')<i class="fas fa-phone-alt"></i>
                                                @elseif($invoice->gateway === 'expresspay')<i class="fas fa-bolt"></i>
                                                @else<i class="fas fa-money-bill-wave"></i>
                                                @endif
                                                {{ ucfirst($invoice->gateway) }}
                                            </div>
                                            @if($invoice->gateway_reference)
                                                <div class="text-xs mt-1 font-mono truncate max-w-[140px]"
                                                     style="color: var(--text-secondary);"
                                                     title="{{ $invoice->gateway_reference }}">
                                                    {{ Str::limit($invoice->gateway_reference, 20) }}
                                                </div>
                                            @endif
                                        @else
                                            <span class="text-xs" style="color: var(--text-secondary);">—</span>
                                        @endif
                                    </td>

                                    {{-- Status --}}
                                    <td class="py-3 px-2">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                              style="background: rgba(var(--{{ $statusColor }}-rgb), 0.15);
                                                     color: var(--{{ $statusColor }});">
                                            @if($invoice->status === AdInvoice::STATUS_PAID)<i class="fas fa-check-circle mr-1"></i>
                                            @elseif($invoice->status === AdInvoice::STATUS_PENDING)<i class="fas fa-clock mr-1"></i>
                                            @elseif($invoice->status === AdInvoice::STATUS_FAILED)<i class="fas fa-times-circle mr-1"></i>
                                            @elseif($invoice->status === AdInvoice::STATUS_REFUNDED)<i class="fas fa-undo mr-1"></i>
                                            @elseif($invoice->status === AdInvoice::STATUS_CANCELLED)<i class="fas fa-ban mr-1"></i>
                                            @else<i class="fas fa-info-circle mr-1"></i>
                                            @endif
                                            {{ $statusLabel }}
                                        </span>
                                    </td>

                                    {{-- Dates --}}
                                    <td class="py-3 px-2">
                                        @if($invoice->due_at)
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                <i class="fas fa-hourglass-half mr-1"></i>
                                                Due {{ $invoice->due_at->format('M d') }}
                                            </div>
                                        @endif
                                        @if($invoice->paid_at)
                                            <div class="text-xs" style="color: var(--success);">
                                                <i class="fas fa-check mr-1"></i>
                                                Paid {{ $invoice->paid_at->format('M d') }}
                                            </div>
                                        @endif
                                        @if($invoice->refunded_at)
                                            <div class="text-xs" style="color: var(--info);">
                                                <i class="fas fa-undo mr-1"></i>
                                                Refunded {{ $invoice->refunded_at->format('M d') }}
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="mt-6 flex justify-between items-center flex-wrap gap-3">
                    <div class="text-xs" style="color: var(--text-secondary);">
                        @if(method_exists($invoices, 'firstItem'))
                            Showing {{ $invoices->firstItem() }}–{{ $invoices->lastItem() }}
                            of {{ $invoices->total() }} invoices
                        @else
                            {{ $invoices->count() }} invoices
                        @endif
                    </div>
                    <div>
                        {{ $invoices->links() }}
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