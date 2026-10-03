@extends('layouts.secu')

@section('title', 'Security Personnel Management')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-user-shield text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-users mr-2" style="color: var(--primary);"></i>
                        Security Personnel Management
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Manage security personnel and assign supervisor roles</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-users mr-1"></i>
                        <span>{{ $personnel->total() ?? 0 }} personnel</span>
                        <span class="mx-2">•</span>
                        <span class="px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            <i class="fas fa-user-tie mr-1"></i> Area Supervisor
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <!-- Trash/Deleted Users Button -->
                <a href="{{ route('security.personnel.trash') }}" 
                   class="btn-warning flex items-center px-3 py-2 rounded-lg text-sm" 
                   style="background-color: #6c757d; color: white;"
                   title="View Deleted Personnel">
                    <i class="fas fa-trash-alt mr-2"></i> Deleted Personnel
                    @if(isset($trashStats['total_trashed']) && $trashStats['total_trashed'] > 0)
                    <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-danger text-white">
                        {{ $trashStats['total_trashed'] }}
                    </span>
                    @endif
                </a>
                
                <a href="{{ route('security.personnel.create') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-success">
                    <i class="fas fa-user-plus mr-2"></i> Add Personnel
                </a>
                <a href="{{ route('security.supervisor-assignments.index') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Assignments
                </a>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
    <div class="card p-4 border-l-4" style="border-left-color: var(--success); background-color: rgba(var(--success-rgb), 0.05);">
        <div class="flex items-center flex-wrap gap-2">
            <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
            <span style="color: var(--text-primary);">{{ session('success') }}</span>
            @if(session('invitation_result') && session('invitation_result')['success'] ?? false)
            <span class="ml-2 px-3 py-1 rounded text-sm" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                <i class="fas fa-paper-plane mr-1"></i> 
                Invitation sent via {{ implode(', ', session('invitation_result')['channels_successful'] ?? []) }}
            </span>
            @endif
        </div>
    </div>
    @endif

    @if(session('error'))
    <div class="card p-4 border-l-4" style="border-left-color: var(--danger); background-color: rgba(var(--danger-rgb), 0.05);">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-2" style="color: var(--danger);"></i>
            <span style="color: var(--text-primary);">{{ session('error') }}</span>
        </div>
    </div>
    @endif

    <!-- Info Alert -->
    <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.3);">
        <div class="flex items-start">
            <i class="fas fa-info-circle text-lg mr-3 mt-0.5" style="color: var(--info);"></i>
            <div>
                <h4 class="text-sm font-medium" style="color: var(--text-primary);">Supervisor Assignment Authority</h4>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    As an Area Supervisor, you can assign <strong>Team Lead (Level 1)</strong> and <strong>Section Lead (Level 2)</strong> roles to security personnel.
                    Post Commander (Level 3) and Area Supervisor roles can only be assigned by Administrators.
                </p>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1" style="color: var(--info);"></i>
                    Only personnel marked as <strong>"Eligible"</strong> (<i class="fas fa-check-circle" style="color: var(--success);"></i>) can be assigned as supervisors.
                </p>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $stats['total'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Personnel</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-users" style="color: var(--primary);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $stats['active'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Active</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-user-check" style="color: var(--success);"></i>
                </div>
            </div>
        </div>
        
        <!-- ✅ Eligible Supervisors (can_be_supervisor flag) -->
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $stats['eligible_supervisors'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Eligible Supervisors</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-user-tie" style="color: var(--info);"></i>
                </div>
            </div>
        </div>

        <!-- ❌ REMOVED: Team Leads Card -->
        <!-- ❌ REMOVED: Section Leads Card -->

        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $stats['unassigned'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Unassigned</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-user-clock" style="color: var(--warning);"></i>
                </div>
            </div>
        </div>

        <!-- Invitation Stats -->
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $invitationStats['pending'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Pending Invitations</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-paper-plane" style="color: var(--info);"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="card p-4">
        <div class="flex flex-wrap items-center gap-2 border-b pb-3" style="border-color: var(--border-color);">
            <a href="{{ route('security.personnel.index', array_merge(request()->except(['tab', 'page']), ['tab' => 'assigned'])) }}" 
               class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 {{ $tab == 'assigned' ? 'bg-primary text-white' : '' }}"
               style="{{ $tab == 'assigned' ? '' : 'color: var(--text-secondary); background-color: var(--bg-secondary);' }}">
                <i class="fas fa-user-check mr-2"></i> Assigned to My Posts
                <span class="ml-1 px-2 py-0.5 rounded-full text-xs" 
                      style="{{ $tab == 'assigned' ? 'background-color: rgba(255,255,255,0.2); color: white;' : 'background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);' }}">
                    {{ $stats['active'] ?? 0 }}
                </span>
            </a>
            
            <a href="{{ route('security.personnel.index', array_merge(request()->except(['tab', 'page']), ['tab' => 'unassigned'])) }}" 
               class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 {{ $tab == 'unassigned' ? 'bg-warning text-white' : '' }}"
               style="{{ $tab == 'unassigned' ? 'background-color: var(--warning); color: white;' : 'color: var(--text-secondary); background-color: var(--bg-secondary);' }}">
                <i class="fas fa-user-plus mr-2"></i> Unassigned Personnel
                <span class="ml-1 px-2 py-0.5 rounded-full text-xs" 
                      style="{{ $tab == 'unassigned' ? 'background-color: rgba(255,255,255,0.2); color: white;' : 'background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);' }}">
                    {{ $stats['unassigned'] ?? 0 }}
                </span>
            </a>
            
            <a href="{{ route('security.personnel.index', array_merge(request()->except(['tab', 'page']), ['tab' => 'all'])) }}" 
               class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 {{ $tab == 'all' ? 'bg-info text-white' : '' }}"
               style="{{ $tab == 'all' ? 'background-color: var(--info); color: white;' : 'color: var(--text-secondary); background-color: var(--bg-secondary);' }}">
                <i class="fas fa-users mr-2"></i> All Personnel
                <span class="ml-1 px-2 py-0.5 rounded-full text-xs" 
                      style="{{ $tab == 'all' ? 'background-color: rgba(255,255,255,0.2); color: white;' : 'background-color: rgba(var(--info-rgb), 0.1); color: var(--info);' }}">
                    {{ $stats['total'] ?? 0 }}
                </span>
            </a>

            <!-- Invitations Tab -->
            <a href="{{ route('security.personnel.index', array_merge(request()->except(['tab', 'page']), ['tab' => 'invitations'])) }}" 
               class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 {{ $tab == 'invitations' ? 'bg-info text-white' : '' }}"
               style="{{ $tab == 'invitations' ? 'background-color: var(--info); color: white;' : 'color: var(--text-secondary); background-color: var(--bg-secondary);' }}">
                <i class="fas fa-paper-plane mr-2"></i> Invitations
                <span class="ml-1 px-2 py-0.5 rounded-full text-xs" 
                      style="{{ $tab == 'invitations' ? 'background-color: rgba(255,255,255,0.2); color: white;' : 'background-color: rgba(var(--info-rgb), 0.1); color: var(--info);' }}">
                    {{ $invitationStats['pending'] ?? 0 }}
                </span>
            </a>

            <!-- Deleted/Trash Tab -->
            <a href="{{ route('security.personnel.trash') }}" 
               class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200"
               style="color: var(--text-secondary); background-color: var(--bg-secondary);">
                <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> Deleted
                @if(($trashStats['total_trashed'] ?? 0) > 0)
                <span class="ml-1 px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                    {{ $trashStats['total_trashed'] }}
                </span>
                @endif
            </a>

            <div class="flex-1"></div>
            
            <!-- Eligible Supervisors count badge -->
            <span class="text-xs px-3 py-1 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                <i class="fas fa-user-check mr-1"></i> 
                Eligible Supervisors: {{ $stats['eligible_supervisors'] ?? 0 }}
            </span>
        </div>
        
        @if($tab == 'all')
            <div class="mt-3 text-xs flex items-center" style="color: var(--text-secondary);">
                <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                <span>Showing all security personnel. Eligible supervisors are marked with <i class="fas fa-check-circle" style="color: var(--success);"></i>.</span>
            </div>
        @elseif($tab == 'unassigned')
            <div class="mt-3 text-xs flex items-center" style="color: var(--text-secondary);">
                <i class="fas fa-info-circle mr-2" style="color: var(--warning);"></i>
                <span>Showing personnel without post assignments. These may have been added by administrators or system users.</span>
            </div>
        @elseif($tab == 'invitations')
            <div class="mt-3 text-xs flex items-center" style="color: var(--text-secondary);">
                <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                <span>Showing personnel with pending or failed invitations. Use the 
                <i class="fas fa-paper-plane ml-1" style="color: var(--info);"></i> button to resend invitations.</span>
            </div>
        @endif
    </div>

    <!-- Filters Card -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
            <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filter Personnel
        </h3>
        
        <form method="GET" action="{{ route('security.personnel.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <input type="hidden" name="tab" value="{{ $tab ?? 'assigned' }}">
            
            <div>
                <label for="search" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-search mr-1" style="color: var(--primary);"></i> Search
                </label>
                <input type="text"
                       id="search"
                       name="search"
                       class="index-custom-input w-full"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                       placeholder="Search by name or email..."
                       value="{{ request('search') }}">
            </div>
            
            <!-- Filter by Eligibility (can_be_supervisor) -->
            <div>
                <label for="can_be_supervisor" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-user-check mr-1" style="color: var(--primary);"></i> Supervisor Eligibility
                </label>
                <select id="can_be_supervisor"
                        name="can_be_supervisor"
                        class="index-custom-dropdown w-full"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="">All</option>
                    <option value="yes" {{ request('can_be_supervisor') == 'yes' ? 'selected' : '' }}>Eligible</option>
                    <option value="no" {{ request('can_be_supervisor') == 'no' ? 'selected' : '' }}>Not Eligible</option>
                </select>
            </div>
            
            <div>
                <label for="security_post_id" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-building mr-1" style="color: var(--primary);"></i> Security Post
                </label>
                <select id="security_post_id"
                        name="security_post_id"
                        class="index-custom-dropdown w-full"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="">All Posts</option>
                    @foreach($posts as $post)
                        <option value="{{ $post->id }}" {{ request('security_post_id') == $post->id ? 'selected' : '' }}>
                            {{ $post->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            <div>
                <label for="status" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-circle mr-1" style="color: var(--primary);"></i> Status
                </label>
                <select id="status"
                        name="status"
                        class="index-custom-dropdown w-full"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="">All Status</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                    <option value="suspended" {{ request('status') == 'suspended' ? 'selected' : '' }}>Suspended</option>
                </select>
            </div>
            
            <div class="flex items-end space-x-3">
                <a href="{{ route('security.personnel.index', ['tab' => $tab ?? 'assigned']) }}"
                   class="btn-secondary px-6 py-2.5 rounded-lg text-sm font-medium inline-flex items-center">
                    <i class="fas fa-times mr-2"></i> Clear
                </a>
                <button type="submit"
                        class="btn-primary px-6 py-2.5 rounded-lg text-sm font-medium text-white inline-flex items-center">
                    <i class="fas fa-filter mr-2"></i> Apply
                </button>
            </div>
        </form>
    </div>

    <!-- Personnel Table -->
    <div class="card">
        <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-list mr-2" style="color: var(--primary);"></i> Security Personnel
                <span class="ml-3 px-2.5 py-1 text-xs rounded-full badge-info">
                    {{ $personnel->total() }} total
                </span>
                @if($tab == 'unassigned')
                    <span class="ml-2 px-2.5 py-1 text-xs rounded-full badge-warning">
                        <i class="fas fa-user-plus mr-1"></i> Needs Assignment
                    </span>
                @endif
                @if($tab == 'invitations')
                    <span class="ml-2 px-2.5 py-1 text-xs rounded-full badge-info">
                        <i class="fas fa-paper-plane mr-1"></i> Invitation Management
                    </span>
                @endif
            </h3>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-clock mr-1"></i> Last updated: {{ now()->format('H:i') }}
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Personnel</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Contact</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Post</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Eligibility</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Role</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Invitation</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($personnel as $person)
                        @php
                            // ============================================
                            // POST ASSIGNMENT STATUS
                            // ============================================
                            $postName = 'Not Assigned';
                            $postId = null;
                            $isAssigned = false;
                            
                            // 1. PRIMARY SOURCE: Check direct security_post_id field
                            if (!empty($person->security_post_id)) {
                                $postId = $person->security_post_id;
                                $directPost = \App\Models\SecurityPost::find($postId);
                                if ($directPost) {
                                    $postName = $directPost->name;
                                    $isAssigned = true;
                                }
                            }
                            
                            // 2. SECONDARY SOURCE: Check ACTIVE schedules
                            if (empty($postId) && $person->schedules && $person->schedules->isNotEmpty()) {
                                $activeSchedule = $person->schedules->filter(function($schedule) {
                                    return in_array($schedule->status, ['active', 'scheduled']);
                                })->first();
                                
                                if ($activeSchedule && $activeSchedule->security_post_id) {
                                    $postId = $activeSchedule->security_post_id;
                                    $post = \App\Models\SecurityPost::find($postId);
                                    if ($post) {
                                        $postName = $post->name;
                                        $isAssigned = true;
                                    }
                                }
                            }
                            
                            // 3. TERTIARY SOURCE: Check ACTIVE supervisor assignments
                            if (empty($postId) && $person->supervisorAssignments && $person->supervisorAssignments->isNotEmpty()) {
                                $activeAssignment = $person->supervisorAssignments->filter(function($assignment) {
                                    return $assignment->is_active === true;
                                })->first();
                                
                                if ($activeAssignment && $activeAssignment->security_post_id) {
                                    $postId = $activeAssignment->security_post_id;
                                    $post = \App\Models\SecurityPost::find($postId);
                                    if ($post) {
                                        $postName = $post->name;
                                        $isAssigned = true;
                                    }
                                }
                            }
                            
                            $isAssigned = !empty($postId) && $postName !== 'Not Assigned';
                            
                            // ============================================
                            // SUPERVISOR STATUS using can_be_supervisor flag
                            // ============================================
                            $isEligible = $person->can_be_supervisor ?? false;
                            
                            // Check if user has an active supervisor assignment
                            $hasSupervisorAssignment = $person->supervisorAssignments && 
                                $person->supervisorAssignments->filter(function($a) { 
                                    return $a->is_active === true; 
                                })->isNotEmpty();
                            
                            // Get the active assignment type
                            $supervisorRole = null;
                            $levelName = 'Not a Supervisor';
                            $levelColor = 'secondary';
                            
                            if ($hasSupervisorAssignment) {
                                $activeAssignment = $person->supervisorAssignments->filter(function($a) {
                                    return $a->is_active === true;
                                })->first();
                                
                                if ($activeAssignment) {
                                    $supervisorRole = $activeAssignment->supervisor_type;
                                    $roleNames = [
                                        'post_supervisor' => 'Post Supervisor',
                                        'shift_supervisor' => 'Shift Supervisor',
                                        'area_supervisor' => 'Area Supervisor',
                                        'relief_supervisor' => 'Relief Supervisor',
                                        'training_supervisor' => 'Training Supervisor',
                                        'team_lead' => 'Team Lead',
                                        'section_lead' => 'Section Lead',
                                        'post_commander' => 'Post Commander'
                                    ];
                                    $levelName = $roleNames[$supervisorRole] ?? ucfirst(str_replace('_', ' ', $supervisorRole));
                                    
                                    $roleColors = [
                                        'post_supervisor' => 'info',
                                        'shift_supervisor' => 'primary',
                                        'area_supervisor' => 'success',
                                        'relief_supervisor' => 'warning',
                                        'training_supervisor' => 'secondary',
                                        'team_lead' => 'warning',
                                        'section_lead' => 'danger',
                                        'post_commander' => 'danger'
                                    ];
                                    $levelColor = $roleColors[$supervisorRole] ?? 'secondary';
                                }
                            }
                            
                            $statusColors = [
                                'active' => 'success',
                                'pending' => 'warning',
                                'inactive' => 'secondary',
                                'suspended' => 'danger'
                            ];
                            $statusColor = $statusColors[$person->status] ?? 'secondary';
                            
                            // Get creator info
                            $creatorName = 'System';
                            if ($person->created_by) {
                                $creator = \App\Models\User::find($person->created_by);
                                $creatorName = $creator->name ?? 'Unknown';
                            }
                            
                            // Get invitation info
                            $lastInvitation = $person->invitations()->latest()->first();
                            $invitationStatus = 'none';
                            $invitationColor = 'secondary';
                            $invitationIcon = 'fa-times-circle';
                            $invitationText = 'No Invitation';
                            $invitationId = null;
                            
                            if ($lastInvitation) {
                                $invitationId = $lastInvitation->id;
                                $invitationStatus = $lastInvitation->status;
                                
                                switch($invitationStatus) {
                                    case 'sent':
                                        $invitationColor = 'info';
                                        $invitationIcon = 'fa-paper-plane';
                                        $invitationText = 'Sent';
                                        break;
                                    case 'pending':
                                        $invitationColor = 'warning';
                                        $invitationIcon = 'fa-clock';
                                        $invitationText = 'Pending';
                                        break;
                                    case 'accepted':
                                        $invitationColor = 'success';
                                        $invitationIcon = 'fa-check-circle';
                                        $invitationText = 'Accepted';
                                        break;
                                    case 'expired':
                                        $invitationColor = 'danger';
                                        $invitationIcon = 'fa-clock';
                                        $invitationText = 'Expired';
                                        break;
                                    case 'failed':
                                        $invitationColor = 'danger';
                                        $invitationIcon = 'fa-exclamation-circle';
                                        $invitationText = 'Failed';
                                        break;
                                    default:
                                        $invitationColor = 'secondary';
                                        $invitationIcon = 'fa-question-circle';
                                        $invitationText = ucfirst($invitationStatus);
                                }
                            }
                            
                            $canResend = $lastInvitation && 
                                         !in_array($lastInvitation->status, ['accepted']) &&
                                         ($lastInvitation->status == 'failed' || 
                                          $lastInvitation->status == 'expired' ||
                                          $lastInvitation->created_at->diffInHours(now()) > 24);
                            
                            // Phone formatting
                            $formattedPhone = $person->phone;
                            if ($person->phone) {
                                if (preg_match('/^\+233(\d{9})$/', $person->phone, $matches)) {
                                    $formattedPhone = '0' . $matches[1];
                                }
                            }
                            $isPhoneVerified = !is_null($person->phone_verified_at);
                        @endphp
                        <tr class="border-b hover:bg-opacity-50 transition-colors duration-200 {{ !$isAssigned ? 'bg-opacity-5' : '' }}" 
                            style="border-color: var(--border-color); background-color: var(--card-bg); {{ !$isAssigned ? 'background-color: rgba(var(--warning-rgb), 0.03);' : '' }}">
                            
                            <td class="py-4 px-6">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3"
                                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                                        {{ substr($person->name, 0, 2) }}
                                    </div>
                                    <div>
                                        <div class="font-medium flex items-center" style="color: var(--text-primary);">
                                            {{ $person->name }}
                                            @if($isEligible)
                                                <span class="ml-2 px-1.5 py-0.5 text-xs rounded" 
                                                      style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);" 
                                                      title="Eligible for supervisor role">
                                                    <i class="fas fa-check-circle"></i>
                                                </span>
                                            @endif
                                            @if(!$isAssigned)
                                                <span class="ml-2 px-2 py-0.5 text-xs rounded-full" 
                                                      style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                                    <i class="fas fa-user-plus mr-1"></i> Unassigned
                                                </span>
                                            @endif
                                            @if($lastInvitation && $lastInvitation->status == 'failed')
                                                <span class="ml-2 px-2 py-0.5 text-xs rounded-full" 
                                                      style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                                    <i class="fas fa-exclamation-circle mr-1"></i> Invitation Failed
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            <span class="px-1.5 py-0.5 rounded-full text-xs badge-{{ $statusColor }}">
                                                {{ ucfirst($person->status) }}
                                            </span>
                                            <span class="ml-2 text-xs" style="color: var(--text-secondary);">
                                                <i class="fas fa-id-badge mr-1"></i> ID: {{ $person->id }}
                                            </span>
                                            <span class="ml-2 text-xs" style="color: var(--text-secondary);">
                                                <i class="fas fa-user-cog mr-1"></i> By: {{ $creatorName }}
                                            </span>
                                            @if($isAssigned && $postId)
                                                <span class="ml-2 text-xs" style="color: var(--success);">
                                                    <i class="fas fa-check-circle mr-1"></i> Assigned
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            
                            <!-- Contact Column -->
                            <td class="py-4 px-6">
                                <div class="text-sm" style="color: var(--text-primary);">{{ $person->email }}</div>
                                <div class="flex items-center mt-1">
                                    @if($person->phone)
                                        <span class="text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-phone mr-1"></i> 
                                            <span style="color: var(--text-primary);">{{ $formattedPhone }}</span>
                                        </span>
                                        @if($isPhoneVerified)
                                            <i class="fas fa-check-circle ml-1 text-xs text-success" title="Phone verified"></i>
                                        @else
                                            <i class="fas fa-exclamation-triangle ml-1 text-xs text-warning" title="Phone not verified"></i>
                                        @endif
                                    @else
                                        <span class="text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-phone mr-1"></i> No phone
                                        </span>
                                    @endif
                                </div>
                            </td>
                            
                            <!-- Post Column -->
                            <td class="py-4 px-6">
                                @if($isAssigned && $postName !== 'Not Assigned')
                                    <div class="text-sm flex items-center" style="color: var(--text-primary);">
                                        <i class="fas fa-building mr-1" style="color: var(--primary);"></i>
                                        {{ $postName }}
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                                        Assigned
                                    </div>
                                @else
                                    <div class="text-sm flex items-center" style="color: var(--text-secondary);">
                                        <i class="fas fa-exclamation-circle mr-1" style="color: var(--warning);"></i>
                                        Not Assigned
                                    </div>
                                @endif
                            </td>
                            
                            <!-- Eligibility Column -->
                            <td class="py-4 px-6">
                                @if($isEligible)
                                    <span class="px-2.5 py-1.5 text-xs rounded-full badge-success">
                                        <i class="fas fa-check-circle mr-1"></i> Eligible
                                    </span>
                                @else
                                    <span class="px-2.5 py-1.5 text-xs rounded-full badge-secondary">
                                        <i class="fas fa-times-circle mr-1"></i> Not Eligible
                                    </span>
                                @endif
                            </td>
                            
                            <!-- Role Column (based on supervisor assignment) -->
                            <td class="py-4 px-6">
                                @if($hasSupervisorAssignment)
                                    <span class="px-2.5 py-1.5 text-xs rounded-full badge-{{ $levelColor }}">
                                        <i class="fas fa-user-tie mr-1"></i>
                                        {{ $levelName }}
                                    </span>
                                    @if($activeAssignment && $activeAssignment->metadata['assignment_type'] ?? false)
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            {{ $activeAssignment->metadata['assignment_type'] === 'role_only' ? 'Role Only' : 'Post Assigned' }}
                                        </div>
                                    @endif
                                @elseif($isEligible)
                                    <span class="text-sm" style="color: var(--text-secondary);">Awaiting Assignment</span>
                                @else
                                    <span class="text-sm" style="color: var(--text-secondary);">—</span>
                                @endif
                            </td>
                            
                            <!-- Invitation Status Column -->
                            <td class="py-4 px-6">
                                <div class="flex flex-col items-start">
                                    <span class="px-2 py-1 text-xs rounded-full badge-{{ $invitationColor }} inline-flex items-center">
                                        <i class="fas {{ $invitationIcon }} mr-1"></i>
                                        {{ $invitationText }}
                                    </span>
                                    @if($lastInvitation && $lastInvitation->expires_at)
                                        <span class="text-xs mt-1" style="color: var(--text-secondary);">
                                            <i class="fas fa-clock mr-1"></i>
                                            Expires: {{ $lastInvitation->expires_at->format('M j, Y') }}
                                        </span>
                                    @endif
                                    @if($lastInvitation && $lastInvitation->created_at)
                                        <span class="text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-calendar mr-1"></i>
                                            Sent: {{ $lastInvitation->created_at->format('M j, Y') }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            
                            <td class="py-4 px-6">
                                <div class="flex flex-wrap gap-1">
                                    <!-- View Details Button -->
                                    <button onclick="viewPersonnelDetails({{ $person->id }})"
                                            class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                            style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                            title="View Details">
                                        <i class="fas fa-eye text-sm"></i>
                                    </button>
                                    
                                    <!-- Edit Button -->
                                    <button onclick="openEditModal({{ $person->id }})"
                                            class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                            style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);"
                                            title="Edit Personnel">
                                        <i class="fas fa-edit text-sm"></i>
                                    </button>
                                    
                                    <!-- Dynamic Assign/Unassign Button -->
                                    @if($isAssigned)
                                        <button onclick="unassignPersonnel({{ $person->id }}, '{{ addslashes($person->name) }}')"
                                                class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                                                title="Unassign from Post">
                                            <i class="fas fa-times-circle text-sm"></i>
                                        </button>
                                    @else
                                        <button onclick="quickAssign({{ $person->id }}, '{{ addslashes($person->name) }}')"
                                                class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                                style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);"
                                                title="Assign to Post">
                                            <i class="fas fa-check-circle text-sm"></i>
                                        </button>
                                    @endif
                                    
                                    <!-- Resend Invitation Button -->
                                    @if($canResend || !$lastInvitation)
                                        <button onclick="resendInvitation({{ $person->id }}, '{{ addslashes($person->name) }}')"
                                                class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                                style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                                title="Send/Resend Invitation">
                                            <i class="fas fa-paper-plane text-sm"></i>
                                        </button>
                                    @endif
                                    
                                    <!-- Assign Supervisor Button (only if eligible and no active assignment) -->
                                    @if($isEligible && !$hasSupervisorAssignment)
                                        <a href="{{ route('security.personnel.supervisor.assign', $person->id) }}"
                                           class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                           style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);"
                                           title="Assign as Supervisor">
                                            <i class="fas fa-user-tie text-sm"></i>
                                        </a>
                                    @elseif($hasSupervisorAssignment && $supervisorRole !== 'post_commander' && $supervisorRole !== 'area_supervisor')
                                        <a href="{{ route('security.personnel.supervisor.assign', $person->id) }}"
                                           class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                           title="Edit Supervisor Role">
                                            <i class="fas fa-user-edit text-sm"></i>
                                        </a>
                                    @endif
                                    
                                    <!-- Remove Supervisor Button -->
                                    @if($hasSupervisorAssignment && $supervisorRole !== 'post_commander' && $supervisorRole !== 'area_supervisor')
                                        <button onclick="removeSupervisor({{ $person->id }}, '{{ addslashes($person->name) }}')"
                                                class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                                                title="Remove Supervisor Role">
                                            <i class="fas fa-user-slash text-sm"></i>
                                        </button>
                                    @endif
                                    
                                    <!-- Delete Button -->
                                    @if($person->id !== auth()->id())
                                        <button onclick="deletePersonnel({{ $person->id }}, '{{ addslashes($person->name) }}')"
                                                class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                                                title="Delete Personnel">
                                            <i class="fas fa-trash-alt text-sm"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="w-20 h-20 rounded-full flex items-center justify-center mb-4"
                                         style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                        <i class="fas fa-user-shield text-3xl" style="color: var(--text-secondary);"></i>
                                    </div>
                                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No Security Personnel Found</h4>
                                    <p class="text-sm mb-4" style="color: var(--text-secondary);">
                                        @if($tab == 'unassigned')
                                            All security personnel have been assigned to posts. 
                                            <a href="{{ route('security.personnel.index', ['tab' => 'assigned']) }}" 
                                               style="color: var(--primary);" class="hover:underline">
                                                View assigned personnel
                                            </a>
                                        @elseif($tab == 'all')
                                            No security personnel have been added yet.
                                        @elseif($tab == 'invitations')
                                            No invitations have been sent or all invitations have been accepted.
                                        @else
                                            No security personnel are currently assigned to your posts.
                                        @endif
                                    </p>
                                    <a href="{{ route('security.personnel.create') }}" class="btn-primary px-4 py-2 rounded-lg">
                                        <i class="fas fa-user-plus mr-2"></i> Add Personnel
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if(method_exists($personnel, 'links'))
            <div class="p-6 border-t" style="border-color: var(--border-color);">
                {{ $personnel->appends(request()->query())->links() }}
            </div>
        @endif
    </div>
</div>

<!-- ==================== MODALS ==================== -->

<!-- Remove Supervisor Confirmation Modal -->
<div id="removeSupervisorModal" class="fixed inset-0 z-50 hidden overflow-y-auto" style="background-color: rgba(0,0,0,0.5);">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="card w-full max-w-md">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Remove Supervisor Role</h3>
            </div>
            <div class="p-6">
                <p style="color: var(--text-primary);" id="removeSupervisorName"></p>
                <div class="mt-3 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                    <p class="text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i>
                        This will remove the supervisor role from this personnel. They will no longer appear in supervisor assignment dropdowns.
                        Any active supervisor assignments will be deactivated.
                    </p>
                </div>
            </div>
            <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                <button type="button" onclick="closeRemoveModal()" class="btn-secondary px-4 py-2 rounded-lg">Cancel</button>
                <form id="removeSupervisorForm" method="POST" action="" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger px-4 py-2 rounded-lg">
                        <i class="fas fa-user-slash mr-2"></i> Remove Supervisor
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Quick Assign Modal -->
<div id="quickAssignModal" class="fixed inset-0 z-50 hidden overflow-y-auto" style="background-color: rgba(0,0,0,0.5);">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="card w-full max-w-md">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Quick Assign to Post</h3>
                </div>
            </div>
            <div class="p-6">
                <p style="color: var(--text-primary);" id="quickAssignName" class="mb-4"></p>
                <div class="mt-4">
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-building mr-1" style="color: var(--primary);"></i> Select Security Post
                    </label>
                    <select id="quickAssignPostId" 
                            class="index-custom-dropdown w-full"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color); padding: 10px 12px; border-radius: 8px;">
                        <option value="">-- Select a post --</option>
                        @foreach($posts as $post)
                            <option value="{{ $post->id }}">{{ $post->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs mt-2" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1" style="color: var(--info);"></i>
                        Assigning to a post will make this personnel visible in your assigned personnel view.
                    </p>
                </div>
            </div>
            <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                <button type="button" onclick="closeQuickAssignModal()" class="btn-secondary px-4 py-2 rounded-lg">
                    <i class="fas fa-times mr-2"></i> Cancel
                </button>
                <button type="button" onclick="submitQuickAssign()" class="btn-success px-4 py-2 rounded-lg">
                    <i class="fas fa-check mr-2"></i> Assign to Post
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Unassign Personnel Modal -->
<div id="unassignPersonnelModal" class="fixed inset-0 z-50 hidden overflow-y-auto" style="background-color: rgba(0,0,0,0.5);">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="card w-full max-w-md">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2" style="color: var(--danger);"></i>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Unassign from Post</h3>
                </div>
            </div>
            <div class="p-6">
                <p style="color: var(--text-primary);" id="unassignPersonnelName" class="mb-4"></p>
                
                <div class="p-3 rounded-lg mb-4" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                    <p class="text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i>
                        This will remove the personnel from their current post assignment. 
                        They will become unassigned and can be reassigned later.
                    </p>
                </div>
                
                <div class="mt-4">
                    <label for="unassignment_reason" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Reason for Unassignment (Optional)
                    </label>
                    <textarea id="unassignment_reason" rows="2" 
                              class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                              placeholder="Enter reason for unassignment..."></textarea>
                </div>
            </div>
            <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                <button type="button" onclick="closeUnassignModal()" class="btn-secondary px-4 py-2 rounded-lg">
                    <i class="fas fa-times mr-2"></i> Cancel
                </button>
                <button type="button" onclick="confirmUnassign()" class="btn-danger px-4 py-2 rounded-lg">
                    <i class="fas fa-user-minus mr-2"></i> Unassign Personnel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Resend Invitation Modal -->
<div id="resendInvitationModal" class="fixed inset-0 z-50 hidden overflow-y-auto" style="background-color: rgba(0,0,0,0.5);">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="card w-full max-w-md">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <div class="flex items-center">
                    <i class="fas fa-paper-plane mr-2" style="color: var(--info);"></i>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Send Invitation</h3>
                </div>
            </div>
            <div class="p-6">
                <p style="color: var(--text-primary);" id="resendInvitationName" class="mb-4"></p>
                
                <div class="space-y-4">
                    <!-- Invitation Type -->
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            <i class="fas fa-tag mr-1" style="color: var(--primary);"></i> Invitation Type
                        </label>
                        <select id="resendInvitationType" 
                                class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="welcome">Welcome Invitation</option>
                            <option value="registration">Registration Invitation</option>
                            <option value="account_setup">Account Setup</option>
                            <option value="password_setup">Password Setup</option>
                            <option value="security_orientation">Security Orientation</option>
                            <option value="security_training">Security Training</option>
                        </select>
                    </div>
                    
                    <!-- Channels -->
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            <i class="fas fa-share-alt mr-1" style="color: var(--primary);"></i> Channels
                        </label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" id="resendChannelEmail" value="email" checked
                                       class="w-4 h-4 rounded focus:ring-primary" style="color: var(--primary);">
                                <span class="ml-2 text-sm" style="color: var(--text-primary);">Email</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="resendChannelSms" value="sms"
                                       class="w-4 h-4 rounded focus:ring-primary" style="color: var(--primary);"
                                       {{ ($smsStatus['system_ready'] ?? false) ? '' : 'disabled' }}>
                                <span class="ml-2 text-sm" style="color: var(--text-primary);">
                                    SMS 
                                    <span class="text-xs" style="color: var(--text-secondary);">
                                        {{ ($smsStatus['system_ready'] ?? false) ? '✅' : '⚠️ Unavailable' }}
                                    </span>
                                </span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="resendChannelWhatsapp" value="whatsapp"
                                       class="w-4 h-4 rounded focus:ring-primary" style="color: var(--primary);"
                                       {{ ($whatsappStatus['system_ready'] ?? false) ? '' : 'disabled' }}>
                                <span class="ml-2 text-sm" style="color: var(--text-primary);">
                                    WhatsApp 
                                    <span class="text-xs" style="color: var(--text-secondary);">
                                        {{ ($whatsappStatus['system_ready'] ?? false) ? '✅' : '⚠️ Unavailable' }}
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Custom Message -->
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            <i class="fas fa-comment mr-1" style="color: var(--primary);"></i> Custom Message (Optional)
                        </label>
                        <textarea id="resendCustomMessage" 
                                  class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  rows="3" 
                                  placeholder="Add a custom message for the invitation..."></textarea>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            <span id="resendCharCount">0</span>/1000 characters
                        </div>
                    </div>
                    
                    <!-- Expiration -->
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            <i class="fas fa-clock mr-1" style="color: var(--primary);"></i> Expiration
                        </label>
                        <select id="resendExpiresInDays" 
                                class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="1">1 Day</option>
                            <option value="3">3 Days</option>
                            <option value="7" selected>7 Days (Default)</option>
                            <option value="14">14 Days</option>
                            <option value="30">30 Days</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                <button type="button" onclick="closeResendInvitationModal()" class="btn-secondary px-4 py-2 rounded-lg">
                    <i class="fas fa-times mr-2"></i> Cancel
                </button>
                <button type="button" onclick="submitResendInvitation()" class="btn-success px-4 py-2 rounded-lg" id="resendSubmitBtn">
                    <i class="fas fa-paper-plane mr-2"></i> Send Invitation
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Personnel Details Modal -->
<div id="personnelDetailsModal" class="fixed inset-0 z-50 hidden overflow-y-auto" style="background-color: rgba(0,0,0,0.5);">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="card w-full max-w-3xl">
            <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                <div class="flex items-center">
                    <i class="fas fa-user-shield mr-3 text-xl" style="color: var(--primary);"></i>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);" id="detailsModalTitle">Personnel Details</h3>
                </div>
                <button onclick="closeDetailsModal()" class="text-gray-500 hover:text-gray-700 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <!-- Loading State -->
                <div id="detailsLoading" class="text-center py-8">
                    <div class="spinner-large mx-auto mb-4"></div>
                    <p style="color: var(--text-secondary);">Loading personnel details...</p>
                </div>
                
                <!-- Content -->
                <div id="detailsContent" class="hidden">
                    <!-- Profile Header -->
                    <div class="flex items-center mb-6 pb-6 border-b" style="border-color: var(--border-color);">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center text-2xl font-bold text-white"
                             style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);"
                             id="detailsAvatar">
                            BE
                        </div>
                        <div class="ml-4">
                            <h4 class="text-xl font-semibold" style="color: var(--text-primary);" id="detailsName">-</h4>
                            <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                                <span id="detailsStatus" class="px-2 py-0.5 rounded-full text-xs">-</span>
                                <span class="mx-2">•</span>
                                <span id="detailsType" class="px-2 py-0.5 rounded-full text-xs">-</span>
                                <span class="mx-2">•</span>
                                <span><i class="fas fa-id-badge mr-1"></i> ID: <span id="detailsId">-</span></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Info Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Contact Information -->
                        <div>
                            <h5 class="text-sm font-semibold mb-3" style="color: var(--text-secondary);">
                                <i class="fas fa-address-card mr-2" style="color: var(--primary);"></i> Contact Information
                            </h5>
                            <div class="space-y-2">
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-envelope w-5" style="color: var(--primary);"></i>
                                    <span style="color: var(--text-primary);" id="detailsEmail">-</span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-phone w-5" style="color: var(--primary);"></i>
                                    <span style="color: var(--text-primary);" id="detailsPhone">-</span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-user w-5" style="color: var(--primary);"></i>
                                    <span style="color: var(--text-primary);" id="detailsUsername">-</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Supervisor Information -->
                        <div>
                            <h5 class="text-sm font-semibold mb-3" style="color: var(--text-secondary);">
                                <i class="fas fa-user-tie mr-2" style="color: var(--primary);"></i> Supervisor Information
                            </h5>
                            <div class="space-y-2">
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-check-circle w-5" style="color: var(--success);"></i>
                                    <span style="color: var(--text-primary);" id="detailsSupervisorStatus">-</span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-user-tie w-5" style="color: var(--primary);"></i>
                                    <span style="color: var(--text-primary);" id="detailsRole">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Post Assignment -->
                    <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                        <h5 class="text-sm font-semibold mb-3" style="color: var(--text-secondary);">
                            <i class="fas fa-building mr-2" style="color: var(--primary);"></i> Post Assignment
                        </h5>
                        <div id="detailsPosts" class="flex flex-wrap gap-2">
                            <span class="text-sm" style="color: var(--text-secondary);">No posts assigned</span>
                        </div>
                    </div>
                    
                    <!-- Assignment History -->
                    <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                        <h5 class="text-sm font-semibold mb-3" style="color: var(--text-secondary);">
                            <i class="fas fa-history mr-2" style="color: var(--primary);"></i> Assignment History
                        </h5>
                        <div id="detailsHistory" class="text-sm" style="color: var(--text-secondary);">
                            No assignment history available.
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                <button onclick="closeDetailsModal()" class="btn-secondary px-4 py-2 rounded-lg">
                    <i class="fas fa-times mr-2"></i> Close
                </button>
                <button onclick="openEditModalFromDetails()" class="btn-primary px-4 py-2 rounded-lg inline-flex items-center">
                    <i class="fas fa-edit mr-2"></i> Edit Personnel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Personnel Modal -->
<div id="editPersonnelModal" class="fixed inset-0 z-50 hidden overflow-y-auto" style="background-color: rgba(0,0,0,0.5);">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="card w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b flex justify-between items-center sticky top-0 z-10" style="border-color: var(--border-color); background-color: var(--card-bg);">
                <div class="flex items-center">
                    <i class="fas fa-edit mr-3 text-xl" style="color: var(--primary);"></i>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Edit Personnel</h3>
                </div>
                <button onclick="closeEditModal()" class="text-gray-500 hover:text-gray-700 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <!-- Loading State -->
                <div id="editLoading" class="text-center py-8">
                    <div class="spinner-large mx-auto mb-4"></div>
                    <p style="color: var(--text-secondary);">Loading personnel data...</p>
                </div>
                
                <!-- Edit Form -->
                <form id="editPersonnelForm" method="POST" action="">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="editUserId" name="user_id">
                    
                    <div id="editContent" class="hidden">
                        <!-- Basic Info -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">Full Name *</label>
                                <input type="text" id="editName" name="name" 
                                       class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">Email *</label>
                                <input type="email" id="editEmail" name="email" 
                                       class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       required>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">Phone</label>
                                <input type="tel" id="editPhone" name="phone" 
                                       class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">Username</label>
                                <input type="text" id="editUsername" name="username" 
                                       class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">Status</label>
                                <select id="editStatus" name="status" 
                                        class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                    <option value="active">Active</option>
                                    <option value="pending">Pending</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">Security Post</label>
                                <select id="editSecurityPost" name="security_post_id" 
                                        class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                    <option value="">Select Post</option>
                                    @foreach($posts as $post)
                                        <option value="{{ $post->id }}">{{ $post->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        
                        <!-- Supervisor Settings (Simplified) -->
                        <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                            <div class="flex items-center justify-between mb-3">
                                <h5 class="text-sm font-semibold" style="color: var(--text-secondary);">
                                    <i class="fas fa-user-tie mr-2" style="color: var(--primary);"></i> Supervisor Settings
                                </h5>
                                <label class="flex items-center cursor-pointer">
                                    <span class="text-sm mr-2" style="color: var(--text-secondary);">Can be Supervisor</span>
                                    <input type="checkbox" id="editCanBeSupervisor" name="can_be_supervisor" value="1" 
                                           class="w-4 h-4 rounded focus:ring-primary" style="color: var(--primary);"
                                           onchange="toggleSupervisorFields()">
                                </label>
                            </div>
                            
                            <div class="text-xs mb-3" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1" style="color: var(--info);"></i>
                                Enabling this makes the user eligible for supervisor assignments.
                                Actual supervisor roles (Team Lead, Section Lead, etc.) are assigned separately.
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 pt-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                        <button type="button" onclick="closeEditModal()" class="btn-secondary px-4 py-2 rounded-lg">
                            <i class="fas fa-times mr-2"></i> Cancel
                        </button>
                        <button type="submit" class="btn-primary px-4 py-2 rounded-lg">
                            <i class="fas fa-save mr-2"></i> Update Personnel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Personnel Modal -->
<div id="deletePersonnelModal" class="fixed inset-0 z-50 hidden overflow-y-auto" style="background-color: rgba(0,0,0,0.5);">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="card w-full max-w-md">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2" style="color: var(--danger);"></i>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Delete Security Personnel</h3>
                </div>
            </div>
            <div class="p-6">
                <p style="color: var(--text-primary);" id="deletePersonnelName" class="mb-4"></p>
                
                <div id="criticalRelationsWarning" class="hidden p-3 rounded-lg mb-4" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <div class="flex items-start">
                        <i class="fas fa-ban mr-2 mt-0.5" style="color: var(--danger);"></i>
                        <div>
                            <p class="font-medium" style="color: var(--danger);">Cannot Delete:</p>
                            <ul id="criticalRelationsList" class="text-sm mt-1 space-y-1" style="color: var(--text-secondary);"></ul>
                            <p class="text-sm mt-2" style="color: var(--text-secondary);">Please resolve these dependencies before deleting.</p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                    <p class="text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i>
                        This will soft-delete the personnel. They will be moved to trash and can be restored later.
                        Any active assignments and schedules will be deactivated.
                    </p>
                </div>
                
                <div class="mt-4">
                    <label for="deletion_reason" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Reason for Deletion (Optional)
                    </label>
                    <textarea id="deletion_reason" rows="2" 
                              class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                              placeholder="Enter reason for deletion..."></textarea>
                </div>
            </div>
            <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                <button type="button" onclick="closeDeletePersonnelModal()" class="btn-secondary px-4 py-2 rounded-lg">
                    <i class="fas fa-times mr-2"></i] Cancel
                </button>
                <button type="button" onclick="confirmDeletePersonnel()" class="btn-danger px-4 py-2 rounded-lg" id="deleteConfirmBtn">
                    <i class="fas fa-trash-alt mr-2"></i> Delete Personnel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== STYLES ==================== -->
<style>
.action-btn {
    transition: all 0.2s ease;
}
.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}
tbody tr {
    transition: background-color 0.2s ease;
}
tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.02) !important;
}
.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border: none;
    transition: all 0.2s ease;
    color: white;
}
.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}
.btn-secondary {
    background-color: var(--bg-secondary);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    transition: all 0.2s ease;
}
.btn-secondary:hover {
    background-color: var(--border-color);
}
.btn-success {
    background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
    border: none;
    transition: all 0.2s ease;
    color: white;
}
.btn-success:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--success-rgb), 0.3);
}
.btn-danger {
    background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
    border: none;
    transition: all 0.2s ease;
    color: white;
}
.btn-danger:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--danger-rgb), 0.3);
}
.badge-success { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3); }
.badge-warning { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3); }
.badge-danger { background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3); }
.badge-info { background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3); }
.badge-primary { background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3); }
.badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3); }
.index-custom-dropdown, .index-custom-input {
    transition: all 0.2s ease;
}
.index-custom-dropdown:focus, .index-custom-input:focus {
    border-color: var(--primary) !important;
    outline: none;
    box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.2);
}
.spinner-large {
    display: inline-block;
    width: 3rem;
    height: 3rem;
    border: 3px solid rgba(var(--primary-rgb), 0.2);
    border-radius: 50%;
    border-top-color: var(--primary);
    animation: spin 0.6s linear infinite;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
/* Modal scroll styling */
#editPersonnelModal .card {
    max-height: 95vh;
}
#editPersonnelModal .overflow-y-auto {
    scrollbar-width: thin;
}
#editPersonnelModal .overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}
#editPersonnelModal .overflow-y-auto::-webkit-scrollbar-track {
    background: var(--bg-secondary);
    border-radius: 3px;
}
#editPersonnelModal .overflow-y-auto::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 3px;
}
</style>

