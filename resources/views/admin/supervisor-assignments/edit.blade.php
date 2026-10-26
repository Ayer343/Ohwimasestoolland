@extends('layouts.app')

@section('title', 'Edit Head of Security Appointment')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--warning) 0%, var(--primary) 100%); color: white; border-color: var(--primary);">
                        <i class="fas fa-user-edit text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-edit mr-2" style="color: var(--primary);"></i>
                        Edit Head of Security Appointment
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>Officer:</span>
                        <span class="mx-1 font-semibold" style="color: var(--primary);">
                            {{ optional($assignment->user)->name ?? 'Deleted User' }}
                        </span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-calendar-alt mr-1"></i>
                        <span>Appointed: {{ $assignment->created_at?->format('M d, Y') }}</span>
                        <span class="mx-2">•</span>
                        @if($assignment->is_active && (!$assignment->end_date || $assignment->end_date->isFuture()))
                            <span class="px-2 py-0.5 rounded-full text-xs"
                                  style="background-color: rgba(var(--success-rgb), 0.15); color: var(--success);">
                                <i class="fas fa-crown mr-1"></i> Active Head
                            </span>
                        @elseif($assignment->end_date && $assignment->end_date->isPast())
                            <span class="px-2 py-0.5 rounded-full text-xs"
                                  style="background-color: rgba(var(--secondary-rgb), 0.15); color: var(--text-secondary);">
                                <i class="fas fa-hourglass-end mr-1"></i> Expired
                            </span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-xs"
                                  style="background-color: rgba(var(--warning-rgb), 0.15); color: var(--warning);">
                                <i class="fas fa-pause-circle mr-1"></i> Inactive
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.supervisor-assignments.show', $assignment) }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Details
                </a>
            </div>
        </div>
    </div>

    <!-- Inactive banner -->
    @if(!$assignment->is_active)
        <div class="card p-4"
             style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-lg mr-3" style="color: var(--warning);"></i>
                <div>
                    <span class="font-medium" style="color: var(--warning);">This appointment is currently inactive.</span>
                    <span class="text-sm ml-2" style="color: var(--text-secondary);">
                        Flip the "Active" toggle below to reactivate it — provided no other head is currently active.
                    </span>
                </div>
            </div>
        </div>
    @endif

    <!-- Expired banner -->
    @if($assignment->end_date && $assignment->end_date->isPast())
        <div class="card p-4"
             style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3);">
            <div class="flex items-center">
                <i class="fas fa-clock text-lg mr-3" style="color: var(--info);"></i>
                <div>
                    <span class="font-medium" style="color: var(--info);">This appointment has expired.</span>
                    <span class="text-sm ml-2" style="color: var(--text-secondary);">
                        Update the end date to re-extend the appointment.
                    </span>
                </div>
            </div>
        </div>
    @endif

    <!-- Validation errors -->
    @if($errors->any())
        <div class="card p-4"
             style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
            <div class="flex items-start">
                <i class="fas fa-exclamation-circle text-lg mt-0.5" style="color: var(--danger);"></i>
                <div class="ml-3">
                    <h3 class="text-sm font-medium" style="color: var(--danger);">Validation Error!</h3>
                    <ul class="mt-2 list-disc pl-5 space-y-1 text-sm" style="color: var(--text-secondary);">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <form action="{{ route('admin.supervisor-assignments.update', $assignment) }}"
          method="POST" id="assignmentForm">
        @csrf
        @method('PUT')

        <!-- Officer + Period -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Officer / Title / Status -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-shield mr-2" style="color: var(--primary);"></i>
                        Officer
                    </h3>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label for="user_id" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-user-shield mr-1" style="color: var(--primary);"></i>
                            Head of Security <span style="color: var(--danger);">*</span>
                        </label>
                        <select name="user_id" id="user_id"
                                class="index-custom-dropdown w-full @error('user_id') is-invalid @enderror"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                required>
                            <option value="">-- Select Officer --</option>
                            @foreach($supervisors as $supervisor)
                                <option value="{{ $supervisor->id }}"
                                        data-name="{{ $supervisor->name }}"
                                        data-email="{{ $supervisor->email }}"
                                        {{ old('user_id', $assignment->user_id) == $supervisor->id ? 'selected' : '' }}>
                                    {{ $supervisor->name }}
                                    @if($supervisor->email)
                                        ({{ $supervisor->email }})
                                    @endif
                                </option>
                            @endforeach
                        </select>

                        <div id="supervisorPreview"
                             class="mt-3 p-3 rounded-lg {{ $assignment->user_id ? '' : 'hidden' }}"
                             style="background-color: rgba(var(--info-rgb), 0.05); border-left: 3px solid var(--info);">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium" style="color: var(--text-secondary);">Selected officer:</span>
                                <div class="flex space-x-2">
                                    <span class="px-2 py-1 text-xs rounded-full badge-info" id="previewName">
                                        {{ optional($assignment->user)->name ?? 'Unknown' }}
                                    </span>
                                    <span class="px-2 py-1 text-xs rounded-full badge-success" id="previewEmail">
                                        {{ optional($assignment->user)->email ?? 'No email' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Only active security officers with <strong>Can be appointed Head of Security</strong>
                            enabled are listed. Manage eligibility in
                            <a href="{{ route('admin.users.index') }}" class="text-primary hover:underline">User Management</a>.
                        </div>
                    </div>

                    <div>
                        <label for="title" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-tag mr-1" style="color: var(--primary);"></i>
                            Title <span class="text-xs" style="color: var(--text-secondary);">(optional)</span>
                        </label>
                        <input type="text" name="title" id="title"
                               class="index-custom-input w-full @error('title') is-invalid @enderror"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               value="{{ old('title', $assignment->title ?: 'Head of Security') }}"
                               placeholder="Head of Security">
                        <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                            Default: <strong>Head of Security</strong>. Override to use a different label
                            (e.g., <em>Acting Head</em>).
                        </p>
                    </div>

                    <div class="pt-2">
                        <div class="flex items-center justify-between p-4 rounded-lg"
                             style="background-color: var(--bg-secondary);">
                            <div class="flex items-start">
                                <i class="fas fa-power-off mr-3 mt-1"
                                   style="color: var({{ $assignment->is_active ? '--success' : '--danger' }});"></i>
                                <div>
                                    <label for="is_active" class="text-sm font-medium cursor-pointer" style="color: var(--text-primary);">
                                        Appointment Active
                                    </label>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Flip off to deactivate the appointment without deleting the record.
                                        Only one active head is allowed at a time.
                                    </p>
                                </div>
                            </div>
                            <div class="toggle-modern flex-shrink-0 ml-4">
                                <input type="checkbox" name="is_active" id="is_active" value="1"
                                       {{ old('is_active', $assignment->is_active) ? 'checked' : '' }} class="sr-only">
                                <label for="is_active" class="toggle-slider"></label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Period -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-calendar-alt mr-2" style="color: var(--primary);"></i>
                        Assignment Period
                    </h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="start_date" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-play-circle mr-1" style="color: var(--success);"></i>
                                Start Date <span style="color: var(--danger);">*</span>
                            </label>
                            <input type="date" name="start_date" id="start_date"
                                   class="index-custom-input w-full @error('start_date') is-invalid @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   value="{{ old('start_date', $assignment->start_date?->format('Y-m-d')) }}"
                                   required>
                        </div>
                        <div>
                            <label for="end_date" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-stop-circle mr-1" style="color: var(--warning);"></i>
                                End Date
                            </label>
                            <input type="date" name="end_date" id="end_date"
                                   class="index-custom-input w-full @error('end_date') is-invalid @enderror"
                                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                                   value="{{ old('end_date', $assignment->end_date?->format('Y-m-d')) }}"
                                   min="{{ old('start_date', $assignment->start_date?->format('Y-m-d')) }}">
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">Leave empty for indefinite</p>
                        </div>
                    </div>

                    <!-- Duration display -->
                    <div class="mt-2 p-3 rounded-lg"
                         style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--border-color);">
                        <div class="flex items-center">
                            <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                            <span class="text-sm" style="color: var(--text-primary);" id="durationDisplay">
                                @php
                                    $start = $assignment->start_date;
                                    $end   = $assignment->end_date;
                                    if ($start && $end) {
                                        $days = (int) $start->diffInDays($end);
                                        echo "Duration: {$days} day(s) ({$start->format('Y-m-d')} → {$end->format('Y-m-d')})";
                                    } else {
                                        echo 'Duration: Indefinite (no end date)';
                                    }
                                @endphp
                            </span>
                        </div>
                    </div>

                    <!-- Time elapsed -->
                    @if($assignment->start_date && $assignment->start_date->isPast())
                        <div class="mt-2 p-3 rounded-lg"
                             style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid var(--border-color);">
                            <div class="flex items-center">
                                <i class="fas fa-hourglass-half mr-2" style="color: var(--success);"></i>
                                <span class="text-sm" style="color: var(--text-primary);">
                                    Appointed {{ $assignment->start_date->diffForHumans(now(), true) }} ago
                                </span>
                            </div>
                        </div>
                    @endif

                    <!-- Presets -->
                    <div class="flex flex-wrap gap-2 mt-2">
                        <span class="text-xs" style="color: var(--text-secondary);">Quick set:</span>
                        <button type="button" class="px-2 py-1 text-xs rounded duration-preset" data-days="7"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                            1 Week
                        </button>
                        <button type="button" class="px-2 py-1 text-xs rounded duration-preset" data-days="30"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                            1 Month
                        </button>
                        <button type="button" class="px-2 py-1 text-xs rounded duration-preset" data-days="90"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                            3 Months
                        </button>
                        <button type="button" class="px-2 py-1 text-xs rounded duration-preset" data-days="365"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                            1 Year
                        </button>
                        <button type="button" class="px-2 py-1 text-xs rounded duration-preset" data-days="0"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                            Indefinite
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div class="card mt-6">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-sticky-note mr-2" style="color: var(--primary);"></i>
                    Notes
                </h3>
            </div>
            <div class="p-6">
                <label for="notes" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-pen mr-1" style="color: var(--primary);"></i>
                    Appointment Notes
                </label>
                <textarea name="notes" id="notes" rows="4"
                          class="index-custom-input w-full @error('notes') is-invalid @enderror"
                          style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                          placeholder="Any special instructions or context for this appointment...">{{ old('notes', $assignment->notes) }}</textarea>
                <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1"></i>
                    Optional. Recorded in the appointment's audit trail.
                </p>

                <!-- Summary -->
                <div class="mt-4 p-4 rounded-lg"
                     style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--border-color);">
                    <h4 class="text-sm font-semibold mb-2 flex items-center" style="color: var(--info);">
                        <i class="fas fa-list-alt mr-2"></i> Summary
                    </h4>
                    <div id="assignmentSummary" class="text-sm space-y-1" style="color: var(--text-secondary);">
                        <!-- Filled by JS -->
                    </div>
                </div>

                <!-- Update history -->
                @if(!empty($assignment->metadata['update_history']))
                    <div class="mt-4">
                        <details class="text-sm">
                            <summary class="cursor-pointer font-medium" style="color: var(--text-secondary);">
                                <i class="fas fa-history mr-1"></i>
                                Update History ({{ count($assignment->metadata['update_history']) }})
                            </summary>
                            <div class="mt-2 space-y-2 pl-2">
                                @foreach(array_reverse($assignment->metadata['update_history']) as $update)
                                    <div class="p-2 rounded text-xs" style="background-color: var(--bg-secondary);">
                                        <div class="flex justify-between">
                                            <span class="font-medium" style="color: var(--text-primary);">
                                                {{ $update['updated_by_name'] ?? 'Unknown' }}
                                            </span>
                                            <span style="color: var(--text-secondary);">
                                                {{ \Carbon\Carbon::parse($update['updated_at'])->diffForHumans() }}
                                            </span>
                                        </div>
                                        @if(!empty($update['changes']))
                                            <div class="mt-1" style="color: var(--text-secondary);">
                                                Updated {{ count($update['changes']) }} field(s):
                                                {{ implode(', ', array_keys($update['changes'])) }}
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    </div>
                @endif
            </div>
        </div>

        <!-- Form Actions -->
        <div class="card mt-6">
            <div class="p-6 flex justify-end space-x-3">
                <a href="{{ route('admin.supervisor-assignments.show', $assignment) }}"
                   class="btn-secondary px-6 py-2.5 rounded-lg text-sm font-medium inline-flex items-center">
                    <i class="fas fa-times mr-2"></i> Cancel
                </a>
                <button type="button" id="resetBtn"
                        class="btn-secondary px-6 py-2.5 rounded-lg text-sm font-medium inline-flex items-center"
                        style="background-color: var(--bg-secondary); color: var(--text-primary);">
                    <i class="fas fa-undo mr-2"></i> Reset to Original
                </button>
                <button type="submit" id="submitBtn"
                        class="btn-primary px-6 py-2.5 rounded-lg text-sm font-medium text-white inline-flex items-center">
                    <i class="fas fa-save mr-2"></i> Update Appointment
                </button>
            </div>
        </div>
    </form>
