<style>
/* Search Modal Theme Support */
.search-modal-container {
    animation: modalSlideIn 0.3s ease-out;
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

/* Transfer Modal Animation */
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

/* ENHANCED GLOWING RED LIGHT EFFECTS FOR NOTIFICATIONS */
.glowing-red-light {
    position: absolute;
    top: 0;
    right: 0;
    width: 10px;
    height: 10px;
    background-color: #ff4757;
    border-radius: 50%;
    animation: glowingRed 2s infinite;
    box-shadow: 
        0 0 10px #ff4757,
        0 0 20px #ff4757,
        0 0 30px #ff4757,
        inset 0 0 10px rgba(255, 71, 87, 0.5);
    z-index: 1;
    pointer-events: none;
}

.glowing-red-dot {
    position: absolute;
    top: 0;
    right: 0;
    width: 10px;
    height: 10px;
    background-color: #ff4757;
    border-radius: 50%;
    animation: glowingRed 2s infinite;
    box-shadow: 
        0 0 10px #ff4757,
        0 0 20px #ff4757,
        0 0 30px #ff4757,
        inset 0 0 10px rgba(255, 71, 87, 0.5);
    z-index: 1;
}

@keyframes glowingRed {
    0% {
        transform: scale(0.8);
        opacity: 0.8;
        box-shadow: 
            0 0 5px #ff4757,
            0 0 10px #ff4757,
            0 0 15px #ff4757;
    }
    50% {
        transform: scale(1.2);
        opacity: 1;
        box-shadow: 
            0 0 15px #ff4757,
            0 0 25px #ff4757,
            0 0 35px #ff4757,
            0 0 45px #ff4757;
    }
    100% {
        transform: scale(0.8);
        opacity: 0.8;
        box-shadow: 
            0 0 5px #ff4757,
            0 0 10px #ff4757,
            0 0 15px #ff4757;
    }
}

.glowing-badge {
    animation: badgeGlow 2s infinite;
    box-shadow: 
        0 0 5px rgba(239, 68, 68, 0.8),
        0 0 10px rgba(239, 68, 68, 0.6),
        inset 0 0 5px rgba(255, 255, 255, 0.3);
    font-weight: bold;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

@keyframes badgeGlow {
    0% {
        transform: scale(1);
        box-shadow: 
            0 0 5px rgba(239, 68, 68, 0.8),
            0 0 10px rgba(239, 68, 68, 0.6);
    }
    50% {
        transform: scale(1.1);
        box-shadow: 
            0 0 10px rgba(239, 68, 68, 0.9),
            0 0 20px rgba(239, 68, 68, 0.7),
            0 0 30px rgba(239, 68, 68, 0.5);
    }
    100% {
        transform: scale(1);
        box-shadow: 
            0 0 5px rgba(239, 68, 68, 0.8),
            0 0 10px rgba(239, 68, 68, 0.6);
    }
}

.notification-dropdown {
    transform-origin: top right;
}

/* Notification items styling */
.notification-item {
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-color);
    transition: background-color 0.2s ease;
}

.notification-item:hover {
    background-color: rgba(var(--primary-rgb), 0.05);
}

.notification-item.unread {
    background-color: rgba(var(--primary-rgb), 0.1);
}

.notification-item:last-child {
    border-bottom: none;
}

/* Modal Input Focus States */
#globalSearchInput:focus,
#searchCategory:focus,
#transferPropertySelect:focus {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1) !important;
}

/* Search Category Dropdown Styling */
#searchCategory option {
    background-color: var(--card-bg);
    color: var(--text-primary);
}

/* Transfer Property Select Styling */
#transferPropertySelect option {
    background-color: var(--card-bg);
    color: var(--text-primary);
}

/* Checkbox Styling for Theme Support */
input[type="checkbox"] {
    accent-color: var(--primary);
}

/* Recent Search Tags */
.recent-search-tag {
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 4px 12px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-block;
    margin: 2px;
}

.recent-search-tag:hover {
    background-color: rgba(var(--primary-rgb), 0.1);
    border-color: var(--primary);
    color: var(--primary);
}

/* Button Hover Effects */
#performSearch:hover,
#proceedToTransfer:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

#clearSearch:hover,
#cancelSearch:hover,
#cancelTransfer:hover {
    text-decoration: underline;
}

/* Modal Close Button Hover */
#closeSearchModal:hover,
#closeTransferModal:hover,
#closeThemeModal:hover {
    background-color: rgba(var(--primary-rgb), 0.1);
}

/* Enhanced Minimal Avatar Styling */
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

/* Enhanced header spacing for cleaner look */
.header {
    padding: 12px 24px;
}

/* Search bar enhancements */
.header-search {
    min-width: 280px;
}

.header-search:focus {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.1);
}

/* Mobile search button styling */
#mobileSearchBtn {
    padding: 8px;
}

/* Header buttons spacing adjustment */
.header-buttons {
    gap: 8px;
}

/* Dropdown menu positioning adjustment */
.dropdown-menu {
    min-width: 220px;
}

/* Advanced search button hover effect */
#advancedSearchBtn:hover {
    background-color: rgba(0, 0, 0, 0.05);
    transform: scale(1.1);
}

/* Dropdown group styles for ownership transfer */
.dropdown-group {
    position: relative;
}

.dropdown-content {
    position: absolute;
    left: 100%;
    top: 0;
    min-width: 200px;
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    z-index: 10;
    padding: 8px 0;
}

