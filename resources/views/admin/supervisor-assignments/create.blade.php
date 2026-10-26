@extends('layouts.app')

@section('title', 'Appoint Head of Security')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-user-shield text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-shield mr-2" style="color: var(--primary);"></i>
                        Appoint Head of Security
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Appoint an eligible officer as Head of Security</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-users mr-1"></i>
                        <span>{{ $supervisors->count() ?? 0 }} eligible officer(s)</span>
                        <span class="mx-2">•</span>
                        @if($currentHead ?? null)
                            <span class="px-2 py-0.5 rounded-full text-xs"
                                  style="background-color: rgba(var(--warning-rgb), 0.15); color: var(--warning);">
                                <i class="fas fa-crown mr-1"></i> Current head: {{ $currentHead->user->name }}
                            </span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-xs"
                                  style="background-color: rgba(var(--success-rgb), 0.15); color: var(--success);">
                                <i class="fas fa-check-circle mr-1"></i> No active head
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.supervisor-assignments.index') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to List
                </a>
            </div>
        </div>
    </div>

    <!-- Current Head Banner -->
    @if($currentHead ?? null)
    <div class="card p-4"
         style="background-color: rgba(var(--warning-rgb), 0.08); border-left: 4px solid var(--warning);">
        <div class="flex items-start">
            <i class="fas fa-exclamation-triangle mt-1 mr-3" style="color: var(--warning);"></i>
            <div>
                <h4 class="font-semibold" style="color: var(--text-primary);">
                    {{ $currentHead->user->name }} is currently Head of Security
                </h4>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Appointed {{ $currentHead->start_date?->format('M j, Y') }}
                    @if($currentHead->end_date)
                        · Ends {{ $currentHead->end_date->format('M j, Y') }}
                    @else
                        · Ongoing
                    @endif
                    @if($currentHead->assignedBy)
                        · Appointed by {{ $currentHead->assignedBy->name }}
                    @endif
                </p>
                <p class="text-sm mt-2" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1" style="color: var(--info);"></i>
                    To replace them, tick <strong>"Replace current head"</strong> below.
                    Their appointment will end the day before the new head's start date.
                </p>
            </div>
        </div>
    </div>
    @endif

    <!-- Error Alert -->
    @if($errors->any())
        <div class="card p-4" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-lg" style="color: var(--danger);"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium" style="color: var(--danger);">Validation Error!</h3>
                    <div class="mt-2 text-sm" style="color: var(--text-secondary);">
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <form action="{{ route('admin.supervisor-assignments.store') }}" method="POST" id="assignmentForm">
        @csrf

        <!-- Officer & Dates -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Officer Selection -->
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
                            Select Head of Security <span style="color: var(--danger);">*</span>
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
                                        {{ old('user_id') == $supervisor->id ? 'selected' : '' }}>
                                    {{ $supervisor->name }}
                                    @if($supervisor->email)
                                        ({{ $supervisor->email }})
                                    @endif
                                </option>
                            @endforeach
                        </select>

                        <!-- Officer Preview -->
                        <div id="supervisorPreview" class="mt-3 p-3 rounded-lg hidden"
                             style="background-color: rgba(var(--info-rgb), 0.05); border-left: 3px solid var(--info);">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium" style="color: var(--text-secondary);">Selected officer:</span>
                                <div class="flex space-x-2">
                                    <span class="px-2 py-1 text-xs rounded-full badge-info" id="previewName"></span>
                                    <span class="px-2 py-1 text-xs rounded-full badge-success" id="previewEmail"></span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Only active security officers with <strong>"Can be appointed Head of Security"</strong> enabled appear here.
                            Manage eligibility in
                            <a href="{{ route('admin.users.index') }}" class="text-primary hover:underline">User Management</a>.
                        </div>
                    </div>

                    <!-- Title (optional override) -->
                    <div>
                        <label for="title" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                            <i class="fas fa-tag mr-1" style="color: var(--primary);"></i>
                            Title
                            <span class="text-xs" style="color: var(--text-secondary);">(optional)</span>
                        </label>
                        <input type="text" name="title" id="title"
                               class="index-custom-input w-full @error('title') is-invalid @enderror"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                               value="{{ old('title', 'Head of Security') }}"
                               placeholder="Head of Security">
                        <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                            Default: <strong>Head of Security</strong>. Override to use a different label (e.g., <em>Acting Head</em>).
                        </p>
                    </div>
                </div>
            </div>

            <!-- Assignment Period -->
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
                                   value="{{ old('start_date', now()->format('Y-m-d')) }}"
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
                                   value="{{ old('end_date') }}"
                                   min="{{ old('start_date', now()->format('Y-m-d')) }}">
                            <p class="mt-1 text-xs" style="color: var(--text-secondary);">Leave empty for indefinite</p>
                        </div>
                    </div>

                    <div class="mt-4 p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--border-color);">
                        <div class="flex items-center">
                            <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                            <span class="text-sm" style="color: var(--text-primary);" id="durationDisplay">Duration: Indefinite</span>
                        </div>
                    </div>

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

        <!-- Replacement & Notes -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
            <!-- Replacement -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-exchange-alt mr-2" style="color: var(--primary);"></i>
                        Replacement
                    </h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex items-start justify-between p-4 rounded-lg"
                         style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <div class="flex items-start">
                            <i class="fas fa-crown mr-3 mt-1" style="color: var(--warning);"></i>
                            <div>
                                <label for="replace_existing" class="text-sm font-medium cursor-pointer" style="color: var(--text-primary);">
                                    Replace current head
                                </label>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    @if($currentHead ?? null)
                                        If ticked, <strong>{{ $currentHead->user->name }}</strong> will be demoted and the new officer appointed.
                                    @else
                                        There is no active head to replace — this checkbox has no effect.
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="toggle-modern flex-shrink-0 ml-4">
                            <input type="checkbox" name="replace_existing" id="replace_existing" value="1"
                                   {{ old('replace_existing') ? 'checked' : '' }}
                                   class="sr-only">
                            <label for="replace_existing" class="toggle-slider"></label>
                        </div>
                    </div>

                    <div class="p-3 rounded-lg"
                         style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--border-color);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--info);"></i>
                            <span class="text-xs" style="color: var(--text-secondary);">
                                Only one active Head of Security exists at a time.
                                The system enforces this invariant — you cannot appoint a second head without
                                confirming replacement.
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-sticky-note mr-2" style="color: var(--primary);"></i>
                        Notes
                    </h3>
                </div>
                <div class="p-6">
                    <label for="notes" class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-pen mr-1" style="color: var(--primary);"></i>
                        Assignment Notes
                    </h4>
                    <textarea name="notes" id="notes" rows="5"
                              class="index-custom-input w-full @error('notes') is-invalid @enderror"
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                              placeholder="Enter any special instructions or context for this appointment...">{{ old('notes') }}</textarea>
                    <p class="mt-1 text-xs" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        Optional. Recorded in the appointment's audit trail.
                    </p>
                </div>
            </div>
        </div>

        <!-- Assignment Summary -->
        <div class="card mt-6">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-list-alt mr-2" style="color: var(--primary);"></i>
                    Summary
                </h3>
            </div>
            <div class="p-6">
                <div class="p-4 rounded-lg"
                     style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--border-color);">
                    <div id="assignmentSummary" class="text-sm space-y-1" style="color: var(--text-secondary);">
                        <p class="mb-0">Fill in the form to see a summary of this appointment.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="card mt-6">
            <div class="p-6 flex justify-end space-x-3">
                <a href="{{ route('admin.supervisor-assignments.index') }}"
                   class="btn-secondary px-6 py-2.5 rounded-lg text-sm font-medium inline-flex items-center">
                    <i class="fas fa-times mr-2"></i> Cancel
                </a>
                <button type="reset"
                        class="btn-secondary px-6 py-2.5 rounded-lg text-sm font-medium inline-flex items-center"
                        style="background-color: var(--bg-secondary); color: var(--text-primary);">
                    <i class="fas fa-undo mr-2"></i> Reset
                </button>
                <button type="submit" id="submitBtn"
                        class="btn-primary px-6 py-2.5 rounded-lg text-sm font-medium text-white inline-flex items-center">
                    <i class="fas fa-save mr-2"></i> Appoint Head of Security
                </button>
            </div>
        </div>
    </form>
