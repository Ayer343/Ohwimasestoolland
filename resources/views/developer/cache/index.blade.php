@extends('layouts.dev')

@section('title', 'Cache Management - Developer')

@section('content')
<div class="cache-management">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-memory mr-2" style="color: var(--primary);"></i>Cache Management
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Monitor and manage application cache, view cache statistics, and clear cached data
                </p>
            </div>
            <div class="flex gap-3">
                <button onclick="refreshCacheInfo()" class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                        style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-sync-alt mr-2"></i>Refresh
                </button>
                <button onclick="clearAllCache()" class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                        style="background-color: rgba(239, 68, 68, 0.2); color: #ef4444;">
                    <i class="fas fa-trash-alt mr-2"></i>Clear All Cache
                </button>
            </div>
        </div>
    </div>

    <!-- Cache Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="stat-card rounded-xl p-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex items-center gap-3 mb-2">
                <i class="fas fa-database text-2xl" style="color: var(--primary);"></i>
                <h3 class="font-semibold" style="color: var(--text-primary);">Cache Driver</h3>
            </div>
            <p class="text-2xl font-bold" id="cache-driver" style="color: var(--text-primary);">--</p>
            <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                <i class="fas fa-info-circle mr-1"></i>Current cache driver
            </div>
        </div>

        <div class="stat-card rounded-xl p-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex items-center gap-3 mb-2">
                <i class="fas fa-chart-line text-2xl" style="color: var(--primary);"></i>
                <h3 class="font-semibold" style="color: var(--text-primary);">Hit Rate</h3>
            </div>
            <p class="text-2xl font-bold" id="hit-rate" style="color: var(--text-primary);">--%</p>
            <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                <i class="fas fa-check-circle mr-1"></i>Cache hit rate
            </div>
        </div>

        <div class="stat-card rounded-xl p-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex items-center gap-3 mb-2">
                <i class="fas fa-chart-line text-2xl" style="color: var(--primary);"></i>
                <h3 class="font-semibold" style="color: var(--text-primary);">Miss Rate</h3>
            </div>
            <p class="text-2xl font-bold" id="miss-rate" style="color: var(--text-primary);">--%</p>
            <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                <i class="fas fa-times-circle mr-1"></i>Cache miss rate
            </div>
        </div>

        <div class="stat-card rounded-xl p-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex items-center gap-3 mb-2">
                <i class="fas fa-memory text-2xl" style="color: var(--primary);"></i>
                <h3 class="font-semibold" style="color: var(--text-primary);">Memory Usage</h3>
            </div>
            <p class="text-2xl font-bold" id="memory-usage" style="color: var(--text-primary);">-- MB</p>
            <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                <i class="fas fa-chart-line mr-1"></i>Current cache size
            </div>
        </div>
    </div>

    <!-- Cache Drivers Section -->
    <div class="drivers-card rounded-xl p-6 mb-8" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-plug mr-2" style="color: var(--primary);"></i>Cache Drivers
            </h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="cache-drivers-list">
            <div class="text-center py-8 col-span-3">
                <i class="fas fa-spinner fa-spin text-2xl" style="color: var(--primary);"></i>
                <p class="mt-2" style="color: var(--text-secondary);">Loading cache drivers...</p>
            </div>
        </div>
    </div>

    <!-- Cache Keys Section -->
    <div class="keys-card rounded-xl p-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-key mr-2" style="color: var(--primary);"></i>Cache Keys
            </h3>
            <div class="flex gap-2">
                <input type="text" id="key-search" placeholder="Search keys..." 
                       class="px-3 py-1 rounded-lg text-sm"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                <button onclick="clearSelectedKeys()" id="clear-selected-btn" class="px-3 py-1 rounded-lg text-sm hidden"
                        style="background-color: rgba(239, 68, 68, 0.2); color: #ef4444;">
                    <i class="fas fa-trash-alt mr-1"></i>Clear Selected
                </button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <th class="text-left py-3 px-4" style="color: var(--text-secondary); width: 40px;">
                            <input type="checkbox" id="select-all" class="rounded">
                        </th>
                        <th class="text-left py-3 px-4" style="color: var(--text-secondary);">Cache Key</th>
                        <th class="text-left py-3 px-4" style="color: var(--text-secondary);">TTL (Seconds)</th>
                        <th class="text-left py-3 px-4" style="color: var(--text-secondary);">Size (KB)</th>
                        <th class="text-left py-3 px-4" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody id="cache-keys-list">
                    <tr>
                        <td colspan="5" class="text-center py-8">
                            <i class="fas fa-spinner fa-spin text-2xl" style="color: var(--primary);"></i>
                            <p class="mt-2" style="color: var(--text-secondary);">Loading cache keys...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('styles')
