{{-- resources/views/developer/invoices/payment-success.blade.php --}}
@extends('layouts.dev')

@section('title', 'Payment Successful')

@section('content')
<div class="max-w-md mx-auto py-12 text-center">
    <div class="card p-8">
        <div class="mb-6">
            <div class="w-20 h-20 rounded-full bg-green-100 flex items-center justify-center mx-auto">
                <i class="fas fa-check-circle text-5xl text-green-500"></i>
            </div>
        </div>
        
        <h2 class="text-2xl font-bold mb-2" style="color: var(--text-primary);">
            Payment Successful! 🎉
        </h2>
        <p class="mb-4" style="color: var(--text-secondary);">
            Your payment has been confirmed successfully.
        </p>
        
        <div class="p-4 rounded-lg mb-6" style="background-color: var(--bg-secondary);">
            <div class="flex justify-between py-2 border-b" style="border-color: var(--border-color);">
                <span style="color: var(--text-secondary);">Invoice</span>
                <span class="font-medium" style="color: var(--text-primary);">#{{ $invoice->invoice_number ?? 'N/A' }}</span>
            </div>
            <div class="flex justify-between py-2">
                <span style="color: var(--text-secondary);">Amount Paid</span>
                <span class="font-bold" style="color: var(--success);">
                    {{ $invoice->currency ?? 'GHS' }} {{ number_format($invoice->amount ?? 0, 2) }}
                </span>
            </div>
        </div>
        
        <div class="flex flex-col gap-3">
            <a href="{{ route('developer.billing.dashboard') }}" 
               class="px-6 py-3 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-1"
               style="background-color: var(--primary); color: white;">
                <i class="fas fa-home mr-2"></i> Return to Dashboard
            </a>
            <a href="{{ route('developer.billing.history') }}" 
               class="px-6 py-3 rounded-lg border transition-all duration-200 hover:bg-gray-50"
               style="border-color: var(--border-color); color: var(--text-secondary);">
                <i class="fas fa-history mr-2"></i> View Payment History
            </a>
        </div>
    </div>
</div>
@endsection