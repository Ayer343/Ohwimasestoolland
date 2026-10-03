@extends('layouts.app')

@section('title', 'Archive Cleanup')

@section('content')
<div class="grid grid-cols-1 gap-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div class="flex items-center mb-4 md:mb-0">
                <i class="fas fa-trash-alt text-2xl mr-3" style="color: var(--danger);"></i>
                <div>
                    <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Archive Cleanup</h2>
                    <p class="text-sm" style="color: var(--text-secondary);">Delete old archive records from the tenant_invoice_archives table</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.tenant-invoices.archives') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-archive mr-2"></i> Back to Archives
                </a>
            </div>
        </div>
    </div>

    <!-- Warning Message -->
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
        <div class="flex items-center">
            <i class="fas fa-exclamation-triangle mr-3 text-xl"></i>
            <div>
                <strong class="font-bold">Warning!</strong>
                <span class="block sm:inline"> This action will permanently delete records from the archive table. Please export the data before deletion.</span>
            </div>
        </div>
    </div>

    <!-- Archive Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Archives</div>
                    <div class="text-2xl font-semibold" style="color: var(--text-primary);">{{ number_format($stats['total_archives']) }}</div>
                </div>
                <i class="fas fa-archive text-2xl opacity-70" style="color: var(--info);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--warning-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">> 1 Year Old</div>
                    <div class="text-2xl font-semibold" style="color: var(--warning);">{{ number_format($stats['older_than_1_year']) }}</div>
                </div>
                <i class="fas fa-calendar-alt text-2xl opacity-70" style="color: var(--warning);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--danger-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">> 5 Years Old</div>
                    <div class="text-2xl font-semibold" style="color: var(--danger);">{{ number_format($stats['older_than_5_years']) }}</div>
                </div>
                <i class="fas fa-hourglass-end text-2xl opacity-70" style="color: var(--danger);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--primary-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Amount</div>
                    <div class="text-2xl font-semibold" style="color: var(--primary);">{{ $system_settings->formatAmount($stats['total_amount']) }}</div>
                </div>
                <i class="fas fa-money-bill-wave text-2xl opacity-70" style="color: var(--primary);"></i>
            </div>
        </div>

        <div class="card p-4" style="background-color: rgba(var(--success-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Penalties</div>
                    <div class="text-2xl font-semibold" style="color: var(--success);">{{ $system_settings->formatAmount($stats['total_penalties']) }}</div>
                </div>
                <i class="fas fa-exclamation-triangle text-2xl opacity-70" style="color: var(--success);"></i>
            </div>
        </div>
    </div>

    <!-- Log Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Cleanup Logs</div>
                    <div class="text-2xl font-semibold" style="color: var(--text-primary);">{{ number_format($stats['log_stats']['total_logs']) }}</div>
                </div>
                <i class="fas fa-history text-2xl opacity-70" style="color: var(--info);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--warning-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Logs > 3 Years</div>
                    <div class="text-2xl font-semibold" style="color: var(--warning);">{{ number_format($stats['log_stats']['older_than_3_years']) }}</div>
                </div>
                <i class="fas fa-calendar-week text-2xl opacity-70" style="color: var(--warning);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--danger-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Logs > 7 Years</div>
                    <div class="text-2xl font-semibold" style="color: var(--danger);">{{ number_format($stats['log_stats']['older_than_7_years']) }}</div>
                </div>
                <i class="fas fa-hourglass-end text-2xl opacity-70" style="color: var(--danger);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--success-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Oldest Log</div>
                    <div class="text-2xl font-semibold" style="color: var(--success);">
                        @if($stats['log_stats']['oldest_log'])
                            {{ \Carbon\Carbon::parse($stats['log_stats']['oldest_log']->created_at)->format('M Y') }}
                        @else
                            -
                        @endif
                    </div>
                </div>
                <i class="fas fa-calendar-alt text-2xl opacity-70" style="color: var(--success);"></i>
            </div>
        </div>
    </div>

    <!-- Cleanup Options Tabs -->
    <div class="card p-6">
        <div class="border-b border-gray-200 mb-4">
            <ul class="flex flex-wrap -mb-px text-sm font-medium text-center">
                <li class="mr-2">
                    <button onclick="showTab('byAge')" id="tabByAgeBtn" class="inline-block p-4 border-b-2 rounded-t-lg active-tab" style="color: var(--primary); border-color: var(--primary);">
                        Cleanup by Age
                    </button>
                </li>
                <li class="mr-2">
                    <button onclick="showTab('bySelection')" id="tabBySelectionBtn" class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300" style="color: var(--text-secondary);">
                        Cleanup by Selection
                    </button>
                </li>
                <li class="mr-2">
                    <button onclick="showTab('cleanupLogs')" id="tabCleanupLogsBtn" class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300" style="color: var(--text-secondary);">
                        Cleanup Logs
                    </button>
                </li>
            </ul>
        </div>

        <!-- Tab 1: Cleanup by Age -->
        <div id="tabByAge" class="tab-content">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Select Age Range to Delete</label>
                    <select id="yearsSelect" class="w-full md:w-64 p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="1">Delete archives older than 1 year</option>
                        <option value="2">Delete archives older than 2 years</option>
                        <option value="3">Delete archives older than 3 years</option>
                        <option value="5">Delete archives older than 5 years</option>
                        <option value="7" selected>Delete archives older than 7 years</option>
                        <option value="10">Delete archives older than 10 years</option>
                    </select>
                </div>
                
                <!-- Bulk Selection for Age Section -->
                <div class="border rounded-lg p-4" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center">
                        <h4 class="text-sm font-medium" style="color: var(--text-primary);">Bulk Selection Options</h4>
                        <div class="flex gap-2">
                            <button type="button" onclick="selectAllAgeRecords()" class="px-3 py-1 text-xs border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary);">
                                Select All in Age Range
                            </button>
                            <button type="button" onclick="deselectAllAgeRecords()" class="px-3 py-1 text-xs border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary);">
                                Deselect All
                            </button>
                        </div>
                    </div>
                    <p class="text-xs mt-2" style="color: var(--text-secondary);">
                        Use these buttons to select/deselect all records that match the selected age range.
                    </p>
                </div>
                
                <div class="bg-yellow-50 p-4 rounded-lg" style="border-left: 4px solid #f59e0b;">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle mr-3 mt-1" style="color: #f59e0b;"></i>
                        <div>
                            <p class="text-sm" style="color: #78350f;">
                                <strong>Records that will be deleted:</strong> 
                                <span id="recordsToDelete">0</span> records totaling 
                                <span id="totalAmountToDelete">GH₵0.00</span>
                            </p>
                            <p class="text-xs mt-1" style="color: #92400e;">
                                Cutoff date: <span id="cutoffDate">-</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 2: Cleanup by Selection -->
        <div id="tabBySelection" class="tab-content hidden">
            <div class="space-y-4">
                <div class="flex justify-between items-center mb-4">
                    <h4 class="font-medium" style="color: var(--text-primary);">Select Specific Records to Delete from Archive</h4>
                    <div class="flex gap-2">
                        <button type="button" onclick="selectAllRecords()" class="px-3 py-1 text-sm border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary);">
                            Select All
                        </button>
                        <button type="button" onclick="deselectAllRecords()" class="px-3 py-1 text-sm border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary);">
                            Deselect All
                        </button>
                    </div>
                </div>
                
                <div class="overflow-x-auto max-h-96 overflow-y-auto border rounded" style="border-color: var(--border-color);">
                    <table class="w-full">
                        <thead class="sticky top-0" style="background-color: var(--bg-primary);">
                            <tr class="border-b" style="border-color: var(--border-color);">
                                <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); width: 40px;">
                                    <input type="checkbox" id="selectAllRecords" class="rounded">
                                </th>
                                <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary);">Invoice #</th>
                                <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary);">Tenant</th>
                                <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary);">Period</th>
                                <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary);">Amount</th>
                                <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary);">Deleted At</th>
                                <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary);">Age</th>
                               </tr>
                        </thead>
                        <tbody id="recordsList">
                               <tr>
                                <td colspan="7" class="text-center p-4 text-sm" style="color: var(--text-secondary);">
                                    <i class="fas fa-spinner fa-spin mr-2"></i> Loading records from archive...
                                </td>
                               </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="bg-blue-50 p-4 rounded-lg" style="border-left: 4px solid #3b82f6;">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle mr-3 mt-1" style="color: #3b82f6;"></i>
                        <div>
                            <p class="text-sm" style="color: #1e40af;">
                                <strong>Selected records to delete:</strong> 
                                <span id="selectedRecordsCount">0</span> records totaling 
                                <span id="selectedRecordsAmount">GH₵0.00</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 3: Cleanup Old Logs -->
        <div id="tabCleanupLogs" class="tab-content hidden">
            <div class="space-y-4">
                <div class="bg-blue-50 p-4 rounded-lg" style="border-left: 4px solid #3b82f6;">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle mr-3 mt-1" style="color: #3b82f6;"></i>
                        <div>
                            <p class="text-sm" style="color: #1e40af;">
                                <strong>Log Retention Information:</strong><br>
                                Cleanup logs are kept for audit purposes. You can delete logs older than a certain age or select individual logs to delete.
                                <strong>Recommended:</strong> Keep logs for at least 7 years for legal compliance.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Bulk Log Deletion by Age -->
                <div class="border rounded-lg p-4" style="border-color: var(--border-color);">
                    <h4 class="font-medium mb-3" style="color: var(--text-primary);">Option 1: Delete Logs by Age</h4>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Delete Logs Older Than</label>
                        <select id="logYearsSelect" class="w-full md:w-64 p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="1">1 year</option>
                            <option value="2">2 years</option>
                            <option value="3">3 years</option>
                            <option value="5">5 years</option>
                            <option value="7" selected>7 years</option>
                            <option value="10">10 years</option>
                        </select>
                    </div>

                    <div class="bg-yellow-50 p-4 rounded-lg mt-3" style="border-left: 4px solid #f59e0b;">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-3 mt-1" style="color: #f59e0b;"></i>
                            <div>
                                <p class="text-sm" style="color: #78350f;">
                                    <strong>Logs that will be deleted:</strong> 
                                    <span id="logsToDeleteCount">0</span> log records
                                </p>
                                <p class="text-xs mt-1" style="color: #92400e;">
                                    Cutoff date: <span id="logsCutoffDate">-</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="border rounded-lg p-4 mt-3" style="border-color: var(--border-color);">
                        <label class="flex items-center">
                            <input type="checkbox" id="confirmLogDelete" class="mr-2" style="width: 18px; height: 18px;">
                            <span class="text-sm" style="color: var(--danger);">
                                I confirm that I want to permanently delete these cleanup logs. This action cannot be undone.
                            </span>
                        </label>
                    </div>

                    <div class="flex justify-end mt-3">
                        <button type="button" onclick="previewLogCleanup()" class="btn-info px-6 py-2 rounded mr-2">
                            <i class="fas fa-eye mr-2"></i> Preview
                        </button>
                        <button type="button" onclick="performLogCleanup()" id="cleanupLogsBtn" class="btn-danger px-6 py-2 rounded" disabled>
                            <i class="fas fa-trash-alt mr-2"></i> Delete Old Logs
                        </button>
                    </div>
                </div>

                <!-- Individual Log Selection with Checkboxes -->
                <div class="border rounded-lg p-4 mt-4" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center mb-3">
                        <h4 class="font-medium" style="color: var(--text-primary);">Option 2: Select Individual Logs to Delete</h4>
                        <div class="flex gap-2">
                            <button type="button" onclick="selectAllLogs()" class="px-3 py-1 text-xs border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary);">
                                Select All Logs
                            </button>
                            <button type="button" onclick="deselectAllLogs()" class="px-3 py-1 text-xs border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary);">
                                Deselect All
                            </button>
                            <button type="button" onclick="selectLogsOlderThan()" class="px-3 py-1 text-xs border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary);">
                                Select Logs Older Than
                            </button>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto max-h-96 overflow-y-auto border rounded" style="border-color: var(--border-color);">
                        <table class="w-full">
                            <thead class="sticky top-0" style="background-color: var(--bg-primary);">
                                <tr class="border-b" style="border-color: var(--border-color);">
                                    <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); width: 40px;">
                                        <input type="checkbox" id="selectAllLogsCheckbox" class="rounded">
                                    </th>
                                    <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary);">Date</th>
                                    <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary);">Performed By</th>
                                    <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary);">Method</th>
                                    <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary);">Records Deleted</th>
                                    <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary);">Total Amount</th>
                                    <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary);">IP Address</th>
                                    <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary);">Age</th>
                                </tr>
                            </thead>
                            <tbody id="cleanupLogsList">
                                <tr>
                                    <td colspan="8" class="text-center p-4 text-sm" style="color: var(--text-secondary);">
                                        <i class="fas fa-spinner fa-spin mr-2"></i> Loading cleanup logs...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="bg-blue-50 p-4 rounded-lg mt-3" style="border-left: 4px solid #3b82f6;">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-3 mt-1" style="color: #3b82f6;"></i>
                            <div>
                                <p class="text-sm" style="color: #1e40af;">
                                    <strong>Selected logs to delete:</strong> 
                                    <span id="selectedLogsCount">0</span> log records
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="border rounded-lg p-4 mt-3" style="border-color: var(--border-color);">
                        <label class="flex items-center">
                            <input type="checkbox" id="confirmIndividualLogDelete" class="mr-2" style="width: 18px; height: 18px;">
                            <span class="text-sm" style="color: var(--danger);">
                                I confirm that I want to permanently delete these selected cleanup logs. This action cannot be undone.
                            </span>
                        </label>
                    </div>
                    
                    <div class="flex justify-end mt-3">
                        <button type="button" onclick="deleteSelectedLogs()" id="deleteSelectedLogsBtn" class="btn-danger px-6 py-2 rounded" disabled>
                            <i class="fas fa-trash-alt mr-2"></i> Delete Selected Logs
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-6 border rounded-lg p-4" style="border-color: var(--border-color);" id="commonSettings">
            <label class="flex items-center mb-3">
                <input type="checkbox" id="exportBeforeDelete" class="mr-2" style="width: 18px; height: 18px;">
                <span class="text-sm" style="color: var(--text-primary);">Export data before deletion (recommended)</span>
            </label>
            
            <div id="exportFormatGroup" class="ml-6 mt-2 hidden">
                <label class="block text-sm mb-2" style="color: var(--text-secondary);">Export Format:</label>
                <div class="flex space-x-3">
                    <label class="flex items-center">
                        <input type="radio" name="export_format" value="csv" checked class="mr-1">
                        <span class="text-sm">CSV</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" name="export_format" value="json" class="mr-1">
                        <span class="text-sm">JSON</span>
                    </label>
                </div>
            </div>
        </div>
        
        <div class="border rounded-lg p-4 mt-4" style="border-color: var(--border-color);" id="commonConfirm">
            <label class="flex items-center">
                <input type="checkbox" id="confirmDelete" class="mr-2" style="width: 18px; height: 18px;">
                <span class="text-sm" style="color: var(--danger);">
                    I confirm that I have exported the data and understand this action cannot be undone
                </span>
            </label>
        </div>
        
        <div class="flex justify-end space-x-3 mt-6">
            <button type="button" onclick="previewCleanup()" id="previewBtn" class="btn-info px-6 py-2 rounded">
                <i class="fas fa-eye mr-2"></i> Preview
            </button>
            <button type="button" onclick="performCleanup()" id="cleanupBtn" class="btn-danger px-6 py-2 rounded" disabled>
                <i class="fas fa-trash-alt mr-2"></i> Delete from Archive
            </button>
        </div>
    </div>
