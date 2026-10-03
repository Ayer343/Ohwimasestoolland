@extends('layouts.app')

@section('title', 'Trashed Invoices')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div class="flex items-center mb-4 md:mb-0">
                <i class="fas fa-trash-alt text-2xl mr-3" style="color: var(--warning);"></i>
                <div>
                    <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Trashed Invoices</h2>
                    <p class="text-sm" style="color: var(--text-secondary);">View and manage soft-deleted invoices</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('invoices.index') }}" class="btn-primary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Invoices
                </a>
                <!-- NEW: Link to Archives -->
                <a href="{{ route('invoices.archives') }}" class="btn-archive flex items-center">
                    <i class="fas fa-archive mr-2"></i> View Archives
                </a>
                @if($statistics['total_trashed'] > 0)
                    <button type="button" onclick="openEmptyTrashModal()" class="btn-danger flex items-center">
                        <i class="fas fa-trash-alt mr-2"></i> Empty Trash
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- NEW: Archive Info Card -->
    <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.1); border-left: 4px solid var(--info);">
        <div class="flex items-start justify-between">
            <div class="flex items-center">
                <i class="fas fa-archive text-2xl mr-3" style="color: var(--info);"></i>
                <div>
                    <h3 class="font-semibold" style="color: var(--text-primary);">Invoice Archiving System</h3>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Invoices deleted from the system are automatically archived for audit purposes. 
                        Permanent deletion only removes from active records but archives are retained for compliance.
                    </p>
                    <div class="mt-2 flex flex-wrap gap-4 text-sm">
                        <span style="color: var(--text-secondary);">
                            <i class="fas fa-archive mr-1"></i> Total Archived: 
                            <strong style="color: var(--info);">{{ $archiveStats['total_archives'] ?? 0 }}</strong>
                        </span>
                        <span style="color: var(--text-secondary);">
                            <i class="fas fa-calendar-alt mr-1"></i> Archived Amount: 
                            <strong style="color: var(--info);">{{ $settings->formatAmount($archiveStats['total_amount'] ?? 0) }}</strong>
                        </span>
                        <span style="color: var(--text-secondary);">
                            <i class="fas fa-clock mr-1"></i> Oldest Archive: 
                            <strong style="color: var(--info);">{{ $archiveStats['oldest_date'] ?? 'N/A' }}</strong>
                        </span>
                    </div>
                </div>
            </div>
            <a href="{{ route('invoices.archives') }}" class="px-3 py-2 rounded text-sm flex items-center" style="background-color: var(--info); color: white;">
                <i class="fas fa-archive mr-1"></i> Browse Archives
            </a>
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

    <!-- Warning: Trash Retention Info -->
    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-center">
            <i class="fas fa-info-circle mr-3 text-xl"></i>
            <div>
                <strong class="font-bold">Trash Retention Policy</strong>
                <p class="text-sm mt-1">Invoices in trash are automatically retained for {{ $settings->trash_retention_days ?? 30 }} days before they can be permanently deleted. Restore any invoices you need before they are permanently removed.</p>
                <p class="text-sm mt-1"><i class="fas fa-archive mr-1"></i> <strong>Note:</strong> Even after permanent deletion, an audit record is preserved in the Invoice Archives for legal compliance.</p>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="card p-4" style="background-color: rgba(var(--warning-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Trashed</div>
                    <div class="text-2xl font-semibold" style="color: var(--warning);" id="totalTrashed">{{ $statistics['total_trashed'] }}</div>
                </div>
                <i class="fas fa-trash-alt text-2xl opacity-70" style="color: var(--warning);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Bulk Invoices</div>
                    <div class="text-2xl font-semibold" style="color: var(--info);">{{ $statistics['bulk_trashed'] }}</div>
                </div>
                <i class="fas fa-layer-group text-2xl opacity-70" style="color: var(--info);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--secondary-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Regular Invoices</div>
                    <div class="text-2xl font-semibold" style="color: var(--text-secondary);">{{ $statistics['regular_trashed'] }}</div>
                </div>
                <i class="fas fa-file-invoice text-2xl opacity-70" style="color: var(--text-secondary);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--success-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Amount</div>
                    <div class="text-2xl font-semibold" style="color: var(--success);">{{ $settings->formatAmount($statistics['total_amount_trashed']) }}</div>
                </div>
                <i class="fas fa-money-bill-wave text-2xl opacity-70" style="color: var(--success);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--danger-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Penalties</div>
                    <div class="text-2xl font-semibold" style="color: var(--danger);">{{ $settings->formatAmount($statistics['total_penalties_trashed']) }}</div>
                </div>
                <i class="fas fa-exclamation-circle text-2xl opacity-70" style="color: var(--danger);"></i>
            </div>
        </div>
    </div>

    <!-- Additional Stats Row -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="card p-4">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Paid Invoices in Trash</div>
                    <div class="text-2xl font-semibold" style="color: var(--success);">{{ $statistics['paid_trashed'] }}</div>
                </div>
                <i class="fas fa-check-circle text-2xl opacity-70" style="color: var(--success);"></i>
            </div>
        </div>

        <div class="card p-4">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Pending Invoices</div>
                    <div class="text-2xl font-semibold" style="color: var(--warning);">{{ $statistics['pending_trashed'] }}</div>
                </div>
                <i class="fas fa-clock text-2xl opacity-70" style="color: var(--warning);"></i>
            </div>
        </div>

        <div class="card p-4">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Overdue Invoices</div>
                    <div class="text-2xl font-semibold" style="color: var(--danger);">{{ $statistics['overdue_trashed'] }}</div>
                </div>
                <i class="fas fa-exclamation-triangle text-2xl opacity-70" style="color: var(--danger);"></i>
            </div>
        </div>

        <!-- NEW: Archive Stats Card -->
        <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.05);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Archived Records</div>
                    <div class="text-2xl font-semibold" style="color: var(--info);">{{ $archiveStats['total_archives'] ?? 0 }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Audit trail preserved</div>
                </div>
                <i class="fas fa-archive text-2xl opacity-70" style="color: var(--info);"></i>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card p-6">
        <form method="GET" action="{{ route('invoices.trash') }}" id="filterForm">
            <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div>
                    <select name="property_id" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Properties</option>
                        @foreach($properties as $property)
                            <option value="{{ $property->id }}" {{ request('property_id') == $property->id ? 'selected' : '' }}>
                                {{ $property->house_number }} {{ $property->street_name }}
                                ({{ $property->landlord->name }})
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
                        <option value="partial" {{ request('status') == 'partial' ? 'selected' : '' }}>Partial</option>
                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
                <div>
                    <select name="type" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Types</option>
                        <option value="regular" {{ request('type') == 'regular' ? 'selected' : '' }}>Regular Monthly</option>
                        <option value="bulk" {{ request('type') == 'bulk' ? 'selected' : '' }}>Bulk Payment</option>
                    </select>
                </div>
                <div>
                    <input type="month" name="period" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           placeholder="Filter by period..." value="{{ request('period') }}">
                </div>
                <div>
                    <input type="text" name="search" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           placeholder="Search by invoice #, reference..." value="{{ request('search') }}">
                </div>
                <div class="flex space-x-2">
                    <button type="submit" class="w-full p-2 border rounded flex items-center justify-center" style="background-color: var(--primary); color: white; border-color: var(--border-color);">
                        <i class="fas fa-filter mr-2"></i> Filter
                    </button>
                    <a href="{{ route('invoices.trash') }}" class="w-full p-2 border rounded flex items-center justify-center" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <i class="fas fa-sync mr-2"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Bulk Operations Card -->
    <div class="card p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h3 class="font-medium mb-1" style="color: var(--text-primary);">Bulk Operations</h3>
                <p class="text-sm" style="color: var(--text-secondary);">Restore or permanently delete multiple invoices at once</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" id="bulkRestoreBtn" class="px-4 py-2 rounded" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                    <i class="fas fa-recycle mr-2"></i> Restore Selected
                </button>
                @if(auth()->user()->isSuperAdmin())
                    <button type="button" id="bulkForceDeleteBtn" class="px-4 py-2 rounded" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                        <i class="fas fa-trash-alt mr-2"></i> Permanently Delete Selected
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Invoices Table Card -->
    <div class="card p-6">
        <!-- Results Count and Selection Info -->
        <div class="mb-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div class="flex items-center gap-4">
                <p class="text-sm" style="color: var(--text-secondary);">
                    Showing <span id="showingFrom">{{ $invoices->firstItem() ?? 0 }}</span> to <span id="showingTo">{{ $invoices->lastItem() ?? 0 }}</span> of <span id="showingTotal">{{ $invoices->total() }}</span> results
                </p>
                <div class="flex items-center">
                    <input type="checkbox" id="selectAllInvoices" class="mr-2">
                    <label for="selectAllInvoices" class="text-sm" style="color: var(--text-secondary);">Select All</label>
                </div>
            </div>
            
            @if(request()->hasAny(['property_id', 'status', 'type', 'period']))
            <div class="flex items-center flex-wrap gap-2">
                <span class="text-sm mr-2" style="color: var(--text-secondary);">Active filters:</span>
                @if(request('property_id'))
                @php
                    $selectedProperty = $properties->firstWhere('id', request('property_id'));
                @endphp
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                    Property: {{ $selectedProperty ? $selectedProperty->house_number . ' ' . $selectedProperty->street_name : 'N/A' }}
                </span>
                @endif
                @if(request('status'))
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                    Status: {{ ucfirst(request('status')) }}
                </span>
                @endif
                @if(request('type'))
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                    Type: {{ ucfirst(request('type')) }}
                </span>
                @endif
            </div>
            @endif
        </div>

        <!-- Selection Actions Bar (hidden by default) -->
        <div id="selectionActions" class="hidden mb-4 p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.1);">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm font-medium" style="color: var(--text-primary);">
                        <span id="selectedCount">0</span> invoice(s) selected
                    </span>
                    <span class="text-sm ml-2" style="color: var(--text-secondary);">
                        Total: <span id="selectedTotal">{{ $settings->formatAmount(0) }}</span>
                    </span>
                </div>
                <div class="flex gap-2">
                    <button onclick="bulkRestore()" class="px-3 py-1 rounded text-sm" style="background-color: var(--success); color: white;">
                        <i class="fas fa-recycle mr-1"></i> Restore
                    </button>
                    @if(auth()->user()->isSuperAdmin())
                        <button onclick="bulkForceDelete()" class="px-3 py-1 rounded text-sm" style="background-color: var(--danger); color: white;">
                            <i class="fas fa-trash-alt mr-1"></i> Permanently Delete
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary); width: 30px;">
                            <input type="checkbox" id="selectAllCheckbox">
                        </th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Invoice #</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Property</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Period</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Due Date</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Amount</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Type</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Deleted At</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Days in Trash</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody id="invoicesTableBody">
                    @forelse($invoices as $invoice)
                    <tr class="border-b" style="border-color: var(--border-color);" 
                        data-invoice-id="{{ $invoice->id }}"
                        data-amount="{{ $invoice->total_amount }}"
                        data-status="{{ $invoice->status }}"
                        data-type="{{ $invoice->is_bulk_payment ? 'bulk' : 'regular' }}"
                        data-deleted-at="{{ $invoice->deleted_at }}">
                        
                        <td class="p-3">
                            <input type="checkbox" class="invoice-checkbox" value="{{ $invoice->id }}" 
                                   data-amount="{{ $invoice->total_amount }}"
                                   data-status="{{ $invoice->status }}">
                        </td>
                        <td class="p-3">
                            <div class="flex items-center">
                                @if($invoice->is_bulk_payment)
                                    <i class="fas fa-layer-group mr-1 text-xs" style="color: var(--info);"></i>
                                @endif
                                <span class="font-medium" style="color: var(--text-primary);">
                                    INV-{{ str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}
                                </span>
                            </div>
                            @if($invoice->payment_reference)
                                <p class="text-xs" style="color: var(--text-secondary);">Ref: {{ $invoice->payment_reference }}</p>
                            @endif
                            @if($invoice->metadata && isset($invoice->metadata['archive_id']))
                                <p class="text-xs" style="color: var(--info);">
                                    <i class="fas fa-archive mr-1"></i> Archived: #{{ $invoice->metadata['archive_id'] }}
                                </p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $invoice->property->house_number }} {{ $invoice->property->street_name }}
                            </p>
                            <div class="text-sm" style="color: var(--text-secondary);">
                                @if($invoice->property->block_number)
                                    Block: {{ $invoice->property->block_number }}
                                @endif
                                @if($invoice->property->zone)
                                    • {{ $invoice->property->zone }}
                                @endif
                            </div>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                Landlord: {{ $invoice->property->landlord->name }}
                            </p>
                        </td>
                        <td class="p-3">
                            @php
                                $periodDisplay = $invoice->period;
                                if($invoice->is_bulk_payment) {
                                    if($invoice->bulk_start_month && $invoice->bulk_end_month) {
                                        $periodDisplay = \Carbon\Carbon::parse($invoice->bulk_start_month . '-01')->format('M Y') . 
                                                       ' - ' . 
                                                       \Carbon\Carbon::parse($invoice->bulk_end_month . '-01')->format('M Y');
                                    } else {
                                        $periodDisplay = 'Bulk Payment';
                                    }
                                }
                                elseif(preg_match('/^\d{4}-\d{2}$/', $invoice->period)) {
                                    try {
                                        $periodDisplay = \Carbon\Carbon::parse($invoice->period . '-01')->format('M Y');
                                    } catch (\Exception $e) {
                                        // Keep original
                                    }
                                }
                            @endphp
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $periodDisplay }}
                            </p>
                            @if($invoice->is_bulk_payment && $invoice->bulk_months)
                            <p class="text-xs" style="color: var(--text-secondary);">
                                {{ $invoice->bulk_months }} months
                            </p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $invoice->due_date->format('M d, Y') }}
                            </p>
                            @if($invoice->status === 'overdue')
                            <p class="text-xs text-danger">Overdue by {{ $invoice->due_date->diffInDays(now()) }} days</p>
                            @endif
                        </td>
                        <td class="p-3">
                            <div>
                                <p class="font-medium" style="color: var(--success);">
                                    {{ $settings->formatAmount($invoice->amount) }}
                                </p>
                                @if($invoice->penalty_amount > 0)
                                <p class="text-xs" style="color: var(--danger);">
                                    +{{ $settings->formatAmount($invoice->penalty_amount) }} penalty
                                </p>
                                @endif
                                @if($invoice->discount_amount > 0)
                                <p class="text-xs" style="color: var(--success);">
                                    -{{ $settings->formatAmount($invoice->discount_amount) }} discount
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
                                    'partial' => 'info',
                                    'processing' => 'info',
                                    'cancelled' => 'secondary'
                                ];
                                $statusColor = $statusColors[$invoice->status] ?? 'secondary';
                            @endphp
                            <span class="px-2 py-1 rounded-full text-xs status-badge" style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.2); color: var(--{{ $statusColor }});">
                                {{ ucfirst($invoice->status) }}
                            </span>
                        </td>
                        <td class="p-3">
                            @if($invoice->is_bulk_payment)
                                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                                    <i class="fas fa-layer-group mr-1"></i> Bulk
                                </span>
                            @else
                                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--secondary-rgb), 0.2); color: var(--text-secondary);">
                                    Regular
                                </span>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $invoice->deleted_at->format('M d, Y H:i') }}
                            </p>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                By: {{ $invoice->metadata['deleted_by_name'] ?? 'System' }}
                            </p>
                        </td>
                        <td class="p-3">
                            @php
                                $daysInTrash = $invoice->deleted_at->diffInDays(now());
                                $canDelete = $daysInTrash >= ($settings->trash_retention_days ?? 30);
                            @endphp
                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: {{ $canDelete ? 'rgba(var(--danger-rgb), 0.2)' : 'rgba(var(--warning-rgb), 0.2)' }}; color: {{ $canDelete ? 'var(--danger)' : 'var(--warning)' }};">
                                {{ $daysInTrash }} days
                            </span>
                            @if(!$canDelete)
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    Needs {{ ($settings->trash_retention_days ?? 30) - $daysInTrash }} more days
                                </p>
                            @endif
                        </td>
                        <td class="p-3">
                            <div class="flex space-x-2">
                                <button type="button" 
                                        onclick="restoreInvoice({{ $invoice->id }})"
                                        class="p-2 rounded restore-btn" 
                                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);" 
                                        title="Restore Invoice">
                                    <i class="fas fa-recycle"></i>
                                </button>
                                @if(auth()->user()->isSuperAdmin())
                                    @php
                                        $canForceDelete = $daysInTrash >= ($settings->trash_retention_days ?? 30);
                                    @endphp
                                    <button type="button" 
                                            onclick="forceDeleteInvoice({{ $invoice->id }}, {{ $canForceDelete ? 'true' : 'false' }})"
                                            class="p-2 rounded force-delete-btn" 
                                            style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);" 
                                            title="Permanently Delete"
                                            {{ !$canForceDelete ? 'disabled' : '' }}>
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="11" class="p-8 text-center">
                            <div class="flex flex-col items-center justify-center" style="color: var(--text-secondary);">
                                <i class="fas fa-trash-alt text-4xl mb-4 opacity-50"></i>
                                <p class="text-lg font-medium mb-2">No trashed invoices found</p>
                                <p class="text-sm">Trash is empty. Deleted invoices will appear here.</p>
                                <a href="{{ route('invoices.archives') }}" class="mt-4 px-4 py-2 rounded text-sm" style="background-color: var(--info); color: white;">
                                    <i class="fas fa-archive mr-2"></i> View Archived Invoices
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
        <div class="flex justify-center mt-6">
            {{ $invoices->appends(request()->query())->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Restore Confirmation Modal -->
<div id="restoreModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Restore Invoice</h3>
            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                Are you sure you want to restore this invoice? It will be moved back to the active invoices list.
            </p>
            <div id="restoreWarning" class="hidden mb-4 p-3 rounded" style="background-color: rgba(var(--warning-rgb), 0.1);">
                <p class="text-sm" style="color: var(--warning);">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <span id="restoreWarningMessage"></span>
                </p>
            </div>
            <form id="restoreForm" method="POST">
                @csrf
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeRestoreModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded text-white" style="background-color: var(--success);">
                        Restore Invoice
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Force Delete Confirmation Modal -->
<div id="forceDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Permanently Delete Invoice</h3>
            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                This action cannot be undone. The invoice will be permanently removed from active records, but an audit trail will be preserved in the Invoice Archives.
            </p>
            <div id="forceDeleteWarning" class="mb-4 p-3 rounded" style="background-color: rgba(var(--danger-rgb), 0.1);">
                <p class="text-sm" style="color: var(--danger);">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <span id="forceDeleteWarningMessage"></span>
                </p>
            </div>
            <form id="forceDeleteForm" method="POST">
                @csrf
                @method('DELETE')
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Type "DELETE" to confirm
                    </label>
                    <input type="text" id="confirmDeleteText" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           placeholder="DELETE" required>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeForceDeleteModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        Cancel
                    </button>
                    <button type="submit" id="confirmForceDeleteBtn" class="px-4 py-2 rounded text-white opacity-50 cursor-not-allowed" style="background-color: var(--danger);" disabled>
                        Permanently Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Empty Trash Modal -->
<div id="emptyTrashModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Empty Trash</h3>
            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                This will permanently delete all invoices in the trash. This action cannot be undone.
            </p>
            <div class="mb-4 p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.1);">
                <p class="text-sm" style="color: var(--info);">
                    <i class="fas fa-archive mr-2"></i>
                    Note: An archive record will still be preserved in the Invoice Archives for audit purposes.
                </p>
            </div>
            <form id="emptyTrashForm" method="POST" action="{{ route('invoices.empty-trash') }}">
                @csrf
                @method('DELETE')
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            Delete invoices older than (days)
                        </label>
                        <input type="number" name="older_than_days" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                               placeholder="Leave empty to delete all" min="1">
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Optional: Only delete invoices that have been in trash longer than this many days.
                        </p>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" name="confirm" id="confirmEmptyTrash" class="mr-2" required>
                        <label for="confirmEmptyTrash" class="text-sm" style="color: var(--text-secondary);">
                            I understand that this action is irreversible
                        </label>
                    </div>
                </div>
                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" onclick="closeEmptyTrashModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded text-white" style="background-color: var(--danger);">
                        Empty Trash
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Progress Modal -->
<div id="bulkProgressModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Processing Bulk Operation</h3>
            <div class="mb-4">
                <div class="flex justify-between mb-2">
                    <span class="text-sm" style="color: var(--text-secondary);">Progress:</span>
                    <span class="text-sm" id="progressPercentage">0%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div id="progressBar" class="bg-success rounded-full h-2" style="width: 0%;"></div>
                </div>
            </div>
            <p id="progressMessage" class="text-sm" style="color: var(--text-secondary);">Preparing operation...</p>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let selectedInvoiceIds = [];

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

    // Initialize checkbox handling
    initializeCheckboxes();
    
    // Confirm delete text input validation
    const confirmDeleteInput = document.getElementById('confirmDeleteText');
    if (confirmDeleteInput) {
        confirmDeleteInput.addEventListener('input', function() {
            const confirmBtn = document.getElementById('confirmForceDeleteBtn');
            if (this.value === 'DELETE') {
                confirmBtn.disabled = false;
                confirmBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                confirmBtn.disabled = true;
                confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        });
    }
});

function initializeCheckboxes() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const invoiceCheckboxes = document.querySelectorAll('.invoice-checkbox');
    const selectionActions = document.getElementById('selectionActions');
    const selectedCountSpan = document.getElementById('selectedCount');
    const selectedTotalSpan = document.getElementById('selectedTotal');
    const bulkRestoreBtn = document.getElementById('bulkRestoreBtn');
    const bulkForceDeleteBtn = document.getElementById('bulkForceDeleteBtn');

    // Function to update selection summary
    function updateSelectionSummary() {
        const checkedCheckboxes = document.querySelectorAll('.invoice-checkbox:checked');
        const checkedCount = checkedCheckboxes.length;
        
        if (selectedCountSpan) selectedCountSpan.textContent = checkedCount;
        
        // Calculate total amount
        let totalAmount = 0;
        checkedCheckboxes.forEach(checkbox => {
            const amount = parseFloat(checkbox.dataset.amount) || 0;
            totalAmount += amount;
        });
        
        if (selectedTotalSpan) selectedTotalSpan.textContent = formatAmount(totalAmount);
        
        // Show/hide selection actions
        if (selectionActions) {
            if (checkedCount > 0) {
                selectionActions.classList.remove('hidden');
            } else {
                selectionActions.classList.add('hidden');
            }
        }
        
        // Update select all checkbox state
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = checkedCount === invoiceCheckboxes.length;
            selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < invoiceCheckboxes.length;
        }
    }

    // Select All checkbox
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            invoiceCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectionSummary();
        });
    }

    // Individual checkboxes
    invoiceCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectionSummary);
    });

    // Bulk Restore button (top card)
    if (bulkRestoreBtn) {
        bulkRestoreBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const selectedIds = getSelectedIds();
            if (selectedIds.length > 0) {
                bulkRestore();
            } else {
                alert('Please select at least one invoice to restore.');
            }
        });
    }

    // Bulk Force Delete button (top card)
    if (bulkForceDeleteBtn) {
        bulkForceDeleteBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const selectedIds = getSelectedIds();
            if (selectedIds.length > 0) {
                bulkForceDelete();
            } else {
                alert('Please select at least one invoice to permanently delete.');
            }
        });
    }

    // Initial update
    updateSelectionSummary();
}

