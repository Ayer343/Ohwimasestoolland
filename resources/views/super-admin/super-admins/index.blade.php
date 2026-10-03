{{-- super-admin/super-admins/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Super Administrators Management')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                        <i class="fas fa-user-shield text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                        Super Administrators
                    </h2>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        View all Super Admin accounts across the system
                    </p>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Total</p>
                    <p class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ $statistics['total'] }}</p>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                     style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-users text-lg" style="color: var(--primary);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Active</p>
                    <p class="text-2xl font-bold mt-1 text-green-600">{{ $statistics['active'] }}</p>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                     style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-check-circle text-lg" style="color: var(--success);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Pending</p>
                    <p class="text-2xl font-bold mt-1 text-yellow-600">{{ $statistics['pending'] }}</p>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                     style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-clock text-lg" style="color: var(--warning);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Suspended</p>
                    <p class="text-2xl font-bold mt-1 text-red-600">{{ $statistics['suspended'] }}</p>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                     style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-ban text-lg" style="color: var(--danger);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Inactive</p>
                    <p class="text-2xl font-bold mt-1" style="color: var(--text-secondary);">{{ $statistics['inactive'] }}</p>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                     style="background-color: rgba(var(--secondary-rgb), 0.1);">
                    <i class="fas fa-user-slash text-lg" style="color: var(--secondary);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Trashed</p>
                    <p class="text-2xl font-bold mt-1" style="color: var(--text-secondary);">{{ $statistics['trashed'] }}</p>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                     style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-trash text-lg" style="color: var(--danger);"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card p-6">
        <form method="GET" action="{{ route('super-admin.super-admins.index') }}" id="filterForm">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Search</label>
                    <input type="text" 
                           name="search" 
                           value="{{ request('search') }}" 
                           placeholder="Name, email, phone..."
                           class="index-custom-input w-full">
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Status</label>
                    <select name="status" class="index-custom-dropdown w-full">
                        <option value="all">All Statuses</option>
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Date From</label>
                    <input type="date" 
                           name="date_from" 
                           value="{{ request('date_from') }}" 
                           class="index-custom-input w-full">
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Date To</label>
                    <input type="date" 
                           name="date_to" 
                           value="{{ request('date_to') }}" 
                           class="index-custom-input w-full">
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Per Page</label>
                    <select name="per_page" class="index-custom-dropdown w-full">
                        <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10</option>
                        <option value="20" {{ request('per_page') == 20 ? 'selected' : '' }}>20</option>
                        <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-between items-center mt-4">
                <div>
                    <button type="submit" class="btn-primary px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-search mr-2"></i> Apply Filters
                    </button>
                    
                    @if(request()->anyFilled(['search', 'status', 'date_from', 'date_to']))
                        <a href="{{ route('super-admin.super-admins.index') }}" 
                           class="btn-secondary px-4 py-2 rounded-lg text-sm font-medium ml-2">
                            <i class="fas fa-undo mr-2"></i> Clear
                        </a>
                    @endif
                </div>
                
                <div>
                    <button type="submit" name="export" value="csv" class="btn-success px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-download mr-2"></i> Export CSV
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Developer Distribution Card -->
    @if(isset($developerStatistics) && $developerStatistics->count() > 0)
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
            <i class="fas fa-chart-pie mr-2" style="color: var(--primary);"></i>
            Super Admin Distribution by Developer
        </h3>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <th class="text-left py-3 px-4 font-semibold text-sm" style="color: var(--text-secondary);">Developer</th>
                        <th class="text-center py-3 px-4 font-semibold text-sm" style="color: var(--text-secondary);">Total</th>
                        <th class="text-center py-3 px-4 font-semibold text-sm" style="color: var(--text-secondary);">Active</th>
                        <th class="text-center py-3 px-4 font-semibold text-sm" style="color: var(--text-secondary);">Pending</th>
                        <th class="text-center py-3 px-4 font-semibold text-sm" style="color: var(--text-secondary);">Suspended</th>
                        <th class="text-center py-3 px-4 font-semibold text-sm" style="color: var(--text-secondary);">Inactive</th>
                        <th class="text-right py-3 px-4 font-semibold text-sm" style="color: var(--text-secondary);">Last Created</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($developerStatistics as $dev)
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td class="py-3 px-4">
                            <div>
                                <p class="font-medium text-sm" style="color: var(--text-primary);">{{ $dev->developer_name }}</p>
                                <p class="text-xs" style="color: var(--text-secondary);">{{ $dev->developer_email }}</p>
                            </div>
                        </td>
                        <td class="text-center py-3 px-4 font-bold" style="color: var(--text-primary);">{{ $dev->total }}</td>
                        <td class="text-center py-3 px-4 text-green-600">{{ $dev->active }}</td>
                        <td class="text-center py-3 px-4 text-yellow-600">{{ $dev->pending }}</td>
                        <td class="text-center py-3 px-4 text-red-600">{{ $dev->suspended }}</td>
                        <td class="text-center py-3 px-4" style="color: var(--text-secondary);">{{ $dev->inactive }}</td>
                        <td class="text-right py-3 px-4 text-sm" style="color: var(--text-secondary);">
                            {{ $dev->last_created_at ? \Carbon\Carbon::parse($dev->last_created_at)->format('M j, Y') : 'N/A' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Super Admins Table - NO HOVER EFFECT -->
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr style="background-color: rgba(var(--secondary-rgb), 0.05); border-bottom: 2px solid var(--border-color);">
                        <th class="text-left py-4 px-6 font-semibold text-sm" style="color: var(--text-secondary);">Super Admin</th>
                        <th class="text-left py-4 px-6 font-semibold text-sm" style="color: var(--text-secondary);">Contact</th>
                        <th class="text-center py-4 px-6 font-semibold text-sm" style="color: var(--text-secondary);">Roles</th>
                        <th class="text-center py-4 px-6 font-semibold text-sm" style="color: var(--text-secondary);">Status</th>
                        <th class="text-center py-4 px-6 font-semibold text-sm" style="color: var(--text-secondary);">Verification</th>
                        <th class="text-center py-4 px-6 font-semibold text-sm" style="color: var(--text-secondary);">Created By</th>
                        <th class="text-center py-4 px-6 font-semibold text-sm" style="color: var(--text-secondary);">Created</th>
                        <th class="text-center py-4 px-6 font-semibold text-sm" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($superAdmins as $admin)
                    @php
                        $hasLandlordRole = $admin->hasRole('landlord');
                        $hasAdminRole = $admin->hasRole('admin');
                        $propertyCount = $admin->properties()->count();
                        $isOwnProfile = auth()->id() == $admin->id;
                    @endphp
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td class="py-4 px-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    @if($admin->has_photo && $admin->photo)
                                    <div class="w-10 h-10 rounded-full overflow-hidden">
                                        <img src="{{ Storage::url('users/photos/' . $admin->photo) }}" 
                                             alt="{{ $admin->name }}" 
                                             class="w-full h-full object-cover">
                                    </div>
                                    @else
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-size: 14px; font-weight: 600;">
                                        {{ $admin->initials }}
                                    </div>
                                    @endif
                                </div>
                                <div class="ml-3">
                                    <p class="font-medium text-sm" style="color: var(--text-primary);">{{ $admin->name }}</p>
                                    <p class="text-xs" style="color: var(--text-secondary);">@ {{ $admin->username ?? 'No username' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-6">
                            <p class="text-sm" style="color: var(--text-primary);">{{ $admin->email }}</p>
                            <p class="text-xs" style="color: var(--text-secondary);">{{ $admin->local_phone ?? 'No phone' }}</p>
                        </td>
                        <td class="py-4 px-6 text-center">
                            <div class="flex flex-wrap justify-center gap-1">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                      style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                                    Super Admin
                                </span>
                                @if($hasLandlordRole)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                      style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                    <i class="fas fa-home mr-1"></i> Landlord
                                    @if($propertyCount > 0)
                                    <span class="ml-1 px-1 py-0.5 rounded-full text-xs" style="background-color: var(--success); color: white;">
                                        {{ $propertyCount }}
                                    </span>
                                    @endif
                                </span>
                                @endif
                                @if($hasAdminRole)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                      style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                    <i class="fas fa-user-shield mr-1"></i> Admin
                                </span>
                                @endif
                            </div>
                        </td>
                        <td class="py-4 px-6 text-center">
                            <span class="inline-flex items-center status-indicator status-{{ $admin->status }}">
                                {{ $admin->display_status['label'] }}
                            </span>
                        </td>
                        <td class="py-4 px-6 text-center">
                            <div class="flex justify-center space-x-1">
                                @if($admin->email_verified_at)
                                    <i class="fas fa-envelope text-green-500" title="Email Verified"></i>
                                @else
                                    <i class="fas fa-envelope text-gray-400" title="Email Not Verified"></i>
                                @endif
                                @if($admin->phone_verified_at)
                                    <i class="fas fa-phone text-green-500 ml-1" title="Phone Verified"></i>
                                @else
                                    <i class="fas fa-phone text-gray-400 ml-1" title="Phone Not Verified"></i>
                                @endif
                            </div>
                        </td>
                        <td class="py-4 px-6 text-center">
                            <p class="text-sm" style="color: var(--text-primary);">{{ $admin->creator_name }}</p>
                        </td>
                        <td class="py-4 px-6 text-center">
                            <p class="text-sm" style="color: var(--text-primary);">{{ $admin->created_at->format('M j, Y') }}</p>
                            <p class="text-xs" style="color: var(--text-secondary);">{{ $admin->age }}</p>
                        </td>
                        <td class="py-4 px-6 text-center">
                            <div class="flex justify-center space-x-2">
                                <a href="{{ route('super-admin.super-admins.show', $admin->id) }}" 
                                   class="btn-info px-3 py-1 rounded text-xs font-medium inline-flex items-center">
                                    <i class="fas fa-eye mr-1"></i> View
                                </a>
                                @if($isOwnProfile)
                                    <button type="button" 
                                            onclick="openRoleModal({{ $admin->id }}, '{{ addslashes($admin->name) }}', {{ $hasLandlordRole ? 'true' : 'false' }}, {{ $propertyCount }}, {{ $hasAdminRole ? 'true' : 'false' }})"
                                            class="btn-warning px-3 py-1 rounded text-xs font-medium inline-flex items-center"
                                            style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                        <i class="fas fa-user-tag mr-1"></i> Roles
                                    </button>
                                @endif
                                @if($isOwnProfile)
                                <a href="{{ route('super-admin.super-admins.activities', $admin->id) }}" 
                                   class="btn-secondary px-3 py-1 rounded text-xs font-medium inline-flex items-center">
                                    <i class="fas fa-history mr-1"></i> Activities
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-12 text-center">
                            <i class="fas fa-user-shield text-5xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                            <p class="text-lg font-medium" style="color: var(--text-primary);">No Super Admins Found</p>
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">No super admin accounts match your filters.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($superAdmins->hasPages())
        <div class="p-6 border-t" style="border-color: var(--border-color);">
            {{ $superAdmins->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>

<!-- ==================== ROLE MANAGEMENT MODAL ==================== -->
@if(auth()->user()->isSuperAdmin())
<div id="roleModal" class="fixed inset-0 bg-black bg-opacity-50 hidden overflow-y-auto h-full w-full z-50" style="backdrop-filter: blur(4px);">
    <div class="relative top-20 mx-auto p-6 border w-full max-w-md shadow-xl rounded-xl" style="background-color: var(--bg-primary);">
        <div class="flex justify-between items-center mb-5 pb-3 border-b" style="border-color: var(--border-color);">
            <h3 class="text-xl font-semibold flex items-center gap-2" style="color: var(--text-primary);">
                <i class="fas fa-user-tag" style="color: var(--warning);"></i>
                Manage My Roles
            </h3>
            <button onclick="closeRoleModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <p class="text-sm mb-5" style="color: var(--text-secondary);">
            Manage additional roles for <strong id="modalUserName" class="text-primary"></strong>
        </p>
        
        <form id="roleForm">
            @csrf
            @method('PUT')
            <input type="hidden" id="roleUserId" name="user_id">
            
            <div class="space-y-4 mb-6">
                <!-- Landlord Role Toggle -->
                <div class="flex items-center justify-between p-4 rounded-xl transition-all duration-200"
                     style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.15);">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--success-rgb), 0.15);">
                            <i class="fas fa-home text-lg" style="color: var(--success);"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-sm" style="color: var(--text-primary);">Landlord Role</p>
                            <p class="text-xs" style="color: var(--text-secondary);">Allows property ownership and management</p>
                            <div id="landlordPropertyWarning" class="hidden mt-1 text-xs" style="color: var(--warning);">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                <span id="landlordPropertyCount"></span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" 
                                   id="landlordRoleToggle" 
                                   class="sr-only peer role-toggle"
                                   data-role="landlord">
                            <div class="w-11 h-6 bg-gray-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                        </label>
                    </div>
                </div>
                
                <!-- Admin Role Toggle -->
                <div class="flex items-center justify-between p-4 rounded-xl transition-all duration-200"
                     style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.15);">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--info-rgb), 0.15);">
                            <i class="fas fa-user-shield text-lg" style="color: var(--info);"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-sm" style="color: var(--text-primary);">Admin Role</p>
                            <p class="text-xs" style="color: var(--text-secondary);">Grants access to admin panel and user management</p>
                        </div>
                    </div>
                    <div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" 
                                   id="adminRoleToggle" 
                                   class="sr-only peer role-toggle"
                                   data-role="admin">
                            <div class="w-11 h-6 bg-gray-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                        </label>
                    </div>
                </div>
            </div>
            
            <div id="roleWarningMessage" class="hidden mb-5 p-3 rounded-lg text-sm"
                 style="background-color: rgba(var(--danger-rgb), 0.1); border-left: 3px solid var(--danger);">
                <i class="fas fa-exclamation-triangle mr-2" style="color: var(--danger);"></i>
                <span id="roleWarningText"></span>
            </div>
            
            <div class="flex justify-end gap-3 pt-3 border-t" style="border-color: var(--border-color);">
                <button type="button" onclick="closeRoleModal()" 
                        class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    Cancel
                </button>
                <button type="submit" 
                        id="roleSubmitBtn"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200"
                        style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                    <i class="fas fa-save mr-2"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<style>
/* Form control styles */
.index-custom-dropdown,
.index-custom-input {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    transition: all 0.3s ease;
}

.index-custom-dropdown:focus,
.index-custom-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.index-custom-dropdown {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 2.5rem;
}

/* Status indicator */
.status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-indicator::before {
    content: '';
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    display: inline-block;
}

.status-active { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); }
.status-active::before { background-color: var(--success); }

.status-pending { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); }
.status-pending::before { background-color: var(--warning); }

.status-suspended { background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); }
.status-suspended::before { background-color: var(--danger); }

.status-inactive { background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); }
.status-inactive::before { background-color: var(--secondary); }

