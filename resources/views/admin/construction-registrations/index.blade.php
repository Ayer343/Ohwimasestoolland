@extends('layouts.app')

@section('title', 'Construction & Property Registrations')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-hard-hat text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-hard-hat mr-2" style="color: var(--primary);"></i> 
                        Construction & Property Registrations
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-tasks mr-2"></i>
                        <span>Manage all registration applications</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>Admin: {{ auth()->user()->name }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <!-- Duplicate View Button -->
                <a href="{{ route('admin.construction-registrations.duplicates') }}"  
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center relative"
                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-copy mr-2"></i> Duplicates
                    @if(($counts['duplicates'] ?? 0) > 0)
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                        {{ $counts['duplicates'] }}
                    </span>
                    @endif
                </a>
                
                <button onclick="showExportModal()" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center" 
                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-file-export mr-2"></i> Export
                </button>
                
                @if(($counts['trashed'] ?? 0) > 0)
                <a href="{{ route('admin.construction-registrations.trash') }}"  
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center relative"
                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-trash mr-2"></i> Trash
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                        {{ $counts['trashed'] }}
                    </span>
                </a>
                @else
                <a href="{{ route('admin.construction-registrations.trash') }}"  
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-trash mr-2"></i> Trash
                </a>
                @endif

                <!-- Archived Button -->
                @if(($counts['archived'] ?? 0) > 0)
                <a href="{{ route('admin.construction-registrations.archived') }}"  
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center relative"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-archive mr-2"></i> Archived
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                        {{ $counts['archived'] }}
                    </span>
                </a>
                @else
                <a href="{{ route('admin.construction-registrations.archived') }}"  
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-archive mr-2"></i> Archived
                </a>
                @endif
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
                
                <a href="{{ route('admin.construction-registrations.stats') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-chart-bar mr-1"></i> Statistics
                </a>
                
                <a href="{{ route('properties.index') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-building mr-1"></i> Properties
                </a>
                
                <a href="{{ route('registration-plans.index') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-tag mr-1"></i> Plans
                </a>

                <a href="{{ route('admin.construction-registrations.archive-stats') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-chart-line mr-1"></i> Archive Stats
                </a>

                <!-- Duplicate Management Link -->
                <a href="{{ route('admin.construction-registrations.duplicates') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-copy mr-1"></i> Duplicates
                    @if(($counts['duplicates'] ?? 0) > 0)
                    <span class="ml-1 bg-red-500 text-white rounded-full px-1.5 py-0.5 text-xs">
                        {{ $counts['duplicates'] }}
                    </span>
                    @endif
                </a>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-8 gap-4 mb-6">
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $counts['total'] }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total</div>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-hard-hat"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $counts['construction'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Construction</div>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $counts['both_purposes'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Both Purposes</div>
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
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $counts['pending'] }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Pending</div>
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
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $counts['approved'] }}</div>
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
                    <div class="text-2xl font-bold" style="color: var(--danger);">{{ $counts['rejected'] }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Rejected</div>
                </div>
            </div>
        </div>

        <!-- Archived Count Card -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-archive"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $counts['archived'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Archived</div>
                </div>
            </div>
        </div>

        <!-- Duplicate Count Card -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-copy"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $counts['duplicates'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Duplicates</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <div class="p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="font-semibold" style="color: var(--text-primary);">Quick Actions</h4>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Common tasks and shortcuts
                    </p>
                </div>
                <div class="flex items-center space-x-2">
                    <button onclick="bulkUpdateStatus('in_review')" 
                            class="px-3 py-2 text-sm rounded-lg inline-flex items-center"
                            style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-check-double mr-2"></i> Mark Selected as In Review
                    </button>
                    <button onclick="bulkAssignAdmin()" 
                            class="px-3 py-2 text-sm rounded-lg inline-flex items-center"
                            style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-user-tag mr-2"></i> Assign Selected
                    </button>
                    <button onclick="bulkArchive()" 
                            class="px-3 py-2 text-sm rounded-lg inline-flex items-center"
                            style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-archive mr-2"></i> Archive Selected
                    </button>
                    <button onclick="window.location.href='{{ route('admin.construction-registrations.duplicates') }}'" 
                            class="px-3 py-2 text-sm rounded-lg inline-flex items-center"
                            style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <i class="fas fa-copy mr-2"></i> Manage Duplicates
                    </button>
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
                @if(request()->hasAny(['search', 'status', 'assigned_to', 'registration_type', 'purpose', 'property_type', 'zone', 'section', 'date_from', 'date_to', 'has_duplicates']))
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-filter mr-1"></i> Filters active
                </div>
                @endif
            </div>
        </div>
        <div class="p-6">
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Search</label>
                        <input type="text" 
                               name="search" 
                               class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               placeholder="Name, property, plot #, phone, email..."
                               value="{{ request('search') }}">
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
                    
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Purpose</label>
                        <select name="purpose" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Purposes</option>
                            <option value="construction" {{ request('purpose') == 'construction' ? 'selected' : '' }}>Construction Only</option>
                            <option value="permanent_registration" {{ request('purpose') == 'permanent_registration' ? 'selected' : '' }}>Permanent Registration</option>
                            <option value="both" {{ request('purpose') == 'both' ? 'selected' : '' }}>Both</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Status</label>
                        <select name="status" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Statuses</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="in_review" {{ request('status') == 'in_review' ? 'selected' : '' }}>In Review</option>
                            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                            <option value="needs_info" {{ request('status') == 'needs_info' ? 'selected' : '' }}>Needs Info</option>
                            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Assigned To</label>
                        <select name="assigned_to" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Admins</option>
                            <option value="unassigned" {{ request('assigned_to') == 'unassigned' ? 'selected' : '' }}>Unassigned</option>
                            @foreach($admins as $admin)
                            <option value="{{ $admin->id }}" {{ request('assigned_to') == $admin->id ? 'selected' : '' }}>
                                {{ $admin->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Zone</label>
                        <input type="text" 
                               name="zone" 
                               class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               placeholder="Zone"
                               value="{{ request('zone') }}">
                    </div>
                    
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Section</label>
                        <input type="text" 
                               name="section" 
                               class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               placeholder="Section"
                               value="{{ request('section') }}">
                    </div>
                    
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Has Tenants</label>
                        <select name="has_tenants" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All</option>
                            <option value="1" {{ request('has_tenants') == '1' ? 'selected' : '' }}>Yes</option>
                            <option value="0" {{ request('has_tenants') == '0' ? 'selected' : '' }}>No</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Show Duplicates</label>
                        <select name="has_duplicates" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Registrations</option>
                            <option value="1" {{ request('has_duplicates') == '1' ? 'selected' : '' }}>Show Only Duplicates</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Date From</label>
                        <input type="date" 
                               name="date_from" 
                               class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               value="{{ request('date_from') }}">
                    </div>
                    
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Date To</label>
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
                    <a href="{{ route('admin.construction-registrations.index') }}" 
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
                        Select registrations to perform actions on multiple items
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
                <select id="bulkActionSelect" 
                        class="form-input p-3 rounded-lg border col-span-1 md:col-span-3"
                        style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                    <option value="">Choose action...</option>
                    <option value="review">Mark as In Review</option>
                    <option value="approve">Approve Selected (Creates Properties)</option>
                    <option value="reject">Reject Selected</option>
                    <option value="assign">Assign to Admin</option>
                    <option value="needs_info">Mark as Needs Info</option>
                    <option value="delete">Move to Trash</option>
                    <option value="archive">Archive Selected</option>
                    <option value="merge">Merge Duplicates</option>
                </select>
                <button type="button" 
                        onclick="performBulkAction()" 
                        class="btn-primary p-3 rounded-lg font-medium inline-flex items-center justify-center"
                        id="bulkActionBtn">
                    <i class="fas fa-play mr-2"></i> Apply
                </button>
            </div>
            
            <!-- Bulk Assign Admin Select -->
            <div id="bulkAssignContainer" class="mt-3 hidden">
                <select id="bulkAdminSelect" 
                        class="form-input w-full p-3 rounded-lg border"
                        style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                    <option value="">Select Admin to Assign...</option>
                    @foreach($admins as $admin)
                    <option value="{{ $admin->id }}">{{ $admin->name }}</option>
                    @endforeach
                </select>
            </div>
            
            <!-- Bulk Merge Container -->
            <div id="bulkMergeContainer" class="mt-3 hidden">
                <div class="p-4 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                    <p class="text-sm mb-3" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1 text-warning"></i>
                        Merging duplicates will combine selected registrations into one. The first selected registration will be the primary.
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-2 font-medium" style="color: var(--text-primary);">Primary Registration</label>
                            <select id="bulkPrimarySelect" 
                                    class="form-input w-full p-3 rounded-lg border"
                                    style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                                <option value="">Select Primary...</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-2 font-medium" style="color: var(--text-primary);">Data to Keep</label>
                            <div class="space-y-1">
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
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 p-2 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                        <p class="text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            All tenants from duplicate registrations will be merged into the primary registration.
                        </p>
                    </div>
                </div>
            </div>
            
            <div id="bulkActionStatus" class="mt-3 hidden"></div>
        </div>
    </div>
    @endif

    <!-- Registrations Table Card -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-list mr-2" style="color: var(--primary);"></i> Registrations
                </h3>
                <div class="text-sm" style="color: var(--text-secondary);">
                    Showing {{ $registrations->firstItem() }} to {{ $registrations->lastItem() }} of {{ $registrations->total() }} registrations
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
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Type/Purpose</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Property</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Location</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Zone/Section</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Status</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Assigned To</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Submitted</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($registrations as $registration)
                            <tr class="border-b transition-colors duration-150 {{ $registration->isDuplicate() ? 'duplicate-row' : '' }}" 
                                style="border-color: var(--border-color);"
                                data-registration-id="{{ $registration->id }}"
                                data-name="{{ $registration->name }}"
                                data-type="{{ $registration->registration_type }}"
                                data-purpose="{{ $registration->purpose }}"
                                data-has-construction="{{ $registration->isConstruction() ? 'true' : 'false' }}"
                                data-has-property-type="{{ $registration->property_type ? 'true' : 'false' }}"
                                data-is-vacant-land="{{ ($registration->isConstruction() && !$registration->property_type && !$registration->construction_documents) ? 'true' : 'false' }}"
                                data-is-duplicate="{{ $registration->isDuplicate() ? 'true' : 'false' }}">
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
                                            @php
                                                $statusColor = [
                                                    'pending' => 'warning',
                                                    'in_review' => 'info',
                                                    'approved' => 'success',
                                                    'rejected' => 'danger',
                                                    'needs_info' => 'warning',
                                                    'cancelled' => 'secondary',
                                                ][$registration->status] ?? 'secondary';
                                            @endphp
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                                 style="background-color: rgba(var({{ $statusColor }}-rgb), 0.1); color: var(--{{ $statusColor }});">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">
                                                {{ $registration->name }}
                                                @if($registration->isDuplicate())
                                                <span class="ml-2 px-2 py-0.5 rounded-full text-xs" 
                                                      style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                                                    <i class="fas fa-copy mr-1"></i> Duplicate
                                                </span>
                                                @endif
                                            </div>
                                            <div class="text-xs mt-1 flex items-center" style="color: var(--text-secondary);">
                                                <i class="fas fa-phone mr-1"></i> {{ $registration->primary_phone }}
                                            </div>
                                            @if($registration->email)
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                <i class="fas fa-envelope mr-1"></i> {{ $registration->email }}
                                            </div>
                                            @endif
                                            @if($registration->additional_phones)
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                <i class="fas fa-phone-alt mr-1"></i> +{{ count($registration->additional_phones) }} more
                                            </div>
                                            @endif
                                            @if($registration->submission_hash)
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                <i class="fas fa-fingerprint mr-1"></i> Hash: {{ substr($registration->submission_hash, 0, 8) }}...
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium"
                                              style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                            {{ $registration->registration_type_label }}
                                        </span>
                                    </div>
                                    <div class="text-xs mt-2">
                                        <span class="font-medium" style="color: var(--text-secondary);">Purpose:</span>
                                        <span style="color: var(--text-primary);">{{ $registration->purpose_label }}</span>
                                    </div>
                                    @if($registration->isConstruction() && $registration->property_type)
                                    <div class="text-xs mt-1">
                                        <span class="font-medium" style="color: var(--text-secondary);">Planned:</span>
                                        <span style="color: var(--text-primary);">{{ $registration->formatted_property_type }}</span>
                                    </div>
                                    @elseif($registration->isConstruction() && !$registration->property_type)
                                    <div class="text-xs mt-1">
                                        <span class="px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                            <i class="fas fa-map-marked-alt mr-1"></i> Vacant Land
                                        </span>
                                    </div>
                                    @endif
                                    @if($registration->isPropertyCapture() && $registration->existing_property_type)
                                    <div class="text-xs mt-1">
                                        <span class="font-medium" style="color: var(--text-secondary);">Existing:</span>
                                        <span style="color: var(--text-primary);">{{ $registration->formatted_existing_property_type }}</span>
                                    </div>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-medium" style="color: var(--text-primary);">
                                        {{ $registration->property_name ?: 'Not Specified' }}
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Plot: {{ $registration->plot_number }}
                                    </div>
                                    @if($registration->estimated_bedrooms || $registration->existing_bedrooms)
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-bed mr-1"></i> 
                                        {{ $registration->estimated_bedrooms ?? $registration->existing_bedrooms }} beds
                                    </div>
                                    @endif
                                    @if($registration->existing_bathrooms)
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-bath mr-1"></i> {{ $registration->existing_bathrooms }} baths
                                    </div>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <div style="color: var(--text-primary);">
                                        {{ $registration->street_name }}
                                    </div>
                                    @if($registration->digital_address)
                                    <div class="text-xs mt-1 flex items-center" style="color: var(--text-secondary);">
                                        <i class="fas fa-map-pin mr-1"></i> {{ $registration->digital_address }}
                                    </div>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    @if($registration->zone || $registration->section)
                                        <div style="color: var(--text-primary);">
                                            @if($registration->zone)Zone {{ $registration->zone }}@endif
                                            @if($registration->section){{ $registration->zone ? ',' : '' }} Sec {{ $registration->section }}@endif
                                        </div>
                                        @if(!$registration->hasZoneAndSection())
                                        <div class="text-xs mt-1" style="color: var(--warning);">
                                            <i class="fas fa-exclamation-triangle mr-1"></i> Incomplete
                                        </div>
                                        @endif
                                    @else
                                        <span class="text-sm" style="color: var(--text-secondary);">
                                            <i class="fas fa-minus mr-1"></i> Not assigned
                                        </span>
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
                                        $style = $statusStyles[$registration->status] ?? $statusStyles['pending'];
                                    @endphp
                                    <span class="px-3 py-1 rounded-full text-xs font-medium inline-flex items-center"
                                          style="background-color: {{ $style['bg'] }}; color: {{ $style['text'] }};">
                                        <i class="fas {{ $style['icon'] }} mr-1"></i>
                                        {{ $registration->status_label }}
                                    </span>
                                    @if($registration->status === 'needs_info' && $registration->info_requested)
                                    <div class="text-xs mt-1" style="color: var(--warning);">
                                        <i class="fas fa-info-circle mr-1"></i> Info requested
                                    </div>
                                    @endif
                                    @if($registration->is_overdue)
                                    <div class="text-xs mt-1" style="color: var(--danger);">
                                        <i class="fas fa-exclamation-circle mr-1"></i> Overdue ({{ $registration->days_since_submission }} days)
                                    </div>
                                    @endif
                                    @if($registration->has_tenants)
                                    <div class="text-xs mt-1" style="color: var(--info);">
                                        <i class="fas fa-users mr-1"></i> {{ $registration->tenant_summary }}
                                    </div>
                                    @endif
                                    @if($registration->isDuplicate())
                                    <div class="text-xs mt-1" style="color: var(--warning);">
                                        <i class="fas fa-copy mr-1"></i> Potential duplicate
                                    </div>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    @if($registration->assignedTo)
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                             style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                            <i class="fas fa-user-shield text-xs"></i>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium" style="color: var(--text-primary);">
                                                {{ $registration->assignedTo->name }}
                                            </div>
                                            @if($registration->assigned_at)
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                {{ $registration->assigned_at->diffForHumans() }}
                                                @if($registration->days_since_assignment > 3)
                                                <span class="ml-1 text-warning">({{ $registration->days_since_assignment }}d)</span>
                                                @endif
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                    @else
                                    <span class="text-sm" style="color: var(--text-secondary);">
                                        <i class="fas fa-user-slash mr-1"></i> Unassigned
                                    </span>
                                    <button onclick="quickAssign({{ $registration->id }}, '{{ addslashes($registration->name) }}')"
                                            class="ml-2 text-xs text-primary hover:underline">
                                        Assign
                                    </button>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <div style="color: var(--text-primary);">
                                        {{ $registration->submitted_at ? $registration->submitted_at->format('M d, Y') : $registration->created_at->format('M d, Y') }}
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        {{ $registration->created_at->diffForHumans() }}
                                    </div>
                                    @if($registration->reviewed_at)
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i> 
                                        Reviewed {{ $registration->reviewed_at->diffForHumans() }}
                                    </div>
                                    @endif
                                    @if($registration->duplicate_check_at)
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-search mr-1"></i> 
                                        Duplicate check: {{ $registration->duplicate_check_at->diffForHumans() }}
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
                                        
                                        @if($registration->status !== 'approved')
                                        <button onclick="quickAssign({{ $registration->id }}, '{{ addslashes($registration->name) }}')"
                                                class="action-btn" 
                                                title="Assign"
                                                style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                            <i class="fas fa-user-tag"></i>
                                        </button>
                                        @endif
                                        
                                        @if($registration->documents()->count() > 0)
                                        <a href="{{ route('admin.construction-registrations.show', $registration) }}#documents"
                                           class="action-btn relative" 
                                           title="View Documents ({{ $registration->documents()->count() }})"
                                           style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                            <i class="fas fa-file-alt"></i>
                                            <span class="absolute -top-1 -right-1 bg-warning text-white text-xs rounded-full h-4 w-4 flex items-center justify-center"
                                                  style="background-color: var(--warning);">
                                                {{ $registration->documents()->count() }}
                                            </span>
                                        </a>
                                        @endif
                                        
                                        @if($registration->construction_documents && count($registration->construction_documents) > 0)
                                        <span class="action-btn relative cursor-help"
                                              title="Construction Documents ({{ count($registration->construction_documents) }})"
                                              style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                            <i class="fas fa-draw-polygon"></i>
                                            <span class="absolute -top-1 -right-1 bg-info text-white text-xs rounded-full h-4 w-4 flex items-center justify-center"
                                                  style="background-color: var(--info);">
                                                {{ count($registration->construction_documents) }}
                                            </span>
                                        </span>
                                        @endif
                                        
                                        @if($registration->property_photos && count($registration->property_photos) > 0)
                                        <span class="action-btn relative cursor-help"
                                              title="Property Photos ({{ count($registration->property_photos) }})"
                                              style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                            <i class="fas fa-camera"></i>
                                            <span class="absolute -top-1 -right-1 bg-success text-white text-xs rounded-full h-4 w-4 flex items-center justify-center"
                                                  style="background-color: var(--success);">
                                                {{ count($registration->property_photos) }}
                                            </span>
                                        </span>
                                        @endif
                                        
                                        @if($registration->status === 'approved' && $registration->approvedProperty)
                                        <a href="{{ route('properties.show', $registration->approvedProperty) }}"
                                           class="action-btn" 
                                           title="View Property"
                                           style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                            <i class="fas fa-building"></i>
                                        </a>
                                        @endif
                                        
                                        @if(in_array($registration->status, ['pending', 'needs_info']))
                                        <button onclick="quickReject({{ $registration->id }}, '{{ addslashes($registration->name) }}')"
                                                class="action-btn" 
                                                title="Reject"
                                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        @endif

                                        <!-- Archive Button -->
                                        <button onclick="archiveSingle({{ $registration->id }}, '{{ addslashes($registration->name) }}')"
                                                class="action-btn" 
                                                title="Archive"
                                                style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                            <i class="fas fa-archive"></i>
                                        </button>

                                        <!-- View Duplicates Button -->
                                        @if($registration->isDuplicate())
                                        <a href="{{ route('admin.construction-registrations.duplicates') }}?registration_id={{ $registration->id }}"
                                           class="action-btn" 
                                           title="View Duplicates"
                                           style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                            <i class="fas fa-copy"></i>
                                        </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-8 px-4 text-center" style="color: var(--text-secondary);">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="fas fa-hard-hat text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                                        <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">No registrations found</p>
                                        <p style="color: var(--text-secondary);">Try adjusting your filters</p>
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

<!-- Quick Assign Modal -->
<div id="quickAssignModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('quickAssignModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Assign Registration</h3>
            <button type="button" class="modal-close" onclick="closeModal('quickAssignModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="quickAssignForm" method="POST">
            @csrf
            <div class="modal-body">
                <div class="space-y-4">
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Registration</label>
                        <p class="p-3 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);" id="quickAssignRegistrationName"></p>
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Assign to Admin</label>
                        <select name="admin_id" class="form-input w-full p-3 rounded-lg border" required
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">Select Admin...</option>
                            @foreach($admins as $admin)
                            <option value="{{ $admin->id }}">{{ $admin->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Note (Optional)</label>
                        <textarea name="note" rows="3" 
                                  class="form-input w-full p-3 rounded-lg border"
                                  style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                  placeholder="Add a note about this assignment..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeModal('quickAssignModal')">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-user-tag mr-2"></i> Assign
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Enhanced Quick Reject Modal with Email & SMS Notifications -->
<div id="quickRejectModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('quickRejectModal')"></div>
    <div class="modal-container" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title">Reject Registration</h3>
            <button type="button" class="modal-close" onclick="closeModal('quickRejectModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="space-y-4">
                <!-- Registration Info -->
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-primary);">Registration Details</p>
                    <p class="text-sm" id="quickRejectRegistrationName" style="color: var(--text-secondary);"></p>
                </div>
                
                <!-- Rejection Reason -->
                <div>
                    <label class="block mb-2 font-medium required" style="color: var(--text-primary);">Rejection Reason</label>
                    <textarea id="rejection_reason" rows="4" required
                              class="form-input w-full p-3 rounded-lg border"
                              style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                              placeholder="Please provide a clear reason for rejection..."></textarea>
                    <small class="text-xs mt-1" style="color: var(--text-secondary);">This reason will be visible to the landlord.</small>
                </div>
                
                <!-- Internal Admin Notes -->
                <div>
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">Admin Notes (Internal)</label>
                    <textarea id="admin_notes" rows="2"
                              class="form-input w-full p-3 rounded-lg border"
                              style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                              placeholder="Optional internal notes for admin reference..."></textarea>
                </div>
                
                <!-- Enhanced Notification Section -->
                <div class="p-4 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.1);">
                    <h4 class="font-medium mb-3 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-bell mr-2" style="color: var(--warning);"></i>
                        Notify Landlord
                    </h4>
                    
                    <!-- Enable/Disable Notifications -->
                    <div class="checkbox-group mb-3">
                        <input type="checkbox" id="notify_landlord" checked>
                        <label for="notify_landlord" style="color: var(--text-primary); font-weight: 500;">Send notification to landlord about rejection</label>
                    </div>
                    
                    <!-- Notification Channels Container -->
                    <div id="notificationChannelsContainer" class="mt-3">
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Notification Channels</label>
                        <div class="grid grid-cols-2 gap-3">
                            <!-- Email Channel -->
                            <div class="flex items-center p-3 rounded-lg border channel-option"
                                 style="border-color: var(--border-color); background-color: var(--card-bg);"
                                 data-channel="email">
                                <input type="checkbox" id="notify_email" value="email" class="mr-3 channel-checkbox" checked>
                                <label for="notify_email" class="flex-1 cursor-pointer">
                                    <div class="flex items-center">
                                        <i class="fas fa-envelope mr-2" style="color: var(--info);"></i>
                                        <div>
                                            <div class="text-sm font-medium" style="color: var(--text-primary);">Email</div>
                                            <div class="text-xs" id="emailStatus" style="color: var(--text-secondary);">Checking...</div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            
                            <!-- SMS Channel -->
                            <div class="flex items-center p-3 rounded-lg border channel-option"
                                 style="border-color: var(--border-color); background-color: var(--card-bg);"
                                 data-channel="sms">
                                <input type="checkbox" id="notify_sms" value="sms" class="mr-3 channel-checkbox" checked>
                                <label for="notify_sms" class="flex-1 cursor-pointer">
                                    <div class="flex items-center">
                                        <i class="fas fa-phone mr-2" style="color: var(--primary);"></i>
                                        <div>
                                            <div class="text-sm font-medium" style="color: var(--text-primary);">SMS</div>
                                            <div class="text-xs" id="smsStatus" style="color: var(--text-secondary);">Checking...</div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Custom Message -->
                    <div class="mt-4" id="customMessageContainer">
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Custom Message (Optional)</label>
                        <textarea id="custom_message" rows="3"
                                  class="form-input w-full p-3 rounded-lg border"
                                  style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                  placeholder="Add a personal message to the landlord..."></textarea>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            If left blank, a default rejection message will be used including the rejection reason.
                        </div>
                    </div>
                    
                    <!-- Channel Availability Warning -->
                    <div id="channelWarning" class="mt-3 hidden">
                        <div class="p-3 rounded-lg text-sm" style="background-color: rgba(var(--warning-rgb), 0.1); border-left: 3px solid var(--warning);">
                            <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i>
                            <span id="channelWarningText" style="color: var(--text-primary);"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('quickRejectModal')">
                Cancel
            </button>
            <button type="button" 
                    onclick="submitReject()"
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background-color: var(--danger);">
                <i class="fas fa-times-circle mr-2"></i> Reject Registration
            </button>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div id="exportModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('exportModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Export Registrations</h3>
            <button type="button" class="modal-close" onclick="closeModal('exportModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form action="{{ route('admin.construction-registrations.export') }}" method="GET">
            <div class="modal-body">
                <div class="space-y-4">
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Format</label>
                        <div class="flex space-x-2">
                            <label class="flex-1">
                                <input type="radio" name="format" value="csv" checked class="hidden">
                                <div class="p-3 border rounded-lg cursor-pointer text-center export-format-option"
                                     style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                    <i class="fas fa-file-csv text-2xl mb-2" style="color: var(--success);"></i>
                                    <div class="font-medium" style="color: var(--text-primary);">CSV</div>
                                </div>
                            </label>
                            <label class="flex-1">
                                <input type="radio" name="format" value="xlsx" class="hidden">
                                <div class="p-3 border rounded-lg cursor-pointer text-center export-format-option"
                                     style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                    <i class="fas fa-file-excel text-2xl mb-2" style="color: var(--success);"></i>
                                    <div class="font-medium" style="color: var(--text-primary);">Excel</div>
                                </div>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Include</label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" name="include_documents" value="1" class="mr-2">
                                <span style="color: var(--text-secondary);">Include document references</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="include_notes" value="1" class="mr-2">
                                <span style="color: var(--text-secondary);">Include admin notes</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="include_tenants" value="1" class="mr-2">
                                <span style="color: var(--text-secondary);">Include tenant information</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="include_activity_log" value="1" class="mr-2">
                                <span style="color: var(--text-secondary);">Include activity log</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="include_duplicate_info" value="1" class="mr-2">
                                <span style="color: var(--text-secondary);">Include duplicate detection info</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Apply Current Filters</label>
                        <div class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            The export will apply your current search and filter settings
                        </div>
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
                <button type="submit" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-download mr-2"></i> Export
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Single Archive Modal -->
<div id="singleArchiveModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('singleArchiveModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Archive Registration</h3>
            <button type="button" class="modal-close" onclick="closeModal('singleArchiveModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="space-y-4">
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-primary);">Registration Details</p>
                    <p class="text-sm" id="singleArchiveRegistrationName" style="color: var(--text-secondary);"></p>
                </div>
                
                <div>
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">Archive Reason (Optional)</label>
                    <textarea id="singleArchiveReason" rows="3"
                              class="form-input w-full p-3 rounded-lg border"
                              style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                              placeholder="Optional reason for archiving..."></textarea>
                </div>
                
                <div class="p-4 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.1);">
                    <div class="checkbox-group mb-3">
                        <input type="checkbox" id="singleArchiveNotify" checked>
                        <label for="singleArchiveNotify" style="color: var(--text-primary); font-weight: 500;">Notify applicant about archiving</label>
                    </div>
                    
                    <div id="singleArchiveChannels" class="mt-3">
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Notification Channels</label>
                        <div class="flex space-x-4">
                            <label class="flex items-center">
                                <input type="checkbox" id="singleArchiveEmail" value="email" class="mr-2" checked>
                                <span style="color: var(--text-primary);"><i class="fas fa-envelope mr-1" style="color: var(--warning);"></i> Email</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="singleArchiveSms" value="sms" class="mr-2">
                                <span style="color: var(--text-primary);"><i class="fas fa-phone mr-1" style="color: var(--primary);"></i> SMS</span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        Archived registrations can be restored later from the Archived page.
                    </p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('singleArchiveModal')">
                Cancel
            </button>
            <button type="button" 
                    onclick="submitSingleArchive()"
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background-color: var(--info);">
                <i class="fas fa-archive mr-2"></i> Archive
            </button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

<!-- Registration Data for JavaScript -->
@php
    $registrationsData = [];
    foreach($registrations as $registration) {
        $registrationsData[$registration->id] = [
            'id' => $registration->id,
            'name' => $registration->name,
            'email' => $registration->email,
            'primary_phone' => $registration->primary_phone,
            'property_name' => $registration->property_name,
            'plot_number' => $registration->plot_number,
            'has_email' => !empty($registration->email),
            'has_phone' => !empty($registration->primary_phone),
            'is_vacant_land' => $registration->isConstruction() && !$registration->property_type && !$registration->construction_documents,
            'has_construction_details' => $registration->isConstruction() && ($registration->property_type || $registration->construction_documents),
            'is_property_capture' => $registration->isPropertyCapture(),
            'is_duplicate' => $registration->isDuplicate(),
            'duplicate_match_method' => $registration->duplicate_match_method,
            'duplicate_registration_id' => $registration->duplicate_registration_id,
        ];
    }
@endphp
@endsection

@section('scripts')
<script>
// Global variables
let currentRejectId = null;
let currentArchiveId = null;
let currentBulkAction = null;
let currentRegistrationsData = @json($registrationsData);

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    initializeBulkSelection();
    initializeExportFormat();
    initializeActionButtons();
    initializeNotificationToggle();
    initializeArchiveContainer();
    initializeDeleteHandlers();
    initializeDuplicateHighlighting();
    updateBulkSelectedCount();
    initializeMergeFunctionality();
});

// ==================== DUPLICATE FUNCTIONS ====================

function initializeDuplicateHighlighting() {
    document.querySelectorAll('.duplicate-row').forEach(row => {
        row.style.borderLeft = '4px solid var(--warning)';
        row.style.backgroundColor = 'rgba(var(--warning-rgb), 0.02)';
        
        // Add tooltip with duplicate info
        const regId = row.dataset.registrationId;
        const data = currentRegistrationsData[regId];
        if (data && data.is_duplicate) {
            const tooltip = document.createElement('div');
            tooltip.className = 'duplicate-tooltip hidden';
            tooltip.innerHTML = `
                <div class="p-2 text-xs">
                    <strong>Duplicate Detected</strong><br>
                    Method: ${data.duplicate_match_method || 'Unknown'}<br>
                    ${data.duplicate_registration_id ? `Related ID: ${data.duplicate_registration_id}` : ''}
                </div>
            `;
            row.style.position = 'relative';
            row.appendChild(tooltip);
            
            row.addEventListener('mouseenter', function() {
                tooltip.classList.remove('hidden');
                tooltip.style.position = 'absolute';
                tooltip.style.top = '100%';
                tooltip.style.left = '0';
                tooltip.style.zIndex = '100';
                tooltip.style.backgroundColor = 'var(--card-bg)';
                tooltip.style.border = '1px solid var(--border-color)';
                tooltip.style.borderRadius = '8px';
                tooltip.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
                tooltip.style.minWidth = '200px';
            });
            
            row.addEventListener('mouseleave', function() {
                tooltip.classList.add('hidden');
            });
        }
    });
}

// ==================== BULK MERGE FUNCTIONS ====================

function initializeMergeFunctionality() {
    const mergeSelect = document.getElementById('bulkActionSelect');
    if (mergeSelect) {
        mergeSelect.addEventListener('change', function() {
            if (this.value === 'merge') {
                showBulkMergeOptions();
            }
        });
    }
}

function showBulkMergeOptions() {
    const selectedIds = Array.from(document.querySelectorAll('.registration-checkbox:checked')).map(cb => cb.value);
    const mergeContainer = document.getElementById('bulkMergeContainer');
    const primarySelect = document.getElementById('bulkPrimarySelect');
    
    if (!mergeContainer || !primarySelect) return;
    
    mergeContainer.classList.remove('hidden');
    
    // Clear existing options
    primarySelect.innerHTML = '<option value="">Select Primary...</option>';
    
    // Add selected registrations as options
    selectedIds.forEach(id => {
        const row = document.querySelector(`tr[data-registration-id="${id}"]`);
        if (row) {
            const name = row.dataset.name || 'Unknown';
            const option = document.createElement('option');
            option.value = id;
            option.textContent = `${name} (ID: ${id})`;
            if (selectedIds.length === 1) {
                option.selected = true;
            }
            primarySelect.appendChild(option);
        }
    });
    
    // Show info about merging
    const infoEl = document.getElementById('mergeInfo');
    if (infoEl) {
        infoEl.innerHTML = `
            <div class="p-3 rounded-lg mt-3" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                <p class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1 text-info"></i>
                    Merging ${selectedIds.length} registration(s) into one. All tenants and selected data will be combined into the primary registration.
                </p>
            </div>
        `;
    }
}

async function performBulkMerge() {
    const selectedIds = Array.from(document.querySelectorAll('.registration-checkbox:checked')).map(cb => cb.value);
    const primaryId = document.getElementById('bulkPrimarySelect')?.value;
    
    if (!primaryId) {
        showToast('Please select a primary registration', 'warning');
        return;
    }
    
    if (selectedIds.length < 2) {
        showToast('Please select at least 2 registrations to merge', 'warning');
        return;
    }
    
    const keepData = [];
    if (document.getElementById('mergeKeepDocuments')?.checked) keepData.push('documents');
    if (document.getElementById('mergeKeepLandlordInfo')?.checked) keepData.push('landlord_info');
    if (document.getElementById('mergeKeepPropertyDetails')?.checked) keepData.push('property_details');
    
    if (!confirm(`This will merge ${selectedIds.length - 1} duplicate(s) into the primary registration. This action cannot be undone!\n\nContinue?`)) {
        return;
    }
    
    try {
        const actionBtn = document.getElementById('bulkActionBtn');
        const originalText = actionBtn?.innerHTML || 'Apply';
        if (actionBtn) {
            actionBtn.disabled = true;
            actionBtn.innerHTML = '<span class="spinner"></span> Merging...';
        }
        
        showLoading('Merging duplicate registrations...');
        
        const response = await fetch(`{{ route('admin.construction-registrations.api.merge-duplicates') }}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                primary_id: primaryId,
                duplicate_ids: selectedIds.filter(id => id != primaryId),
                keep_data: keepData
            })
        });
        
        const data = await response.json();
        
        hideLoading();
        
        if (actionBtn) {
            actionBtn.disabled = false;
            actionBtn.innerHTML = originalText;
        }
        
        if (data.success) {
            showToast(data.message || 'Duplicates merged successfully', 'success');
            setTimeout(() => window.location.reload(), 2000);
        } else {
            showToast(data.message || 'Failed to merge duplicates', 'error');
        }
    } catch (error) {
        hideLoading();
        console.error('Merge error:', error);
        showToast('Network error occurred', 'error');
        
        const actionBtn = document.getElementById('bulkActionBtn');
        if (actionBtn) {
            actionBtn.disabled = false;
            actionBtn.innerHTML = '<i class="fas fa-play mr-2"></i> Apply';
        }
    }
}

// ==================== BULK APPROVAL FUNCTIONS ====================

function checkBulkPlanAvailability() {
    const planSelect = document.getElementById('bulkPlanSelect');
    const selectedIds = Array.from(document.querySelectorAll('.registration-checkbox:checked')).map(cb => cb.value);
    const selectedCount = selectedIds.length;
    
    const warningDiv = document.getElementById('planAvailabilityWarning');
    const successDiv = document.getElementById('planAvailabilitySuccess');
    const warningMessage = document.getElementById('planAvailabilityMessage');
    const successMessage = document.getElementById('planAvailabilitySuccessMessage');
    
    if (!planSelect || !planSelect.value) {
        warningDiv.style.display = 'none';
        successDiv.style.display = 'none';
        return;
    }
    
    const selectedOption = planSelect.options[planSelect.selectedIndex];
    const availableSpots = parseInt(selectedOption?.getAttribute('data-available') || 0);
    const estimatedHouses = parseInt(selectedOption?.getAttribute('data-estimated') || 0);
    const occupancyRate = parseFloat(selectedOption?.getAttribute('data-occupancy') || 0);
    
    // Check if plan is full
    if (availableSpots <= 0) {
        warningDiv.style.display = 'block';
        successDiv.style.display = 'none';
        warningMessage.innerHTML = 'This plan is FULL. No available spots left. Please choose another plan.';
        return;
    }
    
    // Check if enough spots for selected registrations
    if (availableSpots < selectedCount) {
        warningDiv.style.display = 'block';
        successDiv.style.display = 'none';
        warningMessage.innerHTML = `This plan only has ${availableSpots} spot(s) available, but you selected ${selectedCount} registration(s). Please choose a plan with more spots or select fewer registrations.`;
        return;
    }
    
    // Show success message
    warningDiv.style.display = 'none';
    successDiv.style.display = 'block';
    successMessage.innerHTML = `Plan has ${availableSpots} spot(s) available for ${selectedCount} selected registration(s). Occupancy: ${occupancyRate}% (${estimatedHouses - availableSpots}/${estimatedHouses} used)`;
}

function handleBulkPropertyTypeChange() {
    const select = document.getElementById('bulkPropertyTypeSelect');
    if (!select) return;
    
    const selectedOption = select.options[select.selectedIndex];
    const isCustom = selectedOption?.getAttribute('data-custom') === 'true';
    const customField = document.getElementById('bulkCustomPropertyField');
    
    if (isCustom) {
        customField.classList.remove('hidden');
    } else {
        customField.classList.add('hidden');
    }
}

function toggleBulkInvitationChannels() {
    const sendInvitation = document.getElementById('bulkSendInvitation')?.checked;
    const channels = document.getElementById('bulkInvitationChannels');
    
    if (sendInvitation) {
        channels.classList.remove('hidden');
    } else {
        channels.classList.add('hidden');
    }
}

function toggleBulkTenantInvitationChannels() {
    const sendTenantInvitations = document.getElementById('bulkSendTenantInvitations')?.checked;
    const channels = document.getElementById('bulkTenantInvitationChannels');
    
    if (sendTenantInvitations) {
        channels.classList.remove('hidden');
    } else {
        channels.classList.add('hidden');
    }
}

function updateBulkSelectedCount() {
    const selectedIds = Array.from(document.querySelectorAll('.registration-checkbox:checked')).map(cb => cb.value);
    const count = selectedIds.length;
    
    const countEl = document.getElementById('bulkSelectedCount');
    const summaryEl = document.getElementById('bulkSelectedSummary');
    
    if (countEl) {
        countEl.textContent = `${count} selected`;
    }
    
    if (summaryEl && count > 0) {
        // Get summary of selected registrations
        let vacantLand = 0;
        let construction = 0;
        let propertyCapture = 0;
        let withTenants = 0;
        let duplicates = 0;
        
        selectedIds.forEach(id => {
            const row = document.querySelector(`tr[data-registration-id="${id}"]`);
            if (row) {
                if (row.dataset.isVacantLand === 'true') vacantLand++;
                if (row.dataset.type === 'construction') construction++;
                if (row.dataset.type === 'property_capture') propertyCapture++;
                if (row.querySelector('.fa-users')) withTenants++;
                if (row.dataset.isDuplicate === 'true') duplicates++;
            }
        });
        
        let summary = '';
        if (vacantLand > 0) summary += `<span class="text-info">${vacantLand} vacant land</span> `;
        if (construction > 0) summary += `<span class="text-primary">${construction} construction</span> `;
        if (propertyCapture > 0) summary += `<span class="text-success">${propertyCapture} property capture</span> `;
        if (withTenants > 0) summary += `<span class="text-warning">${withTenants} with tenants</span> `;
        if (duplicates > 0) summary += `<span class="text-danger">${duplicates} duplicates</span>`;
        
        summaryEl.innerHTML = summary;
    } else if (summaryEl) {
        summaryEl.innerHTML = 'No registrations selected';
    }
    
    // Check plan availability
    checkBulkPlanAvailability();
}

async function performBulkAction() {
    const selectedAction = currentBulkAction;
    
    if (!selectedAction) {
        showToast('Please select an action', 'warning');
        return;
    }
    
    const selectedIds = Array.from(document.querySelectorAll('.registration-checkbox:checked')).map(cb => cb.value);
    
    if (selectedIds.length === 0) {
        showToast('Please select at least one registration', 'warning');
        return;
    }
    
    // Handle merge action separately
    if (selectedAction === 'merge') {
        await performBulkMerge();
        return;
    }
    
    let additionalData = {};
    
    if (selectedAction === 'assign') {
        const adminId = document.getElementById('bulkAdminSelect').value;
        if (!adminId) {
            showToast('Please select an admin to assign', 'warning');
            return;
        }
        additionalData.admin_id = adminId;
    } else if (selectedAction === 'reject') {
        const reason = document.getElementById('bulkRejectReason').value;
        if (!reason) {
            showToast('Please enter a rejection reason', 'warning');
            return;
        }
        additionalData.rejection_reason = reason;
        
        const notifyLandlord = document.getElementById('bulkNotifyLandlord')?.checked || false;
        const notificationChannels = [];
        if (document.getElementById('bulkNotifyEmail')?.checked) notificationChannels.push('email');
        if (document.getElementById('bulkNotifySms')?.checked) notificationChannels.push('sms');
        
        additionalData.notify_landlord = notifyLandlord;
        additionalData.notification_channels = notificationChannels;
    } else if (selectedAction === 'needs_info') {
        const reason = document.getElementById('bulkNeedsInfoReason').value;
        if (!reason) {
            showToast('Please enter what information is needed', 'warning');
            return;
        }
        additionalData.info_requested = reason;
    } else if (selectedAction === 'approve') {
        // BULK APPROVAL VALIDATION
        const planId = document.getElementById('bulkPlanSelect').value;
        if (!planId) {
            showToast('Please select a registration plan', 'warning');
            return;
        }
        
        const landlordId = document.getElementById('bulkLandlordSelect').value;
        if (!landlordId) {
            showToast('Please select a landlord', 'warning');
            return;
        }
        
        // Check plan availability for all selected registrations
        const planSelect = document.getElementById('bulkPlanSelect');
        const selectedOption = planSelect.options[planSelect.selectedIndex];
        const availableSpots = parseInt(selectedOption?.getAttribute('data-available') || 0);
        
        if (availableSpots < selectedIds.length) {
            showToast(`Selected plan only has ${availableSpots} spots available for ${selectedIds.length} registrations. Please choose a plan with more spots.`, 'error');
            return;
        }
        
        // Get plan details
        const zone = selectedOption?.getAttribute('data-zone') || '';
        const section = selectedOption?.getAttribute('data-section') || '';
        
        // Get property type (optional for vacant land)
        const propertyTypeId = document.getElementById('bulkPropertyTypeSelect').value;
        const customPropertyType = document.getElementById('bulkCustomProperty').value;
        
        // Get invitation settings
        const sendInvitation = document.getElementById('bulkSendInvitation')?.checked || false;
        const invitationChannels = [];
        if (sendInvitation) {
            if (document.getElementById('bulkEmailChannel')?.checked) invitationChannels.push('email');
            if (document.getElementById('bulkSmsChannel')?.checked) invitationChannels.push('sms');
        }
        
        const autoApproveTenants = document.getElementById('bulkAutoApproveTenants')?.checked || false;
        const sendTenantInvitations = document.getElementById('bulkSendTenantInvitations')?.checked || false;
        const tenantInvitationChannels = [];
        if (sendTenantInvitations) {
            if (document.getElementById('bulkTenantEmailChannel')?.checked) tenantInvitationChannels.push('email');
            if (document.getElementById('bulkTenantSmsChannel')?.checked) tenantInvitationChannels.push('sms');
        }
        
        // Count registrations that need property type (non-vacant land)
        let needPropertyType = 0;
        let vacantLandCount = 0;
        
        selectedIds.forEach(id => {
            const row = document.querySelector(`tr[data-registration-id="${id}"]`);
            if (row) {
                if (row.dataset.isVacantLand === 'true') {
                    vacantLandCount++;
                } else if (row.dataset.hasConstruction === 'true' || row.dataset.type === 'property_capture') {
                    needPropertyType++;
                }
            }
        });
        
        // If there are registrations that need property type and none selected
        if (needPropertyType > 0 && !propertyTypeId && !customPropertyType) {
            // Try to auto-detect from registration data
            const hasAnyPropertyType = selectedIds.some(id => {
                const data = currentRegistrationsData[id];
                return data && (data.is_property_capture || data.has_construction_details);
            });
            
            if (hasAnyPropertyType) {
                if (!confirm(`You have ${needPropertyType} registration(s) that require a property type. Some registrations may have property type information from their application.\n\nClick OK to proceed (will try to use registration data), or Cancel to select a property type.`)) {
                    return;
                }
            } else {
                showToast('Please select a property type for registrations with construction details', 'warning');
                return;
            }
        }
        
        // Additional confirmation with details
        let confirmMessage = `You are about to approve ${selectedIds.length} registration(s) with the following:\n\n`;
        confirmMessage += `📋 Plan: ${selectedOption?.text?.trim() || 'N/A'}\n`;
        confirmMessage += `👤 Landlord: ${document.getElementById('bulkLandlordSelect').selectedOptions[0]?.text || 'N/A'}\n`;
        confirmMessage += `🏠 Zone: ${zone || 'N/A'}, Section: ${section || 'N/A'}\n`;
        confirmMessage += `📝 Property Type: ${propertyTypeId ? 'Selected' : 'Auto-detected'}\n`;
        confirmMessage += `📨 Landlord Invitation: ${sendInvitation ? 'Yes' : 'No'}\n`;
        confirmMessage += `👥 Tenant Auto-approval: ${autoApproveTenants ? 'Yes' : 'No'}\n`;
        if (vacantLandCount > 0) {
            confirmMessage += `\n⚠️ ${vacantLandCount} vacant land registration(s) will be created with status "vacant".\n`;
        }
        
        // Check for duplicates in approval
        const duplicateCount = selectedIds.filter(id => {
            const data = currentRegistrationsData[id];
            return data && data.is_duplicate;
        }).length;
        
        if (duplicateCount > 0) {
            confirmMessage += `\n⚠️ ${duplicateCount} duplicate registration(s) detected. Please review before approving.\n`;
        }
        
        if (!confirm(confirmMessage)) {
            return;
        }
        
        additionalData = {
            registration_plan_id: planId,
            zone: zone,
            section: section,
            landlord_id: landlordId,
            property_type_id: propertyTypeId || null,
            custom_property_type: customPropertyType || null,
            send_invitation: sendInvitation,
            invitation_channels: invitationChannels,
            auto_approve_tenants: autoApproveTenants,
            send_tenant_invitations: sendTenantInvitations,
            tenant_invitation_channels: tenantInvitationChannels
        };
        
        // Show progress
        document.getElementById('bulkApprovalProgress').classList.remove('hidden');
        document.getElementById('bulkProgressBar').style.width = '0%';
        document.getElementById('bulkProgressText').textContent = '0%';
    } else if (selectedAction === 'archive') {
        const reason = document.getElementById('bulkArchiveReason')?.value || '';
        const notifyApplicant = document.getElementById('bulkArchiveNotify')?.checked || false;
        const notificationChannels = [];
        if (document.getElementById('bulkArchiveEmail')?.checked) notificationChannels.push('email');
        if (document.getElementById('bulkArchiveSms')?.checked) notificationChannels.push('sms');
        
        additionalData = {
            reason: reason,
            notify_applicant: notifyApplicant,
            notification_channels: notificationChannels
        };
    }
    
    if (selectedAction === 'delete') {
        if (!confirm(`Are you sure you want to move ${selectedIds.length} registration(s) to trash?`)) {
            return;
        }
    } else if (selectedAction === 'archive') {
        if (!confirm(`Are you sure you want to archive ${selectedIds.length} registration(s)?`)) {
            return;
        }
    }
    
    try {
        const actionBtn = document.getElementById('bulkActionBtn');
        const originalText = actionBtn?.innerHTML || 'Apply';
        if (actionBtn) {
            actionBtn.disabled = true;
            actionBtn.innerHTML = '<span class="spinner"></span> Processing...';
        }
        
        showLoading(`Processing ${selectedIds.length} registration(s)...`);
        
        const response = await fetch(`{{ route("admin.construction-registrations.api.bulk-action") }}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: selectedAction,
                registration_ids: selectedIds,
                ...additionalData
            })
        });
        
        const data = await response.json();
        
        hideLoading();
        
        if (actionBtn) {
            actionBtn.disabled = false;
            actionBtn.innerHTML = originalText;
        }
        
        // Hide progress
        document.getElementById('bulkApprovalProgress')?.classList.add('hidden');
        
        if (data.success) {
            let successMessage = data.message;
            
            if (selectedAction === 'approve') {
                if (data.total_approved) {
                    successMessage = `✅ ${data.total_approved} registration(s) approved successfully!`;
                    if (data.total_auto_approved_tenants > 0) {
                        successMessage += ` 🏠 ${data.total_auto_approved_tenants} tenant(s) auto-approved.`;
                    }
                    if (data.created_properties && data.created_properties.length > 0) {
                        successMessage += ` 🏢 ${data.created_properties.length} property(ies) created.`;
                    }
                    if (data.duplicates_handled && data.duplicates_handled > 0) {
                        successMessage += ` ⚠️ ${data.duplicates_handled} duplicate(s) handled.`;
                    }
                }
            }
            
            if (data.notification_summary && selectedAction === 'reject') {
                successMessage += ` ${data.notification_summary.sent} notifications sent, ${data.notification_summary.failed} failed.`;
            }
            if (data.notification_summary && selectedAction === 'archive') {
                successMessage += ` ${data.notification_summary.sent} notification(s) sent.`;
            }
            
            showToast(successMessage, 'success');
            
            // Deselect all checkboxes
            deselectAll();
            
            // Update the table (remove approved rows or reload)
            if (selectedAction === 'approve' || selectedAction === 'delete' || selectedAction === 'reject') {
                setTimeout(() => window.location.reload(), 2000);
            } else {
                setTimeout(() => window.location.reload(), 1500);
            }
        } else {
            let errorMessage = data.message || 'Failed to perform action';
            if (data.errors) {
                const errorList = Object.values(data.errors).flat();
                errorMessage = errorList.join(', ');
            }
            showToast(errorMessage, 'error');
        }
        
    } catch (error) {
        hideLoading();
        console.error('Bulk action error:', error);
        showToast('Network error occurred', 'error');
        
        const actionBtn = document.getElementById('bulkActionBtn');
        if (actionBtn) {
            actionBtn.disabled = false;
            actionBtn.innerHTML = '<i class="fas fa-play mr-2"></i> Apply';
        }
        document.getElementById('bulkApprovalProgress')?.classList.add('hidden');
    }
}