function getSelectedIds() {
    return Array.from(document.querySelectorAll('.invoice-checkbox:checked')).map(cb => cb.value);
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

// Restore single invoice
function restoreInvoice(invoiceId) {
    const modal = document.getElementById('restoreModal');
    const form = document.getElementById('restoreForm');
    const warningDiv = document.getElementById('restoreWarning');
    const warningMessage = document.getElementById('restoreWarningMessage');
    const submitBtn = form ? form.querySelector('button[type="submit"]') : null;
    
    // Set the correct form action for restore
    if (form) {
        form.action = "{{ url('invoices/trash/restore') }}/" + invoiceId;
    }
    
    // Reset warning and enable submit button
    if (warningDiv) warningDiv.classList.add('hidden');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.style.opacity = '1';
        submitBtn.style.cursor = 'pointer';
    }
    
    // Show modal immediately
    if (modal) modal.classList.remove('hidden');
}

function closeRestoreModal() {
    const modal = document.getElementById('restoreModal');
    const form = document.getElementById('restoreForm');
    if (modal) modal.classList.add('hidden');
    if (form) form.action = '';
}

function forceDeleteInvoice(invoiceId, canDelete) {
    if (!canDelete) {
        alert('This invoice cannot be permanently deleted yet. It must remain in trash for at least {{ $settings->trash_retention_days ?? 30 }} days.');
        return;
    }
    
    const modal = document.getElementById('forceDeleteModal');
    const form = document.getElementById('forceDeleteForm');
    const warningMessage = document.getElementById('forceDeleteWarningMessage');
    const confirmInput = document.getElementById('confirmDeleteText');
    
    // Set the correct form action for force delete
    if (form) {
        form.action = "{{ url('invoices/trash/force-delete') }}/" + invoiceId;
    }
    
    if (warningMessage) warningMessage.textContent = 'This invoice will be permanently removed from the database. This action cannot be undone.';
    if (confirmInput) confirmInput.value = '';
    
    // Reset confirm button
    const confirmBtn = document.getElementById('confirmForceDeleteBtn');
    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');
    }
    
    if (modal) modal.classList.remove('hidden');
}

