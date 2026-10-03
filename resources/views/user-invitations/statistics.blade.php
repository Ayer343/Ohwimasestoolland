@extends('layouts.app')

@section('title', 'Invitation Statistics')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 text-gray-800">
            <i class="fas fa-chart-bar text-primary mr-2"></i>
            Invitation Statistics
        </h1>
        <a href="{{ route('admin.user-invitations.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to Invitations
        </a>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Invitations
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $statistics['total_invitations'] }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-envelope fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Accepted
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $statistics['accepted_invitations'] }}</div>
                            <div class="text-xs text-success mt-1">
                                {{ number_format($statistics['acceptance_rate'], 1) }}% Acceptance Rate
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Pending
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $statistics['pending_invitations'] }}</div>
                            <div class="text-xs text-warning mt-1">
                                {{ $statistics['expiring_soon'] }} expiring soon
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Average Expiry
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $statistics['average_expiry_days'] }} days</div>
                            <div class="text-xs text-info mt-1">
                                Default: 7 days
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Status Distribution -->
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-pie mr-2"></i>Status Distribution
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2">
                        <canvas id="statusChart" width="400" height="200"></canvas>
                    </div>
                    <div class="mt-4 text-center small">
                        @foreach($statistics['status_distribution'] as $status => $count)
                        <span class="mr-3">
                            <i class="fas fa-circle text-{{ $status === 'accepted' ? 'success' : ($status === 'sent' ? 'primary' : ($status === 'pending' ? 'warning' : ($status === 'expired' ? 'danger' : 'secondary'))) }}"></i>
                            {{ ucfirst($status) }} ({{ $count }})
                        </span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Type Distribution -->
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-bar mr-2"></i>Invitation Types
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-bar">
                        <canvas id="typeChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Trends -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-line mr-2"></i>Monthly Trends
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="monthlyTrendsChart" width="400" height="100"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Statistics -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-table mr-2"></i>Detailed Statistics
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="thead-light">
                                <tr>
                                    <th>Metric</th>
                                    <th>Count</th>
                                    <th>Percentage</th>
                                    <th>Trend</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Total Invitations Sent</td>
                                    <td>{{ $statistics['total_invitations'] }}</td>
                                    <td>100%</td>
                                    <td><span class="badge bg-primary">Baseline</span></td>
                                </tr>
                                <tr>
                                    <td>Successfully Accepted</td>
                                    <td>{{ $statistics['accepted_invitations'] }}</td>
                                    <td>{{ number_format($statistics['acceptance_rate'], 1) }}%</td>
                                    <td>
                                        @if($statistics['acceptance_rate'] >= 70)
                                        <span class="badge bg-success">Excellent</span>
                                        @elseif($statistics['acceptance_rate'] >= 50)
                                        <span class="badge bg-warning">Good</span>
                                        @else
                                        <span class="badge bg-danger">Needs Improvement</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td>Pending Acceptance</td>
                                    <td>{{ $statistics['pending_invitations'] }}</td>
                                    <td>{{ number_format(($statistics['pending_invitations'] / $statistics['total_invitations']) * 100, 1) }}%</td>
                                    <td>
                                        @if($statistics['pending_invitations'] > 0)
                                        <span class="badge bg-warning">Active</span>
                                        @else
                                        <span class="badge bg-secondary">None</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td>Expired Invitations</td>
                                    <td>{{ $statistics['expired_invitations'] }}</td>
                                    <td>{{ number_format(($statistics['expired_invitations'] / $statistics['total_invitations']) * 100, 1) }}%</td>
                                    <td>
                                        @if($statistics['expired_invitations'] > ($statistics['total_invitations'] * 0.3))
                                        <span class="badge bg-danger">High</span>
                                        @else
                                        <span class="badge bg-warning">Moderate</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td>Failed/Cancelled</td>
                                    <td>{{ $statistics['failed_invitations'] + $statistics['cancelled_invitations'] }}</td>
                                    <td>{{ number_format((($statistics['failed_invitations'] + $statistics['cancelled_invitations']) / $statistics['total_invitations']) * 100, 1) }}%</td>
                                    <td>
                                        @if(($statistics['failed_invitations'] + $statistics['cancelled_invitations']) > 0)
                                        <span class="badge bg-info">Needs Review</span>
                                        @else
                                        <span class="badge bg-success">Clean</span>
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    // Status Distribution Pie Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'pie',
        data: {
            labels: {!! json_encode(array_keys($statistics['status_distribution'])) !!},
            datasets: [{
                data: {!! json_encode(array_values($statistics['status_distribution'])) !!},
                backgroundColor: [
                    '#1cc88a', // accepted - green
                    '#4e73df', // sent - blue  
                    '#f6c23e', // pending - yellow
                    '#e74a3b', // expired - red
                    '#858796', // cancelled - gray
                    '#5a5c69'  // failed - dark gray
                ],
                hoverBackgroundColor: [
                    '#17a673',
                    '#2e59d9',
                    '#d4a017',
                    '#be2617',
                    '#6c757d',
                    '#4a4c55'
                ],
                hoverBorderColor: "rgba(234, 236, 244, 1)",
            }],
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            cutout: '50%',
        },
    });

    // Type Distribution Bar Chart
    const typeCtx = document.getElementById('typeChart').getContext('2d');
    const typeChart = new Chart(typeCtx, {
        type: 'bar',
        data: {
            labels: {!! json_encode(array_keys($statistics['type_distribution'])) !!},
            datasets: [{
                label: 'Invitations',
                data: {!! json_encode(array_values($statistics['type_distribution'])) !!},
                backgroundColor: '#4e73df',
                hoverBackgroundColor: '#2e59d9',
                borderColor: '#4e73df',
            }]
        },
        options: {
            maintainAspectRatio: false,
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: "rgb(234, 236, 244)",
                    }
                }
            }
        }
    });

    // Monthly Trends Chart
    const trendsCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
    const trendsChart = new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: {!! json_encode(array_keys($statistics['monthly_trends'])) !!},
            datasets: [{
                label: 'Sent',
                data: {!! json_encode(array_column($statistics['monthly_trends'], 'sent')) !!},
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.1)',
                fill: true
            }, {
                label: 'Accepted',
                data: {!! json_encode(array_column($statistics['monthly_trends'], 'accepted')) !!},
                borderColor: '#1cc88a',
                backgroundColor: 'rgba(28, 200, 138, 0.1)',
                fill: true
            }]
        },
        options: {
            maintainAspectRatio: false,
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: "rgb(234, 236, 244)",
                    }
                }
            }
        }
    });
});
</script>
@endpush