<script>
document.addEventListener('DOMContentLoaded', function() {
    // ============================================
    // NOTIFICATION SYSTEM
    // ============================================
    
    let unreadCount = 0;
    let updateInterval = null;
    const userRole = 'developer';
    
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
        const badge = document.getElementById('notificationBadge');
        const badgeCount = badge?.querySelector('span');
        
        if (count > 0) {
            if (badgeCount) {
                badgeCount.textContent = count > 99 ? '99+' : count;
            }
            badge?.classList.remove('hidden');
            
            const sidebarBadge = document.getElementById('sidebarNotificationBadge');
            if (sidebarBadge) {
                const badgeSpan = sidebarBadge.querySelector('span');
                if (badgeSpan) badgeSpan.textContent = count > 99 ? '99+' : count;
                sidebarBadge.style.display = 'block';
            }
        } else {
            badge?.classList.add('hidden');
            const sidebarBadge = document.getElementById('sidebarNotificationBadge');
            if (sidebarBadge) sidebarBadge.style.display = 'none';
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
        
        container.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', async (e) => {
                e.stopPropagation();
                const notificationId = item.dataset.id;
                const notification = notifications.find(n => n.id === notificationId);
                
                if (notification && !notification.is_read) {
                    await markNotificationAsRead(notificationId);
                }
                
                if (notification?.action_url) {
                    window.location.href = notification.action_url;
                } else {
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
        const toast = document.createElement('div');
        toast.className = `fixed bottom-4 right-4 z-50 px-4 py-2 rounded-lg shadow-lg text-white ${
            type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500'
        }`;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    
    function initNotificationSystem() {
        fetchUnreadCount();
        fetchRecentNotifications();
        
        if (updateInterval) clearInterval(updateInterval);
        updateInterval = setInterval(() => {
            fetchUnreadCount();
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
        
        document.addEventListener('click', (e) => {
            if (!notificationsDropdown.contains(e.target) && !notificationBell.contains(e.target)) {
                notificationsDropdown.classList.add('hidden');
            }
        });
    }
    
    const markAllReadBtn = document.getElementById('markAllReadBtn');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', async (e) => {
            e.stopPropagation();
            await markAllAsRead();
        });
    }
    
    const clearAllBtn = document.getElementById('clearAllNotificationsBtn');
    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', async (e) => {
            e.stopPropagation();
            await clearAllNotifications();
        });
    }
    
    initNotificationSystem();
    
    // ============================================
    // EMAIL MANAGEMENT MODAL
    // ============================================
    
    const emailManagementBtn = document.getElementById('emailManagementBtn');
    const emailManagementModal = document.getElementById('emailManagementModal');
    const closeEmailModal = document.getElementById('closeEmailModal');
    const cancelEmailModal = document.getElementById('cancelEmailModal');
    const emailManagementDropdown = document.getElementById('emailManagementDropdown');
    
    function openEmailModal() {
        emailManagementModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    
    function closeEmailModalFunc() {
        emailManagementModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
    
    if (emailManagementBtn) {
        emailManagementBtn.addEventListener('click', function(e) {
            e.preventDefault();
            openEmailModal();
        });
    }
    
    if (emailManagementDropdown) {
        emailManagementDropdown.addEventListener('click', function(e) {
            e.preventDefault();
            const userDropdown = document.getElementById('userDropdown');
            if (userDropdown) userDropdown.classList.remove('show');
            openEmailModal();
        });
    }
    
    if (closeEmailModal) closeEmailModal.addEventListener('click', closeEmailModalFunc);
    if (cancelEmailModal) cancelEmailModal.addEventListener('click', closeEmailModalFunc);
    
    if (emailManagementModal) {
        emailManagementModal.addEventListener('click', function(e) {
            if (e.target === emailManagementModal) closeEmailModalFunc();
        });
    }
    
    window.closeEmailModal = closeEmailModalFunc;
    
    // ============================================
    // SMS MANAGEMENT MODAL
    // ============================================
    
    const smsManagementBtn = document.getElementById('smsManagementBtn');
    const smsManagementModal = document.getElementById('smsManagementModal');
    const closeSmsModal = document.getElementById('closeSmsModal');
    const cancelSmsModal = document.getElementById('cancelSmsModal');
    const smsManagementDropdown = document.getElementById('smsManagementDropdown');
    
    function openSmsModal() {
        smsManagementModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    
    function closeSmsModalFunc() {
        smsManagementModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
    
    if (smsManagementBtn) {
        smsManagementBtn.addEventListener('click', function(e) {
            e.preventDefault();
            openSmsModal();
        });
    }
    
    if (smsManagementDropdown) {
        smsManagementDropdown.addEventListener('click', function(e) {
            e.preventDefault();
            const userDropdown = document.getElementById('userDropdown');
            if (userDropdown) userDropdown.classList.remove('show');
            openSmsModal();
        });
    }
    
    if (closeSmsModal) closeSmsModal.addEventListener('click', closeSmsModalFunc);
    if (cancelSmsModal) cancelSmsModal.addEventListener('click', closeSmsModalFunc);
    
    if (smsManagementModal) {
        smsManagementModal.addEventListener('click', function(e) {
            if (e.target === smsManagementModal) closeSmsModalFunc();
        });
    }
    
    window.closeSmsModal = closeSmsModalFunc;
    window.openSmsCompose = function() {
        closeSmsModalFunc();
        window.location.href = '{{ route("sms.compose") }}';
    };
    window.testSmsConnection = function() {
        fetch('/api/sms/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('SMS connection test successful!', 'success');
            } else {
                showToast('SMS connection test failed: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            showToast('Error testing SMS connection', 'error');
        });
    };
    window.refreshSmsStatus = function() {
        showToast('Refreshing SMS status...', 'info');
        location.reload();
    };
    
    // ============================================
    // WHATSAPP MANAGEMENT MODAL
    // ============================================
    
    const whatsappManagementBtn = document.getElementById('whatsappManagementBtn');
    const whatsappManagementModal = document.getElementById('whatsappManagementModal');
    const closeWhatsAppModal = document.getElementById('closeWhatsAppModal');
    const cancelWhatsAppModal = document.getElementById('cancelWhatsAppModal');
    const whatsappManagementDropdown = document.getElementById('whatsappManagementDropdown');
    
    function openWhatsAppModal() {
        whatsappManagementModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    
    function closeWhatsAppModalFunc() {
        whatsappManagementModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
    
    if (whatsappManagementBtn) {
        whatsappManagementBtn.addEventListener('click', function(e) {
            e.preventDefault();
            openWhatsAppModal();
        });
    }
    
    if (whatsappManagementDropdown) {
        whatsappManagementDropdown.addEventListener('click', function(e) {
            e.preventDefault();
            const userDropdown = document.getElementById('userDropdown');
            if (userDropdown) userDropdown.classList.remove('show');
            openWhatsAppModal();
        });
    }
    
    if (closeWhatsAppModal) closeWhatsAppModal.addEventListener('click', closeWhatsAppModalFunc);
    if (cancelWhatsAppModal) cancelWhatsAppModal.addEventListener('click', closeWhatsAppModalFunc);
    
    if (whatsappManagementModal) {
        whatsappManagementModal.addEventListener('click', function(e) {
            if (e.target === whatsappManagementModal) closeWhatsAppModalFunc();
        });
    }
    
    window.closeWhatsAppModal = closeWhatsAppModalFunc;
    
    // ✅ FIXED: Uses whatsapp.messages.compose route
    window.openWhatsAppCompose = function() {
        closeWhatsAppModalFunc();
        window.location.href = '{{ route("developer.whatsapp.messages.compose") }}';
    };
    
    window.testWhatsAppConnection = function() {
        fetch('/api/whatsapp/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('WhatsApp connection test successful!', 'success');
            } else {
                showToast('WhatsApp connection test failed: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            showToast('Error testing WhatsApp connection', 'error');
        });
    };
    
    window.refreshWhatsAppStatus = function() {
        showToast('Refreshing WhatsApp status...', 'info');
        location.reload();
    };
    
    // ============================================
    // COMMUNICATION PROVIDERS MODAL (Combined)
    // ============================================
    
    const communicationManagementBtn = document.getElementById('communicationManagementBtn');
    const communicationManagementModal = document.getElementById('communicationManagementModal');
    const closeCommunicationModal = document.getElementById('closeCommunicationModal');
    const cancelCommunicationModal = document.getElementById('cancelCommunicationModal');
    const communicationManagementDropdown = document.getElementById('communicationManagementDropdown');
    
    function openCommunicationModal() {
        communicationManagementModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    
    function closeCommunicationModalFunc() {
        communicationManagementModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
    
    if (communicationManagementBtn) {
        communicationManagementBtn.addEventListener('click', function(e) {
            e.preventDefault();
            openCommunicationModal();
        });
    }
    
    if (communicationManagementDropdown) {
        communicationManagementDropdown.addEventListener('click', function(e) {
            e.preventDefault();
            const userDropdown = document.getElementById('userDropdown');
            if (userDropdown) userDropdown.classList.remove('show');
            openCommunicationModal();
        });
    }
    
    if (closeCommunicationModal) closeCommunicationModal.addEventListener('click', closeCommunicationModalFunc);
    if (cancelCommunicationModal) cancelCommunicationModal.addEventListener('click', closeCommunicationModalFunc);
    
    if (communicationManagementModal) {
        communicationManagementModal.addEventListener('click', function(e) {
            if (e.target === communicationManagementModal) closeCommunicationModalFunc();
        });
    }
    
    window.closeCommunicationModal = closeCommunicationModalFunc;
    
    // ============================================
    // EMAIL SYNC FUNCTIONS
    // ============================================
    
    window.syncAllEmails = function() {
        showToast('Syncing all email accounts...', 'info');
        fetch('/api/email/sync-all', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message || 'All email accounts synced successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Sync failed: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            showToast('Error syncing email accounts', 'error');
        });
    };
    
    window.syncAccount = function(accountId) {
        showToast('Syncing email account...', 'info');
        fetch(`/api/email/sync/${accountId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message || 'Email account synced successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Sync failed: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            showToast('Error syncing email account', 'error');
        });
    };
    
    window.setPrimaryAccount = function(accountId) {
        fetch(`/api/email/set-primary/${accountId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Primary account updated successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Failed to set primary account: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            showToast('Error setting primary account', 'error');
        });
    };
    
    window.deleteAccount = function(accountId, email) {
        if (!confirm(`Are you sure you want to delete the email account "${email}"? This action cannot be undone.`)) {
            return;
        }
        
        fetch(`/api/email/delete/${accountId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Email account deleted successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Failed to delete account: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            showToast('Error deleting email account', 'error');
        });
    };
    
    // ============================================
    // SEARCH MODAL
    // ============================================
    
    const advancedSearchBtn = document.getElementById('advancedSearchBtn');
    const searchModal = document.getElementById('searchModal');
    const closeSearchModal = document.getElementById('closeSearchModal');
    const cancelSearch = document.getElementById('cancelSearch');
    const clearSearch = document.getElementById('clearSearch');
    const performSearch = document.getElementById('performSearch');
    const quickSearchInput = document.getElementById('quickSearchInput');
    const globalSearchInput = document.getElementById('globalSearchInput');
    const searchCategory = document.getElementById('searchCategory');
    
    if (advancedSearchBtn && searchModal) {
        advancedSearchBtn.addEventListener('click', function(e) {
            e.preventDefault();
            searchModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            if (globalSearchInput) globalSearchInput.focus();
            updateRecentSearches();
        });
    }
    
    function closeSearchModalFunc() {
        searchModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
    
    if (closeSearchModal) closeSearchModal.addEventListener('click', closeSearchModalFunc);
    if (cancelSearch) cancelSearch.addEventListener('click', closeSearchModalFunc);
    
    if (searchModal) {
        searchModal.addEventListener('click', function(e) {
            if (e.target === searchModal) closeSearchModalFunc();
        });
    }
    
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
        
        document.addEventListener('keydown', function(e) {
            if (e.key === '/' && !e.ctrlKey && !e.metaKey && document.activeElement !== quickSearchInput) {
                e.preventDefault();
                quickSearchInput.focus();
            }
        });
    }
    
    const mobileSearchBtn = document.getElementById('mobileSearchBtn');
    if (mobileSearchBtn) {
        mobileSearchBtn.addEventListener('click', function() {
            searchModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            if (globalSearchInput) globalSearchInput.focus();
            updateRecentSearches();
        });
    }
    
    let recentSearches = JSON.parse(localStorage.getItem('developerRecentSearches') || '[]');
    
    function addToRecentSearches(term, category) {
        const search = { term, category, timestamp: Date.now() };
        recentSearches = recentSearches.filter(s => !(s.term === term && s.category === category));
        recentSearches.unshift(search);
        recentSearches = recentSearches.slice(0, 10);
        localStorage.setItem('developerRecentSearches', JSON.stringify(recentSearches));
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
        const searchParams = new URLSearchParams();
        searchParams.append('search', term);
        if (category !== 'all') searchParams.append('category', category);
        if (exactMatch) searchParams.append('exact_match', '1');
        if (caseSensitive) searchParams.append('case_sensitive', '1');
        
        let searchUrl = '';
        if (category === 'sms-providers') {
            searchUrl = '{{ route("admin.sms-providers.index") }}?' + searchParams.toString();
        } else if (category === 'whatsapp-providers') {
            searchUrl = '{{ route("admin.whatsapp-providers.index") }}?' + searchParams.toString();
        } else if (category === 'payment-providers') {
            searchUrl = '{{ route("admin.payment-providers.index") }}?' + searchParams.toString();
        } else if (category === 'email-accounts') {
            searchUrl = '{{ route("email-accounts.index") }}?' + searchParams.toString();
        } else {
            const currentPath = window.location.pathname;
            searchUrl = currentPath + '?' + searchParams.toString();
        }
        
        window.location.href = searchUrl;
    }
    
    // ============================================
    // THEME SETTINGS
    // ============================================
    
    const themeSettingsButton = document.getElementById('themeSettingsButton');
    const themeSettingsModal = document.getElementById('themeSettingsModal');
    const closeThemeModal = document.getElementById('closeThemeModal');
    const closeThemeModalBtn = document.getElementById('closeThemeModalBtn');
    
    let selectedSidebarTheme = localStorage.getItem('sidebarTheme') || 'default';
    let selectedAppearance = localStorage.getItem('appearance') || 'system';
    
    function getCurrentAppearance() {
        const currentTheme = document.body.getAttribute('data-theme');
        if (currentTheme === 'light' || currentTheme === 'dark') return currentTheme;
        const savedAppearance = localStorage.getItem('appearance');
        if (savedAppearance === 'light' || savedAppearance === 'dark') return savedAppearance;
        return 'system';
    }
    
    function getSystemAppearance() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    
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
    
    selectedAppearance = getCurrentAppearance();
    
    if (themeSettingsButton && themeSettingsModal) {
        themeSettingsButton.addEventListener('click', function() {
            themeSettingsModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            selectedAppearance = getCurrentAppearance();
            selectedSidebarTheme = localStorage.getItem('sidebarTheme') || 'default';
            setThemeSelection(selectedSidebarTheme);
            setAppearanceSelection(selectedAppearance);
        });
    }
    
    const closeThemeModalFunc = function() {
        themeSettingsModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    };
    
    if (closeThemeModal) closeThemeModal.addEventListener('click', closeThemeModalFunc);
    if (closeThemeModalBtn) closeThemeModalBtn.addEventListener('click', closeThemeModalFunc);
    
    if (themeSettingsModal) {
        themeSettingsModal.addEventListener('click', function(e) {
            if (e.target === themeSettingsModal) closeThemeModalFunc();
        });
    }
    
    const themeOptions = document.querySelectorAll('#themeSettingsModal .theme-option-compact');
    themeOptions.forEach(option => {
        option.addEventListener('click', function() {
            const theme = this.getAttribute('data-theme');
            setThemeSelection(theme);
            selectedSidebarTheme = theme;
            document.body.setAttribute('data-sidebar-theme', theme);
            localStorage.setItem('sidebarTheme', theme);
        });
    });
    
    const appearanceOptions = document.querySelectorAll('#themeSettingsModal .appearance-option-compact');
    appearanceOptions.forEach(option => {
        option.addEventListener('click', function() {
            const appearance = this.getAttribute('data-theme');
            setAppearanceSelection(appearance);
            applyAppearance(appearance);
        });
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (communicationManagementModal && !communicationManagementModal.classList.contains('hidden')) closeCommunicationModalFunc();
            if (emailManagementModal && !emailManagementModal.classList.contains('hidden')) closeEmailModalFunc();
            if (smsManagementModal && !smsManagementModal.classList.contains('hidden')) closeSmsModalFunc();
            if (whatsappManagementModal && !whatsappManagementModal.classList.contains('hidden')) closeWhatsAppModalFunc();
            if (searchModal && !searchModal.classList.contains('hidden')) closeSearchModalFunc();
            if (themeSettingsModal && !themeSettingsModal.classList.contains('hidden')) closeThemeModalFunc();
            if (notificationsDropdown && !notificationsDropdown.classList.contains('hidden')) notificationsDropdown.classList.add('hidden');
        }
    });
    
    function setThemeSelection(theme) {
        themeOptions.forEach(opt => {
            const existingIndicator = opt.querySelector('.current-selection');
            if (existingIndicator) existingIndicator.remove();
            
            if (opt.getAttribute('data-theme') === theme) {
                opt.classList.add('active');
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
            const existingIndicator = opt.querySelector('.current-selection');
            if (existingIndicator) existingIndicator.remove();
            
            if (opt.getAttribute('data-theme') === appearance) {
                opt.classList.add('active');
                const indicator = document.createElement('div');
                indicator.className = 'current-selection';
                indicator.innerHTML = '<i class="fas fa-check"></i>';
                opt.appendChild(indicator);
            } else {
                opt.classList.remove('active');
            }
        });
    }
    
    const savedSidebarTheme = localStorage.getItem('sidebarTheme') || 'default';
    const savedAppearance = localStorage.getItem('appearance') || 'system';
    
    document.body.setAttribute('data-sidebar-theme', savedSidebarTheme);
    applyAppearance(savedAppearance);
    
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
        if (selectedAppearance === 'system') applyAppearance('system');
    });
});
</script>