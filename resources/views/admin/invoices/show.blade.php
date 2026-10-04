{{-- resources/views/admin/invoices/show.blade.php --}}
@extends('layouts.app')

@section('title', 'Invoice Details — ' . ($invoice->invoice_number ?? 'INV-' . str_pad($invoice->id, 6, '0', STR_PAD_LEFT)))

@php
    use Carbon\Carbon;

    // ── Normalize coverage periods ──
    $coversPeriods = $invoice->covers_periods;
    if (is_string($coversPeriods)) {
        $decoded = json_decode($coversPeriods, true);
        $coversPeriods = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($coversPeriods)) {
        $coversPeriods = [];
    }

    $formattedCoveragePeriods = [];
    foreach ($coversPeriods as $period) {
        try {
            if (is_string($period) && preg_match('/^\d{4}-\d{2}$/', $period)) {
                $formattedCoveragePeriods[] = Carbon::parse($period . '-01')->format('M Y');
            } else {
                $formattedCoveragePeriods[] = (string) $period;
            }
        } catch (\Throwable $e) {
            $formattedCoveragePeriods[] = (string) $period;
        }
    }

    $isCoverageActive = (bool) ($invoice->is_bulk_payment && $invoice->isPaid() && !empty($coversPeriods));

    $metadata = is_array($invoice->metadata) ? $invoice->metadata : [];
    $childInvoicesCount = $invoice->childInvoices ? $invoice->childInvoices->count() : 0;
    $consolidatedInvoices = isset($metadata['consolidated_invoices']) && is_array($metadata['consolidated_invoices'])
        ? $metadata['consolidated_invoices']
        : [];

    $settings = $settings ?? \App\Models\SystemSetting::getSettings();

    // Role shortcuts
    $isAdmin = auth()->user()->isSuperAdmin() || auth()->user()->isAdmin();

    // Status token
    $statusToken = match ($invoice->status) {
        'paid'         => 'success',
        'pending'      => 'warning',
        'overdue'      => 'danger',
        'partial'      => 'info',
        'processing'   => 'info',
        'cancelled'    => 'secondary',
        'consolidated' => 'info',
        default        => 'secondary',
    };

    // Invoice number (always present on model, but guard anyway)
    $invoiceNumber = $invoice->invoice_number
        ?? 'INV-' . str_pad($invoice->id, 6, '0', STR_PAD_LEFT);
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- ============================================================
         HEADER CARD
    ============================================================ --}}
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6 gap-4">
            <div class="flex items-center flex-wrap gap-2">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2 icon-circle-primary">
                        <i class="fas fa-file-invoice text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center flex-wrap gap-2 text-primary">
                        <i class="fas fa-file-invoice icon-primary"></i>
                        Invoice #{{ $invoiceNumber }}
                        @if($invoice->is_bulk_payment)
                            <span class="pill pill-primary"><i class="fas fa-layer-group mr-1"></i> Bulk Payment</span>
                        @endif
                        @if($invoice->status === 'consolidated')
                            <span class="pill pill-info"><i class="fas fa-link mr-1"></i> Consolidated</span>
                        @endif
                        @if($isCoverageActive)
                            <span class="pill pill-success"><i class="fas fa-shield-alt mr-1"></i> Coverage Active</span>
                        @endif
                    </h2>
                    <div class="text-sm flex items-center mt-1 flex-wrap gap-2 text-secondary">
                        <i class="fas fa-info-circle"></i>
                        <span>{{ $invoice->property?->house_number }} {{ $invoice->property?->street_name }}</span>
                        <span>•</span>
                        <i class="fas fa-circle status-dot status-dot-{{ $statusToken }}"></i>
                        <span class="font-medium">
                            {{ $invoice->status === 'consolidated' ? 'In Bulk' : ucfirst($invoice->status) }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('invoices.index') }}" class="btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Invoices
                </a>
            </div>
        </div>
    </div>

    {{-- ============================================================
         FLASH MESSAGES
    ============================================================ --}}
    @foreach(['success' => 'check-circle', 'error' => 'exclamation-circle', 'warning' => 'exclamation-triangle', 'info' => 'info-circle'] as $type => $icon)
        @if(session($type))
        <div class="card" data-flash>
            <div class="flex items-center p-4 rounded-lg flash-{{ $type === 'error' ? 'danger' : $type }}">
                <div class="flex-shrink-0">
                    <i class="fas fa-{{ $icon }} text-xl icon-{{ $type === 'error' ? 'danger' : $type }}"></i>
                </div>
                <div class="ml-3 flex-1">
                    <p class="font-medium text-{{ $type === 'error' ? 'danger' : $type }}">{{ session($type) }}</p>
                </div>
                <button type="button" class="ml-auto flash-close" aria-label="Dismiss">
                    <i class="fas fa-times text-secondary"></i>
                </button>
            </div>
        </div>
        @endif
    @endforeach

    {{-- ============================================================
         COVERAGE ACTIVE BANNER
    ============================================================ --}}
    @if($isCoverageActive && !empty($formattedCoveragePeriods))
    <div class="card">
        <div class="flex items-start p-4 rounded-lg flash-success">
            <i class="fas fa-shield-alt icon-success text-xl mt-1 mr-3"></i>
            <div class="flex-1">
                <strong class="font-bold text-success">✅ Active Bulk Coverage</strong>
                <p class="text-sm mt-1 text-secondary">
                    This bulk payment has active coverage for {{ count($coversPeriods) }} {{ Str::plural('month', count($coversPeriods)) }}.
                </p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach($formattedCoveragePeriods as $period)
                        <span class="pill pill-success">{{ $period }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ============================================================
         CONSOLIDATED INVOICE BANNER
    ============================================================ --}}
    @if($invoice->status === 'consolidated')
    <div class="card">
        <div class="flex items-start p-4 rounded-lg flash-info">
            <i class="fas fa-info-circle icon-info text-xl mt-1 mr-3"></i>
            <div class="flex-1">
                <strong class="font-bold text-info">Consolidated Invoice</strong>
                <p class="text-sm mt-1 text-secondary">
                    This invoice has been consolidated into a bulk payment.
                </p>
                @if($invoice->bulk_parent_id)
                <p class="mt-2">
                    <a href="{{ route('invoices.show', $invoice->bulk_parent_id) }}" class="btn-soft-primary">
                        <i class="fas fa-layer-group mr-2"></i>
                        View Bulk Invoice INV-{{ str_pad($invoice->bulk_parent_id, 6, '0', STR_PAD_LEFT) }}
                    </a>
                </p>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- ============================================================
         MAIN DETAIL GRID
    ============================================================ --}}
    <div class="card p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">

            {{-- Invoice Information --}}
            <div>
                <h3 class="text-lg font-semibold mb-4 text-primary">Invoice Information</h3>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-sm text-secondary">Invoice Number</dt>
                        <dd class="font-medium text-primary">#{{ $invoiceNumber }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-secondary">
                            {{ $invoice->is_bulk_payment ? 'Bulk Period' : 'Period' }}
                        </dt>
                        <dd class="font-medium text-primary">
                            @if($invoice->is_bulk_payment)
                                @if($invoice->bulk_coverage_start && $invoice->bulk_coverage_end)
                                    {{ Carbon::parse($invoice->bulk_coverage_start . '-01')->format('F Y') }} –
                                    {{ Carbon::parse($invoice->bulk_coverage_end . '-01')->format('F Y') }}
                                @elseif($invoice->bulk_start_month && $invoice->bulk_end_month)
                                    {{ Carbon::parse($invoice->bulk_start_month . '-01')->format('F Y') }} –
                                    {{ Carbon::parse($invoice->bulk_end_month . '-01')->format('F Y') }}
                                @else
                                    {{ $invoice->bulk_months ?? '?' }} Month Bulk Payment
                                @endif
                            @else
                                {{ $invoice->period ? Carbon::parse($invoice->period . '-01')->format('F Y') : '—' }}
                            @endif
                        </dd>
                        @if($invoice->is_bulk_payment && $invoice->bulk_months)
                            <dd class="text-xs mt-1 text-secondary">{{ $invoice->bulk_months }} months total</dd>
                        @endif
                    </div>
                    <div>
                        <dt class="text-sm text-secondary">Due Date</dt>
                        <dd class="font-medium text-primary">
                            {{ $invoice->due_date?->format('M d, Y') ?? '—' }}
                        </dd>
                        @if($invoice->status === 'overdue' && $invoice->due_date)
                            <dd class="text-xs text-danger">
                                Overdue by {{ $invoice->due_date->diffInDays(now()) }} days
                            </dd>
                        @endif
                    </div>
                    <div>
                        <dt class="text-sm text-secondary">Generated By</dt>
                        <dd class="font-medium text-primary">{{ $invoice->creator->name ?? 'System' }}</dd>
                    </div>
                    @if($invoice->updated_by)
                    <div>
                        <dt class="text-sm text-secondary">Last Updated By</dt>
                        <dd class="font-medium text-primary">{{ $invoice->updater->name ?? 'Unknown' }}</dd>
                    </div>
                    @endif
                    @if($invoice->bulk_parent_id)
                    <div>
                        <dt class="text-sm text-secondary">Bulk Parent</dt>
                        <dd class="font-medium text-primary">
                            <a href="{{ route('invoices.show', $invoice->bulk_parent_id) }}" class="link-inline">
                                INV-{{ str_pad($invoice->bulk_parent_id, 6, '0', STR_PAD_LEFT) }}
                            </a>
                        </dd>
                    </div>
                    @endif
                </dl>
            </div>

            {{-- Property Information --}}
            <div>
                <h3 class="text-lg font-semibold mb-4 text-primary">Property Information</h3>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-sm text-secondary">Address</dt>
                        <dd class="font-medium text-primary">
                            {{ $invoice->property?->house_number }} {{ $invoice->property?->street_name }}
                        </dd>
                        @if($invoice->property?->block_number)
                            <dd class="text-sm text-secondary">Block: {{ $invoice->property->block_number }}</dd>
                        @endif
                    </div>
                    <div>
                        <dt class="text-sm text-secondary">Zone / Section</dt>
                        <dd class="font-medium text-primary">
                            @if($invoice->property?->zone && $invoice->property?->section)
                                {{ $invoice->property->zone }} — {{ $invoice->property->section }}
                            @elseif($invoice->property?->zone)
                                {{ $invoice->property->zone }}
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-secondary">Digital Address</dt>
                        <dd class="font-medium text-primary">
                            {{ $invoice->property?->digital_address ?? 'Not assigned' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-secondary">Landlord</dt>
                        <dd class="font-medium text-primary">{{ $invoice->property?->landlord?->name ?? '—' }}</dd>
                        @if($invoice->property?->landlord?->phone)
                            <dd class="text-sm text-secondary">{{ $invoice->property->landlord->phone }}</dd>
                        @endif
                        @if($invoice->property?->landlord?->email)
                            <dd class="text-sm text-secondary">{{ $invoice->property->landlord->email }}</dd>
                        @endif
                    </div>
                </dl>
            </div>

            {{-- Payment Information --}}
            <div>
                <h3 class="text-lg font-semibold mb-4 text-primary">Payment Information</h3>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-sm text-secondary">Status</dt>
                        <dd class="flex items-center flex-wrap gap-2 mt-1">
                            <span class="pill pill-{{ $statusToken }}">
                                <i class="fas fa-circle status-dot status-dot-{{ $statusToken }}"></i>
                                {{ $invoice->status === 'consolidated' ? 'In Bulk' : ucfirst($invoice->status) }}
                            </span>
                            @if($isCoverageActive)
                                <span class="pill pill-success">
                                    <i class="fas fa-shield-alt mr-1"></i> Coverage Active
                                </span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-secondary">Base Amount</dt>
                        <dd class="font-medium text-primary">{{ $settings->formatAmount($invoice->amount) }}</dd>
                    </div>
                    @if($invoice->discount_amount > 0)
                    <div>
                        <dt class="text-sm text-success">Discount ({{ $invoice->discount_percentage }}%)</dt>
                        <dd class="font-medium text-success">−{{ $settings->formatAmount($invoice->discount_amount) }}</dd>
                    </div>
                    @endif
                    @if($invoice->penalty_amount > 0)
                    <div>
                        <dt class="text-sm text-danger">Late Fee Penalty</dt>
                        <dd class="font-medium text-danger">+{{ $settings->formatAmount($invoice->penalty_amount) }}</dd>
                    </div>
                    @endif
                    <div class="pt-2 border-t" style="border-color: var(--border-color);">
                        <dt class="text-sm text-secondary">Total Amount</dt>
                        <dd class="text-2xl font-bold text-success">
                            {{ $settings->formatAmount($invoice->total_amount ?? ($invoice->amount + ($invoice->penalty_amount ?? 0) - ($invoice->discount_amount ?? 0))) }}
                        </dd>
                    </div>
                    @if($invoice->paid_amount > 0)
                    <div>
                        <dt class="text-sm text-secondary">Paid Amount</dt>
                        <dd class="font-medium text-success">{{ $settings->formatAmount($invoice->paid_amount) }}</dd>
                    </div>
                    @endif
                    @if($invoice->balance > 0)
                    <div>
                        <dt class="text-sm text-secondary">Balance Due</dt>
                        <dd class="font-medium text-danger">{{ $settings->formatAmount($invoice->balance) }}</dd>
                    </div>
                    @endif
                    @if($invoice->payment_date)
                    <div>
                        <dt class="text-sm text-secondary">Payment Date</dt>
                        <dd class="font-medium text-primary">{{ Carbon::parse($invoice->payment_date)->format('M d, Y') }}</dd>
                    </div>
                    @endif
                    @if($invoice->payment_method)
                    <div>
                        <dt class="text-sm text-secondary">Payment Method</dt>
                        <dd class="font-medium text-primary">{{ ucfirst(str_replace('_', ' ', $invoice->payment_method)) }}</dd>
                    </div>
                    @endif
                    @if($invoice->payment_reference)
                    <div>
                        <dt class="text-sm text-secondary">Reference</dt>
                        <dd class="font-medium text-primary">{{ $invoice->payment_reference }}</dd>
                    </div>
                    @endif
                </dl>
            </div>
        </div>

        {{-- ============================================================
             BULK COVERAGE DETAILS
        ============================================================ --}}
        @if($isCoverageActive && !empty($coversPeriods))
        <div class="mt-6 p-4 rounded-lg flash-success">
            <h3 class="text-lg font-semibold mb-3 text-success">
                <i class="fas fa-shield-alt mr-2"></i> Active Bulk Coverage
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-secondary">Covered Periods</p>
                    <div class="flex flex-wrap gap-2 mt-1">
                        @foreach($formattedCoveragePeriods as $period)
                            <span class="pill pill-success">{{ $period }}</span>
                        @endforeach
                    </div>
                </div>
                <div>
                    <p class="text-sm text-secondary">Coverage Period</p>
                    <p class="font-medium text-primary">
                        @if($invoice->bulk_coverage_start && $invoice->bulk_coverage_end)
                            {{ Carbon::parse($invoice->bulk_coverage_start . '-01')->format('M Y') }} –
                            {{ Carbon::parse($invoice->bulk_coverage_end . '-01')->format('M Y') }}
                        @else
                            {{ count($coversPeriods) }} months
                        @endif
                    </p>
                    @if(isset($metadata['coverage_activated_at']))
                        <p class="text-xs mt-1 text-secondary">
                            Activated: {{ Carbon::parse($metadata['coverage_activated_at'])->format('M d, Y h:i A') }}
                        </p>
                    @endif
                </div>
            </div>
            <div class="mt-3 p-2 rounded-lg flash-info">
                <p class="text-xs text-secondary">
                    <i class="fas fa-info-circle mr-1"></i>
                    No further invoices will be generated for these months. This bulk payment covers all rental obligations for the periods shown above.
                </p>
            </div>

            @if(!empty($consolidatedInvoices))
            <div class="mt-3">
                <p class="text-sm font-medium mb-2 text-secondary">Consolidated Invoices:</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($consolidatedInvoices as $consolidatedId)
                        <a href="{{ route('invoices.show', $consolidatedId) }}" class="pill pill-info">
                            INV-{{ str_pad($consolidatedId, 6, '0', STR_PAD_LEFT) }}
                        </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @endif

        {{-- ============================================================
             PAYMENT DETAILS
        ============================================================ --}}
        @if($invoice->payment)
        <div class="mt-6 p-4 rounded-lg flash-success">
            <h3 class="text-lg font-semibold mb-3 text-success">Payment Details</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-secondary">Transaction ID</p>
                    <p class="font-medium text-primary">{{ $invoice->payment->transaction_id ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-secondary">Payment Provider</p>
                    <p class="font-medium text-primary">{{ $invoice->payment->payment_provider ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-secondary">Payment Status</p>
                    <span class="pill pill-success">{{ $invoice->payment->status ?? 'Completed' }}</span>
                </div>
                <div>
                    <p class="text-sm text-secondary">Processed At</p>
                    <p class="font-medium text-primary">
                        {{ $invoice->payment->created_at?->format('M d, Y \a\t h:i A') ?? '—' }}
                    </p>
                </div>
            </div>
        </div>
        @endif

        {{-- ============================================================
             CHILD INVOICES (for bulk payments)
        ============================================================ --}}
        @if($invoice->is_bulk_payment && $childInvoicesCount > 0)
        <div class="mt-8">
            <h3 class="text-lg font-semibold mb-4 text-primary">Included Monthly Invoices</h3>
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th class="table-th">Period</th>
                            <th class="table-th">Invoice #</th>
                            <th class="table-th">Amount</th>
                            <th class="table-th">Status</th>
                            <th class="table-th">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->childInvoices as $child)
                        <tr class="invoice-row">
                            <td class="table-td">{{ Carbon::parse($child->period . '-01')->format('F Y') }}</td>
                            <td class="table-td">
                                <a href="{{ route('invoices.show', $child->id) }}" class="link-inline">
                                    #INV-{{ str_pad($child->id, 6, '0', STR_PAD_LEFT) }}
                                </a>
                            </td>
                            <td class="table-td">{{ $settings->formatAmount($child->amount) }}</td>
                            <td class="table-td">
                                <span class="pill pill-info">Consolidated</span>
                            </td>
                            <td class="table-td">
                                <a href="{{ route('invoices.show', $child->id) }}" class="action-btn action-info" title="View Invoice">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if(isset($metadata['existing_count']) || isset($metadata['new_count']))
            <div class="mt-4 p-3 rounded-lg flash-info">
                <p class="text-sm text-secondary">
                    <i class="fas fa-info-circle icon-info mr-2"></i>
                    This bulk payment includes
                    @if(isset($metadata['existing_count']) && $metadata['existing_count'] > 0)
                        <strong>{{ $metadata['existing_count'] }}</strong> existing invoice(s)
                    @endif
                    @if(isset($metadata['existing_count']) && $metadata['existing_count'] > 0 && isset($metadata['new_count']) && $metadata['new_count'] > 0)
                        and
                    @endif
                    @if(isset($metadata['new_count']) && $metadata['new_count'] > 0)
                        <strong>{{ $metadata['new_count'] }}</strong> new month(s)
                    @endif
                    .
                </p>
            </div>
            @endif
        </div>
        @endif

        {{-- ============================================================
             INVOICE BREAKDOWN
        ============================================================ --}}
        <div class="mt-8">
            <h3 class="text-lg font-semibold mb-4 text-primary">Invoice Breakdown</h3>
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th class="table-th">Description</th>
                            <th class="table-th text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if($invoice->is_bulk_payment && $childInvoicesCount > 0)
                            @foreach($invoice->childInvoices as $child)
                            <tr class="invoice-row">
                                <td class="table-td">
                                    Monthly Dues — {{ Carbon::parse($child->period . '-01')->format('F Y') }}
                                    @if($child->created_at == $child->updated_at)
                                        <span class="text-xs ml-2 text-info">(Existing)</span>
                                    @else
                                        <span class="text-xs ml-2 text-success">(New)</span>
                                    @endif
                                </td>
                                <td class="table-td text-right">{{ $settings->formatAmount($child->amount) }}</td>
                            </tr>
                            @endforeach

                            @if($invoice->discount_amount > 0)
                            <tr class="invoice-row">
                                <td class="table-td text-success">Bulk Discount ({{ $invoice->discount_percentage }}%)</td>
                                <td class="table-td text-right text-success">−{{ $settings->formatAmount($invoice->discount_amount) }}</td>
                            </tr>
                            @endif

                            @if($invoice->penalty_amount > 0)
                            <tr class="invoice-row">
                                <td class="table-td text-danger">Late Fee Penalty</td>
                                <td class="table-td text-right text-danger">+{{ $settings->formatAmount($invoice->penalty_amount) }}</td>
                            </tr>
                            @endif

                            <tr style="background-color: rgba(var(--primary-rgb), 0.05);">
                                <td class="table-td font-semibold text-primary">Total Amount</td>
                                <td class="table-td text-right font-semibold text-success">
                                    {{ $settings->formatAmount($invoice->total_amount ?? $invoice->amount) }}
                                </td>
                            </tr>
                        @else
                            <tr class="invoice-row">
                                <td class="table-td">
                                    Monthly Dues for {{ $invoice->is_bulk_payment ? 'Bulk Payment' : ($invoice->period ? Carbon::parse($invoice->period . '-01')->format('F Y') : '—') }}
                                </td>
                                <td class="table-td text-right">{{ $settings->formatAmount($invoice->amount) }}</td>
                            </tr>

                            @if($invoice->discount_amount > 0)
                            <tr class="invoice-row">
                                <td class="table-td text-success">Discount ({{ $invoice->discount_percentage }}%)</td>
                                <td class="table-td text-right text-success">−{{ $settings->formatAmount($invoice->discount_amount) }}</td>
                            </tr>
                            @endif

                            @if($invoice->penalty_amount > 0)
                            <tr class="invoice-row">
                                <td class="table-td text-danger">Late Fee Penalty</td>
                                <td class="table-td text-right text-danger">+{{ $settings->formatAmount($invoice->penalty_amount) }}</td>
                            </tr>
                            @endif

                            <tr style="background-color: rgba(var(--primary-rgb), 0.05);">
                                <td class="table-td font-semibold text-primary">Total Amount</td>
                                <td class="table-td text-right font-semibold text-success">
                                    {{ $settings->formatAmount($invoice->total_amount ?? ($invoice->amount + ($invoice->penalty_amount ?? 0) - ($invoice->discount_amount ?? 0))) }}
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ============================================================
             NOTES
        ============================================================ --}}
        @if($invoice->notes)
        <div class="mt-8">
            <h3 class="text-lg font-semibold mb-4 text-primary">Notes</h3>
            <div class="p-4 rounded-lg flash-info">
                <p class="text-sm text-primary whitespace-pre-line">{{ $invoice->notes }}</p>
            </div>
        </div>
        @endif

        {{-- ============================================================
             NOTIFICATION STATUS
        ============================================================ --}}
        @if(isset($notificationStatus))
        <div class="mt-8">
            <h3 class="text-lg font-semibold mb-4 text-primary">Notification Status</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-3 rounded-lg flash-info">
                    <p class="text-sm text-secondary">Generated Notification</p>
                    <p class="font-medium text-primary">
                        @if($notificationStatus['generated_notification_sent'] ?? false)
                            <span class="text-success"><i class="fas fa-check-circle mr-1"></i> Sent</span>
                        @else
                            <span class="text-warning"><i class="fas fa-clock mr-1"></i> Not Sent</span>
                        @endif
                    </p>
                    @if($notificationStatus['generated_sent_at'] ?? null)
                        <p class="text-xs text-secondary">
                            {{ Carbon::parse($notificationStatus['generated_sent_at'])->format('M d, Y h:i A') }}
                        </p>
                    @endif
                </div>

                <div class="p-3 rounded-lg flash-info">
                    <p class="text-sm text-secondary">Reminder Sent</p>
                    <p class="font-medium text-primary">
                        @if($notificationStatus['reminder_sent'] ?? false)
                            <span class="text-success"><i class="fas fa-check-circle mr-1"></i> Yes</span>
                        @else
                            <span class="text-warning"><i class="fas fa-clock mr-1"></i> No</span>
                        @endif
                    </p>
                    @if($notificationStatus['last_reminder_sent_at'] ?? null)
                        <p class="text-xs text-secondary">
                            {{ Carbon::parse($notificationStatus['last_reminder_sent_at'])->format('M d, Y') }}
                        </p>
                    @endif
                </div>

                <div class="p-3 rounded-lg flash-info">
                    <p class="text-sm text-secondary">Overdue Notification</p>
                    <p class="font-medium text-primary">
                        @if($notificationStatus['overdue_notification_sent'] ?? false)
                            <span class="text-success"><i class="fas fa-check-circle mr-1"></i> Sent</span>
                        @else
                            <span class="text-warning"><i class="fas fa-clock mr-1"></i> Not Sent</span>
                        @endif
                    </p>
                    @if($notificationStatus['overdue_sent_at'] ?? null)
                        <p class="text-xs text-secondary">
                            {{ Carbon::parse($notificationStatus['overdue_sent_at'])->format('M d, Y') }}
                        </p>
                    @endif
                </div>
            </div>

            @if($notificationStatus['has_reminder_schedule'] ?? false)
            <div class="mt-3 p-2 rounded-lg flash-info">
                <p class="text-xs text-secondary">
                    <i class="fas fa-clock mr-1"></i>
                    Next reminder scheduled for
                    {{ Carbon::parse($notificationStatus['next_reminder_date'])->format('M d, Y') }}
                </p>
            </div>
            @endif
        </div>
        @endif

        {{-- ============================================================
             ACTIONS
        ============================================================ --}}
        <div class="mt-8 pt-6 border-t flex flex-wrap justify-between items-center gap-4" style="border-color: var(--border-color);">
            <div>
                <p class="text-sm text-secondary">
                    Created: {{ $invoice->created_at->format('M d, Y \a\t h:i A') }}
                </p>
                @if($invoice->updated_at != $invoice->created_at)
                <p class="text-sm text-secondary">
                    Last Updated: {{ $invoice->updated_at->format('M d, Y \a\t h:i A') }}
                </p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                @if($isAdmin && !in_array($invoice->status, ['paid', 'consolidated']))
                    <button type="button" onclick="openMarkAsPaidModal()" class="btn-primary">
                        <i class="fas fa-check-circle mr-2"></i> Mark as Paid
                    </button>
                    <button type="button" onclick="openApplyPenaltyModal()" class="btn-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i> Apply Penalty
                    </button>
                    <button type="button" onclick="openResendNotificationModal()" class="btn-info">
                        <i class="fas fa-envelope mr-2"></i> Resend Notification
                    </button>
                    <button type="button" onclick="openEditModal()" class="btn-secondary">
                        <i class="fas fa-edit mr-2"></i> Edit
                    </button>
                    <button type="button" onclick="openDeleteModal()" class="btn-danger">
                        <i class="fas fa-trash mr-2"></i> Delete
                    </button>
                @endif

                @if($isAdmin && $invoice->penalty_amount > 0)
                    <button type="button" onclick="openRemovePenaltyModal()" class="btn-warning">
                        <i class="fas fa-undo mr-2"></i> Remove Penalty
                    </button>
                @endif

                @if($isAdmin && $invoice->is_bulk_payment && $childInvoicesCount > 0)
                    <button type="button" onclick="openReverseConsolidationModal()" class="btn-warning">
                        <i class="fas fa-undo mr-2"></i> Reverse Consolidation
                    </button>
                @endif

                <a href="{{ route('invoices.print', $invoice->id) }}" target="_blank" class="btn-secondary">
                    <i class="fas fa-print mr-2"></i> Print
                </a>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================
     MODALS
============================================================ --}}
@include('admin.invoices.partials.show-modals')
@endsection

@section('scripts')
<script>
'use strict';

const INVOICE_CONFIG = {
    csrfToken: @json(csrf_token()),
    invoiceId: {{ $invoice->id }},
    routes: {
        markPaid:            @json(route('invoices.mark-paid', $invoice->id)),
        applyPenalty:        @json(route('invoices.apply-penalty', $invoice->id)),
        removePenalty:       @json(route('invoices.remove-penalty', $invoice->id)),
        resendNotification:  @json(route('invoices.resend-notification', $invoice->id)),
        reverseConsolidation:@json(route('invoices.reverse-consolidation', $invoice->id)),
        update:              @json(route('invoices.update', $invoice->id)),
        destroy:             @json(route('invoices.destroy', $invoice->id)),
    },
};

document.addEventListener('DOMContentLoaded', () => {
    // Auto-dismiss flash banners
    document.querySelectorAll('[data-flash]').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity .4s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 400);
        }, 5000);
    });

    document.querySelectorAll('.flash-close').forEach(btn => {
        btn.addEventListener('click', () => btn.closest('.card')?.remove());
    });

    // Modal backdrop click closes
    document.querySelectorAll('.modal-backdrop').forEach(modal => {
        modal.addEventListener('click', e => {
            if (e.target === modal) closeAllModals();
        });
    });

    // Escape closes all
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeAllModals();
    });
});

