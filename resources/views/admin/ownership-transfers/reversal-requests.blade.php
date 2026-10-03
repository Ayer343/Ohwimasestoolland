{{-- admin/ownership-transfers/reversal-requests.blade.php --}}
@php
    use App\Models\PropertyOwnershipTransfer;
    use App\Models\User;

    $isAdmin = auth()->user()->isAdmin();
    $isSuperAdmin = auth()->user()->isSuperAdmin();
    $layout = 'layouts.app';
    $pageTitle = 'Transfer Reversal Requests';
    
    $successMessage = session('success');
    $errorMessage = session('error');
    
    // Statistics
    $pendingCount = $stats['pending'] ?? 0;
    $approvedCount = $stats['approved'] ?? 0;
    $rejectedCount = $stats['rejected'] ?? 0;
    $completedCount = $stats['completed'] ?? 0;
    $expiredCount = $stats['expired'] ?? 0;
    
    // Get current filters
    $currentStatus = request('status');
    $currentPropertyId = request('property_id');
    $currentLandlordId = request('landlord_id');
    $currentDateFrom = request('date_from');
    $currentDateTo = request('date_to');
    
    // Status options
    $statusOptions = [
        'pending' => 'Pending Review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'completed' => 'Completed',
        'expired' => 'Expired'
    ];
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--warning) 0%, var(--danger) 100%); color: white; border-color: var(--warning);">
                        <i class="fas fa-undo-alt text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-undo-alt mr-2" style="color: var(--warning);"></i> 
                        Transfer Reversal Requests
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Review and manage property transfer reversal requests from landlords</span>
                        @if($pendingCount > 0)
                        <span class="ml-2 px-2 py-1 text-xs rounded-full" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                            {{ $pendingCount }} pending
                        </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.ownership-transfers.index') }}" 
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-exchange-alt mr-1"></i> Active Transfers
                </a>
                <a href="{{ route('admin.ownership-transfers.trash') }}" 
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <i class="fas fa-trash-alt mr-1"></i> Trash
                </a>
            </div>
        </div>
    </div>

    <!-- Notification Container -->
    <div id="notificationContainer"></div>

    <!-- Success Messages -->
    @if($successMessage)
    <div class="success-message" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3); border-radius: 0.5rem; padding: 1rem;">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                <span class="font-medium" style="color: var(--success);">{{ $successMessage }}</span>
            </div>
            <button type="button" onclick="this.parentElement.parentElement.style.display='none'" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    <!-- Error Messages -->
    @if($errorMessage)
    <div class="error-message" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3); border-radius: 0.5rem; padding: 1rem;">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2" style="color: var(--danger);"></i>
                <span class="font-medium" style="color: var(--danger);">{{ $errorMessage }}</span>
            </div>
            <button type="button" onclick="this.parentElement.parentElement.style.display='none'" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div class="card">
            <div class="flex items-center p-4">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-clock text-lg" style="color: var(--warning);"></i>
                    </div>
                </div>
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Pending</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($pendingCount) }}</p>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="flex items-center p-4">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1);">
                        <i class="fas fa-check-circle text-lg" style="color: var(--success);"></i>
                    </div>
                </div>
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Approved</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($approvedCount) }}</p>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="flex items-center p-4">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--danger-rgb), 0.1);">
                        <i class="fas fa-times-circle text-lg" style="color: var(--danger);"></i>
                    </div>
                </div>
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Rejected</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($rejectedCount) }}</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="flex items-center p-4">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1);">
                        <i class="fas fa-check-double text-lg" style="color: var(--info);"></i>
                    </div>
                </div>
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Completed</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($completedCount) }}</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="flex items-center p-4">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--danger-rgb), 0.1);">
                        <i class="fas fa-hourglass-end text-lg" style="color: var(--danger);"></i>
                    </div>
                </div>
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Expired</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($expiredCount) }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Banner -->
    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6 rounded-r-lg">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-blue-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-blue-700">
                    <strong>Reversal Requests:</strong> Landlords can request reversal of completed transfers if they transferred to the wrong person.
                    Review each request carefully. Once approved, the property ownership will be returned to the original owner.
                </p>
            </div>
        </div>
    </div>

    <!-- Filters and Table Container -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Filters Sidebar -->
        <div class="lg:col-span-1">
            <div class="card mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-filter mr-2" style="color: var(--warning);"></i> Filters
                    </h3>
                    
                    <form method="GET" action="{{ route('admin.ownership-transfers.reversal-requests') }}" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-tag mr-1"></i> Status
                            </label>
                            <select name="status" class="index-custom-dropdown w-full">
                                <option value="">All Statuses</option>
                                @foreach($statusOptions as $key => $label)
                                    <option value="{{ $key }}" {{ $currentStatus == $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-building mr-1"></i> Property
                            </label>
                            <select name="property_id" class="index-custom-dropdown w-full">
                                <option value="">All Properties</option>
                                @foreach($properties ?? [] as $property)
                                    <option value="{{ $property->id }}" {{ $currentPropertyId == $property->id ? 'selected' : '' }}>
                                        {{ $property->property_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-user-tie mr-1"></i> Requesting Landlord
                            </label>
                            <select name="landlord_id" class="index-custom-dropdown w-full">
                                <option value="">All Landlords</option>
                                @foreach($landlords ?? [] as $landlord)
                                    <option value="{{ $landlord->id }}" {{ $currentLandlordId == $landlord->id ? 'selected' : '' }}>
                                        {{ $landlord->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-calendar mr-1"></i> Request Date From
                            </label>
                            <input type="date" name="date_from" value="{{ $currentDateFrom }}" class="index-custom-input w-full">
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-calendar mr-1"></i> Request Date To
                            </label>
                            <input type="date" name="date_to" value="{{ $currentDateTo }}" class="index-custom-input w-full">
                        </div>

                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <button type="submit" class="block w-full text-center btn-primary px-3 py-2 rounded-lg font-medium text-white">
                                <i class="fas fa-filter mr-2"></i> Apply Filters
                            </button>
                            
                            <a href="{{ route('admin.ownership-transfers.reversal-requests') }}" 
                               class="block w-full text-center mt-2 btn-secondary px-3 py-2 rounded-lg font-medium">
                                <i class="fas fa-redo mr-2"></i> Reset Filters
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Reversal Requests Table -->
        <div class="lg:col-span-3">
            <div class="card p-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            Reversal Requests (<span id="totalEntries">{{ $reversalRequests->total() }}</span>)
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Showing {{ $reversalRequests->firstItem() }} to {{ $reversalRequests->lastItem() }} of {{ $reversalRequests->total() }} entries
                        </p>
                    </div>
                </div>

                @if($reversalRequests->isEmpty())
                    <div class="text-center py-12">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" 
                             style="background-color: rgba(var(--warning-rgb), 0.1);">
                            <i class="fas fa-undo-alt text-2xl" style="color: var(--warning);"></i>
                        </div>
                        <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                            No reversal requests found
                        </h4>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            @if(request()->hasAny(['status', 'property_id', 'landlord_id', 'date_from', 'date_to']))
                                No reversal requests match your search criteria.
                            @else
                                There are no pending reversal requests at this time.
                            @endif
                        </p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary);">
                                        Property / Reference
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary);">
                                        Requested By
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary);">
                                        Current Owner → New Owner
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary);">
                                        Request Details
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary);">
                                        Status
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary);">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($reversalRequests as $request)
                                @php
                                    $isExpired = $request->reversal_deadline && $request->reversal_deadline < now();
                                    $daysRemaining = $request->reversal_deadline ? now()->diffInDays($request->reversal_deadline, false) : null;
                                    $statusColors = [
                                        'pending' => ['bg' => 'warning', 'icon' => 'clock', 'text' => 'Pending'],
                                        'approved' => ['bg' => 'success', 'icon' => 'check-circle', 'text' => 'Approved'],
                                        'rejected' => ['bg' => 'danger', 'icon' => 'times-circle', 'text' => 'Rejected'],
                                        'completed' => ['bg' => 'info', 'icon' => 'check-double', 'text' => 'Completed'],
                                        'expired' => ['bg' => 'danger', 'icon' => 'hourglass-end', 'text' => 'Expired'],
                                    ];
                                    $statusColor = $statusColors[$request->reversal_status] ?? $statusColors['pending'];
                                @endphp
                                <tr data-request-id="{{ $request->id }}" data-status="{{ $request->reversal_status }}">
                                    <td class="p-3 align-top">
                                        <div class="font-medium" style="color: var(--text-primary);">
                                            {{ $request->property->property_name ?? 'N/A' }}
                                        </div>
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            Ref: {{ $request->document_reference }}
                                        </div>
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            Transfer Date: {{ $request->transfer_date ? $request->transfer_date->format('M j, Y') : 'N/A' }}
                                        </div>
                                    </td>
                                    <td class="p-3 align-top">
                                        <div class="font-medium" style="color: var(--text-primary);">
                                            {{ $request->reversalRequestedBy->name ?? $request->currentLandlord->name ?? 'N/A' }}
                                        </div>
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            {{ $request->reversalRequestedBy->email ?? $request->currentLandlord->email ?? 'N/A' }}
                                        </div>
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            Requested: {{ $request->reversal_requested_at ? $request->reversal_requested_at->format('M j, Y') : 'N/A' }}
                                        </div>
                                    </td>
                                    <td class="p-3 align-top">
                                        <div class="flex items-center">
                                            <span class="text-sm" style="color: var(--text-primary);">{{ $request->currentLandlord->name ?? 'N/A' }}</span>
                                            <i class="fas fa-arrow-right mx-2 text-xs" style="color: var(--text-secondary);"></i>
                                            <span class="text-sm" style="color: var(--text-primary);">{{ $request->newLandlord->name ?? $request->new_owner_name }}</span>
                                        </div>
                                        @if($request->sale_amount)
                                        <div class="text-xs mt-1" style="color: var(--primary);">
                                            Amount: GHS {{ number_format($request->sale_amount, 2) }}
                                        </div>
                                        @endif
                                    </td>
                                    <td class="p-3 align-top">
                                        <div class="max-w-xs">
                                            <p class="text-sm" style="color: var(--text-primary);">
                                                {{ Str::limit($request->reversal_reason, 100) }}
                                            </p>
                                            @if($request->reversal_deadline && $request->reversal_status === 'pending')
                                            <div class="mt-2 text-xs {{ $daysRemaining <= 2 ? 'text-danger' : 'text-warning' }}">
                                                <i class="fas fa-clock mr-1"></i>
                                                Deadline: {{ $request->reversal_deadline->format('M j, Y') }}
                                                ({{ $daysRemaining }} days left)
                                            </div>
                                            @endif
                                        </div>
                                     </td>
                                    <td class="p-3 align-top">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium badge-{{ $statusColor['bg'] }}">
                                            <i class="fas fa-{{ $statusColor['icon'] }} mr-1 text-xs"></i>
                                            {{ $statusColor['text'] }}
                                        </span>
                                        @if($request->reversal_status === 'approved')
                                        <div class="text-xs mt-1" style="color: var(--success);">
                                            <i class="fas fa-spinner fa-pulse mr-1"></i> Processing...
                                        </div>
                                        @endif
                                     </td>
                                    <td class="p-3 align-top">
                                        <div class="flex flex-wrap items-center gap-1">
                                            <button type="button" 
                                                    onclick="viewRequestDetails('{{ $request->id }}')"
                                                    class="action-btn view" 
                                                    data-tooltip="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            @if($request->reversal_status === 'pending' && !$isExpired)
                                                <button type="button" 
                                                        onclick="showApproveModal('{{ $request->id }}', '{{ addslashes($request->property->property_name ?? 'N/A') }}', '{{ addslashes($request->reversal_reason) }}')"
                                                        class="action-btn approve" 
                                                        data-tooltip="Approve Reversal">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                                
                                                <button type="button" 
                                                        onclick="showRejectModal('{{ $request->id }}', '{{ addslashes($request->property->property_name ?? 'N/A') }}')"
                                                        class="action-btn reject" 
                                                        data-tooltip="Reject Reversal">
                                                    <i class="fas fa-times-circle"></i>
                                                </button>
                                            @endif
                                            
                                            @if($request->reversal_status === 'approved')
                                                <button type="button" 
                                                        onclick="processReversal('{{ $request->id }}')"
                                                        class="action-btn process" 
                                                        data-tooltip="Process Reversal Now">
                                                    <i class="fas fa-play-circle"></i>
                                                </button>
                                            @endif
                                        </div>
                                     </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-col md:flex-row justify-between items-center pt-6 mt-6 border-t" style="border-color: var(--border-color);">
                        <div class="text-sm mb-4 md:mb-0" style="color: var(--text-secondary);">
                            Showing {{ $reversalRequests->firstItem() }} to {{ $reversalRequests->lastItem() }} of {{ $reversalRequests->total() }} entries
                        </div>
                        <div class="pagination">
                            {{ $reversalRequests->appends(request()->except('page'))->links() }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div id="viewDetailsModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideViewDetailsModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-2xl">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-undo-alt mr-2" style="color: var(--warning);"></i> Reversal Request Details
                </h3>
                <button type="button" onclick="hideViewDetailsModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body" id="detailsContent">
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl" style="color: var(--info);"></i>
                    <p class="mt-2" style="color: var(--text-secondary);">Loading details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="hideViewDetailsModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Approve Reversal Modal -->
<div id="approveModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideApproveModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Approve Reversal Request
                </h3>
                <button type="button" onclick="hideApproveModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="approveForm" method="POST" action="">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <p class="text-sm mb-2" style="color: var(--text-primary);">
                            Approve reversal for: <strong id="approvePropertyName" class="font-semibold"></strong>
                        </p>
                        <div class="p-3 rounded-lg mb-3" style="background-color: rgba(var(--info-rgb), 0.05);">
                            <p class="text-sm font-medium mb-1" style="color: var(--text-primary);">Reason provided by landlord:</p>
                            <p class="text-sm" id="approveReversalReason" style="color: var(--text-secondary);"></p>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Admin Notes (Optional)
                        </label>
                        <textarea name="admin_notes" rows="3" class="index-custom-textarea w-full" placeholder="Add any notes about this approval..."></textarea>
                    </div>
                    
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--warning);"></i>
                            <div class="text-sm" style="color: var(--text-secondary);">
                                <p class="font-medium mb-1">Important:</p>
                                <p>Approving this reversal will:</p>
                                <ul class="list-disc list-inside mt-1">
                                    <li>Return property ownership to the original landlord</li>
                                    <li>Transfer all units back</li>
                                    <li>Restore tenant relationships</li>
                                    <li>Generate a reversal record for audit</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideApproveModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="btn-success px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-check mr-2"></i> Approve Reversal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Reversal Modal -->
