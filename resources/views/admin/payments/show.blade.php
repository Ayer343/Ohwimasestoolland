{{-- resources/views/admin/payments/show.blade.php --}}
@extends('layouts.app')

@section('title', 'Payment Details - ' . $payment->transaction_id)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Payment Details</h2>
            <div class="flex space-x-2">
                <a href="{{ route('admin.payments.index') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Payments
                </a>
                @if(in_array($payment->status, ['pending', 'processing']) && (auth()->user()->isSuperAdmin() || auth()->user()->isAdmin()))
                <button type="button"
                        class="btn-danger flex items-center cancel-payment-btn"
                        data-update-url="{{ route('admin.payments.updateStatus', $payment->id) }}">
                    <i class="fas fa-times mr-2"></i> Cancel Payment
                </button>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            {{-- ...payment info, provider details, metadata, invoices — unchanged... --}}
        </div>

        <div class="lg:col-span-1">
            {{-- ...landlord card, property card, timeline — unchanged... --}}

            @if(auth()->user()->isSuperAdmin() || auth()->user()->isAdmin())
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-cog mr-2"></i> Admin Actions
                </h3>

                {{-- Update Status — plain PUT form --}}
                <form action="{{ route('admin.payments.updateStatus', $payment->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">Update Status</label>
                        <select class="w-full p-2 border rounded"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                name="status" required>
                            <option value="pending"    {{ $payment->status == 'pending'    ? 'selected' : '' }}>Pending</option>
                            <option value="processing" {{ $payment->status == 'processing' ? 'selected' : '' }}>Processing</option>
                            <option value="completed"  {{ $payment->status == 'completed'  ? 'selected' : '' }}>Completed</option>
                            <option value="failed"     {{ $payment->status == 'failed'     ? 'selected' : '' }}>Failed</option>
                            <option value="refunded"   {{ $payment->status == 'refunded'   ? 'selected' : '' }}>Refunded</option>
                            <option value="cancelled"  {{ $payment->status == 'cancelled'  ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary w-full">Update Status</button>
                </form>

                {{-- Cancel — submit hidden status=cancelled to the same route --}}
                @if(in_array($payment->status, ['pending', 'processing']))
                <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                    <button type="button"
                            class="btn-danger w-full cancel-payment-btn"
                            data-update-url="{{ route('admin.payments.updateStatus', $payment->id) }}">
                        <i class="fas fa-times mr-2"></i> Cancel Payment
                    </button>
                </div>
                @endif

                {{-- Mark as Refunded — submit hidden status=refunded --}}
                @if($payment->status === 'completed')
                <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                    <button type="button"
                            class="btn-warning w-full refund-payment-btn"
                            data-update-url="{{ route('admin.payments.updateStatus', $payment->id) }}">
                        <i class="fas fa-undo mr-2"></i> Mark as Refunded
                    </button>
                </div>
                @endif

                {{-- Force Complete — submit hidden status=completed --}}
                @if($payment->invoices && $payment->invoices->count() > 0 && $payment->status !== 'completed')
                <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                    <form action="{{ route('admin.payments.updateStatus', $payment->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="status" value="completed">
                        <button type="submit" class="btn-success w-full"
                                onclick="return confirm('Manually mark this payment as completed? This will update related invoices as paid.')">
                            <i class="fas fa-check-circle mr-2"></i> Force Complete Payment
                        </button>
                    </form>
                </div>
                @endif
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Submit a real form so the controller's redirect()->back() works and
// the flash message ("Payment status updated successfully!") is shown.
function submitStatusForm(url, status, confirmMessage) {
    if (confirmMessage && !confirm(confirmMessage)) {
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;

    const fields = {
        _token:  '{{ csrf_token() }}',
        _method: 'PUT',
        status:  status,
    };

    Object.entries(fields).forEach(([name, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
}

document.querySelectorAll('.cancel-payment-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        submitStatusForm(
            this.dataset.updateUrl,
            'cancelled',
            'Are you sure you want to cancel this payment? This action cannot be undone and will reset any associated invoices.'
        );
    });
});

document.querySelectorAll('.refund-payment-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        submitStatusForm(
            this.dataset.updateUrl,
            'refunded',
            'Are you sure you want to mark this payment as refunded? This will not process an actual refund through the payment gateway.'
        );
    });
});
</script>

<style>
.font-mono {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
}

.btn-danger {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
    border: 1px solid var(--danger);
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    transition: all 0.2s;
    cursor: pointer;
    display: inline-block;
    text-align: center;
}

.btn-danger:hover {
    background-color: var(--danger);
    color: white;
}

.btn-warning {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
    border: 1px solid var(--warning);
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    transition: all 0.2s;
    cursor: pointer;
    display: inline-block;
    text-align: center;
}

.btn-warning:hover {
    background-color: var(--warning);
    color: white;
}

.btn-success {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
    border: 1px solid var(--success);
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    transition: all 0.2s;
    cursor: pointer;
    display: inline-block;
    text-align: center;
}

.btn-success:hover {
    background-color: var(--success);
    color: white;
}

.text-success {
    color: var(--success);
}

.text-warning {
    color: var(--warning);
}

.text-danger {
    color: var(--danger);
}

/* Currency display styling */
.currency-display {
    font-family: monospace;
    font-weight: 600;
}

/* Responsive improvements */
@media (max-width: 768px) {
    .grid-cols-1.lg\:grid-cols-3 {
        grid-template-columns: 1fr;
    }

    .lg\:col-span-2 {
        grid-column: span 1;
    }
}

/* Code block styling */
pre {
    overflow-x: auto;
    white-space: pre-wrap;
    word-wrap: break-word;
}
</style>
@endsection