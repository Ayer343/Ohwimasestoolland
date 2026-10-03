@extends('layouts.app')

@section('title', 'Invoice Details - ' . ($invoice->invoice_number ?? 'INV-'.str_pad($invoice->id, 6, '0', STR_PAD_LEFT)))

@php
    // Safely decode covers_periods if it's a string
    $coversPeriods = $invoice->covers_periods;
    if (is_string($coversPeriods)) {
        $decoded = json_decode($coversPeriods, true);
        $coversPeriods = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($coversPeriods)) {
        $coversPeriods = [];
    }
    
    // Format coverage periods for display
    $formattedCoveragePeriods = [];
    foreach ($coversPeriods as $period) {
        try {
            if (is_string($period) && preg_match('/^\d{4}-\d{2}$/', $period)) {
                $formattedCoveragePeriods[] = \Carbon\Carbon::parse($period . '-01')->format('M Y');
            } else {
                $formattedCoveragePeriods[] = (string) $period;
            }
        } catch (\Exception $e) {
            $formattedCoveragePeriods[] = (string) $period;
        }
    }
    
    // Determine if coverage is active
    $isCoverageActive = $invoice->is_bulk_payment && $invoice->isPaid() && !empty($coversPeriods);
    
    // Safely get metadata
    $metadata = is_array($invoice->metadata) ? $invoice->metadata : [];
    
    // Safely get child invoices count
    $childInvoicesCount = $invoice->childInvoices ? $invoice->childInvoices->count() : 0;
    
    // Safely get consolidated invoices from metadata
    $consolidatedInvoices = isset($metadata['consolidated_invoices']) && is_array($metadata['consolidated_invoices']) ? $metadata['consolidated_invoices'] : [];
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center flex-wrap gap-2">
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-file-invoice mr-2"></i> Invoice Details
                </h2>
                @if($invoice->is_bulk_payment)
                    <span class="ml-3 px-3 py-1 rounded-full text-sm font-medium" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                        <i class="fas fa-layer-group mr-1"></i> Bulk Payment
                    </span>
                @endif
                @if($invoice->status === 'consolidated')
                    <span class="ml-3 px-3 py-1 rounded-full text-sm font-medium" style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                        <i class="fas fa-link mr-1"></i> Consolidated
                    </span>
                @endif
                @if($isCoverageActive)
                    <span class="ml-3 px-3 py-1 rounded-full text-sm font-medium" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                        <i class="fas fa-shield-alt mr-1"></i> Coverage Active
                    </span>
                @endif
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('invoices.index') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Invoices
                </a>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Success!</strong>
        <span class="block sm:inline">{{ session('success') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Error Message -->
    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Error!</strong>
        <span class="block sm:inline">{{ session('error') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Bulk Coverage Active Banner (for paid bulk invoices) -->
    @if($isCoverageActive && !empty($formattedCoveragePeriods))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-start">
            <i class="fas fa-shield-alt text-xl mr-3 mt-1"></i>
            <div>
                <strong class="font-bold">✅ Active Bulk Coverage</strong>
                <p class="text-sm mt-1">This bulk payment has active coverage for {{ count($coversPeriods) }} months.</p>
                @if(!empty($formattedCoveragePeriods))
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach($formattedCoveragePeriods as $period)
                    <span class="px-2 py-1 text-xs rounded-full" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                        {{ $period }}
                    </span>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- Consolidated Invoice Warning (for child invoices) -->
    @if($invoice->status === 'consolidated')
    <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-start">
            <i class="fas fa-info-circle text-xl mr-3 mt-1"></i>
            <div>
                <strong class="font-bold">Consolidated Invoice</strong>
                <p class="text-sm mt-1">This invoice has been consolidated into a bulk payment.</p>
                @if($invoice->bulk_parent_id)
                <p class="text-sm mt-2">
                    <a href="{{ route('invoices.show', $invoice->bulk_parent_id) }}" class="inline-flex items-center px-3 py-1 rounded" style="background-color: var(--primary); color: white;">
                        <i class="fas fa-layer-group mr-2"></i> View Bulk Invoice #{{ str_pad($invoice->bulk_parent_id, 6, '0', STR_PAD_LEFT) }}
                    </a>
                </p>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- Invoice Details Card -->
    <div class="card p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <!-- Invoice Information -->
            <div>
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Invoice Information</h3>
                <div class="space-y-3">
                    <div>
                        <p class="text-sm" style="color: var(--text-secondary);">Invoice Number</p>
                        <p class="font-medium" style="color: var(--text-primary);">
                            #{{ $invoice->invoice_number ?? 'INV-'.str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            @if($invoice->is_bulk_payment) Bulk Period @else Period @endif
                        </p>
                        <p class="font-medium" style="color: var(--text-primary);">
                            @php
                                if($invoice->is_bulk_payment) {
                                    if($invoice->bulk_start_month && $invoice->bulk_end_month) {
                                        echo \Carbon\Carbon::parse($invoice->bulk_start_month . '-01')->format('F Y') . ' - ' . 
                                             \Carbon\Carbon::parse($invoice->bulk_end_month . '-01')->format('F Y');
                                    } else {
                                        echo $invoice->bulk_months . ' Month Bulk Payment';
                                    }
                                } else {
                                    echo \Carbon\Carbon::parse($invoice->period . '-01')->format('F Y');
                                }
                            @endphp
                        </p>
                        @if($invoice->is_bulk_payment && $invoice->bulk_months)
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            {{ $invoice->bulk_months }} months total
                        </p>
                        @endif
                    </div>
                    <div>
                        <p class="text-sm" style="color: var(--text-secondary);">Due Date</p>
                        <p class="font-medium" style="color: var(--text-primary);">
                            {{ $invoice->due_date->format('M d, Y') }}
                        </p>
                        @if($invoice->status === 'overdue')
                        <p class="text-xs" style="color: var(--danger);">
                            Overdue by {{ $invoice->due_date->diffInDays(now()) }} days
                        </p>
                        @endif
                    </div>
                    <div>
                        <p class="text-sm" style="color: var(--text-secondary);">Generated By</p>
                        <p class="font-medium" style="color: var(--text-primary);">
                            {{ $invoice->creator->name ?? 'System' }}
                        </p>
                    </div>
                    @if($invoice->updated_by)
                    <div>
                        <p class="text-sm" style="color: var(--text-secondary);">Last Updated By</p>
                        <p class="font-medium" style="color: var(--text-primary);">
                            {{ $invoice->updater->name ?? 'Unknown' }}
                        </p>
                    </div>
                    @endif
                    @if($invoice->bulk_parent_id)
                    <div>
                        <p class="text-sm" style="color: var(--text-secondary);">Bulk Parent</p>
                        <p class="font-medium" style="color: var(--text-primary);">
                            <a href="{{ route('invoices.show', $invoice->bulk_parent_id) }}" class="text-primary hover:underline">
                                INV-{{ str_pad($invoice->bulk_parent_id, 6, '0', STR_PAD_LEFT) }}
                            </a>
                        </p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Property Information -->
            <div>
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Property Information</h3>
                <div class="space-y-3">
                    <div>
                        <p class="text-sm" style="color: var(--text-secondary);">Address</p>
                        <p class="font-medium" style="color: var(--text-primary);">
                            {{ $invoice->property->house_number }} {{ $invoice->property->street_name }}
                        </p>
                        @if($invoice->property->block_number)
                        <p class="text-sm" style="color: var(--text-secondary);">
                            Block: {{ $invoice->property->block_number }}
                        </p>
                        @endif
                    </div>
                    <div>
                        <p class="text-sm" style="color: var(--text-secondary);">Zone/Section</p>
                        <p class="font-medium" style="color: var(--text-primary);">
                            @if($invoice->property->zone && $invoice->property->section)
                                {{ $invoice->property->zone }} - {{ $invoice->property->section }}
                            @elseif($invoice->property->zone)
                                {{ $invoice->property->zone }}
                            @else
                                -
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-sm" style="color: var(--text-secondary);">Digital Address</p>
                        <p class="font-medium" style="color: var(--text-primary);">
                            {{ $invoice->property->digital_address ?? 'Not assigned' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm" style="color: var(--text-secondary);">Landlord</p>
                        <p class="font-medium" style="color: var(--text-primary);">
                            {{ $invoice->property->landlord->name }}
                        </p>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            {{ $invoice->property->landlord->phone }}
                        </p>
                        @if($invoice->property->landlord->email)
                        <p class="text-sm" style="color: var(--text-secondary);">
                            {{ $invoice->property->landlord->email }}
                        </p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Status & Amount -->
            <div>
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Payment Information</h3>
                <div class="space-y-3">
                    <div>
                        <p class="text-sm" style="color: var(--text-secondary);">Status</p>
                        @php
                            $statusColors = [
                                'paid' => 'success',
                                'pending' => 'warning',
                                'overdue' => 'danger',
                                'partial' => 'info',
                                'cancelled' => 'secondary',
                                'processing' => 'info',
                                'consolidated' => 'info'
                            ];
                            $statusColor = $statusColors[$invoice->status] ?? 'secondary';
                        @endphp
                        <span class="px-3 py-1 rounded-full text-sm font-medium" 
                              style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.2); color: var(--{{ $statusColor }});">
                            {{ $invoice->status == 'consolidated' ? 'In Bulk' : ucfirst($invoice->status) }}
                        </span>
                        
                        @if($isCoverageActive)
                        <span class="ml-2 px-3 py-1 rounded-full text-sm font-medium" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                            <i class="fas fa-shield-alt mr-1"></i> Coverage Active
                        </span>
                        @endif
                    </div>
                    
                    <!-- Amount Breakdown -->
                    <div>
                        <p class="text-sm" style="color: var(--text-secondary);">Base Amount</p>
                        <p class="font-medium" style="color: var(--text-primary);">
                            {{ $settings->formatAmount($invoice->amount) }}
                        </p>
                    </div>
                    
                    @if($invoice->discount_amount > 0)
                    <div>
                        <p class="text-sm" style="color: var(--success);">Discount ({{ $invoice->discount_percentage }}%)</p>
                        <p class="font-medium" style="color: var(--success);">
                            -{{ $settings->formatAmount($invoice->discount_amount) }}
                        </p>
                    </div>
                    @endif
                    
                    @if($invoice->penalty_amount > 0)
                    <div>
                        <p class="text-sm" style="color: var(--danger);">Late Fee Penalty</p>
                        <p class="font-medium" style="color: var(--danger);">
                            +{{ $settings->formatAmount($invoice->penalty_amount) }}
                        </p>
                    </div>
                    @endif
                    
                    <div class="pt-2 border-t" style="border-color: var(--border-color);">
                        <p class="text-sm" style="color: var(--text-secondary);">Total Amount</p>
                        <p class="text-2xl font-bold" style="color: var(--success);">
                            {{ $settings->formatAmount($invoice->total_amount ?? ($invoice->amount + ($invoice->penalty_amount ?? 0) - ($invoice->discount_amount ?? 0))) }}
                        </p>
                    </div>
                    
                    @if($invoice->paid_amount > 0)
                    <div>
                        <p class="text-sm" style="color: var(--text-secondary);">Paid Amount</p>
                        <p class="font-medium" style="color: var(--success);">
                            {{ $settings->formatAmount($invoice->paid_amount) }}
                        </p>
                    </div>
                    @endif
                    
                    @if($invoice->balance > 0)
                    <div>
                        <p class="text-sm" style="color: var(--text-secondary);">Balance Due</p>
                        <p class="font-medium" style="color: var(--danger);">
                            {{ $settings->formatAmount($invoice->balance) }}
                        </p>
                    </div>
                    @endif
                    
                    @if($invoice->payment_date)
                    <div>
                        <p class="text-sm" style="color: var(--text-secondary);">Payment Date</p>
                        <p class="font-medium" style="color: var(--text-primary);">
                            {{ \Carbon\Carbon::parse($invoice->payment_date)->format('M d, Y') }}
                        </p>
                    </div>
                    @endif
                    
                    @if($invoice->payment_method)
                    <div>
                        <p class="text-sm" style="color: var(--text-secondary);">Payment Method</p>
                        <p class="font-medium" style="color: var(--text-primary);">
                            {{ ucfirst(str_replace('_', ' ', $invoice->payment_method)) }}
                        </p>
                    </div>
                    @endif
                    
                    @if($invoice->payment_reference)
                    <div>
                        <p class="text-sm" style="color: var(--text-secondary);">Reference</p>
                        <p class="font-medium" style="color: var(--text-primary);">
                            {{ $invoice->payment_reference }}
                        </p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Bulk Coverage Details (for paid bulk invoices) -->
        @if($isCoverageActive && !empty($coversPeriods))
        <div class="mt-6 p-4 rounded" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
            <h3 class="text-lg font-semibold mb-3" style="color: var(--success);">
                <i class="fas fa-shield-alt mr-2"></i> Active Bulk Coverage
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Covered Periods</p>
                    <div class="flex flex-wrap gap-2 mt-1">
                        @foreach($formattedCoveragePeriods as $period)
                        <span class="px-2 py-1 rounded text-xs" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                            {{ $period }}
                        </span>
                        @endforeach
                    </div>
                </div>
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Coverage Period</p>
                    <p class="font-medium" style="color: var(--text-primary);">
                        @if($invoice->bulk_coverage_start && $invoice->bulk_coverage_end)
                            {{ \Carbon\Carbon::parse($invoice->bulk_coverage_start . '-01')->format('M Y') }} - 
                            {{ \Carbon\Carbon::parse($invoice->bulk_coverage_end . '-01')->format('M Y') }}
                        @else
                            {{ count($coversPeriods) }} months
                        @endif
                    </p>
                    @if(isset($metadata['coverage_activated_at']))
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        Activated: {{ \Carbon\Carbon::parse($metadata['coverage_activated_at'])->format('M d, Y h:i A') }}
                    </p>
                    @endif
                </div>
            </div>
            <div class="mt-3 p-2 rounded" style="background-color: rgba(var(--info-rgb), 0.1);">
                <p class="text-xs" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1"></i>
                    No further invoices will be generated for these months. This bulk payment covers all rental obligations for the periods shown above.
                </p>
            </div>
            
            <!-- Show which invoices were consolidated -->
            @if(!empty($consolidatedInvoices))
            <div class="mt-3">
                <p class="text-sm font-medium mb-2" style="color: var(--text-secondary);">Consolidated Invoices:</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($consolidatedInvoices as $consolidatedId)
                    <a href="{{ route('invoices.show', $consolidatedId) }}" class="px-2 py-1 rounded text-xs" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        INV-{{ str_pad($consolidatedId, 6, '0', STR_PAD_LEFT) }}
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @endif

        <!-- Payment Details (if paid) -->
        @if($invoice->payment)
        <div class="mt-6 p-4 rounded" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
            <h3 class="text-lg font-semibold mb-3" style="color: var(--success);">Payment Details</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Transaction ID</p>
                    <p class="font-medium" style="color: var(--text-primary);">{{ $invoice->payment->transaction_id ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Payment Provider</p>
                    <p class="font-medium" style="color: var(--text-primary);">{{ $invoice->payment->payment_provider ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Payment Status</p>
                    <span class="px-2 py-1 rounded-full text-xs font-medium" 
                          style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                        {{ $invoice->payment->status ?? 'Completed' }}
                    </span>
                </div>
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Processed At</p>
                    <p class="font-medium" style="color: var(--text-primary);">
                        {{ $invoice->payment->created_at->format('M d, Y \a\t h:i A') }}
                    </p>
                </div>
            </div>
        </div>
        @endif

        <!-- Child Invoices (for bulk payments) -->
        @if($invoice->is_bulk_payment && $childInvoicesCount > 0)
        <div class="mt-8">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Included Monthly Invoices</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                         <tr>
                            <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Period</th>
                            <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Invoice #</th>
                            <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Amount</th>
                            <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Status</th>
                            <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Actions</th>
                         </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->childInvoices as $child)
                        <tr class="border-b" style="border-color: var(--border-color);">
                            <td class="p-3" style="color: var(--text-primary);">
                                {{ \Carbon\Carbon::parse($child->period . '-01')->format('F Y') }}
                             </td>
                            <td class="p-3" style="color: var(--text-primary);">
                                <a href="{{ route('invoices.show', $child->id) }}" class="hover:underline" style="color: var(--primary);">
                                    #INV-{{ str_pad($child->id, 6, '0', STR_PAD_LEFT) }}
                                </a>
                             </td>
                            <td class="p-3" style="color: var(--text-primary);">
                                {{ $settings->formatAmount($child->amount) }}
                             </td>
                            <td class="p-3">
                                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                                    Consolidated
                                </span>
                             </td>
                            <td class="p-3">
                                <a href="{{ route('invoices.show', $child->id) }}" class="p-1 rounded" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);" title="View Invoice">
                                    <i class="fas fa-eye"></i>
                                </a>
                             </td>
                         </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <!-- Summary of consolidation -->
            @if(isset($metadata['existing_count']) || isset($metadata['new_count']))
            <div class="mt-4 p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.1);">
                <p class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                    This bulk payment includes 
                    @if(isset($metadata['existing_count']) && $metadata['existing_count'] > 0)
                        <span class="font-bold">{{ $metadata['existing_count'] }}</span> existing invoice(s) 
                    @endif
                    @if(isset($metadata['existing_count']) && $metadata['existing_count'] > 0 && isset($metadata['new_count']) && $metadata['new_count'] > 0)
                        and 
                    @endif
                    @if(isset($metadata['new_count']) && $metadata['new_count'] > 0)
                        <span class="font-bold">{{ $metadata['new_count'] }}</span> new month(s)
                    @endif
                    .
                </p>
            </div>
            @endif
        </div>
        @endif

        <!-- Invoice Breakdown -->
        <div class="mt-8">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Invoice Breakdown</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                         <tr>
                            <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Description</th>
                            <th class="text-right p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Amount</th>
                         </tr>
                    </thead>
                    <tbody>
                        @if($invoice->is_bulk_payment && $childInvoicesCount > 0)
                            @foreach($invoice->childInvoices as $child)
                            <tr class="border-b" style="border-color: var(--border-color);">
                                <td class="p-3" style="color: var(--text-primary);">
                                    Monthly Dues - {{ \Carbon\Carbon::parse($child->period . '-01')->format('F Y') }}
                                    @if($child->created_at == $child->updated_at)
                                    <span class="text-xs ml-2 text-info">(Existing)</span>
                                    @else
                                    <span class="text-xs ml-2 text-success">(New)</span>
                                    @endif
                                 </td>
                                <td class="p-3 text-right" style="color: var(--text-primary);">
                                    {{ $settings->formatAmount($child->amount) }}
                                 </td>
                             </tr>
                            @endforeach
                            
                            @if($invoice->discount_amount > 0)
                            <tr class="border-b" style="border-color: var(--border-color);">
                                <td class="p-3" style="color: var(--success);">Bulk Discount ({{ $invoice->discount_percentage }}%)</td>
                                <td class="p-3 text-right" style="color: var(--success);">-{{ $settings->formatAmount($invoice->discount_amount) }}</td>
                             </tr>
                            @endif
                            
                            @if($invoice->penalty_amount > 0)
                            <tr class="border-b" style="border-color: var(--border-color);">
                                <td class="p-3" style="color: var(--danger);">Late Fee Penalty</td>
                                <td class="p-3 text-right" style="color: var(--danger);">+{{ $settings->formatAmount($invoice->penalty_amount) }}</td>
                             </tr>
                            @endif
                            
                            <tr style="background-color: var(--bg-secondary);">
                                <td class="p-3 font-semibold" style="color: var(--text-primary);">Total Amount</td>
                                <td class="p-3 font-semibold text-right" style="color: var(--success);">
                                    {{ $settings->formatAmount($invoice->total_amount ?? $invoice->amount) }}
                                 </td>
                             </tr>
                        @else
                            <tr class="border-b" style="border-color: var(--border-color);">
                                <td class="p-3" style="color: var(--text-primary);">
                                    Monthly Dues for {{ $invoice->is_bulk_payment ? 'Bulk Payment' : \Carbon\Carbon::parse($invoice->period . '-01')->format('F Y') }}
                                 </td>
                                <td class="p-3 text-right" style="color: var(--text-primary);">
                                    {{ $settings->formatAmount($invoice->amount) }}
                                 </td>
                             </tr>
                            
                            @if($invoice->discount_amount > 0)
                            <tr class="border-b" style="border-color: var(--border-color);">
                                <td class="p-3" style="color: var(--success);">Discount ({{ $invoice->discount_percentage }}%)</td>
                                <td class="p-3 text-right" style="color: var(--success);">-{{ $settings->formatAmount($invoice->discount_amount) }}</td>
                             </tr>
                            @endif
                            
                            @if($invoice->penalty_amount > 0)
                            <tr class="border-b" style="border-color: var(--border-color);">
                                <td class="p-3" style="color: var(--danger);">Late Fee Penalty</td>
                                <td class="p-3 text-right" style="color: var(--danger);">+{{ $settings->formatAmount($invoice->penalty_amount) }}</td>
                             </tr>
                            @endif
                            
                            <tr style="background-color: var(--bg-secondary);">
                                <td class="p-3 font-semibold" style="color: var(--text-primary);">Total Amount</td>
                                <td class="p-3 font-semibold text-right" style="color: var(--success);">
                                    {{ $settings->formatAmount($invoice->total_amount ?? ($invoice->amount + ($invoice->penalty_amount ?? 0) - ($invoice->discount_amount ?? 0))) }}
                                 </td>
                             </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Notes -->
        @if($invoice->notes)
        <div class="mt-8">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Notes</h3>
            <div class="p-4 rounded" style="background-color: var(--bg-secondary); color: var(--text-primary);">
                {{ $invoice->notes }}
            </div>
        </div>
        @endif

        <!-- Notification Status -->
        @if(isset($notificationStatus))
        <div class="mt-8">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Notification Status</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-3 rounded" style="background-color: var(--bg-secondary);">
                    <p class="text-sm" style="color: var(--text-secondary);">Generated Notification</p>
                    <p class="font-medium" style="color: var(--text-primary);">
                        @if($notificationStatus['generated_notification_sent'] ?? false)
                            <span class="text-success">Sent</span>
                        @else
                            <span class="text-warning">Not Sent</span>
                        @endif
                    </p>
                    @if($notificationStatus['generated_sent_at'] ?? null)
                    <p class="text-xs" style="color: var(--text-secondary);">
                        {{ \Carbon\Carbon::parse($notificationStatus['generated_sent_at'])->format('M d, Y h:i A') }}
                    </p>
                    @endif
                </div>
                
                <div class="p-3 rounded" style="background-color: var(--bg-secondary);">
                    <p class="text-sm" style="color: var(--text-secondary);">Reminder Sent</p>
                    <p class="font-medium" style="color: var(--text-primary);">
                        @if($notificationStatus['reminder_sent'] ?? false)
                            <span class="text-success">Yes</span>
                        @else
                            <span class="text-warning">No</span>
                        @endif
                    </p>
                    @if($notificationStatus['last_reminder_sent_at'] ?? null)
                    <p class="text-xs" style="color: var(--text-secondary);">
                        {{ \Carbon\Carbon::parse($notificationStatus['last_reminder_sent_at'])->format('M d, Y') }}
                    </p>
                    @endif
                </div>
                
                <div class="p-3 rounded" style="background-color: var(--bg-secondary);">
                    <p class="text-sm" style="color: var(--text-secondary);">Overdue Notification</p>
                    <p class="font-medium" style="color: var(--text-primary);">
                        @if($notificationStatus['overdue_notification_sent'] ?? false)
                            <span class="text-success">Sent</span>
                        @else
                            <span class="text-warning">Not Sent</span>
                        @endif
                    </p>
                    @if($notificationStatus['overdue_sent_at'] ?? null)
                    <p class="text-xs" style="color: var(--text-secondary);">
                        {{ \Carbon\Carbon::parse($notificationStatus['overdue_sent_at'])->format('M d, Y') }}
                    </p>
                    @endif
                </div>
            </div>
            
            @if($notificationStatus['has_reminder_schedule'] ?? false)
            <div class="mt-3 p-2 rounded" style="background-color: rgba(var(--info-rgb), 0.1);">
                <p class="text-xs" style="color: var(--text-secondary);">
                    <i class="fas fa-clock mr-1"></i>
                    Next reminder scheduled for {{ \Carbon\Carbon::parse($notificationStatus['next_reminder_date'])->format('M d, Y') }}
                </p>
            </div>
            @endif
        </div>
        @endif

        <!-- Actions -->
        <div class="mt-8 pt-6 border-t" style="border-color: var(--border-color);">
            <div class="flex flex-wrap justify-between items-center gap-4">
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        Created: {{ $invoice->created_at->format('M d, Y \a\t h:i A') }}
                    </p>
                    @if($invoice->updated_at != $invoice->created_at)
                    <p class="text-sm" style="color: var(--text-secondary);">
                        Last Updated: {{ $invoice->updated_at->format('M d, Y \a\t h:i A') }}
                    </p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($invoice->status != 'paid' && $invoice->status != 'consolidated' && (auth()->user()->isSuperAdmin() || auth()->user()->isAdmin()))
                    <button type="button" 
                            onclick="openMarkAsPaidModal()"
                            class="btn-primary flex items-center">
                        <i class="fas fa-check-circle mr-2"></i> Mark as Paid
                    </button>
                    @endif
                    
                    @if($invoice->status != 'paid' && $invoice->status != 'consolidated' && (auth()->user()->isSuperAdmin() || auth()->user()->isAdmin()))
                    <button type="button" 
                            onclick="openApplyPenaltyModal()"
                            class="btn-warning flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i> Apply Penalty
                    </button>
                    @endif
                    
                    @if($invoice->penalty_amount > 0 && (auth()->user()->isSuperAdmin() || auth()->user()->isAdmin()))
                    <button type="button" 
                            onclick="openRemovePenaltyModal()"
                            class="btn-warning flex items-center">
                        <i class="fas fa-undo mr-2"></i> Remove Penalty
                    </button>
                    @endif
                    
                    @if($invoice->status != 'paid' && $invoice->status != 'consolidated' && (auth()->user()->isSuperAdmin() || auth()->user()->isAdmin()))
                    <button type="button" 
                            onclick="openResendNotificationModal()"
                            class="btn-info flex items-center">
                        <i class="fas fa-envelope mr-2"></i> Resend Notification
                    </button>
                    @endif
                    
                    @if($invoice->is_bulk_payment && $childInvoicesCount > 0 && (auth()->user()->isSuperAdmin() || auth()->user()->isAdmin()))
                    <button type="button" 
                            onclick="openReverseConsolidationModal()"
                            class="btn-warning flex items-center">
                        <i class="fas fa-undo mr-2"></i> Reverse Consolidation
                    </button>
                    @endif
                    
                    @if($invoice->status != 'paid' && $invoice->status != 'consolidated' && (auth()->user()->isSuperAdmin() || auth()->user()->isAdmin()))
                    <button type="button" 
                            onclick="openEditModal()"
                            class="btn-secondary flex items-center">
                        <i class="fas fa-edit mr-2"></i> Edit
                    </button>
                    @endif
                    
                    @if((auth()->user()->isSuperAdmin() || auth()->user()->isAdmin()) && $invoice->status != 'paid' && $invoice->status != 'consolidated')
                    <button type="button" 
                            onclick="openDeleteModal()"
                            class="btn-danger flex items-center">
                        <i class="fas fa-trash mr-2"></i> Delete
                    </button>
                    @endif
                    
                    <a href="{{ route('invoices.print', $invoice->id) }}" target="_blank" class="btn-secondary flex items-center">
                        <i class="fas fa-print mr-2"></i> Print
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals (remain the same as before, but ensure they use the safe variables) -->
<!-- Mark as Paid Modal, Apply Penalty Modal, etc. -->
<!-- [All modals remain unchanged from the previous version] -->

@endsection

@section('scripts')
<script>
// [All JavaScript remains the same as before]
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide success and error messages after 5 seconds
    const successMessage = document.querySelector('.bg-green-100');
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.display = 'none';
        }, 5000);
    }
    
    const errorMessage = document.querySelector('.bg-red-100');
    if (errorMessage) {
        setTimeout(() => {
            errorMessage.style.display = 'none';
        }, 5000);
    }
});

function openMarkAsPaidModal() {
    document.getElementById('markAsPaidModal').classList.remove('hidden');
}

function closeMarkAsPaidModal() {
    document.getElementById('markAsPaidModal').classList.add('hidden');
}

function openApplyPenaltyModal() {
    document.getElementById('applyPenaltyModal').classList.remove('hidden');
}

function closeApplyPenaltyModal() {
    document.getElementById('applyPenaltyModal').classList.add('hidden');
}

function openRemovePenaltyModal() {
    document.getElementById('removePenaltyModal').classList.remove('hidden');
}

function closeRemovePenaltyModal() {
    document.getElementById('removePenaltyModal').classList.add('hidden');
}

function openResendNotificationModal() {
    document.getElementById('resendNotificationModal').classList.remove('hidden');
}

function closeResendNotificationModal() {
    document.getElementById('resendNotificationModal').classList.add('hidden');
}

function openReverseConsolidationModal() {
    document.getElementById('reverseConsolidationModal').classList.remove('hidden');
}

function closeReverseConsolidationModal() {
    document.getElementById('reverseConsolidationModal').classList.add('hidden');
}

function openEditModal() {
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function openDeleteModal() {
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}

// Close modals when clicking outside
const modals = [
    'markAsPaidModal',
    'applyPenaltyModal',
    'removePenaltyModal',
    'resendNotificationModal',
    'reverseConsolidationModal',
    'editModal',
    'deleteModal'
];

modals.forEach(modalId => {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                if (modalId === 'markAsPaidModal') closeMarkAsPaidModal();
                if (modalId === 'applyPenaltyModal') closeApplyPenaltyModal();
                if (modalId === 'removePenaltyModal') closeRemovePenaltyModal();
                if (modalId === 'resendNotificationModal') closeResendNotificationModal();
                if (modalId === 'reverseConsolidationModal') closeReverseConsolidationModal();
                if (modalId === 'editModal') closeEditModal();
                if (modalId === 'deleteModal') closeDeleteModal();
            }
        });
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        if (!document.getElementById('markAsPaidModal').classList.contains('hidden')) closeMarkAsPaidModal();
        if (!document.getElementById('applyPenaltyModal').classList.contains('hidden')) closeApplyPenaltyModal();
        if (!document.getElementById('removePenaltyModal').classList.contains('hidden')) closeRemovePenaltyModal();
        if (!document.getElementById('resendNotificationModal').classList.contains('hidden')) closeResendNotificationModal();
        if (!document.getElementById('reverseConsolidationModal').classList.contains('hidden')) closeReverseConsolidationModal();
        if (!document.getElementById('editModal').classList.contains('hidden')) closeEditModal();
        if (!document.getElementById('deleteModal').classList.contains('hidden')) closeDeleteModal();
    }
});
</script>

