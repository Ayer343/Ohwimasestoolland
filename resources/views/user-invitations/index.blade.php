@extends('layouts.app')

@section('title', 'User Invitations')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 text-gray-800">
            <i class="fas fa-envelope-open-text text-primary mr-2"></i>
            User Invitations
        </h1>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" data-toggle="modal" data-target="#sendInvitationModal">
                <i class="fas fa-plus mr-2"></i>Send New Invitation
            </button>
            <a href="{{ route('admin.user-invitations.statistics') }}" class="btn btn-outline-secondary">
                <i class="fas fa-chart-bar mr-2"></i>Statistics
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="sent">Sent</option>
                        <option value="pending">Pending</option>
                        <option value="accepted">Accepted</option>
                        <option value="expired">Expired</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="invitation_type" class="form-label">Type</label>
                    <select name="invitation_type" id="invitation_type" class="form-select">
                        <option value="">All Types</option>
                        <option value="welcome">Welcome</option>
                        <option value="registration">Registration</option>
                        <option value="account_setup">Account Setup</option>
                        <option value="password_setup">Password Setup</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date_from" class="form-label">From Date</label>
                    <input type="date" name="date_from" id="date_from" class="form-control">
                </div>
                <div class="col-md-3">
                    <label for="date_to" class="form-label">To Date</label>
                    <input type="date" name="date_to" id="date_to" class="form-control">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter mr-2"></i>Apply Filters
                    </button>
                    <button type="reset" class="btn btn-outline-secondary">
                        <i class="fas fa-redo mr-2"></i>Reset
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Invitations
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="totalCount">0</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-envelope fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Accepted
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="acceptedCount">0</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Pending
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="pendingCount">0</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Expired
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="expiredCount">0</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Acceptance Rate
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="acceptanceRate">0%</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-percentage fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Invitations Table -->
    <div class="card shadow">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="invitationsTable" width="100%" cellspacing="0">
                    <thead class="thead-light">
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Sent</th>
                            <th>Expires</th>
                            <th>Invited By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data will be loaded via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Send Invitation Modal -->
<div class="modal fade" id="sendInvitationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send New Invitation</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="sendInvitationForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="user_id" class="form-label">Select User *</label>
                        <select name="user_id" id="user_id" class="form-select" required>
                            <option value="">Choose a user...</option>
                            <!-- Users will be populated via AJAX -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="invitation_type" class="form-label">Invitation Type *</label>
                        <select name="invitation_type" id="invitation_type" class="form-select" required>
                            <option value="welcome">Welcome Invitation</option>
                            <option value="registration">Registration Invitation</option>
                            <option value="account_setup">Account Setup</option>
                            <option value="password_setup">Password Setup</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Delivery Channels</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="channels[]" value="email" id="channel_email" checked>
                            <label class="form-check-label" for="channel_email">
                                <i class="fas fa-envelope text-primary mr-1"></i> Email
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="channels[]" value="sms" id="channel_sms">
                            <label class="form-check-label" for="channel_sms">
                                <i class="fas fa-sms text-success mr-1"></i> SMS
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="channels[]" value="whatsapp" id="channel_whatsapp">
                            <label class="form-check-label" for="channel_whatsapp">
                                <i class="fab fa-whatsapp text-success mr-1"></i> WhatsApp
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="custom_message" class="form-label">Custom Message (Optional)</label>
                        <textarea name="custom_message" id="custom_message" class="form-control" rows="3" 
                                  placeholder="Add a personalized message for the invitation..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane mr-2"></i>Send Invitation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Invitation Details Modal -->
