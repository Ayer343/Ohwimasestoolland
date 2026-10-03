{{-- resources/views/sanitation/personnel/workers.blade.php --}}

@php
    // Detect which layout to use based on user role or route
    $user = auth()->user();
    $isAdmin = $user->isAdmin() || $user->isSuperAdmin();
    $layout = $isAdmin ? 'layouts.app' : 'layouts.san';
@endphp

@extends($layout)

@section('title', 'Workers - ' . $personnel->full_name)

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-users mr-2" style="color: var(--primary);"></i>
                    Workers
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    {{ $personnel->full_name }} • {{ ucfirst($personnel->role) }}
                </p>
            </div>
            <a href="{{ route('sanitation.personnel.show', $personnel) }}" class="btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i> Back
            </a>
        </div>

        <div class="card p-6">
            @if($workers->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead style="background-color: var(--bg-secondary);">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase" style="color: var(--text-secondary);">Worker</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase" style="color: var(--text-secondary);">Contact</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase" style="color: var(--text-secondary);">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase" style="color: var(--text-secondary);">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y" style="border-color: var(--border-color);">
                            @foreach($workers as $worker)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center space-x-3">
                                            @if($worker->profile_photo)
                                                <img src="{{ Storage::url($worker->profile_photo) }}" 
                                                     alt="{{ $worker->full_name }}"
                                                     class="w-10 h-10 rounded-full object-cover">
                                            @else
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold text-sm"
                                                     style="background: linear-gradient(135deg, var(--info), var(--primary));">
                                                    {{ strtoupper(substr($worker->first_name, 0, 1) . substr($worker->last_name, 0, 1)) }}
                                                </div>
                                            @endif
                                            <div>
                                                <div class="font-medium" style="color: var(--text-primary);">
                                                    {{ $worker->full_name }}
                                                </div>
                                                <div class="text-xs" style="color: var(--text-secondary);">
                                                    {{ $worker->employee_id }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="text-sm" style="color: var(--text-primary);">{{ $worker->phone }}</div>
                                        <div class="text-xs" style="color: var(--text-secondary);">{{ $worker->email }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 rounded text-xs font-medium
                                            @if($worker->status == 'active') bg-green-100 text-green-600
                                            @else bg-gray-100 text-gray-600 @endif">
                                            {{ ucfirst($worker->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <button onclick="unassignWorker({{ $worker->id }})" 
                                                class="text-sm hover:underline" style="color: var(--danger);">
                                            <i class="fas fa-user-minus mr-1"></i> Unassign
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-8" style="color: var(--text-secondary);">
                    <i class="fas fa-users text-4xl mb-3 block" style="color: var(--text-secondary); opacity: 0.3;"></i>
                    <p>No workers assigned to this supervisor</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection