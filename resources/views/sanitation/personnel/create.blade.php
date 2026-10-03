{{-- resources/views/sanitation/personnel/create.blade.php --}}

@php
    $user = auth()->user();
    $isAdmin = $user->isAdmin() || $user->isSuperAdmin();
    $layout = $isAdmin ? 'layouts.app' : 'layouts.san';

    // ✅ Determine the creator's personnel record (if they're sanitation staff)
    $creatorPersonnel = $user->sanitationPersonnel;

    // ✅ Build the list of supervisors this user is allowed to assign to
    if ($isAdmin) {
        $allowedSupervisors = \App\Models\SanitationPersonnel::query()
            ->where(function ($q) {
                $q->whereIn('role', \App\Models\SanitationPersonnel::SUPERVISOR_ROLES)
                  ->orWhere('can_be_supervisor', true);
            })
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get();
    } elseif ($creatorPersonnel && $creatorPersonnel->isSupervisor()) {
        $allowedSupervisors = collect();

        $allowedSupervisors->push($creatorPersonnel);

        if ($creatorPersonnel->supervisor && $creatorPersonnel->supervisor->status === 'active') {
            $allowedSupervisors->push($creatorPersonnel->supervisor);
        }
    } else {
        $allowedSupervisors = collect();
    }

    // Default selection: the creator themselves (if they're a supervisor)
    $defaultSupervisorId = old('supervisor_id', $creatorPersonnel?->id);

    /* ============================================================
     | 📧 EMAIL-ONLY INVITATION AVAILABILITY
     |------------------------------------------------------------
     | This blade assumes the controller passes `$emailStatus` (the
     | same shape used in admin.users.create). If it doesn't, we
     | default to false so the UI fails safe and just doesn't offer
     | an invitation.
     ============================================================ */
    $emailReady = (bool) (($emailStatus['system_ready'] ?? false));
    $anyChannelReady = $emailReady; // only one channel now

    // ✅ Is the invitation toggle currently ON (from old() or default OFF)?
    $inviteRequested = (bool) old('send_invitation', false);

    // ✅ Email service health label + colour class (mirrors admin blade)
    $emailHealth         = $emailStatus['health']  ?? 'unknown';
    $emailHealthClass    = $emailHealth === 'healthy' ? 'text-green-600'
                         : ($emailHealth === 'degraded' ? 'text-yellow-600'
                         : 'text-red-600');
    $emailHealthIconColor = $emailHealth === 'healthy' ? 'var(--success)'
                         : ($emailHealth === 'degraded' ? 'var(--warning)'
                         : 'var(--danger)');
@endphp

@extends($layout)

