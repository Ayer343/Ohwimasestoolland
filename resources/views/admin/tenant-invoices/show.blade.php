@extends('layouts.app')

@section('title', 'Community Development Invoice Details')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div class="flex items-center mb-4 md:mb-0">
                <i class="fas fa-file-invoice text-2xl mr-3" style="color: var(--primary);"></i>
                <div>
                    <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Invoice #{{ $invoice->invoice_number }}</h2>
                    <p class="text-sm" style="color: var(--text-secondary);">View community development invoice details</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.tenant-invoices.index') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to List
                </a>
                <a href="{{ route('admin.tenant-invoices.print', $invoice->id) }}" class="btn-info flex items-center" target="_blank">
                    <i class="fas fa-print mr-2"></i> Print
                </a>
                @if($invoice->status !== 'paid' && $invoice->status !== 'cancelled')
                <button type="button" onclick="openMarkAsPaidModal()" class="btn-success flex items-center">
                    <i class="fas fa-check mr-2"></i> Mark as Paid
                </button>
                @endif
                @if($invoice->status === 'paid')
                <button type="button" onclick="openSendReceiptModal()" class="btn-primary flex items-center">
                    <i class="fas fa-envelope mr-2"></i> Send Receipt
                </button>
                @endif
                @if($invoice->status === 'overdue')
                <button type="button" onclick="applyPenaltyModal()" class="btn-danger flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i> Apply Penalty
                </button>
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

    <!-- Invoice Status Banner -->
    @if($invoice->status === 'overdue')
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
        <div class="flex items-center">
            <i class="fas fa-exclamation-triangle mr-3 text-xl"></i>
            <div>
                <strong class="font-bold">Overdue Invoice!</strong>
                <p class="text-sm mt-1">This invoice is overdue by {{ $invoice->due_date->diffInDays(now()) }} days.</p>
            </div>
        </div>
    </div>
    @elseif($invoice->status === 'paid')
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-3 text-xl"></i>
            <div>
                <strong class="font-bold">Paid Invoice</strong>
                <p class="text-sm mt-1">This invoice was paid on {{ $invoice->payment_date ? \Carbon\Carbon::parse($invoice->payment_date)->format('M d, Y') : 'N/A' }}.</p>
            </div>
        </div>
    </div>
    @elseif($invoice->status === 'cancelled')
    <div class="bg-gray-100 border border-gray-400 text-gray-700 px-4 py-3 rounded relative" role="alert">
        <div class="flex items-center">
            <i class="fas fa-ban mr-3 text-xl"></i>
            <div>
                <strong class="font-bold">Cancelled Invoice</strong>
                <p class="text-sm mt-1">This invoice has been cancelled.</p>
            </div>
        </div>
    </div>
    @endif

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - Invoice Details -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Invoice Details Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Invoice Details</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <table class="w-full">
                            <tr>
                                <td class="py-2" style="color: var(--text-secondary);">Invoice Number:</td>
                                <td class="py-2 font-medium" style="color: var(--text-primary);">{{ $invoice->invoice_number }}</td>
                            </tr>
                            <tr>
                                <td class="py-2" style="color: var(--text-secondary);">Period:</td>
                                <td class="py-2 font-medium" style="color: var(--text-primary);">
                                    {{ \Carbon\Carbon::parse($invoice->period . '-01')->format('F Y') }}
                                </td>
                            </tr>
                            <tr>
                                <td class="py-2" style="color: var(--text-secondary);">Issue Date:</td>
                                <td class="py-2 font-medium" style="color: var(--text-primary);">{{ $invoice->created_at->format('M d, Y') }}</td>
                            </tr>
                            <tr>
                                <td class="py-2" style="color: var(--text-secondary);">Due Date:</td>
                                <td class="py-2 font-medium" style="color: var(--text-primary);">
                                    {{ $invoice->due_date->format('M d, Y') }}
                                    @if($invoice->isOverdue())
                                        <span class="ml-2 text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--danger-rgb), 0.2); color: var(--danger);">Overdue</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="py-2" style="color: var(--text-secondary);">Status:</td>
                                <td class="py-2">
                                    @php
                                        $statusColors = [
                                            'paid' => 'success',
                                            'pending' => 'warning',
                                            'overdue' => 'danger',
                                            'cancelled' => 'secondary'
                                        ];
                                        $statusColor = $statusColors[$invoice->status] ?? 'secondary';
                                    @endphp
                                    <span class="px-3 py-1 rounded-full text-sm" style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.2); color: var(--{{ $statusColor }});">
                                        <i class="{{ $invoice->status_icon }} mr-1"></i>
                                        {{ ucfirst($invoice->status) }}
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div>
                        <table class="w-full">
                            <tr>
                                <td class="py-2" style="color: var(--text-secondary);">Community Development Dues:</td>
                                <td class="py-2 font-medium text-right" style="color: var(--text-primary);">{{ $system_settings->formatAmount($invoice->community_dues) }}</td>
                            </tr>
                            @if($invoice->additional_charges > 0)
                            <tr>
                                <td class="py-2" style="color: var(--text-secondary);">Additional Charges:</td>
                                <td class="py-2 font-medium text-right" style="color: var(--warning);">+ {{ $system_settings->formatAmount($invoice->additional_charges) }}</td>
                            </tr>
                            @endif
                            @if($invoice->penalty_amount > 0)
                            <tr>
                                <td class="py-2" style="color: var(--text-secondary);">Penalty:</td>
                                <td class="py-2 font-medium text-right" style="color: var(--danger);">+ {{ $system_settings->formatAmount($invoice->penalty_amount) }}</td>
                            </tr>
                            @endif
                            <tr class="border-t" style="border-color: var(--border-color);">
                                <td class="py-2 font-semibold" style="color: var(--text-primary);">Total Amount:</td>
                                <td class="py-2 font-bold text-right" style="color: var(--success); font-size: 1.2rem;">{{ $system_settings->formatAmount($invoice->total_amount) }}</td>
                            </tr>
                            @if($invoice->paid_amount > 0)
                            <tr>
                                <td class="py-2" style="color: var(--text-secondary);">Paid Amount:</td>
                                <td class="py-2 font-medium text-right" style="color: var(--success);">{{ $system_settings->formatAmount($invoice->paid_amount) }}</td>
                            </tr>
                            @endif
                            @if($invoice->balance > 0)
                            <tr>
                                <td class="py-2" style="color: var(--text-secondary);">Balance Due:</td>
                                <td class="py-2 font-medium text-right" style="color: var(--danger);">{{ $system_settings->formatAmount($invoice->balance) }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>

                @if($invoice->description)
                <div class="mt-4 p-4 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Description / Notes:</p>
                    <p style="color: var(--text-primary);">{{ $invoice->description }}</p>
                </div>
                @endif

                @if($invoice->metadata && isset($invoice->metadata['penalty_applied']))
                <div class="mt-4 p-4 rounded" style="background-color: rgba(var(--danger-rgb), 0.05);">
                    <p class="text-sm font-medium mb-1" style="color: var(--danger);">
                        <i class="fas fa-exclamation-circle mr-1"></i> Penalty Applied
                    </p>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        Amount: {{ $system_settings->formatAmount($invoice->metadata['penalty_applied']['amount']) }}<br>
                        Reason: {{ $invoice->metadata['penalty_applied']['reason'] ?? 'N/A' }}<br>
                        Applied by: {{ $invoice->metadata['penalty_applied']['applied_by_name'] ?? 'System' }} on 
                        {{ isset($invoice->metadata['penalty_applied']['applied_at']) ? \Carbon\Carbon::parse($invoice->metadata['penalty_applied']['applied_at'])->format('M d, Y H:i') : 'N/A' }}
                    </p>
                </div>
                @endif
            </div>

            <!-- Payment History Card -->
            @if($invoice->payment_method || $invoice->payment_date)
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Payment Information</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-4 rounded" style="background-color: rgba(var(--success-rgb), 0.1);">
                        <p class="text-sm mb-1" style="color: var(--text-secondary);">Payment Method</p>
                        <p class="font-medium" style="color: var(--success);">
                            <i class="fas fa-credit-card mr-1"></i>
                            {{ ucfirst(str_replace('_', ' ', $invoice->payment_method ?? 'N/A')) }}
                        </p>
                    </div>
                    <div class="p-4 rounded" style="background-color: rgba(var(--info-rgb), 0.1);">
                        <p class="text-sm mb-1" style="color: var(--text-secondary);">Payment Reference</p>
                        <p class="font-medium" style="color: var(--info);">{{ $invoice->payment_reference ?? 'N/A' }}</p>
                    </div>
                    <div class="p-4 rounded" style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <p class="text-sm mb-1" style="color: var(--text-secondary);">Payment Date</p>
                        <p class="font-medium" style="color: var(--primary);">
                            <i class="fas fa-calendar-check mr-1"></i>
                            {{ $invoice->payment_date ? \Carbon\Carbon::parse($invoice->payment_date)->format('M d, Y') : 'N/A' }}
                        </p>
                    </div>
                    <div class="p-4 rounded" style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <p class="text-sm mb-1" style="color: var(--text-secondary);">Paid Amount</p>
                        <p class="font-medium" style="color: var(--warning);">{{ $system_settings->formatAmount($invoice->paid_amount) }}</p>
                    </div>
                </div>

                @if($invoice->metadata && isset($invoice->metadata['payment_recorded']))
                <div class="mt-4 text-xs" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1"></i>
                    Payment recorded by {{ $invoice->metadata['payment_recorded']['recorded_by_name'] ?? 'System' }} on 
                    {{ isset($invoice->metadata['payment_recorded']['recorded_at']) ? \Carbon\Carbon::parse($invoice->metadata['payment_recorded']['recorded_at'])->format('M d, Y H:i') : 'N/A' }}
                </div>
                @endif
            </div>
            @endif
        </div>

        <!-- Right Column - Tenant & Unit Info -->
        <div class="space-y-6">
            <!-- Tenant Information Card -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Tenant Information</h3>
                    <a href="{{ route('admin.tenants.show', $invoice->tenant->id) }}" class="text-sm" style="color: var(--primary);">
                        <i class="fas fa-external-link-alt mr-1"></i> View Profile
                    </a>
                </div>
                
                <div class="space-y-3">
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full bg-primary bg-opacity-20 flex items-center justify-center mr-3">
                            <i class="fas fa-user" style="color: var(--primary);"></i>
                        </div>
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">{{ $invoice->tenant->name }}</p>
                            <p class="text-sm" style="color: var(--text-secondary);">{{ $invoice->tenant->email }}</p>
                        </div>
                    </div>
                    
                    <div class="pt-3 border-t" style="border-color: var(--border-color);">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-phone w-5" style="color: var(--text-secondary);"></i>
                            <span style="color: var(--text-primary);">{{ $invoice->tenant->phone ?? 'N/A' }}</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-id-card w-5" style="color: var(--text-secondary);"></i>
                            <span style="color: var(--text-primary);">{{ $invoice->tenant->id_number ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Property Unit Information Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Property Unit Information</h3>
                
                @if($invoice->propertyUnit)
                <div class="space-y-3">
                    <div>
                        <p class="font-medium" style="color: var(--text-primary);">{{ $invoice->propertyUnit->property->property_name ?? 'N/A' }}</p>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-door-open mr-1"></i> Unit: {{ $invoice->propertyUnit->unit_number }}
                        </p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-2 p-3 rounded" style="background-color: rgba(var(--info-rgb), 0.05);">
                        <div style="color: var(--text-secondary);">Unit Type:</div>
                        <div style="color: var(--text-primary);">{{ ucfirst($invoice->propertyUnit->unit_type) }}</div>
                        
                        <div style="color: var(--text-secondary);">Bedrooms:</div>
                        <div style="color: var(--text-primary);">{{ $invoice->propertyUnit->bedrooms ?? 0 }}</div>
                        
                        <div style="color: var(--text-secondary);">Bathrooms:</div>
                        <div style="color: var(--text-primary);">{{ $invoice->propertyUnit->bathrooms ?? 0 }}</div>
                        
                        <div style="color: var(--text-secondary);">Floor Area:</div>
                        <div style="color: var(--text-primary);">{{ $invoice->propertyUnit->floor_area ?? 0 }} sqm</div>
                    </div>
                    
                    @if($invoice->propertyUnit->amenities && count($invoice->propertyUnit->amenities) > 0)
                    <div class="pt-3">
                        <p class="text-sm font-medium mb-2" style="color: var(--text-secondary);">Amenities:</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($invoice->propertyUnit->amenities as $amenity)
                            <span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                <i class="fas fa-check-circle mr-1"></i> {{ ucfirst($amenity) }}
                            </span>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <div class="pt-3 border-t" style="border-color: var(--border-color);">
                        <div class="flex justify-between mb-2">
                            <span style="color: var(--text-secondary);">Property Address:</span>
                        </div>
                        <p style="color: var(--text-primary);">
                            {{ $invoice->propertyUnit->property->house_number ?? '' }} {{ $invoice->propertyUnit->property->street_name ?? '' }}
                        </p>
                        @if($invoice->propertyUnit->property->zone)
                        <p class="text-sm" style="color: var(--text-secondary);">Zone: {{ $invoice->propertyUnit->property->zone }}</p>
                        @endif
                    </div>

                    @if($invoice->propertyUnit->property->landlord)
                    <div class="pt-3 border-t" style="border-color: var(--border-color);">
                        <p class="font-medium mb-2" style="color: var(--text-primary);">Landlord</p>
                        <p style="color: var(--text-primary);">{{ $invoice->propertyUnit->property->landlord->name }}</p>
                        <p class="text-sm" style="color: var(--text-secondary);">{{ $invoice->propertyUnit->property->landlord->phone }}</p>
                    </div>
                    @endif
                </div>
                @else
                <p class="text-warning">No property unit information available</p>
                @endif
            </div>

            <!-- Quick Actions Card -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Quick Actions</h3>
                
                <div class="space-y-2">
                    <button onclick="downloadInvoice()" class="w-full p-3 rounded text-left flex items-center" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-download mr-3"></i>
                        <div>
                            <p class="font-medium">Download PDF</p>
                            <p class="text-xs opacity-75">Save invoice as PDF</p>
                        </div>
                    </button>
                    
                    @if($invoice->status === 'paid')
                    <button onclick="openSendReceiptModal()" class="w-full p-3 rounded text-left flex items-center" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-envelope mr-3"></i>
                        <div>
                            <p class="font-medium">Email Receipt</p>
                            <p class="text-xs opacity-75">Send payment receipt to tenant</p>
                        </div>
                    </button>
                    @endif
                    
                    @if($invoice->status === 'overdue' && $invoice->penalty_amount == 0)
                    <button onclick="applyPenaltyModal()" class="w-full p-3 rounded text-left flex items-center" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                        <i class="fas fa-exclamation-triangle mr-3"></i>
                        <div>
                            <p class="font-medium">Apply Penalty</p>
                            <p class="text-xs opacity-75">Add late payment penalty</p>
                        </div>
                    </button>
                    @endif
                    
                    <a href="{{ route('admin.tenant-invoices.create', ['tenant_id' => $invoice->tenant->id]) }}" class="w-full p-3 rounded text-left flex items-center" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-plus-circle mr-3"></i>
                        <div>
                            <p class="font-medium">Create New Invoice</p>
                            <p class="text-xs opacity-75">Generate another invoice for this tenant</p>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Audit Trail Card -->
            @if($invoice->creator || $invoice->updater)
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Audit Information</h3>
                
                <div class="space-y-2 text-sm">
                    @if($invoice->creator)
                    <div>
                        <span style="color: var(--text-secondary);">Created by:</span>
                        <span style="color: var(--text-primary);">{{ $invoice->creator->name }}</span>
                        <span class="text-xs" style="color: var(--text-secondary);">({{ $invoice->created_at->format('M d, Y H:i') }})</span>
                    </div>
                    @endif
                    
                    @if($invoice->updater && $invoice->updater_id !== $invoice->creator_id)
                    <div>
                        <span style="color: var(--text-secondary);">Last updated by:</span>
                        <span style="color: var(--text-primary);">{{ $invoice->updater->name }}</span>
                        <span class="text-xs" style="color: var(--text-secondary);">({{ $invoice->updated_at->format('M d, Y H:i') }})</span>
                    </div>
                    @endif
                    
                    @if($invoice->metadata && isset($invoice->metadata['generation_method']))
                    <div>
                        <span style="color: var(--text-secondary);">Generation method:</span>
                        <span style="color: var(--text-primary);">{{ ucfirst(str_replace('_', ' ', $invoice->metadata['generation_method'])) }}</span>
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Mark as Paid Modal -->
<div id="markAsPaidModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Mark Invoice as Paid</h3>
            <form id="markAsPaidForm" method="POST" action="{{ route('admin.tenant-invoices.mark-paid', $invoice->id) }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Payment Method</label>
                        <select name="payment_method" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" required>
                            <option value="">Select Payment Method</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="check">Check</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Payment Reference</label>
                        <input type="text" name="payment_reference" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                               placeholder="Transaction ID or reference">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Payment Date</label>
                        <input type="date" name="payment_date" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                               value="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Amount Paid</label>
                        <input type="number" name="amount_paid" id="modal_amount_paid" step="0.01" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                               value="{{ $invoice->balance }}" max="{{ $invoice->balance }}" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Notes</label>
                        <textarea name="notes" rows="2" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                                  placeholder="Optional notes about payment"></textarea>
                    </div>
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="send_receipt" value="1" class="mr-2" checked>
                            <span class="text-sm" style="color: var(--text-secondary);">Send receipt to tenant</span>
                        </label>
                    </div>
                </div>
                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" onclick="closeMarkAsPaidModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded text-white" style="background-color: var(--success);">
                        Confirm Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Send Receipt Modal -->
<div id="sendReceiptModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Send Payment Receipt</h3>
            <form id="sendReceiptForm" method="POST" action="{{ route('admin.tenant-invoices.send-receipt', $invoice->id) }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Recipient Email</label>
                        <input type="email" name="email" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                               value="{{ $invoice->tenant->email }}" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Additional Message (Optional)</label>
                        <textarea name="message" rows="3" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                                  placeholder="Enter any additional message for the tenant"></textarea>
                    </div>
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="attach_pdf" value="1" class="mr-2" checked>
                            <span class="text-sm" style="color: var(--text-secondary);">Attach PDF receipt</span>
                        </label>
                    </div>
                </div>
                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" onclick="closeSendReceiptModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded text-white" style="background-color: var(--primary);">
                        Send Receipt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Apply Penalty Modal -->
<div id="applyPenaltyModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Apply Late Payment Penalty</h3>
            <form id="applyPenaltyForm" method="POST" action="{{ route('admin.tenant-invoices.apply-penalty', $invoice->id) }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Penalty Amount ({{ $system_settings->currency_symbol ?? '₵' }})</label>
                        <input type="number" name="penalty_amount" step="0.01" min="0.01" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                               placeholder="Enter penalty amount" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Reason for Penalty</label>
                        <textarea name="reason" rows="2" class="w-full p-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                                  placeholder="Enter reason for penalty" required></textarea>
                    </div>
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="send_notification" value="1" class="mr-2" checked>
                            <span class="text-sm" style="color: var(--text-secondary);">Notify tenant about penalty</span>
                        </label>
                    </div>
                </div>
                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" onclick="closePenaltyModal()" class="px-4 py-2 border rounded" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded text-white" style="background-color: var(--danger);">
                        Apply Penalty
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide success and error messages after 5 seconds
    const successMessage = document.querySelector('.bg-green-100');
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.transition = 'opacity 0.5s';
            successMessage.style.opacity = '0';
            setTimeout(() => {
                successMessage.style.display = 'none';
            }, 500);
        }, 5000);
    }
    
    const errorMessage = document.querySelector('.bg-red-100');
    if (errorMessage) {
        setTimeout(() => {
            errorMessage.style.transition = 'opacity 0.5s';
            errorMessage.style.opacity = '0';
            setTimeout(() => {
                errorMessage.style.display = 'none';
            }, 500);
        }, 5000);
    }
});

