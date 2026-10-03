<style>
/* =====================================================
   SIDEBAR COLLAPSE STYLES
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

/* FIXED: Toggle button - Always visible and positioned on the edge */
.sidebar.collapsed .toggle-sidebar {
    display: flex !important; /* Override the previous display:none */
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
   BILLING MANAGEMENT MODAL STYLES
   ===================================================== */
.billing-modal,
.admin-tools-modal {
    width: 90%;
    max-width: 1200px;
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

.billing-option-card,
.admin-tools-card {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: flex-start;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    text-decoration: none;
    height: 100%;
}

.billing-option-card:hover,
.admin-tools-card:hover {
    transform: translateY(-4px);
    border-color: var(--primary);
    box-shadow: 0 10px 30px rgba(var(--primary-rgb), 0.15);
}

.billing-option-card.active,
.admin-tools-card.active {
    border-color: var(--primary);
    background-color: rgba(var(--primary-rgb), 0.05);
}

.billing-option-card.active::before,
.admin-tools-card.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background-color: var(--primary);
    border-radius: 4px 0 0 4px;
}

.billing-icon-container,
.admin-tools-icon-container {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    flex-shrink: 0;
    transition: all 0.3s ease;
}

.billing-option-card:hover .billing-icon-container,
.admin-tools-card:hover .admin-tools-icon-container {
    transform: scale(1.1);
}

.billing-icon-container i,
.admin-tools-icon-container i {
    font-size: 22px;
}

.billing-content,
.admin-tools-content {
    flex-grow: 1;
    min-width: 0;
}

.billing-title,
.admin-tools-title {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 5px;
    transition: color 0.3s ease;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.billing-description,
.admin-tools-description {
    font-size: 13px;
    margin-bottom: 10px;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.billing-stats,
.admin-tools-stats {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 5px;
}

.stat-badge {
    font-size: 11px;
    padding: 3px 8px;
    background-color: var(--bg-secondary);
    border-radius: 16px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: var(--text-secondary);
    white-space: nowrap;
}

.billing-arrow,
.admin-tools-arrow {
    color: var(--text-secondary);
    opacity: 0.6;
    transition: all 0.3s ease;
    margin-left: 10px;
    align-self: center;
}

.billing-option-card:hover .billing-arrow,
.admin-tools-card:hover .admin-tools-arrow {
    opacity: 1;
    transform: translateX(4px);
    color: var(--primary);
}

/* =====================================================
   QUICK STATS CARDS
   ===================================================== */
.stat-card {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 15px;
    display: flex;
    align-items: center;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.stat-icon {
    width: 45px;
    height: 45px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    flex-shrink: 0;
}

.stat-icon i {
    font-size: 20px;
}

.stat-content {
    flex-grow: 1;
}

.stat-number {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
}

.stat-label {
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 4px;
}

/* =====================================================
   QUICK ACTIONS
   ===================================================== */
.quick-action-btn {
    padding: 8px 16px;
    background-color: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-size: 13px;
    color: var(--text-primary);
    text-decoration: none;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
}

.quick-action-btn:hover {
    background-color: rgba(var(--primary-rgb), 0.1);
    border-color: var(--primary);
    color: var(--primary);
}

/* =====================================================
   QUICK SEARCH DROPDOWN STYLES
   ===================================================== */
.quick-search-results {
    animation: fadeInDown 0.2s ease-out;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInRight {
    from {
        opacity: 0;
        transform: translateX(-10px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.quick-result-item {
    transition: all 0.15s ease;
    cursor: pointer;
}

.quick-result-item:hover {
    padding-left: 16px;
    background-color: rgba(var(--primary-rgb), 0.05) !important;
}

.quick-result-item mark {
    background-color: rgba(var(--primary-rgb), 0.3);
    color: inherit;
    padding: 0 2px;
    border-radius: 2px;
    font-weight: 500;
}

/* Dark theme specific mark styling */
[data-theme="dark"] .quick-result-item mark {
    background-color: rgba(96, 165, 250, 0.3);
    color: #60a5fa;
}

/* Light theme specific mark styling */
[data-theme="light"] .quick-result-item mark {
    background-color: rgba(37, 99, 235, 0.2);
    color: #2563eb;
}

/* Quick search input focus effect */
#quickSearchInput:focus {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
    min-width: 350px;
    transition: min-width 0.3s ease;
}

/* Quick search dropdown scrollbar */
.quick-search-results::-webkit-scrollbar {
    width: 6px;
}

[data-theme="light"] .quick-search-results::-webkit-scrollbar-track {
    background: #f1f5f9;
}

[data-theme="dark"] .quick-search-results::-webkit-scrollbar-track {
    background: #2d3748;
}

.quick-search-results::-webkit-scrollbar-thumb {
    background: #94a3b8;
    border-radius: 3px;
}

[data-theme="dark"] .quick-search-results::-webkit-scrollbar-thumb {
    background: #4a5568;
}

.quick-search-results::-webkit-scrollbar-thumb:hover {
    background: #64748b;
}

[data-theme="dark"] .quick-search-results::-webkit-scrollbar-thumb:hover {
    background: #718096;
}

/* View all results button */
.view-all-quick-results,
.view-all-results-btn {
    transition: all 0.2s ease;
}

.view-all-quick-results:hover,
.view-all-results-btn:hover {
    background-color: rgba(var(--primary-rgb), 0.1);
    transform: translateX(2px);
}

/* =====================================================
   SEARCH MODAL THEME STYLES
   ===================================================== */
/* Search Modal Container - Theme aware */
.search-modal-container {
    animation: modalSlideIn 0.3s ease-out;
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s ease;
}

/* Light Theme */
[data-theme="light"] .search-modal-container,
.search-modal-container.light-theme {
    background-color: #ffffff !important;
    border: 1px solid #e5e7eb !important;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1) !important;
}

/* Dark Theme */
[data-theme="dark"] .search-modal-container,
.search-modal-container.dark-theme {
    background-color: #1a1e2c !important;
    border: 1px solid #2d3748 !important;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5) !important;
}

/* Modal Header - Theme aware */
.modal-header-light {
    border-bottom: 1px solid;
    padding: 1rem 1.5rem;
    transition: all 0.3s ease;
}

[data-theme="light"] .modal-header-light,
.modal-header-light.light-theme {
    background-color: #f9fafb !important;
    border-bottom-color: #e5e7eb !important;
}

[data-theme="dark"] .modal-header-light,
.modal-header-light.dark-theme {
    background-color: #252b3b !important;
    border-bottom-color: #2d3748 !important;
}

.modal-header-light h3 {
    transition: color 0.3s ease;
}

[data-theme="light"] .modal-header-light h3,
.modal-header-light.light-theme h3 {
    color: #1a202c !important;
}

[data-theme="dark"] .modal-header-light h3,
.modal-header-light.dark-theme h3 {
    color: #e2e8f0 !important;
}

.modal-header-light .text-blue-600 {
    transition: color 0.3s ease;
}

[data-theme="light"] .modal-header-light .text-blue-600,
.modal-header-light.light-theme .text-blue-600 {
    color: #2563eb !important;
}

[data-theme="dark"] .modal-header-light .text-blue-600,
.modal-header-light.dark-theme .text-blue-600 {
    color: #60a5fa !important;
}

/* Modal Footer - Theme aware */
.modal-footer-light {
    border-top: 1px solid;
    padding: 1rem 1.5rem;
    transition: all 0.3s ease;
}

[data-theme="light"] .modal-footer-light,
.modal-footer-light.light-theme {
    background-color: #f9fafb !important;
    border-top-color: #e5e7eb !important;
}

[data-theme="dark"] .modal-footer-light,
.modal-footer-light.dark-theme {
    background-color: #252b3b !important;
    border-top-color: #2d3748 !important;
}

/* Enhanced Modal Scrolling */
.search-modal-container {
    max-height: 85vh;
    display: flex;
    flex-direction: column;
}

/* Smooth scrolling for modal body */
.modal-scrollable-body {
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
    overflow-y: auto;
}

/* Custom scrollbar - Theme aware */
.modal-scrollable-body::-webkit-scrollbar {
    width: 8px;
}

[data-theme="light"] .modal-scrollable-body::-webkit-scrollbar-track,
.modal-scrollable-body.light-theme::-webkit-scrollbar-track {
    background: #f1f5f9;
}

[data-theme="dark"] .modal-scrollable-body::-webkit-scrollbar-track,
.modal-scrollable-body.dark-theme::-webkit-scrollbar-track {
    background: #2d3748;
}

.modal-scrollable-body::-webkit-scrollbar-track {
    margin: 8px 0;
    border-radius: 4px;
}

[data-theme="light"] .modal-scrollable-body::-webkit-scrollbar-thumb,
.modal-scrollable-body.light-theme::-webkit-scrollbar-thumb {
    background: #94a3b8;
}

[data-theme="dark"] .modal-scrollable-body::-webkit-scrollbar-thumb,
.modal-scrollable-body.dark-theme::-webkit-scrollbar-thumb {
    background: #4a5568;
}

.modal-scrollable-body::-webkit-scrollbar-thumb {
    border-radius: 4px;
    transition: all 0.2s ease;
}

.modal-scrollable-body::-webkit-scrollbar-thumb:hover {
    background: #64748b;
}

[data-theme="dark"] .modal-scrollable-body::-webkit-scrollbar-thumb:hover,
.modal-scrollable-body.dark-theme::-webkit-scrollbar-thumb:hover {
    background: #718096;
}

/* Scroll Indicators - Theme aware */
.scroll-indicator {
    position: absolute;
    left: 0;
    right: 0;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
    pointer-events: none;
    transition: opacity 0.3s ease;
    opacity: 0;
}

.scroll-indicator.top-indicator {
    top: 60px;
}

[data-theme="light"] .scroll-indicator.top-indicator,
.scroll-indicator.top-indicator.light-theme {
    background: linear-gradient(to bottom, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0));
}

[data-theme="dark"] .scroll-indicator.top-indicator,
.scroll-indicator.top-indicator.dark-theme {
    background: linear-gradient(to bottom, rgba(26, 30, 44, 0.95), rgba(26, 30, 44, 0));
}

.scroll-indicator.bottom-indicator {
    bottom: 60px;
}

[data-theme="light"] .scroll-indicator.bottom-indicator,
.scroll-indicator.bottom-indicator.light-theme {
    background: linear-gradient(to top, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0));
}

[data-theme="dark"] .scroll-indicator.bottom-indicator,
.scroll-indicator.bottom-indicator.dark-theme {
    background: linear-gradient(to top, rgba(26, 30, 44, 0.95), rgba(26, 30, 44, 0));
}

.scroll-indicator i {
    font-size: 18px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    animation: bounce 2s infinite;
    transition: all 0.3s ease;
}

[data-theme="light"] .scroll-indicator i,
.scroll-indicator.light-theme i {
    color: #3b82f6;
    background: rgba(255, 255, 255, 0.9);
}

[data-theme="dark"] .scroll-indicator i,
.scroll-indicator.dark-theme i {
    color: #60a5fa;
    background: rgba(37, 43, 59, 0.9);
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% {
        transform: translateY(0);
    }
    40% {
        transform: translateY(-5px);
    }
    60% {
        transform: translateY(-3px);
    }
}

/* Show scroll indicators when content overflows */
.scroll-indicator.visible {
    opacity: 1;
    pointer-events: auto;
}

.scroll-indicator.hidden {
    opacity: 0;
    pointer-events: none;
}

/* =====================================================
   FORM ELEMENTS - THEME AWARE
   ===================================================== */
[data-theme="light"] input,
[data-theme="light"] select,
[data-theme="light"] textarea,
.input-light-theme {
    background-color: #f7fafc !important;
    border-color: #e2e8f0 !important;
    color: #2d3748 !important;
}

[data-theme="dark"] input,
[data-theme="dark"] select,
[data-theme="dark"] textarea,
.input-dark-theme {
    background-color: #2d3748 !important;
    border-color: #4a5568 !important;
    color: #e2e8f0 !important;
}

input, select, textarea {
    transition: all 0.2s ease;
    border-radius: 8px;
    padding: 8px 12px;
    border-width: 1px;
    border-style: solid;
}

input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

/* Labels - Theme aware */
[data-theme="light"] label,
.label-light-theme {
    color: #4a5568 !important;
}

[data-theme="dark"] label,
.label-dark-theme {
    color: #a0aec0 !important;
}

/* Search Field Options - Theme aware */
.search-field-option {
    transition: all 0.2s ease;
}

[data-theme="light"] .search-field-option,
.search-field-option.light-theme {
    background-color: #f7fafc !important;
    border-color: #e2e8f0 !important;
    color: #2d3748 !important;
}

[data-theme="dark"] .search-field-option,
.search-field-option.dark-theme {
    background-color: #2d3748 !important;
    border-color: #4a5568 !important;
    color: #e2e8f0 !important;
}

.search-field-option:hover {
    background-color: rgba(var(--primary-rgb), 0.05) !important;
    border-color: var(--primary) !important;
}

/* =====================================================
   RECENT SEARCH TAGS - THEME AWARE
   ===================================================== */
.recent-search-tag {
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 9999px;
    font-size: 14px;
    cursor: pointer;
    border-width: 1px;
    border-style: solid;
}

[data-theme="light"] .recent-search-tag,
.recent-search-tag.light-theme {
    background-color: #f7fafc !important;
    border-color: #e2e8f0 !important;
    color: #2d3748 !important;
}

[data-theme="dark"] .recent-search-tag,
.recent-search-tag.dark-theme {
    background-color: #2d3748 !important;
    border-color: #4a5568 !important;
    color: #e2e8f0 !important;
}

.recent-search-tag:hover {
    background-color: rgba(var(--primary-rgb), 0.1) !important;
    border-color: var(--primary) !important;
}

.recent-search-tag span {
    font-size: 10px;
    opacity: 0.7;
}

/* Clear Recent Searches Button - Theme aware */
[data-theme="light"] #clearRecentSearchesBtn,
#clearRecentSearchesBtn.light-theme {
    color: #ef4444 !important;
}

[data-theme="dark"] #clearRecentSearchesBtn,
#clearRecentSearchesBtn.dark-theme {
    color: #f87171 !important;
}

#clearRecentSearchesBtn:hover {
    opacity: 0.8;
    transform: scale(1.05);
}

/* =====================================================
   RADIO AND CHECKBOX STYLING
   ===================================================== */
[type="radio"], [type="checkbox"] {
    transition: all 0.2s ease;
}

[data-theme="light"] [type="radio"],
[data-theme="light"] [type="checkbox"],
.radio-light-theme, .checkbox-light-theme {
    accent-color: var(--primary);
}

[data-theme="dark"] [type="radio"],
[data-theme="dark"] [type="checkbox"],
.radio-dark-theme, .checkbox-dark-theme {
    accent-color: var(--primary);
    filter: brightness(0.8);
}

/* =====================================================
   RESPONSIVE ADJUSTMENTS
   ===================================================== */
@media (max-width: 1280px) {
    .billing-modal,
    .admin-tools-modal {
        max-width: 1000px;
    }
}

@media (max-width: 1100px) {
    .billing-modal .grid,
    .admin-tools-modal .grid {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}

@media (max-width: 768px) {
    .billing-modal,
    .admin-tools-modal {
        width: 95%;
        margin: 10px;
    }
    
    .billing-modal .grid,
    .admin-tools-modal .grid {
        grid-template-columns: 1fr !important;
    }
    
    .stat-card {
        flex-direction: column;
        text-align: center;
    }
    
    .stat-icon {
        margin-right: 0;
        margin-bottom: 10px;
    }
    
    .quick-search-results {
        position: fixed;
        top: auto;
        left: 10px;
        right: 10px;
        max-height: 60vh;
        width: calc(100% - 20px);
        margin-top: 8px;
    }
    
    #quickSearchInput:focus {
        min-width: auto;
        width: 100%;
    }
    
    .quick-result-item {
        padding: 12px;
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
    
    .notification-dropdown {
        width: 300px !important;
        right: -50px !important;
    }

    /* Sidebar mobile adjustments */
    .sidebar.collapsed {
        width: 0 !important;
        min-width: 0 !important;
        max-width: 0 !important;
        overflow: hidden !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .sidebar.collapsed .logo-section,
    .sidebar.collapsed nav,
    .sidebar.collapsed .absolute.bottom-0 {
        display: none !important;
    }

    /* Mobile toggle button position */
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
    .billing-option-card,
    .admin-tools-card {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .billing-icon-container,
    .admin-tools-icon-container {
        margin-right: 0;
        margin-bottom: 12px;
    }
    
    .billing-arrow,
    .admin-tools-arrow {
        position: absolute;
        bottom: 16px;
        right: 16px;
        margin-left: 0;
    }
    
    .header-search {
        width: 160px;
    }
    
    .search-modal-container .p-6 {
        padding: 1rem !important;
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
   HEADER AND NAVIGATION STYLES
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
   MODAL BACKDROP BLUR EFFECT
   ===================================================== */
#searchModal,
#billingManagementModal,
#administrativeToolsModal,
#themeSettingsModal,
#emailManagementModal,
#smsManagementModal,
#whatsappManagementModal,
#invoiceManagementModal {
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
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
    cursor: pointer;
    transition: all 0.3s;
}

.avatar-minimal:hover {
    transform: scale(1.05);
}

.avatar-minimal img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

/* =====================================================
   SMOOTH TRANSITIONS
   ===================================================== */
.avatar-minimal,
#advancedSearchBtn,
#mobileSearchBtn,
#performSearch,
#clearSearch,
#cancelSearch,
#closeSearchModal,
.billing-option-card,
.admin-tools-card,
.quick-action-btn,
.recent-search-tag,
#clearRecentSearchesBtn {
    transition: all 0.2s ease-in-out;
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
   BADGE COLORS
   ===================================================== */
.bg-yellow-500 { background-color: #f59e0b; }
.bg-orange-500 { background-color: #f97316; }
.bg-blue-500 { background-color: #3b82f6; }
.bg-red-500 { background-color: #ef4444; }

/* =====================================================
   NOTIFICATION BADGE - CONSOLIDATED STYLES
   ===================================================== */
/* Base badge styles - single source of truth */
.notification-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    background-color: #ef4444;
    color: white;
    border-radius: 50%;
    min-width: 20px;
    height: 20px;
    padding: 0 5px;
    font-size: 10px;
    font-weight: 700;
    line-height: 20px;
    text-align: center;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--card-bg, #ffffff);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    animation: notificationPulse 2s infinite;
    z-index: 10;
    transform-origin: center;
}

/* Pulse animation for notification badge */
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

/* Light theme badge adjustments */
[data-theme="light"] .notification-badge {
    border-color: #ffffff;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

/* Dark theme badge adjustments */
[data-theme="dark"] .notification-badge {
    border-color: #1a1e2c;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
}

/* Container for notification bell must have relative positioning */
#notificationBell {
    position: relative;
    display: inline-flex;
    align-items: center;
    cursor: pointer;
}

/* =====================================================
   MODAL OPEN BODY OVERFLOW
   ===================================================== */
.modal-open {
    overflow: hidden;
}

/* =====================================================
   CONDITIONAL DISPLAY FOR PENDING ITEMS
   ===================================================== */
@media (max-width: 768px) {
    .nav-item .ml-auto {
        display: none;
    }
}

/* =====================================================
   TOAST NOTIFICATION STYLES
   ===================================================== */
#toast-container {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

/* =====================================================
   INVOICE SECTION STYLES
   ===================================================== */
.invoice-section {
    animation: fadeInRight 0.4s ease-out;
}

.invoice-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.invoice-card:hover {
    transform: translateX(8px);
    border-color: var(--primary) !important;
    background-color: rgba(var(--primary-rgb), 0.02) !important;
}

.invoice-icon {
    transition: all 0.3s ease;
}

.invoice-card:hover .invoice-icon {
    transform: scale(1.05);
}

.invoice-arrow {
    transition: all 0.3s ease;
}

.section-header {
    position: relative;
}

.stat-mini {
    transition: all 0.2s ease;
}

.stat-mini:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Invoice card hover effect */
.invoice-card:hover .invoice-arrow i {
    transform: translateX(4px);
    color: var(--primary);
}

/* Responsive invoice layout */
@media (max-width: 1024px) {
    .invoice-section .grid-cols-2 {
        grid-template-columns: 1fr !important;
    }
}

/* Scroll indicator for modals */
.scroll-indicator {
    transition: opacity 0.3s ease;
}

/* Fix for sidebar text visibility */
.sidebar:not([data-sidebar-theme]) {
    --sidebar-text: #ffffff;
}

/* =====================================================
   EMAIL MODAL STYLES
   ===================================================== */
.email-modal .stat-card {
    transition: all 0.2s ease;
}
.email-modal .stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.email-modal .quick-action-btn {
    transition: all 0.2s ease;
}
.email-modal .quick-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border-color: var(--primary) !important;
}

.email-modal .account-item {
    transition: all 0.2s ease;
}
.email-modal .account-item:hover {
    transform: translateX(4px);
    border-color: var(--primary) !important;
}

.email-modal .account-item button:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

/* =====================================================
   SMS MODAL STYLES
   ===================================================== */
.sms-modal .stat-card {
    transition: all 0.2s ease;
}
.sms-modal .stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.sms-modal .quick-action-btn {
    transition: all 0.2s ease;
}
.sms-modal .quick-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border-color: var(--primary) !important;
}

.sms-modal .provider-card {
    transition: all 0.2s ease;
}
.sms-modal .provider-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* =====================================================
   WHATSAPP MODAL STYLES
   ===================================================== */
.whatsapp-modal .stat-card {
    transition: all 0.2s ease;
}
.whatsapp-modal .stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.whatsapp-modal .quick-action-btn {
    transition: all 0.2s ease;
}
.whatsapp-modal .quick-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border-color: var(--primary) !important;
}

.whatsapp-modal .provider-card {
    transition: all 0.2s ease;
}
.whatsapp-modal .provider-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* =====================================================
   NOTIFICATION DROPDOWN
   ===================================================== */
.notification-item.unread {
    background-color: rgba(var(--primary-rgb), 0.05);
    border-left: 3px solid var(--primary);
}

.notification-item:hover {
    background-color: rgba(0, 0, 0, 0.02);
}

/* =====================================================
   USER DROPDOWN
   ===================================================== */
.dropdown-menu {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    min-width: 200px;
    border-radius: 8px;
    padding: 8px 0;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    z-index: 1000;
}

.dropdown-menu.show {
    display: block;
}

.dropdown-item {
    display: flex;
    align-items: center;
    padding: 8px 16px;
    color: var(--text-primary);
    text-decoration: none;
    transition: background-color 0.2s ease;
}

.dropdown-item:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

/* =====================================================
   THEME SETTINGS MODAL STYLES
   ===================================================== */
.theme-option-compact.active,
.appearance-option-compact.active {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 2px var(--primary);
}

.theme-option-compact,
.appearance-option-compact {
    transition: all 0.2s ease;
}

.theme-option-compact:hover,
.appearance-option-compact:hover {
    transform: translateY(-2px);
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

@media (max-width: 768px) {
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
}
</style>