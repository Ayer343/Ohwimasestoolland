<script>
(function() {
    // =========================================================================
    // SIDEBAR COLLAPSE STATE MANAGEMENT
    // =========================================================================
    
    const initialSidebarState = window.__INITIAL_SIDEBAR_STATE !== undefined 
        ? window.__INITIAL_SIDEBAR_STATE 
        : localStorage.getItem('sidebarCollapsed') === 'true';
    
    if (localStorage.getItem('sidebarCollapsed') === null) {
        localStorage.setItem('sidebarCollapsed', initialSidebarState);
    }
    
    function toggleSidebar(collapsed, saveToStorage = true) {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.content');
        const mainContentAlt = document.getElementById('main-content');
        
        if (sidebar) {
            sidebar.classList.toggle('collapsed', collapsed);
        }
        
        if (mainContent) {
            mainContent.classList.toggle('collapsed', collapsed);
        }
        
        if (mainContentAlt && !mainContent) {
            mainContentAlt.classList.toggle('collapsed', collapsed);
        }
        
        if (saveToStorage) {
            localStorage.setItem('sidebarCollapsed', collapsed);
        }
        
        // Update Alpine.js state if it exists
        if (window.Alpine) {
            document.querySelectorAll('[x-data]').forEach(el => {
                if (el.__x && el.__x.$data.sidebarCollapsed !== undefined) {
                    el.__x.$data.sidebarCollapsed = collapsed;
                }
            });
        }
        
        return collapsed;
    }
    
    if (document.querySelector('.sidebar')) {
        toggleSidebar(initialSidebarState, false);
    }
    
    // =========================================================================
    // DOM READY - MAIN INITIALIZATION
    // =========================================================================
    
    document.addEventListener('DOMContentLoaded', function() {
        // ---------------------------------------------------------------------
        // SIDEBAR TOGGLE BUTTON
        // ---------------------------------------------------------------------
        
        const toggleSidebarBtn = document.getElementById('toggleSidebar');
        
        if (localStorage.getItem('sidebarCollapsed') !== null && toggleSidebarBtn) {
            toggleSidebarBtn.classList.add('interacted');
        }
        
        if (toggleSidebarBtn) {
            toggleSidebarBtn.addEventListener('click', function(e) {
                e.preventDefault();
                this.classList.add('interacted');
                const isCollapsed = localStorage.getItem('sidebarCollapsed') !== 'true';
                toggleSidebar(isCollapsed, true);
                
                if (window.navigator && window.navigator.vibrate) {
                    window.navigator.vibrate(50);
                }
            });
            
            toggleSidebarBtn.addEventListener('mouseenter', function() {
                this.classList.add('interacted');
            });
        }
        
        // ---------------------------------------------------------------------
        // THEME SETTINGS BUTTON - NOW TRIGGERS ALPINE MODAL
        // ---------------------------------------------------------------------
        
        const themeSettingsButton = document.getElementById('themeSettingsButton');
        const settingsIcon = document.getElementById('settingsIcon');
        
        if (settingsIcon) {
            settingsIcon.classList.add('theme-settings-gear');
            
            if (themeSettingsButton) {
                themeSettingsButton.addEventListener('mouseenter', function() {
                    settingsIcon.classList.add('gear-spin-fast');
                });
                
                themeSettingsButton.addEventListener('mouseleave', function() {
                    settingsIcon.classList.remove('gear-spin-fast');
                });
                
                // This will trigger the Alpine modal if it exists
                themeSettingsButton.addEventListener('click', function(e) {
                    // Check if Alpine modal handler exists
                    const alpineModal = document.querySelector('[x-data="themeModalHandler()"]');
                    if (alpineModal && alpineModal.__x) {
                        // Alpine will handle it via @click
                        return;
                    }
                    
                    // Fallback: open theme settings modal directly
                    const modal = document.getElementById('themeSettingsModal');
                    if (modal) {
                        modal.classList.remove('hidden');
                        document.body.style.overflow = 'hidden';
                    }
                });
            }
        }
        
        // ---------------------------------------------------------------------
        // THEME SETTINGS MODAL - FALLBACK HANDLING
        // ---------------------------------------------------------------------
        
        const themeSettingsModal = document.getElementById('themeSettingsModal');
        const closeThemeModal = document.getElementById('closeThemeModal');
        const closeThemeModalBtn = document.getElementById('closeThemeModalBtn');
        
        function closeThemeModalFunc() {
            if (themeSettingsModal) {
                themeSettingsModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        }
        
        if (closeThemeModal) closeThemeModal.addEventListener('click', closeThemeModalFunc);
        if (closeThemeModalBtn) closeThemeModalBtn.addEventListener('click', closeThemeModalFunc);
        
        if (themeSettingsModal) {
            themeSettingsModal.addEventListener('click', function(e) {
                if (e.target === themeSettingsModal) {
                    closeThemeModalFunc();
                }
            });
        }
        
        // Escape key handler
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && themeSettingsModal && !themeSettingsModal.classList.contains('hidden')) {
                closeThemeModalFunc();
            }
        });
        
        // ---------------------------------------------------------------------
        // MOBILE MENU
        // ---------------------------------------------------------------------
        
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const overlay = document.getElementById('overlay');
        const sidebar = document.querySelector('.sidebar');
        
        if (mobileMenuBtn && overlay && sidebar) {
            mobileMenuBtn.addEventListener('click', function() {
                sidebar.classList.add('active');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
            
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            });
        }
        
        // ---------------------------------------------------------------------
        // USER DROPDOWN
        // ---------------------------------------------------------------------
        
        const userDropdown = document.getElementById('user-dropdown');
        const dropdownMenu = document.getElementById('dropdown-menu');
        
        if (userDropdown && dropdownMenu) {
            userDropdown.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdownMenu.classList.toggle('show');
            });
            
            document.addEventListener('click', function(e) {
                if (dropdownMenu.classList.contains('show') && !userDropdown.contains(e.target)) {
                    dropdownMenu.classList.remove('show');
                }
            });
        }
        
        // ---------------------------------------------------------------------
        // TABS FUNCTIONALITY
        // ---------------------------------------------------------------------
        
        const tabs = document.querySelectorAll('[data-tab-target]');
        const tabPanes = document.querySelectorAll('.tab-pane');
        
        if (tabs.length > 0 && tabPanes.length > 0) {
            function switchTab(tab) {
                const target = tab.getAttribute('data-tab-target');
                
                tabs.forEach(t => {
                    t.classList.remove('active', 'border-primary');
                    t.setAttribute('aria-selected', 'false');
                });
                tab.classList.add('active', 'border-primary');
                tab.setAttribute('aria-selected', 'true');
                
                tabPanes.forEach(pane => {
                    pane.classList.add('hidden');
                    pane.classList.remove('active');
                    if (pane.id === target) {
                        pane.classList.remove('hidden');
                        pane.classList.add('active');
                    }
                });
            }
            
            tabs.forEach(tab => {
                tab.addEventListener('click', (e) => {
                    e.preventDefault();
                    switchTab(tab);
                });
            });

            function checkHash() {
                const hash = window.location.hash.substring(1);
                if (hash) {
                    const tab = document.querySelector(`[data-tab-target="${hash}"]`);
                    if (tab) {
                        switchTab(tab);
                    }
                }
            }
            
            checkHash();
            
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const target = tab.getAttribute('data-tab-target');
                    if (target) {
                        window.location.hash = target;
                    }
                });
            });
        }
        
        // ---------------------------------------------------------------------
        // FORM ELEMENTS FOCUS STYLES
        // ---------------------------------------------------------------------
        
        document.querySelectorAll('input, select, textarea').forEach(element => {
            element.addEventListener('focus', function() {
                this.style.boxShadow = '0 0 0 2px var(--primary)';
                this.style.borderColor = 'var(--primary)';
            });
            
            element.addEventListener('blur', function() {
                this.style.boxShadow = '';
                this.style.borderColor = 'var(--border-color)';
            });
        });
        
        // ---------------------------------------------------------------------
        // SUCCESS MESSAGE AUTO-HIDE
        // ---------------------------------------------------------------------
        
        const successMessage = document.querySelector('.bg-green-100');
        if (successMessage) {
            setTimeout(() => {
                successMessage.style.transition = 'opacity 0.5s ease';
                successMessage.style.opacity = '0';
                setTimeout(() => {
                    successMessage.style.display = 'none';
                }, 500);
            }, 5000);
        }
        
        // ---------------------------------------------------------------------
        // CARD ANIMATIONS
        // ---------------------------------------------------------------------
        
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fadeInUp');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);
        
        document.querySelectorAll('.card').forEach(card => {
            observer.observe(card);
        });
        
        // ---------------------------------------------------------------------
        // SIDEBAR INTERFERENCE FIX
        // ---------------------------------------------------------------------
        
        function fixInterference() {
            const currentState = localStorage.getItem('sidebarCollapsed') === 'true';
            const sidebar = document.querySelector('.sidebar');
            
            if (sidebar) {
                const hasCollapsedClass = sidebar.classList.contains('collapsed');
                if (hasCollapsedClass !== currentState) {
                    toggleSidebar(currentState, false);
                }
            }
        }
        
        setTimeout(fixInterference, 1000);
        
        const originalFetch = window.fetch;
        if (originalFetch) {
            window.fetch = function() {
                return originalFetch.apply(this, arguments)
                    .then(response => {
                        setTimeout(fixInterference, 100);
                        return response;
                    })
                    .catch(error => {
                        throw error;
                    });
            };
        }
        
        // ---------------------------------------------------------------------
        // PULSE ANIMATION REMOVAL
        // ---------------------------------------------------------------------
        
        let scrollTimeout;
        window.addEventListener('scroll', function() {
            if (toggleSidebarBtn && !toggleSidebarBtn.classList.contains('interacted')) {
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(function() {
                    toggleSidebarBtn.classList.add('interacted');
                }, 500);
            }
        }, { passive: true });
        
        const sidebarElement = document.querySelector('.sidebar');
        if (sidebarElement && toggleSidebarBtn) {
            sidebarElement.addEventListener('mouseenter', function() {
                if (!toggleSidebarBtn.classList.contains('interacted')) {
                    toggleSidebarBtn.classList.add('interacted');
                }
            });
        }
        
        setTimeout(function() {
            if (toggleSidebarBtn && !toggleSidebarBtn.classList.contains('interacted')) {
                toggleSidebarBtn.classList.add('interacted');
            }
        }, 10000);
        
        // ---------------------------------------------------------------------
        // MODAL MANAGEMENT
        // ---------------------------------------------------------------------
        
        // Email Modal
        const emailManagementBtn = document.getElementById('emailManagementBtn');
        const emailManagementModal = document.getElementById('emailManagementModal');
        if (emailManagementBtn && emailManagementModal) {
            emailManagementBtn.addEventListener('click', function() {
                emailManagementModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            });
            
            document.getElementById('closeEmailModal')?.addEventListener('click', function() {
                emailManagementModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            });
            
            document.getElementById('cancelEmailModal')?.addEventListener('click', function() {
                emailManagementModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            });
            
            emailManagementModal.addEventListener('click', function(e) {
                if (e.target === emailManagementModal) {
                    emailManagementModal.classList.add('hidden');
                    document.body.style.overflow = 'auto';
                }
            });
        }
        
        // SMS Modal
        const smsManagementBtn = document.getElementById('smsManagementBtn');
        const smsManagementModal = document.getElementById('smsManagementModal');
        if (smsManagementBtn && smsManagementModal) {
            smsManagementBtn.addEventListener('click', function() {
                smsManagementModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            });
            
            document.getElementById('closeSmsModal')?.addEventListener('click', function() {
                smsManagementModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            });
            
            document.getElementById('cancelSmsModal')?.addEventListener('click', function() {
                smsManagementModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            });
            
            smsManagementModal.addEventListener('click', function(e) {
                if (e.target === smsManagementModal) {
                    smsManagementModal.classList.add('hidden');
                    document.body.style.overflow = 'auto';
                }
            });
        }
        
        // WhatsApp Modal
        const whatsappManagementBtn = document.getElementById('whatsappManagementBtn');
        const whatsappManagementModal = document.getElementById('whatsappManagementModal');
        if (whatsappManagementBtn && whatsappManagementModal) {
            whatsappManagementBtn.addEventListener('click', function() {
                whatsappManagementModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            });
            
            document.getElementById('closeWhatsAppModal')?.addEventListener('click', function() {
                whatsappManagementModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            });
            
            document.getElementById('cancelWhatsAppModal')?.addEventListener('click', function() {
                whatsappManagementModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            });
            
            whatsappManagementModal.addEventListener('click', function(e) {
                if (e.target === whatsappManagementModal) {
                    whatsappManagementModal.classList.add('hidden');
                    document.body.style.overflow = 'auto';
                }
            });
        }
        
        // Invoice Modal
        const invoiceManagementBtn = document.getElementById('invoiceManagementBtn');
        const invoiceManagementModal = document.getElementById('invoiceManagementModal');
        if (invoiceManagementBtn && invoiceManagementModal) {
            invoiceManagementBtn.addEventListener('click', function() {
                invoiceManagementModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            });
            
            document.getElementById('closeInvoiceModal')?.addEventListener('click', function() {
                invoiceManagementModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            });
            
            document.getElementById('cancelInvoiceModal')?.addEventListener('click', function() {
                invoiceManagementModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            });
            
            invoiceManagementModal.addEventListener('click', function(e) {
                if (e.target === invoiceManagementModal) {
                    invoiceManagementModal.classList.add('hidden');
                    document.body.style.overflow = 'auto';
                }
            });
        }
        
        // Administrative Tools Modal
        const administrativeToolsBtn = document.getElementById('administrativeToolsBtn');
        const administrativeToolsModal = document.getElementById('administrativeToolsModal');
        if (administrativeToolsBtn && administrativeToolsModal) {
            administrativeToolsBtn.addEventListener('click', function() {
                administrativeToolsModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            });
            
            document.getElementById('closeAdminToolsModal')?.addEventListener('click', function() {
                administrativeToolsModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            });
            
            document.getElementById('cancelAdminToolsModal')?.addEventListener('click', function() {
                administrativeToolsModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            });
            
            administrativeToolsModal.addEventListener('click', function(e) {
                if (e.target === administrativeToolsModal) {
                    administrativeToolsModal.classList.add('hidden');
                    document.body.style.overflow = 'auto';
                }
            });
        }
        
        // Billing Modal
        const billingManagementBtn = document.getElementById('billingManagementBtn');
        const billingManagementModal = document.getElementById('billingManagementModal');
        if (billingManagementBtn && billingManagementModal) {
            billingManagementBtn.addEventListener('click', function() {
                billingManagementModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            });
            
            document.getElementById('closeBillingModal')?.addEventListener('click', function() {
                billingManagementModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            });
            
            document.getElementById('cancelBillingModal')?.addEventListener('click', function() {
                billingManagementModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            });
            
            billingManagementModal.addEventListener('click', function(e) {
                if (e.target === billingManagementModal) {
                    billingManagementModal.classList.add('hidden');
                    document.body.style.overflow = 'auto';
                }
            });
        }
        
        // ---------------------------------------------------------------------
        // NOTIFICATIONS
        // ---------------------------------------------------------------------
        
        window.loadNotifications = function() {
            const list = document.getElementById('notificationList');
            if (!list) return;
            
            list.innerHTML = '<div class="p-4 text-center" style="color: var(--text-secondary);"><i class="fas fa-spinner fa-spin mr-2"></i>Loading notifications...</div>';
            
            fetch('/notifications/ajax', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.html) {
                    list.innerHTML = data.html;
                } else {
                    list.innerHTML = '<div class="p-4 text-center" style="color: var(--text-secondary);">No notifications found</div>';
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                list.innerHTML = '<div class="p-4 text-center" style="color: var(--text-secondary);">Failed to load notifications</div>';
            });
        };
        
        window.markAllAsRead = function() {
            fetch('/notifications/mark-all-read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('All notifications marked as read', 'success');
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                    const badge = document.querySelector('.notification-dot');
                    if (badge) badge.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error marking notifications as read:', error);
                showNotification('Failed to mark notifications as read', 'error');
            });
        };
        
        // ---------------------------------------------------------------------
        // SEARCH FUNCTIONALITY
        // ---------------------------------------------------------------------
        
        window.openSearchModal = function() {
            const modal = document.getElementById('searchModal');
            if (modal) {
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                const input = document.getElementById('globalSearchInput');
                if (input) {
                    setTimeout(() => input.focus(), 100);
                }
            }
        };
        
        window.closeSearchModal = function() {
            const modal = document.getElementById('searchModal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        };
        
        window.performSearch = function() {
            const input = document.getElementById('globalSearchInput');
            const category = document.getElementById('searchCategory');
            
            if (!input || !input.value.trim()) {
                showNotification('Please enter a search term', 'warning');
                return;
            }
            
            const term = encodeURIComponent(input.value.trim());
            const categoryValue = category ? category.value : 'all';
            
            let url = `/search?q=${term}`;
            if (categoryValue !== 'all') {
                url += `&category=${categoryValue}`;
            }
            
            saveRecentSearch(input.value.trim());
            window.location.href = url;
        };
        
        function saveRecentSearch(term) {
            let recentSearches = JSON.parse(localStorage.getItem('recentSearches') || '[]');
            recentSearches = recentSearches.filter(s => s !== term);
            recentSearches.unshift(term);
            if (recentSearches.length > 10) {
                recentSearches = recentSearches.slice(0, 10);
            }
            localStorage.setItem('recentSearches', JSON.stringify(recentSearches));
            renderRecentSearches();
        }
        
        function renderRecentSearches() {
            const container = document.getElementById('recentSearchesList');
            if (!container) return;
            
            const searches = JSON.parse(localStorage.getItem('recentSearches') || '[]');
            if (searches.length === 0) {
                container.innerHTML = '<span class="text-xs" style="color: var(--text-secondary);">No recent searches</span>';
                return;
            }
            
            container.innerHTML = searches.map(term => `
                <span class="recent-search-item cursor-pointer px-3 py-1 rounded-full text-xs transition-all duration-200 hover:bg-opacity-20"
                      style="background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-secondary);"
                      onclick="document.getElementById('globalSearchInput').value='${term}'; performSearch();">
                    <i class="fas fa-history mr-1 text-xs"></i>
                    ${term}
                </span>
            `).join('');
        }
        
        renderRecentSearches();
        
        const searchInput = document.getElementById('globalSearchInput');
        if (searchInput) {
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    performSearch();
                }
            });
        }
        
        document.getElementById('performSearch')?.addEventListener('click', performSearch);
        document.getElementById('cancelSearch')?.addEventListener('click', closeSearchModal);
        
        document.getElementById('clearSearch')?.addEventListener('click', function() {
            const input = document.getElementById('globalSearchInput');
            if (input) {
                input.value = '';
                input.focus();
            }
        });
        
        document.getElementById('clearRecentSearchesBtn')?.addEventListener('click', function() {
            if (confirm('Clear all recent searches?')) {
                localStorage.removeItem('recentSearches');
                renderRecentSearches();
                showNotification('Recent searches cleared', 'info');
            }
        });
        
        document.getElementById('advancedSearchBtn')?.addEventListener('click', openSearchModal);
        
        const quickSearch = document.getElementById('quickSearchInput');
        if (quickSearch) {
            quickSearch.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const term = encodeURIComponent(this.value.trim());
                    if (term) {
                        window.location.href = `/search?q=${term}`;
                    }
                }
            });
            quickSearch.addEventListener('focus', function() {
                this.select();
            });
        }
        
        document.getElementById('mobileSearchBtn')?.addEventListener('click', openSearchModal);
        
        const searchModal = document.getElementById('searchModal');
        if (searchModal) {
            searchModal.addEventListener('click', function(e) {
                if (e.target === searchModal) {
                    closeSearchModal();
                }
            });
        }
        
        // ---------------------------------------------------------------------
        // EMAIL MANAGEMENT FUNCTIONS
        // ---------------------------------------------------------------------
        
        window.syncAllEmails = function() {
            const btn = document.querySelector('.quick-action-btn .fa-sync')?.closest('.quick-action-btn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...';
            }
            
            fetch('/email-accounts/sync-all', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('All emails synced successfully!', 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showNotification(data.message || 'Failed to sync emails', 'error');
                }
            })
            .catch(error => {
                console.error('Error syncing emails:', error);
                showNotification('An error occurred while syncing emails', 'error');
            })
            .finally(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-sync text-white text-xs"></i>';
                }
            });
        };
        
        window.syncAccount = function(accountId) {
            const btn = event?.target?.closest('button');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            }
            
            fetch(`/email-accounts/${accountId}/sync`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Account synced successfully!', 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showNotification(data.message || 'Failed to sync account', 'error');
                }
            })
            .catch(error => {
                console.error('Error syncing account:', error);
                showNotification('An error occurred while syncing account', 'error');
            })
            .finally(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-sync text-xs"></i>';
                }
            });
        };
        
        window.setPrimaryAccount = function(accountId) {
            if (!confirm('Set this as your primary email account?')) return;
            
            fetch(`/email-accounts/${accountId}/set-primary`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Primary account set successfully!', 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showNotification(data.message || 'Failed to set primary account', 'error');
                }
            })
            .catch(error => {
                console.error('Error setting primary account:', error);
                showNotification('An error occurred while setting primary account', 'error');
            });
        };
        
        window.deleteAccount = function(accountId, email) {
            if (!confirm(`Delete email account "${email}"? This action cannot be undone.`)) return;
            
            fetch(`/email-accounts/${accountId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Account deleted successfully!', 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showNotification(data.message || 'Failed to delete account', 'error');
                }
            })
            .catch(error => {
                console.error('Error deleting account:', error);
                showNotification('An error occurred while deleting account', 'error');
            });
        };
        
        // ---------------------------------------------------------------------
        // SMS MANAGEMENT FUNCTIONS
        // ---------------------------------------------------------------------
        
        window.openSmsCompose = function() {
            window.location.href = '/sms/compose';
        };
        
        window.testSmsConnection = function() {
            const btn = event?.target?.closest('button');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
            }
            
            fetch('/sms/test-connection', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('SMS connection test successful!', 'success');
                } else {
                    showNotification(data.message || 'SMS connection test failed', 'error');
                }
            })
            .catch(error => {
                console.error('Error testing SMS connection:', error);
                showNotification('An error occurred while testing SMS connection', 'error');
            })
            .finally(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-wifi text-white text-xs"></i>';
                }
            });
        };
        
        window.refreshSmsStatus = function() {
            window.location.reload();
        };
        
        // ---------------------------------------------------------------------
        // WHATSAPP MANAGEMENT FUNCTIONS
        // ---------------------------------------------------------------------
        
        window.openWhatsAppCompose = function() {
            window.location.href = '/admin/whatsapp/compose';
        };
        
        window.testWhatsAppConnection = function() {
            const btn = event?.target?.closest('button');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
            }
            
            fetch('/admin/whatsapp/test-connection', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('WhatsApp connection test successful!', 'success');
                } else {
                    showNotification(data.message || 'WhatsApp connection test failed', 'error');
                }
            })
            .catch(error => {
                console.error('Error testing WhatsApp connection:', error);
                showNotification('An error occurred while testing WhatsApp connection', 'error');
            })
            .finally(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-wifi text-white text-xs"></i>';
                }
            });
        };
        
        window.refreshWhatsAppStatus = function() {
            window.location.reload();
        };
        
        // ---------------------------------------------------------------------
        // NOTIFICATION HELPER
        // ---------------------------------------------------------------------
        
        window.showNotification = function(message, type = 'info') {
            const colors = {
                success: '#22c55e',
                error: '#ef4444',
                warning: '#f59e0b',
                info: '#3b82f6'
            };
            
            const div = document.createElement('div');
            div.textContent = message;
            div.style.cssText = `
                position: fixed; bottom: 20px; right: 20px; 
                padding: 12px 24px; border-radius: 8px; 
                background: ${colors[type] || colors.info}; 
                color: white; z-index: 99999;
                font-size: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                animation: slideIn 0.3s ease;
                max-width: 400px;
            `;
            document.body.appendChild(div);
            setTimeout(() => {
                div.style.opacity = '0';
                div.style.transition = 'opacity 0.3s ease';
                setTimeout(() => div.remove(), 300);
            }, 3000);
        };
        
        // ---------------------------------------------------------------------
        // SCROLL TO TOP
        // ---------------------------------------------------------------------
        
        const scrollToTopBtn = document.getElementById('scrollToTop');
        if (scrollToTopBtn) {
            window.addEventListener('scroll', function() {
                if (window.scrollY > 400) {
                    scrollToTopBtn.style.opacity = '1';
                    scrollToTopBtn.style.pointerEvents = 'auto';
                } else {
                    scrollToTopBtn.style.opacity = '0';
                    scrollToTopBtn.style.pointerEvents = 'none';
                }
            });
            
            scrollToTopBtn.addEventListener('click', function() {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }
        
        // ---------------------------------------------------------------------
        // KEYBOARD SHORTCUTS
        // ---------------------------------------------------------------------
        
        document.addEventListener('keydown', function(e) {
            // Ctrl+Shift+T - Open theme settings
            if (e.ctrlKey && e.shiftKey && e.key === 'T') {
                e.preventDefault();
                const btn = document.getElementById('themeSettingsButton');
                if (btn) btn.click();
            }
            
            // / or Ctrl+K - Open search
            if ((e.key === '/' || (e.ctrlKey && e.key === 'k')) && 
                !e.target.closest('input') && !e.target.closest('textarea') && !e.target.closest('select')) {
                e.preventDefault();
                openSearchModal();
            }
            
            // Escape - Close modals
            if (e.key === 'Escape') {
                closeSearchModal();
                closeThemeModalFunc();
            }
        });
        
        // ---------------------------------------------------------------------
        // CLOSE MODAL HELPERS
        // ---------------------------------------------------------------------
        
        window.closeEmailModal = function() {
            const modal = document.getElementById('emailManagementModal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        };
        
        window.closeSmsModal = function() {
            const modal = document.getElementById('smsManagementModal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        };
        
        window.closeWhatsAppModal = function() {
            const modal = document.getElementById('whatsappManagementModal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        };
        
        window.closeInvoiceModal = function() {
            const modal = document.getElementById('invoiceManagementModal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        };
        
        window.closeAdminToolsModal = function() {
            const modal = document.getElementById('administrativeToolsModal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        };
        
        window.closeBillingModal = function() {
            const modal = document.getElementById('billingManagementModal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        };
    });
    
    // =========================================================================
    // WINDOW LOAD - FINAL CHECKS
    // =========================================================================
    
    window.addEventListener('load', function() {
        setTimeout(function() {
            const savedState = localStorage.getItem('sidebarCollapsed') === 'true';
            const sidebar = document.querySelector('.sidebar');
            
            if (sidebar) {
                const hasCollapsedClass = sidebar.classList.contains('collapsed');
                if (hasCollapsedClass !== savedState) {
                    toggleSidebar(savedState, false);
                }
            }
            
            document.documentElement.classList.remove('sidebar-loading');
            document.documentElement.classList.add('sidebar-initialized');
            
            const toggleBtn = document.getElementById('toggleSidebar');
            if (toggleBtn && !toggleBtn.classList.contains('interacted')) {
                if (localStorage.getItem('sidebarCollapsed') !== null) {
                    toggleBtn.classList.add('interacted');
                }
            }
        }, 10);
    });
    
    // =========================================================================
    // ALPINE.JS THEME HANDLER - COMPLETE THEME MANAGEMENT
    // =========================================================================
    
    function initAlpineThemeHandler() {
        if (typeof Alpine === 'undefined') {
            setTimeout(initAlpineThemeHandler, 100);
            return;
        }

        Alpine.data('themeModalHandler', () => ({
            // Theme state
            appearance: '{{ $appearance ?? "system" }}',
            sidebarTheme: '{{ $sidebarTheme ?? "default" }}',
            density: '{{ $currentDensity ?? "comfortable" }}',
            headerStyle: '{{ $currentHeader ?? "default" }}',
            customColors: {{ isset($customColors) ? json_encode($customColors) : '{}' }},
            
            // UI state
            isSaving: false,
            isModalOpen: false,
            
            // Initialize
            init() {
                // Load saved settings from localStorage
                this.loadFromLocalStorage();
                
                // Apply initial theme
                this.applyTheme();
                
                // Listen for system theme changes
                this.systemThemeListener = window.matchMedia('(prefers-color-scheme: dark)');
                this.systemThemeListener.addEventListener('change', () => {
                    if (this.appearance === 'system') {
                        this.applyAppearance();
                    }
                });
                
                // Listen for theme color updates from color picker
                document.addEventListener('theme-colors-updated', (e) => {
                    if (e.detail?.colors) {
                        this.customColors = e.detail.colors;
                        this.sidebarTheme = 'custom';
                        this.saveTheme();
                    }
                });
            },
            
            // Load from localStorage
            loadFromLocalStorage() {
                const savedAppearance = localStorage.getItem('appearance');
                if (savedAppearance) this.appearance = savedAppearance;
                
                const savedSidebarTheme = localStorage.getItem('sidebarTheme');
                if (savedSidebarTheme) this.sidebarTheme = savedSidebarTheme;
                
                const savedDensity = localStorage.getItem('density');
                if (savedDensity) this.density = savedDensity;
                
                const savedHeaderStyle = localStorage.getItem('headerStyle');
                if (savedHeaderStyle) this.headerStyle = savedHeaderStyle;
            },
            
            // Apply theme to page
            applyTheme() {
                this.applyAppearance();
                this.applySidebarTheme();
                this.applyDensity();
                this.applyHeaderStyle();
                this.updateLivePreview();
            },
            
            // Apply appearance
            applyAppearance() {
                const body = document.body;
                const html = document.documentElement;
                
                let theme = this.appearance;
                if (theme === 'system') {
                    theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                
                body.setAttribute('data-theme', theme);
                
                if (theme === 'dark') {
                    html.classList.add('dark');
                    html.classList.remove('light');
                } else {
                    html.classList.add('light');
                    html.classList.remove('dark');
                }
                
                // Update meta theme-color
                const metaThemeColor = document.querySelector('meta[name="theme-color"]');
                if (metaThemeColor) {
                    metaThemeColor.content = theme === 'dark' ? '#1e1e2d' : '#ffffff';
                }
            },
            
            // Apply sidebar theme
            applySidebarTheme() {
                const sidebar = document.getElementById('sidebar');
                if (!sidebar) return;
                
                sidebar.setAttribute('data-sidebar-theme', this.sidebarTheme);
                
                // If custom theme, apply inline styles
                if (this.sidebarTheme === 'custom' && this.customColors && Object.keys(this.customColors).length > 0) {
                    const colors = this.customColors;
                    const bgStart = colors.sidebar_bg_start || '#7267f0';
                    const bgEnd = colors.sidebar_bg_end || '#6258e0';
                    const text = colors.sidebar_text || '#ffffff';
                    const activeBg = colors.sidebar_active_bg || 'rgba(255, 255, 255, 0.2)';
                    const hoverBg = colors.sidebar_hover_bg || 'rgba(255, 255, 255, 0.12)';
                    const border = colors.sidebar_border || 'transparent';
                    const shadow = colors.sidebar_shadow || '0 0 20px rgba(114, 103, 240, 0.3)';
                    
                    sidebar.style.background = `linear-gradient(180deg, ${bgStart} 0%, ${bgEnd} 100%) !important`;
                    sidebar.style.setProperty('--sidebar-text', text);
                    sidebar.style.setProperty('--sidebar-active-bg', activeBg);
                    sidebar.style.setProperty('--sidebar-hover-bg', hoverBg);
                    sidebar.style.setProperty('--sidebar-border', border);
                    sidebar.style.setProperty('--sidebar-shadow', shadow);
                } else {
                    // Remove inline styles for predefined themes
                    sidebar.style.background = '';
                    sidebar.style.removeProperty('--sidebar-text');
                    sidebar.style.removeProperty('--sidebar-active-bg');
                    sidebar.style.removeProperty('--sidebar-hover-bg');
                    sidebar.style.removeProperty('--sidebar-border');
                    sidebar.style.removeProperty('--sidebar-shadow');
                }
            },
            
            // Apply density
            applyDensity() {
                const body = document.body;
                const sidebar = document.getElementById('sidebar');
                
                body.setAttribute('data-density', this.density);
                if (sidebar) {
                    sidebar.setAttribute('data-density', this.density);
                }
                
                // Apply density CSS variables
                const root = document.documentElement;
                const densityStyles = {
                    compact: {
                        '--sidebar-padding': '0.5rem',
                        '--nav-item-spacing': '0.25rem',
                        '--font-size-base': '0.875rem',
                        '--card-padding': '1rem'
                    },
                    comfortable: {
                        '--sidebar-padding': '1rem',
                        '--nav-item-spacing': '0.5rem',
                        '--font-size-base': '1rem',
                        '--card-padding': '1.5rem'
                    },
                    spacious: {
                        '--sidebar-padding': '1.5rem',
                        '--nav-item-spacing': '0.75rem',
                        '--font-size-base': '1.125rem',
                        '--card-padding': '2rem'
                    }
                };
                
                const styles = densityStyles[this.density] || densityStyles.comfortable;
                Object.entries(styles).forEach(([key, value]) => {
                    root.style.setProperty(key, value);
                });
            },
            
            // Apply header style
            applyHeaderStyle() {
                const header = document.querySelector('.header');
                if (!header) return;
                
                header.classList.remove('glass', 'solid');
                if (this.headerStyle !== 'default') {
                    header.classList.add(this.headerStyle);
                }
            },
            
            // Update live preview
            updateLivePreview() {
                const preview = document.querySelector('.theme-modal-modern .w-16.h-16');
                if (!preview) return;
                
                if (this.sidebarTheme === 'custom' && this.customColors) {
                    const bgStart = this.customColors.sidebar_bg_start || '#7267f0';
                    const bgEnd = this.customColors.sidebar_bg_end || '#6258e0';
                    preview.style.background = `linear-gradient(180deg, ${bgStart} 0%, ${bgEnd} 100%)`;
                } else {
                    const themeColors = {
                        'default': 'linear-gradient(135deg, #7267f0, #6258e0)',
                        'dark': 'linear-gradient(135deg, #1a1a2e, #16213e)',
                        'light': 'linear-gradient(135deg, #ffffff, #f0f0f0)',
                        'blue': 'linear-gradient(135deg, #1e3a5f, #1a56db)',
                        'green': 'linear-gradient(135deg, #065f46, #059669)'
                    };
                    preview.style.background = themeColors[this.sidebarTheme] || 'var(--primary)';
                }
            },
            
            // Update appearance
            async updateAppearance(value) {
                this.appearance = value;
                this.applyAppearance();
                await this.saveTheme();
            },
            
            // Update sidebar theme
            async updateSidebarTheme(value) {
                this.sidebarTheme = value;
                this.applySidebarTheme();
                this.updateLivePreview();
                await this.saveTheme();
            },
            
            // Update density
            async updateDensity(value) {
                this.density = value;
                this.applyDensity();
                await this.saveTheme();
            },
            
            // Update header style
            async updateHeaderStyle(value) {
                this.headerStyle = value;
                this.applyHeaderStyle();
                await this.saveTheme();
            },
            
            // Save theme to server
            async saveTheme() {
                if (this.isSaving) return;
                this.isSaving = true;
                
                try {
                    const response = await fetch('/theme/update', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        },
                        body: JSON.stringify({
                            appearance: this.appearance,
                            sidebar_theme: this.sidebarTheme,
                            density: this.density,
                            header_style: this.headerStyle,
                            custom_colors: this.sidebarTheme === 'custom' ? this.customColors : {}
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // Save to localStorage
                        localStorage.setItem('appearance', this.appearance);
                        localStorage.setItem('sidebarTheme', this.sidebarTheme);
                        localStorage.setItem('density', this.density);
                        localStorage.setItem('headerStyle', this.headerStyle);
                        
                        // Update custom CSS if provided
                        if (data.css) {
                            this.updateThemeCss(data.css);
                        }
                        
                        this.showNotification('Theme updated successfully!', 'success');
                        
                        // Dispatch event for other components
                        document.dispatchEvent(new CustomEvent('theme-updated', {
                            detail: {
                                settings: {
                                    appearance: this.appearance,
                                    sidebar_theme: this.sidebarTheme,
                                    density: this.density,
                                    header_style: this.headerStyle,
                                    custom_colors: this.customColors
                                }
                            }
                        }));
                    } else {
                        this.showNotification(data.message || 'Failed to update theme', 'error');
                    }
                } catch (error) {
                    console.error('Error saving theme:', error);
                    this.showNotification('An error occurred while saving theme', 'error');
                } finally {
                    this.isSaving = false;
                }
            },
            
            // Update theme CSS
            updateThemeCss(css) {
                document.querySelectorAll('#custom-theme-css').forEach(el => el.remove());
                
                const style = document.createElement('style');
                style.id = 'custom-theme-css';
                style.textContent = css;
                document.head.appendChild(style);
            },
            
            // Reset theme
            async resetTheme() {
                if (!confirm('Reset all theme settings to default?')) return;
                
                try {
                    const response = await fetch('/theme/reset', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        this.appearance = data.settings?.appearance || 'system';
                        this.sidebarTheme = data.settings?.sidebar_theme || 'default';
                        this.density = data.settings?.density || 'comfortable';
                        this.headerStyle = data.settings?.header_style || 'default';
                        this.customColors = data.colors || {};
                        
                        // Apply reset
                        this.applyTheme();
                        if (data.css) {
                            this.updateThemeCss(data.css);
                        }
                        
                        // Clear localStorage
                        localStorage.removeItem('appearance');
                        localStorage.removeItem('sidebarTheme');
                        localStorage.removeItem('density');
                        localStorage.removeItem('headerStyle');
                        
                        this.showNotification('Theme reset to default', 'success');
                        
                        // Reload after a moment
                        setTimeout(() => window.location.reload(), 500);
                    } else {
                        this.showNotification(data.message || 'Failed to reset theme', 'error');
                    }
                } catch (error) {
                    console.error('Error resetting theme:', error);
                    this.showNotification('An error occurred while resetting theme', 'error');
                }
            },
            
            // Open modal
            openModal() {
                this.isModalOpen = true;
                document.getElementById('themeSettingsModal')?.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            },
            
            // Close modal
            closeModal() {
                this.isModalOpen = false;
                document.getElementById('themeSettingsModal')?.classList.add('hidden');
                document.body.style.overflow = 'auto';
            },
            
            // Show notification
            showNotification(message, type = 'info') {
                if (window.showToast) {
                    window.showToast(message, type);
                    return;
                }
                
                const colors = {
                    success: '#22c55e',
                    error: '#ef4444',
                    info: '#3b82f6'
                };
                
                const div = document.createElement('div');
                div.textContent = message;
                div.style.cssText = `
                    position: fixed; bottom: 20px; right: 20px; 
                    padding: 12px 24px; border-radius: 8px; 
                    background: ${colors[type] || colors.info}; 
                    color: white; z-index: 99999;
                    font-size: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                    animation: slideIn 0.3s ease;
                    max-width: 400px;
                `;
                document.body.appendChild(div);
                setTimeout(() => {
                    div.style.opacity = '0';
                    div.style.transition = 'opacity 0.3s ease';
                    setTimeout(() => div.remove(), 300);
                }, 3000);
            }
        }));
    }

    // Initialize Alpine theme handler
    initAlpineThemeHandler();
    
    // =========================================================================
    // ADDITIONAL CSS FOR THEME MODAL
    // =========================================================================
    
    const themeModalStyles = document.createElement('style');
    themeModalStyles.textContent = `
        /* Theme Modal Additional Styles */
        .theme-settings-gear {
            animation: spin-slow 3s linear infinite;
            transition: all 0.3s ease;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
        }
        
        .theme-settings-gear:hover {
            animation-duration: 1s;
            transform: scale(1.1);
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.4));
        }
        
        .gear-spin-fast {
            animation: spin-fast 1s linear infinite !important;
        }
        
        @keyframes spin-slow {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        @keyframes spin-fast {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        @keyframes slideIn {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .theme-modal-modern {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.35);
            animation: modalSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        @keyframes modalSlideUp {
            from { 
                opacity: 0;
                transform: translateY(30px) scale(0.96);
            }
            to { 
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .appearance-option.active,
        .sidebar-theme-option.active,
        .density-option.active,
        .header-option.active {
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 2px var(--primary), 0 4px 12px rgba(var(--primary-rgb), 0.15);
        }
        
        .appearance-option:hover,
        .sidebar-theme-option:hover,
        .density-option:hover,
        .header-option:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        }
        
        @media (max-width: 640px) {
            .theme-modal-modern {
                max-width: 100%;
                margin: 0.5rem;
                border-radius: 16px;
                max-height: 95vh;
            }
            
            .theme-modal-modern .grid-cols-3 {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.5rem;
            }
            
            .theme-modal-modern .grid-cols-5 {
                grid-template-columns: repeat(5, 1fr);
                gap: 0.25rem;
            }
            
            .theme-modal-modern .grid-cols-2 {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }
        
        @media (prefers-color-scheme: dark) {
            .theme-modal-modern {
                border-color: rgba(255, 255, 255, 0.08);
            }
        }
    `;
    document.head.appendChild(themeModalStyles);
})();
</script>