.dropdown-content.hidden {
    display: none;
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
    background-color: rgba(var(--primary-rgb), 0.1);
}

.dropdown-item i {
    margin-right: 12px;
    color: var(--text-secondary);
}

.dropdown-arrow.rotate-180 {
    transform: rotate(180deg);
}

/* Mobile responsiveness adjustments */
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
    }
    
    .notification-dropdown {
        width: 300px !important;
        right: -50px !important;
    }
    
    .dropdown-content {
        position: static;
        left: auto;
        top: auto;
        border: none;
        box-shadow: none;
        padding-left: 32px;
    }
    
    .transfer-modal-container,
    .search-modal-container {
        width: 95% !important;
        margin: 10px;
    }
    
    /* Adjust glowing effects for mobile */
    .glowing-red-light,
    .glowing-red-dot {
        width: 8px;
        height: 8px;
    }
    
    .glowing-badge {
        width: 20px;
        height: 20px;
        font-size: 10px;
    }
}

/* Dark mode specific adjustments */
[data-theme="dark"] .header-search::placeholder {
    color: var(--text-secondary);
}

[data-theme="dark"] #globalSearchInput::placeholder {
    color: var(--text-secondary);
}

[data-theme="dark"] #transferPropertySelect option {
    background-color: #374151;
}

/* Smooth transitions for all interactive elements */
.avatar-minimal,
#advancedSearchBtn,
#mobileSearchBtn,
#performSearch,
#clearSearch,
#cancelSearch,
#closeSearchModal,
#proceedToTransfer,
#cancelTransfer,
#closeTransferModal {
    transition: all 0.2s ease-in-out;
}

/* Modal backdrop blur effect */
#searchModal,
#themeSettingsModal,
#transferModal {
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
}

/* Input placeholder color */
::placeholder {
    color: var(--text-secondary);
    opacity: 0.7;
}

/* Firefox placeholder color */
::-moz-placeholder {
    color: var(--text-secondary);
    opacity: 0.7;
}

/* IE placeholder color */
:-ms-input-placeholder {
    color: var(--text-secondary);
    opacity: 0.7;
}

/* Safari placeholder color */
::-webkit-input-placeholder {
    color: var(--text-secondary);
    opacity: 0.7;
}

/* Scrollbar styling for modal if needed */
.search-modal-container::-webkit-scrollbar,
.transfer-modal-container::-webkit-scrollbar,
.notification-dropdown::-webkit-scrollbar {
    width: 8px;
}

.search-modal-container::-webkit-scrollbar-track,
.transfer-modal-container::-webkit-scrollbar-track,
.notification-dropdown::-webkit-scrollbar-track {
    background: var(--bg-secondary);
    border-radius: 4px;
}

.search-modal-container::-webkit-scrollbar-thumb,
.transfer-modal-container::-webkit-scrollbar-thumb,
.notification-dropdown::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 4px;
}

.search-modal-container::-webkit-scrollbar-thumb:hover,
.transfer-modal-container::-webkit-scrollbar-thumb:hover,
.notification-dropdown::-webkit-scrollbar-thumb:hover {
    background: var(--secondary);
}

/* Select dropdown arrow color */
select {
    color-scheme: light dark;
}

/* Bell icon hover effect */
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

/* Enhanced email icon with glowing effect */
.far.fa-envelope {
    position: relative;
}

.notification-dot {
    position: absolute;
    top: 2px;
    right: 2px;
    width: 8px;
    height: 8px;
    background-color: #ff4757;
    border-radius: 50%;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.has-notifications .notification-dot {
    opacity: 1;
    animation: glowingRed 2s infinite;
}

/* Theme Settings Modal Compact Styles */
.theme-modal-compact {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    width: 90%;
    max-width: 400px;
    max-height: 90vh;
    overflow-y: auto;
    animation: modalFadeIn 0.3s ease-out;
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
    margin-bottom: 4px;
}

.section-description-compact {
    font-size: 12px;
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
}

.appearance-option-compact:hover {
    background-color: rgba(var(--primary-rgb), 0.1);
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
    font-size: 12px;
    font-weight: 500;
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
}

.theme-option-compact:hover {
    background-color: rgba(var(--primary-rgb), 0.1);
}

.theme-preview-compact {
    width: 40px;
    height: 40px;
    border-radius: 6px;
    margin-bottom: 6px;
}

.theme-label-compact {
    font-size: 11px;
    font-weight: 500;
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

/* Active states for theme options */
.appearance-option-compact.active .appearance-icon-compact {
    box-shadow: 0 0 0 2px var(--primary);
}

.theme-option-compact.active .theme-preview-compact {
    box-shadow: 0 0 0 2px var(--primary);
}

/* Additional CSS Variables for theme colors */
:root {
    --light-theme-bg: #ffffff;
    --dark-theme-bg: #1f2937;
}

/* Responsive adjustments for theme modal */
@media (max-width: 640px) {
    .theme-grid-compact {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .appearance-grid-compact {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 480px) {
    .theme-grid-compact {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .appearance-grid-compact {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Transfer form container styling */
#transferFormContainer {
    transition: all 0.3s ease;
}

/* Improve focus states for accessibility */
button:focus-visible,
input:focus-visible,
select:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

/* Ensure proper contrast for disabled states */
button:disabled,
input:disabled,
select:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>