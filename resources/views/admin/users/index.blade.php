@extends('layouts.app')

@section('title', 'User Management')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">User Management</h2>
                <p class="mt-1 text-sm" style="color: var(--text-secondary);">
                    Manage all users except developers. Create, edit, and monitor user accounts.
                </p>
                <div class="mt-3">
                    @php
                        $isDeveloper = auth()->user()->isDeveloper();
                        $isSuperAdmin = auth()->user()->isSuperAdmin();
                        if ($isDeveloper) {
                            $superAdminRoute = route('developer.super-admins.index');
                            $linkText = 'Manage Super Admins';
                        } elseif ($isSuperAdmin) {
                            $superAdminRoute = route('super-admin.super-admins.index');
                            $linkText = 'View Super Admins';
                        } else {
                            $superAdminRoute = route('developer.super-admins.index');
                            $linkText = 'Manage Super Admins';
                        }
                    @endphp
                    <a href="{{ $superAdminRoute }}"
                       class="inline-flex items-center text-sm font-medium transition-colors hover:opacity-80"
                       style="color: var(--primary);">
                        <i class="fas fa-user-shield mr-2"></i>
                        {{ $linkText }}
                        <i class="fas fa-arrow-right ml-2 text-xs"></i>
                    </a>
                </div>
            </div>
            <div class="flex items-center space-x-3 flex-wrap gap-2">
                @if(isset($stats['property_owners']) && $stats['property_owners'] > 0)
                <a href="{{ route('admin.users.property-owners') }}"
                   class="btn-success flex items-center px-3 py-2 rounded-lg text-sm"
                   title="View Property Owners (Landlords with properties)">
                    <i class="fas fa-building mr-2"></i> Property Owners
                    <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-white text-green-800">
                        {{ $stats['property_owners'] }}
                    </span>
                </a>
                @endif

                <a href="{{ route('admin.users.archived') }}"
                   class="btn-secondary flex items-center px-3 py-2 rounded-lg text-sm"
                   style="background-color: #6c757d; color: white;"
                   title="View Archived Users">
                    <i class="fas fa-archive mr-2"></i> Archived Users
                    @if(isset($stats['archived_users']) && $stats['archived_users'] > 0)
                    <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-dark text-white">
                        {{ $stats['archived_users'] }}
                    </span>
                    @endif
                </a>

                <button type="button" onclick="showExportModal()"
                   class="btn-success flex items-center px-3 py-2 rounded-lg text-sm"
                   title="Export Users">
                    <i class="fas fa-file-export mr-2"></i> Export
                </button>

                @if(isset($stats['deleted_users']) && $stats['deleted_users'] > 0)
                <a href="{{ route('admin.users.trash') }}"
                   class="btn-warning flex items-center px-3 py-2 rounded-lg text-sm"
                   title="View Deleted Users">
                    <i class="fas fa-trash-alt mr-2"></i> Deleted Users
                    <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-red-500 text-white">
                        {{ $stats['deleted_users'] }}
                    </span>
                </a>
                @endif

                <a href="{{ route('admin.users.create') }}" class="btn-primary flex items-center px-3 py-2 rounded-lg text-sm">
                    <i class="fas fa-plus mr-2"></i> Add User
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    @php
        $hasDeletedUsers = isset($stats['deleted_users']) && $stats['deleted_users'] > 0;
        $hasLandlordBreakdown = isset($stats['landlord_breakdown']['by_type']) && (
            $stats['landlord_breakdown']['by_type']['admin'] > 0 ||
            $stats['landlord_breakdown']['by_type']['super_admin'] > 0 ||
            $stats['landlord_breakdown']['by_type']['field_agent'] > 0 ||
            $stats['landlord_breakdown']['by_type']['security_personnel'] > 0
        );
        $cardColumns = $hasDeletedUsers ? 'md:grid-cols-2 lg:grid-cols-6' : 'md:grid-cols-2 lg:grid-cols-5';
    @endphp

    <div class="grid grid-cols-1 {{ $cardColumns }} gap-6">
        <!-- Total Users -->
        <div class="card p-6">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Total Users</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ $stats['total_users'] ?? 0 }}</h3>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        {{ $stats['active_users'] ?? 0 }} active
                    </p>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-users text-lg" style="color: var(--primary);"></i>
                </div>
            </div>
        </div>

        <!-- Field Agents -->
        <div class="card p-6">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Field Agents</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ $stats['total_field_agents'] ?? 0 }}</h3>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        {{ $stats['active_field_agents'] ?? 0 }} active, {{ $stats['pending_field_agents'] ?? 0 }} pending
                    </p>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-user-check text-lg" style="color: var(--info);"></i>
                </div>
            </div>
        </div>

        <!-- Landlords Card -->
        <div class="card p-6">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Landlords (All)</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ $stats['total_landlords_by_role'] ?? 0 }}</h3>
                    @if(isset($stats['landlords_without_properties']) && $stats['landlords_without_properties'] > 0)
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        {{ $stats['landlords_without_properties'] }} without properties
                    </p>
                    @endif
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-home text-lg" style="color: var(--success);"></i>
                </div>
            </div>
        </div>

        <!-- Property Owners Card -->
        <div class="card p-6">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Property Owners</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ $stats['property_owners'] ?? 0 }}</h3>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        Landlords with active properties
                    </p>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(40, 167, 69, 0.1);">
                    <i class="fas fa-building text-lg" style="color: #28a745;"></i>
                </div>
            </div>
        </div>

        <!-- Archived Users -->
        <div class="card p-6">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Archived Users</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ $stats['archived_users'] ?? 0 }}</h3>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        {{ $stats['former_landlords'] ?? 0 }} former landlords
                    </p>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(108, 117, 125, 0.1);">
                    <i class="fas fa-archive text-lg" style="color: #6c757d;"></i>
                </div>
            </div>
        </div>

        <!-- Trash/Deleted Users Card -->
        @if($hasDeletedUsers)
        <div class="card p-6 hover:shadow-lg transition-shadow">
            <a href="{{ route('admin.users.trash') }}" class="block">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm font-medium flex items-center" style="color: var(--text-secondary);">
                            <i class="fas fa-trash-alt mr-1 text-xs"></i> Deleted Users (Trash)
                        </p>
                        <h3 class="text-2xl font-bold mt-1" style="color: var(--danger);">
                            {{ $stats['deleted_users'] ?? 0 }}
                        </h3>
                        @php
                            $oldestDeletedDays = $stats['oldest_deleted_days'] ?? null;
                        @endphp
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            @if($oldestDeletedDays)
                                <i class="fas fa-clock mr-1"></i> Oldest: {{ $oldestDeletedDays }} day{{ $oldestDeletedDays !== 1 ? 's' : '' }} ago
                            @else
                                <i class="fas fa-trash-alt mr-1"></i> In trash bin
                            @endif
                        </p>
                        @if(($stats['old_deleted_users_count'] ?? 0) > 0)
                            <p class="text-xs mt-1" style="color: var(--danger);">
                                <i class="fas fa-exclamation-triangle mr-1"></i> {{ $stats['old_deleted_users_count'] }} over 30 days old
                            </p>
                        @endif
                    </div>
                    <div class="p-3 rounded-full" style="background-color: rgba(var(--danger-rgb), 0.1);">
                        <i class="fas fa-trash-alt text-lg" style="color: var(--danger);"></i>
                    </div>
                </div>
                <div class="mt-3 pt-2 border-t text-xs" style="border-color: var(--border-color); color: var(--primary);">
                    <span class="flex items-center justify-end">
                        View trash <i class="fas fa-arrow-right ml-1"></i>
                    </span>
                </div>
            </a>
        </div>
        @endif
    </div>

    <!-- Old Deleted Users Warning Banner -->
    @if(isset($stats['old_deleted_users_count']) && $stats['old_deleted_users_count'] > 0)
    <div class="alert-warning p-4 rounded-lg flex items-center justify-between flex-wrap gap-3"
         style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
        <div class="flex items-center">
            <i class="fas fa-exclamation-triangle text-xl mr-3" style="color: var(--warning);"></i>
            <div>
                <p class="font-medium" style="color: var(--text-primary);">
                    {{ $stats['old_deleted_users_count'] }} user(s) have been deleted for over 30 days
                </p>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    These users are eligible for permanent deletion to free up storage.
                </p>
            </div>
        </div>
        <div class="flex space-x-3">
            <a href="{{ route('admin.users.trash') }}?filter=old" class="btn-warning px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-eye mr-2"></i> View Old Deletions
            </a>
            <button type="button" onclick="promptEmptyOldTrash()" class="btn-danger px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-trash-alt mr-2"></i> Clean Old Trash
            </button>
        </div>
    </div>
    @endif

    <!-- Landlord Breakdown Card -->
    @if($hasLandlordBreakdown)
    <div class="card p-6">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium" style="color: var(--text-secondary);">Landlords by User Type</p>
                <div class="mt-3 grid grid-cols-2 md:grid-cols-3 gap-3">
                    @if(isset($stats['landlord_breakdown']['by_type']['super_admin']) && $stats['landlord_breakdown']['by_type']['super_admin'] > 0)
                    <div class="flex items-center justify-between p-2 rounded-lg" style="background-color: rgba(111, 66, 193, 0.1);">
                        <div class="flex items-center">
                            <i class="fas fa-crown mr-2 text-purple-600"></i>
                            <span class="text-xs font-medium" style="color: var(--text-primary);">Super Admins</span>
                        </div>
                        <span class="text-sm font-bold text-purple-600">{{ $stats['landlord_breakdown']['by_type']['super_admin'] }}</span>
                    </div>
                    @endif

                    @if(isset($stats['landlord_breakdown']['by_type']['admin']) && $stats['landlord_breakdown']['by_type']['admin'] > 0)
                    <div class="flex items-center justify-between p-2 rounded-lg" style="background-color: rgba(59, 130, 246, 0.1);">
                        <div class="flex items-center">
                            <i class="fas fa-user-shield mr-2 text-blue-600"></i>
                            <span class="text-xs font-medium" style="color: var(--text-primary);">Admins</span>
                        </div>
                        <span class="text-sm font-bold text-blue-600">{{ $stats['landlord_breakdown']['by_type']['admin'] }}</span>
                    </div>
                    @endif

                    @if(isset($stats['landlord_breakdown']['by_type']['field_agent']) && $stats['landlord_breakdown']['by_type']['field_agent'] > 0)
                    <div class="flex items-center justify-between p-2 rounded-lg" style="background-color: rgba(6, 182, 212, 0.1);">
                        <div class="flex items-center">
                            <i class="fas fa-user-check mr-2 text-cyan-600"></i>
                            <span class="text-xs font-medium" style="color: var(--text-primary);">Field Agents</span>
                        </div>
                        <span class="text-sm font-bold text-cyan-600">{{ $stats['landlord_breakdown']['by_type']['field_agent'] }}</span>
                    </div>
                    @endif

                    @if(isset($stats['landlord_breakdown']['by_type']['security_personnel']) && $stats['landlord_breakdown']['by_type']['security_personnel'] > 0)
                    <div class="flex items-center justify-between p-2 rounded-lg" style="background-color: rgba(249, 115, 22, 0.1);">
                        <div class="flex items-center">
                            <i class="fas fa-shield-alt mr-2 text-orange-600"></i>
                            <span class="text-xs font-medium" style="color: var(--text-primary);">Security Personnel</span>
                        </div>
                        <span class="text-sm font-bold text-orange-600">{{ $stats['landlord_breakdown']['by_type']['security_personnel'] }}</span>
                    </div>
                    @endif

                    @if(isset($stats['landlord_breakdown']['by_type']['landlord']) && $stats['landlord_breakdown']['by_type']['landlord'] > 0)
                    <div class="flex items-center justify-between p-2 rounded-lg" style="background-color: rgba(34, 197, 94, 0.1);">
                        <div class="flex items-center">
                            <i class="fas fa-home mr-2 text-green-600"></i>
                            <span class="text-xs font-medium" style="color: var(--text-primary);">Landlords (Type)</span>
                        </div>
                        <span class="text-sm font-bold text-green-600">{{ $stats['landlord_breakdown']['by_type']['landlord'] }}</span>
                    </div>
                    @endif

                    @if(isset($stats['landlord_breakdown']['by_type']['tenant']) && $stats['landlord_breakdown']['by_type']['tenant'] > 0)
                    <div class="flex items-center justify-between p-2 rounded-lg" style="background-color: rgba(234, 179, 8, 0.1);">
                        <div class="flex items-center">
                            <i class="fas fa-user-friends mr-2 text-yellow-600"></i>
                            <span class="text-xs font-medium" style="color: var(--text-primary);">Tenants</span>
                        </div>
                        <span class="text-sm font-bold text-yellow-600">{{ $stats['landlord_breakdown']['by_type']['tenant'] }}</span>
                    </div>
                    @endif
                </div>
                <p class="text-xs mt-3" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1"></i>
                    Users who have been granted landlord role (including multi-role users)
                </p>
            </div>
            <div class="p-3 rounded-full" style="background-color: rgba(111, 66, 193, 0.1);">
                <i class="fas fa-chart-pie text-lg" style="color: #6f42c1;"></i>
            </div>
        </div>
    </div>
    @endif

    <!-- Filters Card -->
    <div class="card p-6">
        <form action="{{ route('admin.users.index') }}" method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Search</label>
                <input type="text" name="search" value="{{ request('search') }}"
                       class="w-full p-2 border rounded"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                       placeholder="Name, email, phone...">
            </div>

            <div>
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">User Type</label>
                <select name="type" class="w-full p-2 border rounded"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="all">All Types</option>
                    @foreach($userTypes as $value => $label)
                        <option value="{{ $value }}" {{ request('type', '') == $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Role</label>
                <select name="role" class="w-full p-2 border rounded"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="all">All Roles</option>
                    @foreach($availableRoles as $role)
                        <option value="{{ $role->slug }}" {{ request('role', '') == $role->slug ? 'selected' : '' }}>
                            {{ $role->display_name ?? $role->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Status</label>
                <select name="status" class="w-full p-2 border rounded"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="all">All Statuses</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" {{ request('status', '') == $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Multi-Role</label>
                <select name="multi_role" class="w-full p-2 border rounded"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="all">All Users</option>
                    <option value="yes" {{ request('multi_role', '') == 'yes' ? 'selected' : '' }}>Multi-Role Users</option>
                </select>
            </div>

            <div class="md:col-span-5 flex justify-between items-center pt-4 border-t" style="border-color: var(--border-color);">
                <div class="text-sm" style="color: var(--text-secondary);">
                    {{ $users->total() }} users found
                </div>
                <div class="flex space-x-3">
                    <a href="{{ route('admin.users.index') }}" class="btn-secondary px-4 py-2 rounded-lg text-sm">
                        Clear Filters
                    </a>
                    <button type="submit" class="btn-primary px-4 py-2 rounded-lg text-sm">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="card p-6">
        <div class="overflow-x-auto table-responsive">
            <table class="w-full">
                <thead>
                    <tr style="border-bottom-color: var(--border-color);">
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">
                            <input type="checkbox" id="select-all" class="rounded border-gray-300">
                        </th>
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">User</th>
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">Type</th>
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">Roles</th>
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">Status</th>
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">Phone</th>
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">Created</th>
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">Invitation</th>
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    @php
                        $isLandlordWithoutProperties = false;
                        $showArchiveIcon = false;

                        if ($user->type === \App\Models\User::TYPE_LANDLORD && !$user->isArchived()) {
                            $propertyCount = $user->property_count ?? $user->properties()->count();
                            if ($propertyCount == 0) {
                                $isLandlordWithoutProperties = true;
                                $showArchiveIcon = true;
                            }
                        }

                        $isSuperAdminUser = $user->hasRole('super-admin') || $user->type === \App\Models\User::TYPE_SUPER_ADMIN;
                        $isCurrentUser = $user->id === auth()->id();

                        $hasAcceptedInvitation = false;
                        $acceptanceDate = null;
                        $hasInvitationSent = false;
                        $sentDate = null;

                        if (!is_null($user->invitation_accepted_at)) {
                            $hasAcceptedInvitation = true;
                            $acceptanceDate = $user->invitation_accepted_at;
                        }

                        if (!$hasAcceptedInvitation && $user->invitations()->where('status', 'accepted')->exists()) {
                            $hasAcceptedInvitation = true;
                            $acceptedInvitation = $user->invitations()->where('status', 'accepted')->latest()->first();
                            $acceptanceDate = $acceptedInvitation->accepted_at;
                        }

                        if (!$hasAcceptedInvitation && $user->status === \App\Models\User::STATUS_ACTIVE) {
                            $hasAcceptedInvitation = true;
                        }

                        if (!is_null($user->invitation_sent_at)) {
                            $hasInvitationSent = true;
                            $sentDate = $user->invitation_sent_at;
                        } elseif ($user->invitations()->whereIn('status', ['sent', 'pending'])->exists()) {
                            $hasInvitationSent = true;
                            $latestInvite = $user->invitations()->whereIn('status', ['sent', 'pending'])->latest()->first();
                            $sentDate = $latestInvite->sent_at;
                        }

                        $canReceiveInvitation = !$user->isArchived() &&
                            !$hasAcceptedInvitation &&
                            $user->status !== \App\Models\User::STATUS_ACTIVE &&
                            in_array($user->type, [
                                \App\Models\User::TYPE_FIELD_AGENT,
                                \App\Models\User::TYPE_LANDLORD,
                                \App\Models\User::TYPE_TENANT,
                                \App\Models\User::TYPE_SECURITY_PERSONNEL
                            ]);

                        $canResendInvitation = !$user->isArchived() &&
                            $hasInvitationSent &&
                            !$hasAcceptedInvitation;
                    @endphp
                    <tr class="border-b {{ $isLandlordWithoutProperties ? 'landlord-no-properties-row' : '' }}"
                        style="border-bottom-color: var(--border-color); background-color: var(--bg-secondary);">
                        <td class="py-4 px-4">
                            <input type="checkbox" name="user_ids[]" value="{{ $user->id }}"
                                   class="user-checkbox rounded border-gray-300"
                                   data-user-name="{{ $user->name }}"
                                   data-user-type="{{ $user->type }}"
                                   {{ $isSuperAdminUser && !$isCurrentUser ? 'disabled' : '' }}>
                            @if($isSuperAdminUser && !$isCurrentUser)
                            <span class="text-xs text-gray-400 block mt-1" title="Super Admin users cannot be selected for bulk actions">Super Admin</span>
                            @endif
                        </td>

                        <td class="py-4 px-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 w-10 h-10">
                                    @if($user->has_photo)
                                        @php
                                            $borderColor = $user->isArchived() ? '#6c757d' : 'var(--primary)';
                                            if (!$user->isArchived()) {
                                                if ($user->type === \App\Models\User::TYPE_FIELD_AGENT) {
                                                    $borderColor = 'var(--info)';
                                                } elseif ($user->type === \App\Models\User::TYPE_LANDLORD) {
                                                    $borderColor = 'var(--success)';
                                                } elseif ($user->type === \App\Models\User::TYPE_TENANT) {
                                                    $borderColor = 'var(--warning)';
                                                } elseif ($user->type === \App\Models\User::TYPE_SECURITY_PERSONNEL) {
                                                    $borderColor = 'var(--orange)';
                                                }
                                            }
                                        @endphp
                                        <img src="{{ $user->avatar_url }}"
                                             alt="{{ $user->name }}"
                                             class="w-10 h-10 rounded-full object-cover border-2 cursor-pointer user-photo-zoom lazy-load-photo"
                                             style="border-color: {{ $borderColor }};"
                                             data-user-id="{{ $user->id }}"
                                             data-user-name="{{ $user->name }}"
                                             data-photo-url="{{ $user->avatar_url }}"
                                             onclick="zoomUserPhoto(this)">
                                    @else
                                        @php
                                            $bgColor = $user->isArchived() ? '#6c757d' : 'var(--primary)';
                                            if (!$user->isArchived()) {
                                                if ($user->type === \App\Models\User::TYPE_FIELD_AGENT) {
                                                    $bgColor = 'var(--info)';
                                                } elseif ($user->type === \App\Models\User::TYPE_LANDLORD) {
                                                    $bgColor = 'var(--success)';
                                                } elseif ($user->type === \App\Models\User::TYPE_TENANT) {
                                                    $bgColor = 'var(--warning)';
                                                } elseif ($user->type === \App\Models\User::TYPE_SECURITY_PERSONNEL) {
                                                    $bgColor = 'var(--orange)';
                                                }
                                            }
                                        @endphp
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center font-semibold text-white text-sm user-avatar"
                                             style="background-color: {{ $bgColor }};"
                                             onclick="showAvatarInfo('{{ $user->name }}', '{{ $user->initials }}')">
                                            {{ $user->initials }}
                                        </div>
                                    @endif
                                </div>
                                <div class="ml-4">
                                    <div class="font-medium flex items-center flex-wrap gap-1" style="color: var(--text-primary);">
                                        {{ $user->name }}
                                        @if($user->has_photo)
                                            <i class="fas fa-camera ml-2 text-xs opacity-60" title="Has profile photo"></i>
                                        @endif
                                        @if($user->id === auth()->id())
                                            <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800">
                                                You
                                            </span>
                                        @endif
                                        @if($user->isArchived())
                                            <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-500 text-white archival-badge">
                                                <i class="fas fa-archive mr-1"></i> Archived
                                            </span>
                                        @endif
                                        @if($showArchiveIcon)
                                            <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-warning text-dark warning-badge"
                                                  title="Landlord without properties - Eligible for archival"
                                                  style="background-color: #ffc107; color: #856404;">
                                                <i class="fas fa-exclamation-triangle mr-1"></i> No Properties
                                            </span>
                                        @endif
                                        @if($isSuperAdminUser)
                                            <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-purple-100 text-purple-800">
                                                <i class="fas fa-user-shield mr-1"></i> Super Admin
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-sm" style="color: var(--text-secondary);">{{ $user->email }}</div>
                                    @if($user->username)
                                    <div class="text-xs font-mono" style="color: var(--text-secondary);">@ {{ $user->username }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>

                        <td class="py-4 px-4">
                           @php
                            $typeClass = 'bg-gray-100 text-gray-800';
                            $typeIcon = 'user';
                            $typeName = 'Unknown';

                            if ($user->isArchived()) {
                                $typeClass = 'bg-gray-300 text-gray-700';
                                $typeIcon = 'archive';
                                $typeName = 'Archived';
                            } else {
                                switch ($user->type) {
                                    case \App\Models\User::TYPE_SUPER_ADMIN:
                                        $typeClass = 'bg-purple-100 text-purple-800';
                                        $typeIcon = 'user-shield';
                                        $typeName = 'Super Admin';
                                        break;
                                    case \App\Models\User::TYPE_ADMIN:
                                        $typeClass = 'bg-blue-100 text-blue-800';
                                        $typeIcon = 'user-shield';
                                        $typeName = 'Admin';
                                        break;
                                    case \App\Models\User::TYPE_FIELD_AGENT:
                                        $typeClass = 'bg-cyan-100 text-cyan-800';
                                        $typeIcon = 'user-check';
                                        $typeName = 'Field Agent';
                                        break;
                                    case \App\Models\User::TYPE_LANDLORD:
                                        $typeClass = 'bg-green-100 text-green-800';
                                        $typeIcon = 'home';
                                        $typeName = 'Landlord';
                                        break;
                                    case \App\Models\User::TYPE_TENANT:
                                        $typeClass = 'bg-yellow-100 text-yellow-800';
                                        $typeIcon = 'user-friends';
                                        $typeName = 'Tenant';
                                        break;
                                    case \App\Models\User::TYPE_SECURITY_PERSONNEL:
                                        $typeClass = 'bg-orange-100 text-orange-800';
                                        $typeIcon = 'shield-alt';
                                        $typeName = 'Security Personnel';
                                        break;
                                    case \App\Models\User::TYPE_CONTRACTOR:
                                        $typeClass = 'bg-indigo-100 text-indigo-800';
                                        $typeIcon = 'hard-hat';
                                        $typeName = 'Contractor';
                                        break;
                                    case \App\Models\User::TYPE_SANITATION_PERSONNEL:
                                        $typeClass = 'bg-teal-100 text-teal-800';
                                        $typeIcon = 'trash-alt';
                                        $typeName = 'Sanitation Personnel';
                                        break;
                                }
                            }
                        @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $typeClass }} user-type-badge">
                                <i class="fas fa-{{ $typeIcon }} mr-1"></i>
                                <span class="type-label">{{ $typeName }}</span>
                            </span>
                        </td>

                        <td class="py-4 px-4">
                            <div class="space-y-1">
                                <div class="flex flex-wrap gap-1">
                                    @forelse($user->roles as $role)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                            @if($role->slug === 'landlord') bg-green-100 text-green-800
                                            @elseif($role->slug === 'admin') bg-blue-100 text-blue-800
                                            @elseif($role->slug === 'super-admin') bg-purple-100 text-purple-800
                                            @elseif($role->slug === 'field-agent') bg-cyan-100 text-cyan-800
                                            @elseif($role->slug === 'security-personnel') bg-orange-100 text-orange-800
                                            @elseif($role->slug === 'tenant') bg-yellow-100 text-yellow-800
                                            @else bg-gray-100 text-gray-800
                                            @endif">
                                            {{ $role->display_name ?? $role->name }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-gray-400">No roles assigned</span>
                                    @endforelse
                                </div>

                                @if($user->is_property_owner)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                        <i class="fas fa-building mr-1"></i> {{ $user->property_count }} Properties
                                    </span>
                                @endif

                                @if(isset($user->is_multi_role_user) && $user->is_multi_role_user)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                        <i class="fas fa-tags mr-1"></i> Multi-Role
                                    </span>
                                @endif
                            </div>
                        </td>

                        <td class="py-4 px-4">
                            @php
                                $statusClass = 'bg-gray-100 text-gray-800';
                                $statusIcon = 'question-circle';
                                $statusLabel = 'Unknown';

                                if ($user->isArchived()) {
                                    $statusClass = 'bg-gray-400 text-white';
                                    $statusIcon = 'archive';
                                    $statusLabel = 'Archived';
                                } else {
                                    switch ($user->status) {
                                        case \App\Models\User::STATUS_PENDING:
                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                            $statusIcon = 'clock';
                                            $statusLabel = 'Pending';
                                            break;
                                        case \App\Models\User::STATUS_ACTIVE:
                                            $statusClass = 'bg-green-100 text-green-800';
                                            $statusIcon = 'check-circle';
                                            $statusLabel = 'Active';
                                            break;
                                        case \App\Models\User::STATUS_SUSPENDED:
                                            $statusClass = 'bg-red-100 text-red-800';
                                            $statusIcon = 'ban';
                                            $statusLabel = 'Suspended';
                                            break;
                                        case \App\Models\User::STATUS_INACTIVE:
                                            $statusClass = 'bg-gray-100 text-gray-800';
                                            $statusIcon = 'minus-circle';
                                            $statusLabel = 'Inactive';
                                            break;
                                        case \App\Models\User::STATUS_VERIFICATION_REQUIRED:
                                            $statusClass = 'bg-blue-100 text-blue-800';
                                            $statusIcon = 'shield-alt';
                                            $statusLabel = 'Verification Required';
                                            break;
                                    }
                                }
                            @endphp
                            <div class="user-status-container" data-user-id="{{ $user->id }}">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }} user-status-badge">
                                    <i class="fas fa-{{ $statusIcon }} mr-1"></i>
                                    <span class="status-label">{{ $statusLabel }}</span>
                                </span>

                                @if($user->type === \App\Models\User::TYPE_FIELD_AGENT && !$user->isArchived())
                                    <div class="text-xs mt-1 space-y-1 field-agent-status">
                                        @if($hasAcceptedInvitation)
                                            <span class="text-green-600 flex items-center">
                                                <i class="fas fa-check-circle mr-1"></i> Invitation accepted
                                            </span>
                                        @elseif($user->status === \App\Models\User::STATUS_PENDING)
                                            <span class="text-yellow-600 flex items-center">
                                                <i class="fas fa-clock mr-1"></i> Invitation pending
                                            </span>
                                        @endif

                                        @if(!is_null($user->phone_verified_at))
                                            <span class="text-blue-600 flex items-center">
                                                <i class="fas fa-shield-alt mr-1"></i> Phone verified
                                            </span>
                                        @else
                                            <span class="text-orange-600 flex items-center">
                                                <i class="fas fa-exclamation-triangle mr-1"></i> Phone not verified
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </td>

                        <td class="py-4 px-4">
                            <div class="flex items-center">
                                @if($user->phone)
                                    <span style="color: var(--text-primary);">
                                        @php
                                            if (preg_match('/^\+233(\d{9})$/', $user->phone, $matches)) {
                                                echo '0' . $matches[1];
                                            } else {
                                                echo $user->phone;
                                            }
                                        @endphp
                                    </span>
                                    @if(!is_null($user->phone_verified_at))
                                        <i class="fas fa-check-circle ml-2 text-success" title="Phone verified"></i>
                                    @else
                                        <i class="fas fa-exclamation-triangle ml-2 text-warning" title="Phone not verified"></i>
                                    @endif
                                @else
                                    <span class="text-muted">Not set</span>
                                @endif
                            </div>
                        </td>

                        <td class="py-4 px-4">
                            <div style="color: var(--text-primary);">{{ $user->created_at->format('M j, Y') }}</div>
                            <div class="text-sm" style="color: var(--text-secondary);">
                                by {{ $user->creator_name ?? 'System' }}
                            </div>
                        </td>

                        <td class="py-4 px-4">
                            @if(!$user->isArchived())
                                @if($hasAcceptedInvitation)
                                    <span class="text-green-600 text-xs flex items-center">
                                        <i class="fas fa-check-circle mr-1"></i> Accepted
                                        @if($acceptanceDate)
                                            <span class="ml-1 text-gray-500">({{ $acceptanceDate instanceof \Carbon\Carbon ? $acceptanceDate->diffForHumans() : \Carbon\Carbon::parse($acceptanceDate)->diffForHumans() }})</span>
                                        @endif
                                    </span>
                                @elseif($hasInvitationSent)
                                    <div class="space-y-1">
                                        <span class="text-yellow-600 text-xs flex items-center">
                                            <i class="fas fa-paper-plane mr-1"></i> Sent
                                            @if($sentDate)
                                                <span class="ml-1 text-gray-500">({{ $sentDate instanceof \Carbon\Carbon ? $sentDate->diffForHumans() : \Carbon\Carbon::parse($sentDate)->diffForHumans() }})</span>
                                            @endif
                                        </span>
                                        @if($canResendInvitation)
                                            <button type="button" onclick="resendInvitation({{ $user->id }}, '{{ addslashes($user->name) }}')"
                                                    class="text-blue-600 text-xs hover:text-blue-800 transition-colors">
                                                <i class="fas fa-redo-alt mr-1"></i> Resend
                                            </button>
                                        @endif
                                    </div>
                                @else
                                    @if($canReceiveInvitation)
                                        <button type="button" onclick="showSendInvitationModal({{ $user->id }}, '{{ addslashes($user->name) }}', '{{ $user->type }}')"
                                                class="text-green-600 text-xs hover:text-green-800 transition-colors">
                                            <i class="fas fa-envelope mr-1"></i> Send Invitation
                                        </button>
                                    @else
                                        <span class="text-gray-400 text-xs">
                                            @if($user->status === \App\Models\User::STATUS_ACTIVE)
                                                <i class="fas fa-check-circle mr-1 text-green-500"></i> Active
                                            @else
                                                Not applicable
                                            @endif
                                        </span>
                                    @endif
                                @endif
                            @else
                                <span class="text-gray-400 text-xs">Archived</span>
                            @endif
                        </td>

                        <td class="py-4 px-4">
                            <div class="flex items-center space-x-2 flex-wrap gap-1 action-buttons">
                                <a href="{{ route('admin.users.show', $user->id) }}"
                                   class="btn-secondary btn-sm inline-flex items-center px-2 py-1 rounded text-xs" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>

                                @if(auth()->user()->isSuperAdmin() && !$user->isArchived() && $user->type !== \App\Models\User::TYPE_DEVELOPER)
                                <button type="button"
                                        class="manage-roles-btn btn-info btn-sm inline-flex items-center px-2 py-1 rounded text-xs"
                                        title="Manage Roles"
                                        data-user-id="{{ $user->id }}"
                                        data-user-name="{{ $user->name }}"
                                        data-current-user-id="{{ auth()->id() }}"
                                        data-user-type="{{ $user->type }}"
                                        data-user-roles='@json($user->roles->pluck('slug'))'
                                        data-has-landlord-role="{{ $user->hasRole('landlord') ? 'true' : 'false' }}"
                                        data-property-count="{{ $user->property_count ?? 0 }}"
                                        data-is-property-owner="{{ $user->is_property_owner ? 'true' : 'false' }}">
                                    <i class="fas fa-user-tag"></i>
                                </button>
                                @endif

                                @if($showArchiveIcon)
                                <button type="button" onclick="showArchiveWarning({{ $user->id }}, '{{ addslashes($user->name) }}', {{ $user->property_count ?? 0 }})"
                                        class="btn-warning btn-sm inline-flex items-center px-2 py-1 rounded text-xs archive-warning-btn"
                                        title="This landlord has no properties and is eligible for archival"
                                        style="background-color: #ffc107; color: #856404;">
                                    <i class="fas fa-archive"></i>
                                    <span class="ml-1 hidden sm:inline">Archive</span>
                                </button>
                                @endif

                                @if($user->isArchived())
                                    <button type="button" onclick="restoreArchivedUser({{ $user->id }}, '{{ addslashes($user->name) }}')"
                                            class="btn-success btn-sm inline-flex items-center px-2 py-1 rounded text-xs" title="Restore Account">
                                        <i class="fas fa-undo-alt"></i>
                                    </button>
                                @endif

                                @if(!$user->isArchived())
                                <div class="relative group">
                                    <button type="button" class="btn-info btn-sm inline-flex items-center px-2 py-1 rounded text-xs" title="Photo Options">
                                        <i class="fas fa-camera"></i>
                                    </button>
                                    <div class="absolute left-0 mt-2 w-48 bg-white rounded-lg shadow-lg border z-10 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200"
                                         style="background-color: var(--bg-primary); border-color: var(--border-color);">
                                        <div class="py-1">
                                            @if($user->has_photo)
                                                <button type="button" onclick="updateUserPhoto({{ $user->id }})"
                                                        class="w-full text-left px-4 py-2 text-sm hover:bg-opacity-10"
                                                        style="color: var(--text-primary);">
                                                    <i class="fas fa-sync-alt mr-2"></i> Update Photo
                                                </button>
                                                <button type="button" onclick="removeUserPhoto({{ $user->id }})"
                                                        class="w-full text-left px-4 py-2 text-sm hover:bg-opacity-10"
                                                        style="color: var(--danger);">
                                                    <i class="fas fa-trash mr-2"></i> Remove Photo
                                                </button>
                                            @else
                                                <button type="button" onclick="updateUserPhoto({{ $user->id }})"
                                                        class="w-full text-left px-4 py-2 text-sm hover:bg-opacity-10"
                                                        style="color: var(--text-primary);">
                                                    <i class="fas fa-plus mr-2"></i> Add Photo
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @endif

                                @if(!$user->isArchived() && $user->id !== auth()->id())
                                    @if($user->status === 'active')
                                        <button type="button" onclick="suspendUser({{ $user->id }})"
                                                class="btn-warning btn-sm inline-flex items-center px-2 py-1 rounded text-xs" title="Suspend User">
                                            <i class="fas fa-pause"></i>
                                        </button>
                                    @elseif($user->status === 'suspended')
                                        <button type="button" onclick="activateUser({{ $user->id }})"
                                                class="btn-success btn-sm inline-flex items-center px-2 py-1 rounded text-xs" title="Activate User">
                                            <i class="fas fa-play"></i>
                                        </button>
                                    @elseif($user->status === 'pending')
                                        <button type="button" onclick="activateUser({{ $user->id }})"
                                                class="btn-success btn-sm inline-flex items-center px-2 py-1 rounded text-xs" title="Activate User">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    @endif

                                    @if(auth()->user()->isSuperAdmin() && !$isSuperAdminUser)
                                    <button type="button" onclick="deleteUser({{ $user->id }}, '{{ addslashes($user->name) }}')"
                                            class="btn-danger btn-sm inline-flex items-center px-2 py-1 rounded text-xs" title="Delete User">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    @endif
                                @elseif($user->id === auth()->id())
                                    <span class="btn-warning btn-sm opacity-50 cursor-not-allowed inline-flex items-center px-2 py-1 rounded text-xs" title="Cannot suspend your own account">
                                        <i class="fas fa-pause"></i>
                                    </span>
                                    @if(auth()->user()->isSuperAdmin())
                                    <span class="btn-danger btn-sm opacity-50 cursor-not-allowed inline-flex items-center px-2 py-1 rounded text-xs" title="Cannot delete your own account">
                                        <i class="fas fa-trash"></i>
                                    </span>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-8 px-4 text-center" style="color: var(--text-secondary);">
                            <i class="fas fa-users text-4xl mb-4 opacity-50"></i>
                            <p class="text-lg">No users found</p>
                            <p class="text-sm mt-2">Try adjusting your search filters or create a new user.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
        <div class="border-t pt-4 mt-4" style="border-color: var(--border-color);">
            {{ $users->links() }}
        </div>
        @endif
    </div>

    <!-- Bulk Actions Card -->
    <div class="card p-6">
        <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Bulk Actions</h3>
            <div class="flex items-center space-x-3">
                <select id="bulk-action" class="p-2 border rounded"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="">Choose Action</option>
                    <option value="activate">Activate Selected</option>
                    <option value="suspend">Suspend Selected</option>
                    <option value="deactivate">Deactivate Selected</option>
                    @if(auth()->user()->isSuperAdmin())
                    <option value="assign_landlord">Assign Landlord Role</option>
                    @endif
                    <option value="send_invitation">Send Invitations</option>
                    @if(auth()->user()->isSuperAdmin())
                    <option value="delete">Delete Selected</option>
                    @endif
                </select>
                <button type="button" onclick="performBulkAction()" class="btn-primary px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-play mr-2"></i> Apply
                </button>
            </div>
        </div>
        <div class="mt-4 text-sm" style="color: var(--text-secondary);">
            <i class="fas fa-info-circle mr-2"></i>
            Select users using the checkboxes and choose an action to perform on multiple users at once.
        </div>
    </div>
</div>

<!-- ==================== MODALS ==================== -->

<!-- Photo Zoom Modal -->
<div id="photoZoomModal" class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-[100] hidden p-4 modal-overlay">
    <div class="relative w-full max-w-4xl max-h-[90vh] flex flex-col items-center modal-content">
        <button type="button" onclick="closePhotoZoomModal()"
                class="absolute -top-12 right-0 text-white hover:text-gray-300 transition-colors z-10 bg-black bg-opacity-50 rounded-full p-2">
            <i class="fas fa-times text-xl"></i>
        </button>

        <button type="button" onclick="navigateZoomPhoto(-1)"
                class="absolute left-4 top-1/2 transform -translate-y-1/2 text-white hover:text-gray-300 transition-colors z-10 bg-black bg-opacity-50 rounded-full p-3">
            <i class="fas fa-chevron-left text-xl"></i>
        </button>
        <button type="button" onclick="navigateZoomPhoto(1)"
                class="absolute right-4 top-1/2 transform -translate-y-1/2 text-white hover:text-gray-300 transition-colors z-10 bg-black bg-opacity-50 rounded-full p-3">
            <i class="fas fa-chevron-right text-xl"></i>
        </button>

        <div class="relative w-full h-full flex items-center justify-center">
            <img id="zoomedPhoto"
                 class="max-w-full max-h-[80vh] object-contain rounded-lg shadow-2xl transition-all duration-300"
                 alt="Zoomed User Photo"
                 loading="lazy">

            <div id="photoZoomLoading" class="absolute inset-0 flex items-center justify-center hidden">
                <div class="spinner-large"></div>
            </div>
        </div>

        <div class="mt-4 text-center text-white">
            <h3 id="zoomedUserName" class="text-xl font-semibold"></h3>
            <div class="flex justify-center space-x-4 mt-3">
                <button type="button" onclick="downloadZoomedPhoto()"
                        class="flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                    <i class="fas fa-download mr-2"></i> Download
                </button>
                <button type="button" onclick="rotateZoomedPhoto()"
                        class="flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg transition-colors">
                    <i class="fas fa-redo mr-2"></i> Rotate
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Avatar Info Modal -->
<div id="avatarInfoModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden modal-overlay">
    <div class="bg-white rounded-lg p-6 w-full max-w-sm modal-content" style="background-color: var(--bg-primary);">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">User Avatar</h3>
        <div class="text-center">
            <div id="avatarInitials" class="w-20 h-20 rounded-full flex items-center justify-center font-semibold text-white text-2xl mx-auto mb-4"
                 style="background-color: var(--primary);">
            </div>
            <p class="text-sm" style="color: var(--text-secondary);">
                <span id="avatarUserName" class="font-semibold" style="color: var(--text-primary);"></span><br>
                This user doesn't have a profile photo yet.
            </p>
        </div>
        <div class="flex justify-end mt-6">
            <button type="button" onclick="closeAvatarInfoModal()" class="btn-primary px-4 py-2 rounded-lg">Close</button>
        </div>
    </div>
</div>

<!-- Send Invitation Modal -->
<div id="sendInvitationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden p-4 modal-overlay">
    <div class="bg-white rounded-lg w-full max-w-lg modal-content" style="background-color: var(--bg-primary);">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Send Invitation</h3>
                <button type="button" onclick="closeSendInvitationModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <form id="sendInvitationForm" method="POST">
            @csrf
            <input type="hidden" id="invitationUserId" name="user_id">

            <div class="p-6 space-y-4">
                <div>
                    <p class="text-sm mb-2" style="color: var(--text-primary);">
                        Send invitation to: <strong id="invitationUserName" class="text-primary"></strong>
                    </p>
                    <p class="text-xs" style="color: var(--text-secondary);">
                        User Type: <span id="invitationUserType"></span>
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Invitation Type</label>
                    <select name="invitation_type" id="invitation_type" class="w-full p-2 border rounded"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="welcome">Welcome Invitation</option>
                        <option value="registration">Registration Invitation</option>
                        <option value="account_setup">Account Setup Invitation</option>
                        <option value="password_setup">Password Setup Invitation</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Send Via Channels</label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="checkbox" name="invitation_channels[]" value="email" checked class="rounded border-gray-300">
                            <span class="ml-2 text-sm">Email</span>
                            @if(isset($communicationStatus['email']['enabled']) && !$communicationStatus['email']['enabled'])
                            <span class="ml-2 text-xs text-red-500">(Not configured)</span>
                            @endif
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="invitation_channels[]" value="sms" class="rounded border-gray-300">
                            <span class="ml-2 text-sm">SMS</span>
                            @if(isset($communicationStatus['sms']['enabled']) && !$communicationStatus['sms']['enabled'])
                            <span class="ml-2 text-xs text-red-500">(Not configured)</span>
                            @endif
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="invitation_channels[]" value="whatsapp" class="rounded border-gray-300">
                            <span class="ml-2 text-sm">WhatsApp</span>
                            @if(isset($communicationStatus['whatsapp']['enabled']) && !$communicationStatus['whatsapp']['enabled'])
                            <span class="ml-2 text-xs text-red-500">(Not configured)</span>
                            @endif
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Custom Message (Optional)</label>
                    <textarea name="custom_message" rows="3" class="w-full p-2 border rounded"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                              placeholder="Add a personal message to the invitation..."></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Expires In (Days)</label>
                    <input type="number" name="expires_in_days" value="7" min="1" max="30"
                           class="w-full p-2 border rounded"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                </div>

                <div id="invitationWarning" class="hidden p-3 rounded-lg text-sm"
                     style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i>
                    <span id="invitationWarningText"></span>
                </div>
            </div>

            <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                <button type="button" onclick="closeSendInvitationModal()" class="btn-secondary px-4 py-2 rounded-lg">Cancel</button>
                <button type="submit" class="btn-primary px-4 py-2 rounded-lg">
                    <i class="fas fa-paper-plane mr-2"></i> Send Invitation
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Resend Invitation Modal -->
<div id="resendInvitationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden p-4 modal-overlay">
    <div class="bg-white rounded-lg w-full max-w-lg modal-content" style="background-color: var(--bg-primary);">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Resend Invitation</h3>
                <button type="button" onclick="closeResendInvitationModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <form id="resendInvitationForm" method="POST">
            @csrf
            <input type="hidden" id="resendUserId" name="user_id">

            <div class="p-6 space-y-4">
                <div>
                    <p class="text-sm mb-2" style="color: var(--text-primary);">
                        Resend invitation to: <strong id="resendUserName" class="text-primary"></strong>
                    </p>
                    <p class="text-xs text-yellow-600">
                        <i class="fas fa-clock mr-1"></i> Previous invitation sent: <span id="prevSentDate"></span>
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Resend Options</label>
                    <select name="resend_type" id="resend_type" class="w-full p-2 border rounded"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="same_channels">Same channels as before</option>
                        <option value="available_channels">Use all available channels</option>
                        <option value="selected_channels">Select specific channels</option>
                    </select>
                </div>

                <div id="channelSelection" class="hidden">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Select Channels</label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="checkbox" name="channels[]" value="email" class="rounded border-gray-300">
                            <span class="ml-2 text-sm">Email</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="channels[]" value="sms" class="rounded border-gray-300">
                            <span class="ml-2 text-sm">SMS</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="channels[]" value="whatsapp" class="rounded border-gray-300">
                            <span class="ml-2 text-sm">WhatsApp</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Invitation Type</label>
                    <select name="invitation_type" class="w-full p-2 border rounded"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="welcome">Welcome Invitation</option>
                        <option value="registration">Registration Invitation</option>
                        <option value="account_setup">Account Setup Invitation</option>
                        <option value="password_setup">Password Setup Invitation</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Custom Message (Optional)</label>
                    <textarea name="custom_message" rows="3" class="w-full p-2 border rounded"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                              placeholder="Add a personal message to the invitation..."></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Expires In (Days)</label>
                    <input type="number" name="expires_in_days" value="7" min="1" max="30"
                           class="w-full p-2 border rounded"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                </div>
            </div>

            <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                <button type="button" onclick="closeResendInvitationModal()" class="btn-secondary px-4 py-2 rounded-lg">Cancel</button>
                <button type="submit" class="btn-primary px-4 py-2 rounded-lg">
                    <i class="fas fa-redo-alt mr-2"></i> Resend Invitation
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==================================================================== -->
<!-- EXPORT MODAL — Super Admin/Admin + visible selection indicator      -->
<!-- ==================================================================== -->
<div id="exportModal"
     class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden p-3 sm:p-4 modal-overlay">
    <div class="bg-white rounded-lg w-full max-w-xl max-h-[85vh] flex flex-col overflow-hidden modal-content"
         style="background-color: var(--bg-primary);">

        <!-- Header (fixed) -->
        <div class="flex-shrink-0 px-5 py-3 border-b flex justify-between items-center"
             style="background-color: var(--bg-primary); border-color: var(--border-color);">
            <h3 class="text-base sm:text-lg font-semibold" style="color: var(--text-primary);">Export Users</h3>
            <button type="button" onclick="closeExportModal()" class="text-gray-500 hover:text-gray-700 transition-colors">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <!-- Scrollable body -->
        <div class="flex-1 min-h-0 overflow-y-auto px-5 py-4 scrollable-modal-content">
            <form id="exportForm"
                  method="GET"
                  action="{{ route('admin.users.export') }}"
                  accept-charset="UTF-8">

                <!-- ============================================== -->
                <!-- Export Format — VISIBLE SELECTION INDICATOR   -->
                <!-- ============================================== -->
                <div class="mb-5">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Export Format</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="export-format-option cursor-pointer relative">
                            <input type="radio" name="export_format" value="csv" checked class="sr-only peer">
                            <div class="border-2 rounded-lg p-3 text-center transition-all duration-200
                                        border-gray-300
                                        peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-checked:shadow-md
                                        hover:border-blue-400 hover:shadow-md export-format-card">

                                <!-- Selected checkmark badge (top-right) -->
                                <span class="format-check-badge absolute top-2 right-2 w-6 h-6 rounded-full bg-blue-500 text-white items-center justify-center text-xs shadow-md">
                                    <i class="fas fa-check"></i>
                                </span>

                                <i class="fas fa-file-csv text-2xl mb-1 text-blue-500"></i>
                                <div class="font-medium text-sm" style="color: var(--text-primary);">CSV</div>
                                <div class="text-[11px]" style="color: var(--text-secondary);">Excel compatible</div>
                                <div class="format-selected-label mt-2 text-[10px] font-semibold text-blue-600 uppercase tracking-wide">
                                    <i class="fas fa-check-circle mr-1"></i> Selected
                                </div>
                            </div>
                        </label>

                        <label class="export-format-option cursor-pointer relative">
                            <input type="radio" name="export_format" value="pdf" class="sr-only peer">
                            <div class="border-2 rounded-lg p-3 text-center transition-all duration-200
                                        border-gray-300
                                        peer-checked:border-red-500 peer-checked:bg-red-50 peer-checked:shadow-md
                                        hover:border-red-400 hover:shadow-md export-format-card">

                                <!-- Selected checkmark badge (top-right) -->
                                <span class="format-check-badge absolute top-2 right-2 w-6 h-6 rounded-full bg-red-500 text-white items-center justify-center text-xs shadow-md">
                                    <i class="fas fa-check"></i>
                                </span>

                                <i class="fas fa-file-pdf text-2xl mb-1 text-red-500"></i>
                                <div class="font-medium text-sm" style="color: var(--text-primary);">PDF</div>
                                <div class="text-[11px]" style="color: var(--text-secondary);">Print-friendly</div>
                                <div class="format-selected-label mt-2 text-[10px] font-semibold text-red-600 uppercase tracking-wide">
                                    <i class="fas fa-check-circle mr-1"></i> Selected
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- ============================================== -->
                <!-- User types — INCLUDES SUPER ADMIN + ADMIN     -->
                <!-- ============================================== -->
                <div class="mb-5">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">User Types to Export</label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5">
                        <label class="flex items-center py-1">
                            <input type="checkbox" name="user_types[]" value="all" checked class="rounded border-gray-300 user-type-checkbox">
                            <span class="ml-2 text-sm" style="color: var(--text-primary);">All Users</span>
                        </label>
                        <label class="flex items-center py-1">
                            <input type="checkbox" name="user_types[]" value="{{ \App\Models\User::TYPE_SUPER_ADMIN }}" class="rounded border-gray-300 user-type-checkbox">
                            <span class="ml-2 text-sm" style="color: var(--text-primary);">Super Admins</span>
                        </label>
                        <label class="flex items-center py-1">
                            <input type="checkbox" name="user_types[]" value="{{ \App\Models\User::TYPE_ADMIN }}" class="rounded border-gray-300 user-type-checkbox">
                            <span class="ml-2 text-sm" style="color: var(--text-primary);">Admins</span>
                        </label>
                        <label class="flex items-center py-1">
                            <input type="checkbox" name="user_types[]" value="{{ \App\Models\User::TYPE_LANDLORD }}" class="rounded border-gray-300 user-type-checkbox">
                            <span class="ml-2 text-sm" style="color: var(--text-primary);">Landlords</span>
                        </label>
                        <label class="flex items-center py-1">
                            <input type="checkbox" name="user_types[]" value="{{ \App\Models\User::TYPE_FIELD_AGENT }}" class="rounded border-gray-300 user-type-checkbox">
                            <span class="ml-2 text-sm" style="color: var(--text-primary);">Field Agents</span>
                        </label>
                        <label class="flex items-center py-1">
                            <input type="checkbox" name="user_types[]" value="{{ \App\Models\User::TYPE_TENANT }}" class="rounded border-gray-300 user-type-checkbox">
                            <span class="ml-2 text-sm" style="color: var(--text-primary);">Tenants</span>
                        </label>
                        <label class="flex items-center py-1">
                            <input type="checkbox" name="user_types[]" value="{{ \App\Models\User::TYPE_SECURITY_PERSONNEL }}" class="rounded border-gray-300 user-type-checkbox">
                            <span class="ml-2 text-sm" style="color: var(--text-primary);">Security</span>
                        </label>
                    </div>
                </div>

                <!-- Additional options -->
                <div class="mb-5">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Additional Options</label>
                    <div class="space-y-1.5">
                        <label class="flex items-center">
                            <input type="checkbox" name="include_photos" value="1" class="rounded border-gray-300">
                            <span class="ml-2 text-sm" style="color: var(--text-primary);">Include profile photos (PDF only)</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="include_statistics" value="1" checked class="rounded border-gray-300">
                            <span class="ml-2 text-sm" style="color: var(--text-primary);">Include summary statistics</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="current_filters" value="1" checked class="rounded border-gray-300">
                            <span class="ml-2 text-sm" style="color: var(--text-primary);">Apply current search filters</span>
                        </label>
                    </div>
                </div>

                <!-- Date range -->
                <div class="mb-5">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Date Range</label>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] mb-1" style="color: var(--text-secondary);">From Date</label>
                            <input type="date" name="start_date" class="w-full p-2 border rounded text-sm"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        </div>
                        <div>
                            <label class="block text-[11px] mb-1" style="color: var(--text-secondary);">To Date</label>
                            <input type="date" name="end_date" class="w-full p-2 border rounded text-sm"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        </div>
                    </div>
                </div>

                <!-- Summary -->
                <div class="bg-gray-50 rounded-lg p-3" style="background-color: var(--bg-secondary);">
                    <h4 class="font-medium mb-1 text-xs uppercase tracking-wide" style="color: var(--text-secondary);">Export Summary</h4>
                    <div class="text-sm" style="color: var(--text-primary);">
                        <div id="exportSummary">Preparing export details...</div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Footer (fixed) -->
        <div class="flex-shrink-0 px-5 py-3 border-t flex flex-col sm:flex-row justify-end gap-2 sm:gap-3"
             style="background-color: var(--bg-primary); border-color: var(--border-color);">
            <button type="button" onclick="closeExportModal()" class="btn-secondary px-4 py-2 rounded-lg order-2 sm:order-1">
                Cancel
            </button>
            <button type="submit"
                    form="exportForm"
                    id="exportSubmitBtn"
                    class="btn-success flex items-center justify-center order-1 sm:order-2 px-4 py-2 rounded-lg">
                <i class="fas fa-file-export mr-2"></i> Generate Export
            </button>
        </div>
    </div>
</div>

<!-- Photo Update Modal -->
<div id="photoModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden modal-overlay">
    <div class="bg-white rounded-lg p-6 w-full max-w-md modal-content" style="background-color: var(--bg-primary);">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);" id="photoModalTitle">Update User Photo</h3>
        <form id="photoForm" enctype="multipart/form-data">
            @csrf
            <input type="hidden" id="photoUserId" name="user_id">
            <div class="mb-4">
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Select Photo</label>
                <input type="file" name="photo" id="photoInput" accept="image/*" class="w-full p-2 border rounded"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    Supported formats: JPEG, PNG, GIF, WEBP. Max size: 5MB.
                </p>
            </div>
            <div id="photoPreview" class="mb-4 hidden">
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Preview</label>
                <img id="previewImage" class="w-32 h-32 rounded-full object-cover mx-auto border-2"
                     style="border-color: var(--border-color);"
                     loading="lazy">
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closePhotoModal()" class="btn-secondary px-4 py-2 rounded-lg">Cancel</button>
                <button type="submit" class="btn-primary px-4 py-2 rounded-lg" id="photoSubmitBtn">
                    <i class="fas fa-upload mr-2"></i> Upload Photo
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete User Modal -->
<div id="deleteUserModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden modal-overlay">
    <div class="bg-white rounded-lg p-6 w-full max-w-md modal-content" style="background-color: var(--bg-primary);">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Delete User Account</h3>
        <div class="alert-warning p-3 rounded mb-4">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <strong>Warning:</strong> This action will soft delete the user account.
        </div>

        <p class="mb-4" style="color: var(--text-primary);">
            Are you sure you want to delete <strong id="deleteUserName"></strong>?
        </p>

        <div class="mb-4">
            <label for="deletion_reason" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                Reason for Deletion (Optional)
            </label>
            <textarea id="deletion_reason" name="deletion_reason" rows="3"
                      class="w-full p-2 border rounded"
                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                      placeholder="Enter reason for deletion..."></textarea>
        </div>

        <div id="criticalRelationsWarning" class="alert-danger p-3 rounded mb-4 hidden">
            <i class="fas fa-ban mr-2"></i>
            <strong>Cannot Delete:</strong> User has critical associated data:
            <ul id="criticalRelationsList" class="mt-1 mb-0 pl-4"></ul>
            <small class="d-block mt-2">Please deactivate the account instead.</small>
        </div>

        <div class="flex justify-end space-x-3">
            <button type="button" onclick="closeDeleteModal()" class="btn-secondary px-4 py-2 rounded-lg">Cancel</button>
            <button type="button" id="confirmDeleteBtn" onclick="confirmDeleteUser()" class="btn-danger px-4 py-2 rounded-lg">
                <i class="fas fa-trash mr-2"></i> Delete User
            </button>
        </div>
    </div>