</div>

<style>
/* Modern Toggle Switch Styles */
.toggle-modern {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
}

.toggle-modern input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #d1d5db;
    border: 2px solid #d1d5db;
    transition: .4s;
    border-radius: 34px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 2px;
    bottom: 2px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.toggle-modern input:checked + .toggle-slider {
    background-color: var(--success);
    border-color: var(--success);
}

.toggle-modern input:checked + .toggle-slider:before {
    transform: translateX(24px);
}

.toggle-modern input:focus + .toggle-slider {
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.3);
}

[data-theme="dark"] .toggle-slider {
    background-color: #4b5563;
    border-color: #4b5563;
}

[data-theme="dark"] .toggle-slider:before {
    background-color: #e5e7eb;
}

/* Card hover */
.card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
}

/* Preview panels */
#supervisorPreview {
    transition: all 0.2s ease;
}

/* Duration preset buttons */
.duration-preset:hover {
    background-color: var(--primary) !important;
    color: white !important;
    border-color: var(--primary) !important;
}

.duration-preset:active {
    transform: scale(0.95);
}

/* Responsive */
@media (max-width: 768px) {
    .grid {
        gap: 1rem;
    }

    .toggle-modern {
        width: 40px;
        height: 20px;
    }

    .toggle-slider:before {
        height: 14px;
        width: 14px;
        left: 1px;
        bottom: 1px;
    }

    .toggle-modern input:checked + .toggle-slider:before {
        transform: translateX(20px);
    }
}

.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

