@extends('layouts.app')

@section('title', 'Duplicate Registrations')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--warning) 0%, var(--danger) 100%); color: white; font-weight: 600; border-color: var(--warning);">
                        <i class="fas fa-copy text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-copy mr-2" style="color: var(--warning);"></i> 
                        Duplicate Registrations
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <span>Manage duplicate registration entries</span>
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
                    <i class="fas fa-arrow-left mr-2"></i> Back to Registrations
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Summary -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-copy"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ count($duplicates) }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Duplicate Groups</div>
                </div>
            </div>
        </div>
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--danger);">{{ $totalDuplicates ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Duplicate Entries</div>
                </div>
            </div>
        </div>
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $pendingDuplicates ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Pending Review</div>
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
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $resolvedDuplicates ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Resolved</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert for duplicates found -->
    @if(count($duplicates) > 0)
        <div class="card mb-6">
            <div class="p-4" style="background-color: rgba(var(--warning-rgb), 0.05); border-radius: 8px; border-left: 4px solid var(--warning);">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle mt-1 mr-3" style="color: var(--warning);"></i>
                    <div>
                        <h4 class="font-semibold" style="color: var(--text-primary);">Duplicate Registrations Detected</h4>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            {{ count($duplicates) }} duplicate group(s) found. Review and merge or mark as resolved.
                            Duplicates are identified by matching phone numbers, plot numbers, and property names.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="card mb-6">
            <div class="p-6 text-center">
                <i class="fas fa-check-circle text-4xl mb-3" style="color: var(--success);"></i>
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">No Duplicates Found</h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">All registrations are unique. No duplicate entries detected.</p>
                <a href="{{ route('admin.construction-registrations.index') }}" 
                   class="mt-4 inline-block px-4 py-2 rounded-lg font-medium text-white btn-primary">
                    <i class="fas fa-arrow-left mr-2"></i> Return to Registrations
                </a>
            </div>
        </div>
    @endif

    <!-- Duplicate Groups -->
    @if(count($duplicates) > 0)
        @foreach($duplicates as $groupId => $group)
            <div class="card mb-6 duplicate-group" data-group-id="{{ $loop->index }}">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-copy mr-2" style="color: var(--warning);"></i>
                                Duplicate Group #{{ $loop->iteration }}
                                <span class="ml-3 px-2 py-1 rounded-full text-xs font-medium"
                                      style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                    {{ count($group['duplicates']) }} entries
                                </span>
                            </h3>
                            <div class="text-sm mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-phone mr-1"></i> {{ $group['registration']->primary_phone }}
                                <span class="mx-2">•</span>
                                <i class="fas fa-map-pin mr-1"></i> Plot: {{ $group['registration']->plot_number }}
                                <span class="mx-2">•</span>
                                <i class="fas fa-home mr-1"></i> {{ $group['registration']->property_name }}
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button type="button" 
                                    onclick="mergeGroup({{ $loop->index }})"
                                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                                    style="background-color: var(--primary);">
                                <i class="fas fa-object-group mr-2"></i> Merge Group
                            </button>
                            <button type="button" 
                                    onclick="markResolved({{ $loop->index }})"
                                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                                    style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                <i class="fas fa-check mr-2"></i> Mark Resolved
                            </button>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b" style="border-color: var(--border-color);">
                                    <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">ID</th>
                                    <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Applicant</th>
                                    <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Property</th>
                                    <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Status</th>
                                    <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Submitted</th>
                                    <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Match Method</th>
                                    <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    // Include the primary registration in the duplicates list
                                    $allEntries = collect([$group['registration']])->merge($group['duplicates']);
                                @endphp
                                @foreach($allEntries as $duplicate)
                                    <tr class="border-b transition-colors duration-150" 
                                        style="border-color: var(--border-color);">
                                        <td class="py-3 px-4">
                                            <span class="font-mono text-sm" style="color: var(--text-secondary);">#{{ $duplicate->id }}</span>
                                            @if($duplicate->id == $group['registration']->id)
                                                <span class="ml-2 px-2 py-0.5 rounded-full text-xs font-medium"
                                                      style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                                                    Primary
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="font-medium" style="color: var(--text-primary);">
                                                {{ $duplicate->name }}
                                            </div>
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                <i class="fas fa-phone mr-1"></i> {{ $duplicate->primary_phone }}
                                            </div>
                                            @if($duplicate->email)
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                <i class="fas fa-envelope mr-1"></i> {{ $duplicate->email }}
                                            </div>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4">
                                            <div style="color: var(--text-primary);">
                                                {{ $duplicate->property_name }}
                                            </div>
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                Plot: {{ $duplicate->plot_number }}
                                            </div>
                                            @if($duplicate->street_name)
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                {{ $duplicate->street_name }}
                                            </div>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4">
                                            @php
                                                $statusStyles = [
                                                    'pending' => ['bg' => 'rgba(var(--warning-rgb), 0.1)', 'text' => 'var(--warning)', 'icon' => 'fa-clock'],
                                                    'in_review' => ['bg' => 'rgba(var(--info-rgb), 0.1)', 'text' => 'var(--info)', 'icon' => 'fa-search'],
                                                    'approved' => ['bg' => 'rgba(var(--success-rgb), 0.1)', 'text' => 'var(--success)', 'icon' => 'fa-check-circle'],
                                                    'rejected' => ['bg' => 'rgba(var(--danger-rgb), 0.1)', 'text' => 'var(--danger)', 'icon' => 'fa-times-circle'],
                                                    'needs_info' => ['bg' => 'rgba(var(--warning-rgb), 0.1)', 'text' => 'var(--warning)', 'icon' => 'fa-question-circle'],
                                                    'cancelled' => ['bg' => 'rgba(var(--secondary-rgb), 0.1)', 'text' => 'var(--secondary)', 'icon' => 'fa-ban'],
                                                ];
                                                $style = $statusStyles[$duplicate->status] ?? $statusStyles['pending'];
                                            @endphp
                                            <span class="px-3 py-1 rounded-full text-xs font-medium inline-flex items-center"
                                                  style="background-color: {{ $style['bg'] }}; color: {{ $style['text'] }};">
                                                <i class="fas {{ $style['icon'] }} mr-1"></i>
                                                {{ ucfirst(str_replace('_', ' ', $duplicate->status)) }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div style="color: var(--text-primary);">
                                                {{ $duplicate->submitted_at ? $duplicate->submitted_at->format('M d, Y') : $duplicate->created_at->format('M d, Y') }}
                                            </div>
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                {{ $duplicate->created_at->diffForHumans() }}
                                            </div>
                                        </td>
                                        <td class="py-3 px-4">
                                            @if($duplicate->submission_hash)
                                                @php
                                                    $methodLabels = [
                                                        'submission_hash' => 'Exact Hash Match',
                                                        'key_fields' => 'Key Fields Match',
                                                        'recent_rejected' => 'Recent Rejected',
                                                        'similar_content' => 'Similar Content',
                                                    ];
                                                    $method = $duplicate->duplicate_match_method ?? 'hash_match';
                                                    $label = $methodLabels[$method] ?? ucfirst(str_replace('_', ' ', $method));
                                                @endphp
                                                <span class="px-2 py-1 rounded-full text-xs font-medium"
                                                      style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                    {{ $label }}
                                                </span>
                                            @else
                                                <span class="text-sm" style="color: var(--text-secondary);">
                                                    <i class="fas fa-minus mr-1"></i> N/A
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="flex items-center space-x-2">
                                                <a href="{{ route('admin.construction-registrations.show', $duplicate) }}" 
                                                   class="action-btn" 
                                                   title="View Details"
                                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                @if($duplicate->id != $group['registration']->id)
                                                <button type="button" 
                                                        onclick="selectPrimary({{ $loop->parent->index }}, {{ $duplicate->id }})"
                                                        class="action-btn select-primary-btn" 
                                                        title="Select as Primary"
                                                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);"
                                                        data-group="{{ $loop->parent->index }}"
                                                        data-id="{{ $duplicate->id }}">
                                                    <i class="fas fa-star"></i>
                                                </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div>