// ==================== BULK SELECTION FUNCTIONS ====================

function initializeBulkSelection() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('.registration-checkbox');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
            updateBulkSelectedCount();
        });
    }
    
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectedCount();
            updateBulkSelectedCount();
        });
    });
    
    const bulkActionSelect = document.getElementById('bulkActionSelect');
    if (bulkActionSelect) {
        bulkActionSelect.addEventListener('change', function() {
            const value = this.value;
            const assignContainer = document.getElementById('bulkAssignContainer');
            const rejectContainer = document.getElementById('bulkRejectContainer');
            const needsInfoContainer = document.getElementById('bulkNeedsInfoContainer');
            const approvalContainer = document.getElementById('bulkApprovalContainer');
            const archiveContainer = document.getElementById('bulkArchiveContainer');
            const mergeContainer = document.getElementById('bulkMergeContainer');
            
            // Hide all containers
            assignContainer?.classList.add('hidden');
            rejectContainer?.classList.add('hidden');
            needsInfoContainer?.classList.add('hidden');
            approvalContainer?.classList.add('hidden');
            archiveContainer?.classList.add('hidden');
            mergeContainer?.classList.add('hidden');
            
            // Show relevant container
            if (value === 'assign') {
                assignContainer?.classList.remove('hidden');
            } else if (value === 'reject') {
                rejectContainer?.classList.remove('hidden');
            } else if (value === 'needs_info') {
                needsInfoContainer?.classList.remove('hidden');
            } else if (value === 'approve') {
                approvalContainer?.classList.remove('hidden');
                // Check plan availability when approval is shown
                setTimeout(checkBulkPlanAvailability, 100);
            } else if (value === 'archive') {
                archiveContainer?.classList.remove('hidden');
            } else if (value === 'merge') {
                mergeContainer?.classList.remove('hidden');
                showBulkMergeOptions();
            }
            
            currentBulkAction = value;
        });
    }
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.registration-checkbox:checked');
    const selectedCount = checkboxes.length;
    const bulkActionBtn = document.getElementById('bulkActionBtn');
    
    if (bulkActionBtn) {
        if (selectedCount > 0) {
            bulkActionBtn.disabled = false;
            bulkActionBtn.innerHTML = `<i class="fas fa-play mr-2"></i> Apply (${selectedCount} selected)`;
        } else {
            bulkActionBtn.disabled = true;
            bulkActionBtn.innerHTML = `<i class="fas fa-play mr-2"></i> Apply`;
        }
    }
}

