{{-- ============ BILLING MANAGEMENT MODAL (Super Admin only) ============ --}}
@if($isSuperAdmin ?? false)
<div id="billingManagementModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 hidden overflow-y-auto" style="padding-top: 2rem; padding-bottom: 2rem;">
    <div class="billing-modal relative mx-auto my-auto" 
         style="background-color: var(--card-bg); 
                border: 1px solid var(--border-color);
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: modalSlideUp 0.3s ease-out;
                width: 95%;
                max-width: 1200px;
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
                    <i class="fas fa-chart-line mr-3" style="color: var(--primary);"></i>
                    Billing Management
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Manage all billing activities, agreements, payments, and reports for your account
                </p>
            </div>
            <button id="closeBillingModal" 
                    class="p-2 rounded-full transition-colors duration-200 hover:bg-opacity-20"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="modal-body p-6 overflow-y-auto" style="max-height: calc(100vh - 12rem); scroll-behavior: smooth;">
            
            <!-- Stats Summary -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="stat-card p-4 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $totalBillingRecords ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Total Agreements</div>
                </div>
                <div class="stat-card p-4 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #22c55e;">{{ $completedPayments ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Completed Payments</div>
                </div>
                <div class="stat-card p-4 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #eab308;">{{ $pendingAgreements ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Pending Agreements</div>
                </div>
                <div class="stat-card p-4 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #ef4444;">{{ $awaitingSignature ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Awaiting Signature</div>
                </div>
            </div>
            
            <!-- Main Billing Options Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Dashboard -->
                <a href="{{ route('superadmin.billing.dashboard') }}" 
                   class="billing-option-card group block p-5 rounded-xl transition-all duration-300 hover:shadow-lg"
                   style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);"
                   onclick="closeBillingModal()">
                    <div class="flex items-start">
                        <div class="billing-icon-container w-12 h-12 rounded-xl flex items-center justify-center mr-4 flex-shrink-0"
                             style="background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.15) 0%, rgba(var(--secondary-rgb), 0.1) 100%);">
                            <i class="fas fa-chart-line text-xl" style="color: var(--primary);"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="billing-title font-semibold text-base" style="color: var(--text-primary);">Billing Dashboard</h4>
                            <p class="billing-description text-sm" style="color: var(--text-secondary);">Overview of all billing activities and metrics</p>
                            <div class="billing-stats mt-2 flex flex-wrap gap-2">
                                <span class="stat-badge text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                    {{ $totalBillingRecords ?? 0 }} agreements
                                </span>
                                @if(($pendingAgreements ?? 0) > 0)
                                    <span class="stat-badge text-xs px-2 py-1 rounded-full" style="background-color: rgba(239, 68, 68, 0.1); color: #ef4444;">
                                        {{ $pendingAgreements ?? 0 }} pending
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="billing-arrow ml-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                        </div>
                    </div>
                </a>
                
                <!-- Agreements List -->
                <a href="{{ route('superadmin.billing.agreements-list') }}" 
                   class="billing-option-card group block p-5 rounded-xl transition-all duration-300 hover:shadow-lg"
                   style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);"
                   onclick="closeBillingModal()">
                    <div class="flex items-start">
                        <div class="billing-icon-container w-12 h-12 rounded-xl flex items-center justify-center mr-4 flex-shrink-0"
                             style="background: linear-gradient(135deg, rgba(34, 197, 94, 0.15) 0%, rgba(74, 222, 128, 0.1) 100%);">
                            <i class="fas fa-file-contract text-xl" style="color: #22c55e;"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="billing-title font-semibold text-base" style="color: var(--text-primary);">My Agreements</h4>
                            <p class="billing-description text-sm" style="color: var(--text-secondary);">View and manage all your billing agreements</p>
                            <div class="billing-stats mt-2 flex flex-wrap gap-2">
                                <span class="stat-badge text-xs px-2 py-1 rounded-full" style="background-color: rgba(34, 197, 94, 0.1); color: #22c55e;">
                                    {{ $totalBillingRecords ?? 0 }} total
                                </span>
                                @if(($pendingAgreements ?? 0) > 0)
                                    <span class="stat-badge text-xs px-2 py-1 rounded-full" style="background-color: rgba(239, 68, 68, 0.1); color: #ef4444;">
                                        {{ $pendingAgreements ?? 0 }} pending
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="billing-arrow ml-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                        </div>
                    </div>
                </a>
                
                <!-- Reports -->
                <a href="{{ route('superadmin.billing.reports') }}" 
                   class="billing-option-card group block p-5 rounded-xl transition-all duration-300 hover:shadow-lg"
                   style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);"
                   onclick="closeBillingModal()">
                    <div class="flex items-start">
                        <div class="billing-icon-container w-12 h-12 rounded-xl flex items-center justify-center mr-4 flex-shrink-0"
                             style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(96, 165, 250, 0.1) 100%);">
                            <i class="fas fa-chart-bar text-xl" style="color: #3b82f6;"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="billing-title font-semibold text-base" style="color: var(--text-primary);">Reports & Analytics</h4>
                            <p class="billing-description text-sm" style="color: var(--text-secondary);">Generate billing reports and export data</p>
                            <div class="billing-stats mt-2 flex flex-wrap gap-2">
                                <span class="stat-badge text-xs px-2 py-1 rounded-full" style="background-color: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                                    <i class="fas fa-download mr-1"></i> Export
                                </span>
                            </div>
                        </div>
                        <div class="billing-arrow ml-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                        </div>
                    </div>
                </a>
                
                <!-- Payment History -->
                <a href="{{ route('superadmin.billing.payment-history') }}" 
                   class="billing-option-card group block p-5 rounded-xl transition-all duration-300 hover:shadow-lg"
                   style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);"
                   onclick="closeBillingModal()">
                    <div class="flex items-start">
                        <div class="billing-icon-container w-12 h-12 rounded-xl flex items-center justify-center mr-4 flex-shrink-0"
                             style="background: linear-gradient(135deg, rgba(168, 85, 247, 0.15) 0%, rgba(196, 181, 253, 0.1) 100%);">
                            <i class="fas fa-history text-xl" style="color: #8b5cf6;"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="billing-title font-semibold text-base" style="color: var(--text-primary);">Payment History</h4>
                            <p class="billing-description text-sm" style="color: var(--text-secondary);">View complete payment history and records</p>
                            <div class="billing-stats mt-2 flex flex-wrap gap-2">
                                <span class="stat-badge text-xs px-2 py-1 rounded-full" style="background-color: rgba(168, 85, 247, 0.1); color: #8b5cf6;">
                                    {{ $completedPayments ?? 0 }} payments
                                </span>
                                @if(($pendingPayments ?? 0) > 0)
                                    <span class="stat-badge text-xs px-2 py-1 rounded-full" style="background-color: rgba(239, 68, 68, 0.1); color: #ef4444;">
                                        {{ $pendingPayments ?? 0 }} pending
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="billing-arrow ml-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                        </div>
                    </div>
                </a>
                
                <!-- Shared Payment Status (Primary only) -->
                @if($isPrimary ?? false)
                    <a href="{{ route('superadmin.billing.shared-payment-status') }}" 
                       class="billing-option-card group block p-5 rounded-xl transition-all duration-300 hover:shadow-lg"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); border-left: 4px solid #f59e0b;"
                       onclick="closeBillingModal()">
                        <div class="flex items-start">
                            <div class="billing-icon-container w-12 h-12 rounded-xl flex items-center justify-center mr-4 flex-shrink-0"
                                 style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, rgba(251, 191, 36, 0.1) 100%);">
                                <i class="fas fa-star text-xl" style="color: #f59e0b;"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="billing-title font-semibold text-base" style="color: var(--text-primary);">
                                    Shared Payment Status
                                    <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background-color: #f59e0b; color: white;">Primary</span>
                                </h4>
                                <p class="billing-description text-sm" style="color: var(--text-secondary);">View payment status of all super admins</p>
                                <div class="billing-stats mt-2 flex flex-wrap gap-2">
                                    <span class="stat-badge text-xs px-2 py-1 rounded-full" style="background-color: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                                        <i class="fas fa-crown mr-1"></i> Primary Contact
                                    </span>
                                </div>
                            </div>
                            <div class="billing-arrow ml-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                            </div>
                        </div>
                    </a>
                @endif
                
                <!-- View Statistics -->
                <a href="{{ route('superadmin.billing.statistics') }}" 
                   class="billing-option-card group block p-5 rounded-xl transition-all duration-300 hover:shadow-lg"
                   style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);"
                   onclick="closeBillingModal()">
                    <div class="flex items-start">
                        <div class="billing-icon-container w-12 h-12 rounded-xl flex items-center justify-center mr-4 flex-shrink-0"
                             style="background: linear-gradient(135deg, rgba(236, 72, 153, 0.15) 0%, rgba(244, 114, 182, 0.1) 100%);">
                            <i class="fas fa-chart-pie text-xl" style="color: #ec4899;"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="billing-title font-semibold text-base" style="color: var(--text-primary);">Billing Statistics</h4>
                            <p class="billing-description text-sm" style="color: var(--text-secondary);">View detailed billing analytics and trends</p>
                            <div class="billing-stats mt-2 flex flex-wrap gap-2">
                                <span class="stat-badge text-xs px-2 py-1 rounded-full" style="background-color: rgba(236, 72, 153, 0.1); color: #ec4899;">
                                    <i class="fas fa-chart-line mr-1"></i> Analytics
                                </span>
                            </div>
                        </div>
                        <div class="billing-arrow ml-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                        </div>
                    </div>
                </a>
                
                <!-- Export Data -->
                <a href="{{ route('superadmin.billing.export') }}" 
                   class="billing-option-card group block p-5 rounded-xl transition-all duration-300 hover:shadow-lg"
                   style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);"
                   onclick="closeBillingModal()">
                    <div class="flex items-start">
                        <div class="billing-icon-container w-12 h-12 rounded-xl flex items-center justify-center mr-4 flex-shrink-0"
                             style="background: linear-gradient(135deg, rgba(20, 184, 166, 0.15) 0%, rgba(45, 212, 191, 0.1) 100%);">
                            <i class="fas fa-file-export text-xl" style="color: #14b8a6;"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="billing-title font-semibold text-base" style="color: var(--text-primary);">Export Data</h4>
                            <p class="billing-description text-sm" style="color: var(--text-secondary);">Export billing data in CSV format</p>
                            <div class="billing-stats mt-2 flex flex-wrap gap-2">
                                <span class="stat-badge text-xs px-2 py-1 rounded-full" style="background-color: rgba(20, 184, 166, 0.1); color: #14b8a6;">
                                    <i class="fas fa-download mr-1"></i> CSV Export
                                </span>
                            </div>
                        </div>
                        <div class="billing-arrow ml-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                        </div>
                    </div>
                </a>
            </div>
            
            <!-- Quick Actions -->
            <div class="mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                <h4 class="text-sm font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-bolt mr-2" style="color: var(--primary);"></i>Quick Actions
                </h4>
                <div class="flex flex-wrap gap-3">
                    @if($isSuperAdmin ?? false)
                        <a href="{{ route('superadmin.billing.agreements-list') }}" 
                           class="quick-action-btn px-4 py-2 rounded-lg text-sm transition-colors duration-200"
                           style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                           onclick="closeBillingModal()">
                            <i class="fas fa-list mr-2"></i>View All Agreements
                        </a>
                        <a href="{{ route('superadmin.billing.payment-history') }}" 
                           class="quick-action-btn px-4 py-2 rounded-lg text-sm transition-colors duration-200"
                           style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                           onclick="closeBillingModal()">
                            <i class="fas fa-credit-card mr-2"></i>Payment History
                        </a>
                        <a href="{{ route('superadmin.billing.reports') }}" 
                           class="quick-action-btn px-4 py-2 rounded-lg text-sm transition-colors duration-200"
                           style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                           onclick="closeBillingModal()">
                            <i class="fas fa-file-export mr-2"></i>Export Reports
                        </a>
                        @if($isPrimary ?? false)
                            <a href="{{ route('superadmin.billing.shared-payment-status') }}" 
                               class="quick-action-btn px-4 py-2 rounded-lg text-sm transition-colors duration-200"
                               style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                               onclick="closeBillingModal()">
                                <i class="fas fa-users-cog mr-2"></i>Shared Status
                            </a>
                        @endif
                    @endif
                </div>
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="modal-footer p-6 border-t flex justify-end flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--bg-secondary);">
            <button id="cancelBillingModal" 
                    class="px-4 py-2 text-sm font-medium rounded-lg transition-colors duration-200"
                    style="color: var(--text-secondary);">
                Close
            </button>
        </div>
    </div>
</div>
@endif