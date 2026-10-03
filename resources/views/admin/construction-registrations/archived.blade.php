@extends('layouts.app')

@section('title', 'Archived Registrations')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-archive text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-archive mr-2" style="color: var(--primary);"></i> 
                        Archived Registrations
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-history mr-2"></i>
                        <span>View and manage archived registration records</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>Admin: {{ auth()->user()->name }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <a href="{{ route('admin.construction-registrations.index') }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Active
                </a>
                <button onclick="showExportModal()" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-download mr-2"></i> Export
                </button>
                <button onclick="showArchiveStats()" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-chart-bar mr-2"></i> Statistics
                </button>
                <button onclick="showBulkRestoreModal()" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-undo-alt mr-2"></i> Bulk Restore
                </button>
                <button onclick="showBulkPermanentDeleteModal()" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <i class="fas fa-trash-alt mr-2"></i> Permanent Delete
                </button>
            </div>
        </div>
    </div>

    <!-- Navigation Card -->
    <div class="card p-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.dashboard') }}" 
                   class="inline-flex items-center text-sm font-medium" 
                   style="color: var(--primary);">
                    <i class="fas fa-arrow-left mr-2"></i> Dashboard
                </a>
                
                <a href="{{ route('admin.construction-registrations.index') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-list mr-1"></i> Active Registrations
                </a>
                
                <a href="{{ route('admin.construction-registrations.stats') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-chart-bar mr-1"></i> Statistics
                </a>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
            </div>
        </div>
    </div>

    <!-- Archive Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-archive"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" id="totalArchived" style="color: var(--primary);">0</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Archived</div>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" id="approvedArchived" style="color: var(--success);">0</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Approved</div>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" id="rejectedArchived" style="color: var(--danger);">0</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Rejected</div>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" id="pendingArchived" style="color: var(--warning);">0</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Pending</div>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" id="yearsCount" style="color: var(--info);">0</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Years Archived</div>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                        <i class="fas fa-database"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" id="storageSize" style="color: var(--danger);">0</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Storage Used (MB)</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filters
                </h3>
                @if(request()->hasAny(['search', 'status', 'year', 'registration_type']))
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-filter mr-1"></i> Filters active
                </div>
                @endif
            </div>
        </div>
        <div class="p-6">
            <form method="GET" class="space-y-4" id="filterForm">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Search</label>
                        <input type="text" 
                               name="search" 
                               class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               placeholder="Name, property, plot #..."
                               value="{{ request('search') }}">
                    </div>
                    
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Archive Year</label>
                        <select name="year" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Years</option>
                            @foreach($archiveYears as $year)
                            <option value="{{ $year }}" {{ request('year') == $year ? 'selected' : '' }}>
                                {{ $year }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Status</label>
                        <select name="status" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Statuses</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Registration Type</label>
                        <select name="registration_type" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Types</option>
                            <option value="construction" {{ request('registration_type') == 'construction' ? 'selected' : '' }}>Construction</option>
                            <option value="property_capture" {{ request('registration_type') == 'property_capture' ? 'selected' : '' }}>Property Capture</option>
                        </select>
                    </div>
                </div>
                <div class="flex space-x-2">
                    <button type="submit" 
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                    <a href="{{ route('admin.construction-registrations.archived') }}" 
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
                        Select registrations to restore or permanently delete from archive
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
            
            <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-3">
                <select id="bulkYearSelect" class="form-input p-3 rounded-lg border col-span-1 md:col-span-2"
                        style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                    <option value="">Restore by Year...</option>
                    @foreach($archiveYears as $year)
                    <option value="{{ $year }}">{{ $year }} ({{ $yearlyCounts[$year] ?? 0 }} registrations)</option>
                    @endforeach
                </select>
                <button type="button" 
                        onclick="bulkRestoreByYear()" 
                        class="btn-primary p-3 rounded-lg font-medium inline-flex items-center justify-center">
                    <i class="fas fa-undo-alt mr-2"></i> Restore Selected Year
                </button>
                <button type="button" 
                        onclick="bulkPermanentDeleteByYear()" 
                        class="p-3 rounded-lg font-medium inline-flex items-center justify-center"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <i class="fas fa-trash-alt mr-2"></i> Delete Year Permanently
                </button>
            </div>
            
            <div class="mt-4 flex space-x-3">
                <button type="button" 
                        onclick="bulkRestoreSelected()" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);"
                        id="bulkRestoreSelectedBtn" disabled>
                    <i class="fas fa-undo-alt mr-2"></i> Restore Selected (<span id="selectedCount">0</span>)
                </button>
                <button type="button" 
                        onclick="bulkPermanentDeleteSelected()" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);"
                        id="bulkDeleteSelectedBtn" disabled>
                    <i class="fas fa-trash-alt mr-2"></i> Permanently Delete Selected (<span id="deleteSelectedCount">0</span>)
                </button>
            </div>
        </div>
    </div>
    @endif

    <!-- Archived Registrations Table Card -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-archive mr-2" style="color: var(--primary);"></i> Archived Registrations
                </h3>
                <div class="text-sm" style="color: var(--text-secondary);">
                    Showing {{ $registrations->firstItem() }} to {{ $registrations->lastItem() }} of {{ $registrations->total() }} archived registrations
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
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Status</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Archive Year</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Archived At</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($registrations as $registration)
                            <tr class="border-b transition-colors duration-150" 
                                style="border-color: var(--border-color);">
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
                                                 style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">
                                                {{ $registration->name }}
                                            </div>
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                <i class="fas fa-phone mr-1"></i> {{ $registration->primary_phone }}
                                            </div>
                                            @if($registration->email)
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                <i class="fas fa-envelope mr-1"></i> {{ $registration->email }}
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-medium" style="color: var(--text-primary);">
                                        {{ $registration->property_name ?: 'Not Specified' }}
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Plot: {{ $registration->plot_number }}
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Type: {{ $registration->registration_type_label }}
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    @php
                                        $statusStyles = [
                                            'pending' => ['bg' => 'rgba(var(--warning-rgb), 0.1)', 'text' => 'var(--warning)', 'icon' => 'fa-clock'],
                                            'approved' => ['bg' => 'rgba(var(--success-rgb), 0.1)', 'text' => 'var(--success)', 'icon' => 'fa-check-circle'],
                                            'rejected' => ['bg' => 'rgba(var(--danger-rgb), 0.1)', 'text' => 'var(--danger)', 'icon' => 'fa-times-circle'],
                                            'cancelled' => ['bg' => 'rgba(var(--secondary-rgb), 0.1)', 'text' => 'var(--secondary)', 'icon' => 'fa-ban'],
                                        ];
                                        $style = $statusStyles[$registration->status] ?? $statusStyles['pending'];
                                    @endphp
                                    <span class="px-3 py-1 rounded-full text-xs font-medium inline-flex items-center"
                                          style="background-color: {{ $style['bg'] }}; color: {{ $style['text'] }};">
                                        <i class="fas {{ $style['icon'] }} mr-1"></i>
                                        {{ $registration->status_label }}
                                    </span>
                                 </td>
                                <td class="py-3 px-4">
                                    <span class="px-3 py-1 rounded-full text-xs font-medium"
                                          style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                        <i class="fas fa-calendar mr-1"></i>
                                        {{ $registration->archive_year }}
                                    </span>
                                 </td>
                                <td class="py-3 px-4">
                                    <div style="color: var(--text-primary);">
                                        {{ $registration->archived_at ? $registration->archived_at->format('M d, Y') : 'N/A' }}
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        {{ $registration->archived_at ? $registration->archived_at->diffForHumans() : '' }}
                                    </div>
                                    @if($registration->archivedBy)
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-user-shield mr-1"></i>
                                        By: {{ $registration->archivedBy->name }}
                                    </div>
                                    @endif
                                 </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center space-x-2">
                                        <a href="{{ route('admin.construction-registrations.show', $registration) }}" 
                                           class="action-btn" 
                                           title="View Details"
                                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <button onclick="restoreRegistration({{ $registration->id }}, '{{ addslashes($registration->name) }}', '{{ addslashes($registration->property_name) }}')"
                                                class="action-btn" 
                                                title="Restore from Archive"
                                                style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                            <i class="fas fa-undo-alt"></i>
                                        </button>
                                        
                                        <button onclick="permanentDeleteRegistration({{ $registration->id }}, '{{ addslashes($registration->name) }}', '{{ addslashes($registration->property_name) }}')"
                                                class="action-btn" 
                                                title="Permanently Delete"
                                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                 </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 px-4 text-center" style="color: var(--text-secondary);">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="fas fa-archive text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                                        <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">No archived registrations found</p>
                                        <p style="color: var(--text-secondary);">Try adjusting your filters or archive some registrations first</p>
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

