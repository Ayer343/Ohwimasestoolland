@extends('layouts.field')

@section('title', 'My Performance - Field Agent')

@section('content')
{{-- ============================================ --}}
{{-- SET DEFAULT VALUES TO PREVENT ERRORS        --}}
{{-- ============================================ --}}
@php
    // Core stats with defaults
    $totalProperties = $totalProperties ?? 0;
    $activePlans = $activePlans ?? 0;
    $completedPlans = $completedPlans ?? 0;
    $totalPlansAssigned = $totalPlansAssigned ?? 0;
    $completionRate = $completionRate ?? 0;
    $averagePerPlan = $averagePerPlan ?? 0;
    
    // Display versions (filtered by plan)
    $displayActivePlans = $displayActivePlans ?? $activePlans;
    $displayCompletedPlans = $displayCompletedPlans ?? $completedPlans;
    $displayTotalProperties = $displayTotalProperties ?? $totalProperties;
    
    // Filter data
    $selectedPlanId = $selectedPlanId ?? null;
    $selectedPlan = $selectedPlan ?? null;
    $planDetails = $planDetails ?? null;
    $availablePlans = $availablePlans ?? collect();
    $planPerformance = $planPerformance ?? collect();
    
    // Chart data
    $propertyTypeStats = $propertyTypeStats ?? collect();
    $monthlyData = $monthlyData ?? collect();
    
    // Lists
    $recentProperties = $recentProperties ?? collect();
    $achievements = $achievements ?? collect();
    
    // Additional metrics
    $verificationStats = $verificationStats ?? ['verified' => 0, 'pending' => 0, 'rejected' => 0];
    $thisMonthCount = $thisMonthCount ?? 0;
    $lastMonthCount = $lastMonthCount ?? 0;
    $monthlyChange = $monthlyChange ?? 0;
    
    // Ensure collections are properly initialized
    if (!($propertyTypeStats instanceof \Illuminate\Support\Collection)) {
        $propertyTypeStats = collect($propertyTypeStats);
    }
    if (!($monthlyData instanceof \Illuminate\Support\Collection)) {
        $monthlyData = collect($monthlyData);
    }
    if (!($recentProperties instanceof \Illuminate\Support\Collection)) {
        $recentProperties = collect($recentProperties);
    }
    if (!($achievements instanceof \Illuminate\Support\Collection)) {
        $achievements = collect($achievements);
    }
    if (!($availablePlans instanceof \Illuminate\Support\Collection)) {
        $availablePlans = collect($availablePlans);
    }
    if (!($planPerformance instanceof \Illuminate\Support\Collection)) {
        $planPerformance = collect($planPerformance);
    }
    
    // Determine if showing filtered view
    $isFiltered = !empty($selectedPlanId);
@endphp

