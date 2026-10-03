<script>
document.addEventListener('DOMContentLoaded', function() {
    // ============================================ //
    // 🛠️ NOTIFICATION SYSTEM                       //
    // ============================================ //
    
    let unreadCount = 0;
    let updateInterval = null;
    
    // Get user role from URL or meta tag
    const userRole = 'field-agent'; // Adjust based on your auth system
    
    // Notification functions
    async function fetchUnreadCount() {
        try {
            const response = await fetch('/api/notifications/count');
            const data = await response.json();
            unreadCount = data.unread_count || 0;
            updateNotificationBadges(unreadCount);
            return unreadCount;
        } catch (error) {
            console.error('Error fetching notification count:', error);
            return 0;
        }
    }
    
    async function fetchRecentNotifications(limit = 10) {
        try {
            const response = await fetch(`/api/notifications/recent?limit=${limit}`);
            const notifications = await response.json();
            renderNotificationsDropdown(notifications);
            return notifications;
        } catch (error) {
            console.error('Error fetching notifications:', error);
            return [];
        }
    }
    
    async function markNotificationAsRead(notificationId) {
        try {
            const response = await fetch(`/api/notifications/${notificationId}/read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            
            if (response.ok) {
                fetchUnreadCount();
                fetchRecentNotifications();
                return true;
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
        return false;
    }
    
    async function markAllAsRead() {
        try {
            const response = await fetch('/api/notifications/read-all', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            
            if (response.ok) {
                fetchUnreadCount();
                fetchRecentNotifications();
                showToast('All notifications marked as read', 'success');
                return true;
            }
        } catch (error) {
            console.error('Error marking all as read:', error);
            showToast('Failed to mark all as read', 'error');
        }
        return false;
    }
    
    async function clearAllNotifications() {
        if (!confirm('Are you sure you want to clear all notifications? This action cannot be undone.')) {
            return;
        }
        
        try {
            const response = await fetch('/api/notifications', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            
            if (response.ok) {
                fetchUnreadCount();
                fetchRecentNotifications();
                showToast('All notifications cleared', 'success');
                return true;
            }
        } catch (error) {
            console.error('Error clearing notifications:', error);
            showToast('Failed to clear notifications', 'error');
        }
        return false;
    }
    
    function updateNotificationBadges(count) {
        // Update bell badge
        const badge = document.getElementById('notificationBadge');
        const badgeCount = badge?.querySelector('span');
        
        if (count > 0) {
            if (badgeCount) {
                badgeCount.textContent = count > 99 ? '99+' : count;
            }
            badge?.classList.remove('hidden');
            
            // Update sidebar badge
            const sidebarBadge = document.getElementById('sidebarNotificationBadge');
            if (sidebarBadge) {
                sidebarBadge.querySelector('span').textContent = count > 99 ? '99+' : count;
                sidebarBadge.style.display = 'block';
            }
            
            // Update dropdown badge
            const dropdownBadge = document.getElementById('dropdownNotificationBadge');
            if (dropdownBadge) {
                dropdownBadge.textContent = count > 99 ? '99+' : count;
                dropdownBadge.classList.remove('hidden');
            }
        } else {
            badge?.classList.add('hidden');
            const sidebarBadge = document.getElementById('sidebarNotificationBadge');
            if (sidebarBadge) sidebarBadge.style.display = 'none';
            
            const dropdownBadge = document.getElementById('dropdownNotificationBadge');
            if (dropdownBadge) dropdownBadge.classList.add('hidden');
        }
    }
    
    function renderNotificationsDropdown(notifications) {
        const container = document.getElementById('notificationsList');
        if (!container) return;
        
        if (!notifications || notifications.length === 0) {
            container.innerHTML = `
                <div class="notifications-empty">
                    <i class="far fa-bell-slash"></i>
                    <p>No notifications yet</p>
                    <p class="text-xs mt-2">When you receive notifications, they'll appear here</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = notifications.map(notification => `
            <div class="notification-item ${!notification.is_read ? 'unread' : ''}" data-id="${notification.id}">
                <div class="flex items-start space-x-3">
                    <div class="notification-icon">
                        <i class="${notification.icon || 'fas fa-bell'}"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title">${escapeHtml(notification.title)}</div>
                        <div class="notification-message">${escapeHtml(notification.message)}</div>
                        <div class="notification-time">${notification.time_ago || formatTimeAgo(notification.created_at)}</div>
                    </div>
                </div>
            </div>
        `).join('');
        
        // Add click handlers
        container.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', async (e) => {
                e.stopPropagation();
                const notificationId = item.dataset.id;
                const notification = notifications.find(n => n.id === notificationId);
                
                if (notification && !notification.is_read) {
                    await markNotificationAsRead(notificationId);
                }
                
                // Redirect if action URL exists
                if (notification?.action_url) {
                    window.location.href = notification.action_url;
                } else {
                    // Close dropdown and go to notifications page
                    document.getElementById('notificationsDropdown')?.classList.add('hidden');
                    window.location.href = `/${userRole}/notifications/${notificationId}`;
                }
            });
        });
    }
    
    function formatTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins} minute${diffMins === 1 ? '' : 's'} ago`;
        if (diffHours < 24) return `${diffHours} hour${diffHours === 1 ? '' : 's'} ago`;
        if (diffDays < 7) return `${diffDays} day${diffDays === 1 ? '' : 's'} ago`;
        
        return date.toLocaleDateString();
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function showToast(message, type = 'info') {
        // Simple toast implementation
        const toast = document.createElement('div');
        toast.className = `fixed bottom-4 right-4 z-50 px-4 py-2 rounded-lg shadow-lg text-white ${
            type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500'
        }`;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    
    // Initialize notification system
    function initNotificationSystem() {
        fetchUnreadCount();
        fetchRecentNotifications();
        
        // Set up polling every 30 seconds
        if (updateInterval) clearInterval(updateInterval);
        updateInterval = setInterval(() => {
            fetchUnreadCount();
            // Only refresh dropdown if it's open
            const dropdown = document.getElementById('notificationsDropdown');
            if (dropdown && !dropdown.classList.contains('hidden')) {
                fetchRecentNotifications();
            }
        }, 30000);
    }
    
    // Notification dropdown toggle
    const notificationBell = document.getElementById('notificationBellBtn');
    const notificationsDropdown = document.getElementById('notificationsDropdown');
    
    if (notificationBell && notificationsDropdown) {
        notificationBell.addEventListener('click', async (e) => {
            e.stopPropagation();
            notificationsDropdown.classList.toggle('hidden');
            if (!notificationsDropdown.classList.contains('hidden')) {
                await fetchRecentNotifications();
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!notificationsDropdown.contains(e.target) && !notificationBell.contains(e.target)) {
                notificationsDropdown.classList.add('hidden');
            }
        });
    }
    
    // Mark all as read button
    const markAllReadBtn = document.getElementById('markAllReadBtn');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', async (e) => {
            e.stopPropagation();
            await markAllAsRead();
        });
    }
    
    // Clear all notifications button
    const clearAllBtn = document.getElementById('clearAllNotificationsBtn');
    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', async (e) => {
            e.stopPropagation();
            await clearAllNotifications();
        });
    }
    
    // Start notification system
    initNotificationSystem();
    
    // ============================================ //
    // 📧 EMAIL MANAGEMENT MODAL                   //
    // ============================================ //
    
    const emailModal = document.getElementById('emailManagementModal');
    const emailBtn = document.getElementById('emailManagementBtn');
    const closeEmailModalBtn = document.getElementById('closeEmailModal');
    const cancelEmailModalBtn = document.getElementById('cancelEmailModal');
    
    if (emailBtn && emailModal) {
        emailBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
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
    
    // Close email modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && emailModal && !emailModal.classList.contains('hidden')) {
            closeEmailModalFunc();
        }
    });
    
    // Make close function globally accessible
    window.closeEmailModal = closeEmailModalFunc;
    
    // ============================================ //
    // 📧 EMAIL ACCOUNT FUNCTIONS                   //
    // ============================================ //
    
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
                showNotification('Sync completed successfully!', 'success');
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
    
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        const colors = {
            success: { bg: '#22c55e', icon: 'fa-check-circle' },
            error: { bg: '#ef4444', icon: 'fa-exclamation-circle' },
            warning: { bg: '#f59e0b', icon: 'fa-exclamation-triangle' },
            info: { bg: '#3b82f6', icon: 'fa-info-circle' }
        };
        const color = colors[type] || colors.info;
        
        notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 text-white`;
        notification.style.backgroundColor = color.bg;
        notification.style.animation = 'slideInRight 0.3s ease-out';
        notification.style.minWidth = '300px';
        notification.style.maxWidth = '500px';
        notification.innerHTML = `
            <div class="flex items-center">
                <i class="fas ${color.icon} mr-2 text-lg"></i>
                <span class="text-sm">${escapeHtml(message)}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }
    
    // Make functions globally accessible
    window.syncAccount = syncAccount;
    window.syncAllEmails = syncAllEmails;
    window.setPrimaryAccount = setPrimaryAccount;
    window.deleteAccount = deleteAccount;
    window.showNotification = showNotification;
    
    // ============================================ //
    // 🔍 SEARCH MODAL FUNCTIONALITY                //
    // ============================================ //
    
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
    
    // Open search modal when advanced search button is clicked
    if (advancedSearchBtn && searchModal) {
        advancedSearchBtn.addEventListener('click', function(e) {
            e.preventDefault();
            searchModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            if (globalSearchInput) globalSearchInput.focus();
            updateRecentSearches();
        });
    }
    
    // Close search modal functionality
    function closeSearchModalFunc() {
        if (searchModal) {
            searchModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
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
    
    // Clear search functionality
    if (clearSearch) {
        clearSearch.addEventListener('click', function() {
            if (globalSearchInput) globalSearchInput.value = '';
            if (searchCategory) searchCategory.value = 'all';
            const exactMatch = document.getElementById('filterExactMatch');
            const caseSensitive = document.getElementById('filterCaseSensitive');
            if (exactMatch) exactMatch.checked = false;
            if (caseSensitive) caseSensitive.checked = false;
        });
    }
    
    // Perform search functionality
    if (performSearch) {
        performSearch.addEventListener('click', function() {
            const searchTerm = globalSearchInput ? globalSearchInput.value.trim() : '';
            const category = searchCategory ? searchCategory.value : 'all';
            const exactMatch = document.getElementById('filterExactMatch')?.checked || false;
            const caseSensitive = document.getElementById('filterCaseSensitive')?.checked || false;
            
            if (searchTerm) {
                addToRecentSearches(searchTerm, category);
                performGlobalSearch(searchTerm, category, exactMatch, caseSensitive);
                closeSearchModalFunc();
            }
        });
    }
    
    // Quick search functionality (Enter key)
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
            if (e.key === '/' && !e.ctrlKey && !e.metaKey && document.activeElement !== quickSearchInput) {
                e.preventDefault();
                quickSearchInput.focus();
            }
        });
    }
    
    // Search helper functions
    let recentSearches = JSON.parse(localStorage.getItem('fieldagentRecentSearches') || '[]');
    
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
        localStorage.setItem('fieldagentRecentSearches', JSON.stringify(recentSearches));
    }
    
    function updateRecentSearches() {
        const container = document.getElementById('recentSearchesList');
        const parent = document.getElementById('recentSearches');
        
        if (recentSearches.length > 0 && parent && container) {
            parent.classList.remove('hidden');
            container.innerHTML = '';
            
            recentSearches.forEach(search => {
                const tag = document.createElement('div');
                tag.className = 'recent-search-tag';
                tag.textContent = `${search.term} (${search.category})`;
                tag.addEventListener('click', function() {
                    if (globalSearchInput) globalSearchInput.value = search.term;
                    if (searchCategory) searchCategory.value = search.category;
                });
                container.appendChild(tag);
            });
        } else if (parent) {
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
        
        // Determine the appropriate route based on current page and category
        const currentPath = window.location.pathname;
        
        if (currentPath.includes('/properties')) {
            searchUrl = '{{ route("field-agent.properties.index") }}?' + searchParams.toString();
        } else if (currentPath.includes('/registration-plans')) {
            searchUrl = '{{ route("field-agent.registration-plans.index") }}?' + searchParams.toString();
        } else if (currentPath.includes('/statistics')) {
            searchUrl = '{{ route("field-agent.statistics") }}?' + searchParams.toString();
        } else if (currentPath.includes('/performance')) {
            searchUrl = '{{ route("field-agent.performance") }}?' + searchParams.toString();
        } else {
            // Default to dashboard search
            searchUrl = '{{ route("field-agent.dashboard") }}?' + searchParams.toString();
        }
        
        // Navigate to search results
        window.location.href = searchUrl;
    }
    
    // Close modals with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (searchModal && !searchModal.classList.contains('hidden')) {
                closeSearchModalFunc();
            }
            if (notificationsDropdown && !notificationsDropdown.classList.contains('hidden')) {
                notificationsDropdown.classList.add('hidden');
            }
            if (emailModal && !emailModal.classList.contains('hidden')) {
                closeEmailModalFunc();
            }
        }
    });
    
    // ============================================ //
    // 🎨 THEME SETTINGS MODAL FUNCTIONALITY        //
    // ============================================ //
    
    const themeSettingsButton = document.getElementById('themeSettingsButton');
    const themeSettingsModal = document.getElementById('themeSettingsModal');
    const closeThemeModal = document.getElementById('closeThemeModal');
    const closeThemeModalBtn = document.getElementById('closeThemeModalBtn');
    
    // Theme selection variables
    let selectedSidebarTheme = localStorage.getItem('sidebarTheme') || 'default';
    let selectedAppearance = localStorage.getItem('appearance') || 'system';
    
    // Function to get current appearance based on data-theme attribute
    function getCurrentAppearance() {
        const currentTheme = document.body.getAttribute('data-theme');
        
        // If theme is explicitly set to light or dark, return that
        if (currentTheme === 'light' || currentTheme === 'dark') {
            return currentTheme;
        }
        
        // Otherwise, check if we're following system preference
        const savedAppearance = localStorage.getItem('appearance');
        if (savedAppearance === 'light' || savedAppearance === 'dark') {
            return savedAppearance;
        }
        
        // Default to system
        return 'system';
    }
    
    // Function to detect system preference
    function getSystemAppearance() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    
    // Function to apply appearance with proper system detection
    function applyAppearance(appearance) {
        if (appearance === 'system') {
            const systemTheme = getSystemAppearance();
            document.body.setAttribute('data-theme', systemTheme);
            selectedAppearance = 'system';
        } else {
            document.body.setAttribute('data-theme', appearance);
            selectedAppearance = appearance;
        }
        localStorage.setItem('appearance', selectedAppearance);
    }
    
    // Initialize current appearance
    selectedAppearance = getCurrentAppearance();
    
    // Open modal
    if (themeSettingsButton && themeSettingsModal) {
        themeSettingsButton.addEventListener('click', function() {
            themeSettingsModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            // Update current selections based on actual state
            selectedAppearance = getCurrentAppearance();
            selectedSidebarTheme = localStorage.getItem('sidebarTheme') || 'default';
            
            // Set current selections in modal
            setThemeSelection(selectedSidebarTheme);
            setAppearanceSelection(selectedAppearance);
        });
    }
    
    // Close modal
    const closeModal = function() {
        if (themeSettingsModal) {
            themeSettingsModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    };
    
    if (closeThemeModal) {
        closeThemeModal.addEventListener('click', closeModal);
    }
    
    if (closeThemeModalBtn) {
        closeThemeModalBtn.addEventListener('click', closeModal);
    }
    
    // Close modal when clicking outside
    if (themeSettingsModal) {
        themeSettingsModal.addEventListener('click', function(e) {
            if (e.target === themeSettingsModal) {
                closeModal();
            }
        });
    }
    
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
        });
    });
    
    // Appearance option selection
    const appearanceOptions = document.querySelectorAll('#themeSettingsModal .appearance-option-compact');
    appearanceOptions.forEach(option => {
        option.addEventListener('click', function() {
            const appearance = this.getAttribute('data-theme');
            setAppearanceSelection(appearance);
            
            // Apply appearance immediately
            applyAppearance(appearance);
        });
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && themeSettingsModal && !themeSettingsModal.classList.contains('hidden')) {
            closeModal();
        }
    });
    
    // Helper functions
    function setThemeSelection(theme) {
        themeOptions.forEach(opt => {
            // Remove current selection indicator
            const existingIndicator = opt.querySelector('.current-selection');
            if (existingIndicator) {
                existingIndicator.remove();
            }
            
            if (opt.getAttribute('data-theme') === theme) {
                opt.classList.add('active');
                // Add current selection indicator
                const indicator = document.createElement('div');
                indicator.className = 'current-selection';
                indicator.innerHTML = '<i class="fas fa-check"></i>';
                opt.appendChild(indicator);
            } else {
                opt.classList.remove('active');
            }
        });
    }
    
    function setAppearanceSelection(appearance) {
        appearanceOptions.forEach(opt => {
            // Remove current selection indicator
            const existingIndicator = opt.querySelector('.current-selection');
            if (existingIndicator) {
                existingIndicator.remove();
            }
            
            if (opt.getAttribute('data-theme') === appearance) {
                opt.classList.add('active');
                // Add current selection indicator
                const indicator = document.createElement('div');
                indicator.className = 'current-selection';
                indicator.innerHTML = '<i class="fas fa-check"></i>';
                opt.appendChild(indicator);
            } else {
                opt.classList.remove('active');
            }
        });
    }
    
    // Initialize theme settings based on saved preferences
    const savedSidebarTheme = localStorage.getItem('sidebarTheme') || 'default';
    const savedAppearance = localStorage.getItem('appearance') || 'system';
    
    document.body.setAttribute('data-sidebar-theme', savedSidebarTheme);
    applyAppearance(savedAppearance);
    
    // Listen for system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
        if (selectedAppearance === 'system') {
            applyAppearance('system');
        }
    });
});
</script>