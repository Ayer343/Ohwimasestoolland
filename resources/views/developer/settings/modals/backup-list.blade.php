{{-- resources/views/developer/settings/modals/backup-list.blade.php --}}
<div id="backupListModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full max-h-[80vh] overflow-hidden">
        <!-- Modal Header -->
        <div class="p-6 border-b dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold dark:text-gray-100">
                        <i class="fas fa-history mr-2 text-blue-500"></i>
                        Backup Manager
                    </h3>
                    <p class="text-sm mt-1 dark:text-gray-400">
                        Manage your system backups
                    </p>
                </div>
                <button onclick="closeBackupListModal()" 
                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>

        <!-- Modal Content -->
        <div class="p-6 overflow-y-auto max-h-[60vh]">
            <div id="backupListContent">
                <!-- Loading State -->
                <div id="backupListLoading" class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl text-blue-500 dark:text-blue-400 mb-4"></i>
                    <p class="dark:text-gray-300">Loading backups...</p>
                </div>

                <!-- Error State -->
                <div id="backupListError" class="hidden text-center py-8">
                    <i class="fas fa-exclamation-triangle text-2xl text-red-500 dark:text-red-400 mb-4"></i>
                    <p class="dark:text-gray-300">Failed to load backups</p>
                    <p id="backupListErrorMessage" class="text-sm mt-2 dark:text-gray-400"></p>
                    <button onclick="loadBackups()" 
                            class="mt-4 btn btn-primary px-4 py-2 rounded-lg">
                        <i class="fas fa-redo mr-2"></i> Try Again
                    </button>
                </div>

                <!-- Empty State -->
                <div id="backupListEmpty" class="hidden text-center py-8">
                    <i class="fas fa-database text-4xl text-gray-400 dark:text-gray-500 mb-4"></i>
                    <p class="dark:text-gray-300">No backups available</p>
                    <p class="text-sm mt-2 dark:text-gray-400">
                        Create your first backup using the manual backup option
                    </p>
                    <form method="POST" action="{{ route('developer.backup.create') }}" class="mt-4">
                        @csrf
                        <button type="submit" 
                                class="btn btn-primary px-4 py-2 rounded-lg">
                            <i class="fas fa-plus mr-2"></i> Create First Backup
                        </button>
                    </form>
                </div>

                <!-- Backups List -->
                <div id="backupListTable" class="hidden">
                    <div class="mb-4 flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <div class="relative">
                                <input type="text" 
                                       id="backupSearch" 
                                       placeholder="Search backups..." 
                                       class="custom-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 pl-10 pr-4 py-2 rounded-lg text-sm w-64">
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>
                            <select id="backupFilter" 
                                    class="custom-select dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 text-sm py-2 rounded-lg">
                                <option value="all">All Backups</option>
                                <option value="today">Today</option>
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                                <option value="manual">Manual</option>
                                <option value="auto">Automatic</option>
                            </select>
                        </div>
                        <div class="text-sm dark:text-gray-400">
                            <span id="backupCount">0</span> backups found
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-lg border dark:border-gray-700">
                        <table class="w-full">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="text-left p-4 dark:text-gray-300">
                                        <input type="checkbox" 
                                               id="selectAllBackups" 
                                               class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                                    </th>
                                    <th class="text-left p-4 dark:text-gray-300">
                                        <button class="flex items-center space-x-1" onclick="sortBackups('filename')">
                                            <span>Filename</span>
                                            <i class="fas fa-sort"></i>
                                        </button>
                                    </th>
                                    <th class="text-left p-4 dark:text-gray-300">
                                        <button class="flex items-center space-x-1" onclick="sortBackups('date')">
                                            <span>Date</span>
                                            <i class="fas fa-sort"></i>
                                        </button>
                                    </th>
                                    <th class="text-left p-4 dark:text-gray-300">
                                        <button class="flex items-center space-x-1" onclick="sortBackups('size')">
                                            <span>Size</span>
                                            <i class="fas fa-sort"></i>
                                        </button>
                                    </th>
                                    <th class="text-left p-4 dark:text-gray-300">Type</th>
                                    <th class="text-left p-4 dark:text-gray-300">Status</th>
                                    <th class="text-left p-4 dark:text-gray-300">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="backupListBody" class="divide-y dark:divide-gray-700">
                                <!-- Backup rows will be inserted here -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div id="backupPagination" class="mt-4 flex items-center justify-between hidden">
                        <div class="text-sm dark:text-gray-400">
                            Showing <span id="backupStart">1</span> to <span id="backupEnd">10</span> of <span id="backupTotal">0</span> backups
                        </div>
                        <div class="flex items-center space-x-2">
                            <button id="prevPage" 
                                    onclick="changePage(-1)"
                                    class="btn btn-outline px-3 py-1 rounded-lg text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fas fa-chevron-left mr-1"></i> Previous
                            </button>
                            <div class="flex items-center space-x-1">
                                <!-- Page numbers will be inserted here -->
                            </div>
                            <button id="nextPage" 
                                    onclick="changePage(1)"
                                    class="btn btn-outline px-3 py-1 rounded-lg text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                Next <i class="fas fa-chevron-right ml-1"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="p-6 border-t dark:border-gray-700">
            <div class="flex justify-between items-center">
                <div>
                    <div class="flex items-center space-x-2">
                        <input type="checkbox" 
                               id="backupSelection" 
                               class="hidden rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                        <div id="selectionActions" class="hidden space-x-2">
                            <button onclick="downloadSelectedBackups()" 
                                    class="btn btn-info px-3 py-1 rounded-lg text-sm">
                                <i class="fas fa-download mr-1"></i> Download Selected
                            </button>
                            <button onclick="deleteSelectedBackups()" 
                                    class="btn btn-danger px-3 py-1 rounded-lg text-sm">
                                <i class="fas fa-trash mr-1"></i> Delete Selected
                            </button>
                        </div>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <button onclick="createBackupNow()" 
                            class="btn btn-primary px-4 py-2 rounded-lg">
                        <i class="fas fa-plus mr-2"></i> New Backup
                    </button>
                    <button onclick="closeBackupListModal()" 
                            class="btn btn-secondary px-4 py-2 rounded-lg dark:bg-gray-700 dark:text-gray-200">
                        Close
                    </button>
                </div>
            </div>
            
            <!-- Backup Info -->
            <div class="mt-4 pt-4 border-t dark:border-gray-700">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div class="flex items-center justify-between p-2 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                        <span class="dark:text-gray-300">Total Backups:</span>
                        <span id="totalBackupsCount" class="font-medium dark:text-gray-200">0</span>
                    </div>
                    <div class="flex items-center justify-between p-2 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                        <span class="dark:text-gray-300">Total Size:</span>
                        <span id="totalBackupsSize" class="font-medium dark:text-gray-200">0 MB</span>
                    </div>
                    <div class="flex items-center justify-between p-2 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                        <span class="dark:text-gray-300">Retention:</span>
                        <span class="font-medium dark:text-gray-200">{{ $settings->backup_retention_days ?? 30 }} days</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
