<style>
/* =====================================================
   SIDEBAR COLLAPSE STYLES - Consolidated
   ===================================================== */

/* ===== SIDEBAR COLLAPSED MODE - Section Dividers Hidden ===== */
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
   SIDEBAR TOGGLE BUTTON - ALWAYS VISIBLE
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
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: var(--sidebar-text, #ffffff);
    position: absolute;
    right: -14px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 10;
    background-color: var(--primary);
    border-color: var(--primary);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.toggle-sidebar:hover {
    background: var(--secondary) !important;
    border-color: var(--secondary) !important;
    transform: translateY(-50%) scale(1.1);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

/* Position toggle button on the logo section */
.logo-section {
    position: relative !important;
}

/* Toggle button icon rotation */
.sidebar.collapsed .toggle-sidebar {
    right: -14px;
    background-color: var(--primary);
    border-color: var(--primary);
}

.sidebar.collapsed .toggle-sidebar i {
    transform: rotate(180deg);
}

/* Ensure toggle button is always on top */
.toggle-sidebar {
    z-index: 100 !important;
}

/* Make toggle button more visible in collapsed mode */
.sidebar.collapsed .toggle-sidebar {
    box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.3), 0 4px 12px rgba(0, 0, 0, 0.3);
}

/* For dark sidebar themes, make toggle button stand out */
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
   COMMUNICATION MANAGEMENT MODAL STYLES
   ===================================================== */
.communication-modal {
    width: 90%;
    max-width: 1000px;
    border-radius: 16px;
    overflow: hidden;
}

@keyframes modalSlideUp {
    from {
        opacity: 0;
        transform: translateY(30px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.communication-section {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s ease;
}

.communication-section:hover {
    border-color: var(--primary);
    box-shadow: 0 10px 30px rgba(var(--primary-rgb), 0.1);
}

.section-header {
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-color);
}

.section-title {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 2px;
}

.section-subtitle {
    font-size: 12px;
    opacity: 0.8;
}

.communication-option-card {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 16px;
    display: flex;
    align-items: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    text-decoration: none;
    margin-bottom: 12px;
}

.communication-option-card:hover {
    transform: translateY(-2px);
    border-color: var(--primary);
    box-shadow: 0 8px 20px rgba(var(--primary-rgb), 0.12);
}

.communication-option-card.active {
    border-color: var(--primary);
    background-color: rgba(var(--primary-rgb), 0.05);
}

.communication-option-card.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background-color: var(--primary);
    border-radius: 4px 0 0 4px;
}

.communication-icon-container {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 16px;
    flex-shrink: 0;
    transition: all 0.3s ease;
}

.communication-option-card:hover .communication-icon-container {
    transform: scale(1.05);
}

.communication-icon-container i {
    font-size: 20px;
}

.communication-content {
    flex-grow: 1;
}

.communication-title {
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 4px;
    transition: color 0.3s ease;
}

.communication-description {
    font-size: 12px;
    margin-bottom: 8px;
    line-height: 1.4;
    opacity: 0.8;
}

.communication-arrow {
    color: var(--text-secondary);
    opacity: 0.6;
    transition: all 0.3s ease;
    margin-left: 12px;
}

.communication-option-card:hover .communication-arrow {
    opacity: 1;
    transform: translateX(4px);
    color: var(--primary);
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    margin-top: 8px;
}

.quick-action-btn-sm {
    padding: 6px 10px;
    background-color: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    font-size: 11px;
    color: var(--text-primary);
    text-decoration: none;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.quick-action-btn-sm:hover {
    background-color: rgba(var(--primary-rgb), 0.1);
    border-color: var(--primary);
    color: var(--primary);
}

/* =====================================================
   STAT CARDS
   ===================================================== */
.stat-card {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 16px;
    display: flex;
    align-items: center;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    flex-shrink: 0;
}

.stat-icon i {
    font-size: 18px;
}

.stat-content {
    flex-grow: 1;
}

.stat-value {
    font-size: 20px;
    font-weight: 600;
    line-height: 1;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 11px;
    opacity: 0.8;
}

/* =====================================================
   MODAL STYLES
   ===================================================== */
/* Email Modal Styles */
.email-modal {
    width: 95%;
    max-width: 1200px;
    border-radius: 16px;
    overflow: hidden;
}

/* SMS Modal Styles */
.sms-modal {
    width: 95%;
    max-width: 1200px;
    border-radius: 16px;
    overflow: hidden;
}

/* WhatsApp Modal Styles */
.whatsapp-modal {
    width: 95%;
    max-width: 1200px;
    border-radius: 16px;
    overflow: hidden;
}

/* Invoice Modal Styles */
.invoice-modal {
    width: 95%;
    max-width: 1400px;
    border-radius: 16px;
    overflow: hidden;
}

/* Billing Modal Styles */
.billing-modal,
.admin-tools-modal {
    width: 90%;
    max-width: 1200px;
    border-radius: 16px;
    overflow: hidden;
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
   HEADER AND SEARCH STYLES
   ===================================================== */
.header {
    padding: 12px 24px;
}

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

#mobileSearchBtn {
    padding: 8px;
}

.header-buttons {
    gap: 8px;
}

.dropdown-menu {
    min-width: 220px;
}

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

/* Notification badge pulse animation */
.notification-badge .badge-pulse {
    animation: notificationPulse 2s infinite;
}

@keyframes notificationPulse {
    0% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.1);
        opacity: 0.85;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
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

.theme-modal-header-compact {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
}

.theme-modal-body-compact {
    padding: 20px;
}

.theme-modal-footer-compact {
    padding: 16px 20px;
    border-top: 1px solid var(--border-color);
}

.section-header-compact {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.section-description-compact {
    font-size: 12px;
    color: var(--text-secondary);
    margin-bottom: 16px;
}

.appearance-grid-compact {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
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
    position: relative;
}

.appearance-option-compact:hover {
    background-color: var(--bg-secondary);
    transform: translateY(-2px);
}

.appearance-option-compact.active {
    border-color: var(--primary);
    background-color: rgba(var(--primary-rgb), 0.08);
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
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.appearance-label-compact {
    font-size: 12px;
    font-weight: 500;
    color: var(--text-primary);
}

.theme-grid-compact {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 8px;
}

.theme-option-compact {
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    padding: 8px 4px;
    border-radius: 6px;
    transition: all 0.2s ease;
    border: 2px solid transparent;
    position: relative;
}

.theme-option-compact:hover {
    background-color: var(--bg-secondary);
    transform: translateY(-2px);
}

.theme-option-compact.active {
    border-color: var(--primary);
    background-color: rgba(var(--primary-rgb), 0.08);
}

.theme-preview-compact {
    width: 40px;
    height: 40px;
    border-radius: 6px;
    margin-bottom: 6px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

.theme-label-compact {
    font-size: 11px;
    font-weight: 500;
    color: var(--text-primary);
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

.current-selection {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 20px;
    height: 20px;
    background: var(--primary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 10px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

/* =====================================================
   SIDEBAR OVERLAY FOR MOBILE
   ===================================================== */
.overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1040;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.overlay.active {
    display: block;
    opacity: 1;
}

/* =====================================================
   RESPONSIVE ADJUSTMENTS
   ===================================================== */
@media (max-width: 768px) {
    .communication-modal {
        width: 95%;
        margin: 10px;
    }
    
    .communication-modal .grid {
        grid-template-columns: 1fr !important;
    }
    
    .quick-actions-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
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
        right: -14px;
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
        right: -12px;
        width: 24px;
        height: 24px;
        font-size: 10px;
    }
}

@media (max-width: 480px) {
    .communication-option-card {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .communication-icon-container {
        margin-right: 0;
        margin-bottom: 12px;
    }
    
    .communication-arrow {
        position: absolute;
        bottom: 16px;
        right: 16px;
        margin-left: 0;
    }
    
    .header-search {
        width: 160px;
    }
    
    .theme-grid-compact {
        grid-template-columns: repeat(3, 1fr);
    }

    .toggle-sidebar {
        right: -10px;
        width: 22px;
        height: 22px;
        font-size: 9px;
    }
}

/* =====================================================
   NAVIGATION ITEM ACTIVE STATE
   ===================================================== */
.nav-item.active {
    background-color: rgba(var(--primary-rgb), 0.15) !important;
    color: var(--primary) !important;
}

.nav-item.active i {
    color: var(--primary) !important;
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
   MODAL SCROLLBAR THEME AWARE
   ===================================================== */
.modal-scrollable-body::-webkit-scrollbar {
    width: 8px;
}

[data-theme="light"] .modal-scrollable-body::-webkit-scrollbar-track {
    background: #f1f5f9;
}

[data-theme="dark"] .modal-scrollable-body::-webkit-scrollbar-track {
    background: #2d3748;
}

.modal-scrollable-body::-webkit-scrollbar-thumb {
    border-radius: 4px;
    transition: all 0.2s ease;
}

[data-theme="light"] .modal-scrollable-body::-webkit-scrollbar-thumb {
    background: #94a3b8;
}

[data-theme="dark"] .modal-scrollable-body::-webkit-scrollbar-thumb {
    background: #4a5568;
}

.modal-scrollable-body::-webkit-scrollbar-thumb:hover {
    background: #64748b;
}

[data-theme="dark"] .modal-scrollable-body::-webkit-scrollbar-thumb:hover {
    background: #718096;
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
</style>