<div id="rejectModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideRejectModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i> Reject Reversal Request
                </h3>
                <button type="button" onclick="hideRejectModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="rejectForm" method="POST" action="">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <p class="text-sm mb-2" style="color: var(--text-primary);">
                            Reject reversal for: <strong id="rejectPropertyName" class="font-semibold"></strong>
                        </p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Rejection Reason <span class="text-danger">*</span>
                        </label>
                        <textarea name="rejection_reason" rows="4" class="index-custom-textarea w-full" placeholder="Please provide a reason for rejecting this reversal request..." required></textarea>
                    </div>
                    
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">This action will notify the landlord and cannot be undone.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideRejectModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-times mr-2"></i> Reject Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentRequestId = null;

function viewRequestDetails(requestId) {
    const modal = document.getElementById('viewDetailsModal');
    const content = document.getElementById('detailsContent');
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    content.innerHTML = `
        <div class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-2xl" style="color: var(--info);"></i>
            <p class="mt-2" style="color: var(--text-secondary);">Loading details...</p>
        </div>
    `;
    
    fetch(`/admin/ownership-transfers/${requestId}`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const transfer = data.transfer;
            content.innerHTML = `
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-medium" style="color: var(--text-secondary);">Transfer ID</label>
                            <p class="text-sm font-medium" style="color: var(--text-primary);">#${transfer.id}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium" style="color: var(--text-secondary);">Document Reference</label>
                            <p class="text-sm font-medium" style="color: var(--text-primary);">${escapeHtml(transfer.document_reference)}</p>
                        </div>
                    </div>
                    
                    <div class="border-t pt-4" style="border-color: var(--border-color);">
                        <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">Property Details</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-medium" style="color: var(--text-secondary);">Property Name</label>
                                <p class="text-sm" style="color: var(--text-primary);">${escapeHtml(transfer.property?.property_name || 'N/A')}</p>
                            </div>
                            <div>
                                <label class="text-xs font-medium" style="color: var(--text-secondary);">Registration</label>
                                <p class="text-sm" style="color: var(--text-primary);">${escapeHtml(transfer.property?.registration_pattern || 'N/A')}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="border-t pt-4" style="border-color: var(--border-color);">
                        <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">Ownership Transfer</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-medium" style="color: var(--text-secondary);">From (Original Owner)</label>
                                <p class="text-sm" style="color: var(--text-primary);">${escapeHtml(transfer.current_landlord?.name || 'N/A')}</p>
                            </div>
                            <div>
                                <label class="text-xs font-medium" style="color: var(--text-secondary);">To (Wrong Owner)</label>
                                <p class="text-sm" style="color: var(--text-primary);">${escapeHtml(transfer.new_landlord?.name || transfer.new_owner_name)}</p>
                            </div>
                            <div>
                                <label class="text-xs font-medium" style="color: var(--text-secondary);">Transfer Date</label>
                                <p class="text-sm" style="color: var(--text-primary);">${transfer.transfer_date || 'N/A'}</p>
                            </div>
                            <div>
                                <label class="text-xs font-medium" style="color: var(--text-secondary);">Sale Amount</label>
                                <p class="text-sm" style="color: var(--text-primary);">${transfer.formatted_sale_amount || 'N/A'}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="border-t pt-4" style="border-color: var(--border-color);">
                        <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">Reversal Request</h4>
                        <div class="space-y-3">
                            <div>
                                <label class="text-xs font-medium" style="color: var(--text-secondary);">Requested By</label>
                                <p class="text-sm" style="color: var(--text-primary);">${escapeHtml(transfer.reversal_requested_by?.name || transfer.current_landlord?.name)}</p>
                            </div>
                            <div>
                                <label class="text-xs font-medium" style="color: var(--text-secondary);">Requested At</label>
                                <p class="text-sm" style="color: var(--text-primary);">${transfer.reversal_requested_at ? new Date(transfer.reversal_requested_at).toLocaleString() : 'N/A'}</p>
                            </div>
                            <div>
                                <label class="text-xs font-medium" style="color: var(--text-secondary);">Reason for Reversal</label>
                                <div class="mt-1 p-3 rounded" style="background-color: var(--bg-secondary);">
                                    <p class="text-sm" style="color: var(--text-primary);">${escapeHtml(transfer.reversal_reason || 'No reason provided')}</p>
                                </div>
                            </div>
                            ${transfer.reversal_deadline ? `
                            <div>
                                <label class="text-xs font-medium" style="color: var(--text-secondary);">Review Deadline</label>
                                <p class="text-sm" style="color: var(--text-primary);">${new Date(transfer.reversal_deadline).toLocaleString()}</p>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        } else {
            content.innerHTML = `<div class="text-center py-8"><i class="fas fa-exclamation-circle text-4xl mb-2" style="color: var(--danger);"></i><p>Failed to load details.</p></div>`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        content.innerHTML = `<div class="text-center py-8"><i class="fas fa-exclamation-circle text-4xl mb-2" style="color: var(--danger);"></i><p>An error occurred.</p></div>`;
    });
}

