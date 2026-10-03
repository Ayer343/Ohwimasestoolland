@extends('layouts.app')

@section('title', 'Community Development Invoices')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div class="flex items-center mb-4 md:mb-0">
                <i class="fas fa-file-invoice text-2xl mr-3" style="color: var(--primary);"></i>
                <div>
                    <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Community Development Invoices</h2>
                    <p class="text-sm" style="color: var(--text-secondary);">Manage community development dues and payments</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button onclick="openExportModal()" class="btn-secondary flex items-center">
                    <i class="fas fa-file-pdf mr-2"></i> Export PDF
                </button>
                <a href="{{ route('admin.tenant-invoices.create') }}" class="btn-primary flex items-center">
                    <i class="fas fa-plus mr-2"></i> Create Invoice
                </a>
                <form action="{{ route('admin.tenant-invoices.generate-monthly') }}" method="POST" class="inline" id="generateMonthlyForm">
                    @csrf
                    <button type="submit" class="btn-secondary flex items-center" 
                            onclick="return confirmGenerateMonthly()">
                        <i class="fas fa-sync mr-2"></i> Generate Monthly
                    </button>
                </form>
                <a href="{{ route('admin.tenant-invoices.trash') }}" class="btn-info flex items-center">
                    <i class="fas fa-trash-restore mr-2"></i> Trash
                    @php $trashCount = \App\Models\TenantInvoice::onlyTrashed()->count(); @endphp
                    @if($trashCount > 0)
                        <span class="ml-1 px-2 py-0.5 bg-red-500 text-white rounded-full text-xs">{{ $trashCount }}</span>
                    @endif
                </a>
            </div>
        </div>
    </div>

    <!-- Bulk Actions Bar -->
    <div id="bulkActionsBar" class="card p-4 hidden" style="background-color: rgba(var(--primary-rgb), 0.1);">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span id="selectedCount" class="font-semibold" style="color: var(--primary);">0</span>
                <span style="color: var(--text-secondary);">invoices selected</span>
            </div>
            <div class="flex flex-wrap gap-2">
                <button onclick="bulkExportSelectedPDF()" class="btn-secondary text-sm py-2">
                    <i class="fas fa-file-pdf mr-2"></i> Export Selected PDF
                </button>
                <button onclick="bulkPrintSelected()" class="btn-info text-sm py-2">
                    <i class="fas fa-print mr-2"></i> Print Selected
                </button>
                <button onclick="clearSelection()" class="btn-secondary text-sm py-2">
                    <i class="fas fa-times mr-2"></i> Clear
                </button>
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

    <!-- Warning Message -->
    @if(session('warning'))
    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Warning!</strong>
        <span class="block sm:inline">{{ session('warning') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Info Message for Generation Details -->
    @if(session('generation_details'))
    <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Generation Summary:</strong>
        <span class="block sm:inline">
            @php $details = session('generation_details'); @endphp
            @if(isset($details['generated_count']))
                Generated: {{ $details['generated_count'] }} invoices
            @endif
            @if(isset($details['skipped_count']))
                , Skipped: {{ $details['skipped_count'] }}
            @endif
            @if(isset($details['total_amount']))
                , Total: {{ $system_settings->formatAmount($details['total_amount']) }}
            @endif
            @if(isset($details['errors_count']) && $details['errors_count'] > 0)
                , Errors: {{ $details['errors_count'] }}
            @endif
        </span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
        <div class="card p-4" style="background-color: rgba(var(--primary-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Invoices</div>
                    <div class="text-2xl font-semibold" style="color: var(--text-primary);">{{ $statistics['total_invoices'] ?? 0 }}</div>
                </div>
                <i class="fas fa-file-invoice text-2xl opacity-70" style="color: var(--primary);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--success-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Paid</div>
                    <div class="text-2xl font-semibold" style="color: var(--success);">{{ $statistics['paid_invoices'] ?? 0 }}</div>
                </div>
                <i class="fas fa-check-circle text-2xl opacity-70" style="color: var(--success);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--warning-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Pending</div>
                    <div class="text-2xl font-semibold" style="color: var(--warning);">{{ $statistics['pending_invoices'] ?? 0 }}</div>
                </div>
                <i class="fas fa-clock text-2xl opacity-70" style="color: var(--warning);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--danger-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Overdue</div>
                    <div class="text-2xl font-semibold" style="color: var(--danger);">{{ $statistics['overdue_invoices'] ?? 0 }}</div>
                </div>
                <i class="fas fa-exclamation-triangle text-2xl opacity-70" style="color: var(--danger);"></i>
            </div>
        </div>

        <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Dues</div>
                    <div class="text-2xl font-semibold" style="color: var(--info);">{{ $system_settings->formatAmount($statistics['total_amount'] ?? 0) }}</div>
                </div>
                <i class="fas fa-money-bill-wave text-2xl opacity-70" style="color: var(--info);"></i>
            </div>
        </div>

        <div class="card p-4" style="background-color: rgba(var(--purple-rgb, 128, 0, 128), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Collected</div>
                    <div class="text-2xl font-semibold" style="color: #8b5cf6;">{{ $system_settings->formatAmount($statistics['total_paid'] ?? 0) }}</div>
                </div>
                <i class="fas fa-hand-holding-usd text-2xl opacity-70" style="color: #8b5cf6;"></i>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card p-6">
        <form method="GET" action="{{ route('admin.tenant-invoices.index') }}" id="filterForm">
            <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div>
                    <select name="tenant_id" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Tenants</option>
                        @foreach($tenants ?? [] as $tenant)
                            <option value="{{ $tenant->id }}" {{ request('tenant_id') == $tenant->id ? 'selected' : '' }}>
                                {{ $tenant->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <select name="property_unit_id" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Units</option>
                        @foreach($propertyUnits ?? [] as $unit)
                            <option value="{{ $unit['id'] }}" {{ request('property_unit_id') == $unit['id'] ? 'selected' : '' }}>
                                {{ $unit['display_name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <select name="status" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Status</option>
                        <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="overdue" {{ request('status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
                <div>
                    <input type="month" name="period" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           placeholder="Filter by period..." value="{{ request('period') }}">
                </div>
                <div>
                    <input type="date" name="date_from" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           placeholder="From date" value="{{ request('date_from') }}">
                </div>
                <div class="flex space-x-2">
                    <button type="submit" class="w-full p-2 border rounded flex items-center justify-center" style="background-color: var(--primary); color: white; border-color: var(--border-color);">
                        <i class="fas fa-filter mr-2"></i> Filter
                    </button>
                    <a href="{{ route('admin.tenant-invoices.index') }}" class="w-full p-2 border rounded flex items-center justify-center" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <i class="fas fa-sync mr-2"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Generation Progress Modal -->
    <div id="generationProgressModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="card p-8 text-center max-w-md w-full">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
            <p class="text-lg font-semibold" style="color: var(--text-primary);">Generating Monthly Invoices...</p>
            <p id="generationStatus" class="text-sm mt-2" style="color: var(--text-secondary);">Please wait while we generate invoices for all tenants.</p>
            <div class="mt-4 w-full bg-gray-200 rounded-full h-2">
                <div id="generationProgress" class="bg-primary h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
            </div>
            <button onclick="hideGenerationModal()" class="mt-4 text-sm text-gray-500 hover:text-gray-700">Cancel</button>
        </div>
    </div>

    <!-- Active Invoices Table Card -->
    <div class="card p-6">
        <!-- Results Count and Summary -->
        <div class="mb-4 flex flex-wrap justify-between items-center gap-4">
            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2">
                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()" class="rounded" style="accent-color: var(--primary);">
                    <span class="text-sm" style="color: var(--text-secondary);">Select All</span>
                </label>
                <p class="text-sm" style="color: var(--text-secondary);">
                    Showing <span class="font-medium">{{ $invoices->firstItem() ?? 0 }}</span> to <span class="font-medium">{{ $invoices->lastItem() ?? 0 }}</span> of <span class="font-medium">{{ $invoices->total() }}</span> results
                </p>
            </div>
            
            <div class="flex items-center flex-wrap gap-2">
                <span class="text-sm mr-2" style="color: var(--text-secondary);">Summary:</span>
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                    Total: {{ $system_settings->formatAmount($invoices->sum('total_amount')) }}
                </span>
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                    Paid: {{ $system_settings->formatAmount($invoices->sum('paid_amount')) }}
                </span>
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                    Balance: {{ $system_settings->formatAmount($invoices->sum('balance')) }}
                </span>
            </div>
            
            @if(request()->hasAny(['tenant_id', 'property_unit_id', 'status', 'period', 'date_from']))
            <div class="flex items-center flex-wrap gap-2 mt-2 md:mt-0">
                <span class="text-sm mr-2" style="color: var(--text-secondary);">Active filters:</span>
                @if(request('tenant_id') && isset($tenants))
                    @php $selectedTenant = $tenants->firstWhere('id', request('tenant_id')); @endphp
                    <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                        <i class="fas fa-user mr-1"></i> {{ $selectedTenant ? $selectedTenant->name : 'N/A' }}
                    </span>
                @endif
                @if(request('property_unit_id') && isset($propertyUnits))
                    @php $selectedUnit = collect($propertyUnits)->firstWhere('id', request('property_unit_id')); @endphp
                    <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                        <i class="fas fa-home mr-1"></i> {{ $selectedUnit['display_name'] ?? 'N/A' }}
                    </span>
                @endif
                @if(request('status'))
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                    <i class="fas fa-tag mr-1"></i> {{ ucfirst(request('status')) }}
                </span>
                @endif
                @if(request('period'))
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                    <i class="fas fa-calendar mr-1"></i> {{ \Carbon\Carbon::parse(request('period') . '-01')->format('M Y') }}
                </span>
                @endif
            </div>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color);">
                        <th class="text-left p-3 w-10">
                            <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()" class="rounded" style="accent-color: var(--primary);">
                        </th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Invoice #</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Tenant</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Property / Unit</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Period</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Due Date</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Amount</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Payment</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                    <tr class="border-b" style="border-color: var(--border-color);">
                        <td class="p-3">
                            <input type="checkbox" class="invoice-checkbox rounded" value="{{ $invoice->id }}" onchange="updateSelectionCount()" style="accent-color: var(--primary);">
                        </td>
                        <td class="p-3">
                            <span class="font-medium" style="color: var(--text-primary);">
                                {{ $invoice->invoice_number }}
                            </span>
                        </td>
                        <td class="p-3">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center mr-2">
                                    <span class="text-sm font-medium">{{ substr($invoice->tenant->name ?? 'U', 0, 1) }}</span>
                                </div>
                                <div>
                                    <p class="font-medium" style="color: var(--text-primary);">{{ $invoice->tenant->name ?? 'Unknown' }}</p>
                                    <p class="text-xs" style="color: var(--text-secondary);">{{ $invoice->tenant->phone ?? 'No phone' }}</p>
                                </div>
                            </div>
                        </td>
                        <!-- FIXED: Property/Unit column with null-safe operators -->
                        <td class="p-3">
                            @php
                                $propertyUnit = $invoice->propertyUnit;
                                $property = $propertyUnit ? $propertyUnit->property : null;
                                $hasValidProperty = $propertyUnit && $property;
                            @endphp
                            
                            @if($hasValidProperty)
                                <p class="font-medium" style="color: var(--text-primary);">
                                    {{ $property->property_name ?? 'N/A' }}
                                </p>
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    Unit: {{ $propertyUnit->unit_number ?? 'N/A' }}
                                </p>
                                @if($property->zone)
                                    <p class="text-xs" style="color: var(--text-secondary);">Zone: {{ $property->zone }}</p>
                                @endif
                            @else
                                <div class="text-sm" style="color: var(--danger);">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    <span class="font-medium">Property Unit Missing</span>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Unit ID: {{ $invoice->property_unit_id ?? 'N/A' }}
                                    </p>
                                    @if($invoice->property_unit_id)
                                        <p class="text-xs mt-1" style="color: var(--warning);">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            This property unit may have been deleted or is no longer active.
                                        </p>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ \Carbon\Carbon::parse($invoice->period . '-01')->format('M Y') }}
                            </p>
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $invoice->due_date->format('M d, Y') }}
                            </p>
                            @if($invoice->isOverdue())
                            <p class="text-xs text-danger flex items-center mt-1">
                                <i class="fas fa-exclamation-circle mr-1"></i> {{ $invoice->due_date->diffForHumans() }}
                            </p>
                            @endif
                            @if($invoice->within_grace_period)
                            <p class="text-xs" style="color: var(--warning);">
                                <i class="fas fa-hourglass-half mr-1"></i> Grace: {{ $invoice->days_in_grace }}d left
                            </p>
                            @endif
                        </td>
                        <td class="p-3">
                            <div class="space-y-1">
                                <p class="font-medium" style="color: var(--text-primary);">
                                    {{ $system_settings->formatAmount($invoice->total_amount) }}
                                </p>
                                <div class="flex items-center text-xs space-x-2">
                                    <span style="color: var(--text-secondary);">CD: {{ $system_settings->formatAmount($invoice->community_dues) }}</span>
                                    @if($invoice->additional_charges > 0)
                                    <span style="color: var(--warning);">+{{ $system_settings->formatAmount($invoice->additional_charges) }}</span>
                                    @endif
                                    @if($invoice->penalty_amount > 0)
                                    <span style="color: var(--danger);">+{{ $system_settings->formatAmount($invoice->penalty_amount) }} penalty</span>
                                    @endif
                                </div>
                                @if($invoice->paid_amount > 0)
                                <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                                    <div class="bg-success h-1.5 rounded-full" style="width: 100%"></div>
                                </div>
                                <p class="text-xs text-success mt-1">
                                    <i class="fas fa-check-circle mr-1"></i> Fully paid on {{ $invoice->payment_date?->format('M d, Y') }}
                                </p>
                                @endif
                            </div>
                        </td>
                        <td class="p-3">
                            @php
                                $statusColors = [
                                    'paid' => 'success',
                                    'pending' => 'warning',
                                    'overdue' => 'danger',
                                    'cancelled' => 'secondary'
                                ];
                                $statusColor = $statusColors[$invoice->status] ?? 'secondary';
                            @endphp
                            <span class="px-2 py-1 rounded-full text-xs font-medium" 
                                  style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.2); color: var(--{{ $statusColor }});">
                                <i class="{{ $invoice->status_icon }} mr-1"></i>
                                {{ ucfirst($invoice->status) }}
                            </span>
                            @if($invoice->penalty_amount > 0)
                            <div class="mt-1">
                                <span class="text-xs" style="color: var(--danger);">
                                    <i class="fas fa-exclamation-triangle mr-1"></i> Penalty: {{ $system_settings->formatAmount($invoice->penalty_amount) }}
                                </span>
                            </div>
                            @endif
                        </td>
                        <td class="p-3">
                            @if($invoice->payment_method)
                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                                <i class="fas fa-credit-card mr-1"></i>
                                {{ ucfirst(str_replace('_', ' ', $invoice->payment_method)) }}
                            </span>
                            @if($invoice->payment_date)
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-calendar-check mr-1"></i>
                                {{ \Carbon\Carbon::parse($invoice->payment_date)->format('M d, Y') }}
                            </p>
                            @endif
                            @else
                            <span class="text-sm italic" style="color: var(--text-secondary);">Not paid</span>
                            @endif
                        </td>
                        <td class="p-3">
                            <div class="flex items-center space-x-2">
                                <a href="{{ route('admin.tenant-invoices.show', $invoice->id) }}" 
                                   class="p-2 rounded hover:opacity-80 transition-opacity" 
                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);" 
                                   title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('admin.tenant-invoices.print', $invoice->id) }}" 
                                   target="_blank"
                                   class="p-2 rounded hover:opacity-80 transition-opacity" 
                                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);" 
                                   title="Print Invoice">
                                    <i class="fas fa-print"></i>
                                </a>
                                @if($invoice->status !== 'paid' && $invoice->status !== 'cancelled')
                                <button type="button" 
                                        onclick="openMarkAsPaidModal({{ $invoice->id }}, '{{ $invoice->total_amount }}')"
                                        class="p-2 rounded hover:opacity-80 transition-opacity" 
                                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);" 
                                        title="Mark as Paid">
                                    <i class="fas fa-check"></i>
                                </button>
                                @endif
                                @if($invoice->status === 'pending')
                                <a href="{{ route('admin.tenant-invoices.edit', $invoice->id) }}" 
                                   class="p-2 rounded hover:opacity-80 transition-opacity" 
                                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);" 
                                   title="Edit Invoice">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @endif
                                @if($invoice->status === 'pending' || $invoice->status === 'cancelled')
                                <button type="button" 
                                        onclick="confirmDelete({{ $invoice->id }})"
                                        class="p-2 rounded hover:opacity-80 transition-opacity" 
                                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);" 
                                        title="Delete Invoice">
                                    <i class="fas fa-trash"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="p-8 text-center">
                            <div class="flex flex-col items-center justify-center" style="color: var(--text-secondary);">
                                <i class="fas fa-file-invoice text-5xl mb-4 opacity-30"></i>
                                <p class="text-lg font-medium mb-2">No community development invoices found</p>
                                <p class="text-sm mb-4">Try adjusting your filters or create a new invoice.</p>
                                <a href="{{ route('admin.tenant-invoices.create') }}" class="btn-primary flex items-center">
                                    <i class="fas fa-plus mr-2"></i> Create First Invoice
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($invoices->hasPages())
        <div class="flex justify-between items-center mt-6">
            <div class="text-sm" style="color: var(--text-secondary);">
                Page {{ $invoices->currentPage() }} of {{ $invoices->lastPage() }}
            </div>
            <div class="flex space-x-2">
                {{ $invoices->appends(request()->query())->links() }}
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Export PDF Modal (No white hover) -->
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
                                Select the invoices you want to export as PDF. You can choose to export the current page, all filtered invoices, or selected invoices.
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
                                <p class="text-xs" style="color: var(--text-secondary);">Export only the invoices visible on this page</p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </button>
                    
                    <button onclick="exportFilteredPDF()" class="w-full p-3 border rounded-lg flex items-center justify-between transition-colors" style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                        <div class="flex items-center">
                            <i class="fas fa-filter text-blue-500 text-xl mr-3"></i>
                            <div class="text-left">
                                <p class="font-medium">Export All Filtered Invoices</p>
                                <p class="text-xs" style="color: var(--text-secondary);">Export all invoices matching current filters ({{ $invoices->total() }} total)</p>
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
                    
                    <button onclick="bulkPrintFromModal()" class="w-full p-3 border rounded-lg flex items-center justify-between transition-colors" style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                        <div class="flex items-center">
                            <i class="fas fa-print text-purple-500 text-xl mr-3"></i>
                            <div class="text-left">
                                <p class="font-medium">Print Selected Invoices</p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    Print <span id="modalPrintSelectedCount">0</span> selected invoice(s)
                                </p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </button>
                </div>
                
                <div class="border-t pt-4 mt-4" style="border-color: var(--border-color);">
                    <div class="flex items-center justify-between text-sm">
                        <span style="color: var(--text-secondary);">Total invoices available:</span>
                        <span class="font-semibold" style="color: var(--text-primary);">{{ $invoices->total() }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm mt-2">
                        <span style="color: var(--text-secondary);">Currently selected:</span>
                        <span id="modalSelectedTotal" class="font-semibold" style="color: var(--primary);">0</span>
                    </div>
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

<!-- PDF Export Loading Modal -->
<div id="pdfLoadingModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card p-8 text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
        <p class="text-lg font-semibold" style="color: var(--text-primary);">Generating PDF...</p>
        <p class="text-sm mt-2" style="color: var(--text-secondary);">Please wait while we prepare your document</p>
    </div>
</div>

<!-- Mark as Paid Modal -->
<div id="markAsPaidModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Mark Invoice as Paid</h3>
                <button type="button" onclick="closeMarkAsPaidModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="markAsPaidForm" method="POST">
                @csrf
                <div class="space-y-4">
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-yellow-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    <strong>Note:</strong> Only full payments are accepted. The payment amount must equal the invoice total.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Invoice Total</label>
                        <div class="w-full p-2 border rounded bg-gray-100" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <span id="invoiceTotalDisplay" class="font-bold"></span>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Payment Method</label>
                        <select name="payment_method" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" required>
                            <option value="">Select Payment Method</option>
                            <option value="mtn_momo">MTN Mobile Money</option>
                            <option value="telecel_cash">Telecel Cash</option>
                            <option value="airteltigo_cash">AirtelTigo Cash</option>
                            <option value="paystack">Paystack</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cash">Cash</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Payment Reference</label>
                        <input type="text" name="payment_reference" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                               placeholder="Transaction ID or reference">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Payment Date</label>
                        <input type="date" name="payment_date" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                               value="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Amount Paid</label>
                        <input type="number" name="amount_paid" id="amountPaid" step="0.01" min="0.01" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                               required>
                        <p class="text-xs text-danger mt-1 hidden" id="amountError">Payment must be at least the full invoice amount</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Notes</label>
                        <textarea name="notes" rows="2" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                                  placeholder="Optional notes about payment"></textarea>
                    </div>
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="send_confirmation" value="1" class="mr-2" checked>
                            <span class="text-sm" style="color: var(--text-secondary);">Send payment confirmation to tenant</span>
                        </label>
                    </div>
                </div>
                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" onclick="closeMarkAsPaidModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        Cancel
                    </button>
                    <button type="submit" id="submitPayment" class="px-4 py-2 rounded text-white" style="background-color: var(--success);">
                        <i class="fas fa-check mr-2"></i> Mark as Paid
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <div class="flex items-center mb-4 text-danger">
                <i class="fas fa-exclamation-triangle text-3xl mr-3"></i>
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Confirm Delete</h3>
            </div>
            <p class="mb-4" style="color: var(--text-secondary);">Are you sure you want to delete this invoice? This action cannot be undone.</p>
            
            <div class="mb-4">
                <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Reason for deletion (optional)</label>
                <textarea name="reason" id="deleteReason" rows="2" class="w-full p-2 border rounded" 
                          style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                          placeholder="Please provide a reason for deleting this invoice..."></textarea>
            </div>
            
            <form id="deleteForm" method="POST">
                @csrf
                @method('DELETE')
                <input type="hidden" name="reason" id="deleteReasonInput">
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded text-white" style="background-color: var(--danger);">
                        <i class="fas fa-trash mr-2"></i> Delete Invoice
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden form for bulk PDF export -->
<form id="bulkExportForm" method="POST" action="{{ route('admin.tenant-invoices.bulk-export') }}" target="_blank">
    @csrf
    <input type="hidden" name="invoice_ids" id="bulkInvoiceIds">
    <input type="hidden" name="format" value="pdf">
</form>

<!-- Hidden form for current page export -->
<form id="currentPageExportForm" method="GET" action="{{ route('admin.tenant-invoices.export-current-page') }}" target="_blank">
    @foreach(request()->all() as $key => $value)
        @if($key != '_token' && $key != 'page')
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach
</form>

<!-- Hidden form for all filtered export -->
<form id="allFilteredExportForm" method="GET" action="{{ route('admin.tenant-invoices.export-all-filtered') }}" target="_blank">
    @foreach(request()->all() as $key => $value)
        @if($key != '_token' && $key != 'page')
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach
</form>

@endsection

@section('scripts')
<script>
let selectedInvoices = new Set();
let generationFormSubmitted = false;

document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide messages after 5 seconds
    const messages = document.querySelectorAll('.bg-green-100, .bg-red-100, .bg-yellow-100, .bg-blue-100');
    messages.forEach(message => {
        setTimeout(() => {
            message.style.transition = 'opacity 0.5s';
            message.style.opacity = '0';
            setTimeout(() => {
                message.style.display = 'none';
            }, 500);
        }, 5000);
    });
    
    // Update modal selection count periodically
    setInterval(updateModalSelectionCount, 500);
    
    // Initialize selection count
    updateSelectionCount();
    
    // Handle generation form submission with AJAX if needed
    const generateForm = document.getElementById('generateMonthlyForm');
    if (generateForm) {
        generateForm.addEventListener('submit', function(e) {
            if (!generationFormSubmitted) {
                e.preventDefault();
                confirmAndSubmitGeneration();
            }
        });
    }
});