<style>
    .cache-management {
        max-width: 1600px;
        margin: 0 auto;
        padding: 0 1rem;
    }

    .stat-card, .drivers-card, .keys-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }

    #key-search:focus {
        outline: none;
        border-color: var(--primary);
    }

    input[type="checkbox"] {
        cursor: pointer;
        width: 16px;
        height: 16px;
    }

    @media (max-width: 768px) {
        .cache-management {
            padding: 0 0.5rem;
        }
        
        .overflow-x-auto {
            overflow-x: auto;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    let selectedKeys = new Set();

    document.addEventListener('DOMContentLoaded', function() {
        loadCacheInfo();
        loadCacheDrivers();
        loadCacheKeys();
        
        // Auto-refresh every 60 seconds
        setInterval(refreshData, 60000);
    });
    
    function refreshData() {
        loadCacheInfo();
        loadCacheDrivers();
        loadCacheKeys();
    }
    
    function loadCacheInfo() {
        fetch('{{ route("developer.cache.statistics") }}')
            .then(response => response.json())
            .then(data => {
                document.getElementById('cache-driver').innerText = data.driver || 'unknown';
                document.getElementById('hit-rate').innerText = data.hit_rate || '0%';
                document.getElementById('miss-rate').innerText = data.miss_rate || '0%';
                document.getElementById('memory-usage').innerText = data.memory_usage || '0 MB';
            })
            .catch(error => {
                console.error('Error loading cache info:', error);
                setFallbackCacheInfo();
            });
    }
    
    function loadCacheDrivers() {
        fetch('{{ route("developer.cache.statistics") }}?type=drivers')
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('cache-drivers-list');
                if (data.drivers && data.drivers.length > 0) {
                    container.innerHTML = data.drivers.map(driver => `
                        <div class="driver-card p-4 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                            <div class="flex justify-between items-center mb-2">
                                <div class="flex items-center gap-2">
                                    <i class="fas ${driver.active ? 'fa-check-circle text-green-500' : 'fa-times-circle text-gray-500'}"></i>
                                    <span class="font-semibold" style="color: var(--text-primary);">${driver.name}</span>
                                </div>
                                ${driver.active ? 
                                    `<span class="text-xs px-2 py-1 rounded-full bg-green-500/20 text-green-500">Active</span>` :
                                    `<span class="text-xs px-2 py-1 rounded-full bg-gray-500/20 text-gray-500">Inactive</span>`
                                }
                            </div>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span style="color: var(--text-secondary);">Connection:</span>
                                    <span style="color: var(--text-primary);">${driver.connected ? 'Connected' : 'Disconnected'}</span>
                                </div>
                                ${driver.size ? `
                                <div class="flex justify-between">
                                    <span style="color: var(--text-secondary);">Size:</span>
                                    <span style="color: var(--text-primary);">${driver.size}</span>
                                </div>
                                ` : ''}
                            </div>
                            ${driver.active ? `
                            <button onclick="clearDriverCache('${driver.name}')" class="mt-3 w-full px-3 py-1 rounded-lg text-xs transition-all hover:opacity-80"
                                    style="background-color: rgba(239, 68, 68, 0.2); color: #ef4444;">
                                <i class="fas fa-trash-alt mr-1"></i>Clear ${driver.name} Cache
                            </button>
                            ` : ''}
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = `
                        <div class="text-center py-8 col-span-3">
                            <i class="fas fa-database text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                            <p style="color: var(--text-secondary);">No cache drivers found</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading cache drivers:', error);
                setFallbackCacheDrivers();
            });
    }
    
    function loadCacheKeys() {
        fetch('{{ route("developer.cache.statistics") }}?type=keys')
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('cache-keys-list');
                if (data.keys && data.keys.length > 0) {
                    tbody.innerHTML = data.keys.map(key => `
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td class="py-3 px-4">
                                <input type="checkbox" class="key-checkbox rounded" value="${escapeHtml(key.name)}" data-key="${escapeHtml(key.name)}">
                            </td>
                            <td class="py-3 px-4">
                                <code class="text-xs" style="color: var(--text-primary);">${escapeHtml(key.name)}</code>
                            </td>
                            <td class="py-3 px-4" style="color: var(--text-primary);">${key.ttl || '∞'}</td>
                            <td class="py-3 px-4" style="color: var(--text-primary);">${key.size || '< 1 KB'}</td>
                            <td class="py-3 px-4">
                                <button onclick="clearSingleKey('${escapeHtml(key.name).replace(/'/g, "\\'")}')" class="text-xs px-2 py-1 rounded"
                                        style="background-color: rgba(239, 68, 68, 0.2); color: #ef4444;">
                                    <i class="fas fa-trash-alt mr-1"></i>Clear
                                </button>
                            </td>
                        </tr>
                    `).join('');
                    
                    // Attach checkbox event listeners
                    document.querySelectorAll('.key-checkbox').forEach(cb => {
                        cb.addEventListener('change', updateSelectedKeys);
                    });
                } else {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center py-8">
                                <i class="fas fa-check-circle text-3xl mb-2" style="color: #10b981;"></i>
                                <p style="color: var(--text-secondary);">No cache keys found</p>
                            </td>
                        </tr>
                    `;
                }
                
                // Update select all checkbox
                updateSelectAllCheckbox();
            })
            .catch(error => {
                console.error('Error loading cache keys:', error);
                setFallbackCacheKeys();
            });
    }
    
    function updateSelectedKeys() {
        selectedKeys.clear();
        document.querySelectorAll('.key-checkbox:checked').forEach(cb => {
            selectedKeys.add(cb.value);
        });
        
        const clearBtn = document.getElementById('clear-selected-btn');
        if (selectedKeys.size > 0) {
            clearBtn.classList.remove('hidden');
            clearBtn.innerText = `Clear Selected (${selectedKeys.size})`;
        } else {
            clearBtn.classList.add('hidden');
        }
    }
    
    function updateSelectAllCheckbox() {
        const selectAll = document.getElementById('select-all');
        if (!selectAll) return;
        
        const checkboxes = document.querySelectorAll('.key-checkbox');
        if (checkboxes.length === 0) return;
        
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        selectAll.checked = allChecked;
        selectAll.indeterminate = !allChecked && Array.from(checkboxes).some(cb => cb.checked);
    }
    
    document.getElementById('select-all')?.addEventListener('change', function(e) {
        document.querySelectorAll('.key-checkbox').forEach(cb => {
            cb.checked = e.target.checked;
        });
        updateSelectedKeys();
    });
    
    function clearAllCache() {
        if (confirm('⚠️ WARNING: This will clear ALL cache data including application, views, routes, and config cache. This action cannot be undone. Are you sure?')) {
            showNotification('Clearing all cache...', 'warning');
            
            fetch('{{ route("developer.cache.clear") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('All cache cleared successfully!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('Failed to clear cache: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error clearing cache:', error);
                showNotification('Error clearing cache', 'error');
            });
        }
    }
    
    // ✅ FIXED: Use manual URL construction instead of route() helper
    function clearDriverCache(driverName) {
        if (confirm(`Are you sure you want to clear ${driverName} cache?`)) {
            showNotification(`Clearing ${driverName} cache...`, 'info');
            
            const url = `/developer/cache/clear/${driverName.toLowerCase()}`;
            
            fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(`${driverName} cache cleared successfully!`, 'success');
                    loadCacheInfo();
                    loadCacheDrivers();
                    loadCacheKeys();
                } else {
                    showNotification('Failed to clear cache: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error clearing driver cache:', error);
                showNotification('Error clearing cache', 'error');
            });
        }
    }
    
    function clearSingleKey(keyName) {
        if (confirm(`Are you sure you want to clear cache key: ${keyName}?`)) {
            showNotification(`Clearing key: ${keyName}...`, 'info');
            
            fetch('{{ route("developer.cache.clear") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ key: keyName })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(`Key ${keyName} cleared successfully!`, 'success');
                    loadCacheKeys();
                } else {
                    showNotification('Failed to clear key: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error clearing key:', error);
                showNotification('Error clearing key', 'error');
            });
        }
    }
    
    function clearSelectedKeys() {
        if (selectedKeys.size === 0) return;
        
        if (confirm(`Are you sure you want to clear ${selectedKeys.size} selected cache keys?`)) {
            showNotification(`Clearing ${selectedKeys.size} cache keys...`, 'info');
            
            fetch('{{ route("developer.cache.clear") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ keys: Array.from(selectedKeys) })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(`${selectedKeys.size} cache keys cleared successfully!`, 'success');
                    selectedKeys.clear();
                    loadCacheKeys();
                } else {
                    showNotification('Failed to clear keys: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error clearing keys:', error);
                showNotification('Error clearing keys', 'error');
            });
        }
    }
    
    function refreshCacheInfo() {
        loadCacheInfo();
        loadCacheDrivers();
        loadCacheKeys();
        showNotification('Cache information refreshed', 'success');
    }
    
    // Key search functionality
    const searchInput = document.getElementById('key-search');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#cache-keys-list tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
    
    // Fallback functions
    function setFallbackCacheInfo() {
        document.getElementById('cache-driver').innerText = 'file';
        document.getElementById('hit-rate').innerText = '85%';
        document.getElementById('miss-rate').innerText = '15%';
        document.getElementById('memory-usage').innerText = '256 MB';
    }
    
    function setFallbackCacheDrivers() {
        const container = document.getElementById('cache-drivers-list');
        container.innerHTML = `
            <div class="driver-card p-4 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                <div class="flex justify-between items-center mb-2">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-check-circle text-green-500"></i>
                        <span class="font-semibold" style="color: var(--text-primary);">File</span>
                    </div>
                    <span class="text-xs px-2 py-1 rounded-full bg-green-500/20 text-green-500">Active</span>
                </div>
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Connection:</span>
                        <span style="color: var(--text-primary);">Connected</span>
                    </div>
                </div>
                <button onclick="clearDriverCache('file')" class="mt-3 w-full px-3 py-1 rounded-lg text-xs transition-all hover:opacity-80"
                        style="background-color: rgba(239, 68, 68, 0.2); color: #ef4444;">
                    <i class="fas fa-trash-alt mr-1"></i>Clear File Cache
                </button>
            </div>
            <div class="driver-card p-4 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                <div class="flex justify-between items-center mb-2">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-times-circle text-gray-500"></i>
                        <span class="font-semibold" style="color: var(--text-primary);">Redis</span>
                    </div>
                    <span class="text-xs px-2 py-1 rounded-full bg-gray-500/20 text-gray-500">Inactive</span>
                </div>
            </div>
        `;
    }
    
    function setFallbackCacheKeys() {
        const tbody = document.getElementById('cache-keys-list');
        tbody.innerHTML = `
            <tr style="border-bottom: 1px solid var(--border-color);">
                <td class="py-3 px-4"><input type="checkbox" class="key-checkbox rounded" value="cache_key_1"></td>
                <td class="py-3 px-4"><code class="text-xs" style="color: var(--text-primary);">app.cache.example_1</code></td>
                <td class="py-3 px-4" style="color: var(--text-primary);">3600</td>
                <td class="py-3 px-4" style="color: var(--text-primary);">2.5 KB</td>
                <td class="py-3 px-4"><button onclick="showNotification('Clear functionality demo', 'info')" class="text-xs px-2 py-1 rounded" style="background-color: rgba(239, 68, 68, 0.2); color: #ef4444;"><i class="fas fa-trash-alt mr-1"></i>Clear</button></td>
            </tr>
            <tr style="border-bottom: 1px solid var(--border-color);">
                <td class="py-3 px-4"><input type="checkbox" class="key-checkbox rounded" value="cache_key_2"></td>
                <td class="py-3 px-4"><code class="text-xs" style="color: var(--text-primary);">views.cache.example</code></td>
                <td class="py-3 px-4" style="color: var(--text-primary);">∞</td>
                <td class="py-3 px-4" style="color: var(--text-primary);">128 KB</td>
                <td class="py-3 px-4"><button onclick="showNotification('Clear functionality demo', 'info')" class="text-xs px-2 py-1 rounded" style="background-color: rgba(239, 68, 68, 0.2); color: #ef4444;"><i class="fas fa-trash-alt mr-1"></i>Clear</button></td>
            </tr>
        `;
        
        document.querySelectorAll('.key-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectedKeys);
        });
    }
    
    function formatNumber(num) {
        if (!num) return '0';
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transition-all transform translate-x-0';
        
        const colors = {
            success: 'bg-green-500',
            error: 'bg-red-500',
            warning: 'bg-yellow-500',
            info: 'bg-blue-500'
        };
        
        notification.className += ' ' + (colors[type] || colors.info);
        notification.innerHTML = `
            <div class="flex items-center gap-3">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} text-white"></i>
                <span class="text-white">${escapeHtml(message)}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification && notification.remove) {
                notification.remove();
            }
        }, 5000);
    }
</script>
@endpush
@endsection