function closeForceDeleteModal() {
    const modal = document.getElementById('forceDeleteModal');
    const form = document.getElementById('forceDeleteForm');
    const confirmInput = document.getElementById('confirmDeleteText');
    
    if (modal) modal.classList.add('hidden');
    if (confirmInput) confirmInput.value = '';
    if (form) form.action = '';
}

function openEmptyTrashModal() {
    const modal = document.getElementById('emptyTrashModal');
    if (modal) modal.classList.remove('hidden');
}

function closeEmptyTrashModal() {
    const modal = document.getElementById('emptyTrashModal');
    if (modal) modal.classList.add('hidden');
}

function bulkRestore() {
    const selectedIds = getSelectedIds();
    
    if (selectedIds.length === 0) {
        alert('Please select at least one invoice to restore.');
        return;
    }
    
    if (confirm(`Are you sure you want to restore ${selectedIds.length} invoice(s)?`)) {
        showProgressModal();
        
        fetch('{{ route("invoices.bulk-restore") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ invoice_ids: selectedIds })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                hideProgressModal();
                alert('Error: ' + (data.message || 'Failed to restore invoices'));
            }
        })
        .catch(error => {
            hideProgressModal();
            console.error('Bulk restore failed:', error);
            alert('Error restoring invoices. Please try again.');
        });
    }
}

