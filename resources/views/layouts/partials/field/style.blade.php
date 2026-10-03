<style>
/* =====================================================
   SIDEBAR COLLAPSE STYLES - Section Dividers Hidden
   ===================================================== */

/* ===== SIDEBAR COLLAPSED MODE ===== */
.sidebar.collapsed {
    width: 60px !important;
    min-width: 60px !important;
    max-width: 60px !important;
}

/* Hide section dividers completely when collapsed */
.sidebar.collapsed .nav-divider {
    display: none !important;
    visibility: hidden !important;
    height: 0 !important;
    min-height: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
    border: 0 !important;
}

/* Compact nav items in collapsed mode */
.sidebar.collapsed nav {
    padding: 4px 0 !important;
}

.sidebar.collapsed .nav-item {
    padding: 8px 0 !important;
    margin: 2px 4px !important;
    border-radius: 8px !important;
    justify-content: center !important;
    width: calc(100% - 8px) !important;
    min-height: 40px !important;
    display: flex !important;
    align-items: center !important;
}

.sidebar.collapsed .nav-item i {
    margin: 0 !important;
    font-size: 1.15rem !important;
}

.sidebar.collapsed .nav-item .nav-text,
.sidebar.collapsed .nav-item span.ml-auto,
.sidebar.collapsed .nav-item .ml-auto,
.sidebar.collapsed .nav-item .badge,
.sidebar.collapsed .nav-item .chevron-right,
.sidebar.collapsed .nav-item .flex-grow {
    display: none !important;
}

/* Hide badges and counts in collapsed mode */
.sidebar.collapsed .nav-item .bg-yellow-500,
.sidebar.collapsed .nav-item .bg-red-500,
.sidebar.collapsed .nav-item .bg-blue-500,
.sidebar.collapsed .nav-item .bg-orange-500 {
    display: none !important;
}

/* Compact logo section */
.sidebar.collapsed .logo-section {
    padding: 10px 6px !important;
    position: relative !important;
}

.sidebar.collapsed .logo-text-container,
.sidebar.collapsed .logo-fullname,
.sidebar.collapsed .logo-shortname {
    display: none !important;
}

.sidebar.collapsed .logo-wrapper {
    justify-content: center !important;
}

.sidebar.collapsed .logo-image-container,
.sidebar.collapsed .logo-default {
    width: 32px !important;
    height: 32px !important;
}

.sidebar.collapsed .logo-separator {
    margin: 4px 8px !important;
}

/* FIXED: Toggle button - Always visible and positioned on the edge */
.sidebar.collapsed .toggle-sidebar {
    display: flex !important;
    position: absolute !important;
    right: -14px !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    z-index: 100 !important;
    background-color: var(--primary) !important;
    border-color: var(--primary) !important;
    color: #ffffff !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3) !important;
    width: 28px !important;
    height: 28px !important;
    border-radius: 50% !important;
}

.sidebar.collapsed .toggle-sidebar:hover {
    transform: translateY(-50%) scale(1.1) !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4) !important;
    background-color: var(--secondary) !important;
}

.sidebar.collapsed .toggle-sidebar i {
    transform: rotate(180deg);
    font-size: 12px !important;
}

.sidebar.collapsed .absolute.bottom-0 {
    padding: 6px 0 !important;
}

.sidebar.collapsed #settingsIcon {
    font-size: 1rem !important;
}

/* Active state in collapsed mode */
.sidebar.collapsed .nav-item.active {
    background: rgba(var(--primary-rgb), 0.15) !important;
    border-left: 3px solid var(--primary) !important;
}

/* Remove extra spacing between nav items in collapsed mode */
.sidebar.collapsed .nav-divider + .nav-item {
    margin-top: 2px !important;
}

.sidebar.collapsed .nav-item + .nav-item {
    margin-top: 2px !important;
}

/* Ensure buttons are properly centered in collapsed mode */
.sidebar.collapsed .nav-item button,
.sidebar.collapsed .nav-item a {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 100% !important;
    padding: 0 !important;
}

