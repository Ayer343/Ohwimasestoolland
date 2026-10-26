@extends('layouts.secu')

@section('title', 'Security Personnel')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border-color: var(--primary);">
                        <i class="fas fa-user-shield text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-users mr-2" style="color: var(--primary);"></i>
                        Security Personnel
                    </h2>
                    <div class="text-sm flex items-center flex-wrap mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Submit new personnel for admin approval and manage your approved team</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-users mr-1"></i>
                        <span>{{ $personnel->total() ?? 0 }} {{ $tab === 'pending' ? 'pending' : 'personnel' }}</span>
                        <span class="mx-2">•</span>
                        <span class="px-2 py-0.5 rounded-full text-xs"
                              style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            <i class="fas fa-user-tie mr-1"></i> Head of Security
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('security.personnel.create') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-success">
                    <i class="fas fa-user-plus mr-2"></i> Submit New Personnel
                </a>
                <a href="{{ route('security.supervisor-assignments.index') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Appointments
                </a>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    @if(session('success'))
        <div class="card p-4 border-l-4"
             style="border-left-color: var(--success); background-color: rgba(var(--success-rgb), 0.05);">
            <div class="flex items-center flex-wrap gap-2">
                <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                <span style="color: var(--text-primary);">{{ session('success') }}</span>
                @if(session('invitation_result') && (session('invitation_result')['success'] ?? false))
                    <span class="ml-2 px-3 py-1 rounded text-sm"
                          style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-paper-plane mr-1"></i>
                        Invitation sent via {{ implode(', ', session('invitation_result')['channels_successful'] ?? []) }}
                    </span>
                @endif
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="card p-4 border-l-4"
             style="border-left-color: var(--danger); background-color: rgba(var(--danger-rgb), 0.05);">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2" style="color: var(--danger);"></i>
                <span style="color: var(--text-primary);">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <!-- Info Alert -->
    <div class="card p-4"
         style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.3);">
        <div class="flex items-start">
            <i class="fas fa-info-circle text-lg mr-3 mt-0.5" style="color: var(--info);"></i>
            <div>
                <h4 class="text-sm font-medium" style="color: var(--text-primary);">How personnel submission works</h4>
                <ul class="text-xs mt-1 space-y-1" style="color: var(--text-secondary);">
                    <li>
                        <i class="fas fa-arrow-right mr-1" style="color: var(--primary);"></i>
                        You submit identity details for a new officer. The account is created in <strong>pending</strong> state.
                    </li>
                    <li>
                        <i class="fas fa-arrow-right mr-1" style="color: var(--primary);"></i>
                        An administrator reviews and approves or rejects the submission.
                    </li>
                    <li>
                        <i class="fas fa-arrow-right mr-1" style="color: var(--primary);"></i>
                        Once approved, you can send the officer an invitation to activate their account.
                    </li>
                    <li>
                        <i class="fas fa-arrow-right mr-1" style="color: var(--primary);"></i>
                        Only approved personnel appear in the active tab.
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $stats['active'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Active (Approved)</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                     style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-user-check" style="color: var(--success);"></i>
                </div>
            </div>
        </div>

        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $stats['pending'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Pending Approval</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                     style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-user-clock" style="color: var(--warning);"></i>
                </div>
            </div>
        </div>

        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $stats['total'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Submitted</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                     style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-users" style="color: var(--primary);"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="card p-4">
        <div class="flex flex-wrap items-center gap-2 border-b pb-3" style="border-color: var(--border-color);">
            <a href="{{ route('security.personnel.index', array_merge(request()->except(['tab', 'page']), ['tab' => 'active'])) }}"
               class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200"
               style="{{ $tab === 'active'
                    ? 'background-color: var(--primary); color: white;'
                    : 'color: var(--text-secondary); background-color: var(--bg-secondary);' }}">
                <i class="fas fa-user-check mr-2"></i> Active Personnel
                <span class="ml-1 px-2 py-0.5 rounded-full text-xs"
                      style="{{ $tab === 'active'
                          ? 'background-color: rgba(255,255,255,0.2); color: white;'
                          : 'background-color: rgba(var(--success-rgb), 0.1); color: var(--success);' }}">
                    {{ $stats['active'] ?? 0 }}
                </span>
            </a>

            <a href="{{ route('security.personnel.index', array_merge(request()->except(['tab', 'page']), ['tab' => 'pending'])) }}"
               class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200"
               style="{{ $tab === 'pending'
                    ? 'background-color: var(--warning); color: white;'
                    : 'color: var(--text-secondary); background-color: var(--bg-secondary);' }}">
                <i class="fas fa-user-clock mr-2"></i> Pending Approval
                <span class="ml-1 px-2 py-0.5 rounded-full text-xs"
                      style="{{ $tab === 'pending'
                          ? 'background-color: rgba(255,255,255,0.2); color: white;'
                          : 'background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);' }}">
                    {{ $stats['pending'] ?? 0 }}
                </span>
            </a>

            <a href="{{ route('security.personnel.index', array_merge(request()->except(['tab', 'page']), ['tab' => 'all'])) }}"
               class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200"
               style="{{ $tab === 'all'
                    ? 'background-color: var(--info); color: white;'
                    : 'color: var(--text-secondary); background-color: var(--bg-secondary);' }}">
                <i class="fas fa-users mr-2"></i> All Personnel
                <span class="ml-1 px-2 py-0.5 rounded-full text-xs"
                      style="{{ $tab === 'all'
                          ? 'background-color: rgba(255,255,255,0.2); color: white;'
                          : 'background-color: rgba(var(--info-rgb), 0.1); color: var(--info);' }}">
                    {{ $stats['total'] ?? 0 }}
                </span>
            </a>

            <div class="flex-1"></div>

            <span class="text-xs px-3 py-1 rounded-full"
                  style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                <i class="fas fa-shield-alt mr-1"></i>
                Approvals handled by administrators
            </span>
        </div>

        @if($tab === 'pending')
            <div class="mt-3 text-xs flex items-center" style="color: var(--text-secondary);">
                <i class="fas fa-info-circle mr-2" style="color: var(--warning);"></i>
                <span>These submissions are awaiting administrator approval. Invitations can be sent once approved.</span>
            </div>
        @elseif($tab === 'active')
            <div class="mt-3 text-xs flex items-center" style="color: var(--text-secondary);">
                <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                <span>Approved officers who can be invited and operationalized.</span>
            </div>
        @elseif($tab === 'all')
            <div class="mt-3 text-xs flex items-center" style="color: var(--text-secondary);">
                <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                <span>Showing all personnel you have submitted, in every state.</span>
            </div>
        @endif
    </div>

    <!-- Filters Card -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
            <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filter Personnel
        </h3>

        <form method="GET" action="{{ route('security.personnel.index') }}"
              class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <input type="hidden" name="tab" value="{{ $tab ?? 'active' }}">

            <div>
                <label for="search" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-search mr-1" style="color: var(--primary);"></i> Search
                </label>
                <input type="text"
                       id="search"
                       name="search"
                       class="index-custom-input w-full"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                       placeholder="Search by name or email..."
                       value="{{ request('search') }}">
            </div>

            <div>
                <label for="status" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-circle mr-1" style="color: var(--primary);"></i> Status
                </label>
                <select id="status"
                        name="status"
                        class="index-custom-dropdown w-full"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="">All Status</option>
                    <option value="active"    {{ request('status') === 'active'    ? 'selected' : '' }}>Active</option>
                    <option value="pending"   {{ request('status') === 'pending'   ? 'selected' : '' }}>Pending</option>
                    <option value="inactive"  {{ request('status') === 'inactive'  ? 'selected' : '' }}>Inactive</option>
                    <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                    <option value="rejected"  {{ request('status') === 'rejected'  ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>

            <div>
                <label for="per_page" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-list mr-1" style="color: var(--primary);"></i> Per Page
                </label>
                <select id="per_page"
                        name="per_page"
                        class="index-custom-dropdown w-full"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    @foreach(\App\Http\Controllers\Security\SecurityPersonnelAssignmentController::PER_PAGE_OPTIONS as $pp)
                        <option value="{{ $pp }}" {{ (int) request('per_page', 15) === $pp ? 'selected' : '' }}>{{ $pp }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end space-x-3">
                <a href="{{ route('security.personnel.index', ['tab' => $tab ?? 'active']) }}"
                   class="btn-secondary px-4 py-2.5 rounded-lg text-sm font-medium inline-flex items-center">
                    <i class="fas fa-times mr-2"></i> Clear
                </a>
                <button type="submit"
                        class="btn-primary px-4 py-2.5 rounded-lg text-sm font-medium text-white inline-flex items-center">
                    <i class="fas fa-filter mr-2"></i> Apply
                </button>
            </div>
        </form>
    </div>

    <!-- Personnel Table -->
    <div class="card">
        <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-list mr-2" style="color: var(--primary);"></i>
                {{ $tab === 'pending' ? 'Pending Submissions' : ($tab === 'active' ? 'Active Personnel' : 'All Personnel') }}
                <span class="ml-3 px-2.5 py-1 text-xs rounded-full badge-info">
                    {{ $personnel->total() }} total
                </span>
            </h3>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-clock mr-1"></i> {{ now()->format('M j, Y') }}
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Officer</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Contact</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Submitted</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Invitation</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($personnel as $person)
                        @php
                            // ================================================================
                            // Resolve metadata
                            // ================================================================
                            $metadata = $person->metadata ?? [];
                            if (is_string($metadata)) {
                                $metadata = json_decode($metadata, true) ?? [];
                            }
                            if (!is_array($metadata)) {
                                $metadata = [];
                            }

                            $awaitingApproval = ! empty($metadata['awaiting_approval']);
                            $rejectedAt       = $metadata['rejected_at'] ?? null;
                            $rejectionReason  = $metadata['rejection_reason'] ?? null;
                            $approvedAt       = $metadata['approved_at'] ?? null;
                            $approvedByName   = $metadata['approved_by_name'] ?? null;
                            $submittedAt      = $metadata['submitted_at'] ?? $person->created_at?->toISOString();
                            $isRejected       = $rejectedAt !== null;

                            // 🔧 v2: detect admin-created records so we can label them
                            $createdVia = $metadata['created_via'] ?? null;
                            $createdByRole = $metadata['created_by_role'] ?? null;
                            $isAdminCreated = $createdVia === 'manual_by_admin'
                                || in_array($createdByRole, ['admin', 'super_admin'], true)
                                || optional($person->creator)->type === \App\Models\User::TYPE_ADMIN
                                || optional($person->creator)->type === \App\Models\User::TYPE_SUPER_ADMIN;

                            $isPendingStatus  = $person->status === \App\Models\User::STATUS_PENDING;
                            $isApprovedStatus = $person->status === \App\Models\User::STATUS_ACTIVE;

                            // Status → badge class + label
                            if ($isRejected) {
                                $statusColor = 'danger';
                                $statusLabel = 'Rejected';
                            } elseif ($awaitingApproval) {
                                $statusColor = 'warning';
                                $statusLabel = 'Awaiting Admin Approval';
                            } elseif ($isPendingStatus) {
                                $statusColor = 'warning';
                                $statusLabel = 'Pending';
                            } elseif ($isApprovedStatus) {
                                $statusColor = 'success';
                                $statusLabel = 'Active';
                            } elseif ($person->status === \App\Models\User::STATUS_SUSPENDED) {
                                $statusColor = 'danger';
                                $statusLabel = 'Suspended';
                            } else {
                                $statusColor = 'secondary';
                                $statusLabel = ucfirst($person->status ?? 'Unknown');
                            }

                            // Invitation state
                            $lastInvitation = $person->invitations()->latest()->first();
                            $invitationStatus = 'none';
                            $invitationColor  = 'secondary';
                            $invitationIcon   = 'fa-times-circle';
                            $invitationText   = 'No Invitation';
                            if ($lastInvitation) {
                                $invitationStatus = $lastInvitation->status;
                                switch ($invitationStatus) {
                                    case 'sent':     $invitationColor='info';    $invitationIcon='fa-paper-plane';       $invitationText='Sent';     break;
                                    case 'pending':  $invitationColor='warning'; $invitationIcon='fa-clock';              $invitationText='Pending';  break;
                                    case 'accepted': $invitationColor='success'; $invitationIcon='fa-check-circle';       $invitationText='Accepted'; break;
                                    case 'expired':  $invitationColor='danger';  $invitationIcon='fa-clock';              $invitationText='Expired';  break;
                                    case 'failed':   $invitationColor='danger';  $invitationIcon='fa-exclamation-circle'; $invitationText='Failed';   break;
                                    default:         $invitationColor='secondary'; $invitationIcon='fa-question-circle';  $invitationText=ucfirst($invitationStatus);
                                }
                            }

                            // 🔧 v2: invitation gating is driven by status,
                            // not just the awaiting_approval flag — admin-created
                            // pending officers have no flag.
                            $canSendInvitation = $isApprovedStatus && ! $awaitingApproval && ! $isRejected;
                            $canResend = $canSendInvitation && $lastInvitation && $invitationStatus !== 'accepted'
                                && ($invitationStatus === 'failed' || $invitationStatus === 'expired'
                                    || ($lastInvitation->created_at && $lastInvitation->created_at->diffInHours(now()) > 24));

                            $formattedPhone = $person->phone;
                            if ($person->phone && preg_match('/^\+233(\d{9})$/', $person->phone, $m)) {
                                $formattedPhone = '0' . $m[1];
                            }
                            $isPhoneVerified = ! is_null($person->phone_verified_at);
                        @endphp
                        <tr class="border-b hover:bg-opacity-50 transition-colors duration-200"
                            style="border-color: var(--border-color); background-color: var(--card-bg);">

                            <!-- Officer -->
                            <td class="py-4 px-6">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3 text-white font-semibold"
                                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                                        {{ strtoupper(substr($person->name ?? '?', 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="font-medium flex items-center flex-wrap gap-1" style="color: var(--text-primary);">
                                            {{ $person->name }}
                                            @if($isAdminCreated)
                                                <span class="ml-2 px-2 py-0.5 text-xs rounded-full"
                                                      style="background-color: rgba(var(--info-rgb), 0.15); color: var(--info);"
                                                      title="Created by an administrator">
                                                    <i class="fas fa-user-cog mr-1"></i> Admin Created
                                                </span>
                                            @endif
                                            @if($awaitingApproval)
                                                <span class="ml-2 px-2 py-0.5 text-xs rounded-full"
                                                      style="background-color: rgba(var(--warning-rgb), 0.15); color: var(--warning);">
                                                    <i class="fas fa-clock mr-1"></i> Awaiting Admin
                                                </span>
                                            @endif
                                            @if($isRejected)
                                                <span class="ml-2 px-2 py-0.5 text-xs rounded-full"
                                                      style="background-color: rgba(var(--danger-rgb), 0.15); color: var(--danger);">
                                                    <i class="fas fa-times-circle mr-1"></i> Rejected
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-id-badge mr-1"></i> ID: {{ $person->id }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Contact -->
                            <td class="py-4 px-6">
                                <div class="text-sm" style="color: var(--text-primary);">{{ $person->email }}</div>
                                <div class="flex items-center mt-1">
                                    @if($person->phone)
                                        <span class="text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-phone mr-1"></i>
                                            <span style="color: var(--text-primary);">{{ $formattedPhone }}</span>
                                        </span>
                                        @if($isPhoneVerified)
                                            <i class="fas fa-check-circle ml-1 text-xs text-success" title="Phone verified"></i>
                                        @else
                                            <i class="fas fa-exclamation-triangle ml-1 text-xs text-warning" title="Phone not verified"></i>
                                        @endif
                                    @else
                                        <span class="text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-phone mr-1"></i> No phone
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <!-- Status -->
                            <td class="py-4 px-6">
                                <span class="px-2.5 py-1.5 text-xs rounded-full badge-{{ $statusColor }}">
                                    @if($awaitingApproval)
                                        <i class="fas fa-hourglass-half mr-1"></i>
                                    @elseif($isRejected)
                                        <i class="fas fa-times mr-1"></i>
                                    @elseif($isPendingStatus)
                                        <i class="fas fa-clock mr-1"></i>
                                    @else
                                        <i class="fas fa-circle mr-1" style="font-size: 6px;"></i>
                                    @endif
                                    {{ $statusLabel }}
                                </span>

                                @if($approvedAt)
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-check mr-1" style="color: var(--success);"></i>
                                        Approved {{ \Carbon\Carbon::parse($approvedAt)->diffForHumans() }}
                                        @if($approvedByName)
                                            by {{ $approvedByName }}
                                        @endif
                                    </div>
                                @endif

                                @if($isRejected && $rejectionReason)
                                    <div class="text-xs mt-1 p-2 rounded"
                                         style="color: var(--text-secondary); background-color: rgba(var(--danger-rgb), 0.08); border-left: 2px solid var(--danger);">
                                        <strong style="color: var(--danger);">Reason:</strong> {{ $rejectionReason }}
                                    </div>
                                @endif
                            </td>

                            <!-- Submitted -->
                            <td class="py-4 px-6 text-sm" style="color: var(--text-secondary);">
                                @if($submittedAt)
                                    <i class="fas fa-calendar-alt mr-1"></i>
                                    {{ \Carbon\Carbon::parse($submittedAt)->format('M j, Y') }}
                                    <div class="text-xs">{{ \Carbon\Carbon::parse($submittedAt)->diffForHumans() }}</div>
                                @else
                                    —
                                @endif
                            </td>

                            <!-- Invitation -->
                            <td class="py-4 px-6">
                                @if($awaitingApproval || $isPendingStatus)
                                    <span class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-lock mr-1"></i> Available after approval
                                    </span>
                                @elseif($isRejected)
                                    <span class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-ban mr-1"></i> Not available
                                    </span>
                                @else
                                    <div class="flex flex-col items-start">
                                        <span class="px-2 py-1 text-xs rounded-full badge-{{ $invitationColor }} inline-flex items-center">
                                            <i class="fas {{ $invitationIcon }} mr-1"></i>
                                            {{ $invitationText }}
                                        </span>
                                        @if($lastInvitation && $lastInvitation->expires_at)
                                            <span class="text-xs mt-1" style="color: var(--text-secondary);">
                                                <i class="fas fa-clock mr-1"></i> Expires: {{ $lastInvitation->expires_at->format('M j, Y') }}
                                            </span>
                                        @endif
                                        @if($lastInvitation && $lastInvitation->created_at)
                                            <span class="text-xs" style="color: var(--text-secondary);">
                                                <i class="fas fa-calendar mr-1"></i> Sent: {{ $lastInvitation->created_at->format('M j, Y') }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="py-4 px-6">
                                <div class="flex flex-wrap gap-1">
                                    <button type="button"
                                            onclick="viewPersonnelDetails({{ $person->id }})"
                                            class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                            style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                            title="View Details">
                                        <i class="fas fa-eye text-sm"></i>
                                    </button>

                                    @if($canSendInvitation && !$lastInvitation)
                                        <button type="button"
                                                onclick="resendInvitation({{ $person->id }}, '{{ addslashes($person->name) }}')"
                                                class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                                style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                                title="Send Invitation">
                                            <i class="fas fa-paper-plane text-sm"></i>
                                        </button>
                                    @elseif($canResend)
                                        <button type="button"
                                                onclick="resendInvitation({{ $person->id }}, '{{ addslashes($person->name) }}')"
                                                class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                                style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                                title="Resend Invitation">
                                            <i class="fas fa-redo-alt text-sm"></i>
                                        </button>
                                    @endif

                                    @if($awaitingApproval || $isPendingStatus)
                                        <span class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                              style="background-color: rgba(var(--warning-rgb), 0.08); color: var(--warning); opacity: 0.6;"
                                              title="Awaiting admin approval — no actions available">
                                            <i class="fas fa-lock text-sm"></i>
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="w-20 h-20 rounded-full flex items-center justify-center mb-4"
                                         style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                        <i class="fas fa-user-shield text-3xl" style="color: var(--text-secondary);"></i>
                                    </div>
                                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                                        @if($tab === 'pending')
                                            No Pending Submissions
                                        @elseif($tab === 'active')
                                            No Active Personnel
                                        @else
                                            No Personnel Yet
                                        @endif
                                    </h4>
                                    <p class="text-sm mb-4" style="color: var(--text-secondary);">
                                        @if($tab === 'pending')
                                            You have not submitted any personnel awaiting approval.
                                        @elseif($tab === 'active')
                                            None of your submitted personnel have been approved yet.
                                            <a href="{{ route('security.personnel.index', ['tab' => 'pending']) }}"
                                               style="color: var(--primary);" class="hover:underline">
                                                View pending submissions
                                            </a>
                                        @else
                                            Submit your first security personnel for admin approval.
                                        @endif
                                    </p>
                                    <a href="{{ route('security.personnel.create') }}"
                                       class="btn-primary px-4 py-2 rounded-lg">
                                        <i class="fas fa-user-plus mr-2"></i> Submit New Personnel
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($personnel->hasPages())
            <div class="p-6 border-t" style="border-color: var(--border-color);">
                {{ $personnel->appends(request()->query())->links() }}
            </div>
        @endif
    </div>
</div>

<!-- ==================== MODALS ==================== -->

<!-- Personnel Details Modal -->
<div id="personnelDetailsModal" class="fixed inset-0 z-50 hidden overflow-y-auto" style="background-color: rgba(0,0,0,0.5);">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="card w-full max-w-3xl">
            <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                <div class="flex items-center">
                    <i class="fas fa-user-shield mr-3 text-xl" style="color: var(--primary);"></i>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);" id="detailsModalTitle">Personnel Details</h3>
                </div>
                <button onclick="closeDetailsModal()" class="text-gray-500 hover:text-gray-700 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="p-6">
                <div id="detailsLoading" class="text-center py-8">
                    <div class="spinner-large mx-auto mb-4"></div>
                    <p style="color: var(--text-secondary);">Loading personnel details...</p>
                </div>

                <div id="detailsContent" class="hidden">
                    <div class="flex items-center mb-6 pb-6 border-b" style="border-color: var(--border-color);">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center text-2xl font-bold text-white"
                             style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);"
                             id="detailsAvatar">??</div>
                        <div class="ml-4">
                            <h4 class="text-xl font-semibold" style="color: var(--text-primary);" id="detailsName">-</h4>
                            <div class="text-sm flex items-center mt-1 flex-wrap gap-2" style="color: var(--text-secondary);">
                                <span id="detailsStatus" class="px-2 py-0.5 rounded-full text-xs">-</span>
                                <span id="detailsType" class="px-2 py-0.5 rounded-full text-xs badge-primary">Security Personnel</span>
                                <span><i class="fas fa-id-badge mr-1"></i> ID: <span id="detailsId">-</span></span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h5 class="text-sm font-semibold mb-3" style="color: var(--text-secondary);">
                                <i class="fas fa-address-card mr-2" style="color: var(--primary);"></i> Contact Information
                            </h5>
                            <div class="space-y-2">
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-envelope w-5" style="color: var(--primary);"></i>
                                    <span style="color: var(--text-primary);" id="detailsEmail">-</span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-phone w-5" style="color: var(--primary);"></i>
                                    <span style="color: var(--text-primary);" id="detailsPhone">-</span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-user w-5" style="color: var(--primary);"></i>
                                    <span style="color: var(--text-primary);" id="detailsUsername">-</span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h5 class="text-sm font-semibold mb-3" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> Approval Status
                            </h5>
                            <div class="space-y-2 text-sm">
                                <div class="flex items-center">
                                    <i class="fas fa-circle w-5" style="color: var(--text-secondary);"></i>
                                    <span style="color: var(--text-primary);" id="detailsApprovalState">-</span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-calendar w-5" style="color: var(--text-secondary);"></i>
                                    <span style="color: var(--text-primary);" id="detailsSubmittedAt">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                <button onclick="closeDetailsModal()" class="btn-secondary px-4 py-2 rounded-lg">
                    <i class="fas fa-times mr-2"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Send / Resend Invitation Modal -->
<div id="resendInvitationModal" class="fixed inset-0 z-50 hidden overflow-y-auto" style="background-color: rgba(0,0,0,0.5);">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="card w-full max-w-md">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <div class="flex items-center">
                    <i class="fas fa-paper-plane mr-2" style="color: var(--info);"></i>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Send Invitation</h3>
                </div>
            </div>
            <div class="p-6">
                <p style="color: var(--text-primary);" id="resendInvitationName" class="mb-4"></p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            <i class="fas fa-tag mr-1" style="color: var(--primary);"></i> Invitation Type
                        </label>
                        <select id="resendInvitationType"
                                class="w-full p-2 border rounded-lg"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="welcome">Welcome Invitation</option>
                            <option value="registration">Registration Invitation</option>
                            <option value="account_setup">Account Setup</option>
                            <option value="password_setup">Password Setup</option>
                            <option value="security_orientation">Security Orientation</option>
                            <option value="security_training">Security Training</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            <i class="fas fa-share-alt mr-1" style="color: var(--primary);"></i> Channels
                        </label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" id="resendChannelEmail" value="email" checked class="w-4 h-4 rounded">
                                <span class="ml-2 text-sm" style="color: var(--text-primary);">Email</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="resendChannelSms" value="sms" class="w-4 h-4 rounded">
                                <span class="ml-2 text-sm" style="color: var(--text-primary);">SMS</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="resendChannelWhatsapp" value="whatsapp" class="w-4 h-4 rounded">
                                <span class="ml-2 text-sm" style="color: var(--text-primary);">WhatsApp</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            <i class="fas fa-comment mr-1" style="color: var(--primary);"></i> Custom Message (Optional)
                        </label>
                        <textarea id="resendCustomMessage"
                                  class="w-full p-2 border rounded-lg"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  rows="3"
                                  placeholder="Add a custom message for the invitation..."></textarea>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            <span id="resendCharCount">0</span>/1000 characters
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            <i class="fas fa-clock mr-1" style="color: var(--primary);"></i> Expiration
                        </label>
                        <select id="resendExpiresInDays"
                                class="w-full p-2 border rounded-lg"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="1">1 Day</option>
                            <option value="3">3 Days</option>
                            <option value="7" selected>7 Days (Default)</option>
                            <option value="14">14 Days</option>
                            <option value="30">30 Days</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                <button type="button" onclick="closeResendInvitationModal()" class="btn-secondary px-4 py-2 rounded-lg">
                    <i class="fas fa-times mr-2"></i> Cancel
                </button>
                <button type="button" onclick="submitResendInvitation()" class="btn-success px-4 py-2 rounded-lg" id="resendSubmitBtn">
                    <i class="fas fa-paper-plane mr-2"></i> Send Invitation
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== STYLES ==================== -->
<style>
.action-btn {
    transition: all 0.2s ease;
}
.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}
tbody tr {
    transition: background-color 0.2s ease;
}
tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.02) !important;
}
.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border: none;
    transition: all 0.2s ease;
    color: white;
}
.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}
.btn-secondary {
    background-color: var(--bg-secondary);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    transition: all 0.2s ease;
}
.btn-secondary:hover {
    background-color: var(--border-color);
}
.btn-success {
    background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
    border: none;
    transition: all 0.2s ease;
    color: white;
}
.btn-success:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--success-rgb), 0.3);
}
.badge-success { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3); }
.badge-warning { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3); }
.badge-danger  { background-color: rgba(var(--danger-rgb), 0.1);  color: var(--danger);  border: 1px solid rgba(var(--danger-rgb), 0.3); }
.badge-info    { background-color: rgba(var(--info-rgb), 0.1);    color: var(--info);    border: 1px solid rgba(var(--info-rgb), 0.3); }
.badge-primary { background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3); }
.badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3); }
.index-custom-dropdown, .index-custom-input {
    transition: all 0.2s ease;
}
.index-custom-dropdown:focus, .index-custom-input:focus {
    border-color: var(--primary) !important;
    outline: none;
    box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.2);
}
.spinner-large {
    display: inline-block;
    width: 3rem;
    height: 3rem;
    border: 3px solid rgba(var(--primary-rgb), 0.2);
    border-radius: 50%;
    border-top-color: var(--primary);
    animation: spin 0.6s linear infinite;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<!-- ==================== JAVASCRIPT ==================== -->
<script>
// ==================== PERSONNEL DETAILS ====================
let currentDetailsUserId = null;

function viewPersonnelDetails(userId) {
    currentDetailsUserId = userId;
    const modal = document.getElementById('personnelDetailsModal');
    const loading = document.getElementById('detailsLoading');
    const content = document.getElementById('detailsContent');

    modal.classList.remove('hidden');
    loading.classList.remove('hidden');
    content.classList.add('hidden');
    document.body.style.overflow = 'hidden';

    fetch(`/security/personnel/${userId}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.text();
    })
    .then(text => {
        if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
        text = text.trim();
        try { return JSON.parse(text); }
        catch (e) { throw new Error('Invalid JSON response: ' + text.substring(0, 200)); }
    })
    .then(raw => {
        // 🔧 Normalize payload shapes:
        //   - flat:                  { id, name, ... }
        //   - resource:              { data: { id, name, ... } }
        //   - success wrapper:       { success: true, data: { ... } }
        //   - user wrapper:          { success: true, user: { ... } }
        let data = raw;
        if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
            if (raw.data && typeof raw.data === 'object' && !Array.isArray(raw.data)) {
                data = raw.data;
            } else if (raw.user && typeof raw.user === 'object' && !Array.isArray(raw.user)) {
                data = raw.user;
            }
        }
        if (!data || typeof data !== 'object' || Array.isArray(data)) {
            throw new Error('Unexpected response shape: ' + JSON.stringify(raw).substring(0, 200));
        }
        loading.classList.add('hidden');
        content.classList.remove('hidden');
        populateDetails(data);
    })
    .catch(error => {
        loading.classList.add('hidden');
        content.classList.remove('hidden');
        showToast('error', 'Failed to load personnel details: ' + error.message);
    });
}

function populateDetails(data) {
    // Normalize with defensive fallbacks — payload may be flat or
    // nested under user/data, and any field may be missing.
    const nameStr = String(data.name || '??');
    const initials = nameStr.split(' ').filter(Boolean).map(w => w[0]).join('').substring(0, 2).toUpperCase() || '??';

    document.getElementById('detailsAvatar').textContent = initials;
    document.getElementById('detailsName').textContent = nameStr !== '??' ? nameStr : 'Unknown';
    document.getElementById('detailsId').textContent = data.id ?? '-';
    document.getElementById('detailsModalTitle').textContent = `Personnel Details - ${data.name || 'Unknown'}`;

    // Status badge
    const statusColors = {
        active: 'success',
        pending: 'warning',
        inactive: 'secondary',
        suspended: 'danger',
        rejected: 'danger',
        archived: 'dark',
    };
    const rawStatus = String(data.status || '').toLowerCase();
    const statusColor = statusColors[rawStatus] || 'secondary';
    const statusLabel = rawStatus
        ? rawStatus.charAt(0).toUpperCase() + rawStatus.slice(1)
        : '-';

    const statusEl = document.getElementById('detailsStatus');
    statusEl.textContent = statusLabel;
    statusEl.className = `px-2 py-0.5 rounded-full text-xs badge-${statusColor}`;

    // Contact info
    document.getElementById('detailsEmail').textContent    = data.email    || '-';
    document.getElementById('detailsPhone').textContent    = data.phone    || '-';
    document.getElementById('detailsUsername').textContent = data.username || '-';

    // Approval state — derive from flags OR from status
    let approvalState = 'Approved';
    if (data.awaiting_approval === true) {
        approvalState = 'Awaiting Approval';
    } else if (data.rejected_at || rawStatus === 'rejected') {
        approvalState = 'Rejected by admin';
    } else if (rawStatus === 'pending') {
        approvalState = 'Pending';
    } else if (rawStatus !== 'active' && rawStatus !== '') {
        approvalState = statusLabel;
    }

    // Include approver info if available
    if (data.approved_by_name) {
        approvalState += ` (by ${data.approved_by_name})`;
    }
    document.getElementById('detailsApprovalState').textContent = approvalState;

    // Submitted at — accept any common date field
    const submitted =
        data.submitted_at ||
        data.created_at ||
        data.approved_at ||
        null;

    const submittedEl = document.getElementById('detailsSubmittedAt');
    if (submitted) {
        const d = new Date(submitted);
        submittedEl.textContent = isNaN(d.getTime())
            ? String(submitted)
            : d.toLocaleString(undefined, {
                year: 'numeric', month: 'short', day: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });
    } else {
        submittedEl.textContent = '—';
    }
}

function closeDetailsModal() {
    document.getElementById('personnelDetailsModal').classList.add('hidden');
    document.body.style.overflow = '';
    currentDetailsUserId = null;
}

// ==================== INVITATION ====================
let resendUserId = null;

function resendInvitation(id, name) {
    resendUserId = id;
    document.getElementById('resendInvitationName').innerHTML = `
        <i class="fas fa-user mr-2" style="color: var(--primary);"></i>
        Send invitation to <strong>${name}</strong>
    `;
    document.getElementById('resendInvitationType').value = 'welcome';
    document.getElementById('resendCustomMessage').value = '';
    document.getElementById('resendCharCount').textContent = '0';
    document.getElementById('resendExpiresInDays').value = '7';
    document.getElementById('resendChannelEmail').checked = true;
    document.getElementById('resendChannelSms').checked = false;
    document.getElementById('resendChannelWhatsapp').checked = false;
    document.getElementById('resendInvitationModal').classList.remove('hidden');
}

function closeResendInvitationModal() {
    document.getElementById('resendInvitationModal').classList.add('hidden');
    resendUserId = null;
}

function submitResendInvitation() {
    const channels = [];
    if (document.getElementById('resendChannelEmail').checked) channels.push('email');
    if (document.getElementById('resendChannelSms').checked) channels.push('sms');
    if (document.getElementById('resendChannelWhatsapp').checked) channels.push('whatsapp');

    if (channels.length === 0) {
        showToast('warning', 'Please select at least one channel.');
        return;
    }

    const data = {
        channels: channels,
        invitation_type: document.getElementById('resendInvitationType').value,
        custom_message: document.getElementById('resendCustomMessage').value,
        expires_in_days: parseInt(document.getElementById('resendExpiresInDays').value),
        resend_type: 'selected_channels'
    };

    const submitBtn = document.getElementById('resendSubmitBtn');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending...';
    submitBtn.disabled = true;

    fetch(`/security/personnel/${resendUserId}/resend-invitation`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        return response.text().then(text => {
            if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
            text = text.trim();
            try { return { status: response.status, data: JSON.parse(text) }; }
            catch (e) { throw new Error('Invalid JSON response: ' + text.substring(0, 200)); }
        });
    })
    .then(({ data }) => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;

        if (data.success) {
            showToast('success', data.message || 'Invitation sent successfully!');
            closeResendInvitationModal();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast('error', data.message || 'Failed to send invitation.');
        }
    })
    .catch(error => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        showToast('error', 'An error occurred: ' + error.message);
    });
}

// Character counter
document.getElementById('resendCustomMessage')?.addEventListener('input', function() {
    const count = this.value.length;
    document.getElementById('resendCharCount').textContent = count;
    const el = document.getElementById('resendCharCount');
    el.style.color = count > 950 ? 'var(--danger)'
        : count > 900 ? 'var(--warning)'
        : 'var(--text-secondary)';
});

// ==================== TOAST ====================
function showToast(type, message) {
    const existing = document.querySelector('.custom-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = 'custom-toast';
    const colors = {
        success: 'var(--success)',
        error: 'var(--danger)',
        warning: 'var(--warning)',
        info: 'var(--info)'
    };
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };

    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        padding: 16px 24px;
        border-radius: 12px;
        background: var(--card-bg);
        border-left: 4px solid ${colors[type] || 'var(--info)'};
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        gap: 12px;
        max-width: 450px;
        animation: slideInRight 0.4s ease;
        border: 1px solid var(--border-color);
    `;

    toast.innerHTML = `
        <i class="fas ${icons[type]}" style="color: ${colors[type]}; font-size: 1.2rem;"></i>
        <span style="color: var(--text-primary); font-size: 0.9rem;">${message}</span>
        <button onclick="this.parentElement.remove()" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.1rem; margin-left: 8px;">
            <i class="fas fa-times"></i>
        </button>
    `;

    document.body.appendChild(toast);
    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100px)';
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

// ==================== CLOSE ON OUTSIDE CLICK & ESCAPE ====================
window.addEventListener('click', function (event) {
    if (event.target.classList.contains('fixed')) {
        closeDetailsModal();
        closeResendInvitationModal();
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeDetailsModal();
        closeResendInvitationModal();
    }
});

// Keyframes
if (!document.getElementById('toast-styles')) {
    const styleSheet = document.createElement('style');
    styleSheet.id = 'toast-styles';
    styleSheet.textContent = `
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(100px); }
            to   { opacity: 1; transform: translateX(0); }
        }
    `;
    document.head.appendChild(styleSheet);
}
</script>
@endsection