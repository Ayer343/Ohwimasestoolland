<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #{{ $invoice->invoice_number ?? 'INV-'.str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</title>
    
    <!-- ============ FAVICON ============ -->
    @php
        $settings = \App\Models\SystemSetting::getSettings();
    @endphp
    @if($settings->hasFavicon())
        <link rel="icon" href="{{ $settings->getFaviconUrl() }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ $settings->getFaviconUrl() }}" type="image/x-icon">
        <link rel="apple-touch-icon" href="{{ $settings->getFaviconUrl() }}">
        <!-- Additional favicon sizes for better browser support -->
        <link rel="icon" type="image/png" sizes="16x16" href="{{ $settings->getFaviconUrl() }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ $settings->getFaviconUrl() }}">
        <link rel="icon" type="image/png" sizes="64x64" href="{{ $settings->getFaviconUrl() }}">
        <!-- Microsoft Edge Tile -->
        <meta name="msapplication-TileImage" content="{{ $settings->getFaviconUrl() }}">
        <meta name="msapplication-TileColor" content="#667eea">
    @else
        <!-- Default favicon fallback -->
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <meta name="msapplication-TileColor" content="#667eea">
    @endif
    
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
    </style>
</head>
<body class="p-2">
    <!-- Print Controls - Hidden when printing -->
    <div class="no-print mb-2 p-2 bg-gray-100 rounded flex justify-between items-center text-xs">
        <div>
            <i class="fas fa-info-circle mr-1"></i> Optimized for A5 landscape
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

    @php
        // ✅ FIX: Properly decode covers_periods from JSON string to array
        $coveragePeriods = [];
        $coversPeriodsRaw = $invoice->covers_periods;
        
        if (!empty($coversPeriodsRaw)) {
            if (is_string($coversPeriodsRaw)) {
                $coveragePeriods = json_decode($coversPeriodsRaw, true);
                if (!is_array($coveragePeriods)) {
                    $coveragePeriods = [];
                }
            } elseif (is_array($coversPeriodsRaw)) {
                $coveragePeriods = $coversPeriodsRaw;
            }
        }
        
        // If still empty, try to generate from bulk_coverage_start/end
        if (empty($coveragePeriods) && $invoice->bulk_coverage_start && $invoice->bulk_coverage_end) {
            $start = \Carbon\Carbon::parse($invoice->bulk_coverage_start . '-01');
            $end = \Carbon\Carbon::parse($invoice->bulk_coverage_end . '-01');
            $current = clone $start;
            while ($current <= $end) {
                $coveragePeriods[] = $current->format('Y-m');
                $current->addMonth();
            }
        }
        
        $formattedCoveragePeriods = array_map(function($p) {
            try {
                return \Carbon\Carbon::parse($p . '-01')->format('M y');
            } catch (\Exception $e) {
                return $p;
            }
        }, $coveragePeriods);
        
        $hasActiveCoverage = $invoice->is_bulk_payment && $invoice->isPaid() && !empty($coveragePeriods);
    @endphp

    <!-- Invoice Container - A5 Optimized -->
    <div class="max-w-full mx-auto">
        <!-- Header -->
        <div class="invoice-header flex justify-between items-start">
            <div>
                <h1 class="text-xl font-bold">INVOICE</h1>
                <div class="flex items-center mt-1">
                    @if($invoice->is_bulk_payment)
                        <span class="bulk-badge">BULK</span>
                    @endif
                    @if($hasActiveCoverage)
                        <span class="coverage-badge ml-1">COVERAGE ACTIVE</span>
                    @endif
                </div>
                <p class="text-[7pt] text-blue-100 mt-1">{{ $settings->system_name ?? 'Property Management System' }}</p>
            </div>
            <div class="text-right">
                <div class="text-lg font-bold">#{{ $invoice->invoice_number ?? 'INV-'.str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</div>
                <p class="text-[7pt] text-blue-100">Issued: {{ \Carbon\Carbon::parse($invoice->created_at)->format('d/m/Y') }}</p>
            </div>
        </div>

        <!-- Company & Landlord Info - Compact 2-col -->
        <div class="grid grid-cols-2 border-b">
            <div class="p-2">
                <h3 class="font-bold text-[8pt] mb-1">FROM</h3>
                <p class="font-bold text-[8pt]">{{ $settings->system_name ?? 'Property Management System' }}</p>
                <p class="text-[7pt]">{{ $settings->company_address ?? 'Company Address' }}</p>
                <p class="text-[7pt]">{{ $settings->company_phone ?? 'Phone Number' }}</p>
                @if($settings->company_vat_number)
                    <p class="text-[7pt]">VAT: {{ $settings->company_vat_number }}</p>
                @endif
            </div>
            
            <div class="p-2 bg-light">
                <h3 class="font-bold text-[8pt] mb-1">TO</h3>
                <p class="font-bold text-[8pt]">{{ $invoice->property->landlord->name ?? 'Landlord Name' }}</p>
                @if($invoice->property->landlord->phone)
                    <p class="text-[7pt]">{{ $invoice->property->landlord->phone }}</p>
                @endif
                @if($invoice->property->landlord->email)
                    <p class="text-[7pt] truncate">{{ $invoice->property->landlord->email }}</p>
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
                <span class="font-bold ml-1">{{ \Carbon\Carbon::parse($invoice->due_date)->format('d/m/Y') }}</span>
            </div>
            <div>
                <span class="text-gray-500">Period:</span>
                <span class="font-bold ml-1">
                    @php
                        if($invoice->is_bulk_payment) {
                            if($invoice->bulk_start_month && $invoice->bulk_end_month) {
                                try {
                                    echo \Carbon\Carbon::parse($invoice->bulk_start_month . '-01')->format('M y') . '-' . 
                                         \Carbon\Carbon::parse($invoice->bulk_end_month . '-01')->format('M y');
                                } catch (\Exception $e) {
                                    echo $invoice->bulk_months . 'mo';
                                }
                            } else {
                                echo $invoice->bulk_months . 'mo';
                            }
                        } elseif(preg_match('/^\d{4}-\d{2}$/', $invoice->period)) {
                            echo \Carbon\Carbon::parse($invoice->period . '-01')->format('M Y');
                        } else {
                            echo $invoice->period;
                        }
                    @endphp
                </span>
            </div>
            <div>
                <span class="text-gray-500">Status:</span>
                @php
                    $statusClass = match($invoice->status) {
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
                    {{ $invoice->status == 'consolidated' ? 'CON' : substr(strtoupper($invoice->status), 0, 3) }}
                </span>
            </div>
        </div>

        <!-- Property Information - Compact -->
        <div class="p-2 border-b">
            <div class="grid grid-cols-2 gap-2 text-[8pt]">
                <div>
                    <span class="text-gray-500">Property:</span>
                    <span class="font-bold ml-1">{{ $invoice->property->house_number }} {{ $invoice->property->street_name }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Digital Address:</span>
                    <span class="font-bold ml-1">{{ $invoice->property->digital_address ?? 'N/A' }}</span>
                </div>
            </div>
        </div>

        <!-- Bulk Coverage Summary (if active) - FIXED with decoded periods -->
        @if($hasActiveCoverage)
        <div class="p-2 bg-green-50 border-b text-[8pt]">
            <div class="flex items-center">
                <i class="fas fa-shield-alt text-green-600 mr-1"></i>
                <span class="font-bold text-green-700">Active Coverage:</span>
                <span class="ml-2">
                    @foreach(array_slice($formattedCoveragePeriods, 0, 3) as $period)
                        {{ $period }}{{ !$loop->last ? ', ' : '' }}
                    @endforeach
                    @if(count($formattedCoveragePeriods) > 3)
                        +{{ count($formattedCoveragePeriods) - 3 }} more
                    @endif
                </span>
            </div>
        </div>
        @endif

        <!-- Invoice Items - Compact Table -->
        <div class="p-2 border-b">
            <table class="text-[8pt]">
                <thead>
                    <tr class="bg-light">
                        <th class="text-left">Description</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @if($invoice->is_bulk_payment && $invoice->childInvoices && $invoice->childInvoices->count() > 0)
                        <!-- Show first 3 months of bulk payment -->
                        @foreach($invoice->childInvoices->take(3) as $child)
                        <tr>
                            <td class="py-1">
                                <span class="font-bold">Monthly Charge</span>
                                <span class="text-gray-500 ml-1">
                                    @php
                                        try {
                                            echo \Carbon\Carbon::parse($child->period . '-01')->format('M y');
                                        } catch (\Exception $e) {
                                            echo $child->period;
                                        }
                                    @endphp
                                </span>
                            </td>
                            <td class="text-right">{{ $settings->currency_symbol ?? '₵' }}{{ number_format($child->amount, 2) }}</td>
                        </tr>
                        @endforeach
                        @if($invoice->childInvoices->count() > 3)
                        <tr>
                            <td colspan="2" class="text-center text-gray-500 py-1">
                                + {{ $invoice->childInvoices->count() - 3 }} more months
                            </td>
                        </tr>
                        @endif
                    @elseif($invoice->is_bulk_payment)
                        <tr>
                            <td class="py-1">
                                <span class="font-bold">Bulk Payment</span>
                                <span class="text-gray-500 ml-1">
                                    ({{ $invoice->bulk_months }} months)
                                </span>
                            </td>
                            <td class="text-right">{{ $settings->currency_symbol ?? '₵' }}{{ number_format($invoice->amount, 2) }}</td>
                        </tr>
                    @else
                        <tr>
                            <td class="py-1">
                                <span class="font-bold">Monthly Service Charge</span>
                                <span class="text-gray-500 ml-1">
                                    @php
                                        try {
                                            echo \Carbon\Carbon::parse($invoice->period . '-01')->format('M Y');
                                        } catch (\Exception $e) {
                                            echo $invoice->period;
                                        }
                                    @endphp
                                </span>
                                @if($invoice->bulk_parent_id)
                                <span class="text-purple-600 ml-1">(Consolidated)</span>
                                @endif
                            </td>
                            <td class="text-right">{{ $settings->currency_symbol ?? '₵' }}{{ number_format($invoice->amount, 2) }}</td>
                        </tr>
                    @endif

                    <!-- Penalty if applicable -->
                    @if(($invoice->penalty_amount ?? 0) > 0)
                    <tr>
                        <td class="py-1 text-red-600">
                            <i class="fas fa-exclamation-circle mr-1"></i>Late Fee Penalty
                        </td>
                        <td class="text-right text-red-600">{{ $settings->currency_symbol ?? '₵' }}{{ number_format($invoice->penalty_amount, 2) }}</td>
                    </tr>
                    @endif

                    <!-- Discount if applicable -->
                    @if($invoice->discount_amount > 0)
                    <tr>
                        <td class="py-1 text-green-600">
                            <i class="fas fa-tag mr-1"></i>Discount ({{ $invoice->discount_percentage }}%)
                        </td>
                        <td class="text-right text-green-600">-{{ $settings->currency_symbol ?? '₵' }}{{ number_format($invoice->discount_amount, 2) }}</td>
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
                    <div class="flex justify-between py-1 text-[8pt]">
                        <span>Subtotal:</span>
                        <span class="font-bold">{{ $settings->currency_symbol ?? '₵' }}{{ number_format($invoice->amount, 2) }}</span>
                    </div>
                    
                    <!-- Total -->
                    @php
                        $totalAmount = $invoice->total_amount ?? ($invoice->amount + ($invoice->penalty_amount ?? 0) - ($invoice->discount_amount ?? 0));
                    @endphp
                    <div class="flex justify-between py-1 text-[9pt] font-bold border-t">
                        <span>TOTAL:</span>
                        <span>{{ $settings->currency_symbol ?? '₵' }}{{ number_format($totalAmount, 2) }}</span>
                    </div>
                    
                    <!-- Paid/Balance -->
                    @if($invoice->paid_amount > 0)
                    <div class="flex justify-between py-1 text-[8pt] text-green-600">
                        <span>Paid:</span>
                        <span>{{ $settings->currency_symbol ?? '₵' }}{{ number_format($invoice->paid_amount, 2) }}</span>
                    </div>
                    @endif
                    
                    @if($invoice->balance > 0)
                    <div class="flex justify-between py-1 text-[9pt] font-bold text-red-600">
                        <span>BALANCE DUE:</span>
                        <span>{{ $settings->currency_symbol ?? '₵' }}{{ number_format($invoice->balance, 2) }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Payment Info (if paid) -->
        @if($invoice->status == 'paid' && $invoice->payment_date)
        <div class="p-2 bg-green-50 border-t text-[8pt]">
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <span class="text-gray-500">Paid:</span>
                    <span class="font-bold ml-1">{{ \Carbon\Carbon::parse($invoice->payment_date)->format('d/m/Y') }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Method:</span>
                    <span class="font-bold ml-1">{{ $invoice->payment_method ? ucfirst(str_replace('_', ' ', $invoice->payment_method)) : 'N/A' }}</span>
                </div>
                @if($invoice->payment_reference)
                <div class="col-span-2">
                    <span class="text-gray-500">Ref:</span>
                    <span class="font-bold ml-1 truncate">{{ $invoice->payment_reference }}</span>
                </div>
                @endif
            </div>
        </div>
        @endif

        <!-- Bulk/Consolidated Notes -->
        @if($invoice->status == 'consolidated' && $invoice->bulk_parent_id)
        <div class="p-2 bg-purple-50 border-t text-[8pt]">
            <i class="fas fa-layer-group text-purple-600 mr-1"></i>
            Consolidated into <span class="font-bold">#INV-{{ str_pad($invoice->bulk_parent_id, 6, '0', STR_PAD_LEFT) }}</span>
        </div>
        @endif

        <!-- Terms & Conditions - Mini -->
        <div class="p-2 border-t text-[6pt] text-gray-500">
            <div class="grid grid-cols-2 gap-1">
                <div>• Due in {{ $settings->invoice_due_days ?? 30 }} days</div>
                <div>• Late fee: {{ $settings->late_payment_percentage ?? 5 }}%/mo</div>
                <div>• Quote invoice # with payment</div>
                <div>• Contact: {{ $settings->support_email ?? 'support@example.com' }}</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="p-2 text-center text-[6pt] text-gray-400 border-t">
            <p>Generated {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }} | Computer generated - No signature required</p>
        </div>
    </div>

    <script>
        // Auto-print option (uncomment if needed)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>