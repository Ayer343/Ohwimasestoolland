@extends('layouts.app')

@section('title', 'Year-End Archive Management')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div class="flex items-center mb-4 md:mb-0">
                <i class="fas fa-calendar-alt text-2xl mr-3" style="color: var(--warning);"></i>
                <div>
                    <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Year-End Archive Management</h2>
                    <p class="text-sm" style="color: var(--text-secondary);">Archive paid invoices from previous years</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('invoices.archives') }}" class="btn-info flex items-center">
                    <i class="fas fa-archive mr-2"></i> View Archives
                </a>
                <a href="{{ route('invoices.index') }}" class="btn-primary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Invoices
                </a>
            </div>
        </div>
    </div>

    <!-- Info Card -->
    <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.05); border-left: 4px solid var(--info);">
        <div class="flex items-start justify-between">
            <div>
                <h3 class="font-semibold" style="color: var(--text-primary);">About Year-End Archiving</h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Year-end archiving moves all paid invoices from the selected year to the archive. 
                    Unpaid invoices remain active for collection. This process is irreversible and should 
                    only be performed after all payments for that year have been processed.
                </p>
                <div class="mt-3 flex flex-wrap gap-4 text-sm">
                    <span style="color: var(--text-secondary);">
                        <i class="fas fa-archive mr-1"></i> Total Archived: 
                        <strong style="color: var(--info);">{{ number_format($stats['total_archived'] ?? 0) }}</strong>
                    </span>
                    <span style="color: var(--text-secondary);">
                        <i class="fas fa-money-bill-wave mr-1"></i> Archived Amount: 
                        <strong style="color: var(--info);">{{ $system_settings->formatAmount($stats['total_archived_amount'] ?? 0) }}</strong>
                    </span>
                    <span style="color: var(--text-secondary);">
                        <i class="fas fa-clock mr-1"></i> Last Archive: 
                        <strong style="color: var(--info);">{{ $stats['last_archive_year'] ?? 'Never' }}</strong>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="card p-4" style="background-color: rgba(var(--primary-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Invoices</div>
                    <div class="text-2xl font-semibold" style="color: var(--primary);">{{ number_format($stats['total_invoices'] ?? 0) }}</div>
                </div>
                <i class="fas fa-file-invoice text-2xl opacity-70" style="color: var(--primary);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--success-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Paid Invoices</div>
                    <div class="text-2xl font-semibold" style="color: var(--success);">{{ number_format($stats['paid_invoices'] ?? 0) }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Will be archived</div>
                </div>
                <i class="fas fa-check-circle text-2xl opacity-70" style="color: var(--success);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--warning-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Unpaid Invoices</div>
                    <div class="text-2xl font-semibold" style="color: var(--warning);">{{ number_format($stats['unpaid_invoices'] ?? 0) }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">Will be kept active</div>
                </div>
                <i class="fas fa-clock text-2xl opacity-70" style="color: var(--warning);"></i>
            </div>
        </div>
        
        <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.1);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Already Archived</div>
                    <div class="text-2xl font-semibold" style="color: var(--info);">{{ number_format($stats['already_archived'] ?? 0) }}</div>
                </div>
                <i class="fas fa-archive text-2xl opacity-70" style="color: var(--info);"></i>
            </div>
        </div>
    </div>

    <!-- Archive Actions Card -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Process Year-End Archive</h3>
        
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Select Year to Archive</label>
                <select id="archiveYear" class="w-full md:w-64 p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    @php
                        $currentYear = date('Y');
                    @endphp
                    @for($year = $currentYear - 1; $year >= $currentYear - 5; $year--)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endfor
                </select>
            </div>
            
            <div id="yearPreview" class="hidden p-4 rounded" style="background-color: rgba(var(--info-rgb), 0.1);">
                <div class="flex items-start">
                    <i class="fas fa-info-circle mr-3 mt-1" style="color: var(--info);"></i>
                    <div>
                        <p class="text-sm font-medium mb-1" style="color: var(--text-primary);">Preview for <span id="previewYear"></span></p>
                        <div id="previewDetails" class="text-sm" style="color: var(--text-secondary);">
                            <div class="grid grid-cols-2 gap-2 mt-2">
                                <span>Total Invoices:</span>
                                <span id="previewTotal" class="font-medium">-</span>
                                <span>Paid Invoices (to archive):</span>
                                <span id="previewPaid" class="font-medium" style="color: var(--success);">-</span>
                                <span>Unpaid Invoices (to keep):</span>
                                <span id="previewUnpaid" class="font-medium" style="color: var(--warning);">-</span>
                                <span>Already Archived:</span>
                                <span id="previewArchived" class="font-medium" style="color: var(--info);">-</span>
                                <span>Total Amount:</span>
                                <span id="previewAmount" class="font-medium">-</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex flex-wrap gap-3">
                <button type="button" onclick="previewArchive()" class="btn-secondary flex items-center">
                    <i class="fas fa-eye mr-2"></i> Preview
                </button>
                <button type="button" onclick="processArchive()" class="btn-warning flex items-center">
                    <i class="fas fa-archive mr-2"></i> Process Year-End Archive
                </button>
            </div>
            
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded text-sm mt-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong>Warning:</strong> This will permanently archive all paid invoices from the selected year. 
                Unpaid invoices will remain active. This action cannot be undone.
            </div>
        </div>
    </div>

    <!-- Post-Payment Archiving Card -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Post-Payment Archiving</h3>
        <p class="text-sm mb-4" style="color: var(--text-secondary);">
            Archive invoices that were kept active during year-end (unpaid) but have since been paid 
            and passed the retention period.
        </p>
        
        <div class="flex flex-wrap gap-3">
            <button type="button" onclick="processPostPaymentArchive()" class="btn-success flex items-center">
                <i class="fas fa-credit-card mr-2"></i> Process Post-Payment Archiving
            </button>
            <button type="button" onclick="previewPostPayment()" class="btn-secondary flex items-center">
                <i class="fas fa-eye mr-2"></i> Preview
            </button>
        </div>
        
        <div id="postPaymentPreview" class="hidden mt-4 p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.1);">
            <i class="fas fa-info-circle mr-2"></i>
            <span id="postPaymentPreviewText"></span>
        </div>
    </div>

    <!-- Unpaid Invoices from Previous Years Card -->
    <div class="card p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Unpaid Invoices from Previous Years</h3>
            <a href="{{ route('invoices.unpaid-previous-years') }}" class="btn-info text-sm flex items-center">
                <i class="fas fa-external-link-alt mr-1"></i> View All
            </a>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div class="p-3 rounded" style="background-color: rgba(var(--warning-rgb), 0.1);">
                <div class="text-sm" style="color: var(--text-secondary);">Unpaid Invoices</div>
                <div class="text-xl font-semibold" style="color: var(--warning);">{{ number_format($stats['unpaid_invoices'] ?? 0) }}</div>
                <div class="text-xs mt-1" style="color: var(--text-secondary);">Total Amount: {{ $system_settings->formatAmount($stats['unpaid_amount'] ?? 0) }}</div>
            </div>
            
            <div class="p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.1);">
                <div class="text-sm" style="color: var(--text-secondary);">Years with Unpaid</div>
                <div class="text-xl font-semibold" style="color: var(--info);">{{ number_format(count($stats['years_with_unpaid'] ?? [])) }}</div>
                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                    @if(!empty($stats['years_with_unpaid']))
                        {{ implode(', ', array_slice($stats['years_with_unpaid'], 0, 3)) }}
                        @if(count($stats['years_with_unpaid']) > 3) ... @endif
                    @else
                        None
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Archive Logs Card - FIXED SECTION -->
    @if(isset($archiveLogs) && $archiveLogs->count() > 0)
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Recent Archive Operations</h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color);">
                        <th class="text-left p-2 text-sm" style="color: var(--text-secondary);">Date</th>
                        <th class="text-left p-2 text-sm" style="color: var(--text-secondary);">Method</th>
                        <th class="text-left p-2 text-sm" style="color: var(--text-secondary);">Criteria</th>
                        <th class="text-left p-2 text-sm" style="color: var(--text-secondary);">Deleted</th>
                        <th class="text-left p-2 text-sm" style="color: var(--text-secondary);">Total Amount</th>
                        <th class="text-left p-2 text-sm" style="color: var(--text-secondary);">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($archiveLogs as $log)
                    <tr class="border-b" style="border-color: var(--border-color);">
                        <td class="p-2 text-sm">{{ $log->created_at->format('M d, Y H:i') }}</td>
                        <td class="p-2 text-sm">
                            @if($log->method === 'age')
                                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                                    <i class="fas fa-clock mr-1"></i> By Age
                                </span>
                            @elseif($log->method === 'selection')
                                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                                    <i class="fas fa-check-square mr-1"></i> By Selection
                                </span>
                            @else
                                <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--secondary-rgb), 0.2); color: var(--text-secondary);">
                                    <i class="fas fa-tools mr-1"></i> Manual
                                </span>
                            @endif
                        </td>
                        <td class="p-2 text-sm">
                            @if($log->years_old)
                                <i class="fas fa-hourglass-half mr-1"></i> Older than {{ $log->years_old }} years
                            @elseif($log->selected_count)
                                <i class="fas fa-layer-group mr-1"></i> {{ $log->selected_count }} selected items
                            @elseif($log->cutoff_date)
                                <i class="fas fa-calendar-alt mr-1"></i> Before {{ \Carbon\Carbon::parse($log->cutoff_date)->format('M d, Y') }}
                            @else
                                <i class="fas fa-question-circle mr-1"></i> Unknown criteria
                            @endif
                        </td>
                        <td class="p-2 text-sm">{{ number_format($log->records_deleted) }}</td>
                        <td class="p-2 text-sm">{{ $system_settings->formatAmount($log->total_amount) }}</td>
                        <td class="p-2 text-sm">
                            @if($log->records_deleted > 0)
                                <span class="text-success">
                                    <i class="fas fa-check-circle mr-1"></i> Completed
                                </span>
                            @else
                                <span class="text-warning">
                                    <i class="fas fa-info-circle mr-1"></i> No Records
                                </span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @elseif(isset($archiveLogs))
    <div class="card p-6">
        <div class="text-center py-8">
            <i class="fas fa-history text-3xl mb-3 opacity-50" style="color: var(--text-secondary);"></i>
            <p class="text-sm" style="color: var(--text-secondary);">No archive operations have been logged yet.</p>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">When you perform archive cleanup operations, they will appear here.</p>
        </div>
    </div>
    @endif
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 flex flex-col items-center">
        <i class="fas fa-spinner fa-spin text-3xl mb-3" style="color: var(--primary);"></i>
        <p class="text-sm" style="color: var(--text-primary);">Processing...</p>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Confirm Year-End Archive</h3>
            <p id="confirmationMessage" class="text-sm mb-4" style="color: var(--text-secondary);"></p>
            <div id="confirmationPreview" class="mb-4 p-3 rounded text-sm" style="background-color: rgba(var(--warning-rgb), 0.1);"></div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeConfirmationModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    Cancel
                </button>
                <button type="button" id="confirmActionBtn" class="px-4 py-2 rounded text-white" style="background-color: var(--danger);">
                    Confirm Archive
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
// CSRF Token
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
let pendingAction = null;
let previewData = null;

