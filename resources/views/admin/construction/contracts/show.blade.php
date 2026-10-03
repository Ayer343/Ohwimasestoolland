@extends('layouts.app')

@section('title', 'Contract Details - ' . $contract->contract_number)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-file-contract text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-file-contract mr-2" style="color: var(--primary);"></i> 
                        {{ $contract->contract_number }}
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-tag mr-2"></i>
                        <span>{{ $contract->title }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>Admin: {{ auth()->user()->name }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <a href="{{ route('admin.construction.contracts.index') }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
                <button onclick="refreshPage()" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-sync-alt mr-2"></i> Refresh
                </button>
            </div>
        </div>
    </div>

    <!-- Navigation Card -->
    <div class="card p-6">
        <div class="flex items-center justify-between flex-wrap gap-2">
            <div class="flex items-center space-x-3 flex-wrap gap-2">
                <a href="{{ route('admin.dashboard') }}" 
                   class="inline-flex items-center text-sm font-medium" 
                   style="color: var(--primary);">
                    <i class="fas fa-arrow-left mr-2"></i> Dashboard
                </a>
                
                <a href="{{ route('admin.construction.contracts.index') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-list mr-1"></i> All Contracts
                </a>
                
                @if($contract->property)
                <a href="{{ route('properties.show', $contract->property->id) }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-building mr-1"></i> View Property
                </a>
                @endif
                
                <a href="{{ route('admin.construction-registrations.index') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-hard-hat mr-1"></i> Registrations
                </a>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
            </div>
        </div>
    </div>

    <!-- Status Banner -->
    <div class="card p-4" style="border-left: 4px solid 
        @if($contract->status == 'pending_approval') var(--warning, #f59e0b)
        @elseif($contract->status == 'approved') var(--success, #10b981)
        @elseif($contract->status == 'in_progress') var(--primary, #3b82f6)
        @elseif($contract->status == 'completed') var(--success, #10b981)
        @elseif($contract->status == 'cancelled') var(--danger, #ef4444)
        @elseif($contract->status == 'on_hold') var(--warning, #f59e0b)
        @elseif($contract->status == 'under_review') var(--info, #06b6d4)
        @else var(--secondary, #6c757d)
        @endif;">
        <div class="flex items-center justify-between flex-wrap gap-2">
            <div class="flex items-center">
                @php
                    $statusColors = [
                        'draft' => 'secondary',
                        'pending_approval' => 'warning',
                        'approved' => 'success',
                        'in_progress' => 'primary',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'on_hold' => 'warning',
                        'under_review' => 'info'
                    ];
                    $statusIcons = [
                        'draft' => 'fa-file',
                        'pending_approval' => 'fa-clock',
                        'approved' => 'fa-check-circle',
                        'in_progress' => 'fa-hard-hat',
                        'completed' => 'fa-flag-checkered',
                        'cancelled' => 'fa-times-circle',
                        'on_hold' => 'fa-pause-circle',
                        'under_review' => 'fa-search'
                    ];
                    $statusColor = $statusColors[$contract->status] ?? 'secondary';
                    $statusIcon = $statusIcons[$contract->status] ?? 'fa-question-circle';
                    $statusLabel = ucfirst(str_replace('_', ' ', $contract->status));
                @endphp
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium"
                      style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.15); color: var(--{{ $statusColor }});">
                    <i class="fas {{ $statusIcon }} mr-2"></i>
                    Status: {{ $statusLabel }}
                </span>
                
                @if($contract->status === 'pending_approval')
                    <span class="text-sm ml-3" style="color: var(--text-secondary);">
                        <i class="fas fa-hourglass-half mr-1"></i> Awaiting admin review
                    </span>
                @endif
                
                @if($contract->approved_at && in_array($contract->status, ['approved', 'in_progress', 'completed']))
                    <span class="text-sm ml-3" style="color: var(--success);">
                        <i class="fas fa-check-circle mr-1"></i> 
                        Approved {{ $contract->approved_at->diffForHumans() }}
                        @if($contract->approvedBy)
                            by {{ $contract->approvedBy->name }}
                        @endif
                    </span>
                @endif
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-clock mr-1"></i>
                Last updated: {{ $contract->updated_at->diffForHumans() }}
            </div>
        </div>
    </div>

    <!-- Progress Bar -->
    @if(isset($progress))
    <div class="card p-4">
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium" style="color: var(--text-primary);">Project Progress</span>
            <span class="text-sm font-bold" style="color: var(--primary);">{{ $progress }}%</span>
        </div>
        <div class="w-full rounded-full h-3" style="background-color: rgba(var(--primary-rgb), 0.1);">
            <div class="rounded-full h-3 transition-all duration-500" 
                 style="width: {{ $progress }}%; background: linear-gradient(90deg, var(--primary), var(--secondary));"></div>
        </div>
        <div class="flex justify-between mt-1">
            <span class="text-xs" style="color: var(--text-secondary);">Started: {{ $contract->contract_start_date ? $contract->contract_start_date->format('M d, Y') : 'N/A' }}</span>
            <span class="text-xs" style="color: var(--text-secondary);">Est. Completion: {{ $contract->estimated_completion_date ? $contract->estimated_completion_date->format('M d, Y') : 'N/A' }}</span>
        </div>
    </div>
    @endif

    <!-- ============================================ -->
    <!-- MAIN CONTENT GRID -->
    <!-- ============================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- LEFT COLUMN - Contract Details -->
        <div class="lg:col-span-2">
            <!-- Contract Details Card -->
            <div class="card p-6 mb-6">
                <h4 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-file-contract mr-2" style="color: var(--primary);"></i> Contract Details
                </h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Contract Number</p>
                        <p class="font-bold" style="color: var(--text-primary);">{{ $contract->contract_number }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Title</p>
                        <p class="font-medium" style="color: var(--text-primary);">{{ $contract->title }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Property</p>
                        @if($contract->property)
                            <a href="{{ route('properties.show', $contract->property->id) }}" 
                               class="font-medium hover:underline" style="color: var(--primary);">
                                {{ $contract->property->property_name }}
                            </a>
                            @if($contract->property->digital_address)
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-map-pin mr-1"></i>
                                    {{ $contract->property->digital_address }}
                                </p>
                            @endif
                            @if($contract->property->landlord)
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-user mr-1"></i>
                                    Landlord: {{ $contract->property->landlord->name }}
                                </p>
                            @endif
                        @else
                            <p class="text-sm" style="color: var(--text-secondary);">Property deleted</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Contract Amount</p>
                        <p class="font-bold text-lg" style="color: var(--text-primary);">
                            @if($contract->contract_amount)
                                ₵{{ number_format($contract->contract_amount, 2) }}
                            @else
                                <span style="color: var(--text-secondary);">Not specified</span>
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Contractor Type</p>
                        <p style="color: var(--text-primary);">{{ ucfirst($contract->contractor_type) }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Landlord</p>
                        @if($contract->landlord)
                            <p class="font-medium" style="color: var(--text-primary);">
                                <a href="{{ route('admin.users.show', $contract->landlord->id) }}" 
                                   class="hover:underline" style="color: var(--primary);">
                                    {{ $contract->landlord->name }}
                                </a>
                            </p>
                            @if($contract->landlord->email)
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-envelope mr-1"></i> {{ $contract->landlord->email }}
                                </p>
                            @endif
                            @if($contract->landlord->phone)
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="fas fa-phone mr-1"></i> {{ $contract->landlord->phone }}
                                </p>
                            @endif
                        @else
                            <p class="text-sm" style="color: var(--text-secondary);">Landlord deleted</p>
                        @endif
                    </div>
                </div>
                
                @if($contract->description)
                    <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                        <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Description</p>
                        <p style="color: var(--text-primary);">{{ $contract->description }}</p>
                    </div>
                @endif
            </div>

            <!-- Timeline Card -->
            <div class="card p-6 mb-6">
                <h4 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-clock mr-2" style="color: var(--primary);"></i> Timeline
                </h4>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Start Date</p>
                        <p class="font-medium" style="color: var(--text-primary);">
                            {{ $contract->contract_start_date ? $contract->contract_start_date->format('M d, Y') : 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Estimated Completion</p>
                        <p class="font-medium" style="color: var(--text-primary);">
                            {{ $contract->estimated_completion_date ? $contract->estimated_completion_date->format('M d, Y') : 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Duration</p>
                        @if($contract->contract_start_date && $contract->estimated_completion_date)
                            @php
                                $days = $contract->contract_start_date->diffInDays($contract->estimated_completion_date);
                            @endphp
                            <p class="font-medium" style="color: var(--text-primary);">
                                {{ $days }} days ({{ round($days / 30, 1) }} months)
                            </p>
                        @else
                            <p style="color: var(--text-secondary);">N/A</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Days Remaining</p>
                        @php
                            $daysRemaining = $contract->estimated_completion_date ? 
                                \Carbon\Carbon::now()->diffInDays($contract->estimated_completion_date, false) : null;
                        @endphp
                        @if($daysRemaining !== null && !in_array($contract->status, ['completed', 'cancelled']))
                            @if($daysRemaining > 0)
                                <p class="font-medium" style="color: var(--success);">
                                    {{ $daysRemaining }} days
                                </p>
                            @else
                                <p class="font-medium" style="color: var(--danger);">
                                    Overdue by {{ abs($daysRemaining) }} days
                                </p>
                            @endif
                        @elseif($contract->status === 'completed')
                            <p class="font-medium" style="color: var(--success);">Completed</p>
                        @else
                            <p style="color: var(--text-secondary);">N/A</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Work Scope Card -->
            @if($contract->work_scope)
            <div class="card p-6 mb-6">
                <h4 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-tasks mr-2" style="color: var(--primary);"></i> Work Scope
                </h4>
                <ul class="list-disc list-inside space-y-1" style="color: var(--text-primary);">
                    @foreach(json_decode($contract->work_scope, true) ?? [] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
                <div class="mt-3 text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1"></i>
                    Total: {{ count(json_decode($contract->work_scope, true) ?? []) }} work items
                </div>
            </div>
            @endif

            <!-- Milestones Card -->
            @if(isset($milestoneStats) && $milestoneStats['total'] > 0)
            <div class="card p-6">
                <h4 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-flag mr-2" style="color: var(--primary);"></i> Milestones ({{ $milestoneStats['total'] }})
                </h4>
                
                <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
                    <div class="p-2 rounded-lg text-center" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                        <div class="text-sm font-bold" style="color: var(--text-secondary);">{{ $milestoneStats['total'] }}</div>
                        <div class="text-xs" style="color: var(--text-secondary);">Total</div>
                    </div>
                    <div class="p-2 rounded-lg text-center" style="background-color: rgba(var(--warning-rgb), 0.05);">
                        <div class="text-sm font-bold" style="color: var(--warning);">{{ $milestoneStats['pending'] }}</div>
                        <div class="text-xs" style="color: var(--text-secondary);">Pending</div>
                    </div>
                    <div class="p-2 rounded-lg text-center" style="background-color: rgba(var(--primary-rgb), 0.05);">
                        <div class="text-sm font-bold" style="color: var(--primary);">{{ $milestoneStats['in_progress'] }}</div>
                        <div class="text-xs" style="color: var(--text-secondary);">In Progress</div>
                    </div>
                    <div class="p-2 rounded-lg text-center" style="background-color: rgba(var(--success-rgb), 0.05);">
                        <div class="text-sm font-bold" style="color: var(--success);">{{ $milestoneStats['completed'] }}</div>
                        <div class="text-xs" style="color: var(--text-secondary);">Completed</div>
                    </div>
                    <div class="p-2 rounded-lg text-center" style="background-color: rgba(var(--danger-rgb), 0.05);">
                        <div class="text-sm font-bold" style="color: var(--danger);">{{ $milestoneStats['delayed'] }}</div>
                        <div class="text-xs" style="color: var(--text-secondary);">Delayed</div>
                    </div>
                </div>
                
                @if($contract->milestones && $contract->milestones->count() > 0)
                    <div class="space-y-3 max-h-60 overflow-y-auto">
                        @foreach($contract->milestones->take(10) as $milestone)
                            <div class="p-3 rounded-lg border" style="border-color: var(--border-color);">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium" style="color: var(--text-primary);">{{ $milestone->title }}</p>
                                        @if($milestone->description)
                                            <p class="text-sm" style="color: var(--text-secondary);">{{ $milestone->description }}</p>
                                        @endif
                                    </div>
                                    <span class="px-2 py-1 rounded-full text-xs font-medium inline-flex items-center"
                                          style="background-color: rgba(var(--{{ 
                                            $milestone->status == 'completed' ? 'success' : 
                                            ($milestone->status == 'in_progress' ? 'primary' : 
                                            ($milestone->status == 'delayed' ? 'danger' : 'warning'))
                                          }}-rgb), 0.1); color: var(--{{ 
                                            $milestone->status == 'completed' ? 'success' : 
                                            ($milestone->status == 'in_progress' ? 'primary' : 
                                            ($milestone->status == 'delayed' ? 'danger' : 'warning'))
                                          }});">
                                        <i class="fas fa-{{ 
                                            $milestone->status == 'completed' ? 'check' : 
                                            ($milestone->status == 'in_progress' ? 'spinner' : 
                                            ($milestone->status == 'delayed' ? 'exclamation-triangle' : 'clock'))
                                        }} mr-1"></i>
                                        {{ ucfirst($milestone->status) }}
                                    </span>
                                </div>
                                <div class="flex justify-between mt-2 text-xs" style="color: var(--text-secondary);">
                                    <span>
                                        <i class="fas fa-calendar mr-1"></i>
                                        Target: {{ $milestone->target_date ? $milestone->target_date->format('M d, Y') : 'N/A' }}
                                    </span>
                                    @if($milestone->milestone_amount)
                                        <span>
                                            <i class="fas fa-money-bill mr-1"></i>
                                            ₵{{ number_format($milestone->milestone_amount, 2) }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        @if($contract->milestones->count() > 10)
                            <p class="text-xs text-center" style="color: var(--text-secondary);">
                                + {{ $contract->milestones->count() - 10 }} more milestones
                            </p>
                        @endif
                    </div>
                @endif
            </div>
            @endif
        </div>

        <!-- RIGHT COLUMN - Sidebar -->
        <div class="lg:col-span-1">
            <!-- Actions Card -->
            <div class="card p-6 mb-6">
                <h4 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-cog mr-2" style="color: var(--primary);"></i> Actions
                </h4>
                
                <div class="space-y-3">
                    @if($contract->status === 'pending_approval')
                        <!-- Approve Button -->
                        <button type="button" 
                                onclick="openApproveModal({{ $contract->id }}, '{{ $contract->contract_number }}')"
                                class="w-full px-4 py-2 rounded-lg font-medium inline-flex items-center justify-center text-white"
                                style="background-color: var(--success);">
                            <i class="fas fa-check mr-2"></i> Approve Contract
                        </button>
                        
                        <!-- Reject Button -->
                        <button type="button" 
                                onclick="openRejectModal({{ $contract->id }}, '{{ $contract->contract_number }}')"
                                class="w-full px-4 py-2 rounded-lg font-medium inline-flex items-center justify-center text-white"
                                style="background-color: var(--danger);">
                            <i class="fas fa-times mr-2"></i> Reject Contract
                        </button>
                    @endif
                    
                    @if($contract->status === 'approved')
                        <button type="button" 
                                onclick="startWork({{ $contract->id }})"
                                class="w-full px-4 py-2 rounded-lg font-medium inline-flex items-center justify-center text-white"
                                style="background-color: var(--primary);">
                            <i class="fas fa-play mr-2"></i> Start Work
                        </button>
                    @endif
                    
                    @if($contract->status === 'in_progress')
                        <button type="button" 
                                onclick="openPauseModal({{ $contract->id }}, '{{ $contract->contract_number }}')"
                                class="w-full px-4 py-2 rounded-lg font-medium inline-flex items-center justify-center text-white"
                                style="background-color: var(--warning);">
                            <i class="fas fa-pause mr-2"></i> Pause Work
                        </button>
                        
                        <button type="button" 
                                onclick="openCompleteModal({{ $contract->id }}, '{{ $contract->contract_number }}')"
                                class="w-full px-4 py-2 rounded-lg font-medium inline-flex items-center justify-center text-white"
                                style="background-color: var(--success);">
                            <i class="fas fa-flag-checkered mr-2"></i> Complete Contract
                        </button>
                    @endif
                    
                    @if($contract->status === 'on_hold')
                        <button type="button" 
                                onclick="resumeWork({{ $contract->id }})"
                                class="w-full px-4 py-2 rounded-lg font-medium inline-flex items-center justify-center text-white"
                                style="background-color: var(--success);">
                            <i class="fas fa-play mr-2"></i> Resume Work
                        </button>
                    @endif
                    
                    @if($contract->property)
                        <a href="{{ route('properties.show', $contract->property->id) }}" 
                           class="w-full px-4 py-2 rounded-lg font-medium inline-flex items-center justify-center"
                           style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                            <i class="fas fa-building mr-2"></i> View Property
                        </a>
                    @endif
                    
                    @if($contract->landlord)
                        <a href="{{ route('admin.users.show', $contract->landlord->id) }}" 
                           class="w-full px-4 py-2 rounded-lg font-medium inline-flex items-center justify-center"
                           style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                            <i class="fas fa-user mr-2"></i> View Landlord
                        </a>
                    @endif
                </div>
            </div>

            <!-- Contractor Info Card -->
            <div class="card p-6 mb-6">
                <h4 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-user-tie mr-2" style="color: var(--primary);"></i> Contractor
                </h4>
                
                <div class="space-y-2">
                    <p class="font-medium text-lg" style="color: var(--text-primary);">{{ $contract->contractor_name }}</p>
                    @if($contract->contractor_phone)
                        <p class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-phone mr-2"></i> {{ $contract->contractor_phone }}
                        </p>
                    @endif
                    @if($contract->contractor_email)
                        <p class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-envelope mr-2"></i> {{ $contract->contractor_email }}
                        </p>
                    @endif
                    @if($contract->contractor_address)
                        <p class="text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-map-marker-alt mr-2"></i> {{ $contract->contractor_address }}
                        </p>
                    @endif
                    
                    @if($contract->contractor_type == 'company')
                        <div class="mt-3 pt-3 border-t" style="border-color: var(--border-color);">
                            <p class="text-xs font-medium" style="color: var(--text-secondary);">Company Details</p>
                            @if($contract->company_registration_number)
                                <p class="text-sm" style="color: var(--text-primary);">
                                    <i class="fas fa-id-card mr-1"></i>
                                    Reg #: {{ $contract->company_registration_number }}
                                </p>
                            @endif
                            @if($contract->company_tin)
                                <p class="text-sm" style="color: var(--text-primary);">
                                    <i class="fas fa-hashtag mr-1"></i>
                                    TIN: {{ $contract->company_tin }}
                                </p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <!-- Admin Notes Card -->
            @if($contract->admin_notes || $contract->rejection_reason)
            <div class="card p-6 mb-6">
                <h4 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-sticky-note mr-2" style="color: var(--warning);"></i> Admin Notes
                </h4>
                
                @if($contract->rejection_reason)
                    <div class="p-3 rounded-lg mb-3" style="background-color: rgba(var(--danger-rgb), 0.05); border-left: 3px solid var(--danger);">
                        <p class="text-sm font-medium" style="color: var(--danger);">Rejection Reason</p>
                        <p class="text-sm mt-1" style="color: var(--text-primary);">{{ $contract->rejection_reason }}</p>
                    </div>
                @endif
                
                @if($contract->admin_notes)
                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border-left: 3px solid var(--info);">
                        <p class="text-sm font-medium" style="color: var(--info);">Notes</p>
                        <p class="text-sm mt-1" style="color: var(--text-primary);">{{ $contract->admin_notes }}</p>
                    </div>
                @endif
            </div>
            @endif

            <!-- Activity Log Card -->
            @if(isset($timeline) && $timeline->count() > 0)
            <div class="card p-6">
                <h4 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-history mr-2" style="color: var(--primary);"></i> Activity Log
                </h4>
                
                <div class="space-y-3 max-h-60 overflow-y-auto pr-2">
                    @foreach($timeline as $log)
                        <div class="flex items-start gap-3 p-2 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.02);">
                            <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center"
                                 style="background: rgba(var(--primary-rgb), 0.1);">
                                <i class="fas fa-user text-xs" style="color: var(--primary);"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm" style="color: var(--text-primary);">
                                    {{ $log->description }}
                                </p>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-clock mr-1"></i>
                                        {{ $log->created_at->diffForHumans() }}
                                    </span>
                                    @if($log->user)
                                        <span class="text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-user mr-1"></i>
                                            by {{ $log->user->name }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- APPROVE MODAL -->
<!-- ============================================ -->
<div id="approveModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('approveModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-check-circle text-success mr-2"></i>
                Approve Contract
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('approveModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="approveForm" method="POST">
            @csrf
            @method('PATCH')
            <div class="modal-body">
                <div class="space-y-4">
                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <p class="text-sm font-medium mb-1" style="color: var(--text-primary);">Contract Details</p>
                        <p class="text-sm" id="approveContractNumber" style="color: var(--text-secondary);"></p>
                    </div>
                    
                    <div>
                        <label for="admin_notes" class="block mb-2 font-medium" style="color: var(--text-primary);">Admin Notes (Optional)</label>
                        <textarea name="admin_notes" id="admin_notes" rows="3"
                                  class="form-input w-full p-3 rounded-lg border"
                                  style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                  placeholder="Add any notes about this approval..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeModal('approveModal')">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                        style="background-color: var(--success);">
                    <i class="fas fa-check mr-2"></i> Approve Contract
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- REJECT MODAL -->
<!-- ============================================ -->
<div id="rejectModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('rejectModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-times-circle text-danger mr-2"></i>
                Reject Contract
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('rejectModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="rejectForm" method="POST">
            @csrf
            @method('PATCH')
            <div class="modal-body">
                <div class="space-y-4">
                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <p class="text-sm font-medium mb-1" style="color: var(--text-primary);">Contract Details</p>
                        <p class="text-sm" id="rejectContractNumber" style="color: var(--text-secondary);"></p>
                    </div>
                    
                    <div>
                        <label for="rejection_reason" class="block mb-2 font-medium required" style="color: var(--text-primary);">Rejection Reason</label>
                        <textarea name="rejection_reason" id="rejection_reason" rows="3" required
                                  class="form-input w-full p-3 rounded-lg border"
                                  style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                  placeholder="Please provide a clear reason for rejection..."></textarea>
                        <small class="text-xs mt-1" style="color: var(--text-secondary);">This reason will be visible to the landlord.</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeModal('rejectModal')">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                        style="background-color: var(--danger);">
                    <i class="fas fa-times mr-2"></i> Reject Contract
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- PAUSE MODAL -->
<!-- ============================================ -->
<div id="pauseModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('pauseModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-pause-circle text-warning mr-2"></i>
                Pause Contract
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('pauseModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="pauseForm" method="POST">
            @csrf
            @method('PATCH')
            <div class="modal-body">
                <div class="space-y-4">
                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <p class="text-sm font-medium mb-1" style="color: var(--text-primary);">Contract Details</p>
                        <p class="text-sm" id="pauseContractNumber" style="color: var(--text-secondary);"></p>
                    </div>
                    
                    <div>
                        <label for="pause_reason" class="block mb-2 font-medium required" style="color: var(--text-primary);">Pause Reason</label>
                        <textarea name="pause_reason" id="pause_reason" rows="3" required
                                  class="form-input w-full p-3 rounded-lg border"
                                  style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                  placeholder="Please provide a reason for pausing..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeModal('pauseModal')">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                        style="background-color: var(--warning);">
                    <i class="fas fa-pause mr-2"></i> Pause Contract
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- COMPLETE MODAL -->
<!-- ============================================ -->
<div id="completeModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('completeModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-flag-checkered text-success mr-2"></i>
                Complete Contract
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('completeModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="completeForm" method="POST">
            @csrf
            @method('PATCH')
            <div class="modal-body">
                <div class="space-y-4">
                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <p class="text-sm font-medium mb-1" style="color: var(--text-primary);">Contract Details</p>
                        <p class="text-sm" id="completeContractNumber" style="color: var(--text-secondary);"></p>
                    </div>
                    
                    <div>
                        <label for="completion_notes" class="block mb-2 font-medium" style="color: var(--text-primary);">Completion Notes (Optional)</label>
                        <textarea name="completion_notes" id="completion_notes" rows="3"
                                  class="form-input w-full p-3 rounded-lg border"
                                  style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                  placeholder="Add any notes about the completion..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeModal('completeModal')">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                        style="background-color: var(--success);">
                    <i class="fas fa-flag-checkered mr-2"></i> Complete Contract
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay hidden">
    <div class="text-center">
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary mb-4"></div>
        <div class="text-white font-medium" id="loadingMessage">Processing...</div>
    </div>
</div>

@endsection

@section('scripts')
<script>
// ============================================
// MODAL FUNCTIONS
// ============================================

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

// ============================================
// APPROVE MODAL
// ============================================
function openApproveModal(contractId, contractNumber) {
    const form = document.getElementById('approveForm');
    form.action = `/admin/construction/contracts/${contractId}/approve`;
    document.getElementById('approveContractNumber').textContent = `Contract #: ${contractNumber}`;
    document.getElementById('admin_notes').value = '';
    openModal('approveModal');
}

// ============================================
// REJECT MODAL
// ============================================
function openRejectModal(contractId, contractNumber) {
    const form = document.getElementById('rejectForm');
    form.action = `/admin/construction/contracts/${contractId}/reject`;
    document.getElementById('rejectContractNumber').textContent = `Contract #: ${contractNumber}`;
    document.getElementById('rejection_reason').value = '';
    openModal('rejectModal');
}

// ============================================
// PAUSE MODAL
// ============================================
function openPauseModal(contractId, contractNumber) {
    const form = document.getElementById('pauseForm');
    form.action = `/admin/construction/contracts/${contractId}/pause`;
    document.getElementById('pauseContractNumber').textContent = `Contract #: ${contractNumber}`;
    document.getElementById('pause_reason').value = '';
    openModal('pauseModal');
}

// ============================================
// COMPLETE MODAL
// ============================================
function openCompleteModal(contractId, contractNumber) {
    const form = document.getElementById('completeForm');
    form.action = `/admin/construction/contracts/${contractId}/complete`;
    document.getElementById('completeContractNumber').textContent = `Contract #: ${contractNumber}`;
    document.getElementById('completion_notes').value = '';
    openModal('completeModal');
}

// ============================================
// START WORK
// ============================================
function startWork(contractId) {
    if (confirm('Are you sure you want to start work on this contract?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/admin/construction/contracts/${contractId}/start`;
        
        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = document.querySelector('meta[name="csrf-token"]').content;
        form.appendChild(csrf);
        
        const method = document.createElement('input');
        method.type = 'hidden';
        method.name = '_method';
        method.value = 'PATCH';
        form.appendChild(method);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// ============================================
// RESUME WORK
// ============================================
function resumeWork(contractId) {
    if (confirm('Are you sure you want to resume work on this contract?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/admin/construction/contracts/${contractId}/resume`;
        
        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = document.querySelector('meta[name="csrf-token"]').content;
        form.appendChild(csrf);
        
        const method = document.createElement('input');
        method.type = 'hidden';
        method.name = '_method';
        method.value = 'PATCH';
        form.appendChild(method);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// ============================================
// REFRESH PAGE
// ============================================
function refreshPage() {
    window.location.reload();
}

// ============================================
// TOAST SYSTEM
// ============================================
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
    
    toast.innerHTML = `
        <i class="fas ${iconMap[type] || 'fa-info-circle'} mr-2"></i>
        <span class="text-sm font-medium flex-1">${message}</span>
        <button class="ml-4 transition-colors duration-200 hover:opacity-70" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
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

// ============================================
// LOADING OVERLAY
// ============================================
function showLoading(message = 'Processing...') {
    const overlay = document.getElementById('loadingOverlay');
    const loadingMessage = document.getElementById('loadingMessage');
    if (overlay && loadingMessage) {
        loadingMessage.textContent = message;
        overlay.classList.remove('hidden');
    }
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.add('hidden');
    }
}

// ============================================
// CLOSE MODALS ON OVERLAY CLICK
// ============================================
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        const modal = e.target.closest('.modal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    }
});

// ============================================
// CLOSE MODALS WITH ESCAPE KEY
// ============================================
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

console.log('🚀 Admin Construction Contract Show Loaded');
</script>

<style>
/* ============================================ */
/* CARD STYLES */
/* ============================================ */
.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
}

/* ============================================ */
/* BUTTON STYLES */
/* ============================================ */
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

/* ============================================ */
/* FORM ELEMENTS */
/* ============================================ */
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

/* ============================================ */
/* MODAL STYLES */
/* ============================================ */
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

/* ============================================ */
/* LOADING OVERLAY */
/* ============================================ */
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

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.animate-spin {
    animation: spin 1s linear infinite;
    border-top-color: transparent;
}

/* ============================================ */
/* REQUIRED FIELD INDICATOR */
/* ============================================ */
.required::after {
    content: '*';
    color: var(--danger);
    margin-left: 4px;
}

/* ============================================ */
/* SCROLLBAR STYLING */
/* ============================================ */
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

/* ============================================ */
/* RESPONSIVE ADJUSTMENTS */
/* ============================================ */
@media (max-width: 768px) {
    .grid.grid-cols-1.lg\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .grid.grid-cols-1.md\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .grid.grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: 1fr 1fr;
    }
    
    .grid.grid-cols-2.md\:grid-cols-5 {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .modal-container {
        margin: 1rem;
    }
}

@media (max-width: 640px) {
    .p-6 {
        padding: 1rem !important;
    }
    
    .text-xl {
        font-size: 1.25rem !important;
    }
    
    .text-lg {
        font-size: 1.1rem !important;
    }
    
    .grid.grid-cols-2.md\:grid-cols-5 {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
@endsection