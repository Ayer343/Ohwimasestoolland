@extends('layouts.secu')

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
                        <span>{{ $assignment->created_at ? $assignment->created_at->format('M j, Y') : 'N/A' }}</span>
                        <span class="mx-2">•</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium 
                            @if(!$assignment->trashed())
                                @if($assignment->is_current && $assignment->is_active) bg-success text-white
                                @elseif(!$assignment->is_active) bg-warning text-white
                                @elseif(!$assignment->is_current) bg-secondary text-white @endif
                            @else bg-danger text-white @endif">
                            @if($assignment->trashed()) Deleted
                            @elseif($assignment->is_current && $assignment->is_active) Active
                            @elseif(!$assignment->is_active) Inactive
                            @elseif(!$assignment->is_current) Expired @endif
                        </span>
                        <span class="mx-2">•</span>
                        <span class="px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            <i class="fas fa-user-tie mr-1"></i> Area Supervisor
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3 mt-4 sm:mt-0">
                @if(!$assignment->trashed() && $assignment->supervisor_type !== 'area_supervisor')
                    <a href="{{ route('security.supervisor-assignments.edit', $assignment) }}"
                       class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-primary">
                        <i class="fas fa-edit mr-2"></i> Edit
                    </a>
                    <button onclick="toggleActive({{ $assignment->id }}, {{ $assignment->is_active ? 'false' : 'true' }})"
                            class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center {{ $assignment->is_active ? 'btn-warning' : 'btn-success' }}">
                        <i class="fas {{ $assignment->is_active ? 'fa-pause' : 'fa-play' }} mr-2"></i>
                        {{ $assignment->is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                    @if($assignment->is_current && !$assignment->end_date)
                        <button onclick="showExtendModal({{ $assignment->id }})"
                                class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-info">
                            <i class="fas fa-calendar-plus mr-2"></i> Extend
                        </button>
                    @endif
                    @if($assignment->is_current)
                        <button onclick="showTerminateModal({{ $assignment->id }})"
                                class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-danger">
                            <i class="fas fa-ban mr-2"></i> Terminate
                        </button>
                    @endif
                    <form action="{{ route('security.supervisor-assignments.destroy', $assignment) }}" method="POST" class="inline">
                        @csrf @method('DELETE')
                        <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-danger"
                                onclick="return confirm('Move this assignment to trash?')">
                            <i class="fas fa-trash mr-2"></i> Delete
                        </button>
                    </form>
                @endif
                <a href="{{ route('security.supervisor-assignments.index') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="card p-4" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
            <div class="flex items-center"><i class="fas fa-check-circle text-lg mr-3" style="color: var(--success);"></i>{{ session('success') }}</div>
        </div>
    @endif

    <!-- Main Content -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Supervisor Info -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Supervisor Information</h3>
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
                                <p class="text-sm" style="color: var(--text-secondary);">{{ $user->email ?? 'No email' }}</p>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                                <span class="text-sm" style="color: var(--text-secondary);">Eligible for Supervisor:</span>
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
                                <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $user->phone ?? 'Not provided' }}</span>
                            </div>
                            <div class="flex justify-between py-2" style="border-color: var(--border-color);">
                                <span class="text-sm" style="color: var(--text-secondary);">Member Since:</span>
                                <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $user->created_at ? $user->created_at->format('M Y') : 'N/A' }}</span>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-exclamation-triangle text-3xl mb-2" style="color: var(--warning);"></i>
                            <p style="color: var(--text-secondary);">Supervisor information not available</p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">The user may have been deleted.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Assignment Details -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Assignment Details</h3>
                </div>
                <div class="p-6 space-y-3">
                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Type:</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $assignment->supervisor_type_name ?? ucfirst(str_replace('_', ' ', $assignment->supervisor_type)) }}</span>
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
                        <span class="text-sm" style="color: var(--text-secondary);">Post:</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">
                            @php $post = $assignment->post; @endphp
                            @if($post)
                                {{ $post->name ?? 'Unknown' }} ({{ $post->code ?? 'N/A' }})
                            @else
                                <span class="italic">Role Only (No Post Assigned)</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Primary:</span>
                        <span class="text-sm font-medium">{{ $assignment->is_primary_supervisor ? '⭐ Yes' : 'No' }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Assigned By:</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ optional($assignment->assignedBy)->name ?? 'System' }}</span>
                    </div>
                    <div class="flex justify-between py-2" style="border-color: var(--border-color);">
                        <span class="text-sm" style="color: var(--text-secondary);">Created:</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $assignment->created_at ? $assignment->created_at->format('M j, Y g:i A') : 'N/A' }}</span>
                    </div>
                </div>
            </div>

            <!-- Timeline -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Timeline</h3>
                </div>
                <div class="p-6">
                    <div class="relative">
                        <div class="absolute left-3 top-0 bottom-0 w-0.5" style="background-color: var(--border-color);"></div>
                        <div class="relative pl-10 mb-6">
                            <div class="absolute left-0 w-6 h-6 rounded-full flex items-center justify-center" style="background-color: var(--success); color: white;">
                                <i class="fas fa-play text-xs"></i>
                            </div>
                            <h4 class="text-sm font-semibold" style="color: var(--text-primary);">Start Date</h4>
                            <p class="text-sm" style="color: var(--text-secondary);">{{ $assignment->start_date ? $assignment->start_date->format('M j, Y') : 'N/A' }}</p>
                            <p class="text-xs" style="color: var(--text-secondary);">{{ $assignment->start_date ? $assignment->start_date->diffForHumans() : 'N/A' }}</p>
                        </div>
                        <div class="relative pl-10 mb-6">
                            <div class="absolute left-0 w-6 h-6 rounded-full flex items-center justify-center" style="background-color: {{ $assignment->is_current ? 'var(--info)' : 'var(--secondary)' }}; color: white;">
                                <i class="fas fa-{{ $assignment->is_current ? 'hourglass-half' : 'hourglass-end' }} text-xs"></i>
                            </div>
                            <h4 class="text-sm font-semibold" style="color: var(--text-primary);">Status</h4>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                @if($assignment->is_current)
                                    Currently Active ({{ $assignment->start_date ? $assignment->start_date->diffInDays(now()) : 0 }} days so far)
                                @else
                                    No longer active
                                @endif
                            </p>
                        </div>
                        <div class="relative pl-10">
                            <div class="absolute left-0 w-6 h-6 rounded-full flex items-center justify-center" style="background-color: {{ $assignment->end_date ? 'var(--warning)' : 'var(--secondary)' }}; color: white;">
                                <i class="fas fa-{{ $assignment->end_date ? 'stop' : 'infinity' }} text-xs"></i>
                            </div>
                            <h4 class="text-sm font-semibold" style="color: var(--text-primary);">End Date</h4>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                @if($assignment->end_date)
                                    {{ $assignment->end_date->format('M j, Y') }}
                                    @if($assignment->is_current)
                                        <span class="text-xs block" style="color: var(--text-secondary);">{{ $assignment->end_date->diffForHumans() }} remaining</span>
                                    @else
                                        <span class="text-xs block" style="color: var(--text-secondary);">Ended {{ $assignment->end_date->diffForHumans() }}</span>
                                    @endif
                                @else
                                    Indefinite
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="card p-4 text-center">
                    <div class="text-3xl font-bold" style="color: var(--primary);">{{ $supervisionStats['total_days'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Days</div>
                </div>
                <div class="card p-4 text-center">
                    <div class="text-3xl font-bold" style="color: var(--success);">{{ $supervisionStats['schedules_overseen'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Schedules</div>
                </div>
                <div class="card p-4 text-center">
                    <div class="text-3xl font-bold" style="color: var(--warning);">{{ $supervisionStats['approvals_given'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Approvals</div>
                </div>
                <div class="card p-4 text-center">
                    <div class="text-3xl font-bold" style="color: var(--danger);">{{ $supervisionStats['incidents_reported'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Incidents</div>
                </div>
            </div>

            <!-- Scope & Coverage -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Scope & Coverage</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Shifts -->
                        <div>
                            <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">Shifts Covered</h4>
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

                        <!-- Days -->
                        <div>
                            <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">Days Covered</h4>
                            @if(!empty($assignment->applicable_days))
                                <div class="grid grid-cols-7 gap-1">
                                    @php
                                        $dayLetters = [1=>'M',2=>'T',3=>'W',4=>'T',5=>'F',6=>'S',7=>'S'];
                                        $applicableDays = is_array($assignment->applicable_days) ? $assignment->applicable_days : 
                                                          (is_string($assignment->applicable_days) ? json_decode($assignment->applicable_days, true) : []);
                                    @endphp
                                    @foreach($dayLetters as $dayNum => $dayLetter)
                                        <div class="p-2 text-center rounded-lg
                                            {{ in_array($dayNum, $applicableDays) ? 'font-bold' : 'opacity-40' }}"
                                            style="background-color: {{ in_array($dayNum, $applicableDays) ? 'rgba(var(--success-rgb), 0.2)' : 'var(--bg-secondary)' }};
                                            color: {{ in_array($dayNum, $applicableDays) ? 'var(--success)' : 'var(--text-secondary)' }};">
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

            <!-- Notes -->
            @if($assignment->notes)
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Notes</h3>
                </div>
                <div class="p-6">
                    <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary);">
                        <p class="text-sm whitespace-pre-line" style="color: var(--text-primary);">{{ $assignment->notes }}</p>
                    </div>
                </div>
            </div>
            @endif

            <!-- Admin Notes (if visible to supervisor) -->
            @if(!empty($assignment->metadata['admin_notes']) && auth()->user()->isAdmin())
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
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">New End Date</label>
                        <input type="date" name="new_end_date" id="extend_end_date" class="index-custom-input w-full"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               min="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div class="mb-4">
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Reason</label>
                        <textarea name="reason" rows="3" class="index-custom-input w-full"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Enter reason for extending..."></textarea>
                    </div>
                </div>
                <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                    <button type="button" onclick="closeExtendModal()" class="btn-secondary px-4 py-2 rounded-lg">Cancel</button>
                    <button type="submit" class="btn-primary px-4 py-2 rounded-lg">Extend</button>
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
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Reason</label>
                        <textarea name="reason" rows="3" class="index-custom-input w-full"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Enter reason for termination..."
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
                    <button type="button" onclick="closeTerminateModal()" class="btn-secondary px-4 py-2 rounded-lg">Cancel</button>
                    <button type="submit" class="btn-danger px-4 py-2 rounded-lg">Terminate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border: none;
    transition: all 0.2s;
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
    transition: all 0.2s;
}
.btn-secondary:hover {
    background-color: var(--border-color);
}
.btn-danger {
    background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
    border: none;
    transition: all 0.2s;
    color: white;
}
.btn-danger:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--danger-rgb), 0.3);
}
.btn-success {
    background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
    border: none;
    transition: all 0.2s;
    color: white;
}
.btn-success:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--success-rgb), 0.3);
}
.btn-warning {
    background: linear-gradient(135deg, var(--warning) 0%, #b45309 100%);
    border: none;
    transition: all 0.2s;
    color: white;
}
.btn-warning:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--warning-rgb), 0.3);
}
.btn-info {
    background: linear-gradient(135deg, var(--info) 0%, #2563eb 100%);
    border: none;
    transition: all 0.2s;
    color: white;
}
.btn-info:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--info-rgb), 0.3);
}
.index-custom-input {
    transition: all 0.2s ease;
}
.index-custom-input:focus {
    border-color: var(--primary) !important;
    outline: none;
    box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.2);
}