function selectAll() {
    document.querySelectorAll('.registration-checkbox').forEach(cb => cb.checked = true);
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (selectAllCheckbox) selectAllCheckbox.checked = true;
    updateSelectedCount();
    updateBulkSelectedCount();
}

function deselectAll() {
    document.querySelectorAll('.registration-checkbox').forEach(cb => cb.checked = false);
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (selectAllCheckbox) selectAllCheckbox.checked = false;
    updateSelectedCount();
    updateBulkSelectedCount();
}

// ==================== DELETE FUNCTIONS ====================

function initializeDeleteHandlers() {
    console.log('🔍 Initializing delete handlers...');
    
    document.querySelectorAll('form[action*="destroy"]').forEach(form => {
        const methodInput = form.querySelector('input[name="_method"][value="DELETE"]');
        if (methodInput) {
            const registrationName = form.dataset.registrationName || 
                                   form.closest('tr')?.querySelector('.font-medium')?.textContent || 
                                   'this registration';
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (!confirm(`Are you sure you want to move "${registrationName}" to trash?`)) {
                    return;
                }
                
                const btn = this.querySelector('button[type="submit"]');
                const originalHtml = btn ? btn.innerHTML : '';
                if (btn) {
                    btn.innerHTML = '<span class="spinner"></span> Deleting...';
                    btn.disabled = true;
                }
                
                const row = this.closest('tr');
                
                fetch(this.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new FormData(this),
                    redirect: 'manual'
                })
                .then(response => {
                    if (response.type === 'opaqueredirect' || response.status === 0) {
                        showToast('Registration moved to trash successfully', 'success');
                        if (row) {
                            row.style.transition = 'all 0.3s ease';
                            row.style.opacity = '0';
                            row.style.transform = 'translateX(20px)';
                            setTimeout(() => {
                                row.remove();
                                updateCountsAfterDelete();
                                checkEmptyTable(row);
                            }, 300);
                        }
                        if (btn) {
                            btn.innerHTML = originalHtml;
                            btn.disabled = false;
                        }
                        return;
                    }
                    
                    return response.text().then(text => {
                        try {
                            let cleanText = text;
                            if (cleanText.charCodeAt(0) === 0xFEFF) cleanText = cleanText.substring(1);
                            if (cleanText.startsWith('\uFEFF')) cleanText = cleanText.substring(1);
                            if (cleanText.startsWith('﻿')) cleanText = cleanText.substring(1);
                            return { success: true, data: JSON.parse(cleanText) };
                        } catch (e) {
                            return { success: true, data: { message: 'Registration moved to trash successfully' } };
                        }
                    });
                })
                .then(result => {
                    if (result && result.data && result.data.success === false) {
                        showToast(result.data.message || 'Failed to delete', 'error');
                        if (btn) {
                            btn.innerHTML = originalHtml;
                            btn.disabled = false;
                        }
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    showToast('Registration moved to trash successfully', 'success');
                    if (row) {
                        row.style.transition = 'all 0.3s ease';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(20px)';
                        setTimeout(() => {
                            row.remove();
                            updateCountsAfterDelete();
                            checkEmptyTable(row);
                        }, 300);
                    }
                })
                .finally(() => {
                    if (btn) {
                        btn.innerHTML = originalHtml;
                        btn.disabled = false;
                    }
                });
            });
        }
    });
}