</div>

<style>
.toggle-modern { position: relative; display: inline-block; width: 50px; height: 26px; }
.toggle-modern input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
    position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
    background-color: #d1d5db; border: 2px solid #d1d5db; transition: .4s; border-radius: 34px;
}
.toggle-slider:before {
    position: absolute; content: ""; height: 18px; width: 18px; left: 2px; bottom: 2px;
    background-color: white; transition: .4s; border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}
.toggle-modern input:checked + .toggle-slider { background-color: var(--success); border-color: var(--success); }
.toggle-modern input:checked + .toggle-slider:before { transform: translateX(24px); }
.toggle-modern input:focus + .toggle-slider { box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.3); }
[data-theme="dark"] .toggle-slider { background-color: #4b5563; border-color: #4b5563; }
[data-theme="dark"] .toggle-slider:before { background-color: #e5e7eb; }

.duration-preset:hover {
    background-color: var(--primary) !important;
    color: white !important;
    border-color: var(--primary) !important;
}
.duration-preset:active { transform: scale(0.95); }

.card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
.card:hover { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1); }

.badge-success   { background-color: rgba(var(--success-rgb), 0.1) !important; color: var(--success) !important; border: 1px solid rgba(var(--success-rgb), 0.3) !important; }
.badge-warning   { background-color: rgba(var(--warning-rgb), 0.1) !important; color: var(--warning) !important; border: 1px solid rgba(var(--warning-rgb), 0.3) !important; }
.badge-danger    { background-color: rgba(var(--danger-rgb), 0.1) !important; color: var(--danger) !important; border: 1px solid rgba(var(--danger-rgb), 0.3) !important; }
.badge-info      { background-color: rgba(var(--info-rgb), 0.1) !important; color: var(--info) !important; border: 1px solid rgba(var(--info-rgb), 0.3) !important; }
.badge-primary   { background-color: rgba(var(--primary-rgb), 0.1) !important; color: var(--primary) !important; border: 1px solid rgba(var(--primary-rgb), 0.3) !important; }
.badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1) !important; color: var(--secondary) !important; border: 1px solid rgba(var(--secondary-rgb), 0.3) !important; }