<!-- ==================== JAVASCRIPT ==================== -->
<script>
// ==================== REMOVE SUPERVISOR MODAL ====================
function closeRemoveModal() {
    document.getElementById('removeSupervisorModal').classList.add('hidden');
}

function removeSupervisor(id, name) {
    document.getElementById('removeSupervisorName').innerHTML = `Remove supervisor role from <strong>${name}</strong>?`;
    document.getElementById('removeSupervisorForm').action = `/security/personnel/${id}/supervisor/remove`;
    document.getElementById('removeSupervisorModal').classList.remove('hidden');
}

// ==================== QUICK ASSIGN MODAL ====================
let quickAssignUserId = null;

function quickAssign(id, name) {
    quickAssignUserId = id;
    document.getElementById('quickAssignName').innerHTML = `
        <i class="fas fa-user mr-2" style="color: var(--primary);"></i>
        Assign <strong>${name}</strong> to a security post:
    `;
    document.getElementById('quickAssignPostId').value = '';
    document.getElementById('quickAssignModal').classList.remove('hidden');
}

function closeQuickAssignModal() {
    document.getElementById('quickAssignModal').classList.add('hidden');
    quickAssignUserId = null;
}

function submitQuickAssign() {
    const postId = document.getElementById('quickAssignPostId').value;
    
    if (!postId) {
        const select = document.getElementById('quickAssignPostId');
        select.style.borderColor = 'var(--danger)';
        select.style.boxShadow = '0 0 0 2px rgba(var(--danger-rgb), 0.2)';
        setTimeout(() => {
            select.style.borderColor = '';
            select.style.boxShadow = '';
        }, 3000);
        showToast('warning', 'Please select a security post to assign.');
        return;
    }
    
    const submitBtn = document.querySelector('#quickAssignModal .btn-success');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Assigning...';
    submitBtn.disabled = true;
    
    fetch(`/security/personnel/${quickAssignUserId}/quick-assign-post`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ security_post_id: postId })
    })
    .then(response => {
        return response.text().then(text => {
            if (text.charCodeAt(0) === 0xFEFF) {
                text = text.slice(1);
            }
            text = text.trim();
            try {
                return { status: response.status, data: JSON.parse(text) };
            } catch (e) {
                throw new Error('Invalid JSON response: ' + text.substring(0, 200));
            }
        });
    })
    .then(({ status, data }) => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        
        if (data.success) {
            showToast('success', data.message || 'Personnel assigned successfully!');
            closeQuickAssignModal();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast('error', data.message || 'Failed to assign personnel.');
        }
    })
    .catch(error => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        showToast('error', 'An error occurred: ' + error.message);
        console.error('Quick assign error:', error);
    });
}