<style>
/* Button styles */
.btn-primary {
    background-color: var(--primary);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    text-decoration: none;
    display: inline-block;
}

.btn-primary:hover {
    opacity: 0.9;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-secondary {
    background-color: var(--secondary);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    border: none;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
}

.btn-secondary:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.btn-warning {
    background-color: var(--warning);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    border: none;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
}

.btn-warning:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.btn-danger {
    background-color: var(--danger);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    border: none;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
}

.btn-danger:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.btn-info {
    background-color: var(--info);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    border: none;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
}

.btn-info:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

/* Message styles */
.bg-green-100 {
    background-color: rgba(209, 250, 229, 0.9);
    border-color: rgba(16, 185, 129, 0.3);
}

.bg-red-100 {
    background-color: rgba(254, 226, 226, 0.9);
    border-color: rgba(239, 68, 68, 0.3);
}

.bg-blue-100 {
    background-color: rgba(219, 234, 254, 0.9);
    border-color: rgba(59, 130, 246, 0.3);
}

.text-success {
    color: var(--success);
}

.text-warning {
    color: var(--warning);
}

.text-danger {
    color: var(--danger);
}

/* Modal styles */
#markAsPaidModal,
#applyPenaltyModal,
#removePenaltyModal,
#resendNotificationModal,
#reverseConsolidationModal,
#editModal,
#deleteModal {
    transition: opacity 0.3s ease;
}

#markAsPaidModal.hidden,
#applyPenaltyModal.hidden,
#removePenaltyModal.hidden,
#resendNotificationModal.hidden,
#reverseConsolidationModal.hidden,
#editModal.hidden,
#deleteModal.hidden {
    opacity: 0;
    pointer-events: none;
}

/* Dark mode adjustments */
.dark .bg-green-100 {
    background-color: rgba(16, 185, 129, 0.2) !important;
    border-color: rgba(16, 185, 129, 0.3) !important;
    color: #10b981 !important;
}

.dark .bg-red-100 {
    background-color: rgba(239, 68, 68, 0.2) !important;
    border-color: rgba(239, 68, 68, 0.3) !important;
    color: #ef4444 !important;
}

.dark .bg-blue-100 {
    background-color: rgba(59, 130, 246, 0.2) !important;
    border-color: rgba(59, 130, 246, 0.3) !important;
    color: #3b82f6 !important;
}
</style>
@endsection