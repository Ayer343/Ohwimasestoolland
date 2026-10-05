<table class="w-full text-sm">
    <thead style="background-color: var(--bg-primary);">
        <tr>
            <th class="text-left px-5 py-2 font-medium" style="color: var(--text-secondary);">ID</th>
            <th class="text-left px-5 py-2 font-medium" style="color: var(--text-secondary);">Date</th>
            <th class="text-left px-5 py-2 font-medium" style="color: var(--text-secondary);">Status</th>
            <th class="text-right px-5 py-2"></th>
        </tr>
    </thead>
    <tbody>
        @foreach($items as $s)
            <tr class="border-t" style="border-color: var(--border-color);">
                <td class="px-5 py-3" style="color: var(--text-primary);">#{{ $s->id }}</td>
                <td class="px-5 py-3" style="color: var(--text-secondary);">{{ $s->schedule_date }}</td>
                <td class="px-5 py-3" style="color: var(--text-secondary);">{{ $s->status }}</td>
                <td class="px-5 py-3 text-right">
                    <a href="{{ route('admin.security-schedules.show', $s->id) }}"
                       class="text-xs px-3 py-1 rounded"
                       style="background-color: var(--primary); color: white;">
                        View
                    </a>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>