@extends('layouts.app')

@section('title', 'Registration Statistics')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <!-- Icon -->
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, #ff0080 0%, #ffaa00 100%); color: white; font-weight: 600; border-color: #ff0080;">
                        <i class="fas fa-chart-bar text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-chart-bar mr-2" style="color: #ff0080;"></i> 
                        Registration Statistics
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-chart-line mr-2" style="color: #ffaa00;"></i>
                        <span>Analytics and performance metrics</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-calendar-alt mr-2" style="color: #00cc88;"></i>
                        <span>Last 12 months</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <a href="{{ route('admin.construction-registrations.index') }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background: linear-gradient(135deg, #ff0080 0%, #ff4da6 100%); color: white; border: none;">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Registrations
                </a>
                <button onclick="exportStats()" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                        style="background: linear-gradient(135deg, #00cc88 0%, #33ddaa 100%);">
                    <i class="fas fa-download mr-2"></i> Export Report
                </button>
            </div>
        </div>
    </div>

    <!-- Summary Cards - Updated to use controller data -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Total Registrations -->
        <div class="card p-6" style="border-top: 4px solid #ff0080;">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Total Registrations</p>
                    <p class="text-3xl font-bold mt-2" style="color: #ff0080;">{{ $stats['total_registrations'] ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center"
                     style="background: linear-gradient(135deg, rgba(255,0,128,0.1) 0%, rgba(255,77,166,0.1) 100%);">
                    <i class="fas fa-file-alt text-xl" style="color: #ff0080;"></i>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                <div class="flex items-center text-sm">
                    <i class="fas fa-arrow-up mr-1" style="color: #00cc88;"></i>
                    <span style="color: var(--text-primary);">{{ $stats['monthly_totals']['this_month'] ?? 0 }}</span>
                    <span class="mx-1" style="color: var(--text-secondary);">this month</span>
                </div>
            </div>
        </div>

        <!-- By Type - Construction -->
        <div class="card p-6" style="border-top: 4px solid #0066ff;">
            @php
                $constructionTotal = $stats['by_type']['construction'] ?? 0;
                $propertyTotal = $stats['by_type']['property_capture'] ?? 0;
            @endphp
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Construction</p>
                    <p class="text-3xl font-bold mt-2" style="color: #0066ff;">{{ $constructionTotal }}</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center"
                     style="background: linear-gradient(135deg, rgba(0,102,255,0.1) 0%, rgba(77,148,255,0.1) 100%);">
                    <i class="fas fa-hard-hat text-xl" style="color: #0066ff;"></i>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                <div class="flex items-center text-sm">
                    <span style="color: var(--text-primary);">{{ $stats['by_type']['property_capture'] ?? 0 }}</span>
                    <span class="mx-1" style="color: var(--text-secondary);">property capture</span>
                </div>
            </div>
        </div>

        <!-- By Purpose -->
        <div class="card p-6" style="border-top: 4px solid #ffaa00;">
            @php
                $bothPurpose = $stats['by_purpose']['both'] ?? 0;
            @endphp
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Both Purposes</p>
                    <p class="text-3xl font-bold mt-2" style="color: #ffaa00;">{{ $bothPurpose }}</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center"
                     style="background: linear-gradient(135deg, rgba(255,170,0,0.1) 0%, rgba(255,187,51,0.1) 100%);">
                    <i class="fas fa-layer-group text-xl" style="color: #ffaa00;"></i>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                <div class="flex items-center text-sm">
                    <span style="color: var(--text-primary);">{{ $stats['by_purpose']['construction'] ?? 0 }}</span>
                    <span class="mx-1" style="color: var(--text-secondary);">construction only</span>
                </div>
            </div>
        </div>

        <!-- Pending Review -->
        <div class="card p-6" style="border-top: 4px solid #ff3300;">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Pending Review</p>
                    <p class="text-3xl font-bold mt-2" style="color: #ff3300;">{{ $stats['by_status']['pending'] ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center"
                     style="background: linear-gradient(135deg, rgba(255,51,0,0.1) 0%, rgba(255,102,51,0.1) 100%);">
                    <i class="fas fa-clock text-xl" style="color: #ff3300;"></i>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                <div class="flex items-center text-sm">
                    <span style="color: var(--text-primary);">{{ $stats['by_status']['in_review'] ?? 0 }}</span>
                    <span class="mx-1" style="color: var(--text-secondary);">in review</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Second Row Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Approval Rate -->
        <div class="card p-6" style="border-top: 4px solid #00cc88;">
            @php
                $total = $stats['total_registrations'] ?? 0;
                $approved = $stats['by_status']['approved'] ?? 0;
                $approvalRate = $total > 0 ? round(($approved / $total) * 100, 1) : 0;
            @endphp
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Approval Rate</p>
                    <p class="text-3xl font-bold mt-2" style="color: #00cc88;">{{ $approvalRate }}%</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center"
                     style="background: linear-gradient(135deg, rgba(0,204,136,0.1) 0%, rgba(51,221,170,0.1) 100%);">
                    <i class="fas fa-check-circle text-xl" style="color: #00cc88;"></i>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                <div class="flex items-center text-sm">
                    <span style="color: var(--text-primary);">{{ $approved }} approved</span>
                    <span class="mx-1" style="color: var(--text-secondary);">out of {{ $total }}</span>
                </div>
            </div>
        </div>

        <!-- Average Processing Time -->
        <div class="card p-6" style="border-top: 4px solid #00ffcc;">
            @php
                $avgTime = $stats['avg_processing_time']['hours'] ?? 0;
                $avgDays = round($avgTime / 24, 1);
            @endphp
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Avg Processing Time</p>
                    <p class="text-3xl font-bold mt-2" style="color: #00ffcc;">{{ $avgDays }} days</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center"
                     style="background: linear-gradient(135deg, rgba(0,255,204,0.1) 0%, rgba(77,255,219,0.1) 100%);">
                    <i class="fas fa-clock text-xl" style="color: #00ffcc;"></i>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                <div class="flex items-center text-sm">
                    <span style="color: var(--text-primary);">{{ round($avgTime, 1) }} hours</span>
                    <span class="mx-1" style="color: var(--text-secondary);">average</span>
                </div>
            </div>
        </div>

        <!-- Rejection Rate -->
        <div class="card p-6" style="border-top: 4px solid #ff3300;">
            @php
                $rejected = $stats['by_status']['rejected'] ?? 0;
                $rejectionRate = $total > 0 ? round(($rejected / $total) * 100, 1) : 0;
            @endphp
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Rejection Rate</p>
                    <p class="text-3xl font-bold mt-2" style="color: #ff3300;">{{ $rejectionRate }}%</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center"
                     style="background: linear-gradient(135deg, rgba(255,51,0,0.1) 0%, rgba(255,102,51,0.1) 100%);">
                    <i class="fas fa-times-circle text-xl" style="color: #ff3300;"></i>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                <div class="flex items-center text-sm">
                    <span style="color: var(--text-primary);">{{ $rejected }} rejected</span>
                    <span class="mx-1" style="color: var(--text-secondary);">total</span>
                </div>
            </div>
        </div>

        <!-- With Tenants -->
        <div class="card p-6" style="border-top: 4px solid #aa00ff;">
            @php
                $withTenants = $stats['with_tenants'] ?? 0;
            @endphp
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Properties with Tenants</p>
                    <p class="text-3xl font-bold mt-2" style="color: #aa00ff;">{{ $withTenants }}</p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center"
                     style="background: linear-gradient(135deg, rgba(170,0,255,0.1) 0%, rgba(194,77,255,0.1) 100%);">
                    <i class="fas fa-users text-xl" style="color: #aa00ff;"></i>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                <div class="flex items-center text-sm">
                    <span style="color: var(--text-primary);">{{ $stats['total_tenants'] ?? 0 }}</span>
                    <span class="mx-1" style="color: var(--text-secondary);">total tenants</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Monthly Trends Chart -->
        <div class="card">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-chart-line mr-2" style="color: #ff0080;"></i> Monthly Trends
                </h3>
            </div>
            <div class="p-6">
                <canvas id="monthlyTrendsChart" height="300"></canvas>
            </div>
        </div>

        <!-- Status Distribution -->
        <div class="card">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-chart-pie mr-2" style="color: #00cc88;"></i> Status Distribution
                </h3>
            </div>
            <div class="p-6">
                <canvas id="statusChart" height="300"></canvas>
            </div>
        </div>
    </div>

    <!-- Registration Type Distribution -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-building mr-2" style="color: #0066ff;"></i> Registration Type Distribution
            </h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <canvas id="registrationTypeChart" height="250"></canvas>
                </div>
                <div>
                    <div class="space-y-4">
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <div class="flex items-center">
                                    <span class="w-3 h-3 rounded-full mr-2" style="background: #0066ff;"></span>
                                    <span style="color: var(--text-primary);">Construction</span>
                                </div>
                                <span class="text-sm font-medium" style="color: var(--chart-text);">
                                    {{ $stats['by_type']['construction'] ?? 0 }} ({{ $stats['by_type']['construction_percentage'] ?? 0 }}%)
                                </span>
                            </div>
                            <div class="w-full h-2 rounded-full" style="background-color: var(--border-color);">
                                <div class="h-2 rounded-full" style="width: {{ $stats['by_type']['construction_percentage'] ?? 0 }}%; background: linear-gradient(90deg, #0066ff, #4d94ff);"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <div class="flex items-center">
                                    <span class="w-3 h-3 rounded-full mr-2" style="background: #ff0080;"></span>
                                    <span style="color: var(--text-primary);">Property Capture</span>
                                </div>
                                <span class="text-sm font-medium" style="color: var(--chart-text);">
                                    {{ $stats['by_type']['property_capture'] ?? 0 }} ({{ $stats['by_type']['property_percentage'] ?? 0 }}%)
                                </span>
                            </div>
                            <div class="w-full h-2 rounded-full" style="background-color: var(--border-color);">
                                <div class="h-2 rounded-full" style="width: {{ $stats['by_type']['property_percentage'] ?? 0 }}%; background: linear-gradient(90deg, #ff0080, #ff4da6);"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                        <h4 class="font-medium mb-3" style="color: var(--text-primary);">Purpose Breakdown</h4>
                        <div class="space-y-3">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span style="color: var(--text-secondary);">Construction Only</span>
                                    <span class="text-sm font-medium" style="color: var(--chart-text);">{{ $stats['by_purpose']['construction'] ?? 0 }}</span>
                                </div>
                                <div class="w-full h-1.5 rounded-full" style="background-color: var(--border-color);">
                                    <div class="h-1.5 rounded-full" style="width: {{ $stats['by_purpose']['construction_percentage'] ?? 0 }}%; background: linear-gradient(90deg, #0066ff, #4d94ff);"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span style="color: var(--text-secondary);">Permanent Registration</span>
                                    <span class="text-sm font-medium" style="color: var(--chart-text);">{{ $stats['by_purpose']['permanent_registration'] ?? 0 }}</span>
                                </div>
                                <div class="w-full h-1.5 rounded-full" style="background-color: var(--border-color);">
                                    <div class="h-1.5 rounded-full" style="width: {{ $stats['by_purpose']['permanent_percentage'] ?? 0 }}%; background: linear-gradient(90deg, #ff0080, #ff4da6);"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span style="color: var(--text-secondary);">Both Purposes</span>
                                    <span class="text-sm font-medium" style="color: var(--chart-text);">{{ $stats['by_purpose']['both'] ?? 0 }}</span>
                                </div>
                                <div class="w-full h-1.5 rounded-full" style="background-color: var(--border-color);">
                                    <div class="h-1.5 rounded-full" style="width: {{ $stats['by_purpose']['both_percentage'] ?? 0 }}%; background: linear-gradient(90deg, #aa00ff, #c24dff);"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Property Type Distribution (for construction registrations) -->
    @if(!empty($stats['property_type_stats']))
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-building mr-2" style="color: #00ffcc;"></i> Planned Property Types (Construction)
            </h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <canvas id="propertyTypeChart" height="250"></canvas>
                </div>
                <div>
                    <div class="space-y-4">
                        @foreach($stats['property_type_stats'] as $type => $count)
                        @php
                            $total = array_sum($stats['property_type_stats']);
                            $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                            $colors = [
                                ['start' => '#ff0080', 'end' => '#ff4da6'],
                                ['start' => '#00cc88', 'end' => '#33ddaa'],
                                ['start' => '#0066ff', 'end' => '#4d94ff'],
                                ['start' => '#ffaa00', 'end' => '#ffbb33'],
                                ['start' => '#ff3300', 'end' => '#ff6633'],
                                ['start' => '#aa00ff', 'end' => '#c24dff']
                            ];
                            $color = $colors[$loop->index % count($colors)];
                        @endphp
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <div class="flex items-center">
                                    <span class="w-3 h-3 rounded-full mr-2" style="background: {{ $color['start'] }};"></span>
                                    <span style="color: var(--text-primary);">{{ ucfirst(str_replace('_', ' ', $type)) }}</span>
                                </div>
                                <span class="text-sm font-medium" style="color: var(--chart-text);">{{ $count }} ({{ $percentage }}%)</span>
                            </div>
                            <div class="w-full h-2 rounded-full" style="background-color: var(--border-color);">
                                <div class="h-2 rounded-full" style="width: {{ $percentage }}%; background: linear-gradient(90deg, {{ $color['start'] }}, {{ $color['end'] }});"></div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Existing Property Types (for property capture) -->
    @if(!empty($stats['existing_property_type_stats']))
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-building mr-2" style="color: #ffaa00;"></i> Existing Property Types
            </h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <canvas id="existingPropertyTypeChart" height="250"></canvas>
                </div>
                <div>
                    <div class="space-y-4">
                        @foreach($stats['existing_property_type_stats'] as $type => $count)
                        @php
                            $total = array_sum($stats['existing_property_type_stats']);
                            $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                            $colors = [
                                ['start' => '#ff0080', 'end' => '#ff4da6'],
                                ['start' => '#00cc88', 'end' => '#33ddaa'],
                                ['start' => '#0066ff', 'end' => '#4d94ff'],
                                ['start' => '#ffaa00', 'end' => '#ffbb33'],
                                ['start' => '#ff3300', 'end' => '#ff6633'],
                                ['start' => '#aa00ff', 'end' => '#c24dff']
                            ];
                            $color = $colors[$loop->index % count($colors)];
                        @endphp
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <div class="flex items-center">
                                    <span class="w-3 h-3 rounded-full mr-2" style="background: {{ $color['start'] }};"></span>
                                    <span style="color: var(--text-primary);">{{ ucfirst(str_replace('_', ' ', $type)) }}</span>
                                </div>
                                <span class="text-sm font-medium" style="color: var(--chart-text);">{{ $count }} ({{ $percentage }}%)</span>
                            </div>
                            <div class="w-full h-2 rounded-full" style="background-color: var(--border-color);">
                                <div class="h-2 rounded-full" style="width: {{ $percentage }}%; background: linear-gradient(90deg, {{ $color['start'] }}, {{ $color['end'] }});"></div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Admin Performance -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-user-shield mr-2" style="color: #aa00ff;"></i> Admin Performance
            </h3>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b" style="border-color: var(--border-color);">
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Admin</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Approved</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Rejected</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Total Reviewed</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Approval Rate</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Avg Time</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Currently Assigned</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stats['admin_performance'] ?? [] as $admin)
                        @php
                            $totalReviewed = ($admin['approved'] ?? 0) + ($admin['rejected'] ?? 0);
                            $approvalRate = $totalReviewed > 0 ? round(($admin['approved'] / $totalReviewed) * 100, 1) : 0;
                            $avgTime = $admin['avg_processing_time'] ?? 0;
                            $avgTimeFormatted = $avgTime > 0 ? round($avgTime, 1) . 'h' : 'N/A';
                        @endphp
                        <tr class="border-b" style="border-color: var(--border-color);">
                            <td class="py-3 px-4">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                         style="background: linear-gradient(135deg, rgba(255,0,128,0.1) 0%, rgba(170,0,255,0.1) 100%); color: #ff0080;">
                                        <i class="fas fa-user-shield"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium" style="color: var(--text-primary);">{{ $admin['name'] }}</div>
                                        <div class="text-xs" style="color: var(--text-secondary);">{{ $admin['email'] }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-3 py-1 rounded-full text-xs font-medium"
                                      style="background: linear-gradient(135deg, rgba(0,204,136,0.1) 0%, rgba(51,221,170,0.1) 100%); color: #00cc88; border: 1px solid rgba(0,204,136,0.3);">
                                    {{ $admin['approved'] ?? 0 }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-3 py-1 rounded-full text-xs font-medium"
                                      style="background: linear-gradient(135deg, rgba(255,51,0,0.1) 0%, rgba(255,102,51,0.1) 100%); color: #ff3300; border: 1px solid rgba(255,51,0,0.3);">
                                    {{ $admin['rejected'] ?? 0 }}
                                </span>
                            </td>
                            <td class="py-3 px-4" style="color: var(--text-primary);">{{ $totalReviewed }}</td>
                            <td class="py-3 px-4">
                                <div class="flex items-center">
                                    <span class="mr-2" style="color: var(--text-primary);">{{ $approvalRate }}%</span>
                                    <div class="w-16 h-2 rounded-full" style="background-color: var(--border-color);">
                                        <div class="h-2 rounded-full" style="width: {{ $approvalRate }}%; background: linear-gradient(90deg, #00cc88, #33ddaa);"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-4" style="color: var(--text-primary);">{{ $avgTimeFormatted }}</td>
                            <td class="py-3 px-4">
                                <span class="px-3 py-1 rounded-full text-xs font-medium"
                                      style="background: linear-gradient(135deg, rgba(0,102,255,0.1) 0%, rgba(77,148,255,0.1) 100%); color: #0066ff; border: 1px solid rgba(0,102,255,0.3);">
                                    {{ $admin['assigned_count'] ?? 0 }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="py-8 px-4 text-center" style="color: var(--text-secondary);">
                                <i class="fas fa-user-shield text-4xl mb-3 opacity-50" style="color: #aa00ff;"></i>
                                <p>No admin performance data available</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Zone/Section Distribution -->
    @if(!empty($stats['zone_stats']) || !empty($stats['section_stats']))
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-map-marked-alt mr-2" style="color: #00ffcc;"></i> Zone & Section Distribution
            </h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium mb-4" style="color: var(--text-primary);">By Zone</h4>
                    <div class="space-y-3">
                        @forelse($stats['zone_stats'] ?? [] as $zone => $count)
                        @php
                            $total = array_sum($stats['zone_stats'] ?? []);
                            $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                        @endphp
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span style="color: var(--text-secondary);">Zone {{ $zone }}</span>
                                <span class="text-sm font-medium" style="color: var(--chart-text);">{{ $count }} ({{ $percentage }}%)</span>
                            </div>
                            <div class="w-full h-2 rounded-full" style="background-color: var(--border-color);">
                                <div class="h-2 rounded-full" style="width: {{ $percentage }}%; background: linear-gradient(90deg, #0066ff, #4d94ff);"></div>
                            </div>
                        </div>
                        @empty
                        <p class="text-center" style="color: var(--text-secondary);">No zone data available</p>
                        @endforelse
                    </div>
                </div>
                <div>
                    <h4 class="font-medium mb-4" style="color: var(--text-primary);">By Section</h4>
                    <div class="space-y-3">
                        @forelse($stats['section_stats'] ?? [] as $section => $count)
                        @php
                            $total = array_sum($stats['section_stats'] ?? []);
                            $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                        @endphp
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span style="color: var(--text-secondary);">Section {{ $section }}</span>
                                <span class="text-sm font-medium" style="color: var(--chart-text);">{{ $count }} ({{ $percentage }}%)</span>
                            </div>
                            <div class="w-full h-2 rounded-full" style="background-color: var(--border-color);">
                                <div class="h-2 rounded-full" style="width: {{ $percentage }}%; background: linear-gradient(90deg, #00cc88, #33ddaa);"></div>
                            </div>
                        </div>
                        @empty
                        <p class="text-center" style="color: var(--text-secondary);">No section data available</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Recent Activity -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-history mr-2" style="color: #ffaa00;"></i> Recent Activity
            </h3>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                @forelse($stats['recent_activity'] ?? [] as $activity)
                <div class="flex items-start p-4 rounded-lg" style="background: linear-gradient(135deg, rgba(255,0,128,0.05) 0%, rgba(255,170,0,0.05) 100%);">
                    @php
                        $activityIcon = match($activity['status']) {
                            'approved' => ['icon' => 'fa-check-circle', 'start' => '#00cc88', 'end' => '#33ddaa'],
                            'rejected' => ['icon' => 'fa-times-circle', 'start' => '#ff3300', 'end' => '#ff6633'],
                            'in_review' => ['icon' => 'fa-search', 'start' => '#0066ff', 'end' => '#4d94ff'],
                            'needs_info' => ['icon' => 'fa-question-circle', 'start' => '#ffaa00', 'end' => '#ffbb33'],
                            default => ['icon' => 'fa-clock', 'start' => '#aa00ff', 'end' => '#c24dff']
                        };
                    @endphp
                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                         style="background: linear-gradient(135deg, {{ $activityIcon['start'] }}, {{ $activityIcon['end'] }}); color: white;">
                        <i class="fas {{ $activityIcon['icon'] }}"></i>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-medium" style="color: var(--text-primary);">{{ $activity['name'] }}</span>
                                <span class="mx-2" style="color: var(--text-secondary);">•</span>
                                <span class="text-sm" style="color: var(--text-secondary);">{{ $activity['registration_type'] == 'construction' ? 'Construction' : 'Property' }}</span>
                                @if(!empty($activity['plot_number']))
                                <span class="mx-2" style="color: var(--text-secondary);">•</span>
                                <span class="text-sm" style="color: var(--text-secondary);">Plot {{ $activity['plot_number'] }}</span>
                                @endif
                            </div>
                            <span class="text-sm" style="color: var(--text-secondary);">{{ \Carbon\Carbon::parse($activity['reviewed_at'])->diffForHumans() }}</span>
                        </div>
                        <div class="mt-2 flex items-center">
                            <span class="text-sm" style="color: var(--text-secondary);">Status changed to </span>
                            <span class="mx-1 px-2 py-0.5 rounded-full text-xs font-medium"
                                  style="background: linear-gradient(135deg, {{ $activityIcon['start'] }}, {{ $activityIcon['end'] }}); color: white;">
                                {{ ucfirst(str_replace('_', ' ', $activity['status'])) }}
                            </span>
                            <span class="text-sm" style="color: var(--text-secondary);"> by {{ $activity['reviewer_name'] ?? 'System' }}</span>
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center py-8">
                    <i class="fas fa-history text-4xl mb-3" style="color: #ffaa00; opacity: 0.5;"></i>
                    <p style="color: var(--text-secondary);">No recent activity</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- Export Report Modal -->
<div id="exportReportModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('exportReportModal')"></div>
    <div class="modal-container" style="max-width: 500px; border-top: 4px solid #ff0080;">
        <div class="modal-header" style="background: linear-gradient(135deg, rgba(255,0,128,0.05) 0%, rgba(255,170,0,0.05) 100%);">
            <h3 class="modal-title" style="color: #ff0080;">Export Statistics Report</h3>
            <button type="button" class="modal-close" onclick="closeModal('exportReportModal')">
                <i class="fas fa-times" style="color: #ff0080;"></i>
            </button>
        </div>
        <form action="{{ route('admin.construction-registrations.export-stats') }}" method="GET">
            <div class="modal-body">
                <div class="space-y-4">
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Report Type</label>
                        <select name="report_type" class="form-input w-full p-3 rounded-lg border" style="border-color: #ff0080;" required>
                            <option value="summary">Summary Report</option>
                            <option value="detailed">Detailed Report</option>
                            <option value="performance">Admin Performance Report</option>
                            <option value="trends">Monthly Trends Report</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Date Range</label>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="date" name="date_from" class="form-input p-3 rounded-lg border" style="border-color: #00cc88;"
                                   value="{{ now()->subMonths(12)->format('Y-m-d') }}" required>
                            <input type="date" name="date_to" class="form-input p-3 rounded-lg border" style="border-color: #00cc88;"
                                   value="{{ now()->format('Y-m-d') }}" required>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Registration Type</label>
                        <select name="registration_type" class="form-input w-full p-3 rounded-lg border" style="border-color: #0066ff;" required>
                            <option value="all">All Registrations</option>
                            <option value="construction">Construction Only</option>
                            <option value="property_capture">Property Capture Only</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Format</label>
                        <div class="flex space-x-2">
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="format" value="csv" checked class="hidden peer">
                                <div class="p-3 border rounded-lg text-center export-format-option peer-checked:border-primary peer-checked:bg-primary/10"
                                     style="background: linear-gradient(135deg, rgba(0,204,136,0.1) 0%, rgba(51,221,170,0.1) 100%); border-color: #00cc88;">
                                    <i class="fas fa-file-csv text-2xl mb-2" style="color: #00cc88;"></i>
                                    <div class="font-medium" style="color: #00cc88;">CSV</div>
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="format" value="pdf" class="hidden peer">
                                <div class="p-3 border rounded-lg text-center export-format-option peer-checked:border-primary peer-checked:bg-primary/10"
                                     style="background: linear-gradient(135deg, rgba(255,51,0,0.1) 0%, rgba(255,102,51,0.1) 100%); border-color: #ff3300;">
                                    <i class="fas fa-file-pdf text-2xl mb-2" style="color: #ff3300;"></i>
                                    <div class="font-medium" style="color: #ff3300;">PDF</div>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Include</label>
                        <div class="space-y-2">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" name="include_charts" value="1" class="mr-2 rounded" style="border-color: #ff0080;">
                                <span style="color: var(--text-secondary);">Include charts and visualizations</span>
                            </label>
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" name="include_trends" value="1" class="mr-2 rounded" style="border-color: #00cc88;" checked>
                                <span style="color: var(--text-secondary);">Include trend analysis</span>
                            </label>
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" name="include_admin_stats" value="1" class="mr-2 rounded" style="border-color: #0066ff;" checked>
                                <span style="color: var(--text-secondary);">Include admin performance stats</span>
                            </label>
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" name="include_zone_stats" value="1" class="mr-2 rounded" style="border-color: #aa00ff;" checked>
                                <span style="color: var(--text-secondary);">Include zone/section stats</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer flex justify-end space-x-2 p-4 border-t" style="border-color: var(--border-color);">
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background: linear-gradient(135deg, rgba(255,51,0,0.1) 0%, rgba(255,102,51,0.1) 100%); color: #ff3300; border: 1px solid #ff3300;"
                        onclick="closeModal('exportReportModal')">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                        style="background: linear-gradient(135deg, #00cc88 0%, #33ddaa 100%);">
                    <i class="fas fa-download mr-2"></i> Export
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Festive Theme Colors 🎉
const festiveColors = {
    pink: { start: '#ff0080', end: '#ff4da6' },
    green: { start: '#00cc88', end: '#33ddaa' },
    blue: { start: '#0066ff', end: '#4d94ff' },
    yellow: { start: '#ffaa00', end: '#ffbb33' },
    orange: { start: '#ff3300', end: '#ff6633' },
    purple: { start: '#aa00ff', end: '#c24dff' },
    cyan: { start: '#00ffcc', end: '#4dffdb' }
};

// Initialize charts
document.addEventListener('DOMContentLoaded', function() {
    // Monthly Trends Chart
    const monthlyCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
    const monthlyData = @json($stats['monthly_stats'] ?? []);
    
    new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: monthlyData.map(item => {
                const date = new Date(item.year, item.month - 1);
                return date.toLocaleString('default', { month: 'short', year: 'numeric' });
            }),
            datasets: [
                {
                    label: 'Total',
                    data: monthlyData.map(item => item.total),
                    borderColor: festiveColors.pink.start,
                    backgroundColor: 'rgba(255,0,128,0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: festiveColors.pink.start,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Approved',
                    data: monthlyData.map(item => item.approved),
                    borderColor: festiveColors.green.start,
                    backgroundColor: 'rgba(0,204,136,0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: festiveColors.green.start,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Pending',
                    data: monthlyData.map(item => item.pending),
                    borderColor: festiveColors.yellow.start,
                    backgroundColor: 'rgba(255,170,0,0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: festiveColors.yellow.start,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: 'var(--chart-legend)',
                        font: { weight: '500', size: 12 },
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    titleColor: '#fff',
                    bodyColor: '#e5e7eb',
                    backgroundColor: '#1f2937',
                    borderColor: festiveColors.pink.start,
                    borderWidth: 2,
                    padding: 12,
                    titleFont: { weight: '600', size: 14 },
                    bodyFont: { size: 13 }
                }
            },
            scales: {
                x: {
                    grid: { color: 'var(--border-color)' },
                    ticks: { color: 'var(--chart-axis)', font: { size: 11, weight: '500' } }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'var(--border-color)' },
                    ticks: { 
                        color: 'var(--chart-axis)', 
                        stepSize: 1,
                        font: { size: 11, weight: '500' },
                        callback: function(value) { return Number.isInteger(value) ? value : null; }
                    }
                }
            }
        }
    });

    // Status Distribution Chart - Festive Theme
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusData = @json($stats['by_status'] ?? []);
    
    // Create gradients
    const gradientPending = statusCtx.createLinearGradient(0, 0, 0, 400);
    gradientPending.addColorStop(0, festiveColors.yellow.start);
    gradientPending.addColorStop(1, festiveColors.yellow.end);
    
    const gradientInReview = statusCtx.createLinearGradient(0, 0, 0, 400);
    gradientInReview.addColorStop(0, festiveColors.blue.start);
    gradientInReview.addColorStop(1, festiveColors.blue.end);
    
    const gradientApproved = statusCtx.createLinearGradient(0, 0, 0, 400);
    gradientApproved.addColorStop(0, festiveColors.green.start);
    gradientApproved.addColorStop(1, festiveColors.green.end);
    
    const gradientRejected = statusCtx.createLinearGradient(0, 0, 0, 400);
    gradientRejected.addColorStop(0, festiveColors.orange.start);
    gradientRejected.addColorStop(1, festiveColors.orange.end);
    
    const gradientNeedsInfo = statusCtx.createLinearGradient(0, 0, 0, 400);
    gradientNeedsInfo.addColorStop(0, festiveColors.pink.start);
    gradientNeedsInfo.addColorStop(1, festiveColors.pink.end);
    
    const gradientCancelled = statusCtx.createLinearGradient(0, 0, 0, 400);
    gradientCancelled.addColorStop(0, festiveColors.purple.start);
    gradientCancelled.addColorStop(1, festiveColors.purple.end);
    
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'In Review', 'Approved', 'Rejected', 'Needs Info', 'Cancelled'],
            datasets: [{
                data: [
                    statusData.pending || 0,
                    statusData.in_review || 0,
                    statusData.approved || 0,
                    statusData.rejected || 0,
                    statusData.needs_info || 0,
                    statusData.cancelled || 0
                ],
                backgroundColor: [
                    gradientPending,
                    gradientInReview,
                    gradientApproved,
                    gradientRejected,
                    gradientNeedsInfo,
                    gradientCancelled
                ],
                borderColor: 'var(--card-bg)',
                borderWidth: 3,
                hoverOffset: 8,
                spacing: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: 'var(--chart-legend)',
                        padding: 20,
                        font: { weight: '500', size: 12 },
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    titleColor: '#fff',
                    bodyColor: '#e5e7eb',
                    backgroundColor: '#1f2937',
                    borderColor: festiveColors.pink.start,
                    borderWidth: 2,
                    padding: 12,
                    titleFont: { weight: '600', size: 14 },
                    bodyFont: { size: 13 },
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            },
            cutout: '65%',
            animation: { animateScale: true, animateRotate: true }
        }
    });

    // Registration Type Chart
    const regTypeCtx = document.getElementById('registrationTypeChart').getContext('2d');
    const typeData = @json($stats['by_type'] ?? []);
    
    const gradientConstruction = regTypeCtx.createLinearGradient(0, 0, 0, 400);
    gradientConstruction.addColorStop(0, festiveColors.blue.start);
    gradientConstruction.addColorStop(1, festiveColors.blue.end);
    
    const gradientProperty = regTypeCtx.createLinearGradient(0, 0, 0, 400);
    gradientProperty.addColorStop(0, festiveColors.pink.start);
    gradientProperty.addColorStop(1, festiveColors.pink.end);
    
    new Chart(regTypeCtx, {
        type: 'bar',
        data: {
            labels: ['Construction', 'Property Capture'],
            datasets: [{
                label: 'Number of Registrations',
                data: [typeData.construction || 0, typeData.property_capture || 0],
                backgroundColor: [gradientConstruction, gradientProperty],
                borderRadius: 8,
                barPercentage: 0.6,
                categoryPercentage: 0.8,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    titleColor: '#fff',
                    bodyColor: '#e5e7eb',
                    backgroundColor: '#1f2937',
                    borderColor: festiveColors.pink.start,
                    borderWidth: 2,
                    padding: 12,
                    titleFont: { weight: '600', size: 14 },
                    bodyFont: { size: 13 }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: 'var(--chart-axis)', font: { size: 12, weight: '500' } }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'var(--border-color)' },
                    ticks: { color: 'var(--chart-axis)', stepSize: 1, font: { size: 11, weight: '500' } }
                }
            },
            animation: { duration: 2000, easing: 'easeInOutQuart' }
        }
    });

    // Property Type Chart (if exists)
    const propertyTypeCtx = document.getElementById('propertyTypeChart');
    if (propertyTypeCtx) {
        const ctx = propertyTypeCtx.getContext('2d');
        const propertyTypeData = @json($stats['property_type_stats'] ?? []);
        const propertyLabels = Object.keys(propertyTypeData).map(key => 
            key ? ucfirst(key.replace(/_/g, ' ')) : 'Not Specified'
        );
        
        // Create gradients array
        const gradientColors = [
            [festiveColors.pink.start, festiveColors.pink.end],
            [festiveColors.green.start, festiveColors.green.end],
            [festiveColors.blue.start, festiveColors.blue.end],
            [festiveColors.yellow.start, festiveColors.yellow.end],
            [festiveColors.orange.start, festiveColors.orange.end],
            [festiveColors.purple.start, festiveColors.purple.end]
        ];
        
        const gradients = propertyLabels.map((_, index) => {
            const gradient = ctx.createLinearGradient(0, 0, 0, 400);
            const colors = gradientColors[index % gradientColors.length];
            gradient.addColorStop(0, colors[0]);
            gradient.addColorStop(1, colors[1]);
            return gradient;
        });
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: propertyLabels,
                datasets: [{
                    label: 'Number of Registrations',
                    data: Object.values(propertyTypeData),
                    backgroundColor: gradients,
                    borderRadius: 8,
                    barPercentage: 0.7,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        titleColor: '#fff',
                        bodyColor: '#e5e7eb',
                        backgroundColor: '#1f2937',
                        borderColor: festiveColors.pink.start,
                        borderWidth: 2,
                        padding: 12,
                        titleFont: { weight: '600', size: 14 },
                        bodyFont: { size: 13 }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { 
                            color: 'var(--chart-axis)', 
                            font: { size: 11, weight: '500' },
                            maxRotation: 45,
                            minRotation: 45
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'var(--border-color)' },
                        ticks: { color: 'var(--chart-axis)', stepSize: 1, font: { size: 11, weight: '500' } }
                    }
                },
                animation: { duration: 1500, easing: 'easeInOutQuart' }
            }
        });
    }

    // Existing Property Type Chart (if exists)
    const existingTypeCtx = document.getElementById('existingPropertyTypeChart');
    if (existingTypeCtx) {
        const ctx = existingTypeCtx.getContext('2d');
        const existingTypeData = @json($stats['existing_property_type_stats'] ?? []);
        const existingLabels = Object.keys(existingTypeData).map(key => 
            key ? ucfirst(key.replace(/_/g, ' ')) : 'Not Specified'
        );
        
        const gradientColors = [
            [festiveColors.pink.start, festiveColors.pink.end],
            [festiveColors.green.start, festiveColors.green.end],
            [festiveColors.blue.start, festiveColors.blue.end],
            [festiveColors.yellow.start, festiveColors.yellow.end],
            [festiveColors.orange.start, festiveColors.orange.end],
            [festiveColors.purple.start, festiveColors.purple.end]
        ];
        
        const gradients = existingLabels.map((_, index) => {
            const gradient = ctx.createLinearGradient(0, 0, 0, 400);
            const colors = gradientColors[index % gradientColors.length];
            gradient.addColorStop(0, colors[0]);
            gradient.addColorStop(1, colors[1]);
            return gradient;
        });
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: existingLabels,
                datasets: [{
                    label: 'Number of Registrations',
                    data: Object.values(existingTypeData),
                    backgroundColor: gradients,
                    borderRadius: 8,
                    barPercentage: 0.7,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        titleColor: '#fff',
                        bodyColor: '#e5e7eb',
                        backgroundColor: '#1f2937',
                        borderColor: festiveColors.pink.start,
                        borderWidth: 2,
                        padding: 12,
                        titleFont: { weight: '600', size: 14 },
                        bodyFont: { size: 13 }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { 
                            color: 'var(--chart-axis)', 
                            font: { size: 11, weight: '500' },
                            maxRotation: 45,
                            minRotation: 45
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'var(--border-color)' },
                        ticks: { color: 'var(--chart-axis)', stepSize: 1, font: { size: 11, weight: '500' } }
                    }
                },
                animation: { duration: 1500, easing: 'easeInOutQuart' }
            }
        });
    }
});

