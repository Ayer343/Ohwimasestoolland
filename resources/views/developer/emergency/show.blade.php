@extends('layouts.dev')

@section('title', 'Emergency Mode Details')

@section('content')
@php
    use App\Models\EmergencyMode;
    use App\Models\User;

    $severityColor = match ($emergencyMode->severity_level) {
        'critical' => ['danger',  'var(--danger)'],
        'high'     => ['warning', 'var(--warning)'],
        'medium'   => ['warning', 'var(--warning)'],
        default    => ['success', 'var(--success)'],
    };

    $statusColor = $emergencyMode->is_active
        ? ['danger',  'var(--danger)']
        : match ($emergencyMode->status) {
            EmergencyMode::STATUS_ACTIVATING,
            EmergencyMode::STATUS_DEACTIVATING,
            EmergencyMode::STATUS_RECOVERING => ['warning', 'var(--warning)'],
            default                          => ['success', 'var(--success)'],
        };

    $userTypeLabels = User::getUserTypes();
    $recoveryChecks = $emergencyMode->recovery_checks ?? [];
@endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ============================================================
         | LEFT COLUMN
         * ============================================================ --}}
    <div class="lg:col-span-2 space-y-6">

        {{-- Header Card --}}
        <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center mb-2 flex-wrap gap-2">
                            <h2 class="text-xl font-semibold mr-2" style="color: var(--text-primary);">
                                {{ $emergencyMode->name }}
                            </h2>
                            <span class="badge"
                                  style="background-color: rgba(var(--{{ $statusColor[0] }}-rgb), 0.15); color: {{ $statusColor[1] }}; border: 1px solid rgba(var(--{{ $statusColor[0] }}-rgb), 0.3);">
                                {{ str_replace('_', ' ', $emergencyMode->status) }}
                            </span>
                            <span class="badge"
                                  style="background-color: rgba(var(--{{ $severityColor[0] }}-rgb), 0.15); color: {{ $severityColor[1] }}; border: 1px solid rgba(var(--{{ $severityColor[0] }}-rgb), 0.3);">
                                {{ ucfirst($emergencyMode->severity_level) }}
                            </span>
                        </div>
                        <div class="flex items-center flex-wrap gap-4 text-sm" style="color: var(--text-secondary);">
                            <div class="flex items-center">
                                <i class="fas fa-hashtag mr-2"></i>
                                <span class="font-mono">{{ $emergencyMode->reference_id }}</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span>Created: {{ $emergencyMode->created_at->format('M d, Y H:i') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('developer.emergency.index') }}"
                           class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                           style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                            <i class="fas fa-arrow-left mr-2"></i> Back
                        </a>

                        @if(!$emergencyMode->is_active && $emergencyMode->status === EmergencyMode::STATUS_INACTIVE)
                            <form action="{{ route('developer.emergency.activate', $emergencyMode->id) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit"
                                        onclick="return confirm('Activate this emergency mode?')"
                                        class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                                        style="background-color: var(--warning); color: white;">
                                    <i class="fas fa-power-off mr-2"></i> Activate Now
                                </button>
                            </form>
                        @endif

                        @if($emergencyMode->is_active)
                            <button type="button" onclick="EmergencyShow.openDeactivation()"
                                    class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                                    style="background-color: var(--danger); color: white;">
                                <i class="fas fa-stop-circle mr-2"></i> Deactivate
                            </button>
                            <button type="button" onclick="EmergencyShow.openExtension()"
                                    class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                                    style="background-color: var(--primary); color: white;">
                                <i class="fas fa-clock mr-2"></i> Extend
                            </button>
                        @endif

                        @if($emergencyMode->isScheduled())
                            <button type="button" onclick="EmergencyShow.openCancel()"
                                    class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                                    style="background-color: var(--danger); color: white;">
                                <i class="fas fa-times mr-2"></i> Cancel
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            @if($emergencyMode->is_active)
                <div class="p-6" style="background-color: rgba(var(--danger-rgb), 0.03);">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                            <div class="text-sm" style="color: var(--text-secondary);">Duration</div>
                            <div class="text-2xl font-semibold" style="color: var(--text-primary);">
                                {{ $emergencyMode->current_duration }} min
                            </div>
                            <div class="text-sm mt-1" style="color: var(--text-secondary);">
                                @if($emergencyMode->activated_at)
                                    Started: {{ $emergencyMode->activated_at->format('M d, H:i') }}
                                @endif
                            </div>
                        </div>

                        <div class="p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                            <div class="text-sm" style="color: var(--text-secondary);">Affected Users</div>
                            <div class="text-2xl font-semibold" style="color: var(--text-primary);">
                                {{ number_format((int) ($emergencyMode->affected_users_count ?? 0)) }}
                            </div>
                            <div class="text-sm mt-1" style="color: var(--text-secondary);">
                                <a href="#affected-users" class="hover:underline">View affected users →</a>
                            </div>
                        </div>

                        <div class="p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                            <div class="text-sm" style="color: var(--text-secondary);">Status</div>
                            <div class="text-xl font-semibold flex items-center" style="color: {{ $statusColor[1] }};">
                                @if($emergencyMode->is_active)
                                    <i class="fas fa-exclamation-triangle mr-2 animate-pulse"></i> ACTIVE
                                @else
                                    <i class="fas fa-check-circle mr-2"></i> INACTIVE
                                @endif
                            </div>
                            <div class="text-sm mt-1" style="color: var(--text-secondary);">
                                @if($emergencyMode->last_extended_at)
                                    Last extended: {{ $emergencyMode->last_extended_at->format('M d, H:i') }}
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Emergency Details Card --}}
        <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> Emergency Details
                </h3>
            </div>
            <div class="p-6 space-y-6">

                {{-- Reason --}}
                <div>
                    <h4 class="font-medium mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-exclamation-circle mr-2" style="color: var(--danger);"></i>
                        Emergency Reason
                    </h4>
                    <div class="p-4 rounded-lg border" style="background-color: rgba(var(--danger-rgb), 0.05); border-color: rgba(var(--danger-rgb), 0.2);">
                        <p style="color: var(--text-primary);">{{ $emergencyMode->reason }}</p>
                    </div>
                </div>

                {{-- Description --}}
                @if($emergencyMode->description)
                    <div>
                        <h4 class="font-medium mb-2" style="color: var(--text-primary);">
                            <i class="fas fa-align-left mr-2"></i> Description
                        </h4>
                        <div class="p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                            <p style="color: var(--text-primary);">{{ $emergencyMode->description }}</p>
                        </div>
                    </div>
                @endif

                {{-- Affected Modules --}}
                <div>
                    <h4 class="font-medium mb-3" style="color: var(--text-primary);">
                        <i class="fas fa-cogs mr-2"></i>
                        Affected Modules
                        <span class="text-sm font-normal ml-2" style="color: var(--text-secondary);">
                            ({{ count($emergencyMode->affected_modules ?? []) }} modules)
                        </span>
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @forelse($emergencyMode->affected_modules_list ?? [] as $module)
                            <div class="p-3 rounded-lg border flex items-center"
                                 style="background-color: rgba(var(--warning-rgb), 0.05); border-color: rgba(var(--warning-rgb), 0.2);">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                                     style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                    <i class="fas fa-ban"></i>
                                </div>
                                <span style="color: var(--text-primary);">{{ $module }}</span>
                            </div>
                        @empty
                            <p class="text-sm" style="color: var(--text-secondary);">No modules affected</p>
                        @endforelse
                    </div>
                </div>

                {{-- Restricted Features --}}
                @if(!empty($emergencyMode->restricted_features))
                    <div>
                        <h4 class="font-medium mb-3" style="color: var(--text-primary);">
                            <i class="fas fa-lock mr-2"></i> Restricted Features
                        </h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach($emergencyMode->restricted_features as $feature)
                                <span class="badge"
                                      style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                    <i class="fas fa-times-circle mr-1"></i>
                                    {{ str_replace('_', ' ', ucfirst($feature)) }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Allowed Operations --}}
                @if(!empty($emergencyMode->allowed_operations))
                    <div>
                        <h4 class="font-medium mb-3" style="color: var(--text-primary);">
                            <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                            Allowed Operations
                        </h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach($emergencyMode->allowed_operations as $operation)
                                <span class="badge"
                                      style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                    <i class="fas fa-check mr-1"></i>
                                    {{ str_replace('_', ' ', ucfirst($operation)) }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Notification + Recovery --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="font-medium mb-3" style="color: var(--text-primary);">
                            <i class="fas fa-bell mr-2"></i> Notification Settings
                        </h4>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                                     style="background-color: {{ $emergencyMode->notify_users ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--text-secondary), 0.1)' }};">
                                    <i class="fas {{ $emergencyMode->notify_users ? 'fa-check' : 'fa-times' }} text-sm"
                                       style="color: {{ $emergencyMode->notify_users ? 'var(--success)' : 'var(--text-secondary)' }};"></i>
                                </div>
                                <span style="color: var(--text-primary);">
                                    Notify Users: {{ $emergencyMode->notify_users ? 'Yes' : 'No' }}
                                </span>
                            </div>

                            @if($emergencyMode->notify_users && !empty($emergencyMode->notification_channels))
                                <div class="ml-9">
                                    <span class="text-sm" style="color: var(--text-secondary);">Channels:</span>
                                    <div class="flex flex-wrap gap-1 mt-1">
                                        @foreach($emergencyMode->notification_channels as $channel)
                                            <span class="badge" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                {{ $channel }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if($emergencyMode->last_notified_at)
                                <div class="ml-9 text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-clock mr-1"></i>
                                    Last notified: {{ $emergencyMode->last_notified_at->diffForHumans() }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <div>
                        <h4 class="font-medium mb-3" style="color: var(--text-primary);">
                            <i class="fas fa-shield-alt mr-2"></i> Recovery Settings
                        </h4>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                                     style="background-color: {{ $emergencyMode->auto_recovery ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--text-secondary), 0.1)' }};">
                                    <i class="fas {{ $emergencyMode->auto_recovery ? 'fa-check' : 'fa-times' }} text-sm"
                                       style="color: {{ $emergencyMode->auto_recovery ? 'var(--success)' : 'var(--text-secondary)' }};"></i>
                                </div>
                                <span style="color: var(--text-primary);">
                                    Auto-Recovery: {{ $emergencyMode->auto_recovery ? 'Enabled' : 'Disabled' }}
                                </span>
                            </div>

                            @if($emergencyMode->auto_recovery && !empty($recoveryChecks))
                                <div class="ml-9">
                                    <span class="text-sm" style="color: var(--text-secondary);">Checks:</span>
                                    <div class="flex flex-wrap gap-1 mt-1">
                                        @foreach($recoveryChecks as $check)
                                            <span class="badge" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                {{ $check }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Timeline --}}
                <div>
                    <h4 class="font-medium mb-4" style="color: var(--text-primary);">
                        <i class="fas fa-history mr-2"></i> Emergency Timeline
                    </h4>
                    <div class="relative">
                        <div class="absolute left-4 top-0 bottom-0 w-0.5" style="background-color: var(--border-color);"></div>

                        <div class="space-y-6 ml-10">
                            {{-- Created --}}
                            <div class="flex items-start relative">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center absolute left-[-40px]"
                                     style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 2px solid var(--card-bg); z-index: 10;">
                                    <i class="fas fa-plus text-xs"></i>
                                </div>
                                <div>
                                    <div class="font-medium" style="color: var(--text-primary);">Emergency Created</div>
                                    <div class="text-sm" style="color: var(--text-secondary);">
                                        {{ $emergencyMode->created_at->format('M d, Y H:i') }}
                                    </div>
                                </div>
                            </div>

                            {{-- Activated --}}
                            @if($emergencyMode->activated_at)
                                <div class="flex items-start relative">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center absolute left-[-40px]"
                                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 2px solid var(--card-bg); z-index: 10;">
                                        <i class="fas fa-play text-xs"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium" style="color: var(--text-primary);">Emergency Activated</div>
                                        <div class="text-sm" style="color: var(--text-secondary);">
                                            {{ $emergencyMode->activated_at->format('M d, Y H:i') }}
                                        </div>
                                        @if($emergencyMode->activatedByUser)
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                By: {{ $emergencyMode->activatedByUser->name }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- Extended --}}
                            @if($emergencyMode->last_extended_at)
                                <div class="flex items-start relative">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center absolute left-[-40px]"
                                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 2px solid var(--card-bg); z-index: 10;">
                                        <i class="fas fa-clock text-xs"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium" style="color: var(--text-primary);">Duration Extended</div>
                                        <div class="text-sm" style="color: var(--text-secondary);">
                                            {{ $emergencyMode->last_extended_at->format('M d, Y H:i') }}
                                        </div>
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            Total extensions: {{ $emergencyMode->extensions_count ?? 0 }}
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- Deactivated --}}
                            @if($emergencyMode->deactivated_at)
                                <div class="flex items-start relative">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center absolute left-[-40px]"
                                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 2px solid var(--card-bg); z-index: 10;">
                                        <i class="fas fa-stop text-xs"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium" style="color: var(--text-primary);">Emergency Deactivated</div>
                                        <div class="text-sm" style="color: var(--text-secondary);">
                                            {{ $emergencyMode->deactivated_at->format('M d, Y H:i') }}
                                        </div>
                                        @if($emergencyMode->deactivatedByUser)
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                By: {{ $emergencyMode->deactivatedByUser->name }}
                                            </div>
                                        @endif
                                        @if($emergencyMode->deactivation_reason)
                                            <div class="text-xs mt-2 p-2 rounded border" style="color: var(--text-secondary); border-color: var(--border-color);">
                                                <strong>Reason:</strong> {{ $emergencyMode->deactivation_reason }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Activity Logs --}}
        <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-history mr-2" style="color: var(--primary);"></i> Recent Activity Logs
                    </h3>
                    <a href="{{ route('developer.emergency.history', $emergencyMode->id) }}"
                       class="text-sm flex items-center hover:underline" style="color: var(--primary);">
                        View All Logs <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            <div class="p-6">
                @if($recentLogs && $recentLogs->count() > 0)
                    <div class="space-y-4">
                        @foreach($recentLogs as $log)
                            @php
                                $logStyle = match (true) {
                                    str_contains($log->action, 'activat')    => ['warning', 'fa-play'],
                                    str_contains($log->action, 'deactivat')  => ['success', 'fa-stop'],
                                    str_contains($log->action, 'extend')     => ['primary', 'fa-clock'],
                                    str_contains($log->action, 'cancel')     => ['danger',  'fa-times'],
                                    str_contains($log->action, 'fail')       => ['danger',  'fa-exclamation-triangle'],
                                    str_contains($log->action, 'creat')      => ['info',    'fa-plus'],
                                    default                                  => ['info',    'fa-info-circle'],
                                };
                            @endphp
                            <div class="p-4 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <div class="flex justify-between items-start mb-2 flex-wrap gap-2">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                                             style="background-color: rgba(var(--{{ $logStyle[0] }}-rgb), 0.1); color: var(--{{ $logStyle[0] }});">
                                            <i class="fas {{ $logStyle[1] }}"></i>
                                        </div>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">
                                                {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                                            </div>
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                {{ $log->created_at->format('M d, Y H:i:s') }}
                                            </div>
                                        </div>
                                    </div>
                                    @if($log->performer)
                                        <div class="badge" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                            {{ $log->performer->name }}
                                        </div>
                                    @endif
                                </div>

                                <div class="text-sm mt-2" style="color: var(--text-primary);">
                                    {{ $log->details }}
                                </div>

                                @if($log->metadata && is_array($log->metadata))
                                    <div class="mt-3 pt-3 border-t" style="border-color: var(--border-color);">
                                        <div class="text-xs" style="color: var(--text-secondary);">Details:</div>
                                        <div class="text-xs mt-1 space-y-1">
                                            @foreach($log->metadata as $key => $value)
                                                <div>
                                                    <span class="font-medium" style="color: var(--text-secondary);">
                                                        {{ ucfirst(str_replace('_', ' ', $key)) }}:
                                                    </span>
                                                    <span style="color: var(--text-primary);">
                                                        @if(is_array($value))
                                                            {{ json_encode($value) }}
                                                        @else
                                                            {{ $value }}
                                                        @endif
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--text-secondary), 0.1);">
                            <i class="fas fa-history" style="color: var(--text-secondary); font-size: 1.5rem;"></i>
                        </div>
                        <h4 class="font-medium mb-2" style="color: var(--text-primary);">No Activity Logs</h4>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            No activity has been recorded for this emergency mode yet.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ============================================================
         | RIGHT COLUMN
         * ============================================================ --}}
    <div class="space-y-6">

        {{-- Status Summary --}}
        <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-bar mr-2" style="color: var(--primary);"></i> Status Summary
                </h3>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <div class="text-sm mb-2" style="color: var(--text-secondary);">Current Status</div>
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full mr-2"
                             style="background-color: {{ $statusColor[1] }};"></div>
                        <span class="font-medium capitalize" style="color: var(--text-primary);">
                            {{ str_replace('_', ' ', $emergencyMode->status) }}
                        </span>
                    </div>
                </div>

                @if(!empty($impactMetrics))
                    <div>
                        <div class="text-sm mb-2" style="color: var(--text-secondary);">Impact Summary</div>
                        <div class="space-y-3">
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span style="color: var(--text-secondary);">Affected Users</span>
                                    <span style="color: var(--text-primary);">{{ number_format($impactMetrics['total_affected'] ?? 0) }}</span>
                                </div>
                                <div class="w-full rounded-full h-2" style="background-color: var(--border-color);">
                                    <div class="h-2 rounded-full"
                                         style="background-color: var(--danger); width: {{ min(100, ($impactMetrics['affected_percentage'] ?? 0)) }}%;"></div>
                                </div>
                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                    {{ $impactMetrics['affected_percentage'] ?? 0 }}% of active users
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-center">
                                <div class="p-2 rounded border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                    <div class="text-lg font-semibold" style="color: var(--text-primary);">
                                        {{ $impactMetrics['duration_minutes'] ?? 0 }}
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">Minutes</div>
                                </div>
                                <div class="p-2 rounded border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                    <div class="text-lg font-semibold" style="color: var(--text-primary);">
                                        {{ round(($impactMetrics['duration_minutes'] ?? 0) / 60, 1) }}
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">Hours</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div>
                    <div class="text-sm mb-3" style="color: var(--text-secondary);">Quick Actions</div>
                    <div class="space-y-2">
                        @if($emergencyMode->is_active)
                            <button type="button" onclick="EmergencyShow.openDeactivation()"
                                    class="w-full p-3 rounded-lg border flex items-center justify-center transition-all hover:opacity-80"
                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border-color: rgba(var(--danger-rgb), 0.3);">
                                <i class="fas fa-power-off mr-2"></i> Deactivate Emergency
                            </button>
                            <button type="button" onclick="EmergencyShow.openExtension()"
                                    class="w-full p-3 rounded-lg border flex items-center justify-center transition-all hover:opacity-80"
                                    style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border-color: rgba(var(--primary-rgb), 0.3);">
                                <i class="fas fa-clock mr-2"></i> Extend Duration
                            </button>
                        @elseif($emergencyMode->status === EmergencyMode::STATUS_INACTIVE)
                            <form action="{{ route('developer.emergency.activate', $emergencyMode->id) }}" method="POST" class="w-full">
                                @csrf
                                <button type="submit"
                                        onclick="return confirm('Activate this emergency mode?')"
                                        class="w-full p-3 rounded-lg border flex items-center justify-center transition-all hover:opacity-80"
                                        style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border-color: rgba(var(--warning-rgb), 0.3);">
                                    <i class="fas fa-play mr-2"></i> Activate Now
                                </button>
                            </form>
                        @endif

                        @if($emergencyMode->isScheduled())
                            <button type="button" onclick="EmergencyShow.openCancel()"
                                    class="w-full p-3 rounded-lg border flex items-center justify-center transition-all hover:opacity-80"
                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border-color: rgba(var(--danger-rgb), 0.3);">
                                <i class="fas fa-times mr-2"></i> Cancel Scheduled
                            </button>
                        @endif

                        <a href="{{ route('developer.emergency.history', $emergencyMode->id) }}"
                           class="w-full p-3 rounded-lg border flex items-center justify-center transition-all hover:opacity-80"
                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border-color: rgba(var(--info-rgb), 0.3);">
                            <i class="fas fa-history mr-2"></i> View Full History
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Affected Users --}}
        <div id="affected-users" class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-users mr-2" style="color: var(--primary);"></i> Affected Users
                    </h3>
                    <span class="badge" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                        {{ number_format($affectedUsersCount ?? 0) }} users
                    </span>
                </div>
            </div>
            <div class="p-6">
                @if($emergencyMode->affectedUsers && $emergencyMode->affectedUsers->count() > 0)
                    <div class="space-y-3">
                        @foreach($emergencyMode->affectedUsers->take(5) as $affectedUser)
                            <div class="p-3 rounded-lg border" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                                <div class="flex items-center justify-between mb-2 flex-wrap gap-2">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                                             style="background-color: var(--bg-primary); color: var(--text-secondary);">
                                            <i class="fas fa-user text-xs"></i>
                                        </div>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">
                                                {{ $affectedUser->user?->name ?? 'User #' . $affectedUser->user_id }}
                                            </div>
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                {{ $userTypeLabels[(int) $affectedUser->user_type] ?? 'Type ' . $affectedUser->user_type }}
                                            </div>
                                        </div>
                                    </div>
                                    @if($affectedUser->notified_at)
                                        <div class="badge" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                            <i class="fas fa-check mr-1"></i> Notified
                                        </div>
                                    @endif
                                </div>

                                @if(!empty($affectedUser->affected_features) && is_array($affectedUser->affected_features))
                                    <div class="text-xs mt-2">
                                        <span style="color: var(--text-secondary);">Affected features:</span>
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            @foreach($affectedUser->affected_features as $feature)
                                                <span class="badge"
                                                      style="background-color: rgba(var(--warning-rgb), 0.05); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                                                    {{ str_replace('_', ' ', $feature) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        @if(($affectedUsersCount ?? 0) > 5)
                            <div class="text-center">
                                <button type="button" onclick="EmergencyShow.openAllUsers()"
                                        class="text-sm inline-flex items-center hover:underline"
                                        style="color: var(--primary);">
                                    View all {{ number_format($affectedUsersCount) }} affected users
                                    <i class="fas fa-arrow-right ml-1"></i>
                                </button>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-center py-6">
                        <div class="w-12 h-12 mx-auto mb-3 rounded-full flex items-center justify-center"
                             style="background-color: rgba(var(--text-secondary), 0.1);">
                            <i class="fas fa-users" style="color: var(--text-secondary);"></i>
                        </div>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            No users affected by this emergency mode.
                        </p>
                    </div>
                @endif
            </div>
        </div>

        {{-- System Information --}}
        <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-server mr-2" style="color: var(--primary);"></i> System Information
                </h3>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <div class="text-sm mb-1" style="color: var(--text-secondary);">Created</div>
                    <div style="color: var(--text-primary);">{{ $emergencyMode->created_at->format('M d, Y H:i') }}</div>
                </div>

                @if($emergencyMode->activated_at)
                    <div>
                        <div class="text-sm mb-1" style="color: var(--text-secondary);">Activated</div>
                        <div style="color: var(--text-primary);">{{ $emergencyMode->activated_at->format('M d, Y H:i') }}</div>
                        @if($emergencyMode->activatedByUser)
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                By: {{ $emergencyMode->activatedByUser->name }}
                            </div>
                        @endif
                    </div>
                @endif

                @if($emergencyMode->deactivated_at)
                    <div>
                        <div class="text-sm mb-1" style="color: var(--text-secondary);">Deactivated</div>
                        <div style="color: var(--text-primary);">{{ $emergencyMode->deactivated_at->format('M d, Y H:i') }}</div>
                        @if($emergencyMode->deactivatedByUser)
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                By: {{ $emergencyMode->deactivatedByUser->name }}
                            </div>
                        @endif
                    </div>
                @endif

                @if($emergencyMode->scheduled_start)
                    <div>
                        <div class="text-sm mb-1" style="color: var(--text-secondary);">Scheduled Start</div>
                        <div style="color: var(--text-primary);">{{ $emergencyMode->scheduled_start->format('M d, Y H:i') }}</div>
                    </div>
                @endif

                @if($emergencyMode->scheduled_end)
                    <div>
                        <div class="text-sm mb-1" style="color: var(--text-secondary);">Scheduled End</div>
                        <div style="color: var(--text-primary);">{{ $emergencyMode->scheduled_end->format('M d, Y H:i') }}</div>
                    </div>
                @endif

                <div>
                    <div class="text-sm mb-1" style="color: var(--text-secondary);">Last Updated</div>
                    <div style="color: var(--text-primary);">{{ $emergencyMode->updated_at->format('M d, Y H:i') }}</div>
                </div>
            </div>
        </div>
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
                <button type="button" onclick="EmergencyShow.closeModal('deactivationModal')"
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

            <form action="{{ route('developer.emergency.deactivate', $emergencyMode->id) }}" method="POST">
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
                    <button type="button" onclick="EmergencyShow.closeModal('deactivationModal')"
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
                <button type="button" onclick="EmergencyShow.closeModal('extensionModal')"
                        class="p-2 rounded-lg hover:bg-opacity-20"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                        aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form action="{{ route('developer.emergency.extend', $emergencyMode->id) }}" method="POST">
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
                            <span class="text-sm" style="color: var(--text-secondary);">min</span>
                        </div>
                        <div class="flex justify-between items-center mt-2 flex-wrap gap-2">
                            <span class="text-xs" style="color: var(--text-secondary);">Quick select:</span>
                            <div class="space-x-2">
                                @foreach([30 => '30 min', 60 => '1 hour', 120 => '2 hours', 240 => '4 hours'] as $mins => $label)
                                    <button type="button" onclick="EmergencyShow.setExtension({{ $mins }})"
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
                    <button type="button" onclick="EmergencyShow.closeModal('extensionModal')"
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
     | CANCEL MODAL
     * ============================================================ --}}
