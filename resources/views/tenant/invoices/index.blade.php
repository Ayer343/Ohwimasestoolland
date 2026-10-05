@extends('layouts.tenant')

@section('title', 'My Invoices')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- ============================================================
         HEADER
    ============================================================ --}}
    <div class="card">
        <div class="flex flex-col md:flex-row md:justify-between md:items-center p-6 gap-4">
            <div class="flex items-center">
                <i class="fas fa-file-invoice text-2xl mr-3" style="color: var(--primary);"></i>
                <div>
                    <h2 class="text-xl font-semibold" style="color: var(--text-primary);">My Invoices</h2>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        View and manage your community development invoices
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('tenant.invoices.request-current') }}"
                   class="px-4 py-2 rounded-lg flex items-center transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-md"
                   style="background-color: var(--secondary); color: white;">
                    <i class="fas fa-sync mr-2"></i> Request Current Month
                </a>
                <a href="{{ route('tenant.payments.history') }}"
                   class="px-4 py-2 rounded-lg flex items-center transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-md"
                   style="background-color: var(--info); color: white;">
                    <i class="fas fa-history mr-2"></i> Payment History
                </a>
            </div>
        </div>
    </div>

    {{-- ============================================================
         ARCHIVE NOTIFICATION BANNERS
    ============================================================ --}}

    @php
        $archiveReminderDays = $settings->archive_notification_days ?? 30;
        $upcomingArchiveInvoices = $invoices->filter(function ($invoice) use ($archiveReminderDays) {
            return $invoice->status === 'paid'
                && $invoice->payment_date
                && $invoice->payment_date->lte(now()->subDays($archiveReminderDays));
        });
    @endphp

    @if($upcomingArchiveInvoices->count() > 0)
        <div class="card" style="background: linear-gradient(135deg, rgba(var(--info-rgb), 0.08) 0%, rgba(var(--info-rgb), 0.02) 100%); border-left: 4px solid var(--info);">
            <div class="p-4 relative">
                <div class="flex items-start">
                    <i class="fas fa-archive text-2xl mr-3 mt-0.5" style="color: var(--info);"></i>
                    <div class="flex-1 pr-8">
                        <p class="font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-box-open mr-1"></i> Invoice Archiving Notice
                        </p>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            The following {{ $upcomingArchiveInvoices->count() }} paid invoice(s) will be archived in
                            <strong>{{ $archiveReminderDays }} days</strong>:
                        </p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach($upcomingArchiveInvoices->take(5) as $inv)
                                <span class="px-2 py-1 rounded-full text-xs"
                                      style="background-color: rgba(var(--info-rgb), 0.15); color: var(--info);">
                                    #{{ $inv->invoice_number }} ({{ $inv->month_name }})
                                </span>
                            @endforeach
                            @if($upcomingArchiveInvoices->count() > 5)
                                <span class="px-2 py-1 rounded-full text-xs"
                                      style="background-color: rgba(var(--info-rgb), 0.15); color: var(--info);">
                                    +{{ $upcomingArchiveInvoices->count() - 5 }} more
                                </span>
                            @endif
                        </div>
                        <p class="text-xs mt-2" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Archived invoices will be moved to your history and can still be viewed.
                        </p>
                    </div>
                    <button type="button"
                            class="absolute top-0 bottom-0 right-0 px-4"
                            onclick="this.parentElement.parentElement.style.display='none'">
                        <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @php
        $currentYear        = now()->year;
        $archiveMonth       = $settings->year_end_archive_month ?? 1;
        $archiveDay         = $settings->year_end_archive_day ?? 15;
        $archiveDate        = \Carbon\Carbon::create($currentYear, $archiveMonth, $archiveDay);
        $daysUntilArchive   = now()->startOfDay()->diffInDays($archiveDate, false);
        $invoicesFromPrevYear = $invoices->filter(function ($invoice) use ($currentYear) {
            return $invoice->period < $currentYear . '-01';
        });
        $paidFromPrevYear   = $invoicesFromPrevYear->where('status', 'paid')->count();
        $unpaidFromPrevYear = $invoicesFromPrevYear->where('status', '!=', 'paid')->where('status', '!=', 'cancelled')->count();
    @endphp

    @if($daysUntilArchive > 0 && $daysUntilArchive <= 60 && ($paidFromPrevYear > 0 || $unpaidFromPrevYear > 0))
        <div class="card" style="background: linear-gradient(135deg, rgba(var(--warning-rgb), 0.08) 0%, rgba(var(--warning-rgb), 0.02) 100%); border-left: 4px solid var(--warning);">
            <div class="p-4 relative">
                <div class="flex items-start">
                    <i class="fas fa-calendar-alt text-2xl mr-3 mt-0.5" style="color: var(--warning);"></i>
                    <div class="flex-1 pr-8">
                        <p class="font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Year-End Archiving Coming Soon
                        </p>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            The annual year-end archiving will take place on
                            <strong>{{ $archiveDate->format('F j, Y') }}</strong>
                            (in {{ $daysUntilArchive }} days).
                        </p>
                        @if($paidFromPrevYear > 0)
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                                <strong>{{ $paidFromPrevYear }}</strong> paid invoice(s) from {{ now()->subYear()->year }} will be automatically archived.
                            </p>
                        @endif
                        @if($unpaidFromPrevYear > 0)
                            <p class="text-sm mt-1" style="color: var(--danger);">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                <strong>{{ $unpaidFromPrevYear }} unpaid invoice(s)</strong> from {{ now()->subYear()->year }} require your attention.
                                Please pay them before the archive date to avoid issues.
                            </p>
                        @endif
                    </div>
                    <button type="button"
                            class="absolute top-0 bottom-0 right-0 px-4"
                            onclick="this.parentElement.parentElement.style.display='none'">
                        <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @php
        $oldUnpaidInvoices = $invoices->filter(function ($invoice) {
            return $invoice->status !== 'paid'
                && $invoice->status !== 'cancelled'
                && $invoice->period < now()->subMonths(6)->format('Y-m');
        });
        $totalUnpaidBalance = $oldUnpaidInvoices->sum('balance');
    @endphp

    @if($oldUnpaidInvoices->count() > 0)
        <div class="card" style="background: linear-gradient(135deg, rgba(var(--danger-rgb), 0.08) 0%, rgba(var(--danger-rgb), 0.02) 100%); border-left: 4px solid var(--danger);">
            <div class="p-4 relative">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle text-2xl mr-3 mt-0.5" style="color: var(--danger);"></i>
                    <div class="flex-1 pr-8">
                        <p class="font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Unpaid Invoices from Previous Periods
                        </p>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            You have <strong>{{ $oldUnpaidInvoices->count() }} unpaid invoice(s)</strong> totaling
                            <strong style="color: var(--danger);">{{ $system_settings->formatAmount($totalUnpaidBalance) }}</strong>
                            that are overdue:
                        </p>
                        <ul class="text-sm list-disc list-inside mt-2 space-y-1" style="color: var(--text-secondary);">
                            @foreach($oldUnpaidInvoices->take(3) as $inv)
                                <li>
                                    #{{ $inv->invoice_number }} - {{ $inv->month_name }} -
                                    <strong style="color: var(--danger);">{{ $system_settings->formatAmount($inv->balance) }}</strong>
                                    @if($inv->days_overdue > 0)
                                        <span class="text-xs">(Overdue by {{ $inv->days_overdue }} days)</span>
                                    @endif
                                </li>
                            @endforeach
                            @if($oldUnpaidInvoices->count() > 3)
                                <li>+{{ $oldUnpaidInvoices->count() - 3 }} more invoices</li>
                            @endif
                        </ul>
                        <p class="text-sm mt-2" style="color: var(--text-secondary);">
                            <a href="{{ route('tenant.payments.make') }}"
                               class="underline font-medium" style="color: var(--primary);">Pay Now</a>
                            to avoid additional penalties.
                        </p>
                    </div>
                    <button type="button"
                            class="absolute top-0 bottom-0 right-0 px-4"
                            onclick="this.parentElement.parentElement.style.display='none'">
                        <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if(!$enable_tenant_invoicing)
        <div class="card" style="background: linear-gradient(135deg, rgba(var(--warning-rgb), 0.08) 0%, rgba(var(--warning-rgb), 0.02) 100%); border-left: 4px solid var(--warning);">
            <div class="p-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-2xl mr-3" style="color: var(--warning);"></i>
                    <div>
                        <p class="font-medium" style="color: var(--text-primary);">Invoicing Disabled!</p>
                        <p class="text-sm mt-0.5" style="color: var(--text-secondary);">
                            Tenant invoicing is currently disabled. Please check back later.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="card" style="background: linear-gradient(135deg, rgba(var(--success-rgb), 0.08) 0%, rgba(var(--success-rgb), 0.02) 100%); border-left: 4px solid var(--success);">
            <div class="p-4 relative">
                <div class="flex items-center pr-8">
                    <i class="fas fa-check-circle text-2xl mr-3" style="color: var(--success);"></i>
                    <div>
                        <p class="font-medium" style="color: var(--text-primary);">Success!</p>
                        <p class="text-sm mt-0.5" style="color: var(--text-secondary);">{{ session('success') }}</p>
                    </div>
                </div>
                <button type="button"
                        class="absolute top-0 bottom-0 right-0 px-4"
                        onclick="this.parentElement.parentElement.style.display='none'">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="card" style="background: linear-gradient(135deg, rgba(var(--danger-rgb), 0.08) 0%, rgba(var(--danger-rgb), 0.02) 100%); border-left: 4px solid var(--danger);">
            <div class="p-4 relative">
                <div class="flex items-center pr-8">
                    <i class="fas fa-exclamation-circle text-2xl mr-3" style="color: var(--danger);"></i>
                    <div>
                        <p class="font-medium" style="color: var(--text-primary);">Error!</p>
                        <p class="text-sm mt-0.5" style="color: var(--text-secondary);">{{ session('error') }}</p>
                    </div>
                </div>
                <button type="button"
                        class="absolute top-0 bottom-0 right-0 px-4"
                        onclick="this.parentElement.parentElement.style.display='none'">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
        </div>
    @endif

    {{-- ============================================================
         STAT CARDS
    ============================================================ --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="card p-5">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Total Invoices</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ $statistics['total_invoices'] ?? 0 }}</h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-file-invoice text-xl" style="color: var(--primary);"></i>
                </div>
            </div>
        </div>

        <div class="card p-5">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Paid</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--success);">{{ $statistics['paid_invoices'] ?? 0 }}</h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-check-circle text-xl" style="color: var(--success);"></i>
                </div>
            </div>
        </div>

        <div class="card p-5">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Pending</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--warning);">{{ $statistics['pending_invoices'] ?? 0 }}</h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-clock text-xl" style="color: var(--warning);"></i>
                </div>
            </div>
        </div>

        <div class="card p-5">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Overdue</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--danger);">{{ $statistics['overdue_invoices'] ?? 0 }}</h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-exclamation-triangle text-xl" style="color: var(--danger);"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================
         FINANCIAL SUMMARY
    ============================================================ --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="card p-5">
            <div class="flex justify-between items-center py-2">
                <span class="text-sm" style="color: var(--text-secondary);">Total Paid</span>
                <span class="font-semibold" style="color: var(--success);">{{ $system_settings->formatAmount($statistics['total_paid'] ?? 0) }}</span>
            </div>
            <div class="flex justify-between items-center py-2">
                <span class="text-sm" style="color: var(--text-secondary);">Total Outstanding</span>
                <span class="font-semibold" style="color: var(--warning);">{{ $system_settings->formatAmount($statistics['total_outstanding'] ?? 0) }}</span>
            </div>
            <div class="pt-2 mt-2 border-t" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center py-2">
                    <span class="text-sm" style="color: var(--text-secondary);">Payment Rate</span>
                    <span class="font-semibold" style="color: var(--primary);">{{ $statistics['payment_rate'] ?? 0 }}%</span>
                </div>
            </div>
        </div>

        @if($nextPayment && $nextPayment['exists'])
            <div class="card p-5" style="background: linear-gradient(135deg, rgba(var(--info-rgb), 0.08) 0%, rgba(var(--info-rgb), 0.02) 100%); border-left: 4px solid var(--info);">
                <div class="flex items-start justify-between">
                    <div>
                        <span class="text-sm" style="color: var(--text-secondary);">Next Payment Due</span>
                        <p class="text-lg font-semibold mt-1" style="color: var(--text-primary);">{{ $nextPayment['formatted_amount'] }}</p>
                        <p class="text-sm" style="color: var(--text-secondary);">for {{ $nextPayment['period'] }}</p>
                    </div>
                    <div class="text-right">
                        <span class="inline-block px-2 py-1 rounded-full text-xs"
                              style="background-color: {{ $nextPayment['days_until_due'] <= 3 ? 'rgba(var(--danger-rgb), 0.15)' : 'rgba(var(--info-rgb), 0.15)' }};
                                     color: {{ $nextPayment['days_until_due'] <= 3 ? 'var(--danger)' : 'var(--info)' }};">
                            {{ $nextPayment['days_until_due'] > 0 ? $nextPayment['days_until_due'].' days left' : 'Due today' }}
                        </span>
                        <p class="text-xs mt-2" style="color: var(--text-secondary);">Due: {{ $nextPayment['due_date'] }}</p>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- ============================================================
         FILTERS
    ============================================================ --}}
    <div class="card">
        <div class="p-6">
            <form method="GET" action="{{ route('tenant.invoices.index') }}" id="filterForm">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <select name="status"
                                class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">All Status</option>
                            <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="overdue" {{ request('status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                        </select>
                    </div>
                    <div>
                        <select name="property_unit_id"
                                class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">All Units</option>
                            @foreach($propertyUnits ?? [] as $unit)
                                <option value="{{ $unit['id'] }}" {{ request('property_unit_id') == $unit['id'] ? 'selected' : '' }}>
                                    {{ $unit['display_name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <input type="month" name="period"
                               class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="Filter by period..." value="{{ request('period') }}">
                    </div>
                    <div class="flex gap-2">
                        <button type="submit"
                                class="flex-1 p-3 rounded-lg flex items-center justify-center transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-md"
                                style="background-color: var(--primary); color: white;">
                            <i class="fas fa-filter mr-2"></i> Filter
                        </button>
                        <a href="{{ route('tenant.invoices.index') }}"
                           class="flex-1 p-3 rounded-lg flex items-center justify-center transition-all duration-200 hover:transform hover:-translate-y-1"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                            <i class="fas fa-sync mr-2"></i> Reset
                        </a>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="button" onclick="toggleDateRange()" class="text-sm flex items-center" style="color: var(--primary);">
                        <i class="fas fa-chevron-down mr-1" id="dateRangeIcon"></i> Date Range Filter
                    </button>
                </div>

                <div id="dateRangeFilters" class="hidden mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm mb-1" style="color: var(--text-secondary);">From Date</label>
                        <input type="date" name="from_date"
                               class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               value="{{ request('from_date') }}">
                    </div>
                    <div>
                        <label class="block text-sm mb-1" style="color: var(--text-secondary);">To Date</label>
                        <input type="date" name="to_date"
                               class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               value="{{ request('to_date') }}">
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ============================================================
         INVOICES TABLE
    ============================================================ --}}
    <div class="card">
        <div class="p-6">
            {{-- Bulk export controls --}}
            <div class="flex justify-between items-center mb-4 flex-wrap gap-3">
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="selectAll" class="rounded" style="accent-color: var(--primary);">
                    <label for="selectAll" class="text-sm" style="color: var(--text-secondary);">Select All</label>
                </div>
                <button id="bulkExportBtn"
                        onclick="bulkExportPDF()"
                        class="px-3 py-2 rounded-lg text-sm disabled:opacity-50 flex items-center transition-all duration-200 hover:transform hover:-translate-y-1"
                        style="background-color: rgba(var(--success-rgb), 0.15); color: var(--success);"
                        disabled>
                    <i class="fas fa-download mr-1"></i> Export Selected (PDF)
                </button>
            </div>

            {{-- Results count --}}
            <div class="mb-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
                <p class="text-sm" style="color: var(--text-secondary);">
                    Showing <span class="font-medium">{{ $invoices->firstItem() ?? 0 }}</span>
                    to <span class="font-medium">{{ $invoices->lastItem() ?? 0 }}</span>
                    of <span class="font-medium">{{ $invoices->total() }}</span> invoices
                </p>

                @if(request()->hasAny(['status', 'property_unit_id', 'period', 'from_date']))
                    <div class="flex items-center flex-wrap gap-2">
                        <span class="text-sm mr-2" style="color: var(--text-secondary);">Active filters:</span>
                        @if(request('status'))
                            <span class="px-2 py-1 rounded-full text-xs"
                                  style="background-color: rgba(var(--primary-rgb), 0.15); color: var(--primary);">
                                Status: {{ ucfirst(request('status')) }}
                            </span>
                        @endif
                        @if(request('period'))
                            <span class="px-2 py-1 rounded-full text-xs"
                                  style="background-color: rgba(var(--primary-rgb), 0.15); color: var(--primary);">
                                Period: {{ \Carbon\Carbon::parse(request('period') . '-01')->format('M Y') }}
                            </span>
                        @endif
                    </div>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
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
                            <tr class="border-b transition-colors duration-200"
                                style="border-color: var(--border-color);"
                                data-invoice-id="{{ $invoice->id }}"
                                data-status="{{ $invoice->status }}">
                                <td class="p-3" onclick="event.stopPropagation();">
                                    <input type="checkbox"
                                           class="select-invoice rounded"
                                           data-invoice-id="{{ $invoice->id }}"
                                           style="accent-color: var(--primary);">
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
                                        <p class="text-xs mt-0.5" style="color: var(--text-secondary);">Ref: {{ $invoice->payment_reference }}</p>
                                    @endif
                                </td>
                                <td class="p-3">
                                    <p class="font-medium" style="color: var(--text-primary);">
                                        {{ $invoice->propertyUnit->property->property_name ?? 'N/A' }}
                                    </p>
                                    <p class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                        Unit: {{ $invoice->propertyUnit->unit_number ?? 'N/A' }}
                                    </p>
                                </td>
                                <td class="p-3">
                                    <p class="font-medium" style="color: var(--text-primary);">{{ $invoice->month_name }}</p>
                                    @if($invoice->original_year && $invoice->original_year != $invoice->created_at->year)
                                        <p class="text-xs mt-0.5" style="color: var(--warning);">(From {{ $invoice->original_year }})</p>
                                    @endif
                                </td>
                                <td class="p-3">
                                    <p class="font-medium" style="color: var(--text-primary);">
                                        {{ $invoice->due_date->format('M d, Y') }}
                                    </p>
                                    @if($invoice->status === 'overdue')
                                        <p class="text-xs mt-0.5" style="color: var(--danger);">Overdue by {{ $invoice->days_overdue }} days</p>
                                    @endif
                                </td>
                                <td class="p-3">
                                    <p class="font-medium" style="color: var(--text-primary);">
                                        {{ $system_settings->formatAmount($invoice->total_amount) }}
                                    </p>
                                    @if($invoice->penalty_amount > 0)
                                        <p class="text-xs mt-0.5" style="color: var(--danger);">
                                            +{{ $system_settings->formatAmount($invoice->penalty_amount) }} penalty
                                        </p>
                                    @endif
                                </td>
                                <td class="p-3">
                                    @php
                                        $statusColors = [
                                            'paid'      => 'success',
                                            'pending'   => 'warning',
                                            'overdue'   => 'danger',
                                            'cancelled' => 'secondary',
                                        ];
                                        $statusColor = $statusColors[$invoice->status] ?? 'secondary';
                                    @endphp
                                    <span class="px-2 py-1 rounded-full text-xs inline-block"
                                          style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.15); color: var(--{{ $statusColor }});">
                                        <i class="{{ $invoice->status_icon }} mr-1"></i>
                                        {{ ucfirst($invoice->status) }}
                                    </span>
                                </td>
                                <td class="p-3">
                                    @if($invoice->status === 'paid')
                                        @php
                                            $retentionMonths = $settings->paid_invoice_retention_months ?? 3;
                                            $paymentDate     = $invoice->payment_date;
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
                                                <i class="fas fa-archive mr-1"></i> Pending archiving
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
                                                Grace: {{ $invoice->days_in_grace }} day{{ $invoice->days_in_grace != 1 ? 's' : '' }} left                                            </span>
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
                                    <div class="flex gap-2" onclick="event.stopPropagation();">
                                        <a href="{{ route('tenant.invoices.show', $invoice->id) }}"
                                           class="action-btn" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('tenant.invoices.export-pdf', $invoice->id) }}"
                                           class="action-btn" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);" title="Download PDF" target="_blank">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                        <a href="{{ route('tenant.invoices.print', $invoice->id) }}"
                                           class="action-btn" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);" title="Print" target="_blank">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        @if($invoice->status === 'pending' || $invoice->status === 'overdue')
                                            <a href="{{ route('tenant.payments.make', ['invoice_id' => $invoice->id]) }}"
                                               class="action-btn" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);" title="Make Payment">
                                                <i class="fas fa-credit-card"></i>
                                            </a>
                                        @endif
                                        @if($invoice->status === 'paid')
                                            <button type="button"
                                                    onclick="openReceiptModal({{ $invoice->id }})"
                                                    class="action-btn" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);" title="Request Receipt">
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
                                        <p class="text-lg font-medium mb-2" style="color: var(--text-primary);">No invoices found</p>
                                        <p class="text-sm mb-4">You don't have any invoices yet.</p>
                                        @if($enable_tenant_invoicing)
                                            <a href="{{ route('tenant.invoices.request-current') }}"
                                               class="px-4 py-2 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-1"
                                               style="background-color: var(--primary); color: white;">
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

            @if($invoices->hasPages())
                <div class="flex justify-center mt-6">
                    {{ $invoices->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- ============================================================
         PAYMENT INFORMATION
    ============================================================ --}}
    <div class="card">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-info-circle mr-2"></i> Payment Information
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border-left: 3px solid var(--info);">
                    <div class="flex items-center mb-3">
                        <i class="fas fa-clock mr-2" style="color: var(--info);"></i>
                        <h4 class="font-medium" style="color: var(--text-primary);">Grace Period</h4>
                    </div>
                    <p class="text-sm mb-2" style="color: var(--text-secondary);">
                        You have <strong style="color: var(--info);">{{ $tenant_grace_period_days }} days</strong>
                        after the due date to make payment without penalty.
                    </p>
                    @if(isset($gracePeriodEndDate))
                        <p class="text-xs" style="color: var(--text-secondary);">
                            Grace period ends {{ $gracePeriodEndDate?->format('M d, Y') }}
                        </p>
                    @endif
                </div>

                <div class="p-4 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border-left: 3px solid var(--warning);">
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

            @if(!empty($paymentInstructions))
                <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05); border-left: 3px solid var(--success);">
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
    </div>

    {{-- ============================================================
         RECENT PAYMENT HISTORY
    ============================================================ --}}
    @if(isset($statistics['payment_history']) && count($statistics['payment_history']) > 0)
        <div class="card">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-history mr-2"></i> Recent Payment History
                </h3>

                <div class="space-y-3">
                    @foreach($statistics['payment_history'] as $payment)
                        <div class="flex items-center justify-between p-3 rounded-lg"
                             style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid var(--border-color);">
                            <div>
                                <p class="font-medium" style="color: var(--text-primary);">{{ $payment['period'] }}</p>
                                <p class="text-xs mt-0.5" style="color: var(--text-secondary);">Paid on {{ $payment['payment_date'] }}</p>
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
        </div>
    @endif