</div>

<!-- Role Management Modal -->
@if(auth()->user()->isSuperAdmin())
<div id="roleManagementModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50 modal-overlay">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white" style="background-color: var(--bg-primary);">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium" style="color: var(--text-primary);">
                Manage Roles: <span id="modalUserName"></span>
            </h3>
            <button type="button" onclick="closeRoleModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form id="roleForm" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="user_id" id="roleUserId">
            <input type="hidden" name="target_user_id" id="targetUserId">

            <div class="mb-4">
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                    Assign Additional Roles
                </label>
                <div class="bg-blue-50 p-3 rounded-lg mb-4" style="background-color: rgba(59, 130, 246, 0.1);">
                    <p class="text-xs text-blue-700">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Note:</strong> Super Admin role is permanent and cannot be removed. You can add additional roles below.
                    </p>
                </div>
                <div class="space-y-2 max-h-64 overflow-y-auto" id="rolesList">
                    {{-- Roles populated via JS --}}
                </div>
            </div>

            <div id="landlordWarning" class="hidden mb-4 p-3 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <span id="landlordWarningText"></span>
            </div>

            <div id="propertyOwnerWarning" class="hidden mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span id="propertyOwnerWarningText"></span>
            </div>

            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeRoleModal()"
                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">
                    Cancel
                </button>
                <button type="submit"
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<!-- Restore Archived User Modal -->
<div id="restoreArchivedModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden modal-overlay">
    <div class="bg-white rounded-lg p-6 w-full max-w-md modal-content" style="background-color: var(--bg-primary);">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Restore Archived Account</h3>
        <div class="alert-info p-3 rounded mb-4">
            <i class="fas fa-info-circle mr-2"></i>
            <strong>Restore Account:</strong> This will reactivate the user account and restore full access.
        </div>

        <p class="mb-4" style="color: var(--text-primary);">
            Are you sure you want to restore <strong id="restoreArchivedUserName"></strong>?
        </p>

        <div class="flex justify-end space-x-3">
            <button type="button" onclick="closeRestoreArchivedModal()" class="btn-secondary px-4 py-2 rounded-lg">Cancel</button>
            <button type="button" id="confirmRestoreArchivedBtn" onclick="confirmRestoreArchived()" class="btn-success px-4 py-2 rounded-lg">
                <i class="fas fa-undo-alt mr-2"></i> Restore User
            </button>
        </div>
    </div>
