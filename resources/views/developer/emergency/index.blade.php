@extends('layouts.dev')

@section('title', 'Emergency Mode Management')

@section('content')
@php
    use App\Models\EmergencyMode;

    // Derive the "active" emergency from the current page of results
    // so we don't run a separate query in the view.
    $activeEmergency = $emergencies->getCollection()->firstWhere('is_active', true);

    $totalEmergencies  = (int) ($statistics['total'] ?? 0);
    $avgDuration       = (float) ($statistics['avg_duration'] ?? 0);
    $criticalCount     = (int) ($statistics['critical_count'] ?? 0);

    $severityColor = function (string $level): array {
        return match ($level) {
            'critical' => ['danger',  'var(--danger)'],
            'high'     => ['warning', 'var(--warning)'],
            'medium'   => ['warning', 'var(--warning)'],
            default    => ['success', 'var(--success)'],
        };
    };

    $statusColor = function (EmergencyMode $m): array {
        if ($m->is_active) {
            return ['danger', 'var(--danger)'];
        }
        return match ($m->status) {
            EmergencyMode::STATUS_ACTIVATING,
            EmergencyMode::STATUS_DEACTIVATING,
            EmergencyMode::STATUS_RECOVERING => ['warning', 'var(--warning)'],
            default                          => ['success', 'var(--success)'],
        };
    };
@endphp