</div>

<script>
let currentTab = 'byAge';
let allRecords = [];
let selectedRecordIds = [];
let allLogs = [];
let selectedLogIds = [];
let logYearsSelectValue = 7;

document.addEventListener('DOMContentLoaded', function() {
    const exportCheckbox = document.getElementById('exportBeforeDelete');
    const exportFormatGroup = document.getElementById('exportFormatGroup');
    const confirmCheckbox = document.getElementById('confirmDelete');
    const logConfirmCheckbox = document.getElementById('confirmLogDelete');
    const cleanupLogsBtn = document.getElementById('cleanupLogsBtn');
    const individualLogConfirm = document.getElementById('confirmIndividualLogDelete');
    const deleteSelectedLogsBtn = document.getElementById('deleteSelectedLogsBtn');
    
    if (exportCheckbox) {
        exportCheckbox.addEventListener('change', function() {
            if (this.checked) {
                exportFormatGroup.classList.remove('hidden');
            } else {
                exportFormatGroup.classList.add('hidden');
            }
            updateCleanupButton();
        });
    }
    
    if (confirmCheckbox) {
        confirmCheckbox.addEventListener('change', updateCleanupButton);
    }
    
    if (logConfirmCheckbox) {
        logConfirmCheckbox.addEventListener('change', function() {
            if (this.checked) {
                cleanupLogsBtn.disabled = false;
                cleanupLogsBtn.style.opacity = '1';
                cleanupLogsBtn.style.cursor = 'pointer';
            } else {
                cleanupLogsBtn.disabled = true;
                cleanupLogsBtn.style.opacity = '0.5';
                cleanupLogsBtn.style.cursor = 'not-allowed';
            }
        });
    }
    
    if (individualLogConfirm) {
        individualLogConfirm.addEventListener('change', function() {
            if (this.checked && selectedLogIds.length > 0) {
                deleteSelectedLogsBtn.disabled = false;
                deleteSelectedLogsBtn.style.opacity = '1';
                deleteSelectedLogsBtn.style.cursor = 'pointer';
            } else {
                deleteSelectedLogsBtn.disabled = true;
                deleteSelectedLogsBtn.style.opacity = '0.5';
                deleteSelectedLogsBtn.style.cursor = 'not-allowed';
            }
        });
    }
    
    if (document.getElementById('yearsSelect')) {
        document.getElementById('yearsSelect').addEventListener('change', function() {
            if (currentTab === 'byAge') {
                previewCleanup();
            }
        });
    }
    
    if (document.getElementById('logYearsSelect')) {
        document.getElementById('logYearsSelect').addEventListener('change', function() {
            logYearsSelectValue = this.value;
            previewLogCleanup();
        });
    }
    
    loadRecordsForSelection();
    loadCleanupLogsList();
    previewCleanup();
    previewLogCleanup();
    loadRecentCleanupLogs();
});