// Mark as Paid Modal
function openMarkAsPaidModal() {
    document.getElementById('markAsPaidModal').classList.remove('hidden');
}

function closeMarkAsPaidModal() {
    document.getElementById('markAsPaidModal').classList.add('hidden');
    document.getElementById('markAsPaidForm').reset();
}

// Send Receipt Modal
function openSendReceiptModal() {
    document.getElementById('sendReceiptModal').classList.remove('hidden');
}

function closeSendReceiptModal() {
    document.getElementById('sendReceiptModal').classList.add('hidden');
    document.getElementById('sendReceiptForm').reset();
}

// Apply Penalty Modal
function applyPenaltyModal() {
    document.getElementById('applyPenaltyModal').classList.remove('hidden');
}

function closePenaltyModal() {
    document.getElementById('applyPenaltyModal').classList.add('hidden');
    document.getElementById('applyPenaltyForm').reset();
}

// Download PDF
function downloadInvoice() {
    window.open('{{ route("admin.tenant-invoices.print", $invoice->id) }}', '_blank');
}

// Validate amount paid doesn't exceed balance
document.getElementById('modal_amount_paid')?.addEventListener('input', function() {
    const max = parseFloat(this.max);
    const value = parseFloat(this.value);
    
    if (value > max) {
        this.value = max;
    }
});

