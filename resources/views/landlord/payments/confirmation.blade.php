@extends('layouts.landlord')

@section('title', 'Payment Confirmation')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Status Card -->
    <div class="card p-8 text-center">
        @switch($payment->status)
            @case('pending')
                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-clock text-2xl" style="color: var(--warning);"></i>
                </div>
                <h1 class="text-2xl font-semibold mb-2" style="color: var(--warning);">
                    @if($payment->metadata['payment_type'] === 'invoices')
                        Invoice Payment Processing
                    @elseif($payment->metadata['payment_type'] === 'bulk')
                        Bulk Payment Processing
                    @else
                        Payment Processing
                    @endif
                </h1>
                <p class="mb-6" style="color: var(--text-secondary);">
                    @if($payment->metadata['payment_type'] === 'invoices')
                        Your invoice payment is being processed. Selected invoices will be marked as paid once payment is confirmed.
                    @elseif($payment->metadata['payment_type'] === 'bulk')
                        Your bulk payment is being processed. Invoices for selected months will be generated once payment is confirmed.
                    @else
                        Your payment is being processed.
                    @endif
                </p>
                @break

            @case('processing')
                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-sync-alt fa-spin text-2xl" style="color: var(--info);"></i>
                </div>
                <h1 class="text-2xl font-semibold mb-2" style="color: var(--info);">
                    @if($payment->metadata['payment_type'] === 'invoices')
                        Invoice Payment Processing
                    @elseif($payment->metadata['payment_type'] === 'bulk')
                        Bulk Payment Processing
                    @else
                        Payment Processing
                    @endif
                </h1>
                <p class="mb-6" style="color: var(--text-secondary);">
                    @if($payment->metadata['payment_type'] === 'invoices')
                        Your invoice payment is being processed by {{ $payment->payment_provider_display }}. Invoices will be marked as paid once confirmed.
                    @elseif($payment->metadata['payment_type'] === 'bulk')
                        Your bulk payment is being processed by {{ $payment->payment_provider_display }}. Invoices will be generated for selected months once confirmed.
                    @else
                        Your payment is being processed by {{ $payment->payment_provider_display }}.
                    @endif
                </p>
                @break

            @case('completed')
                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-check-circle text-2xl" style="color: var(--success);"></i>
                </div>
                <h1 class="text-2xl font-semibold mb-2" style="color: var(--success);">
                    @if($payment->metadata['payment_type'] === 'invoices')
                        Invoice Payment Successful!
                    @elseif($payment->metadata['payment_type'] === 'bulk')
                        Bulk Payment Successful!
                    @else
                        Payment Successful!
                    @endif
                </h1>
                <p class="mb-6" style="color: var(--text-secondary);">
                    @if($payment->metadata['payment_type'] === 'invoices')
                        Your invoice payment has been processed successfully via {{ $payment->payment_provider_display }}. All selected invoices have been marked as paid.
                    @elseif($payment->metadata['payment_type'] === 'bulk')
                        Your bulk payment has been processed successfully via {{ $payment->payment_provider_display }}. Invoices for selected months have been generated and marked as paid.
                    @else
                        Your payment has been processed successfully via {{ $payment->payment_provider_display }}.
                    @endif
                </p>
                @break

            @case('failed')
                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-times-circle text-2xl" style="color: var(--danger);"></i>
                </div>
                <h1 class="text-2xl font-semibold mb-2" style="color: var(--danger);">
                    @if($payment->metadata['payment_type'] === 'invoices')
                        Invoice Payment Failed
                    @elseif($payment->metadata['payment_type'] === 'bulk')
                        Bulk Payment Failed
                    @else
                        Payment Failed
                    @endif
                </h1>
                <p class="mb-6" style="color: var(--text-secondary);">
                    @if($payment->metadata['payment_type'] === 'invoices')
                        Your invoice payment could not be processed by {{ $payment->payment_provider_display }}. No invoices have been charged.
                    @elseif($payment->metadata['payment_type'] === 'bulk')
                        Your bulk payment could not be processed by {{ $payment->payment_provider_display }}. No invoices have been generated.
                    @else
                        Your payment could not be processed by {{ $payment->payment_provider_display }}.
                    @endif
                </p>
                @break

            @case('cancelled')
                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background-color: rgba(var(--secondary-rgb), 0.1);">
                    <i class="fas fa-ban text-2xl" style="color: var(--secondary);"></i>
                </div>
                <h1 class="text-2xl font-semibold mb-2" style="color: var(--secondary);">
                    @if($payment->metadata['payment_type'] === 'invoices')
                        Invoice Payment Cancelled
                    @elseif($payment->metadata['payment_type'] === 'bulk')
                        Bulk Payment Cancelled
                    @else
                        Payment Cancelled
                    @endif
                </h1>
                <p class="mb-6" style="color: var(--text-secondary);">
                    @if($payment->metadata['payment_type'] === 'invoices')
                        This invoice payment has been cancelled. No invoices were charged.
                    @elseif($payment->metadata['payment_type'] === 'bulk')
                        This bulk payment has been cancelled. No invoices were generated.
                    @else
                        This payment has been cancelled.
                    @endif
                </p>
                @break

            @default
                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-info-circle text-2xl" style="color: var(--info);"></i>
                </div>
                <h1 class="text-2xl font-semibold mb-2" style="color: var(--text-primary);">
                    @if(isset($payment->metadata['payment_type']) && $payment->metadata['payment_type'] === 'invoices')
                        Invoice Payment {{ ucfirst($payment->status) }}
                    @elseif(isset($payment->metadata['payment_type']) && $payment->metadata['payment_type'] === 'bulk')
                        Bulk Payment {{ ucfirst($payment->status) }}
                    @else
                        Payment {{ ucfirst($payment->status) }}
                    @endif
                </h1>
                <p class="mb-6" style="color: var(--text-secondary);">
                    Payment status: {{ $payment->status }}
                </p>
        @endswitch
        
        <!-- Recipient Information -->
        @if(isset($recipientInfo) && !empty($recipientInfo['number']))
        <div class="card p-4 mb-6" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.2);">
            <div class="flex items-center justify-center mb-2">
                <i class="fas fa-user-check mr-2" style="color: var(--primary);"></i>
                <span class="font-medium" style="color: var(--primary);">Payment Recipient</span>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <p class="mb-1">Name: {{ $recipientInfo['name'] ?? 'System Administrator' }}</p>
                <p class="mb-1">Phone: {{ $recipientInfo['number'] }}</p>
                @if(isset($recipientInfo['network']))
                <p>Network: {{ $recipientInfo['network'] }}</p>
                @endif
            </div>
            <p class="text-xs mt-2 italic" style="color: var(--text-secondary);">
                All payments are securely processed and sent to the system administrator.
            </p>
        </div>
        @endif
        
        <!-- Real-time Status Updates -->
        @if(in_array($payment->status, ['pending', 'processing']))
        <div class="card p-4 mb-6" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid var(--info);">
            <div class="flex items-center justify-center">
                <i class="fas fa-sync-alt fa-spin mr-3" style="color: var(--info);"></i>
                <span style="color: var(--info);">
                    @if(isset($payment->metadata['payment_type']) && $payment->metadata['payment_type'] === 'invoices')
                        Checking invoice payment status automatically...
                    @elseif(isset($payment->metadata['payment_type']) && $payment->metadata['payment_type'] === 'bulk')
                        Checking bulk payment status automatically...
                    @else
                        Checking payment status automatically...
                    @endif
                </span>
            </div>
            <div class="mt-2 text-sm" style="color: var(--text-secondary);">
                This page will update automatically when the status changes.
                @if(isset($payment->metadata['payment_type']) && $payment->metadata['payment_type'] === 'invoices')
                    Selected invoices will be marked as paid once payment is confirmed.
                @elseif(isset($payment->metadata['payment_type']) && $payment->metadata['payment_type'] === 'bulk')
                    Invoices will be generated for selected months once payment is confirmed.
                @endif
            </div>
        </div>
        @endif

        <!-- Verification Required Section (Updated for new gateways) -->
        @if($requiresVerification)
        <div class="card p-6 mb-6" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid var(--warning);">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4" style="background-color: rgba(var(--warning-rgb), 0.2);">
                    <i class="fas fa-shield-alt" style="color: var(--warning);"></i>
                </div>
                <div>
                    <h2 class="text-lg font-semibold" style="color: var(--warning);">Verification Required</h2>
                    <p class="text-sm" style="color: var(--text-secondary);">Complete verification to finalize your payment</p>
                </div>
            </div>
            
            <p class="mb-4" style="color: var(--text-secondary);">
                Please verify your {{ $payment->payment_provider_display }} payment with the code sent to your phone.
                @if(isset($payment->metadata['payment_type']) && $payment->metadata['payment_type'] === 'invoices')
                    Invoices will be marked as paid after successful verification.
                @elseif(isset($payment->metadata['payment_type']) && $payment->metadata['payment_type'] === 'bulk')
                    Invoices will be generated after successful verification.
                @endif
            </p>
            
            <a href="{{ route('landlord.payments.verify.form', $payment->transaction_id) }}" 
               class="flex items-center justify-center px-4 py-2 rounded" style="background-color: var(--warning); color: white;">
                <i class="fas fa-shield-alt mr-2"></i>Verify Payment Now
            </a>
        </div>
        @endif

        <!-- Payment Instructions -->
        @if($providerInstructions && in_array($payment->status, ['pending', 'processing']))
        <div class="card p-6 mb-6" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid var(--info);">
            <h2 class="text-lg font-semibold mb-4" style="color: var(--info);">
                <i class="fas fa-info-circle mr-2"></i>
                @if(isset($payment->metadata['payment_type']) && $payment->metadata['payment_type'] === 'invoices')
                    Invoice Payment Instructions
                @elseif(isset($payment->metadata['payment_type']) && $payment->metadata['payment_type'] === 'bulk')
                    Bulk Payment Instructions
                @else
                    Payment Instructions
                @endif
            </h2>
            <div class="text-sm space-y-2" style="color: var(--text-secondary);">
                {!! $providerInstructions !!}
            </div>
            @if($payment->payment_provider === 'paystack')
            <div class="mt-3 p-3 rounded" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                <p class="text-sm font-medium mb-2" style="color: var(--text-primary);">Payment Reference:</p>
                <code class="px-2 py-1 rounded text-sm" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--text-primary);">
                    {{ $payment->transaction_reference ?? 'Waiting for reference...' }}
                </code>
            </div>
            @endif
        </div>
        @endif

        <!-- Session Instructions (Legacy Support) -->
        @if($instructions)
        <div class="card p-6 mb-6" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid var(--info);">
            <h2 class="text-lg font-semibold mb-4" style="color: var(--info);">
                <i class="fas fa-info-circle mr-2"></i>Payment Instructions
            </h2>
            <p class="mb-0" style="color: var(--text-secondary);">{{ $instructions }}</p>
        </div>
        @endif
        
        <!-- Payment Details -->
        <div class="card p-6 mb-6 text-left">
            <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Payment Details</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm mb-1" style="color: var(--text-secondary);">Transaction ID:</p>
                    <p class="font-medium" style="color: var(--text-primary);">
                        <code class="px-2 py-1 rounded text-sm" style="background-color: rgba(var(--primary-rgb), 0.1);">{{ $payment->transaction_id }}</code>
                    </p>
                </div>
                <div>
                    <p class="text-sm mb-1" style="color: var(--text-secondary);">Amount:</p>
                    <p class="font-medium" style="color: var(--success);">
                        {{ $settings->formatAmount($payment->amount) }}
                    </p>
                </div>
                <div>
                    <p class="text-sm mb-1" style="color: var(--text-secondary);">Payment Provider:</p>
                    <p class="font-medium" style="color: var(--text-primary);">
                        @php
                            // Updated provider display for new gateways
                            $providerDisplay = match($payment->payment_provider) {
                                'expresspay' => 'ExpressPay',
                                'hubtel' => 'Hubtel',
                                'paystack' => 'Paystack',
                                'flutterwave' => 'Flutterwave',
                                default => ucfirst(str_replace('_', ' ', $payment->payment_provider))
                            };
                            // Updated provider icons for new gateways
                            $providerIcon = match($payment->payment_provider) {
                                'expresspay' => 'fa-credit-card',
                                'hubtel' => 'fa-phone-alt',
                                'paystack' => 'fa-credit-card',
                                'flutterwave' => 'fa-cloud-upload-alt',
                                default => 'fa-wallet'
                            };
                            // Updated provider colors for new gateways
                            $providerColor = match($payment->payment_provider) {
                                'expresspay' => '#0066CC',
                                'hubtel' => '#2563EB',
                                'paystack' => '#3B82F6',
                                'flutterwave' => '#F97316',
                                default => 'var(--primary)'
                            };
                        @endphp
                        <span class="inline-flex items-center">
                            <i class="fas {{ $providerIcon }} mr-2" style="color: {{ $providerColor }};"></i>
                            {{ $payment->payment_provider_display ?? $providerDisplay }}
                        </span>
                    </p>
                </div>
                <div>
                    <p class="text-sm mb-1" style="color: var(--text-secondary);">Payment Type:</p>
                    <p class="font-medium" style="color: var(--text-primary);">
                        @if(isset($payment->metadata['payment_type']))
                            @switch($payment->metadata['payment_type'])
                                @case('invoices')
                                    <span class="inline-flex items-center">
                                        <i class="fas fa-file-invoice mr-2" style="color: var(--info);"></i>
                                        Pay Invoices
                                    </span>
                                    @break
                                @case('bulk')
                                    <span class="inline-flex items-center">
                                        <i class="fas fa-calendar-alt mr-2" style="color: var(--purple);"></i>
                                        Bulk Payment
                                    </span>
                                    @break
                                @default
                                    {{ ucfirst($payment->metadata['payment_type']) }}
                            @endswitch
                        @else
                            <span class="text-warning">Not specified</span>
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-sm mb-1" style="color: var(--text-secondary);">Status:</p>
                    <p class="font-medium">
                        @php
                            $statusConfig = [
                                'pending' => ['color' => 'warning', 'icon' => 'clock'],
                                'processing' => ['color' => 'info', 'icon' => 'sync-alt'],
                                'completed' => ['color' => 'success', 'icon' => 'check-circle'],
                                'failed' => ['color' => 'danger', 'icon' => 'times-circle'],
                                'cancelled' => ['color' => 'secondary', 'icon' => 'ban']
                            ];
                            $config = $statusConfig[$payment->status] ?? ['color' => 'info', 'icon' => 'info-circle'];
                        @endphp
                        <span style="color: var(--{{ $config['color'] }});">
                            <i class="fas fa-{{ $config['icon'] }} mr-1"></i>
                            {{ ucfirst($payment->status) }}
                        </span>
                    </p>
                </div>
                <div>
                    <p class="text-sm mb-1" style="color: var(--text-secondary);">Initiated:</p>
                    <p class="font-medium" style="color: var(--text-primary);">
                        {{ $payment->created_at->format('M d, Y H:i') }}
                    </p>
                </div>
                <div>
                    <p class="text-sm mb-1" style="color: var(--text-secondary);">Completed:</p>
                    <p class="font-medium" style="color: var(--text-primary);">
                        @if($payment->payment_date)
                            {{ $payment->payment_date->format('M d, Y H:i') }}
                        @else
                            <span style="color: var(--warning);">Pending</span>
                        @endif
                    </p>
                </div>
                @if($payment->transaction_reference)
                <div class="md:col-span-2">
                    <p class="text-sm mb-1" style="color: var(--text-secondary);">Provider Reference:</p>
                    <p class="font-medium" style="color: var(--text-primary);">
                        <code class="px-2 py-1 rounded" style="background-color: rgba(var(--primary-rgb), 0.1);">{{ $payment->transaction_reference }}</code>
                    </p>
                </div>
                @endif
                @if($payment->metadata['phone_number'] ?? false)
                <div>
                    <p class="text-sm mb-1" style="color: var(--text-secondary);">Phone Number:</p>
                    <p class="font-medium" style="color: var(--text-primary);">{{ $payment->metadata['phone_number'] }}</p>
                </div>
                @endif
                @if($payment->metadata['email'] ?? false)
                <div>
                    <p class="text-sm mb-1" style="color: var(--text-secondary);">Email:</p>
                    <p class="font-medium" style="color: var(--text-primary);">{{ $payment->metadata['email'] }}</p>
                </div>
                @endif
                <!-- Bulk Payment Specific Details -->
                @if(isset($payment->metadata['payment_type']) && $payment->metadata['payment_type'] === 'bulk' && isset($payment->metadata['selected_months']))
                <div class="md:col-span-2">
                    <p class="text-sm mb-1" style="color: var(--text-secondary);">Selected Months:</p>
                    <p class="font-medium" style="color: var(--text-primary);">
                        @php
                            $months = explode(',', $payment->metadata['selected_months']);
                            $monthNames = array_map(function($month) {
                                [$year, $monthNum] = explode('-', $month);
                                return \Carbon\Carbon::create($year, $monthNum, 1)->format('F Y');
                            }, $months);
                        @endphp
                        {{ implode(', ', $monthNames) }}
                    </p>
                </div>
                @endif
            </div>
        </div>
        
        <!-- Property Details -->
        <div class="card p-6 mb-6 text-left">
            <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Property Information</h2>
            
            <div class="space-y-3">
                <div>
                    <p class="text-sm mb-1" style="color: var(--text-secondary);">Property:</p>
                    <p class="font-medium" style="color: var(--text-primary);">{{ $payment->property->name }}</p>
                </div>
                <div>
                    <p class="text-sm mb-1" style="color: var(--text-secondary);">Address:</p>
                    <p class="font-medium" style="color: var(--text-primary);">{{ $payment->property->address }}</p>
                </div>
                @if($payment->property->digital_address)
                <div>
                    <p class="text-sm mb-1" style="color: var(--text-secondary);">Digital Address:</p>
                    <p class="font-medium" style="color: var(--text-primary);">{{ $payment->property->digital_address }}</p>
                </div>
                @endif
            </div>
        </div>
        
        <!-- Invoices Section - Show for invoice and bulk payments -->
        @if(isset($payment->metadata['payment_type']) && in_array($payment->metadata['payment_type'], ['invoices', 'bulk']))
        <div class="card p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                @if($payment->status === 'completed')
                    @if($payment->metadata['payment_type'] === 'invoices')
                        Invoices Paid
                    @else
                        Invoices Generated
                    @endif
                @elseif(in_array($payment->status, ['pending', 'processing']))
                    @if($payment->metadata['payment_type'] === 'invoices')
                        Selected Invoices
                    @else
                        Selected Months for Invoices
                    @endif
                @else
                    @if($payment->metadata['payment_type'] === 'invoices')
                        Invoices
                    @else
                        Months for Bulk Payment
                    @endif
                @endif
            </h2>
            
            @if($payment->metadata['payment_type'] === 'invoices')
                <!-- Display individual invoices for invoice payments -->
                @if(isset($payment->metadata['invoices']) && !empty($payment->metadata['invoices']))
                    @php
                        // Get invoice details from metadata or database
                        $invoiceIds = $payment->metadata['invoices'];
                        $invoices = App\Models\Invoice::whereIn('id', $invoiceIds)->get();
                    @endphp
                    
                    @if($invoices->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Invoice #</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Period</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Amount</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoices as $invoice)
                                <tr class="border-b" style="border-color: var(--border-color);">
                                    <td class="p-3">
                                        <p class="font-medium" style="color: var(--text-primary);">
                                            INV-{{ str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}
                                        </p>
                                    </td>
                                    <td class="p-3">
                                        <p class="font-medium" style="color: var(--text-primary);">
                                            {{ \Carbon\Carbon::parse($invoice->period . '-01')->format('F Y') }}
                                        </p>
                                    </td>
                                    <td class="p-3">
                                        <p class="font-medium" style="color: var(--success);">
                                            {{ $settings->formatAmount($invoice->total_amount) }}
                                        </p>
                                    </td>
                                    <td class="p-3">
                                        @if($payment->status === 'completed')
                                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                                                <i class="fas fa-check mr-1"></i>Paid
                                            </span>
                                        @elseif(in_array($payment->status, ['pending', 'processing']))
                                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                                                <i class="fas fa-clock mr-1"></i>Pending Payment
                                            </span>
                                        @else
                                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--danger-rgb), 0.2); color: var(--danger);">
                                                <i class="fas fa-times mr-1"></i>Not Paid
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2" class="p-3 text-right font-medium" style="color: var(--text-primary);">Total:</td>
                                    <td class="p-3 font-medium" style="color: var(--success);">
                                        {{ $settings->formatAmount($invoices->sum('total_amount')) }}
                                    </td>
                                    <td class="p-3"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    @endif
                @else
                <div class="text-center py-8">
                    <i class="fas fa-file-invoice text-4xl mb-4" style="color: var(--text-secondary);"></i>
                    <p class="text-lg font-medium mb-2" style="color: var(--text-primary);">No Invoices Found</p>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        @if(in_array($payment->status, ['pending', 'processing']))
                            Invoice details will be available once processing is complete.
                        @else
                            No invoices are associated with this payment.
                        @endif
                    </p>
                </div>
                @endif
            @elseif($payment->metadata['payment_type'] === 'bulk')
                <!-- Display months for bulk payments -->
                @if(isset($payment->metadata['selected_months']))
                    @php
                        $months = explode(',', $payment->metadata['selected_months']);
                        $monthlyAmount = $settings->calculateDues($payment->property) ?? 0;
                        $totalAmount = $monthlyAmount * count($months);
                    @endphp
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Month</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Amount</th>
                                    <th class="text-left p-3 font-medium" style="color: var(--text-secondary);">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($months as $month)
                                <tr class="border-b" style="border-color: var(--border-color);">
                                    <td class="p-3">
                                        <p class="font-medium" style="color: var(--text-primary);">
                                            @php
                                                [$year, $monthNum] = explode('-', $month);
                                                echo \Carbon\Carbon::create($year, $monthNum, 1)->format('F Y');
                                            @endphp
                                        </p>
                                    </td>
                                    <td class="p-3">
                                        <p class="font-medium" style="color: var(--success);">
                                            {{ $settings->formatAmount($monthlyAmount) }}
                                        </p>
                                    </td>
                                    <td class="p-3">
                                        @if($payment->status === 'completed')
                                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--success-rgb), 0.2); color: var(--success);">
                                                <i class="fas fa-check mr-1"></i>Paid
                                            </span>
                                        @elseif(in_array($payment->status, ['pending', 'processing']))
                                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--warning-rgb), 0.2); color: var(--warning);">
                                                <i class="fas fa-clock mr-1"></i>Pending
                                            </span>
                                        @else
                                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--danger-rgb), 0.2); color: var(--danger);">
                                                <i class="fas fa-times mr-1"></i>Not Generated
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td class="p-3 text-right font-medium" style="color: var(--text-primary);">Total:</td>
                                    <td class="p-3 font-medium" style="color: var(--success);">
                                        {{ $settings->formatAmount($totalAmount) }}
                                    </td>
                                    <td class="p-3"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <!-- Bulk Payment Note -->
                    @if(in_array($payment->status, ['pending', 'processing']))
                    <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid var(--info);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mt-1 mr-3" style="color: var(--info);"></i>
                            <div>
                                <p class="text-sm font-medium mb-1" style="color: var(--info);">Bulk Payment Information</p>
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    Invoices for the selected months above will be generated and marked as <strong>Paid</strong> 
                                    once the payment is successfully completed. Each month will have its own invoice record.
                                </p>
                            </div>
                        </div>
                    </div>
                    @endif
                @else
                <div class="text-center py-8">
                    <i class="fas fa-calendar-alt text-4xl mb-4" style="color: var(--text-secondary);"></i>
                    <p class="text-lg font-medium mb-2" style="color: var(--text-primary);">No Months Selected</p>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        No months were selected for this bulk payment.
                    </p>
                </div>
                @endif
            @endif
            
            <!-- Payment Status Note -->
            @if(in_array($payment->status, ['pending', 'processing']))
            <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid var(--info);">
                <div class="flex items-start">
                    <i class="fas fa-info-circle mt-1 mr-3" style="color: var(--info);"></i>
                    <div>
                        <p class="text-sm font-medium mb-1" style="color: var(--info);">Payment Status</p>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            @if(isset($payment->metadata['payment_type']) && $payment->metadata['payment_type'] === 'invoices')
                                The selected invoices will be marked as <strong>Paid</strong> once the payment is successfully completed. 
                                If the payment fails or is cancelled, the invoices will remain in their current status.
                            @elseif(isset($payment->metadata['payment_type']) && $payment->metadata['payment_type'] === 'bulk')
                                Invoices for the selected months will be generated and marked as <strong>Paid</strong> once the payment is successfully completed.
                                If the payment fails or is cancelled, no invoices will be generated.
                            @else
                                Payment will be completed once processed by the payment provider.
                            @endif
                        </p>
                    </div>
                </div>
            </div>
            @endif
        </div>
        @endif
        
        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row justify-center space-y-3 sm:space-y-0 sm:space-x-4">
            @if(in_array($payment->status, ['pending', 'processing']))
            <button onclick="checkPaymentStatus()" class="flex items-center justify-center px-4 py-2 rounded" 
                    style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid var(--info);" 
                    id="status-check-btn">
                <i class="fas fa-sync-alt mr-2"></i> Check Status Now
            </button>
            @endif
            
            @if($payment->status === 'pending' && !in_array($payment->payment_provider, ['paystack', 'flutterwave']))
            <form action="{{ route('landlord.payments.cancel', $payment->transaction_id) }}" method="POST" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="flex items-center justify-center px-4 py-2 rounded" 
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid var(--danger);" 
                        onclick="return confirm('Are you sure you want to cancel this payment?')">
                    <i class="fas fa-times mr-2"></i> Cancel Payment
                </button>
            </form>
            @endif
            
            @if($payment->status === 'failed')
            <a href="{{ route('landlord.payments.create', $payment->property_id) }}" 
               class="flex items-center justify-center px-4 py-2 rounded" 
               style="background-color: var(--primary); color: white; border: 1px solid var(--primary);">
                <i class="fas fa-redo-alt mr-2"></i> Retry Payment
            </a>
            @endif
            
            <a href="{{ route('landlord.payments.history') }}" class="flex items-center justify-center px-4 py-2 rounded" 
               style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary); border: 1px solid var(--border-color);">
                <i class="fas fa-history mr-2"></i> Payment History
            </a>
            
            <a href="{{ route('landlord.invoices') }}" class="flex items-center justify-center px-4 py-2 rounded" 
               style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid var(--primary);">
                <i class="fas fa-file-invoice mr-2"></i> My Invoices
            </a>
        </div>
        
        <!-- Print Button -->
        @if($payment->status === 'completed')
        <div class="mt-6">
            <button onclick="window.print()" class="flex items-center justify-center mx-auto px-4 py-2 rounded" 
                    style="border: 1px solid var(--primary); color: var(--primary);">
                <i class="fas fa-print mr-2"></i> Print Receipt
            </button>
        </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