// Helper function to capitalize first letter
function ucfirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function exportStats() {
    openModal('exportReportModal');
}

function openModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Export format selection
document.addEventListener('DOMContentLoaded', function() {
    // Style the checked radio button on load
    document.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
        const option = radio.closest('label')?.querySelector('.export-format-option');
        if (option) {
            option.style.borderColor = radio.value === 'csv' ? '#00cc88' : '#ff3300';
            option.style.background = radio.value === 'csv' 
                ? 'linear-gradient(135deg, rgba(0,204,136,0.2) 0%, rgba(51,221,170,0.2) 100%)'
                : 'linear-gradient(135deg, rgba(255,51,0,0.2) 0%, rgba(255,102,51,0.2) 100%)';
        }
    });

    // Handle radio button click
    document.querySelectorAll('.export-format-option').forEach(option => {
        option.addEventListener('click', function() {
            const label = this.closest('label');
            const radio = label.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
                
                // Reset all options
                document.querySelectorAll('.export-format-option').forEach(opt => {
                    opt.style.borderColor = 'var(--border-color)';
                    opt.style.background = 'var(--bg-secondary)';
                });
                
                // Highlight selected option
                this.style.borderColor = radio.value === 'csv' ? '#00cc88' : '#ff3300';
                this.style.background = radio.value === 'csv'
                    ? 'linear-gradient(135deg, rgba(0,204,136,0.2) 0%, rgba(51,221,170,0.2) 100%)'
                    : 'linear-gradient(135deg, rgba(255,51,0,0.2) 0%, rgba(255,102,51,0.2) 100%)';
            }
        });
    });

    // Close modal on overlay click
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            const modal = e.target.closest('.modal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        }
    });

    // Close modal with Escape key
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
});
</script>

