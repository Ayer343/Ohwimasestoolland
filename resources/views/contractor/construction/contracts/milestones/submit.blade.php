@extends('layouts.contract')

@section('title', 'Submit Milestone - ' . $milestone->title)

@section('content')
<div class="contract-milestone-dashboard">
    <!-- Header -->
    <div class="card header-card">
        <div class="header-content">
            <div>
                <h2 class="page-title">
                    <i class="fas fa-flag-checkered mr-2"></i>
                    Submit Milestone
                </h2>
                <p class="page-subtitle">
                    <i class="fas fa-file-signature mr-1"></i> 
                    {{ $contract->contract_number ?? 'N/A' }} • {{ $milestone->title }}
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
                <i class="fas fa-tasks"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value">{{ $milestoneStats['total'] ?? 0 }}</div>
                <div class="stat-label">Total Milestones</div>
            </div>
        </div>
        
        <div class="stat-card stat-card-success">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value">{{ $milestoneStats['completed'] ?? 0 }}</div>
                <div class="stat-label">Completed</div>
            </div>
        </div>
        
        <div class="stat-card stat-card-warning">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value">{{ $milestoneStats['pending'] ?? 0 }}</div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
        
        <div class="stat-card stat-card-danger">
            <div class="stat-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value">{{ $milestoneStats['overdue'] ?? 0 }}</div>
                <div class="stat-label">Overdue</div>
            </div>
        </div>
    </div>

    <!-- Milestone Details -->
    <div class="card detail-card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> 
                Milestone Information
            </h3>
            <span class="card-subtitle">
                <i class="fas fa-{{ $milestone->status == 'completed' ? 'check-circle' : 'clock' }} mr-1"></i>
                Status: {{ ucfirst(str_replace('_', ' ', $milestone->status)) }}
            </span>
        </div>
        <div class="card-body">
            <div class="milestone-detail-grid">
                <div class="detail-item">
                    <label class="detail-label">Milestone Title</label>
                    <p class="detail-value">{{ $milestone->title }}</p>
                </div>
                <div class="detail-item">
                    <label class="detail-label">Due Date</label>
                    <p class="detail-value">
                        {{ $milestone->due_date ? $milestone->due_date->format('M d, Y') : 'N/A' }}
                        @if($milestone->due_date && $milestone->due_date->isPast() && $milestone->status != 'completed')
                            <span class="detail-badge detail-badge-danger">
                                <i class="fas fa-exclamation-triangle mr-1"></i> Overdue
                            </span>
                        @endif
                    </p>
                </div>
                <div class="detail-item">
                    <label class="detail-label">Status</label>
                    <span class="status-badge status-{{ 
                        $milestone->status == 'completed' ? 'success' : 
                        ($milestone->status == 'in_progress' ? 'primary' : 
                        ($milestone->status == 'delayed' ? 'danger' : 'warning'))
                    }}">
                        <i class="fas fa-{{ 
                            $milestone->status == 'completed' ? 'check-circle' : 
                            ($milestone->status == 'in_progress' ? 'spinner' : 
                            ($milestone->status == 'delayed' ? 'exclamation-triangle' : 'clock'))
                        }} mr-1"></i>
                        {{ ucfirst(str_replace('_', ' ', $milestone->status)) }}
                    </span>
                </div>
            </div>
            
            @if($milestone->description)
                <div class="detail-description">
                    <label class="detail-label">Description</label>
                    <p class="detail-value">{{ $milestone->description }}</p>
                </div>
            @endif

            @if($milestone->status == 'completed')
                <div class="mt-4 p-4 rounded-lg" style="background: rgba(var(--success-rgb), 0.1); border: 1px solid var(--success);">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-success mr-2" style="color: var(--success);"></i>
                        <span style="color: var(--text-primary);">
                            <strong>This milestone has already been completed.</strong>
                            @if($milestone->completed_at)
                                <span class="text-sm" style="color: var(--text-secondary);">
                                    Completed on {{ $milestone->completed_at->format('M d, Y') }}
                                </span>
                            @endif
                        </span>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Submit Form -->
    @if($milestone->status != 'completed')
    <div class="card form-card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-paper-plane mr-2" style="color: var(--success);"></i> 
                Submit Completion
            </h3>
            <span class="card-subtitle">Provide details and evidence for milestone completion</span>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('contractor.milestones.submit', [$contract, $milestone]) }}" 
                  enctype="multipart/form-data" class="milestone-form">
                @csrf

                <!-- Completion Notes -->
                <div class="form-group">
                    <label for="completion_notes" class="form-label">
                        Completion Notes <span class="text-danger">*</span>
                    </label>
                    <textarea name="completion_notes" id="completion_notes" rows="5" required
                              class="form-control @error('completion_notes') is-invalid @enderror"
                              placeholder="Describe how this milestone was completed, any challenges faced, and results achieved...">{{ old('completion_notes') }}</textarea>
                    @error('completion_notes')
                        <p class="form-error"><i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}</p>
                    @enderror
                    <p class="form-helper">
                        <i class="fas fa-info-circle mr-1"></i>
                        The landlord will review these notes along with the submission
                    </p>
                </div>

                <!-- Evidence Upload -->
                <div class="form-group">
                    <label for="evidence" class="form-label">
                        Evidence <span class="text-muted">(Optional)</span>
                    </label>
                    <div class="file-dropzone" id="dropzone">
                        <input type="file" 
                               name="evidence" 
                               id="evidence" 
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                               class="file-input-hidden"
                               onchange="handleFileUpload(this)">
                        <div class="dropzone-content">
                            <i class="fas fa-cloud-upload-alt dropzone-icon"></i>
                            <p class="dropzone-text">Click to upload or drag and drop</p>
                            <p class="dropzone-subtext">Max size: 10MB (PDF, JPG, PNG, DOC, DOCX)</p>
                        </div>
                        <div id="fileInfo" class="file-info hidden">
                            <i class="fas fa-file file-icon"></i>
                            <span id="fileName" class="file-name"></span>
                            <button type="button" class="file-remove" onclick="removeFile()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <img id="evidencePreview" class="photo-preview hidden" alt="Preview">
                    </div>
                    @error('evidence')
                        <p class="form-error"><i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}</p>
                    @enderror
                </div>

                <!-- Submit Actions -->
                <div class="form-actions">
                    <a href="{{ route('contractor.contracts.show', $contract) }}" 
                       class="btn btn-secondary">
                        <i class="fas fa-times mr-2"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-paper-plane mr-2"></i> 
                        Submit Milestone
                    </button>
                </div>
            </form>
        </div>
    </div>
    @else
    <!-- Already Completed Message -->
    <div class="card" style="border: 1px solid var(--success);">
        <div class="card-body text-center py-8">
            <i class="fas fa-check-circle text-4xl mb-3" style="color: var(--success);"></i>
            <h3 class="text-xl font-semibold mb-2" style="color: var(--text-primary);">Milestone Already Completed</h3>
            <p style="color: var(--text-secondary);">
                This milestone was completed on {{ $milestone->completed_at ? $milestone->completed_at->format('M d, Y') : 'N/A' }}
            </p>
            <a href="{{ route('contractor.contracts.show', $contract) }}" 
               class="btn btn-primary mt-4">
                <i class="fas fa-arrow-left mr-2"></i> Back to Contract
            </a>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // File upload handling
    window.handleFileUpload = function(input) {
        const preview = document.getElementById('evidencePreview');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const dropzoneContent = document.querySelector('.dropzone-content');
        
        if (input.files && input.files[0]) {
            const file = input.files[0];
            
            // Show file info
            fileName.textContent = file.name;
            fileInfo.classList.remove('hidden');
            dropzoneContent.style.display = 'none';
            
            // Show preview for images
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            } else {
                preview.classList.add('hidden');
                // Show file type icon
                const fileIcon = document.querySelector('.file-icon');
                if (file.type.includes('pdf')) {
                    fileIcon.className = 'fas fa-file-pdf file-icon';
                } else if (file.type.includes('word')) {
                    fileIcon.className = 'fas fa-file-word file-icon';
                } else {
                    fileIcon.className = 'fas fa-file file-icon';
                }
            }
        }
    };

    window.removeFile = function() {
        const input = document.getElementById('evidence');
        const preview = document.getElementById('evidencePreview');
        const fileInfo = document.getElementById('fileInfo');
        const dropzoneContent = document.querySelector('.dropzone-content');
        
        input.value = '';
        preview.src = '';
        preview.classList.add('hidden');
        fileInfo.classList.add('hidden');
        dropzoneContent.style.display = 'block';
    };

    // Drag and drop for file upload
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('evidence');

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
    if (form) {
        form.addEventListener('submit', function(e) {
            const notes = document.getElementById('completion_notes').value.trim();
            
            if (notes === '') {
                e.preventDefault();
                document.getElementById('completion_notes').focus();
                document.getElementById('completion_notes').style.borderColor = 'var(--danger)';
                
                // Show error message
                let errorMsg = document.getElementById('notesError');
                if (!errorMsg) {
                    errorMsg = document.createElement('p');
                    errorMsg.id = 'notesError';
                    errorMsg.className = 'form-error';
                    errorMsg.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i> Please provide completion notes';
                    document.getElementById('completion_notes').parentNode.appendChild(errorMsg);
                }
                errorMsg.style.display = 'block';
            }
        });

        // Clear error on input
        document.getElementById('completion_notes').addEventListener('input', function() {
            this.style.borderColor = 'var(--border-color)';
            const errorMsg = document.getElementById('notesError');
            if (errorMsg) {
                errorMsg.style.display = 'none';
            }
        });
    }

    // Confirm submission for milestone completion
    const submitBtn = document.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to submit this milestone for review? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    }
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
}

