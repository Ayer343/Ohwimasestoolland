{{-- Add after amount section --}}

@if($paymentDetails->provider ?? false)
<div class="grid grid-cols-2 gap-4 mt-4 pt-4 border-t" style="border-color: var(--border-color);">
    <div>
        <p class="text-sm" style="color: var(--text-secondary);">Payment Provider</p>
        <p class="font-medium" style="color: var(--text-primary);">
            <span class="px-2 py-1 rounded text-xs" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                {{ ucfirst($paymentDetails->provider) }}
            </span>
        </p>
    </div>
    <div>
        <p class="text-sm" style="color: var(--text-secondary);">Transaction Reference</p>
        <p class="font-mono text-sm" style="color: var(--text-primary);">
            {{ $paymentDetails->transaction_reference ?? $paymentDetails->reference ?? 'N/A' }}
        </p>
    </div>
</div>
@endif