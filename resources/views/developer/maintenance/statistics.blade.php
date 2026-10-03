@extends('layouts.dev')

@section('title', 'Maintenance Statistics')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-bar mr-2"></i> Maintenance Statistics
                </h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Comprehensive analytics and insights for maintenance management
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('developer.maintenance.index') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to List
                </a>
            </div>
        </div>
    </div>

    <!-- Overview Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        <div class="card p-4" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Maintenance</div>
                    <div class="text-2xl font-semibold">{{ $statistics['total'] ?? 0 }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        All time records
                    </div>
                </div>
                <i class="fas fa-server text-2xl opacity-70"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Completed</div>
                    <div class="text-2xl font-semibold">{{ $statistics['completed'] ?? 0 }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        {{ $statistics['completion_rate'] ?? 0 }}% success rate
                    </div>
                </div>
                <i class="fas fa-check-circle text-2xl opacity-70"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Avg Duration</div>
                    <div class="text-2xl font-semibold">{{ $statistics['avg_duration'] ?? 0 }} min</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        {{ $statistics['avg_duration_variance'] ?? 0 }}% variance
                    </div>
                </div>
                <i class="fas fa-clock text-2xl opacity-70"></i>
            </div>
        </div>
    </div>

    <!-- Trends Section -->
    <div class="card mb-6">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-chart-line mr-2"></i> Trends & Patterns
            </h3>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Historical data and performance metrics
            </p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Monthly Trends -->
                <div>
                    <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                        <i class="fas fa-calendar-alt mr-2"></i> Monthly Trends
                    </h4>
                    <div class="space-y-3">
                        @if(isset($trends['monthly']) && count($trends['monthly']) > 0)
                            @foreach($trends['monthly'] as $month => $count)
                            <div class="flex items-center justify-between">
                                <span class="text-sm" style="color: var(--text-primary);">{{ $month }}</span>
                                <div class="flex items-center">
                                    <div class="w-32 h-2 rounded-full mr-2" style="background-color: var(--border-color);">
                                        <div class="h-full rounded-full" style="background-color: var(--primary); width: {{ min(100, ($count / max($trends['monthly_max'], 1)) * 100) }}%;"></div>
                                    </div>
                                    <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $count }}</span>
                                </div>
                            </div>
                            @endforeach
                        @else
                            <div class="text-center py-4" style="color: var(--text-secondary);">
                                <i class="fas fa-chart-bar text-2xl mb-2"></i>
                                <p>No monthly data available</p>
                            </div>
                        @endif
                    </div>
                </div>
                
                <!-- Status Distribution -->
                <div>
                    <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                        <i class="fas fa-tasks mr-2"></i> Status Distribution
                    </h4>
                    <div class="space-y-3">
                        @php
                            $statusColors = [
                                'draft' => 'var(--info)',
                                'scheduled' => 'var(--primary)',
                                'in_progress' => 'var(--warning)',
                                'completed' => 'var(--success)',
                                'cancelled' => 'var(--danger)',
                            ];
                            $statusLabels = [
                                'draft' => 'Draft',
                                'scheduled' => 'Scheduled',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ];
                        @endphp
                        @foreach($statusDistribution ?? [] as $status => $count)
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full mr-2" style="background-color: {{ $statusColors[$status] ?? 'var(--text-secondary)' }};"></div>
                                <span class="text-sm" style="color: var(--text-primary);">{{ $statusLabels[$status] ?? $status }}</span>
                            </div>
                            <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $count }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                
                <!-- Impact Level Distribution -->
                <div>
                    <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                        <i class="fas fa-exclamation-triangle mr-2"></i> Impact Distribution
                    </h4>
                    <div class="space-y-3">
                        @php
                            $impactColors = [
                                'low' => 'var(--success)',
                                'medium' => 'var(--warning)',
                                'high' => 'var(--warning)',
                                'critical' => 'var(--danger)',
                            ];
                        @endphp
                        @foreach($impactDistribution ?? [] as $impact => $count)
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full mr-2" style="background-color: {{ $impactColors[$impact] ?? 'var(--text-secondary)' }};"></div>
                                <span class="text-sm capitalize" style="color: var(--text-primary);">{{ $impact }}</span>
                            </div>
                            <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $count }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Metrics -->
    <div class="card mb-6">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-tachometer-alt mr-2"></i> Performance Metrics
            </h3>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Key performance indicators and efficiency measurements
            </p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Duration Accuracy -->
                <div class="text-center">
                    <div class="relative w-24 h-24 mx-auto mb-3">
                        <svg class="w-full h-full" viewBox="0 0 36 36">
                            <path d="M18 2.0845
                                    a 15.9155 15.9155 0 0 1 0 31.831
                                    a 15.9155 15.9155 0 0 1 0 -31.831"
                                fill="none"
                                stroke="var(--border-color)"
                                stroke-width="3"/>
                            <path d="M18 2.0845
                                    a 15.9155 15.9155 0 0 1 0 31.831
                                    a 15.9155 15.9155 0 0 1 0 -31.831"
                                fill="none"
                                stroke="var(--success)"
                                stroke-width="3"
                                stroke-dasharray="{{ $statistics['duration_accuracy'] ?? 0 }}, 100"
                                stroke-linecap="round"/>
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-lg font-bold" style="color: var(--text-primary);">{{ $statistics['duration_accuracy'] ?? 0 }}%</span>
                        </div>
                    </div>
                    <h4 class="text-sm font-medium mb-1" style="color: var(--text-primary);">Duration Accuracy</h4>
                    <p class="text-xs" style="color: var(--text-secondary);">Actual vs Estimated</p>
                </div>
                
                <!-- On-time Completion -->
                <div class="text-center">
                    <div class="relative w-24 h-24 mx-auto mb-3">
                        <svg class="w-full h-full" viewBox="0 0 36 36">
                            <path d="M18 2.0845
                                    a 15.9155 15.9155 0 0 1 0 31.831
                                    a 15.9155 15.9155 0 0 1 0 -31.831"
                                fill="none"
                                stroke="var(--border-color)"
                                stroke-width="3"/>
                            <path d="M18 2.0845
                                    a 15.9155 15.9155 0 0 1 0 31.831
                                    a 15.9155 15.9155 0 0 1 0 -31.831"
                                fill="none"
                                stroke="var(--warning)"
                                stroke-width="3"
                                stroke-dasharray="{{ $statistics['on_time_rate'] ?? 0 }}, 100"
                                stroke-linecap="round"/>
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-lg font-bold" style="color: var(--text-primary);">{{ $statistics['on_time_rate'] ?? 0 }}%</span>
                        </div>
                    </div>
                    <h4 class="text-sm font-medium mb-1" style="color: var(--text-primary);">On-time Completion</h4>
                    <p class="text-xs" style="color: var(--text-secondary);">Within schedule</p>
                </div>
                
                <!-- User Impact Accuracy -->
                <div class="text-center">
                    <div class="relative w-24 h-24 mx-auto mb-3">
                        <svg class="w-full h-full" viewBox="0 0 36 36">
                            <path d="M18 2.0845
                                    a 15.9155 15.9155 0 0 1 0 31.831
                                    a 15.9155 15.9155 0 0 1 0 -31.831"
                                fill="none"
                                stroke="var(--border-color)"
                                stroke-width="3"/>
                            <path d="M18 2.0845
                                    a 15.9155 15.9155 0 0 1 0 31.831
                                    a 15.9155 15.9155 0 0 1 0 -31.831"
                                fill="none"
                                stroke="var(--info)"
                                stroke-width="3"
                                stroke-dasharray="{{ $statistics['user_impact_accuracy'] ?? 0 }}, 100"
                                stroke-linecap="round"/>
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-lg font-bold" style="color: var(--text-primary);">{{ $statistics['user_impact_accuracy'] ?? 0 }}%</span>
                        </div>
                    </div>
                    <h4 class="text-sm font-medium mb-1" style="color: var(--text-primary);">Impact Accuracy</h4>
                    <p class="text-xs" style="color: var(--text-secondary);">Estimated vs Actual users</p>
                </div>
                
                <!-- Downtime Efficiency -->
                <div class="text-center">
                    <div class="relative w-24 h-24 mx-auto mb-3">
                        <svg class="w-full h-full" viewBox="0 0 36 36">
                            <path d="M18 2.0845
                                    a 15.9155 15.9155 0 0 1 0 31.831
                                    a 15.9155 15.9155 0 0 1 0 -31.831"
                                fill="none"
                                stroke="var(--border-color)"
                                stroke-width="3"/>
                            <path d="M18 2.0845
                                    a 15.9155 15.9155 0 0 1 0 31.831
                                    a 15.9155 15.9155 0 0 1 0 -31.831"
                                fill="none"
                                stroke="var(--primary)"
                                stroke-width="3"
                                stroke-dasharray="{{ $statistics['downtime_efficiency'] ?? 0 }}, 100"
                                stroke-linecap="round"/>
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-lg font-bold" style="color: var(--text-primary);">{{ $statistics['downtime_efficiency'] ?? 0 }}%</span>
                        </div>
                    </div>
                    <h4 class="text-sm font-medium mb-1" style="color: var(--text-primary);">Downtime Efficiency</h4>
                    <p class="text-xs" style="color: var(--text-secondary);">Minimal disruption</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="grid grid-cols-1 gap-6">
        <!-- Recent Maintenance -->
        <div class="card">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-history mr-2"></i> Recent Maintenance
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Latest maintenance activities
                </p>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @forelse($recentMaintenances as $maintenance)
                    <div class="flex items-center justify-between p-3 rounded-lg border" style="border-color: var(--border-color);">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                 style="background-color: {{ $maintenance->status === 'completed' ? 'rgba(var(--success-rgb), 0.1)' : 
                                                           ($maintenance->status === 'in_progress' ? 'rgba(var(--warning-rgb), 0.1)' : 
                                                           'rgba(var(--info-rgb), 0.1)') }};
                                        color: {{ $maintenance->status === 'completed' ? 'var(--success)' : 
                                                ($maintenance->status === 'in_progress' ? 'var(--warning)' : 'var(--info)') }};">
                                <i class="fas fa-tools"></i>
                            </div>
                            <div>
                                <h4 class="font-medium text-sm" style="color: var(--text-primary);">
                                    {{ Str::limit($maintenance->title, 40) }}
                                </h4>
                                <div class="flex items-center mt-1">
                                    <span class="text-xs px-2 py-0.5 rounded-full mr-2"
                                          style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                        {{ $maintenance->reference_id }}
                                    </span>
                                    <span class="text-xs" style="color: var(--text-secondary);">
                                        {{ $maintenance->created_at->diffForHumans() }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <a href="{{ route('developer.maintenance.show', $maintenance->id) }}" 
                           class="p-2 rounded hover:bg-opacity-20 transition-colors"
                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            <i class="fas fa-eye"></i>
                        </a>
                    </div>
                    @empty
                    <div class="text-center py-8" style="color: var(--text-secondary);">
                        <i class="fas fa-tools text-2xl mb-2"></i>
                        <p>No recent maintenance found</p>
                    </div>
                    @endforelse
                </div>
                @if($recentMaintenances->count() > 0)
                <div class="mt-4 text-center">
                    <a href="{{ route('developer.maintenance.index') }}" class="btn-primary text-sm">
                        View All Maintenance
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Type Distribution -->
    <div class="card mb-6">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-chart-pie mr-2"></i> Maintenance Type Distribution
            </h3>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Breakdown of maintenance by type
            </p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                @php
                    $typeColors = [
                        'planned' => 'var(--primary)',
                        'emergency' => 'var(--danger)',
                        'hotfix' => 'var(--warning)',
                        'upgrade' => 'var(--info)',
                        'security' => 'var(--success)',
                    ];
                    $typeLabels = [
                        'planned' => 'Planned Maintenance',
                        'emergency' => 'Emergency Maintenance',
                        'hotfix' => 'Hotfix',
                        'upgrade' => 'System Upgrade',
                        'security' => 'Security Patch',
                    ];
                @endphp
                @foreach($typeDistribution ?? [] as $type => $count)
                <div class="text-center">
                    <div class="relative w-20 h-20 mx-auto mb-2">
                        <svg class="w-full h-full" viewBox="0 0 36 36">
                            <circle cx="18" cy="18" r="15.9155" fill="none" 
                                    stroke="{{ $typeColors[$type] ?? 'var(--text-secondary)' }}" 
                                    stroke-width="3"/>
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-lg font-bold" style="color: var(--text-primary);">{{ $count }}</span>
                        </div>
                    </div>
                    <h4 class="text-sm font-medium mb-1" style="color: var(--text-primary);">
                        {{ $typeLabels[$type] ?? ucfirst($type) }}
                    </h4>
                    @if($statistics['total'] > 0)
                    <p class="text-xs" style="color: var(--text-secondary);">
                        {{ round(($count / $statistics['total']) * 100) }}% of total
                    </p>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Export & Actions -->
    <div class="card">
        <div class="p-6">
            <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                <div>
                    <h4 class="font-medium mb-2" style="color: var(--text-primary);">Export Statistics</h4>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        Download comprehensive reports and analytics
                    </p>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        Reports include all statistics shown on this page
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button onclick="exportStatistics('pdf')" class="btn-danger flex items-center">
                        <i class="fas fa-file-pdf mr-2"></i> PDF Report
                    </button>
                    <button onclick="exportStatistics('excel')" class="btn-success flex items-center">
                        <i class="fas fa-file-excel mr-2"></i> Excel Report
                    </button>
                    <button onclick="exportStatistics('csv')" class="btn-info flex items-center">
                        <i class="fas fa-file-csv mr-2"></i> CSV Export
                    </button>
                    <button onclick="printStatistics()" class="btn-secondary flex items-center">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Modal -->
<div id="loadingModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card p-8">
        <div class="text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center animate-spin"
                 style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                <i class="fas fa-spinner text-xl"></i>
            </div>
            <h4 class="font-medium mb-2" style="color: var(--text-primary);">Generating Report</h4>
            <p class="text-sm" style="color: var(--text-secondary);">Please wait...</p>
            <div class="w-48 h-1 mx-auto mt-4 rounded-full overflow-hidden" style="background-color: var(--border-color);">
                <div id="progressBar" class="h-full rounded-full transition-all duration-1000" 
                     style="background-color: var(--primary); width: 0%;"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function exportStatistics(format) {
    const modal = document.getElementById('loadingModal');
    const progressBar = document.getElementById('progressBar');
    
    // Show loading modal
    modal.classList.remove('hidden');
    progressBar.style.width = '0%';
    
    // Simulate progress
    let progress = 0;
    const interval = setInterval(() => {
        progress += 10;
        progressBar.style.width = `${progress}%`;
        
        if (progress >= 100) {
            clearInterval(interval);
            
            // Hide modal after completion
            setTimeout(() => {
                modal.classList.add('hidden');
                
                // Show success message based on format
                const messages = {
                    'pdf': 'PDF report generated successfully!',
                    'excel': 'Excel report generated successfully!',
                    'csv': 'CSV data exported successfully!'
                };
                
                showToast(messages[format] || 'Report generated successfully!', 'success');
                
                // In real implementation, this would trigger a download
                // For demo, we'll just show a message
                if (format === 'csv') {
                    // Simulate CSV download
                    triggerCSVDownload();
                }
            }, 500);
        }
    }, 200);
}

function triggerCSVDownload() {
    // This would be your actual CSV generation and download logic
    // For demo purposes, we're just showing a message
    console.log('CSV download triggered');
}

function printStatistics() {
    // Store original button visibility
    const originalDisplay = {};
    const buttons = document.querySelectorAll('.btn-primary, .btn-secondary, .btn-warning, .btn-success, .btn-danger, .btn-info');
    
    buttons.forEach(btn => {
        originalDisplay[btn.className] = btn.style.display;
        btn.style.display = 'none';
    });
    
    // Trigger print
    window.print();
    
    // Restore button visibility after print
    setTimeout(() => {
        buttons.forEach(btn => {
            btn.style.display = originalDisplay[btn.className] || '';
        });
    }, 500);
}

function showToast(message, type = 'info') {
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 translate-x-full z-50`;
    
    // Set styles based on type
    const styles = {
        success: 'background-color: rgba(var(--success-rgb), 0.9); color: white; border-left: 4px solid var(--success);',
        error: 'background-color: rgba(var(--danger-rgb), 0.9); color: white; border-left: 4px solid var(--danger);',
        warning: 'background-color: rgba(var(--warning-rgb), 0.9); color: white; border-left: 4px solid var(--warning);',
        info: 'background-color: rgba(var(--info-rgb), 0.9); color: white; border-left: 4px solid var(--info);'
    };
    
    toast.setAttribute('style', styles[type] || styles.info);
    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle'} mr-3"></i>
            <span>${message}</span>
        </div>
    `;
    
    // Add to page
    document.body.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
    }, 10);
    
    // Remove after 5 seconds
    setTimeout(() => {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 5000);
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    // Initialize chart hover effects
    document.querySelectorAll('.text-center').forEach(chart => {
        chart.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
        });
        
        chart.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
    
    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+P for print
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            printStatistics();
        }
        
        // Ctrl+E for export (opens export options)
        if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
            e.preventDefault();
            document.querySelector('button[onclick="exportStatistics(\'pdf\')"]').focus();
        }
    });
});
</script>

