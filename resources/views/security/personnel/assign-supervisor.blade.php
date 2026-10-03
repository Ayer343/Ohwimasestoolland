@extends('layouts.secu')

@section('title', 'Assign Supervisor Role')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div>
                <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-user-tie mr-2" style="color: var(--success);"></i>
                    Assign Supervisor Role
                </h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Assign supervisor role to <strong>{{ $personnel->name }}</strong>
                </p>
            </div>
            <a href="{{ route('security.personnel.index') }}" class="btn-secondary px-4 py-2 rounded-lg">
                <i class="fas fa-arrow-left mr-2"></i> Back
            </a>
        </div>
    </div>

    <!-- Personnel Info Card -->
    <div class="card p-6">
        <div class="flex items-center">
            <div class="w-14 h-14 rounded-full flex items-center justify-center text-xl font-bold text-white"
                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                {{ substr($personnel->name, 0, 2) }}
            </div>
            <div class="ml-4">
                <h4 class="font-semibold" style="color: var(--text-primary);">{{ $personnel->name }}</h4>
                <div class="text-sm" style="color: var(--text-secondary);">
                    <span>{{ $personnel->email }}</span>
                    <span class="mx-2">•</span>
                    <span>{{ $personnel->phone ?? 'No phone' }}</span>
                </div>
                <div class="text-xs mt-1">
                    <span class="px-2 py-0.5 rounded-full badge-{{ $personnel->status === 'active' ? 'success' : 'secondary' }}">
                        {{ ucfirst($personnel->status) }}
                    </span>
                    @if(isset($personnel->supervisor_level) && $personnel->supervisor_level > 0)
                        <span class="ml-2 px-2 py-0.5 rounded-full badge-info">
                            Current: {{ $levelNames[$personnel->supervisor_level] ?? 'Unknown' }}
                        </span>
                    @endif
                    @if($personnel->can_be_supervisor)
                        <span class="ml-2 px-2 py-0.5 rounded-full badge-success">
                            <i class="fas fa-check-circle mr-1"></i> Eligible
                        </span>
                    @else
                        <span class="ml-2 px-2 py-0.5 rounded-full badge-warning">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Not Eligible
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- ✅ FIX: Check if personnel already has a supervisor assignment -->
    @if($personnel->supervisorAssignments()->where('is_active', true)->exists())
        <div class="card p-4" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
            <div class="flex items-start">
                <i class="fas fa-exclamation-triangle text-sm mr-2 mt-0.5" style="color: var(--warning);"></i>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-primary);">
                        <strong>⚠️ Personnel already has an active supervisor assignment</strong>
                    </p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        This personnel is currently assigned as a supervisor. Assigning a new role will deactivate the existing assignment.
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- Assignment Form -->
    <div class="card p-6">
        @if($errors->any())
            <div class="mb-4 p-4 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                <ul class="text-sm" style="color: var(--danger);">
                    @foreach($errors->all() as $error)
                        <li><i class="fas fa-exclamation-circle mr-2"></i>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('security.personnel.supervisor.store', $personnel->id) }}" method="POST" id="assign-supervisor-form">
            @csrf
            
            <!-- Supervisor Level -->
            <div class="mb-4">
                <label for="supervisor_level" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                    Supervisor Level *
                </label>
                <select id="supervisor_level" name="supervisor_level" 
                        class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                        required>
                    <option value="">Select Level</option>
                    @foreach($assignableLevels as $level => $label)
                        <option value="{{ $level }}" {{ old('supervisor_level') == $level ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1"></i>
                    Area Supervisors can only assign Team Lead (Level 1) and Section Lead (Level 2).
                </p>
                @error('supervisor_level')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Security Post -->
            <div class="mb-4">
                <label for="security_post_id" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                    Security Post
                </label>
                <select id="security_post_id" name="security_post_id" 
                        class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    <option value="">Select Post (Optional)</option>
                    @foreach($posts as $post)
                        <option value="{{ $post->id }}" {{ old('security_post_id') == $post->id ? 'selected' : '' }}>
                            {{ $post->name }}
                            @if(isset($post->max_personnel))
                                <span class="text-xs" style="color: var(--text-secondary);">
                                    (Max: {{ $post->max_personnel }})
                                </span>
                            @endif
                        </option>
                    @endforeach
                </select>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1"></i>
                    Assigning to a post will link this supervisor to a specific post.
                </p>
                @error('security_post_id')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Start Date -->
            <div class="mb-4">
                <label for="start_date" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                    Start Date *
                </label>
                <input type="date" id="start_date" name="start_date" 
                       value="{{ old('start_date', date('Y-m-d')) }}"
                       class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                       required
                       autocomplete="off">
                @error('start_date')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- End Date -->
            <div class="mb-4">
                <label for="end_date" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                    End Date
                </label>
                <input type="date" id="end_date" name="end_date" 
                       value="{{ old('end_date') }}"
                       class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                       autocomplete="off">
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1"></i>
                    Leave empty for indefinite assignment.
                </p>
                @error('end_date')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Supervisor Score -->
            <div class="mb-4">
                <label for="supervisor_score" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                    Supervisor Score (0-100)
                </label>
                <div class="flex items-center">
                    <input type="number" id="supervisor_score" name="supervisor_score" 
                           value="{{ old('supervisor_score', 50) }}" min="0" max="100"
                           class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           autocomplete="off">
                    <span class="ml-2 text-sm" style="color: var(--text-secondary);">/100</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-1.5 mt-2">
                    <div class="bg-yellow-400 h-1.5 rounded-full" id="score-preview" style="width: {{ old('supervisor_score', 50) }}%;"></div>
                </div>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1"></i>
                    @if(isset($minimumScore))
                        Minimum required score: <strong>{{ $minimumScore }}</strong>
                    @else
                        Score should reflect the supervisor's capability and performance.
                    @endif
                </p>
            </div>

            <!-- Primary Supervisor -->
            <div class="mb-6">
                <label class="flex items-center">
                    <input type="checkbox" name="is_primary_supervisor" value="1" 
                           {{ old('is_primary_supervisor') ? 'checked' : '' }}
                           class="w-4 h-4 rounded focus:ring-primary" style="color: var(--primary);">
                    <span class="ml-2 text-sm" style="color: var(--text-primary);">Set as Primary Supervisor</span>
                </label>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1"></i>
                    Primary supervisors have higher authority and are prioritized in assignments.
                </p>
            </div>

            <!-- ✅ FIX: Info Alert with correct information -->
            <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.3);">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-sm mr-2 mt-0.5" style="color: var(--info);"></i>
                    <div>
                        <p class="text-sm" style="color: var(--text-primary);">
                            <strong>What happens next?</strong>
                        </p>
                        <ul class="text-xs mt-1 space-y-1" style="color: var(--text-secondary);">
                            <li>• The personnel will be granted supervisor permissions</li>
                            <li>• They will appear in supervisor assignment dropdowns</li>
                            <li>• They can start managing team members immediately</li>
                            <li>• You can manage this assignment from the personnel list</li>
                            <li class="text-yellow-500">• <strong>No schedule will be automatically created</strong> - schedules must be created separately</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- ✅ FIX: Add note about no auto-schedule -->
            <div class="mb-6 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                <div class="flex items-start">
                    <i class="fas fa-clock text-sm mr-2 mt-0.5" style="color: var(--warning);"></i>
                    <div>
                        <p class="text-xs" style="color: var(--text-secondary);">
                            <strong>Note:</strong> Assigning a supervisor role does not automatically create a schedule.
                            You will need to create schedules separately for this personnel.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Buttons -->
            <div class="flex justify-end space-x-3 pt-4 border-t" style="border-color: var(--border-color);">
                <a href="{{ route('security.personnel.index') }}" class="btn-secondary px-4 py-2 rounded-lg">
                    <i class="fas fa-times mr-2"></i> Cancel
                </a>
                <button type="submit" class="btn-success px-4 py-2 rounded-lg" id="submit-btn">
                    <i class="fas fa-check mr-2"></i> Assign Supervisor
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.btn-success {
    background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
    border: none;
    transition: all 0.2s ease;
    color: white;
}
.btn-success:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--success-rgb), 0.3);
}
.btn-success:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}
.btn-secondary {
    background-color: var(--bg-secondary);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    transition: all 0.2s ease;
}
.btn-secondary:hover {
    background-color: var(--border-color);
}
.badge-success { 
    background-color: rgba(var(--success-rgb), 0.1); 
    color: var(--success); 
    border: 1px solid rgba(var(--success-rgb), 0.3); 
}
.badge-info { 
    background-color: rgba(var(--info-rgb), 0.1); 
    color: var(--info); 
    border: 1px solid rgba(var(--info-rgb), 0.3); 
}
.badge-secondary { 
    background-color: rgba(var(--secondary-rgb), 0.1); 
    color: var(--secondary); 
    border: 1px solid rgba(var(--secondary-rgb), 0.3); 
}
.badge-warning { 
    background-color: rgba(var(--warning-rgb), 0.1); 
    color: var(--warning); 
    border: 1px solid rgba(var(--warning-rgb), 0.3); 
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Score preview
    const scoreInput = document.getElementById('supervisor_score');
    const scorePreview = document.getElementById('score-preview');
    
    if (scoreInput && scorePreview) {
        scoreInput.addEventListener('input', function() {
            let value = parseInt(this.value) || 0;
            if (value < 0) value = 0;
            if (value > 100) value = 100;
            scorePreview.style.width = value + '%';
            
            // Color change based on score
            if (value >= 75) {
                scorePreview.style.backgroundColor = '#22c55e'; // Green
            } else if (value >= 50) {
                scorePreview.style.backgroundColor = '#eab308'; // Yellow
            } else {
                scorePreview.style.backgroundColor = '#ef4444'; // Red
            }
        });
        
        // Trigger initial color
        scoreInput.dispatchEvent(new Event('input'));
    }

    // ✅ FIX: Form validation before submit
    const form = document.getElementById('assign-supervisor-form');
    const submitBtn = document.getElementById('submit-btn');
    
    if (form && submitBtn) {
        form.addEventListener('submit', function(e) {
            const level = document.getElementById('supervisor_level');
            const startDate = document.getElementById('start_date');
            
            if (!level.value) {
                e.preventDefault();
                alert('Please select a supervisor level.');
                level.focus();
                return;
            }
            
            if (!startDate.value) {
                e.preventDefault();
                alert('Please select a start date.');
                startDate.focus();
                return;
            }
            
            // Disable button to prevent double submission
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Assigning...';
        });
    }

    // ✅ FIX: Post selection preview
    const postSelect = document.getElementById('security_post_id');
    if (postSelect) {
        postSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const postName = selectedOption.text;
                console.log('Selected post:', postName);
                // Could show post details here
            }
        });
    }
});
</script>
@endsection