<div class="performance-container">
    <!-- Page Header -->
    <div class="performance-header mb-6">
        <div class="flex justify-between items-center flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>My Performance
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Track your registration performance and achievements
                    @if($isFiltered)
                        <span class="ml-2 px-2 py-0.5 text-xs rounded-full" style="background-color: rgba(59, 130, 246, 0.2); color: var(--primary);">
                            <i class="fas fa-filter mr-1"></i> Filtered
                        </span>
                    @endif
                </p>
            </div>
            <div class="flex space-x-3 flex-wrap gap-2">
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

    <!-- ============================================ -->
    <!-- PLAN FILTER DROPDOWN                         -->
    <!-- ============================================ -->
    <div class="plan-filter-section mb-6">
        <div class="rounded-xl p-4" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <form method="GET" action="{{ route('field-agent.performance') }}" class="flex flex-wrap items-center gap-4">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-filter" style="color: var(--text-secondary);"></i>
                    <label for="registration_plan_id" class="text-sm font-medium" style="color: var(--text-primary);">
                        Filter by Plan:
                    </label>
                </div>
                
                <div class="flex-1 min-w-[200px]">
                    <select name="registration_plan_id" id="registration_plan_id" 
                            class="w-full rounded-lg px-4 py-2 text-sm transition-all focus:ring-2 focus:ring-opacity-50"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                            onchange="this.form.submit()">
                        <option value="">All Plans ({{ $totalPlansAssigned }} total)</option>
                        @forelse($availablePlans as $plan)
                            <option value="{{ $plan->id }}" 
                                    {{ $selectedPlanId == $plan->id ? 'selected' : '' }}>
                                {{ $plan->name ?? $plan->zone . ($plan->section ? ' - ' . $plan->section : '') }}
                                @if(isset($plan->status))
                                    ({{ ucfirst($plan->status) }})
                                @endif
                            </option>
                        @empty
                            <option value="" disabled>No active plans available</option>
                        @endforelse
                    </select>
                </div>
                
                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium transition-all hover:shadow-md"
                        style="background-color: var(--primary); color: white;">
                    <i class="fas fa-search mr-1"></i> Apply
                </button>
                
                @if($selectedPlanId)
                    <a href="{{ route('field-agent.performance') }}" 
                       class="px-4 py-2 rounded-lg text-sm font-medium transition-all hover:shadow-md inline-flex items-center"
                       style="background-color: var(--bg-secondary); color: var(--text-secondary); border: 1px solid var(--border-color);">
                        <i class="fas fa-times mr-1"></i> Clear
                    </a>
                @endif
            </form>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- SELECTED PLAN INFORMATION                    -->
    <!-- ============================================ -->
    @if($planDetails)
    <div class="selected-plan-info mb-6">
        <div class="rounded-xl p-4" 
             style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(59, 130, 246, 0.05) 100%); border: 1px solid rgba(59, 130, 246, 0.2);">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center" 
                         style="background: linear-gradient(135deg, var(--primary) 0%, #2563eb 100%);">
                        <i class="fas fa-clipboard-list text-white text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold" style="color: var(--text-primary);">
                            {{ $planDetails->name ?? 'Selected Plan' }}
                            <span class="inline-block px-2 py-0.5 text-xs rounded-full ml-2" 
                                  style="background-color: {{ ($planDetails->status ?? '') === 'completed' ? 'rgba(16, 185, 129, 0.2)' : 'rgba(59, 130, 246, 0.2)' }}; 
                                         color: {{ ($planDetails->status ?? '') === 'completed' ? '#10b981' : 'var(--primary)' }};">
                                {{ ucfirst($planDetails->status ?? 'active') }}
                            </span>
                        </h3>
                        <p class="text-xs" style="color: var(--text-secondary);">
                            @if(isset($planDetails->zone))
                                Zone: {{ $planDetails->zone }}
                                @if(isset($planDetails->section))
                                    , Section: {{ $planDetails->section }}
                                @endif
                            @endif
                            @if(isset($planDetails->total_plan_properties))
                                <span class="ml-2">• Total in plan: {{ $planDetails->total_plan_properties }}</span>
                            @endif
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    <div class="text-center">
                        <p class="text-sm font-bold" style="color: var(--text-primary);">
                            {{ $planDetails->total_properties ?? 0 }}
                        </p>
                        <p class="text-xs" style="color: var(--text-secondary);">Your Registrations</p>
                    </div>
                    <div class="text-center">
                        <p class="text-sm font-bold" style="color: var(--text-primary);">
                            {{ $planDetails->estimated_total ?? 0 }}
                        </p>
                        <p class="text-xs" style="color: var(--text-secondary);">Estimated Total</p>
                    </div>
                    <div class="text-center">
                        <p class="text-sm font-bold" style="color: var(--text-primary);">
                            {{ $planDetails->completion_percentage ?? 0 }}%
                        </p>
                        <p class="text-xs" style="color: var(--text-secondary);">Your Progress</p>
                    </div>
                    @if(isset($planDetails->start_date) || isset($planDetails->end_date))
                    <div class="text-center">
                        <p class="text-xs" style="color: var(--text-secondary);">
                            @if(isset($planDetails->start_date))
                                <i class="far fa-calendar-alt mr-1"></i>
                                {{ \Carbon\Carbon::parse($planDetails->start_date)->format('M d, Y') }}
                            @endif
                            @if(isset($planDetails->end_date))
                                <i class="fas fa-arrow-right mx-1 text-xs"></i>
                                {{ \Carbon\Carbon::parse($planDetails->end_date)->format('M d, Y') }}
                            @endif
                        </p>
                    </div>
                    @endif
                </div>
            </div>
            <!-- Progress Bar -->
            <div class="mt-3">
                <div class="flex justify-between text-xs mb-1">
                    <span style="color: var(--text-secondary);">Progress</span>
                    <span style="color: var(--text-primary); font-weight: 600;">{{ $planDetails->completion_percentage ?? 0 }}%</span>
                </div>
                <div class="w-full rounded-full h-2" style="background-color: var(--bg-secondary);">
                    <div class="rounded-full h-2 transition-all duration-700" 
                         style="width: {{ $planDetails->completion_percentage ?? 0 }}%; 
                                background: linear-gradient(90deg, var(--primary) 0%, #8b5cf6 100%);"></div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- ============================================ -->
    <!-- PERFORMANCE SUMMARY CARDS                    -->
    <!-- ============================================ -->
    <div class="stats-grid grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <!-- Card 1: Total Properties -->
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">
                        Total Properties
                        @if($isFiltered)
                            <span class="text-xs ml-1" style="color: var(--text-secondary);">(filtered)</span>
                        @endif
                    </p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $isFiltered ? $displayTotalProperties : $totalProperties }}
                    </p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                    <i class="fas fa-building text-white text-xl"></i>
                </div>
            </div>
            <div class="mt-2">
                <p class="text-xs" style="color: var(--text-secondary);">
                    <i class="fas fa-arrow-up mr-1" style="color: #10b981;"></i>
                    {{ $totalProperties }} total registered
                    @if($isFiltered)
                        <span class="ml-1">({{ $displayTotalProperties }} in selected plan)</span>
                    @endif
                </p>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    <i class="fas fa-calendar-alt mr-1"></i>
                    {{ $thisMonthCount }} this month
                    @if($monthlyChange > 0)
                        <span class="text-green-500">(+{{ $monthlyChange }}%)</span>
                    @elseif($monthlyChange < 0)
                        <span class="text-red-500">({{ $monthlyChange }}%)</span>
                    @endif
                </p>
            </div>
        </div>

        <!-- Card 2: Active Plans -->
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">
                        Active Plans
                        @if($isFiltered)
                            <span class="text-xs ml-1" style="color: var(--text-secondary);">(filtered)</span>
                        @endif
                    </p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $isFiltered ? $displayActivePlans : $activePlans }}
                    </p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <i class="fas fa-clipboard-list text-white text-xl"></i>
                </div>
            </div>
            <div class="mt-2">
                <p class="text-xs" style="color: var(--text-secondary);">
                    <i class="fas fa-clock mr-1" style="color: #f59e0b;"></i>
                    {{ $activePlans }} total plans in progress
                    @if($isFiltered)
                        <span class="ml-1">({{ $displayActivePlans }} active in selected plan)</span>
                    @endif
                </p>
                @if($totalPlansAssigned > 0)
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    <i class="fas fa-tasks mr-1"></i>
                    {{ $totalPlansAssigned }} total assigned plans
                </p>
                @endif
            </div>
        </div>

        <!-- Card 3: Completed Plans -->
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">
                        Completed Plans
                        @if($isFiltered)
                            <span class="text-xs ml-1" style="color: var(--text-secondary);">(filtered)</span>
                        @endif
                    </p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $isFiltered ? $displayCompletedPlans : $completedPlans }}
                    </p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                    <i class="fas fa-check-circle text-white text-xl"></i>
                </div>
            </div>
            <div class="mt-2">
                <p class="text-xs" style="color: var(--text-secondary);">
                    <i class="fas fa-trophy mr-1" style="color: #f59e0b;"></i>
                    {{ $completedPlans }} total plans completed
                    @if($isFiltered)
                        <span class="ml-1">({{ $displayCompletedPlans }} in selected plan)</span>
                    @endif
                </p>
                @if($completionRate > 0)
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    <i class="fas fa-percentage mr-1"></i>
                    {{ $completionRate }}% overall completion rate
                </p>
                @endif
            </div>
        </div>

        <!-- Card 4: Completion Rate -->
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">
                        Completion Rate
                        @if($isFiltered)
                            <span class="text-xs ml-1" style="color: var(--text-secondary);">(filtered)</span>
                        @endif
                    </p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $completionRate }}%</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <i class="fas fa-percentage text-white text-xl"></i>
                </div>
            </div>
            <div class="mt-2">
                <div class="w-full rounded-full h-1.5" style="background-color: var(--bg-secondary);">
                    <div class="rounded-full h-1.5 transition-all duration-500" 
                         style="width: {{ $completionRate }}%; background: linear-gradient(90deg, #8b5cf6 0%, #7c3aed 100%);"></div>
                </div>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    {{ $completedPlans }} of {{ $activePlans + $completedPlans }} plans completed
                </p>
                @if($averagePerPlan > 0)
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    <i class="fas fa-calculator mr-1"></i>
                    Avg. {{ $averagePerPlan }} properties per plan
                </p>
                @endif
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- VERIFICATION STATUS (Extra Card)            -->
    <!-- ============================================ -->
    @if(isset($verificationStats) && ($verificationStats['verified'] > 0 || $verificationStats['pending'] > 0 || $verificationStats['rejected'] > 0))
    <div class="verification-stats mb-8">
        <div class="rounded-xl p-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <h3 class="text-sm font-medium mb-3" style="color: var(--text-primary);">
                <i class="fas fa-check-double mr-2" style="color: var(--primary);"></i>Verification Status
                @if($isFiltered)
                    <span class="text-xs ml-2 font-normal" style="color: var(--text-secondary);">(filtered by plan)</span>
                @endif
            </h3>
            <div class="grid grid-cols-3 gap-4">
                <div class="text-center p-3 rounded-lg" style="background-color: rgba(16, 185, 129, 0.1);">
                    <p class="text-2xl font-bold" style="color: #10b981;">{{ $verificationStats['verified'] ?? 0 }}</p>
                    <p class="text-xs" style="color: var(--text-secondary);">Verified</p>
                </div>
                <div class="text-center p-3 rounded-lg" style="background-color: rgba(245, 158, 11, 0.1);">
                    <p class="text-2xl font-bold" style="color: #f59e0b;">{{ $verificationStats['pending'] ?? 0 }}</p>
                    <p class="text-xs" style="color: var(--text-secondary);">Pending</p>
                </div>
                <div class="text-center p-3 rounded-lg" style="background-color: rgba(239, 68, 68, 0.1);">
                    <p class="text-2xl font-bold" style="color: #ef4444;">{{ $verificationStats['rejected'] ?? 0 }}</p>
                    <p class="text-xs" style="color: var(--text-secondary);">Rejected</p>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- ============================================ -->
    <!-- PROPERTY TYPE DISTRIBUTION                    -->
    <!-- ============================================ -->
    @if($propertyTypeStats->count() > 0)
    <div class="property-types-section mb-8">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-chart-pie mr-2" style="color: var(--primary);"></i>Property Type Distribution
            </h2>
            @if($isFiltered)
                <span class="text-xs px-3 py-1 rounded-full" style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-filter mr-1"></i> Filtered by plan
                </span>
            @endif
        </div>
        <div class="rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach($propertyTypeStats as $stat)
                <div class="flex items-center space-x-4 p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0" 
                         style="background: linear-gradient(135deg, var(--primary) 0%, #2563eb 100%);">
                        <i class="fas fa-home text-white"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium" style="color: var(--text-primary);">
                            {{ $stat->type_name ?? 'Unknown' }}
                            @if(isset($stat->is_custom) && $stat->is_custom)
                                <span class="text-xs font-normal" style="color: var(--text-secondary);">(Custom)</span>
                            @endif
                        </p>
                        <div class="flex justify-between items-center">
                            <p class="text-xs" style="color: var(--text-secondary);">
                                {{ $stat->total ?? 0 }} properties
                            </p>
                            <p class="text-xs font-semibold" style="color: var(--primary);">
                                {{ $stat->percentage ?? 0 }}%
                            </p>
                        </div>
                        <div class="w-full rounded-full h-1.5 mt-1" style="background-color: var(--bg-secondary);">
                            <div class="rounded-full h-1.5 transition-all duration-500" 
                                 style="width: {{ $stat->percentage ?? 0 }}%; background-color: var(--primary);"></div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- ============================================ -->
    <!-- MONTHLY PERFORMANCE CHART                    -->
    <!-- ============================================ -->
    <div class="monthly-performance mb-8">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-calendar-alt mr-2" style="color: var(--primary);"></i>Monthly Performance
            </h2>
            @if($isFiltered)
                <span class="text-xs px-3 py-1 rounded-full" style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-filter mr-1"></i> Filtered by plan
                </span>
            @endif
        </div>
        <div class="rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            @if($monthlyData->count() > 0)
                <div class="relative" style="height: 300px;">
                    <canvas id="monthlyPerformanceChart"></canvas>
                </div>
            @else
                <div class="text-center py-8">
                    <i class="fas fa-chart-bar text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                    <p class="text-sm" style="color: var(--text-secondary);">No monthly data available yet.</p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Start registering properties to see your performance.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- ============================================ -->
    <!-- RECENT ACTIVITY                              -->
    <!-- ============================================ -->
    <div class="recent-activity mb-8">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-clock mr-2" style="color: var(--primary);"></i>Recent Activity
            </h2>
            @if($isFiltered)
                <span class="text-xs px-3 py-1 rounded-full" style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-filter mr-1"></i> Filtered by plan
                </span>
            @endif
        </div>
        <div class="rounded-xl" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            @if($recentProperties->count() > 0)
                <div class="divide-y" style="border-color: var(--border-color);">
                    @foreach($recentProperties as $property)
                    <div class="p-4 hover:bg-opacity-50 transition-all" style="background-color: var(--card-bg);">
                        <div class="flex flex-wrap justify-between items-center gap-2">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0" 
                                     style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                                    <i class="fas fa-building text-white text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium" style="color: var(--text-primary);">
                                        {{ $property->property_name }}
                                        @if(isset($property->registrationPlan))
                                            <span class="text-xs font-normal ml-1" style="color: var(--text-secondary);">
                                                ({{ $property->registrationPlan->name ?? $property->registrationPlan->zone ?? '' }})
                                            </span>
                                        @endif
                                        @if(isset($property->propertyType))
                                            <span class="text-xs font-normal ml-1" style="color: var(--text-secondary);">
                                                • {{ $property->propertyType->name ?? $property->custom_property_type ?? '' }}
                                            </span>
                                        @endif
                                    </p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                        {{ $property->street_name ?? '' }}
                                        @if(isset($property->zone))
                                            , {{ $property->zone }}
                                        @endif
                                        @if(isset($property->landlord))
                                            <span class="ml-2">• Landlord: {{ $property->landlord->name }}</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-medium" style="color: var(--text-secondary);">
                                    {{ isset($property->created_at) ? $property->created_at->diffForHumans() : 'N/A' }}
                                </p>
                                <span class="inline-block px-2 py-0.5 text-xs rounded-full" 
                                      style="background-color: {{ ($property->verification_status ?? '') === 'verified' ? 'rgba(16, 185, 129, 0.2)' : (($property->verification_status ?? '') === 'rejected' ? 'rgba(239, 68, 68, 0.2)' : 'rgba(245, 158, 11, 0.2)') }}; 
                                             color: {{ ($property->verification_status ?? '') === 'verified' ? '#10b981' : (($property->verification_status ?? '') === 'rejected' ? '#ef4444' : '#f59e0b') }};">
                                    {{ ucfirst($property->verification_status ?? 'pending') }}
                                </span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="p-4 text-center" style="border-top: 1px solid var(--border-color);">
                    <a href="{{ route('field-agent.properties.index') }}" 
                       class="text-sm transition-all hover:underline" style="color: var(--primary);">
                        View All Properties <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            @else
                <div class="text-center py-8">
                    <i class="fas fa-clock text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                    <p class="text-sm" style="color: var(--text-secondary);">No recent activity.</p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Start registering properties to see your activity here.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- ============================================ -->
    <!-- ACHIEVEMENTS / MILESTONES                    -->
    <!-- ============================================ -->
    <div class="achievements-section">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-trophy mr-2" style="color: #f59e0b;"></i>Achievements
                <span class="text-sm font-normal ml-2" style="color: var(--text-secondary);">
                    ({{ $achievements->count() }} earned)
                </span>
            </h2>
            @if($isFiltered)
                <span class="text-xs px-3 py-1 rounded-full" style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-filter mr-1"></i> Filtered by plan
                </span>
            @endif
        </div>

        @if($achievements->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
                @foreach($achievements as $achievement)
                <div class="rounded-xl p-6 text-center transition-all hover:shadow-lg hover:scale-105" 
                     style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
                    <div class="w-16 h-16 rounded-full mx-auto mb-3 flex items-center justify-center" 
                         style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                        <i class="fas {{ $achievement->icon ?? 'fa-star' }} text-white text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        {{ $achievement->title }}
                    </h3>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        {{ $achievement->description }}
                    </p>
                    <p class="text-xs mt-2" style="color: var(--text-secondary);">
                        <i class="far fa-calendar-alt mr-1"></i>
                        {{ isset($achievement->earned_at) ? $achievement->earned_at->diffForHumans() : 'Recently' }}
                    </p>
                </div>
                @endforeach
            </div>
        @else
            <div class="rounded-xl p-8 text-center" 
                 style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-style: dashed;">
                <i class="fas fa-medal text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.3;"></i>
                <p class="text-sm" style="color: var(--text-secondary);">No achievements yet.</p>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    Keep registering properties to unlock achievements!
                    @if($isFiltered)
                        <br><span class="text-xs">(Progress tracked for selected plan)</span>
                    @endif
                </p>
            </div>
        @endif
    </div>

    <!-- ============================================ -->
    <!-- PLAN PERFORMANCE TABLE (Optional)            -->
    <!-- ============================================ -->
    @if($planPerformance->count() > 0)
    <div class="plan-performance mt-8">
        <h2 class="text-xl font-semibold mb-4" style="color: var(--text-primary);">
            <i class="fas fa-tasks mr-2" style="color: var(--primary);"></i>Plan Performance
            <span class="text-sm font-normal ml-2" style="color: var(--text-secondary);">
                ({{ $planPerformance->count() }} active plans)
            </span>
        </h2>
        <div class="rounded-xl overflow-hidden" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead style="background-color: var(--bg-secondary);">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium" style="color: var(--text-secondary);">Plan</th>
                            <th class="px-4 py-3 text-center font-medium" style="color: var(--text-secondary);">Registered</th>
                            <th class="px-4 py-3 text-center font-medium" style="color: var(--text-secondary);">Total</th>
                            <th class="px-4 py-3 text-center font-medium" style="color: var(--text-secondary);">Progress</th>
                            <th class="px-4 py-3 text-center font-medium" style="color: var(--text-secondary);">Days Left</th>
                            <th class="px-4 py-3 text-center font-medium" style="color: var(--text-secondary);">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" style="border-color: var(--border-color);">
                        @foreach($planPerformance as $plan)
                        <tr class="hover:bg-opacity-50 transition-all" style="background-color: var(--card-bg);">
                            <td class="px-4 py-3 font-medium" style="color: var(--text-primary);">
                                {{ $plan->plan_name ?? 'Unknown Plan' }}
                                @if(isset($plan->status))
                                    <span class="text-xs font-normal ml-1" style="color: var(--text-secondary);">
                                        ({{ $plan->status }})
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center" style="color: var(--text-primary);">
                                {{ $plan->registered_count ?? 0 }}
                            </td>
                            <td class="px-4 py-3 text-center" style="color: var(--text-primary);">
                                {{ $plan->total_properties ?? 0 }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center space-x-2">
                                    <div class="w-20 rounded-full h-1.5" style="background-color: var(--bg-secondary);">
                                        <div class="rounded-full h-1.5 transition-all duration-500" 
                                             style="width: {{ ($plan->total_properties > 0) ? round(($plan->registered_count / $plan->total_properties) * 100) : 0 }}%; 
                                                    background: linear-gradient(90deg, var(--primary) 0%, #8b5cf6 100%);"></div>
                                    </div>
                                    <span class="text-xs font-medium" style="color: var(--text-secondary);">
                                        {{ ($plan->total_properties > 0) ? round(($plan->registered_count / $plan->total_properties) * 100) : 0 }}%
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center" style="color: var(--text-primary);">
                                @if(isset($plan->days_left) && $plan->days_left !== null)
                                    @if($plan->days_left < 0)
                                        <span style="color: #ef4444;">Expired</span>
                                    @elseif($plan->days_left < 7)
                                        <span style="color: #f59e0b;">{{ $plan->days_left }} days</span>
                                    @else
                                        <span style="color: #10b981;">{{ $plan->days_left }} days</span>
                                    @endif
                                @else
                                    <span style="color: var(--text-secondary);">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-block px-2 py-0.5 text-xs rounded-full" 
                                      style="background-color: {{ ($plan->registered_count ?? 0) >= ($plan->total_properties ?? 0) ? 'rgba(16, 185, 129, 0.2)' : 'rgba(59, 130, 246, 0.2)' }}; 
                                             color: {{ ($plan->registered_count ?? 0) >= ($plan->total_properties ?? 0) ? '#10b981' : 'var(--primary)' }};">
                                    {{ ($plan->registered_count ?? 0) >= ($plan->total_properties ?? 0) ? 'Completed' : 'In Progress' }}
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

    <!-- ============================================ -->
    <!-- FOOTER / SUMMARY                            -->
    <!-- ============================================ -->
    <div class="performance-footer mt-8 text-center">
        <p class="text-xs" style="color: var(--text-secondary);">
            <i class="fas fa-info-circle mr-1"></i>
            Performance metrics are updated in real-time.
            @if($isFiltered)
                <span class="ml-1">Showing data for selected plan only.</span>
            @else
                <span class="ml-1">Showing data across all your assigned plans.</span>
            @endif
            <span class="ml-1">Last updated: {{ now()->format('M d, Y H:i:s') }}</span>
        </p>
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

    .performance-container {
        max-width: 1600px;
        margin: 0 auto;
        padding: 0 1rem;
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

    /* Select dropdown arrow */
    select {
        appearance: auto;
        -webkit-appearance: auto;
        -moz-appearance: auto;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .performance-container {
            padding: 0 0.5rem;
        }
        .stats-grid {
            gap: 1rem;
        }
        .stat-card {
            padding: 1rem;
        }
        .plan-filter-section form {
            flex-direction: column;
            align-items: stretch;
        }
        .plan-filter-section form .flex-1 {
            min-width: 100%;
        }
        .selected-plan-info .flex-wrap {
            flex-direction: column;
            align-items: flex-start;
        }
        .selected-plan-info .flex-wrap .flex-wrap {
            width: 100%;
            justify-content: space-around;
        }
        .grid-cols-1.md\:grid-cols-4 {
            grid-template-columns: repeat(2, 1fr);
        }
        .grid-cols-1.md\:grid-cols-3 {
            grid-template-columns: 1fr;
        }
        .grid-cols-1.md\:grid-cols-3.lg\:grid-cols-4 {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 480px) {
        .grid-cols-1.md\:grid-cols-4 {
            grid-template-columns: 1fr;
        }
        .grid-cols-1.md\:grid-cols-3.lg\:grid-cols-4 {
            grid-template-columns: 1fr;
        }
        .verification-stats .grid-cols-3 {
            grid-template-columns: 1fr 1fr 1fr;
        }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Monthly Performance Chart
        @if($monthlyData->count() > 0)
        const ctx = document.getElementById('monthlyPerformanceChart');
        if (ctx) {
            const monthlyData = @json($monthlyData);
            
            // Get text colors based on theme
            const textColor = getComputedStyle(document.documentElement)
                .getPropertyValue('--text-primary').trim() || '#1f2937';
            const textSecondary = getComputedStyle(document.documentElement)
                .getPropertyValue('--text-secondary').trim() || '#6b7280';
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: monthlyData.map(item => item.month),
                    datasets: [{
                        label: 'Properties Registered',
                        data: monthlyData.map(item => item.count),
                        backgroundColor: 'rgba(59, 130, 246, 0.6)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 2,
                        borderRadius: 4,
                        barPercentage: 0.6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                color: textColor,
                                font: {
                                    size: 12,
                                    weight: '500'
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            cornerRadius: 8,
                            padding: 12,
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + ' properties';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                color: textSecondary,
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                color: 'rgba(107, 114, 128, 0.1)',
                                drawBorder: false
                            }
                        },
                        x: {
                            ticks: {
                                color: textSecondary,
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                display: false
                            }
                        }
                    },
                    animation: {
                        duration: 800,
                        easing: 'easeInOutQuart'
                    }
                }
            });
        }
        @endif

        // Auto-submit form on select change (already handled by onchange)
        // Additional: highlight the selected plan in the dropdown
        const select = document.getElementById('registration_plan_id');
        if (select) {
            // If there's a selected value, add a visual indicator
            @if($selectedPlanId)
                const selectedOption = select.querySelector('option[value="{{ $selectedPlanId }}"]');
                if (selectedOption) {
                    selectedOption.style.fontWeight = 'bold';
                    selectedOption.style.color = 'var(--primary)';
                }
            @endif
            
            // Add counter badge to the select
            const totalPlans = {{ $totalPlansAssigned }};
            if (totalPlans > 0) {
                // The "All Plans" option already shows the count
            }
        }
    });
</script>
@endpush

@endsection