<!-- Export Modal -->
<div id="exportModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('exportModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Export Archived Registrations</h3>
            <button type="button" class="modal-close" onclick="closeModal('exportModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="space-y-4">
                <div>
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">Export Format</label>
                    <select id="exportFormat" class="form-input w-full p-3 rounded-lg border"
                            style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                        <option value="csv">CSV (Excel Compatible)</option>
                        <option value="pdf">PDF Report</option>
                        <option value="excel">Excel (XLSX)</option>
                    </select>
                </div>
                
                <div>
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">Export Type</label>
                    <select id="exportType" class="form-input w-full p-3 rounded-lg border"
                            style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                        <option value="filtered">Export Filtered Data</option>
                        <option value="selected">Export Selected Records Only</option>
                        <option value="all">Export All Archived Records</option>
                    </select>
                </div>
                
                <div id="selectedCountInfo" class="hidden p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                    <p class="text-sm" id="selectedCountText"></p>
                </div>
                
                <div>
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">Include Fields</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="checkbox-label"><input type="checkbox" class="export-field" value="applicant_info" checked> Applicant Info</label>
                        <label class="checkbox-label"><input type="checkbox" class="export-field" value="property_details" checked> Property Details</label>
                        <label class="checkbox-label"><input type="checkbox" class="export-field" value="contact_info" checked> Contact Info</label>
                        <label class="checkbox-label"><input type="checkbox" class="export-field" value="status_info" checked> Status Info</label>
                        <label class="checkbox-label"><input type="checkbox" class="export-field" value="archive_metadata" checked> Archive Metadata</label>
                        <label class="checkbox-label"><input type="checkbox" class="export-field" value="tenant_info"> Tenant Info</label>
                    </div>
                </div>
                
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        PDF reports will be downloaded directly (not opened in a new tab).
                    </p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('exportModal')">
                Cancel
            </button>
            <button type="button" 
                    onclick="performExport()"
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                <i class="fas fa-download mr-2"></i> Export
            </button>
        </div>
    </div>