function updateCountsAfterDelete() {
    const totalCountEl = document.querySelector('.stat-card .text-2xl.font-bold');
    if (totalCountEl) {
        const currentCount = parseInt(totalCountEl.textContent) || 0;
        if (currentCount > 0) {
            totalCountEl.textContent = (currentCount - 1).toString();
        }
    }
    
    const trashBadge = document.querySelector('a[href*="trash"] .absolute');
    if (trashBadge) {
        const currentTrashCount = parseInt(trashBadge.textContent) || 0;
        trashBadge.textContent = (currentTrashCount + 1).toString();
    }
}

function checkEmptyTable(row) {
    const tbody = row?.closest('tbody');
    if (tbody && tbody.children.length === 0) {
        const colspan = tbody.closest('table')?.querySelector('thead tr')?.children?.length || 10;
        tbody.innerHTML = `
            <tr>
                <td colspan="${colspan}" class="py-8 px-4 text-center">
                    <div class="flex flex-col items-center justify-center">
                        <i class="fas fa-hard-hat text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                        <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">No registrations found</p>
                        <p style="color: var(--text-secondary);">Try adjusting your filters</p>
                    </div>
                </td>
            </tr>
        `;
    }
}

// ==================== QUICK ASSIGN FUNCTIONS ====================

function quickAssign(registrationId, registrationName) {
    document.getElementById('quickAssignRegistrationName').textContent = registrationName;
    document.getElementById('quickAssignForm').action = `{{ url('admin/construction-registrations') }}/${registrationId}/assign`;
    openModal('quickAssignModal');
}