</div>

{{-- ============================================================
     REQUEST RECEIPT MODAL
============================================================ --}}
<div id="requestReceiptModal" class="modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Request Payment Receipt</h3>
            <button type="button" class="modal-close" onclick="closeReceiptModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="requestReceiptForm" method="POST" action="">
                @csrf
                <input type="hidden" name="invoice_id" id="receipt_invoice_id">

                <div class="space-y-4">
                    <div>
                        <label class="block mb-2 text-sm" style="color: var(--text-secondary);">Your Email</label>
                        <input type="email" name="email"
                               class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               value="{{ auth()->user()->email }}" required>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm" style="color: var(--text-secondary);">Additional Message (Optional)</label>
                        <textarea name="message" rows="3"
                                  class="w-full p-3 border rounded-lg transition-all duration-200 focus:border-primary focus:ring-2 focus:ring-primary/20"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Enter any additional message..."></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button"
                    class="flex-1 py-3 rounded-lg border transition-all duration-200"
                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                    onclick="closeReceiptModal()">Cancel</button>
            <button type="submit" form="requestReceiptForm"
                    class="flex-1 py-3 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-md"
                    style="background-color: var(--primary); color: white;">
                <i class="fas fa-paper-plane mr-2"></i> Request Receipt
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// ============================================
// GLOBAL VARIABLES
// ============================================
let selectAllHeader, selectAllFooter, invoiceCheckboxes, bulkExportBtn;