<style>
/* Chart container */
canvas {
    max-height: 300px;
    width: 100% !important;
}

/* Export format option */
.export-format-option {
    transition: all 0.2s ease;
}

.export-format-option:hover {
    transform: translateY(-2px);
    filter: brightness(1.1);
}

/* Card hover effects */
.card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid var(--border-color);
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1) !important;
}

/* Progress bars */
.progress-bar {
    transition: width 0.3s ease;
}

/* Chart text colors */
:root {
    --chart-axis: var(--text-secondary);
    --chart-legend: var(--text-primary);
    --chart-text: var(--text-primary);
    
    /* RGB values for opacity effects - Festive Theme */
    --primary-rgb: 255, 0, 128;
    --secondary-rgb: 170, 0, 255;
    --success-rgb: 0, 204, 136;
    --danger-rgb: 255, 51, 0;
    --warning-rgb: 255, 170, 0;
    --info-rgb: 0, 102, 255;
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: var(--bg-secondary);
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #ff0080, #ffaa00);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #ff4da6, #ffbb33);
}

/* Responsive */
@media (max-width: 768px) {
    .modal-container {
        margin: 1rem;
    }
    
    canvas {
        max-height: 250px;
    }
}

/* Modal styles */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal.hidden {
    display: none;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-container {
    position: relative;
    width: 100%;
    max-width: 500px;
    background-color: var(--card-bg);
    border-radius: 12px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    z-index: 1001;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 9999px;
    transition: background-color 0.2s;
}

.modal-close:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

/* Form inputs */
.form-input {
    background-color: var(--input-bg);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.form-input:focus {
    outline: none;
    border-color: #ff0080;
    box-shadow: 0 0 0 3px rgba(255, 0, 128, 0.1);
}

/* Button styles */
.btn-primary {
    background: linear-gradient(135deg, #ff0080, #ffaa00);
    color: white;
    border: none;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(255, 0, 128, 0.3);
}
</style>
@endsection