</div>

<!-- Archive Warning Modal -->
<div id="archiveWarningModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden modal-overlay">
    <div class="bg-white rounded-lg p-6 w-full max-w-md modal-content" style="background-color: var(--bg-primary);">
        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
            <i class="fas fa-archive text-warning mr-2" style="color: #ffc107;"></i>
            Archive Landlord Account
        </h3>

        <div class="alert-warning p-3 rounded mb-4" style="background-color: rgba(255, 193, 7, 0.1); border-left: 4px solid #ffc107;">
            <i class="fas fa-exclamation-triangle mr-2" style="color: #ffc107;"></i>
            <strong>Account Archival Notice</strong>
        </div>

        <p class="mb-3" style="color: var(--text-primary);">
            You are about to archive <strong id="archiveWarningUserName"></strong>.
        </p>

        <div class="bg-gray-100 rounded p-3 mb-4" style="background-color: var(--bg-secondary);">
            <p class="text-sm mb-2" style="color: var(--text-primary);">
                <i class="fas fa-info-circle mr-1 text-info"></i>
                <strong>What happens when you archive this account?</strong>
            </p>
            <ul class="text-sm space-y-1 ml-4" style="color: var(--text-secondary);">
                <li><i class="fas fa-ban mr-2 text-danger"></i> User will lose login access</li>
                <li><i class="fas fa-clock mr-2 text-warning"></i> Account scheduled for deletion after <strong id="archiveDeletionDays">365</strong> days</li>
                <li><i class="fas fa-archive mr-2 text-secondary"></i> All historical data preserved</li>
                <li><i class="fas fa-undo-alt mr-2 text-success"></i> Can be restored within 30 days</li>
            </ul>
        </div>

        <div class="mb-4">
            <label for="archive_reason" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                Archival Reason (Optional)
            </label>
            <textarea id="archive_reason" rows="2"
                      class="w-full p-2 border rounded"
                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                      placeholder="e.g., Landlord transferred all properties..."></textarea>
        </div>

        <div class="bg-blue-50 rounded p-3 mb-4" style="background-color: rgba(23, 162, 184, 0.1);">
            <p class="text-xs" style="color: var(--text-secondary);">
                <i class="fas fa-gavel mr-1"></i>
                This user has <strong id="archivePropertyCount">0</strong> properties. Archival will be processed according to system settings.
            </p>
        </div>

        <div class="flex justify-end space-x-3">
            <button type="button" onclick="closeArchiveWarningModal()" class="btn-secondary px-4 py-2 rounded-lg">Cancel</button>
            <button type="button" id="confirmArchiveBtn" onclick="confirmArchiveUser()" class="btn-warning px-4 py-2 rounded-lg" style="background-color: #ffc107; color: #856404;">
                <i class="fas fa-archive mr-2"></i> Archive Account
            </button>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
