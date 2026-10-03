@extends('layouts.tenant')

@section('title', 'Maintenance Requests')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Maintenance Requests</h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Unit {{ $unit->unit_number ?? '' }} - {{ $unit->property->property_name ?? $unit->property->street_name ?? '' }}
                </p>
            </div>
            
            <div class="flex space-x-2 mt-4 md:mt-0">
                <a href="{{ route('tenant.maintenance.create') }}" class="px-4 py-2 rounded flex items-center" style="background-color: var(--primary); color: white;">
                    <i class="fas fa-plus-circle mr-2"></i> New Request
                </a>
                <a href="{{ route('tenant.maintenance.trashed') }}" class="px-4 py-2 rounded flex items-center" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-trash-alt mr-2"></i> Trash
                    @php
                        $trashedCount = \App\Models\MaintenanceRequest::onlyTrashed()->where('unit_id', $unit->id)->count();
                    @endphp
                    @if($trashedCount > 0)
                        <span class="ml-1 bg-red-500 text-white text-xs px-2 py-1 rounded-full">{{ $trashedCount }}</span>
                    @endif
                </a>
                <button onclick="openExportModal()" class="px-4 py-2 rounded flex items-center" style="background-color: #dc2626; color: white;">
                    <i class="fas fa-file-pdf mr-2"></i> Export
                </button>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div class="card p-4" style="background-color: rgba(var(--primary-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Requests</div>
                    <div class="text-2xl font-semibold" style="color: var(--text-primary);">{{ $stats['total'] ?? 0 }}</div>
                </div>
                <i class="fas fa-clipboard-list text-2xl opacity-70" style="color: var(--primary);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--warning-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Pending</div>
                    <div class="text-2xl font-semibold" style="color: var(--warning);">{{ $stats['pending'] ?? 0 }}</div>
                </div>
                <i class="fas fa-clock text-2xl opacity-70" style="color: var(--warning);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">In Progress</div>
                    <div class="text-2xl font-semibold" style="color: var(--info);">{{ $stats['in_progress'] ?? 0 }}</div>
                </div>
                <i class="fas fa-spinner text-2xl opacity-70" style="color: var(--info);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--success-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Completed</div>
                    <div class="text-2xl font-semibold" style="color: var(--success);">{{ $stats['completed'] ?? 0 }}</div>
                </div>
                <i class="fas fa-check-circle text-2xl opacity-70" style="color: var(--success);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--danger-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Urgent</div>
                    <div class="text-2xl font-semibold" style="color: var(--danger);">{{ $stats['urgent'] ?? 0 }}</div>
                </div>
                <i class="fas fa-exclamation-triangle text-2xl opacity-70" style="color: var(--danger);"></i>
            </div>
        </div>
    </div>

    <!-- Info Banner -->
    <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.05);">
        <div class="flex items-start">
            <i class="fas fa-info-circle mr-3 mt-1" style="color: var(--info);"></i>
            <div class="text-sm" style="color: var(--text-secondary);">
                <strong class="font-semibold" style="color: var(--text-primary);">Maintenance Request Guidelines:</strong>
                <ul class="mt-1 space-y-1">
                    <li>• Please provide clear description of the issue</li>
                    <li>• Upload photos if possible for better understanding</li>
                    <li>• Urgent requests will be prioritized</li>
                    <li>• You will be notified when your request is updated</li>
                    <li>• Completed or cancelled requests can be moved to trash</li>
                </ul>
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

    <!-- Filters Card -->
    <div class="card p-6">
        <form method="GET" action="{{ route('tenant.maintenance.index') }}" id="filterForm" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm mb-2" style="color: var(--text-secondary);">Status</label>
                <select name="status" class="w-full p-2 border rounded" style="border-color: var(--border-color); background-color: var(--bg-primary); color: var(--text-primary);">
                    <option value="">All Statuses</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="assigned" {{ request('status') == 'assigned' ? 'selected' : '' }}>Assigned</option>
                    <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>

            <div>
                <label class="block text-sm mb-2" style="color: var(--text-secondary);">Priority</label>
                <select name="priority" class="w-full p-2 border rounded" style="border-color: var(--border-color); background-color: var(--bg-primary); color: var(--text-primary);">
                    <option value="">All Priorities</option>
                    <option value="low" {{ request('priority') == 'low' ? 'selected' : '' }}>Low</option>
                    <option value="medium" {{ request('priority') == 'medium' ? 'selected' : '' }}>Medium</option>
                    <option value="high" {{ request('priority') == 'high' ? 'selected' : '' }}>High</option>
                    <option value="urgent" {{ request('priority') == 'urgent' ? 'selected' : '' }}>Urgent</option>
                </select>
            </div>

            <div>
                <label class="block text-sm mb-2" style="color: var(--text-secondary);">Category</label>
                <select name="category" class="w-full p-2 border rounded" style="border-color: var(--border-color); background-color: var(--bg-primary); color: var(--text-primary);">
                    <option value="">All Categories</option>
                    <option value="plumbing" {{ request('category') == 'plumbing' ? 'selected' : '' }}>Plumbing</option>
                    <option value="electrical" {{ request('category') == 'electrical' ? 'selected' : '' }}>Electrical</option>
                    <option value="appliance" {{ request('category') == 'appliance' ? 'selected' : '' }}>Appliance</option>
                    <option value="structural" {{ request('category') == 'structural' ? 'selected' : '' }}>Structural</option>
                    <option value="cleaning" {{ request('category') == 'cleaning' ? 'selected' : '' }}>Cleaning</option>
                    <option value="other" {{ request('category') == 'other' ? 'selected' : '' }}>Other</option>
                </select>
            </div>

            <div class="flex items-end space-x-2">
                <button type="submit" class="px-4 py-2 rounded flex-1" style="background-color: var(--primary); color: white;">
                    <i class="fas fa-filter mr-2"></i> Filter
                </button>
                <a href="{{ route('tenant.maintenance.index') }}" class="px-4 py-2 rounded" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                    <i class="fas fa-redo"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Results Count -->
    <div class="card p-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
            <p class="text-sm" style="color: var(--text-secondary);">
                Showing {{ $requests->firstItem() ?? 0 }} to {{ $requests->lastItem() ?? 0 }} of {{ $requests->total() }} results
            </p>
            
            <div class="flex space-x-2 mt-2 md:mt-0">
                <button onclick="loadRequests()" class="text-sm px-3 py-1 rounded" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Loading Indicator -->
        <div id="loadingIndicator" class="hidden mb-4">
            <div class="flex items-center justify-center p-4">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2" style="border-color: var(--primary);"></div>
                <span class="ml-3 text-sm" style="color: var(--text-secondary);">Loading...</span>
            </div>
        </div>

        <!-- Requests Table -->
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color);">
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">ID</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Title</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Category</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Priority</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Created</th>
                        <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody id="requestsTableBody">
                    @forelse($requests as $request)
                    <tr class="border-b request-row" style="border-color: var(--border-color);" 
                        data-request-id="{{ $request->id }}"
                        data-status="{{ $request->status }}"
                        data-priority="{{ $request->priority }}">
                        
                        <td class="p-3">
                            <p class="font-medium" style="color: var(--text-primary);">#{{ $request->id }}</p>
                        </td>
                        
                        <td class="p-3">
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center" 
                                     style="background-color: rgba(var(--primary-rgb), 0.1);">
                                    <i class="fas fa-tools text-sm" style="color: var(--primary);"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-sm" style="color: var(--text-primary);">{{ $request->title }}</p>
                                    <p class="text-xs" style="color: var(--text-secondary);">{{ Str::limit($request->description, 50) }}</p>
                                </div>
                            </div>
                        </td>
                        
                        <td class="p-3">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs" 
                                  style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                <i class="fas fa-tag mr-1"></i>
                                {{ ucfirst($request->category) }}
                            </span>
                        </td>
                        
                        <td class="p-3">
                            @php
                                $priorityColors = [
                                    'low' => ['bg' => 'success', 'icon' => 'arrow-down'],
                                    'medium' => ['bg' => 'warning', 'icon' => 'minus'],
                                    'high' => ['bg' => 'orange', 'icon' => 'arrow-up'],
                                    'urgent' => ['bg' => 'danger', 'icon' => 'exclamation']
                                ];
                                $priorityConfig = $priorityColors[$request->priority] ?? ['bg' => 'secondary', 'icon' => 'circle'];
                            @endphp
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs" 
                                  style="background-color: rgba(var(--{{ $priorityConfig['bg'] }}-rgb), 0.2); color: var(--{{ $priorityConfig['bg'] }});">
                                <i class="fas fa-{{ $priorityConfig['icon'] }} mr-1"></i>
                                {{ ucfirst($request->priority) }}
                            </span>
                        </td>
                        
                        <td class="p-3">
                            @php
                                $statusColors = [
                                    'pending' => ['bg' => 'warning', 'icon' => 'clock'],
                                    'assigned' => ['bg' => 'info', 'icon' => 'user-check'],
                                    'in_progress' => ['bg' => 'primary', 'icon' => 'spinner'],
                                    'completed' => ['bg' => 'success', 'icon' => 'check-circle'],
                                    'cancelled' => ['bg' => 'danger', 'icon' => 'times-circle']
                                ];
                                $statusConfig = $statusColors[$request->status] ?? ['bg' => 'secondary', 'icon' => 'circle'];
                            @endphp
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs" 
                                  style="background-color: rgba(var(--{{ $statusConfig['bg'] }}-rgb), 0.2); color: var(--{{ $statusConfig['bg'] }});">
                                <i class="fas fa-{{ $statusConfig['icon'] }} mr-1"></i>
                                {{ ucfirst(str_replace('_', ' ', $request->status)) }}
                            </span>
                        </td>
                        
                        <td class="p-3">
                            <p class="text-sm" style="color: var(--text-secondary);">
                                {{ $request->created_at->format('M d, Y') }}
                            </p>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                {{ $request->created_at->diffForHumans() }}
                            </p>
                        </td>
                        
                        <td class="p-3">
                            <div class="flex space-x-2 flex-wrap gap-1">
                                <a href="{{ route('tenant.maintenance.show', $request->id) }}" 
                                   class="p-2 rounded" 
                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);" 
                                   title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                @if(in_array($request->status, ['pending']))
                                    <button onclick="cancelRequest({{ $request->id }})" 
                                            class="p-2 rounded" 
                                            style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);" 
                                            title="Cancel Request">
                                        <i class="fas fa-times"></i>
                                    </button>
                                @endif
                                
                                @if(in_array($request->status, ['completed', 'cancelled']))
                                    <button onclick="deleteRequest({{ $request->id }})" 
                                            class="p-2 rounded" 
                                            style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);" 
                                            title="Move to Trash">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                @endif
                                
                                <button onclick="exportSinglePDF({{ $request->id }})" 
                                        class="p-2 rounded" 
                                        style="background-color: rgba(var(--danger-rgb), 0.1); color: #dc2626;" 
                                        title="Export PDF">
                                    <i class="fas fa-file-pdf"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center">
                            <div class="flex flex-col items-center justify-center" style="color: var(--text-secondary);">
                                <i class="fas fa-clipboard-list text-4xl mb-4 opacity-50"></i>
                                <p class="text-lg font-medium mb-2">No maintenance requests found</p>
                                <p class="text-sm">You haven't submitted any maintenance requests yet.</p>
                                <a href="{{ route('tenant.maintenance.create') }}" class="mt-4 px-4 py-2 rounded" style="background-color: var(--primary); color: white;">
                                    <i class="fas fa-plus-circle mr-2"></i> Submit First Request
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($requests->hasPages())
        <div class="mt-6">
            {{ $requests->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Export PDF Modal -->
<div id="exportModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Export Requests as PDF</h3>
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
                                Export your maintenance requests as a PDF document.
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
                                <p class="text-xs" style="color: var(--text-secondary);">Export only requests visible on this page ({{ $requests->count() }} requests)</p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </button>
                    
                    <button onclick="exportAllRequests()" class="w-full p-3 border rounded-lg flex items-center justify-between transition-colors" style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                        <div class="flex items-center">
                            <i class="fas fa-database text-blue-500 text-xl mr-3"></i>
                            <div class="text-left">
                                <p class="font-medium">Export All Requests</p>
                                <p class="text-xs" style="color: var(--text-secondary);">Export all your maintenance requests ({{ $requests->total() }} total)</p>
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

<!-- Cancel Confirmation Modal -->
<div id="cancelModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <div class="flex items-center mb-4">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-2xl" style="color: var(--warning);"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Cancel Request</h3>
                </div>
            </div>
            
            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                Are you sure you want to cancel this maintenance request? This action cannot be undone.
            </p>
            
            <form id="cancelForm" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeCancelModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        Keep Request
                    </button>
                    <button type="submit" class="px-4 py-2 rounded" style="background-color: var(--danger); color: white;">
                        Yes, Cancel Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ✅ NEW: Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <div class="flex items-center mb-4">
                <div class="flex-shrink-0">
                    <i class="fas fa-trash-alt text-2xl" style="color: var(--warning);"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Move to Trash</h3>
                </div>
            </div>
            
            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                Are you sure you want to move this maintenance request to trash? 
                You can permanently delete it from the trash section.
            </p>
            
            <form id="deleteForm" method="POST" action="">
                @csrf
                @method('DELETE')
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded" style="background-color: var(--warning); color: white;">
                        Yes, Move to Trash
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden forms for PDF export -->
<form id="currentPageExportForm" method="GET" action="{{ route('tenant.maintenance.export-current-page') }}" target="_blank">
    @foreach(request()->all() as $key => $value)
        @if($key != '_token' && $key != 'page')
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach
</form>

<form id="allRequestsExportForm" method="GET" action="{{ route('tenant.maintenance.export-all') }}" target="_blank">
    @foreach(request()->all() as $key => $value)
        @if($key != '_token' && $key != 'page')
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach
</form>

<form id="singleExportForm" method="GET" action="{{ route('tenant.maintenance.export-single', ['id' => ':id']) }}" target="_blank"></form>

@endsection

@section('scripts')
<script>
let requestIdToCancel = null;
let requestIdToDelete = null;

document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide messages after 5 seconds
    const messages = document.querySelectorAll('.bg-green-100, .bg-red-100');
    messages.forEach(message => {
        setTimeout(() => {
            message.style.display = 'none';
        }, 5000);
    });

    // Load requests periodically
    setTimeout(loadRequests, 3000);
});