<div id="cancelModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4"
     role="dialog" aria-modal="true" aria-labelledby="cancelTitle">
    <div class="card w-full max-w-lg" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 id="cancelTitle" class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i> Cancel Scheduled Emergency
                </h3>
                <button type="button" onclick="EmergencyShow.closeModal('cancelModal')"
                        class="p-2 rounded-lg hover:bg-opacity-20"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                        aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                This will cancel the scheduled activation. The emergency mode will remain configured but inactive.
            </p>

            <form action="{{ route('developer.emergency.cancel', $emergencyMode->id) }}" method="POST">
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
                    <button type="button" onclick="EmergencyShow.closeModal('cancelModal')"
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

{{-- ============================================================
     | ALL AFFECTED USERS MODAL
     * ============================================================ --}}
<div id="allUsersModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4"
     role="dialog" aria-modal="true" aria-labelledby="allUsersTitle">
    <div class="card w-full max-w-4xl max-h-[80vh] overflow-hidden"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 id="allUsersTitle" class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-users mr-2" style="color: var(--primary);"></i>
                    All Affected Users ({{ number_format($affectedUsersCount ?? 0) }})
                </h3>
                <button type="button" onclick="EmergencyShow.closeModal('allUsersModal')"
                        class="p-2 rounded-lg hover:bg-opacity-20"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                        aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="p-6 overflow-y-auto" style="max-height: calc(80vh - 120px);">
            <div class="text-center py-8">
                <i class="fas fa-users text-4xl mb-4" style="color: var(--text-secondary); opacity: 0.5;"></i>
                <p class="text-sm" style="color: var(--text-secondary);">
                    Full user list is available via the dedicated endpoint
                    <code class="px-1 rounded" style="background-color: var(--bg-secondary);">/emergency/{id}/affected-users</code>.
                </p>
                <p class="text-xs mt-2" style="color: var(--text-secondary);">
                    Add the endpoint and a modal loader to enable this view.
                </p>
            </div>
        </div>
        <div class="p-4 border-t" style="border-color: var(--border-color);">
            <div class="flex justify-end">
                <button type="button" onclick="EmergencyShow.closeModal('allUsersModal')"
                        class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                        style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    Close
                </button>
            </div>
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
        letter-spacing: 0.4px;
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

    .card { transition: box-shadow 0.2s ease; }
    .card:hover { box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08); }

    input:focus, select:focus, textarea:focus, button:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15);
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50%      { opacity: 0.5; }
    }
    .animate-pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }

    @media (prefers-reduced-motion: reduce) {
        .animate-pulse, .card { animation: none !important; transition: none !important; }
    }

    @media (max-width: 768px) {
        .card { border-radius: 0.5rem; }
        .text-2xl { font-size: 1.5rem; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    function openDeactivation() {
        document.getElementById('deactivationModal').classList.remove('hidden');
    }

    function openExtension() {
        document.getElementById('extensionModal').classList.remove('hidden');
    }

    function openCancel() {
        document.getElementById('cancelModal').classList.remove('hidden');
    }

    function openAllUsers() {
        document.getElementById('allUsersModal').classList.remove('hidden');
    }

    function closeModal(id) {
        const el = document.getElementById(id);
        if (el) el.classList.add('hidden');
    }

    function setExtension(minutes) {
        const slider = document.getElementById('extension_slider');
        const input  = document.getElementById('extension_minutes');
        if (slider) slider.value = minutes;
        if (input)  input.value  = minutes;
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Range slider sync
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
                closeModal('allUsersModal');
            }
        });

        // Native form validation is enough — no JS override needed
    });

    window.EmergencyShow = {
        openDeactivation,
        openExtension,
        openCancel,
        openAllUsers,
        closeModal,
        setExtension,
    };
})();
</script>
@endpush