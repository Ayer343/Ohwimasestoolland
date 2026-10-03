@extends('layouts.tenant')

@section('title', 'My Invoices')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div class="flex items-center mb-4 md:mb-0">
                <i class="fas fa-file-invoice text-2xl mr-3" style="color: var(--primary);"></i>
                <div>
                    <h2 class="text-xl font-semibold" style="color: var(--text-primary);">My Invoices</h2>
                    <p class="text-sm" style="color: var(--text-secondary);">View and manage your community development invoices</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('tenant.invoices.request-current') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-sync mr-2"></i> Request Current Month
                </a>
                <a href="{{ route('tenant.payments.history') }}" class="btn-info flex items-center">
                    <i class="fas fa-history mr-2"></i> Payment History
                </a>
            </div>
        </div>
    </div>

    <!-- ========== ARCHIVE NOTIFICATION BANNERS ========== -->
    
    <!-- Archive Warning Banner - Upcoming Archiving -->
    @php
        $archiveReminderDays = $settings->archive_notification_days ?? 30;
        $upcomingArchiveInvoices = $invoices->filter(function($invoice) use ($archiveReminderDays) {
            return $invoice->status === 'paid' && 
                   $invoice->payment_date && 
                   $invoice->payment_date->lte(now()->subDays($archiveReminderDays));
        });
    @endphp
    
    @if($upcomingArchiveInvoices->count() > 0)
    <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative" role="alert">
        <div class="flex items-start">
            <i class="fas fa-archive mt-1 mr-3 text-xl"></i>
            <div class="flex-1">
                <strong class="font-bold">📦 Invoice Archiving Notice</strong>
                <p class="text-sm mt-1">
                    The following {{ $upcomingArchiveInvoices->count() }} paid invoice(s) will be archived in 
                    <strong>{{ $archiveReminderDays }} days</strong>:
                </p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach($upcomingArchiveInvoices->take(5) as $inv)
                        <span class="px-2 py-1 bg-white rounded-full text-xs" style="color: #1e40af;">
                            #{{ $inv->invoice_number }} ({{ $inv->month_name }})
                        </span>
                    @endforeach
                    @if($upcomingArchiveInvoices->count() > 5)
                        <span class="px-2 py-1 bg-white rounded-full text-xs">
                            +{{ $upcomingArchiveInvoices->count() - 5 }} more
                        </span>
                    @endif
                </div>
                <p class="text-xs mt-2">
                    <i class="fas fa-info-circle mr-1"></i>
                    Archived invoices will be moved to your history and can still be viewed.
                </p>
            </div>
            <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    <!-- Year-End Archive Notice Banner -->
    @php
        $currentYear = now()->year;
        $archiveMonth = $settings->year_end_archive_month ?? 1;
        $archiveDay = $settings->year_end_archive_day ?? 15;
        $archiveDate = \Carbon\Carbon::create($currentYear, $archiveMonth, $archiveDay);
        $daysUntilArchive = now()->startOfDay()->diffInDays($archiveDate, false);
        $invoicesFromPrevYear = $invoices->filter(function($invoice) use ($currentYear) {
            return $invoice->period < $currentYear . '-01';
        });
        $paidFromPrevYear = $invoicesFromPrevYear->where('status', 'paid')->count();
        $unpaidFromPrevYear = $invoicesFromPrevYear->where('status', '!=', 'paid')->where('status', '!=', 'cancelled')->count();
    @endphp

    @if($daysUntilArchive > 0 && $daysUntilArchive <= 60 && ($paidFromPrevYear > 0 || $unpaidFromPrevYear > 0))
    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative" role="alert">
        <div class="flex items-start">
            <i class="fas fa-calendar-alt mt-1 mr-3 text-xl"></i>
            <div class="flex-1">
                <strong class="font-bold">⚠️ Year-End Archiving Coming Soon</strong>
                <p class="text-sm mt-1">
                    The annual year-end archiving will take place on <strong>{{ $archiveDate->format('F j, Y') }}</strong> 
                    (in {{ $daysUntilArchive }} days).
                </p>
                @if($paidFromPrevYear > 0)
                <p class="text-sm mt-1">
                    <i class="fas fa-check-circle mr-1"></i>
                    <strong>{{ $paidFromPrevYear }}</strong> paid invoice(s) from {{ now()->subYear()->year }} will be automatically archived.
                </p>
                @endif
                @if($unpaidFromPrevYear > 0)
                <p class="text-sm mt-1 text-red-600">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <strong>{{ $unpaidFromPrevYear }} unpaid invoice(s)</strong> from {{ now()->subYear()->year }} require your attention. 
                    Please pay them before the archive date to avoid issues.
                </p>
                @endif
            </div>
            <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    <!-- Unpaid Old Invoices Banner -->
    @php
        $oldUnpaidInvoices = $invoices->filter(function($invoice) {
            return $invoice->status !== 'paid' && 
                   $invoice->status !== 'cancelled' &&
                   $invoice->period < now()->subMonths(6)->format('Y-m');
        });
        $totalUnpaidBalance = $oldUnpaidInvoices->sum('balance');
    @endphp

    @if($oldUnpaidInvoices->count() > 0)
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
        <div class="flex items-start">
            <i class="fas fa-exclamation-triangle mt-1 mr-3 text-xl"></i>
            <div class="flex-1">
                <strong class="font-bold">⚠️ Unpaid Invoices from Previous Periods</strong>
                <p class="text-sm mt-1">
                    You have <strong>{{ $oldUnpaidInvoices->count() }} unpaid invoice(s)</strong> totaling 
                    <strong>{{ $system_settings->formatAmount($totalUnpaidBalance) }}</strong> that are overdue:
                </p>
                <div class="mt-2">
                    <ul class="text-sm list-disc list-inside">
                        @foreach($oldUnpaidInvoices->take(3) as $inv)
                            <li>
                                #{{ $inv->invoice_number }} - {{ $inv->month_name }} - 
                                <strong class="text-red-700">{{ $system_settings->formatAmount($inv->balance) }}</strong>
                                @if($inv->days_overdue > 0)
                                    <span class="text-xs">(Overdue by {{ $inv->days_overdue }} days)</span>
                                @endif
                            </li>
                        @endforeach
                        @if($oldUnpaidInvoices->count() > 3)
                            <li>+{{ $oldUnpaidInvoices->count() - 3 }} more invoices</li>
                        @endif
                    </ul>
                </div>
                <p class="text-sm mt-2">
                    <a href="{{ route('tenant.payments.make') }}" class="underline font-medium">Pay Now</a> to avoid additional penalties.
                </p>
            </div>
            <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    <!-- System Status Alert -->
    @if(!$enable_tenant_invoicing)
    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-center">
            <i class="fas fa-exclamation-triangle mr-3 text-xl"></i>
            <div>
                <strong class="font-bold">Invoicing Disabled!</strong>
                <span class="block sm:inline"> Tenant invoicing is currently disabled. Please check back later.</span>
            </div>
        </div>
    </div>
    @endif

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

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
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
    </div>

    <!-- Financial Summary -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="card p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm" style="color: var(--text-secondary);">Total Paid</span>
                <span class="text-lg font-semibold" style="color: var(--success);">{{ $system_settings->formatAmount($statistics['total_paid'] ?? 0) }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm" style="color: var(--text-secondary);">Total Outstanding</span>
                <span class="text-lg font-semibold" style="color: var(--warning);">{{ $system_settings->formatAmount($statistics['total_outstanding'] ?? 0) }}</span>
            </div>
            <div class="mt-2 pt-2 border-t" style="border-color: var(--border-color);">
                <div class="flex items-center justify-between">
                    <span class="text-sm" style="color: var(--text-secondary);">Payment Rate</span>
                    <span class="text-lg font-semibold" style="color: var(--primary);">{{ $statistics['payment_rate'] ?? 0 }}%</span>
                </div>
            </div>
        </div>
        
        @if($nextPayment && $nextPayment['exists'])
        <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.1);">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-sm" style="color: var(--text-secondary);">Next Payment Due</span>
                    <p class="text-lg font-semibold mt-1" style="color: var(--text-primary);">{{ $nextPayment['formatted_amount'] }}</p>
                    <p class="text-sm" style="color: var(--text-secondary);">for {{ $nextPayment['period'] }}</p>
                </div>
                <div class="text-right">
                    <span class="inline-block px-2 py-1 rounded-full text-xs" 
                          style="background-color: {{ $nextPayment['days_until_due'] <= 3 ? 'rgba(var(--danger-rgb), 0.2)' : 'rgba(var(--info-rgb), 0.2)' }}; 
                                 color: {{ $nextPayment['days_until_due'] <= 3 ? 'var(--danger)' : 'var(--info)' }};">
                        {{ $nextPayment['days_until_due'] > 0 ? $nextPayment['days_until_due'].' days left' : 'Due today' }}
                    </span>
                    <p class="text-xs mt-2" style="color: var(--text-secondary);">Due: {{ $nextPayment['due_date'] }}</p>
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Filters Card -->
    <div class="card p-6">
        <form method="GET" action="{{ route('tenant.invoices.index') }}" id="filterForm">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <select name="status" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Status</option>
                        <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="overdue" {{ request('status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
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
                    <input type="month" name="period" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           placeholder="Filter by period..." value="{{ request('period') }}">
                </div>
                <div class="flex space-x-2">
                    <button type="submit" class="w-full p-2 border rounded flex items-center justify-center" style="background-color: var(--primary); color: white; border-color: var(--border-color);">
                        <i class="fas fa-filter mr-2"></i> Filter
                    </button>
                    <a href="{{ route('tenant.invoices.index') }}" class="w-full p-2 border rounded flex items-center justify-center" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <i class="fas fa-sync mr-2"></i> Reset
                    </a>
                </div>
            </div>
            
            <!-- Date Range Filter -->
            <div class="mt-4">
                <button type="button" onclick="toggleDateRange()" class="text-sm flex items-center" style="color: var(--primary);">
                    <i class="fas fa-chevron-down mr-1" id="dateRangeIcon"></i> Date Range Filter
                </button>
            </div>
            
            <div id="dateRangeFilters" class="hidden mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm mb-1" style="color: var(--text-secondary);">From Date</label>
                    <input type="date" name="from_date" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           value="{{ request('from_date') }}">
                </div>
                <div>
                    <label class="block text-sm mb-1" style="color: var(--text-secondary);">To Date</label>
                    <input type="date" name="to_date" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                           value="{{ request('to_date') }}">
                </div>
            </div>
        </form>
    </div>

    <!-- Invoices Table Card -->
    <div class="card p-6">
        <!-- Bulk Export Controls -->
        <div class="flex justify-between items-center mb-4">
            <div class="flex items-center space-x-2">
                <input type="checkbox" id="selectAll" class="rounded" style="accent-color: var(--primary);">
                <label for="selectAll" class="text-sm" style="color: var(--text-secondary);">Select All</label>
            </div>
            <button id="bulkExportBtn" 
                    onclick="bulkExportPDF()" 
                    class="px-3 py-1 rounded text-sm disabled:opacity-50 flex items-center" 
                    style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);" 
                    disabled>
                <i class="fas fa-download mr-1"></i> Export Selected (PDF)
            </button>
        </div>

        <!-- Results Count and Summary -->
        <div class="mb-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <p class="text-sm" style="color: var(--text-secondary);">
                Showing <span class="font-medium">{{ $invoices->firstItem() ?? 0 }}</span> to <span class="font-medium">{{ $invoices->lastItem() ?? 0 }}</span> of <span class="font-medium">{{ $invoices->total() }}</span> invoices
            </p>
            
            @if(request()->hasAny(['status', 'property_unit_id', 'period', 'from_date']))
            <div class="flex items-center flex-wrap gap-2">
                <span class="text-sm mr-2" style="color: var(--text-secondary);">Active filters:</span>
                @if(request('status'))
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                    Status: {{ ucfirst(request('status')) }}
                </span>
                @endif
                @if(request('period'))
                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                    Period: {{ \Carbon\Carbon::parse(request('period') . '-01')->format('M Y') }}
                </span>
                @endif
            </div>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color);">
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">
                            <input type="checkbox" id="selectAllHeader" class="rounded" style="accent-color: var(--primary);">
                        </th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Invoice #</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Property Unit</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Period</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Due Date</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Amount</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Archive Status</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Actions</th>
                     </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                    <tr class="border-b hover:bg-opacity-50 cursor-pointer" style="border-color: var(--border-color);" 
                        data-invoice-id="{{ $invoice->id }}"
                        data-status="{{ $invoice->status }}">
                        
                        <td class="p-3" onclick="event.stopPropagation();">
                            <input type="checkbox" class="select-invoice rounded" data-invoice-id="{{ $invoice->id }}" style="accent-color: var(--primary);">
                        </td>
                        <td class="p-3">
                            <div class="flex items-center">
                                @if($invoice->status === 'overdue')
                                    <i class="fas fa-exclamation-circle mr-1 text-xs" style="color: var(--danger);"></i>
                                @endif
                                <span class="font-medium" style="color: var(--text-primary);">
                                    {{ $invoice->invoice_number }}
                                </span>
                            </div>
                            @if($invoice->payment_reference)
                                <p class="text-xs" style="color: var(--text-secondary);">Ref: {{ $invoice->payment_reference }}</p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $invoice->propertyUnit->property->property_name ?? 'N/A' }}
                            </p>
                            <p class="text-xs" style="color: var(--text-secondary);">Unit: {{ $invoice->propertyUnit->unit_number ?? 'N/A' }}</p>
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">{{ $invoice->month_name }}</p>
                            @if($invoice->original_year && $invoice->original_year != $invoice->created_at->year)
                                <p class="text-xs" style="color: var(--warning);">(From {{ $invoice->original_year }})</p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $invoice->due_date->format('M d, Y') }}
                            </p>
                            @if($invoice->status === 'overdue')
                            <p class="text-xs" style="color: var(--danger);">Overdue by {{ $invoice->days_overdue }} days</p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $system_settings->formatAmount($invoice->total_amount) }}
                            </p>
                            @if($invoice->penalty_amount > 0)
                            <p class="text-xs" style="color: var(--danger);">
                                +{{ $system_settings->formatAmount($invoice->penalty_amount) }} penalty
                            </p>
                            @endif
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
                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.2); color: var(--{{ $statusColor }});">
                                <i class="{{ $invoice->status_icon }} mr-1"></i>
                                {{ ucfirst($invoice->status) }}
                            </span>
                        </td>
                        <td class="p-3">
                            @if($invoice->status === 'paid')
                                @php
                                    $retentionMonths = $settings->paid_invoice_retention_months ?? 3;
                                    $paymentDate = $invoice->payment_date;
                                    $willArchiveDate = $paymentDate ? $paymentDate->copy()->addMonths($retentionMonths) : null;
                                    $daysUntilArchive = $willArchiveDate ? now()->diffInDays($willArchiveDate, false) : null;
                                @endphp
                                @if($willArchiveDate && $daysUntilArchive > 0)
                                    <span class="text-xs" style="color: var(--info);">
                                        <i class="fas fa-clock mr-1"></i>
                                        Archive in {{ $daysUntilArchive }} day{{ $daysUntilArchive != 1 ? 's' : '' }}
                                    </span>
                                @elseif($willArchiveDate && $daysUntilArchive <= 0)
                                    <span class="text-xs" style="color: var(--warning);">
                                        <i class="fas fa-archive mr-1"></i>
                                        Pending archiving
                                    </span>
                                @else
                                    <span class="text-xs" style="color: var(--success);">
                                        <i class="fas fa-check-circle mr-1"></i>
                                        Paid on {{ $invoice->payment_date?->format('M d, Y') }}
                                    </span>
                                @endif
                            @elseif($invoice->status === 'pending')
                                @if($invoice->before_due_date)
                                    <span class="text-xs" style="color: var(--info);">
                                        <i class="fas fa-hourglass-half mr-1"></i>
                                        Due in {{ $invoice->days_until_due }} day{{ $invoice->days_until_due != 1 ? 's' : '' }}
                                    </span>
                                @elseif($invoice->within_grace_period)
                                    <span class="text-xs" style="color: var(--warning);">
                                        <i class="fas fa-hourglass-start mr-1"></i>
                                        Grace: {{ $invoice->days_in_grace }} day{{ $invoice->days_in_grace != 1 ? 's' : '' }} left
                                    </span>
                                @else
                                    <span class="text-xs" style="color: var(--danger);">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        Grace period ended
                                    </span>
                                @endif
                            @elseif($invoice->status === 'overdue')
                                <span class="text-xs" style="color: var(--danger);">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Overdue - Payment required
                                </span>
                            @else
                                <span class="text-xs" style="color: var(--text-secondary);">—</span>
                            @endif
                        </td>
                        <td class="p-3">
                            <div class="flex space-x-2" onclick="event.stopPropagation();">
                                <a href="{{ route('tenant.invoices.show', $invoice->id) }}" 
                                   class="p-2 rounded" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('tenant.invoices.export-pdf', $invoice->id) }}" 
                                   class="p-2 rounded" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);" title="Download PDF" target="_blank">
                                    <i class="fas fa-file-pdf"></i>
                                </a>
                                <a href="{{ route('tenant.invoices.print', $invoice->id) }}" 
                                   class="p-2 rounded" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);" title="Print" target="_blank">
                                    <i class="fas fa-print"></i>
                                </a>
                                @if($invoice->status === 'pending' || $invoice->status === 'overdue')
                                    <a href="{{ route('tenant.payments.make', ['invoice_id' => $invoice->id]) }}" 
                                       class="p-2 rounded" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);" title="Make Payment">
                                        <i class="fas fa-credit-card"></i>
                                    </a>
                                @endif
                                @if($invoice->status === 'paid')
                                <button type="button" 
                                        onclick="openReceiptModal({{ $invoice->id }})"
                                        class="p-2 rounded" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);" title="Request Receipt">
                                    <i class="fas fa-envelope"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="p-8 text-center">
                            <div class="flex flex-col items-center justify-center" style="color: var(--text-secondary);">
                                <i class="fas fa-file-invoice text-4xl mb-4 opacity-50"></i>
                                <p class="text-lg font-medium mb-2">No invoices found</p>
                                <p class="text-sm mb-4">You don't have any invoices yet.</p>
                                @if($enable_tenant_invoicing)
                                <a href="{{ route('tenant.invoices.request-current') }}" class="btn-primary">
                                    Request Current Month Invoice
                                </a>
                                @endif
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

    <!-- Payment Information Card -->
    <div class="card p-6">
        <h3 class="font-medium mb-4" style="color: var(--text-primary);">Payment Information</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Grace Period Info -->
            <div class="p-4 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                <div class="flex items-center mb-3">
                    <i class="fas fa-clock mr-2" style="color: var(--info);"></i>
                    <h4 class="font-medium" style="color: var(--text-primary);">Grace Period</h4>
                </div>
                <p class="text-sm mb-2" style="color: var(--text-secondary);">
                    You have <strong class="text-info">{{ $tenant_grace_period_days }} days</strong> after the due date to make payment without penalty.
                </p>
                @if(isset($gracePeriodEndDate))
                <p class="text-xs" style="color: var(--text-secondary);">
                    Grace period ends {{ $gracePeriodEndDate?->format('M d, Y') }}
                </p>
                @endif
            </div>
            
            <!-- Penalty Info -->
            <div class="p-4 rounded" style="background-color: rgba(var(--warning-rgb), 0.05);">
                <div class="flex items-center mb-3">
                    <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i>
                    <h4 class="font-medium" style="color: var(--text-primary);">Late Payment Penalty</h4>
                </div>
                @if($tenant_late_payment_percentage > 0)
                <p class="text-sm mb-1" style="color: var(--text-secondary);">
                    <strong>{{ $tenant_late_payment_percentage }}%</strong> of outstanding amount
                </p>
                @endif
                @if($tenant_fixed_penalty_amount > 0)
                <p class="text-sm" style="color: var(--text-secondary);">
                    Fixed penalty of <strong>{{ $system_settings->formatAmount($tenant_fixed_penalty_amount) }}</strong>
                </p>
                @endif
                @if($tenant_late_payment_percentage == 0 && $tenant_fixed_penalty_amount == 0)
                <p class="text-sm" style="color: var(--text-secondary);">No penalties configured</p>
                @endif
            </div>
        </div>
        
        <!-- Payment Instructions -->
        @if(!empty($paymentInstructions))
        <div class="mt-4 p-4 rounded" style="background-color: rgba(var(--success-rgb), 0.05);">
            <div class="flex items-center mb-3">
                <i class="fas fa-info-circle mr-2" style="color: var(--success);"></i>
                <h4 class="font-medium" style="color: var(--text-primary);">Payment Instructions</h4>
            </div>
            
            @if(isset($paymentInstructions['general']))
            <p class="text-sm mb-3" style="color: var(--text-secondary);">{{ $paymentInstructions['general'] }}</p>
            @endif
            
            @if(isset($paymentInstructions['mobile_money']))
            <div class="mt-2">
                <p class="text-sm font-medium mb-1" style="color: var(--text-primary);">Mobile Money:</p>
                <p class="text-sm" style="color: var(--text-secondary);">
                    Provider: {{ ucfirst($paymentInstructions['mobile_money']['provider']) }}<br>
                    Number: {{ $paymentInstructions['mobile_money']['number'] }}<br>
                    Name: {{ $paymentInstructions['mobile_money']['name'] }}
                </p>
            </div>
            @endif
            
            @if(isset($paymentInstructions['bank']))
            <div class="mt-2">
                <p class="text-sm font-medium mb-1" style="color: var(--text-primary);">Bank Transfer:</p>
                <p class="text-sm" style="color: var(--text-secondary);">
                    Bank: {{ $paymentInstructions['bank']['bank_name'] }}<br>
                    Account: {{ $paymentInstructions['bank']['account_number'] }}<br>
                    Name: {{ $paymentInstructions['bank']['account_name'] }}
                </p>
            </div>
            @endif
            
            @if(isset($paymentInstructions['custom_message']))
            <p class="text-sm mt-2 italic" style="color: var(--text-secondary);">"{{ $paymentInstructions['custom_message'] }}"</p>
            @endif
        </div>
        @endif
    </div>

    <!-- Recent Payment History -->
    @if(isset($statistics['payment_history']) && count($statistics['payment_history']) > 0)
    <div class="card p-6">
        <h3 class="font-medium mb-4" style="color: var(--text-primary);">Recent Payment History</h3>
        
        <div class="space-y-3">
            @foreach($statistics['payment_history'] as $payment)
            <div class="flex items-center justify-between p-3 rounded" style="background-color: rgba(var(--success-rgb), 0.05);">
                <div>
                    <p class="font-medium" style="color: var(--text-primary);">{{ $payment['period'] }}</p>
                    <p class="text-xs" style="color: var(--text-secondary);">Paid on {{ $payment['payment_date'] }}</p>
                </div>
                <span class="font-medium" style="color: var(--success);">{{ $payment['formatted_amount'] }}</span>
            </div>
            @endforeach
        </div>
        
        <div class="mt-4 text-center">
            <a href="{{ route('tenant.payments.history') }}" class="text-sm" style="color: var(--primary);">
                View All Payment History <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    </div>
    @endif
