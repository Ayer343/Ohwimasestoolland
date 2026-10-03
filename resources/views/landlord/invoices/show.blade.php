@extends('layouts.landlord')

@section('title', 'Invoice Details')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Invoice Details</h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Invoice #{{ $invoice->invoice_number ?? 'INV-'.str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}
                </p>
            </div>
            <div class="flex space-x-3 mt-4 md:mt-0">
                <a href="{{ route('landlord.invoices.print', $invoice->id) }}" target="_blank" class="px-4 py-2 rounded flex items-center" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                    <i class="fas fa-print mr-2"></i> Print
                </a>
                @if(in_array($invoice->status, ['pending', 'overdue', 'partial']) && !$invoice->is_bulk_payment && $invoice->balance > 0)
                <a href="{{ route('landlord.payments.create', ['propertyId' => $invoice->property_id]) }}?invoice_ids[]={{ $invoice->id }}" class="px-4 py-2 rounded flex items-center" style="background-color: var(--success); color: white;">
                    <i class="fas fa-credit-card mr-2"></i> Pay Now
                </a>
                @elseif($invoice->is_bulk_payment && in_array($invoice->status, ['pending', 'overdue']) && $invoice->balance > 0)
                <a href="{{ route('landlord.payments.create', ['propertyId' => $invoice->property_id]) }}?invoice_ids[]={{ $invoice->id }}" class="px-4 py-2 rounded flex items-center" style="background-color: var(--success); color: white;">
                    <i class="fas fa-credit-card mr-2"></i> Pay Bulk Invoice
                </a>
                @endif
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

    <!-- Bulk Coverage Active Banner - FIXED JSON DECODING -->
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
        
        $formattedPeriods = array_map(function($p) {
            try {
                return \Carbon\Carbon::parse($p . '-01')->format('M Y');
            } catch (\Exception $e) {
                return $p;
            }
        }, $coveragePeriods);
        
        $hasActiveCoverage = $invoice->is_bulk_payment && $invoice->isPaid() && !empty($coveragePeriods);
    @endphp

    @if($hasActiveCoverage)
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-start">
            <i class="fas fa-shield-alt text-xl mr-3 mt-1"></i>
            <div>
                <strong class="font-bold">✅ Active Bulk Coverage</strong>
                <p class="text-sm mt-1">This bulk payment is active and covers multiple months.</p>
                @if(!empty($formattedPeriods))
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach($formattedPeriods as $period)
                    <span class="px-2 py-1 text-xs rounded-full" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                        {{ $period }}
                    </span>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- Consolidated Invoice Warning -->
    @if($invoice->status === 'consolidated')
    <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-start">
            <i class="fas fa-info-circle text-xl mr-3 mt-1"></i>
            <div>
                <strong class="font-bold">Consolidated Invoice</strong>
                <p class="text-sm mt-1">This invoice has been consolidated into a bulk payment.</p>
                @if($invoice->bulk_parent_id)
                <p class="text-sm mt-2">
                    <a href="{{ route('landlord.invoices.show', $invoice->bulk_parent_id) }}" class="inline-flex items-center px-3 py-1 rounded" style="background-color: var(--primary); color: white;">
                        <i class="fas fa-layer-group mr-2"></i> View Bulk Invoice
                    </a>
                </p>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- Invoice Details -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - Invoice Information -->
        <div class="lg:col-span-2">
            <!-- Invoice Status Card -->
            <div class="card p-6 mb-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between">
                    <div>
                        <h3 class="text-lg font-medium mb-2" style="color: var(--text-primary);">Invoice Status</h3>
                        @php
                            $statusColors = [
                                'paid' => 'success',
                                'pending' => 'warning',
                                'overdue' => 'danger',
                                'partial' => 'info',
                                'cancelled' => 'secondary',
                                'processing' => 'info',
                                'consolidated' => 'info'
                            ];
                            $statusColor = $statusColors[$invoice->status] ?? 'secondary';
                        @endphp
                        <span class="px-3 py-1 rounded-full text-sm font-medium" style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.2); color: var(--{{ $statusColor }});">
                            {{ ucfirst($invoice->status) }}
                        </span>
                        
                        @if($invoice->is_bulk_payment)
                        <span class="ml-2 px-3 py-1 rounded-full text-sm font-medium" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
                            <i class="fas fa-layer-group mr-1"></i> Bulk Payment
                        </span>
                        @endif

                        @if($invoice->bulk_parent_id)
                        <span class="ml-2 px-3 py-1 rounded-full text-sm font-medium" style="background-color: rgba(var(--info-rgb), 0.2); color: var(--info);">
                            <i class="fas fa-link mr-1"></i> Part of Bulk
                        </span>
                        @endif

                        @if($hasActiveCoverage)
                        <span class="ml-2 px-3 py-1 rounded-full text-sm font-medium" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                            <i class="fas fa-shield-alt mr-1"></i> Coverage Active
                        </span>
                        @endif
                    </div>
                    
                    <div class="mt-4 md:mt-0 text-right">
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">
                            {{ $settings->formatAmount($invoice->total_amount ?? ($invoice->amount + ($invoice->penalty_amount ?? 0))) }}
                        </p>
                        <p class="text-sm" style="color: var(--text-secondary);">Total Amount</p>
                        
                        @if($invoice->paid_amount > 0)
                        <p class="text-xs mt-1" style="color: var(--success);">
                            Paid: {{ $settings->formatAmount($invoice->paid_amount) }}
                        </p>
                        @endif
                        
                        @if($invoice->balance > 0)
                        <p class="text-xs" style="color: var(--danger);">
                            Balance: {{ $settings->formatAmount($invoice->balance) }}
                        </p>
                        @endif

                        @if($invoice->discount_amount > 0)
                        <p class="text-xs mt-1" style="color: var(--success);">
                            Discount: {{ $settings->formatAmount($invoice->discount_amount) }} ({{ $invoice->discount_percentage }}%)
                        </p>
                        @endif
                    </div>
                </div>
                
                <!-- Progress Bar for Partial Payments -->
                @if($invoice->status == 'partial' && $invoice->total_amount > 0)
                <div class="mt-4">
                    <div class="flex justify-between text-xs mb-1">
                        <span style="color: var(--text-secondary);">Payment Progress</span>
                        <span style="color: var(--text-secondary);">{{ round(($invoice->paid_amount / $invoice->total_amount) * 100) }}%</span>
                    </div>
                    <div class="w-full h-2 rounded-full" style="background-color: var(--border-color);">
                        <div class="h-2 rounded-full" style="width: {{ ($invoice->paid_amount / $invoice->total_amount) * 100 }}%; background-color: var(--success);"></div>
                    </div>
                </div>
                @endif
            </div>

            <!-- Property Information Card -->
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-medium mb-4" style="color: var(--text-primary);">Property Information</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm mb-1" style="color: var(--text-secondary);">Property Address</p>
                        <p class="font-medium" style="color: var(--text-primary);">
                            {{ $invoice->property->house_number }} {{ $invoice->property->street_name }}
                        </p>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            {{ $invoice->property->zone }} - {{ $invoice->property->section }}
                        </p>
                    </div>
                    
                    <div>
                        <p class="text-sm mb-1" style="color: var(--text-secondary);">Digital Address</p>
                        <p class="font-medium" style="color: var(--text-primary);">
                            {{ $invoice->property->digital_address ?? 'Not assigned' }}
                        </p>
                    </div>
                    
                    <div>
                        <p class="text-sm mb-1" style="color: var(--text-secondary);">Property Status</p>
                        <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--{{ $invoice->property->status == 'active' ? 'success' : 'warning' }}-rgb), 0.2); color: var(--{{ $invoice->property->status == 'active' ? 'success' : 'warning' }});">
                            {{ ucfirst(str_replace('_', ' ', $invoice->property->status)) }}
                        </span>
                    </div>
                    
                    <div>
                        <p class="text-sm mb-1" style="color: var(--text-secondary);">Registration Date</p>
                        <p class="font-medium" style="color: var(--text-primary);">
                            {{ $invoice->property->registration_date ? \Carbon\Carbon::parse($invoice->property->registration_date)->format('M d, Y') : 'N/A' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Bulk Coverage Details (for paid bulk invoices) -->
            @if($hasActiveCoverage)
            <div class="card p-6 mb-6" style="border-left: 4px solid var(--success);">
                <h3 class="text-lg font-medium mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-shield-alt mr-2" style="color: var(--success);"></i>
                    Active Bulk Coverage
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <p class="text-sm mb-1" style="color: var(--text-secondary);">Coverage Period</p>
                        <p class="font-medium" style="color: var(--text-primary);">
                            @if($invoice->bulk_coverage_start && $invoice->bulk_coverage_end)
                                {{ \Carbon\Carbon::parse($invoice->bulk_coverage_start . '-01')->format('M Y') }} - 
                                {{ \Carbon\Carbon::parse($invoice->bulk_coverage_end . '-01')->format('M Y') }}
                            @else
                                {{ count($coveragePeriods) }} months
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-sm mb-1" style="color: var(--text-secondary);">Coverage Status</p>
                        <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                            <i class="fas fa-check-circle mr-1"></i> Active
                        </span>
                    </div>
                </div>
                
                <div class="mt-3">
                    <p class="text-sm mb-2" style="color: var(--text-secondary);">Covered Months:</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($formattedPeriods as $period)
                        <span class="px-3 py-1 rounded-full text-xs" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3); color: var(--text-primary);">
                            {{ $period }}
                        </span>
                        @endforeach
                    </div>
                </div>
                
                <div class="mt-4 p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <p class="text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                        No further invoices will be generated for these months. This bulk payment covers all rental obligations for the periods shown above.
                    </p>
                </div>
            </div>
            @endif

            <!-- For Bulk Payments: Show Child Invoices -->
            @if($invoice->is_bulk_payment && $invoice->childInvoices && $invoice->childInvoices->count() > 0)
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-medium mb-4" style="color: var(--text-primary);">Included Monthly Invoices</h3>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <th class="py-2 text-left text-xs font-medium" style="color: var(--text-secondary);">Period</th>
                                <th class="py-2 text-left text-xs font-medium" style="color: var(--text-secondary);">Original Amount</th>
                                <th class="py-2 text-left text-xs font-medium" style="color: var(--text-secondary);">Status</th>
                                <th class="py-2 text-left text-xs font-medium" style="color: var(--text-secondary);">Type</th>
                              </tr>
                        </thead>
                        <tbody>
                            @foreach($invoice->childInvoices as $child)
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td class="py-2 text-sm" style="color: var(--text-primary);">
                                    @php
                                        $childPeriod = $child->period;
                                        if(preg_match('/^\d{4}-\d{2}$/', $child->period)) {
                                            try {
                                                $childPeriod = \Carbon\Carbon::parse($child->period . '-01')->format('F Y');
                                            } catch (\Exception $e) {}
                                        }
                                    @endphp
                                    {{ $childPeriod }}
                                </td>
                                <td class="py-2 text-sm" style="color: var(--text-primary);">
                                    {{ $settings->formatAmount($child->amount) }}
                                </td>
                                <td class="py-2">
                                    @php
                                        $childStatusColors = [
                                            'paid' => 'success',
                                            'consolidated' => 'info',
                                            'pending' => 'warning',
                                            'overdue' => 'danger'
                                        ];
                                        $childStatusColor = $childStatusColors[$child->status] ?? 'secondary';
                                    @endphp
                                    <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--{{ $childStatusColor }}-rgb), 0.2); color: var(--{{ $childStatusColor }});">
                                        @if($child->status === 'consolidated')
                                            <i class="fas fa-check-circle mr-1"></i> Consolidated
                                        @else
                                            {{ ucfirst($child->status) }}
                                        @endif
                                    </span>
                                </td>
                                <td class="py-2">
                                    @if($child->created_at == $child->updated_at && $child->status == 'consolidated')
                                    <span class="text-xs" style="color: var(--info);">Existing Invoice</span>
                                    @else
                                    <span class="text-xs" style="color: var(--success);">New in Bulk</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                @if($invoice->bulk_months)
                <div class="mt-4 p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <p class="text-sm" style="color: var(--info);">
                        <i class="fas fa-layer-group mr-2"></i>
                        Total of {{ $invoice->bulk_months }} months included in this bulk payment
                        @if($invoice->discount_percentage > 0)
                            <span class="ml-2">({{ $invoice->discount_percentage }}% discount applied)</span>
                        @endif
                    </p>
                </div>
                @endif
            </div>
            @endif

            <!-- For Consolidated Invoices: Show Parent Bulk Info -->
            @if($invoice->bulk_parent_id && $invoice->bulkPayment)
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-medium mb-4" style="color: var(--text-primary);">Bulk Payment Information</h3>
                
                <div class="p-4 rounded" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <div class="flex items-start">
                        <i class="fas fa-layer-group text-2xl mr-3" style="color: var(--info);"></i>
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">
                                This invoice has been consolidated into a bulk payment
                            </p>
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                Bulk Invoice #{{ $invoice->bulkPayment->invoice_number ?? 'INV-'.str_pad($invoice->bulkPayment->id, 6, '0', STR_PAD_LEFT) }}
                            </p>
                            
                            @if($invoice->bulkPayment->isPaid() && !empty($invoice->bulkPayment->covers_periods))
                            <div class="mt-2 flex items-center">
                                <span class="px-2 py-1 text-xs rounded-full" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                                    <i class="fas fa-shield-alt mr-1"></i> Coverage Active
                                </span>
                            </div>
                            @endif
                            
                            <p class="text-sm mt-2">
                                <a href="{{ route('landlord.invoices.show', $invoice->bulk_parent_id) }}" class="inline-flex items-center px-3 py-1 rounded" style="background-color: var(--primary); color: white;">
                                    <i class="fas fa-eye mr-2"></i> View Bulk Invoice
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Invoice Timeline Card -->
            <div class="card p-6">
                <h3 class="text-lg font-medium mb-4" style="color: var(--text-primary);">Invoice Timeline</h3>
                
                <div class="space-y-6">
                    <!-- Created -->
                    <div class="flex">
                        <div class="flex flex-col items-center mr-4">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.2);">
                                <i class="fas fa-file-invoice" style="color: var(--info);"></i>
                            </div>
                            <div class="w-0.5 h-full" style="background-color: var(--border-color);"></div>
                        </div>
                        <div class="pb-6">
                            <p class="font-medium" style="color: var(--text-primary);">Invoice Created</p>
                            <p class="text-sm" style="color: var(--text-secondary);">{{ \Carbon\Carbon::parse($invoice->created_at)->format('M d, Y h:i A') }}</p>
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                @if($invoice->is_bulk_payment)
                                    Bulk payment invoice for 
                                    @if($invoice->bulk_start_month && $invoice->bulk_end_month)
                                        {{ \Carbon\Carbon::parse($invoice->bulk_start_month . '-01')->format('M Y') }} - 
                                        {{ \Carbon\Carbon::parse($invoice->bulk_end_month . '-01')->format('M Y') }}
                                    @else
                                        {{ $invoice->bulk_months }} months
                                    @endif
                                @elseif($invoice->bulk_parent_id)
                                    Monthly invoice (consolidated into bulk)
                                @else
                                    @php
                                        $periodDisplay = $invoice->period;
                                        if(preg_match('/^\d{4}-\d{2}$/', $invoice->period)) {
                                            try {
                                                $periodDisplay = \Carbon\Carbon::parse($invoice->period . '-01')->format('F Y');
                                            } catch (\Exception $e) {}
                                        }
                                    @endphp
                                    Monthly dues charge for {{ $periodDisplay }}
                                @endif
                            </p>
                        </div>
                    </div>
                    
                    <!-- Due Date -->
                    <div class="flex">
                        <div class="flex flex-col items-center mr-4">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center" style="background-color: rgba(var(--{{ $invoice->status == 'overdue' ? 'danger' : 'warning' }}-rgb), 0.2);">
                                <i class="fas fa-calendar-alt" style="color: var(--{{ $invoice->status == 'overdue' ? 'danger' : 'warning' }});"></i>
                            </div>
                            <div class="w-0.5 h-full" style="background-color: var(--border-color);"></div>
                        </div>
                        <div class="pb-6">
                            <p class="font-medium" style="color: var(--text-primary);">Due Date</p>
                            <p class="text-sm" style="color: var(--text-secondary);">{{ \Carbon\Carbon::parse($invoice->due_date)->format('M d, Y') }}</p>
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                @php
                                    $dueDate = \Carbon\Carbon::parse($invoice->due_date);
                                    $daysDiff = $dueDate->diffInDays(now(), false);
                                @endphp
                                @if($invoice->status == 'paid' || $invoice->status == 'consolidated')
                                    <span style="color: var(--success);">Processed</span>
                                @elseif($daysDiff > 0)
                                    <span style="color: var(--danger);">{{ $daysDiff }} days overdue</span>
                                @elseif($daysDiff == 0)
                                    <span style="color: var(--warning);">Due today</span>
                                @else
                                    <span style="color: var(--info);">Due in {{ abs($daysDiff) }} days</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    
                    <!-- Payment (if paid) -->
                    @if($invoice->status == 'paid' || $invoice->payment_date)
                    <div class="flex">
                        <div class="flex flex-col items-center mr-4">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.2);">
                                <i class="fas fa-check" style="color: var(--success);"></i>
                            </div>
                            <div class="w-0.5 h-full" style="background-color: var(--border-color);"></div>
                        </div>
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">Payment Received</p>
                            <p class="text-sm" style="color: var(--text-secondary);">{{ $invoice->payment_date ? \Carbon\Carbon::parse($invoice->payment_date)->format('M d, Y h:i A') : 'N/A' }}</p>
                            <div class="mt-2 p-2 rounded" style="background-color: var(--bg-secondary);">
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    <span class="font-medium">Payment Method:</span> {{ $invoice->payment_method ? ucfirst(str_replace('_', ' ', $invoice->payment_method)) : 'N/A' }}
                                </p>
                                @if($invoice->payment_reference)
                                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                    <span class="font-medium">Reference:</span> {{ $invoice->payment_reference }}
                                </p>
                                @endif
                                
                                @if($hasActiveCoverage)
                                <p class="text-sm mt-1" style="color: var(--success);">
                                    <i class="fas fa-shield-alt mr-1"></i>
                                    <span class="font-medium">Coverage activated for {{ count($coveragePeriods) }} months</span>
                                </p>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif
                    
                    <!-- Consolidation (if applicable) -->
                    @if($invoice->status == 'consolidated' && $invoice->bulk_parent_id)
                    <div class="flex">
                        <div class="flex flex-col items-center mr-4">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.2);">
                                <i class="fas fa-layer-group" style="color: var(--info);"></i>
                            </div>
                        </div>
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">Consolidated into Bulk</p>
                            <p class="text-sm" style="color: var(--text-secondary);">{{ \Carbon\Carbon::parse($invoice->updated_at)->format('M d, Y h:i A') }}</p>
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                This invoice was consolidated into a bulk payment
                            </p>
                        </div>
                    </div>
                    @endif
                    
                    <!-- Coverage Timeline for Paid Bulk Invoices -->
                    @if($hasActiveCoverage && isset($invoice->metadata['coverage_activated_at']))
                    <div class="flex">
                        <div class="flex flex-col items-center mr-4">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.2);">
                                <i class="fas fa-shield-alt" style="color: var(--success);"></i>
                            </div>
                        </div>
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">Coverage Activated</p>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                {{ \Carbon\Carbon::parse($invoice->metadata['coverage_activated_at'])->format('M d, Y h:i A') }}
                            </p>
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                Bulk coverage activated for {{ count($coveragePeriods) }} months
                            </p>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Right Column - Invoice Summary -->
        <div class="lg:col-span-1">
            <!-- Invoice Summary Card -->
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-medium mb-4" style="color: var(--text-primary);">Invoice Summary</h3>
                
                <div class="space-y-3">
                    <div>
                        <p class="text-sm mb-1" style="color: var(--text-secondary);">Invoice Number</p>
                        <p class="font-medium" style="color: var(--text-primary);">{{ $invoice->invoice_number ?? 'INV-'.str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</p>
                    </div>
                    
                    @if(!$invoice->is_bulk_payment && !$invoice->bulk_parent_id)
                    <div>
                        <p class="text-sm mb-1" style="color: var(--text-secondary);">Billing Period</p>
                        @php
                            $periodDisplay = $invoice->period;
                            if(preg_match('/^\d{4}-\d{2}$/', $invoice->period)) {
                                try {
                                    $periodDisplay = \Carbon\Carbon::parse($invoice->period . '-01')->format('F Y');
                                } catch (\Exception $e) {}
                            }
                        @endphp
                        <p class="font-medium" style="color: var(--text-primary);">{{ $periodDisplay }}</p>
                    </div>
                    @endif
                    
                    @if($invoice->is_bulk_payment)
                    <div>
                        <p class="text-sm mb-1" style="color: var(--text-secondary);">Bulk Period</p>
                        <p class="font-medium" style="color: var(--text-primary);">
                            @if($invoice->bulk_start_month && $invoice->bulk_end_month)
                                {{ \Carbon\Carbon::parse($invoice->bulk_start_month . '-01')->format('M Y') }} - 
                                {{ \Carbon\Carbon::parse($invoice->bulk_end_month . '-01')->format('M Y') }}
                            @else
                                {{ $invoice->bulk_months }} months
                            @endif
                        </p>
                    </div>
                    @endif
                    
                    @if($hasActiveCoverage)
                    <div>
                        <p class="text-sm mb-1" style="color: var(--text-secondary);">Covered Months</p>
                        <div class="flex flex-wrap gap-1 mt-1">
                            @foreach(array_slice($formattedPeriods, 0, 3) as $period)
                            <span class="text-xs px-2 py-1 rounded" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                {{ $period }}
                            </span>
                            @endforeach
                            @if(count($formattedPeriods) > 3)
                            <span class="text-xs px-2 py-1" style="color: var(--text-secondary);">
                                +{{ count($formattedPeriods) - 3 }} more
                            </span>
                            @endif
                        </div>
                    </div>
                    @endif
                    
                    <div>
                        <p class="text-sm mb-1" style="color: var(--text-secondary);">Issue Date</p>
                        <p class="font-medium" style="color: var(--text-primary);">{{ \Carbon\Carbon::parse($invoice->created_at)->format('M d, Y') }}</p>
                    </div>
                    
                    <div>
                        <p class="text-sm mb-1" style="color: var(--text-secondary);">Due Date</p>
                        <p class="font-medium" style="color: var(--text-primary);">{{ \Carbon\Carbon::parse($invoice->due_date)->format('M d, Y') }}</p>
                    </div>
                    
                    @if($invoice->payment_method)
                    <div>
                        <p class="text-sm mb-1" style="color: var(--text-secondary);">Payment Method</p>
                        <p class="font-medium" style="color: var(--text-primary);">{{ ucfirst(str_replace('_', ' ', $invoice->payment_method)) }}</p>
                    </div>
                    @endif
                    
                    @if($invoice->payment_reference)
                    <div>
                        <p class="text-sm mb-1" style="color: var(--text-secondary);">Payment Reference</p>
                        <p class="font-medium" style="color: var(--text-primary);">{{ $invoice->payment_reference }}</p>
                    </div>
                    @endif
                    
                    @if($invoice->description)
                    <div>
                        <p class="text-sm mb-1" style="color: var(--text-secondary);">Description</p>
                        <p class="text-sm" style="color: var(--text-primary);">{{ $invoice->description }}</p>
                    </div>
                    @endif
                    
                    @if($invoice->is_bulk_payment && $invoice->bulk_months)
                    <div>
                        <p class="text-sm mb-1" style="color: var(--text-secondary);">Total Months</p>
                        <p class="font-medium" style="color: var(--text-primary);">{{ $invoice->bulk_months }} months</p>
                    </div>
                    @endif
                    
                    @if($invoice->bulk_parent_id)
                    <div>
                        <p class="text-sm mb-1" style="color: var(--text-secondary);">Bulk Parent</p>
                        <p class="font-medium" style="color: var(--text-primary);">
                            <a href="{{ route('landlord.invoices.show', $invoice->bulk_parent_id) }}" class="text-primary hover:underline">
                                INV-{{ str_pad($invoice->bulk_parent_id, 6, '0', STR_PAD_LEFT) }}
                            </a>
                        </p>
                    </div>
                    @endif
                </div>
                
                <!-- Amount Breakdown -->
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Base Amount</span>
                            <span style="color: var(--text-primary);">{{ $settings->formatAmount($invoice->amount) }}</span>
                        </div>
                        
                        @if($invoice->original_amount > $invoice->amount)
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Original Amount</span>
                            <span style="color: var(--text-primary);">{{ $settings->formatAmount($invoice->original_amount) }}</span>
                        </div>
                        @endif
                        
                        @if($invoice->discount_amount > 0)
                        <div class="flex justify-between">
                            <span style="color: var(--success);">Discount ({{ $invoice->discount_percentage }}%)</span>
                            <span style="color: var(--success);">-{{ $settings->formatAmount($invoice->discount_amount) }}</span>
                        </div>
                        @endif
                        
                        @if(($invoice->penalty_amount ?? 0) > 0)
                        <div class="flex justify-between">
                            <span style="color: var(--danger);">Late Fee Penalty</span>
                            <span style="color: var(--danger);">{{ $settings->formatAmount($invoice->penalty_amount) }}</span>
                        </div>
                        @endif
                        
                        <div class="pt-2 border-t" style="border-color: var(--border-color);">
                            <div class="flex justify-between font-medium">
                                <span style="color: var(--text-primary);">Total Amount</span>
                                <span style="color: var(--text-primary);">
                                    {{ $settings->formatAmount($invoice->total_amount ?? ($invoice->amount + ($invoice->penalty_amount ?? 0))) }}
                                </span>
                            </div>
                        </div>
                        
                        @if($invoice->paid_amount > 0)
                        <div class="flex justify-between pt-2">
                            <span style="color: var(--success);">Paid Amount</span>
                            <span style="color: var(--success);">{{ $settings->formatAmount($invoice->paid_amount) }}</span>
                        </div>
                        @endif
                        
                        @if($invoice->balance > 0)
                        <div class="flex justify-between pt-2">
                            <span style="color: var(--danger);">Balance Due</span>
                            <span style="color: var(--danger); font-weight: 600;">{{ $settings->formatAmount($invoice->balance) }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Actions Card -->
            <div class="card p-6">
                <h3 class="text-lg font-medium mb-4" style="color: var(--text-primary);">Actions</h3>
                
                <div class="space-y-3">
                    <a href="{{ route('landlord.invoices.print', $invoice->id) }}" target="_blank" class="block w-full p-3 rounded text-center transition-opacity hover:opacity-80" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                        <i class="fas fa-print mr-2"></i> Print Invoice
                    </a>
                    
                    @if(in_array($invoice->status, ['pending', 'overdue', 'partial']) && $invoice->balance > 0 && !$invoice->bulk_parent_id)
                    <a href="{{ route('landlord.payments.create', ['propertyId' => $invoice->property_id]) }}?invoice_ids[]={{ $invoice->id }}" class="block w-full p-3 rounded text-center transition-opacity hover:opacity-90" style="background-color: var(--success); color: white;">
                        <i class="fas fa-credit-card mr-2"></i> Pay {{ $invoice->balance < $invoice->total_amount ? 'Remaining Balance' : 'Invoice' }}
                    </a>
                    @endif
                    
                    @if($invoice->bulk_parent_id && $invoice->bulkPayment && in_array($invoice->bulkPayment->status, ['pending', 'overdue']))
                    <a href="{{ route('landlord.invoices.show', $invoice->bulk_parent_id) }}" class="block w-full p-3 rounded text-center transition-opacity hover:opacity-80" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-layer-group mr-2"></i> View Bulk Invoice
                    </a>
                    @endif
                    
                    <!-- Check Coverage Button (for regular invoices) -->
                    @if(!$invoice->is_bulk_payment && !$invoice->bulk_parent_id && $invoice->period)
                    <button type="button" onclick="checkBulkCoverage()" class="block w-full p-3 rounded text-center transition-opacity hover:opacity-80" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-shield-alt mr-2"></i> Check Coverage Status
                    </button>
                    @endif
                    
                    <!-- Bulk Payment Button - Only for regular, non-consolidated, non-bulk invoices -->
                    @php
                        $bulkPaymentsEnabled = $settings->enable_bulk_payments ?? false;
                    @endphp
                    
                    @if(!$invoice->is_bulk_payment && !$invoice->bulk_parent_id && in_array($invoice->status, ['pending', 'overdue']) && $bulkPaymentsEnabled)
                    <button type="button" onclick="showBulkPaymentModal()" id="bulkPaymentBtn" class="block w-full p-3 rounded text-center transition-opacity hover:opacity-80" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-layer-group mr-2"></i> Create Bulk Payment
                    </button>
                    @elseif(!$invoice->is_bulk_payment && !$invoice->bulk_parent_id && in_array($invoice->status, ['pending', 'overdue']) && !$bulkPaymentsEnabled)
                    <div class="p-3 rounded text-center text-sm" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Bulk payments are currently disabled
                    </div>
                    @endif
                    
                    <a href="{{ route('landlord.invoices') }}" class="block w-full p-3 rounded text-center transition-opacity hover:opacity-80" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Invoices
                    </a>
                </div>
                
                <!-- Help Section with System Email -->
                <div class="mt-6 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <p class="text-sm font-medium mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-question-circle mr-2"></i> Need Help?
                    </p>
                    <p class="text-xs mb-2" style="color: var(--text-secondary);">
                        For questions about this invoice or payment issues, contact our support team:
                    </p>
                    
                    <div class="flex items-center mt-2 p-2 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2" style="background-color: rgba(var(--info-rgb), 0.2);">
                            <i class="fas fa-envelope" style="color: var(--info);"></i>
                        </div>
                        <div>
                            <p class="text-xs font-medium" style="color: var(--text-secondary);">Email Support</p>
                            <a href="mailto:{{ $settings->system_email ?? 'support@example.com' }}" class="text-sm font-medium hover:underline" style="color: var(--primary);">
                                {{ $settings->system_email ?? 'support@example.com' }}
                            </a>
                        </div>
                    </div>
                    
                    @if(!empty($settings->system_phone))
                    <div class="flex items-center mt-2 p-2 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2" style="background-color: rgba(var(--info-rgb), 0.2);">
                            <i class="fas fa-phone" style="color: var(--info);"></i>
                        </div>
                        <div>
                            <p class="text-xs font-medium" style="color: var(--text-secondary);">Phone Support</p>
                            <a href="tel:{{ $settings->system_phone }}" class="text-sm font-medium hover:underline" style="color: var(--primary);">
                                {{ $settings->system_phone }}
                            </a>
                        </div>
                    </div>
                    @endif
                    
                    <div class="mt-3 text-xs" style="color: var(--text-secondary);">
                        <i class="far fa-clock mr-1"></i> Mon-Fri, 9:00 AM - 5:00 PM
                    </div>
                </div>
                
                @if(!empty($settings->payment_instructions) && in_array($invoice->status, ['pending', 'overdue']) && !$invoice->bulk_parent_id)
                <div class="mt-4 p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <p class="text-xs font-medium mb-1" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle mr-1"></i> Payment Instructions
                    </p>
                    <p class="text-xs" style="color: var(--text-secondary);">{{ $settings->payment_instructions }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Coverage Status Modal -->
<div id="coverageModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden transition-opacity duration-300">
    <div class="absolute inset-0" onclick="hideCoverageModal()"></div>
    <div class="rounded-lg w-full max-w-md mx-4 p-6 relative z-10 transform transition-all duration-300 scale-95 opacity-0" 
         id="coverageModalContent"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium" style="color: var(--text-primary);">Bulk Coverage Status</h3>
            <button type="button" onclick="hideCoverageModal()" class="transition-colors" style="color: var(--text-secondary);">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div id="coverageModalInnerContent" class="space-y-4">
            <div class="text-center py-4">
                <i class="fas fa-spinner fa-spin text-2xl" style="color: var(--primary);"></i>
                <p class="mt-2" style="color: var(--text-secondary);">Checking coverage status...</p>
            </div>
        </div>
        
        <div class="mt-4 flex justify-end">
            <button type="button" onclick="hideCoverageModal()" class="px-4 py-2 rounded transition-colors" 
                    style="background-color: var(--primary); color: white;">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Bulk Payment Modal -->
@php
    $bulkPaymentsEnabled = $settings->enable_bulk_payments ?? false;
    $maxBulkMonths = $settings->max_bulk_months ?? 12;
    $availableMonths = range(2, $maxBulkMonths);
    
    // Get existing invoices for this property
    $existingInvoicesByMonth = [];
    $startDate = now()->startOfMonth();
    
    $existingInvoices = \App\Models\Invoice::where('property_id', $invoice->property_id)
        ->whereIn('status', ['pending', 'overdue'])
        ->where('is_bulk_payment', false)
        ->whereNull('bulk_parent_id')
        ->where('id', '!=', $invoice->id)
        ->get()
        ->keyBy('period');
    
    for ($i = 0; $i < $maxBulkMonths; $i++) {
        $month = $startDate->copy()->addMonths($i)->format('Y-m');
        $existingInvoicesByMonth[$month] = isset($existingInvoices[$month]);
    }
@endphp

@if(!$invoice->is_bulk_payment && !$invoice->bulk_parent_id && in_array($invoice->status, ['pending', 'overdue']) && $bulkPaymentsEnabled)
<div id="bulkPaymentModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden transition-opacity duration-300">
    <div class="absolute inset-0" onclick="hideBulkPaymentModal()"></div>
    <div class="rounded-lg w-full max-w-md mx-4 p-6 relative z-10 transform transition-all duration-300 scale-95 opacity-0 shadow-xl" 
         id="bulkPaymentModalContent"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium" style="color: var(--text-primary);">Create Bulk Payment</h3>
            <button type="button" onclick="hideBulkPaymentModal()" class="transition-colors" style="color: var(--text-secondary);">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <form action="{{ route('landlord.create-bulk-payment') }}" method="POST" id="bulkPaymentForm">
            @csrf
            <input type="hidden" name="property_id" value="{{ $invoice->property_id }}">
            <input type="hidden" name="invoice_ids[]" value="{{ $invoice->id }}">
            <input type="hidden" name="start_month" id="startMonth" value="{{ now()->startOfMonth()->format('Y-m') }}">
            
            <div class="mb-4">
                <label for="months" class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                    Number of Months <span class="text-red-500">*</span>
                </label>
                <select name="months" id="months" class="w-full p-2 rounded border" 
                        style="border: 1px solid var(--border-color); background-color: var(--bg-primary); color: var(--text-primary);" 
                        required>
                    @foreach($availableMonths as $month)
                        <option value="{{ $month }}" {{ $month == 3 ? 'selected' : '' }}>
                            {{ $month }} months
                        </option>
                    @endforeach
                </select>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    Minimum 2 months, maximum {{ $maxBulkMonths }} months
                </p>
            </div>
            
            <!-- Info Message -->
            <div id="bulkInfoMessage" class="mb-4 p-3 rounded hidden" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.2);">
                <div class="flex items-start">
                    <i class="fas fa-info-circle mr-2 mt-1" style="color: var(--info);"></i>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--info);">Information</p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);" id="bulkInfoMessageText"></p>
                    </div>
                </div>
            </div>
            
            <!-- Months to be included -->
            <div class="mb-4">
                <p class="text-sm font-medium mb-2" style="color: var(--text-secondary);">Included Months:</p>
                <div class="max-h-40 overflow-y-auto p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.05);" id="includedMonthsList">
                    <!-- Dynamically populated by JavaScript -->
                </div>
            </div>
            
            <!-- Consolidation Warning (hidden by default) -->
            <div id="consolidationWarning" class="mb-4 p-3 rounded hidden" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle mr-2 mt-1" style="color: var(--warning);"></i>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--warning);">Existing Invoices Found</p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Some months already have pending invoices. They will be automatically consolidated into this bulk payment.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Total Display -->
            <div class="mb-4 p-3 rounded-lg" 
                 style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.2);">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium" style="color: var(--text-secondary);">Monthly Amount:</span>
                    <span class="text-sm font-bold" style="color: var(--text-primary);">{{ $settings->formatAmount($invoice->amount) }}</span>
                </div>
                
                @if($settings->bulk_payment_discount > 0)
                <div class="flex justify-between items-center mt-1">
                    <span class="text-sm font-medium" style="color: var(--success);">Bulk Discount ({{ $settings->bulk_payment_discount }}%):</span>
                    <span class="text-sm font-bold" style="color: var(--success);" id="discountAmount">-{{ $settings->formatAmount(0) }}</span>
                </div>
                @endif
                
                <div class="flex justify-between items-center mt-2 pt-2 border-t" style="border-color: var(--border-color);">
                    <span class="text-base font-medium" style="color: var(--text-secondary);">Total Amount:</span>
                    <span class="text-lg font-bold" style="color: var(--info);" id="totalAmount">{{ $settings->formatAmount($invoice->amount * 3) }}</span>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="hideBulkPaymentModal()" class="px-4 py-2 rounded transition-colors" 
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 rounded transition-colors" 
                        style="background-color: var(--primary); color: white;">
                    Create Bulk Payment
                </button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@section('scripts')
<script>
// Auto-hide success and error messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
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
    
    // Initialize bulk payment calculator if modal exists
    @if(!$invoice->is_bulk_payment && !$invoice->bulk_parent_id && in_array($invoice->status, ['pending', 'overdue']) && ($settings->enable_bulk_payments ?? false))
        const monthsSelect = document.getElementById('months');
        if (monthsSelect) {
            monthsSelect.addEventListener('change', updateBulkPaymentDetails);
            updateBulkPaymentDetails();
        }
    @endif
    
    // Theme detection
    checkCurrentTheme();
});