// ==================== PREVIEW FUNCTIONS ====================

function previewArchive() {
    const year = document.getElementById('archiveYear').value;
    const previewDiv = document.getElementById('yearPreview');
    const previewYearSpan = document.getElementById('previewYear');
    const previewTotal = document.getElementById('previewTotal');
    const previewPaid = document.getElementById('previewPaid');
    const previewUnpaid = document.getElementById('previewUnpaid');
    const previewArchived = document.getElementById('previewArchived');
    const previewAmount = document.getElementById('previewAmount');
    
    previewDiv.classList.remove('hidden');
    previewYearSpan.textContent = year;
    
    // Show loading
    previewTotal.textContent = 'Loading...';
    previewPaid.textContent = 'Loading...';
    previewUnpaid.textContent = 'Loading...';
    previewArchived.textContent = 'Loading...';
    previewAmount.textContent = 'Loading...';
    
    fetch(`/invoices/year-end/statistics/${year}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                previewData = data.data || data;
                previewTotal.textContent = previewData.total_invoices || 0;
                previewPaid.textContent = previewData.paid_invoices || 0;
                previewUnpaid.textContent = previewData.unpaid_invoices || 0;
                previewArchived.textContent = previewData.year_end_archived || 0;
                previewAmount.textContent = previewData.formatted_total_amount || '₵0.00';
            } else {
                previewTotal.textContent = 'Error';
                previewPaid.textContent = 'Error';
                previewUnpaid.textContent = 'Error';
                previewArchived.textContent = 'Error';
                previewAmount.textContent = 'Error';
            }
        })
        .catch(error => {
            console.error('Preview error:', error);
            previewTotal.textContent = 'Failed to load';
            previewPaid.textContent = 'Failed to load';
            previewUnpaid.textContent = 'Failed to load';
            previewArchived.textContent = 'Failed to load';
            previewAmount.textContent = 'Failed to load';
        });
}

function previewPostPayment() {
    const previewDiv = document.getElementById('postPaymentPreview');
    const previewText = document.getElementById('postPaymentPreviewText');
    
    previewDiv.classList.remove('hidden');
    previewText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading preview...';
    
    fetch('/invoices/post-payment-preview')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                previewText.innerHTML = `
                    <i class="fas fa-info-circle mr-2"></i>
                    Found ${data.count} invoices eligible for post-payment archiving totaling ${data.formatted_total}.
                    These invoices were unpaid at year-end but have since been paid and passed the retention period.
                `;
            } else {
                previewText.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Failed to preview: ' + (data.message || 'Unknown error');
            }
        })
        .catch(error => {
            console.error('Preview error:', error);
            previewText.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Failed to preview. Please try again.';
        });
}

// ==================== ARCHIVE FUNCTIONS ====================

function processArchive() {
    const year = document.getElementById('archiveYear').value;
    
    // Get preview data for confirmation
    fetch(`/invoices/year-end/statistics/${year}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const stats = data.data || data;
                const paidCount = stats.paid_invoices || 0;
                const unpaidCount = stats.unpaid_invoices || 0;
                const totalAmount = stats.total_amount || 0;
                const formattedAmount = stats.formatted_total_amount || '₵0.00';
                
                if (paidCount === 0) {
                    alert(`No paid invoices found for ${year}. Nothing to archive.`);
                    return;
                }
                
                showConfirmationModal(
                    `Archive ${year} Invoices`,
                    `This will archive ${paidCount} paid invoice(s) totaling ${formattedAmount}. 
                     ${unpaidCount} unpaid invoice(s) will remain active for collection.\n\nType "ARCHIVE ${year}" to confirm.`,
                    function() {
                        performArchive(year);
                    }
                );
            } else {
                alert('Failed to load statistics for preview. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load statistics. Please try again.');
        });
}

