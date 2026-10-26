@extends('layouts.app')

@section('title', 'Head of Security Appointments')

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
                        <i class="fas fa-user-shield mr-2" style="color: var(--primary);"></i>
                        Head of Security Appointments
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>View and manage Head of Security appointments</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-clipboard-list mr-1"></i>
                        <span>{{ $stats['total_assignments'] ?? 0 }} total</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-check-circle mr-1"></i>
                        <span>{{ $stats['active_assignments'] ?? 0 }} active</span>
                        <span class="mx-2">•</span>
                        <span class="px-2 py-0.5 rounded-full text-xs"
                              style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            <i class="fas fa-shield-alt mr-1"></i> Admin
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.supervisor-assignments.trash.index') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-trash-alt mr-2"></i> Trash
                    @php
                        try {
                            $trashCount = \App\Models\SecuritySupervisorAssignment::onlyTrashed()->count();
                        } catch (\Throwable $e) {
                            $trashCount = 0;
                        }
                    @endphp
                    @if($trashCount > 0)
                        <span class="ml-2 px-2 py-0.5 text-xs rounded-full"
                              style="background-color: var(--warning); color: white;">{{ $trashCount }}</span>
                    @endif
                </a>

                <a href="{{ route('admin.supervisor-assignments.create') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium text-white inline-flex items-center btn-primary">
                    <i class="fas fa-plus mr-2"></i> Appoint Head of Security
                </a>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    @if(session('success'))
        <div class="card p-4"
             style="background-color: rgba(var(--success-rgb), 0.1); border-left: 4px solid var(--success);">
            <div class="flex items-start">
                <i class="fas fa-check-circle mt-0.5 mr-3" style="color: var(--success);"></i>
                <p class="text-sm" style="color: var(--text-primary);">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="card p-4"
             style="background-color: rgba(var(--danger-rgb), 0.1); border-left: 4px solid var(--danger);">
            <div class="flex items-start">
                <i class="fas fa-exclamation-circle mt-0.5 mr-3" style="color: var(--danger);"></i>
                <p class="text-sm" style="color: var(--text-primary);">{{ session('error') }}</p>
            </div>
        </div>
    @endif

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
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Active Head</div>
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

    <!-- Filters -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
            <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filter Appointments
        </h3>

        <form method="GET" action="{{ route('admin.supervisor-assignments.index') }}"
              class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <label for="search" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-search mr-1" style="color: var(--primary);"></i> Search
                </label>
                <input type="text" id="search" name="search"
                       class="index-custom-input w-full"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                       placeholder="Officer name or email…"
                       value="{{ request('search') }}">
            </div>

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
                <label for="sort_by" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-sort mr-1" style="color: var(--primary);"></i> Sort By
                </label>
                <div class="flex space-x-2">
                    <select id="sort_by" name="sort_by" class="index-custom-dropdown flex-1"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="created_at"  {{ request('sort_by', 'created_at') === 'created_at'  ? 'selected' : '' }}>Created</option>
                        <option value="start_date"  {{ request('sort_by') === 'start_date'  ? 'selected' : '' }}>Start Date</option>
                        <option value="end_date"    {{ request('sort_by') === 'end_date'    ? 'selected' : '' }}>End Date</option>
                    </select>
                    <select id="sort_order" name="sort_order" class="index-custom-dropdown w-32"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="desc" {{ request('sort_order', 'desc') === 'desc' ? 'selected' : '' }}>Desc</option>
                        <option value="asc"  {{ request('sort_order') === 'asc'  ? 'selected' : '' }}>Asc</option>
                    </select>
                </div>
            </div>

            <div class="md:col-span-4 flex justify-end space-x-3">
                <a href="{{ route('admin.supervisor-assignments.index') }}"
                   class="btn-secondary px-6 py-2.5 rounded-lg text-sm font-medium inline-flex items-center">
                    <i class="fas fa-times mr-2"></i> Clear Filters
                </a>
                <button type="submit"
                        class="btn-primary px-6 py-2.5 rounded-lg text-sm font-medium text-white inline-flex items-center">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Assignments Table -->
    <div class="card">
        <div class="p-6 border-b flex justify-between items-center" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-list mr-2" style="color: var(--primary);"></i> Appointments
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
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Appointed By</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assignments as $assignment)
                        @php
                            $user             = $assignment->user;
                            $userName         = optional($user)->name ?? 'Deleted User';
                            $userEmail        = optional($user)->email ?? 'No email';
                            $assignedByName   = optional($assignment->assignedBy)->name ?? 'System';
                            $startDate        = $assignment->start_date;
                            $endDate          = $assignment->end_date;

                            $isActive     = (bool) $assignment->is_active;
                            $isExpired    = $endDate && $endDate->isPast();
                            $isExpiringSoon = $isActive
                                && $endDate
                                && !$isExpired
                                && $endDate->diffInDays(now()) <= 30;

                            if ($isActive && !$isExpired) {
                                $statusColor = $isExpiringSoon ? 'warning' : 'success';
                                $statusText  = $isExpiringSoon ? 'Expiring Soon' : 'Active Head';
                                $statusIcon  = $isExpiringSoon ? 'fa-hourglass-half' : 'fa-crown';
                            } elseif ($isExpired) {
                                $statusColor = 'secondary';
                                $statusText  = 'Expired';
                                $statusIcon  = 'fa-hourglass-end';
                            } else {
                                $statusColor = 'warning';
                                $statusText  = 'Inactive';
                                $statusIcon  = 'fa-pause-circle';
                            }

                            $daysRemaining = null;
                            if ($endDate && !$isExpired) {
                                $daysRemaining = (int) now()->diffInDays($endDate, false);
                            }
                        @endphp
                        <tr class="border-b transition-colors duration-200"
                            style="border-color: var(--border-color); cursor: pointer;"
                            onclick="window.location='{{ route('admin.supervisor-assignments.show', $assignment->id) }}'">

                            <!-- Officer -->
                            <td class="py-4 px-6">
                                <div class="flex items-center">
                                    @if($user && $user->has_photo)
                                        <img src="{{ $user->photo_url }}" alt="{{ $userName }}"
                                             class="w-10 h-10 rounded-full object-cover mr-3">
                                    @else
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3 text-white text-sm font-semibold"
                                             style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                                            {{ $user->initials ?? '?' }}
                                        </div>
                                    @endif
                                    <div>
                                        <div class="font-medium flex items-center" style="color: var(--text-primary);">
                                            {{ $userName }}
                                            @if(!$user)
                                                <span class="ml-2 px-1.5 py-0.5 text-xs rounded"
                                                      style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            <i class="fas fa-envelope mr-1"></i> {{ $userEmail }}
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
                                {{ $startDate ? $startDate->format('M j, Y') : '—' }}
                            </td>

                            <!-- End -->
                            <td class="py-4 px-6">
                                <div class="text-sm" style="color: var(--text-primary);">
                                    <i class="fas fa-stop-circle mr-1"
                                       style="color: {{ $endDate ? 'var(--warning)' : 'var(--info)' }};"></i>
                                    {{ $endDate ? $endDate->format('M j, Y') : 'Indefinite' }}
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

                            <!-- Appointed By -->
                            <td class="py-4 px-6 text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-user-check mr-1"></i> {{ $assignedByName }}
                            </td>

                            <!-- Actions -->
                            <td class="py-4 px-6" onclick="event.stopPropagation();">
                                <div class="flex space-x-2">
                                    <a href="{{ route('admin.supervisor-assignments.show', $assignment->id) }}"
                                       class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                       title="View Details">
                                        <i class="fas fa-eye text-sm"></i>
                                    </a>

                                    <a href="{{ route('admin.supervisor-assignments.edit', $assignment->id) }}"
                                       class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                       style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);"
                                       title="Edit">
                                        <i class="fas fa-edit text-sm"></i>
                                    </a>

                                    @if($isActive)
                                        <form method="POST"
                                              action="{{ route('admin.supervisor-assignments.update', $assignment->id) }}"
                                              onsubmit="return confirm('Deactivate this appointment? The officer will no longer be Head of Security.');"
                                              class="inline">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="user_id" value="{{ $assignment->user_id }}">
                                            <input type="hidden" name="title" value="{{ $assignment->title }}">
                                            <input type="hidden" name="start_date" value="{{ $assignment->start_date?->format('Y-m-d') }}">
                                            <input type="hidden" name="end_date" value="{{ $assignment->end_date?->format('Y-m-d') }}">
                                            <input type="hidden" name="notes" value="{{ $assignment->notes }}">
                                            <input type="hidden" name="is_active" value="0">
                                            <button type="submit"
                                                    class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                                    style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);"
                                                    title="Deactivate">
                                                <i class="fas fa-pause text-sm"></i>
                                            </button>
                                        </form>
                                    @endif

                                    <form method="POST"
                                          action="{{ route('admin.supervisor-assignments.destroy', $assignment->id) }}"
                                          onsubmit="return confirm('Move this appointment to trash?');"
                                          class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                                                title="Delete">
                                            <i class="fas fa-trash text-sm"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="w-20 h-20 rounded-full flex items-center justify-center mb-4"
                                         style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                        <i class="fas fa-user-shield text-3xl" style="color: var(--text-secondary);"></i>
                                    </div>
                                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                                        No Appointments Found
                                    </h4>
                                    <p class="text-sm mb-4" style="color: var(--text-secondary);">
                                        {{ request()->anyFilled(['status', 'search', 'start_date', 'end_date', 'sort_by'])
                                            ? 'Try adjusting your filters'
                                            : 'Get started by appointing a Head of Security' }}
                                    </p>
                                    @if(request()->anyFilled(['status', 'search', 'start_date', 'end_date', 'sort_by']))
                                        <a href="{{ route('admin.supervisor-assignments.index') }}"
                                           class="btn-secondary px-6 py-3 rounded-lg text-sm font-medium inline-flex items-center">
                                            <i class="fas fa-times mr-2"></i> Clear Filters
                                        </a>
                                    @else
                                        <a href="{{ route('admin.supervisor-assignments.create') }}"
                                           class="btn-primary px-6 py-3 rounded-lg text-sm font-medium text-white inline-flex items-center">
                                            <i class="fas fa-plus mr-2"></i> Appoint Head of Security
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
    cursor: pointer;
}
tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.03) !important;
}