// ==================== VARIABLES ====================
let currentUserId = null;
let currentUserName = null;
let currentPhotoUserId = null;
let currentZoomRotation = 0;
let currentZoomIndex = 0;
let zoomedPhotos = [];
let isLazyLoadingEnabled = true;
let isLazyLoadObserverInitialized = false;

let currentInvitationUserId = null;
let currentResendUserId = null;

@if(auth()->user()->isSuperAdmin())
let currentRoleUserId = null;
let availableRolesData = [];
let isTargetUserSelf = false;
@endif

let currentRestoreUserId = null;
let currentRestoreUserName = null;

let currentArchiveUserId = null;
let currentArchiveUserName = null;
let currentArchivePropertyCount = 0;

// ==================== DOM CONTENT LOADED ====================
document.addEventListener('DOMContentLoaded', function() {
    // ═══════════════════════════════════════════════════════════════
    // ⚠️ REMOVED: Auto-fill of start_date and end_date
    //
    // Previously this block did:
    //   const oneMonthAgo = new Date();
    //   oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1);
    //   startDateInput.value = oneMonthAgo.toISOString().split('T')[0];
    //   endDateInput.value = today;
    //
    // That silently excluded any user created before "one month ago"
    // from every export — including Super Admin, Admin, and any
    // early-seeded users. The date range is now opt-in: the user
    // must explicitly pick dates if they want to filter.
    //
    // Do NOT re-add date pre-filling here.
    // ═══════════════════════════════════════════════════════════════

    updateExportSummary();
    updateFormatSelectionUI();
    initLazyLoading();

    @if(auth()->user()->isSuperAdmin())
    attachRoleButtonHandlers();
    @endif

    document.querySelectorAll('.user-checkbox[disabled]').forEach(checkbox => {
        checkbox.checked = false;
        checkbox.disabled = true;
    });

    const exportForm = document.getElementById('exportForm');
    if (exportForm) {
        exportForm.addEventListener('change', function(e) {
            updateExportSummary();
            if (e.target && e.target.name === 'export_format') {
                updateFormatSelectionUI();
            }
        });
        exportForm.addEventListener('input', updateExportSummary);
    }
});

