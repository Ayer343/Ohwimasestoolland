<div id="cacheClearModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-[var(--card-bg)] rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-[var(--card-bg)] px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <!-- Header -->
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-gradient-to-br from-yellow-500 to-orange-500 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-broom text-white"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-semibold text-[var(--text-primary)]" id="modal-title">
                            Clear System Cache
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-[var(--text-secondary)]">
                                Select which cache types you want to clear. This action cannot be undone.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Cache Options -->
                <div class="mt-6 space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <!-- Application Cache -->
                        <div class="cache-option" data-type="application">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 rounded-lg flex items-center justify-center mr-3 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-400">
                                    <i class="fas fa-box"></i>
                                </div>
                                <div>
                                    <span class="font-medium text-[var(--text-primary)]">Application</span>
                                    <p class="text-xs text-[var(--text-secondary)]">Main cache</p>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="cache-application" class="cache-checkbox" data-type="application">
                                <label for="cache-application" class="sr-only">Clear application cache</label>
                            </div>
                        </div>

                        <!-- Config Cache -->
                        <div class="cache-option" data-type="config">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 rounded-lg flex items-center justify-center mr-3 bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400">
                                    <i class="fas fa-cogs"></i>
                                </div>
                                <div>
                                    <span class="font-medium text-[var(--text-primary)]">Config</span>
                                    <p class="text-xs text-[var(--text-secondary)]">Configuration files</p>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="cache-config" class="cache-checkbox" data-type="config">
                                <label for="cache-config" class="sr-only">Clear config cache</label>
                            </div>
                        </div>

                        <!-- Route Cache -->
                        <div class="cache-option" data-type="route">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 rounded-lg flex items-center justify-center mr-3 bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400">
                                    <i class="fas fa-route"></i>
                                </div>
                                <div>
                                    <span class="font-medium text-[var(--text-primary)]">Route</span>
                                    <p class="text-xs text-[var(--text-secondary)]">Route definitions</p>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="cache-route" class="cache-checkbox" data-type="route">
                                <label for="cache-route" class="sr-only">Clear route cache</label>
                            </div>
                        </div>

                        <!-- View Cache -->
                        <div class="cache-option" data-type="view">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 rounded-lg flex items-center justify-center mr-3 bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400">
                                    <i class="fas fa-eye"></i>
                                </div>
                                <div>
                                    <span class="font-medium text-[var(--text-primary)]">View</span>
                                    <p class="text-xs text-[var(--text-secondary)]">Compiled views</p>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="cache-view" class="cache-checkbox" data-type="view">
                                <label for="cache-view" class="sr-only">Clear view cache</label>
                            </div>
                        </div>
                    </div>

                    <!-- Select All Option -->
                    <div class="mt-4">
                        <div class="flex items-center justify-between p-3 rounded-lg border border-[var(--border-color)]">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 rounded-lg flex items-center justify-center mr-3 bg-gradient-to-br from-red-500 to-pink-500 text-white">
                                    <i class="fas fa-fire"></i>
                                </div>
                                <div>
                                    <span class="font-medium text-[var(--text-primary)]">Clear All Cache</span>
                                    <p class="text-xs text-[var(--text-secondary)]">Clear everything (recommended for production)</p>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="cache-all" class="cache-checkbox" data-type="all">
                                <label for="cache-all" class="sr-only">Clear all cache</label>
                            </div>
                        </div>
                    </div>

                    <!-- Cache Statistics -->
                    <div class="mt-4 p-3 rounded-lg border border-[var(--border-color)] bg-[var(--bg-secondary)]">
                        <h4 class="font-medium text-sm text-[var(--text-primary)] mb-2">Cache Statistics</h4>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-[var(--text-secondary)]">Cache Size:</span>
                                <span class="font-mono text-[var(--text-primary)]" id="cacheSizeStat">Loading...</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[var(--text-secondary)]">Driver:</span>
                                <span class="font-mono text-[var(--text-primary)]" id="cacheDriverStat">Loading...</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[var(--text-secondary)]">Items:</span>
                                <span class="font-mono text-[var(--text-primary)]" id="cacheItemsStat">Loading...</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[var(--text-secondary)]">Last Cleared:</span>
                                <span class="font-mono text-[var(--text-primary)]" id="cacheLastCleared">Never</span>
                            </div>
                        </div>
                    </div>

                    <!-- Warning Message -->
                    <div class="mt-4 p-3 rounded-lg border border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-900/20">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Warning</h3>
                                <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                    <p>Clearing cache may temporarily slow down the application while cache is being rebuilt.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-[var(--bg-secondary)] px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" onclick="executeCacheClear()" class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-gradient-to-r from-yellow-500 to-orange-500 text-base font-medium text-white hover:from-yellow-600 hover:to-orange-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 sm:ml-3 sm:w-auto sm:text-sm transition-all duration-200">
                    <i class="fas fa-play mr-2"></i>
                    Clear Selected Cache
                </button>
                <button type="button" onclick="closeCacheModal()" class="mt-3 w-full inline-flex justify-center rounded-lg border border-[var(--border-color)] shadow-sm px-4 py-2 bg-[var(--bg-secondary)] text-base font-medium text-[var(--text-primary)] hover:bg-[var(--border-color)] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[var(--primary)] sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-all duration-200">
                    <i class="fas fa-times mr-2"></i>
                    Cancel
                </button>
                <button type="button" onclick="loadCacheStats()" class="mt-3 w-full inline-flex justify-center rounded-lg border border-[var(--border-color)] shadow-sm px-4 py-2 bg-[var(--bg-secondary)] text-base font-medium text-[var(--text-primary)] hover:bg-[var(--border-color)] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[var(--primary)] sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-all duration-200">
                    <i class="fas fa-sync-alt mr-2"></i>
                    Refresh Stats
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.cache-option {
    @apply flex items-center justify-between p-3 rounded-lg border border-[var(--border-color)] cursor-pointer transition-all duration-200;
}