/* Hide any remaining text or labels in collapsed mode */
.sidebar.collapsed .menu-text {
    display: none !important;
}

.sidebar.collapsed .nav-divider .menu-text {
    display: none !important;
}

/* =====================================================
   SIDEBAR TOGGLE BUTTON - EXPANDED MODE
   ===================================================== */
.toggle-sidebar {
    cursor: pointer;
    display: flex !important;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.15);
    border: 2px solid rgba(255, 255, 255, 0.25);
    color: var(--sidebar-text, #ffffff);
    position: absolute;
    right: -14px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 100;
    background-color: var(--primary);
    border-color: var(--primary);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.toggle-sidebar:hover {
    transform: translateY(-50%) scale(1.1);
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.toggle-sidebar i {
    font-size: 12px;
    transition: transform 0.3s ease;
}

/* Logo section must be relative for toggle button positioning */
.logo-section {
    position: relative !important;
}

/* Ensure toggle button is visible in all sidebar themes */
[data-sidebar-theme="dark"] .toggle-sidebar,
[data-sidebar-theme="default"] .toggle-sidebar,
[data-sidebar-theme="blue"] .toggle-sidebar,
[data-sidebar-theme="green"] .toggle-sidebar {
    background-color: var(--primary);
    border-color: var(--primary);
    color: #ffffff;
}

[data-sidebar-theme="light"] .toggle-sidebar {
    background-color: var(--primary);
    border-color: var(--primary);
    color: #ffffff;
}

/* =====================================================
   ENHANCED MINIMAL AVATAR STYLING
   ===================================================== */
.avatar-minimal {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: white;
    cursor: pointer;
    transition: all 0.3s;
    border: 1px solid var(--border-color);
}

.avatar-minimal:hover {
    transform: scale(1.05);
    border-color: var(--primary);
}

.avatar-minimal img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--border-color);
}

/* =====================================================
   HEADER STYLES
   ===================================================== */
.header {
    padding: 12px 24px;
}

/* Search bar enhancements */
.header-search {
    min-width: 280px;
    border-radius: 8px;
    padding: 8px 16px 8px 40px;
    font-size: 14px;
    transition: all 0.2s ease;
}

.header-search:focus {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
    min-width: 350px;
    transition: min-width 0.3s ease;
    outline: none;
}

/* Theme-specific search input styling */
[data-theme="dark"] .header-search {
    background-color: #2d3748 !important;
    border-color: #4a5568 !important;
    color: #e2e8f0 !important;
}

[data-theme="dark"] .header-search::placeholder {
    color: #a0aec0 !important;
}

[data-theme="light"] .header-search {
    background-color: #f7fafc !important;
    border-color: #e2e8f0 !important;
    color: #2d3748 !important;
}

[data-theme="light"] .header-search::placeholder {
    color: #718096 !important;
}

/* Mobile search button styling */
#mobileSearchBtn {
    padding: 8px;
}

/* Header buttons spacing */
.header-buttons {
    gap: 8px;
}

/* Dropdown menu positioning */
.dropdown-menu {
    min-width: 220px;
}

/* Advanced search button hover effect */
#advancedSearchBtn:hover {
    background-color: rgba(0, 0, 0, 0.05);
    transform: scale(1.1);
}

/* =====================================================
   NOTIFICATIONS DROPDOWN STYLES
   ===================================================== */
.notifications-dropdown {
    position: absolute;
    top: 60px;
    right: 20px;
    width: 380px;
    max-width: calc(100vw - 40px);
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    z-index: 1000;
    border: 1px solid var(--border-color);
    overflow: hidden;
}

.notifications-dropdown.hidden {
    display: none;
}

