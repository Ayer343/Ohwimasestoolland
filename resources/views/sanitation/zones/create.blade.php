{{-- resources/views/sanitation/zones/create.blade.php --}}

@php
    $user = auth()->user();
    $isAdmin = $user->isAdmin() || $user->isSuperAdmin();
    $layout = $isAdmin ? 'layouts.app' : 'layouts.san';
@endphp

@extends($layout)

@section('title', 'Create Collection Zone')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-plus-circle mr-2" style="color: var(--primary);"></i>
                    Create Collection Zone
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Create a new waste collection zone
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('sanitation.zones.index') }}" class="btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </div>
        </div>

        <!-- Form -->
        <div class="card p-6">
            <form method="POST" action="{{ route('sanitation.zones.store') }}" enctype="multipart/form-data">
                @csrf
                
                <div class="space-y-6">
                    <!-- Basic Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="name" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Zone Name *
                            </label>
                            <input type="text" name="name" id="name" 
                                   value="{{ old('name') }}"
                                   class="w-full p-2 border rounded-lg @error('name') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="Enter zone name" required>
                            @error('name')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="code" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Zone Code
                            </label>
                            <input type="text" name="code" id="code" 
                                   value="{{ old('code') }}"
                                   class="w-full p-2 border rounded-lg @error('code') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="e.g., ZONE-001">
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Leave empty to auto-generate from name
                            </div>
                            @error('code')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block mb-2 font-medium" style="color: var(--text-primary);">
                            Description
                        </label>
                        <textarea name="description" id="description" rows="3"
                                  class="w-full p-2 border rounded-lg @error('description') border-red-500 @enderror"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Describe the zone boundaries, coverage area, and other details...">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Location Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="region" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Region
                            </label>
                            <input type="text" name="region" id="region" 
                                   value="{{ old('region') }}"
                                   class="w-full p-2 border rounded-lg @error('region') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="e.g., Greater Accra">
                            @error('region')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="district" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                District
                            </label>
                            <input type="text" name="district" id="district" 
                                   value="{{ old('district') }}"
                                   class="w-full p-2 border rounded-lg @error('district') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="e.g., Accra Metropolitan">
                            @error('district')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Coordinates -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="latitude" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Latitude
                            </label>
                            <input type="number" step="any" name="latitude" id="latitude" 
                                   value="{{ old('latitude') }}"
                                   class="w-full p-2 border rounded-lg @error('latitude') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="e.g., 5.6037">
                            @error('latitude')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="longitude" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Longitude
                            </label>
                            <input type="number" step="any" name="longitude" id="longitude" 
                                   value="{{ old('longitude') }}"
                                   class="w-full p-2 border rounded-lg @error('longitude') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="e.g., -0.1870">
                            @error('longitude')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Boundary Coordinates -->
                    <div>
                        <label for="boundary_coordinates" class="block mb-2 font-medium" style="color: var(--text-primary);">
                            Boundary Coordinates (JSON)
                        </label>
                        <textarea name="boundary_coordinates" id="boundary_coordinates" rows="4"
                                  class="w-full p-2 border rounded-lg font-mono text-xs @error('boundary_coordinates') border-red-500 @enderror"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder='{"type":"Polygon","coordinates":[[[5.6037,-0.1870],[5.6037,-0.1770],[5.6137,-0.1770],[5.6137,-0.1870],[5.6037,-0.1870]]]}'>{{ old('boundary_coordinates') }}</textarea>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Enter boundary coordinates as GeoJSON format
                        </div>
                        @error('boundary_coordinates')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Assigned Personnel -->
                    <div>
                        <label for="assigned_personnel_id" class="block mb-2 font-medium" style="color: var(--text-primary);">
                            Assigned Personnel
                        </label>
                        <select name="assigned_personnel_id" id="assigned_personnel_id" 
                                class="w-full p-2 border rounded-lg @error('assigned_personnel_id') border-red-500 @enderror"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">Select Personnel</option>
                            @foreach($personnel as $person)
                                <option value="{{ $person->id }}" {{ old('assigned_personnel_id') == $person->id ? 'selected' : '' }}>
                                    {{ $person->full_name }} ({{ ucfirst($person->role) }})
                                </option>
                            @endforeach
                        </select>
                        @error('assigned_personnel_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="flex items-center space-x-3">
                            <input type="checkbox" name="is_active" value="1" 
                                   {{ old('is_active', true) ? 'checked' : '' }}
                                   class="w-4 h-4 border-gray-300 rounded focus:ring-blue-500" style="color: var(--primary);">
                            <span class="font-medium" style="color: var(--text-primary);">Active</span>
                        </label>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Active zones can be used for waste collection assignments
                        </p>
                    </div>

                    <!-- Additional Settings -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="status" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Status
                            </label>
                            <input type="text" name="status" id="status" 
                                   value="{{ old('status', 'active') }}"
                                   class="w-full p-2 border rounded-lg @error('status') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="e.g., active, under_review">
                            @error('status')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="priority" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Priority (1-100)
                            </label>
                            <input type="number" name="priority" id="priority" 
                                   value="{{ old('priority', 50) }}"
                                   min="1" max="100"
                                   class="w-full p-2 border rounded-lg @error('priority') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            @error('priority')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label for="notes" class="block mb-2 font-medium" style="color: var(--text-primary);">
                            Additional Notes
                        </label>
                        <textarea name="notes" id="notes" rows="3"
                                  class="w-full p-2 border rounded-lg @error('notes') border-red-500 @enderror"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="Any additional notes about this zone...">{{ old('notes') }}</textarea>
                        @error('notes')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Submit -->
                    <div class="flex justify-end space-x-3">
                        <a href="{{ route('sanitation.zones.index') }}" class="btn-secondary">Cancel</a>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save mr-2"></i> Create Zone
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Auto-generate code from name
document.getElementById('name').addEventListener('blur', function() {
    const codeField = document.getElementById('code');
    if (!codeField.value) {
        const name = this.value.trim();
        if (name) {
            const code = name.toUpperCase().replace(/[^A-Z0-9]/g, '');
            codeField.value = code.substring(0, 10);
        }
    }
});
</script>
@endpush