<!-- Merge Modal -->
<div id="mergeModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeMergeModal()"></div>
    <div class="modal-container" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title">Merge Duplicates</h3>
            <button type="button" class="modal-close" onclick="closeMergeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="space-y-4">
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.1);">
                    <p class="text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1 text-warning"></i>
                        Merging will combine all selected duplicates into one primary registration. 
                        All tenants and selected data will be preserved.
                    </p>
                </div>

                <div>
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">Primary Registration</label>
                    <select id="mergePrimarySelect" class="form-input w-full p-3 rounded-lg border" required
                            style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                        <option value="">Select Primary...</option>
                    </select>
                </div>

                <div>
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">Data to Keep</label>
                    <div class="space-y-2">
                        <label class="flex items-center text-sm" style="color: var(--text-secondary);">
                            <input type="checkbox" id="mergeKeepDocuments" value="documents" class="mr-2" checked>
                            Documents & Photos
                        </label>
                        <label class="flex items-center text-sm" style="color: var(--text-secondary);">
                            <input type="checkbox" id="mergeKeepLandlordInfo" value="landlord_info" class="mr-2" checked>
                            Landlord Info
                        </label>
                        <label class="flex items-center text-sm" style="color: var(--text-secondary);">
                            <input type="checkbox" id="mergeKeepPropertyDetails" value="property_details" class="mr-2" checked>
                            Property Details
                        </label>
                        <label class="flex items-center text-sm" style="color: var(--text-secondary);">
                            <input type="checkbox" id="mergeKeepTenants" value="tenants" class="mr-2" checked>
                            Tenant Information
                        </label>
                    </div>
                </div>

                <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        All tenants from duplicate registrations will be merged into the primary registration.
                        Duplicate registrations will be permanently deleted after merging.
                    </p>
                </div>

                <div id="mergeProgress" class="hidden">
                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-spinner fa-spin mr-2"></i> Merging...
                            </span>
                            <span id="mergeProgressText" style="color: var(--text-secondary);">0%</span>
                        </div>
                        <div class="mt-2 w-full h-2 rounded-full" style="background-color: var(--bg-secondary);">
                            <div id="mergeProgressBar" class="h-2 rounded-full transition-all duration-300" style="width: 0%; background-color: var(--success);"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeMergeModal()">
                Cancel
            </button>
            <button type="button" 
                    onclick="confirmMerge()"
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background-color: var(--primary);">
                <i class="fas fa-object-group mr-2"></i> Merge Now
            </button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

