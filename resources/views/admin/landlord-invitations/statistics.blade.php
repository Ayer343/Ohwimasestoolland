@extends('layouts.app')

@section('title', 'Invitation Statistics')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Landlord Invitation Statistics</h3>
                    <div class="card-tools">
                        <a href="{{ route('landlord-invitations.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Invitations
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Summary Cards -->
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="info-box bg-gradient-primary">
                                <span class="info-box-icon"><i class="fas fa-paper-plane"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Invitations</span>
                                    <span class="info-box-number">{{ $stats['total_invitations'] }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-box bg-gradient-success">
                                <span class="info-box-icon"><i class="fas fa-check-circle"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Accepted</span>
                                    <span class="info-box-number">{{ $stats['accepted_invitations'] }}</span>
                                    <div class="progress">
                                        <div class="progress-bar" style="width: {{ $stats['total_invitations'] > 0 ? ($stats['accepted_invitations'] / $stats['total_invitations'] * 100) : 0 }}%"></div>
                                    </div>
                                    <span class="progress-description">
                                        {{ $stats['total_invitations'] > 0 ? number_format(($stats['accepted_invitations'] / $stats['total_invitations'] * 100), 1) : 0 }}% Acceptance Rate
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-box bg-gradient-warning">
                                <span class="info-box-icon"><i class="fas fa-clock"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Active/Pending</span>
                                    <span class="info-box-number">{{ $stats['active_invitations'] + $stats['pending_invitations'] }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-box bg-gradient-info">
                                <span class="info-box-icon"><i class="fas fa-key"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">With Tokens</span>
                                    <span class="info-box-number">{{ $stats['invitations_with_tokens'] }}</span>
                                    <span class="progress-description">
                                        {{ $stats['unique_tokens'] }} unique tokens
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Statistics -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Invitations by Type</h3>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Type</th>
                                                    <th>Count</th>
                                                    <th>Percentage</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($stats['invitations_by_type'] as $type => $count)
                                                    <tr>
                                                        <td>{{ ucfirst(str_replace('_', ' ', $type)) }}</td>
                                                        <td>{{ $count }}</td>
                                                        <td>
                                                            {{ $stats['total_invitations'] > 0 ? number_format(($count / $stats['total_invitations'] * 100), 1) : 0 }}%
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Performance Metrics</h3>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <tbody>
                                                <tr>
                                                    <th>Average Acceptance Time</th>
                                                    <td>
                                                        @if($stats['average_acceptance_time'])
                                                            {{ number_format($stats['average_acceptance_time'], 1) }} hours
                                                        @else
                                                            N/A
                                                        @endif
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th>Pending Invitations</th>
                                                    <td>{{ $stats['pending_invitations'] }}</td>
                                                </tr>
                                                <tr>
                                                    <th>Expired Invitations</th>
                                                    <td>{{ $stats['expired_invitations'] }}</td>
                                                </tr>
                                                <tr>
                                                    <th>Failed Invitations</th>
                                                    <td>{{ $stats['failed_invitations'] }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Distribution -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Status Distribution</h3>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <canvas id="statusChart" width="400" height="200"></canvas>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="legend-container">
                                                <h5>Legend</h5>
                                                <div class="d-flex flex-wrap">
                                                    <span class="badge badge-success mr-2 mb-2">Accepted</span>
                                                    <span class="badge badge-primary mr-2 mb-2">Sent</span>
                                                    <span class="badge badge-warning mr-2 mb-2">Pending</span>
                                                    <span class="badge badge-secondary mr-2 mb-2">Expired</span>
                                                    <span class="badge badge-danger mr-2 mb-2">Failed</span>
                                                    <span class="badge badge-dark mr-2 mb-2">Cancelled</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
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
    document.addEventListener('DOMContentLoaded', function() {
        // Status Distribution Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Accepted', 'Sent', 'Pending', 'Expired', 'Failed', 'Cancelled'],
                datasets: [{
                    data: [
                        {{ $stats['accepted_invitations'] }},
                        {{ $stats['total_invitations'] - $stats['accepted_invitations'] - $stats['pending_invitations'] - $stats['expired_invitations'] - $stats['failed_invitations'] }},
                        {{ $stats['pending_invitations'] }},
                        {{ $stats['expired_invitations'] }},
                        {{ $stats['failed_invitations'] }},
                        0 // Cancelled - you might want to add this to your stats
                    ],
                    backgroundColor: [
                        '#28a745',
                        '#007bff',
                        '#ffc107',
                        '#6c757d',
                        '#dc3545',
                        '#343a40'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    });
</script>
@endpush