@extends('layouts.field')

@section('title', 'Registration Plans - Field Agent')

@section('content')
@php
    /* ── FIXED: precompute property counts in two queries instead of N ──
     *
     * The old template called Property::where(...)->count() inside the
     * loop — once per plan. That's N+1 queries on the page.
     *
     * We now fetch both counts (plan-wide and my own) in two grouped
     * queries, keyed by plan id, and look them up inside the loop.
     */
    $allPlans = $assignments->pluck('registrationPlan')
        ->merge($completedPlans->pluck('registrationPlan'))
        ->filter()
        ->unique('id');

    $planIds = $allPlans->pluck('id')->values();

    $planWideCounts = $planIds->isEmpty()
        ? collect()
        : \App\Models\Property::whereIn('registration_plan_id', $planIds)
            ->select('registration_plan_id', \DB::raw('count(*) as total'))
            ->groupBy('registration_plan_id')
            ->pluck('total', 'registration_plan_id');

    $myCounts = $planIds->isEmpty()
        ? collect()
        : \App\Models\Property::whereIn('registration_plan_id', $planIds)
            ->where('registered_by', auth()->id())
            ->select('registration_plan_id', \DB::raw('count(*) as total'))
            ->groupBy('registration_plan_id')
            ->pluck('total', 'registration_plan_id');

    /* Top stat: SUM of plan-wide counts across all plans shown.
     * FIXED: was previously SUM of MY registrations, mislabelled
     * "Total Properties". */
    $totalPropertiesRegistered = $planWideCounts->sum();
@endphp

