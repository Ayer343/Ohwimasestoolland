@extends('layouts.app')

@section('title', 'Generate Manual Invoice')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Generate Manual Invoice</h2>
            <div class="flex space-x-2">
                <a href="{{ route('invoices.index') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Invoices
                </a>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Success!</strong>
        <span class="block sm:inline">{{ session('success') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Error Message -->
    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Error!</strong>
        <span class="block sm:inline">{{ session('error') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Form Card -->
    <div class="card p-6">
        <form action="{{ route('invoices.generate-manual') }}" method="POST">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Property Selection -->
                <div>
                    <label for="property_id" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Property *</label>
                    <select name="property_id" id="property_id" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" required>
                        <option value="">Select a Property</option>
                        @foreach($properties as $property)
                            <option value="{{ $property->id }}" {{ old('property_id') == $property->id ? 'selected' : '' }} data-status="{{ $property->status }}">
                                {{ $property->house_number }} {{ $property->street_name }} 
                                @if($property->block_number)- Block {{ $property->block_number }}@endif
                                - {{ $property->landlord->name }}
                                @if($property->status != 'active')
                                    ({{ ucfirst($property->status) }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @error('property_id')
                        <p class="text-danger text-sm mt-1" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                    
                    <!-- Property status warning -->
                    <div id="propertyStatusWarning" class="hidden mt-2 p-2 text-sm rounded" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <span id="propertyStatusMessage"></span>
                    </div>
                </div>

                <!-- Period (Year-Month) -->
                <div>
                    <label for="period" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Billing Period (YYYY-MM) *</label>
                    <input type="month" name="period" id="period" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           value="{{ old('period') }}" required min="{{ now()->subMonths(12)->format('Y-m') }}" max="{{ now()->addMonths(3)->format('Y-m') }}">
                    @error('period')
                        <p class="text-danger text-sm mt-1" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                    
                    <!-- Period validation feedback -->
                    <div id="periodFeedback" class="hidden mt-2 p-2 text-sm rounded" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <span id="periodMessage"></span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <!-- Due Date -->
                <div>
                    <label for="due_date" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Due Date *</label>
                    <input type="date" name="due_date" id="due_date" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           value="{{ old('due_date') }}" required min="{{ date('Y-m-d') }}">
                    @error('due_date')
                        <p class="text-danger text-sm mt-1" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Amount -->
                <div>
                    <label for="amount" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Amount *</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--text-secondary);">
                            {{ $settings->currency_symbol ?? ($settings->currency_code === 'GHS' ? '₵' : '$') }}
                        </span>
                        <input type="number" name="amount" id="amount" step="0.01" min="0.01" class="w-full p-2 pl-8 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               value="{{ old('amount') }}" placeholder="0.00" required>
                    </div>
                    @error('amount')
                        <p class="text-danger text-sm mt-1" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Description (Optional) -->
                <div>
                    <label for="description" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">Description (Optional)</label>
                    <input type="text" name="description" id="description" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           value="{{ old('description') }}" placeholder="e.g., Monthly service charge">
                    @error('description')
                        <p class="text-danger text-sm mt-1" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- NEW: Advanced Options -->
            <div class="mb-6">
                <button type="button" onclick="toggleAdvancedOptions()" class="text-sm flex items-center" style="color: var(--primary);">
                    <i class="fas fa-chevron-down mr-1" id="advancedIcon"></i> Advanced Options
                </button>
            </div>

            <div id="advancedOptions" class="hidden mb-6 p-4 rounded" style="background-color: var(--bg-secondary);">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Apply Penalty -->
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="apply_penalty" id="apply_penalty" value="1" class="mr-2" {{ old('apply_penalty') ? 'checked' : '' }}>
                            <span class="text-sm font-medium" style="color: var(--text-primary);">Apply Late Penalty</span>
                        </label>
                        <div id="penaltySection" class="{{ old('apply_penalty') ? '' : 'hidden' }} mt-3">
                            <label for="penalty_amount" class="block text-xs mb-1" style="color: var(--text-secondary);">Penalty Amount</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--text-secondary);">{{ $settings->currency_symbol ?? ($settings->currency_code === 'GHS' ? '₵' : '$') }}</span>
                                <input type="number" name="penalty_amount" id="penalty_amount" step="0.01" min="0" class="w-full p-2 pl-8 border rounded text-sm" 
                                       style="background-color: var(--bg-primary); color: var(--text-primary); border-color: var(--border-color);"
                                       value="{{ old('penalty_amount', $settings->fixed_penalty_amount ?? 0) }}" placeholder="0.00">
                            </div>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Default: {{ $settings->formatAmount($settings->fixed_penalty_amount ?? 0) }}
                            </p>
                        </div>
                    </div>

                    <!-- Notifications -->
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="send_notification" id="send_notification" value="1" class="mr-2" {{ old('send_notification', $remindersEnabled ? 'checked' : '') }}>
                            <span class="text-sm font-medium" style="color: var(--text-primary);">Send Notification</span>
                        </label>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Send invoice notification to landlord
                        </p>
                    </div>

                    <!-- NEW: Ignore Bulk Coverage -->
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="ignore_bulk_coverage" id="ignore_bulk_coverage" value="1" class="mr-2" {{ old('ignore_bulk_coverage') ? 'checked' : '' }}>
                            <span class="text-sm font-medium" style="color: var(--text-primary);">⚠️ Ignore Bulk Coverage</span>
                        </label>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Force generation even if period is covered by bulk payment (not recommended)
                        </p>
                    </div>
                </div>
            </div>

            <!-- NEW: Bulk Coverage Alert (dynamic) -->
            <div id="bulkCoverageAlert" class="hidden mb-6 p-4 rounded" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                <div class="flex items-start">
                    <i class="fas fa-shield-alt mt-1 mr-3 text-xl" style="color: var(--warning);"></i>
                    <div>
                        <p class="font-medium mb-1" style="color: var(--warning);">⚠️ Period Covered by Bulk Payment</p>
                        <p class="text-sm" style="color: var(--text-secondary);" id="bulkCoverageMessage"></p>
                        <div id="bulkCoverageDetails" class="mt-2 p-2 rounded hidden" style="background-color: rgba(var(--info-rgb), 0.1);">
                            <!-- Coverage details will be populated here -->
                        </div>
                        <p class="text-xs mt-2" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Check "Ignore Bulk Coverage" in advanced options to override (may cause duplicate billing).
                        </p>
                    </div>
                </div>
            </div>

            <!-- Invoice Preview Section -->
            <div id="invoicePreview" class="hidden mb-6 p-6 border rounded" style="background-color: var(--bg-secondary); border-color: var(--border-color);">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Invoice Preview</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-3">
                        <div>
                            <p class="text-sm" style="color: var(--text-secondary);">Property:</p>
                            <p id="previewProperty" class="font-medium" style="color: var(--text-primary);"></p>
                        </div>
                        <div>
                            <p class="text-sm" style="color: var(--text-secondary);">Landlord:</p>
                            <p id="previewLandlord" class="font-medium" style="color: var(--text-primary);"></p>
                        </div>
                        <div>
                            <p class="text-sm" style="color: var(--text-secondary);">Billing Period:</p>
                            <p id="previewPeriod" class="font-medium" style="color: var(--text-primary);"></p>
                        </div>
                        <div>
                            <p class="text-sm" style="color: var(--text-secondary);">Digital Address:</p>
                            <p id="previewDigitalAddress" class="text-sm" style="color: var(--text-primary);"></p>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <p class="text-sm" style="color: var(--text-secondary);">Due Date:</p>
                            <p id="previewDueDate" class="font-medium" style="color: var(--text-primary);"></p>
                        </div>
                        <div>
                            <p class="text-sm" style="color: var(--text-secondary);">Base Amount:</p>
                            <p id="previewBaseAmount" class="font-medium" style="color: var(--text-primary);"></p>
                        </div>
                        <div id="previewPenaltyRow" class="hidden">
                            <p class="text-sm" style="color: var(--text-secondary);">Penalty:</p>
                            <p id="previewPenalty" class="font-medium" style="color: var(--danger);"></p>
                        </div>
                        <div class="pt-2 border-t" style="border-color: var(--border-color);">
                            <p class="text-sm" style="color: var(--text-secondary);">Total Amount:</p>
                            <p id="previewTotalAmount" class="font-medium text-lg" style="color: var(--success);"></p>
                        </div>
                        <div>
                            <p class="text-sm" style="color: var(--text-secondary);">Status:</p>
                            <span id="previewStatus" class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">Pending</span>
                        </div>
                    </div>
                </div>
                
                @if($settings->shouldSendPaymentReminders())
                <div class="mt-4 p-2 rounded" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-bell mr-1"></i>
                        Payment reminders will be sent {{ $reminderDays }} days before due date
                    </p>
                </div>
                @endif
            </div>

            <!-- Existing Invoices Alert -->
            <div id="existingInvoiceAlert" class="hidden mb-6 p-4 rounded" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle mt-1 mr-3" style="color: var(--warning);"></i>
                    <div>
                        <p class="font-medium mb-1" style="color: var(--warning);">Existing Invoice Found</p>
                        <p class="text-sm" style="color: var(--text-secondary);" id="existingInvoiceMessage"></p>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex justify-end space-x-3">
                <a href="{{ route('invoices.index') }}" class="px-6 py-2 border rounded flex items-center" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <i class="fas fa-times mr-2"></i> Cancel
                </a>
                <button type="submit" class="btn-primary flex items-center" id="submitButton">
                    <i class="fas fa-file-invoice-dollar mr-2"></i> Generate Invoice
                </button>
            </div>
        </form>
    </div>

    <!-- NEW: Bulk Coverage Info Card -->
    @if(!empty($bulkCoverageWarnings))
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
            <i class="fas fa-shield-alt mr-2" style="color: var(--info);"></i>
            Active Bulk Coverage Summary
        </h3>
        <div class="space-y-3">
            @foreach($bulkCoverageWarnings as $propertyId => $coverage)
            <div class="p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.1);">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="font-medium" style="color: var(--text-primary);">{{ $coverage['property_address'] }}</p>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-calendar-alt mr-1"></i>
                            Coverage Period: {{ $coverage['coverage_start'] }} - {{ $coverage['coverage_end'] }}
                        </p>
                    </div>
                    <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                        {{ $coverage['months_covered'] }} months
                    </span>
                </div>
            </div>
            @endforeach
        </div>
        <p class="text-xs mt-3" style="color: var(--text-secondary);">
            <i class="fas fa-info-circle mr-1"></i>
            These properties have active bulk coverage. Generating invoices for covered periods will be blocked unless "Ignore Bulk Coverage" is checked.
        </p>
    </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const propertySelect = document.getElementById('property_id');
    const periodInput = document.getElementById('period');
    const dueDateInput = document.getElementById('due_date');
    const amountInput = document.getElementById('amount');
    const descriptionInput = document.getElementById('description');
    const applyPenaltyCheck = document.getElementById('apply_penalty');
    const penaltyAmount = document.getElementById('penalty_amount');
    const penaltySection = document.getElementById('penaltySection');
    const ignoreBulkCoverage = document.getElementById('ignore_bulk_coverage');
    const invoicePreview = document.getElementById('invoicePreview');
    const existingInvoiceAlert = document.getElementById('existingInvoiceAlert');
    const bulkCoverageAlert = document.getElementById('bulkCoverageAlert');
    const periodFeedback = document.getElementById('periodFeedback');
    const submitButton = document.getElementById('submitButton');
    const previewPenaltyRow = document.getElementById('previewPenaltyRow');

    // Property data mapping
    const propertiesData = {
        @foreach($properties as $property)
        '{{ $property->id }}': {
            address: '{{ $property->house_number }} {{ $property->street_name }}' + 
                     (@if($property->block_number) ' (Block {{ $property->block_number }})' @else '' @endif) +
                     (@if($property->zone) ', {{ $property->zone }}' @else '' @endif),
            landlord: '{{ $property->landlord->name }}',
            landlordPhone: '{{ $property->landlord->phone }}',
            digitalAddress: @if($property->digital_address) '{{ $property->digital_address }}' @else 'Not Assigned' @endif,
            status: '{{ $property->status }}',
            hasBulkCoverage: {{ isset($bulkCoverageWarnings[$property->id]) ? 'true' : 'false' }},
            coverageDetails: @if(isset($bulkCoverageWarnings[$property->id]))
                {
                    start: '{{ $bulkCoverageWarnings[$property->id]["coverage_start"] }}',
                    end: '{{ $bulkCoverageWarnings[$property->id]["coverage_end"] }}',
                    months: {{ $bulkCoverageWarnings[$property->id]["months_covered"] }}
                }
            @else
                null
            @endif
        },
        @endforeach
    };

    // Store existing invoices for validation
    const existingInvoices = {
        @foreach($properties as $property)
            @foreach($property->invoices as $invoice)
                '{{ $property->id }}_{{ $invoice->period }}': {
                    period: '{{ $invoice->period }}',
                    amount: '{{ number_format($invoice->amount, 2) }}',
                    status: '{{ $invoice->status }}',
                    isBulk: {{ $invoice->is_bulk_payment ? 'true' : 'false' }}
                },
            @endforeach
        @endforeach
    };

    // Toggle advanced options
    window.toggleAdvancedOptions = function() {
        const advancedOptions = document.getElementById('advancedOptions');
        const icon = document.getElementById('advancedIcon');
        
        if (advancedOptions.classList.contains('hidden')) {
            advancedOptions.classList.remove('hidden');
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-up');
        } else {
            advancedOptions.classList.add('hidden');
            icon.classList.remove('fa-chevron-up');
            icon.classList.add('fa-chevron-down');
        }
    };

    // Toggle penalty section
    applyPenaltyCheck.addEventListener('change', function() {
        if (this.checked) {
            penaltySection.classList.remove('hidden');
        } else {
            penaltySection.classList.add('hidden');
        }
        updatePreview();
    });

    // Update preview and check for existing invoices
    function updatePreview() {
        const propertyId = propertySelect.value;
        const period = periodInput.value;
        const dueDate = dueDateInput.value;
        const amount = amountInput.value ? parseFloat(amountInput.value) : 0;
        const penalty = (applyPenaltyCheck.checked && penaltyAmount.value) ? parseFloat(penaltyAmount.value) : 0;
        const totalAmount = amount + penalty;

        // Show preview if all required fields are filled
        if (propertyId && period && dueDate && amount > 0) {
            const property = propertiesData[propertyId];
            
            // Update preview content
            document.getElementById('previewProperty').textContent = property.address;
            document.getElementById('previewLandlord').textContent = `${property.landlord} (${property.landlordPhone})`;
            document.getElementById('previewPeriod').textContent = formatPeriod(period);
            document.getElementById('previewDigitalAddress').textContent = property.digitalAddress;
            document.getElementById('previewDueDate').textContent = formatDate(dueDate);
            document.getElementById('previewBaseAmount').textContent = `{{ $settings->currency_symbol ?? ($settings->currency_code === 'GHS' ? '₵' : '$') }}${amount.toFixed(2)}`;
            
            if (penalty > 0) {
                document.getElementById('previewPenalty').textContent = `+{{ $settings->currency_symbol ?? ($settings->currency_code === 'GHS' ? '₵' : '$') }}${penalty.toFixed(2)}`;
                previewPenaltyRow.classList.remove('hidden');
            } else {
                previewPenaltyRow.classList.add('hidden');
            }
            
            document.getElementById('previewTotalAmount').textContent = `{{ $settings->currency_symbol ?? ($settings->currency_code === 'GHS' ? '₵' : '$') }}${totalAmount.toFixed(2)}`;
            
            invoicePreview.classList.remove('hidden');
            
            // Check property status
            checkPropertyStatus(propertyId);
            
            // Check for existing invoice
            checkExistingInvoice(propertyId, period);
            
            // Check for bulk coverage
            checkBulkCoverage(propertyId, period);
        } else {
            invoicePreview.classList.add('hidden');
            existingInvoiceAlert.classList.add('hidden');
            bulkCoverageAlert.classList.add('hidden');
            periodFeedback.classList.add('hidden');
            document.getElementById('propertyStatusWarning').classList.add('hidden');
            enableSubmitButton();
        }
    }

    // Check property status
    function checkPropertyStatus(propertyId) {
        const property = propertiesData[propertyId];
        const warningDiv = document.getElementById('propertyStatusWarning');
        const messageSpan = document.getElementById('propertyStatusMessage');
        
        if (property.status !== 'active') {
            messageSpan.textContent = `This property is ${property.status}. Invoices can still be generated but landlord may not receive notifications.`;
            warningDiv.classList.remove('hidden');
        } else {
            warningDiv.classList.add('hidden');
        }
    }

    // Check if invoice already exists for this property and period
    function checkExistingInvoice(propertyId, period) {
        const invoiceKey = `${propertyId}_${period}`;
        
        if (existingInvoices[invoiceKey]) {
            const existing = existingInvoices[invoiceKey];
            let message = `An invoice already exists for this property and period (${formatPeriod(period)}). Amount: {{ $settings->currency_symbol ?? ($settings->currency_code === 'GHS' ? '₵' : '$') }}${existing.amount}, Status: ${existing.status}.`;
            
            if (existing.isBulk) {
                message += ' This is a bulk payment invoice.';
            }
            
            document.getElementById('existingInvoiceMessage').textContent = message;
            existingInvoiceAlert.classList.remove('hidden');
            periodFeedback.classList.remove('hidden');
            document.getElementById('periodMessage').textContent = 'An invoice for this period already exists.';
            
            if (!ignoreBulkCoverage.checked) {
                disableSubmitButton();
            }
        } else {
            existingInvoiceAlert.classList.add('hidden');
            periodFeedback.classList.add('hidden');
            
            if (!bulkCoverageAlert.classList.contains('hidden') && !ignoreBulkCoverage.checked) {
                disableSubmitButton();
            } else {
                enableSubmitButton();
            }
        }
    }

    // NEW: Check bulk coverage
    function checkBulkCoverage(propertyId, period) {
        fetch(`/invoices/check-coverage?property_id=${propertyId}&period=${period}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.covered) {
                    const message = `Period ${formatPeriod(period)} is already covered by an active bulk payment.`;
                    document.getElementById('bulkCoverageMessage').textContent = message;
                    
                    // Show coverage details
                    if (data.coverage_details) {
                        const detailsHtml = `
                            <p class="text-sm"><strong>Bulk Invoice:</strong> <a href="/invoices/${data.coverage_details.bulk_invoice_id}" class="text-primary hover:underline">#INV-${String(data.coverage_details.bulk_invoice_id).padStart(6, '0')}</a></p>
                            <p class="text-sm"><strong>Payment Date:</strong> ${data.coverage_details.payment_date}</p>
                            <p class="text-sm"><strong>Transaction:</strong> ${data.coverage_details.transaction_id}</p>
                            <p class="text-sm"><strong>Covered Periods:</strong> ${data.coverage_details.covered_periods ? data.coverage_details.covered_periods.join(', ') : 'N/A'}</p>
                        `;
                        document.getElementById('bulkCoverageDetails').innerHTML = detailsHtml;
                        document.getElementById('bulkCoverageDetails').classList.remove('hidden');
                    }
                    
                    bulkCoverageAlert.classList.remove('hidden');
                    
                    if (!ignoreBulkCoverage.checked) {
                        disableSubmitButton();
                    }
                } else {
                    bulkCoverageAlert.classList.add('hidden');
                    if (existingInvoiceAlert.classList.contains('hidden')) {
                        enableSubmitButton();
                    }
                }
            })
            .catch(error => {
                console.error('Error checking bulk coverage:', error);
            });
    }

    // Format period for display (e.g., "January 2024")
    function formatPeriod(period) {
        if (!period) return '';
        const [year, month] = period.split('-');
        const date = new Date(year, month - 1);
        return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    }

    // Format date for display (e.g., "January 15, 2024")
    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    }

    function enableSubmitButton() {
        submitButton.disabled = false;
        submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
    }

    function disableSubmitButton() {
        submitButton.disabled = true;
        submitButton.classList.add('opacity-50', 'cursor-not-allowed');
    }

    // Event listeners
    propertySelect.addEventListener('change', updatePreview);
    periodInput.addEventListener('change', updatePreview);
    dueDateInput.addEventListener('change', updatePreview);
    amountInput.addEventListener('input', updatePreview);
    descriptionInput.addEventListener('input', updatePreview);
    penaltyAmount.addEventListener('input', updatePreview);
    ignoreBulkCoverage.addEventListener('change', function() {
        if (this.checked) {
            // Re-enable submit button if user acknowledges the risk
            enableSubmitButton();
            // Show warning toast
            showToast('warning', 'Warning: Generating invoice for a period covered by bulk payment may result in duplicate billing.');
        } else {
            // Re-check validations
            updatePreview();
        }
    });

    // Set default due date to end of current month if not set
    if (!dueDateInput.value) {
        const today = new Date();
        const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        dueDateInput.value = lastDay.toISOString().split('T')[0];
    }

    // Set default period to current month if not set
    if (!periodInput.value) {
        const today = new Date();
        periodInput.value = today.toISOString().slice(0, 7);
    }

    // Auto-hide success and error messages after 5 seconds
    const successMessage = document.querySelector('.bg-green-100');
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.display = 'none';
        }, 5000);
    }
    
    const errorMessage = document.querySelector('.bg-red-100');
    if (errorMessage) {
        setTimeout(() => {
            errorMessage.style.display = 'none';
        }, 5000);
    }

    // Trigger preview update on page load if form has values
    if (propertySelect.value || periodInput.value || dueDateInput.value || amountInput.value) {
        updatePreview();
    }

    // Toast notification function
    function showToast(type, message) {
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                max-width: 350px;
            `;
            document.body.appendChild(toastContainer);
        }
        
        const toast = document.createElement('div');
        toast.style.cssText = `
            padding: 12px 16px;
            margin-bottom: 10px;
            border-radius: 8px;
            color: white;
            font-size: 14px;
            animation: slideInRight 0.3s ease-out;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            background-color: var(--${type === 'warning' ? 'warning' : type});
        `;
        
        toast.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-${type === 'warning' ? 'exclamation-triangle' : 'info-circle'} mr-2"></i>
                <span>${message}</span>
            </div>
        `;
        
        toastContainer.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease-out forwards';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }
});

// Client-side validation for period format
document.getElementById('period').addEventListener('blur', function(e) {
    const period = e.target.value;
    if (period && !/^\d{4}-\d{2}$/.test(period)) {
        e.target.setCustomValidity('Please use YYYY-MM format (e.g., 2024-01)');
    } else {
        e.target.setCustomValidity('');
    }
});

// Client-side validation for due date
document.getElementById('due_date').addEventListener('change', function(e) {
    const dueDate = new Date(e.target.value);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    if (dueDate < today) {
        e.target.setCustomValidity('Due date must be today or in the future');
    } else {
        e.target.setCustomValidity('');
    }
});
</script>

<style>
/* Button styles */
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

.btn-primary:hover:not(:disabled) {
    opacity: 0.9;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.btn-secondary {
    background-color: var(--secondary);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    border: none;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
}

.btn-secondary:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

/* Form control styles */
.form-control {
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

/* Message styles */
.bg-green-100 {
    background-color: rgba(209, 250, 229, 0.9);
    border-color: rgba(16, 185, 129, 0.3);
}

.bg-red-100 {
    background-color: rgba(254, 226, 226, 0.9);
    border-color: rgba(239, 68, 68, 0.3);
}

.text-danger {
    color: var(--danger);
}

/* Preview and alert styles */
#invoicePreview, #existingInvoiceAlert, #bulkCoverageAlert {
    transition: all 0.3s ease;
}

/* Input with currency symbol */
.relative input {
    padding-left: 2rem;
}

/* Toast animations */
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

/* Dark mode adjustments */
.dark .bg-green-100 {
    background-color: rgba(16, 185, 129, 0.2) !important;
    border-color: rgba(16, 185, 129, 0.3) !important;
    color: #10b981 !important;
}

.dark .bg-red-100 {
    background-color: rgba(239, 68, 68, 0.2) !important;
    border-color: rgba(239, 68, 68, 0.3) !important;
    color: #ef4444 !important;
}
</style>
@endsection