#backupListModal table th button {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
    font: inherit;
    color: inherit;
}

#backupListModal table th button:hover {
    color: var(--primary);
}

.backup-row {
    transition: background-color 0.2s ease;
}

.backup-row:hover {
    background-color: rgba(var(--primary-rgb), 0.05);
}

.dark .backup-row:hover {
    background-color: rgba(59, 130, 246, 0.05);
}

.backup-status-complete {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
}

.dark .backup-status-complete {
    background-color: rgba(34, 197, 94, 0.1);
    color: rgb(34, 197, 94);
}

.backup-status-failed {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
}

.dark .backup-status-failed {
    background-color: rgba(239, 68, 68, 0.1);
    color: rgb(239, 68, 68);
}

.backup-status-processing {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
}

.dark .backup-status-processing {
    background-color: rgba(245, 158, 11, 0.1);
    color: rgb(245, 158, 11);
}
</style>

<script>
let currentBackups = [];
let currentPage = 1;
let itemsPerPage = 10;
let totalPages = 1;
let sortField = 'date';
let sortDirection = 'desc';
let selectedBackups = new Set();

function showBackupListModal() {
    const modal = document.getElementById('backupListModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    loadBackups();
}

function closeBackupListModal() {
    const modal = document.getElementById('backupListModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
    resetBackupList();
}

function resetBackupList() {
    currentBackups = [];
    currentPage = 1;
    selectedBackups.clear();
    document.getElementById('selectAllBackups').checked = false;
    document.getElementById('backupSelection').classList.add('hidden');
    document.getElementById('selectionActions').classList.add('hidden');
}

function loadBackups() {
    // Show loading state
    document.getElementById('backupListLoading').classList.remove('hidden');
    document.getElementById('backupListError').classList.add('hidden');
    document.getElementById('backupListEmpty').classList.add('hidden');
    document.getElementById('backupListTable').classList.add('hidden');
    document.getElementById('backupPagination').classList.add('hidden');

    fetch('{{ route("developer.backup.list") }}')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                currentBackups = data.backups || [];
                renderBackupList();
            } else {
                throw new Error(data.message || 'Failed to load backups');
            }
        })
        .catch(error => {
            console.error('Error loading backups:', error);
            document.getElementById('backupListLoading').classList.add('hidden');
            document.getElementById('backupListError').classList.remove('hidden');
            document.getElementById('backupListErrorMessage').textContent = error.message;
        });
}