</div>

<!-- Statistics Modal -->
<div id="statsModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('statsModal')"></div>
    <div class="modal-container" style="max-width: 700px;">
        <div class="modal-header">
            <h3 class="modal-title">Archive Statistics</h3>
            <button type="button" class="modal-close" onclick="closeModal('statsModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div id="statsContent" class="space-y-4">
                <div class="text-center py-8">
                    <div class="loading-dots">
                        <span></span><span></span><span></span>
                    </div>
                    <p class="mt-2 text-slate-500">Loading statistics...</p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('statsModal')">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Bulk Restore Modal -->
<div id="bulkRestoreModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('bulkRestoreModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Bulk Restore</h3>
            <button type="button" class="modal-close" onclick="closeModal('bulkRestoreModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="space-y-4">
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.1);">
                    <p class="text-sm" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle mr-1 text-info"></i>
                        Restoring registrations will move them back to active status.
                    </p>
                </div>
                
                <div>
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">Restore by Year</label>
                    <select id="restoreYear" class="form-input w-full p-3 rounded-lg border"
                            style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                        <option value="">Select Year...</option>
                        @foreach($archiveYears as $year)
                        <option value="{{ $year }}">{{ $year }} ({{ $yearlyCounts[$year] ?? 0 }} registrations)</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('bulkRestoreModal')">
                Cancel
            </button>
            <button type="button" 
                    onclick="confirmBulkRestore()"
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background-color: var(--success);">
                <i class="fas fa-undo-alt mr-2"></i> Restore All
            </button>
        </div>
    </div>
</div>