// Close modals when clicking outside
const modals = ['markAsPaidModal', 'sendReceiptModal', 'applyPenaltyModal'];
modals.forEach(modalId => {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                if (modalId === 'markAsPaidModal') closeMarkAsPaidModal();
                if (modalId === 'sendReceiptModal') closeSendReceiptModal();
                if (modalId === 'applyPenaltyModal') closePenaltyModal();
            }
        });
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modalIds = [
            'markAsPaidModal',
            'sendReceiptModal',
            'applyPenaltyModal'
        ];
        
        modalIds.forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (modal && !modal.classList.contains('hidden')) {
                if (modalId === 'markAsPaidModal') closeMarkAsPaidModal();
                if (modalId === 'sendReceiptModal') closeSendReceiptModal();
                if (modalId === 'applyPenaltyModal') closePenaltyModal();
            }
        });
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
    cursor: pointer;
}

.btn-primary:hover {
    opacity: 0.9;
    transform: translateY(-2px);
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
    cursor: pointer;
}

.btn-secondary:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.btn-success {
    background-color: var(--success);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    border: none;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
    cursor: pointer;
}

.btn-success:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.btn-info {
    background-color: var(--info);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    border: none;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
    cursor: pointer;
}

.btn-info:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.btn-danger {
    background-color: var(--danger);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    border: none;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
    cursor: pointer;
}