// ==================== GENERATION FUNCTIONS (FIXED) ====================

function confirmGenerateMonthly() {
    return confirm('Generate monthly invoices for all tenants? This will create invoices for the current month if they don\'t already exist.\n\nThis may take a few moments.');
}

function confirmAndSubmitGeneration() {
    if (confirm('Generate monthly invoices for all tenants? This will create invoices for the current month if they don\'t already exist.\n\nThis may take a few moments.')) {
        showGenerationModal();
        generationFormSubmitted = true;
        document.getElementById('generateMonthlyForm').submit();
    }
    return false;
}

function showGenerationModal() {
    const modal = document.getElementById('generationProgressModal');
    const progressBar = document.getElementById('generationProgress');
    const statusText = document.getElementById('generationStatus');
    
    if (modal) {
        modal.classList.remove('hidden');
        progressBar.style.width = '30%';
        statusText.textContent = 'Starting generation process...';
        
        // Simulate progress (actual progress will be shown after completion)
        let progress = 30;
        const interval = setInterval(() => {
            if (progress < 90) {
                progress += 10;
                progressBar.style.width = progress + '%';
                if (progress === 50) statusText.textContent = 'Processing tenant invoices...';
                if (progress === 70) statusText.textContent = 'Calculating amounts...';
                if (progress === 80) statusText.textContent = 'Creating invoice records...';
            }
        }, 800);
        
        // Store interval to clear later
        window.generationInterval = interval;
    }
}