// ==================== FORMAT SELECTION UI ====================
/**
 * Toggle the visible "Selected" indicator on each format card
 * (checkmark badge + label + border color) based on which radio is checked.
 */
function updateFormatSelectionUI() {
    const options = document.querySelectorAll('.export-format-option');

    options.forEach(option => {
        const radio = option.querySelector('input[type="radio"]');
        const card = option.querySelector('.export-format-card');
        const badge = option.querySelector('.format-check-badge');
        const label = option.querySelector('.format-selected-label');

        if (!radio || !card) return;

        const isSelected = radio.checked;

        // Toggle badge visibility
        if (badge) {
            badge.style.display = isSelected ? 'inline-flex' : 'none';
        }

        // Toggle "Selected" label
        if (label) {
            label.style.visibility = isSelected ? 'visible' : 'hidden';
            label.style.opacity = isSelected ? '1' : '0';
        }

        // Toggle active class for extra styling if needed
        card.classList.toggle('is-selected', isSelected);
    });
}

// ==================== LAZY LOADING ====================
function initLazyLoading() {
    if (isLazyLoadObserverInitialized || !isLazyLoadingEnabled) return;

    const lazyImages = document.querySelectorAll('.lazy-load-photo');

    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    const dataSrc = img.getAttribute('data-src');

                    if (dataSrc) {
                        img.src = dataSrc;
                        img.classList.remove('lazy-load-photo');
                        img.classList.add('loaded');
                        observer.unobserve(img);
                    }
                }
            });
        }, { rootMargin: '50px 0px', threshold: 0.1 });

        lazyImages.forEach(img => imageObserver.observe(img));
        isLazyLoadObserverInitialized = true;
    } else {
        lazyImages.forEach(img => {
            const dataSrc = img.getAttribute('data-src');
            if (dataSrc) {
                img.src = dataSrc;
                img.classList.remove('lazy-load-photo');
                img.classList.add('loaded');
            }
        });
    }
}

