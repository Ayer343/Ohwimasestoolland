{{-- ============ INVOICE MANAGEMENT MODAL ============ --}}
@if($isAuthorized ?? false)
<div id="invoiceManagementModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 hidden overflow-y-auto" style="padding-top: 2rem; padding-bottom: 2rem;">
    <div class="invoice-modal relative mx-auto my-auto" 
         style="background-color: var(--card-bg); 
                border: 1px solid var(--border-color);
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: modalSlideUp 0.3s ease-out;
                width: 90%;
                max-width: 1400px;
                max-height: calc(100vh - 4rem);
                display: flex;
                flex-direction: column;
                border-radius: 16px;
                overflow: hidden;">
        
        <!-- Modal Header -->
        <div class="modal-header flex justify-between items-center p-6 border-b flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--card-bg);">
            <h3 class="text-xl font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-file-invoice-dollar mr-3" style="color: var(--primary);"></i>
                Invoice Management
            </h3>
            <button id="closeInvoiceModal" 
                    class="p-2 rounded-full transition-colors duration-200 hover:bg-opacity-20"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="modal-body p-6 overflow-y-auto" style="max-height: calc(100vh - 12rem); scroll-behavior: smooth;">
            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="w-1 h-8 bg-primary rounded-full mr-3"></div>
                        <h4 class="text-lg font-semibold" style="color: var(--text-primary);">
                            <i class="fas fa-file-invoice mr-2" style="color: var(--primary);"></i>
                            All Invoices
                        </h4>
                    </div>
                    <a href="{{ route('invoices.index') }}" 
                       class="px-4 py-2 rounded-lg transition-colors duration-200 text-sm font-medium"
                       style="background-color: var(--primary); color: white;"
                       onclick="closeInvoiceModal()">
                        <i class="fas fa-arrow-right mr-2"></i>
                        View All Invoices
                    </a>
                </div>
                <p class="text-sm mb-6" style="color: var(--text-secondary);">
                    Manage all landlord and tenant invoices, payments, and billing records.
                </p>
            </div>
            
            <!-- Split Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- LANDLORD INVOICE SECTION -->
                <div class="invoice-section landlord-section">
                    <div class="section-header flex items-center mb-4 pb-3 border-b" style="border-color: var(--border-color);">
                        <div class="w-1 h-6 bg-blue-500 rounded-full mr-3"></div>
                        <i class="fas fa-building text-blue-500 mr-2 text-lg"></i>
                        <h4 class="text-lg font-semibold" style="color: var(--text-primary);">Landlord Invoices</h4>
                        <div class="ml-auto px-2 py-1 rounded-full text-xs font-medium" style="background-color: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                            Property Owners
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <a href="{{ route('invoices.year-end.management') }}" 
                           class="invoice-card group block transition-all duration-300 rounded-xl p-5"
                           style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);"
                           onclick="closeInvoiceModal()">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start space-x-4">
                                    <div class="invoice-icon p-3 rounded-xl" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(147, 197, 253, 0.1) 100%);">
                                        <i class="fas fa-archive text-blue-500 text-xl"></i>
                                    </div>
                                    <div>
                                        <h5 class="font-semibold text-base mb-1" style="color: var(--text-primary);">Year-End Archive</h5>
                                        <p class="text-sm" style="color: var(--text-secondary);">Manage and archive paid invoices from previous years</p>
                                        @if(($landlordEligibleForArchive ?? 0) > 0)
                                            <div class="mt-2 flex items-center">
                                                <span class="px-2 py-1 rounded-full text-xs font-medium" style="background-color: rgba(59, 130, 246, 0.2); color: #3b82f6;">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    {{ $landlordEligibleForArchive ?? 0 }} invoice(s) eligible for archive
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="invoice-arrow opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <i class="fas fa-chevron-right text-gray-400"></i>
                                </div>
                            </div>
                        </a>
                        
                        <a href="{{ route('invoices.unpaid-previous-years') }}" 
                           class="invoice-card group block transition-all duration-300 rounded-xl p-5"
                           style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);"
                           onclick="closeInvoiceModal()">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start space-x-4">
                                    <div class="invoice-icon p-3 rounded-xl" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(252, 165, 165, 0.1) 100%);">
                                        <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
                                    </div>
                                    <div>
                                        <h5 class="font-semibold text-base mb-1" style="color: var(--text-primary);">Unpaid from Previous Years</h5>
                                        <p class="text-sm" style="color: var(--text-secondary);">Review and manage overdue invoices from prior years</p>
                                        @if(($landlordUnpaidPreviousYears ?? 0) > 0)
                                            <div class="mt-2 flex items-center">
                                                <span class="px-2 py-1 rounded-full text-xs font-medium" style="background-color: rgba(239, 68, 68, 0.2); color: #ef4444;">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    {{ $landlordUnpaidPreviousYears ?? 0 }} unpaid invoice(s) from previous years
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="invoice-arrow opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <i class="fas fa-chevron-right text-gray-400"></i>
                                </div>
                            </div>
                        </a>
                        
                        <div class="grid grid-cols-2 gap-3 mt-4">
                            <div class="stat-mini p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <div class="text-2xl font-bold" style="color: var(--primary);">{{ $landlordEligibleForArchive ?? 0 }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">Ready for Archive</div>
                            </div>
                            <div class="stat-mini p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <div class="text-2xl font-bold" style="color: var(--warning);">{{ $landlordUnpaidPreviousYears ?? 0 }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">Unpaid Previous Years</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- TENANT INVOICE SECTION -->
                <div class="invoice-section tenant-section">
                    <div class="section-header flex items-center mb-4 pb-3 border-b" style="border-color: var(--border-color);">
                        <div class="w-1 h-6 bg-green-500 rounded-full mr-3"></div>
                        <i class="fas fa-users text-green-500 mr-2 text-lg"></i>
                        <h4 class="text-lg font-semibold" style="color: var(--text-primary);">Tenant Invoices</h4>
                        <div class="ml-auto px-2 py-1 rounded-full text-xs font-medium" style="background-color: rgba(34, 197, 94, 0.1); color: #22c55e;">
                            Renters
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <a href="{{ route('admin.tenant-invoices.year-end-management') }}" 
                           class="invoice-card group block transition-all duration-300 rounded-xl p-5"
                           style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);"
                           onclick="closeInvoiceModal()">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start space-x-4">
                                    <div class="invoice-icon p-3 rounded-xl" style="background: linear-gradient(135deg, rgba(34, 197, 94, 0.1) 0%, rgba(74, 222, 128, 0.1) 100%);">
                                        <i class="fas fa-archive text-green-500 text-xl"></i>
                                    </div>
                                    <div>
                                        <h5 class="font-semibold text-base mb-1" style="color: var(--text-primary);">Year-End Archive</h5>
                                        <p class="text-sm" style="color: var(--text-secondary);">Archive paid tenant invoices from previous years</p>
                                        @if(($tenantEligibleForArchive ?? 0) > 0)
                                            <div class="mt-2 flex items-center">
                                                <span class="px-2 py-1 rounded-full text-xs font-medium" style="background-color: rgba(34, 197, 94, 0.2); color: #22c55e;">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    {{ $tenantEligibleForArchive ?? 0 }} invoice(s) eligible for archive
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="invoice-arrow opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <i class="fas fa-chevron-right text-gray-400"></i>
                                </div>
                            </div>
                        </a>
                        
                        <a href="{{ route('admin.tenant-invoices.unpaid-previous-years') }}" 
                           class="invoice-card group block transition-all duration-300 rounded-xl p-5"
                           style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);"
                           onclick="closeInvoiceModal()">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start space-x-4">
                                    <div class="invoice-icon p-3 rounded-xl" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(252, 165, 165, 0.1) 100%);">
                                        <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
                                    </div>
                                    <div>
                                        <h5 class="font-semibold text-base mb-1" style="color: var(--text-primary);">Unpaid from Previous Years</h5>
                                        <p class="text-sm" style="color: var(--text-secondary);">Review overdue tenant invoices from prior years</p>
                                        @if(($tenantUnpaidPreviousYears ?? 0) > 0)
                                            <div class="mt-2 flex items-center">
                                                <span class="px-2 py-1 rounded-full text-xs font-medium" style="background-color: rgba(239, 68, 68, 0.2); color: #ef4444;">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    {{ $tenantUnpaidPreviousYears ?? 0 }} unpaid invoice(s) from previous years
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="invoice-arrow opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <i class="fas fa-chevron-right text-gray-400"></i>
                                </div>
                            </div>
                        </a>
                        
                        <div class="grid grid-cols-2 gap-3 mt-4">
                            <div class="stat-mini p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <div class="text-2xl font-bold" style="color: var(--primary);">{{ $tenantEligibleForArchive ?? 0 }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">Ready for Archive</div>
                            </div>
                            <div class="stat-mini p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <div class="text-2xl font-bold" style="color: var(--warning);">{{ $tenantUnpaidPreviousYears ?? 0 }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">Unpaid Previous Years</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                <h4 class="text-sm font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-bolt mr-2"></i>Quick Actions
                </h4>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('invoices.index') }}" class="quick-action-btn px-4 py-2 rounded-lg text-sm"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeInvoiceModal()">
                        <i class="fas fa-list mr-2"></i>All Invoices
                    </a>
                    <a href="{{ route('invoices.create') }}" class="quick-action-btn px-4 py-2 rounded-lg text-sm"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeInvoiceModal()">
                        <i class="fas fa-plus mr-2"></i>Create Invoice
                    </a>
                    <a href="{{ route('admin.payments.index') }}" class="quick-action-btn px-4 py-2 rounded-lg text-sm"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeInvoiceModal()">
                        <i class="fas fa-credit-card mr-2"></i>View Payments
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="modal-footer p-6 border-t flex justify-end flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--bg-secondary);">
            <button id="cancelInvoiceModal" class="px-4 py-2 text-sm font-medium rounded-lg"
                    style="color: var(--text-secondary);">
                Close
            </button>
        </div>
    </div>
</div>
@endif