.notifications-header {
    padding: 16px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notifications-header h3 {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.notifications-actions {
    display: flex;
    gap: 12px;
}

.notifications-actions button {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
}

.notifications-list {
    max-height: 400px;
    overflow-y: auto;
}

.notification-item {
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-color);
    transition: background-color 0.2s;
    cursor: pointer;
}

.notification-item:hover {
    background-color: var(--bg-secondary);
}

.notification-item.unread {
    background-color: rgba(59, 130, 246, 0.05);
}

.notification-item.unread:hover {
    background-color: rgba(59, 130, 246, 0.1);
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-secondary);
}

.notification-content {
    flex: 1;
}

.notification-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.notification-message {
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.4;
}

.notification-time {
    font-size: 11px;
    color: var(--text-secondary);
    margin-top: 4px;
}

.notifications-loading {
    padding: 32px;
    text-align: center;
    color: var(--text-secondary);
}

.notifications-empty {
    padding: 48px 32px;
    text-align: center;
    color: var(--text-secondary);
}

.notifications-empty i {
    font-size: 48px;
    margin-bottom: 12px;
    opacity: 0.5;
}

.notifications-footer {
    padding: 12px 16px;
    border-top: 1px solid var(--border-color);
    text-align: center;
}

.view-all-link {
    font-size: 13px;
    color: #3b82f6;
    text-decoration: none;
    font-weight: 500;
}

.view-all-link:hover {
    text-decoration: underline;
}

.notification-badge {
    position: relative;
}

.notification-badge .badge-count {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #ef4444;
    color: white;
    font-size: 10px;
    font-weight: bold;
    min-width: 18px;
    height: 18px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 5px;
}

/* Mark as read indicator */
.notification-item.unread::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: #3b82f6;
}

/* =====================================================
   BELL ICON HOVER EFFECT
   ===================================================== */
#notificationBell:hover .fa-bell {
    animation: bellRing 0.5s ease-in-out;
}

@keyframes bellRing {
    0% { transform: rotate(0); }
    25% { transform: rotate(15deg); }
    50% { transform: rotate(-15deg); }
    75% { transform: rotate(10deg); }
    100% { transform: rotate(0); }
}

/* =====================================================
   EMAIL MANAGEMENT MODAL STYLES
   ===================================================== */
.email-modal {
    width: 95%;
    max-width: 1200px;
    border-radius: 16px;
    overflow: hidden;
}

/* Email Account Item Styles */
.account-item {
    transition: all 0.2s ease;
}

.account-item:hover {
    background-color: rgba(var(--primary-rgb), 0.05);
}

/* =====================================================
   THEME SETTINGS MODAL STYLES
   ===================================================== */
.theme-modal-compact {
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    width: 90%;
    max-width: 400px;
    max-height: 90vh;
    overflow-y: auto;
    animation: modalFadeIn 0.3s ease-out;
    border: 1px solid var(--border-color);
}

.dark .theme-modal-compact {
    background: #1f2937;
}

.theme-modal-header-compact {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
}

.dark .theme-modal-header-compact {
    border-bottom-color: #374151;
}

.theme-modal-body-compact {
    padding: 20px;
}

.theme-modal-footer-compact {
    padding: 16px 20px;
    border-top: 1px solid var(--border-color);
}

.dark .theme-modal-footer-compact {
    border-top-color: #374151;
}

.section-header-compact {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.dark .section-header-compact {
    color: #f9fafb;
}

.section-description-compact {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-bottom: 12px;
}

.appearance-grid-compact {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}

.appearance-option-compact {
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    padding: 12px 8px;
    border-radius: 8px;
    transition: all 0.2s ease;
    border: 2px solid transparent;
}

.appearance-option-compact:hover {
    background-color: var(--bg-secondary);
}

.dark .appearance-option-compact:hover {
    background-color: #374151;
}

.appearance-option-compact.active {
    border-color: var(--primary);
    background-color: rgba(var(--primary-rgb), 0.08);
}

.dark .appearance-option-compact.active {
    background-color: rgba(var(--primary-rgb), 0.15);
}

.appearance-icon-compact {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 8px;
    transition: all 0.2s ease;
}

.appearance-label-compact {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--text-primary);
}