// ==================== QUICK REJECT FUNCTIONS ====================

function quickReject(registrationId, registrationName) {
    currentRejectId = registrationId;
    document.getElementById('quickRejectRegistrationName').textContent = registrationName;
    
    document.getElementById('rejection_reason').value = '';
    document.getElementById('admin_notes').value = '';
    document.getElementById('custom_message').value = '';
    document.getElementById('notify_landlord').checked = true;
    
    const emailCheckbox = document.getElementById('notify_email');
    const smsCheckbox = document.getElementById('notify_sms');
    if (emailCheckbox) {
        emailCheckbox.checked = true;
        emailCheckbox.disabled = false;
    }
    if (smsCheckbox) {
        smsCheckbox.checked = true;
        smsCheckbox.disabled = false;
    }
    
    const emailStatus = document.getElementById('emailStatus');
    const smsStatus = document.getElementById('smsStatus');
    if (emailStatus) {
        emailStatus.innerHTML = 'Checking availability...';
        emailStatus.style.color = 'var(--text-secondary)';
    }
    if (smsStatus) {
        smsStatus.innerHTML = 'Checking availability...';
        smsStatus.style.color = 'var(--text-secondary)';
    }
    
    hideChannelWarning();
    
    const channelsContainer = document.getElementById('notificationChannelsContainer');
    if (channelsContainer) {
        channelsContainer.style.display = 'block';
    }
    
    checkChannelAvailability(registrationId);
    
    openModal('quickRejectModal');
}

