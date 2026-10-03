{{-- resources/views/sms/index.blade.php --}}
@extends('layouts.app')

@section('title', 'SMS Management')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
            <i class="fas fa-sms mr-2" style="color: var(--primary);"></i>
            SMS Management
        </h1>
        <div class="flex gap-3">
            <a href="{{ route('sms.compose') }}" class="px-4 py-2 rounded-lg text-white font-medium transition-all hover:scale-105"
               style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                <i class="fas fa-pen mr-2"></i> Compose SMS
            </a>
            <a href="{{ route('sms.logs') }}" class="px-4 py-2 rounded-lg font-medium transition-all hover:scale-105"
               style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                <i class="fas fa-history mr-2"></i> View Logs
            </a>
        </div>
    </div>

    <!-- System Status -->
    <div class="mb-6">
        @if($quickStatus['system_ready'] ?? false)
            <div class="p-4 rounded-lg" style="background-color: rgba(34, 197, 94, 0.1); border: 1px solid #22c55e;">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3 text-lg"></i>
                    <div>
                        <p class="font-medium" style="color: var(--text-primary);">SMS System Operational</p>
                        <p class="text-sm" style="color: var(--text-secondary);">{{ $systemStatus['status_message'] ?? 'SMS service is ready to send messages' }}</p>
                    </div>
                </div>
            </div>
        @else
            <div class="p-4 rounded-lg" style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444;">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3 text-lg"></i>
                    <div>
                        <p class="font-medium" style="color: var(--text-primary);">SMS System Not Ready</p>
                        <p class="text-sm" style="color: var(--text-secondary);">{{ $systemStatus['status_message'] ?? 'Please configure an SMS provider' }}</p>
                        @if(auth()->user()->isSuperAdmin() || auth()->user()->isDeveloper())
                            <a href="{{ route('admin.sms-providers.index') }}" class="text-sm mt-2 inline-block px-3 py-1 rounded" style="background-color: var(--primary); color: white;">
                                <i class="fas fa-cog mr-1"></i> Configure Providers
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
        <div class="stat-card p-4 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
            <div class="text-2xl font-bold" style="color: var(--primary);">{{ $usageStats['total_sent'] ?? 0 }}</div>
            <div class="text-xs" style="color: var(--text-secondary);">Total Sent</div>
        </div>
        <div class="stat-card p-4 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
            <div class="text-2xl font-bold" style="color: #22c55e;">{{ $usageStats['successful'] ?? 0 }}</div>
            <div class="text-xs" style="color: var(--text-secondary);">Successful</div>
        </div>
        <div class="stat-card p-4 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
            <div class="text-2xl font-bold" style="color: #ef4444;">{{ $usageStats['failed'] ?? 0 }}</div>
            <div class="text-xs" style="color: var(--text-secondary);">Failed</div>
        </div>
        <div class="stat-card p-4 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
            <div class="text-2xl font-bold" style="color: #eab308;">{{ $usageStats['success_rate'] ?? 0 }}%</div>
            <div class="text-xs" style="color: var(--text-secondary);">Success Rate</div>
        </div>
        <div class="stat-card p-4 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
            <div class="text-2xl font-bold" style="color: var(--info);">{{ $usageStats['today'] ?? 0 }}</div>
            <div class="text-xs" style="color: var(--text-secondary);">Today</div>
        </div>
        <div class="stat-card p-4 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
            <div class="text-2xl font-bold" style="color: var(--primary);">{{ $usageStats['this_month'] ?? 0 }}</div>
            <div class="text-xs" style="color: var(--text-secondary);">This Month</div>
        </div>
    </div>

    <!-- Provider Status -->
    @if(!empty($providers))
    <div class="mb-6">
        <h3 class="text-lg font-semibold mb-3" style="color: var(--text-primary);">
            <i class="fas fa-server mr-2" style="color: var(--primary);"></i>
            Provider Status
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($providers as $key => $provider)
                <div class="provider-card p-4 rounded-lg transition-all duration-200"
                     style="background-color: var(--bg-secondary); border: 1px solid {{ ($provider['enabled'] ?? false) && ($provider['configured'] ?? false) ? 'var(--success)' : 'var(--border-color)' }};">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-2 h-2 rounded-full mr-2 {{ ($provider['enabled'] ?? false) && ($provider['configured'] ?? false) ? 'bg-green-500' : 'bg-red-500' }}"></div>
                            <span class="font-medium" style="color: var(--text-primary);">{{ $provider['name'] ?? $key }}</span>
                        </div>
                        <span class="text-xs px-2 py-0.5 rounded-full {{ ($provider['enabled'] ?? false) && ($provider['configured'] ?? false) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                            {{ ($provider['enabled'] ?? false) && ($provider['configured'] ?? false) ? 'Ready' : (($provider['enabled'] ?? false) ? 'Misconfigured' : 'Disabled') }}
                        </span>
                    </div>
                    @if(isset($provider['missing_configuration']) && !empty($provider['missing_configuration']))
                        <div class="text-xs mt-1" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            Missing: {{ implode(', ', $provider['missing_configuration']) }}
                        </div>
                    @endif
                    @if(($provider['enabled'] ?? false) && ($provider['configured'] ?? false))
                        <div class="text-xs mt-1" style="color: var(--success);">
                            <i class="fas fa-check-circle mr-1"></i>
                            Default Provider: {{ $defaultProvider === $key ? 'Yes' : 'No' }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Recent Activity -->
    @if($recentLogs->isNotEmpty())
    <div>
        <h3 class="text-lg font-semibold mb-3" style="color: var(--text-primary);">
            <i class="fas fa-clock mr-2" style="color: var(--primary);"></i>
            Recent Activity
        </h3>
        <div class="rounded-lg overflow-hidden" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Provider</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Phone</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Message</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentLogs as $log)
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded-full text-xs {{ $log->status_badge }}">
                                        <i class="fas {{ $log->status_icon }} mr-1"></i>
                                        {{ ucfirst($log->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm" style="color: var(--text-secondary);">{{ $log->provider_display }}</td>
                                <td class="px-4 py-3 text-sm" style="color: var(--text-secondary);">{{ $log->masked_phone }}</td>
                                <td class="px-4 py-3 text-sm max-w-xs truncate" style="color: var(--text-secondary);">{{ $log->preview }}</td>
                                <td class="px-4 py-3 text-sm" style="color: var(--text-secondary);">{{ $log->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3 text-center">
            <a href="{{ route('sms.logs') }}" class="text-sm" style="color: var(--primary);">
                <i class="fas fa-arrow-right mr-1"></i> View All Logs
            </a>
        </div>
    </div>
    @endif
</div>

<style>
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
.provider-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
</style>
@endsection