<div class="plans-container">
    <!-- Page Header -->
    <div class="plans-header mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-clipboard-list mr-2" style="color: var(--primary);"></i>Registration Plans
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Track your assigned registration plans and progress
                </p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('field-agent.dashboard') }}" 
                   class="px-4 py-2 rounded-lg transition-all hover:shadow-md inline-flex items-center"
                   style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
                <a href="{{ route('field-agent.properties.create') }}" 
                   class="px-4 py-2 rounded-lg transition-all hover:shadow-md inline-flex items-center"
                   style="background-color: var(--primary); color: white;">
                    <i class="fas fa-plus-circle mr-2"></i> Register Property
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Summary -->
    <div class="stats-grid grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Active Plans</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $assignments->count() }}</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                    <i class="fas fa-play text-white text-xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Completed Plans</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $completedPlans->count() }}</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <i class="fas fa-check-circle text-white text-xl"></i>
                </div>
            </div>
        </div>

        {{-- FIXED: this used to sum MY registrations but was labelled
             "Total Properties". It now sums the plan-wide counts so it
             matches the "Registered" number on every card below. --}}
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Properties</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $totalPropertiesRegistered }}
                    </p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                    <i class="fas fa-building text-white text-xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Overall Progress</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);" id="overall-progress">0%</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <i class="fas fa-chart-line text-white text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Plans Section -->
    <div class="active-plans mb-8">
        <h2 class="text-xl font-semibold mb-4" style="color: var(--text-primary);">
            <i class="fas fa-play-circle mr-2" style="color: var(--primary);"></i>Active Registration Plans
        </h2>
        
        @if($assignments->count() > 0)
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @foreach($assignments as $assignment)
                    @php
                        $plan = $assignment->registrationPlan;

                        /* ── FIXED: plan-wide semantics ──
                         *
                         *   totalProperties = plan.estimated_houses (target)
                         *   registeredCount = ALL registrations under the plan
                         *   remaining       = max(0, target - registeredCount)
                         *   percentage      = registeredCount / target
                         *   myRegistered    = MY registrations (small chip)
                         */
                        $totalProperties = $plan ? ($plan->estimated_houses ?? $plan->total_properties ?? 0) : 0;

                        $registeredCount = $plan
                            ? (int) ($planWideCounts[$plan->id] ?? 0)
                            : 0;

                        $myRegistered = $plan
                            ? (int) ($myCounts[$plan->id] ?? 0)
                            : 0;

                        $remaining = max(0, $totalProperties - $registeredCount);

                        $percentage = $plan && $totalProperties > 0 
                            ? round(($registeredCount / $totalProperties) * 100, 1) : 0;

                        $isOverdue = $plan && $plan->registration_end_date && $plan->registration_end_date < now();
                        $daysLeft = $plan && $plan->registration_end_date ? now()->diffInDays($plan->registration_end_date, false) : null;
                        $planName = $plan ? ($plan->name ?? $plan->zone . ($plan->section ? ' - ' . $plan->section : '')) : 'Unknown Plan';
                    @endphp
                    
                    <div class="plan-card rounded-xl transition-all hover:shadow-lg" 
                         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                        <div class="p-6">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">{{ $planName }}</h3>
                                    @if($plan && $plan->zone)
                                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                            <i class="fas fa-map-marker-alt mr-1"></i> {{ $plan->zone }}
                                            @if($plan->section)
                                                , {{ $plan->section }}
                                            @endif
                                        </p>
                                    @endif
                                </div>
                                @if($isOverdue)
                                    <span class="px-2 py-1 text-xs rounded-full" style="background-color: rgba(239, 68, 68, 0.2); color: #ef4444;">
                                        <i class="fas fa-exclamation-triangle mr-1"></i> Overdue
                                    </span>
                                @elseif($percentage >= 100)
                                    <span class="px-2 py-1 text-xs rounded-full" style="background-color: rgba(16, 185, 129, 0.2); color: #10b981;">
                                        <i class="fas fa-check-circle mr-1"></i> Completed
                                    </span>
                                @else
                                    <span class="px-2 py-1 text-xs rounded-full" style="background-color: rgba(59, 130, 246, 0.2); color: #3b82f6;">
                                        <i class="fas fa-hourglass-half mr-1"></i> In Progress
                                    </span>
                                @endif
                            </div>
                            
                            <!-- Progress Bar -->
                            <div class="mb-4">
                                <div class="flex justify-between text-sm mb-1">
                                    <span style="color: var(--text-secondary);">Progress</span>
                                    <span style="color: var(--text-primary);">{{ $percentage }}%</span>
                                </div>
                                <div class="w-full rounded-full h-2" style="background-color: var(--bg-secondary);">
                                    <div class="rounded-full h-2 transition-all duration-500" 
                                         style="width: {{ $percentage }}%; background-color: {{ $percentage >= 100 ? '#10b981' : 'var(--primary)' }};"></div>
                                </div>
                            </div>
                            
                            <!-- Stats Grid -->
                            <div class="grid grid-cols-3 gap-4 mb-4">
                                <div class="text-center">
                                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $totalProperties }}</p>
                                    <p class="text-xs" style="color: var(--text-secondary);">Total Properties</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-2xl font-bold" style="color: #10b981;">{{ $registeredCount }}</p>
                                    <p class="text-xs" style="color: var(--text-secondary);">Registered</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-2xl font-bold" style="color: #f59e0b;">{{ $remaining }}</p>
                                    <p class="text-xs" style="color: var(--text-secondary);">Remaining</p>
                                </div>
                            </div>

                            {{-- FIXED: personal contribution chip.
                                 Mirrors the mobile screen's "You: N of M"
                                 so agents can see their own number without
                                 confusing the plan-wide stats above. --}}
                            <div class="mb-4">
                                <span class="inline-flex items-center px-2 py-1 text-xs rounded-md"
                                      style="background-color: rgba(139, 92, 246, 0.08); color: {{ $myRegistered > 0 ? '#8b5cf6' : 'var(--text-secondary)' }};">
                                    <i class="fas fa-user mr-1"></i>
                                    You: {{ $myRegistered }} of {{ $registeredCount }}
                                </span>
                            </div>
                            
                            <!-- Deadline Info -->
                            @if($plan && $plan->registration_end_date)
                                <div class="mb-4 p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="text-xs" style="color: var(--text-secondary);">
                                                <i class="far fa-calendar-alt mr-1"></i> Deadline
                                            </p>
                                            <p class="text-sm font-medium" style="color: var(--text-primary);">
                                                {{ \Carbon\Carbon::parse($plan->registration_end_date)->format('F j, Y') }}
                                            </p>
                                        </div>
                                        @if($daysLeft !== null && $daysLeft > 0 && $percentage < 100)
                                            <div class="text-right">
                                                <p class="text-xs" style="color: var(--text-secondary);">Days Left</p>
                                                <p class="text-sm font-bold" style="color: {{ $daysLeft <= 7 ? '#ef4444' : 'var(--primary)' }};">
                                                    {{ $daysLeft }} days
                                                </p>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                            
                            <!-- Action Buttons -->
                            <div class="flex space-x-3">
                                <a href="{{ route('field-agent.registration-plans.show', $plan->id) }}" 
                                   class="flex-1 px-4 py-2 rounded-lg text-center transition-all hover:shadow-md"
                                   style="background-color: var(--primary); color: white;">
                                    <i class="fas fa-eye mr-1"></i> View Details
                                </a>
                                <a href="{{ route('field-agent.properties.create', ['plan_id' => $plan->id]) }}" 
                                   class="flex-1 px-4 py-2 rounded-lg text-center transition-all hover:shadow-md"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                                    <i class="fas fa-plus-circle mr-1"></i> Register
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-12 rounded-xl" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                <i class="fas fa-clipboard-list text-5xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">No Active Plans</p>
                <p class="text-sm" style="color: var(--text-secondary);">You don't have any active registration plans assigned yet.</p>
                <p class="text-sm mt-2" style="color: var(--text-secondary);">Contact your administrator for plan assignments.</p>
            </div>
        @endif
    </div>

    <!-- Completed Plans Section -->
    @if($completedPlans->count() > 0)
    <div class="completed-plans">
        <h2 class="text-xl font-semibold mb-4" style="color: var(--text-primary);">
            <i class="fas fa-check-circle mr-2" style="color: #10b981;"></i>Completed Plans
        </h2>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @foreach($completedPlans as $assignment)
                @php
                    $plan = $assignment->registrationPlan;

                    /* ── FIXED: same plan-wide semantics as active cards.
                     *     Two numbers are shown on completed cards:
                     *       Total Properties = target
                     *       Registered       = plan-wide count
                     *     Progress is hardcoded to 100% (plan is done).
                     */
                    $totalProperties = $plan ? ($plan->estimated_houses ?? $plan->total_properties ?? 0) : 0;

                    $registeredCount = $plan
                        ? (int) ($planWideCounts[$plan->id] ?? 0)
                        : 0;

                    $myRegistered = $plan
                        ? (int) ($myCounts[$plan->id] ?? 0)
                        : 0;

                    $planName = $plan ? ($plan->name ?? $plan->zone . ($plan->section ? ' - ' . $plan->section : '')) : 'Unknown Plan';
                @endphp
                
                <div class="plan-card rounded-xl transition-all hover:shadow-lg opacity-75" 
                     style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">{{ $planName }}</h3>
                                @if($plan && $plan->zone)
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-map-marker-alt mr-1"></i> {{ $plan->zone }}
                                        @if($plan->section)
                                            , {{ $plan->section }}
                                        @endif
                                    </p>
                                @endif
                            </div>
                            <span class="px-2 py-1 text-xs rounded-full" style="background-color: rgba(16, 185, 129, 0.2); color: #10b981;">
                                <i class="fas fa-check-circle mr-1"></i> Completed
                            </span>
                        </div>
                        
                        <div class="mb-4">
                            <div class="flex justify-between text-sm mb-1">
                                <span style="color: var(--text-secondary);">Final Progress</span>
                                <span style="color: var(--text-primary);">100%</span>
                            </div>
                            <div class="w-full rounded-full h-2" style="background-color: var(--bg-secondary);">
                                <div class="rounded-full h-2" style="width: 100%; background-color: #10b981;"></div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div class="text-center">
                                <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $totalProperties }}</p>
                                <p class="text-xs" style="color: var(--text-secondary);">Total Properties</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold" style="color: #10b981;">{{ $registeredCount }}</p>
                                <p class="text-xs" style="color: var(--text-secondary);">Registered</p>
                            </div>
                        </div>

                        {{-- FIXED: personal contribution chip on completed cards too. --}}
                        <div class="mb-4">
                            <span class="inline-flex items-center px-2 py-1 text-xs rounded-md"
                                  style="background-color: rgba(139, 92, 246, 0.08); color: {{ $myRegistered > 0 ? '#8b5cf6' : 'var(--text-secondary)' }};">
                                <i class="fas fa-user mr-1"></i>
                                You: {{ $myRegistered }} of {{ $registeredCount }}
                            </span>
                        </div>
                        
                        <a href="{{ route('field-agent.registration-plans.show', $plan->id) }}" 
                           class="block w-full px-4 py-2 rounded-lg text-center transition-all hover:shadow-md"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                            <i class="fas fa-eye mr-1"></i> View Summary
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Performance Summary -->
    <div class="performance-summary mt-8 rounded-xl p-6" 
         style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
            <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>Performance Summary
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="text-center">
                <p class="text-3xl font-bold" style="color: var(--primary);">{{ $assignments->count() + $completedPlans->count() }}</p>
                <p class="text-sm" style="color: var(--text-secondary);">Total Plans Assigned</p>
            </div>
            <div class="text-center">
                <p class="text-3xl font-bold" style="color: #10b981;">{{ $completedPlans->count() }}</p>
                <p class="text-sm" style="color: var(--text-secondary);">Plans Completed</p>
            </div>
            <div class="text-center">
                @php
                    $totalPlansCount = $assignments->count() + $completedPlans->count();
                    $completionRate = $totalPlansCount > 0 
                        ? round(($completedPlans->count() / $totalPlansCount) * 100, 1)
                        : 0;
                @endphp
                <p class="text-3xl font-bold" style="color: #f59e0b;">{{ $completionRate }}%</p>
                <p class="text-sm" style="color: var(--text-secondary);">Completion Rate</p>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    /* CSS Variables */
    :root {
        --primary: #3b82f6;
        --primary-rgb: 59, 130, 246;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
        --text-primary: #1f2937;
        --text-secondary: #6b7280;
        --card-bg: #ffffff;
        --bg-secondary: #f3f4f6;
        --border-color: #e5e7eb;
    }

    /* Dark mode support */
    @media (prefers-color-scheme: dark) {
        :root {
            --text-primary: #f9fafb;
            --text-secondary: #9ca3af;
            --card-bg: #1f2937;
            --bg-secondary: #374151;
            --border-color: #374151;
        }
    }

    .plans-container {
        max-width: 1600px;
        margin: 0 auto;
        padding: 0 1rem;
    }

    /* Plan Card Hover Effect */
    .plan-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .plan-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
    }

    /* Stat Card Hover */
    .stat-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }

    /* Progress Bar Animation */
    .rounded-full {
        transition: width 0.5s ease-in-out;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .plans-container {
            padding: 0 0.5rem;
        }
        .stats-grid {
            gap: 1rem;
        }
        .stat-card {
            padding: 1rem;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    // Calculate overall progress
    document.addEventListener('DOMContentLoaded', function() {
        const planCards = document.querySelectorAll('.active-plans .plan-card');
        let totalProgress = 0;
        let planCount = 0;
        
        planCards.forEach(card => {
            const progressText = card.querySelector('.flex.justify-between.text-sm.mb-1 span:last-child');
            if (progressText) {
                const percentage = parseFloat(progressText.textContent);
                if (!isNaN(percentage)) {
                    totalProgress += percentage;
                    planCount++;
                }
            }
        });
        
        const overallProgress = planCount > 0 ? Math.round(totalProgress / planCount) : 0;
        const overallElement = document.getElementById('overall-progress');
        if (overallElement) {
            overallElement.textContent = overallProgress + '%';
        }
    });
</script>
@endpush

@endsection