function renderBackupList() {
    // Hide loading state
    document.getElementById('backupListLoading').classList.add('hidden');

    if (currentBackups.length === 0) {
        document.getElementById('backupListEmpty').classList.remove('hidden');
        document.getElementById('backupListTable').classList.add('hidden');
        document.getElementById('backupPagination').classList.add('hidden');
        return;
    }

    // Show table
    document.getElementById('backupListEmpty').classList.add('hidden');
    document.getElementById('backupListTable').classList.remove('hidden');

    // Sort backups
    sortBackupsData();

    // Apply filters
    const filteredBackups = applyFilters(currentBackups);

    // Calculate pagination
    totalPages = Math.ceil(filteredBackups.length / itemsPerPage);
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = Math.min(startIndex + itemsPerPage, filteredBackups.length);
    const pageBackups = filteredBackups.slice(startIndex, endIndex);

    // Update counters
    document.getElementById('backupCount').textContent = filteredBackups.length;
    document.getElementById('totalBackupsCount').textContent = currentBackups.length;
    
    // Calculate total size
    const totalSize = currentBackups.reduce((total, backup) => {
        const size = parseFloat(backup.size) || 0;
        return total + size;
    }, 0);
    document.getElementById('totalBackupsSize').textContent = `${(totalSize / 1024).toFixed(2)} MB`;

    // Render table body
    const tbody = document.getElementById('backupListBody');
    tbody.innerHTML = '';

    pageBackups.forEach((backup, index) => {
        const row = document.createElement('tr');
        row.className = 'backup-row';
        row.dataset.filename = backup.filename;

        const isSelected = selectedBackups.has(backup.filename);
        const statusClass = getStatusClass(backup.status);

        row.innerHTML = `
            <td class="p-4">
                <input type="checkbox" 
                       class="backup-checkbox rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                       data-filename="${backup.filename}"
                       ${isSelected ? 'checked' : ''}
                       onchange="toggleBackupSelection('${backup.filename}', this.checked)">
            </td>
            <td class="p-4">
                <div class="flex items-center">
                    <i class="fas fa-database text-gray-500 dark:text-gray-400 mr-3"></i>
                    <div>
                        <p class="font-medium dark:text-gray-200">${backup.filename}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            ${backup.type || 'Full Backup'}
                        </p>
                    </div>
                </div>
            </td>
            <td class="p-4">
                <div class="dark:text-gray-300">
                    <p>${formatDate(backup.date)}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        ${formatTime(backup.date)}
                    </p>
                </div>
            </td>
            <td class="p-4 dark:text-gray-300">
                ${backup.size || '0 MB'}
            </td>
            <td class="p-4">
                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium
                    ${backup.type === 'manual' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' : 
                      'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'}">
                    <i class="fas fa-${backup.type === 'manual' ? 'hand' : 'robot'} mr-1"></i>
                    ${backup.type === 'manual' ? 'Manual' : 'Auto'}
                </span>
            </td>
            <td class="p-4">
                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium ${statusClass}">
                    <i class="fas fa-${getStatusIcon(backup.status)} mr-1"></i>
                    ${backup.status || 'Unknown'}
                </span>
            </td>
            <td class="p-4">
                <div class="flex space-x-2">
                    <button onclick="downloadBackup('${backup.filename}')" 
                            class="btn btn-info px-2 py-1 rounded text-xs"
                            title="Download">
                        <i class="fas fa-download"></i>
                    </button>
                    <button onclick="restoreBackup('${backup.filename}')" 
                            class="btn btn-warning px-2 py-1 rounded text-xs"
                            title="Restore">
                        <i class="fas fa-redo"></i>
                    </button>
                    <button onclick="deleteBackup('${backup.filename}')" 
                            class="btn btn-danger px-2 py-1 rounded text-xs"
                            title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                    ${backup.verify_url ? `
                    <button onclick="verifyBackup('${backup.filename}')" 
                            class="btn btn-success px-2 py-1 rounded text-xs"
                            title="Verify">
                        <i class="fas fa-check"></i>
                    </button>
                    ` : ''}
                </div>
            </td>
        `;

        tbody.appendChild(row);
    });

    // Update pagination
    updatePagination(filteredBackups.length);
}