function checkChannelAvailability(registrationId) {
    const registration = currentRegistrationsData[registrationId];
    
    if (!registration) {
        console.error('Registration data not found for ID:', registrationId);
        return;
    }
    
    const emailStatus = document.getElementById('emailStatus');
    const smsStatus = document.getElementById('smsStatus');
    const emailCheckbox = document.getElementById('notify_email');
    const smsCheckbox = document.getElementById('notify_sms');
    
    if (registration.has_email && registration.email) {
        if (emailStatus) {
            emailStatus.innerHTML = '<i class="fas fa-check-circle mr-1" style="color: var(--success);"></i> Available';
            emailStatus.style.color = 'var(--success)';
        }
    } else {
        if (emailStatus) {
            emailStatus.innerHTML = '<i class="fas fa-times-circle mr-1" style="color: var(--danger);"></i> No email address provided';
            emailStatus.style.color = 'var(--danger)';
        }
        if (emailCheckbox) {
            emailCheckbox.checked = false;
            emailCheckbox.disabled = true;
        }
    }
    
    if (registration.has_phone && registration.primary_phone) {
        if (smsStatus) {
            smsStatus.innerHTML = '<i class="fas fa-check-circle mr-1" style="color: var(--success);"></i> Available';
            smsStatus.style.color = 'var(--success)';
        }
    } else {
        if (smsStatus) {
            smsStatus.innerHTML = '<i class="fas fa-times-circle mr-1" style="color: var(--danger);"></i> No phone number provided';
            smsStatus.style.color = 'var(--danger)';
        }
        if (smsCheckbox) {
            smsCheckbox.checked = false;
            smsCheckbox.disabled = true;
        }
    }
    
    const hasEmail = registration.has_email;
    const hasPhone = registration.has_phone;
    
    if (!hasEmail && !hasPhone) {
        showChannelWarning('Landlord has no email or phone number. Notifications cannot be sent.');
        const notifyCheckbox = document.getElementById('notify_landlord');
        if (notifyCheckbox) {
            notifyCheckbox.checked = false;
            notifyCheckbox.disabled = true;
        }
    } else if (!hasEmail && hasPhone) {
        showChannelWarning('Landlord has no email address. SMS will be used.');
    } else if (hasEmail && !hasPhone) {
        showChannelWarning('Landlord has no phone number. Email will be used.');
    } else {
        hideChannelWarning();
    }
}

