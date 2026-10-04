{{-- resources/views/admin/invoices/partials/show-modals.blade.php --}}

{{-- MARK AS PAID --}}
<div id="markAsPaidModal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 text-primary">Mark Invoice as Paid</h3>
            <form id="markAsPaidForm" method="POST" action="{{ route('invoices.mark-paid', $invoice->id) }}">
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
                        <input type="text" name="payment_reference" class="form-input" placeholder="Optional reference">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2 text-secondary">Payment Date</label>
                        <input type="date" name="payment_date" class="form-input" value="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2 text-secondary">Notes</label>
                        <textarea name="notes" rows="2" class="form-input" placeholder="Optional notes"></textarea>
                    </div>
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="send_confirmation" value="1" class="mr-2">
                        <span class="text-sm text-secondary">Send confirmation to landlord</span>
                    </label>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="closeMarkAsPaidModal()" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Mark as Paid</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- APPLY PENALTY --}}
<div id="applyPenaltyModal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 text-primary">Apply Penalty</h3>
            <form method="POST" action="{{ route('invoices.apply-penalty', $invoice->id) }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2 text-secondary">Penalty Amount</label>
                        <input type="number" step="0.01" min="0.01" name="penalty_amount" class="form-input" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2 text-secondary">Reason</label>
                        <input type="text" name="reason" class="form-input" maxlength="255" required>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="closeApplyPenaltyModal()" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-warning">Apply Penalty</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- REMOVE PENALTY --}}
<div id="removePenaltyModal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 text-primary">Remove Penalty</h3>
            <form method="POST" action="{{ route('invoices.remove-penalty', $invoice->id) }}">
                @csrf
                <div>
                    <label class="block text-sm font-medium mb-2 text-secondary">Reason</label>
                    <input type="text" name="reason" class="form-input" maxlength="255" required>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="closeRemovePenaltyModal()" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-warning">Remove Penalty</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- RESEND NOTIFICATION --}}
<div id="resendNotificationModal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 text-primary">Resend Notification</h3>
            <p class="text-sm mb-4 text-secondary">
                This will resend the invoice notification through all configured channels (email, SMS, WhatsApp).
            </p>
            <form method="POST" action="{{ route('invoices.resend-notification', $invoice->id) }}">
                @csrf
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeResendNotificationModal()" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-info">Resend</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- REVERSE CONSOLIDATION --}}
<div id="reverseConsolidationModal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 text-primary">Reverse Consolidation</h3>
            <p class="text-sm mb-4 text-secondary">
                This will separate the bulk invoice back into individual monthly invoices. The bulk invoice will be cancelled and all child invoices will be restored to pending status.
            </p>
            <form method="POST" action="{{ route('invoices.reverse-consolidation', $invoice->id) }}">
                @csrf
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeReverseConsolidationModal()" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-warning">Reverse Consolidation</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- EDIT INVOICE --}}
<div id="editModal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 text-primary">Edit Invoice</h3>
            <form method="POST" action="{{ route('invoices.update', $invoice->id) }}">
                @csrf
                @method('PUT')
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2 text-secondary">Amount</label>
                        <input type="number" step="0.01" min="0.01" name="amount"
                               value="{{ $invoice->amount }}" class="form-input" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2 text-secondary">Due Date</label>
                        <input type="date" name="due_date"
                               value="{{ $invoice->due_date?->format('Y-m-d') }}" class="form-input" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2 text-secondary">Description</label>
                        <input type="text" name="description"
                               value="{{ $invoice->description }}" class="form-input" maxlength="500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2 text-secondary">Notes</label>
                        <textarea name="notes" rows="2" class="form-input"></textarea>
                    </div>
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="send_notification" value="1" class="mr-2">
                        <span class="text-sm text-secondary">Notify landlord of changes</span>
                    </label>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="closeEditModal()" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- DELETE --}}
<div id="deleteModal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 text-primary">Move Invoice to Trash</h3>
            <p class="text-sm mb-4 text-secondary">
                This invoice will be moved to the trash. You can restore it later if needed.
            </p>
            <form method="POST" action="{{ route('invoices.destroy', $invoice->id) }}">
                @csrf
                @method('DELETE')
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2 text-secondary">Reason (Optional)</label>
                    <textarea name="reason" rows="2" class="form-input" placeholder="Enter reason for deletion"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeDeleteModal()" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-danger">Move to Trash</button>
                </div>
            </form>
        </div>
    </div>
</div>