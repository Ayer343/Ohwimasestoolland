{{-- resources/views/developer/billing/create-payment-request.blade.php --}}
@extends('layouts.' . (auth()->user()->type === \App\Models\User::TYPE_FIELD_AGENT ? 'field' : 'dev'))

@php
    $pageTitle = 'Create Payment Request';

    if (!function_exists('formatCurrency')) {
        function formatCurrency($amount, $currency = 'GHS') {
            if (empty($amount)) return 'GH₵0.00';
            if ($currency === 'GHS') {
                return 'GH₵' . number_format((float)$amount, 2);
            }
            return $currency . ' ' . number_format((float)$amount, 2);
        }
    }

    $superAdmins = $superAdmins ?? collect();

    $isDarkMode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] === 'true' : false;
@endphp

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- Header Card --}}
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-3">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-plus-circle text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-plus-circle mr-2" style="color: var(--success);"></i>
                        Create Payment Request
                    </h2>
                    <div class="text-sm flex items-center mt-1 flex-wrap gap-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle"></i>
                        <span>Request payment from a Super Admin for services rendered</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('developer.billing.super-admin-payments') }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-medium inline-flex items-center transition-all duration-200 hover:transform hover:-translate-y-0.5"
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Payments
                </a>
                <a href="{{ route('developer.billing.dashboard') }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-medium inline-flex items-center transition-all duration-200 hover:transform hover:-translate-y-0.5"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                </a>
            </div>
        </div>
    </div>

    {{-- Flash Messages --}}
    @foreach(['success' => 'check-circle', 'error' => 'exclamation-circle', 'warning' => 'exclamation-triangle', 'info' => 'info-circle'] as $type => $icon)
        @if(session($type))
        <div class="card">
            <div class="flex items-center p-4"
                 style="background-color: rgba(var(--{{ $type === 'error' ? 'danger' : $type }}-rgb), 0.1);
                        border: 1px solid rgba(var(--{{ $type === 'error' ? 'danger' : $type }}-rgb), 0.3);
                        border-radius: 12px;">
                <div class="flex-shrink-0">
                    <i class="fas fa-{{ $icon }} text-xl" style="color: var(--{{ $type === 'error' ? 'danger' : $type }});"></i>
                </div>
                <div class="ml-3 flex-1">
                    <p style="color: var(--{{ $type === 'error' ? 'danger' : $type }}); font-weight: 500;">{{ session($type) }}</p>
                </div>
                <button type="button" onclick="this.closest('.card').remove()" class="ml-auto">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
        </div>
        @endif
    @endforeach

    {{-- Validation Errors --}}
    @if($errors->any())
    <div class="card">
        <div class="p-4"
             style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3); border-radius: 12px;">
            <div class="flex items-start">
                <i class="fas fa-exclamation-circle text-xl mr-3" style="color: var(--danger);"></i>
                <div class="flex-1">
                    <p class="font-bold mb-2" style="color: var(--danger);">Please fix the following errors:</p>
                    <ul class="list-disc list-inside text-sm space-y-1" style="color: var(--danger);">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Empty state — no super admins available --}}
    @if($superAdmins->isEmpty())
        <div class="card p-12 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full mb-4"
                 style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                <i class="fas fa-user-slash text-3xl"></i>
            </div>
            <h3 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No Active Super Admins</h3>
            <p class="mb-6 max-w-md mx-auto text-sm" style="color: var(--text-secondary);">
                There are no active Super Admins in the system. You need at least one active Super Admin before you can create a payment request.
            </p>
            <a href="{{ route('developer.billing.super-admin-payments') }}"
               class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
               style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                <i class="fas fa-arrow-left mr-2"></i> Back to Payments
            </a>
        </div>
    @else

    {{-- Form Card --}}
    <form method="POST"
          action="{{ route('developer.billing.create-super-admin-request') }}"
          id="createPaymentRequestForm"
          class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        @csrf

        {{-- Left: Form Fields --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Recipient --}}
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-user-shield mr-2" style="color: var(--primary);"></i> Recipient
                </h3>

                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        Super Admin <span style="color: var(--danger);">*</span>
                    </label>
                    <select name="super_admin_id" required
                            class="form-select w-full p-2.5 rounded-lg border"
                            style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                        <option value="">— Select a Super Admin —</option>
                        @foreach($superAdmins as $admin)
                            <option value="{{ $admin->id }}"
                                    data-email="{{ $admin->email }}"
                                    {{ old('super_admin_id') == $admin->id ? 'selected' : '' }}>
                                {{ $admin->name }} &lt;{{ $admin->email }}&gt;
                            </option>
                        @endforeach
                    </select>
                    @error('super_admin_id')
                        <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Payment Details --}}
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-money-check-alt mr-2" style="color: var(--success);"></i> Payment Details
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-1">
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            Amount <span style="color: var(--danger);">*</span>
                        </label>
                        <input type="number"
                               name="amount"
                               value="{{ old('amount') }}"
                               min="0.01"
                               step="0.01"
                               required
                               placeholder="0.00"
                               class="form-input w-full p-2.5 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                        @error('amount')
                            <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-1">
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            Currency
                        </label>
                        <select name="currency"
                                class="form-select w-full p-2.5 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="GHS" {{ old('currency', 'GHS') == 'GHS' ? 'selected' : '' }}>GHS — Ghanaian Cedi</option>
                            <option value="USD" {{ old('currency') == 'USD' ? 'selected' : '' }}>USD — US Dollar</option>
                            <option value="EUR" {{ old('currency') == 'EUR' ? 'selected' : '' }}>EUR — Euro</option>
                            <option value="GBP" {{ old('currency') == 'GBP' ? 'selected' : '' }}>GBP — British Pound</option>
                        </select>
                    </div>

                    <div class="md:col-span-1">
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            Due Date <span style="color: var(--danger);">*</span>
                        </label>
                        <input type="date"
                               name="due_date"
                               value="{{ old('due_date', now()->addDays(30)->format('Y-m-d')) }}"
                               min="{{ now()->addDay()->format('Y-m-d') }}"
                               required
                               class="form-input w-full p-2.5 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                        @error('due_date')
                            <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                        @enderror
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Must be a future date.
                        </p>
                    </div>

                    <div class="md:col-span-1">
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            Payment Method
                        </label>
                        <select name="payment_method"
                                class="form-select w-full p-2.5 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">— Any / Not specified —</option>
                            <option value="bank_transfer" {{ old('payment_method') == 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                            <option value="mobile_money"  {{ old('payment_method') == 'mobile_money'  ? 'selected' : '' }}>Mobile Money</option>
                            <option value="cash"          {{ old('payment_method') == 'cash'          ? 'selected' : '' }}>Cash</option>
                            <option value="check"         {{ old('payment_method') == 'check'         ? 'selected' : '' }}>Check</option>
                        </select>
                        @error('payment_method')
                            <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            Description <span style="color: var(--danger);">*</span>
                        </label>
                        <textarea name="description"
                                  rows="4"
                                  maxlength="500"
                                  required
                                  placeholder="Describe the service or payment purpose..."
                                  class="form-input w-full p-3 rounded-lg border"
                                  style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                        @enderror
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">Max 500 characters.</p>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="card p-6">
                <div class="flex justify-end flex-wrap gap-3">
                    <a href="{{ route('developer.billing.super-admin-payments') }}"
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-times mr-2"></i> Cancel
                    </a>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                        <i class="fas fa-paper-plane mr-2"></i> Create Payment Request
                    </button>
                </div>
            </div>
        </div>

        {{-- Right: Info Panel --}}
        <div class="lg:col-span-1">
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i> How it works
                </h3>
                <ul class="space-y-3 text-sm" style="color: var(--text-secondary);">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                        <span>Select the Super Admin who should pay the request.</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                        <span>Enter the amount and a description of what's being billed.</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                        <span>Set a due date. The Super Admin will be notified.</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                        <span>Track it under <a href="{{ route('developer.billing.super-admin-payments') }}" style="color: var(--primary);">Super Admin Payments</a>.</span>
                    </li>
                </ul>
            </div>

            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-lightbulb mr-2" style="color: var(--warning);"></i> Tips
                </h3>
                <ul class="space-y-3 text-sm" style="color: var(--text-secondary);">
                    <li class="flex items-start">
                        <i class="fas fa-arrow-right mr-2 mt-0.5" style="color: var(--primary); font-size: 0.7rem;"></i>
                        <span>Give a clear description — it appears on the super admin's dashboard.</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-arrow-right mr-2 mt-0.5" style="color: var(--primary); font-size: 0.7rem;"></i>
                        <span>Set a realistic due date — overdue requests are highlighted.</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-arrow-right mr-2 mt-0.5" style="color: var(--primary); font-size: 0.7rem;"></i>
                        <span>You can send reminders from the payments page.</span>
                    </li>
                </ul>
            </div>
        </div>
    </form>

    @endif
</div>

{{-- Toast Container --}}
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('createPaymentRequestForm');

    if (form) {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Creating...';
            }
        });
    }

    // Set a sensible default due date if empty
    const dueDate = document.querySelector('input[name="due_date"]');
    if (dueDate && !dueDate.value) {
        const d = new Date();
        d.setDate(d.getDate() + 30);
        dueDate.value = d.toISOString().split('T')[0];
    }

    @if(session('success'))
        showToast("{{ session('success') }}", 'success');
    @endif
    @if(session('error'))
        showToast("{{ session('error') }}", 'error');
    @endif
});

