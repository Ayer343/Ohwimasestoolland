@extends('layouts.superadmin')

@section('title', 'Deleted System Settings')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Deleted System Settings</h2>
            <div>
                <a href="{{ route('admin.system-settings.index') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Settings
                </a>
            </div>
        </div>
    </div>

    @if($settings->count() > 0)
    <!-- System Settings Table -->
    <div class="card p-6">
        <div class="overflow-x-auto">
            <table class="w-full" style="border-collapse: collapse; width: 100%;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <th class="px-4 py-3 text-left font-medium" style="color: var(--text-primary);">System Name</th>
                        <th class="px-4 py-3 text-left font-medium" style="color: var(--text-primary);">Currency</th>
                        <th class="px-4 py-3 text-left font-medium" style="color: var(--text-primary);">Deleted At</th>
                        <th class="px-4 py-3 text-right font-medium" style="color: var(--text-primary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($settings as $setting)
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td class="px-4 py-3" style="color: var(--text-primary);">
                            <div class="font-medium">{{ $setting->system_name }}</div>
                            <div class="text-sm" style="color: var(--text-secondary);">{{ $setting->system_email }}</div>
                        </td>
                        <td class="px-4 py-3" style="color: var(--text-primary);">
                            <div>{{ $setting->currency_code }} ({{ $setting->currency_symbol }})</div>
                            <div class="text-sm" style="color: var(--text-secondary);">{{ ucfirst($setting->currency_position) }} position</div>
                        </td>
                        <td class="px-4 py-3" style="color: var(--text-primary);">
                            {{ $setting->deleted_at->format('M d, Y h:i A') }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end space-x-2">
                                <form action="{{ route('admin.system-settings.restore') }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="btn-warning flex items-center text-sm py-1 px-3">
                                        <i class="fas fa-undo mr-1"></i> Restore
                                    </button>
                                </form>
                                <form action="{{ route('admin.system-settings.force-delete') }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to permanently delete these settings? This action cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-danger flex items-center text-sm py-1 px-3">
                                        <i class="fas fa-trash-alt mr-1"></i> Delete Permanently
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    @if($settings->hasPages())
    <div class="card p-6">
        <div class="flex justify-between items-center">
            <div class="text-sm" style="color: var(--text-secondary);">
                Showing {{ $settings->firstItem() }} to {{ $settings->lastItem() }} of {{ $settings->total() }} results
            </div>
            <div class="flex space-x-2">
                @if($settings->onFirstPage())
                <span class="px-3 py-1 rounded border" style="background-color: var(--bg-secondary); color: var(--text-secondary); border-color: var(--border-color); cursor: not-allowed;">
                    Previous
                </span>
                @else
                <a href="{{ $settings->previousPageUrl() }}" class="px-3 py-1 rounded border hover:bg-gray-100" style="color: var(--text-primary); border-color: var(--border-color);">
                    Previous
                </a>
                @endif

                @if($settings->hasMorePages())
                <a href="{{ $settings->nextPageUrl() }}" class="px-3 py-1 rounded border hover:bg-gray-100" style="color: var(--text-primary); border-color: var(--border-color);">
                    Next
                </a>
                @else
                <span class="px-3 py-1 rounded border" style="background-color: var(--bg-secondary); color: var(--text-secondary); border-color: var(--border-color); cursor: not-allowed;">
                    Next
                </span>
                @endif
            </div>
        </div>
    </div>
    @endif

    @else
    <!-- Empty State -->
    <div class="card p-6">
        <div class="text-center py-8">
            <i class="fas fa-trash-alt text-4xl mb-4" style="color: var(--text-secondary);"></i>
            <h3 class="text-lg font-medium mb-2" style="color: var(--text-primary);">No Deleted Settings</h3>
            <p class="mb-4" style="color: var(--text-secondary);">There are no deleted system settings to display.</p>
            <a href="{{ route('admin.system-settings.index') }}" class="btn-primary inline-flex items-center">
                <i class="fas fa-cog mr-2"></i> View Current Settings
            </a>
        </div>
    </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add any JavaScript functionality needed for the trash view
    });
</script>
@endsection