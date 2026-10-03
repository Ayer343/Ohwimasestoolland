{{-- resources/views/sanitation/properties/link.blade.php --}}

@php
    $user    = auth()->user();
    $isAdmin = $user->isAdmin() || $user->isSuperAdmin();
    $layout  = $isAdmin ? 'layouts.app' : 'layouts.san';

    // -----------------------------------------------------------------
    // ✅ FIXED: Determine whether the CURRENT user may LINK properties.
    //
    // Rule (mirrors SanitationController::canCurrentUserLinkProperties):
    //   - Admin / Super Admin → allowed
    //   - Sanitation personnel → must be a SUPERVISOR (root OR sub)
    //     whose creator is either an admin OR another supervisor.
    //   - Workers / drivers / others → denied.
    // -----------------------------------------------------------------
    if (!isset($canLinkProperties)) {
        $personnel = $user->sanitationPersonnel;

        $canLinkProperties = false;

        if ($user->isAdmin() || $user->isSuperAdmin()) {
            $canLinkProperties = true;
        } elseif ($personnel && method_exists($personnel, 'isSupervisor') && $personnel->isSupervisor()) {
            $meta = $personnel->metadata ?? [];
            if (is_string($meta)) {
                $meta = json_decode($meta, true) ?: [];
            }

            $createdById = is_array($meta) ? ($meta['created_by'] ?? null) : null;

            if ($createdById) {
                $creator = \App\Models\User::find($createdById);

                if ($creator) {
                    if ($creator->isAdmin() || $creator->isSuperAdmin()) {
                        $canLinkProperties = true;
                    } elseif ($creator->sanitationPersonnel
                        && method_exists($creator->sanitationPersonnel, 'isSupervisor')
                        && $creator->sanitationPersonnel->isSupervisor()) {
                        $canLinkProperties = true;
                    }
                }
            }
        }
    }

    // ✅ Get sanitation settings for emergency contact and company details
    $sanitationSettings = \App\Models\SanitationSetting::getSettings();
@endphp

@extends($layout)

