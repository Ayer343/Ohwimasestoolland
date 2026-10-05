<script>
document.addEventListener('DOMContentLoaded', function() {
    // ============ THEME API FUNCTIONS ============
    const ThemeAPI = {
        getSettings: function() {
            return fetch('/theme/settings', {
                headers: { 
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            }).then(res => res.json());
        },
        
        updateSettings: function(settings) {
            return fetch('/theme/update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(settings)
            }).then(res => res.json());
        },
        
        resetSettings: function() {
            return fetch('/theme/reset', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json'
                }
            }).then(res => res.json());
        },
        
        getOptions: function() {
            return fetch('/theme/options', {
                headers: { 
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            }).then(res => res.json());
        },
        
        applyTheme: function() {
            return fetch('/theme/apply', {
                headers: { 
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            }).then(res => res.json());
        }
    };

    // ============ FIX: Safely get user role with fallback ============
    let userRole = null;
    let isSuperAdmin = false;
    let isAdmin = false;
    let isDeveloper = false;
    
    // Try to get from window.Laravel if available
    if (typeof window !== 'undefined' && window.Laravel && window.Laravel.userType !== undefined) {
        userRole = parseInt(window.Laravel.userType);
        isSuperAdmin = window.Laravel.isSuperAdmin === true;
        isAdmin = window.Laravel.isAdmin === true;
        isDeveloper = window.Laravel.isDeveloper === true;
    } 
    // Fallback: try to get from body classes
    else {
        const body = document.body;
        if (body.classList.contains('super-admin')) {
            isSuperAdmin = true;
            userRole = 0;
        } else if (body.classList.contains('admin')) {
            isAdmin = true;
            userRole = 1;
        } else if (body.classList.contains('developer')) {
            isDeveloper = true;
            userRole = 5;
        }
    }
    
    // If still not set, check meta tag
    if (userRole === null) {
        const metaUserType = document.querySelector('meta[name="user-type"]');
        if (metaUserType) {
            userRole = parseInt(metaUserType.content);
            isSuperAdmin = userRole === 0;
            isAdmin = userRole === 1;
            isDeveloper = userRole === 5;
        }
    }
    
    console.log('User role detected:', { userRole, isSuperAdmin, isAdmin, isDeveloper });

    // ============ SEARCH FIELDS CONFIGURATION ============
    const searchFieldsConfig = {
        'users': {
            fields: ['name', 'email', 'phone', 'username', 'type'],
            displayFields: ['name', 'email', 'phone'],
            route: '/admin/users',
            icon: 'fas fa-users',
            label: 'Users'
        },
        'properties': {
            fields: ['property_name', 'street_name', 'digital_address', 'zone', 'registration_pattern'],
            displayFields: ['property_name', 'street_name', 'digital_address'],
            route: '/properties',
            icon: 'fas fa-building',
            label: 'Properties'
        },
        'property-units': {
            fields: ['unit_number', 'unit_name', 'property_name', 'tenant_name'],
            displayFields: ['unit_number', 'unit_name', 'property_name'],
            route: '/property-units',
            icon: 'fas fa-door-closed',
            label: 'Property Units'
        },
        'registration-plans': {
            fields: ['plan_name', 'description', 'pattern', 'status'],
            displayFields: ['plan_name', 'description', 'status'],
            route: '/registration-plans',
            icon: 'fas fa-map-marked-alt',
            label: 'Registration Plans'
        },
        'invoices': {
            fields: ['invoice_number', 'amount', 'period', 'status', 'customer_name'],
            displayFields: ['invoice_number', 'amount', 'period'],
            route: '/invoices',
            icon: 'fas fa-file-invoice',
            label: 'Invoices'
        },
        'payments': {
            fields: ['transaction_id', 'reference_number', 'amount', 'status', 'payer_name'],
            displayFields: ['transaction_id', 'reference_number', 'amount'],
            route: '/admin/payments',
            icon: 'fas fa-credit-card',
            label: 'Payments'
        },
        'security-posts': {
            fields: ['post_name', 'location', 'code', 'description'],
            displayFields: ['post_name', 'location', 'code'],
            route: '/admin/security-posts',
            icon: 'fas fa-map-marker-alt',
            label: 'Security Posts'
        },
        'security-shifts': {
            fields: ['shift_name', 'shift_code', 'start_time', 'end_time'],
            displayFields: ['shift_name', 'shift_code'],
            route: '/admin/security-shifts',
            icon: 'fas fa-clock',
            label: 'Security Shifts'
        },
        'security-schedules': {
            fields: ['schedule_name', 'post_name', 'personnel_name'],
            displayFields: ['schedule_name', 'post_name', 'personnel_name'],
            route: '/admin/security-schedules',
            icon: 'fas fa-calendar-alt',
            label: 'Security Schedules'
        },
        'security-supervisor-assignments': {
            fields: ['assignment_name', 'supervisor_name', 'post_name', 'status'],
            displayFields: ['assignment_name', 'supervisor_name', 'post_name'],
            route: '/admin/security-supervisor-assignments',
            icon: 'fas fa-user-shield',
            label: 'Supervisor Assignments'
        },
        'security-reports': {
            fields: ['report_title', 'report_type', 'status', 'created_by'],
            displayFields: ['report_title', 'report_type', 'status'],
            route: '/admin/security-reports',
            icon: 'fas fa-file-alt',
            label: 'Security Reports'
        },
        'ownership-transfers': {
            fields: ['document_reference', 'property_name', 'current_landlord', 'new_landlord'],
            displayFields: ['document_reference', 'property_name', 'current_landlord'],
            route: '/admin/ownership-transfers',
            icon: 'fas fa-exchange-alt',
            label: 'Ownership Transfers'
        },
        'construction-registrations': {
            fields: ['registration_number', 'property_name', 'status', 'landlord_name'],
            displayFields: ['registration_number', 'property_name', 'status'],
            route: '/admin/construction-registrations',
            icon: 'fas fa-hard-hat',
            label: 'Construction Registrations'
        }
    };

    // Super Admin only modules
    if (isSuperAdmin) {
        Object.assign(searchFieldsConfig, {
            'system-settings': {
                fields: ['setting_key', 'setting_value', 'description'],
                displayFields: ['setting_key', 'setting_value'],
                route: '/admin/system-settings',
                icon: 'fas fa-cog',
                label: 'System Settings'
            },
            'payment-providers': {
                fields: ['provider_name', 'provider_key', 'status'],
                displayFields: ['provider_name', 'status'],
                route: '/admin/payment-providers',
                icon: 'fas fa-money-bill-wave',
                label: 'Payment Providers'
            },
            'sms-providers': {
                fields: ['provider_name', 'provider_key', 'status'],
                displayFields: ['provider_name', 'status'],
                route: '/admin/sms-providers',
                icon: 'fas fa-sms',
                label: 'SMS Providers'
            },
            'whatsapp-providers': {
                fields: ['provider_name', 'provider_key', 'status'],
                displayFields: ['provider_name', 'status'],
                route: '/admin/whatsapp-providers',
                icon: 'fab fa-whatsapp',
                label: 'WhatsApp Providers'
            },
            'superadmin-billing': {
                fields: ['agreement_number', 'description', 'amount', 'status', 'super_admin_name'],
                displayFields: ['agreement_number', 'description', 'amount'],
                route: '/superadmin/billing/dashboard',
                icon: 'fas fa-chart-line',
                label: 'Billing Management'
            }
        });
    }

    // Admin modules (visible to both Admin and Super Admin)
    if (isAdmin || isSuperAdmin) {
        Object.assign(searchFieldsConfig, {
            'whatsapp-messages': {
                fields: ['message_id', 'recipient', 'content', 'status', 'template_name'],
                displayFields: ['message_id', 'recipient', 'status'],
                route: '/admin/whatsapp/messages',
                icon: 'fab fa-whatsapp',
                label: 'WhatsApp Messages'
            },
            'whatsapp-templates': {
                fields: ['template_name', 'template_id', 'category', 'status'],
                displayFields: ['template_name', 'category', 'status'],
                route: '/admin/whatsapp/templates',
                icon: 'fas fa-file-alt',
                label: 'WhatsApp Templates'
            },
            'whatsapp-logs': {
                fields: ['log_id', 'message_id', 'event', 'status'],
                displayFields: ['log_id', 'event', 'status'],
                route: '/admin/whatsapp/logs',
                icon: 'fas fa-history',
                label: 'WhatsApp Logs'
            }
        });
    }

    // ============ THEME DETECTION FUNCTIONS ============
    function getCurrentTheme() {
        const theme = document.documentElement.getAttribute('data-theme');
        if (theme === 'dark') return 'dark';
        if (theme === 'light') return 'light';
        
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        return 'light';
    }

    function getCurrentSidebarTheme() {
        const theme = document.documentElement.getAttribute('data-sidebar-theme');
        return theme || 'default';
    }

    // ============ BUILD SEARCH URL BASED ON CATEGORY ============
    function buildSearchUrl(category, searchTerm) {
        const encodedTerm = encodeURIComponent(searchTerm.trim());
        
        // Get route from config or use fallback
        let route;
        if (searchFieldsConfig[category]) {
            route = searchFieldsConfig[category].route;
        } else {
            // FIXED: Use proper dashboard based on user role
            if (isSuperAdmin) {
                route = '/super-admin/dashboard';
            } else if (isAdmin) {
                route = '/admin/dashboard';
            } else if (isDeveloper) {
                route = '/developer/dashboard';
            } else {
                route = '/dashboard';
            }
        }
        
        return route + `?search=${encodedTerm}`;
    }
    
    // ============ SIDEBAR TOGGLE FUNCTIONALITY ============
    const toggleSidebarDesktop = document.getElementById('toggleSidebarDesktop');
    const toggleSidebarMobile = document.getElementById('toggleSidebarMobile');
    const overlay = document.getElementById('overlay');
    const sidebar = document.getElementById('sidebar');
    
    function toggleSidebarCollapsed() {
        const body = document.body;
        
        if (body.__x) {
            body.__x.$data.sidebarCollapsed = !body.__x.$data.sidebarCollapsed;
        } else {
            body.classList.toggle('sidebar-collapsed');
        }
        
        if (toggleSidebarDesktop) {
            const icon = toggleSidebarDesktop.querySelector('i');
            if (icon) {
                const isCollapsed = body.__x ? body.__x.$data.sidebarCollapsed : body.classList.contains('sidebar-collapsed');
                icon.className = isCollapsed ? 'fas fa-chevron-right' : 'fas fa-chevron-left';
            }
        }
        
        const isCollapsed = body.__x ? body.__x.$data.sidebarCollapsed : body.classList.contains('sidebar-collapsed');
        localStorage.setItem('sidebar_collapsed', isCollapsed ? 'true' : 'false');
        
        document.dispatchEvent(new CustomEvent('sidebar-toggled', { 
            detail: { collapsed: isCollapsed } 
        }));
    }
    
    function toggleMobileSidebar() {
        if (sidebar && overlay) {
            sidebar.classList.toggle('show-mobile');
            overlay.classList.toggle('show');
            
            if (sidebar.classList.contains('show-mobile')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }
    }
    
    if (toggleSidebarDesktop) {
        toggleSidebarDesktop.addEventListener('click', function(e) {
            e.preventDefault();
            toggleSidebarCollapsed();
        });
    }
    
    if (toggleSidebarMobile) {
        toggleSidebarMobile.addEventListener('click', function(e) {
            e.preventDefault();
            toggleMobileSidebar();
        });
    }
    
    if (overlay) {
        overlay.addEventListener('click', function() {
            if (sidebar && sidebar.classList.contains('show-mobile')) {
                sidebar.classList.remove('show-mobile');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
    }
    
    function restoreSidebarState() {
        const savedState = localStorage.getItem('sidebar_collapsed');
        if (savedState !== null) {
            const shouldBeCollapsed = savedState === 'true';
            const body = document.body;
            
            if (body.__x) {
                body.__x.$data.sidebarCollapsed = shouldBeCollapsed;
            } else {
                if (shouldBeCollapsed) {
                    body.classList.add('sidebar-collapsed');
                } else {
                    body.classList.remove('sidebar-collapsed');
                }
            }
            
            if (toggleSidebarDesktop) {
                const icon = toggleSidebarDesktop.querySelector('i');
                if (icon) {
                    icon.className = shouldBeCollapsed ? 'fas fa-chevron-right' : 'fas fa-chevron-left';
                }
            }
        }
    }
    
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 768) {
                if (sidebar && sidebar.classList.contains('show-mobile')) {
                    sidebar.classList.remove('show-mobile');
                    if (overlay) overlay.classList.remove('show');
                    document.body.style.overflow = '';
                }
            }
        }, 250);
    });
    
    restoreSidebarState();
    
    // ============ QUICK SEARCH FUNCTIONALITY ============
    const quickSearchInput = document.getElementById('quickSearchInput');
    const advancedSearchBtn = document.getElementById('advancedSearchBtn');
    const searchModal = document.getElementById('searchModal');
    const globalSearchInput = document.getElementById('globalSearchInput');
    const searchCategory = document.getElementById('searchCategory');
    const mobileSearchBtn = document.getElementById('mobileSearchBtn');
    
    // Filter categories based on user role
    if (searchCategory) {
        const superAdminOptions = ['system-settings', 'payment-providers', 'sms-providers', 'whatsapp-providers', 'superadmin-billing'];
        const adminOptions = ['whatsapp-messages', 'whatsapp-templates', 'whatsapp-logs'];
        
        Array.from(searchCategory.options).forEach(option => {
            // Remove super admin options for non-super admins
            if (!isSuperAdmin && superAdminOptions.includes(option.value)) {
                option.remove();
            }
            // Remove admin options for non-admin/non-super-admin
            if (!isAdmin && !isSuperAdmin && adminOptions.includes(option.value)) {
                option.remove();
            }
        });
    }
    
    // Create dropdown for quick search results
    const searchResultsDropdown = document.createElement('div');
    searchResultsDropdown.id = 'quickSearchResults';
    searchResultsDropdown.className = 'quick-search-results hidden';
    searchResultsDropdown.style.cssText = `
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        margin-top: 4px;
        max-height: 400px;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        display: none;
    `;
    
    if (quickSearchInput) {
        quickSearchInput.parentElement.style.position = 'relative';
        quickSearchInput.parentElement.appendChild(searchResultsDropdown);
        
        let debounceTimer;
        let currentSearchTerm = '';
        
        quickSearchInput.addEventListener('input', function(e) {
            clearTimeout(debounceTimer);
            const searchTerm = this.value.trim();
            currentSearchTerm = searchTerm;
            
            if (searchTerm.length < 2) {
                searchResultsDropdown.style.display = 'none';
                searchResultsDropdown.classList.add('hidden');
                return;
            }
            
            debounceTimer = setTimeout(() => {
                performMultiFieldSearch(searchTerm);
            }, 500);
        });
        
        quickSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const searchTerm = this.value.trim();
                if (searchTerm) {
                    searchResultsDropdown.style.display = 'none';
                    searchResultsDropdown.classList.add('hidden');
                    showFullResultsModal(searchTerm, 'all');
                }
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === '/' && !e.ctrlKey && !e.metaKey && 
                document.activeElement !== quickSearchInput &&
                document.activeElement.tagName !== 'INPUT' &&
                document.activeElement.tagName !== 'TEXTAREA' &&
                document.activeElement.tagName !== 'SELECT') {
                e.preventDefault();
                quickSearchInput.focus();
                quickSearchInput.select();
            }
            
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                quickSearchInput.focus();
                quickSearchInput.select();
            }
        });
        
        document.addEventListener('click', function(e) {
            if (!quickSearchInput.parentElement.contains(e.target)) {
                searchResultsDropdown.style.display = 'none';
                searchResultsDropdown.classList.add('hidden');
            }
        });
        
        function updateQuickSearchTheme() {
            const theme = getCurrentTheme();
            if (theme === 'dark') {
                quickSearchInput.style.backgroundColor = '#2d3748';
                quickSearchInput.style.borderColor = '#4a5568';
                quickSearchInput.style.color = '#e2e8f0';
            } else {
                quickSearchInput.style.backgroundColor = '#f7fafc';
                quickSearchInput.style.borderColor = '#e2e8f0';
                quickSearchInput.style.color = '#2d3748';
            }
        }
        
        updateQuickSearchTheme();
        document.addEventListener('theme-changed', updateQuickSearchTheme);
    }
    
    // ============ PERFORM MULTI-FIELD SEARCH ============
    async function performMultiFieldSearch(searchTerm) {
        if (!searchTerm || searchTerm.length < 2) return;
        
        searchResultsDropdown.innerHTML = `
            <div class="p-4 text-center" style="color: var(--text-secondary);">
                <i class="fas fa-spinner fa-spin mr-2"></i>
                Searching for "${escapeHtml(searchTerm)}" in all modules...
            </div>
        `;
        searchResultsDropdown.style.display = 'block';
        searchResultsDropdown.classList.remove('hidden');
        
        try {
            const allResults = [];
            const searchLower = searchTerm.toLowerCase();
            
            const moduleKeys = Object.keys(searchFieldsConfig);
            
            const searchPromises = moduleKeys.map(async (moduleKey) => {
                try {
                    const results = await searchModule(moduleKey, searchLower);
                    return results;
                } catch (error) {
                    console.error(`Error searching ${moduleKey}:`, error);
                    return [];
                }
            });
            
            const allModuleResults = await Promise.all(searchPromises);
            
            allModuleResults.forEach(results => {
                allResults.push(...results);
            });
            
            allResults.sort((a, b) => (b.relevance || 0) - (a.relevance || 0));
            
            if (allResults.length > 0) {
                displayMultiFieldResults(allResults.slice(0, 15), searchTerm);
            } else {
                searchResultsDropdown.innerHTML = `
                    <div class="p-4 text-center" style="color: var(--text-secondary);">
                        <i class="fas fa-search mr-2"></i>
                        No results found for "${escapeHtml(searchTerm)}" in any module
                    </div>
                    <div class="p-3 border-t" style="border-color: var(--border-color);">
                        <button class="w-full text-center text-sm py-2 rounded view-all-results-btn" 
                                style="color: var(--primary);">
                            <i class="fas fa-arrow-right mr-2"></i>
                            Search all categories for "${escapeHtml(searchTerm)}"
                        </button>
                    </div>
                `;
                
                const viewAllBtn = searchResultsDropdown.querySelector('.view-all-results-btn');
                if (viewAllBtn) {
                    viewAllBtn.addEventListener('click', function() {
                        searchResultsDropdown.style.display = 'none';
                        searchResultsDropdown.classList.add('hidden');
                        showFullResultsModal(searchTerm, 'all');
                    });
                }
            }
        } catch (error) {
            console.error('Multi-field search error:', error);
            searchResultsDropdown.innerHTML = `
                <div class="p-4 text-center" style="color: var(--text-secondary);">
                    <i class="fas fa-exclamation-triangle mr-2" style="color: var(--danger);"></i>
                    Search failed. Please try again.
                </div>
                <div class="p-3 border-t" style="border-color: var(--border-color);">
                    <button class="w-full text-center text-sm py-2 rounded view-all-results-btn" 
                            style="color: var(--primary);">
                        <i class="fas fa-arrow-right mr-2"></i>
                        Search all categories
                    </button>
                </div>
            `;
            
            const viewAllBtn = searchResultsDropdown.querySelector('.view-all-results-btn');
            if (viewAllBtn) {
                viewAllBtn.addEventListener('click', function() {
                    searchResultsDropdown.style.display = 'none';
                    searchResultsDropdown.classList.add('hidden');
                    showFullResultsModal(searchTerm, 'all');
                });
            }
        }
    }
    
    // ============ SEARCH INDIVIDUAL MODULE ============
    async function searchModule(moduleKey, searchLower) {
        const config = searchFieldsConfig[moduleKey];
        if (!config) return [];

        const results = [];
        const searchUrl = config.route + `?search=${encodeURIComponent(searchLower)}&limit=20`;

        try {
            const response = await fetch(searchUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) return results;

            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            const resultItems = doc.querySelectorAll('table tbody tr, .search-result, .list-item, .item-card, .card, .row-item');
            
            resultItems.forEach((item) => {
                const textContent = item.textContent.toLowerCase();
                
                if (!textContent.includes(searchLower)) return;
                
                let displayTitle = '';
                let displaySubtitle = '';
                let matchFields = [];
                let matchedFieldNames = [];

                const cells = item.querySelectorAll('td');
                
                if (moduleKey === 'users') {
                    const nameEl = item.querySelector('td:nth-child(2)') || item.querySelector('.name') || item.querySelector('[data-field="name"]');
                    const emailEl = item.querySelector('td:nth-child(3)') || item.querySelector('.email') || item.querySelector('[data-field="email"]');
                    const phoneEl = item.querySelector('td:nth-child(4)') || item.querySelector('.phone') || item.querySelector('[data-field="phone"]');
                    const typeEl = item.querySelector('td:nth-child(5)') || item.querySelector('.type') || item.querySelector('[data-field="type"]');
                    
                    displayTitle = nameEl ? nameEl.textContent.trim() : (cells[1] ? cells[1].textContent.trim() : '');
                    displaySubtitle = emailEl ? emailEl.textContent.trim() : (cells[2] ? cells[2].textContent.trim() : '');
                    
                    if (nameEl && nameEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Name'); matchedFieldNames.push('Name'); }
                    if (emailEl && emailEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Email'); matchedFieldNames.push('Email'); }
                    if (phoneEl && phoneEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Phone'); matchedFieldNames.push('Phone'); }
                    if (typeEl && typeEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Type'); matchedFieldNames.push('Type'); }
                    
                    if (matchFields.length === 0) {
                        matchFields.push('General');
                        matchedFieldNames.push('General');
                    }
                } else if (moduleKey === 'properties') {
                    const nameEl = item.querySelector('td:nth-child(2)') || item.querySelector('.property-name') || item.querySelector('[data-field="property_name"]');
                    const streetEl = item.querySelector('td:nth-child(3)') || item.querySelector('.street') || item.querySelector('[data-field="street_name"]');
                    const addressEl = item.querySelector('td:nth-child(4)') || item.querySelector('.digital-address') || item.querySelector('[data-field="digital_address"]');
                    const zoneEl = item.querySelector('td:nth-child(5)') || item.querySelector('.zone') || item.querySelector('[data-field="zone"]');
                    
                    displayTitle = nameEl ? nameEl.textContent.trim() : (cells[1] ? cells[1].textContent.trim() : '');
                    displaySubtitle = streetEl ? streetEl.textContent.trim() : (cells[2] ? cells[2].textContent.trim() : '');
                    
                    if (nameEl && nameEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Property Name'); matchedFieldNames.push('Property Name'); }
                    if (streetEl && streetEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Street'); matchedFieldNames.push('Street'); }
                    if (addressEl && addressEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Digital Address'); matchedFieldNames.push('Digital Address'); }
                    if (zoneEl && zoneEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Zone'); matchedFieldNames.push('Zone'); }
                    
                    if (matchFields.length === 0) {
                        matchFields.push('General');
                        matchedFieldNames.push('General');
                    }
                } else if (moduleKey === 'invoices') {
                    const numberEl = item.querySelector('td:nth-child(2)') || item.querySelector('.invoice-number') || item.querySelector('[data-field="invoice_number"]');
                    const amountEl = item.querySelector('td:nth-child(3)') || item.querySelector('.amount') || item.querySelector('[data-field="amount"]');
                    const statusEl = item.querySelector('td:nth-child(4)') || item.querySelector('.status') || item.querySelector('[data-field="status"]');
                    const periodEl = item.querySelector('td:nth-child(5)') || item.querySelector('.period') || item.querySelector('[data-field="period"]');
                    
                    displayTitle = numberEl ? numberEl.textContent.trim() : (cells[1] ? cells[1].textContent.trim() : '');
                    displaySubtitle = amountEl ? amountEl.textContent.trim() : (cells[2] ? cells[2].textContent.trim() : '');
                    
                    if (numberEl && numberEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Invoice Number'); matchedFieldNames.push('Invoice Number'); }
                    if (amountEl && amountEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Amount'); matchedFieldNames.push('Amount'); }
                    if (statusEl && statusEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Status'); matchedFieldNames.push('Status'); }
                    if (periodEl && periodEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Period'); matchedFieldNames.push('Period'); }
                    
                    if (matchFields.length === 0) {
                        matchFields.push('General');
                        matchedFieldNames.push('General');
                    }
                } else if (moduleKey === 'payments') {
                    const transIdEl = item.querySelector('td:nth-child(2)') || item.querySelector('.transaction-id') || item.querySelector('[data-field="transaction_id"]');
                    const refEl = item.querySelector('td:nth-child(3)') || item.querySelector('.reference') || item.querySelector('[data-field="reference_number"]');
                    const amountEl = item.querySelector('td:nth-child(4)') || item.querySelector('.amount') || item.querySelector('[data-field="amount"]');
                    const statusEl = item.querySelector('td:nth-child(5)') || item.querySelector('.status') || item.querySelector('[data-field="status"]');
                    const payerEl = item.querySelector('td:nth-child(6)') || item.querySelector('.payer') || item.querySelector('[data-field="payer_name"]');
                    
                    displayTitle = transIdEl ? transIdEl.textContent.trim() : (cells[1] ? cells[1].textContent.trim() : '');
                    displaySubtitle = refEl ? refEl.textContent.trim() : (cells[2] ? cells[2].textContent.trim() : '');
                    
                    if (transIdEl && transIdEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Transaction ID'); matchedFieldNames.push('Transaction ID'); }
                    if (refEl && refEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Reference'); matchedFieldNames.push('Reference'); }
                    if (amountEl && amountEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Amount'); matchedFieldNames.push('Amount'); }
                    if (statusEl && statusEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Status'); matchedFieldNames.push('Status'); }
                    if (payerEl && payerEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Payer'); matchedFieldNames.push('Payer'); }
                    
                    if (matchFields.length === 0) {
                        matchFields.push('General');
                        matchedFieldNames.push('General');
                    }
                } else if (moduleKey === 'ownership-transfers') {
                    const refEl = item.querySelector('td:nth-child(2)') || item.querySelector('.reference') || item.querySelector('[data-field="document_reference"]');
                    const propertyEl = item.querySelector('td:nth-child(3)') || item.querySelector('.property') || item.querySelector('[data-field="property_name"]');
                    const currentEl = item.querySelector('td:nth-child(4)') || item.querySelector('.current-landlord') || item.querySelector('[data-field="current_landlord"]');
                    const newEl = item.querySelector('td:nth-child(5)') || item.querySelector('.new-landlord') || item.querySelector('[data-field="new_landlord"]');
                    
                    displayTitle = refEl ? refEl.textContent.trim() : (cells[1] ? cells[1].textContent.trim() : '');
                    displaySubtitle = propertyEl ? propertyEl.textContent.trim() : (cells[2] ? cells[2].textContent.trim() : '');
                    
                    if (refEl && refEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Reference'); matchedFieldNames.push('Reference'); }
                    if (propertyEl && propertyEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Property'); matchedFieldNames.push('Property'); }
                    if (currentEl && currentEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Current Landlord'); matchedFieldNames.push('Current Landlord'); }
                    if (newEl && newEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('New Landlord'); matchedFieldNames.push('New Landlord'); }
                    
                    if (matchFields.length === 0) {
                        matchFields.push('General');
                        matchedFieldNames.push('General');
                    }
                } else if (moduleKey === 'security-posts') {
                    const nameEl = item.querySelector('td:nth-child(2)') || item.querySelector('.post-name') || item.querySelector('[data-field="post_name"]');
                    const locationEl = item.querySelector('td:nth-child(3)') || item.querySelector('.location') || item.querySelector('[data-field="location"]');
                    const codeEl = item.querySelector('td:nth-child(4)') || item.querySelector('.code') || item.querySelector('[data-field="code"]');
                    
                    displayTitle = nameEl ? nameEl.textContent.trim() : (cells[1] ? cells[1].textContent.trim() : '');
                    displaySubtitle = locationEl ? locationEl.textContent.trim() : (cells[2] ? cells[2].textContent.trim() : '');
                    
                    if (nameEl && nameEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Post Name'); matchedFieldNames.push('Post Name'); }
                    if (locationEl && locationEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Location'); matchedFieldNames.push('Location'); }
                    if (codeEl && codeEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Code'); matchedFieldNames.push('Code'); }
                    
                    if (matchFields.length === 0) {
                        matchFields.push('General');
                        matchedFieldNames.push('General');
                    }
                } else if (moduleKey === 'registration-plans') {
                    const nameEl = item.querySelector('td:nth-child(2)') || item.querySelector('.plan-name') || item.querySelector('[data-field="plan_name"]');
                    const descEl = item.querySelector('td:nth-child(3)') || item.querySelector('.description') || item.querySelector('[data-field="description"]');
                    const statusEl = item.querySelector('td:nth-child(4)') || item.querySelector('.status') || item.querySelector('[data-field="status"]');
                    
                    displayTitle = nameEl ? nameEl.textContent.trim() : (cells[1] ? cells[1].textContent.trim() : '');
                    displaySubtitle = descEl ? descEl.textContent.trim() : (cells[2] ? cells[2].textContent.trim() : '');
                    
                    if (nameEl && nameEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Plan Name'); matchedFieldNames.push('Plan Name'); }
                    if (descEl && descEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Description'); matchedFieldNames.push('Description'); }
                    if (statusEl && statusEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Status'); matchedFieldNames.push('Status'); }
                    
                    if (matchFields.length === 0) {
                        matchFields.push('General');
                        matchedFieldNames.push('General');
                    }
                } else if (moduleKey === 'construction-registrations') {
                    const regNumEl = item.querySelector('td:nth-child(2)') || item.querySelector('.registration-number') || item.querySelector('[data-field="registration_number"]');
                    const propNameEl = item.querySelector('td:nth-child(3)') || item.querySelector('.property-name') || item.querySelector('[data-field="property_name"]');
                    const statusEl = item.querySelector('td:nth-child(4)') || item.querySelector('.status') || item.querySelector('[data-field="status"]');
                    const landlordEl = item.querySelector('td:nth-child(5)') || item.querySelector('.landlord-name') || item.querySelector('[data-field="landlord_name"]');
                    
                    displayTitle = regNumEl ? regNumEl.textContent.trim() : (cells[1] ? cells[1].textContent.trim() : '');
                    displaySubtitle = propNameEl ? propNameEl.textContent.trim() : (cells[2] ? cells[2].textContent.trim() : '');
                    
                    if (regNumEl && regNumEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Registration Number'); matchedFieldNames.push('Registration Number'); }
                    if (propNameEl && propNameEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Property Name'); matchedFieldNames.push('Property Name'); }
                    if (statusEl && statusEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Status'); matchedFieldNames.push('Status'); }
                    if (landlordEl && landlordEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Landlord'); matchedFieldNames.push('Landlord'); }
                    
                    if (matchFields.length === 0) {
                        matchFields.push('General');
                        matchedFieldNames.push('General');
                    }
                } else if (moduleKey === 'whatsapp-messages' || moduleKey === 'whatsapp-templates' || moduleKey === 'whatsapp-logs') {
                    // WhatsApp specific search
                    const idEl = item.querySelector('td:nth-child(2)') || item.querySelector('.id') || item.querySelector('[data-field="id"]');
                    const nameEl = item.querySelector('td:nth-child(3)') || item.querySelector('.name') || item.querySelector('[data-field="name"]');
                    const statusEl = item.querySelector('td:nth-child(4)') || item.querySelector('.status') || item.querySelector('[data-field="status"]');
                    
                    displayTitle = nameEl ? nameEl.textContent.trim() : (idEl ? idEl.textContent.trim() : (cells[1] ? cells[1].textContent.trim() : ''));
                    displaySubtitle = statusEl ? statusEl.textContent.trim() : (cells[2] ? cells[2].textContent.trim() : '');
                    
                    if (idEl && idEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('ID'); matchedFieldNames.push('ID'); }
                    if (nameEl && nameEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Name'); matchedFieldNames.push('Name'); }
                    if (statusEl && statusEl.textContent.toLowerCase().includes(searchLower)) { matchFields.push('Status'); matchedFieldNames.push('Status'); }
                    
                    if (matchFields.length === 0) {
                        matchFields.push('General');
                        matchedFieldNames.push('General');
                    }
                } else {
                    if (cells.length >= 2) {
                        displayTitle = cells[0] ? cells[0].textContent.trim() : '';
                        displaySubtitle = cells[1] ? cells[1].textContent.trim() : '';
                    } else {
                        const textParts = textContent.split('\n').filter(s => s.trim().length > 0);
                        displayTitle = textParts[0] || 'Item';
                        displaySubtitle = textParts[1] || '';
                    }
                    
                    if (textContent.includes(searchLower)) {
                        matchFields.push('General');
                        matchedFieldNames.push('General');
                    }
                }

                if (matchFields.length > 0 && displayTitle) {
                    let subtitle = displaySubtitle;
                    if (!subtitle || subtitle === displayTitle) {
                        const config = searchFieldsConfig[moduleKey];
                        subtitle = config ? config.label : moduleKey;
                    }
                    
                    results.push({
                        id: results.length + 1,
                        category: moduleKey,
                        categoryLabel: searchFieldsConfig[moduleKey]?.label || moduleKey,
                        title: displayTitle,
                        subtitle: subtitle,
                        matchFields: matchedFieldNames.join(', '),
                        url: searchUrl,
                        icon: searchFieldsConfig[moduleKey]?.icon || 'fas fa-file-alt',
                        relevance: matchFields.length
                    });
                }
            });

            if (results.length === 0) {
                const pageTitle = doc.querySelector('h1, h2, .page-title, .section-title');
                if (pageTitle && pageTitle.textContent.toLowerCase().includes(searchLower)) {
                    results.push({
                        id: 1,
                        category: moduleKey,
                        categoryLabel: searchFieldsConfig[moduleKey]?.label || moduleKey,
                        title: pageTitle.textContent.trim(),
                        subtitle: 'Page contains your search term',
                        matchFields: 'Page Title',
                        url: searchUrl,
                        icon: searchFieldsConfig[moduleKey]?.icon || 'fas fa-file-alt',
                        relevance: 1
                    });
                }
            }

            return results;
        } catch (error) {
            console.error(`Error searching ${moduleKey}:`, error);
            return results;
        }
    }

    // ============ DISPLAY QUICK RESULTS (DROPDOWN) ============
    function displayMultiFieldResults(results, searchTerm) {
        let html = `
            <div class="p-2 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                <span class="text-xs font-medium" style="color: var(--text-secondary);">
                    <i class="fas fa-search mr-1"></i> Results (${results.length}) - Found in multiple fields
                </span>
                <button class="view-all-quick-results text-xs px-2 py-1 rounded transition-colors"
                        style="color: var(--primary);">
                    View All Results →
                </button>
            </div>
        `;

        results.forEach(result => {
            const categoryColor = getCategoryColor(result.category);
            
            html += `
                <a href="${result.url}" 
                   class="quick-result-item block p-3 hover:bg-opacity-10 transition-all duration-150"
                   style="border-bottom: 1px solid var(--border-color); text-decoration: none; cursor: pointer;">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 mr-3 mt-1">
                            <i class="${result.icon}" style="color: var(--primary); width: 16px;"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start">
                                <p class="text-sm font-medium truncate" style="color: var(--text-primary);">
                                    ${highlightText(result.title, searchTerm)}
                                </p>
                                <span class="text-xs ml-2 px-2 py-0.5 rounded-full flex-shrink-0" 
                                      style="background-color: ${categoryColor}; color: white;">
                                    ${result.categoryLabel}
                                </span>
                            </div>
                            ${result.subtitle && result.subtitle !== result.title ? `
                                <p class="text-xs mt-1 truncate" style="color: var(--text-secondary);">
                                    ${highlightText(result.subtitle, searchTerm)}
                                </p>
                            ` : ''}
                            ${result.matchFields ? `
                                <p class="text-xs mt-1" style="color: var(--text-secondary); opacity: 0.8;">
                                    <i class="fas fa-search-location mr-1"></i>
                                    Matched in: ${result.matchFields}
                                </p>
                            ` : ''}
                            ${result.relevance > 1 ? `
                                <p class="text-xs mt-1" style="color: var(--success); opacity: 0.7;">
                                    <i class="fas fa-star mr-1"></i>
                                    ${result.relevance} fields matched
                                </p>
                            ` : ''}
                        </div>
                        <i class="fas fa-chevron-right text-xs ml-2 mt-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                    </div>
                </a>
            `;
        });

        searchResultsDropdown.innerHTML = html;
        searchResultsDropdown.style.display = 'block';
        searchResultsDropdown.classList.remove('hidden');

        const viewAllBtn = searchResultsDropdown.querySelector('.view-all-quick-results');
        if (viewAllBtn) {
            viewAllBtn.addEventListener('click', function() {
                searchResultsDropdown.style.display = 'none';
                searchResultsDropdown.classList.add('hidden');
                showFullResultsModal(searchTerm, 'all');
            });
        }

        document.querySelectorAll('.quick-result-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
            });
            item.addEventListener('mouseleave', function() {
                this.style.backgroundColor = 'transparent';
            });
        });
    }

    // ============ FULL RESULTS MODAL ============
    function showFullResultsModal(searchTerm, category) {
        if (!searchTerm || searchTerm.trim() === '') {
            showNotification('Please enter a search term', 'warning');
            return;
        }
        
        const trimmedTerm = searchTerm.trim();
        const selectedCategory = category || (searchCategory ? searchCategory.value : 'all');
        
        // Save to recent searches
        saveRecentSearch(trimmedTerm, selectedCategory);
        
        // Create the modal
        const modalOverlay = document.createElement('div');
        modalOverlay.id = 'fullResultsModal';
        modalOverlay.className = 'fixed inset-0 z-50 flex items-center justify-center';
        modalOverlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            padding: 20px;
        `;
        
        modalOverlay.innerHTML = `
            <div class="full-results-modal" style="
                background-color: var(--card-bg);
                border-radius: 16px;
                max-width: 900px;
                width: 100%;
                max-height: 85vh;
                display: flex;
                flex-direction: column;
                overflow: hidden;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
                border: 1px solid var(--border-color);
            ">
                <!-- Modal Header -->
                <div style="
                    padding: 16px 24px;
                    border-bottom: 1px solid var(--border-color);
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    flex-shrink: 0;
                    background-color: var(--bg-secondary);
                ">
                    <div>
                        <h3 style="color: var(--text-primary); font-size: 18px; font-weight: 600; margin: 0;">
                            <i class="fas fa-search mr-2" style="color: var(--primary);"></i>
                            Search Results for "${escapeHtml(trimmedTerm)}"
                        </h3>
                        <p style="color: var(--text-secondary); font-size: 13px; margin: 4px 0 0 0;">
                            Category: ${selectedCategory === 'all' ? 'All Categories' : (searchFieldsConfig[selectedCategory]?.label || selectedCategory)}
                        </p>
                    </div>
                    <button id="closeFullResultsModal" style="
                        background: none;
                        border: none;
                        color: var(--text-secondary);
                        font-size: 24px;
                        cursor: pointer;
                        padding: 4px 8px;
                        border-radius: 6px;
                        transition: background-color 0.2s;
                    " onmouseover="this.style.backgroundColor='var(--bg-primary)'" onmouseout="this.style.backgroundColor='transparent'">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <!-- Modal Body - Loading State -->
                <div id="fullResultsBody" style="
                    padding: 24px;
                    overflow-y: auto;
                    flex: 1;
                    min-height: 200px;
                ">
                    <div style="text-align: center; padding: 40px 0; color: var(--text-secondary);">
                        <i class="fas fa-spinner fa-spin" style="font-size: 30px; display: block; margin-bottom: 16px; color: var(--primary);"></i>
                        Loading search results...
                    </div>
                </div>
                
                <!-- Modal Footer -->
                <div style="
                    padding: 12px 24px;
                    border-top: 1px solid var(--border-color);
                    display: flex;
                    justify-content: flex-end;
                    gap: 12px;
                    flex-shrink: 0;
                    background-color: var(--bg-secondary);
                ">
                    <button onclick="window.performFullSearch('${escapeHtml(trimmedTerm)}', '${selectedCategory}')" style="
                        background-color: var(--primary);
                        color: white;
                        border: none;
                        padding: 8px 20px;
                        border-radius: 8px;
                        cursor: pointer;
                        font-size: 14px;
                        font-weight: 500;
                        transition: opacity 0.2s;
                    " onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                        <i class="fas fa-arrow-right mr-2"></i> Go to Full Page
                    </button>
                    <button id="closeFullResultsModalBtn" style="
                        background-color: var(--bg-primary);
                        color: var(--text-secondary);
                        border: 1px solid var(--border-color);
                        padding: 8px 20px;
                        border-radius: 8px;
                        cursor: pointer;
                        font-size: 14px;
                        transition: background-color 0.2s;
                    " onmouseover="this.style.backgroundColor='var(--bg-secondary)'" onmouseout="this.style.backgroundColor='var(--bg-primary)'">
                        Close
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modalOverlay);
        document.body.style.overflow = 'hidden';
        
        // Close handlers
        const closeModal = () => {
            if (document.getElementById('fullResultsModal')) {
                document.getElementById('fullResultsModal').remove();
                document.body.style.overflow = '';
            }
        };
        
        document.getElementById('closeFullResultsModal').addEventListener('click', closeModal);
        document.getElementById('closeFullResultsModalBtn').addEventListener('click', closeModal);
        modalOverlay.addEventListener('click', function(e) {
            if (e.target === modalOverlay) closeModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });
        
        // Load full results
        loadFullResults(trimmedTerm, selectedCategory);
    }

    // ============ LOAD FULL RESULTS ============
    async function loadFullResults(searchTerm, category) {
        const body = document.getElementById('fullResultsBody');
        if (!body) return;
        
        try {
            const searchLower = searchTerm.toLowerCase();
            let allResults = [];
            
            // Determine which modules to search
            let moduleKeys;
            if (category === 'all') {
                moduleKeys = Object.keys(searchFieldsConfig);
            } else if (searchFieldsConfig[category]) {
                moduleKeys = [category];
            } else {
                moduleKeys = Object.keys(searchFieldsConfig);
            }
            
            // Search modules
            const searchPromises = moduleKeys.map(async (moduleKey) => {
                try {
                    const results = await searchModule(moduleKey, searchLower);
                    return results;
                } catch (error) {
                    console.error(`Error searching ${moduleKey}:`, error);
                    return [];
                }
            });
            
            const allModuleResults = await Promise.all(searchPromises);
            
            allModuleResults.forEach(results => {
                allResults.push(...results);
            });
            
            allResults.sort((a, b) => (b.relevance || 0) - (a.relevance || 0));
            
            if (allResults.length === 0) {
                body.innerHTML = `
                    <div style="text-align: center; padding: 60px 20px; color: var(--text-secondary);">
                        <i class="fas fa-search" style="font-size: 40px; display: block; margin-bottom: 16px; opacity: 0.3;"></i>
                        <p style="font-size: 18px; font-weight: 500; color: var(--text-primary);">No results found</p>
                        <p>No results found for "${escapeHtml(searchTerm)}"</p>
                    </div>
                `;
                return;
            }
            
            // Display results
            let html = `
                <div style="margin-bottom: 16px; color: var(--text-secondary); font-size: 14px;">
                    Found ${allResults.length} result(s) for "${escapeHtml(searchTerm)}"
                </div>
                <div style="display: grid; grid-template-columns: 1fr; gap: 8px;">
            `;
            
            allResults.forEach((result, index) => {
                const categoryColor = getCategoryColor(result.category);
                
                html += `
                    <div style="
                        background-color: var(--bg-secondary);
                        border-radius: 8px;
                        padding: 12px 16px;
                        border: 1px solid var(--border-color);
                        display: flex;
                        align-items: center;
                        gap: 12px;
                        transition: all 0.2s;
                        cursor: pointer;
                    " onmouseover="this.style.borderColor='var(--primary)'; this.style.backgroundColor='rgba(var(--primary-rgb), 0.05)'" 
                       onmouseout="this.style.borderColor='var(--border-color)'; this.style.backgroundColor='var(--bg-secondary)'"
                       onclick="window.location.href='${result.url}'">
                        <div style="flex-shrink: 0; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background-color: rgba(var(--primary-rgb), 0.1);">
                            <i class="${result.icon}" style="color: var(--primary); font-size: 14px;"></i>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                                <div>
                                    <p style="color: var(--text-primary); font-weight: 500; margin: 0; font-size: 14px;">
                                        ${highlightText(result.title, searchTerm)}
                                    </p>
                                    ${result.subtitle && result.subtitle !== result.title ? `
                                        <p style="color: var(--text-secondary); margin: 2px 0 0 0; font-size: 12px;">
                                            ${highlightText(result.subtitle, searchTerm)}
                                        </p>
                                    ` : ''}
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
                                    <span style="
                                        background-color: ${categoryColor};
                                        color: white;
                                        font-size: 10px;
                                        padding: 2px 8px;
                                        border-radius: 12px;
                                        font-weight: 500;
                                    ">
                                        ${result.categoryLabel}
                                    </span>
                                    ${result.relevance > 1 ? `
                                        <span style="color: var(--text-secondary); font-size: 10px; opacity: 0.6;">
                                            ${result.relevance} fields
                                        </span>
                                    ` : ''}
                                    <i class="fas fa-chevron-right" style="color: var(--text-secondary); font-size: 12px; opacity: 0.4;"></i>
                                </div>
                            </div>
                            ${result.matchFields ? `
                                <p style="color: var(--text-secondary); margin: 4px 0 0 0; font-size: 11px; opacity: 0.7;">
                                    <i class="fas fa-search-location mr-1"></i> ${result.matchFields}
                                </p>
                            ` : ''}
                        </div>
                    </div>
                `;
            });
            
            html += `</div>`;
            
            body.innerHTML = html;
            
        } catch (error) {
            console.error('Error loading full results:', error);
            body.innerHTML = `
                <div style="text-align: center; padding: 60px 20px; color: var(--danger);">
                    <i class="fas fa-exclamation-triangle" style="font-size: 40px; display: block; margin-bottom: 16px;"></i>
                    <p style="font-size: 18px; font-weight: 500;">Failed to load results</p>
                    <p>Please try again later.</p>
                </div>
            `;
        }
    }

    // ============ GET CATEGORY COLOR ============
    function getCategoryColor(category) {
        const colors = {
            'users': '#3b82f6',
            'properties': '#8b5cf6',
            'property-units': '#10b981',
            'registration-plans': '#f59e0b',
            'construction-registrations': '#f97316',
            'invoices': '#ef4444',
            'payments': '#06b6d4',
            'security-posts': '#8b5cf6',
            'security-shifts': '#6366f1',
            'security-schedules': '#14b8a6',
            'security-supervisor-assignments': '#8b5cf6',
            'security-reports': '#f59e0b',
            'ownership-transfers': '#f97316',
            'system-settings': '#6b7280',
            'payment-providers': '#8b5cf6',
            'sms-providers': '#ec4899',
            'whatsapp-providers': '#25D366',
            'whatsapp-messages': '#25D366',
            'whatsapp-templates': '#8b5cf6',
            'whatsapp-logs': '#3b82f6',
            'superadmin-billing': '#8b5cf6'
        };
        return colors[category] || '#6b7280';
    }
    
    // ============ PERFORM FULL SEARCH (Redirect to page) ============
    function performFullSearch(searchTerm, category) {
        if (!searchTerm || searchTerm.trim() === '') {
            showNotification('Please enter a search term', 'warning');
            return;
        }
        
        const trimmedTerm = searchTerm.trim();
        const selectedCategory = category || (searchCategory ? searchCategory.value : 'all');
        
        // Save to recent searches
        saveRecentSearch(trimmedTerm, selectedCategory);
        
        // Build and redirect to search URL
        const searchUrl = buildSearchUrl(selectedCategory, trimmedTerm);
        
        // Close all modals
        closeAllModals();
        
        // Redirect to search results
        window.location.href = searchUrl;
    }
    
    // ============ CLOSE ALL MODALS ============
    function closeAllModals() {
        // Close search modal
        const searchModal = document.getElementById('searchModal');
        if (searchModal) {
            searchModal.classList.add('hidden');
            document.body.classList.remove('modal-open');
        }
        
        // Close full results modal
        const fullResultsModal = document.getElementById('fullResultsModal');
        if (fullResultsModal) {
            fullResultsModal.remove();
            document.body.style.overflow = '';
        }
        
        // Close dropdown
        const dropdown = document.getElementById('quickSearchResults');
        if (dropdown) {
            dropdown.style.display = 'none';
            dropdown.classList.add('hidden');
        }
    }
    
    // ============ SAVE RECENT SEARCH ============
    function saveRecentSearch(term, category) {
        try {
            let recentSearches = JSON.parse(localStorage.getItem('global_recent_searches') || '[]');
            
            recentSearches = recentSearches.filter(item => !(item.term === term && item.category === category));
            recentSearches.unshift({ term: term, category: category, timestamp: Date.now() });
            recentSearches = recentSearches.slice(0, 10);
            
            localStorage.setItem('global_recent_searches', JSON.stringify(recentSearches));
            displayRecentSearches();
        } catch (e) {
            console.warn('Could not save recent search:', e);
        }
    }
    
    // ============ DISPLAY RECENT SEARCHES ============
    function displayRecentSearches() {
        const container = document.getElementById('recentSearchesList');
        if (!container) return;
        
        try {
            let recentSearches = JSON.parse(localStorage.getItem('global_recent_searches') || '[]');
            
            if (recentSearches.length === 0) {
                container.innerHTML = '<div class="text-sm text-gray-500 italic">No recent searches</div>';
                return;
            }
            
            const categoryNames = {
                'all': 'All',
                'users': 'Users',
                'registration-plans': 'Plans',
                'construction-registrations': 'Construction',
                'properties': 'Properties',
                'property-units': 'Units',
                'payments': 'Payments',
                'invoices': 'Invoices',
                'security-posts': 'Posts',
                'security-shifts': 'Shifts',
                'security-schedules': 'Schedules',
                'security-supervisor-assignments': 'Assignments',
                'security-reports': 'Reports',
                'ownership-transfers': 'Transfers',
                'system-settings': 'Settings',
                'payment-providers': 'Payment',
                'sms-providers': 'SMS',
                'whatsapp-providers': 'WhatsApp',
                'whatsapp-messages': 'WhatsApp Msgs',
                'whatsapp-templates': 'Templates',
                'whatsapp-logs': 'Logs',
                'superadmin-billing': 'Billing'
            };
            
            let html = '';
            for (const search of recentSearches) {
                const categoryName = categoryNames[search.category] || search.category;
                const displayTerm = search.term.length > 30 ? search.term.substring(0, 30) + '...' : search.term;
                
                html += `
                    <button class="recent-search-item px-3 py-1 rounded-full text-xs transition-colors duration-200 flex items-center gap-1"
                            data-term="${escapeHtml(search.term)}"
                            data-category="${search.category}"
                            style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-secondary);">
                        <i class="fas fa-history text-xs"></i>
                        <span>${escapeHtml(displayTerm)}</span>
                        <span class="text-xs opacity-60">(${categoryName})</span>
                    </button>
                `;
            }
            
            container.innerHTML = html;
            
            document.querySelectorAll('.recent-search-item').forEach(btn => {
                btn.addEventListener('click', function() {
                    const term = this.dataset.term;
                    const category = this.dataset.category;
                    if (term) {
                        const searchInput = document.getElementById('globalSearchInput');
                        const categorySelect = document.getElementById('searchCategory');
                        if (searchInput) searchInput.value = term;
                        if (categorySelect && category) categorySelect.value = category;
                        showFullResultsModal(term, category);
                    }
                });
            });
        } catch (e) {
            console.warn('Could not display recent searches:', e);
        }
    }
    
    // ============ CLEAR RECENT SEARCHES ============
    function clearRecentSearches() {
        if (confirm('Clear all recent searches?')) {
            localStorage.removeItem('global_recent_searches');
            displayRecentSearches();
            showNotification('Recent searches cleared', 'success');
        }
    }
    
    // ============ HELPER FUNCTIONS ============
    function showNotification(message, type = 'info') {
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.style.cssText = 'position: fixed; bottom: 20px; right: 20px; z-index: 9999;';
            document.body.appendChild(toastContainer);
        }
        
        const toast = document.createElement('div');
        const bgColor = type === 'error' ? '#ef4444' : (type === 'success' ? '#10b981' : '#3b82f6');
        toast.style.cssText = `
            background-color: ${bgColor};
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            margin-top: 8px;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideInRight 0.3s ease-out;
            cursor: pointer;
        `;
        toast.textContent = message;
        
        toastContainer.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
        
        toast.onclick = () => toast.remove();
    }
    
    function highlightText(text, searchTerm) {
        if (!text || !searchTerm) return text;
        const regex = new RegExp(`(${escapeRegex(searchTerm)})`, 'gi');
        return text.replace(regex, '<mark style="background-color: rgba(var(--primary-rgb), 0.3); color: inherit; padding: 0 2px; border-radius: 2px;">$1</mark>');
    }
    
    function escapeRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // ============ SEARCH MODAL FUNCTIONALITY ============
    function openSearchModal() {
        if (searchModal) {
            searchModal.classList.remove('hidden');
            document.body.classList.add('modal-open');
            updateSearchModalTheme();
            displayRecentSearches();
            
            setTimeout(() => {
                if (globalSearchInput) {
                    globalSearchInput.focus();
                }
            }, 150);
        }
    }
    
    function closeSearchModalFunc() {
        if (searchModal) {
            searchModal.classList.add('hidden');
            document.body.classList.remove('modal-open');
        }
    }
    
    if (advancedSearchBtn && searchModal) {
        advancedSearchBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openSearchModal();
        });
    }
    
    if (mobileSearchBtn) {
        mobileSearchBtn.addEventListener('click', function() {
            openSearchModal();
        });
    }
    
    const closeSearchModal = document.getElementById('closeSearchModal');
    const cancelSearch = document.getElementById('cancelSearch');
    
    if (closeSearchModal) {
        closeSearchModal.addEventListener('click', closeSearchModalFunc);
    }
    if (cancelSearch) {
        cancelSearch.addEventListener('click', closeSearchModalFunc);
    }
    
    if (searchModal) {
        searchModal.addEventListener('click', function(e) {
            if (e.target === searchModal) {
                closeSearchModalFunc();
            }
        });
    }
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && searchModal && !searchModal.classList.contains('hidden')) {
            closeSearchModalFunc();
        }
    });
    
    // Clear recent searches button
    const clearRecentBtn = document.getElementById('clearRecentSearchesBtn');
    if (clearRecentBtn) {
        clearRecentBtn.addEventListener('click', clearRecentSearches);
    }
    
    // Clear search button
    const clearSearch = document.getElementById('clearSearch');
    if (clearSearch) {
        clearSearch.addEventListener('click', function() {
            if (globalSearchInput) globalSearchInput.value = '';
            if (globalSearchInput) globalSearchInput.focus();
        });
    }
    
    // Perform search button in modal
    const performSearchBtn = document.getElementById('performSearch');
    if (performSearchBtn) {
        performSearchBtn.addEventListener('click', function() {
            const searchTerm = globalSearchInput ? globalSearchInput.value : '';
            const category = searchCategory ? searchCategory.value : 'all';
            showFullResultsModal(searchTerm, category);
        });
    }
    
    // Enter key in global search input
    if (globalSearchInput) {
        globalSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const category = searchCategory ? searchCategory.value : 'all';
                showFullResultsModal(this.value, category);
            }
        });
    }
    
    function updateSearchModalTheme() {
        const theme = getCurrentTheme();
        const modal = document.getElementById('searchModal');
        if (!modal) return;
        
        const modalContainer = modal.querySelector('.search-modal-container');
        const modalHeader = modal.querySelector('.modal-header-light');
        const modalFooter = modal.querySelector('.modal-footer-light');
        
        if (theme === 'dark') {
            if (modalContainer) {
                modalContainer.style.backgroundColor = '#1a1e2c';
                modalContainer.style.borderColor = '#2d3748';
            }
            if (modalHeader) {
                modalHeader.style.backgroundColor = '#252b3b';
                modalHeader.style.borderBottomColor = '#2d3748';
            }
            if (modalFooter) {
                modalFooter.style.backgroundColor = '#252b3b';
                modalFooter.style.borderTopColor = '#2d3748';
            }
        } else {
            if (modalContainer) {
                modalContainer.style.backgroundColor = '#ffffff';
                modalContainer.style.borderColor = '#e5e7eb';
            }
            if (modalHeader) {
                modalHeader.style.backgroundColor = '#f9fafb';
                modalHeader.style.borderBottomColor = '#e5e7eb';
            }
            if (modalFooter) {
                modalFooter.style.backgroundColor = '#f9fafb';
                modalFooter.style.borderTopColor = '#e5e7eb';
            }
        }
    }
    
    // ============ NOTIFICATION FUNCTIONS ============
    // SINGLE SOURCE OF TRUTH FOR NOTIFICATION COUNT
    function updateNotificationCount() {
        fetch('/api/notifications/count')
            .then(response => response.json())
            .then(data => {
                const count = data.unread_count || 0;
                updateNotificationBadge(count);
            })
            .catch(error => {
                console.error('Error updating notification count:', error);
            });
    }

    function updateNotificationBadge(count) {
        // Find the notification bell container
        const notificationBell = document.getElementById('notificationBell');
        if (!notificationBell) return;
        
        // Remove any existing badge first (cleanup)
        const existingBadges = notificationBell.querySelectorAll('.notification-badge');
        existingBadges.forEach(badge => badge.remove());
        
        // If count > 0, create new badge
        if (count > 0) {
            const badge = document.createElement('span');
            badge.className = 'notification-badge';
            badge.textContent = count > 9 ? '9+' : count;
            
            // Style the badge
            badge.style.cssText = `
                position: absolute;
                top: -2px;
                right: -2px;
                background-color: #ef4444;
                color: white;
                border-radius: 50%;
                width: 20px;
                height: 20px;
                font-size: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 600;
                border: 2px solid var(--card-bg, #ffffff);
                animation: pulse 2s infinite;
                z-index: 10;
            `;
            
            // Make sure the parent has relative positioning
            notificationBell.style.position = 'relative';
            notificationBell.appendChild(badge);
        }
    }
    // ============================================================
    // Legacy notification JS removed.
    // The Alpine component in resources/views/components/notification-bell.blade.php
    // is the single source of truth for loading / marking notifications.
    // ============================================================
    
    // ============ BILLING MANAGEMENT MODAL ============
    const billingModal = document.getElementById('billingManagementModal');
    const billingBtn = document.getElementById('billingManagementBtn');
    const closeBillingModalBtn = document.getElementById('closeBillingModal');
    const cancelBillingModalBtn = document.getElementById('cancelBillingModal');
    
    if (billingBtn && billingModal) {
        billingBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            billingModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
    }
    
    function closeBillingModalFunc() {
        if (billingModal) {
            billingModal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }
    
    if (closeBillingModalBtn) {
        closeBillingModalBtn.addEventListener('click', closeBillingModalFunc);
    }
    if (cancelBillingModalBtn) {
        cancelBillingModalBtn.addEventListener('click', closeBillingModalFunc);
    }
    
    if (billingModal) {
        billingModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeBillingModalFunc();
            }
        });
    }
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && billingModal && !billingModal.classList.contains('hidden')) {
            closeBillingModalFunc();
        }
    });
    
    window.closeBillingModal = closeBillingModalFunc;

    // ============ EMAIL MANAGEMENT MODAL ============
    const emailModal = document.getElementById('emailManagementModal');
    const emailBtn = document.getElementById('emailManagementBtn');
    const closeEmailModalBtn = document.getElementById('closeEmailModal');
    const cancelEmailModalBtn = document.getElementById('cancelEmailModal');
    
    if (emailBtn && emailModal) {
        emailBtn.addEventListener('click', function(e) {
            e.preventDefault();
            emailModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
    }
    
    function closeEmailModalFunc() {
        if (emailModal) {
            emailModal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }
    
    if (closeEmailModalBtn) {
        closeEmailModalBtn.addEventListener('click', closeEmailModalFunc);
    }
    if (cancelEmailModalBtn) {
        cancelEmailModalBtn.addEventListener('click', closeEmailModalFunc);
    }
    
    if (emailModal) {
        emailModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeEmailModalFunc();
            }
        });
    }
    
    window.closeEmailModal = closeEmailModalFunc;

    // ============ SMS MANAGEMENT MODAL ============
    const smsModal = document.getElementById('smsManagementModal');
    const smsBtn = document.getElementById('smsManagementBtn');
    const closeSmsModalBtn = document.getElementById('closeSmsModal');
    const cancelSmsModalBtn = document.getElementById('cancelSmsModal');
    
    if (smsBtn && smsModal) {
        smsBtn.addEventListener('click', function(e) {
            e.preventDefault();
            smsModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
    }
    
    function closeSmsModalFunc() {
        if (smsModal) {
            smsModal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }
    
    if (closeSmsModalBtn) {
        closeSmsModalBtn.addEventListener('click', closeSmsModalFunc);
    }
    if (cancelSmsModalBtn) {
        cancelSmsModalBtn.addEventListener('click', closeSmsModalFunc);
    }
    
    if (smsModal) {
        smsModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeSmsModalFunc();
            }
        });
    }
    
    window.closeSmsModal = closeSmsModalFunc;

    // ============ SMS MANAGEMENT FUNCTIONS ============
    function refreshSmsStatus() {
        const btn = event?.target?.closest('button');
        if (!btn) return;
        
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Refreshing...';
        btn.disabled = true;
        
        fetch('/api/sms/status/refresh', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('SMS status refreshed successfully', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification(data.message || 'Failed to refresh status', 'error');
            }
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        })
        .catch(error => {
            console.error('Refresh error:', error);
            showNotification('Failed to refresh SMS status', 'error');
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        });
    }

    function openSmsCompose() {
        closeSmsModalFunc();
        window.location.href = '/sms/compose';
    }

    function testSmsConnection() {
        const btn = event?.target?.closest('button');
        if (!btn) return;
        
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Testing...';
        btn.disabled = true;
        
        fetch('/api/sms/test', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message || 'SMS connection test successful!', 'success');
            } else {
                showNotification(data.message || 'SMS connection test failed', 'error');
            }
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        })
        .catch(error => {
            console.error('Test error:', error);
            showNotification('Failed to test SMS connection', 'error');
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        });
    }

    // ============ WHATSAPP MANAGEMENT MODAL ============
    const whatsappModal = document.getElementById('whatsappManagementModal');
    const whatsappBtn = document.getElementById('whatsappManagementBtn');
    const closeWhatsAppModalBtn = document.getElementById('closeWhatsAppModal');
    const cancelWhatsAppModalBtn = document.getElementById('cancelWhatsAppModal');
    
    if (whatsappBtn && whatsappModal) {
        whatsappBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            whatsappModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
    }
    
    function closeWhatsAppModalFunc() {
        if (whatsappModal) {
            whatsappModal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }
    
    if (closeWhatsAppModalBtn) {
        closeWhatsAppModalBtn.addEventListener('click', closeWhatsAppModalFunc);
    }
    if (cancelWhatsAppModalBtn) {
        cancelWhatsAppModalBtn.addEventListener('click', closeWhatsAppModalFunc);
    }
    
    if (whatsappModal) {
        whatsappModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeWhatsAppModalFunc();
            }
        });
    }
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && whatsappModal && !whatsappModal.classList.contains('hidden')) {
            closeWhatsAppModalFunc();
        }
    });
    
    window.closeWhatsAppModal = closeWhatsAppModalFunc;

    // ============ WHATSAPP MANAGEMENT FUNCTIONS ============
    function openWhatsAppCompose() {
        closeWhatsAppModalFunc();
        // Use admin route for admin users
        if (isAdmin || isSuperAdmin) {
            window.location.href = '/admin/whatsapp/messages/compose';
        } else if (isDeveloper) {
            window.location.href = '/developer/whatsapp/messages/compose';
        } else {
            window.location.href = '/whatsapp/messages/compose';
        }
    }

    function testWhatsAppConnection() {
        const btn = event?.target?.closest('button');
        if (!btn) return;
        
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Testing...';
        btn.disabled = true;
        
        fetch('/api/whatsapp/test', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message || 'WhatsApp connection test successful!', 'success');
            } else {
                showNotification(data.message || 'WhatsApp connection test failed', 'error');
            }
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        })
        .catch(error => {
            console.error('Test error:', error);
            showNotification('Failed to test WhatsApp connection', 'error');
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        });
    }

    function refreshWhatsAppStatus() {
        const btn = event?.target?.closest('button');
        if (!btn) return;
        
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Refreshing...';
        btn.disabled = true;
        
        fetch('/api/whatsapp/status/refresh', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('WhatsApp status refreshed successfully', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification(data.message || 'Failed to refresh status', 'error');
            }
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        })
        .catch(error => {
            console.error('Refresh error:', error);
            showNotification('Failed to refresh WhatsApp status', 'error');
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        });
    }

    // ============ EMAIL ACCOUNT FUNCTIONS ============
    function syncAccount(accountId) {
        if (!confirm('Sync this email account to fetch new emails?')) return;
        
        const button = event?.target?.closest('button');
        if (!button) return;
        
        const originalHtml = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.disabled = true;
        
        fetch(`/email-accounts/${accountId}/sync`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message || 'Sync completed successfully!', 'success');
                setTimeout(() => location.reload(), 2000);
            } else {
                showNotification(data.message || 'Sync failed. Please try again.', 'error');
                button.innerHTML = originalHtml;
                button.disabled = false;
            }
        })
        .catch(error => {
            console.error('Sync error:', error);
            showNotification('An error occurred during sync.', 'error');
            button.innerHTML = originalHtml;
            button.disabled = false;
        });
    }

    function syncAllEmails() {
        if (!confirm('Sync all your email accounts to fetch new emails?')) return;
        
        const accounts = document.querySelectorAll('.account-item');
        let synced = 0;
        let total = accounts.length;
        
        if (total === 0) {
            showNotification('No email accounts to sync.', 'info');
            return;
        }
        
        showNotification(`Syncing ${total} account(s)...`, 'info');
        
        accounts.forEach((account, index) => {
            const accountId = account.getAttribute('data-account-id') || 
                             account.querySelector('[onclick*="syncAccount"]')?.getAttribute('onclick')?.match(/\d+/)?.[0];
            
            if (accountId) {
                fetch(`/email-accounts/${accountId}/sync`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    synced++;
                    if (synced === total) {
                        showNotification(`All ${total} account(s) synced successfully!`, 'success');
                        setTimeout(() => location.reload(), 2000);
                    }
                })
                .catch(error => {
                    console.error('Sync error for account:', accountId, error);
                    synced++;
                    if (synced === total) {
                        showNotification(`Synced ${synced - 1}/${total} accounts. Some had errors.`, 'warning');
                        setTimeout(() => location.reload(), 3000);
                    }
                });
            } else {
                synced++;
            }
        });
    }

    function setPrimaryAccount(accountId) {
        if (!confirm('Set this as your primary email account?')) return;
        
        fetch(`/email-accounts/${accountId}/set-primary`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Primary account set successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotification(data.message || 'Failed to set primary account.', 'error');
            }
        })
        .catch(error => {
            console.error('Set primary error:', error);
            showNotification('An error occurred. Please try again.', 'error');
        });
    }

    function deleteAccount(accountId, email) {
        if (!confirm(`Are you sure you want to unlink the email account "${email}"?`)) return;
        if (!confirm(`This will permanently remove access to "${email}". Continue?`)) return;
        
        fetch(`/email-accounts/${accountId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Email account unlinked successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotification(data.message || 'Failed to unlink email account.', 'error');
            }
        })
        .catch(error => {
            console.error('Delete error:', error);
            showNotification('An error occurred. Please try again.', 'error');
        });
    }

    // ============ INVOICE MANAGEMENT MODAL ============
    const invoiceModal = document.getElementById('invoiceManagementModal');
    const invoiceBtn = document.getElementById('invoiceManagementBtn');
    const closeInvoiceModalBtn = document.getElementById('closeInvoiceModal');
    const cancelInvoiceModalBtn = document.getElementById('cancelInvoiceModal');
    
    if (invoiceBtn && invoiceModal) {
        invoiceBtn.addEventListener('click', function(e) {
            e.preventDefault();
            invoiceModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
    }
    
    function closeInvoiceModalFunc() {
        if (invoiceModal) {
            invoiceModal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }
    
    if (closeInvoiceModalBtn) {
        closeInvoiceModalBtn.addEventListener('click', closeInvoiceModalFunc);
    }
    if (cancelInvoiceModalBtn) {
        cancelInvoiceModalBtn.addEventListener('click', closeInvoiceModalFunc);
    }
    
    if (invoiceModal) {
        invoiceModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeInvoiceModalFunc();
            }
        });
    }
    
    window.closeInvoiceModal = closeInvoiceModalFunc;

    // ============ ADMINISTRATIVE TOOLS MODAL ============
    const adminToolsModal = document.getElementById('administrativeToolsModal');
    const adminToolsBtn = document.getElementById('administrativeToolsBtn');
    const closeAdminToolsModalBtn = document.getElementById('closeAdminToolsModal');
    const cancelAdminToolsModalBtn = document.getElementById('cancelAdminToolsModal');
    
    if (adminToolsBtn && adminToolsModal) {
        adminToolsBtn.addEventListener('click', function(e) {
            e.preventDefault();
            adminToolsModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
    }
    
    function closeAdminToolsModalFunc() {
        if (adminToolsModal) {
            adminToolsModal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }
    
    if (closeAdminToolsModalBtn) {
        closeAdminToolsModalBtn.addEventListener('click', closeAdminToolsModalFunc);
    }
    if (cancelAdminToolsModalBtn) {
        cancelAdminToolsModalBtn.addEventListener('click', closeAdminToolsModalFunc);
    }
    
    if (adminToolsModal) {
        adminToolsModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeAdminToolsModalFunc();
            }
        });
    }
    
    window.closeAdminToolsModal = closeAdminToolsModalFunc;

    // ============ THEME SETTINGS MODAL WITH CONTROLLER INTEGRATION ============
    const themeModal = document.getElementById('themeSettingsModal');
    const themeBtn = document.getElementById('themeSettingsButton');
    const closeThemeModalBtn = document.getElementById('closeThemeModal');
    const closeThemeModalBtn2 = document.getElementById('closeThemeModalBtn');
    
    // Load theme settings from server on page load
    function loadThemeSettings() {
        ThemeAPI.getSettings()
            .then(data => {
                if (data.success) {
                    applyThemeSettings(data.settings);
                    // Update UI to reflect current selections
                    updateThemeUI(data.settings);
                }
            })
            .catch(error => {
                console.error('Failed to load theme settings:', error);
                // Fallback to localStorage
                const savedTheme = localStorage.getItem('theme');
                if (savedTheme) {
                    document.documentElement.setAttribute('data-theme', savedTheme);
                }
            });
    }

    function applyThemeSettings(settings) {
        if (settings.appearance) {
            document.documentElement.setAttribute('data-theme', settings.appearance);
            localStorage.setItem('theme', settings.appearance);
        }
        if (settings.sidebar_theme) {
            document.documentElement.setAttribute('data-sidebar-theme', settings.sidebar_theme);
            localStorage.setItem('sidebar_theme', settings.sidebar_theme);
        }
        if (settings.font_size) {
            document.documentElement.style.setProperty('--font-size', settings.font_size);
            localStorage.setItem('font_size', settings.font_size);
        }
        if (settings.layout) {
            document.documentElement.setAttribute('data-layout', settings.layout);
            localStorage.setItem('layout', settings.layout);
        }
        if (settings.animations) {
            document.documentElement.setAttribute('data-animations', settings.animations);
            localStorage.setItem('animations', settings.animations);
        }
        
        // Dispatch theme changed event
        document.dispatchEvent(new CustomEvent('theme-changed', { 
            detail: { settings: settings } 
        }));
    }

    function updateThemeUI(settings) {
        // Update appearance options
        document.querySelectorAll('.appearance-option-compact').forEach(el => {
            if (el.dataset.theme === settings.appearance) {
                el.classList.add('active');
            } else {
                el.classList.remove('active');
            }
        });
        
        // Update sidebar theme options
        document.querySelectorAll('.theme-option-compact').forEach(el => {
            if (el.dataset.theme === settings.sidebar_theme) {
                el.classList.add('active');
            } else {
                el.classList.remove('active');
            }
        });
    }

    if (themeBtn && themeModal) {
        themeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            // Load fresh settings before showing modal
            ThemeAPI.getSettings()
                .then(data => {
                    if (data.success) {
                        updateThemeUI(data.settings);
                    }
                })
                .catch(error => console.error('Failed to load theme settings:', error));
            
            themeModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
    }
    
    function closeThemeModalFunc() {
        if (themeModal) {
            themeModal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }
    
    if (closeThemeModalBtn) {
        closeThemeModalBtn.addEventListener('click', closeThemeModalFunc);
    }
    if (closeThemeModalBtn2) {
        closeThemeModalBtn2.addEventListener('click', closeThemeModalFunc);
    }
    
    if (themeModal) {
        themeModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeThemeModalFunc();
            }
        });
    }
    
    // Theme options with controller integration
    document.querySelectorAll('.appearance-option-compact, .theme-option-compact').forEach(option => {
        option.addEventListener('click', function() {
            const type = this.classList.contains('appearance-option-compact') ? 'appearance' : 'sidebar_theme';
            const value = this.dataset.theme;
            
            if (!value) return;
            
            // Update UI immediately for responsiveness
            if (type === 'appearance') {
                document.documentElement.setAttribute('data-theme', value);
                localStorage.setItem('theme', value);
            } else {
                document.documentElement.setAttribute('data-sidebar-theme', value);
                localStorage.setItem('sidebar_theme', value);
            }
            
            // Update active states
            document.querySelectorAll(`.${type === 'appearance' ? 'appearance-option-compact' : 'theme-option-compact'}`).forEach(el => {
                el.classList.toggle('active', el.dataset.theme === value);
            });
            
            // Dispatch theme changed event
            document.dispatchEvent(new CustomEvent('theme-changed', { 
                detail: { [type]: value } 
            }));
            
            // Save to server
            const settings = {};
            if (type === 'appearance') {
                settings.appearance = value;
            } else {
                settings.sidebar_theme = value;
            }
            
            ThemeAPI.updateSettings(settings)
                .then(data => {
                    if (data.success) {
                        showNotification(`Theme updated successfully`, 'success');
                    } else {
                        showNotification('Failed to save theme settings', 'error');
                        // Revert to previous state
                        loadThemeSettings();
                    }
                })
                .catch(error => {
                    console.error('Failed to update theme:', error);
                    showNotification('Error saving theme settings', 'error');
                    loadThemeSettings();
                });
        });
    });
    
    // Load saved theme from server on page load
    loadThemeSettings();
    
    window.closeThemeModal = closeThemeModalFunc;

    // ============ SCROLL TO TOP ============
    const scrollToTopBtn = document.getElementById('scrollToTop');
    
    if (scrollToTopBtn) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 300) {
                scrollToTopBtn.classList.remove('opacity-0', 'pointer-events-none');
                scrollToTopBtn.classList.add('opacity-100', 'pointer-events-auto');
            } else {
                scrollToTopBtn.classList.add('opacity-0', 'pointer-events-none');
                scrollToTopBtn.classList.remove('opacity-100', 'pointer-events-auto');
            }
        });
        
        scrollToTopBtn.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // ============ MAKE FUNCTIONS GLOBALLY ACCESSIBLE ============
    window.closeBillingModal = closeBillingModalFunc;
    window.closeEmailModal = closeEmailModalFunc;
    window.closeSmsModal = closeSmsModalFunc;
    window.closeWhatsAppModal = closeWhatsAppModalFunc;
    window.closeInvoiceModal = closeInvoiceModalFunc;
    window.closeAdminToolsModal = closeAdminToolsModalFunc;
    window.closeThemeModal = closeThemeModalFunc;
    window.closeSearchModal = closeSearchModalFunc;
    window.showNotification = showNotification;
    window.syncAccount = syncAccount;
    window.syncAllEmails = syncAllEmails;
    window.setPrimaryAccount = setPrimaryAccount;
    window.deleteAccount = deleteAccount;
    window.refreshSmsStatus = refreshSmsStatus;
    window.openSmsCompose = openSmsCompose;
    window.testSmsConnection = testSmsConnection;
    window.openWhatsAppCompose = openWhatsAppCompose;
    window.testWhatsAppConnection = testWhatsAppConnection;
    window.refreshWhatsAppStatus = refreshWhatsAppStatus;
    // window.loadNotifications removed � Alpine bell handles it
    // window.markNotificationRead removed
    // window.markAllAsRead removed
    window.performFullSearch = performFullSearch;
    window.showFullResultsModal = showFullResultsModal;
    window.updateNotificationCount = updateNotificationCount;
    window.ThemeAPI = ThemeAPI;
    
    // ============ INITIAL LOAD ============
    updateNotificationCount();
    // loadNotifications() removed � Alpine bell loads on demand
    // Listen for theme changes
    document.addEventListener('theme-changed', function() {
        const currentTheme = getCurrentTheme();
        
        const invoiceModal = document.getElementById('invoiceManagementModal');
        if (invoiceModal && !invoiceModal.classList.contains('hidden')) {
            const modal = invoiceModal.querySelector('.invoice-modal');
            if (modal) {
                if (currentTheme === 'dark') {
                    modal.style.backgroundColor = '#1a1e2c';
                    modal.style.borderColor = '#2d3748';
                } else {
                    modal.style.backgroundColor = '#ffffff';
                    modal.style.borderColor = '#e5e7eb';
                }
            }
        }
        
        if (quickSearchInput) {
            if (currentTheme === 'dark') {
                quickSearchInput.style.backgroundColor = '#2d3748';
                quickSearchInput.style.borderColor = '#4a5568';
                quickSearchInput.style.color = '#e2e8f0';
            } else {
                quickSearchInput.style.backgroundColor = '#f7fafc';
                quickSearchInput.style.borderColor = '#e2e8f0';
                quickSearchInput.style.color = '#2d3748';
            }
        }
        
        updateSearchModalTheme();
    });
    
    const initialTheme = getCurrentTheme();
    console.log('Initial theme detected:', initialTheme);
    
    if (window.matchMedia) {
        const darkModeMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        darkModeMediaQuery.addEventListener('change', (e) => {
            if (!document.documentElement.getAttribute('data-theme')) {
                document.dispatchEvent(new CustomEvent('theme-changed'));
            }
        });
    }
});
</script>