// ==================== UNASSIGN PERSONNEL FUNCTIONS ====================
let unassignUserId = null;
let unassignUserName = null;

function unassignPersonnel(id, name) {
    unassignUserId = id;
    unassignUserName = name;
    
    document.getElementById('unassignPersonnelName').innerHTML = `
        <i class="fas fa-user mr-2" style="color: var(--danger);"></i>
        Are you sure you want to unassign <strong>${name}</strong> from their current post?
    `;
    document.getElementById('unassignment_reason').value = '';
    document.getElementById('unassignPersonnelModal').classList.remove('hidden');
}

function closeUnassignModal() {
    document.getElementById('unassignPersonnelModal').classList.add('hidden');
    unassignUserId = null;
    unassignUserName = null;
}

function confirmUnassign() {
    if (!unassignUserId) return;
    
    const reason = document.getElementById('unassignment_reason').value;
    const confirmBtn = document.querySelector('#unassignPersonnelModal .btn-danger');
    const originalText = confirmBtn.innerHTML;
    
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Unassigning...';
    
    fetch(`/security/personnel/${unassignUserId}/unassign-post`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ reason: reason || 'No reason provided' })
    })
    .then(response => {
        return response.text().then(text => {
            if (text.charCodeAt(0) === 0xFEFF) {
                text = text.slice(1);
            }
            text = text.trim();
            try {
                return { status: response.status, data: JSON.parse(text) };
            } catch (e) {
                console.error('JSON Parse Error:', e);
                throw new Error('Invalid JSON response: ' + text.substring(0, 200));
            }
        });
    })
    .then(({ status, data }) => {
        confirmBtn.innerHTML = originalText;
        confirmBtn.disabled = false;
        
        if (data.success) {
            showToast('success', data.message || 'Personnel unassigned successfully!');
            closeUnassignModal();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast('error', data.message || 'Failed to unassign personnel.');
        }
    })
    .catch(error => {
        confirmBtn.innerHTML = originalText;
        confirmBtn.disabled = false;
        showToast('error', 'An error occurred: ' + error.message);
        console.error('Unassign error:', error);
    });
}

