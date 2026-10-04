{{-- resources/views/landlord/invoices/partials/modals.blade.php --}}

{{-- EXPORT PDF MODAL --}}
<div id="exportModal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-primary">Export Invoices as PDF</h3>
                <button type="button" onclick="closeExportModal()" class="text-secondary hover:opacity-70" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="space-y-4">
                <div class="flex p-4 rounded-lg flash-info">
                    <i class="fas fa-info-circle icon-info mr-3"></i>
                    <p class="text-sm text-secondary">Select what you want to export.</p>
                </div>

                <div class="space-y-3">
                    <button type="button" onclick="exportCurrentPagePDF()" class="w-full p-3 card rounded-lg flex items-center justify-between hover:opacity-90 transition">
                        <div class="flex items-center">
                            <i class="fas fa-file-pdf text-red-500 text-xl mr-3"></i>
                            <div class="text-left">
                                <p class="font-medium text-primary">Export Current Page</p>
                                <p class="text-xs text-secondary">{{ $invoices->count() }} invoice(s) on this page</p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-secondary"></i>
                    </button>

                    <button type="button" onclick="exportAllInvoices()" class="w-full p-3 card rounded-lg flex items-center justify-between hover:opacity-90 transition">
                        <div class="flex items-center">
                            <i class="fas fa-database text-blue-500 text-xl mr-3"></i>
                            <div class="text-left">
                                <p class="font-medium text-primary">Export All My Invoices</p>
                                <p class="text-xs text-secondary">{{ $invoices->total() }} total</p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-secondary"></i>
                    </button>

                    <button type="button" onclick="openBulkExportFromModal()" class="w-full p-3 card rounded-lg flex items-center justify-between hover:opacity-90 transition">
                        <div class="flex items-center">
                            <i class="fas fa-check-square text-green-500 text-xl mr-3"></i>
                            <div class="text-left">
                                <p class="font-medium text-primary">Export Selected</p>
                                <p class="text-xs text-secondary">
                                    <span id="modalSelectedCount">0</span> selected invoice(s)
                                </p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-secondary"></i>
                    </button>
                </div>
            </div>

            <div class="flex justify-end mt-6">
                <button type="button" onclick="closeExportModal()" class="btn-secondary">Cancel</button>
            </div>
        </div>
    </div>
</div>

{{-- PDF LOADING MODAL --}}
<div id="pdfLoadingModal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="card p-8 text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 mx-auto mb-4" style="border-color: var(--primary);"></div>
        <p class="text-lg font-semibold text-primary">Generating PDF…</p>
        <p class="text-sm mt-2 text-secondary">Please wait while we prepare your document</p>
    </div>
</div>

{{-- COVERAGE SUMMARY MODAL --}}
<div id="coverageSummaryModal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="card m-4 max-w-2xl w-full">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-primary">Active Bulk Coverage Summary</h3>
                <button type="button" onclick="closeCoverageSummaryModal()" class="text-secondary hover:opacity-70" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="max-h-96 overflow-y-auto">
                @if(count($bulkCoverages) > 0)
                    @foreach($bulkCoverages as $propertyId => $coverages)
                        @if(is_array($coverages) && count($coverages) > 0)
                            @php $property = $properties->firstWhere('id', $propertyId); @endphp
                            <div class="mb-6 last:mb-0">
                                <h4 class="font-medium mb-2 text-primary">
                                    {{ $property->property_name ?? ($property->street_name ?? 'Property') }}
                                </h4>

                                @foreach($coverages as $coverage)
                                    @if(is_array($coverage) && !empty($coverage))
                                        @php
                                            $periods = $coverage['periods'] ?? [];
                                            $formatted = collect($periods)->map(fn ($p) =>
                                                $p ? \Carbon\Carbon::parse($p . '-01')->format('M Y') : ''
                                            )->filter()->values()->toArray();
                                        @endphp
                                        <div class="p-4 rounded-lg mb-3 flash-success">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="text-sm font-medium text-primary">
                                                    <i class="fas fa-shield-alt icon-success mr-1"></i>
                                                    {{ $coverage['invoice_number'] ?? 'INV-' . str_pad($coverage['invoice_id'], 6, '0', STR_PAD_LEFT) }}
                                                </span>
                                                <span class="pill pill-success">{{ count($periods) }} months</span>
                                            </div>
                                            <p class="text-sm mb-2 text-secondary">
                                                <i class="fas fa-calendar-alt mr-1"></i> Covered periods:
                                            </p>
                                            <div class="grid grid-cols-3 gap-2 mb-3">
                                                @foreach(array_slice($formatted, 0, 6) as $period)
                                                    <span class="pill pill-success justify-center">{{ $period }}</span>
                                                @endforeach
                                                @if(count($formatted) > 6)
                                                    <span class="pill pill-secondary justify-center">+{{ count($formatted) - 6 }} more</span>
                                                @endif
                                            </div>
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="text-secondary">
                                                    Paid: {{ $coverage['formatted_payment_date'] ?? ($coverage['payment_date'] ? \Carbon\Carbon::parse($coverage['payment_date'])->format('M d, Y') : 'N/A') }}
                                                </span>
                                                <a href="{{ route('landlord.invoices.show', $coverage['invoice_id']) }}" class="link-info">
                                                    View Invoice <i class="fas fa-arrow-right ml-1"></i>
                                                </a>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                @else
                    <div class="text-center py-8 text-secondary">
                        <i class="fas fa-shield-alt text-4xl mb-4 opacity-50"></i>
                        <p class="text-lg font-medium mb-2">No Active Coverage</p>
                        <p class="text-sm">You don't have any active bulk coverage at the moment.</p>
                    </div>
                @endif
            </div>

            <div class="flex justify-end mt-6">
                <button type="button" onclick="closeCoverageSummaryModal()" class="btn-primary">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- NO PAYMENT METHODS MODAL --}}
<div id="noPaymentMethodsModal" class="modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6 text-center">
            <i class="fas fa-exclamation-triangle text-4xl mb-4 icon-warning"></i>
            <h3 class="text-lg font-medium mb-2 text-primary">No Payment Methods Available</h3>
            <p class="text-sm mb-4 text-secondary">
                There are no payment methods currently configured. Please contact the administrator.
            </p>
            <button type="button" onclick="closeNoPaymentMethodsModal()" class="btn-primary">OK</button>
        </div>
    </div>
</div>