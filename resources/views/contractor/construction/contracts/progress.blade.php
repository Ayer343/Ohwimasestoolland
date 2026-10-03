@extends('layouts.contract')

@section('title', 'Update Progress - ' . $contract->title)

@section('content')
<div class="contract-progress-dashboard">
    <!-- Header -->
    <div class="card header-card">
        <div class="header-content">
            <div>
                <h2 class="page-title">
                    <i class="fas fa-chart-line mr-2"></i>
                    Update Progress
                </h2>
                <p class="page-subtitle">
                    <i class="fas fa-file-signature mr-1"></i> 
                    {{ $contract->contract_number }} • {{ $contract->title }}
                </p>
            </div>
            <a href="{{ route('contractor.contracts.show', $contract) }}" 
               class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i> 
                <span>Back to Contract</span>
            </a>
        </div>
    </div>

    <!-- Progress Overview -->
    <div class="stats-grid">
        <div class="stat-card stat-card-primary">
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value">{{ $contract->progress_percentage ?? 0 }}%</div>
                <div class="stat-label">Current Progress</div>
            </div>
        </div>
        
        <div class="stat-card stat-card-success">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value">
                    <span class="status-badge status-{{ 
                        $contract->status == 'pending_approval' ? 'warning' : 
                        ($contract->status == 'approved' ? 'success' : 
                        ($contract->status == 'in_progress' ? 'primary' : 
                        ($contract->status == 'completed' ? 'success' : 
                        ($contract->status == 'on_hold' ? 'warning' : 'secondary'))))
                    }}">
                        {{ ucfirst(str_replace('_', ' ', $contract->status)) }}
                    </span>
                </div>
                <div class="stat-label">Status</div>
            </div>
        </div>
        
        <div class="stat-card stat-card-info">
            <div class="stat-icon">
                <i class="fas fa-tasks"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value">{{ $completedMilestones ?? 0 }}/{{ $totalMilestones ?? 0 }}</div>
                <div class="stat-label">Milestones Completed</div>
            </div>
        </div>
        
        <div class="stat-card stat-card-secondary">
            <div class="stat-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value">
                    {{ $contract->estimated_completion_date ? $contract->estimated_completion_date->format('M d, Y') : 'N/A' }}
                </div>
                <div class="stat-label">Estimated Completion</div>
            </div>
        </div>
    </div>

    <!-- Progress Form -->
    <div class="card form-card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-edit mr-2" style="color: var(--primary);"></i> 
                Update Progress Details
            </h3>
            <span class="card-subtitle">Fill in the details to update contract progress</span>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('contractor.contracts.update-progress', $contract) }}" 
                  enctype="multipart/form-data" class="progress-form">
                @csrf

                <!-- Progress Percentage -->
                <div class="form-group">
                    <label for="progress_percentage" class="form-label">
                        Progress Percentage
                        <span class="text-muted">({{ $contract->progress_percentage ?? 0 }}% currently)</span>
                    </label>
                    <div class="progress-slider-container">
                        <input type="range" 
                               name="progress_percentage" 
                               id="progress_percentage"
                               min="0" 
                               max="100" 
                               step="1"
                               value="{{ $contract->progress_percentage ?? 0 }}"
                               class="progress-slider"
                               oninput="updateProgressDisplay(this.value)">
                        <span id="progressDisplay" class="progress-display">
                            {{ $contract->progress_percentage ?? 0 }}%
                        </span>
                    </div>
                    <div class="progress-range-labels">
                        <span>0%</span>
                        <span>50%</span>
                        <span>100%</span>
                    </div>
                </div>

                <!-- Status Update -->
                <div class="form-group">
                    <label for="status" class="form-label">
                        Update Status
                    </label>
                    <select name="status" id="status" class="form-control">
                        <option value="">No Change</option>
                        @if($contract->status == 'approved')
                            <option value="in_progress">Start Work (In Progress)</option>
                        @endif
                        @if($contract->status == 'in_progress')
                            <option value="on_hold">Pause Work (On Hold)</option>
                            <option value="completed">Mark as Completed</option>
                        @endif
                        @if($contract->status == 'on_hold')
                            <option value="in_progress">Resume Work (In Progress)</option>
                        @endif
                    </select>
                    <p class="form-helper">
                        <i class="fas fa-info-circle mr-1"></i>
                        Changing status will notify the landlord
                    </p>
                </div>

                <!-- Milestones -->
                @if(isset($milestones) && $milestones->count() > 0)
                    <div class="form-group">
                        <label class="form-label">
                            Mark Milestones as Completed
                            <span class="text-muted">(Select completed milestones)</span>
                        </label>
                        <div class="milestone-checkbox-list">
                            @foreach($milestones as $milestone)
                                <div class="milestone-checkbox-item">
                                    <input type="checkbox" 
                                           name="milestone_ids[]" 
                                           value="{{ $milestone->id }}"
                                           id="milestone_{{ $milestone->id }}"
                                           class="milestone-checkbox"
                                           {{ $milestone->status == 'completed' ? 'checked disabled' : '' }}>
                                    <label for="milestone_{{ $milestone->id }}" class="milestone-checkbox-label">
                                        <span class="milestone-title">{{ $milestone->title }}</span>
                                        <span class="milestone-due-date">
                                            <i class="fas fa-calendar-alt mr-1"></i>
                                            Due: {{ $milestone->due_date->format('M d, Y') }}
                                        </span>
                                    </label>
                                    @if($milestone->status == 'completed')
                                        <span class="milestone-status-completed">
                                            <i class="fas fa-check-circle mr-1"></i> Completed
                                        </span>
                                    @elseif($milestone->due_date->isPast())
                                        <span class="milestone-status-overdue">
                                            <i class="fas fa-exclamation-triangle mr-1"></i> Overdue
                                        </span>
                                    @elseif($milestone->status == 'in_progress')
                                        <span class="milestone-status-progress">
                                            <i class="fas fa-spinner mr-1"></i> In Progress
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Notes -->
                <div class="form-group">
                    <label for="notes" class="form-label">
                        Progress Notes
                    </label>
                    <textarea name="notes" id="notes" rows="4"
                              class="form-control"
                              placeholder="Describe what has been accomplished...">{{ old('notes') }}</textarea>
                </div>

                <!-- Photo Upload -->
                <div class="form-group">
                    <label for="photo" class="form-label">
                        Photo Evidence <span class="text-muted">(Optional)</span>
                    </label>
                    <div class="file-dropzone" id="dropzone">
                        <input type="file" 
                               name="photo" 
                               id="photo" 
                               accept="image/*"
                               class="file-input-hidden"
                               onchange="handleFileUpload(this)">
                        <div class="dropzone-content">
                            <i class="fas fa-cloud-upload-alt dropzone-icon"></i>
                            <p class="dropzone-text">Click to upload or drag and drop</p>
                            <p class="dropzone-subtext">Max size: 5MB (JPG, PNG, GIF)</p>
                        </div>
                        <img id="photoPreview" class="photo-preview hidden" alt="Preview">
                        <button type="button" class="photo-remove hidden" onclick="removePhoto()">
                            <i class="fas fa-times"></i> Remove
                        </button>
                    </div>
                </div>

                <!-- Submit Actions -->
                <div class="form-actions">
                    <a href="{{ route('contractor.contracts.show', $contract) }}" 
                       class="btn btn-secondary">
                        <i class="fas fa-times mr-2"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-2"></i> 
                        Update Progress
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Progress display update
    window.updateProgressDisplay = function(value) {
        document.getElementById('progressDisplay').textContent = value + '%';
    };

    // File upload handling
    window.handleFileUpload = function(input) {
        const preview = document.getElementById('photoPreview');
        const removeBtn = document.querySelector('.photo-remove');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
                removeBtn.classList.remove('hidden');
                document.querySelector('.dropzone-content').style.display = 'none';
            };
            reader.readAsDataURL(input.files[0]);
        }
    };

    window.removePhoto = function() {
        const input = document.getElementById('photo');
        const preview = document.getElementById('photoPreview');
        const removeBtn = document.querySelector('.photo-remove');
        
        input.value = '';
        preview.src = '';
        preview.classList.add('hidden');
        removeBtn.classList.add('hidden');
        document.querySelector('.dropzone-content').style.display = 'block';
    };

    // Drag and drop for photo upload
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('photo');

    dropzone.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('drag-over');
    });

    dropzone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
    });

    dropzone.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            handleFileUpload(fileInput);
        }
    });

    // Auto-hide flash messages
    const flashMessages = document.querySelectorAll('.flash-message');
    flashMessages.forEach(function(message) {
        setTimeout(function() {
            message.classList.add('fade-out');
            setTimeout(function() {
                message.style.display = 'none';
            }, 500);
        }, 5000);
    });

    // Form validation
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        const progress = document.getElementById('progress_percentage').value;
        const notes = document.getElementById('notes').value;
        
        if (parseInt(progress) > 0 && notes.trim() === '') {
            if (!confirm('You have made progress but haven\'t added any notes. Continue anyway?')) {
                e.preventDefault();
                document.getElementById('notes').focus();
            }
        }
    });
});
</script>
@endpush

