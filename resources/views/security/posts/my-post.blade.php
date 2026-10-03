@extends('layouts.secu')

@section('title', 'My Post')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-user-check text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-map-marker-alt mr-2" style="color: var(--success);"></i>
                        My Post Today
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>{{ auth()->user()->name }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-calendar mr-1"></i>
                        <span>{{ now()->format('l, F j, Y') }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('security.posts.index') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> All Posts
                </a>
            </div>
        </div>
    </div>

    @if(isset($post) && isset($schedule))
        <!-- Post Info -->
        <div class="card p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center">
                    <div class="w-14 h-14 rounded-full flex items-center justify-center mr-4"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-size: 22px; font-weight: 600;">
                        {{ substr($post->name, 0, 1) }}
                    </div>
                    <div>
                        <h3 class="text-xl font-bold" style="color: var(--text-primary);">{{ $post->name }}</h3>
                        <div class="text-sm" style="color: var(--text-secondary);">
                            <span class="mr-3">
                                <i class="fas fa-tag mr-1"></i> {{ $post->code ?? 'N/A' }}
                            </span>
                            <span>
                                <i class="fas fa-location-dot mr-1"></i> {{ $post->location ?? 'No location' }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="px-3 py-1 rounded-full text-sm font-medium badge-success">
                        <i class="fas fa-check-circle mr-1"></i> Assigned
                    </span>
                    <a href="{{ route('security.posts.show', $post->id) }}"
                       class="px-4 py-2 rounded-lg text-sm font-medium text-white btn-primary">
                        <i class="fas fa-eye mr-2"></i> View Post
                    </a>
                </div>
            </div>
        </div>

        <!-- Shift Info -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="card p-6">
                <h4 class="text-sm font-semibold uppercase tracking-wider mb-4" style="color: var(--text-secondary);">
                    <i class="fas fa-clock mr-2"></i> Your Shift
                </h4>
                @if($schedule->shift)
                    <div class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $schedule->shift->name }}
                    </div>
                    <div class="text-lg mt-1" style="color: var(--text-secondary);">
                        {{ substr($schedule->shift->start_time, 0, 5) }} - {{ substr($schedule->shift->end_time, 0, 5) }}
                    </div>
                    @if($schedule->shift->is_overnight)
                        <span class="mt-2 inline-block px-2 py-1 text-xs rounded-full badge-info">
                            🌙 Overnight Shift
                        </span>
                    @endif
                @else
                    <div style="color: var(--text-secondary);">No shift assigned</div>
                @endif
            </div>

            <div class="card p-6">
                <h4 class="text-sm font-semibold uppercase tracking-wider mb-4" style="color: var(--text-secondary);">
                    <i class="fas fa-users mr-2"></i> Team Today
                </h4>
                @if(isset($personnel) && $personnel->count() > 0)
                    <div class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $personnel->count() }}
                    </div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">
                        personnel at this post
                    </div>
                    <div class="mt-2 flex flex-wrap gap-1">
                        @foreach($personnel as $p)
                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                                {{ $p->securityUser->name ?? 'Unknown' }}
                            </span>
                        @endforeach
                    </div>
                @else
                    <div style="color: var(--text-secondary);">No other personnel scheduled</div>
                @endif
            </div>

            <div class="card p-6">
                <h4 class="text-sm font-semibold uppercase tracking-wider mb-4" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-2"></i> Status
                </h4>
                <div class="flex items-center">
                    <span class="px-3 py-1 rounded-full text-sm font-medium
                        {{ $schedule->status == 'active' ? 'badge-success' : ($schedule->status == 'scheduled' ? 'badge-info' : 'badge-secondary') }}">
                        {{ ucfirst($schedule->status) }}
                    </span>
                </div>
                @if($schedule->checkin_time)
                    <div class="mt-2 text-sm" style="color: var(--text-primary);">
                        <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                        Checked in at {{ $schedule->checkin_time->format('H:i') }}
                    </div>
                @else
                    <div class="mt-2 text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-hourglass-half mr-1" style="color: var(--warning);"></i>
                        Not checked in yet
                    </div>
                @endif
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <a href="{{ route('security.posts.show', $post->id) }}" 
               class="card p-6 hover:shadow-lg transition-shadow duration-300 flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4" style="background-color: rgba(var(--info-rgb), 0.1);">
                        <i class="fas fa-building text-xl" style="color: var(--info);"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold" style="color: var(--text-primary);">Post Details</h4>
                        <p class="text-sm" style="color: var(--text-secondary);">View full post information</p>
                    </div>
                </div>
                <i class="fas fa-arrow-right" style="color: var(--text-secondary);"></i>
            </a>

            <a href="{{ route('security.posts.schedule', ['securityPost' => $post->id]) }}" 
               class="card p-6 hover:shadow-lg transition-shadow duration-300 flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4" style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-calendar-alt text-xl" style="color: var(--primary);"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold" style="color: var(--text-primary);">Post Schedule</h4>
                        <p class="text-sm" style="color: var(--text-secondary);">View full post schedule</p>
                    </div>
                </div>
                <i class="fas fa-arrow-right" style="color: var(--text-secondary);"></i>
            </a>

            <a href="{{ route('security.schedules.index') }}" 
               class="card p-6 hover:shadow-lg transition-shadow duration-300 flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4" style="background-color: rgba(var(--success-rgb), 0.1);">
                        <i class="fas fa-calendar-check text-xl" style="color: var(--success);"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold" style="color: var(--text-primary);">My Schedules</h4>
                        <p class="text-sm" style="color: var(--text-secondary);">View all your schedules</p>
                    </div>
                </div>
                <i class="fas fa-arrow-right" style="color: var(--text-secondary);"></i>
            </a>
        </div>
    @else
        <!-- No Post Assigned -->
        <div class="card p-12 text-center">
            <div class="w-24 h-24 mx-auto mb-6 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                <i class="fas fa-user-slash text-4xl" style="color: var(--info);"></i>
            </div>
            <h3 class="text-xl font-semibold mb-3" style="color: var(--text-primary);">No Post Assigned Today</h3>
            <p class="mb-6" style="color: var(--text-secondary);">
                You are not scheduled for any post today. Please check your schedule or contact your supervisor.
            </p>
            <a href="{{ route('security.schedules.index') }}" 
               class="inline-flex items-center px-6 py-3 rounded-lg text-sm font-medium text-white btn-primary">
                <i class="fas fa-calendar-alt mr-2"></i> View My Schedules
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

.badge-success { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3); }
.badge-warning { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3); }
.badge-danger { background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3); }
.badge-info { background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3); }
.badge-primary { background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3); }
.badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3); }
</style>
@endsection