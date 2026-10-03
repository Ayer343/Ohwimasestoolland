/**
 * UI Controller Module
 * Handles UI interactions like mobile menu, theme toggle, scroll effects, and animations
 */
window.UIController = {
    init: function() {
        this.setupMobileMenu();
        this.setupScrollEffects();
        this.setupThemeToggle();
        this.setupScrollAnimations();
        this.setupSmoothScroll();
        this.setupModalHandlers();
        this.enableTransitions();
    },
    
    setupMobileMenu: function() {
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mainNav = document.getElementById('mainNav');
        const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');
        
        const toggleMobileMenu = () => {
            if (!mainNav || !mobileMenuOverlay) return;
            
            const isActive = mainNav.classList.toggle('active');
            mobileMenuOverlay.classList.toggle('active');
            document.body.style.overflow = isActive ? 'hidden' : '';
            
            if (mobileMenuBtn) {
                const icon = mobileMenuBtn.querySelector('i');
                if (icon) {
                    icon.className = isActive ? 'fas fa-times' : 'fas fa-bars';
                }
            }
        };
        
        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', toggleMobileMenu);
        }
        
        if (mobileMenuOverlay) {
            mobileMenuOverlay.addEventListener('click', toggleMobileMenu);
        }
        
        // Close mobile menu when clicking on nav links
        document.querySelectorAll('nav a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    toggleMobileMenu();
                }
            });
        });
        
        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768 && mainNav && mainNav.classList.contains('active')) {
                toggleMobileMenu();
            }
        });
    },
    
    setupScrollEffects: function() {
        const header = document.querySelector('header');
        if (!header) return;
        
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                header.style.boxShadow = 'var(--shadow-md)';
                header.style.padding = '0.5rem 0';
            } else {
                header.style.boxShadow = 'var(--shadow-sm)';
                header.style.padding = '1rem 0';
            }
        });
    },
    
    setupThemeToggle: function() {
        const themeToggle = document.createElement('button');
        themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
        themeToggle.className = 'btn btn-outline';
        themeToggle.style.position = 'fixed';
        themeToggle.style.bottom = '20px';
        themeToggle.style.right = '20px';
        themeToggle.style.zIndex = '1000';
        themeToggle.style.padding = '0.75rem';
        themeToggle.style.borderRadius = '50%';
        themeToggle.style.width = '45px';
        themeToggle.style.height = '45px';
        themeToggle.title = 'Toggle theme';
        
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        themeToggle.innerHTML = savedTheme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
        
        themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            themeToggle.innerHTML = newTheme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
            localStorage.setItem('theme', newTheme);
        });
        
        document.body.appendChild(themeToggle);
    },
    
    setupScrollAnimations: function() {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);
        
        document.querySelectorAll('.card, .feature-card, .payment-method').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            observer.observe(el);
        });
    },
    
    setupSmoothScroll: function() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href !== '#') {
                    const target = document.querySelector(href);
                    if (target) {
                        e.preventDefault();
                        window.scrollTo({
                            top: target.offsetTop - 80,
                            behavior: 'smooth'
                        });
                    }
                }
            });
        });
    },
    
    setupModalHandlers: function() {
        const modal = document.getElementById('landlordRegistrationModal');
        const registrationBtn = document.getElementById('landlordRegistrationBtn');
        const closeBtn = document.getElementById('closeModalBtn');
        const cancelBtn = document.getElementById('cancelModalBtn');
        
        const closeModal = () => {
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        };
        
        if (registrationBtn) {
            registrationBtn.addEventListener('click', (e) => {
                e.preventDefault();
                if (modal) {
                    modal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                    
                    // Reset to vacant land by default
                    if (window.RegistrationManager) {
                        window.RegistrationManager.setActiveOption('vacant');
                    }
                    
                    // Initialize has_tenants as 'no' in tenant hidden inputs
                    if (window.TenantManager) {
                        window.TenantManager.clearHiddenInputs();
                    }
                }
            });
        }
        
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeModal();
                }
            });
        }
        
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal && modal.classList.contains('active')) {
                closeModal();
            }
        });
    },
    
    enableTransitions: function() {
        setTimeout(() => {
            document.documentElement.classList.remove('theme-loading');
            document.body.classList.remove('theme-loading');
            
            const style = document.createElement('style');
            style.textContent = `
                .theme-loading * { transition: none !important; }
                body:not(.theme-loading) * { transition: all 0.2s ease; }
            `;
            document.head.appendChild(style);
        }, 100);
    }
};