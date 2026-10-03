@extends('layouts.landlord')

@section('title', 'My Invoices')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <h2 class="text-xl font-semibold mb-4 md:mb-0" style="color: var(--text-primary);">My Invoices</h2>
            
            <!-- Export Buttons -->
            <div class="flex space-x-2">
                <button onclick="openExportModal()" class="px-4 py-2 rounded flex items-center" style="background-color: #dc2626; color: white;">
                    <i class="fas fa-file-pdf mr-2"></i> Export PDF
                </button>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div class="card p-4" style="background-color: rgba(var(--primary-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Due</div>
                    <div class="text-2xl font-semibold" style="color: var(--text-primary);">
                        {{ $settings->formatAmount($totalDue) }}
                    </div>
                </div>
                <i class="fas fa-money-bill-wave text-2xl opacity-70" style="color: var(--primary);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--warning-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Outstanding</div>
                    <div class="text-2xl font-semibold" style="color: var(--text-primary);">{{ $outstandingInvoices }}</div>
                </div>
                <i class="fas fa-clock text-2xl opacity-70" style="color: var(--warning);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Properties</div>
                    <div class="text-2xl font-semibold" style="color: var(--text-primary);">{{ $properties->count() }}</div>
                </div>
                <i class="fas fa-building text-2xl opacity-70" style="color: var(--info);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--success-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Active Coverage</div>
                    <div class="text-2xl font-semibold" style="color: var(--success);">
                        @php
                            $totalCoveredMonths = 0;
                            $activeBulkCount = 0;
                            if(isset($bulkCoverages) && is_array($bulkCoverages)) {
                                foreach($bulkCoverages as $propertyCoverages) {
                                    if(is_array($propertyCoverages)) {
                                        $activeBulkCount += count($propertyCoverages);
                                        foreach($propertyCoverages as $coverage) {
                                            $totalCoveredMonths += is_array($coverage) ? ($coverage['months_covered'] ?? count($coverage['periods'] ?? [])) : 0;
                                        }
                                    }
                                }
                            }
                        @endphp
                        {{ $totalCoveredMonths }}
                    </div>
                </div>
                <i class="fas fa-shield-alt text-2xl opacity-70" style="color: var(--success);"></i>
            </div>
            @if($activeBulkCount > 0)
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    {{ $activeBulkCount }} active bulk payment(s)
                </p>
            @endif
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--success-rgb), 0.05);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Paid Invoices</div>
                    <div class="text-2xl font-semibold" style="color: var(--success);">
                        @php
                            $regularPaidCount = $invoices->filter(function($invoice) {
                                return $invoice->status === 'paid' && 
                                       $invoice->status !== 'consolidated' && 
                                       !$invoice->bulk_payment_id &&
                                       $invoice->is_bulk_payment === false;
                            })->count();
                            
                            $bulkPaidCount = $invoices->filter(function($invoice) {
                                return $invoice->is_bulk_payment && $invoice->status === 'paid';
                            })->count();
                            
                            $totalPaidInvoices = $regularPaidCount + $bulkPaidCount;
                        @endphp
                        {{ $totalPaidInvoices }}
                    </div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        @if($bulkPaidCount > 0)
                            ({{ $regularPaidCount }} regular + {{ $bulkPaidCount }} bulk)
                        @else
                            Regular invoices only
                        @endif
                    </div>
                </div>
                <i class="fas fa-check-circle text-2xl opacity-70" style="color: var(--success);"></i>
            </div>
        </div>
    </div>

    <!-- Explanation Card for Statistics -->
    <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.05);">
        <div class="flex items-start">
            <i class="fas fa-info-circle mr-3 mt-1" style="color: var(--info);"></i>
            <div class="text-sm" style="color: var(--text-secondary);">
                <strong class="font-semibold" style="color: var(--text-primary);">Understanding Your Invoice Statistics:</strong>
                <ul class="mt-1 space-y-1">
                    <li>• <strong class="text-success">Paid Invoices</strong> - Regular invoices you've paid individually</li>
                    <li>• <strong class="text-success">Bulk Payments</strong> - Multi-month payments that cover future months</li>
                    <li>• <strong class="text-info">Consolidated Invoices</strong> - Original invoices now covered by a bulk payment (not counted separately)</li>
                    <li>• <strong class="text-primary">Active Coverage</strong> - Months already paid for via bulk payments</li>
                </ul>
                <p class="mt-2 text-xs">
                    <i class="fas fa-lightbulb mr-1"></i> 
                    When you make a bulk payment, the original invoices become "Consolidated" and are no longer counted as separate paid invoices to prevent double counting.
                </p>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Success!</strong>
        <span class="block sm:inline">{{ session('success') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Error!</strong>
        <span class="block sm:inline">{{ session('error') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Bulk Coverage Summary Cards -->
    @if(isset($bulkCoverages) && is_array($bulkCoverages) && count($bulkCoverages) > 0)
        @foreach($bulkCoverages as $propertyId => $coverages)
            @if(is_array($coverages) && count($coverages) > 0)
                @foreach($coverages as $coverage)
                    @if(is_array($coverage) && !empty($coverage))
                        @php
                            $property = $properties->firstWhere('id', $propertyId);
                            $periods = $coverage['periods'] ?? [];
                            $formattedPeriods = collect($periods)->map(function($p) {
                                return $p ? \Carbon\Carbon::parse($p . '-01')->format('M Y') : '';
                            })->filter()->values()->toArray();
                        @endphp
                        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded relative mb-4" role="alert">
                            <div class="flex items-start">
                                <i class="fas fa-shield-alt text-green-600 text-xl mr-3 mt-1"></i>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <strong class="font-bold text-green-800">✅ Active Bulk Coverage</strong>
                                            <span class="ml-2 text-sm text-green-600">
                                                {{ $property->property_name ?? ($property->street_name ?? 'Property') }}
                                            </span>
                                        </div>
                                        <span class="text-xs px-2 py-1 bg-green-200 text-green-800 rounded-full">
                                            {{ count($periods) }} months covered
                                        </span>
                                    </div>
                                    <p class="text-sm mt-2 text-green-700">
                                        <i class="fas fa-calendar-check mr-1"></i>
                                        Covered periods: 
                                        {{ implode(', ', array_slice($formattedPeriods, 0, 3)) }}
                                        @if(count($formattedPeriods) > 3)
                                            and {{ count($formattedPeriods) - 3 }} more
                                        @endif
                                    </p>
                                    <div class="flex items-center justify-between mt-2">
                                        <p class="text-xs text-green-600">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            No invoices will be generated for these months.
                                        </p>
                                        <a href="{{ route('landlord.invoices.show', $coverage['invoice_id']) }}" class="text-xs bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700 transition">
                                            <i class="fas fa-eye mr-1"></i> View Bulk Invoice
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            @endif
        @endforeach
    @endif

    <!-- Consolidated Invoices Info Banner -->
    @php
        $consolidatedCount = $invoices->where('status', 'consolidated')->count();
        $processingCount = $invoices->where('status', 'processing')->count();
    @endphp
    
    @if($consolidatedCount > 0)
    <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-start">
            <i class="fas fa-info-circle text-xl mr-3 mt-1"></i>
            <div>
                <strong class="font-bold">Consolidated Invoices</strong>
                <p class="text-sm mt-1">
                    You have {{ $consolidatedCount }} consolidated invoice(s) that are part of bulk payments. 
                    These are shown for reference but cannot be paid individually.
                </p>
            </div>
        </div>
    </div>
    @endif

    <!-- Processing Invoices Info Banner -->
    @if($processingCount > 0)
    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-start">
            <i class="fas fa-clock text-xl mr-3 mt-1"></i>
            <div>
                <strong class="font-bold">Processing Invoices</strong>
                <p class="text-sm mt-1">
                    You have {{ $processingCount }} invoice(s) currently in <span class="font-semibold">Processing</span> status. 
                    These are payments that were initiated but may have failed or been cancelled. 
                    You can retry payment using the <span class="font-semibold">Retry Payment</span> button.
                </p>
            </div>
        </div>
    </div>
    @endif

    <!-- Filters Card -->
    <div class="card p-6">
        <form method="GET" action="{{ route('landlord.invoices') }}" id="filterForm" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm mb-2" style="color: var(--text-secondary);">Property</label>
                <select name="property_id" class="w-full p-2 border rounded" style="border-color: var(--border-color); background-color: var(--bg-primary); color: var(--text-primary);">
                    <option value="">All Properties</option>
                    @foreach($properties as $property)
                        <option value="{{ $property->id }}" {{ request('property_id') == $property->id ? 'selected' : '' }}>
                            @if($property->property_name)
                                {{ $property->property_name }} - 
                            @endif
                            {{ $property->house_number ?? '#' }} {{ $property->street_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm mb-2" style="color: var(--text-secondary);">Status</label>
                <select name="status" class="w-full p-2 border rounded" style="border-color: var(--border-color); background-color: var(--bg-primary); color: var(--text-primary);">
                    <option value="">All Statuses</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="processing" {{ request('status') == 'processing' ? 'selected' : '' }}>Processing</option>
                    <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                    <option value="overdue" {{ request('status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                    <option value="consolidated" {{ request('status') == 'consolidated' ? 'selected' : '' }}>Consolidated</option>
                </select>
            </div>

            <div>
                <label class="block text-sm mb-2" style="color: var(--text-secondary);">Invoice Type</label>
                <select name="type" class="w-full p-2 border rounded" style="border-color: var(--border-color); background-color: var(--bg-primary); color: var(--text-primary);">
                    <option value="">All Types</option>
                    <option value="regular" {{ request('type') == 'regular' ? 'selected' : '' }}>Regular Monthly</option>
                    <option value="bulk" {{ request('type') == 'bulk' ? 'selected' : '' }}>Bulk Payment</option>
                </select>
            </div>

            <div>
                <label class="block text-sm mb-2" style="color: var(--text-secondary);">Coverage</label>
                <select name="coverage" class="w-full p-2 border rounded" style="border-color: var(--border-color); background-color: var(--bg-primary); color: var(--text-primary);">
                    <option value="">All Invoices</option>
                    <option value="covered" {{ request('coverage') == 'covered' ? 'selected' : '' }}>Covered by Bulk</option>
                    <option value="not_covered" {{ request('coverage') == 'not_covered' ? 'selected' : '' }}>Not Covered</option>
                </select>
            </div>

            <div class="flex items-end space-x-2">
                <button type="submit" class="px-4 py-2 rounded flex-1" style="background-color: var(--primary); color: white;">
                    <i class="fas fa-filter mr-2"></i> Filter
                </button>
                <a href="{{ route('landlord.invoices') }}" class="px-4 py-2 rounded" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                    <i class="fas fa-redo"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Payment Methods Available -->
    @if(isset($availableMethods) && count($availableMethods) > 0)
    <div class="card p-6">
        <h3 class="font-medium mb-4" style="color: var(--text-primary);">Available Payment Methods</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach($availableMethods as $method)
                <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--primary-rgb), 0.05);">
                    @if($method == 'mtn_momo')
                        <i class="fas fa-mobile-alt text-2xl mb-2" style="color: var(--primary);"></i>
                        <p class="text-sm font-medium" style="color: var(--text-primary);">MTN Mobile Money</p>
                    @elseif($method == 'telecel_cash')
                        <i class="fas fa-sim-card text-2xl mb-2" style="color: var(--info);"></i>
                        <p class="text-sm font-medium" style="color: var(--text-primary);">Telecel Cash</p>
                    @elseif($method == 'airteltigo_cash')
                        <i class="fas fa-wifi text-2xl mb-2" style="color: var(--success);"></i>
                        <p class="text-sm font-medium" style="color: var(--text-primary);">AirtelTigo Cash</p>
                    @elseif($method == 'bank_transfer')
                        <i class="fas fa-university text-2xl mb-2" style="color: var(--warning);"></i>
                        <p class="text-sm font-medium" style="color: var(--text-primary);">Bank Transfer</p>
                    @else
                        <i class="fas fa-credit-card text-2xl mb-2" style="color: var(--text-secondary);"></i>
                        <p class="text-sm font-medium" style="color: var(--text-primary);">{{ ucfirst(str_replace('_', ' ', $method)) }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Results Count and Actions -->
    <div class="card p-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
            <p class="text-sm" style="color: var(--text-secondary);">
                Showing {{ $invoices->firstItem() ?? 0 }} to {{ $invoices->lastItem() ?? 0 }} of {{ $invoices->total() }} results
                @if(request('status') != 'consolidated')
                    <span class="ml-2 text-xs">(consolidated invoices hidden by default)</span>
                @endif
                @if(request('status') == 'processing')
                    <span class="ml-2 text-xs" style="color: var(--warning);">(Showing failed/processing payments that can be retried)</span>
                @endif
            </p>
            
            <div class="flex space-x-2 mt-2 md:mt-0">
                <button onclick="loadOutstandingInvoices()" class="text-sm px-3 py-1 rounded" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
                <a href="{{ route('landlord.invoices', ['status' => 'consolidated']) }}" class="text-sm px-3 py-1 rounded" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                    <i class="fas fa-layer-group mr-1"></i> View Consolidated
                </a>
                <a href="{{ route('landlord.invoices', ['status' => 'processing']) }}" class="text-sm px-3 py-1 rounded" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                    <i class="fas fa-clock mr-1"></i> View Processing
                </a>
                <button onclick="showCoverageSummary()" class="text-sm px-3 py-1 rounded" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                    <i class="fas fa-shield-alt mr-1"></i> Coverage Summary
                </button>
            </div>
        </div>

        <!-- Invoice Selection Section -->
        @if($invoices->whereIn('status', ['pending', 'overdue', 'processing'])->where('bulk_payment_id', null)->count() > 0)
        <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.1);">
            <div id="invoiceSelectionForm">
                <div class="flex flex-col md:flex-row md:items-center justify-between">
                    <div class="flex-1">
                        <p class="font-medium mb-2" style="color: var(--text-primary);">Select Invoices to Pay</p>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            Select one or more invoices to pay at once (consolidated invoices cannot be selected)
                            @if($processingCount > 0)
                                <span class="ml-2 text-xs" style="color: var(--warning);">(Processing invoices can be retried)</span>
                            @endif
                        </p>
                        
                        <div id="selectedInvoicesSummary" class="mt-3 hidden">
                            <div class="flex items-center justify-between p-3 rounded" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.2);">
                                <div>
                                    <p class="text-sm font-medium" style="color: var(--text-primary);">
                                        <span id="selectedCount">0</span> invoice(s) selected
                                    </p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        Total amount: <span id="selectedTotal" class="font-medium">{{ $settings->formatAmount(0) }}</span>
                                    </p>
                                    <div id="bulkPaymentInfo" class="hidden mt-2">
                                        <div class="flex items-center text-xs text-green-600">
                                            <i class="fas fa-shield-alt mr-1"></i>
                                            <span>Creating a bulk payment will cover future months and prevent duplicate invoices</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <button type="button" 
                                            onclick="processSelectedInvoices('invoices')" 
                                            class="px-4 py-2 rounded flex items-center" 
                                            style="background-color: var(--primary); color: white;">
                                        <i class="fas fa-credit-card mr-2"></i> Pay Selected
                                    </button>
                                    <button type="button" 
                                            onclick="processSelectedInvoices('bulk')" 
                                            class="px-4 py-2 rounded flex items-center" 
                                            style="background-color: var(--success); color: white;">
                                        <i class="fas fa-layer-group mr-2"></i> Create Bulk Payment
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Loading Indicator -->
        <div id="loadingIndicator" class="hidden mb-4">
            <div class="flex items-center justify-center p-4">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2" style="border-color: var(--primary);"></div>
                <span class="ml-3 text-sm" style="color: var(--text-secondary);">Loading...</span>
            </div>
        </div>

        <!-- Invoices Table -->
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color);">
                        @if($invoices->whereIn('status', ['pending', 'overdue', 'processing'])->where('bulk_payment_id', null)->count() > 0)
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary); width: 40px;">
                            <input type="checkbox" id="selectAll" title="Select all payable invoices">
                        </th>
                        @endif
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Invoice No.</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Property Info</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Period</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Amount</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Due Date</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody id="invoicesTableBody">
                    @forelse($invoices as $invoice)
                    @php
                        $isCoveredByBulk = false;
                        $coveringBulkInvoice = null;
                        
                        if(isset($bulkCoverages) && is_array($bulkCoverages) && isset($bulkCoverages[$invoice->property_id])) {
                            foreach($bulkCoverages[$invoice->property_id] as $coverage) {
                                if(is_array($coverage) && isset($coverage['periods']) && in_array($invoice->period, $coverage['periods'])) {
                                    $isCoveredByBulk = true;
                                    $coveringBulkInvoice = $coverage;
                                    break;
                                }
                            }
                        }
                        
                        $isProcessing = $invoice->status === 'processing';
                        $isPayable = in_array($invoice->status, ['pending', 'overdue', 'processing']) && 
                                     !$invoice->is_bulk_payment && 
                                     !$invoice->bulk_payment_id && 
                                     !$isCoveredByBulk;
                    @endphp
                    <tr class="border-b invoice-row {{ $invoice->status == 'consolidated' ? 'opacity-75' : '' }} {{ $isCoveredByBulk ? 'bg-green-50' : '' }} {{ $isProcessing ? 'bg-yellow-50' : '' }}" 
                        style="border-color: var(--border-color); background-color: var(--bg-primary);" 
                        data-invoice-id="{{ $invoice->id }}" 
                        data-amount="{{ $invoice->total_amount }}" 
                        data-property-id="{{ $invoice->property_id }}"
                        data-status="{{ $invoice->status }}"
                        data-is-bulk="{{ $invoice->is_bulk_payment ? 'true' : 'false' }}"
                        data-has-parent="{{ $invoice->bulk_payment_id ? 'true' : 'false' }}"
                        data-covered-by-bulk="{{ $isCoveredByBulk ? 'true' : 'false' }}">
                        
                        @if($invoices->whereIn('status', ['pending', 'overdue', 'processing'])->where('bulk_payment_id', null)->count() > 0)
                        <td class="p-3">
                            @if(in_array($invoice->status, ['pending', 'overdue', 'processing']) && !$invoice->bulk_payment_id && !$invoice->is_bulk_payment && !$isCoveredByBulk)
                                <input type="checkbox" name="invoice_ids[]" value="{{ $invoice->id }}" class="invoice-checkbox" data-amount="{{ $invoice->total_amount }}">
                            @elseif($invoice->status == 'consolidated')
                                <span class="text-xs px-2 py-1 rounded" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    <i class="fas fa-link mr-1"></i> Consolidated
                                </span>
                            @elseif($invoice->bulk_payment_id)
                                <span class="text-xs px-2 py-1 rounded" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                                    <i class="fas fa-layer-group mr-1"></i> Part of Bulk
                                </span>
                            @elseif($isCoveredByBulk)
                                <span class="text-xs px-2 py-1 rounded" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                    <i class="fas fa-shield-alt mr-1"></i> Covered
                                </span>
                            @endif
                        </td>
                        @endif
                        
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                @if($invoice->is_bulk_payment)
                                    <i class="fas fa-layer-group mr-1 text-xs" style="color: var(--info);"></i>
                                @elseif($invoice->bulk_payment_id)
                                    <i class="fas fa-link mr-1 text-xs" style="color: var(--info);"></i>
                                @elseif($isCoveredByBulk)
                                    <i class="fas fa-shield-alt mr-1 text-xs" style="color: var(--success);"></i>
                                @elseif($isProcessing)
                                    <i class="fas fa-clock mr-1 text-xs" style="color: var(--warning);"></i>
                                @endif
                                {{ $invoice->invoice_number ?? 'INV-'.str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}
                            </p>
                            @if($invoice->payment_reference)
                                <p class="text-sm" style="color: var(--text-secondary);">Ref: {{ $invoice->payment_reference }}</p>
                            @endif
                            @if($isProcessing)
                                <p class="text-xs" style="color: var(--warning);">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Payment failed or cancelled - click retry
                                </p>
                            @endif
                        </td>
                        
                        <td class="p-3">
                            @if($invoice->property)
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-home text-blue-600 text-sm"></i>
                                </div>
                                <div>
                                    @if($invoice->property->property_name)
                                        <p class="font-medium text-sm" style="color: var(--text-primary);">
                                            {{ $invoice->property->property_name }}
                                        </p>
                                    @endif
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        {{ $invoice->property->house_number ?? '#' }} {{ $invoice->property->street_name }}
                                    </p>
                                </div>
                            </div>
                            @endif
                        </td>
                        
                        <td class="p-3">
                            @php
                                $periodDisplay = $invoice->period;
                                if($invoice->is_bulk_payment && $invoice->bulk_coverage_start && $invoice->bulk_coverage_end) {
                                    $periodDisplay = \Carbon\Carbon::parse($invoice->bulk_coverage_start . '-01')->format('M Y') . ' - ' . 
                                                    \Carbon\Carbon::parse($invoice->bulk_coverage_end . '-01')->format('M Y');
                                } elseif(preg_match('/^\d{4}-\d{2}$/', $invoice->period)) {
                                    $periodDisplay = \Carbon\Carbon::parse($invoice->period . '-01')->format('F Y');
                                }
                            @endphp
                            <p class="font-medium" style="color: var(--text-primary);">{{ $periodDisplay }}</p>
                            @if($invoice->is_bulk_payment)
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    {{ $invoice->childInvoices->count() ?? 0 }} invoices consolidated
                                </p>
                            @endif
                        </td>
                        
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">{{ $settings->formatAmount($invoice->total_amount) }}</p>
                            @if($invoice->penalty_amount > 0)
                                <p class="text-xs" style="color: var(--danger);">
                                    +{{ $settings->formatAmount($invoice->penalty_amount) }} penalty
                                </p>
                            @endif
                        </td>
                        
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">{{ \Carbon\Carbon::parse($invoice->due_date)->format('M d, Y') }}</p>
                            @if(!in_array($invoice->status, ['paid', 'consolidated', 'cancelled']) && !$isCoveredByBulk)
                            <p class="text-sm" style="color: var(--text-secondary);">
                                @php
                                    $daysDiff = \Carbon\Carbon::parse($invoice->due_date)->diffInDays(now(), false);
                                @endphp
                                @if($daysDiff > 0)
                                    <span style="color: var(--danger);">{{ $daysDiff }} days overdue</span>
                                @elseif($daysDiff == 0)
                                    <span style="color: var(--warning);">Due today</span>
                                @else
                                    In {{ abs($daysDiff) }} days
                                @endif
                            </p>
                            @endif
                        </td>
                        
                        <td class="p-3">
                            @php
                                $statusColors = [
                                    'paid' => ['bg' => 'success', 'icon' => 'check-circle'],
                                    'pending' => ['bg' => 'warning', 'icon' => 'clock'],
                                    'processing' => ['bg' => 'warning', 'icon' => 'spinner'],
                                    'overdue' => ['bg' => 'danger', 'icon' => 'exclamation-triangle'],
                                    'consolidated' => ['bg' => 'info', 'icon' => 'link']
                                ];
                                $statusConfig = $statusColors[$invoice->status] ?? ['bg' => 'secondary', 'icon' => 'question-circle'];
                            @endphp
                            
                            @if($isCoveredByBulk && $invoice->status != 'paid')
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs" 
                                      style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                                    <i class="fas fa-shield-alt mr-1"></i> Covered
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs" 
                                      style="background-color: rgba(var(--{{ $statusConfig['bg'] }}-rgb), 0.2); color: var(--{{ $statusConfig['bg'] }});">
                                    <i class="fas fa-{{ $statusConfig['icon'] }} mr-1"></i>
                                    {{ $invoice->status == 'consolidated' ? 'In Bulk' : ucfirst($invoice->status) }}
                                </span>
                            @endif
                        </td>
                        
                        <td class="p-3">
                            <div class="flex space-x-2">
                                <a href="{{ route('landlord.invoices.show', $invoice->id) }}" class="p-2 rounded" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <!-- Pay Now / Retry Payment - Regular Invoice -->
                                @if($isPayable)
                                <form method="GET" action="{{ route('landlord.payments.create', ['propertyId' => $invoice->property_id]) }}" class="inline pay-form" data-invoice-id="{{ $invoice->id }}">
                                    <input type="hidden" name="invoice_ids[]" value="{{ $invoice->id }}">
                                    <input type="hidden" name="property_id" value="{{ $invoice->property_id }}">
                                    <input type="hidden" name="payment_type" value="invoices">
                                    <input type="hidden" name="pre_selected" value="true">
                                    <button type="submit" class="p-2 rounded relative group" 
                                            style="background-color: {{ $isProcessing ? 'rgba(var(--warning-rgb), 0.2)' : 'rgba(var(--success-rgb), 0.1)' }}; 
                                                   color: {{ $isProcessing ? 'var(--warning)' : 'var(--success)' }};" 
                                            title="{{ $isProcessing ? 'Retry Payment (Previous attempt failed or was cancelled)' : 'Pay Now' }}">
                                        @if($isProcessing)
                                            <i class="fas fa-sync-alt"></i>
                                        @else
                                            <i class="fas fa-credit-card"></i>
                                        @endif
                                    </button>
                                </form>
                                @endif
                                
                                <!-- Pay Now - Bulk Invoice -->
                                @if($invoice->is_bulk_payment && in_array($invoice->status, ['pending', 'overdue', 'processing']))
                                <form method="GET" action="{{ route('landlord.payments.create', ['propertyId' => $invoice->property_id]) }}" class="inline pay-form" data-invoice-id="{{ $invoice->id }}">
                                    <input type="hidden" name="invoice_ids[]" value="{{ $invoice->id }}">
                                    <input type="hidden" name="property_id" value="{{ $invoice->property_id }}">
                                    <input type="hidden" name="payment_type" value="bulk">
                                    <input type="hidden" name="pre_selected" value="true">
                                    <button type="submit" class="p-2 rounded relative group" 
                                            style="background-color: {{ $isProcessing ? 'rgba(var(--warning-rgb), 0.2)' : 'rgba(var(--success-rgb), 0.1)' }}; 
                                                   color: {{ $isProcessing ? 'var(--warning)' : 'var(--success)' }};" 
                                            title="{{ $isProcessing ? 'Retry Bulk Payment (Previous attempt failed)' : 'Pay Bulk Invoice' }}">
                                        @if($isProcessing)
                                            <i class="fas fa-sync-alt"></i>
                                        @else
                                            <i class="fas fa-layer-group"></i>
                                        @endif
                                    </button>
                                </form>
                                @endif
                                
                                <button onclick="exportSinglePDF({{ $invoice->id }})" class="p-2 rounded" style="background-color: rgba(var(--danger-rgb), 0.1); color: #dc2626;" title="Export PDF">
                                    <i class="fas fa-file-pdf"></i>
                                </button>
                                
                                <a href="{{ route('landlord.invoices.print', $invoice->id) }}" target="_blank" class="p-2 rounded" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);" title="Print Invoice">
                                    <i class="fas fa-print"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $invoices->whereIn('status', ['pending', 'overdue', 'processing'])->where('bulk_payment_id', null)->count() > 0 ? 8 : 7 }}" class="p-8 text-center">
                            <div class="flex flex-col items-center justify-center" style="color: var(--text-secondary);">
                                <i class="fas fa-file-invoice text-4xl mb-4 opacity-50"></i>
                                <p class="text-lg font-medium mb-2">No invoices found</p>
                                <p class="text-sm">You don't have any invoices for your properties yet.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($invoices->hasPages())
        <div class="mt-6">
            {{ $invoices->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Export PDF Modal -->
<div id="exportModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Export Invoices as PDF</h3>
                <button type="button" onclick="closeExportModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="space-y-4">
                <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-blue-700">
                                Select the invoices you want to export as PDF.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-3">
                    <button onclick="exportCurrentPagePDF()" class="w-full p-3 border rounded-lg flex items-center justify-between transition-colors" style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                        <div class="flex items-center">
                            <i class="fas fa-file-pdf text-red-500 text-xl mr-3"></i>
                            <div class="text-left">
                                <p class="font-medium">Export Current Page</p>
                                <p class="text-xs" style="color: var(--text-secondary);">Export only the invoices visible on this page ({{ $invoices->count() }} invoices)</p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </button>
                    
                    <button onclick="exportAllInvoices()" class="w-full p-3 border rounded-lg flex items-center justify-between transition-colors" style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                        <div class="flex items-center">
                            <i class="fas fa-database text-blue-500 text-xl mr-3"></i>
                            <div class="text-left">
                                <p class="font-medium">Export All My Invoices</p>
                                <p class="text-xs" style="color: var(--text-secondary);">Export all your invoices ({{ $invoices->total() }} total)</p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </button>
                    
                    <button onclick="openBulkExportFromModal()" class="w-full p-3 border rounded-lg flex items-center justify-between transition-colors" style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                        <div class="flex items-center">
                            <i class="fas fa-check-square text-green-500 text-xl mr-3"></i>
                            <div class="text-left">
                                <p class="font-medium">Export Selected Invoices</p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    Export <span id="modalSelectedCount">0</span> selected invoice(s)
                                </p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </button>
                </div>
            </div>
            
            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="closeExportModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- PDF Loading Modal -->
<div id="pdfLoadingModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card p-8 text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
        <p class="text-lg font-semibold" style="color: var(--text-primary);">Generating PDF...</p>
        <p class="text-sm mt-2" style="color: var(--text-secondary);">Please wait while we prepare your document</p>
    </div>
</div>

<!-- Hidden forms for PDF export -->
<form id="currentPageExportForm" method="GET" action="{{ route('landlord.invoices.export-current-page') }}" target="_blank">
    @foreach(request()->all() as $key => $value)
        @if($key != '_token' && $key != 'page')
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach
</form>

<form id="allInvoicesExportForm" method="GET" action="{{ route('landlord.invoices.export-all') }}" target="_blank">
    @foreach(request()->all() as $key => $value)
        @if($key != '_token' && $key != 'page')
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach
</form>

<form id="bulkExportForm" method="POST" action="{{ route('landlord.invoices.bulk-export') }}" target="_blank">
    @csrf
    <input type="hidden" name="invoice_ids" id="bulkInvoiceIds">
</form>

<!-- No Payment Methods Modal -->
<div id="noPaymentMethodsModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md" style="background-color: var(--bg-primary); border-color: var(--border-color);">
        <div class="mt-3 text-center">
            <i class="fas fa-exclamation-triangle text-4xl mb-4" style="color: var(--warning);"></i>
            <h3 class="text-lg font-medium mb-2" style="color: var(--text-primary);">No Payment Methods Available</h3>
            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                There are no payment methods currently configured. Please contact the administrator to set up payment methods.
            </p>
            <button type="button" onclick="closeNoPaymentMethodsModal()" class="px-4 py-2 rounded" style="background-color: var(--primary); color: white;">
                OK
            </button>
        </div>
    </div>
</div>

<!-- Coverage Summary Modal -->
<div id="coverageSummaryModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-[600px] shadow-lg rounded-md" style="background-color: var(--bg-primary); border-color: var(--border-color);">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium" style="color: var(--text-primary);">Active Bulk Coverage Summary</h3>
            <button onclick="closeCoverageSummaryModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="max-h-96 overflow-y-auto">
            @if(isset($bulkCoverages) && is_array($bulkCoverages) && count($bulkCoverages) > 0)
                @foreach($bulkCoverages as $propertyId => $coverages)
                    @if(is_array($coverages) && count($coverages) > 0)
                        @php
                            $property = $properties->firstWhere('id', $propertyId);
                        @endphp
                        <div class="mb-6 last:mb-0">
                            <h4 class="font-medium mb-2" style="color: var(--text-primary);">
                                {{ $property->property_name ?? ($property->street_name ?? 'Property') }}
                            </h4>
                            
                            @foreach($coverages as $coverage)
                                @if(is_array($coverage) && !empty($coverage))
                                    @php
                                        $periods = $coverage['periods'] ?? [];
                                        $formattedPeriods = collect($periods)->map(function($p) {
                                            return $p ? \Carbon\Carbon::parse($p . '-01')->format('M Y') : '';
                                        })->filter()->values()->toArray();
                                    @endphp
                                    <div class="p-4 rounded-lg mb-3" style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-sm font-medium" style="color: var(--text-primary);">
                                                <i class="fas fa-shield-alt text-success mr-1"></i>
                                                {{ $coverage['invoice_number'] ?? 'INV-'.str_pad($coverage['invoice_id'], 6, '0', STR_PAD_LEFT) }}
                                            </span>
                                            <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                {{ count($periods) }} months
                                            </span>
                                        </div>
                                        
                                        <p class="text-sm mb-2" style="color: var(--text-secondary);">
                                            <i class="fas fa-calendar-alt mr-1"></i>
                                            Covered periods:
                                        </p>
                                        
                                        <div class="grid grid-cols-3 gap-2 mb-3">
                                            @foreach(array_slice($formattedPeriods, 0, 6) as $period)
                                                <span class="text-xs px-2 py-1 rounded text-center" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                    {{ $period }}
                                                </span>
                                            @endforeach
                                            @if(count($formattedPeriods) > 6)
                                                <span class="text-xs px-2 py-1 rounded text-center" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                                                    +{{ count($formattedPeriods) - 6 }} more
                                                </span>
                                            @endif
                                        </div>
                                        
                                        <div class="flex items-center justify-between text-xs">
                                            <span style="color: var(--text-secondary);">
                                                Paid: {{ $coverage['formatted_payment_date'] ?? ($coverage['payment_date'] ? \Carbon\Carbon::parse($coverage['payment_date'])->format('M d, Y') : 'N/A') }}
                                            </span>
                                            <a href="{{ route('landlord.invoices.show', $coverage['invoice_id']) }}" class="text-success hover:underline">
                                                View Invoice <i class="fas fa-arrow-right ml-1"></i>
                                            </a>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                @endforeach
            @else
                <div class="text-center py-8" style="color: var(--text-secondary);">
                    <i class="fas fa-shield-alt text-4xl mb-4 opacity-50"></i>
                    <p class="text-lg font-medium mb-2">No Active Coverage</p>
                    <p class="text-sm">You don't have any active bulk coverage at the moment.</p>
                </div>
            @endif
        </div>
        
        <div class="mt-6 text-right">
            <button onclick="closeCoverageSummaryModal()" class="px-4 py-2 rounded" style="background-color: var(--primary); color: white;">
                Close
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let selectedInvoices = new Set();

document.addEventListener('DOMContentLoaded', function() {
    const messages = document.querySelectorAll('.bg-green-100, .bg-red-100, .bg-blue-100, .bg-yellow-100');
    messages.forEach(message => {
        setTimeout(() => {
            message.style.display = 'none';
        }, 5000);
    });

    const coverageBanners = document.querySelectorAll('.bg-green-50');
    coverageBanners.forEach(banner => {
        setTimeout(() => {
            banner.style.display = 'none';
        }, 15000);
    });

    initializeCheckboxes();
    setInterval(updateModalSelectionCount, 500);
    setTimeout(loadOutstandingInvoices, 2000);
});

function initializeCheckboxes() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const invoiceCheckboxes = document.querySelectorAll('.invoice-checkbox');
    const selectedInvoicesSummary = document.getElementById('selectedInvoicesSummary');
    const selectedCountElement = document.getElementById('selectedCount');
    const selectedTotalElement = document.getElementById('selectedTotal');
    const bulkPaymentInfo = document.getElementById('bulkPaymentInfo');

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            invoiceCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
                const invoiceId = parseInt(checkbox.value);
                if (this.checked) {
                    selectedInvoices.add(invoiceId);
                } else {
                    selectedInvoices.delete(invoiceId);
                }
            });
            updateSelectionSummary();
        });

        invoiceCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const invoiceId = parseInt(this.value);
                if (this.checked) {
                    selectedInvoices.add(invoiceId);
                } else {
                    selectedInvoices.delete(invoiceId);
                }
                updateSelectionSummary();
            });
        });
    }

    function updateSelectionSummary() {
        const checkedCheckboxes = document.querySelectorAll('.invoice-checkbox:checked');
        const checkedCount = checkedCheckboxes.length;
        
        if (selectedCountElement) selectedCountElement.textContent = checkedCount;
        
        let totalAmount = 0;
        checkedCheckboxes.forEach(checkbox => {
            const amount = parseFloat(checkbox.dataset.amount) || 0;
            totalAmount += amount;
        });
        
        if (selectedTotalElement) selectedTotalElement.textContent = formatAmount(totalAmount);
        
        if (selectedInvoicesSummary) {
            if (checkedCount > 0) {
                selectedInvoicesSummary.classList.remove('hidden');
                if (checkedCount > 1) {
                    bulkPaymentInfo?.classList.remove('hidden');
                } else {
                    bulkPaymentInfo?.classList.add('hidden');
                }
            } else {
                selectedInvoicesSummary.classList.add('hidden');
                bulkPaymentInfo?.classList.add('hidden');
            }
        }
        
        updateModalSelectionCount();
    }
    
    updateSelectionSummary();
}