<!-- Bulk Permanent Delete Modal -->
<div id="bulkPermanentDeleteModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('bulkPermanentDeleteModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Permanent Delete - Warning!</h3>
            <button type="button" class="modal-close" onclick="closeModal('bulkPermanentDeleteModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="space-y-4">
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <p class="text-sm font-medium mb-2" style="color: var(--danger);">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        ⚠️ THIS ACTION CANNOT BE UNDONE!
                    </p>
                    <p class="text-sm" style="color: var(--text-primary);">
                        Permanently deleting registrations will remove them completely from the archive.
                        This data will be lost forever and cannot be recovered.
                    </p>
                </div>
                
                <div>
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">Delete by Year</label>
                    <select id="permanentDeleteYear" class="form-input w-full p-3 rounded-lg border"
                            style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                        <option value="">Select Year...</option>
                        @foreach($archiveYears as $year)
                        <option value="{{ $year }}">{{ $year }} ({{ $yearlyCounts[$year] ?? 0 }} registrations)</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="confirmPermanentDelete">
                    <label for="confirmPermanentDelete" style="color: var(--danger); font-weight: 500;">
                        I understand that this action is irreversible and I want to permanently delete these records
                    </label>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('bulkPermanentDeleteModal')">
                Cancel
            </button>
            <button type="button" 
                    onclick="confirmBulkPermanentDelete()"
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background-color: var(--danger);"
                    id="confirmDeleteBtn" disabled>
                <i class="fas fa-trash-alt mr-2"></i> Permanently Delete
            </button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

@endsection

@section('scripts')
<script>
// Global variables
let selectedIds = [];

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    initializeBulkSelection();
    loadArchiveStats();
    initializeYearSelect();
    initializeExportModal();
    initializePermanentDeleteConfirmation();
});

function initializeBulkSelection() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('.registration-checkbox');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });
    }
    
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.registration-checkbox:checked');
    selectedIds = Array.from(checkboxes).map(cb => cb.value);
    const count = selectedIds.length;
    
    const selectedCountSpan = document.getElementById('selectedCount');
    const deleteSelectedCountSpan = document.getElementById('deleteSelectedCount');
    const bulkRestoreBtn = document.getElementById('bulkRestoreSelectedBtn');
    const bulkDeleteBtn = document.getElementById('bulkDeleteSelectedBtn');
    
    if (selectedCountSpan) selectedCountSpan.textContent = count;
    if (deleteSelectedCountSpan) deleteSelectedCountSpan.textContent = count;
    if (bulkRestoreBtn) bulkRestoreBtn.disabled = count === 0;
    if (bulkDeleteBtn) bulkDeleteBtn.disabled = count === 0;
}

function selectAll() {
    document.querySelectorAll('.registration-checkbox').forEach(cb => cb.checked = true);
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (selectAllCheckbox) selectAllCheckbox.checked = true;
    updateSelectedCount();
}

function deselectAll() {
    document.querySelectorAll('.registration-checkbox').forEach(cb => cb.checked = false);
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (selectAllCheckbox) selectAllCheckbox.checked = false;
    updateSelectedCount();
}

// Export Functions
function showExportModal() {
    const selectedCount = selectedIds.length;
    const selectedCountInfo = document.getElementById('selectedCountInfo');
    const selectedCountText = document.getElementById('selectedCountText');
    
    if (selectedCountInfo && selectedCountText) {
        if (selectedCount > 0) {
            selectedCountText.textContent = `${selectedCount} registration(s) selected for export`;
            selectedCountInfo.classList.remove('hidden');
        } else {
            selectedCountInfo.classList.add('hidden');
        }
    }
    
    openModal('exportModal');
}

function initializeExportModal() {
    const exportTypeSelect = document.getElementById('exportType');
    if (exportTypeSelect) {
        exportTypeSelect.addEventListener('change', function() {
            const selectedCountInfo = document.getElementById('selectedCountInfo');
            if (this.value === 'selected') {
                if (selectedCountInfo) {
                    selectedCountInfo.classList.remove('hidden');
                }
            } else {
                if (selectedCountInfo) {
                    selectedCountInfo.classList.add('hidden');
                }
            }
        });
    }
}

