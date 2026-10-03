@extends('layouts.secu')

@section('title', 'My Current Assignments')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Welcome Header -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-tasks text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                        My Current Assignments
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>{{ auth()->user()->name }} - {{ auth()->user()->badge_number ?? 'No Badge' }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-calendar mr-1"></i>
                        <span>{{ now()->format('l, F j, Y') }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('security.supervisor.dashboard') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                </a>
                <a href="{{ route('security.supervisor.assignments.index') }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium text-white inline-flex items-center btn-primary">
                    <i class="fas fa-list mr-2"></i> All Assignments
                </a>
            </div>
        </div>
    </div>

    <!-- Assignments List -->
    @if(isset($assignments) && $assignments->count() > 0)
        <div class="grid grid-cols-1 gap-4">
            @foreach($assignments as $assignment)
                <div class="card p-6 transition-all duration-200 hover:shadow-lg">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div class="flex items-start md:items-center">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4 flex-shrink-0"
                                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-size: 18px; font-weight: 600;">
                                {{ $assignment->post ? substr($assignment->post->name, 0, 1) : 'P' }}
                            </div>
                            <div>
                                <h4 class="text-lg font-semibold" style="color: var(--text-primary);">
                                    {{ $assignment->post->name ?? 'No Post Assigned' }}
                                </h4>
                                <div class="flex flex-wrap items-center gap-2 mt-1">
                                    <span class="text-sm" style="color: var(--text-secondary);">
                                        <i class="fas fa-tag mr-1"></i>
                                        {{ $assignment->supervisor_type_name ?? 'Supervisor' }}
                                    </span>
                                    @if($assignment->is_primary_supervisor)
                                        <span class="px-2 py-0.5 text-xs rounded-full" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                            <i class="fas fa-star mr-1"></i> Primary
                                        </span>
                                    @endif
                                    <span class="px-2 py-0.5 text-xs rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                        <i class="fas fa-calendar-alt mr-1"></i>
                                        {{ $assignment->start_date->format('M d, Y') }}
                                        @if($assignment->end_date)
                                            - {{ $assignment->end_date->format('M d, Y') }}
                                        @else
                                            - Ongoing
                                        @endif
                                    </span>
                                </div>
                                @if($assignment->notes)
                                    <p class="text-sm mt-2" style="color: var(--text-secondary);">
                                        <i class="fas fa-comment mr-1"></i>
                                        {{ $assignment->notes }}
                                    </p>
                                @endif
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-2">
                            <!-- Post Schedule Button -->
                            @if($assignment->security_post_id)
                                <a href="{{ route('security.supervisor.posts.schedule', ['postId' => $assignment->security_post_id]) }}"
                                   class="px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 hover:scale-105"
                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                    <i class="fas fa-calendar-alt mr-1"></i> Schedule
                                </a>
                            @endif
                            
                            <!-- View Details -->
                            <a href="{{ route('security.supervisor.assignments.show', $assignment->id) }}"
                               class="px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 hover:scale-105"
                               style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                                <i class="fas fa-eye mr-1"></i> Details
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <!-- Empty State -->
        <div class="card p-12 text-center">
            <div class="w-24 h-24 mx-auto mb-6 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                <i class="fas fa-clipboard-list text-4xl" style="color: var(--info);"></i>
            </div>
            <h3 class="text-xl font-semibold mb-3" style="color: var(--text-primary);">No Current Assignments</h3>
            <p class="mb-6" style="color: var(--text-secondary);">You don't have any active supervisor assignments at the moment.</p>
            <a href="{{ route('security.supervisor.dashboard') }}" 
               class="inline-flex items-center px-6 py-3 rounded-lg text-sm font-medium text-white btn-primary">
                <i class="fas fa-tachometer-alt mr-2"></i> Go to Dashboard
            </a>
        </div>
    @endif
</div>

<style>
.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    transition: all 0.2s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.card {
    transition: all 0.2s ease;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
}

.card:hover {
    transform: translateY(-2px);
}
</style>
@endsection