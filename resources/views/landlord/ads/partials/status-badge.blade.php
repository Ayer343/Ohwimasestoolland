@php
    $badges = [
        'draft'           => ['Draft', 'secondary'],
        'pending_payment' => ['Awaiting Payment', 'warning'],
        'active'          => ['Active', 'success'],
        'paused'          => ['Paused', 'secondary'],
        'rented_early'    => ['Rented', 'info'],
        'expired'         => ['Expired', 'secondary'],
        'cancelled'       => ['Cancelled', 'danger'],
        'rejected'        => ['Rejected', 'danger'],
    ];
    [$label, $color] = $badges[$status] ?? [ucfirst($status), 'secondary'];
@endphp
<span class="px-2.5 py-1 rounded-full text-xs font-medium"
      style="background: rgba(var(--{{ $color }}-rgb), 0.15); color: var(--{{ $color }});">
    {{ $label }}
</span>