function hideGenerationModal() {
    const modal = document.getElementById('generationProgressModal');
    if (modal) {
        modal.classList.add('hidden');
        if (window.generationInterval) {
            clearInterval(window.generationInterval);
            window.generationInterval = null;
        }
    }
}

// ==================== SELECT ALL FIX - SYNC BOTH CHECKBOXES ====================

function toggleSelectAll() {
    const selectAllHeader = document.getElementById('selectAllCheckbox');
    const selectAllFooter = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.invoice-checkbox');
    
    // Determine which checkbox triggered this (use the header checkbox state as source of truth)
    const isChecked = selectAllHeader ? selectAllHeader.checked : (selectAllFooter ? selectAllFooter.checked : false);
    
    // Sync both select all checkboxes
    if (selectAllHeader) selectAllHeader.checked = isChecked;
    if (selectAllFooter) selectAllFooter.checked = isChecked;
    
    // Update all invoice checkboxes
    checkboxes.forEach(checkbox => {
        checkbox.checked = isChecked;
        const invoiceId = parseInt(checkbox.value);
        if (isChecked) {
            selectedInvoices.add(invoiceId);
        } else {
            selectedInvoices.delete(invoiceId);
        }
    });
    
    updateSelectionCount();
}

function updateSelectionCount() {
    const checkboxes = document.querySelectorAll('.invoice-checkbox');
    selectedInvoices.clear();
    
    checkboxes.forEach(checkbox => {
        if (checkbox.checked) {
            selectedInvoices.add(parseInt(checkbox.value));
        }
    });
    
    const count = selectedInvoices.size;
    const selectedCountSpan = document.getElementById('selectedCount');
    if (selectedCountSpan) selectedCountSpan.textContent = count;
    
    const bulkBar = document.getElementById('bulkActionsBar');
    if (bulkBar) {
        if (count > 0) {
            bulkBar.classList.remove('hidden');
        } else {
            bulkBar.classList.add('hidden');
        }
    }
    
    // Update select all checkboxes state
    const selectAllHeader = document.getElementById('selectAllCheckbox');
    const selectAllFooter = document.getElementById('selectAll');
    const totalCheckboxes = checkboxes.length;
    const checkedCheckboxes = document.querySelectorAll('.invoice-checkbox:checked').length;
    
    if (totalCheckboxes > 0) {
        const isAllChecked = (checkedCheckboxes === totalCheckboxes);
        const isIndeterminate = (checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes);
        
        if (selectAllHeader) {
            selectAllHeader.checked = isAllChecked;
            selectAllHeader.indeterminate = isIndeterminate;
        }
        if (selectAllFooter) {
            selectAllFooter.checked = isAllChecked;
            selectAllFooter.indeterminate = isIndeterminate;
        }
    }
    
    // Update modal selection count
    updateModalSelectionCount();
}

