@extends('layouts.field')

@section('title', 'Pending Verifications - Field Agent')

@section('content')
<div class="verifications-container">
    <!-- Page Header -->
    <div class="verifications-header mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-clipboard-check mr-2" style="color: var(--primary);"></i>Pending Verifications
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Review and manage properties awaiting verification
                </p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('field-agent.dashboard') }}"
                   class="px-4 py-2 rounded-lg transition-all hover:shadow-md inline-flex items-center"
                   style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
                <a href="{{ route('field-agent.properties.create') }}"
                   class="px-4 py-2 rounded-lg transition-all hover:shadow-md inline-flex items-center"
                   style="background-color: var(--primary); color: white;">
                    <i class="fas fa-plus-circle mr-2"></i> Register New Property
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Summary Cards -->
    <div class="stats-grid grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg"
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Pending</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);" id="total-pending">
                        {{ isset($pendingProperties) ? $pendingProperties->total() : 0 }}
                    </p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center"
                     style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <i class="fas fa-clock text-white text-xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg"
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Added This Week</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);" id="weekly-pending">
                        {{ $weeklyPending ?? 0 }}
                    </p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center"
                     style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                    <i class="fas fa-calendar-week text-white text-xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg"
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Urgent (7+ days)</p>
                    <p class="text-2xl font-bold" style="color: #ef4444;" id="urgent-pending">
                        {{ $urgentPending ?? 0 }}
                    </p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center"
                     style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                    <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card rounded-xl p-6 transition-all hover:shadow-lg"
             style="background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Your Verification Rate</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);" id="verification-rate">
                        {{ $verificationRate ?? 0 }}%
                    </p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center"
                     style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <i class="fas fa-chart-line text-white text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Actions Bar -->
    @if(isset($pendingProperties) && $pendingProperties->total() > 0)
    <div class="bulk-actions-bar rounded-xl p-4 mb-6 flex justify-between items-center"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="flex items-center space-x-3">
            <input type="checkbox" id="selectAllCheckbox" class="bulk-checkbox">
            <label for="selectAllCheckbox" class="text-sm" style="color: var(--text-primary);">Select All</label>
            <span class="text-sm" id="selectedCountDisplay" style="color: var(--text-secondary);">0 selected</span>
        </div>
        <div>
            <button id="bulkVerifyBtn" onclick="openBulkVerification()"
                    class="px-4 py-2 rounded-lg text-sm transition-all hover:shadow-md"
                    style="background-color: var(--primary); color: white; display: none;">
                <i class="fas fa-check-double mr-1"></i> Bulk Verify
            </button>
        </div>
    </div>
    @endif

    <!-- Properties Table -->
    @if(isset($pendingProperties) && count($pendingProperties) > 0)
    <div class="properties-card rounded-xl"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="px-6 py-4 border-b flex justify-between items-center" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-list mr-2" style="color: var(--primary);"></i>Properties Pending Verification
            </h3>
            <div class="flex items-center space-x-2">
                <span class="text-xs px-2 py-1 rounded-full" style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    Total: {{ $pendingProperties->total() }}
                </span>
                <button id="refreshBtn" class="text-sm hover:underline transition-colors" style="color: var(--primary);">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full" id="verifications-table">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary); width: 40px;">
                            <input type="checkbox" id="selectAllHeader" class="bulk-checkbox">
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider sortable cursor-pointer"
                            style="color: var(--text-secondary);" data-sort="name">
                            Property Name <i class="fas fa-sort ml-1"></i>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">
                            Landlord
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider sortable cursor-pointer"
                            style="color: var(--text-secondary);" data-sort="date">
                            Registration Date <i class="fas fa-sort ml-1"></i>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">
                            Status
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody id="properties-tbody">
                    @forelse($pendingProperties as $property)
                    <tr class="verification-row hover:bg-opacity-5 transition-colors"
                        style="border-bottom: 1px solid var(--border-color);"
                        data-id="{{ $property->id }}"
                        data-date="{{ $property->created_at->timestamp }}"
                        data-name="{{ strtolower($property->property_name) }}">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input type="checkbox" class="property-checkbox bulk-checkbox" data-id="{{ $property->id }}">
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(59, 130, 246, 0.1);">
                                    <i class="fas fa-building" style="color: var(--primary);"></i>
                                </div>
                                <div>
                                    <div class="font-medium" style="color: var(--text-primary);">{{ $property->property_name }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">{{ $property->street_name ?? 'No address provided' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($property->landlord)
                                <div class="text-sm" style="color: var(--text-primary);">{{ $property->landlord->name ?? 'N/A' }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">{{ $property->landlord->email ?? 'No email' }}</div>
                            @else
                                <span class="text-sm" style="color: var(--text-secondary);">Not assigned</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm" style="color: var(--text-primary);">{{ $property->created_at->format('M d, Y') }}</div>
                            <div class="text-xs" style="color: var(--text-secondary);">{{ $property->created_at->diffForHumans() }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $daysPending = $property->created_at->diffInDays(now());
                                $statusClass = $daysPending > 7 ? 'danger' : ($daysPending > 3 ? 'warning' : 'info');
                            @endphp
                            <span class="px-2 py-1 text-xs rounded-full status-badge-{{ $statusClass }}">
                                Pending ({{ $daysPending }} days)
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <div class="flex justify-end space-x-2">
                                <button onclick="viewPropertyDetails({{ $property->id }})"
                                        class="px-3 py-1 rounded-lg text-sm transition-all hover:shadow-md inline-flex items-center"
                                        style="background-color: var(--bg-secondary); color: var(--primary); border: 1px solid var(--border-color);">
                                    <i class="fas fa-eye mr-1"></i> View
                                </button>
                                <button onclick="startVerification({{ $property->id }})"
                                        class="px-3 py-1 rounded-lg text-sm transition-all hover:shadow-md inline-flex items-center"
                                        style="background-color: var(--primary); color: white;">
                                    <i class="fas fa-clipboard-check mr-1"></i> Verify
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <i class="fas fa-check-circle text-5xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                            <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">No Pending Verifications!</p>
                            <p class="text-sm" style="color: var(--text-secondary);">All your registered properties have been verified.</p>
                            <a href="{{ route('field-agent.properties.create') }}" class="mt-4 inline-block px-4 py-2 rounded-lg text-sm hover:shadow-md"
                               style="background-color: var(--primary); color: white;">
                                <i class="fas fa-plus-circle mr-1"></i> Register New Property
                            </a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(isset($pendingProperties) && method_exists($pendingProperties, 'hasPages') && $pendingProperties->hasPages())
        <div class="px-6 py-4 border-t" style="border-color: var(--border-color);">
            {{ $pendingProperties->withQueryString()->links() }}
        </div>
        @endif
    </div>
    @else
    <div class="text-center py-12">
        <i class="fas fa-check-circle text-5xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
        <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">No Pending Verifications</p>
        <p class="text-sm" style="color: var(--text-secondary);">All caught up! No properties pending verification.</p>
    </div>
    @endif
</div>

{{-- ============================================================ --}}
{{-- INDIVIDUAL VERIFICATION MODAL                                --}}
{{-- Uses z-[9999] + grid place-items-center so it sits ABOVE    --}}
{{-- the sidebar and centers in the viewport regardless of any    --}}
{{-- parent stacking context.                                     --}}
{{-- ============================================================ --}}
<div id="verificationModal"
     class="hidden fixed inset-0 z-[9999] overflow-y-auto"
     style="background-color: rgba(0, 0, 0, 0.5);">
    <div class="min-h-screen grid place-items-center p-4">
        <div class="rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto"
             style="background-color: var(--card-bg);">
            <div class="px-6 py-4 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-clipboard-check mr-2" style="color: var(--primary);"></i>Verify Property
                </h3>
                <button type="button" onclick="closeVerificationModal()" class="text-2xl hover:opacity-75" style="color: var(--text-secondary);">
                    &times;
                </button>
            </div>
            <div class="p-6">
                <form id="verificationForm">
                    @csrf
                    <input type="hidden" name="property_id" id="propertyId">

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Verification Status
                        </label>
                        <select name="verification_status" id="verificationStatus"
                                class="w-full px-3 py-2 rounded-lg focus:outline-none focus:ring-2"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                            <option value="verified">✓ Verified - Property meets all requirements</option>
                            <option value="rejected">✗ Rejected - Property does not meet requirements</option>
                            <option value="pending_review">⏳ Pending Additional Review</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Verification Notes <span class="text-xs" style="color: var(--text-secondary);">(Optional)</span>
                        </label>
                        <textarea name="verification_notes" id="verificationNotes" rows="4"
                                  class="w-full px-3 py-2 rounded-lg focus:outline-none focus:ring-2"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);"
                                  placeholder="Add any notes about the verification process..."></textarea>
                    </div>

                    <div class="mb-4" id="rejectionReasonContainer" style="display: none;">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Rejection Reason <span class="text-red-500">*</span>
                        </label>
                        <select name="rejection_reason" id="rejectionReason"
                                class="w-full px-3 py-2 rounded-lg focus:outline-none focus:ring-2"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                            <option value="">Select a reason...</option>
                            <option value="incomplete_documentation">Incomplete Documentation</option>
                            <option value="invalid_address">Invalid Address</option>
                            <option value="duplicate_registration">Duplicate Registration</option>
                            <option value="fraudulent_information">Fraudulent Information</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeVerificationModal()"
                                class="px-4 py-2 rounded-lg transition-all"
                                style="background-color: var(--bg-secondary); color: var(--text-secondary); border: 1px solid var(--border-color);">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 rounded-lg transition-all hover:shadow-md"
                                style="background-color: var(--primary); color: white;">
                            <i class="fas fa-check-circle mr-1"></i> Submit Verification
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================ --}}
{{-- BULK VERIFICATION MODAL                                      --}}
{{-- ============================================================ --}}
<div id="bulkVerificationModal"
     class="hidden fixed inset-0 z-[9999] overflow-y-auto"
     style="background-color: rgba(0, 0, 0, 0.5);">
    <div class="min-h-screen grid place-items-center p-4">
        <div class="rounded-xl max-w-md w-full max-h-[90vh] overflow-y-auto"
             style="background-color: var(--card-bg);">
            <div class="px-6 py-4 border-b flex justify-between items-center" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    Bulk Verification
                </h3>
                <button type="button" onclick="closeBulkVerificationModal()" class="text-2xl hover:opacity-75" style="color: var(--text-secondary);">
                    &times;
                </button>
            </div>
            <div class="p-6">
                <p class="mb-4" style="color: var(--text-secondary);">
                    You have selected <span id="selectedCount" class="font-bold" style="color: var(--primary);">0</span> properties for bulk verification.
                </p>
                <form id="bulkVerificationForm">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Verification Status
                        </label>
                        <select name="verification_status" id="bulkVerificationStatus"
                                class="w-full px-3 py-2 rounded-lg focus:outline-none focus:ring-2"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                            <option value="verified">✓ Verify All</option>
                            <option value="rejected">✗ Reject All</option>
                        </select>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeBulkVerificationModal()"
                                class="px-4 py-2 rounded-lg"
                                style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 rounded-lg"
                                style="background-color: var(--primary); color: white;">
                            Verify Selected
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================ --}}
{{-- LOADING OVERLAY                                              --}}
{{-- ============================================================ --}}
<div id="loadingOverlay"
     class="hidden fixed inset-0 z-[9999] grid place-items-center"
     style="background-color: rgba(0, 0, 0, 0.5);">
    <div class="rounded-lg p-6 flex items-center space-x-3" style="background-color: var(--card-bg);">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2" style="border-color: var(--primary);"></div>
        <span style="color: var(--text-primary);">Processing...</span>
    </div>