/* Button styles */
.btn-primary {
    background-color: var(--primary);
    color: white;
    border: 1px solid var(--primary);
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background-color: var(--secondary);
    border-color: var(--secondary);
    transform: translateY(-1px);
}

.btn-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1);
    color: var(--secondary);
    border: 1px solid rgba(var(--secondary-rgb), 0.3);
    transition: all 0.2s ease;
}

.btn-secondary:hover {
    background-color: rgba(var(--secondary-rgb), 0.2);
    transform: translateY(-1px);
}

.btn-success {
    background-color: var(--success);
    color: white;
    border: 1px solid var(--success);
    transition: all 0.2s ease;
}

.btn-success:hover {
    background-color: #28a745;
    transform: translateY(-1px);
}

.btn-info {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
    border: 1px solid rgba(var(--info-rgb), 0.3);
    transition: all 0.2s ease;
}

.btn-info:hover {
    background-color: rgba(var(--info-rgb), 0.2);
    transform: translateY(-1px);
}

.btn-warning {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
    border: 1px solid rgba(var(--warning-rgb), 0.3);
    transition: all 0.2s ease;
}

.btn-warning:hover {
    background-color: rgba(var(--warning-rgb), 0.2);
    transform: translateY(-1px);
}

/* Remove hover effect from table rows */
tbody tr {
    transition: none !important;
}