function clearSelection() {
    const checkboxes = document.querySelectorAll('.invoice-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    selectedInvoices.clear();
    updateSelectionCount();
    
    const selectAllHeader = document.getElementById('selectAllCheckbox');
    const selectAllFooter = document.getElementById('selectAll');
    if (selectAllHeader) selectAllHeader.checked = false;
    if (selectAllFooter) selectAllFooter.checked = false;
    if (selectAllHeader) selectAllHeader.indeterminate = false;
    if (selectAllFooter) selectAllFooter.indeterminate = false;
}

// ==================== EXPORT MODAL FUNCTIONS ====================

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
    const modalPrintSelectedCount = document.getElementById('modalPrintSelectedCount');
    const modalSelectedTotal = document.getElementById('modalSelectedTotal');
    
    if (modalSelectedCount) modalSelectedCount.textContent = count;
    if (modalPrintSelectedCount) modalPrintSelectedCount.textContent = count;
    if (modalSelectedTotal) modalSelectedTotal.textContent = count;
}

function openBulkExportFromModal() {
    closeExportModal();
    if (selectedInvoices.size === 0) {
        alert('Please select at least one invoice to export.');
        return;
    }
    bulkExportSelectedPDF();
}

function bulkPrintFromModal() {
    closeExportModal();
    if (selectedInvoices.size === 0) {
        alert('Please select at least one invoice to print.');
        return;
    }
    bulkPrintSelected();
}

