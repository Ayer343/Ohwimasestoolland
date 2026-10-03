@extends('layouts.tenant')

@section('title', 'New Maintenance Request')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">New Maintenance Request</h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Unit {{ $unit->unit_number ?? '' }} - {{ $unit->property->property_name ?? $unit->property->street_name ?? '' }}
                </p>
            </div>
            
            <div class="flex space-x-2 mt-4 md:mt-0">
                <a href="{{ route('tenant.maintenance.index') }}" class="px-4 py-2 rounded flex items-center" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Requests
                </a>
            </div>
        </div>
    </div>

    <!-- Info Banner -->
    <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.05);">
        <div class="flex items-start">
            <i class="fas fa-info-circle mr-3 mt-1" style="color: var(--info);"></i>
            <div class="text-sm" style="color: var(--text-secondary);">
                <strong class="font-semibold" style="color: var(--text-primary);">Tips for a Successful Request:</strong>
                <ul class="mt-1 space-y-1">
                    <li>• Be specific about the issue and its location</li>
                    <li>• Upload clear photos showing the problem</li>
                    <li>• Mark as <strong>Urgent</strong> for emergency issues (e.g., water leaks, electrical hazards)</li>
                    <li>• You'll receive notifications when your request is updated</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
        <strong class="font-bold">Success!</strong>
        <span class="block sm:inline">{{ session('success') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
        <strong class="font-bold">Error!</strong>
        <span class="block sm:inline">{{ session('error') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Create Request Form -->
    <div class="card p-6">
        <form method="POST" action="{{ route('tenant.maintenance.store') }}" enctype="multipart/form-data" id="maintenanceForm">
            @csrf

            <div class="grid grid-cols-1 gap-6">
                <!-- Title -->
                <div>
                    <label for="title" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Title <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           id="title" 
                           name="title" 
                           class="w-full p-2 border rounded @error('title') border-red-500 @enderror" 
                           style="border-color: var(--border-color); background-color: var(--bg-primary); color: var(--text-primary);"
                           placeholder="Brief description of the issue"
                           value="{{ old('title') }}"
                           required>
                    @error('title')
                        <p class="text-sm mt-1" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Description <span class="text-red-500">*</span>
                    </label>
                    <textarea id="description" 
                              name="description" 
                              rows="5" 
                              class="w-full p-2 border rounded @error('description') border-red-500 @enderror" 
                              style="border-color: var(--border-color); background-color: var(--bg-primary); color: var(--text-primary);"
                              placeholder="Detailed description of the issue (minimum 20 characters)"
                              required>{{ old('description') }}</textarea>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        <span id="charCount">0</span> / 1000 characters (minimum 20)
                    </p>
                    @error('description')
                        <p class="text-sm mt-1" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Priority & Category Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Priority -->
                    <div>
                        <label for="priority" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Priority <span class="text-red-500">*</span>
                        </label>
                        <select id="priority" 
                                name="priority" 
                                class="w-full p-2 border rounded @error('priority') border-red-500 @enderror" 
                                style="border-color: var(--border-color); background-color: var(--bg-primary); color: var(--text-primary);"
                                required>
                            <option value="">Select Priority</option>
                            <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>🟢 Low - Minor Issue</option>
                            <option value="medium" {{ old('priority') == 'medium' ? 'selected' : '' }}>🟡 Medium - Moderate Issue</option>
                            <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>🟠 High - Significant Issue</option>
                            <option value="urgent" {{ old('priority') == 'urgent' ? 'selected' : '' }}>🔴 Urgent - Emergency</option>
                        </select>
                        @error('priority')
                            <p class="text-sm mt-1" style="color: var(--danger);">{{ $message }}</p>
                        @enderror
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-lightbulb mr-1"></i> 
                            <span id="priorityHelp">Select based on severity</span>
                        </p>
                    </div>

                    <!-- Category -->
                    <div>
                        <label for="category" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Category <span class="text-red-500">*</span>
                        </label>
                        <select id="category" 
                                name="category" 
                                class="w-full p-2 border rounded @error('category') border-red-500 @enderror" 
                                style="border-color: var(--border-color); background-color: var(--bg-primary); color: var(--text-primary);"
                                required>
                            <option value="">Select Category</option>
                            <option value="plumbing" {{ old('category') == 'plumbing' ? 'selected' : '' }}>🚿 Plumbing</option>
                            <option value="electrical" {{ old('category') == 'electrical' ? 'selected' : '' }}>💡 Electrical</option>
                            <option value="appliance" {{ old('category') == 'appliance' ? 'selected' : '' }}>🔧 Appliance</option>
                            <option value="structural" {{ old('category') == 'structural' ? 'selected' : '' }}>🏗️ Structural</option>
                            <option value="cleaning" {{ old('category') == 'cleaning' ? 'selected' : '' }}>🧹 Cleaning</option>
                            <option value="other" {{ old('category') == 'other' ? 'selected' : '' }}>📦 Other</option>
                        </select>
                        @error('category')
                            <p class="text-sm mt-1" style="color: var(--danger);">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Photos -->
                <div>
                    <label for="photos" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Photos <span class="text-xs" style="color: var(--text-secondary);">(Optional)</span>
                    </label>
                    <div class="border-2 border-dashed rounded-lg p-6 text-center" 
                         style="border-color: var(--border-color);"
                         id="dropZone">
                        <input type="file" 
                               id="photos" 
                               name="photos[]" 
                               multiple 
                               accept="image/*"
                               class="hidden">
                        <label for="photos" class="cursor-pointer">
                            <i class="fas fa-cloud-upload-alt text-3xl mb-2" style="color: var(--text-secondary);"></i>
                            <p style="color: var(--text-secondary);">Click or drag to upload photos</p>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">Max 2MB per image • JPG, PNG, JPEG</p>
                        </label>
                    </div>
                    <div id="photoPreview" class="grid grid-cols-4 gap-2 mt-3"></div>
                    @error('photos.*')
                        <p class="text-sm mt-1" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Submit Buttons -->
                <div class="flex flex-col md:flex-row justify-end space-y-2 md:space-y-0 md:space-x-3 pt-4 border-t" style="border-color: var(--border-color);">
                    <a href="{{ route('tenant.maintenance.index') }}" 
                       class="px-4 py-2 border rounded text-center" 
                       style="background-color: var(--bg-secondary); color: var(--text-secondary); border-color: var(--border-color);">
                        Cancel
                    </a>
                    <button type="submit" 
                            id="submitBtn"
                            class="px-4 py-2 rounded flex items-center justify-center" 
                            style="background-color: var(--primary); color: white;">
                        <i class="fas fa-paper-plane mr-2"></i> Submit Request
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card p-8 text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
        <p class="text-lg font-semibold" style="color: var(--text-primary);">Submitting Request...</p>
        <p class="text-sm mt-2" style="color: var(--text-secondary);">Please wait while we process your request</p>
    </div>
</div>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide messages after 5 seconds
    const messages = document.querySelectorAll('.bg-green-100, .bg-red-100');
    messages.forEach(message => {
        setTimeout(() => {
            message.style.display = 'none';
        }, 5000);
    });

    // Character counter
    const description = document.getElementById('description');
    const charCount = document.getElementById('charCount');
    
    if (description && charCount) {
        description.addEventListener('input', function() {
            const count = this.value.length;
            charCount.textContent = count;
            
            if (count < 20 && count > 0) {
                charCount.style.color = 'var(--warning)';
            } else if (count >= 20) {
                charCount.style.color = 'var(--success)';
            } else {
                charCount.style.color = 'var(--text-secondary)';
            }
        });
    }

    // Priority help text
    const priority = document.getElementById('priority');
    const priorityHelp = document.getElementById('priorityHelp');
    
    if (priority && priorityHelp) {
        priority.addEventListener('change', function() {
            const helpTexts = {
                'low': 'Non-urgent issue that doesn\'t affect daily activities',
                'medium': 'Moderate issue that affects comfort but not safety',
                'high': 'Significant issue that affects daily activities or comfort',
                'urgent': 'Emergency requiring immediate attention (water leaks, electrical hazards)'
            };
            priorityHelp.textContent = helpTexts[this.value] || 'Select based on severity';
        });
    }

    // Photo upload preview
    const photos = document.getElementById('photos');
    const preview = document.getElementById('photoPreview');
    const dropZone = document.getElementById('dropZone');

    if (photos && preview) {
        photos.addEventListener('change', function() {
            preview.innerHTML = '';
            const files = this.files;
            
            if (files.length === 0) return;
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'relative';
                    div.innerHTML = `
                        <img src="${e.target.result}" alt="Preview" class="w-full h-24 object-cover rounded">
                        <button type="button" onclick="removePhoto(${i})" 
                                class="absolute top-0 right-0 bg-red-500 text-white rounded-full w-5 h-5 text-xs flex items-center justify-center">
                            ×
                        </button>
                    `;
                    preview.appendChild(div);
                };
                
                reader.readAsDataURL(file);
            }
        });

        // Drag and drop
        if (dropZone) {
            dropZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.style.borderColor = 'var(--primary)';
                this.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
            });

            dropZone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.style.borderColor = 'var(--border-color)';
                this.style.backgroundColor = 'transparent';
            });

            dropZone.addEventListener('drop', function(e) {
                e.preventDefault();
                this.style.borderColor = 'var(--border-color)';
                this.style.backgroundColor = 'transparent';
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    photos.files = files;
                    photos.dispatchEvent(new Event('change'));
                }
            });
        }
    }

    // Form submission
    const form = document.getElementById('maintenanceForm');
    const submitBtn = document.getElementById('submitBtn');
    const loadingOverlay = document.getElementById('loadingOverlay');

    if (form && submitBtn) {
        form.addEventListener('submit', function(e) {
            const description = document.getElementById('description');
            
            if (description && description.value.length < 20) {
                e.preventDefault();
                description.focus();
                description.style.borderColor = 'var(--danger)';
                alert('Please provide a description of at least 20 characters.');
                return;
            }
            
            // Show loading overlay
            if (loadingOverlay) {
                loadingOverlay.classList.remove('hidden');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...';
            }
        });
    }
});

function removePhoto(index) {
    const photos = document.getElementById('photos');
    const preview = document.getElementById('photoPreview');
    
    if (photos && preview) {
        const dt = new DataTransfer();
        const files = photos.files;
        
        for (let i = 0; i < files.length; i++) {
            if (i !== index) {
                dt.items.add(files[i]);
            }
        }
        
        photos.files = dt.files;
        photos.dispatchEvent(new Event('change'));
    }
}
</script>

<style>
/* Loading animation */
.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Hidden file input */
#photos {
    display: none;
}

/* Drop zone hover */
#dropZone:hover {
    border-color: var(--primary);
    background-color: rgba(var(--primary-rgb), 0.05);
}

/* Photo preview */
#photoPreview .relative {
    transition: transform 0.2s;
}

#photoPreview .relative:hover {
    transform: scale(1.05);
}
</style>