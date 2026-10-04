@php
    // ── Precompute everything once, so the markup is just echo ──
    $isBulk       = (bool) $invoice->is_bulk_payment;
    $isChild      = (bool) $invoice->bulk_parent_id;
    $isPaid       = $invoice->isPaid();
    $isConsol     = $invoice->status === 'consolidated';

    $coverageCount = 0;
    if ($isBulk && $isPaid && !empty($invoice->covers_periods)) {
        $raw = $invoice->covers_periods;
        $arr = is_string($raw) ? json_decode($raw, true) : $raw;
        if (is_array($arr)) $coverageCount = count($arr);
    }

    // Period display
    $periodDisplay = $invoice->period;
    if ($isBulk) {
        $periodDisplay = ($invoice->bulk_coverage_start && $invoice->bulk_coverage_end)
            ? \Carbon\Carbon::parse($invoice->bulk_coverage_start . '-01')->format('M Y')
              . ' – ' .
              \Carbon\Carbon::parse($invoice->bulk_coverage_end . '-01')->format('M Y')
            : 'Bulk Payment';
    } elseif (preg_match('/^\d{4}-\d{2}$/', (string) $invoice->period)) {
        try { $periodDisplay = \Carbon\Carbon::parse($invoice->period . '-01')->format('M Y'); }
        catch (\Throwable $e) { /* keep raw */ }
    }

    $statusColors = [
        'paid' => 'success', 'pending' => 'warning', 'overdue' => 'danger',
        'partial' => 'info', 'processing' => 'info',
        'cancelled' => 'secondary', 'consolidated' => 'info',
    ];
    $statusColor = $statusColors[$invoice->status] ?? 'secondary';
@endphp

<tr class="invoice-row {{ $isConsol ? 'row-consolidated' : '' }} {{ $isChild ? 'child-invoice' : '' }}"
    data-invoice-id="{{ $invoice->id }}"
    data-amount="{{ $invoice->total_amount }}"
    data-status="{{ $invoice->status }}"
    data-type="{{ $isBulk ? 'bulk' : ($isChild ? 'child' : 'regular') }}">

    <td class="table-td">
        @if(!in_array($invoice->status, ['paid', 'consolidated', 'cancelled']))
    @if($settings->isOfflinePaymentAllowed())
        <button type="button" onclick="openMarkAsPaidModal({{ $invoice->id }})"
                class="action-btn action-success" title="Mark as Paid (office payment)">
            <i class="fas fa-check"></i>
        </button>
    @else
        <span class="action-btn action-secondary"
              title="Office payments disabled — landlord must pay online"
              style="cursor: not-allowed; opacity: 0.5;">
            <i class="fas fa-check"></i>
        </span>
    @endif