</div>
@endsection

@push('styles')
<style>
    :root {
        --primary: #3b82f6;
        --primary-rgb: 59, 130, 246;
        --warning: #f59e0b;
        --warning-rgb: 245, 158, 11;
        --danger: #ef4444;
        --danger-rgb: 239, 68, 68;
        --success: #10b981;
        --success-rgb: 16, 185, 129;
        --info: #3b82f6;
        --info-rgb: 59, 130, 246;
        --text-primary: #1f2937;
        --text-secondary: #6b7280;
        --card-bg: #ffffff;
        --bg-secondary: #f3f4f6;
        --border-color: #e5e7eb;
    }

    @media (prefers-color-scheme: dark) {
        :root {
            --text-primary: #f9fafb;
            --text-secondary: #9ca3af;
            --card-bg: #1f2937;
            --bg-secondary: #374151;
            --border-color: #374151;
        }
    }

    .verifications-container {
        max-width: 1600px;
        margin: 0 auto;
        padding: 0 1rem;
    }

    /* Status Badges */
    .status-badge-info {
        background-color: rgba(59, 130, 246, 0.2);
        color: #3b82f6;
    }
    .status-badge-warning {
        background-color: rgba(245, 158, 11, 0.2);
        color: #f59e0b;
    }
    .status-badge-danger {
        background-color: rgba(239, 68, 68, 0.2);
        color: #ef4444;
    }
    .status-badge-success {
        background-color: rgba(16, 185, 129, 0.2);
        color: #10b981;
    }

    .bulk-checkbox {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: var(--primary);
    }

    .verification-row:hover {
        background-color: rgba(var(--primary-rgb), 0.05);
    }

    .sortable {
        user-select: none;
        cursor: pointer;
    }
    .sortable:hover {
        opacity: 0.8;
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .animate-spin {
        animation: spin 1s linear infinite;
    }

    @keyframes slide-in {
        from { transform: translateX(100%); opacity: 0; }
        to   { transform: translateX(0);    opacity: 1; }
    }
    .animate-slide-in {
        animation: slide-in 0.3s ease-out;
    }

    @media (max-width: 768px) {
        .verifications-container { padding: 0 0.5rem; }
        .stats-grid { gap: 1rem; }
        .stat-card { padding: 1rem; }
    }
</style>
@endpush

@push('scripts')
<script>
    // ============================================
    // GLOBAL VARIABLES
    // ============================================
    let selectedProperties = [];
    let currentSort = { field: 'date', direction: 'desc' };

    // ============================================
    // INITIALIZATION
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        initializeEventListeners();
        initializeSorting();
    });

    // ============================================
    // EVENT LISTENERS
    // ============================================
    function initializeEventListeners() {
        const refreshBtn = document.getElementById('refreshBtn');
        if (refreshBtn) refreshBtn.addEventListener('click', refreshData);

        const selectAllHeader = document.getElementById('selectAllHeader');
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');

        if (selectAllHeader) {
            selectAllHeader.addEventListener('change', function() {
                toggleAllCheckboxes(this.checked);
                if (selectAllCheckbox) selectAllCheckbox.checked = this.checked;
            });
        }
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                toggleAllCheckboxes(this.checked);
                if (selectAllHeader) selectAllHeader.checked = this.checked;
            });
        }

        document.querySelectorAll('.property-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedProperties);
        });

        const verificationForm = document.getElementById('verificationForm');
        if (verificationForm) verificationForm.addEventListener('submit', submitVerification);

        const bulkForm = document.getElementById('bulkVerificationForm');
        if (bulkForm) bulkForm.addEventListener('submit', submitBulkVerification);

        const statusSelect = document.getElementById('verificationStatus');
        if (statusSelect) {
            statusSelect.addEventListener('change', function() {
                const rejectionContainer = document.getElementById('rejectionReasonContainer');
                if (this.value === 'rejected') {
                    rejectionContainer.style.display = 'block';
                } else {
                    rejectionContainer.style.display = 'none';
                }
            });
        }
    }

    // ============================================
    // CHECKBOX FUNCTIONS
    // ============================================
    function toggleAllCheckboxes(checked) {
        document.querySelectorAll('.property-checkbox').forEach(checkbox => {
            checkbox.checked = checked;
        });
        updateSelectedProperties();
    }

    function updateSelectedProperties() {
        selectedProperties = [];
        document.querySelectorAll('.property-checkbox:checked').forEach(checkbox => {
            const id = parseInt(checkbox.getAttribute('data-id'));
            if (id) selectedProperties.push(id);
        });

        const selectedCountDisplay = document.getElementById('selectedCountDisplay');
        const bulkVerifyBtn = document.getElementById('bulkVerifyBtn');

        if (selectedCountDisplay) selectedCountDisplay.textContent = `${selectedProperties.length} selected`;
        if (bulkVerifyBtn) bulkVerifyBtn.style.display = selectedProperties.length > 0 ? 'inline-flex' : 'none';
    }

    // ============================================
    // SORTING
    // ============================================
    function initializeSorting() {
        document.querySelectorAll('.sortable').forEach(header => {
            header.addEventListener('click', function() {
                const sortField = this.dataset.sort;
                const tbody = document.getElementById('properties-tbody');
                const rows = Array.from(tbody.querySelectorAll('.verification-row'));

                if (currentSort.field === sortField) {
                    currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
                } else {
                    currentSort.field = sortField;
                    currentSort.direction = 'asc';
                }

                rows.sort((a, b) => {
                    let aVal, bVal;
                    switch (sortField) {
                        case 'name':
                            aVal = a.dataset.name || '';
                            bVal = b.dataset.name || '';
                            break;
                        case 'date':
                            aVal = parseInt(a.dataset.date) || 0;
                            bVal = parseInt(b.dataset.date) || 0;
                            break;
                        default:
                            aVal = 0; bVal = 0;
                    }
                    if (currentSort.direction === 'asc') {
                        return aVal > bVal ? 1 : -1;
                    }
                    return aVal < bVal ? 1 : -1;
                });

                rows.forEach(row => tbody.appendChild(row));
                updateSortIcons(sortField);
            });
        });
    }

    function updateSortIcons(activeField) {
        document.querySelectorAll('.sortable i').forEach(icon => {
            icon.className = 'fas fa-sort ml-1';
        });
        const activeHeader = document.querySelector(`.sortable[data-sort="${activeField}"] i`);
        if (activeHeader) {
            activeHeader.className = currentSort.direction === 'asc'
                ? 'fas fa-sort-up ml-1'
                : 'fas fa-sort-down ml-1';
        }
    }

    // ============================================
    // MODAL HELPERS  (FIXED — only toggle `hidden`)
    // ============================================
    function showModal(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('hidden');
        document.body.style.overflow = 'hidden';   // prevent background scroll
    }

    function hideModal(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.add('hidden');
        document.body.style.overflow = '';
    }

    // ============================================
    // VERIFICATION ACTIONS
    // ============================================
    function viewPropertyDetails(propertyId) {
        window.location.href = `/properties/${propertyId}`;
    }

    function startVerification(propertyId) {
        document.getElementById('propertyId').value = propertyId;
        document.getElementById('verificationForm').reset();
        document.getElementById('rejectionReasonContainer').style.display = 'none';
        const statusSelect = document.getElementById('verificationStatus');
        if (statusSelect) statusSelect.value = 'verified';
        showModal('verificationModal');
    }

    function closeVerificationModal() {
        hideModal('verificationModal');
    }

    async function submitVerification(e) {
        e.preventDefault();
        showLoading();

        const propertyId = document.getElementById('propertyId').value;
        const status = document.getElementById('verificationStatus').value;
        const notes = document.getElementById('verificationNotes').value;
        const rejectionReason = document.getElementById('rejectionReason')?.value || null;

        const url = '{{ route("field-agent.verifications.single") }}';

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    property_id: parseInt(propertyId),
                    verification_status: status,
                    verification_notes: notes,
                    rejection_reason: rejectionReason
                })
            });

            const result = await response.json();

            if (result.success) {
                showNotification('Property verified successfully!', 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showNotification(result.message || 'Verification failed', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('An error occurred. Please try again.', 'error');
        } finally {
            hideLoading();
            closeVerificationModal();
        }
    }

    // ============================================
    // BULK VERIFICATION
    // ============================================
    function openBulkVerification() {
        if (selectedProperties.length === 0) {
            showNotification('Please select properties to verify', 'warning');
            return;
        }
        document.getElementById('selectedCount').textContent = selectedProperties.length;
        showModal('bulkVerificationModal');
    }

    function closeBulkVerificationModal() {
        hideModal('bulkVerificationModal');
    }

    async function submitBulkVerification(e) {
        e.preventDefault();
        showLoading();

        const status = document.getElementById('bulkVerificationStatus').value;

        try {
            const response = await fetch('{{ route("field-agent.verifications.bulk") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    property_ids: selectedProperties,
                    verification_status: status
                })
            });

            const result = await response.json();

            if (result.success) {
                showNotification(`${selectedProperties.length} properties verified successfully!`, 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showNotification(result.message || 'Bulk verification failed', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('An error occurred. Please try again.', 'error');
        } finally {
            hideLoading();
            closeBulkVerificationModal();
        }
    }

    // ============================================
    // DATA REFRESH
    // ============================================
    async function refreshData() {
        showLoading();
        try {
            const response = await fetch('{{ route("field-agent.verifications.refresh") }}', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });
            const result = await response.json();

            if (result.success) {
                const totalPendingElem = document.getElementById('total-pending');
                const weeklyPendingElem = document.getElementById('weekly-pending');
                const urgentPendingElem = document.getElementById('urgent-pending');
                const verificationRateElem = document.getElementById('verification-rate');

                if (totalPendingElem) totalPendingElem.textContent = result.data.total || 0;
                if (weeklyPendingElem) weeklyPendingElem.textContent = result.data.weekly || 0;
                if (urgentPendingElem) urgentPendingElem.textContent = result.data.urgent || 0;
                if (verificationRateElem) verificationRateElem.textContent = (result.data.rate || 0) + '%';

                showNotification('Data refreshed successfully!', 'success');
            } else {
                showNotification(result.message || 'Failed to refresh data', 'error');
            }
        } catch (error) {
            console.error('Error refreshing data:', error);
            showNotification('Failed to refresh data', 'error');
        } finally {
            hideLoading();
        }
    }

    // ============================================
    // HELPERS
    // ============================================
    function showLoading() {
        showModal('loadingOverlay');
    }

    function hideLoading() {
        hideModal('loadingOverlay');
    }

    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg animate-slide-in';
        notification.style.zIndex = '10000';

        let bgColor = '#10b981';
        let icon = 'check-circle';

        switch (type) {
            case 'error':
                bgColor = '#ef4444';
                icon = 'exclamation-circle';
                break;
            case 'warning':
                bgColor = '#f59e0b';
                icon = 'exclamation-triangle';
                break;
            case 'info':
                bgColor = '#3b82f6';
                icon = 'info-circle';
                break;
        }

        notification.style.backgroundColor = bgColor;
        notification.style.color = 'white';
        notification.innerHTML = `<div class="flex items-center"><i class="fas fa-${icon} mr-2"></i><span>${message}</span></div>`;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transition = 'opacity 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
</script>
@endpush