// ==================== RESEND INVITATION MODAL ====================
let resendUserId = null;

function resendInvitation(id, name) {
    resendUserId = id;
    document.getElementById('resendInvitationName').innerHTML = `
        <i class="fas fa-user mr-2" style="color: var(--primary);"></i>
        Send invitation to <strong>${name}</strong>
    `;
    document.getElementById('resendInvitationType').value = 'welcome';
    document.getElementById('resendCustomMessage').value = '';
    document.getElementById('resendCharCount').textContent = '0';
    document.getElementById('resendExpiresInDays').value = '7';
    
    // Reset channel checkboxes
    document.getElementById('resendChannelEmail').checked = true;
    document.getElementById('resendChannelSms').checked = false;
    document.getElementById('resendChannelWhatsapp').checked = false;
    
    document.getElementById('resendInvitationModal').classList.remove('hidden');
}

function closeResendInvitationModal() {
    document.getElementById('resendInvitationModal').classList.add('hidden');
    resendUserId = null;
}

function submitResendInvitation() {
    const channels = [];
    if (document.getElementById('resendChannelEmail').checked) channels.push('email');
    if (document.getElementById('resendChannelSms').checked) channels.push('sms');
    if (document.getElementById('resendChannelWhatsapp').checked) channels.push('whatsapp');
    
    if (channels.length === 0) {
        showToast('warning', 'Please select at least one channel.');
        return;
    }
    
    const data = {
        channels: channels,
        invitation_type: document.getElementById('resendInvitationType').value,
        custom_message: document.getElementById('resendCustomMessage').value,
        expires_in_days: parseInt(document.getElementById('resendExpiresInDays').value),
        resend_type: 'selected_channels'
    };
    
    const submitBtn = document.getElementById('resendSubmitBtn');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending...';
    submitBtn.disabled = true;
    
    fetch(`/security/personnel/${resendUserId}/resend-invitation`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        return response.text().then(text => {
            if (text.charCodeAt(0) === 0xFEFF) {
                text = text.slice(1);
            }
            text = text.trim();
            try {
                return { status: response.status, data: JSON.parse(text) };
            } catch (e) {
                console.error('JSON Parse Error:', e);
                console.error('Raw response:', text);
                throw new Error('Invalid JSON response: ' + text.substring(0, 200));
            }
        });
    })
    .then(({ status, data }) => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        
        if (data.success) {
            showToast('success', data.message || 'Invitation sent successfully!');
            closeResendInvitationModal();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast('error', data.message || 'Failed to send invitation.');
            if (data.message && data.message.includes('deleted')) {
                setTimeout(() => window.location.reload(), 3000);
            }
        }
    })
    .catch(error => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        showToast('error', 'An error occurred: ' + error.message);
        console.error('Resend invitation error:', error);
    });
}