function showTab(tabName) {
    currentTab = tabName;
    
    const byAgeBtn = document.getElementById('tabByAgeBtn');
    const bySelectionBtn = document.getElementById('tabBySelectionBtn');
    const cleanupLogsBtn = document.getElementById('tabCleanupLogsBtn');
    const byAgeContent = document.getElementById('tabByAge');
    const bySelectionContent = document.getElementById('tabBySelection');
    const cleanupLogsContent = document.getElementById('tabCleanupLogs');
    const commonSettings = document.getElementById('commonSettings');
    const commonConfirm = document.getElementById('commonConfirm');
    const previewBtn = document.getElementById('previewBtn');
    const cleanupBtn = document.getElementById('cleanupBtn');
    
    if (tabName === 'byAge') {
        byAgeBtn.classList.add('active-tab');
        byAgeBtn.style.color = 'var(--primary)';
        byAgeBtn.style.borderColor = 'var(--primary)';
        bySelectionBtn.classList.remove('active-tab');
        bySelectionBtn.style.color = 'var(--text-secondary)';
        bySelectionBtn.style.borderColor = 'transparent';
        cleanupLogsBtn.classList.remove('active-tab');
        cleanupLogsBtn.style.color = 'var(--text-secondary)';
        cleanupLogsBtn.style.borderColor = 'transparent';
        byAgeContent.classList.remove('hidden');
        bySelectionContent.classList.add('hidden');
        cleanupLogsContent.classList.add('hidden');
        commonSettings.classList.remove('hidden');
        commonConfirm.classList.remove('hidden');
        previewBtn.classList.remove('hidden');
        cleanupBtn.classList.remove('hidden');
        previewCleanup();
    } else if (tabName === 'bySelection') {
        bySelectionBtn.classList.add('active-tab');
        bySelectionBtn.style.color = 'var(--primary)';
        bySelectionBtn.style.borderColor = 'var(--primary)';
        byAgeBtn.classList.remove('active-tab');
        byAgeBtn.style.color = 'var(--text-secondary)';
        byAgeBtn.style.borderColor = 'transparent';
        cleanupLogsBtn.classList.remove('active-tab');
        cleanupLogsBtn.style.color = 'var(--text-secondary)';
        cleanupLogsBtn.style.borderColor = 'transparent';
        bySelectionContent.classList.remove('hidden');
        byAgeContent.classList.add('hidden');
        cleanupLogsContent.classList.add('hidden');
        commonSettings.classList.remove('hidden');
        commonConfirm.classList.remove('hidden');
        previewBtn.classList.remove('hidden');
        cleanupBtn.classList.remove('hidden');
        updateSelectedCount();
    } else {
        cleanupLogsBtn.classList.add('active-tab');
        cleanupLogsBtn.style.color = 'var(--primary)';
        cleanupLogsBtn.style.borderColor = 'var(--primary)';
        byAgeBtn.classList.remove('active-tab');
        byAgeBtn.style.color = 'var(--text-secondary)';
        byAgeBtn.style.borderColor = 'transparent';
        bySelectionBtn.classList.remove('active-tab');
        bySelectionBtn.style.color = 'var(--text-secondary)';
        bySelectionBtn.style.borderColor = 'transparent';
        cleanupLogsContent.classList.remove('hidden');
        byAgeContent.classList.add('hidden');
        bySelectionContent.classList.add('hidden');
        commonSettings.classList.add('hidden');
        commonConfirm.classList.add('hidden');
        previewBtn.classList.add('hidden');
        cleanupBtn.classList.add('hidden');
        loadCleanupLogsList();
    }
}