function showChannelWarning(message) {
    const warningContainer = document.getElementById('channelWarning');
    const warningText = document.getElementById('channelWarningText');
    
    if (warningContainer && warningText) {
        warningText.innerHTML = message;
        warningContainer.classList.remove('hidden');
    }
}

function hideChannelWarning() {
    const warningContainer = document.getElementById('channelWarning');
    if (warningContainer) {
        warningContainer.classList.add('hidden');
    }
}

async function submitReject() {
    const reason = document.getElementById('rejection_reason').value.trim();
    const adminNotes = document.getElementById('admin_notes').value.trim();
    const notifyLandlord = document.getElementById('notify_landlord').checked;
    const customMessage = document.getElementById('custom_message').value.trim();
    
    let notificationChannels = [];
    if (notifyLandlord) {
        const emailCheckbox = document.getElementById('notify_email');
        const smsCheckbox = document.getElementById('notify_sms');
        
        if (emailCheckbox && emailCheckbox.checked && !emailCheckbox.disabled) notificationChannels.push('email');
        if (smsCheckbox && smsCheckbox.checked && !smsCheckbox.disabled) notificationChannels.push('sms');
    }
    
    if (!reason) {
        showToast('Please provide a rejection reason', 'warning');
        document.getElementById('rejection_reason').focus();
        return;
    }
    
    if (reason.length < 10) {
        showToast('Please provide a more detailed rejection reason (minimum 10 characters)', 'warning');
        return;
    }
    
    if (notifyLandlord && notificationChannels.length === 0) {
        if (!confirm('Notifications are enabled but no channels selected. The landlord will not receive any notification. Continue anyway?')) {
            return;
        }
    }
    
    const submitBtn = document.querySelector('#quickRejectModal button[onclick="submitReject()"]');
    const originalText = submitBtn.innerHTML;
    
    try {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner"></span> Processing rejection...';
        
        showLoading('Processing rejection and sending notifications...');
        
        const response = await fetch(`/admin/construction-registrations/${currentRejectId}/update-status`, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                status: 'rejected',
                rejection_reason: reason,
                admin_notes: adminNotes || null,
                notify_landlord: notifyLandlord,
                notification_channels: notificationChannels,
                custom_message: customMessage || null
            })
        });
        
        const data = await response.json();
        
        hideLoading();
        
        if (data.success) {
            let successMessage = data.message || 'Registration rejected successfully';
            if (data.notification_sent && data.notification_channels_sent && data.notification_channels_sent.length > 0) {
                successMessage += ` Notification sent via ${data.notification_channels_sent.join(', ')}.`;
            } else if (notifyLandlord && notificationChannels.length > 0) {
                if (data.notification_error) {
                    successMessage += ` Warning: ${data.notification_error}`;
                } else if (!data.notification_sent) {
                    successMessage += ' Notification could not be sent.';
                }
            }
            
            showToast(successMessage, 'success');
            closeModal('quickRejectModal');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            let errorMessage = data.message || 'Failed to reject registration';
            if (data.errors) {
                const errorList = Object.values(data.errors).flat();
                errorMessage = errorList.join(', ');
            }
            showToast(errorMessage, 'error');
        }
    } catch (error) {
        hideLoading();
        console.error('Error:', error);
        showToast('Network error occurred. Please try again.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
}

// ==================== ARCHIVE FUNCTIONS ====================

function initializeArchiveContainer() {
    const archiveContainer = document.getElementById('bulkArchiveContainer');
    if (archiveContainer) {
        archiveContainer.classList.add('hidden');
    }
}

function bulkArchive() {
    currentBulkAction = 'archive';
    const bulkActionSelect = document.getElementById('bulkActionSelect');
    if (bulkActionSelect) {
        bulkActionSelect.value = 'archive';
        const event = new Event('change');
        bulkActionSelect.dispatchEvent(event);
    }
    document.querySelector('.card:has(#bulkActionSelect)').scrollIntoView({ behavior: 'smooth' });
}

function archiveSingle(registrationId, registrationName) {
    currentArchiveId = registrationId;
    document.getElementById('singleArchiveRegistrationName').textContent = registrationName;
    
    document.getElementById('singleArchiveReason').value = '';
    document.getElementById('singleArchiveNotify').checked = true;
    document.getElementById('singleArchiveEmail').checked = true;
    document.getElementById('singleArchiveSms').checked = false;
    
    openModal('singleArchiveModal');
}

async function submitSingleArchive() {
    if (!currentArchiveId) {
        showToast('No registration selected', 'error');
        return;
    }
    
    const reason = document.getElementById('singleArchiveReason').value.trim();
    const notifyApplicant = document.getElementById('singleArchiveNotify').checked;
    const notificationChannels = [];
    
    if (notifyApplicant) {
        if (document.getElementById('singleArchiveEmail').checked) notificationChannels.push('email');
        if (document.getElementById('singleArchiveSms').checked) notificationChannels.push('sms');
    }
    
    const submitBtn = document.querySelector('#singleArchiveModal button[onclick="submitSingleArchive()"]');
    const originalText = submitBtn.innerHTML;
    
    try {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner"></span> Archiving...';
        
        showLoading('Archiving registration...');
        
        const response = await fetch(`/admin/construction-registrations/archive`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                registration_ids: [currentArchiveId],
                reason: reason,
                notify_applicant: notifyApplicant,
                notification_channels: notificationChannels
            })
        });
        
        const data = await response.json();
        
        hideLoading();
        
        if (data.success) {
            let successMessage = data.message || 'Registration archived successfully';
            if (data.notification_summary && notifyApplicant) {
                successMessage += ` ${data.notification_summary.sent} notification(s) sent.`;
            }
            showToast(successMessage, 'success');
            closeModal('singleArchiveModal');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast(data.message || 'Failed to archive registration', 'error');
        }
    } catch (error) {
        hideLoading();
        console.error('Archive error:', error);
        showToast('Network error occurred', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
}

function bulkUpdateStatus(status) {
    currentBulkAction = status;
    const bulkActionSelect = document.getElementById('bulkActionSelect');
    if (bulkActionSelect) bulkActionSelect.value = status;
    performBulkAction();
}

function bulkAssignAdmin() {
    currentBulkAction = 'assign';
    const bulkActionSelect = document.getElementById('bulkActionSelect');
    if (bulkActionSelect) bulkActionSelect.value = 'assign';
    document.getElementById('bulkAssignContainer').classList.remove('hidden');
    performBulkAction();
}

// ==================== NOTIFICATION TOGGLE ====================

function initializeNotificationToggle() {
    const notifyCheckbox = document.getElementById('notify_landlord');
    const channelsContainer = document.getElementById('notificationChannelsContainer');
    
    if (notifyCheckbox && channelsContainer) {
        channelsContainer.style.display = notifyCheckbox.checked ? 'block' : 'none';
        
        notifyCheckbox.addEventListener('change', function() {
            channelsContainer.style.display = this.checked ? 'block' : 'none';
            if (!this.checked) {
                hideChannelWarning();
            }
        });
    }
}

// ==================== EXPORT FUNCTIONS ====================

function showExportModal() {
    openModal('exportModal');
}

function initializeExportFormat() {
    const exportFormatOptions = document.querySelectorAll('.export-format-option');
    exportFormatOptions.forEach(option => {
        option.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input[type="radio"]');
            if (input) {
                input.checked = true;
                exportFormatOptions.forEach(opt => {
                    opt.style.borderColor = 'var(--border-color)';
                    opt.style.backgroundColor = 'var(--bg-secondary)';
                });
                this.style.borderColor = 'var(--primary)';
                this.style.backgroundColor = 'rgba(var(--primary-rgb), 0.1)';
            }
        });
    });
}

