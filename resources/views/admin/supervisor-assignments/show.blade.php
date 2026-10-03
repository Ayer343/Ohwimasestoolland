@extends('layouts.app')

@section('title', 'Supervisor Assignment Details')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-wrap justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-user-tie text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-clipboard-list mr-2" style="color: var(--primary);"></i>
                        Supervisor Assignment Details
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-hashtag mr-2"></i>
                        <span>Assignment #{{ $assignment->id }}</span>
                        <span class="mx-2">•</span>
                        <i class="far fa-calendar-alt mr-1"></i>
                        <span>Created {{ $assignment->created_at ? $assignment->created_at->format('M j, Y') : 'N/A' }}</span>
                        <span class="mx-2">•</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium 
                            @if(!$assignment->trashed())
                                @if($assignment->is_current && $assignment->is_active)
                                    bg-success text-white
                                @elseif(!$assignment->is_active)
                                    bg-warning text-white
                                @elseif(!$assignment->is_current)
                                    bg-secondary text-white
                                @endif
                            @else
                                bg-danger text-white
                            @endif">
                            @if($assignment->trashed())
                                <i class="fas fa-trash-alt mr-1"></i> Deleted
                            @elseif($assignment->is_current && $assignment->is_active)
                                <i class="fas fa-check-circle mr-1"></i> Active
                            @elseif(!$assignment->is_active)
                                <i class="fas fa-pause-circle mr-1"></i> Inactive
                            @elseif(!$assignment->is_current)
                                <i class="fas fa-clock mr-1"></i> Expired
                            @endif
                        </span>
                        <span class="mx-2">•</span>
                        <span class="px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            <i class="fas fa-user-shield mr-1"></i> Admin
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3 mt-4 sm:mt-0">
                @if(!$assignment->trashed())
                    <a href="{{ route('admin.supervisor-assignments.edit', $assignment) }}"
                       class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-primary">
                        <i class="fas fa-edit mr-2"></i> Edit
                    </a>
                    <button type="button" 
                            onclick="toggleActive({{ $assignment->id }}, {{ $assignment->is_active ? 'false' : 'true' }})"
                            class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center 
                                   {{ $assignment->is_active ? 'btn-warning' : 'btn-success' }}">
                        <i class="fas {{ $assignment->is_active ? 'fa-pause' : 'fa-play' }} mr-2"></i>
                        {{ $assignment->is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                    @if($assignment->is_current && !$assignment->end_date)
                        <button type="button" 
                                onclick="showExtendModal({{ $assignment->id }})"
                                class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-info">
                            <i class="fas fa-calendar-plus mr-2"></i> Extend
                        </button>
                    @endif
                    @if($assignment->is_current)
                        <button type="button" 
                                onclick="showTerminateModal({{ $assignment->id }})"
                                class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-danger">
                            <i class="fas fa-ban mr-2"></i> Terminate
                        </button>
                    @endif
                    <form action="{{ route('admin.supervisor-assignments.destroy', $assignment) }}" 
                          method="POST" 
                          class="delete-form d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" 
                                class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-secondary"
                                style="background-color: var(--danger); color: white;"
                                onclick="return confirm('Are you sure you want to move this assignment to trash?')">
                            <i class="fas fa-trash mr-2"></i> Delete
                        </button>
                    </form>
                @else
                    <form action="{{ route('admin.supervisor-assignments.restore', $assignment->id) }}" 
                          method="POST" 
                          class="d-inline">
                        @csrf
                        <button type="submit" 
                                class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-success">
                            <i class="fas fa-undo-alt mr-2"></i> Restore
                        </button>
                    </form>
                    <form action="{{ route('admin.supervisor-assignments.force-delete', $assignment->id) }}" 
                          method="POST" 
                          class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" 
                                class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-danger"
                                onclick="return confirm('Are you sure you want to permanently delete this assignment? This action cannot be undone.')">
                            <i class="fas fa-trash-alt mr-2"></i> Delete Permanently
                        </button>
                    </form>
                @endif
                <a href="{{ route('admin.supervisor-assignments.index') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="card p-4" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-lg" style="color: var(--success);"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium" style="color: var(--success);">Success!</h3>
                    <div class="mt-2 text-sm" style="color: var(--text-secondary);">
                        {{ session('success') }}
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="card p-4" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-lg" style="color: var(--danger);"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium" style="color: var(--danger);">Error!</h3>
                    <div class="mt-2 text-sm" style="color: var(--text-secondary);">
                        {{ session('error') }}
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - Basic Info & Stats -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Supervisor Info Card -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-tie mr-2" style="color: var(--primary);"></i>
                        Supervisor Information
                    </h3>
                </div>
                <div class="p-6">
                    @php
                        $user = $assignment->user;
                    @endphp
                    @if($user)
                        <div class="flex items-center mb-4">
                            <div class="w-16 h-16 rounded-full flex items-center justify-center"
                                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-size: 24px;">
                                {{ strtoupper(substr($user->name ?? 'N/A', 0, 1)) }}
                            </div>
                            <div class="ml-4">
                                <h4 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                                    {{ $user->name ?? 'Unknown' }}
                                    @if($user->can_be_supervisor ?? false)
                                        <span class="ml-2 px-1.5 py-0.5 text-xs rounded" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);" title="Eligible for supervisor role">
                                            <i class="fas fa-check-circle"></i>
                                        </span>
                                    @endif
                                </h4>
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    {{ $user->email ?? 'No email' }}
                                </p>
                            </div>
                        </div>
                        
                        <div class="space-y-3">
                            <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                                <span class="text-sm" style="color: var(--text-secondary);">Supervisor Eligible:</span>
                                <span class="text-sm font-medium" style="color: var(--text-primary);">
                                    @if($user->can_be_supervisor ?? false)
                                        <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                            <i class="fas fa-check-circle mr-1"></i> Yes
                                        </span>
                                    @else
                                        <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                            <i class="fas fa-exclamation-triangle mr-1"></i> No
                                        </span>
                                    @endif
                                </span>
                            </div>
                            <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                                <span class="text-sm" style="color: var(--text-secondary);">Phone:</span>
                                <span class="text-sm font-medium" style="color: var(--text-primary);">
                                    {{ $user->phone ?? 'Not provided' }}
                                </span>
                            </div>
                            <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                                <span class="text-sm" style="color: var(--text-secondary);">Member Since:</span>
                                <span class="text-sm font-medium" style="color: var(--text-primary);">
                                    {{ $user->created_at ? $user->created_at->format('M Y') : 'N/A' }}
                                </span>
                            </div>
                        </div>

                        @if($user && $user->metadata)
                            <div class="mt-4">
                                <button type="button" 
                                        onclick="toggleSupervisorDetails()"
                                        class="w-full px-3 py-2 text-sm rounded-lg btn-secondary flex items-center justify-between">
                                    <span><i class="fas fa-chevron-down mr-2"></i> View Additional Details</span>
                                    <i class="fas fa-chevron-down" id="supervisorDetailsIcon"></i>
                                </button>
                                <div id="supervisorDetails" class="hidden mt-3 p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                                    <pre class="text-xs" style="color: var(--text-secondary);">{{ json_encode($user->metadata, JSON_PRETTY_PRINT) }}</pre>
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-exclamation-triangle text-3xl mb-2" style="color: var(--warning);"></i>
                            <p style="color: var(--text-secondary);">Supervisor information not available</p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">The user may have been deleted.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Assignment Details Card -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i>
                        Assignment Details
                    </h3>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-secondary);">Supervisor Type:</span>
                            <span class="text-sm font-medium" style="color: var(--text-primary);">
                                {{ $assignment->supervisor_type_name ?? ucfirst(str_replace('_', ' ', $assignment->supervisor_type)) }}
                            </span>
                        </div>
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-secondary);">Assignment Type:</span>
                            <span class="text-sm font-medium" style="color: var(--text-primary);">
                                @if($assignment->metadata['assignment_type'] ?? false)
                                    {{ $assignment->metadata['assignment_type'] === 'role_only' ? 'Role Only' : 'Post Specific' }}
                                @elseif($assignment->post)
                                    Post Specific
                                @else
                                    Role Only
                                @endif
                            </span>
                        </div>
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-secondary);">Security Post:</span>
                            <span class="text-sm font-medium" style="color: var(--text-primary);">
                                @php $post = $assignment->post; @endphp
                                @if($post)
                                    {{ $post->name ?? 'Unknown' }} ({{ $post->code ?? 'No code' }})
                                @else
                                    <span class="italic">Role Only (No Post Assigned)</span>
                                @endif
                            </span>
                        </div>
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-secondary);">Primary Supervisor:</span>
                            <span class="text-sm font-medium">
                                @if($assignment->is_primary_supervisor)
                                    <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                        <i class="fas fa-star mr-1"></i> Yes
                                    </span>
                                @else
                                    <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                                        <i class="fas fa-user mr-1"></i> No
                                    </span>
                                @endif
                            </span>
                        </div>
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-secondary);">Assigned By:</span>
                            <span class="text-sm font-medium" style="color: var(--text-primary);">
                                {{ optional($assignment->assignedBy)->name ?? 'System' }}
                            </span>
                        </div>
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-secondary);">Assigned On:</span>
                            <span class="text-sm font-medium" style="color: var(--text-primary);">
                                {{ $assignment->created_at ? $assignment->created_at->format('M j, Y g:i A') : 'N/A' }}
                            </span>
                        </div>
                        <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                            <span class="text-sm" style="color: var(--text-secondary);">Last Updated:</span>
                            <span class="text-sm font-medium" style="color: var(--text-primary);">
                                {{ $assignment->updated_at ? $assignment->updated_at->diffForHumans() : 'N/A' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Timeline Card -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-clock mr-2" style="color: var(--primary);"></i>
                        Assignment Timeline
                    </h3>
                </div>
                <div class="p-6">
                    <div class="relative">
                        <!-- Timeline line -->
                        <div class="absolute left-3 top-0 bottom-0 w-0.5" style="background-color: var(--border-color);"></div>
                        
                        <!-- Start Date -->
                        <div class="relative pl-10 mb-6">
                            <div class="absolute left-0 w-6 h-6 rounded-full flex items-center justify-center"
                                 style="background-color: var(--success); color: white;">
                                <i class="fas fa-play text-xs"></i>
                            </div>
                            <div>
                                <h4 class="text-sm font-semibold" style="color: var(--text-primary);">Start Date</h4>
                                <p class="text-sm" style="color: var(--text-secondary);">{{ $assignment->start_date ? $assignment->start_date->format('l, F j, Y') : 'N/A' }}</p>
                                <p class="text-xs" style="color: var(--text-secondary);">{{ $assignment->start_date ? $assignment->start_date->diffForHumans() : 'N/A' }}</p>
                            </div>
                        </div>

                        <!-- Current Status -->
                        <div class="relative pl-10 mb-6">
                            <div class="absolute left-0 w-6 h-6 rounded-full flex items-center justify-center"
                                 style="background-color: {{ (isset($assignment->is_current) && $assignment->is_current) ? 'var(--info)' : 'var(--secondary)' }}; color: white;">
                                <i class="fas fa-{{ (isset($assignment->is_current) && $assignment->is_current) ? 'hourglass-half' : 'hourglass-end' }} text-xs"></i>
                            </div>
                            <div>
                                <h4 class="text-sm font-semibold" style="color: var(--text-primary);">Current Status</h4>
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    @if(isset($assignment->is_current) && $assignment->is_current)
                                        Currently Active ({{ $assignment->start_date ? $assignment->start_date->diffInDays(now()) : 0 }} days so far)
                                    @else
                                        No longer active
                                    @endif
                                </p>
                            </div>
                        </div>

                        <!-- End Date -->
                        <div class="relative pl-10">
                            <div class="absolute left-0 w-6 h-6 rounded-full flex items-center justify-center"
                                 style="background-color: {{ $assignment->end_date ? 'var(--warning)' : 'var(--secondary)' }}; color: white;">
                                <i class="fas fa-{{ $assignment->end_date ? 'stop' : 'infinity' }} text-xs"></i>
                            </div>
                            <div>
                                <h4 class="text-sm font-semibold" style="color: var(--text-primary);">End Date</h4>
                                @if($assignment->end_date)
                                    <p class="text-sm" style="color: var(--text-secondary);">{{ $assignment->end_date->format('l, F j, Y') }}</p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        @if(isset($assignment->is_current) && $assignment->is_current)
                                            {{ $assignment->end_date->diffForHumans() }} remaining
                                        @else
                                            Ended {{ $assignment->end_date->diffForHumans() }}
                                        @endif
                                    </p>
                                @else
                                    <p class="text-sm italic" style="color: var(--text-secondary);">No end date (Indefinite)</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Duration Stats -->
                    <div class="mt-6 grid grid-cols-2 gap-3">
                        <div class="p-3 rounded-lg text-center" style="background-color: var(--bg-secondary);">
                            <div class="text-2xl font-bold" style="color: var(--primary);">{{ $supervisionStats['total_days'] ?? 0 }}</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Total Days</div>
                        </div>
                        <div class="p-3 rounded-lg text-center" style="background-color: var(--bg-secondary);">
                            <div class="text-2xl font-bold" style="color: var(--success);">
                                @if((isset($assignment->is_current) && $assignment->is_current) && !$assignment->end_date)
                                    ∞
                                @elseif($assignment->end_date)
                                    {{ max(0, $assignment->end_date->diffInDays(now())) }}
                                @else
                                    0
                                @endif
                            </div>
                            <div class="text-xs" style="color: var(--text-secondary);">Days Remaining</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="card p-4 text-center">
                    <div class="text-3xl font-bold" style="color: var(--primary);">{{ $supervisionStats['schedules_overseen'] ?? 0 }}</div>
                    <div class="text-xs uppercase tracking-wider mt-1" style="color: var(--text-secondary);">Schedules</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Overseen</div>
                </div>
                <div class="card p-4 text-center">
                    <div class="text-3xl font-bold" style="color: var(--success);">{{ $supervisionStats['verifications_performed'] ?? 0 }}</div>
                    <div class="text-xs uppercase tracking-wider mt-1" style="color: var(--text-secondary);">Verifications</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Performed</div>
                </div>
                <div class="card p-4 text-center">
                    <div class="text-3xl font-bold" style="color: var(--warning);">{{ $supervisionStats['approvals_given'] ?? 0 }}</div>
                    <div class="text-xs uppercase tracking-wider mt-1" style="color: var(--text-secondary);">Approvals</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Given</div>
                </div>
                <div class="card p-4 text-center">
                    <div class="text-3xl font-bold" style="color: var(--danger);">{{ $supervisionStats['incidents_reported'] ?? 0 }}</div>
                    <div class="text-xs uppercase tracking-wider mt-1" style="color: var(--text-secondary);">Incidents</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Reported</div>
                </div>
            </div>

            <!-- Scope & Coverage Card -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-map-marked-alt mr-2" style="color: var(--primary);"></i>
                        Scope & Coverage
                    </h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Shifts Covered -->
                        <div>
                            <h4 class="text-sm font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-clock mr-2" style="color: var(--info);"></i>
                                Shifts Covered
                            </h4>
                            @if(!empty($assignment->shift_ids) && isset($assignment->shifts) && $assignment->shifts && $assignment->shifts->count() > 0)
                                <div class="space-y-2">
                                    @foreach($assignment->shifts as $shift)
                                        <div class="flex items-center p-2 rounded-lg" style="background-color: var(--bg-secondary);">
                                            <i class="fas fa-clock mr-2" style="color: var(--info);"></i>
                                            <span style="color: var(--text-primary);">{{ $shift->name ?? 'Unknown Shift' }}</span>
                                            @if($shift->start_time && $shift->end_time)
                                                <span class="ml-auto text-xs" style="color: var(--text-secondary);">
                                                    {{ substr($shift->start_time, 0, 5) }} - {{ substr($shift->end_time, 0, 5) }}
                                                </span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @elseif(!empty($assignment->shift_ids))
                                <p class="text-sm" style="color: var(--text-secondary);">Shift data not available</p>
                            @else
                                <p class="text-sm italic" style="color: var(--text-secondary);">All shifts</p>
                            @endif
                        </div>

                        <!-- Days Covered -->
                        <div>
                            <h4 class="text-sm font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                                <i class="fas fa-calendar-alt mr-2" style="color: var(--success);"></i>
                                Days Covered
                            </h4>
                            @if(!empty($assignment->applicable_days))
                                <div class="grid grid-cols-7 gap-1">
                                    @php
                                        $dayLetters = [1=>'M',2=>'T',3=>'W',4=>'T',5=>'F',6=>'S',7=>'S'];
                                        $applicableDays = is_array($assignment->applicable_days) ? $assignment->applicable_days : 
                                                          (is_string($assignment->applicable_days) ? json_decode($assignment->applicable_days, true) : []);
                                    @endphp
                                    @foreach($dayLetters as $dayNum => $dayLetter)
                                        <div class="p-2 text-center rounded-lg
                                            {{ in_array($dayNum, $applicableDays) 
                                                ? 'font-bold' 
                                                : 'opacity-40' }}"
                                            style="background-color: {{ in_array($dayNum, $applicableDays) 
                                                ? 'rgba(var(--success-rgb), 0.2)' 
                                                : 'var(--bg-secondary)' }};
                                            color: {{ in_array($dayNum, $applicableDays) 
                                                ? 'var(--success)' 
                                                : 'var(--text-secondary)' }};">
                                            {{ $dayLetter }}
                                        </div>
                                    @endforeach
                                </div>
                                @if(!empty($applicableDays))
                                    <p class="text-xs mt-2" style="color: var(--text-secondary);">
                                        Working days: 
                                        @foreach($applicableDays as $dayNum)
                                            {{ \Carbon\Carbon::createFromFormat('N', $dayNum)->format('D') }}@if(!$loop->last), @endif
                                        @endforeach
                                    </p>
                                @endif
                            @else
                                <p class="text-sm italic" style="color: var(--text-secondary);">All days (Mon-Sun)</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Permissions Card -->
            <div class="card">
                <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-shield-alt mr-2" style="color: var(--primary);"></i>
                        Permissions
                    </h3>
                    <span class="px-3 py-1 rounded-full text-xs" 
                          style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        {{ $assignment->permissions_list ? count($assignment->permissions_list) : 0 }} permissions
                    </span>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @php
                            $permissionLabels = [
                                'can_override_checkins' => 'Override Check-ins',
                                'can_approve_swaps' => 'Approve Shift Swaps',
                                'can_approve_overtime' => 'Approve Overtime',
                                'can_review_incidents' => 'Review Incidents',
                                'can_verify_checkins' => 'Verify Check-ins',
                                'can_request_backup' => 'Request Backup',
                                'can_approve_breaks' => 'Approve Breaks',
                                'can_escalate_issues' => 'Escalate Issues',
                                'can_view_all_schedules' => 'View All Schedules',
                                'can_edit_schedules' => 'Edit Schedules',
                            ];
                        @endphp

                        @foreach($permissionLabels as $permission => $label)
                            <div class="flex items-center p-2 rounded-lg" style="background-color: var(--bg-secondary);">
                                @if(isset($assignment->$permission) && $assignment->$permission)
                                    <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                                    <span style="color: var(--text-primary);">{{ $label }}</span>
                                @else
                                    <i class="fas fa-times-circle mr-2" style="color: var(--danger); opacity: 0.5;"></i>
                                    <span style="color: var(--text-secondary);">{{ $label }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if($assignment->permissions && is_array($assignment->permissions) && count($assignment->permissions) > 0)
                        <div class="mt-4">
                            <h4 class="text-sm font-semibold mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-code mr-2"></i>Custom Permissions
                            </h4>
                            <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                                <pre class="text-xs" style="color: var(--text-secondary);">{{ json_encode($assignment->permissions, JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Recent Schedules Card -->
            @if(isset($recentSchedules) && $recentSchedules && $recentSchedules->count() > 0)
            <div class="card">
                <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-calendar-check mr-2" style="color: var(--primary);"></i>
                        Recent Schedules Overseen
                    </h3>
                    <span class="text-xs" style="color: var(--text-secondary);">Last 20 schedules</span>
                </div>
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr style="border-bottom: 2px solid var(--border-color);">
                                    <th class="py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Date</th>
                                    <th class="py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Personnel</th>
                                    <th class="py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Shift</th>
                                    <th class="py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentSchedules as $schedule)
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td class="py-3 text-sm" style="color: var(--text-primary);">
                                        {{ $schedule->assignment_date ? $schedule->assignment_date->format('M j, Y') : 'N/A' }}
                                    </td>
                                    <td class="py-3">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-size: 12px;">
                                                {{ $schedule->securityUser && $schedule->securityUser->name ? strtoupper(substr($schedule->securityUser->name, 0, 1)) : 'U' }}
                                            </div>
                                            <span class="text-sm" style="color: var(--text-primary);">
                                                {{ optional($schedule->securityUser)->name ?? 'Unknown' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-3 text-sm" style="color: var(--text-primary);">
                                        {{ optional($schedule->shift)->name ?? 'N/A' }}
                                    </td>
                                    <td class="py-3">
                                        <span class="px-2 py-1 text-xs rounded-full"
                                              style="background-color: 
                                                @if($schedule->status == 'completed') rgba(var(--success-rgb), 0.2); color: var(--success);
                                                @elseif($schedule->status == 'in_progress') rgba(var(--warning-rgb), 0.2); color: var(--warning);
                                                @else rgba(var(--info-rgb), 0.2); color: var(--info);
                                                @endif">
                                            {{ $schedule->status ? ucfirst(str_replace('_', ' ', $schedule->status)) : 'Unknown' }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            <!-- Verification Logs Card -->
            @if(isset($verificationLogs) && $verificationLogs && count($verificationLogs) > 0)
            <div class="card mt-6">
                <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-clipboard-check mr-2" style="color: var(--primary);"></i>
                        Recent Verification Logs
                    </h3>
                    <span class="text-xs" style="color: var(--text-secondary);">Last 10 verifications</span>
                </div>
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr style="border-bottom: 2px solid var(--border-color);">
                                    <th class="py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Date/Time</th>
                                    <th class="py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Schedule</th>
                                    <th class="py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Personnel</th>
                                    <th class="py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Method</th>
                                    <th class="py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($verificationLogs as $log)
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td class="py-3 text-sm" style="color: var(--text-primary);">
                                        @if(property_exists($log, 'created_at') && $log->created_at)
                                            {{ \Carbon\Carbon::parse($log->created_at)->format('M j, Y g:i A') }}
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td class="py-3 text-sm" style="color: var(--text-primary);">
                                        @if(property_exists($log, 'schedule_id') && $log->schedule_id)
                                            Schedule #{{ $log->schedule_id }}
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td class="py-3">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-size: 12px;">
                                                @if(property_exists($log, 'personnel_name') && $log->personnel_name)
                                                    {{ strtoupper(substr($log->personnel_name, 0, 1)) }}
                                                @else
                                                    U
                                                @endif
                                            </div>
                                            <span class="text-sm" style="color: var(--text-primary);">
                                                {{ $log->personnel_name ?? 'Unknown' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <span class="px-2 py-1 text-xs rounded-full"
                                              style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                                            {{ $log->verification_method ?? 'Standard' }}
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        @php
                                            $status = property_exists($log, 'status') ? $log->status : 'completed';
                                        @endphp
                                        <span class="px-2 py-1 text-xs rounded-full"
                                              style="background-color: 
                                                @if($status == 'completed') rgba(var(--success-rgb), 0.2); color: var(--success);
                                                @elseif($status == 'pending') rgba(var(--warning-rgb), 0.2); color: var(--warning);
                                                @else rgba(var(--info-rgb), 0.2); color: var(--info);
                                                @endif">
                                            {{ ucfirst($status) }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            <!-- Notes Card -->
            @if($assignment->notes)
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-sticky-note mr-2" style="color: var(--primary);"></i>
                        Assignment Notes
                    </h3>
                </div>
                <div class="p-6">
                    <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                        <p class="text-sm whitespace-pre-line" style="color: var(--text-primary);">{{ $assignment->notes }}</p>
                    </div>
                </div>
            </div>
            @endif

            <!-- Admin Notes Card -->
            @if(!empty($assignment->metadata['admin_notes']))
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--primary);">
                        <i class="fas fa-user-shield mr-2"></i>
                        Admin Notes <span class="text-xs ml-2" style="color: var(--text-secondary);">(Internal)</span>
                    </h3>
                </div>
                <div class="p-6">
                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border-left: 3px solid var(--primary);">
                        <p class="text-sm whitespace-pre-line" style="color: var(--text-primary);">{{ $assignment->metadata['admin_notes'] }}</p>
                    </div>
                </div>
            </div>
            @endif

            <!-- Metadata Card -->
            @if($assignment->metadata && (is_array($assignment->metadata) || is_object($assignment->metadata)) && count((array)$assignment->metadata) > 0)
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-database mr-2" style="color: var(--primary);"></i>
                        Metadata & History
                    </h3>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
                        @foreach((array)$assignment->metadata as $key => $value)
                            @if($key !== 'admin_notes' && $key !== 'update_history')
                                <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                                    <h4 class="text-xs font-semibold mb-2 uppercase" style="color: var(--info);">{{ str_replace('_', ' ', $key) }}</h4>
                                    <pre class="text-xs" style="color: var(--text-secondary);">{{ is_array($value) || is_object($value) ? json_encode($value, JSON_PRETTY_PRINT) : $value }}</pre>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Extend Modal -->
<div id="extendModal" class="fixed inset-0 z-50 hidden overflow-y-auto" style="background-color: rgba(0,0,0,0.5);">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="card w-full max-w-md">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Extend Assignment</h3>
            </div>
            <form id="extendForm" method="POST">
                @csrf
                <div class="p-6">
                    <div class="mb-4">
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            New End Date
                        </label>
                        <input type="date" name="new_end_date" id="extend_end_date" 
                               class="index-custom-input w-full"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               min="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div class="mb-4">
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            Reason for Extension
                        </label>
                        <textarea name="reason" rows="3" 
                                  class="index-custom-input w-full"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Enter reason for extending this assignment..."></textarea>
                    </div>
                </div>
                <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                    <button type="button" 
                            onclick="closeExtendModal()"
                            class="px-4 py-2 rounded-lg text-sm font-medium btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 rounded-lg text-sm font-medium btn-primary text-white">
                        Extend Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Terminate Modal -->
<div id="terminateModal" class="fixed inset-0 z-50 hidden overflow-y-auto" style="background-color: rgba(0,0,0,0.5);">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="card w-full max-w-md">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Terminate Assignment</h3>
            </div>
            <form id="terminateForm" method="POST">
                @csrf
                <div class="p-6">
                    <div class="mb-4">
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            Reason for Termination
                        </label>
                        <textarea name="reason" rows="3" 
                                  class="index-custom-input w-full"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Enter reason for terminating this assignment..."
                                  required></textarea>
                    </div>
                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1);">
                        <p class="text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-exclamation-triangle mr-1" style="color: var(--danger);"></i>
                            Terminating this assignment will end it immediately. This action can be logged but cannot be undone.
                        </p>
                    </div>
                </div>
                <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                    <button type="button" 
                            onclick="closeTerminateModal()"
                            class="px-4 py-2 rounded-lg text-sm font-medium btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 rounded-lg text-sm font-medium btn-danger text-white">
                        Terminate Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Modern Toggle Switch Styles */
.toggle-modern {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
}

.toggle-modern input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #d1d5db;
    border: 2px solid #d1d5db;
    transition: .4s;
    border-radius: 34px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 2px;
    bottom: 2px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.toggle-modern input:checked + .toggle-slider {
    background-color: var(--success);
    border-color: var(--success);
}

.toggle-modern input:checked + .toggle-slider:before {
    transform: translateX(24px);
}

/* Card hover effects */
.card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
}

/* Timeline styles */
.timeline-item {
    transition: all 0.2s ease;
}

.timeline-item:hover {
    transform: translateX(5px);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid {
        gap: 1rem;
    }
    
    .toggle-modern {
        width: 40px;
        height: 20px;
    }
    
    .toggle-slider:before {
        height: 14px;
        width: 14px;
        left: 1px;
        bottom: 1px;
    }
    
    .toggle-modern input:checked + .toggle-slider:before {
        transform: translateX(18px);
    }
}

/* Modal animations */
#extendModal, #terminateModal {
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

#extendModal.show, #terminateModal.show {
    opacity: 1;
    visibility: visible;
}

/* Table styles */
.table-hover tr:hover {
    background-color: rgba(var(--primary-rgb), 0.05);
}

/* Badge styles */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
}

/* Permission items */
.permission-item {
    transition: all 0.2s ease;
}

.permission-item:hover {
    background-color: rgba(var(--primary-rgb), 0.05);
}

/* Stats cards */
.stat-card {
    transition: transform 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
}

/* JSON viewer */
pre {
    white-space: pre-wrap;
    word-wrap: break-word;
    font-family: 'Courier New', monospace;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize any tooltips or popovers if needed
});

// Toggle supervisor details
function toggleSupervisorDetails() {
    const details = document.getElementById('supervisorDetails');
    const icon = document.getElementById('supervisorDetailsIcon');
    
    if (details.classList.contains('hidden')) {
        details.classList.remove('hidden');
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
    } else {
        details.classList.add('hidden');
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
    }
}

// Toggle active status
function toggleActive(id, shouldActivate) {
    const action = shouldActivate ? 'activate' : 'deactivate';
    
    if (!confirm(`Are you sure you want to ${action} this assignment?`)) {
        return;
    }
    
    fetch(`/admin/supervisor-assignments/${id}/toggle-active`, {
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
            window.location.reload();
        } else {
            alert('Failed to toggle assignment status: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while toggling assignment status.');
    });
}

// Extend modal
function showExtendModal(id) {
    const modal = document.getElementById('extendModal');
    const form = document.getElementById('extendForm');
    const dateInput = document.getElementById('extend_end_date');
    
    // Set min date to today
    const today = new Date().toISOString().split('T')[0];
    dateInput.min = today;
    
    form.action = `/admin/supervisor-assignments/${id}/extend`;
    modal.classList.add('show');
    modal.classList.remove('hidden');
}

function closeExtendModal() {
    const modal = document.getElementById('extendModal');
    modal.classList.remove('show');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

// Terminate modal
function showTerminateModal(id) {
    const modal = document.getElementById('terminateModal');
    const form = document.getElementById('terminateForm');
    
    form.action = `/admin/supervisor-assignments/${id}/terminate`;
    modal.classList.add('show');
    modal.classList.remove('hidden');
}

function closeTerminateModal() {
    const modal = document.getElementById('terminateModal');
    modal.classList.remove('show');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

// Close modals when clicking outside
window.addEventListener('click', function(event) {
    const extendModal = document.getElementById('extendModal');
    const terminateModal = document.getElementById('terminateModal');
    
    if (event.target === extendModal) {
        closeExtendModal();
    }
    if (event.target === terminateModal) {
        closeTerminateModal();
    }
});

// Handle form submissions
document.addEventListener('submit', function(e) {
    const submitBtn = e.target.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
        
        // Re-enable after 10 seconds in case of error (timeout)
        setTimeout(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }, 10000);
    }
});
</script>
@endsection