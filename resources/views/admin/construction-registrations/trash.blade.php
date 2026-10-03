@extends('layouts.app')

@section('title', 'Trashed Registrations')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <!-- Icon -->
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--warning) 0%, var(--danger) 100%); color: white; font-weight: 600; border-color: var(--warning);">
                        <i class="fas fa-trash-alt text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-trash-alt mr-2" style="color: var(--warning);"></i> 
                        Trashed Registrations
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-history mr-2"></i>
                        <span>Recover or permanently delete registrations</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-clock mr-2"></i>
                        <span>Items are kept for 30 days</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <a href="{{ route('admin.construction-registrations.index') }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Registrations
                </a>
                @if($registrations->total() > 0)
                <button onclick="restoreAll()" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-undo-alt mr-2"></i> Restore All
                </button>
                <button onclick="emptyTrash()" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <i class="fas fa-trash-alt mr-2"></i> Empty Trash
                </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Info Banner -->
    <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.05); border-left: 4px solid var(--info);">
        <div class="flex items-center">
            <i class="fas fa-info-circle text-2xl mr-3" style="color: var(--info);"></i>
            <div>
                <p class="text-sm" style="color: var(--text-primary);">
                    <strong>{{ $registrations->total() }}</strong> registration(s) in trash. 
                    Items are automatically deleted after 30 days in trash.
                </p>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    Restore items to make them active again, or permanently delete them to free up space.
                </p>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filters
            </h3>
        </div>
        <div class="p-6">
            <form method="GET" action="{{ route('admin.construction-registrations.trash') }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Search</label>
                        <input type="text" 
                               name="search" 
                               class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               placeholder="Search by name, property, plot #..."
                               value="{{ request('search') }}">
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Deleted Date From</label>
                        <input type="date" 
                               name="date_from" 
                               class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               value="{{ request('date_from') }}">
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Deleted Date To</label>
                        <input type="date" 
                               name="date_to" 
                               class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               value="{{ request('date_to') }}">
                    </div>
                </div>
                <div class="flex space-x-2">
                    <button type="submit" 
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                    <a href="{{ route('admin.construction-registrations.trash') }}" 
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-times mr-2"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Actions -->
    @if($registrations->count() > 0)
    <div class="card">
        <div class="p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="font-semibold" style="color: var(--text-primary);">Bulk Actions</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Select registrations to restore or permanently delete
                    </p>
                </div>
                <div class="flex items-center space-x-2">
                    <button type="button" 
                            onclick="selectAll()" 
                            class="px-3 py-2 text-sm rounded-lg inline-flex items-center"
                            style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-check-square mr-2"></i> Select All
                    </button>
                    <button type="button" 
                            onclick="deselectAll()" 
                            class="px-3 py-2 text-sm rounded-lg inline-flex items-center"
                            style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-square mr-2"></i> Deselect All
                    </button>
                </div>
            </div>
            
            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                <select id="bulkActionSelect" 
                        class="form-input p-3 rounded-lg border col-span-1 md:col-span-2"
                        style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                    <option value="">Choose action...</option>
                    <option value="restore">Restore Selected</option>
                    <option value="force_delete">Permanently Delete Selected</option>
                </select>
                <button type="button" 
                        onclick="performBulkAction()" 
                        class="btn-primary p-3 rounded-lg font-medium inline-flex items-center justify-center"
                        id="bulkActionBtn">
                    <i class="fas fa-play mr-2"></i> Apply
                </button>
            </div>
        </div>
    </div>
    @endif

    <!-- Trashed Registrations Table -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--warning);"></i> Trashed Registrations
                </h3>
                <div class="text-sm" style="color: var(--text-secondary);">
                    Showing {{ $registrations->firstItem() }} to {{ $registrations->lastItem() }} of {{ $registrations->total() }} trashed registrations
                </div>
            </div>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b" style="border-color: var(--border-color);">
                            @if($registrations->count() > 0)
                            <th class="text-left py-3 px-4" style="width: 40px;">
                                <input type="checkbox" id="selectAllCheckbox" class="bulk-checkbox">
                            </th>
                            @endif
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Applicant</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Property</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Location</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Original Status</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Deleted</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($registrations as $registration)
                            <tr class="border-b transition-colors duration-150" 
                                style="border-color: var(--border-color); background-color: rgba(var(--danger-rgb), 0.02);">
                                @if($registrations->count() > 0)
                                <td class="py-3 px-4">
                                    <input type="checkbox" 
                                           class="registration-checkbox bulk-checkbox" 
                                           value="{{ $registration->id }}">
                                </td>
                                @endif
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <div class="applicant-icon mr-3">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                                 style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">
                                                {{ $registration->name }}
                                            </div>
                                            <div class="text-xs mt-1 flex items-center" style="color: var(--text-secondary);">
                                                <i class="fas fa-phone mr-1"></i> {{ $registration->primary_phone }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-medium" style="color: var(--text-primary);">
                                        {{ $registration->property_name ?: 'Not Specified' }}
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Type: {{ ucfirst($registration->property_type) }}
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div style="color: var(--text-primary);">
                                        Plot {{ $registration->plot_number }}
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        {{ $registration->street_name }}
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    @php
                                        $statusStyles = [
                                            'pending' => ['bg' => 'rgba(var(--warning-rgb), 0.1)', 'text' => 'var(--warning)'],
                                            'in_review' => ['bg' => 'rgba(var(--info-rgb), 0.1)', 'text' => 'var(--info)'],
                                            'approved' => ['bg' => 'rgba(var(--success-rgb), 0.1)', 'text' => 'var(--success)'],
                                            'rejected' => ['bg' => 'rgba(var(--danger-rgb), 0.1)', 'text' => 'var(--danger)'],
                                            'needs_info' => ['bg' => 'rgba(var(--warning-rgb), 0.1)', 'text' => 'var(--warning)'],
                                        ];
                                        $style = $statusStyles[$registration->status] ?? $statusStyles['pending'];
                                    @endphp
                                    <span class="px-3 py-1 rounded-full text-xs font-medium"
                                          style="background-color: {{ $style['bg'] }}; color: {{ $style['text'] }};">
                                        {{ ucfirst(str_replace('_', ' ', $registration->status)) }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <div style="color: var(--text-primary);">
                                        {{ $registration->deleted_at->format('M d, Y') }}
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        {{ $registration->deleted_at->diffForHumans() }}
                                    </div>
                                    @php
                                        $daysInTrash = $registration->deleted_at->diffInDays(now());
                                        $daysLeft = max(0, 30 - $daysInTrash);
                                    @endphp
                                    @if($daysLeft > 0)
                                    <div class="text-xs mt-1" style="color: var(--warning);">
                                        <i class="fas fa-clock mr-1"></i> {{ $daysLeft }} days left
                                    </div>
                                    @else
                                    <div class="text-xs mt-1" style="color: var(--danger);">
                                        <i class="fas fa-exclamation-circle mr-1"></i> Auto-delete soon
                                    </div>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center space-x-2">
                                        <button onclick="restoreRegistration({{ $registration->id }}, '{{ $registration->name }}')"
                                                class="action-btn" 
                                                title="Restore"
                                                style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                            <i class="fas fa-undo-alt"></i>
                                        </button>
                                        <button onclick="showForceDeleteModal({{ $registration->id }}, '{{ $registration->name }}')"
                                                class="action-btn" 
                                                title="Permanently Delete"
                                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                        <a href="{{ route('admin.construction-registrations.show', $registration->id) }}" 
                                           class="action-btn" 
                                           title="View Details"
                                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-8 px-4 text-center" style="color: var(--text-secondary);">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="fas fa-trash-alt text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                                        <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">Trash is empty</p>
                                        <p style="color: var(--text-secondary);">No deleted registrations found</p>
                                        <div class="mt-4">
                                            <a href="{{ route('admin.construction-registrations.index') }}" 
                                               class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                                                <i class="fas fa-arrow-left mr-2"></i> Back to Registrations
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($registrations->hasPages())
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    {{ $registrations->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Restore Modal -->
<div id="restoreModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('restoreModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Restore Registration</h3>
            <button type="button" class="modal-close" onclick="closeModal('restoreModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="text-center">
                <i class="fas fa-undo-alt text-5xl mb-4" style="color: var(--success);"></i>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);" id="restoreRegistrationName">
                    <!-- Registration name will be inserted here -->
                </h4>
                <p class="mb-4" style="color: var(--text-secondary);">
                    Are you sure you want to restore this registration? It will be moved back to the active list.
                </p>
                @if(isset($registration) && $registration->approvedProperty ?? false)
                <div class="p-3 mb-4 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <p class="text-sm" style="color: var(--warning);">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        This registration has an associated property. Restoring will reactivate the property.
                    </p>
                </div>
                @endif
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('restoreModal')">
                Cancel
            </button>
            <form id="restoreForm" method="POST" style="display: inline;">
                @csrf
            </form>
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background-color: var(--success); border: 1px solid var(--success);"
                    onclick="confirmRestore()">
                <i class="fas fa-undo-alt mr-2"></i> Restore
            </button>
        </div>
    </div>
</div>

<!-- Force Delete Modal -->
<div id="forceDeleteModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('forceDeleteModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Permanently Delete Registration</h3>
            <button type="button" class="modal-close" onclick="closeModal('forceDeleteModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="text-center">
                <i class="fas fa-exclamation-triangle text-5xl mb-4" style="color: var(--danger);"></i>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);" id="forceDeleteRegistrationName">
                    <!-- Registration name will be inserted here -->
                </h4>
                <p class="mb-4" style="color: var(--text-secondary);">
                    This action <strong class="text-danger">cannot be undone</strong>. This will permanently delete:
                </p>
                <ul class="text-left mb-4 p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                    <li class="flex items-center mb-2">
                        <i class="fas fa-times-circle text-danger mr-2"></i>
                        <span style="color: var(--text-primary);">Registration record</span>
                    </li>
                    <li class="flex items-center mb-2">
                        <i class="fas fa-times-circle text-danger mr-2"></i>
                        <span style="color: var(--text-primary);">All associated documents</span>
                    </li>
                    <li class="flex items-center mb-2">
                        <i class="fas fa-times-circle text-danger mr-2"></i>
                        <span style="color: var(--text-primary);">All notes and history</span>
                    </li>
                    @if(isset($registration) && $registration->approvedProperty ?? false)
                    <li class="flex items-center">
                        <i class="fas fa-times-circle text-danger mr-2"></i>
                        <span style="color: var(--text-primary);">Associated property ({{ $registration->approvedProperty->property_name ?? '' }})</span>
                    </li>
                    @endif
                </ul>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('forceDeleteModal')">
                Cancel
            </button>
            <form id="forceDeleteForm" method="POST" style="display: inline;">
                @csrf
                @method('DELETE')
            </form>
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background-color: var(--danger); border: 1px solid var(--danger);"
                    onclick="confirmForceDelete()">
                <i class="fas fa-trash-alt mr-2"></i> Permanently Delete
            </button>
        </div>
    </div>
</div>

<!-- Empty Trash Modal -->
<div id="emptyTrashModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('emptyTrashModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Empty Trash</h3>
            <button type="button" class="modal-close" onclick="closeModal('emptyTrashModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="text-center">
                <i class="fas fa-trash-alt text-5xl mb-4" style="color: var(--danger);"></i>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Empty Trash</h4>
                <p class="mb-4" style="color: var(--text-secondary);">
                    Are you sure you want to permanently delete all {{ $registrations->total() }} registrations in trash?
                </p>
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <p class="text-sm" style="color: var(--danger);">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        This action cannot be undone. All registrations, documents, and notes will be permanently deleted.
                    </p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('emptyTrashModal')">
                Cancel
            </button>
            <form id="emptyTrashForm" action="{{ route('admin.construction-registrations.empty-trash') }}" method="POST" style="display: inline;">
                @csrf
                @method('DELETE')
            </form>
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background-color: var(--danger); border: 1px solid var(--danger);"
                    onclick="confirmEmptyTrash()">
                <i class="fas fa-trash-alt mr-2"></i> Empty Trash
            </button>
        </div>
    </div>
</div>

<!-- Restore All Modal -->
<div id="restoreAllModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('restoreAllModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Restore All Registrations</h3>
            <button type="button" class="modal-close" onclick="closeModal('restoreAllModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="text-center">
                <i class="fas fa-undo-alt text-5xl mb-4" style="color: var(--success);"></i>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Restore All Registrations</h4>
                <p class="mb-4" style="color: var(--text-secondary);">
                    Are you sure you want to restore all {{ $registrations->total() }} registrations from trash?
                </p>
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <p class="text-sm" style="color: var(--success);">
                        <i class="fas fa-info-circle mr-2"></i>
                        All registrations will be moved back to the active list with their original status.
                    </p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('restoreAllModal')">
                Cancel
            </button>
            <form id="restoreAllForm" action="{{ route('admin.construction-registrations.restore-all') }}" method="POST" style="display: inline;">
                @csrf
            </form>
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background-color: var(--success); border: 1px solid var(--success);"
                    onclick="confirmRestoreAll()">
                <i class="fas fa-undo-alt mr-2"></i> Restore All
            </button>
        </div>
    </div>
</div>

<!-- Bulk Action Form - FIXED: Using proper route and improved structure -->
<form id="bulkActionForm" action="{{ route('admin.construction-registrations.bulk-action') }}" method="POST" style="display: none;">
    @csrf
    <input type="hidden" name="action" id="bulkActionInput">
    <input type="hidden" name="registration_ids" id="bulkRegistrationIds">
</form>

<!-- Toast Container for notifications -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

@endsection

@push('styles')
<style>
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    visibility: hidden;
    opacity: 0;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.modal.active {
    visibility: visible;
    opacity: 1;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
}

.modal-container {
    position: relative;
    background-color: var(--card-bg);
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    z-index: 1001;
    border: 1px solid var(--border-color);
}

.modal-header {
    padding: 1.5rem;
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
    font-size: 1.25rem;
    cursor: pointer;
    color: var(--text-secondary);
    padding: 0.5rem;
    border-radius: 8px;
    transition: all 0.2s;
}

.modal-close:hover {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

.action-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.bulk-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--primary);
}

.text-danger {
    color: var(--danger) !important;
}

.bg-secondary {
    background-color: var(--bg-secondary);
}

/* Toast notification styles */
.toast {
    min-width: 300px;
    padding: 1rem;
    border-radius: 8px;
    background-color: var(--card-bg);
    border-left: 4px solid;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    animation: slideIn 0.3s ease;
}

.toast-success { border-left-color: var(--success); }
.toast-error { border-left-color: var(--danger); }
.toast-warning { border-left-color: var(--warning); }
.toast-info { border-left-color: var(--info); }

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .modal-container {
        width: 95%;
        margin: 1rem;
    }
    
    .modal-footer {
        flex-direction: column;
    }
    
    .modal-footer button {
        width: 100%;
    }
}
</style>
@endpush

