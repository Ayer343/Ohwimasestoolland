<table class="w-full text-sm">
    <thead style="background-color: var(--bg-primary);">
        <tr>
            <th class="text-left px-5 py-2 font-medium" style="color: var(--text-secondary);">ID</th>
            <th class="text-left px-5 py-2 font-medium" style="color: var(--text-secondary);">Type</th>
            <th class="text-left px-5 py-2 font-medium" style="color: var(--text-secondary);">Status</th>
            <th class="text-left px-5 py-2 font-medium" style="color: var(--text-secondary);">Period</th>
            <th class="text-right px-5 py-2"></th>
        </tr>
    </thead>
    <tbody>
        @foreach($items as $a)
            <tr class="border-t" style="border-color: var(--border-color);">
                <td class="px-5 py-3 font-mono" style="color: var(--text-primary);">#{{ $a->id }}</td>
                <td class="px-5 py-3" style="color: var(--text-secondary);">
                    {{ $a->supervisor_type ?? '—' }}
                </td>
                <td class="px-5 py-3">
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $a->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                        {{ $a->is_active ? 'Active' : 'Inactive' }}
                    </span>
                    @if($a->approval_status)
                        <span class="text-xs ml-1">{{ $a->approval_status }}</span>
                    @endif
                </td>
                <td class="px-5 py-3 text-xs" style="color: var(--text-secondary);">
                    {{ $a->start_date ? \Carbon\Carbon::parse($a->start_date)->format('M d') : '—' }}
                    →
                    {{ $a->end_date ? \Carbon\Carbon::parse($a->end_date)->format('M d') : '—' }}
                </td>
                <td class="px-5 py-3 text-right">
                    <a href="{{ route('admin.supervisor-assignments.show', $a->id) }}"
                       class="text-xs px-3 py-1 rounded"
                       style="background-color: var(--primary); color: white;">
                        View
                    </a>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>