@section('title', 'Add Personnel')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-3xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-user-plus mr-2" style="color: var(--primary);"></i>
                    Add Sanitation Personnel
                </h1>

                @if($creatorPersonnel && $creatorPersonnel->isSupervisor())
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-sitemap mr-1" style="color: var(--primary);"></i>
                        You are adding a new team member under
                        <strong style="color: var(--text-primary);">{{ $creatorPersonnel->full_name }}</strong>
                        @if($creatorPersonnel->supervisor)
                            <span style="color: var(--text-secondary);">
                                (your supervisor: {{ $creatorPersonnel->supervisor->full_name }})
                            </span>
                        @endif
                    </p>
                @elseif($isAdmin)
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        You are adding a new sanitation personnel as <strong>Admin</strong>.
                    </p>
                @endif
            </div>
            <a href="{{ route('sanitation.personnel.index') }}" class="btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i> Back
            </a>
        </div>

        <!-- Non-supervisor guard -->
        @if(!$isAdmin && (!$creatorPersonnel || !$creatorPersonnel->isSupervisor()))
            <div class="card p-6 mb-6" style="border-left: 4px solid var(--warning);">
                <div class="flex items-start gap-3">
                    <i class="fas fa-exclamation-triangle text-yellow-500 text-xl mt-1"></i>
                    <div>
                        <h4 class="font-semibold text-sm" style="color: var(--text-primary);">
                            You cannot add personnel
                        </h4>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Only sanititation <strong>supervisors</strong> and administrators
                            can add new personnel. Please contact your supervisor if this is a mistake.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- ============================================================ --}}
        {{-- ✅ SUCCESS / ERROR FLASH — mirrors admin.users.create --}}
        {{-- ============================================================ --}}
        @if(session('success'))
            <div class="card p-0 overflow-hidden border-l-4 mb-6" style="border-left-color: var(--success);">
                <div class="p-4" style="background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(34, 197, 94, 0.05));">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-2xl mr-3" style="color: var(--success);"></i>
                            <div>
                                <h3 class="text-lg font-semibold" style="color: var(--success);">
                                    Personnel Created Successfully!
                                </h3>
                                <p class="mt-1 text-sm" style="color: var(--text-primary);">{{ session('success') }}</p>

                                @if(session('invitation_result') && (session('invitation_result')['success'] ?? false))
                                    <div class="mt-3 p-3 rounded-lg"
                                         style="background-color: rgba(var(--info-rgb), 0.1);">
                                        <div class="flex items-start">
                                            <i class="fas fa-paper-plane mt-0.5 mr-2" style="color: var(--info);"></i>
                                            <div>
                                                <p class="text-sm font-medium" style="color: var(--text-primary);">
                                                    Invitation Status:
                                                </p>
                                                <p class="text-sm" style="color: var(--text-secondary);">
                                                    Invitation sent successfully via
                                                    {{ implode(', ', session('invitation_result')['channels_successful'] ?? []) }}
                                                    @if(!empty(session('invitation_result')['failed_channels']))
                                                        <br><span class="text-yellow-600">
                                                            ⚠️ Failed channels:
                                                            {{ implode(', ', session('invitation_result')['failed_channels']) }}
                                                        </span>
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <button type="button"
                                onclick="this.closest('.card').remove()"
                                class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="card p-0 overflow-hidden border-l-4 mb-6" style="border-left-color: var(--danger);">
                <div class="p-4" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-circle text-2xl mr-3" style="color: var(--danger);"></i>
                            <div>
                                <h3 class="text-lg font-semibold" style="color: var(--danger);">Error!</h3>
                                <p class="mt-1 text-sm" style="color: var(--text-primary);">{{ session('error') }}</p>
                            </div>
                        </div>
                        <button type="button"
                                onclick="this.closest('.card').remove()"
                                class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <div class="card p-6">
            <form action="{{ route('sanitation.personnel.store') }}" method="POST" enctype="multipart/form-data" id="personnel-create-form">
                @csrf

                {{-- Hidden fallback for the supervisor ID when the picker isn't rendered --}}
                @if(!$isAdmin && $creatorPersonnel && $creatorPersonnel->isSupervisor())
                    <input type="hidden" name="supervisor_id" value="{{ $defaultSupervisorId }}">
                @endif

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

                    {{-- ============================================================ --}}
                    {{-- 📧 EMAIL — marked * and required when invitation is ON --}}
                    {{-- ============================================================ --}}
                    <div>
                        <label for="email" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Email Address
                            <span id="email-required-star"
                                  class="text-red-500 {{ $inviteRequested ? '' : 'hidden' }}">*</span>
                        </label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}"
                               class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 @error('email') border-red-500 @enderror"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="person@example.com"
                               data-invite-required="{{ $inviteRequested ? '1' : '0' }}">
                        @error('email')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-xs mt-1" style="color: var(--text-secondary);" id="email-required-hint">
                            <i class="fas fa-info-circle mr-1"></i>
                            <span id="email-required-hint-text">
                                @if($inviteRequested)
                                    Required — an invitation will be sent to this address.
                                @else
                                    Optional — only needed if you plan to send an email invitation.
                                @endif
                            </span>
                        </p>
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

                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Profile Photo
                        </label>
                        <input type="file" name="profile_photo" accept="image/*"
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
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Hire Date
                        </label>
                        <input type="date" name="hire_date" value="{{ old('hire_date', date('Y-m-d')) }}"
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
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Role *
                        </label>
                        <select name="role" id="role" class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                required>
                            <option value="">Select Role</option>
                            @foreach($roles as $role)
                                <option value="{{ $role }}" {{ old('role') == $role ? 'selected' : '' }}>
                                    {{ ucfirst($role) }}
                                </option>
                            @endforeach
                        </select>
                        @error('role')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror

                        @if($creatorPersonnel && $creatorPersonnel->isSupervisor() && !$isAdmin)
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                As a supervisor you can create supervisors, workers, and drivers under you.
                            </p>
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Status *
                        </label>
                        <select name="status" id="status" class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                required>
                            <option value="">Select Status</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" {{ old('status', 'active') == $status ? 'selected' : '' }}>
                                    {{ ucfirst($status) }}
                                </option>
                            @endforeach
                        </select>
                        @error('status')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- ✅ Supervisor (Reports To) -->
                    <div class="md:col-span-2" id="supervisor-block">
                        <h3 class="font-semibold mb-4 mt-4" style="color: var(--text-primary);">
                            <i class="fas fa-sitemap mr-1" style="color: var(--primary);"></i>
                            Reporting Structure
                        </h3>
                    </div>

                    @if($allowedSupervisors->isNotEmpty())
                        <div class="md:col-span-2">
                            <label for="supervisor_id" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                Reports To (Supervisor) *
                            </label>
                            <select name="supervisor_id" id="supervisor_id"
                                    class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                    required>
                                @foreach($allowedSupervisors as $supervisor)
                                    <option value="{{ $supervisor->id }}"
                                            {{ (string) $defaultSupervisorId === (string) $supervisor->id ? 'selected' : '' }}>
                                        {{ $supervisor->full_name }}
                                        ({{ ucfirst($supervisor->role) }})
                                        @if($supervisor->employee_id)
                                            · {{ $supervisor->employee_id }}
                                        @endif
                                        @if($creatorPersonnel && $supervisor->id === $creatorPersonnel->id)
                                            — That's you
                                        @endif
                                        @if($creatorPersonnel && $creatorPersonnel->supervisor && $supervisor->id === $creatorPersonnel->supervisor_id)
                                            — Your supervisor
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('supervisor_id')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                The selected supervisor will be notified whenever this personnel's
                                collection requests are approved or updated.
                            </p>
                        </div>
                    @else
                        <div class="md:col-span-2">
                            <div class="p-4 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border-left: 3px solid var(--warning);">
                                <p class="text-sm" style="color: var(--text-primary);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    @if($isAdmin)
                                        No active supervisors available. Create a supervisor first, then come back to add subordinates.
                                    @else
                                        You are not currently assigned as a supervisor. Please contact an administrator.
                                    @endif
                                </p>
                            </div>
                        </div>
                    @endif

                    <!-- Vehicle Information -->
                    <div class="md:col-span-2">
                        <h3 class="font-semibold mb-4 mt-4" style="color: var(--text-primary);">Vehicle Information</h3>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Vehicle Number
                        </label>
                        <input type="text" name="vehicle_number" value="{{ old('vehicle_number') }}"
                               class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="GT-1234-20">
                        @error('vehicle_number')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Vehicle Type
                        </label>
                        <input type="text" name="vehicle_type" value="{{ old('vehicle_type') }}"
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

                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Shift Preference
                        </label>
                        <input type="text" name="shift_preference" value="{{ old('shift_preference') }}"
                               class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               placeholder="Morning, Afternoon, Night, Flexible">
                        @error('shift_preference')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Certifications
                        </label>
                        <textarea name="certifications" rows="2"
                                  class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                  placeholder="List certifications (comma separated)">{{ old('certifications') }}</textarea>
                        @error('certifications')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- ============================================================ --}}
                {{-- 📧 SEND INVITATION (EMAIL ONLY) --}}
                {{-- ============================================================ --}}
                <div class="mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-semibold" style="color: var(--text-primary);">
                            <i class="fas fa-paper-plane mr-2" style="color: var(--info);"></i>
                            Send Invitation to User
                        </h3>
                        <span class="px-3 py-1 rounded-full text-xs font-medium"
                              style="background-color: {{ $emailReady ? 'rgba(var(--success-rgb), 0.15)' : 'rgba(var(--warning-rgb), 0.15)' }};
                                     color: {{ $emailReady ? 'var(--success)' : 'var(--warning)' }};">
                            <i class="fas fa-envelope mr-1"></i>
                            Email: {{ $emailReady ? 'Ready' : 'Not Available' }}
                        </span>
                    </div>

                    {{-- Email unavailable banner --}}
                    @if(!$emailReady)
                        <div class="rounded p-4 border-l-4 mb-4"
                             style="background-color: rgba(var(--warning-rgb), 0.1); border-left-color: var(--warning);">
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--warning);"></i>
                                <div>
                                    <h4 class="font-medium mb-1" style="color: var(--text-primary);">
                                        Email service not available
                                    </h4>
                                    <p class="text-sm" style="color: var(--text-secondary);">
                                        Invitations cannot be sent right now. You can still create the personnel record
                                        without an invitation, and resend it later from the personnel details page.
                                    </p>
                                    <div class="text-xs mt-2" style="color: var(--text-secondary);">
                                        <strong>Current Status:</strong>
                                        {{ $emailStatus['health'] ?? 'unknown' }} —
                                        {{ $emailStatus['message'] ?? 'Email service not configured' }}
                                    </div>
                                    @if(auth()->user()->isAdmin())
                                        <div class="mt-2">
                                            <a href="{{ route('admin.email-config.index') }}"
                                               class="btn-secondary btn-sm inline-flex items-center">
                                                <i class="fas fa-cog mr-2"></i> Configure Email
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Send Invitation toggle (email only) --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="modern-radio-option {{ !$emailReady ? 'modern-radio-option--disabled' : '' }}">
                            <input type="radio" name="send_invitation" id="send_invitation_yes" value="1"
                                   class="modern-radio-input"
                                   {{ $emailReady ? '' : 'disabled' }}
                                   {{ old('send_invitation') ? 'checked' : '' }}>
                            <label for="send_invitation_yes" class="modern-radio-label">
                                <div class="modern-radio-content">
                                    <div class="modern-radio-icon">
                                        <i class="fas fa-paper-plane"></i>
                                    </div>
                                    <div class="modern-radio-text">
                                        <div class="modern-radio-title">Send Invitation</div>
                                        <div class="modern-radio-description">
                                            @if($emailReady)
                                                Send a secure email with a password setup link
                                            @else
                                                <span class="text-red-500">Email service unavailable</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="modern-radio-check"><i class="fas fa-check"></i></div>
                                </div>
                            </label>
                        </div>

                        <div class="modern-radio-option">
                            <input type="radio" name="send_invitation" id="send_invitation_no" value="0"
                                   class="modern-radio-input"
                                   {{ !old('send_invitation') ? 'checked' : '' }}>
                            <label for="send_invitation_no" class="modern-radio-label">
                                <div class="modern-radio-content">
                                    <div class="modern-radio-icon">
                                        <i class="fas fa-ban"></i>
                                    </div>
                                    <div class="modern-radio-text">
                                        <div class="modern-radio-title">Don't Send</div>
                                        <div class="modern-radio-description">Skip invitation for now</div>
                                    </div>
                                    <div class="modern-radio-check"><i class="fas fa-check"></i></div>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Invitation details (shown when Yes selected) --}}
                    <div id="invitation-details" class="{{ old('send_invitation') && $emailReady ? '' : 'hidden' }} mt-6 space-y-4">
                        <div class="rounded p-3"
                             style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3);">
                            <div class="flex items-start">
                                <i class="fas fa-shield-alt mr-2 mt-0.5" style="color: var(--info);"></i>
                                <div class="text-sm" style="color: var(--text-secondary);">
                                    <strong style="color: var(--text-primary);">Secure email invitation:</strong>
                                    The personnel will receive a link to set their own password.
                                    No password is sent by email. The link expires automatically.
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="invitation_type" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Invitation Type *
                                </label>
                                <select class="w-full p-2 border rounded"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                        id="invitation_type" name="invitation_type">
                                    <option value="welcome"        {{ old('invitation_type') == 'welcome' ? 'selected' : '' }}>Welcome Invitation</option>
                                    <option value="registration"   {{ old('invitation_type') == 'registration' ? 'selected' : '' }}>Registration Invitation</option>
                                    <option value="account_setup"  {{ old('invitation_type') == 'account_setup' ? 'selected' : '' }}>Account Setup</option>
                                    <option value="password_setup" {{ old('invitation_type') == 'password_setup' ? 'selected' : '' }}>Password Setup</option>
                                </select>
                            </div>

                            <div>
                                <label for="expires_in_days" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    Invitation Expiration
                                </label>
                                <select class="w-full p-2 border rounded"
                                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                        id="expires_in_days" name="expires_in_days">
                                    <option value="1"  {{ old('expires_in_days') == '1'  ? 'selected' : '' }}>1 Day</option>
                                    <option value="3"  {{ old('expires_in_days') == '3'  ? 'selected' : '' }}>3 Days</option>
                                    <option value="7"  {{ old('expires_in_days', '7') == '7' ? 'selected' : '' }}>7 Days (Default)</option>
                                    <option value="14" {{ old('expires_in_days') == '14' ? 'selected' : '' }}>14 Days</option>
                                    <option value="30" {{ old('expires_in_days') == '30' ? 'selected' : '' }}>30 Days</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="invitation_message" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Custom Invitation Message
                            </label>
                            <textarea class="w-full p-2 border rounded"
                                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                      id="invitation_message" name="invitation_message"
                                      rows="3"
                                      maxlength="1000"
                                      placeholder="Optional message to include in the invitation email.">{{ old('invitation_message') }}</textarea>
                            <div class="flex justify-between items-center mt-1">
                                <span class="text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i> Max 1000 characters.
                                </span>
                                <span class="text-xs" style="color: var(--text-secondary);">
                                    <span id="char-count">0</span>/1000
                                </span>
                            </div>
                        </div>

                        {{-- ✅ Email service details card (mirrors admin.users.create) --}}
                        <div id="email-service-details"
                             class="rounded p-3"
                             style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3); border-left: 4px solid var(--info);">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                <div class="flex items-center">
                                    <i class="fas fa-server mr-2" style="color: var(--info);"></i>
                                    <div>
                                        <span class="font-medium" style="color: var(--text-secondary);">Provider:</span>
                                        <span class="ml-1" style="color: var(--text-primary);">
                                            {{ $emailStatus['provider'] ?? 'Unknown' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-heartbeat mr-2" style="color: {{ $emailHealthIconColor }};"></i>
                                    <div>
                                        <span class="font-medium" style="color: var(--text-secondary);">Status:</span>
                                        <span class="ml-1 capitalize {{ $emailHealthClass }}">
                                            {{ $emailHealth }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-chart-line mr-2" style="color: var(--info);"></i>
                                    <div>
                                        <span class="font-medium" style="color: var(--text-secondary);">Success Rate:</span>
                                        <span class="ml-1" style="color: var(--text-primary);">
                                            {{ $emailStatus['statistics']['success_rate'] ?? 0 }}%
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-envelope-open mr-2" style="color: var(--info);"></i>
                                    <div>
                                        <span class="font-medium" style="color: var(--text-secondary);">Today's Usage:</span>
                                        <span class="ml-1" style="color: var(--text-primary);">
                                            {{ $emailStatus['limits']['daily_limit']['used_today'] ?? 0 }}/{{ $emailStatus['limits']['daily_limit']['max_emails_per_day'] ?? 0 }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded p-3"
                             style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
                            <div class="text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-2" style="color: var(--success);"></i>
                                A valid email address is <strong>required</strong> when sending an invitation.
                                The <em>Email Address</em> field above will be used.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-center mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                    {{-- ✅ Live form status — mirrors admin.users.create --}}
                    <div class="text-sm" style="color: var(--text-secondary);" id="form-status">
                        <!-- Form status messages will appear here -->
                    </div>
                    <div class="flex space-x-3">
                        <a href="{{ route('sanitation.personnel.index') }}" class="btn-secondary">Cancel</a>
                        <button type="submit"
                                class="btn-primary"
                                id="submit-button"
                                @if(!$isAdmin && (!$creatorPersonnel || !$creatorPersonnel->isSupervisor())) disabled @endif>
                            <i class="fas fa-save mr-2"></i> Create Personnel
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

{{-- ============================================================ --}}
{{-- SCRIPT: invitation toggle + validation (email only) --}}
{{-- ============================================================ --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const emailReady = {{ $emailReady ? 'true' : 'false' }};

    const sendYes      = document.getElementById('send_invitation_yes');
    const sendNo       = document.getElementById('send_invitation_no');
    const details      = document.getElementById('invitation-details');
    const emailInput   = document.getElementById('email');
    const statusSelect = document.getElementById('status');
    const form         = document.getElementById('personnel-create-form');
    const message      = document.getElementById('invitation_message');
    const charCount    = document.getElementById('char-count');
    const formStatus   = document.getElementById('form-status');

    // ✅ Email required-marker elements
    const emailStar        = document.getElementById('email-required-star');
    const emailHintText    = document.getElementById('email-required-hint-text');

    function refreshRadioStyles() {
        [sendYes, sendNo].forEach(r => {
            if (!r) return;
            const opt = r.closest('.modern-radio-option');
            if (!opt) return;
            if (r.checked) opt.classList.add('modern-radio-option--checked');
            else opt.classList.remove('modern-radio-option--checked');
        });
    }

    function refreshEmailRequiredState(inviteOn) {
        if (!emailInput) return;

        emailInput.dataset.inviteRequired = inviteOn ? '1' : '0';

        if (inviteOn) {
            emailInput.setAttribute('required', 'required');
        } else {
            emailInput.removeAttribute('required');
        }

        if (emailStar) {
            emailStar.classList.toggle('hidden', !inviteOn);
        }

        if (emailHintText) {
            emailHintText.textContent = inviteOn
                ? 'Required — an invitation will be sent to this address.'
                : 'Optional — only needed if you plan to send an email invitation.';
        }
    }

    function validateEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test((value || '').trim());
    }

    /**
     * ✅ Live form status — mirrors admin.users.create's updateFormStatus()
     */
    function updateFormStatus() {
        if (!formStatus) return;

        const inviteOn = !!(sendYes && sendYes.checked);
        const userEmail = (emailInput?.value || '').trim();
        const isEmailValid = userEmail && validateEmail(userEmail);

        let statusMessage = '';
        let iconClass = 'fa-exclamation-triangle text-yellow-600';

        if (!emailReady) {
            statusMessage = 'Email service unavailable. Personnel will be created without an invitation.';
        } else if (!inviteOn) {
            statusMessage = 'Personnel will be created without an invitation.';
            iconClass = 'fa-user-check text-green-600';
        } else if (!isEmailValid) {
            statusMessage = 'Enter a valid email address to send the invitation.';
        } else {
            statusMessage = `Personnel will be created and an invitation sent via EMAIL to ${userEmail}.`;
            iconClass = 'fa-user-check text-green-600';
        }

        formStatus.innerHTML = `<div class="flex items-center">
            <i class="fas ${iconClass} mr-2"></i>
            <span>${statusMessage}</span>
        </div>`;
    }

    function toggleInvitationDetails() {
        if (!emailReady) {
            if (sendYes) sendYes.checked = false;
            if (sendNo)  sendNo.checked  = true;
            details.classList.add('hidden');
            refreshEmailRequiredState(false);
            refreshRadioStyles();
            updateFormStatus();
            return;
        }

        if (sendYes && sendYes.checked) {
            details.classList.remove('hidden');
            if (statusSelect) statusSelect.value = 'pending';
            refreshEmailRequiredState(true);
        } else {
            details.classList.add('hidden');
            refreshEmailRequiredState(false);
        }
        refreshRadioStyles();
        updateFormStatus();
    }

    if (sendYes) sendYes.addEventListener('change', toggleInvitationDetails);
    if (sendNo)  sendNo.addEventListener('change',  toggleInvitationDetails);

    // ✅ Live-update the status line as the user types an email
    if (emailInput) {
        emailInput.addEventListener('input', updateFormStatus);
    }

    if (message && charCount) {
        const updateCount = () => { charCount.textContent = message.value.length; };
        message.addEventListener('input', updateCount);
        updateCount();
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            if (!emailReady) return;
            if (sendYes && sendYes.checked) {
                if (!validateEmail(emailInput ? emailInput.value : '')) {
                    e.preventDefault();
                    alert('A valid email address is required to send an invitation.');
                    if (emailInput) emailInput.focus();
                }
            }
        });
    }

    // Run once on load — syncs visual + required + status with server-rendered state
    toggleInvitationDetails();
});
</script>

<style>
/* Modern radio card (email invitation toggle) */
.modern-radio-option {
    position: relative;
    transition: all 0.3s ease;
    border: 2px solid var(--border-color);
    border-radius: 12px;
    background-color: var(--bg-secondary);
    cursor: pointer;
    overflow: hidden;
}
.modern-radio-option:hover:not(.modern-radio-option--disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    border-color: var(--primary);
}
.modern-radio-option--checked {
    border-color: var(--primary) !important;
    background: linear-gradient(135deg, rgba(114, 103, 240, 0.1), rgba(114, 103, 240, 0.05)) !important;
    box-shadow: 0 8px 25px rgba(114, 103, 240, 0.15) !important;
    transform: translateY(-2px);
}
.modern-radio-option--disabled {
    opacity: 0.55;
    cursor: not-allowed !important;
}
.modern-radio-option--disabled .modern-radio-label { cursor: not-allowed; }
.modern-radio-input {
    position: absolute; opacity: 0; width: 0; height: 0;
}
.modern-radio-label { display: block; cursor: pointer; padding: 0; margin: 0; }
.modern-radio-content { display: flex; align-items: center; padding: 16px; position: relative; }
.modern-radio-icon {
    width: 44px; height: 44px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    margin-right: 14px; font-size: 18px; transition: all 0.3s ease;
    background: var(--bg-tertiary);
}
.modern-radio-option--checked .modern-radio-icon {
    background: linear-gradient(135deg, var(--primary), var(--secondary)) !important;
    color: white !important;
    transform: scale(1.05);
}
.modern-radio-text { flex: 1; }
.modern-radio-title { font-weight: 600; font-size: 15px; color: var(--text-primary); margin-bottom: 2px; }
.modern-radio-description { font-size: 13px; color: var(--text-secondary); line-height: 1.4; }
.modern-radio-check {
    width: 22px; height: 22px; border-radius: 50%;
    border: 2px solid var(--border-color);
    display: flex; align-items: center; justify-content: center;
    transition: all 0.3s ease; background: white;
}
.modern-radio-option--checked .modern-radio-check {
    background: var(--primary) !important;
    border-color: var(--primary) !important;
    color: white !important;
    transform: scale(1.05);
}
.modern-radio-check i { font-size: 11px; opacity: 0; transition: opacity 0.3s ease; }
.modern-radio-option--checked .modern-radio-check i { opacity: 1 !important; }

.hidden { display: none !important; }

/* Text colour helpers used above (mirror admin blade) */
.text-green-600  { color: #16a34a; }
.text-yellow-600 { color: #ca8a04; }
.text-red-600    { color: #dc2626; }
</style>