function performArchive(year) {
    const loadingOverlay = document.getElementById('loadingOverlay');
    loadingOverlay.classList.remove('hidden');
    
    fetch('/invoices/year-end/process', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ year: year })
    })
    .then(response => response.json())
    .then(data => {
        loadingOverlay.classList.add('hidden');
        
        if (data.success) {
            alert(`✅ ${data.message}\n\nArchived: ${data.archived_count}\nKept: ${data.unpaid_kept}`);
            window.location.reload();
        } else {
            alert(`❌ Error: ${data.message}`);
        }
    })
    .catch(error => {
        loadingOverlay.classList.add('hidden');
        console.error('Archive error:', error);
        alert('Failed to process archive: ' + error.message);
    });
}

function processPostPaymentArchive() {
    showConfirmationModal(
        'Process Post-Payment Archiving',
        'This will archive invoices that were unpaid at year-end but have since been paid and passed the retention period.\n\nType "ARCHIVE" to confirm.',
        function() {
            performPostPaymentArchive();
        }
    );
}

function performPostPaymentArchive() {
    const loadingOverlay = document.getElementById('loadingOverlay');
    loadingOverlay.classList.remove('hidden');
    
    fetch('/invoices/post-payment-archive', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        loadingOverlay.classList.add('hidden');
        
        if (data.success) {
            alert(`✅ ${data.message}\n\nProcessed: ${data.total}\nArchived: ${data.archived}`);
            window.location.reload();
        } else {
            alert(`❌ Error: ${data.message}`);
        }
    })
    .catch(error => {
        loadingOverlay.classList.add('hidden');
        console.error('Post-payment archive error:', error);
        alert('Failed to process post-payment archiving: ' + error.message);
    });
}

