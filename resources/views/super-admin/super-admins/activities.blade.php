{{-- super-admin/super-admins/activities.blade.php --}}
@extends('layouts.app')

@php
    $isOwnProfile = auth()->id() == $user->id;
    $pageTitle = $isOwnProfile ? 'My Activities' : "Activities: {$user->name}";
@endphp

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                        <i class="fas fa-history text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                        {{ $pageTitle }}
                    </h2>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        View all activities and actions performed
                    </p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('super-admin.super-admins.show', $user->id) }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium btn-secondary">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Profile
                </a>
            </div>
        </div>
    </div>

    <!-- User Info Card -->
    <div class="card p-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                @if($user->has_photo && $user->photo)
                <div class="w-12 h-12 rounded-full overflow-hidden">
                    <img src="{{ Storage::url('users/photos/' . $user->photo) }}" 
                         alt="{{ $user->name }}" 
                         class="w-full h-full object-cover">
                </div>
                @else
                <div class="w-12 h-12 rounded-full flex items-center justify-center"
                     style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600;">
                    {{ $user->initials }}
                </div>
                @endif
            </div>
            <div class="ml-4">
                <h3 class="font-semibold text-lg" style="color: var(--text-primary);">{{ $user->name }}</h3>
                <p class="text-sm" style="color: var(--text-secondary);">{{ $user->email }}</p>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card p-6">
        <form method="GET" action="{{ route('super-admin.super-admins.activities', $user->id) }}" id="filterForm">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Activity Type</label>
                    <select name="type" class="index-custom-dropdown w-full">
                        <option value="all">All Activities</option>
                        <option value="login" {{ request('type') == 'login' ? 'selected' : '' }}>Login</option>
                        <option value="logout" {{ request('type') == 'logout' ? 'selected' : '' }}>Logout</option>
                        <option value="create" {{ request('type') == 'create' ? 'selected' : '' }}>Create</option>
                        <option value="update" {{ request('type') == 'update' ? 'selected' : '' }}>Update</option>
                        <option value="delete" {{ request('type') == 'delete' ? 'selected' : '' }}>Delete</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Date From</label>
                    <input type="date" 
                           name="date_from" 
                           value="{{ request('date_from') }}" 
                           class="index-custom-input w-full">
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Date To</label>
                    <input type="date" 
                           name="date_to" 
                           value="{{ request('date_to') }}" 
                           class="index-custom-input w-full">
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);">Per Page</label>
                    <select name="per_page" class="index-custom-dropdown w-full">
                        <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10</option>
                        <option value="20" {{ request('per_page') == 20 ? 'selected' : '' }}>20</option>
                        <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-between items-center mt-4">
                <div>
                    <button type="submit" class="btn-primary px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-search mr-2"></i> Apply Filters
                    </button>
                    
                    @if(request()->anyFilled(['type', 'date_from', 'date_to']))
                        <a href="{{ route('super-admin.super-admins.activities', $user->id) }}" 
                           class="btn-secondary px-4 py-2 rounded-lg text-sm font-medium ml-2">
                            <i class="fas fa-undo mr-2"></i> Clear
                        </a>
                    @endif
                </div>
                
                <div>
                    <a href="{{ route('super-admin.super-admins.export-activities', $user->id) . '?' . http_build_query(request()->except('page')) }}" 
                       class="btn-success px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-download mr-2"></i> Export CSV
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Activities Table -->
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr style="background-color: rgba(var(--secondary-rgb), 0.05); border-bottom: 2px solid var(--border-color);">
                        <th class="text-left py-4 px-6 font-semibold text-sm" style="color: var(--text-secondary);">Date & Time</th>
                        <th class="text-left py-4 px-6 font-semibold text-sm" style="color: var(--text-secondary);">Activity</th>
                        <th class="text-left py-4 px-6 font-semibold text-sm" style="color: var(--text-secondary);">IP Address</th>
                        <th class="text-left py-4 px-6 font-semibold text-sm" style="color: var(--text-secondary);">Device</th>
                        <th class="text-left py-4 px-6 font-semibold text-sm" style="color: var(--text-secondary);">Browser</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activities as $activity)
                    <tr style="border-bottom: 1px solid var(--border-color);" class="hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                        <td class="py-4 px-6">
                            <p class="text-sm font-medium" style="color: var(--text-primary);">
                                {{ $activity->created_at->format('M j, Y') }}
                            </p>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                {{ $activity->created_at->format('g:i A') }}
                                <span class="ml-1">({{ $activity->created_at->diffForHumans() }})</span>
                            </p>
                        </td>
                        <td class="py-4 px-6">
                            <div class="flex items-center">
                                @php
                                    $icon = match($activity->activity_type ?? $activity->type) {
                                        'login' => 'fa-sign-in-alt',
                                        'logout' => 'fa-sign-out-alt',
                                        'create' => 'fa-plus-circle',
                                        'update' => 'fa-edit',
                                        'delete' => 'fa-trash',
                                        default => 'fa-history'
                                    };
                                    $color = match($activity->activity_type ?? $activity->type) {
                                        'login' => 'text-green-500',
                                        'logout' => 'text-yellow-500',
                                        'create' => 'text-blue-500',
                                        'update' => 'text-purple-500',
                                        'delete' => 'text-red-500',
                                        default => 'text-gray-500'
                                    };
                                @endphp
                                <i class="fas {{ $icon }} {{ $color }} mr-2"></i>
                                <span class="text-sm" style="color: var(--text-primary);">{{ $activity->description }}</span>
                            </div>
                        </td>
                        <td class="py-4 px-6">
                            <code class="text-xs" style="color: var(--text-secondary);">{{ $activity->ip_address ?? 'N/A' }}</code>
                        </td>
                        <td class="py-4 px-6">
                            <span class="text-sm" style="color: var(--text-primary);">
                                {{ $activity->metadata['user_agent_parsed']['device_type'] ?? 'Unknown' }}
                            </span>
                        </td>
                        <td class="py-4 px-6">
                            <span class="text-sm" style="color: var(--text-primary);">
                                {{ $activity->metadata['user_agent_parsed']['browser'] ?? 'Unknown' }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-12 text-center">
                            <i class="fas fa-history text-5xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                            <p class="text-lg font-medium" style="color: var(--text-primary);">No Activities Found</p>
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">No activities match your filters.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($activities->hasPages())
        <div class="p-6 border-t" style="border-color: var(--border-color);">
            {{ $activities->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>

<style>
.index-custom-dropdown,
.index-custom-input {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    transition: all 0.3s ease;
}

.index-custom-dropdown:focus,
.index-custom-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.index-custom-dropdown {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 2.5rem;
}

.btn-primary {
    background-color: var(--primary);
    color: white;
    border: 1px solid var(--primary);
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background-color: var(--secondary);
    border-color: var(--secondary);
    transform: translateY(-1px);
}

.btn-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1);
    color: var(--secondary);
    border: 1px solid rgba(var(--secondary-rgb), 0.3);
    transition: all 0.2s ease;
}

.btn-secondary:hover {
    background-color: rgba(var(--secondary-rgb), 0.2);
    transform: translateY(-1px);
}

.btn-success {
    background-color: var(--success);
    color: white;
    border: 1px solid var(--success);
    transition: all 0.2s ease;
}

.btn-success:hover {
    background-color: #28a745;
    transform: translateY(-1px);
}
</style>
@endsection