.btn-danger:hover {
    opacity: 0.9;
    transform: translateY(-2px);
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

.bg-yellow-100 {
    background-color: rgba(254, 249, 195, 0.9);
    border-color: rgba(234, 179, 8, 0.3);
}

.bg-gray-100 {
    background-color: rgba(243, 244, 246, 0.9);
    border-color: rgba(156, 163, 175, 0.3);
}

/* Modal styles */
#markAsPaidModal,
#sendReceiptModal,
#applyPenaltyModal {
    transition: opacity 0.3s ease;
}

#markAsPaidModal.hidden,
#sendReceiptModal.hidden,
#applyPenaltyModal.hidden {
    opacity: 0;
    pointer-events: none;
}

#markAsPaidModal:not(.hidden),
#sendReceiptModal:not(.hidden),
#applyPenaltyModal:not(.hidden) {
    opacity: 1;
    pointer-events: auto;
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

.dark .bg-yellow-100 {
    background-color: rgba(234, 179, 8, 0.2) !important;
    border-color: rgba(234, 179, 8, 0.3) !important;
    color: #eab308 !important;
}

.dark .bg-gray-100 {
    background-color: rgba(75, 85, 99, 0.2) !important;
    border-color: rgba(75, 85, 99, 0.3) !important;
    color: #9ca3af !important;
}

/* Table styles */
table {
    border-collapse: separate;
    border-spacing: 0;
}

/* Quick actions hover effect */
.w-full.p-3.rounded {
    transition: all 0.2s;
}

.w-full.p-3.rounded:hover {
    transform: translateX(5px);
}
</style>
@endsection