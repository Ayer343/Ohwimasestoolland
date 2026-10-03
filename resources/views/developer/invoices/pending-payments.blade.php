{{-- Add after existing content --}}

<!-- Online Payment Attempts -->
@if(isset($onlinePayments) && $onlinePayments->isNotEmpty())
<div class="card p-6 mt-6">
    <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
        <i class="fas fa-credit-card mr-2"></i> Online Payment Attempts
    </h3>
    
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b" style="border-color: var(--border-color);">
                    <th class="text-left p-3" style="color: var(--text-secondary);">Invoice</th>
                    <th class="text-left p-3" style="color: var(--text-secondary);">Provider</th>
                    <th class="text-left p-3" style="color: var(--text-secondary);">Amount</th>
                    <th class="text-left p-3" style="color: var(--text-secondary);">Status</th>
                    <th class="text-left p-3" style="color: var(--text-secondary);">Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($onlinePayments as $payment)
                <tr class="border-b" style="border-color: var(--border-color);">
                    <td class="p-3">
                        <a href="{{ route('developer.billing.history') }}" 
                           class="hover:text-primary" style="color: var(--text-primary);">
                            #{{ $payment->invoice_number }}
                        </a>
                    </td>
                    <td class="p-3">
                        <span class="px-2 py-1 rounded text-xs" 
                              style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            {{ ucfirst($payment->provider) }}
                        </span>
                    </td>
                    <td class="p-3" style="color: var(--text-primary);">
                        GHS {{ number_format($payment->amount, 2) }}
                    </td>
                    <td class="p-3">
                        @if($payment->status === 'success')
                        <span class="px-2 py-1 rounded text-xs" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            ✅ Paid
                        </span>
                        @elseif($payment->status === 'pending')
                        <span class="px-2 py-1 rounded text-xs" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                            ⏳ Pending
                        </span>
                        @else
                        <span class="px-2 py-1 rounded text-xs" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                            ❌ Failed
                        </span>
                        @endif
                    </td>
                    <td class="p-3" style="color: var(--text-secondary);">
                        {{ \Carbon\Carbon::parse($payment->created_at)->format('M d, Y H:i') }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif