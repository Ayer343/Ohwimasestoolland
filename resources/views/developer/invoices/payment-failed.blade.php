{{-- resources/views/developer/invoices/payment-failed.blade.php --}}
@extends('layouts.dev')

@section('title', 'Payment Failed')

@section('content')
<div class="max-w-md mx-auto py-12 text-center">
    <div class="card p-8">
        <div class="mb-6">
            <div class="w-20 h-20 rounded-full bg-red-100 flex items-center justify-center mx-auto">
                <i class="fas fa-times-circle text-5xl text-red-500"></i>
            </div>
        </div>
        
        <h2 class="text-2xl font-bold mb-2" style="color: var(--text-primary);">
            Payment Failed ❌
        </h2>
        <p class="mb-4" style="color: var(--text-secondary);">
            {{ $message ?? 'Your payment could not be processed. Please try again.' }}
        </p>
        
        <div class="flex flex-col gap-3">
            <a href="{{ route('developer.payments.pay-invoice', $invoice->id ?? 0) }}" 
               class="px-6 py-3 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-1"
               style="background-color: var(--warning); color: white;">
                <i class="fas fa-redo mr-2"></i> Try Again
            </a>
            <a href="{{ route('developer.billing.dashboard') }}" 
               class="px-6 py-3 rounded-lg border transition-all duration-200 hover:bg-gray-50"
               style="border-color: var(--border-color); color: var(--text-secondary);">
                <i class="fas fa-home mr-2"></i> Return to Dashboard
            </a>
        </div>
    </div>
</div>
@endsection