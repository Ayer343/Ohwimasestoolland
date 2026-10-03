{{-- ============ SMS MANAGEMENT MODAL ============ --}}
@if($isAuthorized ?? false)
<div id="smsManagementModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 hidden overflow-y-auto" style="padding-top: 2rem; padding-bottom: 2rem;">
    <div class="sms-modal relative mx-auto my-auto" 
         style="background-color: var(--card-bg); 
                border: 1px solid var(--border-color);
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: modalSlideUp 0.3s ease-out;
                width: 95%;
                max-width: 1400px;
                max-height: calc(100vh - 4rem);
                display: flex;
                flex-direction: column;
                border-radius: 16px;
                overflow: hidden;">
        
        <!-- Modal Header -->
        <div class="modal-header flex justify-between items-center p-6 border-b flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--card-bg);">
            <div>
                <h3 class="text-xl font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-sms mr-3" style="color: var(--primary);"></i>
                    SMS Management
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Manage SMS providers, send messages, and monitor SMS activity
                </p>
            </div>
            <button id="closeSmsModal" 
                    class="p-2 rounded-full transition-colors duration-200 hover:bg-opacity-20"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="modal-body p-6 overflow-y-auto" style="max-height: calc(100vh - 12rem); scroll-behavior: smooth;">
            
            <!-- System Status Alert -->
            @if($smsQuickStatus['system_ready'] ?? false)
                <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(34, 197, 94, 0.1); border: 1px solid #22c55e;">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-3 text-lg"></i>
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">SMS System Operational</p>
                            <p class="text-sm" style="color: var(--text-secondary);">{{ $smsSystemStatus['status_message'] ?? 'SMS service is ready to send messages' }}</p>
                        </div>
                    </div>
                </div>
            @else
                <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444;">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-3 text-lg"></i>
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">SMS System Not Ready</p>
                            <p class="text-sm" style="color: var(--text-secondary);">{{ $smsSystemStatus['status_message'] ?? 'Please configure an SMS provider' }}</p>
                            @if($isSuperAdmin ?? false || $isDeveloper ?? false)
                                <a href="{{ route('admin.sms-providers.index') }}" class="text-sm mt-2 inline-block px-3 py-1 rounded" style="background-color: var(--primary); color: white;" onclick="closeSmsModal()">
                                    <i class="fas fa-cog mr-1"></i> Configure Providers
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 mb-6">
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $smsUsageStats['total_sent'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Total Sent</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #22c55e;">{{ $smsUsageStats['successful'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Successful</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #ef4444;">{{ $smsUsageStats['failed'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Failed</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #eab308;">{{ $smsUsageStats['success_rate'] ?? 0 }}%</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Success Rate</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $smsUsageStats['today'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Today</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $smsUsageStats['this_month'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">This Month</div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="mb-6">
                <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                    <i class="fas fa-bolt mr-2" style="color: var(--primary);"></i>Quick Actions
                </h4>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    <button onclick="openSmsCompose()" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                            style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, var(--primary), var(--secondary));">
                            <i class="fas fa-pen text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Send SMS</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Compose new message</div>
                        </div>
                    </button>
                    
                    <a href="{{ route('sms.logs') }}" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeSmsModal()">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #3b82f6, #60a5fa);">
                            <i class="fas fa-history text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">View Logs</div>
                            <div class="text-xs" style="color: var(--text-secondary);">SMS history</div>
                        </div>
                    </a>
                    
                    @if($isSuperAdmin ?? false || $isDeveloper ?? false)
                        <a href="{{ route('admin.sms-providers.index') }}" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                           style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                           onclick="closeSmsModal()">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);">
                                <i class="fas fa-cog text-white text-xs"></i>
                            </div>
                            <div>
                                <div class="text-sm font-medium">Providers</div>
                                <div class="text-xs" style="color: var(--text-secondary);">Configure SMS providers</div>
                            </div>
                        </a>
                    @endif
                    
                    <button onclick="testSmsConnection()" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                            style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #06b6d4, #22d3ee);">
                            <i class="fas fa-wifi text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Test Connection</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Verify SMS provider</div>
                        </div>
                    </button>
                </div>
            </div>
            
            <!-- Provider Status -->
            @if(!empty($smsProviders))
                <div class="mb-6">
                    <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                        <i class="fas fa-server mr-2" style="color: var(--primary);"></i>Provider Status
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($smsProviders as $key => $provider)
                            <div class="provider-card p-3 rounded-lg transition-all duration-200"
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
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            
            <!-- Recent SMS Activity -->
            @php
                $recentSms = [];
                try {
                    $recentSms = \App\Models\SmsLog::orderBy('created_at', 'desc')
                        ->limit(10)
                        ->get();
                } catch (\Exception $e) {
                    $recentSms = [];
                }
            @endphp
            
            @if($recentSms->isNotEmpty())
                <div>
                    <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                        <i class="fas fa-clock mr-2" style="color: var(--primary);"></i>Recent Activity
                    </h4>
                    <div class="space-y-2">
                        @foreach($recentSms as $log)
                            <div class="flex items-center justify-between p-3 rounded-lg transition-all duration-200"
                                 style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <div class="flex items-center min-w-0 flex-1">
                                    <div class="w-2 h-2 rounded-full mr-3 flex-shrink-0 {{ $log->status === 'success' ? 'bg-green-500' : 'bg-red-500' }}"></div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm truncate" style="color: var(--text-primary);">
                                            {{ Str::limit($log->message ?? 'No message', 50) }}
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            <span>{{ $log->provider ?? 'unknown' }}</span>
                                            <span class="mx-1">•</span>
                                            <span>{{ $log->phone_number ?? 'unknown' }}</span>
                                            <span class="mx-1">•</span>
                                            <span>{{ $log->created_at ? $log->created_at->diffForHumans() : 'N/A' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center flex-shrink-0 ml-2">
                                    <span class="text-xs px-2 py-0.5 rounded-full {{ $log->status === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                        {{ ucfirst($log->status ?? 'unknown') }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
        
        <!-- Modal Footer -->
        <div class="modal-footer p-4 border-t flex justify-between flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--bg-secondary);">
            <div>
                <button onclick="refreshSmsStatus()" class="text-sm px-3 py-1.5 rounded-lg transition-colors duration-200"
                        style="background-color: var(--primary); color: white;">
                    <i class="fas fa-sync mr-1"></i> Refresh Status
                </button>
            </div>
            <button id="cancelSmsModal" 
                    class="px-4 py-2 text-sm font-medium rounded-lg transition-colors duration-200"
                    style="color: var(--text-secondary);">
                Close
            </button>
        </div>
    </div>
</div>
@endif