@extends('layouts.app')

@section('title', 'Year-End Archive Management')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div class="flex items-center mb-4 md:mb-0">
                <i class="fas fa-archive text-2xl mr-3" style="color: var(--primary);"></i>
                <div>
                    <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Year-End Archive Management</h2>
                    <p class="text-sm" style="color: var(--text-secondary);">Manage automatic archiving of paid invoices from previous years</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.tenant-invoices.index') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Invoices
                </a>
                <a href="{{ route('admin.tenant-invoices.trash') }}" class="btn-info flex items-center">
                    <i class="fas fa-trash-restore mr-2"></i> View Trash
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

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="card p-4" style="background-color: rgba(var(--primary-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Invoices from {{ $stats['year'] ?? now()->subYear()->year }}</div>
                    <div class="text-2xl font-semibold" style="color: var(--text-primary);">{{ number_format($stats['total_invoices'] ?? 0) }}</div>
                </div>
                <i class="fas fa-file-invoice text-2xl opacity-70" style="color: var(--primary);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--success-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Paid (Will Archive)</div>
                    <div class="text-2xl font-semibold" style="color: var(--success);">{{ number_format($stats['paid_invoices'] ?? 0) }}</div>
                </div>
                <i class="fas fa-check-circle text-2xl opacity-70" style="color: var(--success);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--warning-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Unpaid (Keep Active)</div>
                    <div class="text-2xl font-semibold" style="color: var(--warning);">{{ number_format($stats['unpaid_invoices'] ?? 0) }}</div>
                </div>
                <i class="fas fa-clock text-2xl opacity-70" style="color: var(--warning);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Post-Payment Archived</div>
                    <div class="text-2xl font-semibold" style="color: var(--info);">{{ number_format($stats['post_payment_archived'] ?? 0) }}</div>
                </div>
                <i class="fas fa-archive text-2xl opacity-70" style="color: var(--info);"></i>
            </div>
        </div>
    </div>

    <!-- Archive Process Card -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Year-End Archive Process</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="border rounded-lg p-4" style="border-color: var(--border-color);">
                <div class="flex items-center mb-2">
                    <i class="fas fa-calendar-alt text-blue-500 mr-2"></i>
                    <span class="font-medium" style="color: var(--text-primary);">Next Archive Date</span>
                </div>
                <p class="text-2xl font-bold" style="color: var(--primary);">{{ $stats['next_archive_date'] ?? 'January 15, 2026' }}</p>
                <p class="text-sm mt-2" style="color: var(--text-secondary);">
                    All paid invoices from {{ now()->year }} will be archived
                </p>
            </div>
            
            <div class="border rounded-lg p-4" style="border-color: var(--border-color);">
                <div class="flex items-center mb-2">
                    <i class="fas fa-hourglass-half text-yellow-500 mr-2"></i>
                    <span class="font-medium" style="color: var(--text-primary);">Retention Period</span>
                </div>
                <p class="text-2xl font-bold" style="color: var(--warning);">{{ $stats['retention_period_months'] ?? 3 }} months</p>
                <p class="text-sm mt-2" style="color: var(--text-secondary);">
                    Paid invoices kept before post-payment archiving
                </p>
            </div>
            
            <div class="border rounded-lg p-4" style="border-color: var(--border-color);">
                <div class="flex items-center mb-2">
                    <i class="fas fa-chart-line text-green-500 mr-2"></i>
                    <span class="font-medium" style="color: var(--text-primary);">Collection Rate</span>
                </div>
                <p class="text-2xl font-bold" style="color: var(--success);">
                    {{ $stats['total_invoices'] > 0 ? round(($stats['paid_invoices'] / $stats['total_invoices']) * 100, 1) : 0 }}%
                </p>
                <p class="text-sm mt-2" style="color: var(--text-secondary);">
                    {{ number_format($stats['paid_invoices'] ?? 0) }} of {{ number_format($stats['total_invoices'] ?? 0) }} invoices paid
                </p>
            </div>
        </div>

        <!-- Timeline Visualization -->
        <div class="mb-6">
            <p class="text-sm font-medium mb-3" style="color: var(--text-secondary);">Archive Timeline</p>
            <div class="relative">
                <div class="flex justify-between mb-2">
                    <div class="text-center flex-1">
                        <div class="font-bold" style="color: var(--text-primary);">{{ now()->subYear()->year }}</div>
                        <div class="text-xs" style="color: var(--text-secondary);">All invoices created</div>
                    </div>
                    <div class="text-center flex-1">
                        <div class="font-bold" style="color: var(--text-primary);">Jan {{ now()->year }}</div>
                        <div class="text-xs" style="color: var(--text-secondary);">Year-end archive runs</div>
                    </div>
                    <div class="text-center flex-1">
                        <div class="font-bold" style="color: var(--text-primary);">Paid → Archive</div>
                        <div class="text-xs" style="color: var(--text-secondary);">After {{ $stats['retention_period_months'] ?? 3 }} months</div>
                    </div>
                </div>
                
                <div class="flex items-center">
                    <div class="flex-1 h-2 rounded-l" style="background-color: var(--success);"></div>
                    <div class="flex-1 h-2" style="background-color: var(--warning);"></div>
                    <div class="flex-1 h-2 rounded-r" style="background-color: var(--info);"></div>
                </div>
                
                <div class="flex justify-between mt-4">
                    <div class="text-center flex-1">
                        <span class="text-xs" style="color: var(--text-secondary);">📄 Active Invoices</span>
                        <div class="font-semibold" style="color: var(--text-primary);">{{ number_format($stats['total_invoices'] ?? 0) }}</div>
                    </div>
                    <div class="text-center flex-1">
                        <span class="text-xs" style="color: var(--text-secondary);">📦 Paid → Archive</span>
                        <div class="font-semibold" style="color: var(--success);">{{ number_format($stats['paid_invoices'] ?? 0) }}</div>
                    </div>
                    <div class="text-center flex-1">
                        <span class="text-xs" style="color: var(--text-secondary);">💰 Unpaid Kept</span>
                        <div class="font-semibold" style="color: var(--warning);">{{ number_format($stats['unpaid_invoices'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap gap-4 mt-4">
            <form action="{{ route('admin.tenant-invoices.process-year-end') }}" method="POST" class="inline">
                @csrf
                <input type="hidden" name="year" value="{{ now()->subYear()->year }}">
                <button type="submit" 
                        onclick="return confirm('⚠️ WARNING: This will archive all paid invoices from {{ now()->subYear()->year }}.\n\n- {{ number_format($stats['paid_invoices'] ?? 0) }} paid invoices will be moved to trash\n- {{ number_format($stats['unpaid_invoices'] ?? 0) }} unpaid invoices will remain active\n\nThis action cannot be undone.\n\nAre you sure you want to continue?')"
                        class="btn-primary flex items-center">
                    <i class="fas fa-archive mr-2"></i> Archive {{ now()->subYear()->year }} Paid Invoices
                </button>
            </form>
            
            <form action="{{ route('admin.tenant-invoices.send-year-end-reminders') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="btn-secondary flex items-center">
                    <i class="fas fa-bell mr-2"></i> Send Archive Reminders
                </button>
            </form>
            
            <a href="{{ route('admin.tenant-invoices.unpaid-previous-years') }}" class="btn-info flex items-center">
                <i class="fas fa-exclamation-triangle mr-2"></i> View Unpaid from Previous Years
            </a>
            
            <button type="button" onclick="openPostPaymentPreview()" class="btn-success flex items-center">
                <i class="fas fa-eye mr-2"></i> Preview Post-Payment Archive
            </button>
        </div>
    </div>

    <!-- Unpaid Invoices Section -->
    @if(isset($unpaidInvoices) && $unpaidInvoices->count() > 0)
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
            Unpaid Invoices from {{ now()->subYear()->year }} (Will Remain Active)
        </h3>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color);">
                        <th class="p-3 text-left font-medium" style="color: var(--text-secondary);">Invoice #</th>
                        <th class="p-3 text-left font-medium" style="color: var(--text-secondary);">Tenant</th>
                        <th class="p-3 text-left font-medium" style="color: var(--text-secondary);">Period</th>
                        <th class="p-3 text-left font-medium" style="color: var(--text-secondary);">Due Date</th>
                        <th class="p-3 text-left font-medium" style="color: var(--text-secondary);">Amount</th>
                        <th class="p-3 text-left font-medium" style="color: var(--text-secondary);">Status</th>
                        <th class="p-3 text-left font-medium" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($unpaidInvoices as $invoice)
                    <tr class="border-b" style="border-color: var(--border-color);">
                        <td class="p-3">
                            <span class="font-medium" style="color: var(--text-primary);">#{{ $invoice->invoice_number }}</span>
                        </td>
                        <td class="p-3">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2" style="background-color: rgba(var(--warning-rgb), 0.2);">
                                    <span class="text-sm font-medium" style="color: var(--warning);">{{ substr($invoice->tenant->name ?? 'U', 0, 1) }}</span>
                                </div>
                                <div>
                                    <p class="font-medium" style="color: var(--text-primary);">{{ $invoice->tenant->name ?? 'Unknown' }}</p>
                                    <p class="text-xs" style="color: var(--text-secondary);">{{ $invoice->tenant->phone ?? 'No phone' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">{{ $invoice->month_name }}</p>
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">{{ $invoice->due_date->format('M d, Y') }}</p>
                            @if($invoice->isOverdue())
                            <p class="text-xs text-danger flex items-center mt-1">
                                <i class="fas fa-exclamation-circle mr-1"></i> {{ $invoice->due_date->diffForHumans() }}
                            </p>
                            @endif
                        </td>
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">{{ $system_settings->formatAmount($invoice->total_amount) }}</p>
                            @if($invoice->penalty_amount > 0)
                            <p class="text-xs text-danger">+ Penalty: {{ $system_settings->formatAmount($invoice->penalty_amount) }}</p>
                            @endif
                        </td>
                        <td class="p-3">
                            @php
                                $statusColors = [
                                    'pending' => 'warning',
                                    'overdue' => 'danger',
                                ];
                                $statusColor = $statusColors[$invoice->status] ?? 'secondary';
                            @endphp
                            <span class="px-2 py-1 rounded-full text-xs font-medium" 
                                  style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.2); color: var(--{{ $statusColor }});">
                                <i class="{{ $invoice->status_icon }} mr-1"></i>
                                {{ ucfirst($invoice->status) }}
                            </span>
                        </td>
                        <td class="p-3">
                            <a href="{{ route('admin.tenant-invoices.show', $invoice->id) }}" 
                               class="p-2 rounded hover:opacity-80 transition-opacity inline-block" 
                               style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);" 
                               title="View Details">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        @if($unpaidInvoices instanceof \Illuminate\Pagination\LengthAwarePaginator && $unpaidInvoices->hasPages())
        <div class="flex justify-between items-center mt-6">
            <div class="text-sm" style="color: var(--text-secondary);">
                Page {{ $unpaidInvoices->currentPage() }} of {{ $unpaidInvoices->lastPage() }}
            </div>
            <div class="flex space-x-2">
                {{ $unpaidInvoices->appends(request()->query())->links() }}
            </div>
        </div>
        @endif
    </div>
    @endif

    <!-- Archive Settings Card -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Archive Configuration</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-3">
                <div class="flex justify-between items-center p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <span style="color: var(--text-secondary);">Year-End Archive Enabled:</span>
                    <span class="font-medium" style="color: var(--success);">
                        <i class="fas fa-check-circle mr-1"></i> Yes
                    </span>
                </div>
                <div class="flex justify-between items-center p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <span style="color: var(--text-secondary);">Archive Date:</span>
                    <span class="font-medium" style="color: var(--text-primary);">January 15th</span>
                </div>
                <div class="flex justify-between items-center p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <span style="color: var(--text-secondary);">Archive Reminder Days:</span>
                    <span class="font-medium" style="color: var(--text-primary);">30 days before</span>
                </div>
            </div>
            <div class="space-y-3">
                <div class="flex justify-between items-center p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <span style="color: var(--text-secondary);">Post-Payment Retention:</span>
                    <span class="font-medium" style="color: var(--text-primary);">{{ $stats['retention_period_months'] ?? 3 }} months</span>
                </div>
                <div class="flex justify-between items-center p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <span style="color: var(--text-secondary);">Archive Notifications:</span>
                    <span class="font-medium" style="color: var(--success);">
                        <i class="fas fa-envelope mr-1"></i> Email & SMS
                    </span>
                </div>
                <div class="flex justify-between items-center p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <span style="color: var(--text-secondary);">Admin Reports:</span>
                    <span class="font-medium" style="color: var(--success);">
                        <i class="fas fa-chart-line mr-1"></i> Enabled
                    </span>
                </div>
            </div>
        </div>
        
        <div class="mt-4 p-4 rounded" style="background-color: rgba(var(--warning-rgb), 0.1);">
            <div class="flex items-start">
                <i class="fas fa-info-circle mt-1 mr-3" style="color: var(--warning);"></i>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-primary);">How Year-End Archiving Works</p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        • All invoices from the previous year remain in the active table until the archive date (January 15th)<br>
                        • On the archive date, paid invoices are moved to the trash/archive table<br>
                        • Unpaid invoices stay in the active table and can still be paid<br>
                        • After payment, unpaid invoices wait {{ $stats['retention_period_months'] ?? 3 }} months before automatic archiving<br>
                        • Tenants receive reminders before archiving to ensure they can review their invoices
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Archive Logs Card -->
    @if(isset($archiveLogs) && $archiveLogs->count() > 0)
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Recent Archive Activity</h3>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color);">
                        <th class="p-3 text-left font-medium" style="color: var(--text-secondary);">Date</th>
                        <th class="p-3 text-left font-medium" style="color: var(--text-secondary);">Action</th>
                        <th class="p-3 text-left font-medium" style="color: var(--text-secondary);">Invoice</th>
                        <th class="p-3 text-left font-medium" style="color: var(--text-secondary);">Tenant</th>
                        <th class="p-3 text-left font-medium" style="color: var(--text-secondary);">Amount</th>
                        <th class="p-3 text-left font-medium" style="color: var(--text-secondary);">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($archiveLogs as $log)
                    <tr class="border-b" style="border-color: var(--border-color);">
                        <td class="p-3">
                            <span style="color: var(--text-primary);">{{ $log->created_at->format('M d, Y H:i') }}</span>
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                                {{ ucfirst(str_replace('_', ' ', $log->action ?? $log->archive_type)) }}
                            </span>
                        </td>
                        <td class="p-3">
                            <span class="font-medium" style="color: var(--text-primary);">#{{ $log->invoice_number }}</span>
                        </td>
                        <td class="p-3" style="color: var(--text-primary);">{{ $log->tenant_name }}</td>
                        <td class="p-3" style="color: var(--text-primary);">{{ $system_settings->formatAmount($log->total_amount) }}</td>
                        <td class="p-3">
                            @php
                                $statusClass = $log->status === 'success' ? 'success' : ($log->status === 'pending' ? 'warning' : 'danger');
                            @endphp
                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--{{ $statusClass }}-rgb), 0.2); color: var(--{{ $statusClass }});">
                                {{ ucfirst($log->status ?? 'Completed') }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        @if($archiveLogs instanceof \Illuminate\Pagination\LengthAwarePaginator && $archiveLogs->hasPages())
        <div class="flex justify-between items-center mt-6">
            <div class="text-sm" style="color: var(--text-secondary);">
                Page {{ $archiveLogs->currentPage() }} of {{ $archiveLogs->lastPage() }}
            </div>
            <div class="flex space-x-2">
                {{ $archiveLogs->appends(request()->query())->links() }}
            </div>
        </div>
        @endif
    </div>
    @endif
</div>

<!-- Post-Payment Archive Preview Modal -->
<div id="postPaymentPreviewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-4xl w-full max-h-[80vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Post-Payment Archive Preview</h3>
                <button type="button" onclick="closePostPaymentPreview()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div id="previewContent" class="space-y-4">
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-3xl" style="color: var(--primary);"></i>
                    <p class="mt-2" style="color: var(--text-secondary);">Loading preview data...</p>
                </div>
            </div>
            
            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="closePostPaymentPreview()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    Close
                </button>
                <form action="{{ route('admin.tenant-invoices.process-post-payment') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" onclick="return confirm('Archive these paid invoices after retention period?')" class="px-4 py-2 rounded text-white" style="background-color: var(--success);">
                        <i class="fas fa-archive mr-2"></i> Archive Now
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide messages after 5 seconds
    const messages = document.querySelectorAll('.bg-green-100, .bg-red-100, .bg-yellow-100');
    messages.forEach(message => {
        setTimeout(() => {
            message.style.transition = 'opacity 0.5s';
            message.style.opacity = '0';
            setTimeout(() => {
                message.style.display = 'none';
            }, 500);
        }, 5000);
    });
});