function getStatusClass(status) {
    switch (status?.toLowerCase()) {
        case 'completed':
        case 'success':
            return 'backup-status-complete';
        case 'failed':
        case 'error':
            return 'backup-status-failed';
        case 'processing':
        case 'in_progress':
            return 'backup-status-processing';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
    }
}

function getStatusIcon(status) {
    switch (status?.toLowerCase()) {
        case 'completed':
        case 'success':
            return 'check-circle';
        case 'failed':
        case 'error':
            return 'exclamation-circle';
        case 'processing':
        case 'in_progress':
            return 'spinner';
        default:
            return 'question-circle';
    }
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

function sortBackups(field) {
    if (sortField === field) {
        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        sortField = field;
        sortDirection = 'desc';
    }
    renderBackupList();
}

function sortBackupsData() {
    currentBackups.sort((a, b) => {
        let aValue = a[sortField];
        let bValue = b[sortField];

        // Handle special fields
        if (sortField === 'date') {
            aValue = new Date(a.date || 0);
            bValue = new Date(b.date || 0);
        } else if (sortField === 'size') {
            aValue = parseFloat(a.size) || 0;
            bValue = parseFloat(b.size) || 0;
        }

        if (sortDirection === 'asc') {
            return aValue > bValue ? 1 : -1;
        } else {
            return aValue < bValue ? 1 : -1;
        }
    });
}

function applyFilters(backups) {
    const searchTerm = document.getElementById('backupSearch').value.toLowerCase();
    const filterType = document.getElementById('backupFilter').value;

    return backups.filter(backup => {
        // Apply search filter
        if (searchTerm && !backup.filename.toLowerCase().includes(searchTerm)) {
            return false;
        }

        // Apply type filter
        if (filterType !== 'all') {
            if (filterType === 'manual' && backup.type !== 'manual') return false;
            if (filterType === 'auto' && backup.type === 'manual') return false;
            if (filterType === 'today') {
                const today = new Date().toDateString();
                const backupDate = new Date(backup.date).toDateString();
                if (today !== backupDate) return false;
            }
            if (filterType === 'week') {
                const weekAgo = new Date();
                weekAgo.setDate(weekAgo.getDate() - 7);
                if (new Date(backup.date) < weekAgo) return false;
            }
            if (filterType === 'month') {
                const monthAgo = new Date();
                monthAgo.setMonth(monthAgo.getMonth() - 1);
                if (new Date(backup.date) < monthAgo) return false;
            }
        }

        return true;
    });
}

function updatePagination(totalItems) {
    if (totalItems <= itemsPerPage) {
        document.getElementById('backupPagination').classList.add('hidden');
        return;
    }

    document.getElementById('backupPagination').classList.remove('hidden');
    document.getElementById('backupStart').textContent = (currentPage - 1) * itemsPerPage + 1;
    document.getElementById('backupEnd').textContent = Math.min(currentPage * itemsPerPage, totalItems);
    document.getElementById('backupTotal').textContent = totalItems;

    // Update page buttons
    const pageNumbers = document.querySelector('#backupPagination .flex.items-center.space-x-1');
    pageNumbers.innerHTML = '';

    // Show limited page numbers
    const maxPagesToShow = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxPagesToShow / 2));
    let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);

    if (endPage - startPage + 1 < maxPagesToShow) {
        startPage = Math.max(1, endPage - maxPagesToShow + 1);
    }

    for (let i = startPage; i <= endPage; i++) {
        const button = document.createElement('button');
        button.className = `px-3 py-1 rounded-lg text-sm ${i === currentPage ? 'btn-primary' : 'btn-outline dark:border-gray-600 dark:text-gray-300'}`;
        button.textContent = i;
        button.onclick = () => goToPage(i);
        pageNumbers.appendChild(button);
    }

    // Update navigation buttons
    document.getElementById('prevPage').disabled = currentPage === 1;
    document.getElementById('nextPage').disabled = currentPage === totalPages;
}

