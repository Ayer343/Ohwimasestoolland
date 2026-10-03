@extends('layouts.secu')

@section('title', 'Pending Approvals')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Welcome Header -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-clipboard-check text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-hourglass-half mr-2" style="color: var(--warning);"></i>
                        Pending Approvals
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>{{ auth()->user()->name }} - {{ auth()->user()->badge_number ?? 'No Badge' }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-bell mr-1"></i>
                        <span>{{ array_sum($pending) }} items requiring attention</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('security.supervisor.assignments.current') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-eye mr-2"></i> My Assignments
                </a>
                <a href="{{ route('security.supervisor.dashboard') }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium text-white inline-flex items-center btn-primary">
                    <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Swap Requests -->
        <div class="card p-6 hover:shadow-lg transition-shadow duration-300">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-exchange-alt text-xl" style="color: var(--info);"></i>
                </div>
                <span class="text-3xl font-bold" style="color: var(--text-primary);">{{ $pending['swap_requests'] }}</span>
            </div>
            <h3 class="font-semibold mb-1" style="color: var(--text-primary);">Swap Requests</h3>
            <p class="text-sm" style="color: var(--text-secondary);">Pending shift swap approvals</p>
            @if($pending['swap_requests'] > 0)
                <div class="mt-4">
                    <a href="#swap-requests" class="text-sm font-medium" style="color: var(--info);">
                        Review Now <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            @endif
        </div>

        <!-- Overtime Requests -->
        <div class="card p-6 hover:shadow-lg transition-shadow duration-300">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-clock text-xl" style="color: var(--warning);"></i>
                </div>
                <span class="text-3xl font-bold" style="color: var(--text-primary);">{{ $pending['overtime_requests'] }}</span>
            </div>
            <h3 class="font-semibold mb-1" style="color: var(--text-primary);">Overtime Requests</h3>
            <p class="text-sm" style="color: var(--text-secondary);">Pending overtime approvals</p>
            @if($pending['overtime_requests'] > 0)
                <div class="mt-4">
                    <a href="#overtime-requests" class="text-sm font-medium" style="color: var(--warning);">
                        Review Now <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            @endif
        </div>

        <!-- Pending Verifications -->
        <div class="card p-6 hover:shadow-lg transition-shadow duration-300">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-fingerprint text-xl" style="color: var(--success);"></i>
                </div>
                <span class="text-3xl font-bold" style="color: var(--text-primary);">{{ $pending['pending_verifications'] }}</span>
            </div>
            <h3 class="font-semibold mb-1" style="color: var(--text-primary);">Pending Verifications</h3>
            <p class="text-sm" style="color: var(--text-secondary);">Today's check-ins to verify</p>
            @if($pending['pending_verifications'] > 0)
                <div class="mt-4">
                    <a href="#verifications" class="text-sm font-medium" style="color: var(--success);">
                        Verify Now <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            @endif
        </div>

        <!-- Total Pending -->
        <div class="card p-6 hover:shadow-lg transition-shadow duration-300" style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-full flex items-center justify-center bg-white bg-opacity-20">
                    <i class="fas fa-bell text-xl text-white"></i>
                </div>
                <span class="text-3xl font-bold text-white">{{ array_sum($pending) }}</span>
            </div>
            <h3 class="font-semibold mb-1 text-white">Total Pending</h3>
            <p class="text-sm text-white text-opacity-90">Items requiring your attention</p>
        </div>
    </div>

    <!-- Swap Requests Section -->
    @if($pending['swap_requests'] > 0)
    <div id="swap-requests" class="card mt-6">
        <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-exchange-alt mr-2" style="color: var(--info);"></i>
                Shift Swap Requests
                <span class="ml-3 px-2.5 py-1 text-xs rounded-full badge-info">{{ $pending['swap_requests'] }} pending</span>
            </h3>
            <a href="#" class="text-sm" style="color: var(--info);">
                View All <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Employee</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Post</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Date</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Shift</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Requested With</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Reason</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($swapRequests ?? [] as $request)
                        <tr class="border-b hover:bg-opacity-50 transition-colors duration-200" 
                            style="border-color: var(--border-color); background-color: var(--card-bg);">
                            <td class="py-4 px-6">
                                <div class="font-medium" style="color: var(--text-primary);">{{ $request->employee->name }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">{{ $request->employee->badge_number }}</div>
                            </td>
                            <td class="py-4 px-6" style="color: var(--text-primary);">{{ $request->post->name }}</td>
                            <td class="py-4 px-6">
                                <div style="color: var(--text-primary);">{{ $request->schedule->assignment_date->format('M j, Y') }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">{{ $request->schedule->assignment_date->format('l') }}</div>
                            </td>
                            <td class="py-4 px-6">
                                <span class="px-2 py-1 text-xs rounded-full badge-info">{{ $request->shift->name }}</span>
                            </td>
                            <td class="py-4 px-6">
                                <div class="font-medium" style="color: var(--text-primary);">{{ $request->swapWith->name }}</div>
                            </td>
                            <td class="py-4 px-6">
                                <div class="max-w-xs truncate" style="color: var(--text-secondary);" title="{{ $request->reason }}">
                                    {{ $request->reason }}
                                </div>
                            </td>
                            <td class="py-4 px-6">
                                <div class="flex space-x-2">
                                    <button onclick="approveSwap({{ $request->id }})" 
                                            class="px-3 py-1 rounded text-xs font-medium" style="background-color: var(--success); color: white;">
                                        Approve
                                    </button>
                                    <button onclick="rejectSwap({{ $request->id }})" 
                                            class="px-3 py-1 rounded text-xs font-medium" style="background-color: var(--danger); color: white;">
                                        Reject
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Overtime Requests Section -->
    @if($pending['overtime_requests'] > 0)
    <div id="overtime-requests" class="card mt-6">
        <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-clock mr-2" style="color: var(--warning);"></i>
                Overtime Requests
                <span class="ml-3 px-2.5 py-1 text-xs rounded-full badge-warning">{{ $pending['overtime_requests'] }} pending</span>
            </h3>
            <a href="#" class="text-sm" style="color: var(--warning);">
                View All <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Employee</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Post</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Date</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Shift</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Requested OT</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Reason</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($overtimeRequests ?? [] as $request)
                        <tr class="border-b hover:bg-opacity-50 transition-colors duration-200" 
                            style="border-color: var(--border-color); background-color: var(--card-bg);">
                            <td class="py-4 px-6">
                                <div class="font-medium" style="color: var(--text-primary);">{{ $request->employee->name }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">{{ $request->employee->badge_number }}</div>
                            </td>
                            <td class="py-4 px-6" style="color: var(--text-primary);">{{ $request->post->name }}</td>
                            <td class="py-4 px-6">
                                <div style="color: var(--text-primary);">{{ $request->schedule->assignment_date->format('M j, Y') }}</div>
                            </td>
                            <td class="py-4 px-6">
                                <span class="px-2 py-1 text-xs rounded-full badge-info">{{ $request->shift->name }}</span>
                            </td>
                            <td class="py-4 px-6">
                                <span class="font-medium" style="color: var(--warning);">{{ $request->requested_minutes }} minutes</span>
                            </td>
                            <td class="py-4 px-6">
                                <div class="max-w-xs truncate" style="color: var(--text-secondary);">{{ $request->reason }}</div>
                            </td>
                            <td class="py-4 px-6">
                                <div class="flex space-x-2">
                                    <button onclick="approveOvertime({{ $request->id }})" 
                                            class="px-3 py-1 rounded text-xs font-medium" style="background-color: var(--success); color: white;">
                                        Approve
                                    </button>
                                    <button onclick="rejectOvertime({{ $request->id }})" 
                                            class="px-3 py-1 rounded text-xs font-medium" style="background-color: var(--danger); color: white;">
                                        Reject
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Verifications Section -->
    @if($pending['pending_verifications'] > 0)
    <div id="verifications" class="card mt-6">
        <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-fingerprint mr-2" style="color: var(--success);"></i>
                Pending Check-in Verifications
                <span class="ml-3 px-2.5 py-1 text-xs rounded-full badge-success">{{ $pending['pending_verifications'] }} pending</span>
            </h3>
            <a href="#" class="text-sm" style="color: var(--success);">
                View All <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Employee</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Post</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Check-in Time</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Method</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Location</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($verifications ?? [] as $verification)
                        <tr class="border-b hover:bg-opacity-50 transition-colors duration-200" 
                            style="border-color: var(--border-color); background-color: var(--card-bg);">
                            <td class="py-4 px-6">
                                <div class="font-medium" style="color: var(--text-primary);">{{ $verification->user->name }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">{{ $verification->user->badge_number }}</div>
                            </td>
                            <td class="py-4 px-6" style="color: var(--text-primary);">{{ $verification->post->name }}</td>
                            <td class="py-4 px-6">
                                <div style="color: var(--text-primary);">{{ $verification->checkin_time->format('H:i') }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">{{ $verification->checkin_time->format('M j') }}</div>
                            </td>
                            <td class="py-4 px-6">
                                @php
                                    $methodColors = [
                                        'gps' => 'info',
                                        'qr' => 'primary',
                                        'face' => 'success',
                                        'manual' => 'warning'
                                    ];
                                    $methodColor = $methodColors[$verification->method] ?? 'secondary';
                                @endphp
                                <span class="px-2 py-1 text-xs rounded-full badge-{{ $methodColor }}">
                                    {{ ucfirst($verification->method) }}
                                </span>
                            </td>
                            <td class="py-4 px-6">
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    @if($verification->latitude && $verification->longitude)
                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                        {{ substr($verification->latitude, 0, 8) }}, {{ substr($verification->longitude, 0, 8) }}
                                    @else
                                        Not available
                                    @endif
                                </div>
                            </td>
                            <td class="py-4 px-6">
                                <div class="flex space-x-2">
                                    <button onclick="verifyCheckin({{ $verification->id }})" 
                                            class="px-3 py-1 rounded text-xs font-medium" style="background-color: var(--success); color: white;">
                                        <i class="fas fa-check mr-1"></i> Verify
                                    </button>
                                    <button onclick="viewVerification({{ $verification->id }})" 
                                            class="px-3 py-1 rounded text-xs font-medium" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                        <i class="fas fa-eye mr-1"></i> Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- No Pending Items -->
    @if(array_sum($pending) == 0)
    <div class="card mt-6">
        <div class="p-12 text-center">
            <div class="w-24 h-24 mx-auto mb-6 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                <i class="fas fa-check-circle text-4xl" style="color: var(--success);"></i>
            </div>
            <h3 class="text-xl font-semibold mb-3" style="color: var(--text-primary);">All Caught Up!</h3>
            <p class="mb-6" style="color: var(--text-secondary);">You have no pending approvals at the moment.</p>
            <div class="flex justify-center space-x-4">
                <a href="{{ route('security.supervisor.team.today') }}" 
                   class="px-6 py-3 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-users mr-2"></i> View Today's Team
                </a>
                <a href="{{ route('security.supervisor.dashboard') }}" 
                   class="px-6 py-3 rounded-lg text-sm font-medium text-white inline-flex items-center btn-primary">
                    <i class="fas fa-tachometer-alt mr-2"></i> Go to Dashboard
                </a>
            </div>
        </div>
    </div>
    @endif
</div>

<!-- Action Modals -->
<div id="approveModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeModal('approveModal')"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full" 
             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);" id="modalTitle">
                    <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                    Approve Request
                </h3>
            </div>
            
            <div class="p-6">
                <form id="approveForm" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Notes (Optional)</label>
                        <textarea name="notes" rows="3" class="w-full rounded-lg px-4 py-2" 
                                  style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);"
                                  placeholder="Add any notes about this approval..."></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('approveModal')"
                                class="px-4 py-2 rounded-lg text-sm font-medium"
                                style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 rounded-lg text-sm font-medium text-white"
                                style="background-color: var(--success);">
                            Confirm Approval
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let currentRequestId = null;
let currentAction = null;

function approveSwap(requestId) {
    currentRequestId = requestId;
    currentAction = 'swap';
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Approve Swap Request';
    document.getElementById('approveForm').action = `{{ url('security/supervisor/actions/approve-swap') }}/${requestId}`;
    document.getElementById('approveModal').classList.remove('hidden');
}

function rejectSwap(requestId) {
    if(confirm('Are you sure you want to reject this swap request?')) {
        fetch(`{{ url('security/supervisor/actions/approve-swap') }}/${requestId}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ status: 'rejected' })
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alert('Swap request rejected');
                window.location.reload();
            } else {
                alert(data.message || 'Failed to reject request');
            }
        });
    }
}

function approveOvertime(requestId) {
    currentRequestId = requestId;
    currentAction = 'overtime';
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Approve Overtime Request';
    document.getElementById('approveForm').action = `{{ url('security/supervisor/actions/approve-overtime') }}/${requestId}`;
    document.getElementById('approveModal').classList.remove('hidden');
}

function rejectOvertime(requestId) {
    if(confirm('Are you sure you want to reject this overtime request?')) {
        fetch(`{{ url('security/supervisor/actions/approve-overtime') }}/${requestId}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ status: 'rejected' })
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alert('Overtime request rejected');
                window.location.reload();
            } else {
                alert(data.message || 'Failed to reject request');
            }
        });
    }
}

function verifyCheckin(verificationId) {
    if(confirm('Verify this check-in?')) {
        fetch(`{{ url('security/supervisor/actions/verify-checkin') }}/${verificationId}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alert('Check-in verified successfully');
                window.location.reload();
            } else {
                alert(data.message || 'Failed to verify check-in');
            }
        });
    }
}

function viewVerification(verificationId) {
    window.location.href = `{{ url('security/supervisor/actions/verify-checkin') }}/${verificationId}`;
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('fixed')) {
        closeModal('approveModal');
    }
}
</script>

<style>
.badge-success { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3); }
.badge-warning { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3); }
.badge-info { background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3); }
.badge-primary { background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3); }
.badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3); }
</style>
@endsection