<div id="themeSettingsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="theme-modal-compact">
        <div class="theme-modal-header-compact">
            <h3>
                <i class="fas fa-palette mr-2"></i>
                Theme Settings
            </h3>
            <button id="closeThemeModal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="theme-modal-body-compact">
            <!-- Appearance Section -->
            <div class="mb-6">
                <h4 class="section-header-compact">
                    <i class="fas fa-desktop"></i>
                    Appearance
                </h4>
                <p class="section-description-compact">
                    Choose how your dashboard looks
                </p>
                <div class="appearance-grid-compact">
                    <div class="appearance-option-compact active" data-theme="system">
                        <div class="appearance-icon-compact bg-gradient-to-r from-blue-500 to-purple-600">
                            <i class="fas fa-desktop"></i>
                        </div>
                        <span class="appearance-label-compact">System</span>
                    </div>
                    <div class="appearance-option-compact" data-theme="light">
                        <div class="appearance-icon-compact bg-gradient-to-r from-yellow-400 to-orange-500">
                            <i class="fas fa-sun"></i>
                        </div>
                        <span class="appearance-label-compact">Light</span>
                    </div>
                    <div class="appearance-option-compact" data-theme="dark">
                        <div class="appearance-icon-compact bg-gradient-to-r from-gray-700 to-gray-900">
                            <i class="fas fa-moon"></i>
                        </div>
                        <span class="appearance-label-compact">Dark</span>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar Theme Section -->
            <div class="mb-6">
                <h4 class="section-header-compact">
                    <i class="fas fa-paint-brush"></i>
                    Sidebar Theme
                </h4>
                <p class="section-description-compact">
                    Customize your sidebar appearance
                </p>
                <div class="theme-grid-compact">
                    <div class="theme-option-compact active" data-theme="default">
                        <div class="theme-preview-compact bg-gradient-to-b from-purple-500 to-purple-700"></div>
                        <span class="theme-label-compact">Default</span>
                    </div>
                    <div class="theme-option-compact" data-theme="dark">
                        <div class="theme-preview-compact bg-gradient-to-b from-gray-800 to-gray-900"></div>
                        <span class="theme-label-compact">Dark</span>
                    </div>
                    <div class="theme-option-compact" data-theme="light">
                        <div class="theme-preview-compact bg-gradient-to-b from-white to-gray-100 border border-gray-300"></div>
                        <span class="theme-label-compact">Light</span>
                    </div>
                    <div class="theme-option-compact" data-theme="blue">
                        <div class="theme-preview-compact bg-gradient-to-b from-blue-600 to-blue-800"></div>
                        <span class="theme-label-compact">Blue</span>
                    </div>
                    <div class="theme-option-compact" data-theme="green">
                        <div class="theme-preview-compact bg-gradient-to-b from-green-600 to-green-800"></div>
                        <span class="theme-label-compact">Green</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="theme-modal-footer-compact">
            <button id="closeThemeModalBtn">Close</button>
        </div>
    </div>
</div>