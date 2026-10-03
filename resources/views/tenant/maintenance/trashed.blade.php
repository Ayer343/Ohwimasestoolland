@extends('layouts.tenant')

@section('title', 'Trashed Maintenance Requests')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--danger) 0%, #dc3545 100%); color: white; font-weight: 600; border-color: var(--danger);">
                        <i class="fas fa-trash-alt text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> 
                        Trashed Maintenance Requests
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Requests moved to trash can be permanently deleted</span>
                        <span class="mx-2">•</span>
                        <span>Total: {{ $stats['total'] ?? 0 }} trashed request(s)</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                </div>
                <a href="{{ route('tenant.maintenance.index') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Requests
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="card p-4" style="background-color: rgba(var(--danger-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Trashed Requests</div>
                    <div class="text-2xl font-semibold" style="color: var(--text-primary);">
                        {{ $stats['total'] ?? 0 }}
                    </div>
                </div>
                <i class="fas fa-trash-alt text-2xl opacity-70" style="color: var(--danger);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--warning-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Pending Deletion</div>
                    <div class="text-2xl font-semibold" style="color: var(--text-primary);">
                        {{ $trashedRequests->where('status', 'cancelled')->count() }}
                    </div>
                </div>
                <i class="fas fa-clock text-2xl opacity-70" style="color: var(--warning);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Completed</div>
                    <div class="text-2xl font-semibold" style="color: var(--text-primary);">
                        {{ $trashedRequests->where('status', 'completed')->count() }}
                    </div>
                </div>
                <i class="fas fa-check-circle text-2xl opacity-70" style="color: var(--info);"></i>
            </div>
        </div>
    </div>

    <!-- Trashed Requests Table -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
            <i class="fas fa-list mr-2" style="color: var(--danger);"></i> 
            Trashed Requests
        </h3>

        @if($trashedRequests->isEmpty())
            <div class="text-center py-12">
                <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" 
                     style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-trash-alt text-2xl" style="color: var(--danger);"></i>
                </div>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No Trashed Requests</h4>
                <p class="text-sm" style="color: var(--text-secondary);">
                    There are no requests in the trash. Deleted requests will appear here.
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Request #</th>
                            <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Title</th>
                            <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Priority</th>
                            <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Status</th>
                            <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Deleted</th>
                            <th class="text-left p-3 font-medium" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($trashedRequests as $request)
                        <tr class="border-b" style="border-color: var(--border-color);">
                            <td class="p-3">
                                <div class="text-sm font-medium" style="color: var(--text-primary);">
                                    {{ $request->reference_id ?? 'MREQ-'.str_pad($request->id, 6, '0', STR_PAD_LEFT) }}
                                </div>
                            </td>
                            <td class="p-3">
                                <div class="text-sm font-medium" style="color: var(--text-primary);">
                                    {{ $request->title ?? 'Maintenance Request' }}
                                </div>
                                <div class="text-xs truncate max-w-xs" style="color: var(--text-secondary);">
                                    {{ Str::limit($request->description, 80) }}
                                </div>
                            </td>
                            <td class="p-3">
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    {{ $request->priority == 'urgent' ? 'badge-danger' : '' }}
                                    {{ $request->priority == 'high' ? 'badge-warning' : '' }}
                                    {{ $request->priority == 'medium' ? 'badge-info' : '' }}
                                    {{ $request->priority == 'low' ? 'badge-success' : '' }}">
                                    {{ ucfirst($request->priority) }}
                                </span>
                            </td>
                            <td class="p-3">
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    {{ $request->status == 'cancelled' ? 'badge-danger' : '' }}
                                    {{ $request->status == 'completed' ? 'badge-success' : '' }}">
                                    {{ str_replace('_', ' ', ucfirst($request->status)) }}
                                </span>
                            </td>
                            <td class="p-3">
                                <div class="text-sm" style="color: var(--text-primary);">
                                    {{ $request->deleted_at->format('M d, Y') }}
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    {{ $request->deleted_at->diffForHumans() }}
                                </div>
                            </td>
                            <td class="p-3">
                                <div class="flex space-x-2">
                                    <button onclick="permanentlyDelete({{ $request->id }})" 
                                            class="action-btn delete" 
                                            title="Permanently Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($trashedRequests->hasPages())
            <div class="mt-6">
                {{ $trashedRequests->withQueryString()->links() }}
            </div>
            @endif
        @endif
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <div class="flex items-center mb-4">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-2xl" style="color: var(--danger);"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Permanently Delete</h3>
                </div>
            </div>
            
            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                Are you sure you want to permanently delete this maintenance request? This action cannot be undone.
            </p>
            
            <form id="deleteForm" method="POST" action="">
                @csrf
                @method('DELETE')
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded" style="background-color: var(--danger); color: white;">
                        Yes, Permanently Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let deleteId = null;

function permanentlyDelete(id) {
    deleteId = id;
    const modal = document.getElementById('deleteModal');
    const form = document.getElementById('deleteForm');
    
    if (modal && form) {
        form.action = '{{ route("tenant.maintenance.force-delete", ["id" => ":id"]) }}'.replace(':id', id);
        modal.classList.remove('hidden');
    }
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    deleteId = null;
}

document.addEventListener('click', function(e) {
    const modal = document.getElementById('deleteModal');
    if (e.target === modal) {
        closeDeleteModal();
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeDeleteModal();
    }
});
</script>
@endsection