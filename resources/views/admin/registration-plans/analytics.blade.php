@extends('layouts.app')

@section('title', 'Registration Plans Analytics')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Registration Plans Analytics</h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Comprehensive insights and performance metrics for registration plans
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <div class="flex items-center px-3 py-1 rounded-full text-sm font-medium" 
                     style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                    <i class="fas fa-chart-bar mr-2"></i>
                    Analytics Dashboard
                </div>
                <a href="{{ route('registration-plans.index') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Plans
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Plans -->
        <div class="card p-6">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Total Plans</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);">
                        {{ $analytics['plans_by_status']->sum() }}
                    </h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-clipboard-list text-xl" style="color: var(--primary);"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex justify-between text-sm mb-1">
                    <span style="color: var(--text-secondary);">Active</span>
                    <span style="color: var(--text-primary); font-weight: 600;">
                        {{ ($analytics['plans_by_status']['assigned'] ?? 0) + ($analytics['plans_by_status']['in_progress'] ?? 0) }}
                    </span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    @php
                        $totalPlans = $analytics['plans_by_status']->sum();
                        $activePlans = ($analytics['plans_by_status']['assigned'] ?? 0) + ($analytics['plans_by_status']['in_progress'] ?? 0);
                        $activePercentage = $totalPlans > 0 ? ($activePlans / $totalPlans) * 100 : 0;
                    @endphp
                    <div class="bg-green-500 h-2 rounded-full" style="width: {{ $activePercentage }}%"></div>
                </div>
            </div>
        </div>

        <!-- Completed Plans -->
        <div class="card p-6">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Completed Plans</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);">
                        {{ $analytics['plans_by_status']['completed'] ?? 0 }}
                    </h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-check-circle text-xl" style="color: var(--success);"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex justify-between text-sm mb-1">
                    <span style="color: var(--text-secondary);">Success Rate</span>
                    <span style="color: var(--text-primary); font-weight: 600;">
                        @php
                            $completed = $analytics['plans_by_status']['completed'] ?? 0;
                            $total = $analytics['plans_by_status']->sum();
                            $successRate = $total > 0 ? round(($completed / $total) * 100) : 0;
                        @endphp
                        {{ $successRate }}%
                    </span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-500 h-2 rounded-full" style="width: {{ $successRate }}%"></div>
                </div>
            </div>
        </div>

        <!-- Active Assignments -->
        <div class="card p-6">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Active Assignments</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);">
                        {{ $analytics['assignment_performance']->count() }}
                    </h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-users text-xl" style="color: var(--warning);"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex justify-between text-sm">
                    <span style="color: var(--text-secondary);">Properties Registered</span>
                    <span style="color: var(--text-primary); font-weight: 600;">
                        {{ $analytics['assignment_performance']->sum('total_properties') }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Top Zone -->
        <div class="card p-6">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Most Active Zone</p>
                    <h3 class="text-lg font-bold mt-1" style="color: var(--text-primary);">
                        {{ $analytics['plans_by_zone']->first()->zone ?? 'N/A' }}
                    </h3>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        {{ $analytics['plans_by_zone']->first()->count ?? 0 }} plans
                    </p>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-map-marker-alt text-xl" style="color: var(--info);"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Analytics Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Plans by Status Chart -->
        <div class="card p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Plans by Status</h3>
                <div class="flex items-center space-x-2">
                    <i class="fas fa-chart-pie text-xl opacity-70" style="color: var(--primary);"></i>
                </div>
            </div>
            <div class="space-y-4">
                @php
                    $statusColors = [
                        'draft' => 'bg-gray-500',
                        'assigned' => 'bg-blue-500',
                        'in_progress' => 'bg-yellow-500',
                        'completed' => 'bg-green-500',
                        'cancelled' => 'bg-red-500'
                    ];
                    
                    $statusLabels = [
                        'draft' => 'Draft',
                        'assigned' => 'Assigned',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled'
                    ];
                @endphp
                
                @foreach($analytics['plans_by_status'] as $status => $count)
                    @if($count > 0)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full mr-3 {{ $statusColors[$status] ?? 'bg-gray-400' }}"></div>
                            <span style="color: var(--text-primary);">{{ $statusLabels[$status] ?? ucfirst($status) }}</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <span style="color: var(--text-primary); font-weight: 600;">{{ $count }}</span>
                            <span class="text-sm" style="color: var(--text-secondary);">
                                @php
                                    $percentage = $analytics['plans_by_status']->sum() > 0 ? 
                                        round(($count / $analytics['plans_by_status']->sum()) * 100) : 0;
                                @endphp
                                {{ $percentage }}%
                            </span>
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="h-2 rounded-full {{ $statusColors[$status] ?? 'bg-gray-400' }}" 
                             style="width: {{ $percentage }}%"></div>
                    </div>
                    @endif
                @endforeach
            </div>
        </div>

        <!-- Top Zones -->
        <div class="card p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Plans by Zone</h3>
                <div class="flex items-center space-x-2">
                    <i class="fas fa-map text-xl opacity-70" style="color: var(--info);"></i>
                </div>
            </div>
            <div class="space-y-4">
                @foreach($analytics['plans_by_zone']->take(8) as $zone)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-2 h-2 bg-blue-500 rounded-full mr-3"></div>
                            <span style="color: var(--text-primary);" class="truncate">{{ $zone->zone ?: 'Unspecified' }}</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <span style="color: var(--text-primary); font-weight: 600;">{{ $zone->count }}</span>
                            <span class="text-sm px-2 py-1 rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                @php
                                    $percentage = $analytics['plans_by_zone']->sum('count') > 0 ? 
                                        round(($zone->count / $analytics['plans_by_zone']->sum('count')) * 100) : 0;
                                @endphp
                                {{ $percentage }}%
                            </span>
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-500 h-2 rounded-full" style="width: {{ $percentage }}%"></div>
                    </div>
                @endforeach
                
                @if($analytics['plans_by_zone']->count() > 8)
                    <div class="text-center pt-2">
                        <span class="text-sm" style="color: var(--text-secondary);">
                            +{{ $analytics['plans_by_zone']->count() - 8 }} more zones
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Agent Performance Section -->
    <div class="card p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Agent Performance</h3>
            <div class="flex items-center space-x-2">
                <i class="fas fa-trophy text-xl opacity-70" style="color: var(--warning);"></i>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Top Performers -->
            <div>
                <h4 class="font-semibold mb-4" style="color: var(--text-primary);">Top Performing Agents</h4>
                <div class="space-y-4">
                    @forelse($analytics['assignment_performance']->take(5) as $assignment)
                        <div class="flex items-center justify-between p-3 rounded-lg border" style="border-color: var(--border-color);">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3" 
                                     style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div>
                                    <div style="color: var(--text-primary); font-weight: 600;">
                                        {{ $assignment->agent->name ?? 'Unknown Agent' }}
                                    </div>
                                    <div class="text-sm" style="color: var(--text-secondary);">
                                        {{ $assignment->total_properties }} properties registered
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-medium" style="color: var(--success);">
                                    #{{ $loop->iteration }}
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    Top Performer
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8" style="color: var(--text-secondary);">
                            <i class="fas fa-users text-3xl mb-3 opacity-50"></i>
                            <p>No agent performance data available</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Assignment Types -->
            <div>
                <h4 class="font-semibold mb-4" style="color: var(--text-primary);">Assignment Types Distribution</h4>
                <div class="space-y-4">
                    @foreach($analytics['assignment_types'] as $type)
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full mr-3 
                                    {{ $type->agent_assignment_type === 'single' ? 'bg-blue-500' : 'bg-green-500' }}"></div>
                                <span style="color: var(--text-primary);">
                                    {{ $type->agent_assignment_type === 'single' ? 'Single Agent' : 'Multiple Agents' }}
                                </span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <span style="color: var(--text-primary); font-weight: 600;">{{ $type->count }}</span>
                                <span class="text-sm px-2 py-1 rounded-full" 
                                      style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                    @php
                                        $totalTypes = $analytics['assignment_types']->sum('count');
                                        $percentage = $totalTypes > 0 ? round(($type->count / $totalTypes) * 100) : 0;
                                    @endphp
                                    {{ $percentage }}%
                                </span>
                            </div>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="h-2 rounded-full {{ $type->agent_assignment_type === 'single' ? 'bg-blue-500' : 'bg-green-500' }}" 
                                 style="width: {{ $percentage }}%"></div>
                        </div>
                    @endforeach
                </div>

                <!-- Pattern Usage -->
                <h4 class="font-semibold mt-6 mb-4" style="color: var(--text-primary);">Popular Naming Patterns</h4>
                <div class="space-y-3">
                    @foreach($analytics['pattern_usage']->take(5) as $pattern)
                        <div class="flex justify-between items-center p-2 rounded" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                            <code class="text-sm font-mono" style="color: var(--text-primary);">
                                {{ $pattern->naming_pattern }}
                            </code>
                            <span class="text-sm px-2 py-1 rounded-full" 
                                  style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                {{ $pattern->count }} uses
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Registration Trends -->
    <div class="card p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Monthly Registration Trends</h3>
            <div class="flex items-center space-x-2">
                <i class="fas fa-chart-line text-xl opacity-70" style="color: var(--success);"></i>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr style="border-bottom-color: var(--border-color);">
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">Period</th>
                        <th class="text-right py-3 px-4 font-medium" style="color: var(--text-primary);">Plans Created</th>
                        <th class="text-right py-3 px-4 font-medium" style="color: var(--text-primary);">Target Houses</th>
                        <th class="text-right py-3 px-4 font-medium" style="color: var(--text-primary);">Avg. per Plan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($analytics['monthly_registration'] as $monthly)
                        <tr style="border-bottom-color: var(--border-color);">
                            <td class="py-3 px-4" style="color: var(--text-primary);">
                                {{ date('F Y', mktime(0, 0, 0, $monthly->month, 1, $monthly->year)) }}
                            </td>
                            <td class="text-right py-3 px-4" style="color: var(--text-primary);">
                                {{ $monthly->plans_count }}
                            </td>
                            <td class="text-right py-3 px-4" style="color: var(--text-primary);">
                                {{ number_format($monthly->target_houses) }}
                            </td>
                            <td class="text-right py-3 px-4" style="color: var(--text-primary);">
                                @php
                                    $avg = $monthly->plans_count > 0 ? round($monthly->target_houses / $monthly->plans_count) : 0;
                                @endphp
                                {{ $avg }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-8" style="color: var(--text-secondary);">
                                No monthly data available
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Zone Performance Details -->
    <div class="card p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Zone Performance Details</h3>
            <div class="flex items-center space-x-2">
                <i class="fas fa-chart-bar text-xl opacity-70" style="color: var(--info);"></i>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($analytics['active_assignments_by_zone'] as $zone => $data)
                <div class="border rounded-lg p-4" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-start mb-3">
                        <h4 class="font-semibold" style="color: var(--text-primary);">{{ $zone ?: 'Unspecified' }}</h4>
                        <span class="text-sm px-2 py-1 rounded-full" 
                              style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            {{ $data['count'] }} active
                        </span>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span style="color: var(--text-secondary);">Properties Registered</span>
                            <span style="color: var(--text-primary); font-weight: 600;">
                                {{ $data['total_properties'] }}
                            </span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span style="color: var(--text-secondary);">Avg. per Assignment</span>
                            <span style="color: var(--text-primary); font-weight: 600;">
                                @php
                                    $avg = $data['count'] > 0 ? round($data['total_properties'] / $data['count']) : 0;
                                @endphp
                                {{ $avg }}
                            </span>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t" style="border-color: var(--border-color);">
                        <div class="text-xs" style="color: var(--text-secondary);">
                            Performance: 
                            <span class="font-medium {{ $avg >= 10 ? 'text-green-600' : ($avg >= 5 ? 'text-yellow-600' : 'text-red-600') }}">
                                {{ $avg >= 10 ? 'High' : ($avg >= 5 ? 'Medium' : 'Low') }}
                            </span>
                        </div>
                    </div>
                </div>
            @endforeach
            
            @if($analytics['active_assignments_by_zone']->count() === 0)
                <div class="col-span-3 text-center py-8" style="color: var(--text-secondary);">
                    <i class="fas fa-map-marker-alt text-3xl mb-3 opacity-50"></i>
                    <p>No active assignments by zone data available</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Export and Actions -->
    <div class="card p-6">
        <div class="flex justify-between items-center">
            <div>
                <h3 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Analytics Export</h3>
                <p class="text-sm" style="color: var(--text-secondary);">
                    Download comprehensive analytics reports for further analysis
                </p>
            </div>
            <div class="flex space-x-3">
                <button class="btn-primary flex items-center" onclick="exportAnalytics('pdf')">
                    <i class="fas fa-file-pdf mr-2"></i> Export PDF
                </button>
                <button class="btn-secondary flex items-center" onclick="exportAnalytics('excel')">
                    <i class="fas fa-file-excel mr-2"></i> Export Excel
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function exportAnalytics(format) {
    // Show loading state
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Exporting...';
    button.disabled = true;

    // Simulate API call - replace with actual export endpoint
    setTimeout(() => {
        // Show success message
        showNotification('Analytics export started successfully!', 'success');
        
        // Reset button
        button.innerHTML = originalText;
        button.disabled = false;
        
        // In a real implementation, this would trigger a download
        // window.location.href = `/admin/registration-plans/analytics/export?format=${format}`;
    }, 1500);
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 transform transition-transform duration-300 translate-x-full`;
    
    const bgColor = type === 'success' ? 'bg-green-500' : 
                   type === 'error' ? 'bg-red-500' : 
                   type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500';
    
    notification.className += ` ${bgColor} text-white`;
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info'}-circle mr-2"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);
    
    // Remove after 5 seconds
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 5000);
}

// Initialize charts when needed
document.addEventListener('DOMContentLoaded', function() {
    // You can initialize actual charts here using Chart.js or other libraries
    console.log('Analytics dashboard loaded');
    
    // Example: Initialize a chart if you add charting functionality
    // initializePlansByStatusChart();
});

function initializePlansByStatusChart() {
    // This is a placeholder for actual chart implementation
    // You would use Chart.js, ApexCharts, or another library here
    const ctx = document.getElementById('plansByStatusChart');
    if (ctx) {
        // Chart initialization code would go here
    }
}
</script>

<style>
/* Custom styles for analytics dashboard */
.card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

/* Progress bar animations */
.w-full.bg-gray-200 .h-2 {
    transition: width 0.5s ease-in-out;
}

/* Status color coding */
.bg-gray-500 { background-color: #6B7280; }
.bg-blue-500 { background-color: #3B82F6; }
.bg-yellow-500 { background-color: #EAB308; }
.bg-green-500 { background-color: #10B981; }
.bg-red-500 { background-color: #EF4444; }

/* Responsive design improvements */
@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-2.lg\:grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .grid.grid-cols-1.lg\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .grid.grid-cols-1.md\:grid-cols-2.lg\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
}

/* Print styles */
@media print {
    .btn-primary, .btn-secondary {
        display: none;
    }
    
    .card {
        break-inside: avoid;
        box-shadow: none;
        border: 1px solid #ddd;
    }
}

/* Dark mode adjustments */
[data-theme="dark"] .bg-gray-200 {
    background-color: #374151;
}

[data-theme="dark"] .card {
    background-color: var(--card-bg);
    border-color: var(--border-color);
}

[data-theme="dark"] code {
    background-color: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
}

/* Animation for number counters */
@keyframes countUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.text-2xl.font-bold {
    animation: countUp 0.6s ease-out;
}

/* Hover effects for interactive elements */
.btn-primary:hover, .btn-secondary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Custom scrollbar for tables */
.overflow-x-auto::-webkit-scrollbar {
    height: 6px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: var(--bg-secondary);
    border-radius: 3px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 3px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background: var(--text-secondary);
}

/* Loading states */
.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

/* Focus states for accessibility */
.btn-primary:focus, .btn-secondary:focus {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

/* Status indicator animations */
.w-3.h-3.rounded-full {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

/* Performance rating colors */
.text-green-600 { color: #10B981; }
.text-yellow-600 { color: #EAB308; }
.text-red-600 { color: #EF4444; }

/* Zone performance card hover effects */
.border.rounded-lg.p-4 {
    transition: all 0.3s ease;
}

.border.rounded-lg.p-4:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border-color: var(--primary);
}

/* Pattern usage code styling */
code.font-mono {
    font-family: 'Courier New', monospace;
    background-color: rgba(var(--primary-rgb), 0.1);
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.875rem;
}

/* Monthly trends table styling */
table.w-full {
    border-collapse: collapse;
}

table.w-full th, table.w-full td {
    padding: 0.75rem 1rem;
    text-align: left;
}

table.w-full tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.05);
}

/* Responsive text sizing */
@media (max-width: 640px) {
    .text-2xl.font-bold {
        font-size: 1.5rem;
    }
    
    .text-lg.font-semibold {
        font-size: 1.125rem;
    }
}

/* Export buttons container */
.flex.space-x-3 {
    flex-wrap: wrap;
    gap: 0.5rem;
}

@media (max-width: 640px) {
    .flex.space-x-3 {
        justify-content: center;
        width: 100%;
    }
    
    .flex.space-x-3 button {
        flex: 1;
        min-width: 140px;
    }
}
</style>
@endsection