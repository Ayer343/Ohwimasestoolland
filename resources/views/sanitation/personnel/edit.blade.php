{{-- resources/views/sanitation/personnel/edit.blade.php --}}

@php
    $user = auth()->user();
    $isAdmin = $user->isAdmin() || $user->isSuperAdmin();
    $layout = $isAdmin ? 'layouts.app' : 'layouts.san';

    // ✅ Current user's personnel record (if any)
    $currentPersonnel = $user->sanitationPersonnel;
    $isEditingSelf = $currentPersonnel && $currentPersonnel->id === $personnel->id;

    // ✅ Hierarchy context
    $isRoot = $personnel->isRootSupervisor();
    $teamSize = $personnel->subordinates()->count();
    $hasSubordinates = $teamSize > 0;

    // ✅ Allowed supervisors — same rules as the controller
    if ($isAdmin) {
        $allowedSupervisors = \App\Models\SanitationPersonnel::query()
            ->where(function ($q) {
                $q->whereIn('role', \App\Models\SanitationPersonnel::SUPERVISOR_ROLES)
                  ->orWhere('can_be_supervisor', true);
            })
            ->where('status', 'active')
            ->where('id', '!=', $personnel->id) // never allow self
            ->orderBy('first_name')
            ->get();
    } elseif ($currentPersonnel && $currentPersonnel->isSupervisor()) {
        $allowedSupervisors = collect([$currentPersonnel]);
        if ($currentPersonnel->supervisor && $currentPersonnel->supervisor->status === 'active') {
            $allowedSupervisors->push($currentPersonnel->supervisor);
        }
        // Never include the personnel being edited
        $allowedSupervisors = $allowedSupervisors
            ->reject(fn ($p) => $p->id === $personnel->id)
            ->values();
    } else {
        $allowedSupervisors = collect();
    }

    // Preselect current supervisor, fallback to creator
    $defaultSupervisorId = old('supervisor_id', $personnel->supervisor_id ?? $currentPersonnel?->id);

    // If this personnel has subordinates, they must keep a supervisor_id (can't orphan the team by becoming root)
    $mustKeepSupervisor = $hasSubordinates && !$isAdmin && !$isEditingSelf;
@endphp

@extends($layout)