async function performExport() {
    const format = document.getElementById('exportFormat').value;
    const exportType = document.getElementById('exportType').value;
    const selectedFields = Array.from(document.querySelectorAll('.export-field:checked')).map(cb => cb.value);
    
    let url = `{{ route("admin.construction-registrations.export-archived") }}?format=${format}&export_type=${exportType}&fields=${selectedFields.join(',')}`;
    
    // Add current filters
    const formData = new FormData(document.getElementById('filterForm'));
    for (let [key, value] of formData.entries()) {
        if (value && key !== '_token') {
            url += `&${key}=${encodeURIComponent(value)}`;
        }
    }
    
    // Add selected IDs for selected export type
    if (exportType === 'selected' && selectedIds.length > 0) {
        url += `&ids=${selectedIds.join(',')}`;
    }
    
    try {
        showLoading('Preparing export...');
        
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': format === 'pdf' ? 'application/pdf' : 'application/octet-stream'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const blob = await response.blob();
        const downloadUrl = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = downloadUrl;
        
        let extension = '';
        if (format === 'pdf') {
            extension = 'pdf';
        } else if (format === 'excel') {
            extension = 'xlsx';
        } else {
            extension = 'csv';
        }
        
        a.download = `archived-registrations-${new Date().toISOString().slice(0,19)}.${extension}`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(downloadUrl);
        
        hideLoading();
        closeModal('exportModal');
        showToast(`Export completed successfully (${format.toUpperCase()})`, 'success');
        
    } catch (error) {
        hideLoading();
        console.error('Export error:', error);
        showToast('Failed to export data: ' + error.message, 'error');
    }
}

// Restore Functions
async function restoreRegistration(registrationId, name, propertyName) {
    if (!confirm(`Are you sure you want to restore "${name}"'s registration for "${propertyName}" from archive?`)) {
        return;
    }
    
    try {
        showLoading('Restoring registration...');
        
        const response = await fetch(`{{ url('admin/construction-registrations/restore-archived') }}/${registrationId}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        hideLoading();
        
        if (data.success) {
            showToast('Registration restored successfully', 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast(data.message || 'Failed to restore registration', 'error');
        }
    } catch (error) {
        hideLoading();
        console.error('Error:', error);
        showToast('Network error occurred', 'error');
    }
}

// Permanent Delete Functions
async function permanentDeleteRegistration(registrationId, name, propertyName) {
    if (!confirm(`⚠️ PERMANENT DELETE WARNING!\n\nYou are about to permanently delete "${name}"'s registration for "${propertyName}".\n\nTHIS ACTION CANNOT BE UNDONE!\n\nAre you absolutely sure?`)) {
        return;
    }
    
    try {
        showLoading('Permanently deleting registration...');
        
        const response = await fetch(`{{ url('admin/construction-registrations/permanent-delete') }}/${registrationId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        hideLoading();
        
        if (data.success) {
            showToast('Registration permanently deleted', 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast(data.message || 'Failed to delete registration', 'error');
        }
    } catch (error) {
        hideLoading();
        console.error('Error:', error);
        showToast('Network error occurred', 'error');
    }
}

function showBulkRestoreModal() {
    openModal('bulkRestoreModal');
}

function showBulkPermanentDeleteModal() {
    document.getElementById('confirmPermanentDelete').checked = false;
    document.getElementById('confirmDeleteBtn').disabled = true;
    openModal('bulkPermanentDeleteModal');
}

function initializePermanentDeleteConfirmation() {
    const confirmCheckbox = document.getElementById('confirmPermanentDelete');
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    
    if (confirmCheckbox && confirmBtn) {
        confirmCheckbox.addEventListener('change', function() {
            confirmBtn.disabled = !this.checked;
        });
    }
}

async function confirmBulkRestore() {
    const year = document.getElementById('restoreYear').value;
    
    if (!year) {
        showToast('Please select a year to restore', 'warning');
        return;
    }
    
    if (!confirm(`Are you sure you want to restore ALL registrations from year ${year}? They will become active again.`)) {
        return;
    }
    
    try {
        showLoading('Restoring registrations...');
        
        const response = await fetch(`{{ route("admin.construction-registrations.restore-archived") }}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                year: year
            })
        });
        
        const data = await response.json();
        hideLoading();
        closeModal('bulkRestoreModal');
        
        if (data.success) {
            showToast(`Successfully restored ${data.count} registration(s)`, 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast(data.message || 'Failed to restore registrations', 'error');
        }
    } catch (error) {
        hideLoading();
        console.error('Error:', error);
        showToast('Network error occurred', 'error');
    }
}