function formatAmount(amount) {
    const currencySymbol = '{{ $settings->currency_symbol ?? "₵" }}';
    const decimalPlaces = {{ $settings->decimal_places ?? 2 }};
    const formattedAmount = parseFloat(amount).toLocaleString('en-US', {
        minimumFractionDigits: decimalPlaces,
        maximumFractionDigits: decimalPlaces
    });
    return currencySymbol + formattedAmount;
}

function getSelectedIds() {
    return Array.from(document.querySelectorAll('.invoice-checkbox:checked')).map(cb => cb.value);
}

function openExportModal() {
    updateModalSelectionCount();
    document.getElementById('exportModal').classList.remove('hidden');
}

function closeExportModal() {
    document.getElementById('exportModal').classList.add('hidden');
}

function updateModalSelectionCount() {
    const count = selectedInvoices.size;
    const modalSelectedCount = document.getElementById('modalSelectedCount');
    if (modalSelectedCount) modalSelectedCount.textContent = count;
}

function openBulkExportFromModal() {
    closeExportModal();
    if (selectedInvoices.size === 0) {
        alert('Please select at least one invoice to export.');
        return;
    }
    bulkExport();
}

function showLoadingModal() {
    const modal = document.getElementById('pdfLoadingModal');
    if (modal) modal.classList.remove('hidden');
}