/* Badge styles */
.badge-success {
    background-color: rgba(var(--success-rgb), 0.1) !important;
    color: var(--success) !important;
    border: 1px solid rgba(var(--success-rgb), 0.3) !important;
}
.badge-warning {
    background-color: rgba(var(--warning-rgb), 0.1) !important;
    color: var(--warning) !important;
    border: 1px solid rgba(var(--warning-rgb), 0.3) !important;
}
.badge-danger {
    background-color: rgba(var(--danger-rgb), 0.1) !important;
    color: var(--danger) !important;
    border: 1px solid rgba(var(--danger-rgb), 0.3) !important;
}
.badge-info {
    background-color: rgba(var(--info-rgb), 0.1) !important;
    color: var(--info) !important;
    border: 1px solid rgba(var(--info-rgb), 0.3) !important;
}
.badge-primary {
    background-color: rgba(var(--primary-rgb), 0.1) !important;
    color: var(--primary) !important;
    border: 1px solid rgba(var(--primary-rgb), 0.3) !important;
}
.badge-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
}
</style>

<script>
function closeExtendModal() { 
    document.getElementById('extendModal').classList.add('hidden'); 
}

function closeTerminateModal() { 
    document.getElementById('terminateModal').classList.add('hidden'); 
}

function showExtendModal(id) {
    const modal = document.getElementById('extendModal');
    const form = document.getElementById('extendForm');
    const dateInput = document.getElementById('extend_end_date');
    
    // Set min date to today
    const today = new Date().toISOString().split('T')[0];
    dateInput.min = today;
    
    form.action = `/security/supervisor-assignments/${id}/extend`;
    modal.classList.remove('hidden');
}

function showTerminateModal(id) {
    const modal = document.getElementById('terminateModal');
    const form = document.getElementById('terminateForm');
    
    form.action = `/security/supervisor-assignments/${id}/terminate`;
    modal.classList.remove('hidden');
}

async function toggleActive(id, isActive) {
    const action = isActive ? 'deactivate' : 'activate';
    if (!confirm(`Are you sure you want to ${action} this assignment?`)) return;
    
    try {
        const response = await fetch(`/security/supervisor-assignments/${id}/toggle-active`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });
        const data = await response.json();
        if (data.success) {
            alert(data.message);
            window.location.reload();
        } else {
            alert(data.message || `Failed to ${action} assignment`);
        }
    } catch (error) {
        console.error('Toggle error:', error);
        alert(`Failed to ${action} assignment. Please try again.`);
    }
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
        
        setTimeout(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }, 10000);
    }
});
</script>
@endsection