// Check current theme for debugging
function checkCurrentTheme() {
    const isDark = document.documentElement.classList.contains('dark');
    console.log('Current theme:', isDark ? 'Dark' : 'Light');
}

// Update bulk payment details (months list, totals, warnings)
function updateBulkPaymentDetails() {
    const monthsSelect = document.getElementById('months');
    if (!monthsSelect) return;
    
    const months = parseInt(monthsSelect.value);
    const monthlyAmount = {{ $invoice->amount }};
    const discountPercentage = {{ $settings->bulk_payment_discount ?? 0 }};
    const startMonth = '{{ now()->startOfMonth()->format("Y-m") }}';
    
    // Calculate total before discount
    const subtotal = monthlyAmount * months;
    const discountAmount = subtotal * (discountPercentage / 100);
    const totalAmount = subtotal - discountAmount;
    
    // Update totals
    const totalAmountElement = document.getElementById('totalAmount');
    if (totalAmountElement) {
        totalAmountElement.textContent = '{{ $settings->currency_symbol ?? '₵' }}' + totalAmount.toFixed(2);
    }
    
    const discountAmountElement = document.getElementById('discountAmount');
    if (discountAmountElement) {
        discountAmountElement.textContent = '-{{ $settings->currency_symbol ?? '₵' }}' + discountAmount.toFixed(2);
    }
    
    // Generate months list
    const monthsList = document.getElementById('includedMonthsList');
    const infoMessage = document.getElementById('bulkInfoMessage');
    const infoMessageText = document.getElementById('bulkInfoMessageText');
    
    if (monthsList) {
        let html = '<ul class="space-y-1 text-sm">';
        let hasExisting = false;
        let existingCount = 0;
        let existingMonths = [];
        
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                           'July', 'August', 'September', 'October', 'November', 'December'];
        
        for (let i = 0; i < months; i++) {
            const date = new Date(startMonth + '-01');
            date.setMonth(date.getMonth() + i);
            const year = date.getFullYear();
            const month = date.getMonth();
            const monthStr = year + '-' + String(month + 1).padStart(2, '0');
            const monthName = monthNames[month] + ' ' + year;
            
            // Check if invoice exists (from PHP data)
            const existingInvoices = @json($existingInvoicesByMonth);
            const exists = existingInvoices[monthStr] || false;
            
            if (exists) {
                hasExisting = true;
                existingCount++;
                existingMonths.push(monthName);
                html += `<li class="text-amber-600 dark:text-amber-400">
                    <i class="fas fa-check-circle mr-1 text-xs"></i>
                    ${monthName} <span class="text-xs font-bold">(existing invoice - will be consolidated)</span>
                </li>`;
            } else {
                html += `<li>
                    <i class="fas fa-plus-circle mr-1 text-xs text-green-600"></i>
                    ${monthName} <span class="text-xs">(new invoice)</span>
                </li>`;
            }
        }
        
        html += '</ul>';
        monthsList.innerHTML = html;
        
        // Update info message
        if (infoMessage && infoMessageText) {
            if (hasExisting) {
                infoMessageText.innerHTML = `
                    <strong>${existingCount}</strong> existing invoice(s) found for: ${existingMonths.join(', ')}.
                    They will be automatically consolidated into this bulk payment. No duplicate invoices will be created.
                `;
                infoMessage.classList.remove('hidden');
            } else {
                infoMessage.classList.add('hidden');
            }
        }
        
        // Show/hide consolidation warning
        const warning = document.getElementById('consolidationWarning');
        if (warning) {
            if (hasExisting) {
                warning.classList.remove('hidden');
            } else {
                warning.classList.add('hidden');
            }
        }
    }
}