// ==================== INVITATION FUNCTIONS ====================
function showSendInvitationModal(userId, userName, userType) {
    currentInvitationUserId = userId;
    document.getElementById('invitationUserId').value = userId;
    document.getElementById('invitationUserName').textContent = userName;

    const typeMap = {
        '{{ \App\Models\User::TYPE_FIELD_AGENT }}': 'Field Agent',
        '{{ \App\Models\User::TYPE_LANDLORD }}': 'Landlord',
        '{{ \App\Models\User::TYPE_TENANT }}': 'Tenant',
        '{{ \App\Models\User::TYPE_SECURITY_PERSONNEL }}': 'Security Personnel'
    };
    document.getElementById('invitationUserType').textContent = typeMap[userType] || userType;

    const form = document.getElementById('sendInvitationForm');
    form.reset();
    document.querySelectorAll('#sendInvitationForm input[name="invitation_channels[]"]').forEach(checkbox => {
        checkbox.checked = (checkbox.value === 'email');
    });
    document.querySelector('#sendInvitationForm input[name="expires_in_days"]').value = 7;

    form.action = `/admin/users/${userId}/send-invitation`;
    document.getElementById('sendInvitationModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeSendInvitationModal() {
    document.getElementById('sendInvitationModal').classList.add('hidden');
    document.body.style.overflow = '';
    currentInvitationUserId = null;
}

function resendInvitation(userId, userName) {
    currentResendUserId = userId;
    document.getElementById('resendUserId').value = userId;
    document.getElementById('resendUserName').textContent = userName;
    document.getElementById('prevSentDate').textContent = 'Loading...';

    fetch(`/admin/users/${userId}/invitation-info`, {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.invitation) {
            const sentDate = new Date(data.invitation.sent_at);
            document.getElementById('prevSentDate').textContent = sentDate.toLocaleString();

            if (data.invitation.type) {
                const typeSelect = document.querySelector('#resendInvitationForm select[name="invitation_type"]');
                if (typeSelect && typeSelect.querySelector(`option[value="${data.invitation.type}"]`)) {
                    typeSelect.value = data.invitation.type;
                }
            }
        } else {
            document.getElementById('prevSentDate').textContent = 'Unknown';
        }
    })
    .catch(error => {
        console.error('Error fetching invitation info:', error);
        document.getElementById('prevSentDate').textContent = 'Unable to load';
        showNotification('Could not load previous invitation details', 'warning');
    });

    const form = document.getElementById('resendInvitationForm');
    form.reset();
    document.getElementById('channelSelection').classList.add('hidden');
    document.querySelector('#resend_type').value = 'same_channels';

    form.action = `/admin/users/${userId}/resend-invitation`;
    document.getElementById('resendInvitationModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeResendInvitationModal() {
    document.getElementById('resendInvitationModal').classList.add('hidden');
    document.body.style.overflow = '';
    currentResendUserId = null;
}

document.addEventListener('DOMContentLoaded', function() {
    const resendTypeSelect = document.getElementById('resend_type');
    if (resendTypeSelect) {
        resendTypeSelect.addEventListener('change', function() {
            const channelSelection = document.getElementById('channelSelection');
            if (this.value === 'selected_channels') {
                channelSelection.classList.remove('hidden');
            } else {
                channelSelection.classList.add('hidden');
            }
        });
    }
});

document.getElementById('sendInvitationForm')?.addEventListener('submit', function(e) {
    e.preventDefault();

    const selectedChannels = Array.from(document.querySelectorAll('#sendInvitationForm input[name="invitation_channels[]"]:checked'))
        .map(cb => cb.value);

    if (selectedChannels.length === 0) {
        showNotification('Please select at least one channel to send the invitation.', 'error');
        return;
    }

    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;

    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending...';
    submitBtn.disabled = true;

    fetch(this.action, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(async response => {
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || `HTTP ${response.status}`);
        return data;
    })
    .then(data => {
        if (data.success) {
            showNotification(data.message || 'Invitation sent successfully!', 'success');
            closeSendInvitationModal();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            throw new Error(data.message || 'Failed to send invitation');
        }
    })
    .catch(error => {
        console.error('Send invitation error:', error);
        showNotification('Error: ' + error.message, 'error');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

document.getElementById('resendInvitationForm')?.addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;

    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Resending...';
    submitBtn.disabled = true;

    fetch(this.action, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(async response => {
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || `HTTP ${response.status}`);
        return data;
    })
    .then(data => {
        if (data.success) {
            showNotification(data.message || 'Invitation resent successfully!', 'success');
            closeResendInvitationModal();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            throw new Error(data.message || 'Failed to resend invitation');
        }
    })
    .catch(error => {
        console.error('Resend invitation error:', error);
        showNotification('Error: ' + error.message, 'error');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

// ==================== PHOTO ZOOM FUNCTIONS ====================
function zoomUserPhoto(element) {
    const userId = element.dataset.userId;
    const userName = element.dataset.userName;
    const photoUrl = element.dataset.photoUrl;

    zoomedPhotos = Array.from(document.querySelectorAll('.user-photo-zoom, .loaded'))
        .map(img => ({
            userId: img.dataset.userId,
            userName: img.dataset.userName,
            photoUrl: img.dataset.photoUrl || img.src,
            element: img
        }));

    currentZoomIndex = zoomedPhotos.findIndex(photo => photo.userId === userId);
    if (currentZoomIndex === -1) return;

    document.getElementById('photoZoomLoading').classList.remove('hidden');
    document.getElementById('photoZoomModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    const zoomedPhoto = document.getElementById('zoomedPhoto');
    const highQualityUrl = photoUrl.replace(/(\/thumb|\/small)/, '/large') || photoUrl;

    zoomedPhoto.onload = function() {
        document.getElementById('photoZoomLoading').classList.add('hidden');
        currentZoomRotation = 0;
        zoomedPhoto.style.transform = 'rotate(0deg)';
        zoomedPhoto.classList.add('loaded');
    };

    zoomedPhoto.onerror = function() {
        document.getElementById('photoZoomLoading').classList.add('hidden');
        showNotification('Failed to load photo', 'error');
    };

    zoomedPhoto.src = highQualityUrl;
    document.getElementById('zoomedUserName').textContent = userName;

    document.addEventListener('keydown', handleZoomKeyboard);
}

function closePhotoZoomModal() {
    document.getElementById('photoZoomModal').classList.add('hidden');
    document.body.style.overflow = '';
    document.removeEventListener('keydown', handleZoomKeyboard);
    currentZoomRotation = 0;
}

function navigateZoomPhoto(direction) {
    currentZoomIndex += direction;

    if (currentZoomIndex < 0) {
        currentZoomIndex = zoomedPhotos.length - 1;
    } else if (currentZoomIndex >= zoomedPhotos.length) {
        currentZoomIndex = 0;
    }

    const photo = zoomedPhotos[currentZoomIndex];
    const zoomedPhoto = document.getElementById('zoomedPhoto');

    document.getElementById('photoZoomLoading').classList.remove('hidden');
    const highQualityUrl = photo.photoUrl.replace(/(\/thumb|\/small)/, '/large') || photo.photoUrl;

    zoomedPhoto.onload = function() {
        document.getElementById('photoZoomLoading').classList.add('hidden');
        currentZoomRotation = 0;
        zoomedPhoto.style.transform = 'rotate(0deg)';
    };

    zoomedPhoto.src = highQualityUrl;
    document.getElementById('zoomedUserName').textContent = photo.userName;
}

function handleZoomKeyboard(e) {
    if (e.key === 'Escape') {
        closePhotoZoomModal();
    } else if (e.key === 'ArrowLeft') {
        navigateZoomPhoto(-1);
    } else if (e.key === 'ArrowRight') {
        navigateZoomPhoto(1);
    } else if (e.key === 'r' || e.key === 'R') {
        rotateZoomedPhoto();
    }
}

function rotateZoomedPhoto() {
    currentZoomRotation = (currentZoomRotation + 90) % 360;
    document.getElementById('zoomedPhoto').style.transform = `rotate(${currentZoomRotation}deg)`;
}

function downloadZoomedPhoto() {
    const zoomedPhoto = document.getElementById('zoomedPhoto');
    const userName = document.getElementById('zoomedUserName').textContent;
    const link = document.createElement('a');
    link.download = `profile-photo-${userName.toLowerCase().replace(/\s+/g, '-')}.jpg`;
    link.href = zoomedPhoto.src;
    link.click();
}

function showAvatarInfo(userName, initials) {
    document.getElementById('avatarInitials').textContent = initials;
    document.getElementById('avatarUserName').textContent = userName;
    document.getElementById('avatarInfoModal').classList.remove('hidden');
}

function closeAvatarInfoModal() {
    document.getElementById('avatarInfoModal').classList.add('hidden');
}

// ==================== EXPORT MODAL FUNCTIONS ====================
function showExportModal() {
    const modal = document.getElementById('exportModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    updateExportSummary();
    updateFormatSelectionUI();

    const scrollableContent = modal.querySelector('.scrollable-modal-content');
    if (scrollableContent) scrollableContent.scrollTop = 0;
}

function closeExportModal() {
    document.getElementById('exportModal').classList.add('hidden');
    document.body.style.overflow = '';
}

function updateExportSummary() {
    const format = document.querySelector('input[name="export_format"]:checked')?.value || 'csv';
    const selectedTypes = Array.from(document.querySelectorAll('input[name="user_types[]"]:checked'))
                              .map(cb => cb.value);

    const formatText = format.toUpperCase();
    let typeText = 'All User Types';

    if (!selectedTypes.includes('all') && selectedTypes.length > 0) {
        const typeLabels = {
            '{{ \App\Models\User::TYPE_SUPER_ADMIN }}': 'Super Admins',
            '{{ \App\Models\User::TYPE_ADMIN }}': 'Admins',
            '{{ \App\Models\User::TYPE_LANDLORD }}': 'Landlords',
            '{{ \App\Models\User::TYPE_FIELD_AGENT }}': 'Field Agents',
            '{{ \App\Models\User::TYPE_TENANT }}': 'Tenants',
            '{{ \App\Models\User::TYPE_SECURITY_PERSONNEL }}': 'Security Personnel'
        };
        typeText = selectedTypes.map(type => typeLabels[type] || type).join(', ');
    }

    const summaryElement = document.getElementById('exportSummary');
    if (summaryElement) {
        summaryElement.textContent = `Exporting ${typeText} in ${formatText} format`;
    }
}

document.querySelectorAll('.export-format-option').forEach(option => {
    const radio = option.querySelector('input[type="radio"]');
    if (radio) {
        radio.addEventListener('change', function() {
            updateExportSummary();
            updateFormatSelectionUI();

            const photoCheckbox = document.querySelector('input[name="include_photos"]');
            if (photoCheckbox && this.value !== 'pdf') {
                photoCheckbox.checked = false;
            }
        });
    }
});

document.querySelectorAll('.user-type-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        if (this.value === 'all' && this.checked) {
            document.querySelectorAll('.user-type-checkbox').forEach(cb => {
                if (cb.value !== 'all') cb.checked = false;
            });
        } else if (this.checked) {
            const allCb = document.querySelector('input[value="all"]');
            if (allCb) allCb.checked = false;
        }

        const anyChecked = Array.from(document.querySelectorAll('.user-type-checkbox')).some(cb => cb.checked);
        if (!anyChecked) {
            const allCb = document.querySelector('input[value="all"]');
            if (allCb) allCb.checked = true;
        }

        updateExportSummary();
    });
});

document.getElementById('exportForm')?.addEventListener('submit', function(e) {
    const selectedTypes = Array.from(document.querySelectorAll('input[name="user_types[]"]:checked'));
    if (selectedTypes.length === 0) {
        e.preventDefault();
        showNotification('Please select at least one user type to export.', 'error');
        return false;
    }

    setTimeout(() => {
        closeExportModal();
    }, 50);
    return true;
});

document.getElementById('select-all')?.addEventListener('change', function(e) {
    document.querySelectorAll('.user-checkbox:not([disabled])').forEach(checkbox => {
        checkbox.checked = e.target.checked;
    });
});

// ==================== PHOTO MANAGEMENT FUNCTIONS ====================
function updateUserPhoto(userId) {
    currentPhotoUserId = userId;
    document.getElementById('photoModalTitle').textContent = 'Update User Photo';
    document.getElementById('photoSubmitBtn').innerHTML = '<i class="fas fa-upload mr-2"></i> Upload Photo';
    document.getElementById('photoModal').classList.remove('hidden');
    document.getElementById('photoInput').value = '';
    document.getElementById('photoPreview').classList.add('hidden');
    document.getElementById('photoUserId').value = userId;
}

function removeUserPhoto(userId) {
    if (confirm('Are you sure you want to remove this user\'s profile photo?')) {
        showNotification('Removing photo...', 'info');
        fetch(`/admin/users/${userId}/remove-photo`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Profile photo removed successfully!', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                throw new Error(data.message || 'Failed to remove photo');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error: ' + error.message, 'error');
        });
    }
}

function closePhotoModal() {
    document.getElementById('photoModal').classList.add('hidden');
    currentPhotoUserId = null;
}

document.getElementById('photoInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('photoPreview');
    const previewImage = document.getElementById('previewImage');

    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImage.src = e.target.result;
            preview.classList.remove('hidden');
        }
        reader.readAsDataURL(file);
    } else {
        preview.classList.add('hidden');
    }
});

