{{-- resources/views/sanitation/workers/create.blade.php --}}

@php
    // Detect which layout to use based on user role or route
    $user = auth()->user();
    $isAdmin = $user->isAdmin() || $user->isSuperAdmin();
    $layout = $isAdmin ? 'layouts.app' : 'layouts.san';
@endphp

@extends($layout)

@section('title', 'Add Worker')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-3xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold" style="color: var(--text-primary);">
                <i class="fas fa-user-plus mr-2" style="color: var(--primary);"></i>
                Add Sanitation Worker
            </h1>
            <a href="{{ route('sanitation.workers.index') }}" class="btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i> Back
            </a>
        </div>

        <div class="card p-6">
            <form action="{{ route('sanitation.workers.store') }}" method="POST">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Personal Information -->
                    <div class="md:col-span-2">
                        <h3 class="font-semibold mb-4" style="color: var(--text-primary);">Personal Information</h3>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            First Name *
                        </label>
                        <input type="text" name="first_name" value="{{ old('first_name') }}" 
                               class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               required>
                        @error('first_name')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Last Name *
                        </label>
                        <input type="text" name="last_name" value="{{ old('last_name') }}" 
                               class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               required>
                        @error('last_name')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Phone Number *
                        </label>
                        <input type="text" name="phone" value="{{ old('phone') }}" 
                               class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="+233XXXXXXXXX"
                               required>
                        @error('phone')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Email Address
                        </label>
                        <input type="email" name="email" value="{{ old('email') }}" 
                               class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="worker@example.com">
                        @error('email')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Address
                        </label>
                        <input type="text" name="address" value="{{ old('address') }}" 
                               class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="Full address">
                        @error('address')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Employment Details -->
                    <div class="md:col-span-2">
                        <h3 class="font-semibold mb-4 mt-4" style="color: var(--text-primary);">Employment Details</h3>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Supervisor
                        </label>
                        <select name="supervisor_id" class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">Select Supervisor</option>
                            @foreach($supervisors as $supervisor)
                                <option value="{{ $supervisor->id }}" {{ old('supervisor_id') == $supervisor->id ? 'selected' : '' }}>
                                    {{ $supervisor->full_name }} ({{ ucfirst($supervisor->role) }})
                                </option>
                            @endforeach
                        </select>
                        @error('supervisor_id')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Status *
                        </label>
                        <select name="status" class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                required>
                            <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                        @error('status')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Hire Date
                        </label>
                        <input type="date" name="hire_date" value="{{ old('hire_date') }}" 
                               class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        @error('hire_date')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Emergency Contact
                        </label>
                        <input type="text" name="emergency_contact" value="{{ old('emergency_contact') }}" 
                               class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="Emergency contact name and phone">
                        @error('emergency_contact')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Skills & Certifications -->
                    <div class="md:col-span-2">
                        <h3 class="font-semibold mb-4 mt-4" style="color: var(--text-primary);">Skills & Certifications</h3>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Skills
                        </label>
                        <textarea name="skills" rows="3"
                                  class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="List skills (one per line)">{{ old('skills') }}</textarea>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Enter each skill on a new line
                        </p>
                        @error('skills')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Certifications
                        </label>
                        <textarea name="certifications" rows="3"
                                  class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="List certifications (one per line)">{{ old('certifications') }}</textarea>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Enter each certification on a new line
                        </p>
                        @error('certifications')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                    <a href="{{ route('sanitation.workers.index') }}" class="btn-secondary">Cancel</a>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save mr-2"></i> Create Worker
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection