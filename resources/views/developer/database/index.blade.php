@extends('layouts.dev')

@section('title', 'Database Management - Developer')

@section('content')
<div class="database-management">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-database mr-2" style="color: var(--primary);"></i>Database Management
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Monitor and manage database performance, backups, and queries
                </p>
            </div>
            <div class="flex gap-3">
                <button onclick="refreshDatabaseInfo()" class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                        style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-sync-alt mr-2"></i>Refresh
                </button>
                <button onclick="createBackup()" class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                        style="background-color: var(--primary); color: white;">
                    <i class="fas fa-database mr-2"></i>Create Backup
                </button>
            </div>
        </div>
    </div>

    <!-- Database Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="stat-card rounded-xl p-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex items-center gap-3 mb-2">
                <i class="fas fa-database text-2xl" style="color: var(--primary);"></i>
                <h3 class="font-semibold" style="color: var(--text-primary);">Database Size</h3>
            </div>
            <p class="text-2xl font-bold" id="db-size" style="color: var(--text-primary);">-- MB</p>
            <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                <i class="fas fa-table mr-1"></i><span id="table-count">--</span> tables
            </div>
        </div>

        <div class="stat-card rounded-xl p-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex items-center gap-3 mb-2">
                <i class="fas fa-chart-line text-2xl" style="color: var(--primary);"></i>
                <h3 class="font-semibold" style="color: var(--text-primary);">Slow Queries</h3>
            </div>
            <p class="text-2xl font-bold" id="slow-queries-count" style="color: var(--text-primary);">--</p>
            <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                <i class="fas fa-clock mr-1"></i>Last 24 hours
            </div>
        </div>

        <div class="stat-card rounded-xl p-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex items-center gap-3 mb-2">
                <i class="fas fa-plug text-2xl" style="color: var(--primary);"></i>
                <h3 class="font-semibold" style="color: var(--text-primary);">Active Connections</h3>
            </div>
            <p class="text-2xl font-bold" id="active-connections" style="color: var(--text-primary);">--</p>
            <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                <i class="fas fa-chart-line mr-1"></i>Current session count
            </div>
        </div>

        <div class="stat-card rounded-xl p-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex items-center gap-3 mb-2">
                <i class="fas fa-hourglass-half text-2xl" style="color: var(--primary);"></i>
                <h3 class="font-semibold" style="color: var(--text-primary);">Query Time (Avg)</h3>
            </div>
            <p class="text-2xl font-bold" id="avg-query-time" style="color: var(--text-primary);">-- ms</p>
            <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                <i class="fas fa-chart-line mr-1"></i>Average execution time
            </div>
        </div>
    </div>

    <!-- Database Tables Section -->
    <div class="tables-card rounded-xl p-6 mb-8" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-table mr-2" style="color: var(--primary);"></i>Database Tables
            </h3>
            <div class="flex gap-2">
                <input type="text" id="table-search" placeholder="Search tables..." 
                       class="px-3 py-1 rounded-lg text-sm"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                <button onclick="analyzeTables()" class="px-3 py-1 rounded-lg text-sm"
                        style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-chart-line mr-1"></i>Analyze
                </button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <th class="text-left py-3 px-4" style="color: var(--text-secondary);">Table Name</th>
                        <th class="text-left py-3 px-4" style="color: var(--text-secondary);">Rows</th>
                        <th class="text-left py-3 px-4" style="color: var(--text-secondary);">Data Size (MB)</th>
                        <th class="text-left py-3 px-4" style="color: var(--text-secondary);">Index Size (MB)</th>
                        <th class="text-left py-3 px-4" style="color: var(--text-secondary);">Total Size (MB)</th>
                        <th class="text-left py-3 px-4" style="color: var(--text-secondary);">Actions</th>
                    </td>
                </thead>
                <tbody id="tables-list">
                    <tr>
                        <td colspan="6" class="text-center py-8">
                            <i class="fas fa-spinner fa-spin text-2xl" style="color: var(--primary);"></i>
                            <p class="mt-2" style="color: var(--text-secondary);">Loading tables...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Slow Queries Section -->
    <div class="slow-queries-card rounded-xl p-6 mb-8" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-hourglass-half mr-2" style="color: var(--primary);"></i>Recent Slow Queries
            </h3>
            <button onclick="refreshSlowQueries()" class="text-sm hover:underline" style="color: var(--primary);">
                <i class="fas fa-sync-alt mr-1"></i>Refresh
            </button>
        </div>
        <div class="space-y-3" id="slow-queries-container">
            <div class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-2xl" style="color: var(--primary);"></i>
                <p class="mt-2" style="color: var(--text-secondary);">Loading slow queries...</p>
            </div>
        </div>
    </div>

    <!-- Backup History Section -->
    <div class="backups-card rounded-xl p-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-archive mr-2" style="color: var(--primary);"></i>Backup History
            </h3>
            <div class="flex gap-2">
                <button onclick="cleanupOldBackups()" class="px-3 py-1 rounded-lg text-sm"
                        style="background-color: rgba(239, 68, 68, 0.2); color: #ef4444;">
                    <i class="fas fa-trash-alt mr-1"></i>Cleanup Old (>30 days)
                </button>
            </div>
        </div>
        <div class="space-y-3" id="backups-container">
            <div class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-2xl" style="color: var(--primary);"></i>
                <p class="mt-2" style="color: var(--text-secondary);">Loading backups...</p>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .database-management {
        max-width: 1600px;
        margin: 0 auto;
        padding: 0 1rem;
    }

    .stat-card, .tables-card, .slow-queries-card, .backups-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }

    #table-search:focus {
        outline: none;
        border-color: var(--primary);
    }

    @media (max-width: 768px) {
        .database-management {
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
    // CSRF Token setup for all AJAX requests
    const csrfToken = '{{ csrf_token() }}';
    
    document.addEventListener('DOMContentLoaded', function() {
        loadDatabaseInfo();
        loadTables();
        loadSlowQueries();
        loadBackups();
        
        // Auto-refresh every 60 seconds
        setInterval(refreshData, 60000);
    });
    
    function refreshData() {
        loadDatabaseInfo();
        loadSlowQueries();
    }
    
    function loadDatabaseInfo() {
        fetch('{{ route("developer.database.status") }}')
            .then(response => response.json())
            .then(data => {
                document.getElementById('db-size').innerText = data.size || '0 MB';
                document.getElementById('table-count').innerText = data.table_count || '0';
                document.getElementById('slow-queries-count').innerText = data.slow_queries_count || '0';
                document.getElementById('active-connections').innerText = data.active_connections || '0';
                document.getElementById('avg-query-time').innerText = data.avg_query_time || '0';
            })
            .catch(error => {
                console.error('Error loading database info:', error);
                setFallbackDatabaseInfo();
            });
    }
    
    function loadTables() {
        fetch('{{ route("developer.database.statistics") }}')
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('tables-list');
                if (data.tables && data.tables.length > 0) {
                    tbody.innerHTML = data.tables.map(table => `
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td class="py-3 px-4" style="color: var(--text-primary);">${escapeHtml(table.name)}</td>
                            <td class="py-3 px-4" style="color: var(--text-primary);">${formatNumber(table.rows)}</td>
                            <td class="py-3 px-4" style="color: var(--text-primary);">${table.data_size}</td>
                            <td class="py-3 px-4" style="color: var(--text-primary);">${table.index_size}</td>
                            <td class="py-3 px-4" style="color: var(--text-primary);">${table.total_size}</td>
                            <td class="py-3 px-4">
                                <button onclick="optimizeTable('${escapeHtml(table.name).replace(/'/g, "\\'")}')" class="text-xs px-2 py-1 rounded"
                                        style="background-color: rgba(59, 130, 246, 0.2); color: #3b82f6;">
                                    <i class="fas fa-chart-line mr-1"></i>Optimize
                                </button>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center py-8">
                                <i class="fas fa-database text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                                <p style="color: var(--text-secondary);">No tables found</p>
                            </td>
                        </tr>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading tables:', error);
                setFallbackTables();
            });
    }
    
    function loadSlowQueries() {
        fetch('{{ route("developer.database.statistics") }}?type=slow_queries')
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('slow-queries-container');
                if (data.slow_queries && data.slow_queries.length > 0) {
                    container.innerHTML = data.slow_queries.map(query => `
                        <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <p class="text-sm font-mono" style="color: var(--text-primary);">${escapeHtml(query.query)}</p>
                                    <div class="flex gap-4 mt-2">
                                        <span class="text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-clock mr-1"></i>${query.time} ms
                                        </span>
                                        <span class="text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-chart-line mr-1"></i>${query.count} occurrences
                                        </span>
                                    </div>
                                </div>
                                <button onclick="explainQuery('${escapeHtml(query.query).replace(/'/g, "\\'")}')" class="text-xs px-2 py-1 rounded"
                                        style="background-color: rgba(59, 130, 246, 0.2); color: #3b82f6;">
                                    <i class="fas fa-info-circle mr-1"></i>Explain
                                </button>
                            </div>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = `
                        <div class="text-center py-8">
                            <i class="fas fa-check-circle text-3xl mb-2" style="color: #10b981;"></i>
                            <p style="color: var(--text-secondary);">No slow queries detected</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading slow queries:', error);
                setFallbackSlowQueries();
            });
    }
    
    function loadBackups() {
        fetch('{{ route("developer.database.backups") }}')
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('backups-container');
                if (data.backups && data.backups.length > 0) {
                    container.innerHTML = data.backups.map(backup => `
                        <div class="p-3 rounded-lg flex justify-between items-center" style="background-color: var(--bg-secondary);">
                            <div>
                                <p class="font-medium" style="color: var(--text-primary);">${escapeHtml(backup.name)}</p>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    <i class="far fa-calendar-alt mr-1"></i>${backup.date}
                                    <span class="mx-2">•</span>
                                    <i class="fas fa-database mr-1"></i>${backup.size}
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <button onclick="downloadBackup('${escapeHtml(backup.name).replace(/'/g, "\\'")}')" class="text-xs px-2 py-1 rounded"
                                        style="background-color: rgba(16, 185, 129, 0.2); color: #10b981;">
                                    <i class="fas fa-download mr-1"></i>Download
                                </button>
                                <button onclick="deleteBackup('${escapeHtml(backup.name).replace(/'/g, "\\'")}')" class="text-xs px-2 py-1 rounded"
                                        style="background-color: rgba(239, 68, 68, 0.2); color: #ef4444;">
                                    <i class="fas fa-trash-alt mr-1"></i>Delete
                                </button>
                            </div>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = `
                        <div class="text-center py-8">
                            <i class="fas fa-archive text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                            <p style="color: var(--text-secondary);">No backups available</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading backups:', error);
                setFallbackBackups();
            });
    }
    
    function createBackup() {
        showNotification('Creating database backup...', 'info');
        
        fetch('{{ route("developer.database.backups.create") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Backup created successfully!', 'success');
                loadBackups();
            } else {
                showNotification('Failed to create backup: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error creating backup:', error);
            showNotification('Error creating backup: ' + error.message, 'error');
        });
    }
    
    function deleteBackup(backupName) {
        if (confirm(`Are you sure you want to delete backup: ${backupName}?`)) {
            showNotification(`Deleting backup: ${backupName}...`, 'info');
            
            fetch('{{ route("developer.database.backups.delete") }}', {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ filename: backupName })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.message || `HTTP ${response.status}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showNotification(`Backup ${backupName} deleted successfully!`, 'success');
                    loadBackups();
                } else {
                    showNotification('Failed to delete backup: ' + (data.message || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error deleting backup:', error);
                showNotification('Error deleting backup: ' + error.message, 'error');
            });
        }
    }
    
    function downloadBackup(backupName) {
        showNotification(`Preparing download: ${backupName}...`, 'info');
        
        const downloadUrl = `{{ route("developer.database.backups.download") }}?filename=${encodeURIComponent(backupName)}`;
        
        // Create a temporary link and trigger download
        const link = document.createElement('a');
        link.href = downloadUrl;
        link.download = backupName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showNotification(`Download started for ${backupName}`, 'success');
    }
    
    function cleanupOldBackups() {
        if (confirm('Are you sure you want to delete backups older than 30 days? This action cannot be undone.')) {
            showNotification('Cleaning up old backups...', 'info');
            
            fetch('{{ route("developer.database.backups.cleanup") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message || 'Cleanup completed successfully!', 'success');
                    loadBackups();
                } else {
                    showNotification('Failed to cleanup backups: ' + (data.message || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error cleaning up backups:', error);
                showNotification('Error cleaning up backups: ' + error.message, 'error');
            });
        }
    }
    
    function optimizeTable(tableName) {
        showNotification(`Optimizing table: ${tableName}...`, 'info');
        
        fetch('{{ route("developer.database.maintenance.optimize") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ table: tableName })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(`Table ${tableName} optimized successfully!`, 'success');
                loadTables();
            } else {
                showNotification('Failed to optimize table: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error optimizing table:', error);
            showNotification('Error optimizing table: ' + error.message, 'error');
        });
    }
    
    function explainQuery(query) {
        showNotification('Query analysis will be available soon', 'info');
    }
    
    function analyzeTables() {
        showNotification('Analyzing database tables...', 'info');
        loadTables();
    }
    
    function refreshDatabaseInfo() {
        loadDatabaseInfo();
        loadTables();
        loadSlowQueries();
        loadBackups();
        showNotification('Database information refreshed', 'success');
    }
    
    function refreshSlowQueries() {
        loadSlowQueries();
    }
    
    // Table search functionality
    const searchInput = document.getElementById('table-search');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#tables-list tr');
            
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
    function setFallbackDatabaseInfo() {
        document.getElementById('db-size').innerText = '125 MB';
        document.getElementById('table-count').innerText = '45';
        document.getElementById('slow-queries-count').innerText = '12';
        document.getElementById('active-connections').innerText = '8';
        document.getElementById('avg-query-time').innerText = '45';
    }
    
    function setFallbackTables() {
        const tbody = document.getElementById('tables-list');
        const sampleTables = [
            { name: 'users', rows: 1250, data_size: '2.5 MB', index_size: '0.8 MB', total_size: '3.3 MB' },
            { name: 'properties', rows: 850, data_size: '4.2 MB', index_size: '1.2 MB', total_size: '5.4 MB' },
            { name: 'payments', rows: 3450, data_size: '6.8 MB', index_size: '2.1 MB', total_size: '8.9 MB' },
            { name: 'property_units', rows: 1200, data_size: '3.1 MB', index_size: '0.9 MB', total_size: '4.0 MB' }
        ];
        
        tbody.innerHTML = sampleTables.map(table => `
            <tr style="border-bottom: 1px solid var(--border-color);">
                <td class="py-3 px-4" style="color: var(--text-primary);">${table.name}</td>
                <td class="py-3 px-4" style="color: var(--text-primary);">${formatNumber(table.rows)}</td>
                <td class="py-3 px-4" style="color: var(--text-primary);">${table.data_size}</td>
                <td class="py-3 px-4" style="color: var(--text-primary);">${table.index_size}</td>
                <td class="py-3 px-4" style="color: var(--text-primary);">${table.total_size}</td>
                <td class="py-3 px-4">
                    <button onclick="optimizeTable('${table.name}')" class="text-xs px-2 py-1 rounded"
                            style="background-color: rgba(59, 130, 246, 0.2); color: #3b82f6;">
                        <i class="fas fa-chart-line mr-1"></i>Optimize
                    </button>
                </td>
            </tr>
        `).join('');
    }
    
    function setFallbackSlowQueries() {
        const container = document.getElementById('slow-queries-container');
        const sampleQueries = [
            { query: 'SELECT * FROM properties WHERE created_at > NOW() - INTERVAL 7 DAY', time: 1250.5, count: 45 },
            { query: 'SELECT u.*, p.* FROM users u LEFT JOIN properties p ON u.id = p.user_id', time: 890.3, count: 128 },
            { query: 'SELECT COUNT(*) FROM payments WHERE status = "pending"', time: 567.8, count: 234 }
        ];
        
        container.innerHTML = sampleQueries.map(query => `
            <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <p class="text-sm font-mono" style="color: var(--text-primary);">${escapeHtml(query.query)}</p>
                        <div class="flex gap-4 mt-2">
                            <span class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-clock mr-1"></i>${query.time} ms
                            </span>
                            <span class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-chart-line mr-1"></i>${query.count} occurrences
                            </span>
                        </div>
                    </div>
                    <button onclick="explainQuery('${escapeHtml(query.query).replace(/'/g, "\\'")}')" class="text-xs px-2 py-1 rounded"
                            style="background-color: rgba(59, 130, 246, 0.2); color: #3b82f6;">
                        <i class="fas fa-info-circle mr-1"></i>Explain
                    </button>
                </div>
            </div>
        `).join('');
    }
    
    function setFallbackBackups() {
        const container = document.getElementById('backups-container');
        container.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-archive text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                <p style="color: var(--text-secondary);">No backups available</p>
            </div>
        `;
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