function openPostPaymentPreview() {
    const modal = document.getElementById('postPaymentPreviewModal');
    const content = document.getElementById('previewContent');
    modal.classList.remove('hidden');
    
    // Fetch preview data
    fetch('{{ route("admin.tenant-invoices.preview-post-payment") }}')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.invoices && data.invoices.length > 0) {
                let html = `
                    <div class="p-4 rounded mb-4" style="background-color: rgba(var(--info-rgb), 0.1);">
                        <p class="font-medium">📊 Summary</p>
                        <p class="text-sm mt-1">Found ${data.invoices.length} invoices eligible for post-payment archiving</p>
                        <p class="text-sm">Total Amount: ${data.formatted_total || '₵0.00'}</p>
                        <p class="text-xs mt-2" style="color: var(--text-secondary);">These invoices were paid after year-end archiving and have passed the ${data.retention_months || 3}-month retention period.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b" style="border-color: var(--border-color);">
                                    <th class="p-2 text-left">Invoice #</th>
                                    <th class="p-2 text-left">Tenant</th>
                                    <th class="p-2 text-left">Original Year</th>
                                    <th class="p-2 text-left">Payment Date</th>
                                    <th class="p-2 text-left">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                data.invoices.forEach(invoice => {
                    html += `
                        <tr class="border-b" style="border-color: var(--border-color);">
                            <td class="p-2">#${invoice.invoice_number}</td>
                            <td class="p-2">${invoice.tenant_name}</td>
                            <td class="p-2">${invoice.original_year || 'N/A'}</td>
                            <td class="p-2">${invoice.payment_date}</td>
                            <td class="p-2">${invoice.formatted_amount}</td>
                        </tr>
                    `;
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
                content.innerHTML = html;
            } else {
                content.innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-check-circle text-5xl mb-3" style="color: var(--success);"></i>
                        <p class="text-lg font-medium" style="color: var(--text-primary);">No invoices eligible for post-payment archiving</p>
                        <p class="text-sm mt-2" style="color: var(--text-secondary);">All paid invoices are either within the retention period or already archived.</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            content.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-5xl mb-3" style="color: var(--danger);"></i>
                    <p class="text-lg font-medium" style="color: var(--text-primary);">Failed to load preview</p>
                    <p class="text-sm mt-2" style="color: var(--text-secondary);">Please try again later.</p>
                </div>
            `;
            console.error('Error loading preview:', error);
        });
}

function closePostPaymentPreview() {
    const modal = document.getElementById('postPaymentPreviewModal');
    modal.classList.add('hidden');
    document.getElementById('previewContent').innerHTML = `
        <div class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-3xl" style="color: var(--primary);"></i>
            <p class="mt-2" style="color: var(--text-secondary);">Loading preview data...</p>
        </div>
    `;
}

// Close modal when clicking outside
document.getElementById('postPaymentPreviewModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closePostPaymentPreview();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('postPaymentPreviewModal');
        if (modal && !modal.classList.contains('hidden')) {
            closePostPaymentPreview();
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

.btn-success {
    background-color: var(--success);
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

.btn-success:hover {
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
    cursor: pointer;
}

.btn-danger:hover {
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

/* Modal styles */
#postPaymentPreviewModal {
    transition: opacity 0.3s ease;
}

#postPaymentPreviewModal.hidden {
    opacity: 0;
    pointer-events: none;
}

#postPaymentPreviewModal:not(.hidden) {
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

/* Table styles */
table {
    border-collapse: separate;
    border-spacing: 0;
}

/* Hover effects */
tr:hover td {
    background-color: rgba(var(--primary-rgb), 0.05);
}
</style>
@endsection