// ==================== CONFIRMATION MODAL ====================

function showConfirmationModal(title, message, actionCallback) {
    const modal = document.getElementById('confirmationModal');
    const messageEl = document.getElementById('confirmationMessage');
    const previewEl = document.getElementById('confirmationPreview');
    const confirmBtn = document.getElementById('confirmActionBtn');
    
    messageEl.innerHTML = `<strong>${title}</strong><br><br>${message}`;
    previewEl.innerHTML = '';
    pendingAction = actionCallback;
    
    modal.classList.remove('hidden');
    
    // Store the original click handler
    confirmBtn.onclick = function() {
        modal.classList.add('hidden');
        if (pendingAction) pendingAction();
        pendingAction = null;
    };
}

function closeConfirmationModal() {
    const modal = document.getElementById('confirmationModal');
    modal.classList.add('hidden');
    pendingAction = null;
}

// ==================== HELPER FUNCTIONS ====================

// Auto-preview when year changes
document.getElementById('archiveYear')?.addEventListener('change', function() {
    previewArchive();
});

// Load initial preview
setTimeout(() => {
    previewArchive();
}, 500);

// Auto-hide messages after 5 seconds
setTimeout(() => {
    document.querySelectorAll('.bg-green-100, .bg-red-100').forEach(el => {
        el.style.display = 'none';
    });
}, 5000);

// Close modal on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeConfirmationModal();
    }
});

// Close modal when clicking outside
document.getElementById('confirmationModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeConfirmationModal();
    }
});
</script>

<style>
.btn-primary {
    background-color: var(--primary);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    text-decoration: none;
    display: inline-block;
}

.btn-primary:hover {
    opacity: 0.9;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-secondary {
    background-color: var(--secondary);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    text-decoration: none;
    display: inline-block;
}

.btn-secondary:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.btn-success {
    background-color: var(--success);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    text-decoration: none;
    display: inline-block;
}

.btn-success:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.btn-info {
    background-color: var(--info);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    text-decoration: none;
    display: inline-block;
}

.btn-info:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.btn-warning {
    background-color: var(--warning);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    text-decoration: none;
    display: inline-block;
}

.btn-warning:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

/* Modal animations */
#confirmationModal {
    transition: opacity 0.3s ease;
}

#confirmationModal.hidden {
    opacity: 0;
    pointer-events: none;
}

#confirmationModal:not(.hidden) {
    opacity: 1;
    pointer-events: auto;
}

/* Dark mode adjustments */
.dark .bg-yellow-100 {
    background-color: rgba(234, 179, 8, 0.2) !important;
    color: #eab308 !important;
}
</style>
@endsection