async function confirmBulkPermanentDelete() {
    const year = document.getElementById('permanentDeleteYear').value;
    const confirmed = document.getElementById('confirmPermanentDelete').checked;
    
    if (!year) {
        showToast('Please select a year to delete', 'warning');
        return;
    }
    
    if (!confirmed) {
        showToast('Please confirm that you understand this action is irreversible', 'warning');
        return;
    }
    
    if (!confirm(`⚠️ FINAL WARNING!\n\nYou are about to PERMANENTLY DELETE ALL archived registrations from year ${year}.\n\nTHIS DATA WILL BE LOST FOREVER AND CANNOT BE RECOVERED!\n\nType "DELETE" to confirm:`)) {
        return;
    }
    
    const confirmation = prompt('Type "DELETE" to confirm permanent deletion:');
    if (confirmation !== 'DELETE') {
        showToast('Confirmation cancelled - incorrect text entered', 'warning');
        return;
    }
    
    try {
        showLoading('Permanently deleting registrations...');
        
        const response = await fetch(`{{ route("admin.construction-registrations.permanent-delete-bulk") }}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                year: year
            })
        });
        
        const data = await response.json();
        hideLoading();
        closeModal('bulkPermanentDeleteModal');
        
        if (data.success) {
            showToast(`Successfully deleted ${data.count} registration(s) permanently`, 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast(data.message || 'Failed to delete registrations', 'error');
        }
    } catch (error) {
        hideLoading();
        console.error('Error:', error);
        showToast('Network error occurred', 'error');
    }
}

async function bulkRestoreSelected() {
    if (selectedIds.length === 0) {
        showToast('Please select registrations to restore', 'warning');
        return;
    }
    
    if (!confirm(`Are you sure you want to restore ${selectedIds.length} selected registration(s) from archive?`)) {
        return;
    }
    
    try {
        showLoading('Restoring selected registrations...');
        
        const response = await fetch(`{{ route("admin.construction-registrations.restore-archived") }}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                registration_ids: selectedIds
            })
        });
        
        const data = await response.json();
        hideLoading();
        
        if (data.success) {
            showToast(`Successfully restored ${data.count} registration(s)`, 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast(data.message || 'Failed to restore registrations', 'error');
        }
    } catch (error) {
        hideLoading();
        console.error('Error:', error);
        showToast('Network error occurred', 'error');
    }
}

async function bulkPermanentDeleteSelected() {
    if (selectedIds.length === 0) {
        showToast('Please select registrations to delete', 'warning');
        return;
    }
    
    const confirmation = prompt(`⚠️ PERMANENT DELETE WARNING!\n\nYou are about to permanently delete ${selectedIds.length} selected registration(s).\n\nTHIS ACTION CANNOT BE UNDONE!\n\nType "DELETE" to confirm:`);
    if (confirmation !== 'DELETE') {
        showToast('Deletion cancelled - incorrect confirmation', 'warning');
        return;
    }
    
    try {
        showLoading('Permanently deleting selected registrations...');
        
        const response = await fetch(`{{ route("admin.construction-registrations.permanent-delete-bulk") }}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                registration_ids: selectedIds
            })
        });
        
        const data = await response.json();
        hideLoading();
        
        if (data.success) {
            showToast(`Successfully deleted ${data.count} registration(s) permanently`, 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast(data.message || 'Failed to delete registrations', 'error');
        }
    } catch (error) {
        hideLoading();
        console.error('Error:', error);
        showToast('Network error occurred', 'error');
    }
}

async function bulkPermanentDeleteByYear() {
    const year = document.getElementById('bulkYearSelect').value;
    
    if (!year) {
        showToast('Please select a year to delete', 'warning');
        return;
    }
    
    const confirmation = prompt(`⚠️ PERMANENT DELETE WARNING!\n\nYou are about to permanently delete ALL archived registrations from year ${year}.\n\nTHIS DATA WILL BE LOST FOREVER AND CANNOT BE RECOVERED!\n\nType "DELETE" to confirm:`);
    if (confirmation !== 'DELETE') {
        showToast('Deletion cancelled - incorrect confirmation', 'warning');
        return;
    }
    
    try {
        showLoading('Permanently deleting registrations...');
        
        const response = await fetch(`{{ route("admin.construction-registrations.permanent-delete-bulk") }}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                year: year
            })
        });
        
        const data = await response.json();
        hideLoading();
        
        if (data.success) {
            showToast(`Successfully deleted ${data.count} registration(s) permanently`, 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast(data.message || 'Failed to delete registrations', 'error');
        }
    } catch (error) {
        hideLoading();
        console.error('Error:', error);
        showToast('Network error occurred', 'error');
    }
}