// ==================== PERSONNEL DETAILS MODAL ====================
let currentDetailsUserId = null;

function viewPersonnelDetails(userId) {
    currentDetailsUserId = userId;
    const modal = document.getElementById('personnelDetailsModal');
    const loading = document.getElementById('detailsLoading');
    const content = document.getElementById('detailsContent');
    
    modal.classList.remove('hidden');
    loading.classList.remove('hidden');
    content.classList.add('hidden');
    document.body.style.overflow = 'hidden';
    
    fetch(`/security/personnel/${userId}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.text();
    })
    .then(text => {
        if (text.charCodeAt(0) === 0xFEFF) {
            text = text.slice(1);
        }
        text = text.trim();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('JSON Parse Error:', e);
            console.error('Raw response:', text);
            throw new Error('Invalid JSON response');
        }
    })
    .then(data => {
        loading.classList.add('hidden');
        content.classList.remove('hidden');
        populateDetails(data);
    })
    .catch(error => {
        loading.classList.add('hidden');
        content.classList.remove('hidden');
        showToast('error', 'Failed to load personnel details: ' + error.message);
        console.error('Details error:', error);
    });
}

function populateDetails(data) {
    const initials = data.name.split(' ').map(word => word[0]).join('').substring(0, 2).toUpperCase();
    document.getElementById('detailsAvatar').textContent = initials || '??';
    document.getElementById('detailsName').textContent = data.name || 'Unknown';
    document.getElementById('detailsId').textContent = data.id || '-';
    document.getElementById('detailsModalTitle').textContent = `Personnel Details - ${data.name || 'Unknown'}`;
    
    const statusColors = {
        active: 'success',
        pending: 'warning',
        inactive: 'secondary',
        suspended: 'danger'
    };
    const statusColor = statusColors[data.status] || 'secondary';
    document.getElementById('detailsStatus').textContent = data.status ? data.status.charAt(0).toUpperCase() + data.status.slice(1) : '-';
    document.getElementById('detailsStatus').className = `px-2 py-0.5 rounded-full text-xs badge-${statusColor}`;
    
    const typeMap = {
        6: 'Security Personnel',
        0: 'Super Admin',
        1: 'Admin',
        2: 'Landlord',
        3: 'Tenant',
        4: 'Field Agent',
        5: 'Developer',
        7: 'Former Landlord',
        8: 'Contractor'
    };
    const typeName = typeMap[data.type] || 'Unknown';
    document.getElementById('detailsType').textContent = typeName;
    document.getElementById('detailsType').className = `px-2 py-0.5 rounded-full text-xs badge-primary`;
    
    document.getElementById('detailsEmail').textContent = data.email || '-';
    document.getElementById('detailsPhone').textContent = data.phone || '-';
    document.getElementById('detailsUsername').textContent = data.username || '-';
    
    // Supervisor Information
    const isEligible = data.can_be_supervisor || false;
    document.getElementById('detailsSupervisorStatus').textContent = isEligible ? 'Eligible' : 'Not Eligible';
    document.getElementById('detailsSupervisorStatus').style.color = isEligible ? 'var(--success)' : 'var(--text-secondary)';
    
    // Get role from supervisor assignments
    let roleText = 'No active assignment';
    if (data.supervisor_assignments && data.supervisor_assignments.length > 0) {
        const active = data.supervisor_assignments.find(a => a.is_active === true);
        if (active) {
            const roleNames = {
                'post_supervisor': 'Post Supervisor',
                'shift_supervisor': 'Shift Supervisor',
                'area_supervisor': 'Area Supervisor',
                'relief_supervisor': 'Relief Supervisor',
                'training_supervisor': 'Training Supervisor',
                'team_lead': 'Team Lead',
                'section_lead': 'Section Lead',
                'post_commander': 'Post Commander'
            };
            roleText = roleNames[active.supervisor_type] || active.supervisor_type;
        }
    }
    document.getElementById('detailsRole').textContent = roleText;
    
    const postsContainer = document.getElementById('detailsPosts');
    postsContainer.innerHTML = '';
    if (data.posts && data.posts.length > 0) {
        data.posts.forEach(post => {
            const badge = document.createElement('span');
            badge.className = 'px-2 py-1 text-xs rounded-full badge-info';
            badge.textContent = post.name;
            postsContainer.appendChild(badge);
        });
    } else {
        postsContainer.innerHTML = '<span class="text-sm" style="color: var(--text-secondary);">No posts assigned</span>';
    }
    
    const historyContainer = document.getElementById('detailsHistory');
    if (data.supervisor_assignments && data.supervisor_assignments.length > 0) {
        let historyHtml = '<ul class="space-y-1">';
        data.supervisor_assignments.forEach(assignment => {
            const type = assignment.supervisor_type || 'post_supervisor';
            const typeLabel = type.replace('_', ' ').toUpperCase();
            const status = assignment.is_active ? 'Active' : 'Inactive';
            const startDate = assignment.start_date ? new Date(assignment.start_date).toLocaleDateString() : 'N/A';
            const endDate = assignment.end_date ? new Date(assignment.end_date).toLocaleDateString() : 'Ongoing';
            historyHtml += `
                <li class="flex items-center justify-between text-xs py-1 border-b" style="border-color: var(--border-color);">
                    <span style="color: var(--text-primary);">${typeLabel}</span>
                    <span>
                        <span class="px-2 py-0.5 rounded-full ${status === 'Active' ? 'badge-success' : 'badge-secondary'}">
                            ${status}
                        </span>
                    </span>
                    <span style="color: var(--text-secondary);">${startDate} → ${endDate}</span>
                </li>
            `;
        });
        historyHtml += '</ul>';
        historyContainer.innerHTML = historyHtml;
    } else {
        historyContainer.innerHTML = 'No assignment history available.';
    }
}

function openEditModalFromDetails() {
    if (currentDetailsUserId) {
        closeDetailsModal();
        setTimeout(() => openEditModal(currentDetailsUserId), 300);
    }
}

function closeDetailsModal() {
    document.getElementById('personnelDetailsModal').classList.add('hidden');
    document.body.style.overflow = '';
}

// ==================== EDIT PERSONNEL MODAL ====================
let currentEditUserId = null;

function openEditModal(userId) {
    currentEditUserId = userId;
    const modal = document.getElementById('editPersonnelModal');
    const loading = document.getElementById('editLoading');
    const content = document.getElementById('editContent');
    
    modal.classList.remove('hidden');
    loading.classList.remove('hidden');
    content.classList.add('hidden');
    document.body.style.overflow = 'hidden';
    
    document.getElementById('editPersonnelForm').reset();
    document.getElementById('editCanBeSupervisor').checked = false;
    
    fetch(`/security/personnel/${userId}/edit-data`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            });
        }
        return response.text();
    })
    .then(text => {
        if (text.charCodeAt(0) === 0xFEFF) {
            text = text.slice(1);
        }
        text = text.trim();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('JSON Parse Error:', e);
            throw new Error('Invalid JSON response');
        }
    })
    .then(data => {
        loading.classList.add('hidden');
        content.classList.remove('hidden');
        populateEditForm(data);
    })
    .catch(error => {
        console.error('Edit error:', error);
        loading.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-exclamation-circle text-4xl mb-4" style="color: var(--danger);"></i>
                <p style="color: var(--danger); font-weight: 500;">Failed to load personnel data</p>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 8px;">${error.message}</p>
                <button onclick="closeEditModal()" class="btn-secondary px-4 py-2 rounded-lg mt-4">
                    <i class="fas fa-times mr-2"></i> Close
                </button>
                <button onclick="openEditModal(${userId})" class="btn-primary px-4 py-2 rounded-lg mt-4 ml-2">
                    <i class="fas fa-redo mr-2"></i> Retry
                </button>
            </div>
        `;
        showToast('error', 'Failed to load personnel data: ' + error.message);
    });
}