// Bulk Payment Modal Functions
function showBulkPaymentModal() {
    const modal = document.getElementById('bulkPaymentModal');
    const modalContent = document.getElementById('bulkPaymentModalContent');
    
    if (modal && modalContent) {
        console.log('Showing bulk payment modal');
        modal.classList.remove('hidden');
        
        setTimeout(() => {
            modalContent.classList.remove('scale-95', 'opacity-0');
            modalContent.classList.add('scale-100', 'opacity-100');
        }, 10);
        
        updateBulkPaymentDetails();
        document.body.style.overflow = 'hidden';
    } else {
        console.error('Modal elements not found');
    }
}

function hideBulkPaymentModal() {
    const modal = document.getElementById('bulkPaymentModal');
    const modalContent = document.getElementById('bulkPaymentModalContent');
    
    if (modal && modalContent) {
        console.log('Hiding bulk payment modal');
        modalContent.classList.remove('scale-100', 'opacity-100');
        modalContent.classList.add('scale-95', 'opacity-0');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }, 300);
    }
}

// Form validation before submission
document.addEventListener('DOMContentLoaded', function() {
    const bulkForm = document.getElementById('bulkPaymentForm');
    if (bulkForm) {
        bulkForm.addEventListener('submit', function(e) {
            const monthsSelect = document.getElementById('months');
            if (!monthsSelect) return;
            
            const selectedMonths = parseInt(monthsSelect.value);
            const maxMonths = {{ $maxBulkMonths }};
            
            if (selectedMonths < 2) {
                e.preventDefault();
                showToast('error', 'Minimum 2 months required for bulk payment.');
                return false;
            }
            
            if (selectedMonths > maxMonths) {
                e.preventDefault();
                showToast('error', `Maximum ${maxMonths} months allowed for bulk payment.`);
                return false;
            }
            
            return true;
        });
    }
});