<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- ============================================================
         | HEADER
         * ============================================================ --}}
    <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="flex justify-between items-center p-6 flex-wrap gap-3">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-shield-alt mr-2" style="color: var(--primary);"></i> Emergency Mode Management
                </h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Configure, activate, and monitor emergency response modes.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('developer.emergency.create') }}"
                   class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                   style="background-color: var(--danger); color: white;">
                    <i class="fas fa-plus-circle mr-2"></i> New Emergency Mode
                </a>
                <a href="{{ route('developer.emergency.statistics') }}"
                   class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                   style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-chart-bar mr-2"></i> Statistics
                </a>
            </div>
        </div>
    </div>

    {{-- ============================================================
         | ACTIVE EMERGENCY BANNER
         * ============================================================ --}}
    @if($activeEmergency)
        <div class="card p-6"
             style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;
                    border-left: 6px solid var(--danger); box-shadow: 0 10px 30px rgba(var(--danger-rgb), 0.15);">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div class="flex items-center">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center bg-gradient-to-br from-red-500 to-red-700 animate-pulse mr-4 flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold" style="color: var(--text-primary);">
                            🚨 System Emergency Active
                        </h3>
                        <div class="flex flex-wrap items-center gap-2 mt-2">
                            <span class="badge" style="background-color: rgba(var(--danger-rgb), 0.15); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                <i class="fas fa-exclamation-circle mr-1"></i> Active
                            </span>
                            <span class="badge"
                                  style="background-color: rgba(var(--{{ $severityColor($activeEmergency->severity_level)[0] }}-rgb), 0.15);
                                         color: {{ $severityColor($activeEmergency->severity_level)[1] }};
                                         border: 1px solid rgba(var(--{{ $severityColor($activeEmergency->severity_level)[0] }}-rgb), 0.3);">
                                <i class="fas fa-bolt mr-1"></i>
                                {{ strtoupper($activeEmergency->severity_level) }} Severity
                            </span>
                            <span class="badge" style="background-color: rgba(139, 92, 246, 0.15); color: #8b5cf6; border: 1px solid rgba(139, 92, 246, 0.3);">
                                <i class="fas fa-clock mr-1"></i>
                                {{ $activeEmergency->current_duration }} minutes
                            </span>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button"
                            onclick="EmergencyIndex.openDeactivation({{ $activeEmergency->id }})"
                            class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                            style="background-color: var(--danger); color: white;">
                        <i class="fas fa-power-off mr-2"></i> Deactivate
                    </button>
                    <button type="button"
                            onclick="EmergencyIndex.openExtension({{ $activeEmergency->id }})"
                            class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                        <i class="fas fa-clock mr-2"></i> Extend
                    </button>
                    <a href="{{ route('developer.emergency.show', $activeEmergency->id) }}"
                       class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                       style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i> Details
                    </a>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                    <div class="text-sm" style="color: var(--text-secondary);">Duration</div>
                    <div class="text-2xl font-semibold" style="color: var(--text-primary);">
                        {{ $activeEmergency->current_duration }} min
                    </div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">
                        Started: {{ optional($activeEmergency->activated_at)->format('M d, H:i') ?? '—' }}
                    </div>
                </div>

                <div class="p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                    <div class="text-sm" style="color: var(--text-secondary);">Affected Users</div>
                    <div class="text-2xl font-semibold" style="color: var(--text-primary);">
                        {{ number_format((int) ($activeEmergency->affected_users_count ?? 0)) }}
                    </div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">
                        {{ collect($activeEmergency->affected_modules ?? [])
                            ->map(fn ($m) => str_replace('_', ' ', ucfirst($m)))
                            ->take(3)
                            ->implode(', ') ?: 'No modules specified' }}
                        @if(count($activeEmergency->affected_modules ?? []) > 3)
                            +{{ count($activeEmergency->affected_modules) - 3 }} more
                        @endif
                    </div>
                </div>

                <div class="p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                    <div class="text-sm" style="color: var(--text-secondary);">Activated By</div>
                    <div class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-shield mr-2"></i>
                        {{ $activeEmergency->activatedByUser?->name ?? 'System' }}
                    </div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">
                        {{ optional($activeEmergency->activated_at)->format('M d, H:i') ?? '—' }}
                    </div>
                </div>
            </div>

            <div class="mt-6 p-4 rounded-lg" style="background: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-circle mt-1 mr-3" style="color: var(--danger);"></i>
                    <div class="flex-1 min-w-0">
                        <h4 class="font-medium mb-2" style="color: var(--text-primary);">Emergency Reason:</h4>
                        <p style="color: var(--text-secondary);">{{ $activeEmergency->reason }}</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================================
         | STATISTICS CARDS
         * ============================================================ --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card p-4" style="background-color: rgba(var(--primary-rgb), 0.1); border: 1px solid rgba(var(--primary-rgb), 0.2); border-radius: 0.75rem; color: var(--primary);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Emergencies</div>
                    <div class="text-2xl font-semibold">{{ number_format($totalEmergencies) }}</div>
                </div>
                <i class="fas fa-shield-alt text-2xl opacity-70"></i>
            </div>
        </div>

        <div class="card p-4" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2); border-radius: 0.75rem; color: var(--warning);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Currently Active</div>
                    <div class="text-2xl font-semibold">{{ $activeEmergency ? '1 Active' : '0 Active' }}</div>
                </div>
                <i class="fas fa-exclamation-triangle text-2xl opacity-70 {{ $activeEmergency ? 'animate-pulse' : '' }}"></i>
            </div>
        </div>

        <div class="card p-4" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.2); border-radius: 0.75rem; color: var(--danger);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Average Duration</div>
                    <div class="text-2xl font-semibold">{{ round($avgDuration) }} min</div>
                </div>
                <i class="fas fa-clock text-2xl opacity-70"></i>
            </div>
        </div>

        <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.2); border-radius: 0.75rem; color: var(--info);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Critical Severity</div>
                    <div class="text-2xl font-semibold">{{ number_format($criticalCount) }}</div>
                </div>
                <i class="fas fa-skull-crossbones text-2xl opacity-70"></i>
            </div>
        </div>
    </div>

    {{-- ============================================================
         | FILTERS
         * ============================================================ --}}
    <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6">
            <form action="{{ route('developer.emergency.index') }}" method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">Status</label>
                        <select name="status" class="w-full p-2 border rounded-lg"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">All Status</option>
                            @foreach([
                                'inactive'     => 'Inactive',
                                'activating'   => 'Activating',
                                'active'       => 'Active',
                                'deactivating' => 'Deactivating',
                                'recovering'   => 'Recovering',
                            ] as $value => $label)
                                <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">Severity Level</label>
                        <select name="severity" class="w-full p-2 border rounded-lg"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">All Severities</option>
                            @foreach(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'] as $value => $label)
                                <option value="{{ $value }}" {{ request('severity') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm mb-2" style="color: var(--text-secondary);">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}"
                               class="w-full p-2 border rounded-lg"
                               placeholder="Search name, reference ID, reason..."
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    </div>

                    <div class="flex items-end space-x-2">
                        <button type="submit"
                                class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center flex-1"
                                style="background-color: var(--primary); color: white;">
                            <i class="fas fa-filter mr-2"></i> Apply Filters
                        </button>
                        <a href="{{ route('developer.emergency.index') }}"
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
         | EMERGENCY MODES LIST
         * ============================================================ --}}
    <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center flex-wrap gap-2">
                <div>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-list mr-2" style="color: var(--primary);"></i> All Emergency Modes
                    </h3>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Showing {{ $emergencies->firstItem() ?? 0 }}–{{ $emergencies->lastItem() ?? 0 }} of {{ $emergencies->total() }} records
                    </p>
                </div>
                <div class="text-sm" style="color: var(--text-secondary);">
                    {{ $emergencies->total() }} total records
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr style="background-color: var(--bg-secondary);">
                        <th class="p-3 text-left text-xs font-medium uppercase" style="color: var(--text-secondary);">Reference</th>
                        <th class="p-3 text-left text-xs font-medium uppercase" style="color: var(--text-secondary);">Name</th>
                        <th class="p-3 text-left text-xs font-medium uppercase" style="color: var(--text-secondary);">Severity</th>
                        <th class="p-3 text-left text-xs font-medium uppercase" style="color: var(--text-secondary);">Status</th>
                        <th class="p-3 text-left text-xs font-medium uppercase" style="color: var(--text-secondary);">Created</th>
                        <th class="p-3 text-left text-xs font-medium uppercase" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($emergencies as $emergency)
                        @php
                            [$sevKey, $sevColor]   = $severityColor($emergency->severity_level);
                            [$stKey,  $stColor]    = $statusColor($emergency);
                        @endphp
                        <tr class="border-b transition-colors duration-200"
                            style="border-color: var(--border-color); {{ $emergency->is_active ? 'background: rgba(var(--danger-rgb), 0.03);' : '' }}">
                            <td class="p-3" style="color: var(--text-primary);">
                                <div class="font-mono text-sm">{{ $emergency->reference_id }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    {{ $emergency->created_at->format('M d, Y') }}
                                </div>
                            </td>
                            <td class="p-3">
                                <div class="font-medium" style="color: var(--text-primary);">
                                    {{ Str::limit($emergency->name, 50) }}
                                </div>
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                    {{ Str::limit((string) $emergency->reason, 70) }}
                                </div>
                                @if($emergency->isScheduled() && $emergency->scheduled_start)
                                    <span class="badge mt-1" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                        <i class="fas fa-calendar mr-1"></i>
                                        {{ $emergency->scheduled_start->format('M d, H:i') }}
                                    </span>
                                @endif
                            </td>
                            <td class="p-3">
                                <span class="badge"
                                      style="background-color: rgba(var(--{{ $sevKey }}-rgb), 0.1); color: {{ $sevColor }}; border: 1px solid rgba(var(--{{ $sevKey }}-rgb), 0.3);">
                                    {{ ucfirst($emergency->severity_level) }}
                                </span>
                                @if(($emergency->affected_users_count ?? 0) > 0)
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-users mr-1"></i>
                                        {{ number_format($emergency->affected_users_count) }} users
                                    </div>
                                @endif
                            </td>
                            <td class="p-3">
                                <span class="badge"
                                      style="background-color: rgba(var(--{{ $stKey }}-rgb), 0.1); color: {{ $stColor }}; border: 1px solid rgba(var(--{{ $stKey }}-rgb), 0.3);">
                                    {{ str_replace('_', ' ', $emergency->status) }}
                                </span>
                                @if($emergency->activated_at)
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        {{ $emergency->activated_at->format('M d, H:i') }}
                                    </div>
                                @endif
                            </td>
                            <td class="p-3" style="color: var(--text-primary);">
                                <div class="text-sm">{{ $emergency->created_at->format('M d, Y') }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    {{ $emergency->created_at->format('H:i') }}
                                </div>
                                @if($emergency->activatedByUser)
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-user mr-1"></i>
                                        {{ $emergency->activatedByUser->name }}
                                    </div>
                                @endif
                            </td>
                            <td class="p-3">
                                <div class="flex flex-wrap gap-1">
                                    {{-- View --}}
                                    <a href="{{ route('developer.emergency.show', $emergency->id) }}"
                                       class="action-btn"
                                       style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                       title="View Details"
                                       aria-label="View details">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    {{-- Activate (inactive only) --}}
                                    @if(!$emergency->is_active && $emergency->status === EmergencyMode::STATUS_INACTIVE)
                                        <form action="{{ route('developer.emergency.activate', $emergency->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit"
                                                    onclick="return confirm('Activate this emergency mode?')"
                                                    class="action-btn"
                                                    style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);"
                                                    title="Activate Now"
                                                    aria-label="Activate now">
                                                <i class="fas fa-power-off"></i>
                                            </button>
                                        </form>
                                    @endif

                                    {{-- Deactivate (active only) --}}
                                    @if($emergency->is_active)
                                        <button type="button"
                                                onclick="EmergencyIndex.openDeactivation({{ $emergency->id }})"
                                                class="action-btn"
                                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                                                title="Deactivate"
                                                aria-label="Deactivate">
                                            <i class="fas fa-stop-circle"></i>
                                        </button>
                                    @endif

                                    {{-- Extend (active only) --}}
                                    @if($emergency->is_active)
                                        <button type="button"
                                                onclick="EmergencyIndex.openExtension({{ $emergency->id }})"
                                                class="action-btn"
                                                style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);"
                                                title="Extend Duration"
                                                aria-label="Extend duration">
                                            <i class="fas fa-clock"></i>
                                        </button>
                                    @endif

                                    {{-- Cancel (scheduled only) --}}
                                    @if($emergency->isScheduled())
                                        <button type="button"
                                                onclick="EmergencyIndex.openCancel({{ $emergency->id }})"
                                                class="action-btn"
                                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                                                title="Cancel Scheduled"
                                                aria-label="Cancel scheduled">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center">
                                <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center"
                                     style="background-color: rgba(var(--text-secondary), 0.1);">
                                    <i class="fas fa-shield-alt" style="color: var(--text-secondary); font-size: 1.5rem;"></i>
                                </div>
                                <h4 class="font-medium mb-2" style="color: var(--text-primary);">No Emergency Modes Found</h4>
                                <p class="text-sm mb-4" style="color: var(--text-secondary);">
                                    {{ request()->hasAny(['status', 'severity', 'search'])
                                        ? 'No emergency modes match your filters.'
                                        : 'No emergency modes have been created yet.' }}
                                </p>
                                @if(request()->hasAny(['status', 'severity', 'search']))
                                    <a href="{{ route('developer.emergency.index') }}"
                                       class="px-4 py-2 rounded-lg text-sm inline-flex items-center"
                                       style="background-color: var(--primary); color: white;">
                                        <i class="fas fa-times mr-2"></i> Clear Filters
                                    </a>
                                @else
                                    <a href="{{ route('developer.emergency.create') }}"
                                       class="px-4 py-2 rounded-lg text-sm inline-flex items-center"
                                       style="background-color: var(--danger); color: white;">
                                        <i class="fas fa-plus-circle mr-2"></i> Create Emergency Mode
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($emergencies->hasPages())
            <div class="p-4 border-t" style="border-color: var(--border-color);">
                {{ $emergencies->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>

{{-- ============================================================
     | DEACTIVATION MODAL
     * ============================================================ --}}
<div id="deactivationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4"
     role="dialog" aria-modal="true" aria-labelledby="deactivationTitle">
    <div class="card w-full max-w-lg" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 id="deactivationTitle" class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-power-off mr-2" style="color: var(--danger);"></i> Deactivate Emergency Mode
                </h3>
                <button type="button" onclick="EmergencyIndex.closeModal('deactivationModal')"
                        class="p-2 rounded-lg hover:bg-opacity-20"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                        aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                <div class="flex">
                    <i class="fas fa-exclamation-circle mt-1 mr-3" style="color: var(--danger);"></i>
                    <div>
                        <h4 class="font-medium mb-1" style="color: var(--danger);">Warning</h4>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            This will deactivate the emergency mode and restore normal system operations.
                        </p>
                    </div>
                </div>
            </div>

            <form id="deactivationForm" method="POST">
                @csrf

                <div class="space-y-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            Deactivation Reason <span class="text-red-500">*</span>
                        </label>
                        <textarea name="deactivation_reason" rows="3" maxlength="500"
                                  class="w-full p-3 border rounded-lg"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Why are you deactivating this emergency mode?"
                                  required minlength="10"></textarea>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">Minimum 10 characters</div>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" id="confirmDeactivation" name="confirm" value="1" required
                               class="h-4 w-4 rounded"
                               style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                        <label for="confirmDeactivation" class="ml-2 text-sm" style="color: var(--text-secondary);">
                            I confirm that I want to deactivate this emergency mode <span class="text-red-500">*</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="EmergencyIndex.closeModal('deactivationModal')"
                            class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                            style="background-color: var(--danger); color: white;">
                        <i class="fas fa-power-off mr-2"></i> Deactivate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============================================================
     | EXTENSION MODAL
     * ============================================================ --}}
<div id="extensionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4"
     role="dialog" aria-modal="true" aria-labelledby="extensionTitle">
    <div class="card w-full max-w-lg" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 id="extensionTitle" class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-clock mr-2" style="color: var(--primary);"></i> Extend Emergency Mode
                </h3>
                <button type="button" onclick="EmergencyIndex.closeModal('extensionModal')"
                        class="p-2 rounded-lg hover:bg-opacity-20"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                        aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="extensionForm" method="POST">
                @csrf

                <div class="space-y-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            Extension Reason <span class="text-red-500">*</span>
                        </label>
                        <textarea name="extension_reason" rows="3" maxlength="500"
                                  class="w-full p-3 border rounded-lg"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Why do you need to extend this emergency mode?"
                                  required minlength="10"></textarea>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">Minimum 10 characters</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            Additional Minutes <span class="text-red-500">*</span>
                        </label>
                        <div class="flex items-center space-x-4">
                            <input type="range" id="extension_slider" name="extension_slider"
                                   min="15" max="1440" value="60"
                                   class="flex-1 h-2 rounded-lg appearance-none cursor-pointer"
                                   style="background-color: var(--border-color);">
                            <input type="number" id="extension_minutes" name="extension_minutes"
                                   value="60"
                                   class="w-24 p-2 border rounded-lg text-center"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   min="15" max="1440" required>
                            <span class="text-sm" style="color: var(--text-secondary);">minutes</span>
                        </div>
                        <div class="flex justify-between items-center mt-2 flex-wrap gap-2">
                            <span class="text-xs" style="color: var(--text-secondary);">Quick select:</span>
                            <div class="space-x-2">
                                @foreach([30 => '30 min', 60 => '1 hour', 120 => '2 hours', 240 => '4 hours'] as $mins => $label)
                                    <button type="button" onclick="EmergencyIndex.setExtension({{ $mins }})"
                                            class="text-xs px-2 py-1 rounded border hover:bg-opacity-10"
                                            style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border-color: rgba(var(--primary-rgb), 0.3);">
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="EmergencyIndex.closeModal('extensionModal')"
                            class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                            style="background-color: var(--primary); color: white;">
                        <i class="fas fa-clock mr-2"></i> Extend
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============================================================
     | CANCEL SCHEDULED MODAL
     * ============================================================ --}}