function populateEditForm(data) {
    document.getElementById('editUserId').value = data.id;
    document.getElementById('editName').value = data.name || '';
    document.getElementById('editEmail').value = data.email || '';
    document.getElementById('editPhone').value = data.phone || '';
    document.getElementById('editUsername').value = data.username || '';
    document.getElementById('editStatus').value = data.status || 'active';
    document.getElementById('editSecurityPost').value = data.security_post_id || '';
    
    // Only can_be_supervisor flag
    const isEligible = data.can_be_supervisor || false;
    document.getElementById('editCanBeSupervisor').checked = isEligible;
    
    document.getElementById('editPersonnelForm').action = `/security/personnel/${data.id}`;
}

function toggleSupervisorFields() {
    // Simple toggle with explanation
    const checked = document.getElementById('editCanBeSupervisor').checked;
    if (checked) {
        showToast('info', 'User will be eligible for supervisor assignments.');
    } else {
        showToast('info', 'User will not be eligible for supervisor assignments.');
    }
}

function closeEditModal() {
    document.getElementById('editPersonnelModal').classList.add('hidden');
    document.body.style.overflow = '';
    currentEditUserId = null;
}

document.getElementById('editPersonnelForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Updating...';
    submitBtn.disabled = true;
    
    const formData = new FormData(this);
    const userId = document.getElementById('editUserId').value;
    
    fetch(`/security/personnel/${userId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                if (text.charCodeAt(0) === 0xFEFF) {
                    text = text.slice(1);
                }
                text = text.trim();
                try {
                    const data = JSON.parse(text);
                    throw new Error(data.message || `HTTP ${response.status}`);
                } catch (e) {
                    throw new Error(`HTTP ${response.status}: ${text.substring(0, 100)}`);
                }
            });
        }
        return response.text();
    })
    .then(text => {
        if (text.charCodeAt(0) === 0xFEFF) {
            text = text.slice(1);
        }
        text = text.trim();
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error('Invalid JSON response');
        }
    })
    .then(data => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        
        if (data.success) {
            showToast('success', data.message || 'Personnel updated successfully!');
            closeEditModal();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            if (data.errors) {
                let errorMessages = Object.values(data.errors).flat().join('\n');
                showToast('error', errorMessages || data.message || 'Failed to update personnel.');
            } else {
                showToast('error', data.message || 'Failed to update personnel.');
            }
        }
    })
    .catch(error => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        showToast('error', 'An error occurred: ' + error.message);
        console.error('Update error:', error);
    });
});

// ==================== DELETE PERSONNEL FUNCTIONS ====================
let deleteUserId = null;
let deleteUserName = null;

function deletePersonnel(id, name) {
    deleteUserId = id;
    deleteUserName = name;
    
    document.getElementById('deletePersonnelName').innerHTML = `
        <i class="fas fa-user mr-2" style="color: var(--primary);"></i>
        Are you sure you want to delete <strong>${name}</strong>?
    `;
    document.getElementById('deletion_reason').value = '';
    document.getElementById('criticalRelationsWarning').classList.add('hidden');
    document.getElementById('criticalRelationsList').innerHTML = '';
    
    const confirmBtn = document.getElementById('deleteConfirmBtn');
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Checking...';
    
    document.getElementById('deletePersonnelModal').classList.remove('hidden');
    
    // Check for critical relations
    fetch(`/security/personnel/${id}/check-relations`, {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.text();
    })
    .then(text => {
        if (text.charCodeAt(0) === 0xFEFF) {
            text = text.slice(1);
        }
        text = text.trim();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('JSON Parse Error:', e);
            throw new Error('Invalid JSON response');
        }
    })
    .then(data => {
        const confirmBtn = document.getElementById('deleteConfirmBtn');
        
        if (data.has_critical_relations) {
            document.getElementById('criticalRelationsWarning').classList.remove('hidden');
            const list = document.getElementById('criticalRelationsList');
            list.innerHTML = '';
            data.critical_relations.forEach(relation => {
                const li = document.createElement('li');
                li.innerHTML = `<i class="fas fa-circle text-xs mr-2" style="color: var(--danger);"></i> ${relation}`;
                list.appendChild(li);
            });
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="fas fa-ban mr-2"></i> Cannot Delete';
        } else {
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="fas fa-trash-alt mr-2"></i> Delete Personnel';
        }
    })
    .catch(error => {
        console.error('Error checking relations:', error);
        const confirmBtn = document.getElementById('deleteConfirmBtn');
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '<i class="fas fa-trash-alt mr-2"></i> Delete Personnel';
        showToast('error', 'Failed to check dependencies: ' + error.message);
    });
}

function closeDeletePersonnelModal() {
    document.getElementById('deletePersonnelModal').classList.add('hidden');
    deleteUserId = null;
    deleteUserName = null;
}

function confirmDeletePersonnel() {
    if (!deleteUserId) return;
    
    const reason = document.getElementById('deletion_reason').value;
    const confirmBtn = document.getElementById('deleteConfirmBtn');
    const originalText = confirmBtn.innerHTML;
    
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';
    
    fetch(`/security/personnel/${deleteUserId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ deletion_reason: reason || 'No reason provided' })
    })
    .then(response => {
        return response.text().then(text => {
            if (text.charCodeAt(0) === 0xFEFF) {
                text = text.slice(1);
            }
            text = text.trim();
            try {
                return { status: response.status, data: JSON.parse(text) };
            } catch (e) {
                console.error('JSON Parse Error:', e);
                throw new Error('Invalid JSON response: ' + text.substring(0, 200));
            }
        });
    })
    .then(({ status, data }) => {
        confirmBtn.innerHTML = originalText;
        confirmBtn.disabled = false;
        
        if (data.success) {
            showToast('success', data.message || 'Personnel deleted successfully!');
            closeDeletePersonnelModal();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast('error', data.message || 'Failed to delete personnel.');
        }
    })
    .catch(error => {
        confirmBtn.innerHTML = originalText;
        confirmBtn.disabled = false;
        showToast('error', 'An error occurred: ' + error.message);
        console.error('Delete error:', error);
    });
}