.badge-success    { background-color: rgba(var(--success-rgb), 0.1) !important; color: var(--success) !important; border: 1px solid rgba(var(--success-rgb), 0.3) !important; }
.badge-warning    { background-color: rgba(var(--warning-rgb), 0.1) !important; color: var(--warning) !important; border: 1px solid rgba(var(--warning-rgb), 0.3) !important; }
.badge-danger     { background-color: rgba(var(--danger-rgb), 0.1) !important; color: var(--danger) !important; border: 1px solid rgba(var(--danger-rgb), 0.3) !important; }
.badge-info       { background-color: rgba(var(--info-rgb), 0.1) !important; color: var(--info) !important; border: 1px solid rgba(var(--info-rgb), 0.3) !important; }
.badge-primary    { background-color: rgba(var(--primary-rgb), 0.1) !important; color: var(--primary) !important; border: 1px solid rgba(var(--primary-rgb), 0.3) !important; }
.badge-secondary  { background-color: rgba(var(--secondary-rgb), 0.1) !important; color: var(--secondary) !important; border: 1px solid rgba(var(--secondary-rgb), 0.3) !important; }

.index-custom-dropdown,
.index-custom-input {
    transition: all 0.2s ease;
}
.index-custom-dropdown:focus,
.index-custom-input:focus {
    border-color: var(--primary) !important;
    outline: none;
    box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.2);
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
</style>
@endsection