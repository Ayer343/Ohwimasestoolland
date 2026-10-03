<script>
(function() {
    // =========================================================================
    // SIDEBAR COLLAPSE STATE MANAGEMENT - Aligned with layout fix
    // =========================================================================
    
    // Use the globally stored initial state if available
    const initialSidebarState = window.__INITIAL_SIDEBAR_STATE !== undefined 
        ? window.__INITIAL_SIDEBAR_STATE 
        : localStorage.getItem('sidebarCollapsed') === 'true';
    
    // Update localStorage if needed (ensures consistency)
    if (localStorage.getItem('sidebarCollapsed') === null) {
        localStorage.setItem('sidebarCollapsed', initialSidebarState);
    }
    
    // Function to toggle sidebar with proper class application
    function toggleSidebar(collapsed, saveToStorage = true) {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.content');
        const mainContentAlt = document.getElementById('main-content');
        
        // Apply to sidebar
        if (sidebar) {
            if (collapsed) {
                sidebar.classList.add('collapsed');
            } else {
                sidebar.classList.remove('collapsed');
            }
        }
        
        // Apply to main content (try both selectors)
        if (mainContent) {
            if (collapsed) {
                mainContent.classList.add('collapsed');
            } else {
                mainContent.classList.remove('collapsed');
            }
        }
        
        if (mainContentAlt && !mainContent) {
            if (collapsed) {
                mainContentAlt.classList.add('collapsed');
            } else {
                mainContentAlt.classList.remove('collapsed');
            }
        }
        
        // Save to localStorage if requested
        if (saveToStorage) {
            localStorage.setItem('sidebarCollapsed', collapsed);
        }
        
        // Update Alpine.js state if it exists
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
    
    // Apply initial sidebar state immediately (if elements exist)
    if (document.querySelector('.sidebar')) {
        toggleSidebar(initialSidebarState, false);
    }
    
    // =========================================================================
    // MAIN INITIALIZATION - Run when DOM is ready
    // =========================================================================
    
    document.addEventListener('DOMContentLoaded', function() {
        // =====================================================================
        // FIX: Remove pulse animation from toggle button after first click
        // =====================================================================
        
        const toggleSidebarBtn = document.getElementById('toggleSidebar');
        
        // Check if user has already interacted with sidebar
        if (localStorage.getItem('sidebarCollapsed') !== null) {
            // User has previous interaction, remove pulse animation immediately
            if (toggleSidebarBtn) {
                toggleSidebarBtn.classList.add('interacted');
            }
        }
        
        // Add click handler to remove pulse animation and handle toggle
        if (toggleSidebarBtn) {
            toggleSidebarBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remove pulse animation class
                this.classList.add('interacted');
                
                // Toggle sidebar state
                const isCollapsed = localStorage.getItem('sidebarCollapsed') !== 'true';
                toggleSidebar(isCollapsed, true);
                
                // Add haptic feedback if available (for mobile)
                if (window.navigator && window.navigator.vibrate) {
                    window.navigator.vibrate(50);
                }
            });
            
            // Also remove pulse on hover (user is interested)
            toggleSidebarBtn.addEventListener('mouseenter', function() {
                this.classList.add('interacted');
            });
        }
        
        // Enhanced theme settings functionality
        const themeSettingsButton = document.getElementById('themeSettingsButton');
        const themeSettingsModal = document.getElementById('themeSettingsModal');
        const closeThemeModal = document.getElementById('closeThemeModal');
        const closeThemeModalBtn = document.getElementById('closeThemeModalBtn');
        const settingsIcon = document.getElementById('settingsIcon');
        
        // Add enhanced spinning animation to settings gear
        if (settingsIcon && themeSettingsButton) {
            settingsIcon.classList.add('theme-settings-gear');
            
            // Add hover effect for faster spinning
            themeSettingsButton.addEventListener('mouseenter', function() {
                settingsIcon.classList.add('gear-spin-fast');
            });
            
            themeSettingsButton.addEventListener('mouseleave', function() {
                settingsIcon.classList.remove('gear-spin-fast');
            });
        }
        
        // Theme selection variables
        let selectedSidebarTheme = localStorage.getItem('sidebarTheme') || 'default';
        let selectedAppearance = localStorage.getItem('appearance') || 'system';
        
        // Initialize theme settings
        function initializeThemeSettings() {
            const savedSidebarTheme = localStorage.getItem('sidebarTheme') || 'default';
            const savedAppearance = localStorage.getItem('appearance') || 'system';
            
            document.body.setAttribute('data-sidebar-theme', savedSidebarTheme);
            applyAppearance(savedAppearance);
            
            // Set initial selections in modal if modal exists
            const themeOptions = document.querySelectorAll('#themeSettingsModal .theme-option-compact');
            const appearanceOptions = document.querySelectorAll('#themeSettingsModal .appearance-option-compact');
            
            if (themeOptions.length) {
                setThemeSelection(savedSidebarTheme);
            }
            
            if (appearanceOptions.length) {
                setAppearanceSelection(savedAppearance);
            }
        }
        
        // Enhanced theme modal functionality
        if (themeSettingsButton && themeSettingsModal) {
            themeSettingsButton.addEventListener('click', function() {
                themeSettingsModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                
                // Update current selections
                selectedAppearance = getCurrentAppearance();
                selectedSidebarTheme = localStorage.getItem('sidebarTheme') || 'default';
                
                // Refresh theme options in case they were dynamically loaded
                const themeOptions = document.querySelectorAll('#themeSettingsModal .theme-option-compact');
                const appearanceOptions = document.querySelectorAll('#themeSettingsModal .appearance-option-compact');
                
                setThemeSelection(selectedSidebarTheme);
                setAppearanceSelection(selectedAppearance);
                
                // Add opening animation
                const modalContent = themeSettingsModal.querySelector('.theme-modal-compact');
                if (modalContent) {
                    modalContent.style.animation = 'modalFadeIn 0.3s ease-out';
                }
            });
        }
        
        // Close theme modal
        const closeThemeModalFunc = function() {
            if (themeSettingsModal) {
                themeSettingsModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        };
        
        if (closeThemeModal) closeThemeModal.addEventListener('click', closeThemeModalFunc);
        if (closeThemeModalBtn) closeThemeModalBtn.addEventListener('click', closeThemeModalFunc);
        
        // Close theme modal when clicking outside
        if (themeSettingsModal) {
            themeSettingsModal.addEventListener('click', function(e) {
                if (e.target === themeSettingsModal) {
                    closeThemeModalFunc();
                }
            });
        }
        
        // Enhanced theme option selection
        const themeOptions = document.querySelectorAll('#themeSettingsModal .theme-option-compact');
        themeOptions.forEach(option => {
            option.addEventListener('click', function() {
                const theme = this.getAttribute('data-theme');
                setThemeSelection(theme);
                selectedSidebarTheme = theme;
                
                // Apply sidebar theme with smooth transition
                document.body.style.transition = 'all 0.5s ease';
                document.body.setAttribute('data-sidebar-theme', theme);
                localStorage.setItem('sidebarTheme', theme);
                
                // Reset transition after animation
                setTimeout(() => {
                    document.body.style.transition = '';
                }, 500);
            });
        });
        
        // Enhanced appearance option selection
        const appearanceOptions = document.querySelectorAll('#themeSettingsModal .appearance-option-compact');
        appearanceOptions.forEach(option => {
            option.addEventListener('click', function() {
                const appearance = this.getAttribute('data-theme');
                setAppearanceSelection(appearance);
                
                // Apply appearance with smooth transition
                document.body.style.transition = 'all 0.5s ease';
                applyAppearance(appearance);
                
                // Reset transition after animation
                setTimeout(() => {
                    document.body.style.transition = '';
                }, 500);
            });
        });
        
        // Helper functions
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
        
        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && themeSettingsModal && !themeSettingsModal.classList.contains('hidden')) {
                closeThemeModalFunc();
            }
        });
        
        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
            if (selectedAppearance === 'system') {
                applyAppearance('system');
            }
        });
        
        // Initialize theme settings
        initializeThemeSettings();
        
        // Mobile menu functionality
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
        
        // Dropdown functionality
        const userDropdown = document.getElementById('user-dropdown');
        const dropdownMenu = document.getElementById('dropdown-menu');
        
        if (userDropdown && dropdownMenu) {
            userDropdown.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdownMenu.classList.toggle('show');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (dropdownMenu.classList.contains('show') && !userDropdown.contains(e.target)) {
                    dropdownMenu.classList.remove('show');
                }
            });
        }
        
        // Tab functionality for payment providers
        const tabs = document.querySelectorAll('[data-tab-target]');
        const tabPanes = document.querySelectorAll('.tab-pane');
        
        if (tabs.length > 0 && tabPanes.length > 0) {
            // Function to switch tabs
            function switchTab(tab) {
                const target = tab.getAttribute('data-tab-target');
                
                // Update active tab
                tabs.forEach(t => {
                    t.classList.remove('active', 'border-primary');
                    t.setAttribute('aria-selected', 'false');
                });
                tab.classList.add('active', 'border-primary');
                tab.setAttribute('aria-selected', 'true');
                
                // Show active pane and hide others
                tabPanes.forEach(pane => {
                    pane.classList.add('hidden');
                    pane.classList.remove('active');
                    if (pane.id === target) {
                        pane.classList.remove('hidden');
                        pane.classList.add('active');
                    }
                });
            }
            
            // Add click event to all tabs
            tabs.forEach(tab => {
                tab.addEventListener('click', (e) => {
                    e.preventDefault();
                    switchTab(tab);
                });
            });

            // Handle URL hash for direct tab access
            function checkHash() {
                const hash = window.location.hash.substring(1);
                if (hash) {
                    const tab = document.querySelector(`[data-tab-target="${hash}"]`);
                    if (tab) {
                        switchTab(tab);
                    }
                }
            }
            
            // Check hash on page load
            checkHash();
            
            // Update hash when tabs are clicked
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const target = tab.getAttribute('data-tab-target');
                    if (target) {
                        window.location.hash = target;
                    }
                });
            });
        }

        // Add focus styles to form elements
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

        // Auto-hide success message after 5 seconds
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
        
        // Add animation to cards when they come into view
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
        
        // Observe all cards for animation
        document.querySelectorAll('.card').forEach(card => {
            observer.observe(card);
        });
        
        // Fix for any third-party scripts that might interfere
        const fixInterference = function() {
            // Ensure sidebar state remains correct after any dynamic updates
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
        
        // Run interference fix after a short delay and after any AJAX completions
        setTimeout(fixInterference, 1000);
        
        // Also run when any AJAX requests might have completed
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

        // =====================================================================
        // ADDITIONAL PULSE ANIMATION REMOVAL SCENARIOS
        // =====================================================================
        
        // Remove pulse animation if user scrolls (they're actively using the page)
        let scrollTimeout;
        window.addEventListener('scroll', function() {
            if (toggleSidebarBtn && !toggleSidebarBtn.classList.contains('interacted')) {
                // Debounce to avoid performance issues
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(function() {
                    toggleSidebarBtn.classList.add('interacted');
                }, 500);
            }
        }, { passive: true });
        
        // Remove pulse animation if user interacts with any part of the sidebar
        const sidebarElement = document.querySelector('.sidebar');
        if (sidebarElement && toggleSidebarBtn) {
            sidebarElement.addEventListener('mouseenter', function() {
                if (!toggleSidebarBtn.classList.contains('interacted')) {
                    toggleSidebarBtn.classList.add('interacted');
                }
            });
        }
        
        // Remove pulse animation after page has been loaded for a while
        // (user has seen it, no need to keep pulsing)
        setTimeout(function() {
            if (toggleSidebarBtn && !toggleSidebarBtn.classList.contains('interacted')) {
                toggleSidebarBtn.classList.add('interacted');
            }
        }, 10000); // 10 seconds timeout
    });
    
    // =========================================================================
    // WINDOW LOAD EVENT - Final checks and cleanup
    // =========================================================================
    
    window.addEventListener('load', function() {
        // Final verification that sidebar state is correct
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
            
            // Remove any loading classes
            document.documentElement.classList.remove('sidebar-loading');
            document.documentElement.classList.add('sidebar-initialized');
            
            // Final check for pulse animation on toggle button
            const toggleBtn = document.getElementById('toggleSidebar');
            if (toggleBtn && !toggleBtn.classList.contains('interacted')) {
                // If user has previously interacted (based on localStorage), remove pulse
                if (localStorage.getItem('sidebarCollapsed') !== null) {
                    toggleBtn.classList.add('interacted');
                }
            }
        }, 10);
    });
})();
</script>