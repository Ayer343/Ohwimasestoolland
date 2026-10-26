@extends('layouts.dev')

@section('title', 'Maintenance Management')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- ============================================================
         | HEADER CARD
         * ============================================================ --}}
    <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="flex justify-between items-center p-6 flex-wrap gap-3">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-tools mr-2" style="color: var(--primary);"></i> Maintenance Management
                </h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Schedule, monitor, and review system maintenance windows.
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('developer.maintenance.create') }}"
                   class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                   style="background-color: var(--primary); color: white;">
                    <i class="fas fa-calendar-plus mr-2"></i> Schedule Maintenance
                </a>
                <a href="{{ route('developer.maintenance.statistics') }}"
                   class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                   style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-chart-bar mr-2"></i> Statistics
                </a>
                <a href="{{ route('developer.maintenance.trash') }}"
                   class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                   style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-trash-restore mr-2"></i> Trash
                    @if(($trashCount ?? 0) > 0)
                        <span class="ml-2 px-2 py-0.5 rounded-full text-xs"
                              style="background-color: rgba(var(--danger-rgb), 0.15); color: var(--danger);">
                            {{ $trashCount }}
                        </span>
                    @endif
                </a>
            </div>
        </div>
    </div>

    {{-- ============================================================
         | ACTIVE MAINTENANCE BANNER
         * ============================================================ --}}
    @if($activeMaintenance ?? false)
    <div class="card p-6"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;
                border-left: 6px solid var(--warning); box-shadow: 0 10px 30px rgba(var(--warning-rgb), 0.15);">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center bg-gradient-to-br from-yellow-400 to-orange-500 animate-pulse mr-4">
                    <i class="fas fa-tools text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold" style="color: var(--text-primary);">
                        🚧 System Maintenance In Progress
                    </h3>
                    <div class="flex flex-wrap items-center gap-2 mt-2">
                        <span class="badge" style="background-color: rgba(var(--warning-rgb), 0.15); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                            <i class="fas fa-sync-alt mr-1"></i> In Progress
                        </span>
                        <span class="badge"
                              style="background-color: {{ $activeMaintenance->impact_level === 'critical' ? 'rgba(var(--danger-rgb), 0.15)' : ($activeMaintenance->impact_level === 'high' ? 'rgba(var(--warning-rgb), 0.15)' : 'rgba(var(--info-rgb), 0.15)') }};
                                     color: {{ $activeMaintenance->impact_level === 'critical' ? 'var(--danger)' : ($activeMaintenance->impact_level === 'high' ? 'var(--warning)' : 'var(--info)') }};
                                     border: 1px solid {{ $activeMaintenance->impact_level === 'critical' ? 'rgba(var(--danger-rgb), 0.3)' : ($activeMaintenance->impact_level === 'high' ? 'rgba(var(--warning-rgb), 0.3)' : 'rgba(var(--info-rgb), 0.3)') }};">
                            <i class="fas fa-exclamation mr-1"></i>
                            {{ strtoupper($activeMaintenance->impact_level) }} Impact
                        </span>
                        <span class="badge" style="background-color: rgba(139, 92, 246, 0.15); color: #8b5cf6; border: 1px solid rgba(139, 92, 246, 0.3);">
                            <i class="fas fa-tag mr-1"></i>
                            {{ strtoupper(str_replace('_', ' ', $activeMaintenance->maintenance_type)) }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex space-x-2 flex-wrap gap-2">
                <button type="button"
                        onclick="openCompleteModal({{ $activeMaintenance->id }}, {{ $activeMaintenance->estimated_duration_minutes }}, {{ $activeMaintenance->estimated_affected_users ?? 0 }})"
                        class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                        style="background-color: var(--success); color: white;">
                    <i class="fas fa-check-circle mr-2"></i> Complete Maintenance
                </button>
                <a href="{{ route('developer.maintenance.show', $activeMaintenance->id) }}"
                   class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                   style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-2"></i> View Details
                </a>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                <div class="text-sm" style="color: var(--text-secondary);">Duration</div>
                <div class="text-2xl font-semibold" style="color: var(--text-primary);">
                    {{ $activeMaintenance->actual_start ? $activeMaintenance->actual_start->diffInMinutes(now()) : 0 }} min elapsed
                </div>
                <div class="text-sm mt-1" style="color: var(--text-secondary);">
                    Estimated: {{ $activeMaintenance->estimated_duration_minutes }} minutes
                </div>
            </div>

            <div class="p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                <div class="text-sm" style="color: var(--text-secondary);">Affected Users</div>
                <div class="text-2xl font-semibold" style="color: var(--text-primary);">
                    {{ $activeMaintenance->affectedUsers()->count() }}
                </div>
                <div class="text-sm mt-1" style="color: var(--text-secondary);">
                    @php
                        $userTypes = \App\Models\User::getUserTypes();
                        $labels = array_map(fn($t) => $userTypes[$t] ?? "Type {$t}", $activeMaintenance->affected_user_types ?? []);
                    @endphp
                    {{ implode(', ', $labels) }}
                </div>
            </div>

            <div class="p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                <div class="text-sm" style="color: var(--text-secondary);">Started By</div>
                <div class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-user-shield mr-2"></i>
                    {{ $activeMaintenance->creator->name ?? 'System' }}
                </div>
                <div class="text-sm mt-1" style="color: var(--text-secondary);">
                    {{ $activeMaintenance->actual_start ? $activeMaintenance->actual_start->format('M d, H:i') : 'N/A' }}
                </div>
            </div>
        </div>

        <div class="mt-6 p-4 rounded-lg" style="background: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
            <div class="flex items-start">
                <i class="fas fa-info-circle mt-1 mr-3" style="color: var(--warning);"></i>
                <div class="flex-1">
                    <h4 class="font-medium mb-2" style="color: var(--text-primary);">User Impact:</h4>
                    <p style="color: var(--text-secondary);">{{ $activeMaintenance->user_impact_description }}</p>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ============================================================
         | STATISTICS CARDS
         * ============================================================ --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="card p-4" style="background-color: rgba(var(--primary-rgb), 0.1); border: 1px solid rgba(var(--primary-rgb), 0.2); border-radius: 0.75rem;">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Maintenance</div>
                    <div class="text-2xl font-semibold" style="color: var(--primary);">
                        {{ $statistics['total'] ?? 0 }}
                    </div>
                </div>
                <i class="fas fa-server text-2xl opacity-70" style="color: var(--primary);"></i>
            </div>
        </div>

        <div class="card p-4" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.2); border-radius: 0.75rem;">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Average Duration</div>
                    <div class="text-2xl font-semibold" style="color: var(--success);">
                        {{ $statistics['avg_duration'] ?? 0 }} min
                    </div>
                </div>
                <i class="fas fa-clock text-2xl opacity-70" style="color: var(--success);"></i>
            </div>
        </div>

        <div class="card p-4" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2); border-radius: 0.75rem;">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Currently Active</div>
                    <div class="text-2xl font-semibold" style="color: var(--warning);">
                        {{ $activeMaintenance ? '1 Active' : '0 Active' }}
                    </div>
                </div>
                <i class="fas fa-sync-alt text-2xl opacity-70 {{ $activeMaintenance ? 'animate-spin' : '' }}" style="color: var(--warning);"></i>
            </div>
        </div>
    </div>

    {{-- ============================================================
         | FILTERS
         * ============================================================ --}}
    <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6">
            <form action="{{ route('developer.maintenance.index') }}" method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">Status</label>
                        <select name="status" class="w-full p-2 border rounded-lg"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">All Status</option>
                            @foreach(['draft' => 'Draft', 'scheduled' => 'Scheduled', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label)
                                <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">Type</label>
                        <select name="type" class="w-full p-2 border rounded-lg"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">All Types</option>
                            @foreach(['planned' => 'Planned', 'emergency' => 'Emergency', 'hotfix' => 'Hotfix', 'upgrade' => 'Upgrade', 'security' => 'Security'] as $value => $label)
                                <option value="{{ $value }}" {{ request('type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">Impact Level</label>
                        <select name="impact" class="w-full p-2 border rounded-lg"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">All Impact Levels</option>
                            @foreach(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'] as $value => $label)
                                <option value="{{ $value }}" {{ request('impact') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}"
                               class="w-full p-2 border rounded-lg"
                               placeholder="Search title, description..."
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">From Date</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}"
                               class="w-full p-2 border rounded-lg"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    </div>

                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">To Date</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}"
                               class="w-full p-2 border rounded-lg"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    </div>

                    <div class="flex items-end space-x-2">
                        <button type="submit" class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center flex-1"
                                style="background-color: var(--primary); color: white;">
                            <i class="fas fa-filter mr-2"></i> Apply Filters
                        </button>
                        <a href="{{ route('developer.maintenance.index') }}"
                           class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                           style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                            <i class="fas fa-times mr-2"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ============================================================
         | MAINTENANCE LIST
         * ============================================================ --}}
    <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center flex-wrap gap-2">
                <div>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-list mr-2" style="color: var(--primary);"></i> All Maintenance Events
                    </h3>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Showing {{ $maintenances->firstItem() ?? 0 }} to {{ $maintenances->lastItem() ?? 0 }} of {{ $maintenances->total() }} records
                    </p>
                </div>
                <div class="text-sm" style="color: var(--text-secondary);">
                    {{ $maintenances->total() }} total records
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr style="background-color: var(--bg-secondary);">
                        <th class="p-3 text-left text-xs font-medium uppercase" style="color: var(--text-secondary);">Reference</th>
                        <th class="p-3 text-left text-xs font-medium uppercase" style="color: var(--text-secondary);">Title</th>
                        <th class="p-3 text-left text-xs font-medium uppercase" style="color: var(--text-secondary);">Type</th>
                        <th class="p-3 text-left text-xs font-medium uppercase" style="color: var(--text-secondary);">Impact</th>
                        <th class="p-3 text-left text-xs font-medium uppercase" style="color: var(--text-secondary);">Status</th>
                        <th class="p-3 text-left text-xs font-medium uppercase" style="color: var(--text-secondary);">Schedule</th>
                        <th class="p-3 text-left text-xs font-medium uppercase" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($maintenances as $maintenance)
                        @php
                            $typeStyle = match ($maintenance->maintenance_type) {
                                'emergency' => ['danger',   'var(--danger)'],
                                'hotfix'    => ['warning',  'var(--warning)'],
                                'upgrade'   => ['info',     'var(--info)'],
                                'security'  => ['primary',  'var(--primary)'],
                                default     => ['info',     'var(--info)'],
                            };

                            $impactStyle = match ($maintenance->impact_level) {
                                'critical' => ['danger',   'var(--danger)'],
                                'high'     => ['warning',  'var(--warning)'],
                                'medium'   => ['warning',  'var(--warning)'],
                                default    => ['success',  'var(--success)'],
                            };

                            $statusStyle = match ($maintenance->status) {
                                'in_progress' => ['warning', 'var(--warning)'],
                                'completed'   => ['success', 'var(--success)'],
                                'cancelled'   => ['danger',  'var(--danger)'],
                                'draft'       => ['info',    'var(--info)'],
                                default       => ['info',    'var(--info)'],
                            };
                        @endphp

                        <tr class="border-b transition-colors duration-200"
                            style="border-color: var(--border-color); {{ $maintenance->status === 'in_progress' ? 'background: rgba(var(--warning-rgb), 0.03);' : '' }}">
                            <td class="p-3" style="color: var(--text-primary);">
                                <div class="font-mono text-sm">{{ $maintenance->reference_id }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    {{ $maintenance->created_at->format('M d, Y') }}
                                </div>
                            </td>
                            <td class="p-3">
                                <div class="font-medium" style="color: var(--text-primary);">
                                    {{ Str::limit($maintenance->title, 50) }}
                                </div>
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                    {{ Str::limit($maintenance->description, 70) }}
                                </div>
                                @if($maintenance->is_emergency)
                                    <span class="inline-block px-2 py-0.5 mt-1 text-xs rounded-full"
                                          style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                        Emergency
                                    </span>
                                @endif
                            </td>
                            <td class="p-3">
                                <span class="badge" style="background-color: rgba(var(--{{ $typeStyle[0] }}-rgb), 0.1); color: {{ $typeStyle[1] }}; border: 1px solid rgba(var(--{{ $typeStyle[0] }}-rgb), 0.3);">
                                    {{ str_replace('_', ' ', $maintenance->maintenance_type) }}
                                </span>
                            </td>
                            <td class="p-3">
                                <span class="badge" style="background-color: rgba(var(--{{ $impactStyle[0] }}-rgb), 0.1); color: {{ $impactStyle[1] }}; border: 1px solid rgba(var(--{{ $impactStyle[0] }}-rgb), 0.3);">
                                    {{ $maintenance->impact_level }}
                                </span>
                            </td>
                            <td class="p-3">
                                <span class="badge" style="background-color: rgba(var(--{{ $statusStyle[0] }}-rgb), 0.1); color: {{ $statusStyle[1] }}; border: 1px solid rgba(var(--{{ $statusStyle[0] }}-rgb), 0.3);">
                                    {{ str_replace('_', ' ', $maintenance->status) }}
                                </span>
                                @if($maintenance->status === 'scheduled' && $maintenance->scheduled_start)
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        {{ $maintenance->scheduled_start->diffForHumans() }}
                                    </div>
                                @endif
                            </td>
                            <td class="p-3" style="color: var(--text-primary);">
                                @if($maintenance->status === 'in_progress' && $maintenance->actual_start)
                                    <div class="text-sm">Started: {{ $maintenance->actual_start->format('M d, H:i') }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ $maintenance->actual_start->diffForHumans() }}
                                    </div>
                                @elseif($maintenance->scheduled_start)
                                    <div class="text-sm">{{ $maintenance->scheduled_start->format('M d, H:i') }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ $maintenance->scheduled_start->diffForHumans() }}
                                    </div>
                                @else
                                    <span class="text-sm" style="color: var(--text-secondary);">Not scheduled</span>
                                @endif
                            </td>
                            <td class="p-3">
                                <div class="flex flex-wrap gap-1">
                                    {{-- View --}}
                                    <a href="{{ route('developer.maintenance.show', $maintenance->id) }}"
                                       class="action-btn" title="View Details"
                                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    {{-- Edit --}}
                                    @if(in_array($maintenance->status, ['draft', 'scheduled'], true))
                                        <a href="{{ route('developer.maintenance.edit', $maintenance->id) }}"
                                           class="action-btn" title="Edit"
                                           style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    @endif

                                    {{-- Approve (draft) --}}
                                    @if($maintenance->status === 'draft')
                                        <form action="{{ route('developer.maintenance.approve', $maintenance->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit"
                                                    onclick="return confirm('Approve this maintenance schedule?')"
                                                    class="action-btn" title="Approve"
                                                    style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    @endif

                                    {{-- Start (scheduled) --}}
                                    @if($maintenance->status === 'scheduled')
                                        <form action="{{ route('developer.maintenance.start', $maintenance->id) }}" method="POST" class="inline">
                                            @csrf
                                            <input type="hidden" name="confirm_pre_checks" value="1">
                                            <input type="hidden" name="start_notes" value="Started from index">
                                            <button type="submit"
                                                    onclick="return confirm('Start this maintenance now?')"
                                                    class="action-btn" title="Start Now"
                                                    style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        </form>
                                    @endif

                                    {{-- Complete (in progress) --}}
                                    @if($maintenance->status === 'in_progress')
                                        <button type="button"
                                                onclick="openCompleteModal({{ $maintenance->id }}, {{ $maintenance->estimated_duration_minutes }}, {{ $maintenance->estimated_affected_users ?? 0 }})"
                                                class="action-btn" title="Complete"
                                                style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                            <i class="fas fa-check-circle"></i>
                                        </button>
                                    @endif

                                    {{-- Cancel (draft/scheduled) --}}
                                    @if(in_array($maintenance->status, ['draft', 'scheduled'], true))
                                        <form action="{{ route('developer.maintenance.cancel', $maintenance->id) }}" method="POST" class="inline">
                                            @csrf
                                            <input type="hidden" name="cancellation_reason" value="Cancelled from index">
                                            <button type="submit"
                                                    onclick="return confirm('Cancel this maintenance?')"
                                                    class="action-btn" title="Cancel"
                                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    @endif

                                    {{-- Delete (draft) --}}
                                    @if($maintenance->status === 'draft')
                                        <form action="{{ route('developer.maintenance.destroy', $maintenance->id) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    onclick="return confirm('Are you sure you want to delete this maintenance schedule?')"
                                                    class="action-btn" title="Delete"
                                                    style="background-color: rgba(var(--danger-rgb), 0.2); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center">
                                <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center"
                                     style="background-color: rgba(var(--text-secondary), 0.1);">
                                    <i class="fas fa-tools" style="color: var(--text-secondary); font-size: 1.5rem;"></i>
                                </div>
                                <h4 class="font-medium mb-2" style="color: var(--text-primary);">No Maintenance Found</h4>
                                <p class="text-sm mb-4" style="color: var(--text-secondary);">
                                    {{ request()->hasAny(['status', 'type', 'impact', 'search', 'date_from', 'date_to'])
                                        ? 'No maintenance events match your filters.'
                                        : 'No maintenance events have been scheduled yet.' }}
                                </p>
                                @if(request()->hasAny(['status', 'type', 'impact', 'search', 'date_from', 'date_to']))
                                    <a href="{{ route('developer.maintenance.index') }}"
                                       class="px-4 py-2 rounded-lg text-sm inline-flex items-center"
                                       style="background-color: var(--primary); color: white;">
                                        <i class="fas fa-times mr-2"></i> Clear Filters
                                    </a>
                                @else
                                    <a href="{{ route('developer.maintenance.create') }}"
                                       class="px-4 py-2 rounded-lg text-sm inline-flex items-center"
                                       style="background-color: var(--primary); color: white;">
                                        <i class="fas fa-plus mr-2"></i> Schedule Maintenance
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($maintenances->hasPages())
            <div class="p-4 border-t" style="border-color: var(--border-color);">
                {{ $maintenances->links() }}
            </div>
        @endif
    </div>
</div>

{{-- ============================================================
     | COMPLETE MAINTENANCE MODAL
     * ============================================================ --}}
<div id="completeMaintenanceModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="card w-full max-w-lg" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                    Complete Maintenance
                </h3>
                <button type="button" onclick="closeModal('completeMaintenanceModal')"
                        class="p-2 rounded-lg hover:bg-opacity-20"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="completeMaintenanceForm" method="POST">
                @csrf

                <div class="space-y-4 mb-6">
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                            Completion Notes <span style="color: var(--danger);">*</span>
                        </label>
                        <textarea name="completion_notes" rows="3"
                                  class="w-full p-3 border rounded-lg"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Describe what was done during maintenance..."
                                  required minlength="10"></textarea>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">Minimum 10 characters</div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                                Actual Duration (min) <span style="color: var(--danger);">*</span>
                            </label>
                            <input type="number" name="actual_duration_minutes" id="modalDuration"
                                   min="1" max="1440"
                                   class="w-full p-2 border rounded-lg"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   required>
                        </div>

                        <div>
                            <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                                Affected Users <span style="color: var(--danger);">*</span>
                            </label>
                            <input type="number" name="actual_affected_users" id="modalUsers"
                                   min="0"
                                   class="w-full p-2 border rounded-lg"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                            Downtime Minutes (if any)
                        </label>
                        <input type="number" name="downtime_minutes" min="0"
                               class="w-full p-2 border rounded-lg"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    </div>

                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">
                            Estimated vs Actual <span style="color: var(--danger);">*</span>
                        </label>
                        <select name="estimated_vs_actual" class="w-full p-2 border rounded-lg"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                required>
                            <option value="within_estimate">Within Estimate</option>
                            <option value="under_estimate">Under Estimate</option>
                            <option value="over_estimate">Over Estimate</option>
                        </select>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" id="post_checks_passed" name="post_checks_passed" value="1" required
                               class="h-4 w-4 rounded"
                               style="background-color: var(--bg-secondary); border-color: var(--border-color); color: var(--primary);">
                        <label for="post_checks_passed" class="ml-2 text-sm" style="color: var(--text-secondary);">
                            Confirm post-maintenance checks have passed <span style="color: var(--danger);">*</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeModal('completeMaintenanceModal')"
                            class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                            style="background-color: var(--success); color: white;">
                        <i class="fas fa-check-circle mr-2"></i> Complete Maintenance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* Action button base */
    .action-btn {
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        transition: all 0.2s ease;
        cursor: pointer;
        border: none;
        padding: 0;
    }
    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    /* Badge base */
    .badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.25rem 0.6rem;
        border-radius: 9999px;
        font-size: 0.7rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Table row hover */
    tbody tr {
        transition: background-color 0.2s ease;
    }
    tbody tr:hover {
        background-color: rgba(var(--primary-rgb), 0.03) !important;
    }

    /* Card hover */
    .card {
        transition: box-shadow 0.2s ease;
    }
    .card:hover {
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
    }

    /* Focus rings */
    input:focus, select:focus, textarea:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15);
    }

    /* Responsive table adjustments */
    @media (max-width: 768px) {
        .overflow-x-auto { font-size: 0.85rem; }
        .overflow-x-auto th,
        .overflow-x-auto td { padding: 0.5rem 0.4rem; }
        .action-btn { width: 28px; height: 28px; font-size: 0.8rem; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    /* ---------- Auto-submit filters on select change ---------- */
    document.querySelectorAll('select[name="status"], select[name="type"], select[name="impact"]').forEach(select => {
        select.addEventListener('change', function () {
            this.closest('form').submit();
        });
    });

    /* ---------- Modal open / close ---------- */
    window.openCompleteModal = function (maintenanceId, estimatedDuration, estimatedUsers) {
        const form    = document.getElementById('completeMaintenanceForm');
        const modal   = document.getElementById('completeMaintenanceModal');
        const durEl   = document.getElementById('modalDuration');
        const usersEl = document.getElementById('modalUsers');

        form.action = `/developer/maintenance/${maintenanceId}/complete`;

        // Seed with sensible defaults from the schedule
        if (durEl && !durEl.value) durEl.value = estimatedDuration || 60;
        if (usersEl && !usersEl.value) usersEl.value = estimatedUsers || 0;

        modal.classList.remove('hidden');
    };

    window.closeModal = function (modalId) {
        const el = document.getElementById(modalId);
        if (el) el.classList.add('hidden');
    };

    /* ---------- Close modal on backdrop click ---------- */
    document.querySelectorAll('.fixed.inset-0').forEach(modal => {
        modal.addEventListener('click', function (e) {
            if (e.target === this) this.classList.add('hidden');
        });
    });

    /* ---------- ESC closes modals ---------- */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeModal('completeMaintenanceModal');
        }
    });
})();
</script>
@endpush