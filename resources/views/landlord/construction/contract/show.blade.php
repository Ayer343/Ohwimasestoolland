@extends('layouts.landlord')

@section('title', 'Construction Contract Details')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-wrap justify-between items-center p-6">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                    {{ $contract->title }}
                </h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    <i class="fas fa-file-signature mr-1"></i> 
                    Contract #{{ $contract->id }} • Created {{ $contract->created_at->format('M d, Y') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2 mt-2 sm:mt-0">
                @if(in_array($contract->status, ['draft', 'pending_approval']))
                    <a href="{{ route('landlord.construction.contract.edit', $contract) }}" 
                       class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 flex items-center transition-colors">
                        <i class="fas fa-edit mr-2"></i> 
                        <span>Edit Contract</span>
                    </a>
                @endif
                <a href="{{ route('landlord.construction.contract.index') }}" 
                   class="px-4 py-2 border rounded-lg hover:bg-gray-50 flex items-center transition-colors" 
                   style="border-color: var(--border-color); color: var(--text-primary);">
                    <i class="fas fa-arrow-left mr-2"></i> 
                    <span>Back to Contracts</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Status Banner -->
    <div class="card p-4" style="border-left: 4px solid 
        @if($contract->status == 'pending_approval') var(--warning, #f59e0b)
        @elseif($contract->status == 'approved') var(--success, #10b981)
        @elseif($contract->status == 'in_progress') var(--primary, #3b82f6)
        @elseif($contract->status == 'completed') var(--success, #10b981)
        @elseif($contract->status == 'rejected') var(--danger, #ef4444)
        @else var(--secondary, #6c757d)
        @endif;">
        <div class="flex items-center justify-between flex-wrap gap-2">
            <div class="flex items-center">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm" 
                      style="background-color: rgba(var(--{{ 
                        $contract->status == 'pending_approval' ? 'warning' : 
                        ($contract->status == 'approved' ? 'success' : 
                        ($contract->status == 'in_progress' ? 'primary' : 
                        ($contract->status == 'completed' ? 'success' : 
                        ($contract->status == 'rejected' ? 'danger' : 'secondary'))))
                      }}-rgb), 0.2); color: var(--{{ 
                        $contract->status == 'pending_approval' ? 'warning' : 
                        ($contract->status == 'approved' ? 'success' : 
                        ($contract->status == 'in_progress' ? 'primary' : 
                        ($contract->status == 'completed' ? 'success' : 
                        ($contract->status == 'rejected' ? 'danger' : 'secondary'))))
                      }});">
                    <i class="fas fa-{{ 
                        $contract->status == 'pending_approval' ? 'clock' : 
                        ($contract->status == 'approved' ? 'check-circle' : 
                        ($contract->status == 'in_progress' ? 'hard-hat' : 
                        ($contract->status == 'completed' ? 'flag-checkered' : 
                        ($contract->status == 'rejected' ? 'times-circle' : 'file'))))
                    }} mr-2"></i>
                    Status: {{ ucfirst(str_replace('_', ' ', $contract->status)) }}
                </span>
                @if($contract->status == 'pending_approval')
                    <span class="text-sm ml-3" style="color: var(--text-secondary);">
                        <i class="fas fa-hourglass-half mr-1"></i> Awaiting admin review
                    </span>
                @endif
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar mr-1"></i>
                Last updated: {{ $contract->updated_at->diffForHumans() }}
            </div>
        </div>
    </div>

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
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">Contract Title</p>
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
                                    <i class="fas fa-map-marker-alt mr-1"></i>
                                    {{ $contract->property->digital_address }}
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
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                </div>
            </div>

            <!-- Work Scope Card -->
            @if($contract->work_scope)
            <div class="card p-6">
                <h4 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-tasks mr-2" style="color: var(--primary);"></i> Work Scope
                </h4>
                <ul class="list-disc list-inside space-y-1" style="color: var(--text-primary);">
                    @foreach(json_decode($contract->work_scope, true) ?? [] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>

        <!-- RIGHT COLUMN - Sidebar -->
        <div class="lg:col-span-1">
            <!-- Status Actions Card -->
            <div class="card p-6 mb-6">
                <h4 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-cog mr-2" style="color: var(--primary);"></i> Actions
                </h4>
                
                <div class="space-y-3">
                    @if($contract->property)
                        <a href="{{ route('properties.show', $contract->property->id) }}" 
                           class="w-full px-4 py-2 border rounded-lg hover:bg-gray-50 flex items-center justify-center transition-colors" 
                           style="border-color: var(--border-color); color: var(--text-primary);">
                            <i class="fas fa-building mr-2"></i> View Property
                        </a>
                    @endif
                    
                    @if(in_array($contract->status, ['draft', 'pending_approval']))
                        <a href="{{ route('landlord.construction.contract.edit', $contract) }}" 
                           class="w-full px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 flex items-center justify-center transition-colors">
                            <i class="fas fa-edit mr-2"></i> Edit Contract
                        </a>
                    @endif
                    
                    @if($contract->status == 'pending_approval')
                        <div class="p-3 rounded-lg text-center" style="background: rgba(var(--warning-rgb), 0.1);">
                            <i class="fas fa-clock text-yellow-500 text-xl mb-1"></i>
                            <p class="text-sm" style="color: var(--text-primary);">Awaiting admin approval</p>
                            <p class="text-xs" style="color: var(--text-secondary);">Submitted {{ $contract->created_at->diffForHumans() }}</p>
                        </div>
                    @endif
                    
                    @if($contract->status == 'approved')
                        <div class="p-3 rounded-lg text-center" style="background: rgba(var(--success-rgb), 0.1);">
                            <i class="fas fa-check-circle text-green-500 text-xl mb-1"></i>
                            <p class="text-sm" style="color: var(--text-primary);">Contract approved</p>
                            <p class="text-xs" style="color: var(--text-secondary);">Ready to begin work</p>
                        </div>
                    @endif
                    
                    @if($contract->status == 'in_progress')
                        <div class="p-3 rounded-lg text-center" style="background: rgba(var(--primary-rgb), 0.1);">
                            <i class="fas fa-hard-hat text-blue-500 text-xl mb-1"></i>
                            <p class="text-sm" style="color: var(--text-primary);">Work in progress</p>
                            <p class="text-xs" style="color: var(--text-secondary);">Project is currently ongoing</p>
                        </div>
                    @endif
                    
                    @if($contract->status == 'completed')
                        <div class="p-3 rounded-lg text-center" style="background: rgba(var(--success-rgb), 0.1);">
                            <i class="fas fa-flag-checkered text-green-500 text-xl mb-1"></i>
                            <p class="text-sm" style="color: var(--text-primary);">Project completed</p>
                            <p class="text-xs" style="color: var(--text-secondary);">Work has been finished</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Contractor Info Card -->
            <div class="card p-6 mb-6">
                <h4 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-user-tie mr-2" style="color: var(--primary);"></i> Contractor
                </h4>
                
                <div class="space-y-2">
                    <p class="font-medium" style="color: var(--text-primary);">{{ $contract->contractor_name }}</p>
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
                                    Reg #: {{ $contract->company_registration_number }}
                                </p>
                            @endif
                            @if($contract->company_tin)
                                <p class="text-sm" style="color: var(--text-primary);">
                                    TIN: {{ $contract->company_tin }}
                                </p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <!-- Activity Log Card -->
            @if($contract->activityLogs && $contract->activityLogs->count() > 0)
            <div class="card p-6">
                <h4 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-history mr-2" style="color: var(--primary);"></i> Activity Log
                </h4>
                
                <div class="space-y-3 max-h-60 overflow-y-auto">
                    @foreach($contract->activityLogs->take(10) as $log)
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center" 
                                 style="background: rgba(var(--primary-rgb), 0.1);">
                                <i class="fas fa-user text-xs" style="color: var(--primary);"></i>
                            </div>
                            <div>
                                <p class="text-sm" style="color: var(--text-primary);">
                                    {{ $log->description }}
                                </p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    {{ $log->created_at->diffForHumans() }}
                                    @if($log->user)
                                        • by {{ $log->user->name }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide messages
    const messages = document.querySelectorAll('.bg-green-100, .bg-red-100');
    messages.forEach(function(msg) {
        setTimeout(function() {
            msg.style.opacity = '0';
            msg.style.transition = 'opacity 0.5s ease';
            setTimeout(function() {
                msg.style.display = 'none';
            }, 500);
        }, 5000);
    });
});
</script>
@endsection