function hideLoadingModal() {
    const modal = document.getElementById('pdfLoadingModal');
    if (modal) modal.classList.add('hidden');
}

function exportCurrentPagePDF() {
    closeExportModal();
    showLoadingModal();
    const form = document.getElementById('currentPageExportForm');
    if (form) form.submit();
    setTimeout(hideLoadingModal, 2000);
}

function exportAllInvoices() {
    closeExportModal();
    showLoadingModal();
    const form = document.getElementById('allInvoicesExportForm');
    if (form) form.submit();
    setTimeout(hideLoadingModal, 3000);
}

function exportSinglePDF(invoiceId) {
    showLoadingModal();
    window.open(`/landlord/invoices/${invoiceId}/export-pdf`, '_blank');
    setTimeout(hideLoadingModal, 2000);
}

function bulkExport() {
    const selectedIds = getSelectedIds();
    if (selectedIds.length === 0) {
        alert('Please select at least one invoice to export.');
        return;
    }
    showLoadingModal();
    const form = document.getElementById('bulkExportForm');
    const bulkInvoiceIds = document.getElementById('bulkInvoiceIds');
    if (form && bulkInvoiceIds) {
        bulkInvoiceIds.value = selectedIds.join(',');
        form.submit();
    }
    setTimeout(hideLoadingModal, 3000);
}

