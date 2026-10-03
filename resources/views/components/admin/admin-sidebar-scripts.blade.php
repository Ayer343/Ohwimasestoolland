@props(['userRole' => null, 'isSuperAdmin' => false, 'isAdmin' => false, 'routeMap' => [], 'searchableCategories' => [], 'searchFields' => []])

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ============ INITIALIZATION ============
    const userRole = {{ $userRole ?? 'null' }};
    const isSuperAdmin = {{ $isSuperAdmin ? 'true' : 'false' }};
    const isAdmin = {{ $isAdmin ? 'true' : 'false' }};
    
    // Get data from props
    const routeMap = {!! json_encode($routeMap ?? []) !!};
    const searchableCategories = {!! json_encode($searchableCategories ?? []) !!};
    const searchFields = {!! json_encode($searchFields ?? []) !!};
    
    // ============ THEME DETECTION FUNCTIONS ============
    function getCurrentTheme() {
        const theme = document.documentElement.getAttribute('data-theme');
        if (theme === 'dark') return 'dark';
        if (theme === 'light') return 'light';
        
        // Check system preference
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        return 'light';
    }
    
    function getCssVariable(name) {
        return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    }
    
    // Apply theme classes to modal
    function applyThemeToModal(modal, theme) {
        if (!modal) return;
        
        const modalContainer = modal.querySelector('.search-modal-container');
        const modalHeader = modal.querySelector('.modal-header-light');
        const modalFooter = modal.querySelector('.modal-footer-light');
        const scrollableBody = modal.querySelector('.modal-scrollable-body');
        const inputs = modal.querySelectorAll('input, select, textarea');
        const labels = modal.querySelectorAll('label');
        const searchFieldOptions = modal.querySelectorAll('.search-field-option');
        const recentSearchTags = modal.querySelectorAll('.recent-search-tag');
        const clearBtn = modal.querySelector('#clearRecentSearchesBtn');
        const toggleBtn = modal.querySelector('#toggleAdvancedFilters');
        const advancedFilters = modal.querySelector('#advancedFilters');
        const scrollIndicators = modal.querySelectorAll('.scroll-indicator i');
        const scrollTopIndicator = modal.querySelector('#scrollTopIndicator');
        const scrollBottomIndicator = modal.querySelector('#scrollBottomIndicator');
        
        // Remove existing theme classes
        const elements = [
            modalContainer, modalHeader, modalFooter, scrollableBody,
            ...inputs, ...labels, ...searchFieldOptions, ...recentSearchTags,
            clearBtn, toggleBtn, advancedFilters, scrollTopIndicator, scrollBottomIndicator
        ].filter(el => el);
        
        elements.forEach(el => {
            el.classList.remove('light-theme', 'dark-theme');
        });
        
        // Add new theme classes
        if (theme === 'dark') {
            // Apply dark theme
            if (modalContainer) {
                modalContainer.classList.add('dark-theme');
                modalContainer.style.backgroundColor = '#1a1e2c';
                modalContainer.style.borderColor = '#2d3748';
            }
            
            if (modalHeader) {
                modalHeader.classList.add('dark-theme');
                modalHeader.style.backgroundColor = '#252b3b';
                modalHeader.style.borderBottomColor = '#2d3748';
                
                const title = modalHeader.querySelector('h3');
                if (title) title.style.color = '#e2e8f0';
                
                const blueText = modalHeader.querySelector('.text-blue-600');
                if (blueText) blueText.style.color = '#60a5fa';
            }
            
            if (modalFooter) {
                modalFooter.classList.add('dark-theme');
                modalFooter.style.backgroundColor = '#252b3b';
                modalFooter.style.borderTopColor = '#2d3748';
            }
            
            inputs.forEach(input => {
                input.classList.add('input-dark-theme');
                input.style.backgroundColor = '#2d3748';
                input.style.borderColor = '#4a5568';
                input.style.color = '#e2e8f0';
            });
            
            labels.forEach(label => {
                label.classList.add('label-dark-theme');
                label.style.color = '#a0aec0';
            });
            
            searchFieldOptions.forEach(option => {
                option.classList.add('dark-theme');
                option.style.backgroundColor = '#2d3748';
                option.style.borderColor = '#4a5568';
                option.style.color = '#e2e8f0';
            });
            
            recentSearchTags.forEach(tag => {
                tag.classList.add('dark-theme');
                tag.style.backgroundColor = '#2d3748';
                tag.style.borderColor = '#4a5568';
                tag.style.color = '#e2e8f0';
            });
            
            if (clearBtn) {
                clearBtn.classList.add('dark-theme');
                clearBtn.style.color = '#f87171';
            }
            
            if (toggleBtn) {
                toggleBtn.classList.add('dark-theme');
                toggleBtn.style.backgroundColor = '#2d3748';
                toggleBtn.style.borderColor = '#4a5568';
                toggleBtn.style.color = '#a0aec0';
            }
            
            if (advancedFilters) {
                advancedFilters.classList.add('dark-theme');
                advancedFilters.style.backgroundColor = '#252b3b';
                advancedFilters.style.borderColor = '#2d3748';
            }
            
            if (scrollTopIndicator) {
                scrollTopIndicator.classList.add('dark-theme');
                scrollTopIndicator.style.background = 'linear-gradient(to bottom, rgba(26, 30, 44, 0.95), rgba(26, 30, 44, 0))';
            }
            
            if (scrollBottomIndicator) {
                scrollBottomIndicator.classList.add('dark-theme');
                scrollBottomIndicator.style.background = 'linear-gradient(to top, rgba(26, 30, 44, 0.95), rgba(26, 30, 44, 0))';
            }
            
            scrollIndicators.forEach(icon => {
                icon.classList.add('dark-theme');
                icon.style.color = '#60a5fa';
                icon.style.background = 'rgba(37, 43, 59, 0.9)';
            });
        } else {
            // Apply light theme
            if (modalContainer) {
                modalContainer.classList.add('light-theme');
                modalContainer.style.backgroundColor = '#ffffff';
                modalContainer.style.borderColor = '#e5e7eb';
            }
            
            if (modalHeader) {
                modalHeader.classList.add('light-theme');
                modalHeader.style.backgroundColor = '#f9fafb';
                modalHeader.style.borderBottomColor = '#e5e7eb';
                
                const title = modalHeader.querySelector('h3');
                if (title) title.style.color = '#1a202c';
                
                const blueText = modalHeader.querySelector('.text-blue-600');
                if (blueText) blueText.style.color = '#2563eb';
            }
            
            if (modalFooter) {
                modalFooter.classList.add('light-theme');
                modalFooter.style.backgroundColor = '#f9fafb';
                modalFooter.style.borderTopColor = '#e5e7eb';
            }
            
            inputs.forEach(input => {
                input.classList.add('input-light-theme');
                input.style.backgroundColor = '#f7fafc';
                input.style.borderColor = '#e2e8f0';
                input.style.color = '#2d3748';
            });
            
            labels.forEach(label => {
                label.classList.add('label-light-theme');
                label.style.color = '#4a5568';
            });
            
            searchFieldOptions.forEach(option => {
                option.classList.add('light-theme');
                option.style.backgroundColor = '#f7fafc';
                option.style.borderColor = '#e2e8f0';
                option.style.color = '#2d3748';
            });
            
            recentSearchTags.forEach(tag => {
                tag.classList.add('light-theme');
                tag.style.backgroundColor = '#f7fafc';
                tag.style.borderColor = '#e2e8f0';
                tag.style.color = '#2d3748';
            });
            
            if (clearBtn) {
                clearBtn.classList.add('light-theme');
                clearBtn.style.color = '#ef4444';
            }
            
            if (toggleBtn) {
                toggleBtn.classList.add('light-theme');
                toggleBtn.style.backgroundColor = '#f7fafc';
                toggleBtn.style.borderColor = '#e2e8f0';
                toggleBtn.style.color = '#4a5568';
            }
            
            if (advancedFilters) {
                advancedFilters.classList.add('light-theme');
                advancedFilters.style.backgroundColor = '#f9fafb';
                advancedFilters.style.borderColor = '#e5e7eb';
            }
            
            if (scrollTopIndicator) {
                scrollTopIndicator.classList.add('light-theme');
                scrollTopIndicator.style.background = 'linear-gradient(to bottom, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0))';
            }
            
            if (scrollBottomIndicator) {
                scrollBottomIndicator.classList.add('light-theme');
                scrollBottomIndicator.style.background = 'linear-gradient(to top, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0))';
            }
            
            scrollIndicators.forEach(icon => {
                icon.classList.add('light-theme');
                icon.style.color = '#3b82f6';
                icon.style.background = 'rgba(255, 255, 255, 0.9)';
            });
        }
    }
    
    // ============ MODAL MANAGEMENT ============
    // Store scrollbar width for body scroll prevention
    function storeScrollbarWidth() {
        const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
        document.documentElement.style.setProperty('--scrollbar-width', scrollbarWidth + 'px');
    }
    
    // Calculate and store scrollbar width
    storeScrollbarWidth();
    window.addEventListener('resize', storeScrollbarWidth);
    
    // ============ BILLING MANAGEMENT MODAL ============
    if (isSuperAdmin) {
        const billingManagementBtn = document.getElementById('billingManagementBtn');
        const billingManagementModal = document.getElementById('billingManagementModal');
        const closeBillingModal = document.getElementById('closeBillingModal');
        const cancelBillingModal = document.getElementById('cancelBillingModal');
        const billingManagementDropdown = document.getElementById('billingManagementDropdown');
        
        // Open billing management modal
        function openBillingModal() {
            if (billingManagementModal) {
                billingManagementModal.classList.remove('hidden');
                document.body.classList.add('modal-open');
                updateBillingModalTheme();
            }
        }
        
        // Close billing management modal
        function closeBillingModalFunc() {
            if (billingManagementModal) {
                billingManagementModal.classList.add('hidden');
                document.body.classList.remove('modal-open');
            }
        }
        
        // Open modal from sidebar button
        if (billingManagementBtn) {
            billingManagementBtn.addEventListener('click', function(e) {
                e.preventDefault();
                openBillingModal();
            });
        }
        
        // Open modal from dropdown menu
        if (billingManagementDropdown) {
            billingManagementDropdown.addEventListener('click', function(e) {
                e.preventDefault();
                const userDropdown = document.getElementById('userDropdown');
                if (userDropdown) {
                    userDropdown.classList.remove('show');
                }
                openBillingModal();
            });
        }
        
        // Close modal with close button
        if (closeBillingModal) {
            closeBillingModal.addEventListener('click', closeBillingModalFunc);
        }
        
        // Close modal with cancel button
        if (cancelBillingModal) {
            cancelBillingModal.addEventListener('click', closeBillingModalFunc);
        }
        
        // Close modal when clicking outside
        if (billingManagementModal) {
            billingManagementModal.addEventListener('click', function(e) {
                if (e.target === billingManagementModal) {
                    closeBillingModalFunc();
                }
            });
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && billingManagementModal && !billingManagementModal.classList.contains('hidden')) {
                closeBillingModalFunc();
            }
        });
        
        // Close modal function for onclick events
        window.closeBillingModal = function() {
            closeBillingModalFunc();
        };
        
        // Update billing modal theme
        function updateBillingModalTheme() {
            const modal = document.querySelector('.billing-modal');
            if (!modal) return;
            
            const textPrimary = getCssVariable('--text-primary');
            const textSecondary = getCssVariable('--text-secondary');
            const borderColor = getCssVariable('--border-color');
            const cardBg = getCssVariable('--card-bg');
            const bgSecondary = getCssVariable('--bg-secondary');
            const primary = getCssVariable('--primary');
            
            // Update all cards
            const cards = modal.querySelectorAll('.billing-option-card');
            cards.forEach(card => {
                card.style.backgroundColor = cardBg;
                card.style.borderColor = borderColor;
                
                if (card.classList.contains('active')) {
                    card.style.borderColor = primary;
                }
            });
            
            // Update stat cards
            const statCards = modal.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                card.style.backgroundColor = cardBg;
                card.style.borderColor = borderColor;
            });
            
            // Update text colors
            const titles = modal.querySelectorAll('.billing-title, .stat-number');
            titles.forEach(title => {
                title.style.color = textPrimary;
            });
            
            const descriptions = modal.querySelectorAll('.billing-description, .stat-label');
            descriptions.forEach(desc => {
                desc.style.color = textSecondary;
            });
            
            // Update badges
            const badges = modal.querySelectorAll('.stat-badge');
            badges.forEach(badge => {
                badge.style.backgroundColor = bgSecondary;
                badge.style.color = textSecondary;
            });
            
            // Update quick action buttons
            const quickActions = modal.querySelectorAll('.quick-action-btn');
            quickActions.forEach(btn => {
                btn.style.backgroundColor = bgSecondary;
                btn.style.borderColor = borderColor;
                btn.style.color = textPrimary;
            });
            
            // Update modal header and footer
            const header = modal.querySelector('.modal-header');
            const footer = modal.querySelector('.modal-footer');
            if (header) {
                header.style.borderColor = borderColor;
            }
            if (footer) {
                footer.style.borderColor = borderColor;
                footer.style.backgroundColor = bgSecondary;
            }
        }
    }
    
    // ============ ADMINISTRATIVE TOOLS MODAL ============
    const administrativeToolsBtn = document.getElementById('administrativeToolsBtn');
    const administrativeToolsModal = document.getElementById('administrativeToolsModal');
    const closeAdminToolsModal = document.getElementById('closeAdminToolsModal');
    const cancelAdminToolsModal = document.getElementById('cancelAdminToolsModal');
    const administrativeToolsDropdown = document.getElementById('administrativeToolsDropdown');
    
    // Open administrative tools modal
    function openAdminToolsModal() {
        if (administrativeToolsModal) {
            administrativeToolsModal.classList.remove('hidden');
            document.body.classList.add('modal-open');
            updateAdminToolsModalTheme();
        }
    }
    
    // Close administrative tools modal
    function closeAdminToolsModalFunc() {
        if (administrativeToolsModal) {
            administrativeToolsModal.classList.add('hidden');
            document.body.classList.remove('modal-open');
        }
    }
    
    // Open modal from sidebar button
    if (administrativeToolsBtn) {
        administrativeToolsBtn.addEventListener('click', function(e) {
            e.preventDefault();
            openAdminToolsModal();
        });
    }
    
    // Open modal from dropdown menu
    if (administrativeToolsDropdown) {
        administrativeToolsDropdown.addEventListener('click', function(e) {
            e.preventDefault();
            const userDropdown = document.getElementById('userDropdown');
            if (userDropdown) {
                userDropdown.classList.remove('show');
            }
            openAdminToolsModal();
        });
    }
    
    // Close modal with close button
    if (closeAdminToolsModal) {
        closeAdminToolsModal.addEventListener('click', closeAdminToolsModalFunc);
    }
    
    // Close modal with cancel button
    if (cancelAdminToolsModal) {
        cancelAdminToolsModal.addEventListener('click', closeAdminToolsModalFunc);
    }
    
    // Close modal when clicking outside
    if (administrativeToolsModal) {
        administrativeToolsModal.addEventListener('click', function(e) {
            if (e.target === administrativeToolsModal) {
                closeAdminToolsModalFunc();
            }
        });
    }
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && administrativeToolsModal && !administrativeToolsModal.classList.contains('hidden')) {
            closeAdminToolsModalFunc();
        }
    });
    
    // Close modal function for onclick events
    window.closeAdminToolsModal = function() {
        closeAdminToolsModalFunc();
    };
    
    // Update administrative tools modal theme
    function updateAdminToolsModalTheme() {
        const modal = document.querySelector('.admin-tools-modal');
        if (!modal) return;
        
        const textPrimary = getCssVariable('--text-primary');
        const textSecondary = getCssVariable('--text-secondary');
        const borderColor = getCssVariable('--border-color');
        const cardBg = getCssVariable('--card-bg');
        const bgSecondary = getCssVariable('--bg-secondary');
        const primary = getCssVariable('--primary');
        
        // Update all cards
        const cards = modal.querySelectorAll('.admin-tools-card');
        cards.forEach(card => {
            card.style.backgroundColor = cardBg;
            card.style.borderColor = borderColor;
            
            if (card.classList.contains('active')) {
                card.style.borderColor = primary;
            }
        });
        
        // Update text colors
        const titles = modal.querySelectorAll('.admin-tools-title');
        titles.forEach(title => {
            title.style.color = textPrimary;
        });
        
        const descriptions = modal.querySelectorAll('.admin-tools-description');
        descriptions.forEach(desc => {
            desc.style.color = textSecondary;
        });
        
        // Update badges
        const badges = modal.querySelectorAll('.stat-badge');
        badges.forEach(badge => {
            badge.style.backgroundColor = bgSecondary;
            badge.style.color = textSecondary;
        });
        
        // Update quick action buttons
        const quickActions = modal.querySelectorAll('.quick-action-btn');
        quickActions.forEach(btn => {
            btn.style.backgroundColor = bgSecondary;
            btn.style.borderColor = borderColor;
            btn.style.color = textPrimary;
        });
        
        // Update modal header and footer
        const header = modal.querySelector('.modal-header');
        const footer = modal.querySelector('.modal-footer');
        if (header) {
            header.style.borderColor = borderColor;
        }
        if (footer) {
            footer.style.borderColor = borderColor;
            footer.style.backgroundColor = bgSecondary;
        }
    }
    
    // ============ THEME SETTINGS MODAL ============
    const themeSettingsButton = document.getElementById('themeSettingsButton');
    const themeSettingsModal = document.getElementById('themeSettingsModal');
    const closeThemeModal = document.getElementById('closeThemeModal');
    const closeThemeModalBtn = document.getElementById('closeThemeModalBtn');
    
    if (themeSettingsButton && themeSettingsModal) {
        themeSettingsButton.addEventListener('click', function() {
            themeSettingsModal.classList.remove('hidden');
            document.body.classList.add('modal-open');
        });
    }
    
    function closeThemeModalFunc() {
        if (themeSettingsModal) {
            themeSettingsModal.classList.add('hidden');
            document.body.classList.remove('modal-open');
        }
    }
    
    if (closeThemeModal) {
        closeThemeModal.addEventListener('click', closeThemeModalFunc);
    }
    
    if (closeThemeModalBtn) {
        closeThemeModalBtn.addEventListener('click', closeThemeModalFunc);
    }
    
    if (themeSettingsModal) {
        themeSettingsModal.addEventListener('click', function(e) {
            if (e.target === themeSettingsModal) {
                closeThemeModalFunc();
            }
        });
    }
    
    // ============ SEARCH MODAL FUNCTIONALITY ============
    const advancedSearchBtn = document.getElementById('advancedSearchBtn');
    const searchModal = document.getElementById('searchModal');
    const closeSearchModal = document.getElementById('closeSearchModal');
    const cancelSearch = document.getElementById('cancelSearch');
    const clearSearch = document.getElementById('clearSearch');
    const performSearch = document.getElementById('performSearch');
    const quickSearchInput = document.getElementById('quickSearchInput');
    const globalSearchInput = document.getElementById('globalSearchInput');
    const searchCategory = document.getElementById('searchCategory');
    const mobileSearchBtn = document.getElementById('mobileSearchBtn');
    const toggleAdvancedFilters = document.getElementById('toggleAdvancedFilters');
    const advancedFilters = document.getElementById('advancedFilters');
    const clearRecentSearchesBtn = document.getElementById('clearRecentSearchesBtn');
    const scrollTopIndicator = document.getElementById('scrollTopIndicator');
    const scrollBottomIndicator = document.getElementById('scrollBottomIndicator');
    
    // Check if user is authenticated
    const isAuthenticated = {{ auth()->check() ? 'true' : 'false' }};
    
    if (isAuthenticated) {
        console.log('Search access granted for user');
        
        // Scroll indicator functionality
        function initModalScrollIndicators() {
            const modalBody = document.querySelector('.modal-scrollable-body');
            
            if (!modalBody || !scrollTopIndicator || !scrollBottomIndicator) return;
            
            function updateScrollIndicators() {
                const scrollTop = modalBody.scrollTop;
                const scrollHeight = modalBody.scrollHeight;
                const clientHeight = modalBody.clientHeight;
                
                // Show/hide top indicator
                if (scrollTop > 20) {
                    scrollTopIndicator.classList.remove('hidden');
                    scrollTopIndicator.classList.add('visible');
                } else {
                    scrollTopIndicator.classList.remove('visible');
                    scrollTopIndicator.classList.add('hidden');
                }
                
                // Show/hide bottom indicator
                if (scrollHeight - scrollTop > clientHeight + 20) {
                    scrollBottomIndicator.classList.remove('hidden');
                    scrollBottomIndicator.classList.add('visible');
                } else {
                    scrollBottomIndicator.classList.remove('visible');
                    scrollBottomIndicator.classList.add('hidden');
                }
            }
            
            // Initial check
            updateScrollIndicators();
            
            // Update on scroll
            modalBody.addEventListener('scroll', updateScrollIndicators);
            
            // Update on resize
            window.addEventListener('resize', updateScrollIndicators);
            
            // Smooth scroll to top when top indicator is clicked
            scrollTopIndicator.addEventListener('click', function(e) {
                e.preventDefault();
                modalBody.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
            
            // Smooth scroll to bottom when bottom indicator is clicked
            scrollBottomIndicator.addEventListener('click', function(e) {
                e.preventDefault();
                modalBody.scrollTo({
                    top: modalBody.scrollHeight,
                    behavior: 'smooth'
                });
            });
            
            // Add pointer events to indicators
            scrollTopIndicator.style.pointerEvents = 'auto';
            scrollBottomIndicator.style.pointerEvents = 'auto';
        }
        
        // Update search modal theme
        function updateSearchModalTheme() {
            const theme = getCurrentTheme();
            const modal = document.getElementById('searchModal');
            if (!modal) return;
            
            applyThemeToModal(modal, theme);
        }
        
        // Open search modal
        function openSearchModal() {
            if (!searchModal) return;
            
            searchModal.classList.remove('hidden');
            document.body.classList.add('modal-open');
            
            // Update theme immediately
            updateSearchModalTheme();
            
            // Initialize scroll indicators
            setTimeout(initModalScrollIndicators, 100);
            
            // Update content
            updateRecentSearches();
            updateSearchFields();
            
            // Focus on search input
            setTimeout(() => {
                if (globalSearchInput) {
                    globalSearchInput.focus();
                }
            }, 150);
        }
        
        // Open search modal when advanced search button is clicked
        if (advancedSearchBtn && searchModal) {
            advancedSearchBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                openSearchModal();
            });
        }
        
        // Update search fields based on category
        function updateSearchFields() {
            const category = searchCategory ? searchCategory.value : 'all';
            const container = document.getElementById('searchFieldsContainer');
            const optionsDiv = document.getElementById('searchOptions');
            
            if (category !== 'all' && searchFields[category]) {
                if (container && optionsDiv) {
                    optionsDiv.classList.remove('hidden');
                    container.innerHTML = '';
                    
                    searchFields[category].forEach(field => {
                        const fieldOption = document.createElement('label');
                        fieldOption.className = 'search-field-option flex items-center p-2 rounded border transition-colors duration-200';
                        
                        // Apply current theme
                        const theme = getCurrentTheme();
                        if (theme === 'dark') {
                            fieldOption.style.backgroundColor = '#2d3748';
                            fieldOption.style.borderColor = '#4a5568';
                            fieldOption.style.color = '#e2e8f0';
                        } else {
                            fieldOption.style.backgroundColor = '#f7fafc';
                            fieldOption.style.borderColor = '#e2e8f0';
                            fieldOption.style.color = '#2d3748';
                        }
                        
                        fieldOption.innerHTML = `
                            <input type="checkbox" name="search_fields[]" value="${field}" checked class="mr-2">
                            ${field.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                        `;
                        container.appendChild(fieldOption);
                    });
                    
                    // Update theme for new elements
                    updateSearchModalTheme();
                    
                    // Initialize scroll indicators after content update
                    setTimeout(initModalScrollIndicators, 50);
                }
            } else {
                if (optionsDiv) {
                    optionsDiv.classList.add('hidden');
                }
            }
        }
        
        // Toggle advanced filters
        if (toggleAdvancedFilters && advancedFilters) {
            toggleAdvancedFilters.addEventListener('click', function() {
                advancedFilters.classList.toggle('hidden');
                const icon = this.querySelector('i');
                if (advancedFilters.classList.contains('hidden')) {
                    this.innerHTML = '<i class="fas fa-filter mr-1"></i>Advanced';
                } else {
                    this.innerHTML = '<i class="fas fa-times mr-1"></i>Close';
                }
                
                // Update scroll indicators after toggle
                setTimeout(initModalScrollIndicators, 50);
            });
        }
        
        // Close search modal
        function closeSearchModalFunc() {
            if (searchModal) {
                searchModal.classList.add('hidden');
                document.body.classList.remove('modal-open');
            }
        }
        
        if (closeSearchModal) {
            closeSearchModal.addEventListener('click', closeSearchModalFunc);
        }
        
        if (cancelSearch) {
            cancelSearch.addEventListener('click', closeSearchModalFunc);
        }
        
        // Close search modal when clicking outside
        if (searchModal) {
            searchModal.addEventListener('click', function(e) {
                if (e.target === searchModal) {
                    closeSearchModalFunc();
                }
            });
        }
        
        // Close search modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && searchModal && !searchModal.classList.contains('hidden')) {
                closeSearchModalFunc();
            }
        });
        
        // Clear search
        if (clearSearch) {
            clearSearch.addEventListener('click', function() {
                if (globalSearchInput) globalSearchInput.value = '';
                if (searchCategory) searchCategory.value = 'all';
                const exactMatch = document.getElementById('filterExactMatch');
                const caseSensitive = document.getElementById('filterCaseSensitive');
                if (exactMatch) exactMatch.checked = false;
                if (caseSensitive) caseSensitive.checked = false;
                
                // Reset advanced filters
                const dateFrom = document.getElementById('filterDateFrom');
                const dateTo = document.getElementById('filterDateTo');
                const status = document.getElementById('filterStatus');
                if (dateFrom) dateFrom.value = '';
                if (dateTo) dateTo.value = '';
                if (status) status.value = '';
                
                // Reset search context
                const currentContext = document.querySelector('input[name="searchContext"]:checked');
                if (currentContext && currentContext.value === 'current') {
                    const globalContext = document.querySelector('input[name="searchContext"][value="global"]');
                    if (globalContext) globalContext.checked = true;
                }
                
                // Reset search fields
                updateSearchFields();
                
                // Focus back on search input
                if (globalSearchInput) {
                    globalSearchInput.focus();
                }
            });
        }
        
        // Perform search
        if (performSearch) {
            performSearch.addEventListener('click', function() {
                const searchTerm = globalSearchInput ? globalSearchInput.value.trim() : '';
                const category = searchCategory ? searchCategory.value : 'all';
                const exactMatch = document.getElementById('filterExactMatch') ? 
                    document.getElementById('filterExactMatch').checked : false;
                const caseSensitive = document.getElementById('filterCaseSensitive') ? 
                    document.getElementById('filterCaseSensitive').checked : false;
                const searchContext = document.querySelector('input[name="searchContext"]:checked') ? 
                    document.querySelector('input[name="searchContext"]:checked').value : 'global';
                
                // Get selected search fields
                const selectedFields = [];
                if (category !== 'all') {
                    const fieldCheckboxes = document.querySelectorAll('input[name="search_fields[]"]:checked');
                    fieldCheckboxes.forEach(checkbox => {
                        selectedFields.push(checkbox.value);
                    });
                }
                
                // Get advanced filter values
                const dateFrom = document.getElementById('filterDateFrom') ? 
                    document.getElementById('filterDateFrom').value : '';
                const dateTo = document.getElementById('filterDateTo') ? 
                    document.getElementById('filterDateTo').value : '';
                const statusFilter = document.getElementById('filterStatus') ? 
                    document.getElementById('filterStatus').value : '';
                
                if (searchTerm) {
                    addToRecentSearches(searchTerm, category);
                    performGlobalSearch(
                        searchTerm, 
                        category, 
                        exactMatch, 
                        caseSensitive, 
                        searchContext,
                        selectedFields,
                        dateFrom,
                        dateTo,
                        statusFilter
                    );
                    closeSearchModalFunc();
                } else {
                    if (globalSearchInput) {
                        globalSearchInput.style.borderColor = '#ef4444';
                        setTimeout(() => {
                            const theme = getCurrentTheme();
                            globalSearchInput.style.borderColor = theme === 'dark' ? '#4a5568' : '#e5e7eb';
                        }, 1000);
                        globalSearchInput.focus();
                    }
                }
            });
        }
        
        // Quick search
        if (quickSearchInput) {
            quickSearchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    const searchTerm = this.value.trim();
                    if (searchTerm) {
                        addToRecentSearches(searchTerm, 'all');
                        performGlobalSearch(searchTerm, 'all', false, false, 'global', [], '', '', '');
                    }
                }
            });
            
            // Focus search with '/' key
            document.addEventListener('keydown', function(e) {
                if (e.key === '/' && !e.ctrlKey && !e.metaKey && isAuthenticated) {
                    e.preventDefault();
                    if (quickSearchInput) {
                        quickSearchInput.focus();
                    }
                }
            });
            
            // Apply theme to quick search input
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
            
            // Initial theme update
            updateQuickSearchTheme();
            
            // Listen for theme changes
            document.addEventListener('theme-changed', updateQuickSearchTheme);
        }
        
        // Mobile search button
        if (mobileSearchBtn) {
            mobileSearchBtn.addEventListener('click', function() {
                openSearchModal();
            });
        }
        
        // Recent searches functionality
        let recentSearches = JSON.parse(localStorage.getItem('user_' + userRole + '_RecentSearches') || '[]');
        
        function addToRecentSearches(term, category) {
            const search = { term, category, timestamp: Date.now() };
            
            // Remove if already exists
            recentSearches = recentSearches.filter(s => 
                !(s.term === term && s.category === category)
            );
            
            // Add to beginning
            recentSearches.unshift(search);
            
            // Keep only last 10 searches
            recentSearches = recentSearches.slice(0, 10);
            
            // Save to localStorage
            localStorage.setItem('user_' + userRole + '_RecentSearches', JSON.stringify(recentSearches));
            
            // Update UI
            updateRecentSearches();
        }
        
        function updateRecentSearches() {
            const container = document.getElementById('recentSearchesList');
            const parent = document.getElementById('recentSearches');
            const theme = getCurrentTheme();
            
            if (container && parent) {
                if (recentSearches.length > 0) {
                    parent.classList.remove('hidden');
                    container.innerHTML = '';
                    
                    recentSearches.forEach(search => {
                        const tag = document.createElement('div');
                        tag.className = 'recent-search-tag px-3 py-1 rounded-full text-sm cursor-pointer transition-colors duration-200';
                        
                        // Apply theme
                        if (theme === 'dark') {
                            tag.style.backgroundColor = '#2d3748';
                            tag.style.borderColor = '#4a5568';
                            tag.style.color = '#e2e8f0';
                        } else {
                            tag.style.backgroundColor = '#f7fafc';
                            tag.style.borderColor = '#e2e8f0';
                            tag.style.color = '#2d3748';
                        }
                        
                        tag.innerHTML = `
                            ${search.term} 
                            <span style="font-size: 10px; opacity: 0.7;">(${searchableCategories[search.category] || search.category})</span>
                        `;
                        tag.title = `Click to search "${search.term}" in ${searchableCategories[search.category] || search.category}`;
                        tag.addEventListener('click', function() {
                            if (globalSearchInput) globalSearchInput.value = search.term;
                            if (searchCategory) searchCategory.value = search.category;
                            updateSearchFields();
                            if (globalSearchInput) globalSearchInput.focus();
                        });
                        container.appendChild(tag);
                    });
                    
                    // Update theme for new elements
                    updateSearchModalTheme();
                    
                    // Update scroll indicators after content update
                    setTimeout(initModalScrollIndicators, 50);
                } else {
                    parent.classList.add('hidden');
                }
            }
        }
        
        // Clear recent searches
        if (clearRecentSearchesBtn) {
            clearRecentSearchesBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if (recentSearches.length === 0) {
                    return;
                }
                
                // Clear from localStorage
                localStorage.removeItem('user_' + userRole + '_RecentSearches');
                recentSearches = [];
                
                // Update UI
                updateRecentSearches();
            });
        }
        
        // Get current page route
        function getCurrentRoute() {
            const currentPath = window.location.pathname;
            
            // Find the matching route from accessible routes
            for (const key in routeMap) {
                const routePath = routeMap[key].replace(/^.*\/\/[^\/]+/, ''); // Get path from URL
                if (currentPath.includes(routePath)) {
                    return routeMap[key];
                }
            }
            
            return null;
        }
        
        function performGlobalSearch(
            term, 
            category, 
            exactMatch, 
            caseSensitive, 
            context, 
            fields, 
            dateFrom, 
            dateTo, 
            status
        ) {
            // Build search URL based on category and user role
            let searchUrl = '';
            const searchParams = new URLSearchParams();
            
            searchParams.append('search', term);
            searchParams.append('role', userRole);
            
            if (category !== 'all') {
                searchParams.append('category', category);
            }
            
            if (exactMatch) {
                searchParams.append('exact_match', '1');
            }
            
            if (caseSensitive) {
                searchParams.append('case_sensitive', '1');
            }
            
            if (fields.length > 0) {
                searchParams.append('fields', fields.join(','));
            }
            
            if (dateFrom) {
                searchParams.append('date_from', dateFrom);
            }
            
            if (dateTo) {
                searchParams.append('date_to', dateTo);
            }
            
            if (status) {
                searchParams.append('status', status);
            }
            
            // Determine the appropriate route
            const currentRoute = getCurrentRoute();
            
            if (context === 'current' && currentRoute) {
                // Search within current section
                searchUrl = currentRoute + '?' + searchParams.toString();
            } else if (category !== 'all' && routeMap[category]) {
                // Use category-specific route
                searchUrl = routeMap[category] + '?' + searchParams.toString();
            } else {
                // Default to dashboard based on role
                if (isSuperAdmin) {
                    searchUrl = '{{ route("super-admin.dashboard") }}' + '?' + searchParams.toString();
                } else {
                    searchUrl = '{{ route("admin.dashboard") }}' + '?' + searchParams.toString();
                }
            }
            
            // Show loading state
            const performBtn = document.getElementById('performSearch');
            if (performBtn) {
                const originalHtml = performBtn.innerHTML;
                performBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Searching...';
                performBtn.disabled = true;
                
                // Reset button after 3 seconds if navigation fails
                setTimeout(() => {
                    performBtn.innerHTML = originalHtml;
                    performBtn.disabled = false;
                }, 3000);
            }
            
            // Navigate to search results
            console.log('Navigating to:', searchUrl);
            window.location.href = searchUrl;
        }
        
        // Keyboard navigation in search modal
        document.addEventListener('keydown', function(e) {
            if (searchModal && !searchModal.classList.contains('hidden')) {
                // Tab key navigation
                if (e.key === 'Tab') {
                    const focusableElements = searchModal.querySelectorAll(
                        'input, select, textarea, button, [href], [tabindex]:not([tabindex="-1"])'
                    );
                    const firstElement = focusableElements[0];
                    const lastElement = focusableElements[focusableElements.length - 1];
                    
                    // Trap focus within modal
                    if (e.shiftKey) {
                        if (document.activeElement === firstElement) {
                            e.preventDefault();
                            lastElement.focus();
                        }
                    } else {
                        if (document.activeElement === lastElement) {
                            e.preventDefault();
                            firstElement.focus();
                        }
                    }
                }
                
                // Arrow key scrolling
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    const modalBody = document.querySelector('.modal-scrollable-body');
                    if (modalBody) {
                        const scrollAmount = e.key === 'ArrowDown' ? 100 : -100;
                        modalBody.scrollBy({
                            top: scrollAmount,
                            behavior: 'smooth'
                        });
                        e.preventDefault();
                    }
                }
                
                // Page up/down scrolling
                if (e.key === 'PageDown' || e.key === 'PageUp') {
                    const modalBody = document.querySelector('.modal-scrollable-body');
                    if (modalBody) {
                        const scrollAmount = e.key === 'PageDown' ? 
                            modalBody.clientHeight * 0.8 : 
                            -modalBody.clientHeight * 0.8;
                        modalBody.scrollBy({
                            top: scrollAmount,
                            behavior: 'smooth'
                        });
                        e.preventDefault();
                    }
                }
                
                // Home/End keys
                if (e.key === 'Home') {
                    const modalBody = document.querySelector('.modal-scrollable-body');
                    if (modalBody) {
                        modalBody.scrollTo({
                            top: 0,
                            behavior: 'smooth'
                        });
                        e.preventDefault();
                    }
                }
                
                if (e.key === 'End') {
                    const modalBody = document.querySelector('.modal-scrollable-body');
                    if (modalBody) {
                        modalBody.scrollTo({
                            top: modalBody.scrollHeight,
                            behavior: 'smooth'
                        });
                        e.preventDefault();
                    }
                }
            }
        });
        
        // Listen for category changes
        if (searchCategory) {
            searchCategory.addEventListener('change', function() {
                updateSearchFields();
                // Scroll to top when category changes
                const modalBody = document.querySelector('.modal-scrollable-body');
                if (modalBody) {
                    modalBody.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        }
        
        // Initialize search functionality
        updateRecentSearches();
        updateSearchModalTheme();
        updateSearchFields();
    }
    
    // ============ NOTIFICATION FUNCTIONALITY ============
    // [removed] legacy loadNotifications – superseded by notification-bell component
                return response.json();
            })
            .then(data => {
                let html = '';
                
                if(!data || data.length === 0) {
                    html = `
                        <div class="p-6 text-center">
                            <i class="fas fa-bell-slash text-gray-300 text-3xl mb-3"></i>
                            <p class="text-gray-500 text-sm" style="color: var(--text-secondary);">No notifications</p>
                        </div>
                    `;
                } else {
                    data.forEach(notification => {
                        const isUnread = notification.is_unread || false;
                        const timeAgo = notification.time_ago || 'Just now';
                        const icon = notification.icon || 'fas fa-bell';
                        const category = notification.category ? 
                            `<span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-600" style="background-color: var(--bg-secondary); color: var(--text-secondary);">${notification.category}</span>` : '';
                        
                        html += `
                        <div class="border-b border-gray-100 last:border-0 notification-item ${isUnread ? 'unread' : ''}" style="border-color: var(--border-color);">
                            <div class="p-3 hover:bg-gray-50 ${isUnread ? 'bg-blue-50' : ''}" style="${isUnread ? 'background-color: rgba(var(--primary-rgb), 0.1);' : ''}">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 mt-1">
                                        <i class="${icon}" style="color: var(--primary);"></i>
                                    </div>
                                    <div class="ml-3 flex-1">
                                        <div class="flex justify-between items-start">
                                            <p class="font-medium text-sm" style="color: var(--text-primary);">${notification.title}</p>
                                            ${category}
                                        </div>
                                        <p class="text-sm mt-1" style="color: var(--text-secondary);">${notification.message}</p>
                                        <div class="flex justify-between items-center mt-2">
                                            <span class="text-xs" style="color: var(--text-secondary); opacity: 0.7;">${timeAgo}</span>
                                            <div class="flex space-x-2">
                                                ${isUnread ? 
                                                    `<button onclick="markAsRead('${notification.id}')" 
                                                        class="text-xs transition-colors duration-200"
                                                        style="color: var(--primary); hover:color: var(--secondary);">
                                                        Mark as read
                                                    </button>` : ''
                                                }
                                                ${notification.action_url ? 
                                                    `<a href="${notification.action_url}" 
                                                        class="text-xs transition-colors duration-200"
                                                        style="color: var(--primary); hover:color: var(--secondary);">
                                                        View
                                                    </a>` : ''
                                                }
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                    });
                }
                
                const notificationList = document.getElementById('notificationList');
                if (notificationList) {
                    notificationList.innerHTML = html;
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                const notificationList = document.getElementById('notificationList');
                if (notificationList) {
                    notificationList.innerHTML = `
                        <div class="p-4 text-center" style="color: var(--danger);">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            Failed to load notifications
                        </div>
                    `;
                }
            });
    }
    
    function markAsRead(notificationId) {
        fetch(`/api/notifications/${notificationId}/read`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                // loadNotifications() removed
                updateNotificationCount();
            }
        })
        .catch(error => {
            console.error('Error marking as read:', error);
        });
    }
    
    function markAllAsRead() {
        fetch('/api/notifications/read-all', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            }
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                // loadNotifications() removed
                updateNotificationCount();
            }
        })
        .catch(error => {
            console.error('Error marking all as read:', error);
        });
    }
    
    function clearAllNotifications() {
        if(confirm('Are you sure you want to clear all notifications?')) {
            fetch('/api/notifications', {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                }
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    // loadNotifications() removed
                    updateNotificationCount();
                }
            })
            .catch(error => {
                console.error('Error clearing notifications:', error);
            });
        }
    }
    
    function updateNotificationCount() {
        fetch('/api/notifications/count')
            .then(response => response.json())
            .then(data => {
                const count = data.unread_count || 0;
                const notificationBell = document.querySelector('#notificationBell');
                const badge = document.querySelector('#notificationBell .bg-red-500');
                
                if(count > 0) {
                    // Update header badge
                    if(!badge && notificationBell) {
                        const button = document.querySelector('#notificationBell');
                        const relativeDiv = button.querySelector('.relative');
                        
                        // Add badge if not present
                        const newBadge = document.createElement('span');
                        newBadge.className = 'absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 text-xs flex items-center justify-center';
                        newBadge.textContent = count > 9 ? '9+' : count;
                        relativeDiv.appendChild(newBadge);
                    } else if (badge) {
                        badge.textContent = count > 9 ? '9+' : count;
                    }
                    
                    // Update sidebar badge
                    const sidebarBadge = document.querySelector('.sidebar-notification-badge');
                    if(sidebarBadge) {
                        sidebarBadge.textContent = count > 9 ? '9+' : count;
                    } else {
                        const sidebarLink = document.querySelector('a[href*="notifications.index"]');
                        if(sidebarLink) {
                            const newSidebarBadge = document.createElement('span');
                            newSidebarBadge.className = 'ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center sidebar-notification-badge';
                            newSidebarBadge.textContent = count > 9 ? '9+' : count;
                            sidebarLink.appendChild(newSidebarBadge);
                        }
                    }
                    
                    // Update dropdown badge
                    const dropdownBadge = document.querySelector('.dropdown-item a[href*="notifications"] span');
                    if(dropdownBadge) {
                        dropdownBadge.textContent = count > 9 ? '9+' : count;
                    }
                } else {
                    // Remove header badge
                    if(badge) {
                        badge.remove();
                    }
                    
                    // Remove sidebar badge
                    if(sidebarBadge) {
                        sidebarBadge.remove();
                    }
                    
                    // Remove dropdown badge
                    const dropdownBadge = document.querySelector('.dropdown-item a[href*="notifications"] span');
                    if(dropdownBadge) {
                        dropdownBadge.remove();
                    }
                }
            })
            .catch(error => {
                console.error('Error updating notification count:', error);
            });
    }
    
    // Update notification count every 60 seconds
    setInterval(updateNotificationCount, 60000);
    
    // Update on page load
    updateNotificationCount();
    
    // ============ THEME CHANGE LISTENER ============
    // Listen for theme changes
    document.addEventListener('theme-changed', function() {
        const currentTheme = getCurrentTheme();
        
        // Update billing modal if open and user is super admin
        if (isSuperAdmin) {
            const billingModal = document.getElementById('billingManagementModal');
            if (billingModal && !billingModal.classList.contains('hidden')) {
                updateBillingModalTheme();
            }
        }
        
        // Update admin tools modal if open
        const adminToolsModal = document.getElementById('administrativeToolsModal');
        if (adminToolsModal && !adminToolsModal.classList.contains('hidden')) {
            updateAdminToolsModalTheme();
        }
        
        // Update search modal if open
        const searchModal = document.getElementById('searchModal');
        if (searchModal && !searchModal.classList.contains('hidden')) {
            updateSearchModalTheme();
        }
        
        // Update quick search input
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
    });
    
    // Make notification functions available globally
    // window.loadNotifications removed
    window.markAsRead = markAsRead;
    // window.markAllAsRead removed
    window.clearAllNotifications = clearAllNotifications;
    window.updateNotificationCount = updateNotificationCount;
    
    // Initial theme detection and application
    const initialTheme = getCurrentTheme();
    console.log('Initial theme detected:', initialTheme);
    
    // Add theme change observer for system preference changes
    if (window.matchMedia) {
        const darkModeMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        darkModeMediaQuery.addEventListener('change', (e) => {
            // Only update if no explicit theme is set
            if (!document.documentElement.getAttribute('data-theme')) {
                const newTheme = e.matches ? 'dark' : 'light';
                console.log('System theme changed to:', newTheme);
                document.dispatchEvent(new CustomEvent('theme-changed'));
            }
        });
    }
});
</script>