document.addEventListener('DOMContentLoaded', function () {
    // Auto-hide flash cards after 5s (matches dashboard pattern)
    const autoHideTargets = document.querySelectorAll('[data-auto-hide]');
    autoHideTargets.forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity = '0';
            setTimeout(() => el.style.display = 'none', 500);
        }, 5000);
    });

    // Row click navigation (skip checkbox + action buttons)
    const rows = document.querySelectorAll('tbody tr');
    rows.forEach(row => {
        row.addEventListener('click', function (e) {
            if (e.target.type === 'checkbox'
                || e.target.closest('.flex.gap-2')
                || e.target.closest('.select-invoice')) {
                return;
            }
            const invoiceLink = this.querySelector('a[href*="show"]');
            if (invoiceLink) window.location.href = invoiceLink.href;
        });
    });

    initSelectAll();

    // Close modal on overlay click
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('modal-overlay')) {
            const modal = e.target.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
    });

    // Close modal on Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal').forEach(modal => {
                if (modal.style.display === 'block') {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
        }
    });
});

// ============================================
// SELECT ALL / BULK EXPORT
// ============================================
function initSelectAll() {
    selectAllHeader = document.getElementById('selectAllHeader');
    selectAllFooter = document.getElementById('selectAll');
    invoiceCheckboxes = document.querySelectorAll('.select-invoice');
    bulkExportBtn = document.getElementById('bulkExportBtn');

    if (selectAllHeader) selectAllHeader.addEventListener('change', function () { handleSelectAll(this); });
    if (selectAllFooter) selectAllFooter.addEventListener('change', function () { handleSelectAll(this); });

    invoiceCheckboxes.forEach(cb => cb.addEventListener('change', updateBulkExportButton));
    updateBulkExportButton();
}