function processSelectedInvoices(paymentType = 'invoices') {
    const selectedInvoiceIds = getSelectedIds();
    if (selectedInvoiceIds.length === 0) {
        alert('Please select at least one invoice to pay.');
        return;
    }
    
    const firstSelectedRow = document.querySelector('.invoice-checkbox:checked').closest('.invoice-row');
    const propertyId = firstSelectedRow ? firstSelectedRow.dataset.propertyId : null;
    if (!propertyId) {
        alert('Unable to determine property. Please select invoices from the same property.');
        return;
    }
    
    const allSameProperty = Array.from(document.querySelectorAll('.invoice-checkbox:checked'))
        .every(checkbox => {
            const row = checkbox.closest('.invoice-row');
            return row.dataset.propertyId === propertyId;
        });
    if (!allSameProperty) {
        alert('Please select invoices from the same property only.');
        return;
    }
    
    const hasBulkChildren = Array.from(document.querySelectorAll('.invoice-checkbox:checked'))
        .some(checkbox => {
            const row = checkbox.closest('.invoice-row');
            return row.dataset.hasParent === 'true';
        });
    if (hasBulkChildren) {
        alert('Cannot select invoices that are already part of a bulk payment.');
        return;
    }

    const hasCoveredInvoices = Array.from(document.querySelectorAll('.invoice-checkbox:checked'))
        .some(checkbox => {
            const row = checkbox.closest('.invoice-row');
            return row.dataset.coveredByBulk === 'true';
        });
    if (hasCoveredInvoices) {
        alert('Cannot select invoices that are covered by an existing bulk payment.');
        return;
    }
    
    const baseUrl = '{{ route("landlord.payments.create", ["propertyId" => ":propertyId"]) }}'.replace(':propertyId', propertyId);
    const url = new URL(baseUrl, window.location.origin);
    selectedInvoiceIds.forEach(invoiceId => {
        url.searchParams.append('invoice_ids[]', invoiceId);
    });
    url.searchParams.append('payment_type', paymentType);
    url.searchParams.append('pre_selected', 'true');
    window.location.href = url.toString();
}