// ==================== EXPORT FUNCTIONS ====================

function exportCurrentPagePDF() {
    closeExportModal();
    showLoadingModal();
    
    const form = document.getElementById('currentPageExportForm');
    if (form) form.submit();
    
    setTimeout(hideLoadingModal, 2000);
}

function exportFilteredPDF() {
    closeExportModal();
    showLoadingModal();
    
    const form = document.getElementById('allFilteredExportForm');
    if (form) form.submit();
    
    setTimeout(hideLoadingModal, 2000);
}

function exportSinglePDF(invoiceId) {
    showLoadingModal();
    window.open(`/admin/tenant-invoices/${invoiceId}/export-pdf`, '_blank');
    setTimeout(hideLoadingModal, 2000);
}

function bulkExportSelectedPDF() {
    if (selectedInvoices.size === 0) {
        alert('Please select at least one invoice to export.');
        return;
    }
    
    showLoadingModal();
    
    const form = document.getElementById('bulkExportForm');
    const bulkInvoiceIds = document.getElementById('bulkInvoiceIds');
    if (form && bulkInvoiceIds) {
        bulkInvoiceIds.value = Array.from(selectedInvoices).join(',');
        form.submit();
    }
    
    setTimeout(hideLoadingModal, 3000);
}

function bulkPrintSelected() {
    if (selectedInvoices.size === 0) {
        alert('Please select at least one invoice to print.');
        return;
    }
    
    const invoiceIds = Array.from(selectedInvoices).join(',');
    const printWindow = window.open(`/admin/tenant-invoices/bulk-print?ids=${invoiceIds}`, '_blank');
    if (!printWindow) {
        alert('Please allow pop-ups to print invoices.');
    }
}