function changePage(delta) {
    const newPage = currentPage + delta;
    if (newPage >= 1 && newPage <= totalPages) {
        currentPage = newPage;
        renderBackupList();
    }
}

function goToPage(page) {
    if (page >= 1 && page <= totalPages) {
        currentPage = page;
        renderBackupList();
    }
}

// Selection Management
function toggleBackupSelection(filename, selected) {
    if (selected) {
        selectedBackups.add(filename);
    } else {
        selectedBackups.delete(filename);
    }
    updateSelectionUI();
}

function updateSelectionUI() {
    const selectAllCheckbox = document.getElementById('selectAllBackups');
    const selectionCheckbox = document.getElementById('backupSelection');
    const selectionActions = document.getElementById('selectionActions');
    const backupCheckboxes = document.querySelectorAll('.backup-checkbox');

    if (selectedBackups.size > 0) {
        selectionCheckbox.classList.remove('hidden');
        selectionActions.classList.remove('hidden');
        selectAllCheckbox.checked = selectedBackups.size === currentBackups.length;
    } else {
        selectionCheckbox.classList.add('hidden');
        selectionActions.classList.add('hidden');
        selectAllCheckbox.checked = false;
    }

    // Update individual checkboxes
    backupCheckboxes.forEach(checkbox => {
        const filename = checkbox.dataset.filename;
        checkbox.checked = selectedBackups.has(filename);
    });
}

document.getElementById('selectAllBackups').addEventListener('change', function(e) {
    const backupCheckboxes = document.querySelectorAll('.backup-checkbox');
    if (this.checked) {
        backupCheckboxes.forEach(checkbox => {
            const filename = checkbox.dataset.filename;
            selectedBackups.add(filename);
            checkbox.checked = true;
        });
    } else {
        selectedBackups.clear();
        backupCheckboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
    }
    updateSelectionUI();
});

// Search and Filter Events
document.getElementById('backupSearch').addEventListener('input', () => {
    currentPage = 1;
    renderBackupList();
});

document.getElementById('backupFilter').addEventListener('change', () => {
    currentPage = 1;
    renderBackupList();
});

// Backup Actions
function createBackupNow() {
    if (!confirm('Create a new backup now?')) return;

    showToast('Creating backup...', 'info');
    
    fetch('{{ route("developer.backup.create") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Backup created successfully!', 'success');
            setTimeout(() => loadBackups(), 2000);
        } else {
            showToast('Failed to create backup: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error creating backup:', error);
        showToast('Failed to create backup: ' + error.message, 'error');
    });
}

