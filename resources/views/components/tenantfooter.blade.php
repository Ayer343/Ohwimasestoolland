</main>

<!-- Footer Section -->
<footer class="fixed bottom-0 left-0 right-0 z-40 transition-all duration-300 transform translate-y-0" 
        style="background-color: var(--header-bg); border-color: var(--border-color);" id="mainFooter">
    <div class="border-t" style="border-color: var(--border-color);">
        <div class="container mx-auto px-4 py-3">
            <div class="flex flex-col md:flex-row items-center justify-between">
                <p class="text-sm mb-2 md:mb-0 text-center md:text-left" style="color: var(--text-secondary);">
                    &copy; <span id="footerCurrentYear"></span> <span id="footerAppName">Loading...</span>. All rights reserved.
                </p>
                <div class="flex items-center space-x-6">
                    <p class="text-sm" style="color: var(--text-secondary);">
                        Powered by SteveTech Engineering | +233-595652410
                    </p>
                </div>
            </div>
        </div>
    </div>
</footer>

<script>
    // ============================================
    // FOOTER COMPONENT - DYNAMIC SYSTEM SETTINGS
    // Fetches system_name directly from database via API
    // ============================================
    
    /**
     * Fetch system settings from the backend API
     * This retrieves the system_name from the system_settings table
     */
    async function fetchSystemSettings() {
        try {
            // Attempt to fetch from the dedicated system settings endpoint
            const response = await fetch('/admin/system-settings/api/settings', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                cache: 'no-store' // Don't cache to ensure fresh data
            });
            
            if (response.ok) {
                const data = await response.json();
                return data;
            } else {
                console.warn('API endpoint not available, trying alternative endpoint...');
                // Try alternative endpoint (public settings endpoint)
                return await fetchPublicSystemSettings();
            }
        } catch (error) {
            console.error('Error fetching from admin endpoint:', error);
            return await fetchPublicSystemSettings();
        }
    }
    
    /**
     * Fallback: Fetch from public system settings endpoint
     * This endpoint should be accessible without admin authentication
     */
    async function fetchPublicSystemSettings() {
        try {
            const response = await fetch('/api/system-settings', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                cache: 'no-store'
            });
            
            if (response.ok) {
                const data = await response.json();
                return data;
            } else {
                throw new Error('Public API endpoint returned ' + response.status);
            }
        } catch (error) {
            console.error('Error fetching from public endpoint:', error);
            return null;
        }
    }
    
    /**
     * Alternative: Load system name from meta tag injected by backend
     * This is the fastest and most reliable method
     */
    function getSystemNameFromMeta() {
        // Check for meta tag with name="system-name"
        const metaSystemName = document.querySelector('meta[name="system-name"]');
        if (metaSystemName && metaSystemName.getAttribute('content')) {
            return metaSystemName.getAttribute('content');
        }
        
        // Check for meta tag with name="app-name" as fallback
        const metaAppName = document.querySelector('meta[name="app-name"]');
        if (metaAppName && metaAppName.getAttribute('content')) {
            return metaAppName.getAttribute('content');
        }
        
        return null;
    }
    
    /**
     * Update footer with system name from settings
     */
    async function updateFooterWithSystemName() {
        const appNameSpan = document.getElementById('footerAppName');
        if (!appNameSpan) return;
        
        // Try meta tag first (fastest, no API call)
        let systemName = getSystemNameFromMeta();
        
        if (systemName) {
            appNameSpan.textContent = systemName;
            console.log('Footer updated from meta tag:', systemName);
            return;
        }
        
        // If meta tag not available, fetch from API
        appNameSpan.textContent = 'Loading...';
        
        try {
            const settings = await fetchSystemSettings();
            
            if (settings && settings.system_name) {
                appNameSpan.textContent = settings.system_name;
                console.log('Footer updated from API:', settings.system_name);
            } else if (settings && settings.data && settings.data.system_name) {
                // Handle nested response structure
                appNameSpan.textContent = settings.data.system_name;
                console.log('Footer updated from nested API:', settings.data.system_name);
            } else {
                // Fallback to config value or default
                const fallbackName = document.querySelector('meta[name="app-name"]')?.getAttribute('content') || 
                                    document.querySelector('title')?.innerText?.split('|')[0]?.trim() || 
                                    'Hilltop Estate';
                appNameSpan.textContent = fallbackName;
                console.log('Footer using fallback name:', fallbackName);
            }
        } catch (error) {
            console.error('Failed to load system name for footer:', error);
            // Final fallback
            appNameSpan.textContent = 'Hilltop Estate';
        }
    }
    
    /**
     * Set current year in footer
     */
    function setFooterCurrentYear() {
        const yearSpan = document.getElementById('footerCurrentYear');
        if (yearSpan) {
            yearSpan.textContent = new Date().getFullYear();
        }
    }
    
    // ============================================
    // FOOTER SCROLL & POSITION FUNCTIONALITY
    // ============================================
    
    document.addEventListener('DOMContentLoaded', function() {
        // Set current year first
        setFooterCurrentYear();
        
        // Load and update footer with system name from database
        updateFooterWithSystemName();
        
        const footer = document.getElementById('mainFooter');
        const mainContent = document.getElementById('mainContent');
        const sidebar = document.getElementById('sidebar');
        
        // Variables for scroll handling
        let scrollTimeout;
        let isFooterVisible = true;
        let lastScrollTop = 0;
        
        // Enhanced auto-hide on scroll functionality
        function handleScroll() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const scrollDirection = scrollTop > lastScrollTop;
            const scrollDistance = Math.abs(scrollTop - lastScrollTop);
            
            // Clear any existing timeout
            clearTimeout(scrollTimeout);
            
            // If scrolling down and beyond threshold, hide footer
            if (scrollDirection && scrollDistance > 50 && scrollTop > 100) {
                if (isFooterVisible) {
                    hideFooter();
                }
            } 
            // If scrolling up or at top, show footer
            else if (!scrollDirection || scrollTop <= 100) {
                if (!isFooterVisible) {
                    showFooter();
                }
            }
            
            // Update last scroll position
            lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
            
            // Set a timeout to show footer if user stops scrolling
            scrollTimeout = setTimeout(function() {
                if (!isFooterVisible && scrollTop < 100) {
                    showFooter();
                }
            }, 1500);
        }
        
        // Show footer with smooth animation
        function showFooter() {
            footer.style.transform = 'translateY(0)';
            footer.style.transition = 'transform 0.3s ease-out';
            isFooterVisible = true;
            
            // Adjust content padding
            const footerHeight = footer.offsetHeight;
            if (mainContent) {
                mainContent.style.paddingBottom = footerHeight + 20 + 'px';
            }
        }
        
        // Hide footer with smooth animation
        function hideFooter() {
            footer.style.transform = 'translateY(100%)';
            footer.style.transition = 'transform 0.3s ease-out';
            isFooterVisible = false;
            
            // Adjust content padding
            if (mainContent) {
                mainContent.style.paddingBottom = '20px';
            }
        }
        
        // Show/hide footer on hover
        footer.addEventListener('mouseenter', function() {
            if (!isFooterVisible) {
                showFooter();
            }
        });
        
        // Function to adjust footer position based on sidebar state
        function adjustFooterPosition() {
            if (!footer || !mainContent) return;
            
            const footerHeight = footer.offsetHeight;
            if (isFooterVisible) {
                mainContent.style.paddingBottom = footerHeight + 20 + 'px';
            } else {
                mainContent.style.paddingBottom = '20px';
            }
            
            // On mobile, footer should always take full width
            if (window.innerWidth <= 1280) {
                footer.style.left = '0';
                footer.style.right = '0';
                footer.style.width = '100%';
                return;
            }
            
            // Adjust footer left margin based on sidebar state for desktop
            if (sidebar && sidebar.classList.contains('collapsed')) {
                footer.style.left = '80px';
                footer.style.right = '0';
                footer.style.width = 'calc(100% - 80px)';
            } else if (sidebar) {
                footer.style.left = '280px';
                footer.style.right = '0';
                footer.style.width = 'calc(100% - 280px)';
            } else {
                footer.style.left = '0';
                footer.style.right = '0';
                footer.style.width = '100%';
            }
        }
        
        // Initial adjustment
        adjustFooterPosition();
        
        // Set up scroll event listener with debounce
        let scrollDebounce;
        window.addEventListener('scroll', function() {
            clearTimeout(scrollDebounce);
            scrollDebounce = setTimeout(handleScroll, 50);
        });
        
        // Handle sidebar collapse and its effect on footer
        if (sidebar) {
            const observer = new MutationObserver(function() {
                adjustFooterPosition();
            });
            
            observer.observe(sidebar, {
                attributes: true,
                attributeFilter: ['class']
            });
        }
        
        // Also adjust on window resize
        window.addEventListener('resize', function() {
            adjustFooterPosition();
            // Reset scroll tracking
            lastScrollTop = window.pageYOffset || document.documentElement.scrollTop;
        });
        
        // Touch device handling
        let touchStartY = 0;
        let touchEndY = 0;
        
        document.addEventListener('touchstart', function(e) {
            touchStartY = e.changedTouches[0].screenY;
        }, { passive: true });
        
        document.addEventListener('touchend', function(e) {
            touchEndY = e.changedTouches[0].screenY;
            const touchDistance = touchStartY - touchEndY;
            
            // Hide footer on significant downward swipe
            if (touchDistance > 50 && (window.pageYOffset || document.documentElement.scrollTop) > 100) {
                hideFooter();
            }
            // Show footer on upward swipe or if near top
            else if (touchDistance < -50 || (window.pageYOffset || document.documentElement.scrollTop) <= 100) {
                showFooter();
            }
        }, { passive: true });
    });
    
    // Global function to adjust footer position
    function adjustFooterPosition() {
        const footer = document.getElementById('mainFooter');
        const mainContent = document.getElementById('mainContent');
        const sidebar = document.getElementById('sidebar');
        
        if (!footer || !mainContent) return;
        
        const footerHeight = footer.offsetHeight;
        const isFooterVisible = !footer.style.transform.includes('100%');
        
        if (isFooterVisible) {
            mainContent.style.paddingBottom = footerHeight + 20 + 'px';
        } else {
            mainContent.style.paddingBottom = '20px';
        }
        
        // On mobile, footer should always take full width
        if (window.innerWidth <= 1280) {
            footer.style.left = '0';
            footer.style.right = '0';
            footer.style.width = '100%';
            return;
        }
        
        // Adjust footer position based on sidebar state for desktop
        if (sidebar) {
            if (sidebar.classList.contains('collapsed')) {
                // Sidebar collapsed on desktop
                footer.style.left = '80px';
                footer.style.right = '0';
                footer.style.width = 'calc(100% - 80px)';
            } else {
                // Sidebar expanded on desktop
                footer.style.left = '280px';
                footer.style.right = '0';
                footer.style.width = 'calc(100% - 280px)';
            }
        }
    }
    
    // Toggle sidebar on mobile
    const toggleSidebarMobile = document.getElementById('toggleSidebarMobile');
    if (toggleSidebarMobile) {
        toggleSidebarMobile.addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            
            // If we're opening the sidebar on mobile, ensure it's not in collapsed state
            if (sidebar.classList.contains('active')) {
                sidebar.classList.remove('collapsed');
                document.getElementById('mainContent').classList.remove('collapsed');
            }
            
            // Adjust footer position
            adjustFooterPosition();
        });
    }
    
    // Close sidebar when clicking overlay
    const overlay = document.getElementById('overlay');
    if (overlay) {
        overlay.addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('active');
            this.classList.remove('active');
            adjustFooterPosition();
        });
    }
    
    // Toggle sidebar on desktop
    const toggleSidebarDesktop = document.getElementById('toggleSidebarDesktop');
    if (toggleSidebarDesktop) {
        toggleSidebarDesktop.addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('mainContent');
            
            // Check if we're on mobile and sidebar is open
            const isMobile = window.innerWidth <= 1280;
            const isSidebarActive = sidebar.classList.contains('active');
            
            if (isMobile && isSidebarActive) {
                // On mobile, just close the sidebar when the toggle is clicked
                sidebar.classList.remove('active');
                document.getElementById('overlay').classList.remove('active');
            } else {
                // On desktop, toggle the collapsed state
                sidebar.classList.toggle('collapsed');
                content.classList.toggle('collapsed');
                
                // Save state to localStorage
                if (sidebar.classList.contains('collapsed')) {
                    localStorage.setItem('sidebarCollapsed', 'true');
                } else {
                    localStorage.setItem('sidebarCollapsed', 'false');
                }
            }
            
            // Adjust footer position
            adjustFooterPosition();
        });
    }
    
    // Check for saved sidebar state
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('mainContent');
        if (sidebar && content) {
            sidebar.classList.add('collapsed');
            content.classList.add('collapsed');
            
            // Adjust footer position after a brief delay to allow DOM to update
            setTimeout(adjustFooterPosition, 50);
        }
    }
    
    // Toggle user dropdown
    const userMenuButton = document.getElementById('userMenuButton');
    if (userMenuButton) {
        userMenuButton.addEventListener('click', function() {
            const userDropdown = document.getElementById('userDropdown');
            if (userDropdown) {
                userDropdown.classList.toggle('show');
            }
        });
    }
    
    // Close dropdown when clicking outside
    window.addEventListener('click', function(e) {
        const userDropdown = document.getElementById('userDropdown');
        const userMenuButton = document.getElementById('userMenuButton');
        
        if (userDropdown && userMenuButton && 
            !e.target.closest('#userMenuButton') && 
            !e.target.closest('.dropdown-menu')) {
            userDropdown.classList.remove('show');
        }
    });
    
    // Theme toggle functionality
    const themeToggle = document.getElementById('themeToggle');
    const body = document.body;
    
    if (themeToggle) {
        // Check for saved theme preference or respect OS preference
        if (localStorage.getItem('theme') === 'dark' || 
            (window.matchMedia('(prefers-color-scheme: dark)').matches && !localStorage.getItem('theme'))) {
            body.setAttribute('data-theme', 'dark');
            themeToggle.checked = true;
        }
        
        themeToggle.addEventListener('change', function() {
            if (this.checked) {
                body.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
            } else {
                body.setAttribute('data-theme', 'light');
                localStorage.setItem('theme', 'light');
            }
        });
    }
    
    // Sidebar theme functionality
    const themeOptions = document.querySelectorAll('.theme-option');
    
    // Check for saved sidebar theme
    const savedSidebarTheme = localStorage.getItem('sidebarTheme');
    if (savedSidebarTheme) {
        body.setAttribute('data-sidebar-theme', savedSidebarTheme);
        // Update active state
        themeOptions.forEach(option => {
            if (option.getAttribute('data-theme') === savedSidebarTheme) {
                option.classList.add('active');
            } else {
                option.classList.remove('active');
            }
        });
    }
    
    // Add click event to theme options
    themeOptions.forEach(option => {
        option.addEventListener('click', function() {
            const theme = this.getAttribute('data-theme');
            body.setAttribute('data-sidebar-theme', theme);
            localStorage.setItem('sidebarTheme', theme);
            
            // Update active state
            themeOptions.forEach(opt => {
                if (opt === option) {
                    opt.classList.add('active');
                } else {
                    opt.classList.remove('active');
                }
            });
        });
    });
    
    // Handle window resize for better responsiveness
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('mainContent');
        const overlay = document.getElementById('overlay');
        
        // Adjust footer position
        adjustFooterPosition();
        
        // If we resize to desktop width, ensure sidebar is visible
        if (window.innerWidth > 1280) {
            if (sidebar) sidebar.classList.remove('active');
            if (overlay) overlay.classList.remove('active');
            
            // Restore collapsed state if it was set
            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                if (sidebar) sidebar.classList.add('collapsed');
                if (content) content.classList.add('collapsed');
            } else {
                if (sidebar) sidebar.classList.remove('collapsed');
                if (content) content.classList.remove('collapsed');
            }
        } else {
            // On mobile, ensure sidebar is hidden by default
            if (sidebar) sidebar.classList.remove('active');
            if (overlay) overlay.classList.remove('active');
            
            // On mobile, we don't want the collapsed state
            if (sidebar) sidebar.classList.remove('collapsed');
            if (content) content.classList.remove('collapsed');
        }
        
        // Adjust chart sizes on resize
        if (window.innerWidth < 768) {
            document.querySelectorAll('.chart-container').forEach(container => {
                container.style.height = '200px';
            });
        } else if (window.innerWidth < 1024) {
            document.querySelectorAll('.chart-container').forEach(container => {
                container.style.height = '250px';
            });
        } else {
            document.querySelectorAll('.chart-container').forEach(container => {
                container.style.height = '350px';
            });
        }
    });
</script>