function updateCleanupButton() {
    const confirmCheckbox = document.getElementById('confirmDelete');
    const cleanupBtn = document.getElementById('cleanupBtn');
    
    if (confirmCheckbox && confirmCheckbox.checked) {
        cleanupBtn.disabled = false;
        cleanupBtn.style.opacity = '1';
        cleanupBtn.style.cursor = 'pointer';
    } else {
        cleanupBtn.disabled = true;
        cleanupBtn.style.opacity = '0.5';
        cleanupBtn.style.cursor = 'not-allowed';
    }
}

function previewCleanup() {
    if (currentTab === 'byAge') {
        const years = document.getElementById('yearsSelect').value;
        
        fetch(`/admin/tenant-invoices/archives/preview-cleanup?years=${years}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('recordsToDelete').textContent = data.count;
                    document.getElementById('totalAmountToDelete').textContent = data.formatted_amount;
                    document.getElementById('cutoffDate').textContent = data.cutoff_date;
                } else {
                    document.getElementById('recordsToDelete').textContent = '0';
                    document.getElementById('totalAmountToDelete').textContent = 'GH₵0.00';
                    document.getElementById('cutoffDate').textContent = '-';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('recordsToDelete').textContent = 'Error';
            });
    }
}

function previewLogCleanup() {
    const years = document.getElementById('logYearsSelect').value;
    
    fetch(`/admin/tenant-invoices/archives/preview-log-cleanup?years=${years}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('logsToDeleteCount').textContent = data.count;
                document.getElementById('logsCutoffDate').textContent = data.cutoff_date;
            } else {
                document.getElementById('logsToDeleteCount').textContent = '0';
                document.getElementById('logsCutoffDate').textContent = '-';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('logsToDeleteCount').textContent = 'Error';
        });
}