// Auto-refresh for pending/processing payments
let statusCheckInterval;

function startStatusPolling() {
    @if(in_array($payment->status, ['pending', 'processing']))
        statusCheckInterval = setInterval(checkPaymentStatus, 10000); // Check every 10 seconds
    @endif
}

function checkPaymentStatus() {
    const button = document.getElementById('status-check-btn');
    if (!button) return;
    
    const originalHtml = button.innerHTML;
    
    // Show loading state
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Checking...';
    button.disabled = true;
    
    fetch(`/landlord/payments/{{ $payment->transaction_id }}/status`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.status !== '{{ $payment->status }}') {
                    // Status changed, reload the page
                    window.location.reload();
                } else {
                    // Status unchanged, show message
                    showNotification('Payment status is still ' + data.status, 'info');
                }
            } else {
                showNotification('Failed to check payment status', 'error');
            }
        })
        .catch(error => {
            console.error('Error checking payment status:', error);
            showNotification('Error checking payment status', 'error');
        })
        .finally(() => {
            // Restore button state
            button.innerHTML = originalHtml;
            button.disabled = false;
        });
}

function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = 'fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 animate-fade-in';
    notification.style.backgroundColor = type === 'error' ? 'rgba(var(--danger-rgb), 0.9)' : 
                                       type === 'success' ? 'rgba(var(--success-rgb), 0.9)' : 
                                       'rgba(var(--info-rgb), 0.9)';
    notification.style.color = 'white';
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : type === 'success' ? 'check-circle' : 'info-circle'} mr-2"></i>
            <span>${message}</span>
            <button class="ml-4 text-white hover:text-gray-200" onclick="this.parentElement.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Remove notification after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 5000);
}

// Start polling when page loads
document.addEventListener('DOMContentLoaded', function() {
    startStatusPolling();
    
    // Clean up interval when leaving page
    window.addEventListener('beforeunload', function() {
        if (statusCheckInterval) {
            clearInterval(statusCheckInterval);
        }
    });
});

// Keyboard shortcut: Escape to close modal if open
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        // Close any open modals
        const modals = document.querySelectorAll('.fixed.inset-0:not(.hidden)');
        modals.forEach(modal => {
            modal.classList.add('hidden');
        });
    }
});
</script>

<style>
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

@media print {
    .flex, .btn, button, a {
        display: none !important;
    }
    .card {
        border: 1px solid #e5e7eb !important;
        box-shadow: none !important;
        break-inside: avoid;
    }
    .no-print {
        display: none !important;
    }
}
</style>
@endsection