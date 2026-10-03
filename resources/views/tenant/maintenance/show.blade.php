@extends('layouts.tenant')

@section('title', 'Maintenance Request #' . $request->id)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div>
                <div class="flex items-center space-x-3">
                    <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                        Maintenance Request #{{ $request->id }}
                    </h2>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs" 
                          style="background-color: rgba(var(--{{ $request->status == 'completed' ? 'success' : ($request->status == 'cancelled' ? 'danger' : 'warning') }}-rgb), 0.2); color: var(--{{ $request->status == 'completed' ? 'success' : ($request->status == 'cancelled' ? 'danger' : 'warning') }});">
                        <i class="fas fa-{{ $request->status == 'completed' ? 'check-circle' : ($request->status == 'cancelled' ? 'times-circle' : 'clock') }} mr-1"></i>
                        {{ ucfirst(str_replace('_', ' ', $request->status)) }}
                    </span>
                </div>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Unit {{ $unit->unit_number ?? '' }} - {{ $unit->property->property_name ?? $unit->property->street_name ?? '' }}
                </p>
            </div>
            
            <div class="flex space-x-2 mt-4 md:mt-0">
                <a href="{{ route('tenant.maintenance.index') }}" class="px-4 py-2 rounded flex items-center" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Requests
                </a>
                @if(in_array($request->status, ['pending']))
                    <button onclick="cancelRequest({{ $request->id }})" class="px-4 py-2 rounded flex items-center" style="background-color: var(--danger); color: white;">
                        <i class="fas fa-times mr-2"></i> Cancel Request
                    </button>
                @endif
                <button onclick="exportPDF({{ $request->id }})" class="px-4 py-2 rounded flex items-center" style="background-color: #dc2626; color: white;">
                    <i class="fas fa-file-pdf mr-2"></i> Export PDF
                </button>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
        <strong class="font-bold">Success!</strong>
        <span class="block sm:inline">{{ session('success') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
        <strong class="font-bold">Error!</strong>
        <span class="block sm:inline">{{ session('error') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Request Details -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2">
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">{{ $request->title }}</h3>
                
                <div class="prose max-w-none" style="color: var(--text-secondary);">
                    <p>{{ $request->description }}</p>
                </div>

                @if($request->photos && count($request->photos) > 0)
                    <div class="mt-6">
                        <h4 class="text-sm font-medium mb-3" style="color: var(--text-primary);">Attached Photos</h4>
                        <div class="grid grid-cols-3 md:grid-cols-4 gap-3">
                            @foreach($request->photos as $photo)
                                <div class="relative group">
                                    <img src="{{ Storage::url($photo) }}" 
                                         alt="Request photo" 
                                         class="w-full h-24 object-cover rounded-lg cursor-pointer"
                                         onclick="openPhotoModal('{{ Storage::url($photo) }}')">
                                    <button onclick="openPhotoModal('{{ Storage::url($photo) }}')" 
                                            class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-200 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-search-plus text-white opacity-0 group-hover:opacity-100 transition-opacity duration-200"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Status Timeline -->
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    <h4 class="text-sm font-medium mb-4" style="color: var(--text-primary);">Status History</h4>
                    <div class="space-y-4">
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center" 
                                 style="background-color: rgba(var(--primary-rgb), 0.1);">
                                <i class="fas fa-plus text-sm" style="color: var(--primary);"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium" style="color: var(--text-primary);">Request Created</p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    {{ $request->created_at->format('M d, Y g:i A') }}
                                    @if($request->reporter)
                                        by {{ $request->reporter->name }}
                                    @endif
                                </p>
                            </div>
                        </div>

                        @if($request->status != 'pending')
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center" 
                                     style="background-color: rgba(var(--info-rgb), 0.1);">
                                    <i class="fas fa-arrow-right text-sm" style="color: var(--info);"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium" style="color: var(--text-primary);">
                                        Status Updated to {{ ucfirst(str_replace('_', ' ', $request->status)) }}
                                    </p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        {{ $request->updated_at->format('M d, Y g:i A') }}
                                    </p>
                                    @if($request->notes)
                                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                            Note: {{ $request->notes }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if($request->status == 'completed')
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center" 
                                     style="background-color: rgba(var(--success-rgb), 0.1);">
                                    <i class="fas fa-check text-sm" style="color: var(--success);"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium" style="color: var(--success);">Completed</p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        {{ $request->completed_at ? \Carbon\Carbon::parse($request->completed_at)->format('M d, Y g:i A') : 'Completed' }}
                                    </p>
                                    @if($request->resolution_notes)
                                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                            Resolution: {{ $request->resolution_notes }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if($request->status == 'cancelled')
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center" 
                                     style="background-color: rgba(var(--danger-rgb), 0.1);">
                                    <i class="fas fa-times text-sm" style="color: var(--danger);"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium" style="color: var(--danger);">Cancelled</p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        {{ $request->cancelled_at ? \Carbon\Carbon::parse($request->cancelled_at)->format('M d, Y g:i A') : 'Cancelled' }}
                                    </p>
                                    @if($request->cancellation_reason)
                                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                            Reason: {{ $request->cancellation_reason }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <div class="card p-6 space-y-4">
                <h4 class="text-sm font-medium" style="color: var(--text-primary);">Request Details</h4>
                
                <div>
                    <p class="text-xs" style="color: var(--text-secondary);">Category</p>
                    <p class="text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-tag mr-1" style="color: var(--info);"></i>
                        {{ ucfirst($request->category) }}
                    </p>
                </div>

                <div>
                    <p class="text-xs" style="color: var(--text-secondary);">Priority</p>
                    @php
                        $priorityColors = [
                            'low' => ['color' => 'success', 'icon' => 'arrow-down'],
                            'medium' => ['color' => 'warning', 'icon' => 'minus'],
                            'high' => ['color' => 'orange', 'icon' => 'arrow-up'],
                            'urgent' => ['color' => 'danger', 'icon' => 'exclamation']
                        ];
                        $priorityConfig = $priorityColors[$request->priority] ?? ['color' => 'secondary', 'icon' => 'circle'];
                    @endphp
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs" 
                          style="background-color: rgba(var(--{{ $priorityConfig['color'] }}-rgb), 0.2); color: var(--{{ $priorityConfig['color'] }});">
                        <i class="fas fa-{{ $priorityConfig['icon'] }} mr-1"></i>
                        {{ ucfirst($request->priority) }}
                    </span>
                </div>

                <div>
                    <p class="text-xs" style="color: var(--text-secondary);">Reported By</p>
                    <p class="text-sm font-medium" style="color: var(--text-primary);">
                        {{ $request->reporter->name ?? 'Unknown' }}
                    </p>
                    <p class="text-xs" style="color: var(--text-secondary);">
                        {{ $request->reporter->email ?? '' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs" style="color: var(--text-secondary);">Created</p>
                    <p class="text-sm font-medium" style="color: var(--text-primary);">
                        {{ $request->created_at->format('M d, Y') }}
                    </p>
                    <p class="text-xs" style="color: var(--text-secondary);">
                        {{ $request->created_at->diffForHumans() }}
                    </p>
                </div>

                @if($request->assigned_to)
                <div>
                    <p class="text-xs" style="color: var(--text-secondary);">Assigned To</p>
                    <p class="text-sm font-medium" style="color: var(--text-primary);">
                        {{ $request->assignedTo->name ?? 'Unassigned' }}
                    </p>
                </div>
                @endif

                @if($request->estimated_completion_date)
                <div>
                    <p class="text-xs" style="color: var(--text-secondary);">Estimated Completion</p>
                    <p class="text-sm font-medium" style="color: var(--text-primary);">
                        {{ \Carbon\Carbon::parse($request->estimated_completion_date)->format('M d, Y') }}
                    </p>
                </div>
                @endif

                @if($request->cost_estimate)
                <div>
                    <p class="text-xs" style="color: var(--text-secondary);">Cost Estimate</p>
                    <p class="text-sm font-medium" style="color: var(--text-primary);">
                        {{ $settings->formatAmount($request->cost_estimate) }}
                    </p>
                </div>
                @endif
            </div>

            <!-- Quick Actions -->
            <div class="card p-6 mt-4">
                <h4 class="text-sm font-medium mb-3" style="color: var(--text-primary);">Quick Actions</h4>
                <div class="space-y-2">
                    <a href="{{ route('tenant.maintenance.create') }}" class="block w-full px-4 py-2 rounded text-center text-sm" style="background-color: var(--primary); color: white;">
                        <i class="fas fa-plus-circle mr-2"></i> New Request
                    </a>
                    <button onclick="exportPDF({{ $request->id }})" class="block w-full px-4 py-2 rounded text-center text-sm" style="background-color: #dc2626; color: white;">
                        <i class="fas fa-file-pdf mr-2"></i> Download PDF
                    </button>
                    <a href="{{ route('tenant.maintenance.index') }}" class="block w-full px-4 py-2 rounded text-center text-sm" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                        <i class="fas fa-list mr-2"></i> View All Requests
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Photo Modal -->
<div id="photoModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center hidden z-50">
    <div class="relative max-w-4xl max-h-full">
        <button onclick="closePhotoModal()" class="absolute top-0 right-0 m-4 text-white hover:text-gray-300">
            <i class="fas fa-times text-2xl"></i>
        </button>
        <img id="modalPhoto" src="" alt="Full size photo" class="max-h-screen object-contain rounded-lg">
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
            
            <form id="cancelForm" method="POST" action="{{ route('tenant.maintenance.cancel', $request->id) }}">
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

<!-- PDF Loading Modal -->
<div id="pdfLoadingModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card p-8 text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
        <p class="text-lg font-semibold" style="color: var(--text-primary);">Generating PDF...</p>
        <p class="text-sm mt-2" style="color: var(--text-secondary);">Please wait while we prepare your document</p>
    </div>
</div>

<!-- Hidden form for PDF export -->
<form id="exportForm" method="GET" action="{{ route('tenant.maintenance.export-single', $request->id) }}" target="_blank"></form>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide messages after 5 seconds
    const messages = document.querySelectorAll('.bg-green-100, .bg-red-100');
    messages.forEach(message => {
        setTimeout(() => {
            message.style.display = 'none';
        }, 5000);
    });
});

function openPhotoModal(src) {
    const modal = document.getElementById('photoModal');
    const img = document.getElementById('modalPhoto');
    if (modal && img) {
        img.src = src;
        modal.classList.remove('hidden');
    }
}

function closePhotoModal() {
    document.getElementById('photoModal').classList.add('hidden');
}

function cancelRequest(requestId) {
    document.getElementById('cancelModal').classList.remove('hidden');
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.add('hidden');
}

function exportPDF(requestId) {
    document.getElementById('pdfLoadingModal').classList.remove('hidden');
    document.getElementById('exportForm').submit();
    setTimeout(() => {
        document.getElementById('pdfLoadingModal').classList.add('hidden');
    }, 2000);
}

// Close modals with escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closePhotoModal();
        closeCancelModal();
    }
});

// Close modal on backdrop click
document.getElementById('photoModal')?.addEventListener('click', function(e) {
    if (e.target === this) closePhotoModal();
});

document.getElementById('cancelModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeCancelModal();
});
</script>