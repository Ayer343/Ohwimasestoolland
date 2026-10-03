<script>
document.addEventListener('DOMContentLoaded', function() {
    // Advanced Search Modal functionality
    const advancedSearchBtn = document.getElementById('advancedSearchBtn');
    const searchModal = document.getElementById('searchModal');
    const closeSearchModal = document.getElementById('closeSearchModal');
    const cancelSearch = document.getElementById('cancelSearch');
    const clearSearch = document.getElementById('clearSearch');
    const performSearch = document.getElementById('performSearch');
    const quickSearchInput = document.getElementById('quickSearchInput');
    const globalSearchInput = document.getElementById('globalSearchInput');
    const searchCategory = document.getElementById('searchCategory');
    
    // Transfer Modal functionality
    const transferModal = document.getElementById('transferModal');
    const closeTransferModal = document.getElementById('closeTransferModal');
    const cancelTransfer = document.getElementById('cancelTransfer');
    const proceedToTransfer = document.getElementById('proceedToTransfer');
    const transferPropertySelect = document.getElementById('transferPropertySelect');
    
    // Update search modal theme
    function updateSearchModalTheme() {
        const modalContainer = document.querySelector('.search-modal-container');
        if (modalContainer) {
            const textPrimary = getCssVariable('--text-primary');
            const textSecondary = getCssVariable('--text-secondary');
            const bgSecondary = getCssVariable('--bg-secondary');
            const borderColor = getCssVariable('--border-color');
            const primary = getCssVariable('--primary');
            
            // Update input colors
            const inputs = modalContainer.querySelectorAll('input, select');
            inputs.forEach(input => {
                input.style.color = textPrimary;
                input.style.backgroundColor = bgSecondary;
                input.style.borderColor = borderColor;
            });
            
            // Update labels
            const labels = modalContainer.querySelectorAll('label');
            labels.forEach(label => {
                label.style.color = textPrimary;
            });
            
            // Update text spans
            const textSpans = modalContainer.querySelectorAll('span');
            textSpans.forEach(span => {
                if (!span.classList.contains('ml-auto')) {
                    span.style.color = textSecondary;
                }
            });
            
            // Update buttons
            const clearBtn = document.getElementById('clearSearch');
            const cancelBtn = document.getElementById('cancelSearch');
            const performBtn = document.getElementById('performSearch');
            
            if (clearBtn) clearBtn.style.color = textSecondary;
            if (cancelBtn) cancelBtn.style.color = textSecondary;
            if (performBtn) {
                performBtn.style.backgroundColor = primary;
                performBtn.style.color = 'white';
            }
        }
    }
    
    // Update transfer modal theme
    function updateTransferModalTheme() {
        const modalContainer = document.querySelector('.transfer-modal-container');
        if (modalContainer) {
            const textPrimary = getCssVariable('--text-primary');
            const textSecondary = getCssVariable('--text-secondary');
            const bgSecondary = getCssVariable('--bg-secondary');
            const borderColor = getCssVariable('--border-color');
            const primary = getCssVariable('--primary');
            const cardBg = getCssVariable('--card-bg');
            
            // Update modal container
            modalContainer.style.backgroundColor = cardBg;
            modalContainer.style.borderColor = borderColor;
            
            // Update borders
            const borders = modalContainer.querySelectorAll('.border-b, .border-t');
            borders.forEach(border => {
                border.style.borderColor = borderColor;
            });
            
            // Update text colors
            const textElements = modalContainer.querySelectorAll('h3, label, p');
            textElements.forEach(el => {
                el.style.color = textPrimary;
            });
            
            // Update select element
            const select = modalContainer.querySelector('select');
            if (select) {
                select.style.backgroundColor = bgSecondary;
                select.style.color = textPrimary;
                select.style.borderColor = borderColor;
            }
            
            // Update buttons
            const cancelBtn = document.getElementById('cancelTransfer');
            const proceedBtn = document.getElementById('proceedToTransfer');
            
            if (cancelBtn) cancelBtn.style.color = textSecondary;
            if (proceedBtn) {
                proceedBtn.style.backgroundColor = primary;
                proceedBtn.style.color = 'white';
            }
            
            // Update info box
            const infoBox = modalContainer.querySelector('.bg-blue-50, [style*="background-color: rgba"]');
            if (infoBox) {
                infoBox.style.backgroundColor = `rgba(${hexToRgb(primary)}, 0.1)`;
                infoBox.style.borderColor = `rgba(${hexToRgb(primary)}, 0.3)`;
            }
        }
    }
    
    // Helper function to convert hex to RGB
    function hexToRgb(hex) {
        // Remove # if present
        hex = hex.replace('#', '');
        
        // Parse hex values
        const r = parseInt(hex.substring(0, 2), 16);
        const g = parseInt(hex.substring(2, 4), 16);
        const b = parseInt(hex.substring(4, 6), 16);
        
        return `${r}, ${g}, ${b}`;
    }
    
    // Open search modal when advanced search button is clicked
    if (advancedSearchBtn && searchModal) {
        advancedSearchBtn.addEventListener('click', function(e) {
            e.preventDefault();
            searchModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            globalSearchInput.focus();
            updateRecentSearches();
            updateSearchModalTheme();
        });
    }
    
    // Close search modal functionality
    function closeSearchModalFunc() {
        searchModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
    
    if (closeSearchModal) {
        closeSearchModal.addEventListener('click', closeSearchModalFunc);
    }
    
    if (cancelSearch) {
        cancelSearch.addEventListener('click', closeSearchModalFunc);
    }
    
    // Close search modal when clicking outside
    searchModal.addEventListener('click', function(e) {
        if (e.target === searchModal) {
            closeSearchModalFunc();
        }
    });
    
    // Clear search functionality
    if (clearSearch) {
        clearSearch.addEventListener('click', function() {
            globalSearchInput.value = '';
            searchCategory.value = 'all';
            document.getElementById('filterExactMatch').checked = false;
            document.getElementById('filterCaseSensitive').checked = false;
        });
    }
    
    // Perform search functionality
    if (performSearch) {
        performSearch.addEventListener('click', function() {
            const searchTerm = globalSearchInput.value.trim();
            const category = searchCategory.value;
            const exactMatch = document.getElementById('filterExactMatch').checked;
            const caseSensitive = document.getElementById('filterCaseSensitive').checked;
            
            if (searchTerm) {
                addToRecentSearches(searchTerm, category);
                performGlobalSearch(searchTerm, category, exactMatch, caseSensitive);
                closeSearchModalFunc();
            } else {
                globalSearchInput.style.borderColor = getCssVariable('--danger');
                setTimeout(() => {
                    globalSearchInput.style.borderColor = getCssVariable('--border-color');
                }, 1000);
                globalSearchInput.focus();
            }
        });
    }
    
    // Quick search functionality
    if (quickSearchInput) {
        quickSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const searchTerm = this.value.trim();
                if (searchTerm) {
                    addToRecentSearches(searchTerm, 'all');
                    performGlobalSearch(searchTerm, 'all', false, false);
                }
            }
        });
        
        // Focus search with '/' key
        document.addEventListener('keydown', function(e) {
            if (e.key === '/' && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                quickSearchInput.focus();
            }
        });
    }
    
    // Mobile search button functionality
    const mobileSearchBtn = document.getElementById('mobileSearchBtn');
    if (mobileSearchBtn) {
        mobileSearchBtn.addEventListener('click', function() {
            searchModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            globalSearchInput.focus();
            updateRecentSearches();
            updateSearchModalTheme();
        });
    }
    
    // ==================== OWNERSHIP TRANSFER FUNCTIONALITY ====================
    
    // Dropdown functionality for ownership transfer
    document.querySelectorAll('.dropdown-group > .nav-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const dropdown = this.nextElementSibling;
            const arrow = this.querySelector('.dropdown-arrow');
            
            dropdown.classList.toggle('hidden');
            arrow.classList.toggle('rotate-180');
        });
    });
    
    // Initiate transfer from nav dropdown
    window.initiateTransferFromNav = function() {
        transferModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        updateTransferModalTheme();
        return false;
    };
    
    // View all transfers