.cache-option:hover {
    @apply border-[var(--primary)] bg-[var(--bg-secondary)];
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.cache-option.selected {
    @apply border-[var(--primary)] bg-gradient-to-r from-yellow-50 to-orange-50 dark:from-yellow-900/10 dark:to-orange-900/10;
}

.cache-checkbox {
    @apply h-5 w-5 text-[var(--primary)] focus:ring-[var(--primary)] border-[var(--border-color)] rounded;
}

.cache-checkbox:checked {
    @apply bg-gradient-to-r from-yellow-500 to-orange-500 border-transparent;
}

/* Animation for clearing cache */
@keyframes spin-slow {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.cache-clearing {
    animation: spin-slow 2s linear infinite;
}

/* Modal animations */
@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

#cacheClearModal > div > div {
    animation: modalFadeIn 0.3s ease-out;
}
</style>

<script>
let selectedCacheTypes = [];

function showCacheClearModal() {
    const modal = document.getElementById('cacheClearModal');
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    
    // Load cache statistics
    loadCacheStats();
    
    // Reset selections
    selectedCacheTypes = [];
    document.querySelectorAll('.cache-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
    document.querySelectorAll('.cache-option').forEach(option => {
        option.classList.remove('selected');
    });
    
    // Focus on modal
    setTimeout(() => {
        modal.focus();
    }, 100);
}

function closeCacheModal() {
    const modal = document.getElementById('cacheClearModal');
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

// Close modal when clicking outside
document.getElementById('cacheClearModal').addEventListener('click', function(e) {
    if (e.target.id === 'cacheClearModal') {
        closeCacheModal();
    }
});

// Handle checkbox changes
document.querySelectorAll('.cache-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const type = this.dataset.type;
        const option = this.closest('.cache-option');
        
        if (this.checked) {
            if (type === 'all') {
                // Select all other checkboxes
                document.querySelectorAll('.cache-checkbox').forEach(cb => {
                    if (cb !== this) {
                        cb.checked = true;
                        cb.closest('.cache-option').classList.add('selected');
                        if (!selectedCacheTypes.includes(cb.dataset.type)) {
                            selectedCacheTypes.push(cb.dataset.type);
                        }
                    }
                });
            }
            
            if (!selectedCacheTypes.includes(type)) {
                selectedCacheTypes.push(type);
            }
            option.classList.add('selected');
        } else {
            if (type === 'all') {
                // Unselect all other checkboxes
                document.querySelectorAll('.cache-checkbox').forEach(cb => {
                    if (cb !== this) {
                        cb.checked = false;
                        cb.closest('.cache-option').classList.remove('selected');
                    }
                });
                selectedCacheTypes = [];
            } else {
                // Uncheck "all" if any individual is unchecked
                const allCheckbox = document.getElementById('cache-all');
                if (allCheckbox.checked) {
                    allCheckbox.checked = false;
                    allCheckbox.closest('.cache-option').classList.remove('selected');
                }
                
                selectedCacheTypes = selectedCacheTypes.filter(t => t !== type);
            }
            option.classList.remove('selected');
        }
        
        updateSelectedCount();
    });
});