// ==================== EXPORT FUNCTIONS ====================

function openExportModal() {
    document.getElementById('exportModal').classList.remove('hidden');
}

function closeExportModal() {
    document.getElementById('exportModal').classList.add('hidden');
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

function exportAllRequests() {
    closeExportModal();
    showLoadingModal();
    
    const form = document.getElementById('allRequestsExportForm');
    if (form) form.submit();
    
    setTimeout(hideLoadingModal, 3000);
}

function exportSinglePDF(requestId) {
    showLoadingModal();
    
    const form = document.getElementById('singleExportForm');
    if (form) {
        form.action = form.action.replace(':id', requestId);
        form.submit();
    }
    
    setTimeout(hideLoadingModal, 2000);
}

// ==================== CANCEL FUNCTIONS ====================

function cancelRequest(requestId) {
    requestIdToCancel = requestId;
    const modal = document.getElementById('cancelModal');
    const form = document.getElementById('cancelForm');
    
    if (modal && form) {
        form.action = '{{ route("tenant.maintenance.cancel", ["id" => ":id"]) }}'.replace(':id', requestId);
        modal.classList.remove('hidden');
    }
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.add('hidden');
    requestIdToCancel = null;
}

// ==================== DELETE FUNCTIONS ====================

function deleteRequest(requestId) {
    requestIdToDelete = requestId;
    const modal = document.getElementById('deleteModal');
    const form = document.getElementById('deleteForm');
    
    if (modal && form) {
        form.action = '{{ route("tenant.maintenance.destroy", ["id" => ":id"]) }}'.replace(':id', requestId);
        modal.classList.remove('hidden');
    }
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    requestIdToDelete = null;
}

// ==================== API FUNCTIONS ====================

function loadRequests() {
    const loadingIndicator = document.getElementById('loadingIndicator');
    
    if (loadingIndicator) loadingIndicator.classList.remove('hidden');
    
    const status = document.querySelector('select[name="status"]')?.value || '';
    const priority = document.querySelector('select[name="priority"]')?.value || '';
    const category = document.querySelector('select[name="category"]')?.value || '';
    
    let url = '{{ route("tenant.maintenance.api.requests") }}';
    const params = new URLSearchParams();
    if (status) params.append('status', status);
    if (priority) params.append('priority', priority);
    if (category) params.append('category', category);
    if (params.toString()) url += '?' + params.toString();
    
    fetch(url)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                updateStatistics(data.stats);
                updateRequests(data.requests);
            }
        })
        .catch(error => console.error('Error:', error))
        .finally(() => {
            if (loadingIndicator) loadingIndicator.classList.add('hidden');
        });
}