// ==================== TOAST NOTIFICATIONS ====================
function showToast(type, message) {
    const existingToast = document.querySelector('.custom-toast');
    if (existingToast) existingToast.remove();
    
    const toast = document.createElement('div');
    toast.className = 'custom-toast';
    const colors = {
        success: 'var(--success)',
        error: 'var(--danger)',
        warning: 'var(--warning)',
        info: 'var(--info)'
    };
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        padding: 16px 24px;
        border-radius: 12px;
        background: var(--card-bg);
        border-left: 4px solid ${colors[type] || 'var(--info)'};
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        gap: 12px;
        max-width: 450px;
        animation: slideInRight 0.4s ease;
        border: 1px solid var(--border-color);
    `;
    
    toast.innerHTML = `
        <i class="fas ${icons[type]}" style="color: ${colors[type]}; font-size: 1.2rem;"></i>
        <span style="color: var(--text-primary); font-size: 0.9rem;">${message}</span>
        <button onclick="this.parentElement.remove()" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.1rem; margin-left: 8px;">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100px)';
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

// ==================== CONFIRM EMPTY TRASH ====================
function confirmEmptyTrash() {
    if (confirm('⚠️ WARNING: This will permanently delete all users in the trash. This action cannot be undone. Continue?')) {
        if (confirm('Are you sure? Type "empty_all_trash" to confirm.')) {
            const confirmation = prompt('Type "empty_all_trash" to confirm permanent deletion:');
            if (confirmation === 'empty_all_trash') {
                fetch('/security/personnel/empty-trash', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ confirmation: 'empty_all_trash' })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('success', data.message);
                        setTimeout(() => window.location.reload(), 2000);
                    } else {
                        showToast('error', data.message || 'Failed to empty trash.');
                    }
                })
                .catch(error => {
                    showToast('error', 'An error occurred: ' + error.message);
                    console.error('Empty trash error:', error);
                });
            } else {
                showToast('warning', 'Trash empty cancelled.');
            }
        }
    }
}