function performLogCleanup() {
    const confirmCheckbox = document.getElementById('confirmLogDelete');
    const years = document.getElementById('logYearsSelect').value;
    
    if (!confirmCheckbox.checked) {
        alert('Please confirm that you want to delete these logs.');
        return;
    }
    
    if (confirm(`Are you sure you want to permanently delete logs older than ${years} years? This action cannot be undone.`)) {
        const cleanupBtn = document.getElementById('cleanupLogsBtn');
        const originalText = cleanupBtn.innerHTML;
        cleanupBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';
        cleanupBtn.disabled = true;
        
        fetch(`/admin/tenant-invoices/archives/cleanup-old-logs?years=${years}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ years: years })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
                cleanupBtn.innerHTML = originalText;
                cleanupBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred during log cleanup: ' + error.message);
            cleanupBtn.innerHTML = originalText;
            cleanupBtn.disabled = false;
        });
    }
}

function deleteSelectedLogs() {
    const confirmCheckbox = document.getElementById('confirmIndividualLogDelete');
    
    if (!confirmCheckbox.checked) {
        alert('Please confirm that you want to delete the selected logs.');
        return;
    }
    
    if (selectedLogIds.length === 0) {
        alert('Please select at least one log to delete.');
        return;
    }
    
    if (confirm(`Are you sure you want to permanently delete ${selectedLogIds.length} selected log records? This action cannot be undone.`)) {
        const deleteBtn = document.getElementById('deleteSelectedLogsBtn');
        const originalText = deleteBtn.innerHTML;
        deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';
        deleteBtn.disabled = true;
        
        fetch('/admin/tenant-invoices/archives/delete-selected-logs', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ log_ids: selectedLogIds })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
                deleteBtn.innerHTML = originalText;
                deleteBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred: ' + error.message);
            deleteBtn.innerHTML = originalText;
            deleteBtn.disabled = false;
        });
    }
}

function loadCleanupLogsList() {
    const tbody = document.getElementById('cleanupLogsList');
    tbody.innerHTML = '<tr><td colspan="8" class="text-center p-4 text-sm"><i class="fas fa-spinner fa-spin mr-2"></i> Loading cleanup logs...</td></tr>';
    
    fetch('/admin/tenant-invoices/archives/cleanup-logs')
        .then(response => response.json())
        .then(data => {
            if (data && data.length > 0) {
                allLogs = data;
                renderLogsTable();
            } else {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center p-4 text-sm" style="color: var(--text-secondary);">No cleanup logs found</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            tbody.innerHTML = '<tr><td colspan="8" class="text-center p-4 text-sm" style="color: var(--danger);">Error loading logs: ' + error.message + '</td></tr>';
        });
}

function renderLogsTable() {
    const tbody = document.getElementById('cleanupLogsList');
    if (allLogs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center p-4 text-sm" style="color: var(--text-secondary);">No cleanup logs found</td></tr>';
        return;
    }
    
    tbody.innerHTML = allLogs.map(log => {
        const createdAt = new Date(log.created_at);
        const now = new Date();
        const ageDays = Math.floor((now - createdAt) / (1000 * 60 * 60 * 24));
        
        return `
            <tr class="border-b" style="border-color: var(--border-color);">
                <td class="p-3 text-center">
                    <input type="checkbox" class="log-checkbox" data-id="${log.id}" data-created-at="${log.created_at}">
                 </td>
                <td class="p-2 text-sm">${new Date(log.created_at).toLocaleString()}</td>
                <td class="p-2 text-sm">${escapeHtml(log.performed_by_name)}</td>
                <td class="p-2 text-sm">${log.method || 'by_age'}</td>
                <td class="p-2 text-sm">${log.records_deleted.toLocaleString()}</td>
                <td class="p-2 text-sm">${log.formatted_amount}</td>
                <td class="p-2 text-sm">${log.ip_address || '-'}</td>
                <td class="p-2 text-sm">${ageDays} days</td>
            </tr>
        `;
    }).join('');
    
    // Add event listeners to checkboxes
    document.querySelectorAll('.log-checkbox').forEach(cb => {
        cb.addEventListener('change', updateSelectedLogsCount);
    });
    
    // Handle select all checkbox
    const selectAllLogsCheckbox = document.getElementById('selectAllLogsCheckbox');
    if (selectAllLogsCheckbox) {
        selectAllLogsCheckbox.addEventListener('change', function() {
            document.querySelectorAll('.log-checkbox').forEach(cb => {
                cb.checked = this.checked;
            });
            updateSelectedLogsCount();
        });
    }
}

function updateSelectedLogsCount() {
    const checkboxes = document.querySelectorAll('.log-checkbox:checked');
    selectedLogIds = Array.from(checkboxes).map(cb => cb.getAttribute('data-id'));
    
    document.getElementById('selectedLogsCount').textContent = selectedLogIds.length;
    
    // Enable/disable delete button based on selection and confirmation
    const confirmCheckbox = document.getElementById('confirmIndividualLogDelete');
    const deleteBtn = document.getElementById('deleteSelectedLogsBtn');
    
    if (selectedLogIds.length > 0 && confirmCheckbox && confirmCheckbox.checked) {
        deleteBtn.disabled = false;
        deleteBtn.style.opacity = '1';
        deleteBtn.style.cursor = 'pointer';
    } else {
        deleteBtn.disabled = true;
        deleteBtn.style.opacity = '0.5';
        deleteBtn.style.cursor = 'not-allowed';
    }
}

function selectAllLogs() {
    document.querySelectorAll('.log-checkbox').forEach(cb => {
        cb.checked = true;
    });
    updateSelectedLogsCount();
}

function deselectAllLogs() {
    document.querySelectorAll('.log-checkbox').forEach(cb => {
        cb.checked = false;
    });
    updateSelectedLogsCount();
}

function selectLogsOlderThan() {
    const years = prompt('Enter number of years to select logs older than:', '7');
    if (!years || isNaN(years)) return;
    
    const cutoffDate = new Date();
    cutoffDate.setFullYear(cutoffDate.getFullYear() - parseInt(years));
    
    document.querySelectorAll('.log-checkbox').forEach(cb => {
        const createdAt = new Date(cb.getAttribute('data-created-at'));
        cb.checked = createdAt < cutoffDate;
    });
    
    updateSelectedLogsCount();
    alert(`Selected logs older than ${years} years.`);
}

function loadRecordsForSelection() {
    const tbody = document.getElementById('recordsList');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center p-4 text-sm"><i class="fas fa-spinner fa-spin mr-2"></i> Loading records from archive...</td></tr>';
    
    fetch('/admin/tenant-invoices/archives/get-all-records')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allRecords = data.records;
                renderRecordsTable();
            } else {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center p-4 text-sm" style="color: var(--danger);">Failed to load records: ${data.message || 'Unknown error'}</td></tr>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            tbody.innerHTML = `<tr><td colspan="7" class="text-center p-4 text-sm" style="color: var(--danger);">Error loading records: ${error.message}</td></tr>`;
        });
}

function renderRecordsTable() {
    const tbody = document.getElementById('recordsList');
    if (allRecords.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center p-4 text-sm" style="color: var(--text-secondary);">No records found in archive</td></tr>';
        return;
    }
    
    tbody.innerHTML = allRecords.map(record => `
        <tr class="border-b" style="border-color: var(--border-color);">
            <td class="p-3 text-center">
                <input type="checkbox" class="record-checkbox" data-id="${record.id}" data-amount="${record.total_amount}">
            </td>
            <td class="p-3 text-sm">${escapeHtml(record.invoice_number)}</td>
            <td class="p-3 text-sm">${escapeHtml(record.tenant_name)}</td>
            <td class="p-3 text-sm">${escapeHtml(record.month_name)}</td>
            <td class="p-3 text-sm">${record.formatted_amount}</td>
            <td class="p-3 text-sm">${record.deleted_at_formatted}</td>
            <td class="p-3 text-sm">${record.age_days} days</td>
        </tr>
    `).join('');
    
    document.querySelectorAll('.record-checkbox').forEach(cb => {
        cb.addEventListener('change', updateSelectedCount);
    });
    
    const selectAllCheckbox = document.getElementById('selectAllRecords');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            document.querySelectorAll('.record-checkbox').forEach(cb => {
                cb.checked = this.checked;
            });
            updateSelectedCount();
        });
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.record-checkbox:checked');
    selectedRecordIds = Array.from(checkboxes).map(cb => cb.getAttribute('data-id'));
    const totalAmount = Array.from(checkboxes).reduce((sum, cb) => sum + parseFloat(cb.getAttribute('data-amount')), 0);
    
    document.getElementById('selectedRecordsCount').textContent = selectedRecordIds.length;
    document.getElementById('selectedRecordsAmount').textContent = formatAmount(totalAmount);
}

function selectAllRecords() {
    document.querySelectorAll('.record-checkbox').forEach(cb => {
        cb.checked = true;
    });
    updateSelectedCount();
}

function deselectAllRecords() {
    document.querySelectorAll('.record-checkbox').forEach(cb => {
        cb.checked = false;
    });
    updateSelectedCount();
}

function selectAllAgeRecords() {
    if (currentTab === 'byAge') {
        const years = document.getElementById('yearsSelect').value;
        const count = document.getElementById('recordsToDelete').textContent;
        
        if (parseInt(count) === 0) {
            alert('No records match the selected age criteria.');
            return;
        }
        
        alert(`Selected all ${count} records older than ${years} years. Click "Delete from Archive" to proceed.`);
    }
}

function deselectAllAgeRecords() {
    if (currentTab === 'byAge') {
        alert('In Age mode, you cannot deselect individually. Use the Preview to see what will be deleted.');
    }
}

function formatAmount(amount) {
    const currencySymbol = '{{ $system_settings->currency_symbol ?? 'GH₵' }}';
    const currencyPosition = '{{ $system_settings->currency_position ?? 'left' }}';
    
    if (currencyPosition === 'left') {
        return `${currencySymbol}${amount.toFixed(2)}`;
    } else {
        return `${amount.toFixed(2)}${currencySymbol}`;
    }
}

function performCleanup() {
    const exportBefore = document.getElementById('exportBeforeDelete');
    const confirmCheckbox = document.getElementById('confirmDelete');
    const exportFormatRadio = document.querySelector('input[name="export_format"]:checked');
    
    const exportBeforeChecked = exportBefore ? exportBefore.checked : false;
    const confirmChecked = confirmCheckbox ? confirmCheckbox.checked : false;
    const exportFormat = exportFormatRadio ? exportFormatRadio.value : 'csv';
    
    if (!confirmChecked) {
        alert('Please confirm that you want to delete these records by checking the confirmation box.');
        return;
    }
    
    let params = new URLSearchParams();
    params.append('confirm', 'true');
    params.append('exported', exportBeforeChecked ? 'true' : 'false');
    params.append('format', exportFormat);
    
    if (currentTab === 'byAge') {
        const years = document.getElementById('yearsSelect').value;
        params.append('years', years);
        params.append('method', 'by_age');
        
        const recordsToDelete = document.getElementById('recordsToDelete').textContent;
        
        if (parseInt(recordsToDelete) === 0) {
            alert('No records match the selected age criteria.');
            return;
        }
        
        if (exportBeforeChecked) {
            window.location.href = `/admin/tenant-invoices/archives/export-cleanup?${params.toString()}`;
            
            setTimeout(() => {
                if (confirm(`After exporting, proceed with deletion of ${recordsToDelete} records?`)) {
                    executeDeletion(params);
                }
            }, 2000);
        } else {
            if (confirm(`Are you sure you want to permanently delete ${recordsToDelete} archives older than ${years} years? This action cannot be undone.`)) {
                executeDeletion(params);
            }
        }
    } else {
        if (selectedRecordIds.length === 0) {
            alert('Please select at least one record to delete.');
            return;
        }
        params.append('selected_ids', selectedRecordIds.join(','));
        params.append('method', 'by_selection');
        
        if (exportBeforeChecked) {
            window.location.href = `/admin/tenant-invoices/archives/export-cleanup?${params.toString()}`;
            
            setTimeout(() => {
                if (confirm(`After exporting, proceed with deletion of ${selectedRecordIds.length} selected records?`)) {
                    executeDeletion(params);
                }
            }, 2000);
        } else {
            if (confirm(`Are you sure you want to permanently delete ${selectedRecordIds.length} selected records from the archive? This action cannot be undone.`)) {
                executeDeletion(params);
            }
        }
    }
}

function executeDeletion(params) {
    const cleanupBtn = document.getElementById('cleanupBtn');
    const originalText = cleanupBtn.innerHTML;
    cleanupBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';
    cleanupBtn.disabled = true;
    
    fetch('/admin/tenant-invoices/archives/perform-cleanup', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: JSON.stringify(Object.fromEntries(params))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
            cleanupBtn.innerHTML = originalText;
            cleanupBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred during cleanup: ' + error.message);
        cleanupBtn.innerHTML = originalText;
        cleanupBtn.disabled = false;
    });
}

function loadRecentCleanupLogs() {
    fetch('/admin/tenant-invoices/archives/cleanup-logs')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('cleanupLogs');
            if (!data || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center p-4 text-sm" style="color: var(--text-secondary);">No cleanup logs found</td></tr>';
                return;
            }
            
            // Show only the 10 most recent logs in the recent logs section
            const recentLogs = data.slice(0, 10);
            tbody.innerHTML = recentLogs.map(log => `
                <tr class="border-b" style="border-color: var(--border-color);">
                    <td class="p-2 text-sm">${new Date(log.created_at).toLocaleString()}</td>
                    <td class="p-2 text-sm">${escapeHtml(log.performed_by_name)}</td>
                    <td class="p-2 text-sm">${log.method || 'by_age'}</td>
                    <td class="p-2 text-sm">${log.records_deleted.toLocaleString()}</td>
                    <td class="p-2 text-sm">${log.formatted_amount}</td>
                    <td class="p-2 text-sm">${log.ip_address || '-'}</td>
                </tr>
            `).join('');
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('cleanupLogs').innerHTML = '<tr><td colspan="6" class="text-center p-4 text-sm" style="color: var(--danger);">Error loading logs</td></tr>';
        });
}
</script>

<style>
.active-tab {
    color: var(--primary) !important;
    border-bottom-color: var(--primary) !important;
}

.btn-danger {
    background-color: var(--danger);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}

.btn-danger:hover:not(:disabled) {
    opacity: 0.9;
    transform: translateY(-1px);
}

.btn-danger:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-info {
    background-color: var(--info);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}

.btn-info:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

.sticky {
    position: sticky;
    top: 0;
    z-index: 10;
}

.overflow-y-auto::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: var(--border-color);
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 4px;
}
</style>
@endsection