<div class="modal fade" id="invitationDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Invitation Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="invitationDetailsContent">
                <!-- Details will be loaded via AJAX -->
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    let dataTable;

    // Initialize DataTable
    function initializeDataTable() {
        dataTable = $('#invitationsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("admin.user-invitations.index") }}',
                data: function (d) {
                    d.status = $('#status').val();
                    d.invitation_type = $('#invitation_type').val();
                    d.date_from = $('#date_from').val();
                    d.date_to = $('#date_to').val();
                }
            },
            columns: [
                { 
                    data: 'user.name',
                    name: 'user.name',
                    render: function(data, type, row) {
                        return `<div class="d-flex align-items-center">
                            <div class="avatar-sm bg-light rounded-circle d-flex align-items-center justify-content-center me-2">
                                <i class="fas fa-user text-primary"></i>
                            </div>
                            <div>
                                <div class="fw-bold">${row.user.name}</div>
                                <small class="text-muted">${row.user.type_name}</small>
                            </div>
                        </div>`;
                    }
                },
                { data: 'user.email', name: 'user.email' },
                { 
                    data: 'invitation_type',
                    name: 'invitation_type',
                    render: function(data) {
                        const types = {
                            'welcome': { class: 'bg-primary', text: 'Welcome' },
                            'registration': { class: 'bg-info', text: 'Registration' },
                            'account_setup': { class: 'bg-success', text: 'Account Setup' },
                            'password_setup': { class: 'bg-warning', text: 'Password Setup' }
                        };
                        const type = types[data] || { class: 'bg-secondary', text: data };
                        return `<span class="badge ${type.class}">${type.text}</span>`;
                    }
                },
                { 
                    data: 'status',
                    name: 'status',
                    render: function(data, type, row) {
                        const statuses = {
                            'sent': { class: 'bg-primary', text: 'Sent', icon: 'fa-paper-plane' },
                            'pending': { class: 'bg-warning', text: 'Pending', icon: 'fa-clock' },
                            'accepted': { class: 'bg-success', text: 'Accepted', icon: 'fa-check-circle' },
                            'expired': { class: 'bg-danger', text: 'Expired', icon: 'fa-exclamation-triangle' },
                            'cancelled': { class: 'bg-secondary', text: 'Cancelled', icon: 'fa-ban' },
                            'failed': { class: 'bg-dark', text: 'Failed', icon: 'fa-exclamation-circle' }
                        };
                        const status = statuses[data] || { class: 'bg-secondary', text: data, icon: 'fa-question' };
                        
                        let expiryInfo = '';
                        if (data === 'sent' || data === 'pending') {
                            const daysLeft = row.days_until_expiry;
                            if (daysLeft <= 1) {
                                expiryInfo = `<small class="d-block text-danger">Expires today</small>`;
                            } else if (daysLeft <= 3) {
                                expiryInfo = `<small class="d-block text-warning">${daysLeft} days left</small>`;
                            }
                        }
                        
                        return `<div>
                            <span class="badge ${status.class}">
                                <i class="fas ${status.icon} mr-1"></i>${status.text}
                            </span>
                            ${expiryInfo}
                        </div>`;
                    }
                },
                { 
                    data: 'sent_at',
                    name: 'sent_at',
                    render: function(data) {
                        return data ? moment(data).format('MMM D, YYYY HH:mm') : '-';
                    }
                },
                { 
                    data: 'expires_at',
                    name: 'expires_at',
                    render: function(data, type, row) {
                        const now = moment();
                        const expires = moment(data);
                        const isExpired = expires.isBefore(now);
                        
                        let badgeClass = 'bg-success';
                        if (isExpired) {
                            badgeClass = 'bg-danger';
                        } else if (expires.diff(now, 'days') <= 1) {
                            badgeClass = 'bg-warning';
                        }
                        
                        return `<div>
                            <span class="badge ${badgeClass}">${expires.format('MMM D, YYYY')}</span>
                            <div class="small text-muted">${expires.fromNow()}</div>
                        </div>`;
                    }
                },
                { 
                    data: 'invited_by_user.name',
                    name: 'invitedBy.name',
                    render: function(data, type, row) {
                        return data || 'System';
                    }
                },
                {
                    data: 'id',
                    name: 'actions',
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        let actions = `
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-info view-details" data-id="${data}" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>`;
                        
                        if (row.status === 'sent' || row.status === 'pending') {
                            actions += `
                                <button class="btn btn-warning resend-invitation" data-id="${row.id}" data-user-id="${row.user.id}" title="Resend">
                                    <i class="fas fa-redo"></i>
                                </button>
                                <button class="btn btn-danger cancel-invitation" data-id="${row.id}" title="Cancel">
                                    <i class="fas fa-ban"></i>
                                </button>`;
                        }
                        
                        if (row.status === 'expired' || row.status === 'failed' || row.status === 'cancelled') {
                            actions += `
                                <button class="btn btn-success resend-invitation" data-id="${row.id}" data-user-id="${row.user.id}" title="Resend">
                                    <i class="fas fa-paper-plane"></i>
                                </button>`;
                        }
                        
                        actions += `</div>`;
                        
                        return actions;
                    }
                }
            ],
            order: [[4, 'desc']] // Sort by sent_at descending
        });
    }

    // Load users for send invitation modal
    function loadUsers() {
        $.ajax({
            url: '{{ route("admin.users.index") }}?json=true',
            type: 'GET',
            success: function(response) {
                const userSelect = $('#user_id');
                userSelect.empty().append('<option value="">Choose a user...</option>');
                
                response.data.forEach(user => {
                    if (user.can_receive_invitation) {
                        userSelect.append(`<option value="${user.id}">${user.name} - ${user.email} (${user.type_name})</option>`);
                    }
                });
            }
        });
    }

    // Filter form handler
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        dataTable.ajax.reload();
        updateStatistics();
    });

    // Reset filters
    $('#filterForm').on('reset', function() {
        setTimeout(() => {
            dataTable.ajax.reload();
            updateStatistics();
        }, 100);
    });

    // Update statistics
    function updateStatistics() {
        $.ajax({
            url: '{{ route("admin.user-invitations.statistics") }}',
            type: 'GET',
            data: $('#filterForm').serialize(),
            success: function(response) {
                $('#totalCount').text(response.total);
                $('#acceptedCount').text(response.accepted);
                $('#pendingCount').text(response.pending);
                $('#expiredCount').text(response.expired);
                $('#acceptanceRate').text(response.acceptance_rate + '%');
            }
        });
    }

    // Send invitation form handler
    $('#sendInvitationForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        $.ajax({
            url: '{{ route("admin.user-invitations.send") }}',
            type: 'POST',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function(response) {
                $('#sendInvitationModal').modal('hide');
                showToast('success', response.message);
                dataTable.ajax.reload();
                updateStatistics();
                $('#sendInvitationForm')[0].reset();
            },
            error: function(xhr) {
                showToast('error', xhr.responseJSON?.message || 'Failed to send invitation');
            }
        });
    });

    // View invitation details
    $(document).on('click', '.view-details', function() {
        const invitationId = $(this).data('id');
        
        $.ajax({
            url: `{{ url('admin/user-invitations') }}/${invitationId}`,
            type: 'GET',
            success: function(response) {
                $('#invitationDetailsContent').html(response.html);
                $('#invitationDetailsModal').modal('show');
            }
        });
    });

    // Resend invitation
    $(document).on('click', '.resend-invitation', function() {
        const invitationId = $(this).data('id');
        const userId = $(this).data('user-id');
        
        if (!confirm('Are you sure you want to resend this invitation?')) return;
        
        $.ajax({
            url: `{{ url('admin/user-invitations') }}/${userId}/resend`,
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function(response) {
                showToast('success', response.message);
                dataTable.ajax.reload();
                updateStatistics();
            },
            error: function(xhr) {
                showToast('error', xhr.responseJSON?.message || 'Failed to resend invitation');
            }
        });
    });

    // Cancel invitation
    $(document).on('click', '.cancel-invitation', function() {
        const invitationId = $(this).data('id');
        
        if (!confirm('Are you sure you want to cancel this invitation? This action cannot be undone.')) return;
        
        $.ajax({
            url: `{{ url('admin/user-invitations') }}/${invitationId}/cancel`,
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function(response) {
                showToast('success', response.message);
                dataTable.ajax.reload();
                updateStatistics();
            },
            error: function(xhr) {
                showToast('error', xhr.responseJSON?.message || 'Failed to cancel invitation');
            }
        });
    });

    // Initialize when modal opens
    $('#sendInvitationModal').on('show.bs.modal', function() {
        loadUsers();
    });

    // Toast notification function
    function showToast(type, message) {
        // Implement your toast notification here
        alert(`${type.toUpperCase()}: ${message}`);
    }

    // Initialize everything
    initializeDataTable();
    updateStatistics();
});
</script>
@endpush