document.getElementById('photoForm')?.addEventListener('submit', function(e) {
    e.preventDefault();

    if (!currentPhotoUserId) {
        showNotification('No user selected.', 'error');
        return;
    }

    const formData = new FormData();
    const photoFile = document.getElementById('photoInput').files[0];

    if (!photoFile) {
        showNotification('Please select a photo.', 'error');
        return;
    }

    if (photoFile.size > 5 * 1024 * 1024) {
        showNotification('File size must be less than 5MB.', 'error');
        return;
    }

    const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!validTypes.includes(photoFile.type)) {
        showNotification('Please select a valid image file (JPEG, PNG, GIF, or WEBP).', 'error');
        return;
    }

    formData.append('photo', photoFile);
    formData.append('_token', '{{ csrf_token() }}');

    const submitBtn = document.getElementById('photoSubmitBtn');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Uploading...';
    submitBtn.disabled = true;

    fetch(`/admin/users/${currentPhotoUserId}/update-photo`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Profile photo updated successfully!', 'success');
            closePhotoModal();
            setTimeout(() => window.location.reload(), 1000);
        } else {
            throw new Error(data.message || 'Failed to update photo');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error: ' + error.message, 'error');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

// ==================== DELETE USER FUNCTIONS ====================
function closeDeleteModal() {
    document.getElementById('deleteUserModal').classList.add('hidden');
    currentUserId = null;
    currentUserName = null;
}

function deleteUser(userId, userName) {
    currentUserId = userId;
    currentUserName = userName;

    document.getElementById('deleteUserName').textContent = userName;
    document.getElementById('confirmDeleteBtn').disabled = true;
    document.getElementById('confirmDeleteBtn').innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Checking...';

    document.getElementById('deleteUserModal').classList.remove('hidden');
    document.getElementById('criticalRelationsWarning').classList.add('hidden');
    document.getElementById('deletion_reason').value = '';

    fetch(`/admin/users/${userId}/check-relations`, {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.has_critical_relations) {
            document.getElementById('criticalRelationsWarning').classList.remove('hidden');
            const relationsList = document.getElementById('criticalRelationsList');
            relationsList.innerHTML = '';
            data.critical_relations.forEach(relation => {
                const li = document.createElement('li');
                li.textContent = relation;
                relationsList.appendChild(li);
            });
            document.getElementById('confirmDeleteBtn').disabled = true;
            document.getElementById('confirmDeleteBtn').innerHTML = '<i class="fas fa-ban mr-2"></i> Cannot Delete';
        } else {
            document.getElementById('confirmDeleteBtn').disabled = false;
            document.getElementById('confirmDeleteBtn').innerHTML = '<i class="fas fa-trash mr-2"></i> Delete User';
        }
    })
    .catch(error => {
        console.error('Error checking relations:', error);
        document.getElementById('confirmDeleteBtn').disabled = false;
        document.getElementById('confirmDeleteBtn').innerHTML = '<i class="fas fa-trash mr-2"></i> Delete User';
        showNotification('Warning: Could not verify user dependencies. Proceed with caution.', 'warning');
    });
}

function confirmDeleteUser() {
    const deletionReason = document.getElementById('deletion_reason').value;

    const confirmBtn = document.getElementById('confirmDeleteBtn');
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';

    const formData = new FormData();
    formData.append('_token', '{{ csrf_token() }}');
    formData.append('_method', 'DELETE');
    formData.append('deletion_reason', deletionReason || 'Manual deletion by admin');

    fetch(`/admin/users/${currentUserId}`, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data && data.success) {
            showNotification('User deleted successfully!', 'success');
            closeDeleteModal();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            throw new Error(data?.message || 'Delete failed');
        }
    })
    .catch(error => {
        console.error('Delete error:', error);
        showNotification('Error deleting user: ' + error.message, 'error');
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '<i class="fas fa-trash mr-2"></i> Delete User';
    });
}

// ==================== BULK ACTIONS ====================
function performBulkAction() {
    const action = document.getElementById('bulk-action').value;
    const selectedUsers = Array.from(document.querySelectorAll('input[name="user_ids[]"]:checked:not([disabled])'))
                               .map(checkbox => checkbox.value);

    if (!action) {
        showNotification('Please select an action.', 'error');
        return;
    }
    if (selectedUsers.length === 0) {
        showNotification('Please select at least one user.', 'error');
        return;
    }

    let confirmMessage = '';
    let actionData = { action: action, user_ids: selectedUsers };

    if (action === 'assign_landlord') {
        @if(auth()->user()->isSuperAdmin())
        confirmMessage = `Assign landlord role to ${selectedUsers.length} selected user(s)?`;
        if (confirm(confirmMessage)) {
            performRoleBulkAction(selectedUsers, 'assign_landlord');
        }
        @else
        showNotification('Only Super Administrators can assign landlord roles.', 'error');
        @endif
        return;
    } else if (action === 'send_invitation') {
        confirmMessage = `Send invitations to ${selectedUsers.length} selected user(s)?`;
        if (confirm(confirmMessage)) {
            performBulkInvitationAction(selectedUsers);
        }
        return;
    } else if (action === 'delete') {
        @if(auth()->user()->isSuperAdmin())
        confirmMessage = `Are you sure you want to delete ${selectedUsers.length} user(s)?`;
        @else
        showNotification('Only Super Administrators can delete users.', 'error');
        return;
        @endif
    } else if (action === 'suspend') {
        confirmMessage = `Suspend ${selectedUsers.length} user(s)?`;
    } else if (action === 'activate') {
        confirmMessage = `Activate ${selectedUsers.length} user(s)?`;
    } else if (action === 'deactivate') {
        confirmMessage = `Deactivate ${selectedUsers.length} user(s)?`;
    }

    if (confirmMessage && !confirm(confirmMessage)) return;

    fetch('{{ route("admin.users.bulk-action") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(actionData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            location.reload();
        } else {
            showNotification('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while performing the bulk action.', 'error');
    });
}

function performBulkInvitationAction(userIds) {
    showNotification(`Sending invitations to ${userIds.length} user(s)...`, 'info');

    fetch('{{ route("admin.users.bulk-action") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            action: 'send_invitation',
            user_ids: userIds,
            invitation_channels: ['email'],
            invitation_type: 'welcome'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            location.reload();
        } else {
            showNotification('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while sending invitations.', 'error');
    });
}

function performRoleBulkAction(userIds, actionType) {
    @if(auth()->user()->isSuperAdmin())
    showNotification(`Processing ${actionType} action for ${userIds.length} user(s)...`, 'info');

    fetch('{{ route("admin.users.bulk-role-action") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            action: actionType,
            user_ids: userIds
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            location.reload();
        } else {
            showNotification('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while assigning roles.', 'error');
    });
    @endif
}

// ==================== ARCHIVAL FUNCTIONS ====================
function restoreArchivedUser(userId, userName) {
    currentRestoreUserId = userId;
    currentRestoreUserName = userName;
    document.getElementById('restoreArchivedUserName').textContent = userName;
    document.getElementById('restoreArchivedModal').classList.remove('hidden');
}

function closeRestoreArchivedModal() {
    document.getElementById('restoreArchivedModal').classList.add('hidden');
    currentRestoreUserId = null;
}

function confirmRestoreArchived() {
    if (!currentRestoreUserId) return;

    const restoreBtn = document.getElementById('confirmRestoreArchivedBtn');
    restoreBtn.disabled = true;
    restoreBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Restoring...';

    fetch(`/admin/users/${currentRestoreUserId}/restore-archived`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('User restored successfully!', 'success');
            closeRestoreArchivedModal();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            throw new Error(data.message || 'Failed to restore');
        }
    })
    .catch(error => {
        console.error('Restore error:', error);
        showNotification('Error: ' + error.message, 'error');
        restoreBtn.disabled = false;
        restoreBtn.innerHTML = '<i class="fas fa-undo-alt mr-2"></i> Restore User';
    });
}

function showArchiveWarning(userId, userName, propertyCount) {
    currentArchiveUserId = userId;
    currentArchiveUserName = userName;
    currentArchivePropertyCount = propertyCount;

    document.getElementById('archiveWarningUserName').textContent = userName;
    document.getElementById('archivePropertyCount').textContent = propertyCount;

    fetch('/admin/settings/archival-days')
        .then(response => response.json())
        .then(data => {
            document.getElementById('archiveDeletionDays').textContent = data.days || 365;
        })
        .catch(() => {
            document.getElementById('archiveDeletionDays').textContent = 365;
        });

    document.getElementById('archiveWarningModal').classList.remove('hidden');
    document.getElementById('archive_reason').value = '';
}

function closeArchiveWarningModal() {
    document.getElementById('archiveWarningModal').classList.add('hidden');
    currentArchiveUserId = null;
}

function confirmArchiveUser() {
    if (!currentArchiveUserId) return;

    const reason = document.getElementById('archive_reason').value;
    const archiveBtn = document.getElementById('confirmArchiveBtn');

    archiveBtn.disabled = true;
    archiveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Archiving...';

    fetch(`/admin/users/${currentArchiveUserId}/archive`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ reason: reason || 'Manual archival by admin' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message || 'User archived successfully!', 'success');
            closeArchiveWarningModal();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            throw new Error(data.message || 'Failed to archive user');
        }
    })
    .catch(error => {
        console.error('Archive error:', error);
        showNotification('Error: ' + error.message, 'error');
        archiveBtn.disabled = false;
        archiveBtn.innerHTML = '<i class="fas fa-archive mr-2"></i> Archive Account';
    });
}

// ==================== INDIVIDUAL ACTION FUNCTIONS ====================
function activateUser(userId) {
    if (confirm('Activate this user account?')) {
        showNotification('Activating user...', 'info');
        fetch(`/admin/users/${userId}/activate`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (response.ok) {
                showNotification('User activated!', 'success');
                location.reload();
            } else {
                return response.json().then(data => {
                    throw new Error(data.message || 'Error activating user');
                });
            }
        })
        .catch(error => {
            console.error('Activation error:', error);
            showNotification(error.message, 'error');
        });
    }
}

function suspendUser(userId) {
    const reason = prompt('Please enter a reason for suspension:', '');
    if (reason !== null) {
        showNotification('Suspending user...', 'info');
        fetch(`/admin/users/${userId}/suspend`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ reason: reason || 'No reason provided' })
        })
        .then(response => {
            if (response.ok) {
                showNotification('User suspended!', 'success');
                location.reload();
            } else {
                return response.json().then(data => {
                    throw new Error(data.message || 'Error suspending user');
                });
            }
        })
        .catch(error => {
            console.error('Suspension error:', error);
            showNotification(error.message, 'error');
        });
    }
}

// ==================== ROLE MANAGEMENT FUNCTIONS (SUPER ADMIN ONLY) ====================
@if(auth()->user()->isSuperAdmin())
function attachRoleButtonHandlers() {
    const roleButtons = document.querySelectorAll('.manage-roles-btn');
    roleButtons.forEach(button => {
        button.removeEventListener('click', handleRoleButtonClick);
        button.addEventListener('click', handleRoleButtonClick);
    });
}

function handleRoleButtonClick(e) {
    e.preventDefault();
    e.stopPropagation();

    const userId = this.dataset.userId;
    const userName = this.dataset.userName;
    const currentUserId = this.dataset.currentUserId;
    const userType = this.dataset.userType;
    const hasLandlordRole = this.dataset.hasLandlordRole === 'true';
    const propertyCount = parseInt(this.dataset.propertyCount) || 0;
    const isPropertyOwner = this.dataset.isPropertyOwner === 'true';

    const isSelf = (userId === currentUserId);

    openRoleManagementModal(userId, userName, userType, hasLandlordRole, propertyCount, isPropertyOwner, isSelf);
}

function openRoleManagementModal(userId, userName, userType, hasLandlordRole, propertyCount, isPropertyOwner, isSelf) {
    currentRoleUserId = userId;
    isTargetUserSelf = isSelf;
    document.getElementById('modalUserName').textContent = userName;
    document.getElementById('roleUserId').value = userId;
    document.getElementById('targetUserId').value = userId;
    document.getElementById('roleForm').setAttribute('action', `/admin/users/${userId}/update-roles`);

    document.getElementById('landlordWarning').classList.add('hidden');
    document.getElementById('propertyOwnerWarning').classList.add('hidden');

    const rolesList = document.getElementById('rolesList');
    rolesList.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Loading roles...</div>';

    const modal = document.getElementById('roleManagementModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    fetch(`/admin/users/${userId}/roles-data`, {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        console.log('Role API Response Status:', response.status);
        if (!response.ok) {
            return response.text().then(text => {
                console.error('Role API Error Response:', text);
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            });
        }

        return response.text().then(text => {
            if (text.startsWith('\ufeff')) {
                text = text.replace(/^\uFEFF/, '');
            }
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON Parse Error:', e);
                rolesList.innerHTML = `
                    <div class="text-center py-4 text-red-500">
                        <i class="fas fa-exclamation-circle text-xl mb-2 block"></i>
                        Server returned invalid data
                        <button onclick="retryLoadRoles(${userId})" class="mt-3 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                            <i class="fas fa-redo mr-2"></i> Retry
                        </button>
                    </div>
                `;
                showNotification('Error loading roles. Please try again.', 'error');
                throw new Error('Invalid JSON response from server');
            }
        });
    })
    .then(response => {
        if (response.success) {
            availableRolesData = response.available_roles;
            renderRoleCheckboxes(response.available_roles, response.user.current_roles, isSelf);

            if (response.user.is_property_owner && response.user.property_count > 0) {
                const warningDiv = document.getElementById('propertyOwnerWarning');
                warningDiv.classList.remove('hidden');
                document.getElementById('propertyOwnerWarningText').innerHTML =
                    `<strong>Warning:</strong> This user owns ${response.user.property_count} property(s).<br>
                     Removing the landlord role will require transferring ownership first.`;
            }
        } else {
            const errorMsg = response.message || 'Failed to load roles';
            rolesList.innerHTML = `
                <div class="text-center py-4 text-red-500">
                    <i class="fas fa-exclamation-circle text-xl mb-2 block"></i>
                    ${errorMsg}
                </div>
            `;
            showNotification(errorMsg, 'error');
        }
    })
    .catch(error => {
        console.error('Error loading roles:', error);
        if (!rolesList.innerHTML.includes('Retry')) {
            rolesList.innerHTML = `
                <div class="text-center py-4 text-red-500">
                    <i class="fas fa-exclamation-circle text-xl mb-2 block"></i>
                    Error loading user roles
                    <div class="text-xs mt-2 text-gray-500">${error.message || 'Unknown error'}</div>
                    <button onclick="retryLoadRoles(${userId})" class="mt-3 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                        <i class="fas fa-redo mr-2"></i> Retry
                    </button>
                </div>
            `;
        }
        showNotification('Error loading user roles: ' + error.message, 'error');
    });
}