function showCoverageSummary() {
    document.getElementById('coverageSummaryModal').classList.remove('hidden');
}

function closeCoverageSummaryModal() {
    document.getElementById('coverageSummaryModal').classList.add('hidden');
}

function closeNoPaymentMethodsModal() {
    document.getElementById('noPaymentMethodsModal').classList.add('hidden');
}

function loadOutstandingInvoices() {
    const loadingIndicator = document.getElementById('loadingIndicator');
    if (loadingIndicator) loadingIndicator.classList.remove('hidden');
    fetch('/landlord/outstanding-invoices')
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                updateStatistics(data.data);
            }
        })
        .catch(error => console.error('Error:', error))
        .finally(() => {
            if (loadingIndicator) loadingIndicator.classList.add('hidden');
        });
}

function updateStatistics(data) {
    if (!data) return;
    const totalDueElement = document.querySelector('.card.p-4:first-child .text-2xl');
    if (totalDueElement && data.total_due !== undefined) {
        totalDueElement.textContent = formatAmount(data.total_due);
    }
    const outstandingElement = document.querySelector('.card.p-4:nth-child(2) .text-2xl');
    if (outstandingElement && data.outstanding_count !== undefined) {
        outstandingElement.textContent = data.outstanding_count;
    }
}

document.getElementById('exportModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeExportModal();
});

