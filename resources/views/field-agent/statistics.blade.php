@extends('layouts.field')

@section('title', 'Statistics - Field Agent')

@section('content')
<div class="statistics-container">
    <!-- Page Header -->
    <div class="statistics-header mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-bar mr-2" style="color: var(--primary);"></i>Statistics
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Comprehensive overview of your registration performance
                </p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('field-agent.dashboard') }}" 
                   class="px-4 py-2 rounded-lg transition-all hover:shadow-md inline-flex items-center"
                   style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
                <button onclick="window.print()" 
                        class="px-4 py-2 rounded-lg transition-all hover:shadow-md inline-flex items-center"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    <i class="fas fa-print mr-2"></i> Print Report
                </button>
            </div>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="filter-section mb-6">
        <div class="rounded-xl p-4" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <form method="GET" action="{{ route('field-agent.statistics') }}" class="flex flex-wrap items-center gap-4">
                <div class="flex items-center space-x-2">
                    <label class="text-sm font-medium" style="color: var(--text-secondary);">From:</label>
                    <input type="date" name="from_date" value="{{ request('from_date') }}"
                           class="px-3 py-1.5 rounded-lg text-sm"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                </div>
                <div class="flex items-center space-x-2">
                    <label class="text-sm font-medium" style="color: var(--text-secondary);">To:</label>
                    <input type="date" name="to_date" value="{{ request('to_date') }}"
                           class="px-3 py-1.5 rounded-lg text-sm"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                </div>
                <button type="submit" 
                        class="px-4 py-1.5 rounded-lg transition-all text-sm"
                        style="background-color: var(--primary); color: white;">
                    <i class="fas fa-filter mr-1"></i> Apply Filter
                </button>
                @if(request()->has('from_date') || request()->has('to_date'))
                    <a href="{{ route('field-agent.statistics') }}" 
                       class="text-sm transition-all hover:underline" style="color: var(--primary);">
                        <i class="fas fa-times mr-1"></i> Clear
                    </a>
                @endif
            </form>
        </div>
    </div>

    <!-- Key Statistics Cards -->
    <div class="stats-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Properties</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $totalProperties ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                    <i class="fas fa-building text-white text-xl"></i>
                </div>
            </div>
            <div class="mt-2 flex items-center">
                <span class="text-xs" style="color: var(--text-secondary);">Registered properties</span>
            </div>
        </div>

        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">This Month</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $thisMonthCount ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <i class="fas fa-calendar-check text-white text-xl"></i>
                </div>
            </div>
            <div class="mt-2">
                <p class="text-xs" style="color: var(--text-secondary);">
                    <i class="fas fa-arrow-up mr-1" style="color: #10b981;"></i>
                    {{ $thisMonthCount ?? 0 }} properties this month
                </p>
            </div>
        </div>

        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Verification Status</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ $verifiedCount ?? 0 }}/{{ $totalProperties ?? 0 }}
                    </p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                    <i class="fas fa-check-double text-white text-xl"></i>
                </div>
            </div>
            <div class="mt-2">
                <div class="w-full rounded-full h-1.5" style="background-color: var(--bg-secondary);">
                    <div class="rounded-full h-1.5 transition-all duration-500" 
                         style="width: {{ isset($totalProperties) && $totalProperties > 0 ? round(($verifiedCount / $totalProperties) * 100, 1) : 0 }}%; background-color: #8b5cf6;"></div>
                </div>
            </div>
        </div>

        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg" 
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Completion Rate</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $completionRate ?? 0 }}%</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <i class="fas fa-percentage text-white text-xl"></i>
                </div>
            </div>
            <div class="mt-2">
                <p class="text-xs" style="color: var(--text-secondary);">
                    Overall plan completion rate
                </p>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="charts-grid grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Monthly Trend Chart -->
        <div class="chart-card rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>Monthly Trend
            </h3>
            <div class="relative" style="height: 280px;">
                <canvas id="monthlyTrendChart"></canvas>
            </div>
        </div>

        <!-- Property Type Distribution -->
        <div class="chart-card rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-chart-pie mr-2" style="color: var(--primary);"></i>Property Type Distribution
            </h3>
            <div class="relative" style="height: 280px;">
                <canvas id="propertyTypeChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Detailed Statistics -->
    <div class="detailed-stats grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Plan Performance -->
        <div class="rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-clipboard-list mr-2" style="color: var(--primary);"></i>Plan Performance
            </h3>
            @if(isset($planPerformance) && $planPerformance->count() > 0)
                <div class="space-y-3">
                    @foreach($planPerformance as $plan)
                        @php
                            $percentage = $plan->total_properties > 0 
                                ? round(($plan->registered_count / $plan->total_properties) * 100, 1) 
                                : 0;
                        @endphp
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span style="color: var(--text-secondary);">{{ $plan->plan_name }}</span>
                                <span style="color: var(--text-primary);">{{ $plan->registered_count }}/{{ $plan->total_properties }}</span>
                            </div>
                            <div class="w-full rounded-full h-2" style="background-color: var(--bg-secondary);">
                                <div class="rounded-full h-2 transition-all duration-500" 
                                     style="width: {{ $percentage }}%; background-color: {{ $percentage >= 80 ? '#10b981' : ($percentage >= 50 ? '#f59e0b' : '#ef4444') }};"></div>
                            </div>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                {{ $percentage }}% complete
                                @if(isset($plan->days_left) && $plan->days_left !== null && $plan->days_left > 0)
                                    · {{ $plan->days_left }} days left
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <i class="fas fa-clipboard-list text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                    <p class="text-sm" style="color: var(--text-secondary);">No plan data available.</p>
                </div>
            @endif
        </div>

        <!-- Status Distribution -->
        <div class="rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-tasks mr-2" style="color: var(--primary);"></i>Property Status Distribution
            </h3>
            @if(isset($statusDistribution) && $statusDistribution->count() > 0)
                <div class="space-y-3">
                    @foreach($statusDistribution as $status)
                        @php
                            $colors = [
                                'pending' => '#f59e0b',
                                'verified' => '#10b981',
                                'rejected' => '#ef4444',
                                'active' => '#3b82f6',
                                'inactive' => '#6b7280',
                                'under_maintenance' => '#8b5cf6',
                                'vacant' => '#ec4899'
                            ];
                            $color = $colors[strtolower($status->status)] ?? '#6b7280';
                            $percentage = isset($totalProperties) && $totalProperties > 0 
                                ? round(($status->count / $totalProperties) * 100, 1) 
                                : 0;
                        @endphp
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span style="color: var(--text-secondary);">
                                    <span class="inline-block w-3 h-3 rounded-full mr-2" 
                                          style="background-color: {{ $color }};"></span>
                                    {{ ucfirst(str_replace('_', ' ', $status->status)) }}
                                </span>
                                <span style="color: var(--text-primary);">{{ $status->count }}</span>
                            </div>
                            <div class="w-full rounded-full h-2" style="background-color: var(--bg-secondary);">
                                <div class="rounded-full h-2 transition-all duration-500" 
                                     style="width: {{ $percentage }}%; background-color: {{ $color }};"></div>
                            </div>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                {{ $percentage }}% of total properties
                            </p>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <i class="fas fa-tasks text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                    <p class="text-sm" style="color: var(--text-secondary);">No status data available.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Top Performers & Recent Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Most Active Landlords -->
        <div class="rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-users mr-2" style="color: var(--primary);"></i>Top Landlords
            </h3>
            @if(isset($topLandlords) && $topLandlords->count() > 0)
                <div class="space-y-3">
                    @foreach($topLandlords as $index => $landlord)
                        <div class="flex items-center justify-between p-2 rounded-lg" 
                             style="background-color: var(--bg-secondary);">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold text-white"
                                     style="background: linear-gradient(135deg, var(--primary) 0%, #2563eb 100%);">
                                    {{ $index + 1 }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium" style="color: var(--text-primary);">
                                        {{ $landlord->name }}
                                    </p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        {{ $landlord->property_count }} properties
                                    </p>
                                </div>
                            </div>
                            <span class="text-xs px-2 py-1 rounded-full" 
                                  style="background-color: rgba(59, 130, 246, 0.2); color: #3b82f6;">
                                {{ $landlord->property_count }} props
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <i class="fas fa-users text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                    <p class="text-sm" style="color: var(--text-secondary);">No landlord data available.</p>
                </div>
            @endif
        </div>

        <!-- Recent Activity Timeline -->
        <div class="rounded-xl p-6" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-clock mr-2" style="color: var(--primary);"></i>Recent Activity
            </h3>
            @if(isset($recentActivity) && $recentActivity->count() > 0)
                <div class="space-y-4 max-h-80 overflow-y-auto custom-scrollbar">
                    @foreach($recentActivity as $activity)
                        <div class="flex items-start space-x-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                                 style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                                <i class="fas fa-building text-white text-xs"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm" style="color: var(--text-primary);">
                                    <span class="font-medium">{{ $activity->property_name }}</span>
                                    <span class="text-xs" style="color: var(--text-secondary);">
                                        - {{ ucfirst($activity->verification_status ?? 'pending') }}
                                    </span>
                                </p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    <i class="far fa-calendar-alt mr-1"></i>
                                    {{ $activity->created_at->format('M j, Y g:i A') }}
                                    <span class="mx-1">•</span>
                                    {{ $activity->created_at->diffForHumans() }}
                                </p>
                            </div>
                            @if($activity->verification_status === 'verified')
                                <span class="text-xs px-2 py-0.5 rounded-full" 
                                      style="background-color: rgba(16, 185, 129, 0.2); color: #10b981;">
                                    Verified
                                </span>
                            @else
                                <span class="text-xs px-2 py-0.5 rounded-full" 
                                      style="background-color: rgba(245, 158, 11, 0.2); color: #f59e0b;">
                                    Pending
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <i class="fas fa-clock text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                    <p class="text-sm" style="color: var(--text-secondary);">No recent activity.</p>
                </div>
            @endif
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

    [data-theme="dark"], .dark-theme {
        --text-primary: #f9fafb;
        --text-secondary: #9ca3af;
        --card-bg: #1f2937;
        --bg-secondary: #374151;
        --border-color: #374151;
    }

    .statistics-container {
        max-width: 1600px;
        margin: 0 auto;
        padding: 0 1rem;
    }

    .stat-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .rounded-full {
        transition: width 0.5s ease-in-out;
    }

    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: var(--bg-secondary);
        border-radius: 10px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: var(--border-color);
        border-radius: 10px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: var(--text-secondary);
    }

    @media (max-width: 768px) {
        .statistics-container {
            padding: 0 0.5rem;
        }
        .stats-grid {
            gap: 1rem;
        }
        .stat-card {
            padding: 1rem;
        }
        .charts-grid {
            grid-template-columns: 1fr;
        }
        .detailed-stats {
            grid-template-columns: 1fr;
        }
    }

    @media print {
        .statistics-header .flex {
            flex-direction: column;
            align-items: flex-start !important;
        }
        .statistics-header .flex .space-x-3 {
            margin-top: 1rem;
        }
        .filter-section {
            display: none !important;
        }
        .stat-card {
            break-inside: avoid;
        }
        .chart-card {
            break-inside: avoid;
        }
        .rounded-xl {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
        }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get text color for charts
        const textColor = getComputedStyle(document.documentElement)
            .getPropertyValue('--text-secondary').trim() || '#6b7280';
        const cardBg = getComputedStyle(document.documentElement)
            .getPropertyValue('--card-bg').trim() || '#ffffff';
        
        // ========== MONTHLY TREND CHART ==========
        @if(isset($monthlyTrend) && $monthlyTrend->count() > 0)
            (function() {
                const trendCtx = document.getElementById('monthlyTrendChart');
                if (!trendCtx) {
                    console.warn('Monthly trend chart canvas not found');
                    return;
                }
                
                // Convert collection to array for JavaScript
                const monthlyData = @json($monthlyTrend);
                console.log('Monthly Trend Data:', monthlyData);
                
                if (!monthlyData || monthlyData.length === 0) {
                    console.warn('No monthly trend data available');
                    return;
                }
                
                new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: monthlyData.map(item => item.month),
                        datasets: [{
                            label: 'Properties Registered',
                            data: monthlyData.map(item => item.count),
                            borderColor: 'rgba(59, 130, 246, 1)',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                labels: {
                                    color: textColor
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1,
                                    color: textColor
                                },
                                grid: {
                                    color: 'rgba(107, 114, 128, 0.1)'
                                }
                            },
                            x: {
                                ticks: {
                                    color: textColor,
                                    maxRotation: 45
                                },
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            })();
        @else
            console.warn('Monthly trend data not available or empty');
        @endif

        // ========== PROPERTY TYPE CHART ==========
        @if(isset($propertyTypeStats) && $propertyTypeStats->count() > 0)
            (function() {
                const typeCtx = document.getElementById('propertyTypeChart');
                if (!typeCtx) {
                    console.warn('Property type chart canvas not found');
                    return;
                }
                
                const typeStats = @json($propertyTypeStats);
                console.log('Property Type Stats:', typeStats);
                
                if (!typeStats || typeStats.length === 0) {
                    console.warn('No property type stats available');
                    return;
                }
                
                const colors = [
                    '#3b82f6', '#10b981', '#f59e0b', '#ef4444', 
                    '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'
                ];
                
                new Chart(typeCtx, {
                    type: 'doughnut',
                    data: {
                        labels: typeStats.map(item => item.type_name),
                        datasets: [{
                            data: typeStats.map(item => item.count),
                            backgroundColor: colors.slice(0, typeStats.length),
                            borderWidth: 2,
                            borderColor: cardBg
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: textColor,
                                    padding: 20,
                                    usePointStyle: true,
                                    pointStyle: 'circle'
                                }
                            }
                        },
                        cutout: '60%'
                    }
                });
            })();
        @else
            console.warn('Property type stats not available or empty');
        @endif
        
        // ========== DEBUGGING: Log all available data ==========
        console.log('📊 Statistics Page Data:', {
            totalProperties: {{ $totalProperties ?? 0 }},
            thisMonthCount: {{ $thisMonthCount ?? 0 }},
            verifiedCount: {{ $verifiedCount ?? 0 }},
            completionRate: {{ $completionRate ?? 0 }},
            monthlyTrendCount: {{ isset($monthlyTrend) ? $monthlyTrend->count() : 0 }},
            propertyTypeStatsCount: {{ isset($propertyTypeStats) ? $propertyTypeStats->count() : 0 }},
            planPerformanceCount: {{ isset($planPerformance) ? $planPerformance->count() : 0 }},
            statusDistributionCount: {{ isset($statusDistribution) ? $statusDistribution->count() : 0 }},
            topLandlordsCount: {{ isset($topLandlords) ? $topLandlords->count() : 0 }},
            recentActivityCount: {{ isset($recentActivity) ? $recentActivity->count() : 0 }}
        });
    });
</script>
@endpush

@endsection