@section('title', 'Link Property to Waste Collection')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-4xl mx-auto">

        {{-- ✅ HARD GATE: If user cannot link, show a graceful "not authorized"
             screen instead of the form. The controller already 403s — this
             is the friendly UI equivalent. --}}
        @unless($canLinkProperties)
            <div class="card p-8 text-center">
                <div class="flex flex-col items-center gap-3">
                    <i class="fas fa-lock text-5xl" style="color: var(--text-secondary); opacity: 0.5;"></i>
                    <h2 class="text-xl font-bold mt-2" style="color: var(--text-primary);">
                        Not Authorized to Link Properties
                    </h2>
                    <p class="text-sm max-w-md" style="color: var(--text-secondary);">
                        Only sanitation supervisors created by an administrator
                        or another supervisor can link properties to waste collection.
                        If you need linking rights, contact your administrator.
                    </p>
                    <div class="flex flex-wrap gap-2 mt-4 justify-center">
                        <a href="{{ route('sanitation.properties.available') }}" class="btn-secondary">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Available Properties
                        </a>
                        @if($property)
                            <a href="{{ route('sanitation.properties.show', $property) }}" class="btn-secondary">
                                <i class="fas fa-eye mr-2"></i> View Property
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Debug footer for admins --}}
            @if($isAdmin)
                <div class="card p-3 mt-6" style="background-color: #fef3c7; border-color: #f59e0b;">
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-bug mr-1 text-yellow-600"></i>
                        Linking rights for current user:
                        <strong style="color: #dc2626;">DENIED</strong>
                    </p>
                </div>
            @endif
        @else
            {{-- ✅ AUTHORIZED: full form --}}

            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-3xl font-bold" style="color: var(--text-primary);">
                        <i class="fas fa-link mr-2" style="color: var(--primary);"></i>
                        Link Property to Waste Collection
                    </h1>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Link {{ $property->property_name }} to waste collection services
                    </p>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="{{ route('sanitation.properties.available') }}" class="btn-secondary">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>
                </div>
            </div>

            <!-- ⚠️ Approval Notice -->
            <div class="card p-4 mb-6" style="background-color: rgba(245, 158, 11, 0.1); border-color: #f59e0b;">
                <div class="flex items-start gap-3">
                    <i class="fas fa-info-circle text-yellow-500 text-xl mt-1"></i>
                    <div>
                        <h4 class="font-semibold text-sm" style="color: var(--text-primary);">
                            <i class="fas fa-user-check mr-1"></i> Landlord Approval Required
                        </h4>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            When you link this property, the landlord will receive a notification and must
                            <strong>approve</strong> the waste collection request before services can begin.
                            The landlord has <strong>7 days</strong> to respond.
                        </p>
                        <div class="flex items-center gap-4 mt-2 text-xs">
                            <span style="color: var(--text-secondary);">
                                <i class="fas fa-bell mr-1" style="color: var(--primary);"></i>
                                Landlord gets notified
                            </span>
                            <span style="color: var(--text-secondary);">
                                <i class="fas fa-clock mr-1" style="color: var(--warning);"></i>
                                7 days to approve
                            </span>
                            <span style="color: var(--text-secondary);">
                                <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                                Auto-approves if no response
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Property Info -->
            <div class="card p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <h3 class="font-semibold text-sm" style="color: var(--text-secondary);">Property Name</h3>
                        <p class="text-lg font-medium" style="color: var(--text-primary);">{{ $property->property_name }}</p>
                    </div>
                    <div>
                        <h3 class="font-semibold text-sm" style="color: var(--text-secondary);">Landlord</h3>
                        <p class="text-lg font-medium" style="color: var(--text-primary);">
                            {{ $property->landlord->name ?? 'N/A' }}
                            @if($property->landlord)
                                <span class="text-xs ml-2 px-2 py-0.5 rounded-full" style="background-color: rgba(34, 197, 94, 0.2); color: #22c55e;">
                                    <i class="fas fa-check-circle mr-0.5"></i> Verified
                                </span>
                            @endif
                        </p>
                        @if($property->landlord)
                            <p class="text-xs" style="color: var(--text-secondary);">
                                <i class="fas fa-phone mr-1"></i> {{ $property->landlord->phone ?? 'No phone' }}
                                <span class="mx-1">|</span>
                                <i class="fas fa-envelope mr-1"></i> {{ $property->landlord->email ?? 'No email' }}
                            </p>
                        @endif
                    </div>
                    <div>
                        <h3 class="font-semibold text-sm" style="color: var(--text-secondary);">Digital Address</h3>
                        <p class="text-lg font-medium" style="color: var(--text-primary);">{{ $property->digital_address ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <h3 class="font-semibold text-sm" style="color: var(--text-secondary);">Zone</h3>
                        <p class="text-lg font-medium" style="color: var(--text-primary);">{{ $property->zone ?? 'Unassigned' }}</p>
                    </div>
                </div>
            </div>

            <!-- ✅ Sanitation Company Info Card -->
            <div class="card p-4 mb-6" style="background-color: rgba(var(--primary-rgb), 0.05); border-color: var(--border-color);">
                <div class="flex items-start gap-3">
                    <i class="fas fa-building text-xl mt-1" style="color: var(--primary);"></i>
                    <div class="flex-1">
                        <h4 class="font-semibold text-sm" style="color: var(--text-primary);">
                            {{ $sanitationSettings->company_name ?? 'Sanitation Services' }}
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mt-2 text-xs">
                            @if($sanitationSettings->company_email)
                                <div>
                                    <span style="color: var(--text-secondary);">Email:</span>
                                    <span style="color: var(--text-primary);">{{ $sanitationSettings->company_email }}</span>
                                </div>
                            @endif
                            @if($sanitationSettings->company_phone)
                                <div>
                                    <span style="color: var(--text-secondary);">Phone:</span>
                                    <span style="color: var(--text-primary);">{{ $sanitationSettings->company_phone }}</span>
                                </div>
                            @endif
                            @if($sanitationSettings->contact_person_name)
                                <div>
                                    <span style="color: var(--text-secondary);">Contact Person:</span>
                                    <span style="color: var(--text-primary);">{{ $sanitationSettings->contact_person_name }}</span>
                                </div>
                            @endif
                            @if($sanitationSettings->contact_person_phone)
                                <div>
                                    <span style="color: var(--text-secondary);">Contact Phone:</span>
                                    <span style="color: var(--text-primary);">{{ $sanitationSettings->contact_person_phone }}</span>
                                </div>
                            @endif
                        </div>
                        @if($sanitationSettings->company_address)
                            <p class="text-xs mt-2" style="color: var(--text-secondary);">
                                <i class="fas fa-map-marker-alt mr-1"></i> {{ $sanitationSettings->company_address }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Link Form -->
            <div class="card p-6">
                <form method="POST" action="{{ route('sanitation.properties.link.store', $property) }}" id="linkForm">
                    @csrf

                    <div class="space-y-6">
                        <!-- Collection Zone -->
                        <div>
                            <label for="collection_zone_id" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-map-pin mr-1" style="color: var(--primary);"></i>
                                Collection Zone
                            </label>
                            <select name="collection_zone_id" id="collection_zone_id"
                                    class="w-full p-2 border rounded-lg @error('collection_zone_id') border-red-500 @enderror"
                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                <option value="">Select Zone</option>
                                @foreach($zones as $zone)
                                    <option value="{{ $zone->id }}" {{ old('collection_zone_id') == $zone->id ? 'selected' : '' }}>
                                        {{ $zone->name }}
                                        @if($zone->code)
                                            ({{ $zone->code }})
                                        @endif
                                        @if($zone->assignedPersonnel)
                                            - {{ $zone->assignedPersonnel->full_name }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('collection_zone_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Collection Frequency -->
                        <div>
                            <label for="collection_frequency" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                Collection Frequency <span class="text-red-500">*</span>
                            </label>
                            <select name="collection_frequency" id="collection_frequency"
                                    class="w-full p-2 border rounded-lg @error('collection_frequency') border-red-500 @enderror"
                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                <option value="">Select Frequency</option>
                                <option value="daily" {{ old('collection_frequency') == 'daily' ? 'selected' : '' }}>Daily</option>
                                <option value="weekly" {{ old('collection_frequency') == 'weekly' ? 'selected' : '' }}>Weekly</option>
                                <option value="biweekly" {{ old('collection_frequency') == 'biweekly' ? 'selected' : '' }}>Bi-Weekly</option>
                                <option value="monthly" {{ old('collection_frequency') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                            </select>
                            @error('collection_frequency')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Collection Days -->
                        <div>
                            <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-calendar-alt mr-1" style="color: var(--primary);"></i>
                                Collection Days
                            </label>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                @foreach(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day)
                                    <label class="flex items-center space-x-2 p-1 rounded hover:bg-opacity-10 transition-colors duration-200" style="cursor: pointer;">
                                        <input type="checkbox" name="collection_days[]" value="{{ $day }}"
                                               {{ (is_array(old('collection_days')) && in_array($day, old('collection_days'))) ? 'checked' : '' }}
                                               class="w-4 h-4 border-gray-300 rounded focus:ring-blue-500" style="color: var(--primary);">
                                        <span class="text-sm" style="color: var(--text-primary);">{{ $day }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('collection_days')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Preferred Time -->
                        <div>
                            <label for="preferred_time" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-clock mr-1" style="color: var(--primary);"></i>
                                Preferred Time
                            </label>
                            <input type="text" name="preferred_time" id="preferred_time"
                                   value="{{ old('preferred_time') }}"
                                   class="w-full p-2 border rounded-lg @error('preferred_time') border-red-500 @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   placeholder="e.g., Morning, 8:00 AM - 12:00 PM">
                            @error('preferred_time')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Waste Types -->
                        <div>
                            <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-trash-alt mr-1" style="color: var(--primary);"></i>
                                Waste Types
                            </label>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                @foreach(['general' => 'General Waste', 'recyclable' => 'Recyclable', 'organic' => 'Organic', 'hazardous' => 'Hazardous', 'bulk' => 'Bulk Waste'] as $type => $label)
                                    <label class="flex items-center space-x-2 p-1 rounded hover:bg-opacity-10 transition-colors duration-200" style="cursor: pointer;">
                                        <input type="checkbox" name="waste_types[]" value="{{ $type }}"
                                               {{ (is_array(old('waste_types')) && in_array($type, old('waste_types'))) ? 'checked' : '' }}
                                               class="w-4 h-4 border-gray-300 rounded focus:ring-blue-500" style="color: var(--primary);">
                                        <span class="text-sm" style="color: var(--text-primary);">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('waste_types')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Assigned Personnel -->
                        <div>
                            <label for="assigned_personnel_id" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-user-tie mr-1" style="color: var(--primary);"></i>
                                Assigned Personnel
                            </label>
                            <select name="assigned_personnel_id" id="assigned_personnel_id"
                                    class="w-full p-2 border rounded-lg @error('assigned_personnel_id') border-red-500 @enderror"
                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                <option value="">Select Personnel</option>
                                @foreach($personnel as $person)
                                    <option value="{{ $person->id }}" {{ old('assigned_personnel_id') == $person->id ? 'selected' : '' }}>
                                        {{ $person->full_name }}
                                        <span class="text-xs" style="color: var(--text-secondary);">
                                            ({{ ucfirst($person->role) }})
                                            @if($person->active_jobs_count > 0)
                                                - {{ $person->active_jobs_count }} active jobs
                                            @endif
                                        </span>
                                    </option>
                                @endforeach
                            </select>
                            @error('assigned_personnel_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Assigned Worker -->
                        <div>
                            <label for="assigned_worker_id" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-user-hard-hat mr-1" style="color: var(--primary);"></i>
                                Assigned Worker
                            </label>
                            <select name="assigned_worker_id" id="assigned_worker_id"
                                    class="w-full p-2 border rounded-lg @error('assigned_worker_id') border-red-500 @enderror"
                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                                <option value="">Select Worker</option>
                                @foreach($workers as $worker)
                                    <option value="{{ $worker->id }}" {{ old('assigned_worker_id') == $worker->id ? 'selected' : '' }}>
                                        {{ $worker->full_name }}
                                        @if($worker->supervisor)
                                            <span class="text-xs" style="color: var(--text-secondary);">
                                                (Supervisor: {{ $worker->supervisor->full_name }})
                                            </span>
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('assigned_worker_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Special Instructions -->
                        <div>
                            <label for="special_instructions" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-sticky-note mr-1" style="color: var(--primary);"></i>
                                Special Instructions
                            </label>
                            <textarea name="special_instructions" id="special_instructions" rows="3"
                                      class="w-full p-2 border rounded-lg @error('special_instructions') border-red-500 @enderror"
                                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                      placeholder="Any special instructions for waste collection...">{{ old('special_instructions') }}</textarea>
                            @error('special_instructions')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- ✅ Emergency Contact - Now populated from Sanitation Settings -->
                        <div>
                            <label for="emergency_contact" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-phone-alt mr-1" style="color: var(--danger);"></i>
                                Emergency Contact
                            </label>
                            <div class="relative">
                                <input type="text" name="emergency_contact" id="emergency_contact"
                                       value="{{ old('emergency_contact', $sanitationSettings->contact_person_name ? $sanitationSettings->contact_person_name . ': ' . $sanitationSettings->contact_person_phone : '') }}"
                                       class="w-full p-2 border rounded-lg @error('emergency_contact') border-red-500 @enderror"
                                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                       placeholder="Name: Phone Number (e.g., John Doe: +233241234567)">
                                @if($sanitationSettings->contact_person_name && $sanitationSettings->contact_person_phone)
                                    <div class="mt-1 text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Default: {{ $sanitationSettings->contact_person_name }} ({{ $sanitationSettings->contact_person_phone }})
                                    </div>
                                @endif
                            </div>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Contact person in case of emergency during collection
                            </p>
                            @error('emergency_contact')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- ✅ Company Emergency Contacts from Settings -->
                        @if($sanitationSettings->emergency_contacts && count($sanitationSettings->emergency_contacts) > 0)
                            <div>
                                <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-phone mr-1" style="color: var(--danger);"></i>
                                    Company Emergency Contacts
                                </label>
                                <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                                    @foreach($sanitationSettings->emergency_contacts as $contact)
                                        <div class="flex items-center gap-2 text-sm py-1">
                                            <i class="fas fa-user-circle" style="color: var(--primary);"></i>
                                            <span style="color: var(--text-primary);">
                                                {{ $contact['name'] ?? 'Contact' }}
                                                @if(isset($contact['phone']))
                                                    <span class="ml-2" style="color: var(--text-secondary);">
                                                        <i class="fas fa-phone mr-1"></i> {{ $contact['phone'] }}
                                                    </span>
                                                @endif
                                                @if(isset($contact['relationship']))
                                                    <span class="ml-2 text-xs" style="color: var(--text-secondary);">
                                                        ({{ $contact['relationship'] }})
                                                    </span>
                                                @endif
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Notes -->
                        <div>
                            <label for="notes" class="block mb-2 font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-pen mr-1" style="color: var(--text-secondary);"></i>
                                Additional Notes
                            </label>
                            <textarea name="notes" id="notes" rows="3"
                                      class="w-full p-2 border rounded-lg @error('notes') border-red-500 @enderror"
                                      style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                      placeholder="Additional notes for the collection team...">{{ old('notes') }}</textarea>
                            @error('notes')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Declaration -->
                        <div class="p-4 rounded-lg border" style="background-color: rgba(var(--info-rgb), 0.05); border-color: var(--border-color);">
                            <label class="flex items-start space-x-3 cursor-pointer">
                                <input type="checkbox" name="declaration" value="1"
                                       {{ old('declaration') ? 'checked' : '' }}
                                       class="mt-1 w-4 h-4 border-gray-300 rounded focus:ring-blue-500" style="color: var(--primary);">
                                <div>
                                    <span class="font-medium" style="color: var(--text-primary);">
                                        <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                                        I confirm that all information provided is accurate
                                    </span>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                        I understand that this property will be linked to waste collection services and
                                        the landlord will be notified for approval. The landlord has 7 days to respond.
                                    </p>
                                </div>
                            </label>
                            @error('declaration')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Submit -->
                        <div class="flex justify-end space-x-3 pt-4 border-t" style="border-color: var(--border-color);">
                            <a href="{{ route('sanitation.properties.available') }}" class="btn-secondary">
                                <i class="fas fa-times mr-2"></i> Cancel
                            </a>
                            <button type="submit" class="btn-primary" id="submitBtn">
                                <i class="fas fa-paper-plane mr-2"></i> Submit for Approval
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Debug footer for admins --}}
            @if($isAdmin)
                <div class="card p-3 mt-6" style="background-color: #fef3c7; border-color: #f59e0b;">
                    <p class="text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-bug mr-1 text-yellow-600"></i>
                        Linking rights for current user:
                        <strong style="color: #16a34a;">GRANTED (admin, or supervisor created by admin/supervisor)</strong>
                    </p>
                </div>
            @endif
        @endunless
    </div>
</div>
@endsection

@push('styles')
<style>
    .btn-primary {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1.5rem;
        background-color: var(--primary);
        color: white;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
        text-decoration: none;
    }
    .btn-primary:hover {
        opacity: 0.9;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        color: white;
        text-decoration: none;
    }
    .btn-secondary {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1.5rem;
        background-color: var(--bg-secondary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s;
        cursor: pointer;
        text-decoration: none;
    }
    .btn-secondary:hover {
        background-color: var(--bg-secondary);
        opacity: 0.8;
        text-decoration: none;
        color: var(--text-primary);
    }
    .card {
        background-color: var(--card-bg);
        border-radius: 0.75rem;
        border: 1px solid var(--border-color);
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    }
    input[type="checkbox"] {
        accent-color: var(--primary);
        cursor: pointer;
    }
    select, input, textarea {
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    select:focus, input:focus, textarea:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
        outline: none;
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('linkForm');
        const submitBtn = document.getElementById('submitBtn');

        // ✅ If the form isn't rendered (unauthorized view), bail out.
        if (!form || !submitBtn) {
            return;
        }

        // Prevent double submission
        form.addEventListener('submit', function(e) {
            if (submitBtn.disabled) {
                e.preventDefault();
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...';

            // Re-enable after 10 seconds if something goes wrong
            setTimeout(function() {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i> Submit for Approval';
            }, 10000);
        });

        // Collection frequency validation
        const frequencySelect = document.getElementById('collection_frequency');
        frequencySelect?.addEventListener('change', function() {
            const daysContainer = document.querySelector('input[name="collection_days[]"]')?.closest('.grid');
            if (this.value === '') {
                daysContainer?.style.setProperty('opacity', '0.5');
            } else {
                daysContainer?.style.setProperty('opacity', '1');
            }
        });

        // Toggle checklist items on label click
        document.querySelectorAll('.grid label').forEach(label => {
            label.addEventListener('click', function(e) {
                if (e.target.tagName === 'INPUT') return;
                const checkbox = this.querySelector('input[type="checkbox"]');
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;
                }
            });
        });
    });
</script>
@endpush