// Handle option clicks (toggle checkbox)
document.querySelectorAll('.cache-option').forEach(option => {
    option.addEventListener('click', function(e) {
        if (!e.target.classList.contains('cache-checkbox')) {
            const checkbox = this.querySelector('.cache-checkbox');
            checkbox.checked = !checkbox.checked;
            checkbox.dispatchEvent(new Event('change'));
        }
    });
});

function updateSelectedCount() {
    const count = selectedCacheTypes.length;
    const btn = document.querySelector('[onclick="executeCacheClear()"]');
    
    if (count === 0) {
        btn.innerHTML = '<i class="fas fa-play mr-2"></i> Clear Selected Cache';
        btn.disabled = true;
        btn.classList.add('opacity-50', 'cursor-not-allowed');
    } else {
        btn.innerHTML = `<i class="fas fa-play mr-2"></i> Clear Cache (${count} selected)`;
        btn.disabled = false;
        btn.classList.remove('opacity-50', 'cursor-not-allowed');
    }
}

async function loadCacheStats() {
    try {
        const statsElements = {
            cacheSizeStat: document.getElementById('cacheSizeStat'),
            cacheDriverStat: document.getElementById('cacheDriverStat'),
            cacheItemsStat: document.getElementById('cacheItemsStat'),
            cacheLastCleared: document.getElementById('cacheLastCleared')
        };
        
        // Show loading state
        Object.values(statsElements).forEach(el => {
            if (el) el.textContent = 'Loading...';
        });
        
        // Fetch cache statistics from server
        const response = await fetch('{{ route("developer.system-monitor.cache-stats") }}');
        if (!response.ok) throw new Error('Failed to load cache stats');
        
        const data = await response.json();
        
        // Update statistics display
        if (statsElements.cacheSizeStat) {
            statsElements.cacheSizeStat.textContent = data.size || 'Unknown';
        }
        
        if (statsElements.cacheDriverStat) {
            statsElements.cacheDriverStat.textContent = data.driver || 'Unknown';
        }
        
        if (statsElements.cacheItemsStat) {
            statsElements.cacheItemsStat.textContent = data.items ? data.items.toString() : 'Unknown';
        }
        
        if (statsElements.cacheLastCleared && data.last_cleared) {
            const date = new Date(data.last_cleared);
            statsElements.cacheLastCleared.textContent = formatTimeAgo(date);
        }
        
    } catch (error) {
        console.error('Failed to load cache statistics:', error);
        
        // Show error state
        const statsElements = {
            cacheSizeStat: document.getElementById('cacheSizeStat'),
            cacheDriverStat: document.getElementById('cacheDriverStat'),
            cacheItemsStat: document.getElementById('cacheItemsStat'),
            cacheLastCleared: document.getElementById('cacheLastCleared')
        };
        
        Object.values(statsElements).forEach(el => {
            if (el) el.textContent = 'Error';
            if (el) el.style.color = 'var(--danger)';
        });
    }
}