document.getElementById('pdfLoadingModal')?.addEventListener('click', function(e) {
    if (e.target === this) hideLoadingModal();
});

document.getElementById('coverageSummaryModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeCoverageSummaryModal();
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeExportModal();
        closeCoverageSummaryModal();
        hideLoadingModal();
    }
});
</script>

<style>
.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.fixed.inset-0 {
    backdrop-filter: blur(2px);
}

.relative.top-20.mx-auto {
    animation: modalFadeIn 0.3s ease-out;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.hidden {
    display: none !important;
}

input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

/* ✅ FIXED: Table row hover - uses theme colors */
tbody tr:hover {
    background-color: var(--bg-secondary) !important;
}

/* Dark mode hover adjustment */
.dark tbody tr:hover {
    background-color: rgba(255, 255, 255, 0.05) !important;
}

/* Card hover - uses theme colors */
.card:hover {
    background-color: var(--bg-secondary);
}

/* Dark mode adjustments */
.dark .bg-green-50 {
    background-color: rgba(16, 185, 129, 0.15) !important;
}

.dark .bg-yellow-50 {
    background-color: rgba(234, 179, 8, 0.15) !important;
}

.dark .bg-blue-100 {
    background-color: rgba(59, 130, 246, 0.2) !important;
}

.dark .bg-yellow-100 {
    background-color: rgba(234, 179, 8, 0.2) !important;
}
</style>
@endsection