async function bulkRestoreByYear() {
    const year = document.getElementById('bulkYearSelect').value;
    
    if (!year) {
        showToast('Please select a year to restore', 'warning');
        return;
    }
    
    if (!confirm(`Are you sure you want to restore ALL registrations from year ${year}?`)) {
        return;
    }
    
    try {
        showLoading('Restoring registrations...');
        
        const response = await fetch(`{{ route("admin.construction-registrations.restore-archived") }}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                year: year
            })
        });
        
        const data = await response.json();
        hideLoading();
        
        if (data.success) {
            showToast(`Successfully restored ${data.count} registration(s)`, 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast(data.message || 'Failed to restore registrations', 'error');
        }
    } catch (error) {
        hideLoading();
        console.error('Error:', error);
        showToast('Network error occurred', 'error');
    }
}

// Statistics Functions
async function showArchiveStats() {
    openModal('statsModal');
    await loadArchiveStats();
}

async function loadArchiveStats() {
    try {
        const response = await fetch(`{{ route("admin.construction-registrations.archive-stats") }}`);
        const data = await response.json();
        
        if (data.success) {
            updateStatsDisplay(data.stats);
            
            const statsContent = document.getElementById('statsContent');
            statsContent.innerHTML = `
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--primary-rgb), 0.05);">
                        <div class="text-2xl font-bold" style="color: var(--primary);">${data.stats.total_archived}</div>
                        <div class="text-sm text-slate-500">Total Archived</div>
                    </div>
                    <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--info-rgb), 0.05);">
                        <div class="text-2xl font-bold" style="color: var(--info);">${data.stats.years.length}</div>
                        <div class="text-sm text-slate-500">Archive Years</div>
                    </div>
                    <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--danger-rgb), 0.05);">
                        <div class="text-2xl font-bold" style="color: var(--danger);">${data.stats.estimated_storage_mb || 0}</div>
                        <div class="text-sm text-slate-500">Storage Used (MB)</div>
                    </div>
                    <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--success-rgb), 0.05);">
                        <div class="text-2xl font-bold" style="color: var(--success);">${data.stats.archived_percentage || 0}%</div>
                        <div class="text-sm text-slate-500">Of Total Records</div>
                    </div>
                </div>
                
                <div>
                    <h4 class="font-semibold mb-2" style="color: var(--text-primary);">By Year</h4>
                    <div class="space-y-2 max-h-60 overflow-y-auto">
                        ${data.stats.years.map(y => `
                            <div class="flex justify-between items-center p-2 rounded" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                                <span>${y.archive_year}</span>
                                <span class="font-semibold">${y.count} records</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <h4 class="font-semibold mb-2" style="color: var(--text-primary);">By Status</h4>
                        <div class="space-y-2">
                            ${data.stats.by_status.map(s => `
                                <div class="flex justify-between items-center p-2 rounded" style="background-color: rgba(var(--warning-rgb), 0.05);">
                                    <span>${s.status_label}</span>
                                    <span class="font-semibold">${s.count} records</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="font-semibold mb-2" style="color: var(--text-primary);">By Type</h4>
                        <div class="space-y-2">
                            ${data.stats.by_type.map(t => `
                                <div class="flex justify-between items-center p-2 rounded" style="background-color: rgba(var(--success-rgb), 0.05);">
                                    <span>${t.registration_type === 'construction' ? 'Construction' : 'Property Capture'}</span>
                                    <span class="font-semibold">${t.count} records</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
                
                <div class="text-xs text-slate-400 pt-2 border-t border-slate-200">
                    <div>Latest archive: ${data.stats.latest_archive ? new Date(data.stats.latest_archive.archived_at).toLocaleDateString() : 'N/A'}</div>
                    <div>Oldest archive: ${data.stats.oldest_archive ? new Date(data.stats.oldest_archive.archived_at).toLocaleDateString() : 'N/A'}</div>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading archive stats:', error);
        document.getElementById('statsContent').innerHTML = `
            <div class="text-center text-red-500 py-4">
                <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                <p>Failed to load statistics</p>
            </div>
        `;
    }
}

function updateStatsDisplay(stats) {
    const totalArchived = document.getElementById('totalArchived');
    const approvedArchived = document.getElementById('approvedArchived');
    const rejectedArchived = document.getElementById('rejectedArchived');
    const pendingArchived = document.getElementById('pendingArchived');
    const yearsCount = document.getElementById('yearsCount');
    const storageSize = document.getElementById('storageSize');
    
    if (totalArchived) totalArchived.textContent = stats.total_archived;
    if (approvedArchived) {
        const approved = stats.by_status.find(s => s.status === 'approved');
        approvedArchived.textContent = approved ? approved.count : 0;
    }
    if (rejectedArchived) {
        const rejected = stats.by_status.find(s => s.status === 'rejected');
        rejectedArchived.textContent = rejected ? rejected.count : 0;
    }
    if (pendingArchived) {
        const pending = stats.by_status.find(s => s.status === 'pending');
        pendingArchived.textContent = pending ? pending.count : 0;
    }
    if (yearsCount) yearsCount.textContent = stats.years.length;
    if (storageSize) storageSize.textContent = stats.estimated_storage_mb || 0;
}

function initializeYearSelect() {
    const yearSelect = document.getElementById('archiveYear');
    if (yearSelect && yearSelect.options.length === 0) {
        const currentYear = new Date().getFullYear();
        for (let y = currentYear; y >= 2020; y--) {
            const option = document.createElement('option');
            option.value = y;
            option.textContent = y;
            yearSelect.appendChild(option);
        }
    }
}

// UI Helper Functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function showToast(message, type = 'info') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full ${
        type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
    }`;
    
    const messageEl = document.createElement('span');
    messageEl.className = 'text-sm font-medium flex-1';
    messageEl.textContent = message;
    
    const closeBtn = document.createElement('button');
    closeBtn.className = 'ml-4 transition-colors duration-200';
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.onclick = () => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    };
    
    toast.appendChild(messageEl);
    toast.appendChild(closeBtn);
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 10);
    
    setTimeout(() => {
        if (toast.parentNode === toastContainer) {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

function showLoading(message = 'Processing...') {
    let overlay = document.getElementById('loadingOverlay');
    
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.className = 'loading-overlay hidden';
        overlay.innerHTML = `
            <div class="text-center">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary mb-4"></div>
                <div class="text-white font-medium" id="loadingMessage">${message}</div>
            </div>
        `;
        document.body.appendChild(overlay);
    }
    
    document.getElementById('loadingMessage').textContent = message;
    overlay.classList.remove('hidden');
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.add('hidden');
    }
}

// Close modal on overlay click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        const modal = e.target.closest('.modal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(modal => {
            if (!modal.classList.contains('hidden')) {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        });
    }
});
</script>