function handleSelectAll(checkbox) {
    invoiceCheckboxes.forEach(cb => cb.checked = checkbox.checked);
    updateBulkExportButton();
}

function updateBulkExportButton() {
    const checkedCount = document.querySelectorAll('.select-invoice:checked').length;
    if (bulkExportBtn) bulkExportBtn.disabled = checkedCount === 0;
}

// ============================================
// BULK EXPORT
// ============================================
function bulkExportPDF() {
    const selectedIds = Array.from(document.querySelectorAll('.select-invoice:checked'))
        .map(cb => cb.dataset.invoiceId);

    if (selectedIds.length === 0) {
        showToast('Please select at least one invoice to export.', 'error');
        return;
    }

    const exportBtn = document.getElementById('bulkExportBtn');
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Generating...';
    exportBtn.disabled = true;

    fetch('{{ route("tenant.invoices.bulk-export-pdf") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/zip, application/json'
        },
        body: JSON.stringify({ invoice_ids: selectedIds })
    })
    .then(response => {
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const ct = response.headers.get('content-type');
        if (ct && ct.includes('application/zip')) return response.blob();
        return response.json().then(data => {
            throw new Error(data.error || data.message || 'Export failed');
        });
    })
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
        a.download = `invoices_${timestamp}.zip`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        a.remove();
        showToast(`Successfully exported ${selectedIds.length} invoice(s)!`, 'success');
    })
    .catch(error => {
        console.error('Export error:', error);
        showToast(error.message || 'Failed to export PDFs. Please try again.', 'error');
    })
    .finally(() => {
        exportBtn.innerHTML = originalText;
        exportBtn.disabled = false;
    });
}