function showLoadingModal() {
    const modal = document.getElementById('pdfLoadingModal');
    if (modal) modal.classList.remove('hidden');
}

function hideLoadingModal() {
    const modal = document.getElementById('pdfLoadingModal');
    if (modal) modal.classList.add('hidden');
}

// ==================== PAYMENT FUNCTIONS ====================

let currentInvoiceTotal = 0;

function openMarkAsPaidModal(invoiceId, totalAmount) {
    const form = document.getElementById('markAsPaidForm');
    if (form) form.action = `/admin/tenant-invoices/${invoiceId}/mark-paid`;
    
    currentInvoiceTotal = parseFloat(totalAmount);
    
    const amountField = document.getElementById('amountPaid');
    if (amountField) {
        amountField.value = totalAmount;
        amountField.min = totalAmount;
        amountField.max = totalAmount;
    }
    
    const invoiceTotalDisplay = document.getElementById('invoiceTotalDisplay');
    if (invoiceTotalDisplay) {
        invoiceTotalDisplay.textContent = '₵' + parseFloat(totalAmount).toFixed(2);
    }
    
    const modal = document.getElementById('markAsPaidModal');
    if (modal) modal.classList.remove('hidden');
}

function closeMarkAsPaidModal() {
    const modal = document.getElementById('markAsPaidModal');
    const form = document.getElementById('markAsPaidForm');
    const amountError = document.getElementById('amountError');
    
    if (modal) modal.classList.add('hidden');
    if (form) form.reset();
    if (amountError) amountError.classList.add('hidden');
}