// Check bulk coverage for this invoice's period
function checkBulkCoverage() {
    const propertyId = {{ $invoice->property_id }};
    const period = '{{ $invoice->period }}';
    
    if (!period) {
        showToast('info', 'This invoice does not have a period to check.');
        return;
    }
    
    const modal = document.getElementById('coverageModal');
    const modalContent = document.getElementById('coverageModalInnerContent');
    
    if (!modal || !modalContent) return;
    
    modal.classList.remove('hidden');
    setTimeout(() => {
        document.getElementById('coverageModalContent').classList.remove('scale-95', 'opacity-0');
        document.getElementById('coverageModalContent').classList.add('scale-100', 'opacity-100');
    }, 10);
    
    fetch(`/landlord/invoices/check-coverage?property_id=${propertyId}&period=${period}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = '';
                
                if (data.covered) {
                    html = `
                        <div class="text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-4" style="background-color: rgba(var(--success-rgb), 0.2);">
                                <i class="fas fa-shield-alt text-3xl" style="color: var(--success);"></i>
                            </div>
                            <h4 class="text-lg font-medium mb-2" style="color: var(--text-primary);">Period is Covered</h4>
                            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                                ${data.message}
                            </p>
                    `;
                    
                    if (data.coverage_details) {
                        html += `
                            <div class="mt-4 p-4 rounded text-left" style="background-color: rgba(var(--info-rgb), 0.1);">
                                <p class="text-sm font-medium mb-2" style="color: var(--text-primary);">Coverage Details:</p>
                                <p class="text-xs mb-1" style="color: var(--text-secondary);">
                                    <span class="font-medium">Bulk Invoice:</span> 
                                    <a href="/landlord/invoices/${data.coverage_details.bulk_invoice_id}" class="text-primary hover:underline">
                                        #INV-${String(data.coverage_details.bulk_invoice_id).padStart(6, '0')}
                                    </a>
                                </p>
                                <p class="text-xs mb-1" style="color: var(--text-secondary);">
                                    <span class="font-medium">Payment Date:</span> ${data.coverage_details.payment_date}
                                </p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    <span class="font-medium">Transaction:</span> ${data.coverage_details.transaction_id}
                                </p>
                            </div>
                        `;
                    }
                    
                    html += `</div>`;
                } else {
                    html = `
                        <div class="text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-4" style="background-color: rgba(var(--warning-rgb), 0.2);">
                                <i class="fas fa-info-circle text-3xl" style="color: var(--warning);"></i>
                            </div>
                            <h4 class="text-lg font-medium mb-2" style="color: var(--text-primary);">No Active Coverage</h4>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                ${data.message}
                            </p>
                        </div>
                    `;
                }
                
                modalContent.innerHTML = html;
            } else {
                modalContent.innerHTML = `
                    <div class="text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-4" style="background-color: rgba(var(--danger-rgb), 0.2);">
                            <i class="fas fa-exclamation-triangle text-3xl" style="color: var(--danger);"></i>
                        </div>
                        <h4 class="text-lg font-medium mb-2" style="color: var(--text-primary);">Error</h4>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            ${data.message || 'Failed to check coverage status.'}
                        </p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Coverage check failed:', error);
            modalContent.innerHTML = `
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-4" style="background-color: rgba(var(--danger-rgb), 0.2);">
                        <i class="fas fa-exclamation-triangle text-3xl" style="color: var(--danger);"></i>
                    </div>
                    <h4 class="text-lg font-medium mb-2" style="color: var(--text-primary);">Error</h4>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        Failed to check coverage status. Please try again.
                    </p>
                </div>
            `;
        });
}

