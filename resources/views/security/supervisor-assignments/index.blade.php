@extends('layouts.secu')

@section('title', 'Head of Security Appointment')

@section('content')
@php
    $me = auth()->user();
@endphp

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
                        <i class="fas fa-user-shield mr-2" style="color: var(--primary);"></i>
                        Head of Security
                    </h2>
                    <div class="text-sm flex items-center flex-wrap mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>{{ $currentHead ? 'You are currently Head of Security' : 'Your appointment history' }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-clipboard-list mr-1"></i>
                        <span>{{ $stats['total_assignments'] ?? 0 }} total</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-check-circle mr-1"></i>
                        <span>{{ $stats['active_assignments'] ?? 0 }} active</span>
                        <span class="mx-2">•</span>
                        @if($currentHead)
                            <span class="px-2 py-0.5 rounded-full text-xs"
                                  style="background-color: rgba(var(--success-rgb), 0.15); color: var(--success);">
                                <i class="fas fa-crown mr-1"></i> Active Head
                            </span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-xs"
                                  style="background-color: rgba(var(--secondary-rgb), 0.15); color: var(--text-secondary);">
                                <i class="fas fa-pause-circle mr-1"></i> Not currently head
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('security.dashboard') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Info banner -->
    <div class="card p-4"
         style="background-color: rgba(var(--info-rgb), 0.08); border-left: 4px solid var(--info);">
        <div class="flex items-start">
            <i class="fas fa-info-circle mt-0.5 mr-3" style="color: var(--info);"></i>
            <div>
                <p class="text-sm font-medium" style="color: var(--text-primary);">
                    Appointments are managed by administrators
                </p>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    This page is read-only. If you are the active Head of Security and choose to step down,
                    use <strong>Resign from post</strong> — an administrator will appoint a successor.
                    For any other change (dates, title, reassignment), please contact an administrator.
                </p>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $stats['total_assignments'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total Appointments</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                     style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-clipboard-list" style="color: var(--primary);"></i>
                </div>
            </div>
        </div>

        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $stats['active_assignments'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Active</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                     style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-crown" style="color: var(--success);"></i>
                </div>
            </div>
        </div>

        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $stats['expiring_soon'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Expiring Soon</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                     style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-clock" style="color: var(--info);"></i>
                </div>
            </div>
        </div>

        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $stats['expired_assignments'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Expired</div>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                     style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-hourglass-end" style="color: var(--warning);"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
            <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filter
        </h3>

        <form method="GET" action="{{ route('security.supervisor-assignments.index') }}"
              class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="status" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-power-off mr-1" style="color: var(--primary);"></i> Status
                </label>
                <select id="status" name="status" class="index-custom-dropdown w-full"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="">All Status</option>
                    <option value="active"   {{ request('status') === 'active'   ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    <option value="expired"  {{ request('status') === 'expired'  ? 'selected' : '' }}>Expired</option>
                </select>
            </div>

            <div>
                <label for="start_date" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-calendar-alt mr-1" style="color: var(--primary);"></i> From
                </label>
                <input type="date" id="start_date" name="start_date"
                       class="index-custom-input w-full"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                       value="{{ request('start_date') }}">
            </div>

            <div>
                <label for="end_date" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-calendar-check mr-1" style="color: var(--primary);"></i> To
                </label>
                <input type="date" id="end_date" name="end_date"
                       class="index-custom-input w-full"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                       value="{{ request('end_date') }}">
            </div>

            <div>
                <label for="search" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-search mr-1" style="color: var(--primary);"></i> Search
                </label>
                <input type="text" id="search" name="search"
                       class="index-custom-input w-full"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                       placeholder="Officer name or email…"
                       value="{{ request('search') }}">
            </div>

            <div class="md:col-span-4 flex justify-end space-x-3">
                <a href="{{ route('security.supervisor-assignments.index') }}"
                   class="btn-secondary px-6 py-2.5 rounded-lg text-sm font-medium inline-flex items-center">
                    <i class="fas fa-times mr-2"></i> Clear
                </a>
                <button type="submit"
                        class="btn-primary px-6 py-2.5 rounded-lg text-sm font-medium text-white inline-flex items-center">
                    <i class="fas fa-filter mr-2"></i> Apply
                </button>
            </div>
        </form>
    </div>

    <!-- Appointments Table -->
    <div class="card">
        <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-list mr-2" style="color: var(--primary);"></i>
                Appointments
                <span class="ml-3 px-2.5 py-1 text-xs rounded-full badge-info">
                    {{ $assignments->total() }} total
                </span>
            </h3>
            <span class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-week mr-1"></i> {{ now()->format('F j, Y') }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Officer</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Title</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Start</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">End</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assignments as $assignment)
                        @php
                            $isActive   = (bool) $assignment->is_active;
                            $isExpired  = $assignment->end_date && $assignment->end_date->isPast();
                            $isSoon     = $isActive
                                && $assignment->end_date
                                && ! $isExpired
                                && $assignment->end_date->diffInDays(now()) <= 30;

                            if ($isActive && ! $isExpired) {
                                $statusColor = $isSoon ? 'warning' : 'success';
                                $statusText  = $isSoon ? 'Expiring Soon' : 'Active Head';
                                $statusIcon  = $isSoon ? 'fa-hourglass-half' : 'fa-crown';
                            } elseif ($isExpired) {
                                $statusColor = 'secondary';
                                $statusText  = 'Expired';
                                $statusIcon  = 'fa-hourglass-end';
                            } else {
                                $statusColor = 'warning';
                                $statusText  = 'Inactive';
                                $statusIcon  = 'fa-pause-circle';
                            }

                            $daysRemaining = ($assignment->end_date && ! $isExpired)
                                ? (int) now()->diffInDays($assignment->end_date, false)
                                : null;

                            $officer       = $assignment->user;
                            $officerName   = optional($officer)->name ?? 'Deleted User';
                            $officerEmail  = optional($officer)->email ?? 'No email';
                            $isOwnAppt     = $officer && $officer->id === $me->id;
                            $canResign     = $isOwnAppt && $isActive && ! $isExpired;
                        @endphp
                        <tr class="border-b transition-colors duration-200"
                            style="border-color: var(--border-color); cursor: pointer;"
                            onclick="window.location='{{ route('security.supervisor-assignments.show', $assignment->id) }}'">

                            <!-- Officer -->
                            <td class="py-4 px-6">
                                <div class="flex items-center">
                                    @if($officer && $officer->has_photo)
                                        <img src="{{ $officer->photo_url }}" alt="{{ $officerName }}"
                                             class="w-10 h-10 rounded-full object-cover mr-3">
                                    @else
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3 text-white text-sm font-semibold"
                                             style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                                            {{ $officer->initials ?? '?' }}
                                        </div>
                                    @endif
                                    <div>
                                        <div class="font-medium" style="color: var(--text-primary);">
                                            {{ $officerName }}
                                            @if($isOwnAppt)
                                                <span class="ml-2 px-1.5 py-0.5 text-xs rounded"
                                                      style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                                    You
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            <i class="fas fa-envelope mr-1"></i> {{ $officerEmail }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Title -->
                            <td class="py-4 px-6">
                                <span class="px-2.5 py-1 text-xs rounded-full badge-primary">
                                    <i class="fas fa-tag mr-1"></i> {{ $assignment->title_label }}
                                </span>
                            </td>

                            <!-- Start -->
                            <td class="py-4 px-6 text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-play-circle mr-1" style="color: var(--success);"></i>
                                {{ $assignment->start_date?->format('M j, Y') ?? '—' }}
                            </td>

                            <!-- End -->
                            <td class="py-4 px-6">
                                <div class="text-sm" style="color: var(--text-primary);">
                                    <i class="fas fa-stop-circle mr-1"
                                       style="color: {{ $assignment->end_date ? 'var(--warning)' : 'var(--info)' }};"></i>
                                    {{ $assignment->end_date?->format('M j, Y') ?? 'Indefinite' }}
                                </div>
                                @if($daysRemaining !== null)
                                    <div class="text-xs mt-1"
                                         style="color: {{ $daysRemaining < 0 ? 'var(--danger)' : ($daysRemaining <= 30 ? 'var(--warning)' : 'var(--text-secondary)') }};">
                                        @if($daysRemaining < 0)
                                            <i class="fas fa-exclamation-circle mr-1"></i>
                                            {{ abs($daysRemaining) }} day(s) ago
                                        @else
                                            <i class="fas fa-hourglass-half mr-1"></i>
                                            {{ $daysRemaining }} day(s) left
                                        @endif
                                    </div>
                                @endif
                            </td>

                            <!-- Status -->
                            <td class="py-4 px-6">
                                <span class="px-2.5 py-1.5 text-xs rounded-full badge-{{ $statusColor }}">
                                    <i class="fas {{ $statusIcon }} mr-1"></i> {{ $statusText }}
                                </span>
                            </td>

                            <!-- Actions: View + optional Resign -->
                            <td class="py-4 px-6" onclick="event.stopPropagation();">
                                <div class="flex space-x-2">
                                    <a href="{{ route('security.supervisor-assignments.show', $assignment->id) }}"
                                       class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                       title="View Details">
                                        <i class="fas fa-eye text-sm"></i>
                                    </a>

                                    @if($canResign)
                                        <button type="button"
                                                onclick="showResignModal({{ $assignment->id }}, '{{ addslashes($officerName) }}')"
                                                class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                                                title="Resign from post">
                                            <i class="fas fa-sign-out-alt text-sm"></i>
                                        </button>
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
                                        No Appointments
                                    </h4>
                                    <p class="text-sm mb-4" style="color: var(--text-secondary);">
                                        {{ request()->anyFilled(['status', 'search', 'start_date', 'end_date'])
                                            ? 'Try adjusting your filters'
                                            : 'You do not have any Head-of-Security appointments yet.' }}
                                    </p>
                                    @if(request()->anyFilled(['status', 'search', 'start_date', 'end_date']))
                                        <a href="{{ route('security.supervisor-assignments.index') }}"
                                           class="btn-secondary px-6 py-3 rounded-lg text-sm font-medium inline-flex items-center">
                                            <i class="fas fa-times mr-2"></i> Clear Filters
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($assignments->hasPages())
            <div class="p-6 border-t" style="border-color: var(--border-color);">
                {{ $assignments->appends(request()->query())->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Resign from post modal -->
<div id="resignModal" class="fixed inset-0 z-50 hidden overflow-y-auto"
     style="background-color: rgba(0,0,0,0.5);">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="card w-full max-w-md">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-sign-out-alt mr-2" style="color: var(--danger);"></i>
                    Resign from Head of Security
                </h3>
            </div>
            <form id="resignForm" method="POST">
                @csrf
                <div class="p-6 space-y-4">
                    <div class="p-3 rounded-lg"
                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <p class="text-sm" style="color: var(--text-primary);">
                            <i class="fas fa-exclamation-triangle mr-1" style="color: var(--warning);"></i>
                            The community will have <strong>no active Head of Security</strong>
                            until an administrator appoints a successor.
                        </p>
                    </div>

                    <div>
                        <label for="resign_reason" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            Reason for resignation <span style="color: var(--danger);">*</span>
                        </label>
                        <textarea name="reason" id="resign_reason" rows="3"
                                  class="index-custom-input w-full"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Why are you stepping down?"
                                  required></textarea>
                    </div>

                    <label class="flex items-start space-x-3 cursor-pointer">
                        <input type="checkbox" id="resign_confirm" required
                               class="mt-1 w-4 h-4 rounded"
                               style="accent-color: var(--primary);">
                        <span class="text-sm" style="color: var(--text-secondary);">
                            I understand that my appointment will end immediately and administrators will be notified.
                        </span>
                    </label>
                </div>
                <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                    <button type="button" onclick="closeModal('resignModal')"
                            class="btn-secondary px-4 py-2 rounded-lg">Cancel</button>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg"
                            style="background-color: var(--danger); color: white;">
                        Confirm resignation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.action-btn { transition: all 0.2s ease; }
.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

tbody tr {
    transition: background-color 0.2s ease;
    cursor: pointer;
}
tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.03) !important;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border: none;
    transition: all 0.2s ease;
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

.badge-primary   { background-color: rgba(var(--primary-rgb), 0.1) !important;   color: var(--primary) !important;   border: 1px solid rgba(var(--primary-rgb), 0.3) !important; }
.badge-success   { background-color: rgba(var(--success-rgb), 0.1) !important;   color: var(--success) !important;   border: 1px solid rgba(var(--success-rgb), 0.3) !important; }
.badge-warning   { background-color: rgba(var(--warning-rgb), 0.1) !important;   color: var(--warning) !important;   border: 1px solid rgba(var(--warning-rgb), 0.3) !important; }
.badge-danger    { background-color: rgba(var(--danger-rgb), 0.1) !important;    color: var(--danger) !important;    border: 1px solid rgba(var(--danger-rgb), 0.3) !important; }
.badge-info      { background-color: rgba(var(--info-rgb), 0.1) !important;      color: var(--info) !important;      border: 1px solid rgba(var(--info-rgb), 0.3) !important; }
.badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1) !important; color: var(--secondary) !important; border: 1px solid rgba(var(--secondary-rgb), 0.3) !important; }

.index-custom-dropdown, .index-custom-input {
    transition: all 0.2s ease;
}
.index-custom-dropdown:focus, .index-custom-input:focus {
    border-color: var(--primary) !important;
    outline: none;
    box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.2);
}
</style>

<script>
function showResignModal(id, name) {
    const form = document.getElementById('resignForm');
    form.action = `/security/supervisor-assignments/${id}/terminate`;

    document.getElementById('resign_reason').value = '';
    document.getElementById('resign_confirm').checked = false;

    document.getElementById('resignModal').classList.remove('hidden');
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

window.addEventListener('click', function (event) {
    const modal = document.getElementById('resignModal');
    if (event.target === modal) {
        modal.classList.add('hidden');
    }
});
</script>
@endsection