<style>
/* ============================================ */
/* CSS VARIABLES (Should be in parent) */
/* ============================================ */
:root {
    --primary: #2563eb;
    --primary-rgb: 37, 99, 235;
    --secondary: #6b7280;
    --secondary-rgb: 107, 114, 128;
    --success: #16a34a;
    --success-rgb: 22, 163, 74;
    --warning: #f59e0b;
    --warning-rgb: 245, 158, 11;
    --danger: #dc2626;
    --danger-rgb: 220, 38, 38;
    --info: #06b6d4;
    --info-rgb: 6, 182, 212;
    --text-primary: #1f2937;
    --text-secondary: #6b7280;
    --text-muted: #9ca3af;
    --border-color: #e5e7eb;
    --card-bg: #ffffff;
    --bg-secondary: #f3f4f6;
    --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --radius: 12px;
    --spacing-sm: 0.5rem;
    --spacing-md: 1rem;
    --spacing-lg: 1.5rem;
    --spacing-xl: 2rem;
}

/* ============================================ */
/* BASE LAYOUT */
/* ============================================ */
.contract-progress-dashboard {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

/* ============================================ */
/* CARD COMPONENT */
/* ============================================ */
.card {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}

.card-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.card-body {
    padding: 1.5rem;
}

.card-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
}