// ── Modal helpers ──
function openModal(id)  { document.getElementById(id)?.classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id)?.classList.add('hidden'); }
function closeAllModals() {
    document.querySelectorAll('.modal-backdrop').forEach(m => m.classList.add('hidden'));
}

function openMarkAsPaidModal()          { openModal('markAsPaidModal'); }
function closeMarkAsPaidModal()         { closeModal('markAsPaidModal'); }
function openApplyPenaltyModal()        { openModal('applyPenaltyModal'); }
function closeApplyPenaltyModal()       { closeModal('applyPenaltyModal'); }
function openRemovePenaltyModal()       { openModal('removePenaltyModal'); }
function closeRemovePenaltyModal()      { closeModal('removePenaltyModal'); }
function openResendNotificationModal()  { openModal('resendNotificationModal'); }
function closeResendNotificationModal() { closeModal('resendNotificationModal'); }
function openReverseConsolidationModal(){ openModal('reverseConsolidationModal'); }
function closeReverseConsolidationModal(){ closeModal('reverseConsolidationModal'); }
function openEditModal()                { openModal('editModal'); }
function closeEditModal()               { closeModal('editModal'); }
function openDeleteModal()              { openModal('deleteModal'); }
function closeDeleteModal()             { closeModal('deleteModal'); }
</script>
@endsection