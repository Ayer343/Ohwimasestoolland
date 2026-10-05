<table class="w-full text-sm">
    <thead style="background-color: var(--bg-primary);">
        <tr>
            <th class="text-left px-5 py-2 font-medium" style="color: var(--text-secondary);">Reference</th>
            <th class="text-left px-5 py-2 font-medium" style="color: var(--text-secondary);">Status</th>
            <th class="text-right px-5 py-2"></th>
        </tr>
    </thead>
    <tbody>
        @foreach($items as $t)
            <tr class="border-t" style="border-color: var(--border-color);">
                <td class="px-5 py-3 font-mono" style="color: var(--text-primary);">{{ $t->document_reference }}</td>
                <td class="px-5 py-3" style="color: var(--text-secondary);">{{ $t->status }}</td>
                <td class="px-5 py-3 text-right">
                    <a href="{{ route('admin.ownership-transfers.show', $t->id) }}"
                       class="text-xs px-3 py-1 rounded"
                       style="background-color: var(--primary); color: white;">
                        View
                    </a>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>