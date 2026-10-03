{{-- ============ SEARCH MODAL ============ --}}
@if($isAuthorized ?? false)
<div id="searchModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 hidden overflow-y-auto" style="padding-top: 5rem; padding-bottom: 2rem;">
    <div class="rounded-lg shadow-xl w-11/12 md:w-2/3 lg:w-1/2 max-w-2xl search-modal-container relative mx-auto my-auto"
         style="background-color: var(--card-bg);
                border: 1px solid var(--border-color);
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: modalSlideIn 0.3s ease-out;
                max-height: calc(100vh - 6rem);
                display: flex;
                flex-direction: column;
                border-radius: 16px;
                overflow: hidden;">
        
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b flex justify-between items-center flex-shrink-0 modal-header-light"
             style="border-color: var(--border-color); background-color: var(--card-bg);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-search mr-2" style="color: var(--primary);"></i>Advanced Search
                <span class="text-xs ml-2 px-2 py-1 rounded-full" 
                      style="background-color: var(--primary); color: white;">
                    @if($isSuperAdmin ?? false)
                        Super Admin
                    @elseif($isAdmin ?? false)
                        Admin
                    @elseif($isDeveloper ?? false)
                        Developer
                    @endif
                </span>
            </h3>
            <button id="closeSearchModal" 
                    class="p-1 rounded-full transition-colors duration-200 hover:bg-opacity-20"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="flex-1 overflow-y-auto modal-scrollable-body p-6" style="max-height: calc(100vh - 14rem); scroll-behavior: smooth;">
            <!-- Info Message -->
            <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.1); border: 1px solid var(--primary);">
                <div class="flex items-start">
                    <i class="fas fa-info-circle mr-3 mt-0.5" style="color: var(--primary);"></i>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-primary);">How to Search</p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Enter your search term and press Enter. You will be redirected to the search results page 
                            where you can filter results by category and fields.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Search Input -->
            <div class="mb-6">
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                    Search Term
                </label>
                <input type="text" id="globalSearchInput" placeholder="Enter search term..." 
                       class="w-full px-4 py-3 rounded-lg transition-colors duration-200"
                       style="background-color: var(--bg-secondary); 
                              border: 1px solid var(--border-color);
                              color: var(--text-primary);"
                       data-role="{{ $currentUserType ?? 'admin' }}">
            </div>
            
            <!-- Search Category -->
            <div class="mb-6">
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                    Search Category (Optional)
                </label>
                <select id="searchCategory" 
                        class="w-full px-4 py-3 rounded-lg transition-colors duration-200 appearance-none"
                        style="background-color: var(--bg-secondary); 
                               border: 1px solid var(--border-color);
                               color: var(--text-primary);
                               background-image: url(\"data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e\");
                               background-position: right 0.5rem center;
                               background-repeat: no-repeat;
                               background-size: 1.5em 1.5em;
                               padding-right: 2.5rem;">
                    <option value="all">All Categories</option>
                    <option value="users">👥 Users</option>
                    <option value="registration-plans">🗺️ Registration Plans</option>
                    <option value="construction-registrations">🏗️ Construction Registrations</option>
                    <option value="properties">🏢 Properties</option>
                    <option value="property-units">🚪 Property Units</option>
                    <option value="payments">💳 Payments</option>
                    <option value="invoices">📄 Invoices</option>
                    <option value="security-posts">📍 Security Posts</option>
                    <option value="security-shifts">⏰ Security Shifts</option>
                    <option value="security-schedules">📅 Security Schedules</option>
                    <option value="security-supervisor-assignments">👔 Supervisor Assignments</option>
                    <option value="security-reports">📊 Security Reports</option>
                    <option value="ownership-transfers">🔄 Ownership Transfers</option>
                    @if($isSuperAdmin ?? false)
                        <option value="system-settings">⚙️ System Settings</option>
                        <option value="payment-providers">💳 Payment Providers</option>
                        <option value="sms-providers">📱 SMS Providers</option>
                        <option value="whatsapp-providers">💬 WhatsApp Providers</option>
                        <option value="superadmin-billing">💰 Billing Management</option>
                    @endif
                </select>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    <i class="fas fa-lightbulb mr-1"></i> Select a category to search within a specific module
                </p>
            </div>
            
            <!-- Recent Searches -->
            <div id="recentSearches" class="mb-6">
                <div class="flex justify-between items-center mb-2">
                    <label class="block text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-history mr-1"></i> Recent Searches
                    </label>
                    <button id="clearRecentSearchesBtn" 
                            class="text-xs px-2 py-1 rounded transition-colors duration-200"
                            style="color: var(--danger); hover:opacity:80;"
                            title="Clear all recent searches">
                        <i class="fas fa-trash-alt mr-1"></i>Clear All
                    </button>
                </div>
                <div id="recentSearchesList" class="flex flex-wrap gap-2">
                    <!-- Recent searches will be populated here -->
                </div>
            </div>
        </div>
        
        <!-- Scroll indicators -->
        <div class="scroll-indicator top-indicator hidden" id="scrollTopIndicator">
            <i class="fas fa-chevron-up" style="color: var(--primary);"></i>
        </div>
        <div class="scroll-indicator bottom-indicator hidden" id="scrollBottomIndicator">
            <i class="fas fa-chevron-down" style="color: var(--primary);"></i>
        </div>
        
        <!-- Modal Footer -->
        <div class="px-6 py-4 border-t flex justify-between flex-shrink-0 modal-footer-light"
             style="border-color: var(--border-color); background-color: var(--bg-secondary);">
            <div class="flex space-x-2">
                <button id="clearSearch" 
                        class="px-3 py-2 text-xs font-medium transition-colors duration-200 rounded"
                        style="color: var(--text-secondary); hover:background-color: var(--bg-primary);">
                    <i class="fas fa-eraser mr-1"></i>Clear
                </button>
            </div>
            <div class="flex space-x-3">
                <button id="cancelSearch" 
                        class="px-4 py-2 text-sm font-medium transition-colors duration-200 rounded"
                        style="color: var(--text-secondary); hover:background-color: var(--bg-primary);">
                    Cancel
                </button>
                <button id="performSearch" 
                        class="px-4 py-2 text-sm font-medium rounded-lg transition-colors duration-200"
                        style="background-color: var(--primary); color: white; hover:opacity:90;">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
            </div>
        </div>
    </div>
</div>
@endif