/* ============================================ */
/* BASE LAYOUT */
/* ============================================ */
.contract-milestone-dashboard {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
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

.stat-card-warning .stat-icon {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
}
.stat-card-warning .stat-value {
    color: var(--warning);
}

.stat-card-danger .stat-icon {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
}
.stat-card-danger .stat-value {
    color: var(--danger);
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

.page-title .fa-flag-checkered {
    color: var(--success);
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

.btn-success {
    background-color: var(--success);
    color: #ffffff;
    border-color: var(--success);
}

.btn-success:hover {
    background-color: #15803d;
    border-color: #15803d;
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.text-center {
    text-align: center;
}

.py-8 {
    padding-top: 2rem;
    padding-bottom: 2rem;
}

.text-4xl {
    font-size: 2.25rem;
    line-height: 2.5rem;
}

.mb-2 {
    margin-bottom: 0.5rem;
}

.mb-3 {
    margin-bottom: 0.75rem;
}

.mb-4 {
    margin-bottom: 1rem;
}

.mt-4 {
    margin-top: 1rem;
}

/* ============================================ */
/* MILESTONE DETAILS */
/* ============================================ */
.milestone-detail-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.25rem;
}

.detail-item {
    display: flex;
    flex-direction: column;
}

.detail-label {
    font-size: 0.813rem;
    font-weight: 500;
    color: var(--text-secondary);
    margin-bottom: 0.25rem;
}

.detail-value {
    font-weight: 500;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.detail-badge {
    font-size: 0.688rem;
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    font-weight: 500;
}

.detail-badge-danger {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
}

.detail-description {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
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

/* ============================================ */
/* FORM STYLES */
/* ============================================ */
.form-card .card-body {
    padding: 1.5rem;
}

.milestone-form {
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

.form-label .text-danger {
    color: var(--danger);
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

.form-control.is-invalid {
    border-color: var(--danger);
}

.form-control.is-invalid:focus {
    box-shadow: 0 0 0 3px rgba(var(--danger-rgb), 0.1);
}

textarea.form-control {
    resize: vertical;
    min-height: 120px;
}

.form-helper {
    font-size: 0.813rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

.form-error {
    font-size: 0.813rem;
    color: var(--danger);
    margin-top: 0.25rem;
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

/* File Info */
.file-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background-color: var(--bg-secondary);
    border-radius: 8px;
    margin-top: 0.5rem;
}

.file-info.hidden {
    display: none;
}

.file-icon {
    font-size: 1.5rem;
    color: var(--primary);
}

.file-name {
    flex: 1;
    font-size: 0.875rem;
    color: var(--text-primary);
    word-break: break-all;
}

.file-remove {
    background: none;
    border: none;
    color: var(--danger);
    cursor: pointer;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.file-remove:hover {
    background: rgba(var(--danger-rgb), 0.1);
}

/* Photo Preview */
.photo-preview {
    max-height: 200px;
    margin: 0.5rem auto;
    border-radius: 8px;
    display: block;
}

.photo-preview.hidden {
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
    
    .milestone-detail-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .milestone-detail-grid .detail-item:last-child {
        grid-column: span 2;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .card-subtitle {
        margin-top: 0.25rem;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }
    
    .milestone-detail-grid {
        grid-template-columns: 1fr;
    }
    
    .milestone-detail-grid .detail-item:last-child {
        grid-column: span 1;
    }
    
    .detail-value {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .file-dropzone {
        padding: 1rem;
    }
    
    .file-info {
        flex-wrap: wrap;
    }
    
    .file-name {
        width: 100%;
    }
}
</style>
@endsection