<div id="cancelModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4"
     role="dialog" aria-modal="true" aria-labelledby="cancelTitle">
    <div class="card w-full max-w-lg" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 id="cancelTitle" class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i> Cancel Scheduled Emergency
                </h3>
                <button type="button" onclick="EmergencyIndex.closeModal('cancelModal')"
                        class="p-2 rounded-lg hover:bg-opacity-20"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                        aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                This will cancel the scheduled activation. The emergency mode will remain configured but inactive.
            </p>

            <form id="cancelForm" method="POST">
                @csrf

                <div class="mb-6">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Cancellation Reason <span class="text-red-500">*</span>
                    </label>
                    <textarea name="cancellation_reason" rows="3" maxlength="500"
                              class="w-full p-3 border rounded-lg"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                              placeholder="Why are you cancelling this scheduled emergency mode?"
                              required minlength="10"></textarea>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Minimum 10 characters</div>
                </div>

                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="EmergencyIndex.closeModal('cancelModal')"
                            class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                            style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                        Keep Schedule
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                            style="background-color: var(--danger); color: white;">
                        <i class="fas fa-times mr-2"></i> Cancel Emergency
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.25rem 0.6rem;
        border-radius: 9999px;
        font-size: 0.7rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

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

    input[type="range"] {
        -webkit-appearance: none;
        height: 6px;
        border-radius: 3px;
        outline: none;
    }
    input[type="range"]::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background-color: var(--primary);
        cursor: pointer;
        border: 2px solid white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }
    input[type="range"]::-moz-range-thumb {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background-color: var(--primary);
        cursor: pointer;
        border: 2px solid white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    tbody tr { transition: background-color 0.2s ease; }
    tbody tr:hover { background-color: rgba(var(--primary-rgb), 0.03) !important; }

    .card { transition: box-shadow 0.2s ease; }
    .card:hover { box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08); }

    input:focus, select:focus, textarea:focus, button:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15);
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    .animate-pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }

    @media (prefers-reduced-motion: reduce) {
        .animate-pulse, .action-btn, .card { animation: none !important; transition: none !important; }
    }

    @media (max-width: 768px) {
        .overflow-x-auto { font-size: 0.85rem; }
        .overflow-x-auto th,
        .overflow-x-auto td { padding: 0.5rem 0.4rem; }
        .action-btn { width: 28px; height: 28px; font-size: 0.8rem; }
        .card { border-radius: 0.5rem; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    const ROUTES = {
        deactivate: @json(route('developer.emergency.deactivate', ['emergency' => '__ID__'])),
        extend:     @json(route('developer.emergency.extend',     ['emergency' => '__ID__'])),
        cancel:     @json(route('developer.emergency.cancel',     ['emergency' => '__ID__'])),
    };

    function buildUrl(template, id) {
        return template.replace('__ID__', id);
    }

    /* ---------- Modal openers ---------- */
    function openDeactivation(id) {
        document.getElementById('deactivationForm').action = buildUrl(ROUTES.deactivate, id);
        document.getElementById('deactivationModal').classList.remove('hidden');
    }

    function openExtension(id) {
        document.getElementById('extensionForm').action = buildUrl(ROUTES.extend, id);
        document.getElementById('extensionModal').classList.remove('hidden');
    }

    function openCancel(id) {
        document.getElementById('cancelForm').action = buildUrl(ROUTES.cancel, id);
        document.getElementById('cancelModal').classList.remove('hidden');
    }

    function closeModal(id) {
        const el = document.getElementById(id);
        if (el) el.classList.add('hidden');
    }

    /* ---------- Extension slider sync ---------- */
    function setExtension(minutes) {
        const slider = document.getElementById('extension_slider');
        const input  = document.getElementById('extension_minutes');
        if (slider) slider.value = minutes;
        if (input)  input.value  = minutes;
    }

    /* ---------- Auto-submit filters ---------- */
    function bindFilters() {
        document.querySelectorAll('select[name="status"], select[name="severity"]').forEach(select => {
            select.addEventListener('change', function () {
                this.closest('form').submit();
            });
        });
    }

    /* ---------- Init ---------- */
    document.addEventListener('DOMContentLoaded', function () {
        bindFilters();

        const slider = document.getElementById('extension_slider');
        const input  = document.getElementById('extension_minutes');
        if (slider && input) {
            slider.addEventListener('input', function () { input.value = this.value; });
            input.addEventListener('input', function () { slider.value = this.value; });
        }

        // Backdrop click closes modals
        document.querySelectorAll('.fixed.inset-0').forEach(modal => {
            modal.addEventListener('click', function (e) {
                if (e.target === this) this.classList.add('hidden');
            });
        });

        // Escape closes modals
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeModal('deactivationModal');
                closeModal('extensionModal');
                closeModal('cancelModal');
            }
        });
    });

    /* ---------- Public API ---------- */
    window.EmergencyIndex = {
        openDeactivation,
        openExtension,
        openCancel,
        closeModal,
        setExtension,
    };
})();
</script>
@endpush