// ==================== DELETE FUNCTIONS ====================

function confirmDelete(invoiceId) {
    const form = document.getElementById('deleteForm');
    if (form) form.action = `/admin/tenant-invoices/${invoiceId}`;
    
    const modal = document.getElementById('deleteModal');
    if (modal) modal.classList.remove('hidden');
}

function closeDeleteModal() {
    const deleteReason = document.getElementById('deleteReason');
    if (deleteReason) deleteReason.value = '';
    
    const modal = document.getElementById('deleteModal');
    if (modal) modal.classList.add('hidden');
}

document.getElementById('deleteForm')?.addEventListener('submit', function(e) {
    const reasonTextarea = document.getElementById('deleteReason');
    const reasonInput = document.getElementById('deleteReasonInput');
    if (reasonTextarea && reasonInput) {
        reasonInput.value = reasonTextarea.value;
    }
});

// ==================== MODAL CLOSE HANDLERS ====================

// Close modals when clicking outside
document.getElementById('exportModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeExportModal();
    }
});

document.getElementById('markAsPaidModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeMarkAsPaidModal();
    }
});

document.getElementById('deleteModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});

document.getElementById('pdfLoadingModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        hideLoadingModal();
    }
});

document.getElementById('generationProgressModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        hideGenerationModal();
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const exportModal = document.getElementById('exportModal');
        if (exportModal && !exportModal.classList.contains('hidden')) {
            closeExportModal();
        }
        
        const paidModal = document.getElementById('markAsPaidModal');
        if (paidModal && !paidModal.classList.contains('hidden')) {
            closeMarkAsPaidModal();
        }
        
        const deleteModal = document.getElementById('deleteModal');
        if (deleteModal && !deleteModal.classList.contains('hidden')) {
            closeDeleteModal();
        }
        
        const loadingModal = document.getElementById('pdfLoadingModal');
        if (loadingModal && !loadingModal.classList.contains('hidden')) {
            hideLoadingModal();
        }
        
        const generationModal = document.getElementById('generationProgressModal');
        if (generationModal && !generationModal.classList.contains('hidden')) {
            hideGenerationModal();
        }
    }
});

