<table class="w-full text-sm">
    <thead style="background-color: var(--bg-primary);">
        <tr>
            <th class="text-left px-5 py-2 font-medium" style="color: var(--text-secondary);">Reference</th>
            <th class="text-left px-5 py-2 font-medium" style="color: var(--text-secondary);">Amount</th>
            <th class="text-left px-5 py-2 font-medium" style="color: var(--text-secondary);">Status</th>
            <th class="text-right px-5 py-2"></th>
        </tr>
    </thead>
    <tbody>
        @foreach($items as $p)
            <tr class="border-t" style="border-color: var(--border-color);">
                <td class="px-5 py-3 font-mono" style="color: var(--text-primary);">{{ $p->transaction_reference }}</td>
                <td class="px-5 py-3" style="color: var(--text-primary);">{{ number_format((float) $p->amount, 2) }}</td>
                <td class="px-5 py-3" style="color: var(--text-secondary);">{{ $p->status }}</td>
                <td class="px-5 py-3 text-right">
                    <a href="{{ route('admin.payments.show', $p->id) }}"
                       class="text-xs px-3 py-1 rounded"
                       style="background-color: var(--primary); color: white;">
                        View
                    </a>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>