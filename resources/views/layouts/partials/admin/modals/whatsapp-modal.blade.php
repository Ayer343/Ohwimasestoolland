{{-- ============ WHATSAPP MANAGEMENT MODAL ============ --}}
@if($isAuthorized ?? false)
<div id="whatsappManagementModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 hidden overflow-y-auto" style="padding-top: 2rem; padding-bottom: 2rem;">
    <div class="whatsapp-modal relative mx-auto my-auto" 
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
                    <i class="fab fa-whatsapp mr-3" style="color: #25D366;"></i>
                    WhatsApp Management
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Manage WhatsApp Business API providers, send messages, templates, and monitor activity
                </p>
            </div>
            <button id="closeWhatsAppModal" 
                    class="p-2 rounded-full transition-colors duration-200 hover:bg-opacity-20"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="modal-body p-6 overflow-y-auto" style="max-height: calc(100vh - 12rem); scroll-behavior: smooth;">
            
            <!-- System Status Alert -->
            @if($whatsappQuickStatus['system_ready'] ?? false)
                <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(37, 211, 102, 0.1); border: 1px solid #25D366;">
                    <div class="flex items-center">
                        <i class="fab fa-whatsapp text-green-500 mr-3 text-lg"></i>
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">WhatsApp System Operational</p>
                            <p class="text-sm" style="color: var(--text-secondary);">{{ $whatsappSystemStatus['status_message'] ?? 'WhatsApp service is ready to send messages' }}</p>
                        </div>
                    </div>
                </div>
            @else
                <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444;">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-3 text-lg"></i>
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">WhatsApp System Not Ready</p>
                            <p class="text-sm" style="color: var(--text-secondary);">{{ $whatsappSystemStatus['status_message'] ?? 'Please configure a WhatsApp provider' }}</p>
                            @if($isSuperAdmin ?? false || $isDeveloper ?? false)
                                <a href="{{ route('admin.whatsapp-providers.index') }}" class="text-sm mt-2 inline-block px-3 py-1 rounded" style="background-color: #25D366; color: white;" onclick="closeWhatsAppModal()">
                                    <i class="fab fa-whatsapp mr-1"></i> Configure Providers
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #25D366;">{{ $totalWhatsAppProviders ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Total Providers</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #22c55e;">0</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Messages Sent</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #eab308;">0</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Templates</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: var(--primary);">0</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Webhooks</div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="mb-6">
                <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                    <i class="fas fa-bolt mr-2" style="color: var(--primary);"></i>Quick Actions
                </h4>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    <button onclick="openWhatsAppCompose()" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                            style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #25D366, #128C7E);">
                            <i class="fab fa-whatsapp text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Send Message</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Compose new WhatsApp</div>
                        </div>
                    </button>
                    
                    <a href="{{ route('admin.whatsapp.logs.index') }}" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeWhatsAppModal()">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #3b82f6, #60a5fa);">
                            <i class="fas fa-history text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">View Logs</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Message history</div>
                        </div>
                    </a>
                    
                    <a href="{{ route('admin.whatsapp.templates.index') }}" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeWhatsAppModal()">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);">
                            <i class="fas fa-file-alt text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Templates</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Manage message templates</div>
                        </div>
                    </a>
                    
                    @if($isSuperAdmin ?? false || $isDeveloper ?? false)
                        <a href="{{ route('admin.whatsapp-providers.index') }}" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                           style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                           onclick="closeWhatsAppModal()">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                                <i class="fas fa-cog text-white text-xs"></i>
                            </div>
                            <div>
                                <div class="text-sm font-medium">Providers</div>
                                <div class="text-xs" style="color: var(--text-secondary);">Configure WhatsApp</div>
                            </div>
                        </a>
                    @endif
                    
                    <button onclick="testWhatsAppConnection()" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                            style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #06b6d4, #22d3ee);">
                            <i class="fas fa-wifi text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Test Connection</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Verify WhatsApp provider</div>
                        </div>
                    </button>
                </div>
            </div>
            
            <!-- Provider Status -->
            @if(!empty($whatsappProviders))
                <div>
                    <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                        <i class="fas fa-server mr-2" style="color: var(--primary);"></i>Provider Status
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($whatsappProviders as $key => $provider)
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
        </div>
        
        <!-- Modal Footer -->
        <div class="modal-footer p-4 border-t flex justify-between flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--bg-secondary);">
            <div>
                <button onclick="refreshWhatsAppStatus()" class="text-sm px-3 py-1.5 rounded-lg transition-colors duration-200"
                        style="background-color: #25D366; color: white;">
                    <i class="fas fa-sync mr-1"></i> Refresh Status
                </button>
            </div>
            <button id="cancelWhatsAppModal" 
                    class="px-4 py-2 text-sm font-medium rounded-lg transition-colors duration-200"
                    style="color: var(--text-secondary);">
                Close
            </button>
        </div>
    </div>
</div>
@endif