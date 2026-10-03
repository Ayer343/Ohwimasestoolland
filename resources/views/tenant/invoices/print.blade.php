<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #{{ $invoice->invoice_number ?? 'INV-'.str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* A5 Size Optimization */
        @page {
            size: A5 landscape;
            margin: 0.5cm;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                font-size: 9pt;
                line-height: 1.2;
                background: white;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .page-break {
                page-break-before: always;
            }
            
            /* Compact spacing for A5 */
            .p-2 { padding: 0.25rem; }
            .p-3 { padding: 0.5rem; }
            .p-4 { padding: 0.75rem; }
            .p-6 { padding: 0.75rem; }
            .mb-2 { margin-bottom: 0.25rem; }
            .mb-4 { margin-bottom: 0.5rem; }
            .mt-2 { margin-top: 0.25rem; }
            .mt-4 { margin-top: 0.5rem; }
            .gap-2 { gap: 0.25rem; }
            .gap-4 { gap: 0.5rem; }
            .text-xs { font-size: 7pt; }
            .text-sm { font-size: 8pt; }
            .text-base { font-size: 9pt; }
            .text-lg { font-size: 10pt; }
            .text-xl { font-size: 12pt; }
            .text-2xl { font-size: 14pt; }
            .text-3xl { font-size: 16pt; }
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            background: white;
            margin: 0;
            padding: 0;
        }
        
        .invoice-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px;
            border-radius: 4px 4px 0 0;
        }
        
        .border-bottom {
            border-bottom: 1px solid #e5e7eb;
        }
        
        .bg-light {
            background-color: #f9fafb;
        }

        /* Bulk invoice specific styling */
        .bulk-badge {
            background-color: #8b5cf6;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7pt;
            font-weight: 600;
            display: inline-block;
            margin-left: 4px;
        }

        .coverage-badge {
            background-color: #10b981;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7pt;
            font-weight: 600;
            display: inline-block;
        }

        /* Compact table */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 4px 6px;
            text-align: left;
        }
        
        th {
            background-color: #f3f4f6;
            font-weight: 600;
        }
        
        tr {
            border-bottom: 1px solid #e5e7eb;
        }

        /* Status badges */
        .status-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 7pt;
            font-weight: 600;
            display: inline-block;
            text-transform: uppercase;
        }
        
        .status-paid { background-color: #d1fae5; color: #065f46; }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-overdue { background-color: #fee2e2; color: #991b1b; }
        .status-partial { background-color: #dbeafe; color: #1e40af; }
        .status-consolidated { background-color: #f3e8ff; color: #6b21a8; }
        .status-cancelled { background-color: #f3f4f6; color: #374151; }
        
        /* Tenant specific styles */
        .due-badge {
            background-color: #fee2e2;
            color: #991b1b;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 6pt;
            font-weight: 600;
            display: inline-block;
            margin-left: 4px;
        }
    </style>
</head>
<body class="p-2">
    <!-- Print Controls - Hidden when printing -->
    <div class="no-print mb-2 p-2 bg-gray-100 rounded flex justify-between items-center text-xs">
        <div>
            <i class="fas fa-info-circle mr-1"></i> Optimized for A5 landscape | Tenant View
        </div>
        <div class="space-x-2">
            <button onclick="window.print()" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 text-xs">
                <i class="fas fa-print mr-1"></i> Print
            </button>
            <button onclick="window.close()" class="px-3 py-1 bg-gray-600 text-white rounded hover:bg-gray-700 text-xs">
                <i class="fas fa-times mr-1"></i> Close
            </button>
        </div>
    </div>

    <!-- Invoice Container - A5 Optimized -->
    <div class="max-w-full mx-auto">
        <!-- Header -->
        <div class="invoice-header flex justify-between items-start">
            <div>
                <h1 class="text-xl font-bold">INVOICE</h1>
                <div class="flex items-center mt-1">
                    @if(isset($invoice->is_bulk_payment) && $invoice->is_bulk_payment)
                        <span class="bulk-badge">BULK PAYMENT</span>
                    @endif
                    @if(($invoice->penalty_amount ?? 0) > 0)
                        <span class="coverage-badge ml-1" style="background-color: #ef4444;">PENALTY APPLIED</span>
                    @endif
                </div>
                <p class="text-[7pt] text-blue-100 mt-1">{{ $system_settings->system_name ?? $settings->system_name ?? 'Property Management System' }}</p>
            </div>
            <div class="text-right">
                <div class="text-lg font-bold">#{{ $invoice->invoice_number ?? 'INV-'.str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</div>
                <p class="text-[7pt] text-blue-100">Issued: {{ \Carbon\Carbon::parse($invoice->created_at)->format('d/m/Y') }}</p>
            </div>
        </div>

        <!-- Company & Tenant Info - Compact 2-col -->
        <div class="grid grid-cols-2 border-b">
            <div class="p-2">
                <h3 class="font-bold text-[8pt] mb-1">FROM</h3>
                <p class="font-bold text-[8pt]">{{ $system_settings->system_name ?? $settings->system_name ?? 'Property Management System' }}</p>
                <p class="text-[7pt]">{{ $system_settings->system_address ?? $settings->company_address ?? 'Company Address' }}</p>
                <p class="text-[7pt]">{{ $system_settings->system_phone ?? $settings->company_phone ?? 'Phone Number' }}</p>
                @if(($system_settings->system_email ?? $settings->company_email ?? false))
                    <p class="text-[7pt]">{{ $system_settings->system_email ?? $settings->company_email }}</p>
                @endif
                @if(($system_settings->tax_id ?? $settings->company_vat_number ?? false))
                    <p class="text-[7pt]">Tax ID: {{ $system_settings->tax_id ?? $settings->company_vat_number }}</p>
                @endif
            </div>
            
            <div class="p-2 bg-light">
                <h3 class="font-bold text-[8pt] mb-1">BILL TO</h3>
                <p class="font-bold text-[8pt]">{{ $invoice->tenant->name ?? 'Tenant Name' }}</p>
                @if(isset($invoice->tenant) && $invoice->tenant->phone)
                    <p class="text-[7pt]">{{ $invoice->tenant->phone }}</p>
                @endif
                @if(isset($invoice->tenant) && $invoice->tenant->email)
                    <p class="text-[7pt] truncate">{{ $invoice->tenant->email }}</p>
                @endif
                @if(isset($invoice->tenant) && $invoice->tenant->tenant_id)
                    <p class="text-[7pt]">Tenant ID: {{ $invoice->tenant->tenant_id }}</p>
                @endif
            </div>
        </div>

        <!-- Invoice Details - Compact 4-col -->
        <div class="grid grid-cols-4 gap-1 p-2 border-b text-[8pt]">
            <div>
                <span class="text-gray-500">Date:</span>
                <span class="font-bold ml-1">{{ \Carbon\Carbon::parse($invoice->created_at)->format('d/m/Y') }}</span>
            </div>
            <div>
                <span class="text-gray-500">Due:</span>
                <span class="font-bold ml-1 {{ \Carbon\Carbon::parse($invoice->due_date)->isPast() && $invoice->status != 'paid' ? 'text-red-600' : '' }}">
                    {{ \Carbon\Carbon::parse($invoice->due_date)->format('d/m/Y') }}
                    @if(\Carbon\Carbon::parse($invoice->due_date)->isPast() && $invoice->status != 'paid')
                        <span class="due-badge">OVERDUE</span>
                    @endif
                </span>
            </div>
            <div>
                <span class="text-gray-500">Period:</span>
                <span class="font-bold ml-1">
                    @php
                        if(isset($invoice->is_bulk_payment) && $invoice->is_bulk_payment) {
                            if(isset($invoice->bulk_start_month) && isset($invoice->bulk_end_month) && $invoice->bulk_start_month && $invoice->bulk_end_month) {
                                try {
                                    echo \Carbon\Carbon::parse($invoice->bulk_start_month . '-01')->format('M y') . ' - ' . 
                                         \Carbon\Carbon::parse($invoice->bulk_end_month . '-01')->format('M y');
                                } catch (\Exception $e) {
                                    echo ($invoice->bulk_months ?? 'N/A') . ' months';
                                }
                            } elseif(isset($invoice->bulk_months)) {
                                echo $invoice->bulk_months . ' months';
                            } else {
                                echo 'Bulk Payment';
                            }
                        } elseif(isset($invoice->period) && preg_match('/^\d{4}-\d{2}$/', $invoice->period)) {
                            echo \Carbon\Carbon::parse($invoice->period . '-01')->format('M Y');
                        } elseif(isset($invoice->period)) {
                            echo $invoice->period;
                        } else {
                            echo 'N/A';
                        }
                    @endphp
                </span>
            </div>
            <div>
                <span class="text-gray-500">Status:</span>
                @php
                    $status = $invoice->status ?? 'pending';
                    $statusClass = match($status) {
                        'paid' => 'status-paid',
                        'pending' => 'status-pending',
                        'overdue' => 'status-overdue',
                        'partial' => 'status-partial',
                        'consolidated' => 'status-consolidated',
                        'cancelled' => 'status-cancelled',
                        default => 'status-pending'
                    };
                @endphp
                <span class="status-badge {{ $statusClass }} ml-1">
                    {{ strtoupper($status) }}
                </span>
            </div>
        </div>

        <!-- Property Information - Compact -->
        <div class="p-2 border-b">
            <div class="grid grid-cols-2 gap-2 text-[8pt]">
                <div>
                    <span class="text-gray-500">Property:</span>
                    <span class="font-bold ml-1">
                        @if(isset($invoice->propertyUnit) && isset($invoice->propertyUnit->property))
                            {{ $invoice->propertyUnit->property->property_name ?? 'N/A' }}
                        @elseif(isset($invoice->propertyUnit))
                            {{ $invoice->propertyUnit->unit_number ?? 'N/A' }}
                        @else
                            N/A
                        @endif
                    </span>
                </div>
                <div>
                    <span class="text-gray-500">Unit:</span>
                    <span class="font-bold ml-1">{{ $invoice->propertyUnit->unit_number ?? 'N/A' }}</span>
                </div>
                @if(isset($invoice->propertyUnit) && isset($invoice->propertyUnit->property) && isset($invoice->propertyUnit->property->house_number))
                <div>
                    <span class="text-gray-500">Address:</span>
                    <span class="font-bold ml-1">{{ $invoice->propertyUnit->property->house_number }} {{ $invoice->propertyUnit->property->street_name ?? '' }}</span>
                </div>
                @endif
                @if(isset($invoice->propertyUnit) && isset($invoice->propertyUnit->property) && isset($invoice->propertyUnit->property->digital_address))
                <div>
                    <span class="text-gray-500">Digital Address:</span>
                    <span class="font-bold ml-1">{{ $invoice->propertyUnit->property->digital_address }}</span>
                </div>
                @endif
            </div>
        </div>

        <!-- Invoice Items - Compact Table -->
        <div class="p-2 border-b">
            <table class="text-[8pt] w-full">
                <thead>
                    <tr class="bg-light">
                        <th class="text-left">Description</th>
                        <th class="text-right">Amount ({{ $system_settings->currency_symbol ?? $settings->currency_symbol ?? '₵' }})</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Monthly Service Charge -->
                    <tr>
                        <td class="py-1">
                            <span class="font-bold">Monthly Service Charge</span>
                            @if(isset($invoice->period) && preg_match('/^\d{4}-\d{2}$/', $invoice->period))
                                <span class="text-gray-500 ml-1">
                                    {{ \Carbon\Carbon::parse($invoice->period . '-01')->format('M Y') }}
                                </span>
                            @endif
                        </td>
                        <td class="text-right">{{ number_format($invoice->amount ?? $invoice->community_dues ?? $invoice->total_amount, 2) }}</td>
                    </tr>

                    <!-- Additional Charges -->
                    @if(isset($invoice->additional_charges) && $invoice->additional_charges > 0)
                    <tr>
                        <td class="py-1">
                            <i class="fas fa-plus-circle mr-1 text-gray-500"></i>Additional Charges
                            @if(isset($invoice->additional_charges_description))
                                <span class="text-gray-500 ml-1">({{ $invoice->additional_charges_description }})</span>
                            @endif
                        </td>
                        <td class="text-right">{{ number_format($invoice->additional_charges, 2) }}</td>
                    </tr>
                    @endif

                    <!-- Penalty if applicable -->
                    @if(($invoice->penalty_amount ?? 0) > 0)
                    <tr>
                        <td class="py-1 text-red-600">
                            <i class="fas fa-exclamation-circle mr-1"></i>Late Payment Penalty
                            @if(isset($invoice->penalty_applied_at))
                                <span class="text-gray-500 ml-1">(applied {{ \Carbon\Carbon::parse($invoice->penalty_applied_at)->format('d/m/Y') }})</span>
                            @endif
                        </td>
                        <td class="text-right text-red-600">{{ number_format($invoice->penalty_amount, 2) }}</td>
                    </tr>
                    @endif

                    <!-- Discount if applicable -->
                    @if(($invoice->discount_amount ?? 0) > 0)
                    <tr>
                        <td class="py-1 text-green-600">
                            <i class="fas fa-tag mr-1"></i>Discount
                            @if(isset($invoice->discount_percentage))
                                <span class="text-gray-500 ml-1">({{ $invoice->discount_percentage }}%)</span>
                            @endif
                        </td>
                        <td class="text-right text-green-600">-{{ number_format($invoice->discount_amount, 2) }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <!-- Totals - Compact -->
        <div class="p-2">
            <div class="flex justify-end">
                <div class="w-1/2">
                    <!-- Subtotal -->
                    @php
                        $subtotal = $invoice->amount ?? $invoice->community_dues ?? 0;
                        if(isset($invoice->additional_charges)) $subtotal += $invoice->additional_charges;
                    @endphp
                    <div class="flex justify-between py-1 text-[8pt]">
                        <span>Subtotal:</span>
                        <span class="font-bold">{{ $system_settings->currency_symbol ?? $settings->currency_symbol ?? '₵' }}{{ number_format($subtotal, 2) }}</span>
                    </div>
                    
                    <!-- Penalty -->
                    @if(($invoice->penalty_amount ?? 0) > 0)
                    <div class="flex justify-between py-1 text-[8pt] text-red-600">
                        <span>Penalty:</span>
                        <span>+ {{ $system_settings->currency_symbol ?? $settings->currency_symbol ?? '₵' }}{{ number_format($invoice->penalty_amount, 2) }}</span>
                    </div>
                    @endif
                    
                    <!-- Discount -->
                    @if(($invoice->discount_amount ?? 0) > 0)
                    <div class="flex justify-between py-1 text-[8pt] text-green-600">
                        <span>Discount:</span>
                        <span>- {{ $system_settings->currency_symbol ?? $settings->currency_symbol ?? '₵' }}{{ number_format($invoice->discount_amount, 2) }}</span>
                    </div>
                    @endif
                    
                    <!-- Total -->
                    @php
                        $totalAmount = $invoice->total_amount ?? ($subtotal + ($invoice->penalty_amount ?? 0) - ($invoice->discount_amount ?? 0));
                    @endphp
                    <div class="flex justify-between py-1 text-[9pt] font-bold border-t pt-1 mt-1">
                        <span>TOTAL DUE:</span>
                        <span>{{ $system_settings->currency_symbol ?? $settings->currency_symbol ?? '₵' }}{{ number_format($totalAmount, 2) }}</span>
                    </div>
                    
                    <!-- Paid Amount -->
                    @if(($invoice->paid_amount ?? 0) > 0)
                    <div class="flex justify-between py-1 text-[8pt] text-green-600">
                        <span>Amount Paid:</span>
                        <span>{{ $system_settings->currency_symbol ?? $settings->currency_symbol ?? '₵' }}{{ number_format($invoice->paid_amount, 2) }}</span>
                    </div>
                    @endif
                    
                    <!-- Balance -->
                    @if(($invoice->balance ?? $totalAmount) > 0 && ($invoice->status ?? '') != 'paid')
                    <div class="flex justify-between py-1 text-[9pt] font-bold text-red-600">
                        <span>BALANCE DUE:</span>
                        <span>{{ $system_settings->currency_symbol ?? $settings->currency_symbol ?? '₵' }}{{ number_format($invoice->balance ?? $totalAmount, 2) }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Payment Information (if paid) -->
        @if(isset($invoice->status) && $invoice->status == 'paid' && isset($invoice->payment_date))
        <div class="p-2 bg-green-50 border-t text-[8pt]">
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <span class="text-gray-500">Payment Date:</span>
                    <span class="font-bold ml-1">{{ \Carbon\Carbon::parse($invoice->payment_date)->format('d/m/Y') }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Payment Method:</span>
                    <span class="font-bold ml-1">{{ $invoice->payment_method ? ucfirst(str_replace('_', ' ', $invoice->payment_method)) : 'N/A' }}</span>
                </div>
                @if(isset($invoice->payment_reference) && $invoice->payment_reference)
                <div class="col-span-2">
                    <span class="text-gray-500">Reference:</span>
                    <span class="font-bold ml-1 truncate">{{ $invoice->payment_reference }}</span>
                </div>
                @endif
            </div>
        </div>
        @endif

        <!-- Invoice Notes -->
        @if(isset($invoice->notes) && $invoice->notes)
        <div class="p-2 bg-gray-50 border-t text-[7pt]">
            <i class="fas fa-sticky-note mr-1 text-gray-500"></i>
            <span class="font-bold">Notes:</span>
            <span class="ml-1">{{ $invoice->notes }}</span>
        </div>
        @endif

        <!-- Terms & Conditions -->
        <div class="p-2 border-t text-[6pt] text-gray-500">
            <div class="grid grid-cols-2 gap-1">
                <div>• Payment due within {{ $system_settings->invoice_due_days ?? $settings->invoice_due_days ?? 30 }} days</div>
                <div>• Late fee: {{ $system_settings->late_payment_percentage ?? $settings->late_payment_percentage ?? 5 }}% per month</div>
                <div>• Quote invoice number with payment</div>
                <div>• For inquiries: {{ $system_settings->support_email ?? $settings->support_email ?? 'support@example.com' }}</div>
                <div>• Grace period: {{ $system_settings->tenant_grace_period_days ?? $settings->tenant_grace_period_days ?? 7 }} days</div>
                @if(($system_settings->tenant_late_payment_percentage ?? $settings->tenant_late_payment_percentage ?? 0) > 0)
                    <div>• Late payment penalty: {{ $system_settings->tenant_late_payment_percentage ?? $settings->tenant_late_payment_percentage }}%</div>
                @endif
            </div>
        </div>

        <!-- Footer -->
        <div class="p-2 text-center text-[6pt] text-gray-400 border-t mt-2">
            <p>Thank you for your prompt payment. This is a computer-generated invoice and does not require a signature.</p>
            <p class="mt-1">Generated on {{ \Carbon\Carbon::now()->format('d/m/Y H:i:s') }} | {{ $system_settings->system_name ?? $settings->system_name ?? 'Property Management System' }} &copy; {{ date('Y') }}</p>
        </div>
    </div>

    <script>
        // Auto-print option (uncomment if needed)
        // window.onload = function() { setTimeout(function() { window.print(); }, 500); }
        
        // Add keyboard shortcut (Ctrl+P)
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>