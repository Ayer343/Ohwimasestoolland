@extends('layouts.landlord')

@section('title', 'Make Payment - ' . ($property->name ?? 'Property'))

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Make Payment</h2>
                <div class="mt-2">
                    <h3 class="text-lg font-medium" style="color: var(--text-primary);">{{ $property->name ?? 'Property' }}</h3>
                    <p class="text-sm" style="color: var(--text-secondary);">{{ $property->address ?? $property->street_name ?? '' }}</p>
                </div>
            </div>
            <div class="mt-4 md:mt-0">
                <a href="{{ route('landlord.invoices') }}" class="px-4 py-2 rounded flex items-center" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Invoices
                </a>
            </div>
        </div>
    </div>

    <!-- Payment Provider Status Alert -->
    @if(empty($availableMethods))
        <div class="card p-6" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid var(--danger);">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle mr-3 text-xl" style="color: var(--danger);"></i>
                <div>
                    <h3 class="font-semibold" style="color: var(--danger);">No Payment Methods Available</h3>
                    <p class="mt-1" style="color: var(--danger);">All payment providers are currently unavailable. Please contact system administrator.</p>
                </div>
            </div>
        </div>
    @else
        <!-- Success Alert -->
        @if(session('success'))
            <div class="card p-6" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid var(--success);">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-3 text-xl" style="color: var(--success);"></i>
                    <div>
                        <h3 class="font-semibold" style="color: var(--success);">Success!</h3>
                        <p class="mt-1" style="color: var(--success);">{{ session('success') }}</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Error Alert -->
        @if($errors->any())
            <div class="card p-6" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid var(--danger);">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle mr-3 text-xl" style="color: var(--danger);"></i>
                    <div>
                        <h3 class="font-semibold" style="color: var(--danger);">Please fix the following errors:</h3>
                        <ul class="mt-1 list-disc list-inside" style="color: var(--danger);">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <!-- Pre-selected Invoices Notification -->
        @if(isset($preSelected) && $preSelected && !empty($preSelectedInvoiceIds))
        <div class="card p-6 mb-4" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid var(--info);">
            <div class="flex items-start">
                <i class="fas fa-info-circle mr-3 text-xl mt-1" style="color: var(--info);"></i>
                <div class="flex-1">
                    <h3 class="font-semibold" style="color: var(--info);">Invoices Pre-selected</h3>
                    <p class="mt-1 text-sm" style="color: var(--text-secondary);">
                        {{ count($preSelectedInvoiceIds) }} invoice(s) have been pre-selected from the invoice list. 
                        @if(isset($preSelectedPaymentType) && $preSelectedPaymentType === 'bulk')
                            These invoices will be converted to a bulk payment.
                        @else
                            Review and proceed with payment.
                        @endif
                    </p>
                    
                    <!-- Selected Invoices List -->
                    <div class="mt-3 p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                        <p class="text-sm font-medium mb-2" style="color: var(--text-primary);">Selected Invoices:</p>
                        <ul class="space-y-2">
                            @foreach($preSelectedInvoices as $invoice)
                            <li class="text-sm flex items-start">
                                <i class="fas fa-file-invoice mr-2 mt-1" style="color: var(--info);"></i>
                                <div>
                                    <span class="font-medium" style="color: var(--text-primary);">INV-{{ str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</span>
                                    <span class="mx-2" style="color: var(--text-secondary);">-</span>
                                    <span style="color: var(--success);">{{ $settings->formatAmount($invoice->total_amount) }}</span>
                                    <span class="text-xs ml-2" style="color: var(--text-secondary);">
                                        @php
                                            $periodDisplay = $invoice->period;
                                            if(preg_match('/^\d{4}-\d{2}$/', $invoice->period)) {
                                                try {
                                                    $periodDisplay = \Carbon\Carbon::parse($invoice->period . '-01')->format('M Y');
                                                } catch (\Exception $e) {
                                                    // Keep original
                                                }
                                            } elseif($invoice->is_bulk_payment) {
                                                $periodDisplay = 'Bulk Payment';
                                            }
                                        @endphp
                                        ({{ $periodDisplay }})
                                    </span>
                                </div>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="mt-3 flex space-x-3">
                        <button type="button" onclick="proceedWithPreSelected()" class="px-3 py-1 text-sm rounded" style="background-color: var(--primary); color: white;">
                            <i class="fas fa-check mr-1"></i> Proceed with Selected
                        </button>
                        <a href="{{ route('landlord.invoices') }}" class="px-3 py-1 text-sm rounded" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                            <i class="fas fa-times mr-1"></i> Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Payment Options Card -->
        <div class="card p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Payment Options</h3>
            
            <form action="{{ route('landlord.payments.process') }}" method="POST" id="payment-form" novalidate>
                @csrf
                <input type="hidden" name="property_id" value="{{ $property->id }}">
                
                <!-- Payment Type Selection -->
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Payment Type</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="relative">
                            <input type="radio" id="pay-invoices" name="payment_type" value="invoices" 
                                   class="sr-only" {{ (isset($preSelectedPaymentType) && $preSelectedPaymentType == 'invoices') || (!isset($preSelectedPaymentType) && $outstandingInvoices->count() > 0) ? 'checked' : '' }} 
                                   {{ $outstandingInvoices->count() === 0 ? 'disabled' : '' }}>
                            <label for="pay-invoices" class="flex flex-col p-4 border-2 rounded-lg cursor-pointer payment-type-label 
                                {{ $outstandingInvoices->count() === 0 ? 'opacity-50 cursor-not-allowed' : '' }}" 
                                style="border-color: var(--border-color);">
                                <span class="font-semibold" style="color: var(--text-primary);">Pay Invoices</span>
                                <span class="text-sm" style="color: var(--text-secondary);">
                                    {{ $outstandingInvoices->count() > 0 ? 'Select specific invoices to pay' : 'No outstanding invoices' }}
                                </span>
                            </label>
                        </div>
                        <div class="relative">
                            <input type="radio" id="pay-bulk" name="payment_type" value="bulk" 
                                   class="sr-only" {{ (isset($preSelectedPaymentType) && $preSelectedPaymentType == 'bulk') || ($outstandingInvoices->count() === 0 && !isset($preSelectedPaymentType) && ($settings->enable_bulk_payments ?? false)) ? 'checked' : '' }} 
                                   {{ !($settings->enable_bulk_payments ?? false) ? 'disabled' : '' }}>
                            <label for="pay-bulk" class="flex flex-col p-4 border-2 rounded-lg cursor-pointer payment-type-label 
                                {{ !($settings->enable_bulk_payments ?? false) ? 'opacity-50 cursor-not-allowed' : '' }}" 
                                style="border-color: var(--border-color);">
                                <span class="font-semibold" style="color: var(--text-primary);">Bulk Payment</span>
                                <span class="text-sm" style="color: var(--text-secondary);">
                                    {{ ($settings->enable_bulk_payments ?? false) ? 'Pay multiple months in advance' : 'Bulk payments disabled' }}
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Outstanding Invoices Section -->
                @if($outstandingInvoices && $outstandingInvoices->count() > 0)
                <div class="card p-6 mb-6" id="invoices-section">
                    <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Outstanding Invoices</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary); width: 50px;">
                                        <div class="flex items-center">
                                            <input type="checkbox" id="select-all-invoices" class="mr-2" aria-label="Select all invoices">
                                            <label for="select-all-invoices" class="text-sm">Select All</label>
                                        </div>
                                    </th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Period</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Due Date</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Amount</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Status</th>
                                 </tr>
                            </thead>
                            <tbody>
                                @foreach($outstandingInvoices as $invoice)
                                @php
                                    $periodDisplay = $invoice->period;
                                    if(preg_match('/^\d{4}-\d{2}$/', $invoice->period)) {
                                        try {
                                            $periodDisplay = \Carbon\Carbon::parse($invoice->period . '-01')->format('F Y');
                                        } catch (\Exception $e) {
                                            // Keep original
                                        }
                                    } elseif($invoice->is_bulk_payment) {
                                        $periodDisplay = 'Bulk Payment';
                                    }
                                @endphp
                                <tr class="border-b" style="border-color: var(--border-color);">
                                    <td class="p-3">
                                        <input type="checkbox" name="pay_invoices[]" value="{{ $invoice->id }}" 
                                               class="invoice-checkbox" data-amount="{{ $invoice->total_amount }}"
                                               {{ $invoice->status === 'paid' ? 'disabled' : '' }}
                                               {{ (isset($preSelected) && $preSelected && in_array($invoice->id, $preSelectedInvoiceIds ?? [])) ? 'checked' : '' }}
                                               aria-label="Select invoice for {{ $periodDisplay }}">
                                     </td>
                                    <td class="p-3">
                                        <p class="font-medium" style="color: var(--text-primary);">
                                            {{ $periodDisplay }}
                                        </p>
                                        @if($invoice->is_bulk_payment)
                                            <span class="text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                                <i class="fas fa-layer-group mr-1"></i> Bulk
                                            </span>
                                        @endif
                                     </td>
                                    <td class="p-3">
                                        <p class="font-medium" style="color: var(--text-primary);">
                                            {{ \Carbon\Carbon::parse($invoice->due_date)->format('M d, Y') }}
                                        </p>
                                        @if($invoice->status === 'overdue')
                                        <p class="text-xs mt-1" style="color: var(--danger);">Overdue</p>
                                        @endif
                                     </td>
                                    <td class="p-3">
                                        <p class="font-medium" style="color: var(--success);">{{ $settings->formatAmount($invoice->total_amount) }}</p>
                                        @if($invoice->penalty_amount > 0)
                                        <p class="text-xs mt-1" style="color: var(--danger);">+{{ $settings->formatAmount($invoice->penalty_amount) }} penalty</p>
                                        @endif
                                        @if($invoice->discount_amount > 0)
                                        <p class="text-xs mt-1" style="color: var(--success);">-{{ $settings->formatAmount($invoice->discount_amount) }} discount</p>
                                        @endif
                                     </td>
                                    <td class="p-3">
                                        @php
                                            $statusColors = [
                                                'overdue' => 'danger',
                                                'pending' => 'warning',
                                                'paid' => 'success',
                                                'partial' => 'info',
                                                'consolidated' => 'info'
                                            ];
                                            $statusColor = $statusColors[$invoice->status] ?? 'secondary';
                                            $statusDisplay = $invoice->status === 'consolidated' ? 'In Bulk' : ucfirst($invoice->status);
                                        @endphp
                                        <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.2); color: var(--{{ $statusColor }});">
                                            {{ $statusDisplay }}
                                        </span>
                                     </td>
                                 </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                 <tr>
                                    <td colspan="3" class="p-3 text-right font-medium" style="color: var(--text-primary);">Total:</td>
                                    <td class="p-3 font-medium" style="color: var(--success);" id="selected-invoices-total">
                                        {{ $settings->formatAmount(0) }}
                                     </td>
                                    <td class="p-3"></td>
                                 </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                @endif

                <!-- Bulk Payment Options (Hidden by default) -->
                <div id="bulk-payment-options" class="mb-6 hidden">
                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid var(--info);">
                        <h4 class="font-semibold mb-3" style="color: var(--info);">Bulk Payment</h4>
                        
                        <!-- ✅ Existing Bulk Coverage Alert (including pending) -->
                        @if(isset($existingCoverage) && ($existingCoverage['has_coverage'] || $existingCoverage['has_pending_coverage']))
                        <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid var(--warning);">
                            <div class="flex items-start">
                                <i class="fas fa-shield-alt mr-3 mt-1" style="color: var(--warning);"></i>
                                <div class="flex-1">
                                    <p class="font-medium" style="color: var(--warning);">
                                        @if($existingCoverage['has_pending_coverage'])
                                            ⚠️ Pending & Active Bulk Coverage Detected
                                        @else
                                            Active Bulk Coverage Detected
                                        @endif
                                    </p>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                        You have existing bulk coverage for the following periods:
                                    </p>
                                    
                                    @if(!empty($existingCoverage['active_coverages']))
                                    <div class="mt-2">
                                        <p class="text-xs font-semibold mb-1" style="color: var(--success);">✅ Active Coverage (Paid):</p>
                                        <ul class="space-y-1">
                                            @foreach($existingCoverage['active_coverages'] as $coverage)
                                            <li class="text-sm flex items-center justify-between">
                                                <div>
                                                    <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                                                    <span style="color: var(--text-primary);">
                                                        Invoice #{{ $coverage['invoice_number'] }}: {{ $coverage['formatted_range'] }}
                                                        ({{ count($coverage['periods']) }} months)
                                                    </span>
                                                </div>
                                                <a href="{{ route('landlord.invoices.show', $coverage['invoice_id']) }}" 
                                                   class="text-xs px-2 py-1 rounded" style="background-color: var(--success); color: white;">
                                                    View
                                                </a>
                                            </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    @endif
                                    
                                    @if(!empty($existingCoverage['pending_coverages']))
                                    <div class="mt-2">
                                        <p class="text-xs font-semibold mb-1" style="color: var(--warning);">⏳ Pending Coverage (Unpaid):</p>
                                        <ul class="space-y-1">
                                            @foreach($existingCoverage['pending_coverages'] as $coverage)
                                            <li class="text-sm flex items-center justify-between">
                                                <div>
                                                    <i class="fas fa-hourglass-half mr-2" style="color: var(--warning);"></i>
                                                    <span style="color: var(--text-primary);">
                                                        Invoice #{{ $coverage['invoice_number'] }}: {{ $coverage['formatted_range'] }}
                                                        ({{ count($coverage['periods']) }} months)
                                                    </span>
                                                </div>
                                                <a href="{{ route('landlord.invoices.show', $coverage['invoice_id']) }}" 
                                                   class="text-xs px-2 py-1 rounded" style="background-color: var(--warning); color: white;">
                                                    Complete Payment
                                                </a>
                                            </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    @endif
                                    
                                    <p class="text-xs mt-2" style="color: var(--warning);">
                                        <i class="fas fa-info-circle mr-1"></i> 
                                        Covered months are disabled and cannot be selected again. 
                                        @if($existingCoverage['has_pending_coverage'])
                                            Please complete the pending bulk payment first, or cancel it if you want to create a new one.
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                        @endif
                        
                        <p class="text-sm mb-4" style="color: var(--text-secondary);">
                            Pay multiple months in advance. <strong class="text-primary">Months must be selected consecutively</strong> (e.g., April, May, June). 
                            This ensures continuous coverage without gaps.
                        </p>
                        
                        <!-- Consecutive Months Warning -->
                        <div id="consecutive-warning" class="mb-4 hidden"></div>
                        
                        <!-- Month Selection -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-3" style="color: var(--text-secondary);">
                                Select Months to Pay *
                            </label>
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3" id="month-selection-grid">
                                <!-- Months will be dynamically populated from server-side data -->
                                @if(isset($availableMonths) && count($availableMonths) > 0)
                                    @foreach($availableMonths as $month)
                                    <div class="relative">
                                        <input type="checkbox" 
                                               id="month-{{ $month['value'] }}" 
                                               value="{{ $month['value'] }}" 
                                               class="sr-only month-checkbox" 
                                               {{ $month['disabled'] ? 'disabled' : '' }}
                                               data-covered="{{ $month['is_covered'] ? 'true' : 'false' }}"
                                               data-pending="{{ $month['is_pending_coverage'] ? 'true' : 'false' }}"
                                               data-month-name="{{ $month['month_name'] }}"
                                               data-year="{{ $month['year'] }}"
                                               data-period="{{ $month['value'] }}">
                                        <label for="month-{{ $month['value'] }}" 
                                               class="flex items-center justify-center p-3 border-2 rounded-lg cursor-pointer text-center month-label transition-colors duration-200 
                                                      {{ $month['disabled'] ? 'opacity-50 cursor-not-allowed' : '' }}"
                                               style="border-color: var(--border-color);">
                                            <div>
                                                <span class="font-medium text-sm" style="color: var(--text-primary);">{{ $month['month_name'] }}</span>
                                                <br>
                                                <span class="text-xs" style="color: var(--text-secondary);">{{ $month['year'] }}</span>
                                                @if($month['disabled'])
                                                    <br>
                                                    <span class="text-xs mt-1 inline-block" style="color: var(--warning);">
                                                        <i class="fas fa-{{ $month['is_pending_coverage'] ? 'hourglass-half' : ($month['is_covered'] ? 'lock' : 'lock') }}"></i> 
                                                        @if($month['is_pending_coverage'])
                                                            Pending
                                                        @elseif($month['is_covered'])
                                                            Covered
                                                        @else
                                                            Past
                                                        @endif
                                                    </span>
                                                @endif
                                            </div>
                                        </label>
                                    </div>
                                    @endforeach
                                @else
                                    <div class="col-span-full text-center p-4" style="color: var(--text-secondary);">
                                        <i class="fas fa-calendar-alt mr-2"></i> No months available for bulk payment
                                    </div>
                                @endif
                            </div>
                            <p class="text-xs mt-2" style="color: var(--text-secondary);" id="month-selection-info">
                                Select 1 to {{ $settings->max_bulk_months ?? 12 }} months
                                @if(isset($existingCoverage) && $existingCoverage['coverage_count'] > 0)
                                    <span class="ml-2" style="color: var(--warning);">
                                        ({{ $existingCoverage['coverage_count'] }} month(s) already covered)
                                    </span>
                                @endif
                                @if(isset($existingCoverage) && $existingCoverage['pending_count'] > 0)
                                    <span class="ml-2" style="color: var(--warning);">
                                        ({{ $existingCoverage['pending_count'] }} pending bulk payment(s))
                                    </span>
                                @endif
                            </p>
                        </div>

                        <!-- Selected Months Summary -->
                        <div id="selected-months-summary" class="hidden">
                            <div class="flex items-center justify-between p-3 rounded" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid var(--success);">
                                <div>
                                    <span class="font-medium" style="color: var(--success);">Selected Months:</span>
                                    <span id="selected-months-list" class="text-sm ml-2" style="color: var(--text-primary);"></span>
                                </div>
                                <span id="selected-months-count" class="px-2 py-1 rounded-full text-xs font-medium" 
                                      style="background-color: var(--success); color: white;">0</span>
                            </div>
                        </div>

                        <!-- Bulk Payment Calculation -->
                        <div class="mt-4 p-3 rounded" style="background-color: rgba(var(--primary-rgb), 0.05);">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium" style="color: var(--text-primary);">Monthly Amount:</span>
                                <span class="text-sm font-medium" style="color: var(--text-primary);">
                                    {{ $settings->formatAmount($settings->calculateDues($property) ?? 0) }}
                                </span>
                            </div>
                            @if(($settings->bulk_payment_discount ?? 0) > 0)
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium" style="color: var(--success);">Bulk Discount ({{ $settings->bulk_payment_discount }}%):</span>
                                <span class="text-sm font-medium" style="color: var(--success);" id="bulk-discount-amount">-{{ $settings->formatAmount(0) }}</span>
                            </div>
                            @endif
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium" style="color: var(--text-primary);">Selected Months:</span>
                                <span class="text-sm font-medium" id="bulk-months-count" style="color: var(--text-primary);">0</span>
                            </div>
                            <div class="flex justify-between items-center border-t pt-2" style="border-color: var(--border-color);">
                                <span class="font-semibold" style="color: var(--text-primary);">Total Amount:</span>
                                <span class="font-semibold" style="color: var(--success);" id="bulk-total-amount">
                                    {{ $settings->formatAmount(0) }}
                                </span>
                            </div>
                        </div>

                        <input type="hidden" name="selected_months" id="selected-months-input" value="">
                    </div>
                </div>

                <!-- Payment Provider Selection (Updated for new gateways) -->
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Payment Provider</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        @foreach($availableMethods as $provider => $displayName)
                        @php
                            $gatewayStatus = $configurationStatus[$provider] ?? null;
                            $isAvailable = $gatewayStatus && $gatewayStatus['enabled'] && $gatewayStatus['configured'];
                            $providerIcon = match($provider) {
                                'expresspay' => 'fa-credit-card',
                                'hubtel' => 'fa-phone-alt',
                                'paystack' => 'fa-credit-card',
                                'flutterwave' => 'fa-cloud-upload-alt',
                                default => 'fa-wallet'
                            };
                            $providerColor = match($provider) {
                                'expresspay' => '#0066CC',
                                'hubtel' => '#2563EB',
                                'paystack' => '#3B82F6',
                                'flutterwave' => '#F97316',
                                default => 'var(--primary)'
                            };
                        @endphp
                        <div class="relative">
                            <input type="radio" id="provider-{{ $provider }}" name="payment_provider" value="{{ $provider }}" 
                                   class="sr-only" {{ $loop->first ? 'checked' : '' }} 
                                   {{ !$isAvailable ? 'disabled' : '' }}>
                            <label for="provider-{{ $provider }}" class="flex flex-col items-center p-4 border-2 rounded-lg cursor-pointer payment-provider-label 
                                {{ !$isAvailable ? 'opacity-50 cursor-not-allowed' : '' }}" 
                                style="border-color: var(--border-color);">
                                <i class="fas {{ $providerIcon }} text-2xl mb-2" style="color: {{ $providerColor }};" aria-hidden="true"></i>
                                <span class="font-semibold text-center" style="color: var(--text-primary);">{{ $displayName }}</span>
                                @if(isset($configurationStatus[$provider]))
                                    <span class="text-xs mt-1 px-2 py-1 rounded-full 
                                        {{ $isAvailable ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $isAvailable ? '✓ Available' : '✗ Unavailable' }}
                                    </span>
                                @endif
                            </label>
                        </div>
                        @endforeach
                    </div>
                </div>

                <!-- Provider-specific Details (Updated for new gateways) -->
                <div id="provider-details" class="mb-6">
                    <!-- Mobile Money Providers (ExpressPay, Hubtel, Flutterwave) -->
                    <div id="mobile-money-details" class="hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="phone_number" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                                    Phone Number *
                                </label>
                                <input type="text" name="phone_number" id="phone_number" 
                                       class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="e.g., 0551234567" aria-describedby="phone-help"
                                       value="{{ old('phone_number', auth()->user()->phone ?? '') }}">
                                <p id="phone-help" class="text-xs mt-1" style="color: var(--text-secondary);">
                                    Enter your mobile money number. You will receive a prompt on this number.
                                </p>
                                @error('phone_number')
                                    <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                    <p class="text-sm font-medium" style="color: var(--text-primary);">Currency</p>
                                    <p class="text-lg font-semibold mt-1" style="color: var(--success);">{{ $settings->currency_code ?? 'GHS' }} - {{ $settings->currency_symbol ?? '₵' }}</p>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">System default currency</p>
                                </div>
                                <input type="hidden" name="currency" value="{{ $settings->currency_code ?? 'GHS' }}">
                            </div>
                        </div>

                        <!-- Mobile Money Instructions -->
                        <div id="mobile-money-instructions" class="mt-4 p-4 rounded-lg hidden" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid var(--info);">
                            <h5 class="font-semibold mb-2" style="color: var(--info);" id="mobile-money-instructions-title">Payment Instructions</h5>
                            <div class="text-sm space-y-2" style="color: var(--text-secondary);" id="mobile-money-instructions-content">
                                <!-- Instructions will be loaded dynamically -->
                            </div>
                        </div>
                    </div>

                    <!-- Paystack Details -->
                    <div id="paystack-details" class="hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="email" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                                    Email Address *
                                </label>
                                <input type="email" name="email" id="email" 
                                       class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="your@email.com" aria-describedby="email-help"
                                       value="{{ old('email', auth()->user()->email) }}">
                                <p id="email-help" class="text-xs mt-1" style="color: var(--text-secondary);">Payment receipt will be sent here</p>
                                @error('email')
                                    <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                    <p class="text-sm font-medium" style="color: var(--text-primary);">Currency</p>
                                    <p class="text-lg font-semibold mt-1" style="color: var(--success);">{{ $settings->currency_code ?? 'GHS' }} - {{ $settings->currency_symbol ?? '₵' }}</p>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">System default currency</p>
                                </div>
                                <input type="hidden" name="currency" value="{{ $settings->currency_code ?? 'GHS' }}">
                            </div>
                        </div>

                        <!-- Paystack Instructions -->
                        <div id="paystack-instructions" class="mt-4 p-4 rounded-lg hidden" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid var(--info);">
                            <h5 class="font-semibold mb-2" style="color: var(--info);">Paystack Instructions</h5>
                            <div class="text-sm space-y-2" style="color: var(--text-secondary);">
                                <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> You will be redirected to Paystack payment page</p>
                                <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Choose your preferred payment method (card, bank, etc.)</p>
                                <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Complete the payment process on the secure Paystack page</p>
                                <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> You will be redirected back to this site after payment</p>
                                <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Payment receipt will be sent to your email</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Amount -->
                <div class="mb-6">
                    <label for="amount" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Amount to Pay ({{ $settings->currency_code ?? 'GHS' }}) *
                    </label>
                    <input type="number" name="amount" id="amount" step="0.01" min="0.01"
                           class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           required readonly aria-describedby="amount-description"
                           value="{{ old('amount') }}">
                    <p id="amount-description" class="text-sm mt-1" style="color: var(--text-secondary);">
                        @if($outstandingInvoices && $outstandingInvoices->count() > 0)
                            Total for selected invoices
                        @else
                            Amount for selected months
                        @endif
                    </p>
                    @error('amount')
                        <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Payment Description -->
                <div class="mb-6">
                    <label for="description" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        Payment Description
                    </label>
                    <input type="text" name="description" id="description" 
                           class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           value="{{ old('description', $outstandingInvoices && $outstandingInvoices->count() > 0 ? 'Payment for selected invoices - ' . ($property->name ?? 'Property') : 'Bulk payment - ' . ($property->name ?? 'Property')) }}"
                           aria-describedby="description-help">
                    <p id="description-help" class="text-sm mt-1" style="color: var(--text-secondary);">
                        Description for this payment transaction
                    </p>
                    @error('description')
                        <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-3 rounded flex items-center" id="submit-button" style="background-color: var(--primary); color: white;" aria-describedby="payment-confirmation">
                        <i class="fas fa-credit-card mr-2" aria-hidden="true"></i> 
                        <span id="submit-text">Process Payment</span>
                        <span id="loading-text" class="hidden">
                            <i class="fas fa-spinner fa-spin mr-2" aria-hidden="true"></i> Processing...
                        </span>
                    </button>
                </div>
                
                <!-- Payment Confirmation Modal -->
                <div id="payment-confirmation-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
                    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4" style="background-color: var(--bg-primary); color: var(--text-primary);">
                        <h3 class="text-lg font-semibold mb-4">Confirm Payment</h3>
                        <p class="mb-6" style="color: var(--text-secondary);">You are about to make a payment of <span id="confirm-amount" class="font-semibold" style="color: var(--success);"></span>. This action cannot be undone.</p>
                        <div class="flex justify-end space-x-3">
                            <button type="button" id="cancel-payment" class="px-4 py-2 rounded" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">Cancel</button>
                            <button type="button" id="confirm-payment" class="px-4 py-2 rounded" style="background-color: var(--primary); color: white;">Confirm Payment</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuration status for validation
    const configStatus = @json($configurationStatus ?? []);
    const settings = @json($settings ?? []);
    const paymentInstructions = @json($paymentInstructions ?? []);
    
    // Toggle payment type options
    const paymentTypeRadios = document.querySelectorAll('input[name="payment_type"]');
    const bulkOptions = document.getElementById('bulk-payment-options');
    const invoicesSection = document.getElementById('invoices-section');
    const amountInput = document.getElementById('amount');
    const amountDescription = document.getElementById('amount-description');
    const descriptionInput = document.getElementById('description');
    const monthlyDues = {{ isset($settings) && method_exists($settings, 'calculateDues') ? ($settings->calculateDues($property) ?? 0) : 0 }};
    
    // Bulk payment variables
    const monthSelectionGrid = document.getElementById('month-selection-grid');
    const selectedMonthsSummary = document.getElementById('selected-months-summary');
    const selectedMonthsList = document.getElementById('selected-months-list');
    const selectedMonthsCount = document.getElementById('selected-months-count');
    const bulkMonthsCount = document.getElementById('bulk-months-count');
    const bulkTotalAmount = document.getElementById('bulk-total-amount');
    const bulkDiscountAmount = document.getElementById('bulk-discount-amount');
    const selectedMonthsInput = document.getElementById('selected-months-input');
    
    // Provider elements
    const mobileMoneyDetails = document.getElementById('mobile-money-details');
    const paystackDetails = document.getElementById('paystack-details');
    const mobileMoneyInstructions = document.getElementById('mobile-money-instructions');
    const paystackInstructions = document.getElementById('paystack-instructions');
    const mobileMoneyInstructionsTitle = document.getElementById('mobile-money-instructions-title');
    const mobileMoneyInstructionsContent = document.getElementById('mobile-money-instructions-content');
    
    // Modal elements
    const confirmationModal = document.getElementById('payment-confirmation-modal');
    const confirmAmount = document.getElementById('confirm-amount');
    const cancelPaymentBtn = document.getElementById('cancel-payment');
    const confirmPaymentBtn = document.getElementById('confirm-payment');

    // Pre-selected invoices data from controller
    const preSelected = @json(isset($preSelected) && $preSelected ? true : false);
    const preSelectedPaymentType = @json(isset($preSelectedPaymentType) ? $preSelectedPaymentType : 'invoices');
    const preSelectedInvoiceIds = @json(isset($preSelectedInvoiceIds) ? $preSelectedInvoiceIds : []);

    // Coverage data from controller
    const availableMonths = @json($availableMonths ?? []);
    const existingCoverage = @json($existingCoverage ?? []);

    // Format amount with currency and proper formatting
    function formatAmount(amount) {
        const formattedAmount = parseFloat(amount).toLocaleString('en-US', {
            minimumFractionDigits: {{ $settings->decimal_places ?? 2 }},
            maximumFractionDigits: {{ $settings->decimal_places ?? 2 }}
        });
        return '{{ $settings->currency_symbol ?? '₵' }}' + formattedAmount;
    }
    
    // Format amount for input field (without currency symbol)
    function formatAmountForInput(amount) {
        return parseFloat(amount).toFixed({{ $settings->decimal_places ?? 2 }});
    }
    
    // ✅ Function to check if selected months are consecutive
    function areMonthsConsecutive(selectedValues) {
        if (selectedValues.length <= 1) return true;
        
        const sortedValues = [...selectedValues].sort();
        
        for (let i = 0; i < sortedValues.length - 1; i++) {
            const current = new Date(sortedValues[i] + '-01');
            const next = new Date(sortedValues[i + 1] + '-01');
            current.setMonth(current.getMonth() + 1);
            
            if (current.getFullYear() !== next.getFullYear() || current.getMonth() !== next.getMonth()) {
                return false;
            }
        }
        
        return true;
    }
    
    // ✅ Function to enforce consecutive month selection
    function enforceConsecutiveSelection() {
        const checkboxes = document.querySelectorAll('.month-checkbox:not([disabled])');
        const selectedCheckboxes = Array.from(checkboxes).filter(cb => cb.checked);
        
        if (selectedCheckboxes.length === 0) {
            const warningElement = document.getElementById('consecutive-warning');
            const submitButton = document.getElementById('submit-button');
            if (warningElement) warningElement.classList.add('hidden');
            if (submitButton) submitButton.disabled = false;
            return true;
        }
        
        // Get selected values
        const selectedValues = selectedCheckboxes.map(cb => cb.value);
        selectedValues.sort();
        
        // Check if they are consecutive
        const isConsecutive = areMonthsConsecutive(selectedValues);
        
        const warningElement = document.getElementById('consecutive-warning');
        const submitButton = document.getElementById('submit-button');
        
        if (!isConsecutive && selectedValues.length > 1) {
            if (warningElement) {
                const monthNames = selectedValues.map(v => {
                    const date = new Date(v + '-01');
                    return date.toLocaleString('default', { month: 'long', year: 'numeric' });
                }).join(', ');
                
                warningElement.classList.remove('hidden');
                warningElement.innerHTML = `
                    <div class="flex items-start p-3 rounded" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid var(--warning);">
                        <i class="fas fa-exclamation-triangle mr-2 mt-1" style="color: var(--warning);"></i>
                        <div class="flex-1">
                            <p class="text-sm font-medium" style="color: var(--warning);">Non-Consecutive Months Selected</p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Bulk payments must cover <strong>consecutive months</strong> (e.g., April, May, June). 
                                The selected months (${monthNames}) are not consecutive.
                                ${selectedValues.length > 1 ? 'This will leave gaps that will still require monthly payments.' : ''}
                            </p>
                            <div class="mt-2 flex space-x-2">
                                <button type="button" onclick="selectConsecutiveMonths('${selectedValues[0]}', ${selectedValues.length})" 
                                        class="text-xs px-2 py-1 rounded" style="background-color: var(--primary); color: white;">
                                    <i class="fas fa-fill-drip mr-1"></i> Fill Gap
                                </button>
                                <button type="button" onclick="clearNonConsecutive()" 
                                        class="text-xs px-2 py-1 rounded" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                                    <i class="fas fa-times mr-1"></i> Clear Selection
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            if (submitButton) submitButton.disabled = true;
            return false;
        } else {
            if (warningElement) warningElement.classList.add('hidden');
            if (submitButton) submitButton.disabled = false;
            return true;
        }
    }
    
    // ✅ Helper function to select consecutive months from a start point
    window.selectConsecutiveMonths = function(startMonth, count) {
        const checkboxes = document.querySelectorAll('.month-checkbox:not([disabled])');
        const startDate = new Date(startMonth + '-01');
        
        checkboxes.forEach(cb => cb.checked = false);
        
        for (let i = 0; i < count; i++) {
            const targetDate = new Date(startDate);
            targetDate.setMonth(startDate.getMonth() + i);
            const targetMonth = targetDate.getFullYear() + '-' + String(targetDate.getMonth() + 1).padStart(2, '0');
            
            const targetCheckbox = document.querySelector(`.month-checkbox[value="${targetMonth}"]`);
            if (targetCheckbox && !targetCheckbox.disabled) {
                targetCheckbox.checked = true;
            }
        }
        
        updateBulkPaymentDetails();
        enforceConsecutiveSelection();
    };
    
    // ✅ Helper function to clear non-consecutive selection
    window.clearNonConsecutive = function() {
        const checkboxes = document.querySelectorAll('.month-checkbox:not([disabled])');
        checkboxes.forEach(cb => cb.checked = false);
        updateBulkPaymentDetails();
        enforceConsecutiveSelection();
    };
    
    // Generate month options for bulk payment using server-side data
    function generateMonthOptions() {
        if (!monthSelectionGrid) return;
        
        if (!availableMonths || availableMonths.length === 0) {
            monthSelectionGrid.innerHTML = `
                <div class="col-span-full text-center p-4" style="color: var(--text-secondary);">
                    <i class="fas fa-calendar-alt mr-2"></i> No months available for bulk payment
                </div>
            `;
            return;
        }
        
        monthSelectionGrid.innerHTML = '';
        
        availableMonths.forEach(month => {
            const monthElement = document.createElement('div');
            monthElement.className = 'relative';
            
            let disabledText = '';
            let iconClass = 'lock';
            let statusText = '';
            
            if (month.disabled) {
                if (month.is_pending_coverage) {
                    iconClass = 'hourglass-half';
                    statusText = 'Pending';
                } else if (month.is_covered) {
                    iconClass = 'lock';
                    statusText = 'Covered';
                } else {
                    iconClass = 'lock';
                    statusText = 'Past';
                }
                disabledText = `<br><span class="text-xs mt-1 inline-block" style="color: var(--warning);">
                    <i class="fas fa-${iconClass}"></i> ${statusText}
                </span>`;
            }
            
            monthElement.innerHTML = `
                <input type="checkbox" 
                       id="month-${month.value}" 
                       value="${month.value}" 
                       class="sr-only month-checkbox" 
                       ${month.disabled ? 'disabled' : ''}
                       data-covered="${month.is_covered}"
                       data-pending="${month.is_pending_coverage || false}"
                       data-month-name="${month.month_name}"
                       data-year="${month.year}"
                       data-period="${month.value}">
                <label for="month-${month.value}" 
                       class="flex items-center justify-center p-3 border-2 rounded-lg cursor-pointer text-center month-label transition-colors duration-200 
                              ${month.disabled ? 'opacity-50 cursor-not-allowed' : ''}"
                       style="border-color: var(--border-color);">
                    <div>
                        <span class="font-medium text-sm" style="color: var(--text-primary);">${month.month_name}</span>
                        <br>
                        <span class="text-xs" style="color: var(--text-secondary);">${month.year}</span>
                        ${disabledText}
                    </div>
                </label>
            `;
            
            monthSelectionGrid.appendChild(monthElement);
        });
        
        document.querySelectorAll('.month-checkbox:not([disabled])').forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkPaymentDetails);
        });
    }
    
    // Update bulk payment details when months are selected
    function updateBulkPaymentDetails() {
        const selectedMonths = Array.from(document.querySelectorAll('.month-checkbox:checked:not([disabled])'));
        const selectedCount = selectedMonths.length;
        const selectedValues = selectedMonths.map(checkbox => checkbox.value);
        
        if (selectedMonthsCount) selectedMonthsCount.textContent = selectedCount;
        if (bulkMonthsCount) bulkMonthsCount.textContent = selectedCount;
        if (selectedMonthsInput) selectedMonthsInput.value = selectedValues.join(',');
        
        document.querySelectorAll('.month-label').forEach(label => {
            label.style.borderColor = 'var(--border-color)';
            label.style.backgroundColor = 'transparent';
        });
        
        selectedMonths.forEach(checkbox => {
            const label = document.querySelector(`label[for="${checkbox.id}"]`);
            if (label) {
                label.style.borderColor = 'var(--primary)';
                label.style.backgroundColor = 'rgba(var(--primary-rgb), 0.1)';
            }
        });
        
        if (selectedCount > 0 && selectedMonthsList) {
            const monthNames = selectedMonths.map(checkbox => {
                const [year, month] = checkbox.value.split('-');
                const date = new Date(year, month - 1);
                return date.toLocaleString('default', { month: 'long', year: 'numeric' });
            });
            
            selectedMonthsList.textContent = monthNames.join(', ');
            if (selectedMonthsSummary) selectedMonthsSummary.classList.remove('hidden');
        } else if (selectedMonthsSummary) {
            selectedMonthsSummary.classList.add('hidden');
        }
        
        const subtotal = monthlyDues * selectedCount;
        const discountPercentage = {{ $settings->bulk_payment_discount ?? 0 }};
        const discountAmount = subtotal * (discountPercentage / 100);
        const totalAmount = subtotal - discountAmount;
        
        if (bulkTotalAmount) bulkTotalAmount.textContent = formatAmount(totalAmount);
        if (bulkDiscountAmount) bulkDiscountAmount.textContent = '-' + formatAmount(discountAmount);
        
        const selectedPaymentType = document.querySelector('input[name="payment_type"]:checked');
        if (selectedPaymentType && selectedPaymentType.value === 'bulk') {
            amountInput.value = formatAmountForInput(totalAmount);
            if (amountDescription) {
                amountDescription.textContent = `Amount for ${selectedCount} months`;
                if (discountPercentage > 0) {
                    amountDescription.textContent += ` (${discountPercentage}% bulk discount applied)`;
                }
            }
            if (descriptionInput) {
                descriptionInput.value = `Bulk payment for ${selectedCount} months - {{ $property->name ?? 'Property' }}`;
            }
        }
        
        enforceConsecutiveSelection();
    }
    
    // Initialize amount based on payment type
    function initializeAmount() {
        const paymentType = document.querySelector('input[name="payment_type"]:checked');
        if (!paymentType) return;
        
        if (paymentType.value === 'invoices') {
            updateInvoiceTotal();
            if (amountDescription) amountDescription.textContent = 'Total for selected invoices';
            if (descriptionInput) descriptionInput.value = 'Payment for selected invoices - {{ $property->name ?? 'Property' }}';
            if (invoicesSection) {
                invoicesSection.style.display = 'block';
            }
        } else if (paymentType.value === 'bulk') {
            updateBulkPaymentDetails();
            if (invoicesSection) {
                invoicesSection.style.display = 'none';
            }
        }
    }
    
    if (paymentTypeRadios.length > 0) {
        paymentTypeRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'bulk') {
                    if (bulkOptions) bulkOptions.classList.remove('hidden');
                    const selectedMonths = document.querySelectorAll('.month-checkbox:checked:not([disabled])');
                    if (selectedMonths.length === 0) {
                        const firstMonth = document.querySelector('.month-checkbox:not([disabled])');
                        if (firstMonth) {
                            firstMonth.checked = true;
                            updateBulkPaymentDetails();
                        }
                    }
                } else {
                    if (bulkOptions) bulkOptions.classList.add('hidden');
                    if (selectedMonthsInput) selectedMonthsInput.value = '';
                }
                initializeAmount();
                handlePaymentTypeChange();
            });
        });
    }
    
    generateMonthOptions();
    
    // Payment provider selection functionality (Updated for new gateways)
    const paymentProviderRadios = document.querySelectorAll('input[name="payment_provider"]');
    const providerLabels = document.querySelectorAll('.payment-provider-label');
    
    function handlePaymentProviderChange() {
        const selectedProvider = document.querySelector('input[name="payment_provider"]:checked');
        
        providerLabels.forEach(label => {
            label.style.borderColor = 'var(--border-color)';
            label.style.backgroundColor = 'transparent';
        });
        
        if (selectedProvider) {
            const selectedLabel = document.querySelector(`label[for="provider-${selectedProvider.value}"]`);
            if (selectedLabel) {
                selectedLabel.style.borderColor = 'var(--primary)';
                selectedLabel.style.backgroundColor = 'rgba(var(--primary-rgb), 0.1)';
            }
            
            if (mobileMoneyDetails) mobileMoneyDetails.classList.add('hidden');
            if (paystackDetails) paystackDetails.classList.add('hidden');
            if (mobileMoneyInstructions) mobileMoneyInstructions.classList.add('hidden');
            if (paystackInstructions) paystackInstructions.classList.add('hidden');
            
            // Updated provider checks for new gateways
            if (['expresspay', 'hubtel', 'flutterwave'].includes(selectedProvider.value)) {
                if (mobileMoneyDetails) mobileMoneyDetails.classList.remove('hidden');
                if (mobileMoneyInstructions) mobileMoneyInstructions.classList.remove('hidden');
                loadMobileMoneyInstructions(selectedProvider.value);
            } else if (selectedProvider.value === 'paystack') {
                if (paystackDetails) paystackDetails.classList.remove('hidden');
                if (paystackInstructions) paystackInstructions.classList.remove('hidden');
            }
            
            updateSubmitButton(selectedProvider.value);
        }
    }
    
    function loadMobileMoneyInstructions(provider) {
        if (paymentInstructions && paymentInstructions[provider]) {
            if (mobileMoneyInstructionsTitle) {
                mobileMoneyInstructionsTitle.textContent = `${getProviderDisplayName(provider)} Instructions`;
            }
            if (mobileMoneyInstructionsContent) {
                mobileMoneyInstructionsContent.innerHTML = paymentInstructions[provider];
            }
        } else if (mobileMoneyInstructionsContent) {
            // Default instructions for new gateways
            let instructions = '';
            switch(provider) {
                case 'expresspay':
                    instructions = `
                        <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> You will receive a prompt on your mobile money number</p>
                        <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Enter your PIN to authorize the payment</p>
                        <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Payment confirmation will be sent via SMS</p>
                        <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Your invoices will be updated automatically</p>
                    `;
                    break;
                case 'hubtel':
                    instructions = `
                        <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> You will receive a prompt on your mobile money number</p>
                        <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Enter your PIN to authorize the payment</p>
                        <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Payment confirmation will be sent via SMS</p>
                        <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Your invoices will be updated automatically</p>
                    `;
                    break;
                case 'flutterwave':
                    instructions = `
                        <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> You will be redirected to Flutterwave payment page</p>
                        <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Choose your preferred payment method (card, mobile money, bank)</p>
                        <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Complete the payment process on the secure Flutterwave page</p>
                        <p><i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Payment receipt will be sent to your email</p>
                    `;
                    break;
                default:
                    instructions = `
                        <p><i class="fas fa-info-circle mr-2"></i> Please have your mobile money wallet ready.</p>
                        <p><i class="fas fa-info-circle mr-2"></i> You will receive a prompt to authorize the payment.</p>
                    `;
            }
            mobileMoneyInstructionsContent.innerHTML = instructions;
        }
    }
    
    function getProviderDisplayName(provider) {
        const providers = {
            'expresspay': 'ExpressPay',
            'hubtel': 'Hubtel',
            'paystack': 'Paystack',
            'flutterwave': 'Flutterwave'
        };
        return providers[provider] || provider;
    }
    
    function updateSubmitButton(provider) {
        const submitText = document.getElementById('submit-text');
        if (!submitText) return;
        
        switch(provider) {
            case 'paystack':
                submitText.textContent = 'Pay with Paystack';
                break;
            case 'expresspay':
                submitText.textContent = 'Pay with ExpressPay';
                break;
            case 'hubtel':
                submitText.textContent = 'Pay with Hubtel';
                break;
            case 'flutterwave':
                submitText.textContent = 'Pay with Flutterwave';
                break;
            default:
                submitText.textContent = 'Process Payment';
        }
    }
    
    paymentProviderRadios.forEach(radio => {
        radio.addEventListener('change', handlePaymentProviderChange);
    });
    
    handlePaymentProviderChange();
    
    // Invoice selection functionality
    const selectAll = document.getElementById('select-all-invoices');
    const invoiceCheckboxes = document.querySelectorAll('.invoice-checkbox:not([disabled])');
    const selectedTotal = document.getElementById('selected-invoices-total');
    
    if (selectAll && invoiceCheckboxes.length > 0) {
        selectAll.addEventListener('change', function() {
            invoiceCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateInvoiceTotal();
        });
    }
    
    if (invoiceCheckboxes.length > 0) {
        invoiceCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateInvoiceTotal();
                updateSelectAllState();
            });
        });
    }
    
    function updateSelectAllState() {
        if (!selectAll) return;
        
        const allChecked = Array.from(invoiceCheckboxes).every(checkbox => checkbox.checked);
        const someChecked = Array.from(invoiceCheckboxes).some(checkbox => checkbox.checked);
        
        selectAll.checked = allChecked;
        selectAll.indeterminate = someChecked && !allChecked;
    }
    
    function updateInvoiceTotal() {
        let total = 0;
        let selectedCount = 0;
        
        invoiceCheckboxes.forEach(checkbox => {
            if (checkbox.checked) {
                const amount = parseFloat(checkbox.dataset.amount);
                total += amount;
                selectedCount++;
            }
        });
        
        if (selectedTotal) {
            selectedTotal.textContent = formatAmount(total);
        }
        
        const selectedPaymentType = document.querySelector('input[name="payment_type"]:checked');
        if (selectedPaymentType && selectedPaymentType.value === 'invoices') {
            amountInput.value = formatAmountForInput(total);
            if (descriptionInput) {
                descriptionInput.value = `Payment for ${selectedCount} invoice(s) - {{ $property->name ?? 'Property' }}`;
            }
        }
        
        updateSelectAllState();
    }
    
    const paymentTypeLabels = document.querySelectorAll('.payment-type-label');
    const paymentTypeRadios2 = document.querySelectorAll('input[name="payment_type"]');
    
    function handlePaymentTypeChange() {
        const selectedType = document.querySelector('input[name="payment_type"]:checked');
        
        paymentTypeLabels.forEach(label => {
            label.style.borderColor = 'var(--border-color)';
            label.style.backgroundColor = 'transparent';
        });
        
        if (selectedType) {
            const selectedLabel = document.querySelector(`label[for="pay-${selectedType.value}"]`);
            if (selectedLabel) {
                selectedLabel.style.borderColor = 'var(--primary)';
                selectedLabel.style.backgroundColor = 'rgba(var(--primary-rgb), 0.1)';
            }
        }
        
        initializeAmount();
    }
    
    paymentTypeRadios2.forEach(radio => {
        radio.addEventListener('change', handlePaymentTypeChange);
    });

    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    function isValidPhoneNumber(phone) {
        const phoneRegex = /^(?:\+233|0)[235]\d{8}$/;
        return phoneRegex.test(phone.replace(/\s+/g, ''));
    }

    function resetSubmitButton() {
        const submitButton = document.getElementById('submit-button');
        const submitText = document.getElementById('submit-text');
        const loadingText = document.getElementById('loading-text');
        
        if (submitText) submitText.classList.remove('hidden');
        if (loadingText) loadingText.classList.add('hidden');
        if (submitButton) submitButton.disabled = false;
    }
    
    function showConfirmationModal() {
        const amount = parseFloat(amountInput.value);
        if (confirmAmount) confirmAmount.textContent = formatAmount(amount);
        if (confirmationModal) confirmationModal.classList.remove('hidden');
    }
    
    function hideConfirmationModal() {
        if (confirmationModal) confirmationModal.classList.add('hidden');
        resetSubmitButton();
    }
    
    if (cancelPaymentBtn) {
        cancelPaymentBtn.addEventListener('click', hideConfirmationModal);
    }
    
    if (confirmPaymentBtn) {
        confirmPaymentBtn.addEventListener('click', function() {
            hideConfirmationModal();
            document.getElementById('payment-form').submit();
        });
    }
    
    if (confirmationModal) {
        confirmationModal.addEventListener('click', function(e) {
            if (e.target === confirmationModal) {
                hideConfirmationModal();
            }
        });
    }
    
    const paymentForm = document.getElementById('payment-form');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitButton = document.getElementById('submit-button');
            const submitText = document.getElementById('submit-text');
            const loadingText = document.getElementById('loading-text');
            
            if (submitText) submitText.classList.add('hidden');
            if (loadingText) loadingText.classList.remove('hidden');
            if (submitButton) submitButton.disabled = true;
            
            const paymentProvider = document.querySelector('input[name="payment_provider"]:checked');
            if (!paymentProvider) {
                showError('Please select a payment provider.');
                resetSubmitButton();
                return;
            }
            
            const providerValue = paymentProvider.value;
            const paymentTypeElement = document.querySelector('input[name="payment_type"]:checked');
            if (!paymentTypeElement) {
                showError('Please select a payment type.');
                resetSubmitButton();
                return;
            }
            
            const paymentType = paymentTypeElement.value;
            const amount = parseFloat(amountInput.value);
            
            if (configStatus[providerValue] && !configStatus[providerValue].configured) {
                showError('This payment provider is not properly configured. Please try another provider.');
                resetSubmitButton();
                return;
            }
            
            let validationError = null;
            
            // Updated provider checks for new gateways
            if (['expresspay', 'hubtel', 'flutterwave'].includes(providerValue)) {
                const phoneNumber = document.getElementById('phone_number')?.value.trim();
                if (!phoneNumber) {
                    validationError = 'Please provide your phone number for mobile money payment.';
                } else if (!isValidPhoneNumber(phoneNumber)) {
                    validationError = 'Please provide a valid Ghana phone number (e.g., 0551234567 or +233551234567).';
                }
            } else if (providerValue === 'paystack') {
                const email = document.getElementById('email')?.value.trim();
                if (!email) {
                    validationError = 'Please provide your email address for Paystack payment.';
                } else if (!isValidEmail(email)) {
                    validationError = 'Please provide a valid email address.';
                }
            }
            
            if (validationError) {
                showError(validationError);
                resetSubmitButton();
                return;
            }
            
            if (!amount || amount <= 0) {
                showError('Please enter a valid payment amount.');
                resetSubmitButton();
                return;
            }
            
            if (paymentType === 'invoices') {
                const selectedInvoices = document.querySelectorAll('.invoice-checkbox:checked');
                if (selectedInvoices.length === 0) {
                    showError('Please select at least one invoice to pay.');
                    resetSubmitButton();
                    return;
                }
            } else if (paymentType === 'bulk') {
                const selectedMonths = document.querySelectorAll('.month-checkbox:checked:not([disabled])');
                const selectedCount = selectedMonths.length;
                const selectedValues = Array.from(selectedMonths).map(cb => cb.value);
                
                if (selectedCount < 1) {
                    showError('Please select at least 1 month for bulk payment.');
                    resetSubmitButton();
                    return;
                }
                
                if (selectedCount > {{ $settings->max_bulk_months ?? 12 }}) {
                    showError(`You cannot select more than {{ $settings->max_bulk_months ?? 12 }} months for bulk payment.`);
                    resetSubmitButton();
                    return;
                }
                
                if (!areMonthsConsecutive(selectedValues)) {
                    showError('Months must be selected consecutively (e.g., April, May, June). Please select a continuous range of months.');
                    resetSubmitButton();
                    return;
                }
                
                const subtotal = monthlyDues * selectedCount;
                const discountPercentage = {{ $settings->bulk_payment_discount ?? 0 }};
                const discountAmount = subtotal * (discountPercentage / 100);
                const expectedAmount = subtotal - discountAmount;
                
                if (Math.abs(amount - expectedAmount) > 0.01) {
                    showError('Payment amount does not match the calculated bulk payment amount. Please refresh the page and try again.');
                    resetSubmitButton();
                    return;
                }
            }
            
            if (amount >= 1000) {
                showConfirmationModal();
            } else {
                this.submit();
            }
        });
    }
    
    function showError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'card p-6 mb-4';
        errorDiv.style.backgroundColor = 'rgba(var(--danger-rgb), 0.1)';
        errorDiv.style.border = '1px solid var(--danger)';
        errorDiv.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle mr-3 text-xl" style="color: var(--danger);"></i>
                <div>
                    <h3 class="font-semibold" style="color: var(--danger);">Error</h3>
                    <p class="mt-1" style="color: var(--danger);">${message}</p>
                </div>
            </div>
        `;
        
        const formCard = document.querySelector('.card.p-6');
        if (formCard && formCard.parentNode) {
            formCard.parentNode.insertBefore(errorDiv, formCard);
        }
        
        setTimeout(() => {
            errorDiv.remove();
        }, 5000);
        
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    handlePaymentTypeChange();
    
    if (invoiceCheckboxes.length > 0) {
        updateInvoiceTotal();
        updateSelectAllState();
    }
    initializeAmount();
    
    // Pre-selected invoices handling
    if (preSelected && preSelectedInvoiceIds.length > 0) {
        console.log('Processing pre-selected invoices:', preSelectedInvoiceIds);
        
        setTimeout(() => {
            const paymentTypeRadio = document.querySelector(`input[name="payment_type"][value="${preSelectedPaymentType}"]`);
            if (paymentTypeRadio) {
                paymentTypeRadio.checked = true;
                paymentTypeRadio.dispatchEvent(new Event('change'));
                console.log('Auto-selected payment type:', preSelectedPaymentType);
            }
            
            if (preSelectedPaymentType === 'invoices') {
                preSelectedInvoiceIds.forEach(invoiceId => {
                    const checkbox = document.querySelector(`input[name="pay_invoices[]"][value="${invoiceId}"]`);
                    if (checkbox) {
                        checkbox.checked = true;
                        checkbox.dispatchEvent(new Event('change'));
                        console.log('Auto-selected invoice:', invoiceId);
                    }
                });
            }
            
            showPreSelectionNotification(preSelectedInvoiceIds.length, preSelectedPaymentType);
        }, 500);
    }
    
    function showPreSelectionNotification(count, paymentType) {
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg animate-fade-in';
        notification.style.backgroundColor = 'rgba(var(--info-rgb), 0.9)';
        notification.style.color = 'white';
        notification.innerHTML = `
            <div class="flex items-start">
                <i class="fas fa-check-circle mr-3"></i>
                <div class="flex-1">
                    <p class="font-medium">${count} invoice(s) pre-selected</p>
                    <p class="text-sm mt-1">Payment type: ${paymentType === 'bulk' ? 'Bulk Payment' : 'Pay Invoices'}</p>
                    <p class="text-xs mt-1">Review and proceed with payment</p>
                </div>
                <button class="ml-4 text-white hover:text-gray-200" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);
    }
    
    window.proceedWithPreSelected = function() {
        const preSelectedPaymentType = '{{ $preSelectedPaymentType ?? 'invoices' }}';
        const paymentTypeRadio = document.querySelector(`input[name="payment_type"][value="${preSelectedPaymentType}"]`);
        if (paymentTypeRadio) {
            paymentTypeRadio.checked = true;
            paymentTypeRadio.dispatchEvent(new Event('change'));
        }
        document.getElementById('payment-form').scrollIntoView({ behavior: 'smooth' });
    };

    @if($errors->any())
        const firstErrorField = document.querySelector('.text-red-600');
        if (firstErrorField) {
            const inputField = firstErrorField.closest('.grid-cols-2')?.querySelector('input');
            if (inputField) {
                inputField.focus();
            }
        }
    @endif
});
</script>

<style>
.payment-type-label, .payment-provider-label, .month-label {
    transition: all 0.2s ease-in-out;
}

.payment-type-label:hover, .payment-provider-label:hover, .month-label:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.btn-primary, .btn-secondary {
    transition: all 0.2s ease-in-out;
}

.btn-primary:hover, .btn-secondary:hover {
    transform: translateY(-1px);
}

@keyframes fade-in {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fade-in 0.3s ease-out;
}

@media (max-width: 768px) {
    .grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .grid-cols-2 {
        grid-template-columns: 1fr;
    }
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.fa-spinner {
    animation: spin 1s linear infinite;
}

.fixed.inset-0.bg-black {
    backdrop-filter: blur(4px);
}

input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.02);
}

.dark .bg-green-100 {
    background-color: rgba(16, 185, 129, 0.2) !important;
    color: #10b981 !important;
}

.dark .bg-red-100 {
    background-color: rgba(239, 68, 68, 0.2) !important;
    color: #ef4444 !important;
}

.dark .text-green-800 {
    color: #10b981 !important;
}

.dark .text-red-800 {
    color: #ef4444 !important;
}
</style>
@endsection