@push('scripts')
<script>
// Global variables
let currentGroupId = null;
let currentGroupData = null;
let selectedPrimary = {};

// ============================================
// TOAST SYSTEM
// ============================================
const Toast = {
    container: document.getElementById('toast-container'),
    
    show(message, type = 'info', duration = 5000) {
        if (!this.container) {
            console.warn('Toast container not found');
            return;
        }
        
        const toast = document.createElement('div');
        const iconMap = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <i class="fas ${iconMap[type] || 'fa-info-circle'}"></i>
            <span>${message}</span>
        `;
        this.container.appendChild(toast);
        
        toast.addEventListener('click', () => {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 300);
        });
        
        setTimeout(() => {
            if (toast.parentNode) {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.remove();
                    }
                }, 300);
            }
        }, duration);
    },
    
    success(msg, duration = 5000) { this.show(msg, 'success', duration); },
    error(msg, duration = 5000) { this.show(msg, 'error', duration); },
    info(msg, duration = 5000) { this.show(msg, 'info', duration); },
    warning(msg, duration = 5000) { this.show(msg, 'warning', duration); }
};

// ============================================
// MERGE FUNCTIONS
// ============================================

function mergeGroup(groupId) {
    currentGroupId = groupId;
    const groupData = getGroupData(groupId);
    currentGroupData = groupData;
    
    // Populate primary select
    const select = document.getElementById('mergePrimarySelect');
    select.innerHTML = '<option value="">Select Primary...</option>';
    
    groupData.duplicates.forEach(dup => {
        const option = document.createElement('option');
        option.value = dup.id;
        option.textContent = `${dup.name} (ID: ${dup.id}) - ${dup.propertyName}`;
        if (selectedPrimary[groupId] == dup.id) {
            option.selected = true;
        }
        select.appendChild(option);
    });
    
    // Reset progress
    document.getElementById('mergeProgress').classList.add('hidden');
    document.getElementById('mergeProgressBar').style.width = '0%';
    document.getElementById('mergeProgressText').textContent = '0%';
    
    openMergeModal();
}

function getGroupData(groupId) {
    // Get data from the DOM
    const groupElement = document.querySelector(`.duplicate-group[data-group-id="${groupId}"]`);
    if (!groupElement) return { duplicates: [] };
    
    const duplicates = [];
    const rows = groupElement.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const id = row.querySelector('td:first-child span')?.textContent?.replace('#', '') || '';
        const name = row.querySelector('td:nth-child(2) .font-medium')?.textContent || '';
        const propertyName = row.querySelector('td:nth-child(3) > div:first-child')?.textContent || '';
        if (id) {
            duplicates.push({ id, name, propertyName });
        }
    });
    
    return { duplicates };
}

function selectPrimary(groupId, registrationId) {
    selectedPrimary[groupId] = registrationId;
    
    // Update UI
    const groupElement = document.querySelector(`.duplicate-group[data-group-id="${groupId}"]`);
    if (groupElement) {
        groupElement.querySelectorAll('.select-primary-btn').forEach(btn => {
            const id = btn.dataset.id;
            if (id == registrationId) {
                btn.style.backgroundColor = 'rgba(var(--success-rgb), 0.3)';
                btn.style.color = 'var(--success)';
                btn.title = 'Primary Selected';
            } else {
                btn.style.backgroundColor = 'rgba(var(--success-rgb), 0.1)';
                btn.style.color = 'var(--success)';
                btn.title = 'Select as Primary';
            }
        });
        
        Toast.success(`Primary registration selected: ID #${registrationId}`);
    }
}

