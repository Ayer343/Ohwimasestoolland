<script>
(function () {
    'use strict';

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

        if (sidebar) sidebar.classList.toggle('collapsed', collapsed);
        if (mainContent) mainContent.classList.toggle('collapsed', collapsed);
        if (mainContentAlt && !mainContent) mainContentAlt.classList.toggle('collapsed', collapsed);

        if (saveToStorage) localStorage.setItem('sidebarCollapsed', collapsed);

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
    // THEME MANAGER  (plain JS, no Alpine dependency)
    // =========================================================================
    /*
     * Responsibilities:
     *   • Apply appearance / sidebar theme / custom colors to <html> tokens
     *   • Bind the #themeSettingsModal controls (buttons + color inputs)
     *   • Debounce POSTs to /theme/update and /theme/update-colors
     *   • Drive #themeSaveIndicator, aria-checked, swatches, hex labels
     *   • Update the live preview frame in real time
     *   • Expose window.ThemeManager for other scripts
     */

    const THEME = {
        appearance: 'system',        // light | dark | system
        sidebarTheme: 'default',     // default | dark | light | blue | green | custom
        customColors: {
            primary:   '#7267f0',
            secondary: '#6258e0',
            accent:    '#f59e0b'
        }
    };

    const STORAGE_KEY = 'app_theme_v2';
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const ROUTES = window.__THEME_ROUTES__ || {
        update:       '/theme/update',
        updateColors: '/theme/update-colors',
        reset:        '/theme/reset',
        resetColors:  '/theme/reset-colors'
    };

    // Seed from server-rendered config if present.
    if (window.__THEME__ && typeof window.__THEME__ === 'object') {
        if (window.__THEME__.appearance)   THEME.appearance = window.__THEME__.appearance;
        if (window.__THEME__.sidebarTheme) THEME.sidebarTheme = window.__THEME__.sidebarTheme;
        if (window.__THEME__.customColors) Object.assign(THEME.customColors, window.__THEME__.customColors);
    }

    // Fall back to localStorage.
    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
        if (saved) {
            if (saved.appearance)   THEME.appearance = saved.appearance;
            if (saved.sidebarTheme) THEME.sidebarTheme = saved.sidebarTheme;
            if (saved.customColors) Object.assign(THEME.customColors, saved.customColors);
        }
    } catch (_) { /* ignore */ }

    // ---- Helpers ----
    function persist() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({
                appearance: THEME.appearance,
                sidebarTheme: THEME.sidebarTheme,
                customColors: THEME.customColors
            }));
        } catch (_) {}
    }

    function hexToRgb(hex) {
        const h = (hex || '').replace('#', '');
        if (h.length !== 6) return { r: 114, g: 103, b: 240 };
        return {
            r: parseInt(h.slice(0, 2), 16),
            g: parseInt(h.slice(2, 4), 16),
            b: parseInt(h.slice(4, 6), 16)
        };
    }

    function contrastText(hex) {
        const { r, g, b } = hexToRgb(hex);
        const lum = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
        return lum > 0.6 ? '#1e293b' : '#ffffff';
    }

    function withAlpha(hex, alpha) {
        const { r, g, b } = hexToRgb(hex);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    function debounce(fn, wait) {
        let t;
        return function () {
            clearTimeout(t);
            const args = arguments;
            t = setTimeout(() => fn.apply(this, args), wait);
        };
    }

    // ---- Apply ----
    function applyAppearance() {
        const html = document.documentElement;
        let resolved = THEME.appearance;

        if (resolved === 'system') {
            resolved = window.matchMedia('(prefers-color-scheme: dark)').matches
                ? 'dark' : 'light';
        }
        html.setAttribute('data-theme', resolved);
        html.classList.toggle('dark',  resolved === 'dark');
        html.classList.toggle('light', resolved === 'light');

        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) meta.content = resolved === 'dark' ? '#1e1e2d' : '#ffffff';
    }

    function applySidebarTheme() {
        const html = document.documentElement;
        html.setAttribute('data-sidebar-theme', THEME.sidebarTheme);

        // Clear inline overrides from a previous custom apply.
        [
            '--sidebar-bg', '--sidebar-text', '--sidebar-text-muted',
            '--sidebar-icon', '--sidebar-logo', '--sidebar-logo-muted',
            '--sidebar-hover', '--sidebar-active', '--sidebar-border',
            '--sidebar-shadow'
        ].forEach(p => html.style.removeProperty(p));

        if (THEME.sidebarTheme !== 'custom') return;

        const p = THEME.customColors.primary   || '#7267f0';
        const s = THEME.customColors.secondary || '#6258e0';
        const textColor = contrastText(p);
        const isLightText = textColor === '#ffffff';

        html.style.setProperty('--sidebar-bg',
            `linear-gradient(180deg, ${p} 0%, ${s} 100%)`);
        html.style.setProperty('--sidebar-text', textColor);
        html.style.setProperty('--sidebar-text-muted',
            isLightText ? 'rgba(255,255,255,0.72)' : 'rgba(30,41,59,0.68)');
        html.style.setProperty('--sidebar-icon', textColor);
        html.style.setProperty('--sidebar-logo', textColor);
        html.style.setProperty('--sidebar-logo-muted',
            isLightText ? 'rgba(255,255,255,0.72)' : 'rgba(30,41,59,0.68)');
        html.style.setProperty('--sidebar-hover',
            isLightText ? 'rgba(255,255,255,0.12)' : 'rgba(0,0,0,0.06)');
        html.style.setProperty('--sidebar-active',
            isLightText ? 'rgba(255,255,255,0.20)' : 'rgba(0,0,0,0.10)');
        html.style.setProperty('--sidebar-border', 'transparent');
        html.style.setProperty('--sidebar-shadow', `0 0 20px ${withAlpha(p, 0.30)}`);
    }

    function applyAll() {
        applyAppearance();
        applySidebarTheme();
        syncModalControls();
        updateLivePreview();
    }

    // ---- Modal sync ----
    function syncModalControls() {
        document.querySelectorAll('.theme-appearance-btn').forEach(btn => {
            const isActive = btn.dataset.appearance === THEME.appearance;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-checked', isActive ? 'true' : 'false');
        });

        document.querySelectorAll('.sidebar-theme-btn').forEach(btn => {
            const isActive = btn.dataset.sidebarTheme === THEME.sidebarTheme;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-checked', isActive ? 'true' : 'false');
        });

        const map = [
            { key: 'primary',   input: 'customColorPrimary',   swatch: 'customColorPrimarySwatch',   label: 'customColorPrimaryValue' },
            { key: 'secondary', input: 'customColorSecondary', swatch: 'customColorSecondarySwatch', label: 'customColorSecondaryValue' },
            { key: 'accent',    input: 'customColorAccent',    swatch: 'customColorAccentSwatch',    label: 'customColorAccentValue' }
        ];
        map.forEach(({ key, input, swatch, label }) => {
            const value = THEME.customColors[key] || '#000000';
            const inputEl  = document.getElementById(input);
            const swatchEl = document.getElementById(swatch);
            const labelEl  = document.getElementById(label);
            if (inputEl)  inputEl.value = value;
            if (swatchEl) swatchEl.style.backgroundColor = value;
            if (labelEl)  labelEl.textContent = value.toUpperCase();
        });

        const left  = document.querySelector('.sidebar-swatch-half--left');
        const right = document.querySelector('.sidebar-swatch-half--right');
        if (left)  left.style.backgroundColor  = THEME.customColors.primary;
        if (right) right.style.backgroundColor = THEME.customColors.secondary;
    }

    function updateLivePreview() {
        const sidebar = document.getElementById('themePreviewSidebar');
        if (!sidebar) return;

        if (THEME.sidebarTheme === 'custom') {
            const p = THEME.customColors.primary;
            const s = THEME.customColors.secondary;
            sidebar.style.background = `linear-gradient(180deg, ${p} 0%, ${s} 100%)`;
        } else {
            const map = {
                default: 'linear-gradient(180deg, #7267f0 0%, #6258e0 100%)',
                dark:    'linear-gradient(180deg, #232933 0%, #1e2229 100%)',
                light:   'linear-gradient(180deg, #ffffff 0%, #f5f7f9 100%)',
                blue:    'linear-gradient(180deg, #1a56db 0%, #1e429f 100%)',
                green:   'linear-gradient(180deg, #057a55 0%, #0a5c36 100%)'
            };
            sidebar.style.background = map[THEME.sidebarTheme] || map.default;
        }
    }

    // ---- Save indicator ----
    function showSaving() {
        document.getElementById('themeSaveIndicator')?.classList.remove('hidden');
    }
    function hideSaving() {
        document.getElementById('themeSaveIndicator')?.classList.add('hidden');
    }

    // ---- Network ----
    async function postJSON(url, payload) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': CSRF
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.success === false) {
            throw new Error(data.message || `Request failed (${res.status})`);
        }
        return data;
    }

    const saveSettings = debounce(async function () {
        showSaving();
        try {
            await postJSON(ROUTES.update, {
                appearance: THEME.appearance,
                sidebar_theme: THEME.sidebarTheme
            });
        } catch (err) {
            console.error('[Theme] settings save failed:', err);
        } finally {
            hideSaving();
        }
    }, 250);

    const saveColors = debounce(async function () {
        showSaving();
        try {
            await postJSON(ROUTES.updateColors, {
                colors: {
                    primary:   THEME.customColors.primary,
                    secondary: THEME.customColors.secondary,
                    accent:    THEME.customColors.accent
                }
            });
        } catch (err) {
            console.error('[Theme] colors save failed:', err);
        } finally {
            hideSaving();
        }
    }, 350);

    // ---- Toast ----
    function themeToast(message, type = 'info') {
        if (typeof window.showNotification === 'function') {
            window.showNotification(message, type);
            return;
        }
        const colors = { success: '#22c55e', error: '#ef4444', info: '#3b82f6' };
        const div = document.createElement('div');
        div.textContent = message;
        div.style.cssText = `
            position: fixed; bottom: 20px; right: 20px;
            padding: 12px 24px; border-radius: 8px;
            background: ${colors[type] || colors.info};
            color: #fff; z-index: 99999; font-size: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            max-width: 400px;`;
        document.body.appendChild(div);
        setTimeout(() => {
            div.style.transition = 'opacity .3s';
            div.style.opacity = '0';
            setTimeout(() => div.remove(), 300);
        }, 2500);
    }

    // ---- Bind modal ----
    function bindThemeModal() {
        const modal = document.getElementById('themeSettingsModal');
        if (!modal) return;
        if (modal.dataset.themeBound === '1') return;
        modal.dataset.themeBound = '1';

        // Appearance
        modal.querySelectorAll('.theme-appearance-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                THEME.appearance = btn.dataset.appearance;
                persist();
                applyAppearance();
                syncModalControls();
                saveSettings();
            });
        });

        // Sidebar theme
        modal.querySelectorAll('.sidebar-theme-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                THEME.sidebarTheme = btn.dataset.sidebarTheme;
                persist();
                applySidebarTheme();
                syncModalControls();
                updateLivePreview();
                saveSettings();
                if (THEME.sidebarTheme === 'custom') saveColors();
            });
        });

        // Color inputs
        [
            { id: 'customColorPrimary',   key: 'primary'   },
            { id: 'customColorSecondary', key: 'secondary' },
            { id: 'customColorAccent',    key: 'accent'    }
        ].forEach(({ id, key }) => {
            const input = document.getElementById(id);
            if (!input) return;
            input.addEventListener('input', () => {
                const value = input.value;
                THEME.customColors[key] = value;

                const swatch = document.getElementById(id + 'Swatch');
                const label  = document.getElementById(id + 'Value');
                if (swatch) swatch.style.backgroundColor = value;
                if (label)  label.textContent = value.toUpperCase();

                if (THEME.sidebarTheme === 'custom') {
                    applySidebarTheme();
                    updateLivePreview();
                    syncModalControls();
                    persist();
                    saveColors();
                }
            });
        });

        // Reset colors
        document.getElementById('resetColorsBtn')?.addEventListener('click', async (e) => {
            e.preventDefault();
            try {
                const data = await postJSON(ROUTES.resetColors, {});
                const colors = data?.data?.colors || data?.colors || {
                    primary: '#7267f0', secondary: '#6258e0', accent: '#f59e0b'
                };
                THEME.customColors = {
                    primary:   colors.primary   || '#7267f0',
                    secondary: colors.secondary || '#6258e0',
                    accent:    colors.accent    || '#f59e0b'
                };
                persist();
                syncModalControls();
                if (THEME.sidebarTheme === 'custom') {
                    applySidebarTheme();
                    updateLivePreview();
                }
                themeToast('Colors reset to default', 'success');
            } catch (err) {
                console.error('[Theme] reset colors failed:', err);
                themeToast('Failed to reset colors', 'error');
            }
        });

        // Reset all
        document.getElementById('resetThemeBtn')?.addEventListener('click', async (e) => {
            e.preventDefault();
            if (!confirm('Reset all theme settings to default?')) return;
            try {
                const data = await postJSON(ROUTES.reset, {});
                const settings = data?.data?.settings || data?.settings || {};
                const colors   = data?.data?.colors   || data?.colors   || {};

                THEME.appearance   = settings.appearance   || 'system';
                THEME.sidebarTheme = settings.sidebar_theme || 'default';
                THEME.customColors = {
                    primary:   colors.primary   || '#7267f0',
                    secondary: colors.secondary || '#6258e0',
                    accent:    colors.accent    || '#f59e0b'
                };
                persist();
                applyAll();
                themeToast('Theme reset to default', 'success');
            } catch (err) {
                console.error('[Theme] reset failed:', err);
                themeToast('Failed to reset theme', 'error');
            }
        });

        // Close handlers
        const close = () => {
            modal.classList.add('hidden');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        };
        document.getElementById('closeThemeModal')?.addEventListener('click', close);
        document.getElementById('closeThemeModalBtn')?.addEventListener('click', close);
        modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) close();
        });

        // Open via gear
        document.getElementById('themeSettingsButton')?.addEventListener('click', (e) => {
            e.preventDefault();
            modal.classList.remove('hidden');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            syncModalControls();
        });

        // Expose close for the global Escape handler
        window.__closeThemeModal = close;
    }

    // ---- OS theme reactivity ----
    window.matchMedia('(prefers-color-scheme: dark)')
        .addEventListener('change', () => {
            if (THEME.appearance === 'system') applyAppearance();
        });

    // ---- Public API ----
    window.ThemeManager = {
        get state() { return JSON.parse(JSON.stringify(THEME)); },
        apply: applyAll,
        setAppearance(v) { THEME.appearance = v; persist(); applyAll(); saveSettings(); },
        setSidebarTheme(v) { THEME.sidebarTheme = v; persist(); applyAll(); saveSettings(); },
        setCustomColor(key, value) {
            THEME.customColors[key] = value;
            persist();
            applySidebarTheme();
            syncModalControls();
            updateLivePreview();
            saveColors();
        },
        toast: themeToast
    };

    // =========================================================================
    // DOM READY - MAIN INITIALIZATION
    // =========================================================================

    document.addEventListener('DOMContentLoaded', function () {

        // ---- Sidebar toggle ----
        const toggleSidebarBtn = document.getElementById('toggleSidebar');

        if (localStorage.getItem('sidebarCollapsed') !== null && toggleSidebarBtn) {
            toggleSidebarBtn.classList.add('interacted');
        }

        if (toggleSidebarBtn) {
            toggleSidebarBtn.addEventListener('click', function (e) {
                e.preventDefault();
                this.classList.add('interacted');
                const isCollapsed = localStorage.getItem('sidebarCollapsed') !== 'true';
                toggleSidebar(isCollapsed, true);
                if (window.navigator && window.navigator.vibrate) window.navigator.vibrate(50);
            });
            toggleSidebarBtn.addEventListener('mouseenter', function () {
                this.classList.add('interacted');
            });
        }

        // ---- Gear hover animation ----
        const themeSettingsButton = document.getElementById('themeSettingsButton');
        const settingsIcon = document.getElementById('settingsIcon');

        if (settingsIcon) {
            settingsIcon.classList.add('theme-settings-gear');
            if (themeSettingsButton) {
                themeSettingsButton.addEventListener('mouseenter', function () {
                    settingsIcon.classList.add('gear-spin-fast');
                });
                themeSettingsButton.addEventListener('mouseleave', function () {
                    settingsIcon.classList.remove('gear-spin-fast');
                });
            }
        }

        // ---- Theme manager boot + bind ----
        applyAll();
        bindThemeModal();

        // ---- Mobile menu ----
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const overlay = document.getElementById('overlay');
        const sidebar = document.querySelector('.sidebar');

        if (mobileMenuBtn && overlay && sidebar) {
            mobileMenuBtn.addEventListener('click', function () {
                sidebar.classList.add('active');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
            overlay.addEventListener('click', function () {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            });
        }

        // ---- User dropdown ----
        const userDropdown = document.getElementById('user-dropdown');
        const dropdownMenu = document.getElementById('dropdown-menu');

        if (userDropdown && dropdownMenu) {
            userDropdown.addEventListener('click', function (e) {
                e.stopPropagation();
                dropdownMenu.classList.toggle('show');
            });
            document.addEventListener('click', function (e) {
                if (dropdownMenu.classList.contains('show') && !userDropdown.contains(e.target)) {
                    dropdownMenu.classList.remove('show');
                }
            });
        }

        // ---- Tabs ----
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
                    if (tab) switchTab(tab);
                }
            }
            checkHash();
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const target = tab.getAttribute('data-tab-target');
                    if (target) window.location.hash = target;
                });
            });
        }

        // ---- Form focus styles ----
        document.querySelectorAll('input, select, textarea').forEach(element => {
            element.addEventListener('focus', function () {
                this.style.boxShadow = '0 0 0 2px var(--primary)';
                this.style.borderColor = 'var(--primary)';
            });
            element.addEventListener('blur', function () {
                this.style.boxShadow = '';
                this.style.borderColor = 'var(--border-color)';
            });
        });

        // ---- Success message auto-hide ----
        const successMessage = document.querySelector('.bg-green-100');
        if (successMessage) {
            setTimeout(() => {
                successMessage.style.transition = 'opacity 0.5s ease';
                successMessage.style.opacity = '0';
                setTimeout(() => { successMessage.style.display = 'none'; }, 500);
            }, 5000);
        }

        // ---- Card entrance animations ----
        const observerOptions = { threshold: 0.1, rootMargin: '0px 0px -50px 0px' };
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fadeInUp');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);
        document.querySelectorAll('.card').forEach(card => observer.observe(card));

        // ---- Sidebar interference fix ----
        function fixInterference() {
            const currentState = localStorage.getItem('sidebarCollapsed') === 'true';
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                const hasCollapsedClass = sidebar.classList.contains('collapsed');
                if (hasCollapsedClass !== currentState) toggleSidebar(currentState, false);
            }
        }
        setTimeout(fixInterference, 1000);

        const originalFetch = window.fetch;
        if (originalFetch) {
            window.fetch = function () {
                return originalFetch.apply(this, arguments)
                    .then(response => {
                        setTimeout(fixInterference, 100);
                        return response;
                    })
                    .catch(error => { throw error; });
            };
        }

        // ---- Pulse removal ----
        let scrollTimeout;
        window.addEventListener('scroll', function () {
            if (toggleSidebarBtn && !toggleSidebarBtn.classList.contains('interacted')) {
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(function () {
                    toggleSidebarBtn.classList.add('interacted');
                }, 500);
            }
        }, { passive: true });

        const sidebarElement = document.querySelector('.sidebar');
        if (sidebarElement && toggleSidebarBtn) {
            sidebarElement.addEventListener('mouseenter', function () {
                if (!toggleSidebarBtn.classList.contains('interacted')) {
                    toggleSidebarBtn.classList.add('interacted');
                }
            });
        }
        setTimeout(function () {
            if (toggleSidebarBtn && !toggleSidebarBtn.classList.contains('interacted')) {
                toggleSidebarBtn.classList.add('interacted');
            }
        }, 10000);

        // ---- Modal management (unchanged) ----
        function wireModal(btnId, modalId, closeIds) {
            const btn = document.getElementById(btnId);
            const modal = document.getElementById(modalId);
            if (!btn || !modal) return;
            const open = () => {
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            };
            const close = () => {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            };
            btn.addEventListener('click', open);
            closeIds.forEach(id => {
                document.getElementById(id)?.addEventListener('click', close);
            });
            modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
            window['close' + modalId.charAt(0).toUpperCase() + modalId.slice(1)] = close;
        }

        wireModal('emailManagementBtn',      'emailManagementModal',      ['closeEmailModal', 'cancelEmailModal']);
        wireModal('smsManagementBtn',        'smsManagementModal',        ['closeSmsModal', 'cancelSmsModal']);
        wireModal('whatsappManagementBtn',   'whatsappManagementModal',   ['closeWhatsAppModal', 'cancelWhatsAppModal']);
        wireModal('invoiceManagementBtn',    'invoiceManagementModal',    ['closeInvoiceModal', 'cancelInvoiceModal']);
        wireModal('administrativeToolsBtn',  'administrativeToolsModal',  ['closeAdminToolsModal', 'cancelAdminToolsModal']);
        wireModal('billingManagementBtn',    'billingManagementModal',    ['closeBillingModal', 'cancelBillingModal']);

        // ---- Notifications ----
        window.loadNotifications = function () {
            const list = document.getElementById('notificationList');
            if (!list) return;
            list.innerHTML = '<div class="p-4 text-center" style="color: var(--text-secondary);"><i class="fas fa-spinner fa-spin mr-2"></i>Loading notifications...</div>';
            fetch('/notifications/ajax', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(data => {
                    list.innerHTML = (data.success && data.html)
                        ? data.html
                        : '<div class="p-4 text-center" style="color: var(--text-secondary);">No notifications found</div>';
                })
                .catch(() => {
                    list.innerHTML = '<div class="p-4 text-center" style="color: var(--text-secondary);">Failed to load notifications</div>';
                });
        };

        window.markAllAsRead = function () {
            fetch('/notifications/mark-all-read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF
                }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showNotification('All notifications marked as read', 'success');
                    document.querySelectorAll('.notification-item.unread').forEach(i => i.classList.remove('unread'));
                    const badge = document.querySelector('.notification-dot');
                    if (badge) badge.style.display = 'none';
                }
            })
            .catch(() => showNotification('Failed to mark notifications as read', 'error'));
        };

        // ---- Search ----
        window.openSearchModal = function () {
            const modal = document.getElementById('searchModal');
            if (!modal) return;
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            setTimeout(() => document.getElementById('globalSearchInput')?.focus(), 100);
        };
        window.closeSearchModal = function () {
            const modal = document.getElementById('searchModal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        };

        window.performSearch = function () {
            const input = document.getElementById('globalSearchInput');
            const category = document.getElementById('searchCategory');
            if (!input || !input.value.trim()) {
                showNotification('Please enter a search term', 'warning');
                return;
            }
            const term = encodeURIComponent(input.value.trim());
            const categoryValue = category ? category.value : 'all';
            let url = `/search?q=${term}`;
            if (categoryValue !== 'all') url += `&category=${categoryValue}`;
            saveRecentSearch(input.value.trim());
            window.location.href = url;
        };

        function saveRecentSearch(term) {
            let recentSearches = JSON.parse(localStorage.getItem('recentSearches') || '[]');
            recentSearches = recentSearches.filter(s => s !== term);
            recentSearches.unshift(term);
            if (recentSearches.length > 10) recentSearches = recentSearches.slice(0, 10);
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

        document.getElementById('globalSearchInput')?.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch();
            }
        });
        document.getElementById('performSearch')?.addEventListener('click', performSearch);
        document.getElementById('cancelSearch')?.addEventListener('click', closeSearchModal);
        document.getElementById('clearSearch')?.addEventListener('click', function () {
            const input = document.getElementById('globalSearchInput');
            if (input) { input.value = ''; input.focus(); }
        });
        document.getElementById('clearRecentSearchesBtn')?.addEventListener('click', function () {
            if (confirm('Clear all recent searches?')) {
                localStorage.removeItem('recentSearches');
                renderRecentSearches();
                showNotification('Recent searches cleared', 'info');
            }
        });
        document.getElementById('advancedSearchBtn')?.addEventListener('click', openSearchModal);

        const quickSearch = document.getElementById('quickSearchInput');
        if (quickSearch) {
            quickSearch.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const term = encodeURIComponent(this.value.trim());
                    if (term) window.location.href = `/search?q=${term}`;
                }
            });
            quickSearch.addEventListener('focus', function () { this.select(); });
        }
        document.getElementById('mobileSearchBtn')?.addEventListener('click', openSearchModal);

        const searchModal = document.getElementById('searchModal');
        if (searchModal) {
            searchModal.addEventListener('click', function (e) {
                if (e.target === searchModal) closeSearchModal();
            });
        }

        // ---- Email management ----
        window.syncAllEmails = function () {
            const btn = document.querySelector('.quick-action-btn .fa-sync')?.closest('.quick-action-btn');
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...'; }
            fetch('/email-accounts/sync-all', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showNotification('All emails synced successfully!', 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else showNotification(data.message || 'Failed to sync emails', 'error');
            })
            .catch(() => showNotification('An error occurred while syncing emails', 'error'))
            .finally(() => {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-sync text-white text-xs"></i>'; }
            });
        };

        window.syncAccount = function (accountId) {
            const btn = event?.target?.closest('button');
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }
            fetch(`/email-accounts/${accountId}/sync`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showNotification('Account synced successfully!', 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else showNotification(data.message || 'Failed to sync account', 'error');
            })
            .catch(() => showNotification('An error occurred while syncing account', 'error'))
            .finally(() => {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-sync text-xs"></i>'; }
            });
        };

        window.setPrimaryAccount = function (accountId) {
            if (!confirm('Set this as your primary email account?')) return;
            fetch(`/email-accounts/${accountId}/set-primary`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showNotification('Primary account set successfully!', 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else showNotification(data.message || 'Failed to set primary account', 'error');
            })
            .catch(() => showNotification('An error occurred while setting primary account', 'error'));
        };

        window.deleteAccount = function (accountId, email) {
            if (!confirm(`Delete email account "${email}"? This action cannot be undone.`)) return;
            fetch(`/email-accounts/${accountId}`, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showNotification('Account deleted successfully!', 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else showNotification(data.message || 'Failed to delete account', 'error');
            })
            .catch(() => showNotification('An error occurred while deleting account', 'error'));
        };

        // ---- SMS management ----
        window.openSmsCompose = function () { window.location.href = '/sms/compose'; };
        window.testSmsConnection = function () {
            const btn = event?.target?.closest('button');
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...'; }
            fetch('/sms/test-connection', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) showNotification('SMS connection test successful!', 'success');
                else showNotification(data.message || 'SMS connection test failed', 'error');
            })
            .catch(() => showNotification('An error occurred while testing SMS connection', 'error'))
            .finally(() => {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-wifi text-white text-xs"></i>'; }
            });
        };
        window.refreshSmsStatus = function () { window.location.reload(); };

        // ---- WhatsApp management ----
        window.openWhatsAppCompose = function () { window.location.href = '/admin/whatsapp/compose'; };
        window.testWhatsAppConnection = function () {
            const btn = event?.target?.closest('button');
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...'; }
            fetch('/admin/whatsapp/test-connection', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) showNotification('WhatsApp connection test successful!', 'success');
                else showNotification(data.message || 'WhatsApp connection test failed', 'error');
            })
            .catch(() => showNotification('An error occurred while testing WhatsApp connection', 'error'))
            .finally(() => {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-wifi text-white text-xs"></i>'; }
            });
        };
        window.refreshWhatsAppStatus = function () { window.location.reload(); };

        // ---- Notification helper ----
        window.showNotification = function (message, type = 'info') {
            const colors = {
                success: '#22c55e',
                error:   '#ef4444',
                warning: '#f59e0b',
                info:    '#3b82f6'
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
                max-width: 400px;`;
            document.body.appendChild(div);
            setTimeout(() => {
                div.style.opacity = '0';
                div.style.transition = 'opacity 0.3s ease';
                setTimeout(() => div.remove(), 300);
            }, 3000);
        };

        // ---- Scroll to top ----
        const scrollToTopBtn = document.getElementById('scrollToTop');
        if (scrollToTopBtn) {
            window.addEventListener('scroll', function () {
                if (window.scrollY > 400) {
                    scrollToTopBtn.style.opacity = '1';
                    scrollToTopBtn.style.pointerEvents = 'auto';
                } else {
                    scrollToTopBtn.style.opacity = '0';
                    scrollToTopBtn.style.pointerEvents = 'none';
                }
            });
            scrollToTopBtn.addEventListener('click', function () {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }

        // ---- Keyboard shortcuts ----
        document.addEventListener('keydown', function (e) {
            // Ctrl+Shift+T → open theme settings
            if (e.ctrlKey && e.shiftKey && e.key === 'T') {
                e.preventDefault();
                document.getElementById('themeSettingsButton')?.click();
            }
            // / or Ctrl+K → open search
            if ((e.key === '/' || (e.ctrlKey && e.key === 'k')) &&
                !e.target.closest('input') && !e.target.closest('textarea') && !e.target.closest('select')) {
                e.preventDefault();
                openSearchModal();
            }
            // Escape → close modals
            if (e.key === 'Escape') {
                closeSearchModal();
                if (typeof window.__closeThemeModal === 'function') window.__closeThemeModal();
            }
        });

        // ---- Global close helpers ----
        window.closeEmailModal = function () {
            const m = document.getElementById('emailManagementModal');
            if (m) { m.classList.add('hidden'); document.body.style.overflow = 'auto'; }
        };
        window.closeSmsModal = function () {
            const m = document.getElementById('smsManagementModal');
            if (m) { m.classList.add('hidden'); document.body.style.overflow = 'auto'; }
        };
        window.closeWhatsAppModal = function () {
            const m = document.getElementById('whatsappManagementModal');
            if (m) { m.classList.add('hidden'); document.body.style.overflow = 'auto'; }
        };
        window.closeInvoiceModal = function () {
            const m = document.getElementById('invoiceManagementModal');
            if (m) { m.classList.add('hidden'); document.body.style.overflow = 'auto'; }
        };
        window.closeAdminToolsModal = function () {
            const m = document.getElementById('administrativeToolsModal');
            if (m) { m.classList.add('hidden'); document.body.style.overflow = 'auto'; }
        };
        window.closeBillingModal = function () {
            const m = document.getElementById('billingManagementModal');
            if (m) { m.classList.add('hidden'); document.body.style.overflow = 'auto'; }
        };
    });

    // =========================================================================
    // WINDOW LOAD — FINAL CHECKS
    // =========================================================================

    window.addEventListener('load', function () {
        setTimeout(function () {
            const savedState = localStorage.getItem('sidebarCollapsed') === 'true';
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                const hasCollapsedClass = sidebar.classList.contains('collapsed');
                if (hasCollapsedClass !== savedState) toggleSidebar(savedState, false);
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
})();
</script>