function retryLoadRoles(userId) {
    const button = document.querySelector(`.manage-roles-btn[data-user-id="${userId}"]`);
    if (button) {
        handleRoleButtonClick.call(button, new Event('click'));
    } else {
        showNotification('Could not find user button. Please refresh and try again.', 'warning');
    }
}

function renderRoleCheckboxes(availableRoles, currentRoles, isSelf) {
    const rolesList = document.getElementById('rolesList');
    const currentRoleSlugs = currentRoles.map(r => r.slug);

    const filteredRoles = availableRoles.filter(role => role.slug !== 'super-admin');

    if (!filteredRoles || filteredRoles.length === 0) {
        rolesList.innerHTML = '<div class="text-center py-4 text-gray-500">No additional roles available to assign</div>';
        return;
    }

    const sortedRoles = [...filteredRoles].sort((a, b) => (a.priority || 999) - (b.priority || 999));

    let html = '';
    html += `
        <div class="mb-3 p-2 bg-purple-50 rounded-lg border border-purple-200">
            <p class="text-xs text-purple-700 flex items-center">
                <i class="fas fa-lock mr-1"></i>
                <strong>Super Admin role is permanent</strong> - It cannot be removed, but you can add additional roles below.
            </p>
        </div>
    `;

    sortedRoles.forEach(role => {
        const isChecked = currentRoleSlugs.includes(role.slug);

        let roleDescription = role.description || '';
        if (role.slug === 'landlord') roleDescription = 'Allows property ownership and management';
        else if (role.slug === 'admin') roleDescription = 'Administrative access with limited permissions';
        else if (role.slug === 'field-agent') roleDescription = 'Field operations and property inspections';
        else if (role.slug === 'security-personnel') roleDescription = 'Security monitoring and incident reporting';
        else if (role.slug === 'tenant') roleDescription = 'Property tenant access and rent management';

        let badgeColor = '';
        if (role.slug === 'landlord') badgeColor = 'text-green-700';
        else if (role.slug === 'admin') badgeColor = 'text-blue-700';
        else if (role.slug === 'field-agent') badgeColor = 'text-cyan-700';
        else if (role.slug === 'security-personnel') badgeColor = 'text-orange-700';
        else if (role.slug === 'tenant') badgeColor = 'text-yellow-700';
        else badgeColor = 'text-gray-700';

        html += `
            <label class="flex items-start p-3 hover:bg-gray-50 rounded cursor-pointer transition-colors duration-150 border-b border-gray-100">
                <input type="checkbox"
                       name="roles[]"
                       value="${role.id}"
                       ${isChecked ? 'checked' : ''}
                       class="role-checkbox mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                       data-role-slug="${role.slug}"
                       data-role-name="${role.display_name}"
                       data-role-id="${role.id}">
                <div class="ml-3 flex-1">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium ${badgeColor}" style="color: var(--text-primary);">${role.display_name}</span>
                        ${isChecked ? '<span class="assigned-indicator text-xs text-green-600"><i class="fas fa-check-circle"></i> Assigned</span>' : ''}
                    </div>
                    ${roleDescription ? `<p class="text-xs mt-1" style="color: var(--text-secondary);">${roleDescription}</p>` : ''}
                </div>
            </label>
        `;
    });

    rolesList.innerHTML = html;

    document.querySelectorAll('.role-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const isUncheckingLandlord = !this.checked && this.dataset.roleSlug === 'landlord';
            const propertyOwnerWarning = document.getElementById('propertyOwnerWarning');
            const landlordWarning = document.getElementById('landlordWarning');

            if (isUncheckingLandlord && !propertyOwnerWarning.classList.contains('hidden')) {
                if (!confirm('WARNING: This user owns properties. Removing the landlord role will prevent them from managing their properties. Continue?')) {
                    this.checked = true;
                }
            }

            const labelContainer = this.closest('label');
            const assignedSpan = labelContainer.querySelector('.assigned-indicator');

            if (this.checked) {
                if (!assignedSpan) {
                    const titleSpan = labelContainer.querySelector('.flex.items-center.justify-between');
                    titleSpan.innerHTML += '<span class="assigned-indicator text-xs text-green-600"><i class="fas fa-check-circle"></i> Assigned</span>';
                }
            } else {
                if (assignedSpan) assignedSpan.remove();
            }

            const landlordCheckbox = document.querySelector('.role-checkbox[data-role-slug="landlord"]');
            const isLandlordChecked = landlordCheckbox?.checked || false;

            if (!isLandlordChecked && !propertyOwnerWarning.classList.contains('hidden')) {
                landlordWarning.classList.remove('hidden');
                document.getElementById('landlordWarningText').innerHTML = '<strong>Note:</strong> Landlord role is required for users who own properties to manage them.';
            } else {
                landlordWarning.classList.add('hidden');
            }
        });
    });
}

function closeRoleModal() {
    const modal = document.getElementById('roleManagementModal');
    modal.classList.add('hidden');
    document.body.style.overflow = '';
    currentRoleUserId = null;
    availableRolesData = [];
    isTargetUserSelf = false;
}

const roleForm = document.getElementById('roleForm');
if (roleForm) {
    roleForm.removeEventListener('submit', submitRoleForm);
    roleForm.addEventListener('submit', submitRoleForm);
}

function submitRoleForm(e) {
    e.preventDefault();

    if (!currentRoleUserId) {
        showNotification('No user selected.', 'error');
        return;
    }

    const formData = new FormData(document.getElementById('roleForm'));
    formData.append('_method', 'PUT');

    const submitBtn = document.querySelector('#roleForm button[type="submit"]');
    const originalText = submitBtn.innerHTML;

    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
    submitBtn.disabled = true;

    fetch(`/admin/users/${currentRoleUserId}/update-roles`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => {
        return response.text().then(text => {
            if (text.startsWith('\ufeff')) text = text.replace(/^\uFEFF/, '');
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse update response:', e);
                throw new Error('Invalid response from server');
            }
        });
    })
    .then(response => {
        if (response.success) {
            showNotification(response.message || 'User roles updated successfully!', 'success');
            closeRoleModal();
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification(response.message || 'Failed to update roles', 'error');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error updating roles:', error);
        showNotification('Error updating roles: ' + error.message, 'error');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}
@endif

// ==================== UTILITY FUNCTIONS ====================
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 transform transition-all duration-300 ${
        type === 'success' ? 'bg-green-500 text-white' :
        type === 'error' ? 'bg-red-500 text-white' :
        type === 'warning' ? 'bg-yellow-500 text-white' :
        'bg-blue-500 text-white'
    }`;
    notification.innerHTML = `<div class="flex items-center"><i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} mr-2"></i><span>${message}</span></div>`;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 5000);
}

// Close modals when clicking outside
document.getElementById('exportModal')?.addEventListener('click', function(e) { if (e.target === this) closeExportModal(); });
document.getElementById('photoModal')?.addEventListener('click', function(e) { if (e.target === this) closePhotoModal(); });
document.getElementById('deleteUserModal')?.addEventListener('click', function(e) { if (e.target === this) closeDeleteModal(); });
document.getElementById('photoZoomModal')?.addEventListener('click', function(e) { if (e.target === this) closePhotoZoomModal(); });
document.getElementById('avatarInfoModal')?.addEventListener('click', function(e) { if (e.target === this) closeAvatarInfoModal(); });
document.getElementById('restoreArchivedModal')?.addEventListener('click', function(e) { if (e.target === this) closeRestoreArchivedModal(); });
document.getElementById('sendInvitationModal')?.addEventListener('click', function(e) { if (e.target === this) closeSendInvitationModal(); });
document.getElementById('resendInvitationModal')?.addEventListener('click', function(e) { if (e.target === this) closeResendInvitationModal(); });
document.getElementById('archiveWarningModal')?.addEventListener('click', function(e) { if (e.target === this) closeArchiveWarningModal(); });
@if(auth()->user()->isSuperAdmin())
document.getElementById('roleManagementModal')?.addEventListener('click', function(e) { if (e.target === this) closeRoleModal(); });
@endif

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeExportModal(); closePhotoModal(); closeDeleteModal(); closePhotoZoomModal();
        closeAvatarInfoModal(); closeRestoreArchivedModal(); closeSendInvitationModal();
        closeResendInvitationModal(); closeArchiveWarningModal();
        @if(auth()->user()->isSuperAdmin())
        closeRoleModal();
        @endif
    }
});
</script>

<style>
.modal-overlay { animation: fadeIn 0.2s ease-out; }
.modal-content { animation: slideIn 0.3s ease-out; }

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideIn {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.user-status-badge,
.user-type-badge {
    display: inline-flex !important;
    visibility: visible !important;
    opacity: 1 !important;
}
.status-label,
.type-label { display: inline !important; visibility: visible !important; }
.field-agent-status { display: block !important; }
td .user-status-badge,
td .user-type-badge { display: inline-flex !important; }

.role-checkbox { cursor: pointer; }

#rolesList { scrollbar-width: thin; }
#rolesList::-webkit-scrollbar { width: 6px; }
#rolesList::-webkit-scrollbar-track { background: var(--bg-secondary); border-radius: 3px; }
#rolesList::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 3px; }

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    border-radius: 0.25rem;
    transition: all 0.2s ease;
}
.btn-sm:hover { transform: translateY(-1px); }

.spinner-large {
    display: inline-block;
    width: 3rem;
    height: 3rem;
    border: 3px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: white;
    animation: spin 0.6s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.landlord-no-properties-row { background-color: rgba(255, 193, 7, 0.05); }
.landlord-no-properties-row:hover { background-color: rgba(255, 193, 7, 0.1); }

.archive-warning-btn:hover { transform: scale(1.05); filter: brightness(0.95); }

@media (max-width: 768px) {
    .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .action-buttons { flex-wrap: wrap; gap: 0.25rem; }
    .archive-warning-btn .hidden.sm\:inline { display: none; }
}

.hover\:shadow-lg:hover {
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

/* ===================================================== */
/* EXPORT MODAL — compact + scrollable                   */
/* ===================================================== */
#exportModal .scrollable-modal-content {
    min-height: 0;
    scrollbar-width: thin;
}
#exportModal .scrollable-modal-content::-webkit-scrollbar { width: 6px; }
#exportModal .scrollable-modal-content::-webkit-scrollbar-track { background: var(--bg-secondary); border-radius: 3px; }
#exportModal .scrollable-modal-content::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 3px; }

#exportModal .export-format-card {
    transition: all 0.2s ease;
}
#exportModal .export-format-card:hover {
    transform: translateY(-1px);
}

/* ===================================================== */
/* EXPORT FORMAT — VISIBLE SELECTION INDICATOR           */
/* ===================================================== */
/* By default (unchecked), hide the badge and "Selected" label. */
#exportModal .format-check-badge {
    display: none;
    transition: opacity 0.15s ease, transform 0.15s ease;
}
#exportModal .format-selected-label {
    visibility: hidden;
    opacity: 0;
    transition: opacity 0.15s ease, visibility 0.15s ease;
}

/* When the radio inside the option is checked, show both. */
#exportModal .export-format-option input[type="radio"]:checked ~ .export-format-card .format-check-badge {
    display: inline-flex;
}
#exportModal .export-format-option input[type="radio"]:checked ~ .export-format-card .format-selected-label {
    visibility: visible;
    opacity: 1;
}

/* Subtle pop animation on the checkmark when it appears. */
@keyframes badgePop {
    0%   { transform: scale(0.5); opacity: 0; }
    60%  { transform: scale(1.15); opacity: 1; }
    100% { transform: scale(1); opacity: 1; }
}
#exportModal .export-format-option input[type="radio"]:checked ~ .export-format-card .format-check-badge {
    animation: badgePop 0.25s ease-out;
}

#archiveWarningModal .modal-content { max-width: 500px; }
#archiveWarningModal .alert-warning { background-color: rgba(255, 193, 7, 0.1); }
#restoreArchivedModal .alert-info { background-color: rgba(23, 162, 184, 0.1); }
</style>
@endsection