// ============================================
// DATE RANGE TOGGLE
// ============================================
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

// ============================================
// RECEIPT MODAL
// ============================================
function openReceiptModal(invoiceId) {
    const modal = document.getElementById('requestReceiptModal');
    const form = document.getElementById('requestReceiptForm');
    const invoiceIdInput = document.getElementById('receipt_invoice_id');

    invoiceIdInput.value = invoiceId;
    form.action = `/tenant/invoices/${invoiceId}/request-receipt`;

    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeReceiptModal() {
    const modal = document.getElementById('requestReceiptModal');
    modal.style.display = 'none';
    document.getElementById('requestReceiptForm').reset();
    document.body.style.overflow = 'auto';
}

// ============================================
// TOAST
// ============================================
function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed top-4 right-4 z-50 space-y-3';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = 'flex items-center p-4 rounded-lg shadow-lg transform transition-all duration-300 translate-x-full';

    const colors = {
        success: { bg: 'rgba(var(--success-rgb), 0.9)', text: 'white', icon: 'fa-check-circle' },
        error:   { bg: 'rgba(var(--danger-rgb), 0.9)',  text: 'white', icon: 'fa-exclamation-circle' },
        warning: { bg: 'rgba(var(--warning-rgb), 0.9)', text: 'white', icon: 'fa-exclamation-triangle' },
        info:    { bg: 'rgba(var(--info-rgb), 0.9)',    text: 'white', icon: 'fa-info-circle' }
    };

    const color = colors[type] || colors.info;
    toast.style.backgroundColor = color.bg;
    toast.style.color = color.text;

    toast.innerHTML = `
        <i class="fas ${color.icon} mr-3 text-lg"></i>
        <span class="flex-1">${message}</span>
        <button type="button" class="ml-4 text-lg opacity-70 hover:opacity-100" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;

    container.appendChild(toast);
    setTimeout(() => toast.style.transform = 'translateX(0)', 10);
    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}
</script>
@endpush

@push('styles')
<style>
/* Shared action button (matches developer billing dashboard) */
.action-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
}
.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Modal system (matches developer billing dashboard) */
.modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999; }
.modal-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
}
.modal-container {
    position: relative;
    background-color: var(--card-bg);
    border-radius: 16px;
    margin: 2rem auto;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    border: 1px solid var(--border-color);
    animation: modalFadeIn 0.3s ease-out;
}
.modal-header {
    padding: 1.5rem 1.5rem 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}
.modal-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 1.25rem;
    padding: 0.25rem;
    border-radius: 6px;
    transition: all 0.2s ease;
}
.modal-close:hover {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--text-primary);
}
.modal-body { padding: 1.5rem; }
.modal-footer {
    padding: 1rem 1.5rem 1.5rem 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    gap: 1rem;
}

@keyframes modalFadeIn {
    from { opacity: 0; transform: scale(0.9) translateY(-20px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}
.fa-spinner { animation: spin 1s linear infinite; }

/* Table styles */
table { border-collapse: separate; border-spacing: 0; }
tbody tr { cursor: pointer; }
tbody tr:hover { background-color: rgba(var(--primary-rgb), 0.04); }

/* Checkbox */
input[type="checkbox"] {
    cursor: pointer;
    width: 16px;
    height: 16px;
}

/* Responsive */
@media (max-width: 768px) {
    .modal-container { margin: 1rem; width: calc(100% - 2rem); }
    .modal-footer { flex-direction: column; }
    .overflow-x-auto { margin: 0 -1rem; }
    table { min-width: 1000px; }
}
</style>
@endpush