@push('scripts')
<script>
// Toast notification function
function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    let icon = '';
    switch(type) {
        case 'success':
            icon = 'fa-check-circle';
            break;
        case 'error':
            icon = 'fa-exclamation-circle';
            break;
        case 'warning':
            icon = 'fa-exclamation-triangle';
            break;
        default:
            icon = 'fa-info-circle';
    }
    
    toast.innerHTML = `
        <i class="fas ${icon} mr-3" style="color: var(--${type});"></i>
        <span style="color: var(--text-primary);">${message}</span>
    `;
    
    container.appendChild(toast);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s ease reverse';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }, 3000);
}

// Modal management
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Close modal when pressing Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.active').forEach(modal => {
            modal.classList.remove('active');
        });
        document.body.style.overflow = '';
    }
});

// Restore functions
function restoreRegistration(id, name) {
    document.getElementById('restoreRegistrationName').textContent = name;
    // FIXED: Use Laravel's route helper with the ID parameter properly
    document.getElementById('restoreForm').action = '{{ route("admin.construction-registrations.restore", ["id" => "PLACEHOLDER"]) }}'.replace('PLACEHOLDER', id);
    openModal('restoreModal');
}

function confirmRestore() {
    document.getElementById('restoreForm').submit();
}

// Force delete functions
function showForceDeleteModal(id, name) {
    document.getElementById('forceDeleteRegistrationName').textContent = name;
    // FIXED: Use Laravel's route helper with the ID parameter properly
    document.getElementById('forceDeleteForm').action = '{{ route("admin.construction-registrations.force-delete", ["id" => "PLACEHOLDER"]) }}'.replace('PLACEHOLDER', id);
    openModal('forceDeleteModal');
}