</div>

<!-- Request Receipt Modal -->
<div id="requestReceiptModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Request Payment Receipt</h3>
                <button type="button" onclick="closeReceiptModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="requestReceiptForm" method="POST" action="">
                @csrf
                <input type="hidden" name="invoice_id" id="receipt_invoice_id">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Your Email</label>
                        <input type="email" name="email" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                               value="{{ auth()->user()->email }}" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Additional Message (Optional)</label>
                        <textarea name="message" rows="3" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                                  placeholder="Enter any additional message..."></textarea>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" onclick="closeReceiptModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded text-white" style="background-color: var(--primary);">
                        Request Receipt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
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

    // Table row click navigation (excluding checkbox and action buttons)
    const rows = document.querySelectorAll('tbody tr');
    rows.forEach(row => {
        row.addEventListener('click', function(e) {
            // Don't navigate if clicking on checkbox or action buttons
            if (e.target.type === 'checkbox' || 
                e.target.closest('.flex.space-x-2') || 
                e.target.closest('.select-invoice')) {
                return;
            }
            const invoiceLink = this.querySelector('a[href*="show"]');
            if (invoiceLink) {
                window.location.href = invoiceLink.href;
            }
        });
        
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
        });
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
    
    // Initialize select all checkboxes
    initSelectAll();
});