<style>
/* Print styles */
@media print {
    .card {
        break-inside: avoid;
        border: 1px solid #ddd !important;
        box-shadow: none !important;
        margin-bottom: 20px;
    }
    
    .btn-primary, .btn-secondary, .btn-warning, 
    .btn-success, .btn-danger, .btn-info,
    button {
        display: none !important;
    }
    
    .flex.justify-between {
        justify-content: flex-start !important;
    }
    
    .grid {
        display: block !important;
    }
    
    .grid > * {
        margin-bottom: 20px;
    }
    
    .text-sm {
        font-size: 12px !important;
    }
    
    .text-xs {
        font-size: 10px !important;
    }
    
    .text-2xl {
        font-size: 24px !important;
    }
    
    .text-lg {
        font-size: 18px !important;
    }
    
    /* Hide interactive elements */
    .hover\:bg-opacity-20, 
    .hover\:bg-opacity-5,
    .transition-colors,
    .group-hover\:block {
        display: none !important;
    }
    
    /* Ensure good contrast for printing */
    * {
        color: #000 !important;
        background-color: transparent !important;
    }
    
    /* Remove background colors for charts */
    [style*="background-color: rgba("] {
        background-color: #f8f9fa !important;
    }
}

/* Progress circle animation */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.animate-spin {
    animation: spin 1s linear infinite;
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Chart hover effects */
.text-center:hover .relative {
    transform: scale(1.05);
    transition: transform 0.3s ease;
}

/* Toast animation */
@keyframes slideInRight {
    from {
        transform: translateX(100%);
    }
    to {
        transform: translateX(0);
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
    }
    to {
        transform: translateX(100%);
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid-cols-4 {
        grid-template-columns: repeat(2, 1fr) !important;
    }
    
    .grid-cols-3, 
    .grid-cols-2 {
        grid-template-columns: 1fr !important;
    }
    
    .flex.flex-col.md\:flex-row {
        flex-direction: column !important;
    }
    
    .flex.space-x-2,
    .flex.gap-2 {
        flex-wrap: wrap !important;
        gap: 0.5rem !important;
    }
    
    .p-6 {
        padding: 1rem !important;
    }
    
    .text-2xl {
        font-size: 1.5rem !important;
    }
    
    .w-24.h-24 {
        width: 4rem !important;
        height: 4rem !important;
    }
    
    .w-20.h-20 {
        width: 3.5rem !important;
        height: 3.5rem !important;
    }
}

@media (max-width: 480px) {
    .grid-cols-4 {
        grid-template-columns: 1fr !important;
    }
    
    .flex.justify-between {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 1rem;
    }
    
    .flex.justify-between > div:last-child {
        align-self: stretch;
    }
    
    .flex.space-x-2 {
        width: 100%;
    }
    
    .flex.space-x-2 a,
    .flex.space-x-2 button {
        flex: 1;
        justify-content: center;
    }
}

/* Dark mode print adjustments */
@media print and (prefers-color-scheme: dark) {
    .card {
        border-color: #444 !important;
    }
    
    * {
        color: #333 !important;
    }
    
    [style*="background-color: rgba("] {
        background-color: #e9ecef !important;
    }
}

/* Accessibility improvements */
@media (prefers-reduced-motion: reduce) {
    .animate-spin,
    .animate-pulse,
    .transition-all,
    .transition-colors,
    .transform {
        animation: none !important;
        transition: none !important;
    }
}

/* Focus states for accessibility */
button:focus,
a:focus,
input:focus {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .card {
        border: 2px solid currentColor !important;
    }
    
    button,
    a.btn-primary,
    a.btn-secondary,
    a.btn-warning,
    a.btn-success,
    a.btn-danger {
        border: 2px solid currentColor !important;
    }
}
</style>
@endsection