// ==================== RESEND INVITATION CHARACTER COUNT ====================
document.getElementById('resendCustomMessage')?.addEventListener('input', function() {
    const count = this.value.length;
    document.getElementById('resendCharCount').textContent = count;
    if (count > 950) {
        document.getElementById('resendCharCount').style.color = 'var(--danger)';
    } else if (count > 900) {
        document.getElementById('resendCharCount').style.color = 'var(--warning)';
    } else {
        document.getElementById('resendCharCount').style.color = 'var(--text-secondary)';
    }
});

// ==================== MODAL CLOSE ON OUTSIDE CLICK ====================
window.onclick = function(event) {
    if (event.target.classList.contains('fixed')) {
        closeRemoveModal();
        closeQuickAssignModal();
        closeUnassignModal();
        closeDetailsModal();
        closeEditModal();
        closeResendInvitationModal();
        closeDeletePersonnelModal();
    }
}

// ==================== KEYBOARD SHORTCUTS ====================
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeRemoveModal();
        closeQuickAssignModal();
        closeUnassignModal();
        closeDetailsModal();
        closeEditModal();
        closeResendInvitationModal();
        closeDeletePersonnelModal();
    }
});

// Add animation keyframes if not already present
if (!document.getElementById('toast-styles')) {
    const styleSheet = document.createElement('style');
    styleSheet.id = 'toast-styles';
    styleSheet.textContent = `
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
    `;
    document.head.appendChild(styleSheet);
}
</script>
@endsection