/* Badge styles */
.badge-success {
    background-color: rgba(var(--success-rgb), 0.1) !important;
    color: var(--success) !important;
    border: 1px solid rgba(var(--success-rgb), 0.3) !important;
}
.badge-warning {
    background-color: rgba(var(--warning-rgb), 0.1) !important;
    color: var(--warning) !important;
    border: 1px solid rgba(var(--warning-rgb), 0.3) !important;
}
.badge-danger {
    background-color: rgba(var(--danger-rgb), 0.1) !important;
    color: var(--danger) !important;
    border: 1px solid rgba(var(--danger-rgb), 0.3) !important;
}
.badge-info {
    background-color: rgba(var(--info-rgb), 0.1) !important;
    color: var(--info) !important;
    border: 1px solid rgba(var(--info-rgb), 0.3) !important;
}
.badge-primary {
    background-color: rgba(var(--primary-rgb), 0.1) !important;
    color: var(--primary) !important;
    border: 1px solid rgba(var(--primary-rgb), 0.3) !important;
}
.badge-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // ------- Officer preview -------
    const userSelect        = document.getElementById('user_id');
    const supervisorPreview = document.getElementById('supervisorPreview');
    const previewName       = document.getElementById('previewName');
    const previewEmail      = document.getElementById('previewEmail');

    function updateOfficerPreview() {
        const selected = userSelect.options[userSelect.selectedIndex];
        if (selected && selected.value) {
            previewName.textContent  = selected.dataset.name  || 'Unknown';
            previewEmail.textContent = selected.dataset.email || 'No email';
            supervisorPreview.classList.remove('hidden');
        } else {
            supervisorPreview.classList.add('hidden');
        }
        updateAssignmentSummary();
    }

    if (userSelect) {
        userSelect.addEventListener('change', updateOfficerPreview);
    }

    // ------- Date handling -------
    const startDate         = document.getElementById('start_date');
    const endDate           = document.getElementById('end_date');
    const durationDisplay   = document.getElementById('durationDisplay');

    function updateDurationDisplay() {
        const start = startDate?.value;
        const end   = endDate?.value;

        if (!durationDisplay) return;

        if (start && end) {
            const startMoment = new Date(start);
            const endMoment   = new Date(end);
            const days        = Math.round((endMoment - startMoment) / (1000 * 60 * 60 * 24));
            durationDisplay.textContent = `Duration: ${days} day(s) (${start} → ${end})`;
        } else if (start && !end) {
            durationDisplay.textContent = 'Duration: Indefinite (no end date)';
        } else {
            durationDisplay.textContent = 'Duration: Indefinite';
        }
        updateAssignmentSummary();
    }

    if (startDate) {
        startDate.addEventListener('change', function () {
            if (endDate) endDate.min = this.value;
            updateDurationDisplay();
        });
    }
    if (endDate) {
        endDate.addEventListener('change', updateDurationDisplay);
    }

    // Duration presets
    document.querySelectorAll('.duration-preset').forEach(button => {
        button.addEventListener('click', function () {
            const days = parseInt(this.dataset.days, 10);

            if (!startDate || !endDate) return;

            if (days === 0) {
                endDate.value = '';
            } else {
                const start = new Date(startDate.value || new Date().toISOString().slice(0, 10));
                const end   = new Date(start);
                end.setDate(end.getDate() + days);
                endDate.value = end.toISOString().split('T')[0];
            }
            updateDurationDisplay();
        });
    });

    // ------- Assignment summary -------
    function updateAssignmentSummary() {
        const summaryEl = document.getElementById('assignmentSummary');
        if (!summaryEl) return;

        const officerOpt = userSelect?.options[userSelect.selectedIndex];
        const officer    = officerOpt?.value ? (officerOpt.dataset.name || officerOpt.text) : 'Not selected';
        const title      = document.getElementById('title')?.value || 'Head of Security';
        const start      = startDate?.value || 'Not set';
        const end        = endDate?.value || 'Indefinite';
        const replace    = document.getElementById('replace_existing')?.checked ? 'Yes' : 'No';

        summaryEl.innerHTML = `
            <div class="flex justify-between"><span class="font-medium">Officer:</span> <span>${officer}</span></div>
            <div class="flex justify-between"><span class="font-medium">Title:</span> <span>${title}</span></div>
            <div class="flex justify-between"><span class="font-medium">Start date:</span> <span>${start}</span></div>
            <div class="flex justify-between"><span class="font-medium">End date:</span> <span>${end}</span></div>
            <div class="flex justify-between"><span class="font-medium">Replace current head:</span> <span>${replace}</span></div>
        `;
    }

    // Bind summary-updating listeners
    ['user_id', 'title', 'start_date', 'end_date', 'replace_existing'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', updateAssignmentSummary);
        if (el && el.tagName === 'INPUT' && el.type === 'text') {
            el.addEventListener('input', updateAssignmentSummary);
        }
    });

    // ------- Submit -------
    const form      = document.getElementById('assignmentForm');
    const submitBtn = document.getElementById('submitBtn');

    if (form && submitBtn) {
        form.addEventListener('submit', function (e) {
            if (!userSelect?.value) {
                e.preventDefault();
                alert('Please select an officer.');
                userSelect?.focus();
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Appointing...';
        });
    }

    // ------- Init -------
    updateOfficerPreview();
    updateDurationDisplay();
    updateAssignmentSummary();
});
</script>
@endsection