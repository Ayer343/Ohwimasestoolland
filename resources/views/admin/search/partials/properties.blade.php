<table class="w-full text-sm">
    <thead style="background-color: var(--bg-primary);">
        <tr>
            <th class="text-left px-5 py-2 font-medium" style="color: var(--text-secondary);">Property</th>
            <th class="text-left px-5 py-2 font-medium" style="color: var(--text-secondary);">Street</th>
            <th class="text-left px-5 py-2 font-medium" style="color: var(--text-secondary);">Digital Address</th>
            <th class="text-right px-5 py-2"></th>
        </tr>
    </thead>
    <tbody>
        @foreach($items as $p)
            <tr class="border-t" style="border-color: var(--border-color);">
                <td class="px-5 py-3" style="color: var(--text-primary);">{{ $p->property_name }}</td>
                <td class="px-5 py-3" style="color: var(--text-secondary);">{{ $p->street_name ?? '—' }}</td>
                <td class="px-5 py-3" style="color: var(--text-secondary);">{{ $p->digital_address ?? '—' }}</td>
                <td class="px-5 py-3 text-right">
                    <a href="{{ route('properties.show', $p->id) }}"
                       class="text-xs px-3 py-1 rounded"
                       style="background-color: var(--primary); color: white;">
                        View
                    </a>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>