async function executeCacheClear() {
    if (selectedCacheTypes.length === 0) {
        showNotification('Please select at least one cache type to clear', 'warning');
        return;
    }
    
    // Determine which type to clear
    let clearType = 'all';
    if (selectedCacheTypes.length === 1 && selectedCacheTypes[0] !== 'all') {
        clearType = selectedCacheTypes[0];
    }
    
    if (!confirm(`Are you sure you want to clear ${clearType === 'all' ? 'ALL' : clearType} cache?`)) {
        return;
    }
    
    try {
        // Show loading state
        const clearBtn = document.querySelector('[onclick="executeCacheClear()"]');
        const originalText = clearBtn.innerHTML;
        clearBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Clearing...';
        clearBtn.disabled = true;
        clearBtn.classList.add('opacity-50', 'cursor-not-allowed');
        
        // Call the cache clear endpoint
        const response = await fetch(`{{ route("developer.system-monitor.clear-cache", ["type" => ":type"]) }}`.replace(':type', clearType), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        
        const data = await response.json();
        
        // Reset button state
        clearBtn.innerHTML = originalText;
        clearBtn.disabled = false;
        clearBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        
        if (data.success) {
            showNotification(`Cache cleared successfully: ${data.cleared.join(', ')}`, 'success');
            
            // Update last cleared time
            const lastClearedEl = document.getElementById('cacheLastCleared');
            if (lastClearedEl) {
                lastClearedEl.textContent = 'Just now';
                lastClearedEl.style.color = 'var(--success)';
            }
            
            // Reset selections
            selectedCacheTypes = [];
            document.querySelectorAll('.cache-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            document.querySelectorAll('.cache-option').forEach(option => {
                option.classList.remove('selected');
            });
            updateSelectedCount();
            
            // Reload cache stats after a delay
            setTimeout(loadCacheStats, 1000);
            
            // Close modal after successful clear
            setTimeout(closeCacheModal, 1500);
            
        } else {
            showNotification(`Failed to clear cache: ${data.message}`, 'error');
        }
        
    } catch (error) {
        console.error('Cache clear failed:', error);
        showNotification('Failed to clear cache: ' + error.message, 'error');
        
        // Reset button state
        const clearBtn = document.querySelector('[onclick="executeCacheClear()"]');
        clearBtn.innerHTML = '<i class="fas fa-play mr-2"></i> Clear Selected Cache';
        clearBtn.disabled = false;
        clearBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    }
}

function formatTimeAgo(date) {
    const seconds = Math.floor((new Date() - date) / 1000);
    
    if (seconds < 60) return 'Just now';
    
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d ago`;
    
    return date.toLocaleDateString();
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg transform transition-all duration-300 translate-x-full`;
    
    let bgColor = 'bg-blue-500';
    let icon = 'info-circle';
    
    switch(type) {
        case 'success':
            bgColor = 'bg-green-500';
            icon = 'check-circle';
            break;
        case 'error':
            bgColor = 'bg-red-500';
            icon = 'exclamation-circle';
            break;
        case 'warning':
            bgColor = 'bg-yellow-500';
            icon = 'exclamation-triangle';
            break;
    }
    
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-${icon} mr-3 text-white"></i>
            <span class="text-white font-medium">${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    notification.classList.add(bgColor);
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 10);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            if (notification.parentElement) {
                notification.parentElement.removeChild(notification);
            }
        }, 300);
    }, 5000);
}

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('cacheClearModal');
    if (!modal.classList.contains('hidden')) {
        // Escape to close
        if (e.key === 'Escape') {
            closeCacheModal();
        }
        // Enter to execute
        if (e.key === 'Enter' && !e.ctrlKey) {
            e.preventDefault();
            executeCacheClear();
        }
        // Ctrl+A to select all
        if (e.ctrlKey && e.key === 'a') {
            e.preventDefault();
            document.getElementById('cache-all').checked = true;
            document.getElementById('cache-all').dispatchEvent(new Event('change'));
        }
    }
});

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Load cache stats on page load
    setTimeout(loadCacheStats, 1000);
});
</script>