function bulkForceDelete() {
    const selectedIds = getSelectedIds();
    
    if (selectedIds.length === 0) {
        alert('Please select at least one invoice to permanently delete.');
        return;
    }
    
    const confirmMessage = `⚠️ WARNING: This will permanently delete ${selectedIds.length} invoice(s). This action cannot be undone!\n\nType "DELETE" to confirm.`;
    const userInput = prompt(confirmMessage);
    
    if (userInput === 'DELETE') {
        showProgressModal();
        
        fetch('{{ route("invoices.bulk-force-delete") }}', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ invoice_ids: selectedIds })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                hideProgressModal();
                alert('Error: ' + (data.message || 'Failed to delete invoices'));
            }
        })
        .catch(error => {
            hideProgressModal();
            console.error('Bulk force delete failed:', error);
            alert('Error deleting invoices. Please try again.');
        });
    }
}

function showProgressModal() {
    const modal = document.getElementById('bulkProgressModal');
    const progressBar = document.getElementById('progressBar');
    const progressPercentage = document.getElementById('progressPercentage');
    const progressMessage = document.getElementById('progressMessage');
    
    if (progressBar) progressBar.style.width = '0%';
    if (progressPercentage) progressPercentage.textContent = '0%';
    if (progressMessage) progressMessage.textContent = 'Processing...';
    if (modal) modal.classList.remove('hidden');
    
    // Animate progress bar
    let progress = 0;
    const interval = setInterval(() => {
        if (progress < 90) {
            progress += 10;
            if (progressBar) progressBar.style.width = progress + '%';
            if (progressPercentage) progressPercentage.textContent = progress + '%';
        }
    }, 300);
    
    // Store interval to clear later
    window.progressInterval = interval;
}

