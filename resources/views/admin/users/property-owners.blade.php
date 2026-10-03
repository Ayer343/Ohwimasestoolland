@extends('layouts.app')

@section('title', 'Property Owners')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Property Owners</h2>
                <p class="mt-1 text-sm" style="color: var(--text-secondary);">
                    Users who own properties (landlord role or legacy type + properties)
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('admin.users.index') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Users
                </a>
                <button onclick="exportPropertyOwners()" class="btn-success flex items-center">
                    <i class="fas fa-file-export mr-2"></i> Export
                </button>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                    <i class="fas fa-building text-lg"></i>
                </div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Total Property Owners</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $stats['total_property_owners'] }}</p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                    <i class="fas fa-home text-lg"></i>
                </div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Total Properties</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $stats['total_properties'] }}</p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-indigo-100 text-indigo-600 mr-4">
                    <i class="fas fa-tags text-lg"></i>
                </div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Multi-Role Owners</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $stats['multi_role_property_owners'] }}</p>
                    <p class="text-xs" style="color: var(--text-secondary);">Users with landlord + other roles</p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4">
                    <i class="fas fa-phone-slash text-lg"></i>
                </div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">Missing Phone</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $stats['property_owners_without_phone'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card p-6">
        <form method="GET" action="{{ route('admin.users.property-owners') }}" class="flex gap-4">
            <div class="flex-1">
                <input type="text" name="search" value="{{ request('search') }}" 
                       class="w-full p-2 border rounded" 
                       placeholder="Search by name, email, or phone..."
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
            </div>
            <div>
                <button type="submit" class="btn-primary px-4 py-2 rounded">
                    <i class="fas fa-search mr-2"></i> Search
                </button>
            </div>
            <div>
                <a href="{{ route('admin.users.property-owners') }}" class="btn-secondary px-4 py-2 rounded">
                    <i class="fas fa-sync mr-2"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Property Owners Table -->
    <div class="card p-6">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr style="border-bottom-color: var(--border-color);">
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">Owner</th>
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">Contact</th>
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">Properties</th>
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">Roles</th>
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">Joined</th>
                        <th class="text-left py-3 px-4 font-medium" style="color: var(--text-primary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($propertyOwners as $owner)
                    <tr class="border-b" style="border-bottom-color: var(--border-color);">
                        <td class="py-3 px-4">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center font-semibold text-white text-sm"
                                     style="background-color: {{ $owner->is_multi_role ? '#6f42c1' : '#28a745' }};">
                                    {{ $owner->initials }}
                                </div>
                                <div class="ml-3">
                                    <div class="font-medium" style="color: var(--text-primary);">
                                        {{ $owner->name }}
                                        @if($owner->is_multi_role)
                                            <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-purple-100 text-purple-800">
                                                <i class="fas fa-tags mr-1"></i> Multi-Role
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-sm" style="color: var(--text-secondary);">{{ $owner->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex items-center">
                                @if($owner->phone)
                                    <i class="fas fa-phone mr-2 text-green-600"></i>
                                    <span style="color: var(--text-primary);">{{ $owner->phone }}</span>
                                @else
                                    <span class="text-warning">
                                        <i class="fas fa-exclamation-triangle mr-1"></i> No phone
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <div>
                                <span class="font-bold text-lg" style="color: var(--text-primary);">{{ $owner->property_count }}</span>
                                <span class="text-sm" style="color: var(--text-secondary);">total</span>
                                @if($owner->active_property_count > 0)
                                    <br>
                                    <span class="text-xs text-green-600">
                                        <i class="fas fa-check-circle mr-1"></i>{{ $owner->active_property_count }} active
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex flex-wrap gap-1">
                                @foreach($owner->roles as $role)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                        @if($role->slug === 'landlord') bg-green-100 text-green-800
                                        @elseif($role->slug === 'admin') bg-blue-100 text-blue-800
                                        @elseif($role->slug === 'super-admin') bg-purple-100 text-purple-800
                                        @elseif($role->slug === 'field-agent') bg-cyan-100 text-cyan-800
                                        @elseif($role->slug === 'security-personnel') bg-orange-100 text-orange-800
                                        @elseif($role->slug === 'tenant') bg-yellow-100 text-yellow-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ $role->name }}
                                    </span>
                                @endforeach
                                @if(!$owner->hasRole('landlord') && $owner->type === \App\Models\User::TYPE_LANDLORD)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                        <i class="fas fa-history mr-1"></i> Legacy
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <div style="color: var(--text-primary);">{{ $owner->created_at->format('M j, Y') }}</div>
                            <div class="text-xs" style="color: var(--text-secondary);">
                                {{ $owner->created_at->diffForHumans() }}
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex space-x-1">
                                <a href="{{ route('admin.users.show', $owner->id) }}" 
                                   class="p-2 rounded-lg" 
                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                   title="View User">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('properties.index', ['landlord_id' => $owner->id]) }}" 
                                   class="p-2 rounded-lg" 
                                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);"
                                   title="View Properties">
                                    <i class="fas fa-building"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-8 px-4 text-center" style="color: var(--text-secondary);">
                            <i class="fas fa-users text-4xl mb-4 opacity-50"></i>
                            <p class="text-lg">No property owners found</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($propertyOwners->hasPages())
        <div class="border-t pt-4 mt-4" style="border-color: var(--border-color);">
            {{ $propertyOwners->links() }}
        </div>
        @endif
    </div>
</div>

<form id="exportForm" method="POST" action="{{ route('admin.users.property-owners.export') }}" class="hidden">
    @csrf
    <input type="hidden" name="export_format" id="exportFormat">
</form>
@endsection

@section('scripts')
<script>
function exportPropertyOwners() {
    const format = confirm('Export as CSV? Click OK for CSV, Cancel for PDF (coming soon)');
    if (format) {
        document.getElementById('exportFormat').value = 'csv';
        document.getElementById('exportForm').submit();
    } else {
        alert('PDF export coming soon!');
    }
}
</script>
@endsection