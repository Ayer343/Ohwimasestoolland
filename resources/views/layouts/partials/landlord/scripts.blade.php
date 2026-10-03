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
        const mainElement = document.querySelector('main');
        
        if (sidebar) {
            if (collapsed) {
                sidebar.classList.add('collapsed');
            } else {
                sidebar.classList.remove('collapsed');
            }
        }
        
        if (mainContent) {
            if (collapsed) {
                mainContent.classList.add('collapsed');
            } else {
                mainContent.classList.remove('collapsed');
            }
        }
        
        if (mainElement && !mainContent) {
            if (collapsed) {
                mainElement.classList.add('collapsed');
            } else {
                mainElement.classList.remove('collapsed');
            }
        }
        
        if (saveToStorage) {
            localStorage.setItem('sidebarCollapsed', collapsed);
        }
        
        if (window.Alpine) {
            const alpineElements = document.querySelectorAll('[x-data]');
            alpineElements.forEach(el => {
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
    // MAIN INITIALIZATION
    // =========================================================================
    
    document.addEventListener('DOMContentLoaded', function() {
        // =====================================================================
        // THEME SETTINGS
        // =====================================================================
        
        const themeSettingsButton = document.getElementById('themeSettingsButton');
        const themeSettingsModal = document.getElementById('themeSettingsModal');
        const closeThemeModal = document.getElementById('closeThemeModal');
        const closeThemeModalBtn = document.getElementById('closeThemeModalBtn');
        const settingsIcon = document.getElementById('settingsIcon');
        
        const toggleSidebarBtn = document.getElementById('toggleSidebar');
        if (toggleSidebarBtn) {
            toggleSidebarBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const isCollapsed = localStorage.getItem('sidebarCollapsed') !== 'true';
                toggleSidebar(isCollapsed, true);
            });
        }
        
        if (settingsIcon && themeSettingsButton) {
            settingsIcon.classList.add('theme-settings-gear');
            themeSettingsButton.addEventListener('mouseenter', function() {
                settingsIcon.classList.add('gear-spin-fast');
            });
            themeSettingsButton.addEventListener('mouseleave', function() {
                settingsIcon.classList.remove('gear-spin-fast');
            });
        }
        
        let selectedSidebarTheme = localStorage.getItem('sidebarTheme') || 'default';
        let selectedAppearance = localStorage.getItem('appearance') || 'system';
        
        function initializeThemeSettings() {
            const savedSidebarTheme = localStorage.getItem('sidebarTheme') || 'default';
            const savedAppearance = localStorage.getItem('appearance') || 'system';
            
            document.body.setAttribute('data-sidebar-theme', savedSidebarTheme);
            applyAppearance(savedAppearance);
            
            const themeOptions = document.querySelectorAll('#themeSettingsModal .theme-option-compact');
            const appearanceOptions = document.querySelectorAll('#themeSettingsModal .appearance-option-compact');
            
            if (themeOptions.length) {
                setThemeSelection(savedSidebarTheme);
            }
            if (appearanceOptions.length) {
                setAppearanceSelection(savedAppearance);
            }
        }
        
        if (themeSettingsButton && themeSettingsModal) {
            themeSettingsButton.addEventListener('click', function() {
                themeSettingsModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                
                selectedAppearance = getCurrentAppearance();
                selectedSidebarTheme = localStorage.getItem('sidebarTheme') || 'default';
                
                const themeOptions = document.querySelectorAll('#themeSettingsModal .theme-option-compact');
                const appearanceOptions = document.querySelectorAll('#themeSettingsModal .appearance-option-compact');
                
                setThemeSelection(selectedSidebarTheme);
                setAppearanceSelection(selectedAppearance);
                
                const modalContent = themeSettingsModal.querySelector('.theme-modal-compact');
                if (modalContent) {
                    modalContent.style.animation = 'modalFadeIn 0.3s ease-out';
                }
            });
        }
        
        const closeThemeModalFunc = function() {
            if (themeSettingsModal) {
                themeSettingsModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        };
        
        if (closeThemeModal) closeThemeModal.addEventListener('click', closeThemeModalFunc);
        if (closeThemeModalBtn) closeThemeModalBtn.addEventListener('click', closeThemeModalFunc);
        
        if (themeSettingsModal) {
            themeSettingsModal.addEventListener('click', function(e) {
                if (e.target === themeSettingsModal) {
                    closeThemeModalFunc();
                }
            });
        }
        
        const themeOptions = document.querySelectorAll('#themeSettingsModal .theme-option-compact');
        themeOptions.forEach(option => {
            option.addEventListener('click', function() {
                const theme = this.getAttribute('data-theme');
                setThemeSelection(theme);
                selectedSidebarTheme = theme;
                document.body.style.transition = 'all 0.5s ease';
                document.body.setAttribute('data-sidebar-theme', theme);
                localStorage.setItem('sidebarTheme', theme);
                setTimeout(() => {
                    document.body.style.transition = '';
                }, 500);
            });
        });
        
        const appearanceOptions = document.querySelectorAll('#themeSettingsModal .appearance-option-compact');
        appearanceOptions.forEach(option => {
            option.addEventListener('click', function() {
                const appearance = this.getAttribute('data-theme');
                setAppearanceSelection(appearance);
                document.body.style.transition = 'all 0.5s ease';
                applyAppearance(appearance);
                setTimeout(() => {
                    document.body.style.transition = '';
                }, 500);
            });
        });
        
        function getCurrentAppearance() {
            const currentTheme = document.body.getAttribute('data-theme');
            if (currentTheme === 'light' || currentTheme === 'dark') {
                return currentTheme;
            }
            const savedAppearance = localStorage.getItem('appearance');
            if (savedAppearance === 'light' || savedAppearance === 'dark') {
                return savedAppearance;
            }
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
        
        function setThemeSelection(theme) {
            const themeOptions = document.querySelectorAll('#themeSettingsModal .theme-option-compact');
            themeOptions.forEach(opt => {
                const existingIndicator = opt.querySelector('.current-selection-compact');
                if (existingIndicator) existingIndicator.remove();
                
                if (opt.getAttribute('data-theme') === theme) {
                    opt.classList.add('active');
                    const indicator = document.createElement('div');
                    indicator.className = 'current-selection-compact';
                    indicator.innerHTML = '<i class="fas fa-check"></i>';
                    opt.appendChild(indicator);
                } else {
                    opt.classList.remove('active');
                }
            });
        }
        
        function setAppearanceSelection(appearance) {
            const appearanceOptions = document.querySelectorAll('#themeSettingsModal .appearance-option-compact');
            appearanceOptions.forEach(opt => {
                const existingIndicator = opt.querySelector('.current-selection-compact');
                if (existingIndicator) existingIndicator.remove();
                
                if (opt.getAttribute('data-theme') === appearance) {
                    opt.classList.add('active');
                    const indicator = document.createElement('div');
                    indicator.className = 'current-selection-compact';
                    indicator.innerHTML = '<i class="fas fa-check"></i>';
                    opt.appendChild(indicator);
                } else {
                    opt.classList.remove('active');
                }
            });
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && themeSettingsModal && !themeSettingsModal.classList.contains('hidden')) {
                closeThemeModalFunc();
            }
        });
        
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
            if (selectedAppearance === 'system') {
                applyAppearance('system');
            }
        });
        
        initializeThemeSettings();

        // =====================================================================
        // 📧 EMAIL MANAGEMENT MODAL
        // =====================================================================

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
                refreshEmailStats();
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

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && emailModal && !emailModal.classList.contains('hidden')) {
                closeEmailModalFunc();
            }
        });

        // =====================================================================
        // 📧 EMAIL ACCOUNT FUNCTIONS
        // =====================================================================

        window.syncAccount = function(accountId) {
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
        };

        window.syncAllEmails = function() {
            if (!confirm('Sync all your email accounts to fetch new emails?')) return;
            
            const accounts = document.querySelectorAll('.account-item');
            let synced = 0;
            let total = accounts.length;
            
            if (total === 0) {
                showNotification('No email accounts to sync.', 'info');
                return;
            }
            
            showNotification(`Syncing ${total} account(s)...`, 'info');
            
            accounts.forEach((account) => {
                let accountId = account.getAttribute('data-account-id');
                if (!accountId) {
                    const syncBtn = account.querySelector('[onclick*="syncAccount"]');
                    if (syncBtn) {
                        const match = syncBtn.getAttribute('onclick')?.match(/\d+/);
                        if (match) accountId = match[0];
                    }
                }
                
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
        };

        window.setPrimaryAccount = function(accountId) {
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
        };

        window.deleteAccount = function(accountId, email) {
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
        };

        function refreshEmailStats() {
            fetch('/api/email-accounts/stats', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const statElements = document.querySelectorAll('.stat-card .text-2xl');
                    if (statElements.length >= 6) {
                        if (statElements[0]) statElements[0].textContent = data.total_accounts || 0;
                        if (statElements[1]) statElements[1].textContent = data.verified_accounts || 0;
                        if (statElements[2]) statElements[2].textContent = data.pending_accounts || 0;
                        if (statElements[3]) statElements[3].textContent = data.failed_accounts || 0;
                        if (statElements[4]) statElements[4].textContent = data.total_emails || 0;
                        if (statElements[5]) statElements[5].textContent = data.unread_emails || 0;
                    }
                }
            })
            .catch(error => {
                console.warn('Could not refresh email stats:', error);
            });
        }

        window.closeEmailModal = closeEmailModalFunc;

        // =====================================================================
        // 🔄 OWNERSHIP TRANSFER - DROPDOWN & MODAL MANAGEMENT
        // =====================================================================

        // =========================================================
        // 1. DROPDOWN TOGGLE FUNCTIONALITY
        // =========================================================
        const dropdownGroup = document.querySelector('.dropdown-group');
        const dropdownToggle = dropdownGroup?.querySelector('.nav-item');
        const dropdownContent = dropdownGroup?.querySelector('.dropdown-content');
        const dropdownArrow = dropdownToggle?.querySelector('.dropdown-arrow');

        // Toggle dropdown on click
        if (dropdownToggle && dropdownContent) {
            dropdownToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Toggle dropdown content
                const isHidden = dropdownContent.classList.contains('hidden');
                
                if (isHidden) {
                    dropdownContent.classList.remove('hidden');
                    dropdownContent.style.display = 'block';
                    dropdownContent.style.animation = 'fadeIn 0.2s ease-out';
                    if (dropdownArrow) {
                        dropdownArrow.style.transform = 'rotate(180deg)';
                    }
                } else {
                    dropdownContent.classList.add('hidden');
                    dropdownContent.style.display = 'none';
                    if (dropdownArrow) {
                        dropdownArrow.style.transform = 'rotate(0deg)';
                    }
                }
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (dropdownGroup && !dropdownGroup.contains(e.target)) {
                if (dropdownContent) {
                    dropdownContent.classList.add('hidden');
                    dropdownContent.style.display = 'none';
                }
                if (dropdownArrow) {
                    dropdownArrow.style.transform = 'rotate(0deg)';
                }
            }
        });

        // =========================================================
        // 2. TRANSFER MODAL - OPEN/CLOSE
        // =========================================================
        const transferModal = document.getElementById('transferModal');
        const transferPropertySelect = document.getElementById('transferPropertySelect');
        const proceedToTransferBtn = document.getElementById('proceedToTransfer');
        const cancelTransferBtn = document.getElementById('cancelTransfer');
        const closeTransferModalBtn = document.getElementById('closeTransferModal');
        const transferFormContainer = document.getElementById('transferFormContainer');

        // Open transfer modal from navigation (Initiate New Transfer)
        window.initiateTransferFromNav = function() {
            if (transferModal) {
                transferModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                
                // Close dropdown
                if (dropdownContent) {
                    dropdownContent.classList.add('hidden');
                    dropdownContent.style.display = 'none';
                }
                if (dropdownArrow) {
                    dropdownArrow.style.transform = 'rotate(0deg)';
                }
                
                // Load properties into select if not already loaded
                if (transferPropertySelect && transferPropertySelect.options.length <= 1) {
                    loadPropertiesForTransfer();
                }
                
                return false;
            }
            return false;
        };

        // Quick property transfer from sidebar
        window.quickPropertyTransfer = function() {
            if (transferModal) {
                transferModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                
                if (transferPropertySelect && transferPropertySelect.options.length <= 1) {
                    loadPropertiesForTransfer();
                }
                
                return false;
            }
            return false;
        };

        // Load properties dynamically
        function loadPropertiesForTransfer() {
            if (!transferPropertySelect) return;
            
            const currentOptions = transferPropertySelect.options.length;
            if (currentOptions > 1) return; // Already loaded
            
            // Show loading state
            transferPropertySelect.innerHTML = `
                <option value="">Loading properties...</option>
            `;
            
            fetch('/api/landlord/properties-for-transfer', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.properties) {
                    // Clear existing options
                    transferPropertySelect.innerHTML = '<option value="">-- Select a Property --</option>';
                    
                    data.properties.forEach(property => {
                        const option = document.createElement('option');
                        option.value = property.id;
                        option.textContent = `${property.property_name} (${property.registration_pattern})`;
                        transferPropertySelect.appendChild(option);
                    });
                    
                    if (data.properties.length === 0) {
                        const option = document.createElement('option');
                        option.value = '';
                        option.textContent = '-- No properties available for transfer --';
                        option.disabled = true;
                        transferPropertySelect.appendChild(option);
                    }
                }
            })
            .catch(error => {
                console.error('Error loading properties:', error);
                showNotification('Failed to load properties', 'error');
                transferPropertySelect.innerHTML = '<option value="">-- Error loading properties --</option>';
            });
        }

        // Close transfer modal
        function closeTransferModalFunc() {
            if (transferModal) {
                transferModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
                if (transferFormContainer) {
                    transferFormContainer.classList.add('hidden');
                    transferFormContainer.innerHTML = '';
                }
                if (transferPropertySelect) {
                    transferPropertySelect.value = '';
                }
            }
        }

        if (closeTransferModalBtn) {
            closeTransferModalBtn.addEventListener('click', closeTransferModalFunc);
        }
        if (cancelTransferBtn) {
            cancelTransferBtn.addEventListener('click', closeTransferModalFunc);
        }

        if (transferModal) {
            transferModal.addEventListener('click', function(e) {
                if (e.target === transferModal) {
                    closeTransferModalFunc();
                }
            });
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && transferModal && !transferModal.classList.contains('hidden')) {
                closeTransferModalFunc();
            }
        });

        // =========================================================
        // 3. HANDLE PROPERTY SELECTION - LOAD FORM
        // =========================================================
        if (transferPropertySelect) {
            transferPropertySelect.addEventListener('change', function() {
                const propertyId = this.value;
                const formContainer = document.getElementById('transferFormContainer');
                
                if (propertyId && formContainer) {
                    // Show loading state
                    formContainer.innerHTML = `
                        <div class="p-4 text-center" style="color: var(--text-secondary);">
                            <i class="fas fa-spinner fa-spin text-2xl mb-2 block"></i>
                            <span>Loading transfer form...</span>
                        </div>
                    `;
                    formContainer.classList.remove('hidden');
                    
                    const formUrl = `/properties/${propertyId}/ownership-transfer/form`;
                    
                    fetch(formUrl, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'text/html, application/json'
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        return response.text();
                    })
                    .then(html => {
                        formContainer.innerHTML = html;
                        formContainer.classList.remove('hidden');
                        
                        // Initialize any form plugins or event handlers
                        initializeTransferForm(propertyId);
                        
                        // Scroll to form
                        setTimeout(() => {
                            formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }, 200);
                    })
                    .catch(error => {
                        console.error('Error loading transfer form:', error);
                        showNotification('Failed to load transfer form. Please try again.', 'error');
                        formContainer.classList.add('hidden');
                        formContainer.innerHTML = `
                            <div class="p-4 text-center" style="color: var(--danger);">
                                <i class="fas fa-exclamation-circle text-2xl mb-2 block"></i>
                                <p class="font-medium">Failed to load transfer form</p>
                                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                    ${error.message || 'Please try again or contact support.'}
                                </p>
                                <button onclick="retryLoadForm('${propertyId}')" 
                                        class="mt-3 px-4 py-2 text-sm rounded-lg transition-colors duration-200"
                                        style="background-color: var(--primary); color: white;">
                                    <i class="fas fa-sync mr-2"></i>Retry
                                </button>
                            </div>
                        `;
                    });
                } else {
                    if (formContainer) {
                        formContainer.classList.add('hidden');
                        formContainer.innerHTML = '';
                    }
                }
            });
        }

        // Retry function for loading form
        window.retryLoadForm = function(propertyId) {
            const formContainer = document.getElementById('transferFormContainer');
            if (!formContainer) return;
            
            formContainer.innerHTML = `
                <div class="p-4 text-center" style="color: var(--text-secondary);">
                    <i class="fas fa-spinner fa-spin text-2xl mb-2 block"></i>
                    <span>Retrying...</span>
                </div>
            `;
            formContainer.classList.remove('hidden');
            
            const select = document.getElementById('transferPropertySelect');
            if (select) {
                select.value = propertyId;
                select.dispatchEvent(new Event('change'));
            }
        };

        // =========================================================
        // 4. INITIALIZE TRANSFER FORM
        // =========================================================
        function initializeTransferForm(propertyId) {
            // Handle existing landlord selection toggle
            const ownerTypeRadios = document.querySelectorAll('input[name="owner_type"]');
            const existingLandlordGroup = document.getElementById('existingLandlordGroup');
            const newOwnerGroup = document.getElementById('newOwnerGroup');
            
            if (ownerTypeRadios.length) {
                ownerTypeRadios.forEach(radio => {
                    radio.addEventListener('change', function() {
                        if (this.value === 'existing') {
                            if (existingLandlordGroup) existingLandlordGroup.style.display = 'block';
                            if (newOwnerGroup) newOwnerGroup.style.display = 'none';
                        } else {
                            if (existingLandlordGroup) existingLandlordGroup.style.display = 'none';
                            if (newOwnerGroup) newOwnerGroup.style.display = 'block';
                        }
                    });
                });
            }
            
            // Handle document upload preview
            const documentInput = document.getElementById('transfer_document');
            const fileNameDisplay = document.getElementById('file_name_display');
            const fileNameText = document.getElementById('fileNameText');
            
            if (documentInput && fileNameDisplay && fileNameText) {
                documentInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        fileNameText.textContent = this.files[0].name;
                        fileNameDisplay.style.display = 'block';
                    } else {
                        fileNameDisplay.style.display = 'none';
                    }
                });
            }
            
            // Handle terms acceptance
            const termsCheckbox = document.getElementById('terms');
            const submitBtn = document.getElementById('submitTransferBtn');
            
            if (termsCheckbox && submitBtn) {
                termsCheckbox.addEventListener('change', function() {
                    submitBtn.disabled = !this.checked;
                });
            }
            
            // Handle form submission via AJAX
            const form = document.getElementById('transferForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalText = submitBtn?.innerHTML || 'Submit';
                    
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
                    }
                    
                    const formData = new FormData(this);
                    
                    fetch(this.action, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    })
                    .then(response => {
                        const contentType = response.headers.get('content-type');
                        if (contentType && contentType.includes('application/json')) {
                            return response.json();
                        }
                        return response.text().then(text => {
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                if (response.redirected) {
                                    return { success: true, redirect: response.url };
                                }
                                throw new Error('Unexpected response format');
                            }
                        });
                    })
                    .then(data => {
                        if (data.success) {
                            showNotification(data.message || 'Transfer request submitted successfully!', 'success');
                            closeTransferModalFunc();
                            setTimeout(() => {
                                window.location.href = data.redirect || '/landlord/ownership-transfers';
                            }, 1500);
                        } else {
                            showNotification(data.message || 'Failed to submit transfer request.', 'error');
                            if (data.errors) {
                                displayValidationErrors(data.errors);
                            }
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalText;
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Form submission error:', error);
                        showNotification('An error occurred. Please try again.', 'error');
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }
                    });
                });
            }
        }

        // =========================================================
        // 5. DISPLAY VALIDATION ERRORS
        // =========================================================
        function displayValidationErrors(errors) {
            const errorContainer = document.getElementById('formErrors');
            if (!errorContainer) return;
            
            errorContainer.innerHTML = '';
            errorContainer.style.display = 'block';
            
            let errorHtml = '<div class="p-3 rounded-lg" style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444;">';
            errorHtml += '<ul class="list-disc list-inside text-sm" style="color: #ef4444;">';
            
            Object.values(errors).forEach(errorMessages => {
                if (Array.isArray(errorMessages)) {
                    errorMessages.forEach(msg => {
                        errorHtml += `<li>${msg}</li>`;
                    });
                } else {
                    errorHtml += `<li>${errorMessages}</li>`;
                }
            });
            
            errorHtml += '</ul></div>';
            errorContainer.innerHTML = errorHtml;
            
            setTimeout(() => {
                errorContainer.style.display = 'none';
            }, 5000);
        }

        // =========================================================
        // 6. PROCEED TO TRANSFER BUTTON
        // =========================================================
        if (proceedToTransferBtn && transferPropertySelect) {
            proceedToTransferBtn.addEventListener('click', function() {
                const propertyId = transferPropertySelect.value;
                
                if (!propertyId) {
                    showNotification('Please select a property to transfer.', 'warning');
                    transferPropertySelect.focus();
                    return;
                }
                
                const formContainer = document.getElementById('transferFormContainer');
                if (formContainer && formContainer.classList.contains('hidden')) {
                    transferPropertySelect.dispatchEvent(new Event('change'));
                }
                
                if (formContainer) {
                    setTimeout(() => {
                        formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 300);
                }
            });
        }

        window.closeTransferModal = closeTransferModalFunc;

        // =====================================================================
        // 🔔 NOTIFICATION FUNCTIONS
        // =====================================================================

        window.updateNotificationCount = function() {
            fetch('/api/notifications/count', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                const count = data.unread_count || 0;
                const notificationBell = document.getElementById('notificationBell');
                if (!notificationBell) return;
                
                const existingBadges = notificationBell.querySelectorAll('.notification-badge, .glowing-red-light, .glowing-badge');
                existingBadges.forEach(badge => badge.remove());
                
                if (count > 0) {
                    const light = document.createElement('div');
                    light.className = 'glowing-red-light';
                    notificationBell.querySelector('.relative')?.appendChild(light);
                    
                    const badge = document.createElement('span');
                    badge.className = 'glowing-badge';
                    badge.textContent = count > 9 ? '9+' : count;
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
                    notificationBell.querySelector('.relative')?.appendChild(badge);
                }
            })
            .catch(error => {
                console.warn('Could not update notification count:', error);
            });
        };

        window.loadNotifications = function() {
            const list = document.getElementById('notificationList');
            if (!list) return;
            
            fetch('/api/notifications/recent', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.length === 0) {
                    list.innerHTML = `
                        <div class="p-4 text-center" style="color: var(--text-secondary);">
                            <i class="fas fa-bell-slash mr-2"></i>No notifications
                        </div>
                    `;
                    return;
                }
                
                list.innerHTML = data.map(notification => `
                    <div class="notification-item p-3 border-b hover:bg-opacity-5 ${notification.is_unread ? 'unread' : ''}" 
                         style="border-color: var(--border-color); cursor: pointer;"
                         onclick="window.markNotificationRead('${notification.id}')">
                        <div class="flex items-start">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                                 style="background-color: rgba(var(--primary-rgb), 0.1);">
                                <i class="${notification.icon || 'fas fa-bell'}" style="color: var(--primary);"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium" style="color: var(--text-primary);">${notification.title}</p>
                                <p class="text-xs" style="color: var(--text-secondary);">${notification.message}</p>
                                <p class="text-xs mt-1" style="color: var(--text-secondary); opacity: 0.7;">${notification.time_ago}</p>
                            </div>
                        </div>
                    </div>
                `).join('');
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                list.innerHTML = `
                    <div class="p-4 text-center" style="color: var(--danger);">
                        <i class="fas fa-exclamation-circle mr-2"></i>Failed to load notifications
                    </div>
                `;
            });
        };

        window.markNotificationRead = function(notificationId) {
            fetch(`/api/notifications/${notificationId}/mark-read`, {
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
                    // loadNotifications() removed
                    updateNotificationCount();
                }
            })
            .catch(error => console.error('Error marking notification read:', error));
        };

        window.markAllAsRead = function() {
            fetch('/api/notifications/mark-all-read', {
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
                    // loadNotifications() removed
                    updateNotificationCount();
                }
            })
            .catch(error => console.error('Error marking all as read:', error));
        };

        window.clearAllNotifications = function() {
            if (!confirm('Clear all notifications?')) return;
            
            fetch('/api/notifications/clear-all', {
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
                    // loadNotifications() removed
                    updateNotificationCount();
                    showNotification('All notifications cleared', 'success');
                }
            })
            .catch(error => console.error('Error clearing notifications:', error));
        };

        // =====================================================================
        // 🔔 TOAST NOTIFICATION SYSTEM
        // =====================================================================

        window.showNotification = function(message, type = 'info') {
            let toastContainer = document.getElementById('toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toast-container';
                toastContainer.style.cssText = `
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    z-index: 9999;
                    display: flex;
                    flex-direction: column;
                    gap: 8px;
                    max-width: 400px;
                    width: 100%;
                `;
                document.body.appendChild(toastContainer);
            }
            
            const toast = document.createElement('div');
            const bgColor = type === 'error' ? '#ef4444' : 
                           (type === 'success' ? '#10b981' : 
                           (type === 'warning' ? '#f59e0b' : '#3b82f6'));
            
            toast.style.cssText = `
                background-color: ${bgColor};
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                font-size: 14px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                animation: slideInRight 0.3s ease-out;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 10px;
            `;
            
            const iconMap = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };
            
            toast.innerHTML = `
                <i class="fas ${iconMap[type] || 'fa-info-circle'}"></i>
                <span>${message}</span>
            `;
            
            toastContainer.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOutRight 0.3s ease-out';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
            
            toast.onclick = () => toast.remove();
        };

        // =====================================================================
        // 🔍 SEARCH MODAL
        // =====================================================================

        const searchModal = document.getElementById('searchModal');
        const advancedSearchBtn = document.getElementById('advancedSearchBtn');
        const closeSearchModal = document.getElementById('closeSearchModal');
        const cancelSearch = document.getElementById('cancelSearch');
        const performSearchBtn = document.getElementById('performSearch');
        const globalSearchInput = document.getElementById('globalSearchInput');
        const searchCategory = document.getElementById('searchCategory');
        const clearSearchBtn = document.getElementById('clearSearch');
        const mobileSearchBtn = document.getElementById('mobileSearchBtn');

        function openSearchModal() {
            if (searchModal) {
                searchModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                setTimeout(() => {
                    if (globalSearchInput) {
                        globalSearchInput.focus();
                        globalSearchInput.select();
                    }
                }, 100);
            }
        }

        function closeSearchModalFunc() {
            if (searchModal) {
                searchModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        }

        if (advancedSearchBtn) {
            advancedSearchBtn.addEventListener('click', function(e) {
                e.preventDefault();
                openSearchModal();
            });
        }

        if (mobileSearchBtn) {
            mobileSearchBtn.addEventListener('click', function() {
                openSearchModal();
            });
        }

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

        if (clearSearchBtn && globalSearchInput) {
            clearSearchBtn.addEventListener('click', function() {
                globalSearchInput.value = '';
                globalSearchInput.focus();
            });
        }

        if (performSearchBtn) {
            performSearchBtn.addEventListener('click', function() {
                performSearch();
            });
        }

        if (globalSearchInput) {
            globalSearchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    performSearch();
                }
            });
        }

        function performSearch() {
            const searchTerm = globalSearchInput?.value?.trim();
            if (!searchTerm) {
                showNotification('Please enter a search term', 'warning');
                return;
            }
            
            const category = searchCategory?.value || 'all';
            
            let url = '/search?q=' + encodeURIComponent(searchTerm);
            if (category !== 'all') {
                url += '&category=' + encodeURIComponent(category);
            }
            
            closeSearchModalFunc();
            window.location.href = url;
        }

        const quickSearchInput = document.getElementById('quickSearchInput');
        if (quickSearchInput) {
            quickSearchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    const searchTerm = this.value.trim();
                    if (searchTerm) {
                        window.location.href = '/search?q=' + encodeURIComponent(searchTerm);
                    }
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === '/' && 
                    !e.ctrlKey && !e.metaKey && 
                    document.activeElement?.tagName !== 'INPUT' &&
                    document.activeElement?.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                    quickSearchInput.focus();
                    quickSearchInput.select();
                }
                
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    if (quickSearchInput) {
                        quickSearchInput.focus();
                        quickSearchInput.select();
                    }
                }
            });
        }

        window.closeSearchModal = closeSearchModalFunc;

        // =====================================================================
        // 📱 MOBILE MENU
        // =====================================================================
        
        const mobileMenuBtn = document.getElementById('toggleSidebarMobile');
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
        
        // =====================================================================
        // 👤 USER DROPDOWN
        // =====================================================================
        
        const userMenuButton = document.getElementById('userMenuButton');
        const userDropdown = document.getElementById('userDropdown');
        
        if (userMenuButton && userDropdown) {
            userMenuButton.addEventListener('click', function(e) {
                e.stopPropagation();
                userDropdown.classList.toggle('show');
            });
            
            document.addEventListener('click', function(e) {
                if (userDropdown && userDropdown.classList.contains('show') && !userMenuButton.contains(e.target)) {
                    userDropdown.classList.remove('show');
                }
            });
        }
        
        // =====================================================================
        // 🎨 FORM ELEMENTS FOCUS STYLES
        // =====================================================================
        
        const formElements = document.querySelectorAll('input, select, textarea');
        formElements.forEach(element => {
            element.addEventListener('focus', function() {
                this.style.boxShadow = '0 0 0 2px var(--primary)';
                this.style.borderColor = 'var(--primary)';
            });
            
            element.addEventListener('blur', function() {
                this.style.boxShadow = '';
                this.style.borderColor = 'var(--border-color)';
            });
        });

        // =====================================================================
        // ✨ SUCCESS MESSAGE AUTO-HIDE
        // =====================================================================
        
        const successMessage = document.querySelector('.bg-green-100, .alert-success, .success-message');
        if (successMessage) {
            setTimeout(() => {
                successMessage.style.transition = 'opacity 0.5s ease';
                successMessage.style.opacity = '0';
                setTimeout(() => {
                    successMessage.style.display = 'none';
                }, 500);
            }, 5000);
        }
        
        // =====================================================================
        // 📊 CARD ANIMATION ON SCROLL
        // =====================================================================
        
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
        
        document.querySelectorAll('.card, .dashboard-card, .stat-card').forEach(card => {
            observer.observe(card);
        });
        
        // =====================================================================
        // 🛡️ SIDEBAR STATE INTERFERENCE FIX
        // =====================================================================
        
        const fixInterference = function() {
            const currentState = localStorage.getItem('sidebarCollapsed') === 'true';
            const sidebar = document.querySelector('.sidebar');
            
            if (sidebar) {
                const hasCollapsedClass = sidebar.classList.contains('collapsed');
                if (hasCollapsedClass !== currentState) {
                    console.log('Sidebar state interference detected, correcting...');
                    toggleSidebar(currentState, false);
                }
            }
        };
        
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
        
        if (window.jQuery) {
            $(document).ajaxComplete(function() {
                setTimeout(fixInterference, 100);
            });
        }
        
        // =====================================================================
        // 🚀 INITIAL LOAD
        // =====================================================================
        
        updateNotificationCount();
        // loadNotifications() removed
        // Expose functions globally
        window.closeEmailModal = closeEmailModalFunc;
        window.closeTransferModal = closeTransferModalFunc;
        window.closeSearchModal = closeSearchModalFunc;
        window.closeThemeModal = closeThemeModalFunc;
        window.initiateTransferFromNav = initiateTransferFromNav;
        window.quickPropertyTransfer = quickPropertyTransfer;
        window.retryLoadForm = retryLoadForm;
        
        console.log('✅ Landlord dashboard initialized with all modals');
        console.log('✅ Transfer form endpoint: /properties/{id}/ownership-transfer/form');
        console.log('✅ Transfer dropdown toggle active');
    });
    
    // =====================================================================
    // 📌 WINDOW LOAD EVENT - Final checks
    // =====================================================================
    
    window.addEventListener('load', function() {
        setTimeout(function() {
            const savedState = localStorage.getItem('sidebarCollapsed') === 'true';
            const sidebar = document.querySelector('.sidebar');
            
            if (sidebar) {
                const hasCollapsedClass = sidebar.classList.contains('collapsed');
                if (hasCollapsedClass !== savedState) {
                    console.log('Final sidebar state correction on window load');
                    toggleSidebar(savedState, false);
                }
            }
            
            document.documentElement.classList.remove('sidebar-loading');
            document.documentElement.classList.add('sidebar-initialized');
            document.body.style.overflow = 'auto';
        }, 10);
    });
})();
</script>