.dark .appearance-label-compact {
    color: #d1d5db;
}

.theme-grid-compact {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}

.theme-option-compact {
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    padding: 12px 8px;
    border-radius: 8px;
    transition: all 0.2s ease;
    border: 2px solid transparent;
}

.theme-option-compact:hover {
    background-color: var(--bg-secondary);
}

.dark .theme-option-compact:hover {
    background-color: #374151;
}

.theme-option-compact.active {
    border-color: var(--primary);
    background-color: rgba(var(--primary-rgb), 0.08);
}

.dark .theme-option-compact.active {
    background-color: rgba(var(--primary-rgb), 0.15);
}

.theme-preview-compact {
    width: 100%;
    height: 40px;
    border-radius: 6px;
    margin-bottom: 8px;
    transition: all 0.2s ease;
}

.theme-label-compact {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--text-primary);
}

.dark .theme-label-compact {
    color: #d1d5db;
}

/* Current selection indicator */
.current-selection {
    position: absolute;
    top: 8px;
    right: 8px;
    background: var(--primary);
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 10px;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(-10px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* =====================================================
   MODAL BACKDROP BLUR
   ===================================================== */
#communicationManagementModal,
#themeSettingsModal,
#searchModal,
#emailManagementModal,
#smsManagementModal,
#whatsappManagementModal,
#billingManagementModal,
#administrativeToolsModal,
#invoiceManagementModal {
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
}

/* =====================================================
   RESPONSIVE ADJUSTMENTS
   ===================================================== */
@media (max-width: 768px) {
    .header {
        padding: 10px 16px;
    }
    
    .avatar-minimal {
        width: 28px;
        height: 28px;
    }
    
    .header-buttons {
        gap: 6px;
    }
    
    .header-search {
        min-width: auto;
        width: 200px;
    }
    
    .header-search:focus {
        min-width: auto;
        width: 100%;
    }
    
    .notifications-dropdown {
        position: fixed;
        top: 60px;
        right: 10px;
        left: 10px;
        width: auto;
        max-width: none;
    }
    
    .email-modal,
    .sms-modal,
    .whatsapp-modal,
    .invoice-modal,
    .billing-modal,
    .admin-tools-modal {
        width: 95%;
        margin: 10px;
    }

    /* Sidebar mobile adjustments */
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        position: fixed !important;
        top: 0;
        left: 0;
        height: 100vh;
        z-index: 1050;
        width: 280px !important;
    }

    .sidebar.mobile-open {
        transform: translateX(0);
    }

    .sidebar.collapsed.mobile-open {
        transform: translateX(0);
        width: 280px !important;
    }

    .sidebar.collapsed.mobile-open .nav-divider {
        display: flex !important;
    }

    .sidebar.collapsed.mobile-open .nav-text {
        display: inline !important;
    }

    .sidebar.collapsed.mobile-open .logo-text-container {
        display: block !important;
    }

    .sidebar.collapsed.mobile-open .toggle-sidebar {
        display: flex !important;
        right: -12px !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
    }

    .sidebar.collapsed.mobile-open .ml-auto {
        display: inline-flex !important;
    }

    .sidebar.collapsed.mobile-open .bg-yellow-500,
    .sidebar.collapsed.mobile-open .bg-red-500,
    .sidebar.collapsed.mobile-open .bg-blue-500,
    .sidebar.collapsed.mobile-open .bg-orange-500 {
        display: inline-flex !important;
    }

    .overlay.active {
        display: block;
        opacity: 1;
    }

    /* Mobile toggle button position adjustment */
    .toggle-sidebar {
        right: -12px !important;
        width: 24px !important;
        height: 24px !important;
        font-size: 10px !important;
    }

    .sidebar.collapsed .toggle-sidebar {
        right: -12px !important;
        width: 24px !important;
        height: 24px !important;
        font-size: 10px !important;
    }
}