function confirmForceDelete() {
    document.getElementById('forceDeleteForm').submit();
}

// Restore all
function restoreAll() {
    openModal('restoreAllModal');
}

function confirmRestoreAll() {
    document.getElementById('restoreAllForm').submit();
}

// Empty trash
function emptyTrash() {
    openModal('emptyTrashModal');
}

function confirmEmptyTrash() {
    document.getElementById('emptyTrashForm').submit();
}

// Bulk selection
function selectAll() {
    document.querySelectorAll('.registration-checkbox').forEach(checkbox => {
        checkbox.checked = true;
    });
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = true;
    }
}

function deselectAll() {
    document.querySelectorAll('.registration-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
    }
}

// Select all checkbox functionality
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function(e) {
            document.querySelectorAll('.registration-checkbox').forEach(checkbox => {
                checkbox.checked = e.target.checked;
            });
        });
    }
});

// FIXED: Bulk action function with proper validation and submission
function performBulkAction() {
    const actionSelect = document.getElementById('bulkActionSelect');
    if (!actionSelect) {
        console.error('Bulk action select not found');
        showToast('Bulk action selector not found', 'error');
        return;
    }
    
    const action = actionSelect.value;
    const selectedCheckboxes = document.querySelectorAll('.registration-checkbox:checked');
    const selectedIds = Array.from(selectedCheckboxes).map(checkbox => checkbox.value);
    
    if (!action) {
        showToast('Please select an action', 'warning');
        return;
    }
    
    if (selectedIds.length === 0) {
        showToast('Please select at least one registration', 'warning');
        return;
    }
    
    let confirmMessage = '';
    let actionType = '';
    
    if (action === 'restore') {
        confirmMessage = `Are you sure you want to restore ${selectedIds.length} registration(s)?`;
        actionType = 'restore';
    } else if (action === 'force_delete') {
        confirmMessage = `Are you sure you want to permanently delete ${selectedIds.length} registration(s)? This action cannot be undone.`;
        actionType = 'force_delete';
    } else {
        showToast('Invalid action selected', 'error');
        return;
    }
    
    if (confirm(confirmMessage)) {
        // Set the form values
        document.getElementById('bulkActionInput').value = action;
        document.getElementById('bulkRegistrationIds').value = JSON.stringify(selectedIds);
        
        // Submit the form
        const form = document.getElementById('bulkActionForm');
        
        // Show loading state on button
        const applyBtn = document.getElementById('bulkActionBtn');
        const originalText = applyBtn.innerHTML;
        applyBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
        applyBtn.disabled = true;
        
        // Submit the form
        form.submit();
    }
}

// Initialize any tooltips or additional functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Trash page initialized');
});
</script>
@endpush