function hideProgressModal() {
    const modal = document.getElementById('bulkProgressModal');
    if (modal) modal.classList.add('hidden');
    if (window.progressInterval) {
        clearInterval(window.progressInterval);
        window.progressInterval = null;
    }
}

// Close modals when clicking outside
document.getElementById('restoreModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeRestoreModal();
    }
});

document.getElementById('forceDeleteModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeForceDeleteModal();
    }
});

document.getElementById('emptyTrashModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeEmptyTrashModal();
    }
});

document.getElementById('bulkProgressModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        hideProgressModal();
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeRestoreModal();
        closeForceDeleteModal();
        closeEmptyTrashModal();
        hideProgressModal();
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

.btn-danger:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-archive {
    background-color: var(--info);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    text-decoration: none;
    display: inline-block;
}

.btn-archive:hover {
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

.text-danger {
    color: var(--danger);
}

.text-warning {
    color: var(--warning);
}

/* Modal styles */
#restoreModal,
#forceDeleteModal,
#emptyTrashModal,
#bulkProgressModal {
    transition: opacity 0.3s ease;
}

#restoreModal.hidden,
#forceDeleteModal.hidden,
#emptyTrashModal.hidden,
#bulkProgressModal.hidden {
    opacity: 0;
    pointer-events: none;
}

#restoreModal:not(.hidden),
#forceDeleteModal:not(.hidden),
#emptyTrashModal:not(.hidden),
#bulkProgressModal:not(.hidden) {
    opacity: 1;
    pointer-events: auto;
}

/* Checkbox styling */
input[type="checkbox"] {
    width: 16px;
    height: 16px;
    cursor: pointer;
}

/* Selection actions bar */
#selectionActions {
    transition: all 0.3s ease;
}

/* Disabled button styling */
button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Progress bar */
.bg-success {
    background-color: var(--success);
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

/* Table row hover effect */
tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.05);
    transition: background-color 0.2s;
}
</style>
@endsection