@endif
    </td>

    <td class="table-td">
        <div class="flex items-center gap-1">
            @if($isBulk)
                <i class="fas fa-layer-group text-xs icon-info"></i>
            @elseif($isChild)
                <i class="fas fa-link text-xs icon-info"></i>
            @endif
            <span class="font-medium text-primary">INV-{{ str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</span>
        </div>
        @if($isChild)
            <p class="text-xs mt-1">
                <a href="{{ route('invoices.show', $invoice->bulk_parent_id) }}" class="link-info">
                    <i class="fas fa-layer-group mr-1"></i>
                    Parent: INV-{{ str_pad($invoice->bulk_parent_id, 6, '0', STR_PAD_LEFT) }}
                </a>
            </p>
        @endif
        @if($invoice->payment_reference)
            <p class="text-xs text-secondary">Ref: {{ $invoice->payment_reference }}</p>
        @endif
    </td>

    <td class="table-td">
        <p class="font-medium text-primary">{{ $invoice->property->house_number }} {{ $invoice->property->street_name }}</p>
        <div class="text-sm text-secondary">
            @if($invoice->property->block_number) Block: {{ $invoice->property->block_number }} @endif
            @if($invoice->property->zone) • {{ $invoice->property->zone }} @endif
        </div>
        <p class="text-xs text-secondary">Landlord: {{ optional($invoice->property->landlord)->name }}</p>
    </td>

    <td class="table-td">
        <p class="font-medium text-primary">{{ $periodDisplay }}</p>
        @if($isBulk && $invoice->bulk_months)
            <p class="text-xs text-secondary">{{ $invoice->bulk_months }} months</p>
        @endif
        @if($isChild)
            <p class="text-xs text-info"><i class="fas fa-check-circle mr-1"></i> Consolidated</p>
        @endif
    </td>

    <td class="table-td">
        <p class="font-medium text-primary">{{ $invoice->due_date->format('M d, Y') }}</p>
        @if($invoice->status === 'overdue')
            <p class="text-xs text-danger">Overdue by {{ $invoice->due_date->diffInDays(now()) }} days</p>
        @endif
    </td>

    <td class="table-td">
        <p class="font-medium text-success">{{ $settings->formatAmount($invoice->amount) }}</p>
        @if($invoice->penalty_amount > 0)
            <p class="text-xs text-danger">+{{ $settings->formatAmount($invoice->penalty_amount) }} penalty</p>
        @endif
        @if(isset($invoice->discount_amount) && $invoice->discount_amount > 0)
            <p class="text-xs text-success">-{{ $settings->formatAmount($invoice->discount_amount) }} discount</p>
        @endif
        @if($invoice->paid_amount > 0 && $invoice->paid_amount < $invoice->total_amount)
            <p class="text-xs text-warning">Paid: {{ $settings->formatAmount($invoice->paid_amount) }}</p>
        @endif
    </td>

    <td class="table-td">
        <span class="pill pill-{{ $statusColor }}">
            <i class="fas fa-circle status-dot status-dot-{{ $statusColor }}"></i>
            {{ $isConsol ? 'In Bulk' : ucfirst($invoice->status) }}
        </span>
    </td>

    <td class="table-td">
        @if($isBulk)
            <span class="pill pill-primary"><i class="fas fa-layer-group mr-1"></i> Bulk</span>
        @elseif($isChild)
            <span class="pill pill-info"><i class="fas fa-link mr-1"></i> Child</span>
        @else
            <span class="pill pill-secondary">Regular</span>
        @endif
    </td>

    <td class="table-td">
        @if($isBulk && $isPaid && $coverageCount > 0)
            <span class="pill pill-success" title="Covers {{ $coverageCount }} months">
                <i class="fas fa-shield-alt mr-1"></i> Active
            </span>
            <p class="text-xs mt-1 text-secondary">{{ $coverageCount }} months</p>
        @elseif($isBulk && $isPaid)
            <span class="pill pill-warning"><i class="fas fa-clock mr-1"></i> Pending</span>
        @elseif($isChild)
            <span class="text-xs text-info">Covered by bulk</span>
        @else
            <span class="text-xs text-secondary">–</span>
        @endif
    </td>

    <td class="table-td">
        @if($invoice->payment_method)
            <span class="pill pill-info">{{ ucfirst(str_replace('_', ' ', $invoice->payment_method)) }}</span>
            @if($invoice->payment_date)
                <p class="text-xs mt-1 text-secondary">{{ \Carbon\Carbon::parse($invoice->payment_date)->format('M d, Y') }}</p>
            @endif
        @else
            <span class="text-sm text-secondary">–</span>
        @endif
    </td>

    <td class="table-td">
        <div class="flex flex-wrap gap-1">
            <a href="{{ route('invoices.show', $invoice->id) }}" class="action-btn action-info" title="View">
                <i class="fas fa-eye"></i>
            </a>
            <a href="{{ route('invoices.print', $invoice->id) }}" class="action-btn action-secondary" title="Print">
                <i class="fas fa-print"></i>
            </a>
            <button onclick="exportSinglePDF({{ $invoice->id }})" class="action-btn action-danger" title="Export PDF">
                <i class="fas fa-file-pdf"></i>
            </button>
            @if(!in_array($invoice->status, ['paid','consolidated']))
                <button onclick="openMarkAsPaidModal({{ $invoice->id }})" class="action-btn action-success" title="Mark as Paid">
                    <i class="fas fa-check"></i>
                </button>
            @endif
            @if($isBulk && $invoice->childInvoices && safeCount($invoice->childInvoices) > 0)
                <button onclick="openReverseConsolidationModal({{ $invoice->id }})" class="action-btn action-warning" title="Reverse Consolidation">
                    <i class="fas fa-undo"></i>
                </button>
            @endif
            @if($isBulk && $isPaid && $coverageCount > 0)
                <button onclick="showCoverageInfo({{ $invoice->id }})" class="action-btn action-success" title="View Coverage">
                    <i class="fas fa-shield-alt"></i>
                </button>
            @endif
            @if(!$isConsol)
                <button onclick="confirmSoftDelete({{ $invoice->id }})" class="action-btn action-danger" title="Move to Trash">
                    <i class="fas fa-trash-alt"></i>
                </button>
            @endif
        </div>
    </td>
</tr>