function hideCoverageModal() {
    const modal = document.getElementById('coverageModal');
    const modalContent = document.getElementById('coverageModalContent');
    
    if (modal && modalContent) {
        modalContent.classList.remove('scale-100', 'opacity-100');
        modalContent.classList.add('scale-95', 'opacity-0');
        
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }
}

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
        display: flex;
        align-items: center;
        justify-content: space-between;
    `;
    
    const colors = {
        success: 'var(--success)',
        error: 'var(--danger)',
        warning: 'var(--warning)',
        info: 'var(--info)'
    };
    
    toast.style.backgroundColor = colors[type] || colors.info;
    
    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} mr-2"></i>
            <span>${message}</span>
        </div>
        <button type="button" class="ml-4 text-white hover:text-gray-200">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    const closeBtn = toast.querySelector('button');
    closeBtn.addEventListener('click', () => {
        toast.style.animation = 'slideOutRight 0.3s ease-out forwards';
        setTimeout(() => toast.remove(), 300);
    });
    
    setTimeout(() => {
        if (toast.parentNode) {
            toast.style.animation = 'slideOutRight 0.3s ease-out forwards';
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
    
    toastContainer.appendChild(toast);
}

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const bulkModal = document.getElementById('bulkPaymentModal');
        if (bulkModal && !bulkModal.classList.contains('hidden')) {
            hideBulkPaymentModal();
        }
        
        const coverageModal = document.getElementById('coverageModal');
        if (coverageModal && !coverageModal.classList.contains('hidden')) {
            hideCoverageModal();
        }
    }
});

// Real-time status checking (skip for consolidated invoices)
@if(in_array($invoice->status, ['pending', 'overdue', 'partial']) && !$invoice->bulk_parent_id)
setInterval(checkInvoiceStatus, 30000);
setTimeout(checkInvoiceStatus, 2000);
@endif

function checkInvoiceStatus() {
    const invoiceId = {{ $invoice->id }};
    
    fetch(`/landlord/invoices/${invoiceId}/status`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const currentStatus = '{{ $invoice->status }}';
                if (data.data.status !== currentStatus) {
                    location.reload();
                }
            }
        })
        .catch(error => console.log('Status check failed:', error.message));
}

// Theme observer
const themeObserver = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.attributeName === 'class') {
            console.log('Theme changed:', 
                document.documentElement.classList.contains('dark') ? 'Dark' : 'Light');
        }
    });
});

themeObserver.observe(document.documentElement, { attributes: true });
</script>

<style>
/* Success and error message styles */
.bg-green-100 {
    background-color: rgba(209, 250, 229, 0.9);
    border-color: rgba(16, 185, 129, 0.3);
}

.bg-red-100 {
    background-color: rgba(254, 226, 226, 0.9);
    border-color: rgba(239, 68, 68, 0.3);
}

.bg-blue-100 {
    background-color: rgba(219, 234, 254, 0.9);
    border-color: rgba(59, 130, 246, 0.3);
}

/* Timeline styling */
.w-0\.5 {
    width: 2px;
}

/* Card hover effects */
.card {
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

/* Modal transitions */
#bulkPaymentModal, #coverageModal {
    transition: opacity 0.3s ease;
}

#bulkPaymentModal.hidden, #coverageModal.hidden {
    opacity: 0;
    pointer-events: none;
}

#bulkPaymentModal:not(.hidden), #coverageModal:not(.hidden) {
    opacity: 1;
    pointer-events: auto;
}

#bulkPaymentModalContent, #coverageModalContent {
    transition: all 0.3s ease;
}

/* Button cursor fix */
button#bulkPaymentBtn {
    cursor: pointer !important;
    user-select: none;
}

/* Help section hover effect */
.p-4.rounded-lg:hover {
    background-color: rgba(var(--info-rgb), 0.15) !important;
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

/* Modal backdrop */
.fixed.inset-0.bg-black {
    backdrop-filter: blur(4px);
}

/* Dark mode specific adjustments */
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

.dark .bg-blue-100 {
    background-color: rgba(59, 130, 246, 0.2) !important;
    border-color: rgba(59, 130, 246, 0.3) !important;
    color: #3b82f6 !important;
}

.dark .text-amber-600 {
    color: #fbbf24 !important;
}

/* Max height for months list */
.max-h-40 {
    max-height: 10rem;
    overflow-y: auto;
}

/* Custom scrollbar for months list */
.max-h-40::-webkit-scrollbar {
    width: 6px;
}

.max-h-40::-webkit-scrollbar-track {
    background: rgba(var(--secondary-rgb), 0.1);
    border-radius: 3px;
}

.max-h-40::-webkit-scrollbar-thumb {
    background: rgba(var(--secondary-rgb), 0.3);
    border-radius: 3px;
}

.max-h-40::-webkit-scrollbar-thumb:hover {
    background: rgba(var(--secondary-rgb), 0.5);
}

/* Coverage badge animation */
@keyframes pulse-green {
    0% {
        box-shadow: 0 0 0 0 rgba(var(--success-rgb), 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(var(--success-rgb), 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(var(--success-rgb), 0);
    }
}

.coverage-active {
    animation: pulse-green 2s infinite;
}
</style>
@endsection