function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(container);
    }

    const colors = {
        success: { bg: 'bg-green-100',  text: 'text-green-800',  icon: 'fa-check-circle' },
        error:   { bg: 'bg-red-100',    text: 'text-red-800',    icon: 'fa-exclamation-circle' },
        warning: { bg: 'bg-yellow-100', text: 'text-yellow-800', icon: 'fa-exclamation-triangle' },
        info:    { bg: 'bg-blue-100',   text: 'text-blue-800',   icon: 'fa-info-circle' },
    };
    const cfg = colors[type] || colors.info;

    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full ${cfg.bg} ${cfg.text}`;

    const messageEl = document.createElement('span');
    messageEl.className = 'text-sm font-medium flex-1';
    messageEl.innerHTML = `<i class="fas ${cfg.icon} mr-2"></i>${message}`;

    const closeBtn = document.createElement('button');
    closeBtn.className = 'ml-4 hover:opacity-70';
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.onclick = () => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    };

    toast.appendChild(messageEl);
    toast.appendChild(closeBtn);
    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 10);

    setTimeout(() => {
        if (toast.parentNode === container) {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}
</script>
@endsection

@section('styles')
<style>
.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06) !important;
}

.form-input,
.form-select {
    background-color: var(--card-bg) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 8px !important;
    transition: all 0.2s ease !important;
}

.form-input:focus,
.form-select:focus {
    outline: none !important;
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1) !important;
}

.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: 1px solid var(--primary) !important;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
    transform: translateY(-1px);
}

button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .grid.grid-cols-1.lg\:grid-cols-3 { grid-template-columns: 1fr; }
    .card .p-6 { padding: 1rem !important; }
}
</style>
@endsection