function openMergeModal() {
    document.getElementById('mergeModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeMergeModal() {
    document.getElementById('mergeModal').classList.remove('active');
    document.body.style.overflow = '';
}

function confirmMerge() {
    const select = document.getElementById('mergePrimarySelect');
    const primaryId = select.value;
    
    if (!primaryId) {
        Toast.warning('Please select a primary registration');
        return;
    }
    
    const keepData = [];
    if (document.getElementById('mergeKeepDocuments').checked) keepData.push('documents');
    if (document.getElementById('mergeKeepLandlordInfo').checked) keepData.push('landlord_info');
    if (document.getElementById('mergeKeepPropertyDetails').checked) keepData.push('property_details');
    if (document.getElementById('mergeKeepTenants').checked) keepData.push('tenants');
    
    // Get all duplicate IDs in the group
    const groupData = getGroupData(currentGroupId);
    const duplicateIds = groupData.duplicates.map(d => d.id).filter(id => id != primaryId);
    
    if (duplicateIds.length === 0) {
        Toast.warning('No duplicates to merge');
        return;
    }
    
    if (!confirm(`This will merge ${duplicateIds.length} duplicate(s) into primary registration #${primaryId}. Continue?`)) {
        return;
    }
    
    // Show progress
    document.getElementById('mergeProgress').classList.remove('hidden');
    document.getElementById('mergeProgressBar').style.width = '30%';
    document.getElementById('mergeProgressText').textContent = '30%';
    
    // Perform merge
    performMerge(primaryId, duplicateIds, keepData);
}

async function performMerge(primaryId, duplicateIds, keepData) {
    try {
        const response = await fetch(`{{ route('admin.construction-registrations.api.merge-duplicates') }}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                primary_id: primaryId,
                duplicate_ids: duplicateIds,
                keep_data: keepData
            })
        });
        
        const data = await response.json();
        
        document.getElementById('mergeProgressBar').style.width = '100%';
        document.getElementById('mergeProgressText').textContent = '100%';
        
        if (data.success) {
            Toast.success(data.message || 'Duplicates merged successfully');
            closeMergeModal();
            setTimeout(() => window.location.reload(), 2000);
        } else {
            Toast.error(data.message || 'Failed to merge duplicates');
            document.getElementById('mergeProgress').classList.add('hidden');
        }
    } catch (error) {
        console.error('Merge error:', error);
        Toast.error('Network error occurred');
        document.getElementById('mergeProgress').classList.add('hidden');
    }
}

function markResolved(groupId) {
    if (!confirm('Mark this duplicate group as resolved? This will not merge the duplicates.')) {
        return;
    }
    
    const groupData = getGroupData(groupId);
    const registrationIds = groupData.duplicates.map(d => d.id);
    
    // Send API request to mark as resolved
    fetch(`{{ route('admin.construction-registrations.api.bulk-action') }}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'mark_resolved',
            registration_ids: registrationIds
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Toast.success('Duplicate group marked as resolved');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            Toast.error(data.message || 'Failed to mark as resolved');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Toast.error('Network error occurred');
    });
}

// Close modal on overlay click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        closeMergeModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeMergeModal();
    }
});