<style>
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}

.loading-overlay.hidden {
    display: none;
}

.loading-overlay .animate-spin {
    animation: spin 1s linear infinite;
    border-top-color: transparent;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
}

.modal.hidden {
    display: none;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
}

.modal-container {
    position: relative;
    background-color: var(--card-bg);
    border-radius: 16px;
    margin: 2rem auto;
    max-width: 700px;
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

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    background-color: var(--bg-secondary);
    border-radius: 0 0 16px 16px;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.bulk-checkbox {
    width: 18px;
    height: 18px;
    border-radius: 4px;
    border: 2px solid var(--border-color);
    background-color: var(--card-bg);
    cursor: pointer;
    transition: all 0.2s ease;
}

.bulk-checkbox:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

.bulk-checkbox:checked::after {
    content: '✓';
    color: white;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
}

.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
}

.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: 1px solid var(--primary) !important;
    transition: all 0.2s ease;
}

.btn-primary:hover:not(:disabled) {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
    transform: translateY(-1px);
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.form-input {
    background-color: var(--card-bg) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 8px !important;
    transition: all 0.2s ease !important;
}

.form-input:focus {
    outline: none !important;
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1) !important;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--text-primary);
    cursor: pointer;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.checkbox-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.checkbox-group label {
    cursor: pointer;
}

.hidden {
    display: none !important;
}
</style>
@endsection