.card-subtitle {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

/* ============================================ */
/* HEADER */
/* ============================================ */
.header-card .header-content {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
}

.page-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.page-title .fa-chart-line {
    color: var(--primary);
}

.page-subtitle {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

/* ============================================ */
/* BUTTONS */
/* ============================================ */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.625rem 1.25rem;
    font-weight: 500;
    border-radius: 8px;
    border: 1px solid transparent;
    transition: all 0.2s ease;
    cursor: pointer;
    text-decoration: none;
    font-size: 0.875rem;
    gap: 0.25rem;
    min-height: 44px;
}

.btn-primary {
    background-color: var(--primary);
    color: #ffffff;
    border-color: var(--primary);
}

.btn-primary:hover {
    background-color: #1d4ed8;
    border-color: #1d4ed8;
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.btn-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1);
    color: var(--secondary);
    border: 1px solid rgba(var(--secondary-rgb), 0.2);
}

.btn-secondary:hover {
    background-color: rgba(var(--secondary-rgb), 0.2);
    transform: translateY(-1px);
}

/* ============================================ */
/* STATISTICS GRID */
/* ============================================ */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
}

.stat-card {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    padding: 1rem;
    display: flex;
    align-items: center;
    transition: all 0.2s ease;
    box-shadow: var(--shadow-sm);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.stat-icon {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.75rem;
    flex-shrink: 0;
}

.stat-icon i {
    font-size: 1rem;
}

.stat-content {
    flex: 1;
    min-width: 0;
}

.stat-value {
    font-size: 1.25rem;
    font-weight: 700;
    line-height: 1.2;
}

.stat-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-top: 0.125rem;
}

/* Stat Colors */
.stat-card-primary .stat-icon {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
}
.stat-card-primary .stat-value {
    color: var(--primary);
}

.stat-card-success .stat-icon {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
}
.stat-card-success .stat-value {
    color: var(--success);
}

.stat-card-info .stat-icon {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
}
.stat-card-info .stat-value {
    color: var(--info);
}

.stat-card-secondary .stat-icon {
    background-color: rgba(var(--secondary-rgb), 0.1);
    color: var(--secondary);
}
.stat-card-secondary .stat-value {
    color: var(--secondary);
}

/* ============================================ */
/* STATUS BADGE */
/* ============================================ */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
    white-space: nowrap;
}

.status-primary {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
}

.status-success {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
}

.status-warning {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
}

.status-danger {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
}

.status-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1);
    color: var(--secondary);
}

/* ============================================ */
/* FORM STYLES */
/* ============================================ */
.form-card .card-body {
    padding: 1.5rem;
}

.progress-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-primary);
}

.form-label .text-muted {
    font-weight: 400;
    color: var(--text-secondary);
}

.form-control {
    width: 100%;
    padding: 0.625rem 0.875rem;
    font-size: 0.875rem;
    line-height: 1.5;
    color: var(--text-primary);
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    transition: all 0.2s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.form-control::placeholder {
    color: var(--text-muted);
}

textarea.form-control {
    resize: vertical;
    min-height: 100px;
}

.form-helper {
    font-size: 0.813rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

/* ============================================ */
/* PROGRESS SLIDER */
/* ============================================ */
.progress-slider-container {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.progress-slider {
    flex: 1;
    height: 6px;
    -webkit-appearance: none;
    appearance: none;
    background: linear-gradient(to right, var(--primary) 0%, var(--primary) var(--progress, 0%), var(--bg-secondary) var(--progress, 0%), var(--bg-secondary) 100%);
    border-radius: 9999px;
    outline: none;
    transition: all 0.2s ease;
}

.progress-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--primary);
    cursor: pointer;
    border: 2px solid white;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
    transition: all 0.2s ease;
}

.progress-slider::-webkit-slider-thumb:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
}