// Initialize select primary buttons
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.select-primary-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const groupId = this.dataset.group;
            const id = this.dataset.id;
            selectPrimary(groupId, id);
        });
    });
});

console.log('Duplicate Management page loaded successfully');
</script>

<style>
/* Toast styles */
.toast {
    background: var(--card-bg);
    border-left: 4px solid;
    border-radius: var(--radius-md);
    padding: 1rem 1.25rem;
    margin-bottom: 0.5rem;
    box-shadow: var(--shadow-lg);
    animation: slideIn 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    pointer-events: auto;
    font-size: 0.9rem;
}

.toast.success { border-left-color: var(--success); }
.toast.error { border-left-color: var(--danger); }
.toast.warning { border-left-color: var(--warning); }
.toast.info { border-left-color: var(--info); }

.toast i {
    font-size: 1.2rem;
    flex-shrink: 0;
}

.toast.success i { color: var(--success); }
.toast.error i { color: var(--danger); }
.toast.warning i { color: var(--warning); }
.toast.info i { color: var(--info); }

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

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

/* Card styles */
.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
}

/* Action button styles */
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

/* Modal styles */
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

.modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
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
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    border: 1px solid var(--border-color);
    animation: modalFadeIn 0.3s ease-out;
    width: 100%;
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
    min-width: 44px;
    min-height: 44px;
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

/* Form elements */
.form-input, .form-select {
    background-color: var(--card-bg) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 8px !important;
    transition: all 0.2s ease !important;
}

.form-input:focus, .form-select:focus {
    outline: none !important;
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1) !important;
}

/* Responsive */
@media (max-width: 768px) {
    .duplicate-group .flex.justify-between {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .modal-container {
        margin: 1rem;
        max-height: 95vh;
    }
    
    table th, table td {
        padding: 0.5rem;
        font-size: 0.875rem;
    }
    
    .grid.grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .grid.grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
    
    .modal-container {
        margin: 0.5rem;
        border-radius: 12px;
    }
    
    .modal-header {
        padding: 1rem;
    }
    
    .modal-body {
        padding: 1rem;
    }
    
    .modal-footer {
        padding: 0.75rem 1rem;
        flex-wrap: wrap;
    }
    
    .modal-footer .btn {
        flex: 1;
        min-width: 100px;
    }
}

/* Scrollbar styling */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: rgba(var(--primary-rgb), 0.05);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: rgba(var(--primary-rgb), 0.2);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: rgba(var(--primary-rgb), 0.3);
}

/* Spinner */
.spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: white;
    animation: spin 0.6s linear infinite;
    margin-right: 8px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Hide element class */
.hidden {
    display: none !important;
}

/* Text colors */
.text-warning { color: var(--warning); }
.text-success { color: var(--success); }
.text-danger { color: var(--danger); }
.text-info { color: var(--info); }
.text-primary { color: var(--primary); }
.text-secondary { color: var(--text-secondary); }

/* Background colors */
.bg-warning { background-color: var(--warning); }
.bg-success { background-color: var(--success); }
.bg-danger { background-color: var(--danger); }
.bg-info { background-color: var(--info); }
.bg-primary { background-color: var(--primary); }
</style>
@endpush
@endsection