.index-custom-dropdown, .index-custom-input { transition: all 0.2s ease; }
.index-custom-dropdown:focus, .index-custom-input:focus {
    border-color: var(--primary) !important; outline: none;
    box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.2);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border: none; transition: all 0.2s ease;
}
.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3); }

.btn-secondary {
    background-color: var(--bg-secondary); border: 1px solid var(--border-color);
    color: var(--text-primary); transition: all 0.2s ease;
}
.btn-secondary:hover { background-color: var(--border-color); }

@media (max-width: 768px) {
    .grid { gap: 1rem; }
    .toggle-modern { width: 40px; height: 20px; }
    .toggle-slider:before { height: 14px; width: 14px; }
    .toggle-modern input:checked + .toggle-slider:before { transform: translateX(18px); }
}

.sr-only {
    position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
    overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // ---- Snapshot of original values for the Reset button ----
    const original = {
        user_id:   '{{ $assignment->user_id }}',
        title:     {!! json_encode($assignment->title) !!},
        start_date:'{{ $assignment->start_date?->format('Y-m-d') }}',
        end_date:  '{{ $assignment->end_date?->format('Y-m-d') }}',
        notes:     {!! json_encode($assignment->notes) !!},
        is_active: {{ $assignment->is_active ? 'true' : 'false' }},
    };

    const userSelect        = document.getElementById('user_id');
    const titleInput        = document.getElementById('title');
    const startDate         = document.getElementById('start_date');
    const endDate           = document.getElementById('end_date');
    const notesInput        = document.getElementById('notes');
    const isActiveToggle    = document.getElementById('is_active');
    const supervisorPreview = document.getElementById('supervisorPreview');
    const previewName       = document.getElementById('previewName');
    const previewEmail      = document.getElementById('previewEmail');
    const durationDisplay   = document.getElementById('durationDisplay');

    // ---- Live preview ----
    function updateOfficerPreview() {
        const sel = userSelect.options[userSelect.selectedIndex];
        if (sel && sel.value) {
            previewName.textContent  = sel.dataset.name  || 'Unknown';
            previewEmail.textContent = sel.dataset.email || 'No email';
            supervisorPreview.classList.remove('hidden');
        } else {
            supervisorPreview.classList.add('hidden');
        }
        updateAssignmentSummary();
    }

    // ---- Duration display ----
    function updateDurationDisplay() {
        const start = startDate.value;
        const end   = endDate.value;

        if (start && end) {
            const startMoment = new Date(start);
            const endMoment   = new Date(end);
            const days = Math.round((endMoment - startMoment) / (1000 * 60 * 60 * 24));
            durationDisplay.textContent = `Duration: ${days} day(s) (${start} → ${end})`;
        } else if (start && !end) {
            durationDisplay.textContent = 'Duration: Indefinite (no end date)';
        } else {
            durationDisplay.textContent = 'Duration: Indefinite';
        }
    }

    // ---- Summary ----
    function updateAssignmentSummary() {
        const el = document.getElementById('assignmentSummary');
        if (!el) return;

        const opt        = userSelect.options[userSelect.selectedIndex];
        const officer    = opt?.value ? (opt.dataset.name || opt.text) : 'Not selected';
        const title      = titleInput.value || 'Head of Security';
        const start      = startDate.value || 'Not set';
        const end        = endDate.value || 'Indefinite';
        const active     = isActiveToggle.checked ? '✅ Active' : '❌ Inactive';

        el.innerHTML = `
            <div class="flex justify-between"><span class="font-medium">Officer:</span> <span>${officer}</span></div>
            <div class="flex justify-between"><span class="font-medium">Title:</span> <span>${title}</span></div>
            <div class="flex justify-between"><span class="font-medium">Status:</span> <span>${active}</span></div>
            <div class="flex justify-between"><span class="font-medium">Start:</span> <span>${start}</span></div>
            <div class="flex justify-between"><span class="font-medium">End:</span> <span>${end}</span></div>
        `;
    }

    // ---- Duration presets ----
    document.querySelectorAll('.duration-preset').forEach(button => {
        button.addEventListener('click', function () {
            const days = parseInt(this.dataset.days, 10);
            if (days === 0) {
                endDate.value = '';
            } else {
                const base = new Date(startDate.value || new Date().toISOString().slice(0, 10));
                const end  = new Date(base);
                end.setDate(end.getDate() + days);
                endDate.value = end.toISOString().split('T')[0];
            }
            updateDurationDisplay();
            updateAssignmentSummary();
        });
    });

    // ---- Wiring ----
    userSelect.addEventListener('change', updateOfficerPreview);
    startDate.addEventListener('change', function () {
        endDate.min = this.value;
        updateDurationDisplay();
    });
    endDate.addEventListener('change', updateDurationDisplay);
    titleInput.addEventListener('input', updateAssignmentSummary);
    isActiveToggle.addEventListener('change', updateAssignmentSummary);

    // ---- Reset to original ----
    document.getElementById('resetBtn').addEventListener('click', function () {
        if (!confirm('Reset all fields to their original values? Any unsaved changes will be lost.')) {
            return;
        }
        userSelect.value   = original.user_id;
        titleInput.value   = original.title || '';
        startDate.value    = original.start_date;
        endDate.value      = original.end_date;
        notesInput.value   = original.notes || '';
        isActiveToggle.checked = original.is_active;

        updateOfficerPreview();
        updateDurationDisplay();
        updateAssignmentSummary();
    });

    // ---- Submit ----
    const form      = document.getElementById('assignmentForm');
    const submitBtn = document.getElementById('submitBtn');

    form.addEventListener('submit', function (e) {
        if (!userSelect.value) {
            e.preventDefault();
            alert('Please select an officer.');
            return;
        }
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Updating...';
    });

    // ---- Init ----
    updateOfficerPreview();
    updateDurationDisplay();
    updateAssignmentSummary();
});
</script>
@endsection