.progress-slider::-moz-range-thumb {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--primary);
    cursor: pointer;
    border: 2px solid white;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}

.progress-display {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary);
    min-width: 60px;
    text-align: center;
}

.progress-range-labels {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: var(--text-secondary);
    padding: 0 4px;
}

/* ============================================ */
/* MILESTONE CHECKBOX LIST */
/* ============================================ */
.milestone-checkbox-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    max-height: 240px;
    overflow-y: auto;
    padding-right: 0.25rem;
}

.milestone-checkbox-list::-webkit-scrollbar {
    width: 4px;
}

.milestone-checkbox-list::-webkit-scrollbar-track {
    background: var(--bg-secondary);
    border-radius: 9999px;
}

.milestone-checkbox-list::-webkit-scrollbar-thumb {
    background: var(--text-muted);
    border-radius: 9999px;
}

.milestone-checkbox-item {
    display: flex;
    align-items: center;
    padding: 0.625rem 0.75rem;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    transition: all 0.2s ease;
    gap: 0.75rem;
}

.milestone-checkbox-item:hover {
    background-color: rgba(var(--primary-rgb), 0.02);
}

.milestone-checkbox {
    min-width: 18px;
    min-height: 18px;
    cursor: pointer;
    accent-color: var(--primary);
}

.milestone-checkbox:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.milestone-checkbox-label {
    flex: 1;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.milestone-checkbox-label .milestone-title {
    font-weight: 500;
    color: var(--text-primary);
}

.milestone-checkbox-label .milestone-due-date {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.milestone-status-completed {
    font-size: 0.75rem;
    color: var(--success);
    font-weight: 500;
}

.milestone-status-overdue {
    font-size: 0.75rem;
    color: var(--danger);
    font-weight: 500;
}

.milestone-status-progress {
    font-size: 0.75rem;
    color: var(--primary);
    font-weight: 500;
}

/* ============================================ */
/* FILE DROPZONE */
/* ============================================ */
.file-dropzone {
    border: 2px dashed var(--border-color);
    border-radius: 8px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
}

.file-dropzone:hover {
    border-color: var(--primary);
    background-color: rgba(var(--primary-rgb), 0.02);
}

.file-dropzone.drag-over {
    border-color: var(--primary);
    background-color: rgba(var(--primary-rgb), 0.05);
}

.file-input-hidden {
    display: none;
}

.dropzone-content {
    pointer-events: none;
}

.dropzone-icon {
    font-size: 2.5rem;
    color: var(--text-muted);
    margin-bottom: 0.5rem;
}

.dropzone-text {
    color: var(--text-secondary);
    margin: 0;
}

.dropzone-subtext {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

.photo-preview {
    max-height: 200px;
    margin: 0.5rem auto;
    border-radius: 8px;
    display: block;
}

.photo-preview.hidden {
    display: none;
}

.photo-remove {
    margin-top: 0.5rem;
    padding: 0.25rem 0.75rem;
    border: 1px solid var(--danger);
    border-radius: 6px;
    background: transparent;
    color: var(--danger);
    cursor: pointer;
    font-size: 0.813rem;
    transition: all 0.2s ease;
}

.photo-remove:hover {
    background: rgba(var(--danger-rgb), 0.1);
}

.photo-remove.hidden {
    display: none;
}

/* ============================================ */
/* FORM ACTIONS */
/* ============================================ */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-color);
    flex-wrap: wrap;
}

/* ============================================ */
/* FLASH MESSAGES */
/* ============================================ */
.flash-message {
    transition: opacity 0.5s ease;
}

.flash-message.fade-out {
    opacity: 0;
}

/* ============================================ */
/* TEXT HELPERS */
/* ============================================ */
.text-muted {
    color: var(--text-muted);
}

/* ============================================ */
/* RESPONSIVE */
/* ============================================ */
@media (max-width: 768px) {
    .header-card .header-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .progress-slider-container {
        flex-direction: column;
        align-items: stretch;
    }
    
    .progress-display {
        font-size: 1.25rem;
        min-width: auto;
        text-align: center;
    }
    
    .milestone-checkbox-item {
        flex-wrap: wrap;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
        justify-content: center;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .card-subtitle {
        margin-top: 0.25rem;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }
    
    .stat-card {
        padding: 0.75rem;
    }
    
    .stat-icon {
        width: 2rem;
        height: 2rem;
        margin-right: 0.5rem;
    }
    
    .stat-value {
        font-size: 1rem;
    }
    
    .stat-label {
        font-size: 0.688rem;
    }
    
    .milestone-checkbox-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
    
    .milestone-checkbox-label {
        width: 100%;
    }
    
    .file-dropzone {
        padding: 1rem;
    }
}
</style>
@endsection