function downloadBackup(filename) {
    showToast('Preparing download...', 'info');
    
    // Create a temporary link to trigger download
    const link = document.createElement('a');
    link.href = `{{ route("developer.backup.download") }}?filename=${encodeURIComponent(filename)}`;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    setTimeout(() => {
        showToast('Download started', 'success');
    }, 500);
}

function downloadSelectedBackups() {
    if (selectedBackups.size === 0) {
        showToast('No backups selected', 'warning');
        return;
    }

    if (selectedBackups.size > 1) {
        showToast('Downloading multiple backups as zip...', 'info');
        // In a real implementation, you would create a zip file on the server
        // and provide a download link
    }

    // For now, download each selected backup individually
    selectedBackups.forEach((filename, index) => {
        setTimeout(() => {
            downloadBackup(filename);
        }, index * 1000); // Stagger downloads
    });
}

function restoreBackup(filename) {
    if (!confirm(`⚠️ WARNING: Restoring from backup "${filename}" will overwrite all current data.\n\nAre you sure you want to continue?`)) {
        return;
    }

    showToast('Starting restore process...', 'warning');

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("developer.backup.restore") }}';
    form.innerHTML = `
        @csrf
        <input type="hidden" name="backup_filename" value="${filename}">
        <input type="hidden" name="confirm_restore" value="1">
    `;
    document.body.appendChild(form);
    form.submit();
}

function deleteBackup(filename) {
    if (!confirm(`Are you sure you want to delete backup "${filename}"? This action cannot be undone.`)) {
        return;
    }

    showToast('Deleting backup...', 'warning');

    fetch('{{ route("developer.backup.delete") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ filename })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Backup deleted successfully', 'success');
            // Remove from current backups
            currentBackups = currentBackups.filter(b => b.filename !== filename);
            selectedBackups.delete(filename);
            renderBackupList();
        } else {
            showToast('Failed to delete backup: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error deleting backup:', error);
        showToast('Failed to delete backup: ' + error.message, 'error');
    });
}

function deleteSelectedBackups() {
    if (selectedBackups.size === 0) {
        showToast('No backups selected', 'warning');
        return;
    }

    if (!confirm(`Are you sure you want to delete ${selectedBackups.size} selected backup(s)? This action cannot be undone.`)) {
        return;
    }

    showToast(`Deleting ${selectedBackups.size} backup(s)...`, 'warning');

    // Delete each selected backup
    const promises = Array.from(selectedBackups).map(filename => 
        fetch('{{ route("developer.backup.delete") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ filename })
        }).then(response => response.json())
    );

    Promise.all(promises)
        .then(results => {
            const successful = results.filter(r => r.success).length;
            const failed = results.filter(r => !r.success).length;
            
            if (failed === 0) {
                showToast(`Successfully deleted ${successful} backup(s)`, 'success');
            } else {
                showToast(`Deleted ${successful} backup(s), failed to delete ${failed} backup(s)`, 'warning');
            }
            
            // Reload backups
            loadBackups();
            selectedBackups.clear();
            updateSelectionUI();
        })
        .catch(error => {
            console.error('Error deleting backups:', error);
            showToast('Failed to delete backups: ' + error.message, 'error');
        });
}

function verifyBackup(filename) {
    showToast('Verifying backup integrity...', 'info');

    fetch(`{{ route("developer.backup.verify") }}?filename=${encodeURIComponent(filename)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.valid) {
                    showToast('✓ Backup verification passed', 'success');
                } else {
                    showToast('✗ Backup verification failed: ' + data.message, 'error');
                }
            } else {
                showToast('Verification failed: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error verifying backup:', error);
            showToast('Verification failed: ' + error.message, 'error');
        });
}

// Initialize modal when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Listen for backup list button clicks
    document.addEventListener('click', function(e) {
        if (e.target.closest('[onclick*="listBackups"]') || 
            e.target.closest('[onclick*="showBackupListModal"]')) {
            e.preventDefault();
            showBackupListModal();
        }
    });
});
</script>