// ==================== UI HELPER FUNCTIONS ====================

function initializeActionButtons() {
    document.querySelectorAll('.action-btn').forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });
    });
}

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

// ==================== TOAST SYSTEM ====================

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
    
    const iconMap = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    const icon = document.createElement('i');
    icon.className = `fas ${iconMap[type] || 'fa-info-circle'} mr-2`;
    icon.style.fontSize = '1.1rem';
    
    const messageEl = document.createElement('span');
    messageEl.className = 'text-sm font-medium flex-1';
    messageEl.textContent = message;
    
    const closeBtn = document.createElement('button');
    closeBtn.className = 'ml-4 transition-colors duration-200 hover:opacity-70';
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.onclick = () => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    };
    
    const contentWrapper = document.createElement('div');
    contentWrapper.className = 'flex items-center flex-1';
    contentWrapper.appendChild(icon);
    contentWrapper.appendChild(messageEl);
    
    toast.appendChild(contentWrapper);
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
                <div class="text-white text-sm mt-2" id="loadingSubMessage"></div>
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

console.log('Admin Construction Registrations loaded successfully');
</script>

<style>
/* Bulk selection styles */
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

/* Card styles */
.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
}

/* Button styles */
.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: 1px solid var(--primary) !important;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
    transform: translateY(-1px);
}

.btn-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
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

.form-input:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Channel option styles */
.channel-option {
    transition: all 0.2s ease;
    cursor: pointer;
}

.channel-option:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.channel-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.channel-checkbox:disabled + label {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Duplicate row highlighting */
.duplicate-row {
    border-left: 4px solid var(--warning) !important;
    background-color: rgba(var(--warning-rgb), 0.02) !important;
    position: relative;
}

.duplicate-row:hover {
    background-color: rgba(var(--warning-rgb), 0.05) !important;
}

.duplicate-tooltip {
    position: absolute;
    top: 100%;
    left: 0;
    z-index: 100;
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 8px 12px;
    min-width: 200px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    color: var(--text-primary);
}

.duplicate-tooltip.hidden {
    display: none;
}

/* Loading overlay */
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
    z-index: 9999;
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

/* Export format option */
.export-format-option {
    transition: all 0.2s ease;
    cursor: pointer;
}

.export-format-option:hover {
    transform: translateY(-2px);
    border-color: var(--primary) !important;
}

/* Applicant icon */
.applicant-icon {
    transition: all 0.2s ease;
}

tr:hover .applicant-icon {
    transform: scale(1.1);
}

/* Table hover effects */
tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.02) !important;
}

/* Stats cards grid */
.grid.grid-cols-1.md\:grid-cols-7 {
    grid-template-columns: repeat(7, 1fr);
}

/* Bulk merge container styles */
#bulkMergeContainer {
    transition: all 0.3s ease;
}

#bulkMergeContainer select,
#bulkMergeContainer input {
    transition: all 0.2s ease;
}

#bulkMergeContainer select:focus,
#bulkMergeContainer input:focus {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1) !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-7 {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .grid.grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
    
    table th, table td {
        padding: 0.5rem;
        font-size: 0.875rem;
    }
    
    .modal-container {
        margin: 1rem;
    }
}

@media (max-width: 640px) {
    .grid.grid-cols-1.md\:grid-cols-7 {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .p-6 {
        padding: 1rem !important;
    }
    
    .text-xl {
        font-size: 1.25rem !important;
    }
    
    .text-2xl {
        font-size: 1.5rem !important;
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

/* Required field indicator */
.required::after {
    content: '*';
    color: var(--danger);
    margin-left: 4px;
}

/* Hide element class */
.hidden {
    display: none !important;
}

/* Notification channels container transition */
#notificationChannelsContainer {
    transition: all 0.3s ease;
}

/* Bulk approval container styles */
#bulkApprovalContainer {
    transition: all 0.3s ease;
}

#bulkApprovalContainer select,
#bulkApprovalContainer input,
#bulkApprovalContainer textarea {
    transition: all 0.2s ease;
}

#bulkApprovalContainer select:focus,
#bulkApprovalContainer input:focus,
#bulkApprovalContainer textarea:focus {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1) !important;
}

/* Plan availability warning styles */
#planAvailabilityWarning {
    animation: fadeIn 0.3s ease;
}

#planAvailabilitySuccess {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-5px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Bulk progress bar */
#bulkProgressBar {
    transition: width 0.5s ease;
}

/* Selected registrations summary */
#bulkSelectedSummary {
    line-height: 1.6;
}

#bulkSelectedSummary span {
    display: inline-block;
    margin-right: 8px;
}

#bulkSelectedSummary .text-info { color: var(--info); }
#bulkSelectedSummary .text-primary { color: var(--primary); }
#bulkSelectedSummary .text-success { color: var(--success); }
#bulkSelectedSummary .text-warning { color: var(--warning); }
#bulkSelectedSummary .text-danger { color: var(--danger); }
</style>
@endsection