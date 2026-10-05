<table class="w-full text-sm">
    <thead style="background-color: var(--bg-primary);">
        <tr>
            <th class="text-left px-5 py-2 font-medium" style="color: var(--text-secondary);">Unit</th>
            <th class="text-left px-5 py-2 font-medium" style="color: var(--text-secondary);">Property</th>
            <th class="text-right px-5 py-2"></th>
        </tr>
    </thead>
    <tbody>
        @foreach($items as $u)
            <tr class="border-t" style="border-color: var(--border-color);">
                <td class="px-5 py-3" style="color: var(--text-primary);">Unit {{ $u->unit_number }}</td>
                <td class="px-5 py-3" style="color: var(--text-secondary);">{{ $u->property->property_name ?? '—' }}</td>
                <td class="px-5 py-3 text-right">
                    <a href="{{ route('admin.property-units.show', $u->id) }}"
                       class="text-xs px-3 py-1 rounded"
                       style="background-color: var(--primary); color: white;">
                        View
                    </a>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>