/* Modal animation */
#roleModal {
    transition: opacity 0.2s ease;
}

/* Toggle switch animation */
.peer:checked + div {
    background-color: #22c55e !important;
}

.peer:checked + div::after {
    transform: translateX(100%);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid.grid-cols-1.md\\:grid-cols-6 {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .card .overflow-x-auto {
        overflow-x: auto;
    }
}
</style>

@if(auth()->user()->isSuperAdmin())
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
let currentModalUserId = null;
let currentModalUserName = null;
let currentLandlordState = false;
let currentAdminState = false;
let currentPropertyCount = 0;

function openRoleModal(userId, userName, hasLandlordRole, propertyCount, hasAdminRole) {
    currentModalUserId = userId;
    currentModalUserName = userName;
    currentLandlordState = hasLandlordRole;
    currentAdminState = hasAdminRole;
    currentPropertyCount = propertyCount;
    
    document.getElementById('modalUserName').textContent = userName;
    document.getElementById('roleUserId').value = userId;
    
    // Set toggle states
    document.getElementById('landlordRoleToggle').checked = hasLandlordRole;
    document.getElementById('adminRoleToggle').checked = hasAdminRole;
    
    // Handle landlord role warning if properties exist
    const landlordWarning = document.getElementById('landlordPropertyWarning');
    const landlordPropertyCountSpan = document.getElementById('landlordPropertyCount');
    
    if (propertyCount > 0 && hasLandlordRole) {
        landlordWarning.classList.remove('hidden');
        landlordPropertyCountSpan.textContent = `You own ${propertyCount} property(s). Cannot remove Landlord role while owning properties.`;
        document.getElementById('landlordRoleToggle').disabled = true;
        document.getElementById('landlordRoleToggle').closest('label').style.opacity = '0.5';
        document.getElementById('landlordRoleToggle').closest('label').style.cursor = 'not-allowed';
    } else if (propertyCount > 0 && !hasLandlordRole) {
        landlordWarning.classList.remove('hidden');
        landlordPropertyCountSpan.textContent = `You own ${propertyCount} property(s). Add Landlord role to manage them properly.`;
        document.getElementById('landlordRoleToggle').disabled = false;
        document.getElementById('landlordRoleToggle').closest('label').style.opacity = '1';
        document.getElementById('landlordRoleToggle').closest('label').style.cursor = 'pointer';
    } else {
        landlordWarning.classList.add('hidden');
        document.getElementById('landlordRoleToggle').disabled = false;
        document.getElementById('landlordRoleToggle').closest('label').style.opacity = '1';
        document.getElementById('landlordRoleToggle').closest('label').style.cursor = 'pointer';
    }
    
    document.getElementById('roleModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeRoleModal() {
    document.getElementById('roleModal').classList.add('hidden');
    document.body.style.overflow = '';
    currentModalUserId = null;
    
    // Clear warning
    document.getElementById('roleWarningMessage').classList.add('hidden');
}

document.getElementById('roleForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    if (!currentModalUserId) {
        showRoleNotification('No user selected.', 'error');
        return;
    }
    
    const newLandlordState = document.getElementById('landlordRoleToggle').checked;
    const newAdminState = document.getElementById('adminRoleToggle').checked;
    
    // Build roles array based on toggles
    const roles = [];
    if (newLandlordState) roles.push('landlord');
    if (newAdminState) roles.push('admin');
    
    // Check if there are any changes
    const currentRoles = [];
    if (currentLandlordState) currentRoles.push('landlord');
    if (currentAdminState) currentRoles.push('admin');
    
    const rolesChanged = JSON.stringify(roles.sort()) !== JSON.stringify(currentRoles.sort());
    
    if (!rolesChanged) {
        showRoleNotification('No changes to save.', 'info');
        setTimeout(() => closeRoleModal(), 1000);
        return;
    }
    
    const submitBtn = document.getElementById('roleSubmitBtn');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
    submitBtn.disabled = true;
    
    // Build form data for the standard user roles endpoint
    const formData = new FormData();
    roles.forEach(role => {
        // Get role ID from the roles table
        formData.append('roles[]', getRoleIdBySlug(role));
    });
    formData.append('_method', 'PUT');
    formData.append('_token', csrfToken);
    
    try {
        const response = await fetch(`/admin/users/${currentModalUserId}/update-roles`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });
        
        // Get response as text first to handle potential parsing issues
        const responseText = await response.text();
        console.log('Update Roles Response:', responseText);
        
        // Check for BOM
        let cleanText = responseText;
        if (cleanText.startsWith('\ufeff')) {
            cleanText = cleanText.replace(/^\uFEFF/, '');
        }
        
        let data;
        try {
            data = JSON.parse(cleanText);
        } catch (e) {
            console.error('Failed to parse response:', e);
            throw new Error('Invalid response from server');
        }
        
        if (data.success) {
            showRoleNotification('Roles updated successfully!', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            throw new Error(data.message || 'Failed to update roles');
        }
    } catch (error) {
        console.error('Error updating roles:', error);
        showRoleNotification(error.message || 'Failed to update roles', 'error');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
});

// Helper function to get role ID by slug
function getRoleIdBySlug(slug) {
    const roleMap = {
        'super-admin': 1,
        'admin': 2,
        'landlord': 3,
        'field-agent': 4,
        'tenant': 5,
        'security-personnel': 6
    };
    return roleMap[slug] || null;
}

function showRoleNotification(message, type = 'success') {
    const existingNotifications = document.querySelectorAll('.role-notification');
    existingNotifications.forEach(n => n.remove());
    
    const notification = document.createElement('div');
    notification.className = `role-notification fixed top-20 right-4 z-50 px-5 py-3 rounded-lg shadow-xl transform transition-all duration-300 ${
        type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500'
    } text-white`;
    notification.style.animation = 'slideInRight 0.3s ease-out';
    notification.style.minWidth = '300px';
    notification.innerHTML = `
        <div class="flex items-center gap-3">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} text-lg"></i>
            <span class="text-sm">${escapeHtml(message)}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-auto text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
        }
    }, 4000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Add CSS animations if not present
if (!document.querySelector('#role-styles')) {
    const style = document.createElement('style');
    style.id = 'role-styles';
    style.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .role-notification {
            z-index: 9999;
        }
        
        #roleModal {
            backdrop-filter: blur(4px);
        }
    `;
    document.head.appendChild(style);
}
</script>
@endif

@endsection