window.viewMyPendingTransfers = function() {
    window.location.href = '{{ route("landlord.ownership-transfers.index") }}';
    return false;
};

// View transfer history
window.viewTransferHistory = function() {
    window.location.href = '{{ route("landlord.ownership-history") }}';
    return false;
};

// View completed transfers
window.viewCompletedTransfers = function() {
    window.location.href = '{{ route("landlord.ownership-transfers.completed") }}';
    return false;
};
    
    // Quick property transfer
    window.quickPropertyTransfer = function() {
        transferModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        updateTransferModalTheme();
        return false;
    };
    
    // Transfer modal functionality
    if (closeTransferModal) {
        closeTransferModal.addEventListener('click', function() {
            transferModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        });
    }
    
    if (cancelTransfer) {
        cancelTransfer.addEventListener('click', function() {
            transferModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        });
    }
    
    // Close transfer modal when clicking outside
    transferModal.addEventListener('click', function(e) {
        if (e.target === transferModal) {
            transferModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    });
    
    // Proceed to transfer
    if (proceedToTransfer) {
        proceedToTransfer.addEventListener('click', function() {
            const propertyId = transferPropertySelect.value;
            if (!propertyId) {
                alert('Please select a property to transfer.');
                transferPropertySelect.focus();
                transferPropertySelect.style.borderColor = getCssVariable('--danger');
                setTimeout(() => {
                    transferPropertySelect.style.borderColor = getCssVariable('--border-color');
                }, 2000);
                return;
            }
            
            // Redirect to the transfer creation page for the selected property
            window.location.href = `/properties/${propertyId}/ownership-transfer/create`;
        });
    }
    
    // Update transfer form when property is selected
    if (transferPropertySelect) {
        transferPropertySelect.addEventListener('change', function() {
            const propertyId = this.value;
            const formContainer = document.getElementById('transferFormContainer');
            
            if (propertyId) {
                formContainer.classList.remove('hidden');
                // You could load additional property details here via AJAX
                formContainer.innerHTML = `
                    <div class="mt-4 p-3 rounded-lg" 
                         style="background-color: rgba(${hexToRgb(getCssVariable('--primary'))}, 0.1); 
                                border: 1px solid rgba(${hexToRgb(getCssVariable('--primary'))}, 0.3);">
                        <p class="text-sm" style="color: var(--text-primary);">
                            <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i>
                            You will be redirected to the ownership transfer form for the selected property.
                        </p>
                    </div>
                `;
            } else {
                formContainer.classList.add('hidden');
            }
        });
    }
    
    // Search helper functions
    let recentSearches = JSON.parse(localStorage.getItem('landlordRecentSearches') || '[]');
    
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
        localStorage.setItem('landlordRecentSearches', JSON.stringify(recentSearches));
    }
    
    function updateRecentSearches() {
        const container = document.getElementById('recentSearchesList');
        const parent = document.getElementById('recentSearches');
        
        if (recentSearches.length > 0) {
            parent.classList.remove('hidden');
            container.innerHTML = '';
            
            recentSearches.forEach(search => {
                const tag = document.createElement('div');
                tag.className = 'recent-search-tag';
                tag.textContent = `${search.term} (${search.category})`;
                tag.title = `Click to search "${search.term}" in ${search.category}`;
                tag.addEventListener('click', function() {
                    globalSearchInput.value = search.term;
                    searchCategory.value = search.category;
                    globalSearchInput.focus();
                });
                container.appendChild(tag);
            });
        } else {
            parent.classList.add('hidden');
        }
    }
    
    function performGlobalSearch(term, category, exactMatch, caseSensitive) {
        // Build search URL based on category
        let searchUrl = '';
        const searchParams = new URLSearchParams();
        
        searchParams.append('search', term);
        
        if (category !== 'all') {
            searchParams.append('category', category);
        }
        
        if (exactMatch) {
            searchParams.append('exact_match', '1');
        }
        
        if (caseSensitive) {
            searchParams.append('case_sensitive', '1');
        }
        
        // Determine the appropriate route based on category
        if (category === 'properties') {
            searchUrl = '{{ route("properties.my-properties") }}?' + searchParams.toString();
        } else if (category === 'property-units') {
            searchUrl = '{{ route("property-units.index") }}?' + searchParams.toString();
        } else if (category === 'tenants') {
            searchUrl = '{{ route("landlord.tenants.index") }}?' + searchParams.toString();
        } else if (category === 'payments') {
            searchUrl = '{{ route("landlord.payments.history") }}?' + searchParams.toString();
        } else if (category === 'invoices') {
            searchUrl = '{{ route("landlord.invoices") }}?' + searchParams.toString();
        } else if (category === 'ownership-transfers') {
            searchUrl = '{{ route("properties.my-properties") }}?' + searchParams.toString();
        } else {
            // Fallback to current path
            const currentPath = window.location.pathname;
            if (currentPath.includes('/my-properties')) {
                searchUrl = '{{ route("properties.my-properties") }}?' + searchParams.toString();
            } else if (currentPath.includes('/property-units')) {
                searchUrl = '{{ route("property-units.index") }}?' + searchParams.toString();
            } else if (currentPath.includes('/tenants')) {
                searchUrl = '{{ route("landlord.tenants.index") }}?' + searchParams.toString();
            } else if (currentPath.includes('/payments')) {
                searchUrl = '{{ route("landlord.payments.history") }}?' + searchParams.toString();
            } else if (currentPath.includes('/invoices')) {
                searchUrl = '{{ route("landlord.invoices") }}?' + searchParams.toString();
            } else {
                searchUrl = '{{ route("landlord.dashboard") }}?' + searchParams.toString();
            }
        }
        
        // Show loading state
        const performBtn = document.getElementById('performSearch');
        if (performBtn) {
            const originalHtml = performBtn.innerHTML;
            performBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Searching...';
            performBtn.disabled = true;
        }
        
        // Navigate to search results
        setTimeout(() => {
            window.location.href = searchUrl;
        }, 500);
    }
    
    // NOTIFICATION FUNCTIONALITY
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
                            `<span class="px-2 py-1 text-xs rounded-full" style="background-color: var(--bg-secondary); color: var(--text-secondary);">${notification.category}</span>` : '';
                        
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
                
                document.getElementById('notificationList').innerHTML = html;
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                document.getElementById('notificationList').innerHTML = `
                    <div class="p-4 text-center" style="color: var(--danger);">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Failed to load notifications
                    </div>
                `;
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
                const badge = document.querySelector('#notificationBell .glowing-badge');
                const glowingLight = document.querySelector('#notificationBell .glowing-red-light');
                const sidebarBadge = document.querySelector('.sidebar-notification-badge');
                const dropdownBadge = document.querySelector('.dropdown-item a[href*="notifications"] span');
                
                if(count > 0) {
                    // Update header badge and glowing effect
                    if(!badge) {
                        const button = document.querySelector('#notificationBell');
                        const relativeDiv = button.querySelector('.relative');
                        
                        // Add glowing red light if not present
                        if (!glowingLight) {
                            const newGlowingLight = document.createElement('div');
                            newGlowingLight.className = 'glowing-red-light';
                            relativeDiv.appendChild(newGlowingLight);
                        }
                        
                        // Add badge if not present
                        const newBadge = document.createElement('span');
                        newBadge.className = 'absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 text-xs flex items-center justify-center glowing-badge';
                        newBadge.textContent = count > 9 ? '9+' : count;
                        relativeDiv.appendChild(newBadge);
                    } else {
                        badge.textContent = count > 9 ? '9+' : count;
                    }
                    
                    // Update sidebar badge
                    if(sidebarBadge) {
                        sidebarBadge.textContent = count > 9 ? '9+' : count;
                    }
                    
                    // Update dropdown badge
                    if(dropdownBadge) {
                        dropdownBadge.textContent = count > 9 ? '9+' : count;
                        dropdownBadge.classList.add('glowing-badge');
                    }
                } else {
                    // Remove header badge and glowing effect
                    if(badge) {
                        badge.remove();
                    }
                    if(glowingLight) {
                        glowingLight.remove();
                    }
                    
                    // Remove sidebar badge
                    if(sidebarBadge) {
                        sidebarBadge.remove();
                    }
                    
                    // Remove dropdown badge
                    if(dropdownBadge) {
                        dropdownBadge.remove();
                        dropdownBadge.classList.remove('glowing-badge');
                    }
                }
            })
            .catch(error => {
                console.error('Error updating notification count:', error);
            });
    }
    
    // Get CSS variable helper
    function getCssVariable(name) {
        return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    }
    
    // Update notification count every 60 seconds
    setInterval(updateNotificationCount, 60000);
    
    // Update on page load
    updateNotificationCount();
    
    // Initialize modals theme on load
    updateSearchModalTheme();
    updateTransferModalTheme();
    
    // Theme settings modal functionality
    const themeSettingsButton = document.getElementById('themeSettingsButton');
    const themeSettingsModal = document.getElementById('themeSettingsModal');
    const closeThemeModal = document.getElementById('closeThemeModal');
    const closeThemeModalBtn = document.getElementById('closeThemeModalBtn');
    
    // Theme selection variables
    let selectedSidebarTheme = localStorage.getItem('sidebarTheme') || 'default';
    
    // Open modal
    if (themeSettingsButton && themeSettingsModal) {
        themeSettingsButton.addEventListener('click', function() {
            themeSettingsModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            updateThemeModalColors();
            
            // Set current selections in modal
            setThemeSelection(selectedSidebarTheme);
        });
    }
    
    // Update theme modal colors
    function updateThemeModalColors() {
        const modal = document.querySelector('.theme-modal-compact');
        if (modal) {
            const textPrimary = getCssVariable('--text-primary');
            const textSecondary = getCssVariable('--text-secondary');
            const borderColor = getCssVariable('--border-color');
            const cardBg = getCssVariable('--card-bg');
            
            // Update modal background and border
            modal.style.backgroundColor = cardBg;
            modal.style.borderColor = borderColor;
            
            // Update headers and text
            const headers = modal.querySelectorAll('h3, h4, .appearance-label-compact, .theme-label-compact');
            headers.forEach(header => {
                header.style.color = textPrimary;
            });
            
            const descriptions = modal.querySelectorAll('.section-description-compact');
            descriptions.forEach(desc => {
                desc.style.color = textSecondary;
            });
            
            // Update borders
            const borders = modal.querySelectorAll('.theme-modal-header-compact, .theme-modal-footer-compact');
            borders.forEach(border => {
                border.style.borderColor = borderColor;
            });
        }
    }
    
    // Close modal
    const closeThemeModalFunc = function() {
        themeSettingsModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    };
    
    if (closeThemeModal) {
        closeThemeModal.addEventListener('click', closeThemeModalFunc);
    }
    
    if (closeThemeModalBtn) {
        closeThemeModalBtn.addEventListener('click', closeThemeModalFunc);
    }
    
    // Close modal when clicking outside
    themeSettingsModal.addEventListener('click', function(e) {
        if (e.target === themeSettingsModal) {
            closeThemeModalFunc();
        }
    });
    
    // Theme option selection
    const themeOptions = document.querySelectorAll('#themeSettingsModal .theme-option-compact');
    themeOptions.forEach(option => {
        option.addEventListener('click', function() {
            const theme = this.getAttribute('data-theme');
            setThemeSelection(theme);
            selectedSidebarTheme = theme;
            
            // Apply sidebar theme immediately
            document.body.setAttribute('data-sidebar-theme', theme);
            localStorage.setItem('sidebarTheme', theme);
            
            // Update all modals with new theme
            updateSearchModalTheme();
            updateTransferModalTheme();
            updateThemeModalColors();
        });
    });
    
    // Appearance option selection
    const appearanceOptions = document.querySelectorAll('#themeSettingsModal .appearance-option-compact');
    appearanceOptions.forEach(option => {
        option.addEventListener('click', function() {
            const theme = this.getAttribute('data-theme');
            document.body.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            
            // Update all modals with new theme
            updateSearchModalTheme();
            updateTransferModalTheme();
            updateThemeModalColors();
        });
    });
    
    // Close modals with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (!searchModal.classList.contains('hidden')) {
                closeSearchModalFunc();
            }
            if (!themeSettingsModal.classList.contains('hidden')) {
                closeThemeModalFunc();
            }
            if (!transferModal.classList.contains('hidden')) {
                transferModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        }
    });
    
    // Helper functions
    function setThemeSelection(theme) {
        themeOptions.forEach(opt => {
            if (opt.getAttribute('data-theme') === theme) {
                opt.classList.add('active');
            } else {
                opt.classList.remove('active');
            }
        });
    }
    
    // Initialize theme settings based on saved preferences
    const savedSidebarTheme = localStorage.getItem('sidebarTheme') || 'default';
    const savedTheme = localStorage.getItem('theme') || 
                       (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    
    document.body.setAttribute('data-sidebar-theme', savedSidebarTheme);
    document.body.setAttribute('data-theme', savedTheme);
    
    // Listen for theme changes and update modals
    document.addEventListener('theme-changed', function() {
        updateSearchModalTheme();
        updateTransferModalTheme();
        updateThemeModalColors();
    });
    
    // Make notification functions available globally
    // window.loadNotifications removed
    window.markAsRead = markAsRead;
    // window.markAllAsRead removed
    window.clearAllNotifications = clearAllNotifications;
    window.updateNotificationCount = updateNotificationCount;
});
</script>