@section('title', 'Edit ' . $personnel->full_name)

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-3xl mx-auto">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                @if($personnel->profile_photo)
                    <img src="{{ Storage::url($personnel->profile_photo) }}"
                         alt="{{ $personnel->full_name }}"
                         class="w-12 h-12 rounded-full object-cover">
                @else
                    <div class="w-12 h-12 rounded-full flex items-center justify-center text-white font-semibold"
                         style="background: linear-gradient(135deg, var(--primary), var(--info));">
                        {{ strtoupper(substr($personnel->first_name, 0, 1) . substr($personnel->last_name, 0, 1)) }}
                    </div>
                @endif
                <div>
                    <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                        Edit: {{ $personnel->full_name }}
                    </h1>
                    <div class="text-xs flex items-center gap-2 mt-1" style="color: var(--text-secondary);">
                        <span>{{ $personnel->employee_id }}</span>
                        <span>•</span>
                        <span>{{ ucfirst($personnel->role) }}</span>
                        @if($isRoot)
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold"
                                  style="background-color: rgba(var(--warning-rgb), 0.15); color: var(--warning);">
                                <i class="fas fa-crown mr-0.5"></i>Root
                            </span>
                        @endif
                        @if($isEditingSelf)
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold"
                                  style="background-color: rgba(var(--primary-rgb), 0.15); color: var(--primary);">
                                You
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            <a href="{{ route('sanitation.personnel.show', ['personnel' => $personnel->id]) }}" class="btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i> Back
            </a>
        </div>

        <!-- ✅ Team warning -->
        @if($hasSubordinates)
            <div class="card p-4 mb-4" style="border-left: 4px solid var(--info);">
                <div class="flex items-start gap-3">
                    <i class="fas fa-users text-xl mt-1" style="color: var(--info);"></i>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-primary);">
                            This personnel supervises {{ $teamSize }} team member{{ $teamSize === 1 ? '' : 's' }}
                        </p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Changing their role away from "Supervisor" or removing their own supervisor
                            may orphan their team. Make sure to reassign subordinates first if needed.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <!-- ✅ Self-edit warning -->
        @if($isEditingSelf)
            <div class="card p-4 mb-4" style="border-left: 4px solid var(--warning);">
                <div class="flex items-start gap-3">
                    <i class="fas fa-user-shield text-xl mt-1" style="color: var(--warning);"></i>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-primary);">
                            You are editing your own personnel record
                        </p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Be careful not to change your own role or reporting line in a way that
                            removes your ability to manage your team.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <div class="card p-6">
            <form action="{{ route('sanitation.personnel.update', ['personnel' => $personnel->id]) }}"
                  method="POST"
                  enctype="multipart/form-data"
                  id="personnel-edit-form">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Personal Information -->
                    <div class="md:col-span-2">
                        <h3 class="font-semibold mb-4" style="color: var(--text-primary);">Personal Information</h3>
                    </div>

                    <div>
                        <label for="first_name" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            First Name *
                        </label>
                        <input type="text" name="first_name" id="first_name"
                               value="{{ old('first_name', $personnel->first_name) }}"
                               class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               required>
                        @error('first_name')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="last_name" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Last Name *
                        </label>
                        <input type="text" name="last_name" id="last_name"
                               value="{{ old('last_name', $personnel->last_name) }}"
                               class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               required>
                        @error('last_name')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Phone Number *
                        </label>
                        <input type="text" name="phone" id="phone"
                               value="{{ old('phone', $personnel->phone) }}"
                               class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="+233XXXXXXXXX"
                               required>
                        @error('phone')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Email Address
                        </label>
                        <input type="email" name="email" id="email"
                               value="{{ old('email', $personnel->email) }}"
                               class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="person@example.com">
                        @error('email')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="address" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Address
                        </label>
                        <input type="text" name="address" id="address"
                               value="{{ old('address', $personnel->address) }}"
                               class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="Full address">
                        @error('address')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="profile_photo" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Profile Photo
                        </label>
                        @if($personnel->profile_photo)
                            <div class="mb-2">
                                <img src="{{ Storage::url($personnel->profile_photo) }}"
                                     alt="{{ $personnel->full_name }}"
                                     class="w-20 h-20 rounded-full object-cover">
                            </div>
                        @endif
                        <input type="file" name="profile_photo" id="profile_photo" accept="image/*"
                               class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Max size: 5MB. Accepted: JPEG, PNG, JPG, GIF
                        </p>
                        @error('profile_photo')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="hire_date" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Hire Date
                        </label>
                        <input type="date" name="hire_date" id="hire_date"
                               value="{{ old('hire_date', optional($personnel->hire_date)->format('Y-m-d')) }}"
                               class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        @error('hire_date')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Employment Details -->
                    <div class="md:col-span-2">
                        <h3 class="font-semibold mb-4 mt-4" style="color: var(--text-primary);">Employment Details</h3>
                    </div>

                    <div>
                        <label for="role" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Role *
                        </label>
                        <select name="role" id="role"
                                class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                required>
                            @foreach($roles as $role)
                                <option value="{{ $role }}" {{ old('role', $personnel->role) == $role ? 'selected' : '' }}>
                                    {{ ucfirst($role) }}
                                </option>
                            @endforeach
                        </select>
                        @error('role')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                        @if($hasSubordinates)
                            <p class="text-xs mt-1" style="color: var(--warning);">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                This personnel has {{ $teamSize }} direct report(s). Changing away from a supervisor
                                role may leave them without a manager.
                            </p>
                        @endif
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Status *
                        </label>
                        <select name="status" id="status"
                                class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                required>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" {{ old('status', $personnel->status) == $status ? 'selected' : '' }}>
                                    {{ ucfirst($status) }}
                                </option>
                            @endforeach
                        </select>
                        @error('status')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- ✅ NEW: Reporting Structure -->
                    <div class="md:col-span-2">
                        <h3 class="font-semibold mb-4 mt-4" style="color: var(--text-primary);">
                            <i class="fas fa-sitemap mr-1" style="color: var(--primary);"></i>
                            Reporting Structure
                        </h3>
                    </div>

                    @if($allowedSupervisors->isNotEmpty())
                        <div class="md:col-span-2">
                            <label for="supervisor_id" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                Reports To (Supervisor)
                                @if($mustKeepSupervisor)
                                    <span class="text-xs" style="color: var(--danger);">*</span>
                                @endif
                            </label>
                            <select name="supervisor_id" id="supervisor_id"
                                    class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                    @if($mustKeepSupervisor) required @endif>
                                {{-- Allow clearing only when safe --}}
                                @if(!$mustKeepSupervisor)
                                    <option value="">— No supervisor (top of tree) —</option>
                                @endif

                                @foreach($allowedSupervisors as $supervisor)
                                    <option value="{{ $supervisor->id }}"
                                            {{ (string) $defaultSupervisorId === (string) $supervisor->id ? 'selected' : '' }}>
                                        {{ $supervisor->full_name }}
                                        ({{ ucfirst($supervisor->role) }})
                                        @if($supervisor->employee_id)
                                            · {{ $supervisor->employee_id }}
                                        @endif
                                        @if($currentPersonnel && $supervisor->id === $currentPersonnel->id)
                                            — That's you
                                        @endif
                                        @if($currentPersonnel && $currentPersonnel->supervisor && $supervisor->id === $currentPersonnel->supervisor_id)
                                            — Your supervisor
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('supervisor_id')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror

                            {{-- Current supervisor preview --}}
                            @if($personnel->supervisor)
                                <div class="mt-3 p-3 rounded-lg flex items-center gap-3"
                                     style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                    <i class="fas fa-user-tie" style="color: var(--primary);"></i>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        Currently reports to
                                        <strong style="color: var(--text-primary);">{{ $personnel->supervisor->full_name }}</strong>
                                        ({{ ucfirst($personnel->supervisor->role) }})
                                    </div>
                                </div>
                            @else
                                <p class="text-xs mt-2" style="color: var(--warning);">
                                    <i class="fas fa-crown mr-1"></i>
                                    This personnel is currently at the top of the tree (no supervisor).
                                </p>
                            @endif

                            @if($mustKeepSupervisor)
                                <p class="text-xs mt-2" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Since this personnel has {{ $teamSize }} subordinate(s), they must remain
                                    under a supervisor to keep the hierarchy intact.
                                </p>
                            @elseif($isEditingSelf && $hasSubordinates)
                                <p class="text-xs mt-2" style="color: var(--warning);">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    You are editing yourself and have {{ $teamSize }} subordinate(s).
                                    Removing your own supervisor will make you the root supervisor.
                                </p>
                            @else
                                <p class="text-xs mt-2" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Leave blank to make this personnel a root supervisor.
                                </p>
                            @endif
                        </div>
                    @else
                        <div class="md:col-span-2">
                            <div class="p-4 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.08); border-left: 3px solid var(--warning);">
                                <p class="text-sm" style="color: var(--text-primary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    You don't have permission to change the reporting structure for this personnel.
                                </p>
                            </div>
                        </div>
                    @endif

                    <!-- Vehicle Information -->
                    <div class="md:col-span-2">
                        <h3 class="font-semibold mb-4 mt-4" style="color: var(--text-primary);">Vehicle Information</h3>
                    </div>

                    <div>
                        <label for="vehicle_number" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Vehicle Number
                        </label>
                        <input type="text" name="vehicle_number" id="vehicle_number"
                               value="{{ old('vehicle_number', $personnel->vehicle_number) }}"
                               class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="GT-1234-20">
                        @error('vehicle_number')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="vehicle_type" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Vehicle Type
                        </label>
                        <input type="text" name="vehicle_type" id="vehicle_type"
                               value="{{ old('vehicle_type', $personnel->vehicle_type) }}"
                               class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="Truck, Pickup, etc.">
                        @error('vehicle_type')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Additional Information -->
                    <div class="md:col-span-2">
                        <h3 class="font-semibold mb-4 mt-4" style="color: var(--text-primary);">Additional Information</h3>
                    </div>

                    <div>
                        <label for="emergency_contact" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Emergency Contact
                        </label>
                        <input type="text" name="emergency_contact" id="emergency_contact"
                               value="{{ old('emergency_contact', $personnel->emergency_contact) }}"
                               class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="Emergency contact name and phone">
                        @error('emergency_contact')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="shift_preference" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Shift Preference
                        </label>
                        <input type="text" name="shift_preference" id="shift_preference"
                               value="{{ old('shift_preference', $personnel->shift_preference) }}"
                               class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="Morning, Afternoon, Night, Flexible">
                        @error('shift_preference')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="certifications" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Certifications
                        </label>
                        <textarea name="certifications" id="certifications" rows="2"
                                  class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="List certifications (comma separated)">{{ old('certifications', is_array($personnel->certifications) ? implode(', ', $personnel->certifications) : $personnel->certifications) }}</textarea>
                        @error('certifications')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end space-x-3 mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                    <a href="{{ route('sanitation.personnel.show', ['personnel' => $personnel->id]) }}" class="btn-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="btn-primary" id="submit-button">
                        <i class="fas fa-save mr-2"></i> Update Personnel
                    </button>
                </div>
            </form>
        </div>

        <!-- ✅ Team preview -->
        @if($hasSubordinates)
            <div class="card p-6 mt-6">
                <h3 class="font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-users mr-2" style="color: var(--info);"></i>
                    Direct Reports ({{ $teamSize }})
                </h3>
                <div class="space-y-2 max-h-64 overflow-y-auto">
                    @foreach($personnel->subordinates()->orderBy('first_name')->get() as $subordinate)
                        <a href="{{ route('sanitation.personnel.edit', ['personnel' => $subordinate->id]) }}"
                           class="flex items-center gap-3 p-3 rounded-lg hover:opacity-80 transition-colors"
                           style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-semibold"
                                 style="background: linear-gradient(135deg, var(--primary), var(--info));">
                                {{ strtoupper(substr($subordinate->first_name, 0, 1) . substr($subordinate->last_name, 0, 1)) }}
                            </div>
                            <div class="flex-1">
                                <div class="text-sm font-medium" style="color: var(--text-primary);">
                                    {{ $subordinate->full_name }}
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    {{ ucfirst($subordinate->role) }} · {{ $subordinate->employee_id }}
                                </div>
                            </div>
                            <i class="fas fa-pen text-xs" style="color: var(--text-secondary);"></i>
                        </a>
                    @endforeach
                </div>
                <p class="text-xs mt-3" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1"></i>
                    Click any report to edit them directly.
                </p>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Simple client-side guard: warn on submit if a supervisor removes themselves from having any supervisor
    // while having subordinates. Server-side validation is still the source of truth.
    document.getElementById('personnel-edit-form')?.addEventListener('submit', function (e) {
        const supervisorSelect = document.getElementById('supervisor_id');
        const mustKeep = @json($mustKeepSupervisor);
        if (mustKeep && supervisorSelect && !supervisorSelect.value) {
            e.preventDefault();
            alert('This personnel has subordinates. They must remain under a supervisor.');
            return false;
        }
    });
</script>
@endpush