{{-- resources/views/admin/invoices/partials/modals.blade.php --}}

{{-- ============================================================
     EXPORT PDF MODAL
============================================================ --}}
<div id="exportModal" class="modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="exportModalTitle">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 id="exportModalTitle" class="text-lg font-semibold text-primary">Export Invoices as PDF</h3>
                <button type="button" onclick="closeExportModal()" class="text-secondary hover:opacity-70" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="space-y-4">
                <div class="flex p-4 rounded-lg flash-info">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle icon-info"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-secondary">
                            Choose what to export. You can export the current page, all filtered invoices, or only the ones you've selected.
                        </p>
                    </div>
                </div>

                <div class="space-y-3">
                    <button type="button" onclick="exportCurrentPagePDF()"
                            class="w-full p-3 rounded-lg flex items-center justify-between card hover:opacity-90 transition">
                        <div class="flex items-center">
                            <i class="fas fa-file-pdf text-red-500 text-xl mr-3"></i>
                            <div class="text-left">
                                <p class="font-medium text-primary">Export Current Page</p>
                                <p class="text-xs text-secondary">Only invoices visible on this page</p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-secondary"></i>
                    </button>

                    <button type="button" onclick="exportFilteredPDF()"
                            class="w-full p-3 rounded-lg flex items-center justify-between card hover:opacity-90 transition">
                        <div class="flex items-center">
                            <i class="fas fa-filter text-blue-500 text-xl mr-3"></i>
                            <div class="text-left">
                                <p class="font-medium text-primary">Export All Filtered</p>
                                <p class="text-xs text-secondary">All invoices matching current filters ({{ $invoices->total() }} total)</p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-secondary"></i>
                    </button>

                    <button type="button" onclick="openBulkExportFromModal()"
                            class="w-full p-3 rounded-lg flex items-center justify-between card hover:opacity-90 transition">
                        <div class="flex items-center">
                            <i class="fas fa-check-square text-green-500 text-xl mr-3"></i>
                            <div class="text-left">
                                <p class="font-medium text-primary">Export Selected</p>
                                <p class="text-xs text-secondary">
                                    Export <span id="modalSelectedCount">0</span> selected invoice(s)
                                </p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-secondary"></i>
                    </button>
                </div>

                <div class="border-t pt-4 mt-4" style="border-color: var(--border-color);">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-secondary">Total invoices available:</span>
                        <span class="font-semibold text-primary">{{ $invoices->total() }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm mt-2">
                        <span class="text-secondary">Currently selected:</span>
                        <span id="modalSelectedTotal" class="font-semibold text-primary">0</span>
                    </div>
                </div>
            </div>

            <div class="flex justify-end mt-6">
                <button type="button" onclick="closeExportModal()" class="btn-secondary">Cancel</button>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================
     PDF LOADING MODAL
============================================================ --}}
<div id="pdfLoadingModal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="card p-8 text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 mx-auto mb-4"
             style="border-color: var(--primary);"></div>
        <p class="text-lg font-semibold text-primary">Generating PDF…</p>
        <p class="text-sm mt-2 text-secondary">Please wait while we prepare your document</p>
    </div>
</div>

{{-- ============================================================
     GENERATION PROGRESS MODAL
============================================================ --}}
<div id="generationProgressModal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="card p-8 text-center max-w-md w-full">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 mx-auto mb-4"
             style="border-color: var(--primary);"></div>
        <p class="text-lg font-semibold text-primary">Generating Monthly Invoices…</p>
        <p id="generationStatus" class="text-sm mt-2 text-secondary">
            Queuing the generation. This may take a few minutes for large portfolios.
        </p>
        <div class="mt-4 w-full rounded-full h-2" style="background-color: rgba(var(--primary-rgb), .1);">
            <div id="generationProgress" class="h-2 rounded-full transition-all duration-300"
                 style="width: 0%; background-color: var(--primary);"></div>
        </div>
        <button type="button" onclick="hideGenerationModal()"
                class="mt-4 text-sm text-secondary hover:opacity-70">
            Close
        </button>
    </div>
</div>

{{-- ============================================================
     SOFT DELETE MODAL
============================================================ --}}
<div id="softDeleteModal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 text-primary">Move Invoice to Trash</h3>
            <p class="text-sm mb-4 text-secondary">
                This invoice will be moved to the trash. You can restore it later if needed.
            </p>

            <div id="softDeleteWarning" class="hidden mb-4 p-3 rounded-lg flash-warning">
                <p class="text-sm text-warning">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <span id="softDeleteWarningMessage"></span>
                </p>
            </div>

            <form id="softDeleteForm" method="POST">
                @csrf
                @method('DELETE')
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2 text-secondary">Reason (Optional)</label>
                    <textarea name="reason" rows="2" class="form-input"
                              placeholder="Enter reason for deletion"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeSoftDeleteModal()" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-danger">Move to Trash</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============================================================
     MARK AS PAID MODAL
============================================================ --}}
<div id="markAsPaidModal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 text-primary">Mark Invoice as Paid</h3>
            <form id="markAsPaidForm" method="POST">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2 text-secondary">Payment Method</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="">Select Payment Method</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="manual_mobile_money">Manual Mobile Money</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2 text-secondary">Payment Reference</label>
                        <input type="text" name="payment_reference" class="form-input"
                               placeholder="Optional reference number">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2 text-secondary">Payment Date</label>
                        <input type="date" name="payment_date" class="form-input"
                               value="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div id="coverageActivationSection" class="hidden">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="activate_coverage" value="1" class="mr-2" checked>
                            <span class="text-sm text-secondary">Activate bulk coverage (prevents duplicate generation)</span>
                        </label>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2 text-secondary">Notes</label>
                        <textarea name="notes" rows="2" class="form-input" placeholder="Optional notes"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="closeMarkAsPaidModal()" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Mark as Paid</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============================================================
     REVERSE CONSOLIDATION MODAL
============================================================ --}}
<div id="reverseConsolidationModal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 text-primary">Reverse Consolidation</h3>
            <p class="text-sm mb-4 text-secondary">
                This will separate the bulk invoice back into individual monthly invoices.
                The bulk invoice will be cancelled and all child invoices will be restored to pending status.
            </p>
            <form id="reverseConsolidationForm" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2 text-secondary">Reason for Reversal</label>
                    <textarea name="reason" rows="2" class="form-input"
                              placeholder="Enter reason for reversal" required></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeReverseConsolidationModal()" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-warning">Reverse Consolidation</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============================================================
     BULK STATUS UPDATE MODAL
============================================================ --}}
<div id="bulkStatusModal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 text-primary">Bulk Update Status</h3>
            <form id="bulkStatusForm" method="POST" action="{{ route('invoices.bulk-update-status') }}">
                @csrf
                <input type="hidden" name="invoice_ids" id="bulkStatusInvoiceIds">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2 text-secondary">New Status</label>
                        <select name="status" class="form-select" required>
    <option value="">Select Status</option>
    @if($settings->isOfflinePaymentAllowed())
        <option value="paid">Paid</option>
    @endif
    <option value="pending">Pending</option>
    <option value="cancelled">Cancelled</option>
</select>

@if(!$settings->isOfflinePaymentAllowed())
    <p class="text-xs mt-2 text-secondary">
        <i class="fas fa-info-circle mr-1"></i>
        The "Paid" status is unavailable because office payments are disabled
        in System Settings. Landlords must pay through the online gateway.
    </p>
@endif
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2 text-secondary">Payment Method (if paid)</label>
                        <select name="payment_method" class="form-select">
                            <option value="">Select Payment Method</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="manual_mobile_money">Manual Mobile Money</option>
                        </select>
                    </div>
                    <div id="bulkCoverageSection" class="hidden">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="activate_coverage" value="1" class="mr-2" checked>
                            <span class="text-sm text-secondary">Activate coverage for bulk invoices</span>
                        </label>
                    </div>
                    <div>
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="send_notifications" value="1" class="mr-2">
                            <span class="text-sm text-secondary">Send notifications to landlords</span>
                        </label>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="closeBulkStatusModal()" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============================================================
     COVERAGE INFO MODAL (content loaded dynamically)
============================================================ --}}
<div id="coverageInfoModal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="card m-4 max-w-lg w-full">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-primary">Bulk Coverage Details</h3>
                <button type="button" onclick="closeCoverageModal()" class="text-secondary hover:opacity-70" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="coverageModalContent" class="space-y-4">
                {{-- Content injected by showCoverageInfo() --}}
            </div>
            <div class="flex justify-end mt-6">
                <button type="button" onclick="closeCoverageModal()" class="btn-primary">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================
     BULK COVERAGE CHECK MODAL
============================================================ --}}
<div id="bulkCoverageCheckModal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 text-primary">Check Bulk Coverage</h3>
            <form id="bulkCoverageForm" onsubmit="checkBulkCoverage(event)">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2 text-secondary">Property</label>
                        <select id="coveragePropertyId" class="form-select" required>
                            <option value="">Select Property</option>
                            @foreach($properties as $property)
                                <option value="{{ $property->id }}">
                                    {{ $property->house_number }} {{ $property->street_name }}
                                    ({{ optional($property->landlord)->name }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2 text-secondary">Period (Month)</label>
                        <input type="month" id="coveragePeriod" class="form-input" required>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="closeBulkCoverageModal()" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Check Coverage</button>
                </div>
            </form>
        </div>
    </div>
</div>