// Validate amount paid must equal invoice total
document.getElementById('amountPaid')?.addEventListener('input', function() {
    const value = parseFloat(this.value) || 0;
    const errorEl = document.getElementById('amountError');
    const submitBtn = document.getElementById('submitPayment');
    
    const tolerance = 0.01;
    
    if (Math.abs(value - currentInvoiceTotal) > tolerance) {
        if (errorEl) errorEl.classList.remove('hidden');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.5';
            submitBtn.style.cursor = 'not-allowed';
        }
    } else {
        if (errorEl) errorEl.classList.add('hidden');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
            submitBtn.style.cursor = 'pointer';
        }
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
    cursor: pointer;
}

.btn-primary:hover {
    opacity: 0.9;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
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
    cursor: pointer;
}

.btn-secondary:hover {
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
    cursor: pointer;
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

.bg-yellow-100 {
    background-color: rgba(254, 249, 195, 0.9);
    border-color: rgba(234, 179, 8, 0.3);
}

.bg-blue-100 {
    background-color: rgba(219, 234, 254, 0.9);
    border-color: rgba(59, 130, 246, 0.3);
}

/* Modal styles */
#exportModal,
#markAsPaidModal,
#deleteModal,
#pdfLoadingModal,
#generationProgressModal {
    transition: opacity 0.3s ease;
}

#exportModal.hidden,
#markAsPaidModal.hidden,
#deleteModal.hidden,
#pdfLoadingModal.hidden,
#generationProgressModal.hidden {
    opacity: 0;
    pointer-events: none;
}

#exportModal:not(.hidden),
#markAsPaidModal:not(.hidden),
#deleteModal:not(.hidden),
#pdfLoadingModal:not(.hidden),
#generationProgressModal:not(.hidden) {
    opacity: 1;
    pointer-events: auto;
}

/* Loading animation */
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.animate-spin {
    animation: spin 1s linear infinite;
}

/* Remove white hover effect from modal buttons - only translate */
button.w-full {
    transition: transform 0.2s ease;
}

button.w-full:hover {
    transform: translateX(4px);
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

.dark .bg-yellow-100 {
    background-color: rgba(234, 179, 8, 0.2) !important;
    border-color: rgba(234, 179, 8, 0.3) !important;
    color: #eab308 !important;
}

.dark .bg-blue-100 {
    background-color: rgba(59, 130, 246, 0.2) !important;
    border-color: rgba(59, 130, 246, 0.3) !important;
    color: #60a5fa !important;
}

.dark .bg-blue-50 {
    background-color: rgba(59, 130, 246, 0.2) !important;
}

.dark .text-blue-700 {
    color: #60a5fa !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid {
        gap: 1rem;
    }
    
    table {
        font-size: 0.875rem;
    }
    
    .btn-primary,
    .btn-secondary,
    .btn-info {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }
}
</style>
@endsection