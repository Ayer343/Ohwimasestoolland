@extends('layouts.secu')

@section('title', 'Assignment History')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-history text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-clipboard-list mr-2" style="color: var(--primary);"></i>
                        Assignment History
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>{{ auth()->user()->name }} - Past Assignments</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('security.supervisor.assignments.index') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Assignments
                </a>
                <a href="{{ route('security.supervisor.dashboard') }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium text-white inline-flex items-center btn-primary">
                    <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="card p-6">
            <div class="text-3xl font-bold mb-2" style="color: var(--text-primary);">{{ $stats['total'] }}</div>
            <div class="text-sm" style="color: var(--text-secondary);">Total Past Assignments</div>
        </div>
        <div class="card p-6">
            <div class="text-3xl font-bold mb-2" style="color: var(--text-primary);">{{ $stats['expired'] }}</div>
            <div class="text-sm" style="color: var(--text-secondary);">Expired Assignments</div>
        </div>
        <div class="card p-6">
            <div class="text-3xl font-bold mb-2" style="color: var(--text-primary);">{{ $stats['inactive'] }}</div>
            <div class="text-sm" style="color: var(--text-secondary);">Deactivated Assignments</div>
        </div>
    </div>

    <!-- Assignments Table -->
    <div class="card">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Security Post</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Supervisor Type</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Duration</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">End Reason</th>
                        <th class="text-left py-4 px-6 text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assignments as $assignment)
                        <tr class="border-b hover:bg-opacity-50 transition-colors duration-200" 
                            style="border-color: var(--border-color); background-color: var(--card-bg);">
                            <td class="py-4 px-6">
                                <div class="font-medium" style="color: var(--text-primary);">{{ $assignment->post->name ?? 'All Posts' }}</div>
                            </td>
                            <td class="py-4 px-6">
                                <span class="px-2 py-1 text-xs rounded-full badge-secondary">
                                    {{ $assignment->supervisor_type_name }}
                                </span>
                            </td>
                            <td class="py-4 px-6">
                                <div style="color: var(--text-primary);">{{ $assignment->start_date->format('M j, Y') }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">to {{ $assignment->end_date?->format('M j, Y') ?? 'Indefinite' }}</div>
                            </td>
                            <td class="py-4 px-6">
                                @if($assignment->end_date && $assignment->end_date < now())
                                    <span class="px-2 py-1 text-xs rounded-full badge-secondary">Expired</span>
                                @else
                                    <span class="px-2 py-1 text-xs rounded-full badge-warning">Inactive</span>
                                @endif
                            </td>
                            <td class="py-4 px-6">
                                <span class="text-xs" style="color: var(--text-secondary);">
                                    {{ $assignment->metadata['termination_reason'] ?? 'Natural expiration' }}
                                </span>
                            </td>
                            <td class="py-4 px-6">
                                <a href="{{ route('security.supervisor.assignments.show', $assignment->id) }}" 
                                   class="action-btn w-8 h-8 rounded-lg flex items-center justify-center"
                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                   title="View Details">
                                    <i class="fas fa-eye text-sm"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center" style="color: var(--text-secondary);">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-history text-4xl mb-3"></i>
                                    <p>No past assignments found.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if(method_exists($assignments, 'links'))
            <div class="p-6 border-t" style="border-color: var(--border-color);">
                {{ $assignments->appends(request()->query())->links() }}
            </div>
        @endif
    </div>
</div>
@endsection