function updateStatistics(stats) {
    if (!stats) return;
    
    const totalElement = document.querySelector('.card.p-4:first-child .text-2xl');
    if (totalElement && stats.total !== undefined) totalElement.textContent = stats.total;
    
    const pendingElement = document.querySelector('.card.p-4:nth-child(2) .text-2xl');
    if (pendingElement && stats.pending !== undefined) pendingElement.textContent = stats.pending;
    
    const inProgressElement = document.querySelector('.card.p-4:nth-child(3) .text-2xl');
    if (inProgressElement && stats.in_progress !== undefined) inProgressElement.textContent = stats.in_progress;
    
    const completedElement = document.querySelector('.card.p-4:nth-child(4) .text-2xl');
    if (completedElement && stats.completed !== undefined) completedElement.textContent = stats.completed;
    
    const urgentElement = document.querySelector('.card.p-4:nth-child(5) .text-2xl');
    if (urgentElement && stats.urgent !== undefined) urgentElement.textContent = stats.urgent;
}

function updateRequests(requests) {
    const tbody = document.getElementById('requestsTableBody');
    if (!tbody || !requests || requests.length === 0) {
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="p-8 text-center">
                        <div class="flex flex-col items-center justify-center" style="color: var(--text-secondary);">
                            <i class="fas fa-clipboard-list text-4xl mb-4 opacity-50"></i>
                            <p class="text-lg font-medium mb-2">No requests found</p>
                            <p class="text-sm">Try adjusting your filters.</p>
                        </div>
                    </td>
                </tr>
            `;
        }
        return;
    }
    
    // Update the table with new data
    // You can implement this to dynamically update the table
}

// ==================== MODAL CLOSE HANDLERS ====================

document.getElementById('exportModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeExportModal();
});

document.getElementById('pdfLoadingModal')?.addEventListener('click', function(e) {
    if (e.target === this) hideLoadingModal();
});

document.getElementById('cancelModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeCancelModal();
});

document.getElementById('deleteModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeExportModal();
        closeCancelModal();
        closeDeleteModal();
        hideLoadingModal();
    }
});
</script>

<style>
/* Loading animation */
.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Modal styles */
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
    opacity: 0;
    pointer-events: none;
    display: flex !important;
}

#exportModal:not(.hidden),
#pdfLoadingModal:not(.hidden),
#cancelModal:not(.hidden),
#deleteModal:not(.hidden) {
    opacity: 1;
    pointer-events: auto;
}

/* Table row hover */
tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.02);
}

/* Dark mode adjustments */
.dark .bg-blue-50 {
    background-color: rgba(59, 130, 246, 0.2) !important;
}

.dark .bg-green-50 {
    background-color: rgba(16, 185, 129, 0.15) !important;
}
</style>
@endsection