// Select All functionality
let selectAllHeader, selectAllFooter, invoiceCheckboxes, bulkExportBtn;

function initSelectAll() {
    selectAllHeader = document.getElementById('selectAllHeader');
    selectAllFooter = document.getElementById('selectAll');
    invoiceCheckboxes = document.querySelectorAll('.select-invoice');
    bulkExportBtn = document.getElementById('bulkExportBtn');
    
    if (selectAllHeader) {
        selectAllHeader.addEventListener('change', function() {
            handleSelectAll(this);
        });
    }
    
    if (selectAllFooter) {
        selectAllFooter.addEventListener('change', function() {
            handleSelectAll(this);
        });
    }
    
    invoiceCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkExportButton);
    });
    
    updateBulkExportButton();
}

function handleSelectAll(checkbox) {
    invoiceCheckboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateBulkExportButton();
}

function updateBulkExportButton() {
    const checkedCount = document.querySelectorAll('.select-invoice:checked').length;
    if (bulkExportBtn) {
        bulkExportBtn.disabled = checkedCount === 0;
    }
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 px-4 py-3 rounded shadow-lg z-50 transition-opacity duration-300`;
    
    if (type === 'success') {
        notification.style.backgroundColor = '#10b981';
        notification.style.color = 'white';
        notification.innerHTML = `<i class="fas fa-check-circle mr-2"></i> ${message}`;
    } else if (type === 'error') {
        notification.style.backgroundColor = '#ef4444';
        notification.style.color = 'white';
        notification.innerHTML = `<i class="fas fa-exclamation-circle mr-2"></i> ${message}`;
    } else {
        notification.style.backgroundColor = '#3b82f6';
        notification.style.color = 'white';
        notification.innerHTML = `<i class="fas fa-info-circle mr-2"></i> ${message}`;
    }
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 5000);
}

function bulkExportPDF() {
    const selectedIds = Array.from(document.querySelectorAll('.select-invoice:checked')).map(cb => cb.dataset.invoiceId);
    
    if (selectedIds.length === 0) {
        showNotification('Please select at least one invoice to export.', 'error');
        return;
    }
    
    // Show loading state
    const exportBtn = document.getElementById('bulkExportBtn');
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Generating...';
    exportBtn.disabled = true;
    
    // Submit via fetch to handle blob response
    fetch('{{ route("tenant.invoices.bulk-export-pdf") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/zip, application/json'
        },
        body: JSON.stringify({
            invoice_ids: selectedIds
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Check content type
        const contentType = response.headers.get('content-type');
        
        if (contentType && contentType.includes('application/zip')) {
            // It's a zip file
            return response.blob();
        } else {
            // It's JSON error
            return response.json().then(data => {
                throw new Error(data.error || data.message || 'Export failed');
            });
        }
    })
    .then(blob => {
        // Create download link
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        const timestamp = new Date().toISOString().slice(0,19).replace(/:/g, '-');
        a.download = `invoices_${timestamp}.zip`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        a.remove();
        
        showNotification(`Successfully exported ${selectedIds.length} invoice(s)!`, 'success');
    })
    .catch(error => {
        console.error('Export error:', error);
        showNotification(error.message || 'Failed to export PDFs. Please try again.', 'error');
    })
    .finally(() => {
        // Reset button
        exportBtn.innerHTML = originalText;
        exportBtn.disabled = false;
    });
}

function toggleDateRange() {
    const dateRangeFilters = document.getElementById('dateRangeFilters');
    const icon = document.getElementById('dateRangeIcon');
    
    if (dateRangeFilters.classList.contains('hidden')) {
        dateRangeFilters.classList.remove('hidden');
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
    } else {
        dateRangeFilters.classList.add('hidden');
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
    }
}

function openReceiptModal(invoiceId) {
    const modal = document.getElementById('requestReceiptModal');
    const form = document.getElementById('requestReceiptForm');
    const invoiceIdInput = document.getElementById('receipt_invoice_id');
    
    invoiceIdInput.value = invoiceId;
    form.action = `/tenant/invoices/${invoiceId}/request-receipt`;
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeReceiptModal() {
    const modal = document.getElementById('requestReceiptModal');
    modal.classList.add('hidden');
    document.getElementById('requestReceiptForm').reset();
    document.body.style.overflow = '';
}

// Close modal when clicking outside
document.getElementById('requestReceiptModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeReceiptModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('requestReceiptModal');
        if (modal && !modal.classList.contains('hidden')) {
            closeReceiptModal();
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
    background-color: rgba(16, 185, 129, 0.2);
    border-color: rgba(16, 185, 129, 0.3);
    color: #10b981;
}

.bg-red-100 {
    background-color: rgba(239, 68, 68, 0.2);
    border-color: rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.bg-yellow-100 {
    background-color: rgba(234, 179, 8, 0.2);
    border-color: rgba(234, 179, 8, 0.3);
    color: #eab308;
}

.bg-blue-100 {
    background-color: rgba(59, 130, 246, 0.2);
    border-color: rgba(59, 130, 246, 0.3);
    color: #3b82f6;
}

/* Table styles */
table {
    border-collapse: separate;
    border-spacing: 0;
}

/* Table row hover effect */
tbody tr {
    transition: background-color 0.2s ease;
    cursor: pointer;
}

/* Status badges */
.px-2.py-1.rounded-full {
    display: inline-block;
    font-weight: 500;
}

/* Modal styles */
#requestReceiptModal {
    transition: opacity 0.3s ease;
}

#requestReceiptModal.hidden {
    opacity: 0;
    pointer-events: none;
}

#requestReceiptModal:not(.hidden) {
    opacity: 1;
    pointer-events: auto;
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
    color: #3b82f6 !important;
}

/* Responsive table */
@media (max-width: 768px) {
    .overflow-x-auto {
        margin: 0 -1rem;
    }
    
    table {
        min-width: 1000px;
    }
    
    .btn-primary, .btn-secondary, .btn-info {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }
}

/* Checkbox styling */
input[type="checkbox"] {
    cursor: pointer;
    width: 16px;
    height: 16px;
}

/* Loading spinner */
.fa-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>
@endsection