@media (max-width: 480px) {
    .header-search {
        width: 160px;
    }
    
    .theme-modal-compact {
        width: 100vw;
        max-width: none;
        margin: 0;
        border-radius: 0;
    }
    
    .appearance-grid-compact {
        grid-template-columns: repeat(3, 1fr);
        gap: 0.25rem;
    }
    
    .theme-grid-compact {
        grid-template-columns: repeat(3, 1fr);
        gap: 0.25rem;
    }
    
    .appearance-option-compact,
    .theme-option-compact {
        padding: 0.5rem 0.25rem;
    }
    
    .appearance-icon-compact {
        width: 2.5rem;
        height: 2.5rem;
        font-size: 1.1rem;
    }
    
    .theme-preview-compact {
        height: 2.5rem;
    }
    
    .appearance-label-compact,
    .theme-label-compact {
        font-size: 0.65rem;
    }

    .toggle-sidebar {
        right: -10px !important;
        width: 22px !important;
        height: 22px !important;
        font-size: 9px !important;
    }

    .sidebar.collapsed .toggle-sidebar {
        right: -10px !important;
        width: 22px !important;
        height: 22px !important;
        font-size: 9px !important;
    }
}

/* =====================================================
   SIDEBAR THEME TEXT COLORS
   ===================================================== */
.sidebar,
.sidebar .nav-item,
.sidebar .logo-shortname,
.sidebar .logo-fullname,
.sidebar .nav-text,
.sidebar a:not(.active),
.sidebar button:not(.active) {
    color: var(--sidebar-text, #ffffff) !important;
    transition: color 0.2s ease;
}

/* Sidebar icons */
.sidebar .nav-item i,
.sidebar button i {
    color: var(--sidebar-text, #ffffff) !important;
    opacity: 0.8;
    transition: all 0.2s ease;
}

/* Sidebar theme-specific text colors */
[data-sidebar-theme="default"] {
    --sidebar-text: #ffffff;
}

[data-sidebar-theme="dark"] {
    --sidebar-text: #e2e8f0;
}

[data-sidebar-theme="light"] {
    --sidebar-text: #1e293b;
}

[data-sidebar-theme="blue"] {
    --sidebar-text: #ffffff;
}

[data-sidebar-theme="green"] {
    --sidebar-text: #ffffff;
}

/* Hover states */
.sidebar .nav-item:hover,
.sidebar button:hover {
    color: var(--sidebar-text-hover, var(--primary)) !important;
}

.sidebar .nav-item:hover i,
.sidebar button:hover i {
    color: var(--sidebar-text-hover, var(--primary)) !important;
    opacity: 1;
}

/* Active states */
.sidebar .nav-item.active,
.sidebar button.active {
    color: var(--primary) !important;
    background-color: rgba(var(--primary-rgb), 0.15) !important;
}

.sidebar .nav-item.active i,
.sidebar button.active i {
    color: var(--primary) !important;
    opacity: 1;
}

/* =====================================================
   BADGE COLORS
   ===================================================== */
.bg-yellow-500 { background-color: #f59e0b; }
.bg-orange-500 { background-color: #f97316; }
.bg-blue-500 { background-color: #3b82f6; }
.bg-red-500 { background-color: #ef4444; }

/* =====================================================
   PLACEHOLDER TEXT COLOR
   ===================================================== */
[data-theme="light"] ::placeholder,
::placeholder.light-theme {
    color: #a0aec0 !important;
    opacity: 1;
}

[data-theme="dark"] ::placeholder,
::placeholder.dark-theme {
    color: #718096 !important;
    opacity: 1;
}

/* =====================================================
   SMOOTH TRANSITIONS
   ===================================================== */
.avatar-minimal,
#advancedSearchBtn,
#mobileSearchBtn,
.communication-option-card,
.quick-action-btn-sm,
.billing-option-card,
.admin-tools-card,
.quick-action-btn,
.recent-search-tag,
#clearRecentSearchesBtn {
    transition: all 0.2s ease-in-out;
}
</style>