function hideViewDetailsModal() {
    const modal = document.getElementById('viewDetailsModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showApproveModal(requestId, propertyName, reversalReason) {
    currentRequestId = requestId;
    const modal = document.getElementById('approveModal');
    const form = document.getElementById('approveForm');
    const propertyNameSpan = document.getElementById('approvePropertyName');
    const reasonSpan = document.getElementById('approveReversalReason');
    
    form.action = `/admin/ownership-transfers/reversal/${requestId}/approve`;
    propertyNameSpan.textContent = propertyName || 'Unknown Property';
    reasonSpan.textContent = reversalReason || 'No reason provided';
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideApproveModal() {
    const modal = document.getElementById('approveModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showRejectModal(requestId, propertyName) {
    currentRequestId = requestId;
    const modal = document.getElementById('rejectModal');
    const form = document.getElementById('rejectForm');
    const propertyNameSpan = document.getElementById('rejectPropertyName');
    
    form.action = `/admin/ownership-transfers/reversal/${requestId}/reject`;
    propertyNameSpan.textContent = propertyName || 'Unknown Property';
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideRejectModal() {
    const modal = document.getElementById('rejectModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function processReversal(requestId) {
    if (confirm('⚠️ WARNING: This will immediately reverse the property transfer. This action cannot be undone. Are you sure?')) {
        showNotification('info', 'Processing reversal...');
        
        fetch(`/admin/ownership-transfers/reversal/${requestId}/process`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', data.message);
                setTimeout(() => window.location.reload(), 2000);
            } else {
                showNotification('error', data.message || 'An error occurred');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'An error occurred while processing the reversal');
        });
    }
}

function showNotification(type, message) {
    const container = document.getElementById('notificationContainer');
    if (!container) return;
    
    const notification = document.createElement('div');
    notification.className = `mb-4 p-4 rounded-lg shadow-lg`;
    notification.style.backgroundColor = type === 'success' ? 'rgba(var(--success-rgb), 0.1)' : type === 'error' ? 'rgba(var(--danger-rgb), 0.1)' : 'rgba(var(--info-rgb), 0.1)';
    notification.style.border = type === 'success' ? '1px solid rgba(var(--success-rgb), 0.3)' : type === 'error' ? '1px solid rgba(var(--danger-rgb), 0.3)' : '1px solid rgba(var(--info-rgb), 0.3)';
    notification.innerHTML = `
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} mr-2" style="color: ${type === 'success' ? 'var(--success)' : type === 'error' ? 'var(--danger)' : 'var(--info)'};"></i>
                <span style="color: ${type === 'success' ? 'var(--success)' : type === 'error' ? 'var(--danger)' : 'var(--info)'};">${escapeHtml(message)}</span>
            </div>
            <button type="button" onclick="this.parentElement.parentElement.remove()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    container.appendChild(notification);
    setTimeout(() => notification.remove(), 5000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Handle form submissions
document.getElementById('approveForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    submitBtn.disabled = true;
    
    fetch(this.action, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json'
        },
        body: new FormData(this)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message);
            hideApproveModal();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification('error', data.message || 'An error occurred');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'An error occurred');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

document.getElementById('rejectForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    submitBtn.disabled = true;
    
    fetch(this.action, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json'
        },
        body: new FormData(this)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message);
            hideRejectModal();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification('error', data.message || 'An error occurred');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'An error occurred');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});
</script>

<style>
.index-custom-input,
.index-custom-dropdown,
.index-custom-textarea {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
}

.action-btn {
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    border: 1px solid transparent;
    cursor: pointer;
}

.action-btn.view {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
    border-color: rgba(var(--info-rgb), 0.3);
}

.action-btn.approve {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
    border-color: rgba(var(--success-rgb), 0.3);
}

.action-btn.reject {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
    border-color: rgba(var(--danger-rgb), 0.3);
}

.action-btn.process {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
    border-color: rgba(var(--primary-rgb), 0.3);
}

.action-btn:hover {
    transform: translateY(-1px);
}

.modal-container {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    max-height: 90vh;
    overflow-y: auto;
    border-radius: 16px;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    background: var(--card-bg);
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    position: sticky;
    bottom: 0;
    background: var(--card-bg);
}

.modal-close-btn {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1.25rem;
    padding: 0.5rem;
    border-radius: 50%;
}

.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
}

.btn-success {
    background-color: var(--success) !important;
    color: white !important;
}

.btn-danger {
    background-color: var(--danger) !important;
    color: white !important;
}

.btn-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
}

.badge-warning {
    background-color: rgba(var(--warning-rgb), 0.1) !important;
    color: var(--warning) !important;
}

.badge-success {
    background-color: rgba(var(--success-rgb), 0.1) !important;
    color: var(--success) !important;
}

.badge-danger {
    background-color: rgba(var(--danger-rgb), 0.1) !important;
    color: var(--danger) !important;
}

.badge-info {
    background-color: rgba(var(--info-rgb), 0.1) !important;
    color: var(--info) !important;
}

table {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
}

table th {
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 0.75rem;
    padding: 0.75rem;
    border-bottom: 2px solid var(--border-color);
    background-color: var(--bg-secondary) !important;
}

table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: top;
    background-color: var(--card-bg) !important;
}
</style>
@endsection