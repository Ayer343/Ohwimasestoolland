@extends('layouts.secu')

@section('title', 'Edit Security Personnel')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border-color: var(--primary);">
                        <i class="fas fa-user-edit text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-shield mr-2" style="color: var(--primary);"></i>
                        Edit Security Personnel
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-user mr-2"></i>
                        <span>{{ $personnel->name }}</span>
                        <span class="mx-2">•</span>
                        <span class="px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            ID: {{ $personnel->id }}
                        </span>
                        @if($personnel->can_be_supervisor)
                            <span class="ml-2 px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                <i class="fas fa-check-circle mr-1"></i> Eligible
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('security.personnel.index') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Personnel
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="card p-4 border-l-4" style="border-left-color: var(--success); background-color: rgba(var(--success-rgb), 0.05);">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
            <span style="color: var(--text-primary);">{{ session('success') }}</span>
        </div>
    </div>
    @endif

    @if($errors->any())
    <div class="card p-4 border-l-4" style="border-left-color: var(--danger); background-color: rgba(var(--danger-rgb), 0.05);">
        <div class="flex items-start">
            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
            <div>
                <span class="font-medium" style="color: var(--danger);">Validation Errors:</span>
                <ul class="mt-1 text-sm" style="color: var(--text-secondary);">
                    @foreach($errors->all() as $error)
                        <li>• {{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    @endif

    <!-- Edit Form -->
    <form action="{{ route('security.personnel.update', $personnel->id) }}" method="POST" id="edit-personnel-form">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-1 gap-6">

            <!-- Personal Information -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-id-card mr-2" style="color: var(--primary);"></i>
                    Personal Information
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block mb-2 font-medium" style="color: var(--text-primary);">Full Name *</label>
                        <input type="text" id="name" name="name" value="{{ old('name', $personnel->name) }}"
                               class="w-full p-2 border rounded @error('name') border-red-500 @enderror"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" required>
                        @error('name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block mb-2 font-medium" style="color: var(--text-primary);">Email Address *</label>
                        <input type="email" id="email" name="email" value="{{ old('email', $personnel->email) }}"
                               class="w-full p-2 border rounded @error('email') border-red-500 @enderror"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" required>
                        @error('email')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="phone" class="block mb-2 font-medium" style="color: var(--text-primary);">Phone Number</label>
                        <input type="tel" id="phone" name="phone" value="{{ old('phone', $personnel->phone) }}"
                               class="w-full p-2 border rounded @error('phone') border-red-500 @enderror"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        @error('phone')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="username" class="block mb-2 font-medium" style="color: var(--text-primary);">Username</label>
                        <input type="text" id="username" name="username" value="{{ old('username', $personnel->username) }}"
                               class="w-full p-2 border rounded @error('username') border-red-500 @enderror"
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Leave empty to auto-generate
                        </div>
                        @error('username')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Personnel Settings -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-cog mr-2" style="color: var(--primary);"></i>
                    Personnel Settings
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="status" class="block mb-2 font-medium" style="color: var(--text-primary);">Status *</label>
                        <select id="status" name="status"
                                class="w-full p-2 border rounded @error('status') border-red-500 @enderror"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" {{ old('status', $personnel->status) == $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('status')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="security_post_id" class="block mb-2 font-medium" style="color: var(--text-primary);">Security Post</label>
                        <select id="security_post_id" name="security_post_id"
                                class="w-full p-2 border rounded @error('security_post_id') border-red-500 @enderror"
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">-- Role Only (No Post Assignment) --</option>
                            @foreach($posts as $post)
                                <option value="{{ $post->id }}" 
                                    {{ old('security_post_id', $personnel->security_post_id) == $post->id ? 'selected' : '' }}>
                                    {{ $post->name }}
                                    @if(isset($post->max_personnel))
                                        <span class="text-xs text-gray-500">- Max: {{ $post->max_personnel }}</span>
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Select <strong>"Role Only"</strong> to remove post assignment.
                            Select a post to assign the personnel to that specific post.
                        </div>
                        @error('security_post_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- ✅ UPDATED: SUPERVISOR ELIGIBILITY (SIMPLIFIED)               -->
            <!-- ============================================================ -->
            <div class="card p-6">
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center">
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            <i class="fas fa-user-tie mr-2" style="color: var(--success);"></i>
                            Supervisor Eligibility
                        </h3>
                        <span class="ml-3 px-2 py-1 text-xs rounded-full badge-secondary">Optional</span>
                    </div>
                    <button type="button" id="supervisor-toggle"
                            class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center transition-all duration-300"
                            style="background-color: {{ old('can_be_supervisor', $personnel->can_be_supervisor ?? false) ? 'var(--success)' : 'var(--bg-secondary)' }};
                                   color: {{ old('can_be_supervisor', $personnel->can_be_supervisor ?? false) ? 'white' : 'var(--text-primary)' }};
                                   border: 1px solid {{ old('can_be_supervisor', $personnel->can_be_supervisor ?? false) ? 'var(--success)' : 'var(--border-color)' }};">
                        <i class="fas {{ old('can_be_supervisor', $personnel->can_be_supervisor ?? false) ? 'fa-toggle-on' : 'fa-toggle-off' }} mr-2"></i>
                        <span id="supervisor-toggle-text">{{ old('can_be_supervisor', $personnel->can_be_supervisor ?? false) ? 'Eligible' : 'Not Eligible' }}</span>
                    </button>
                </div>

                <input type="hidden" name="can_be_supervisor" id="can_be_supervisor" value="{{ old('can_be_supervisor', $personnel->can_be_supervisor ?? 0) }}">

                <div id="supervisor-fields" class="{{ old('can_be_supervisor', $personnel->can_be_supervisor ?? false) ? '' : 'hidden' }}">
                    <!-- Supervisor Info Alert -->
                    <div class="mb-4 p-3 rounded border" style="background-color: rgba(var(--info-rgb), 0.1); border-color: rgba(var(--info-rgb), 0.3);">
                        <div class="flex items-start text-sm">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--info);"></i>
                            <div>
                                <span style="color: var(--text-secondary);">
                                    Enabling this makes the user <strong>eligible for supervisor assignments</strong>.
                                </span>
                                <ul class="text-xs mt-2 space-y-1" style="color: var(--text-secondary);">
                                    <li>• <strong>Team Lead (Level 1)</strong> and <strong>Section Lead (Level 2)</strong> roles are assigned separately</li>
                                    <li>• <strong>Post Commander (Level 3)</strong> and <strong>Area Supervisor</strong> can only be assigned by Administrators</li>
                                    <li>• You can manage supervisor assignments from the <a href="{{ route('security.supervisor-assignments.index') }}" class="text-primary hover:underline">Supervisor Assignments</a> page</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--border-color);">
                        <div class="flex items-center">
                            <i class="fas fa-info-circle mr-2" style="color: var(--info);"></i>
                            <span class="text-sm" style="color: var(--text-secondary);">
                                <strong>Note:</strong> Making a user eligible for supervisor does not automatically assign a supervisor role.
                                You must assign the specific role (Team Lead, Section Lead) separately.
                            </span>
                        </div>
                    </div>

                    <!-- ✅ REMOVED: supervisor_level dropdown -->
                    <!-- ✅ REMOVED: supervisor_score input -->
                </div>

                <!-- ✅ ADDED: Current Supervisor Status Display -->
                @php
                    $hasSupervisorAssignment = $personnel->supervisorAssignments && 
                        $personnel->supervisorAssignments->filter(function($a) { 
                            return $a->is_active === true; 
                        })->isNotEmpty();
                    
                    $supervisorRole = null;
                    $roleName = 'Not Assigned';
                    $roleColor = 'secondary';
                    
                    if ($hasSupervisorAssignment) {
                        $activeAssignment = $personnel->supervisorAssignments->filter(function($a) {
                            return $a->is_active === true;
                        })->first();
                        
                        if ($activeAssignment) {
                            $supervisorRole = $activeAssignment->supervisor_type;
                            $roleNames = [
                                'post_supervisor' => 'Post Supervisor',
                                'shift_supervisor' => 'Shift Supervisor',
                                'area_supervisor' => 'Area Supervisor',
                                'relief_supervisor' => 'Relief Supervisor',
                                'training_supervisor' => 'Training Supervisor',
                                'team_lead' => 'Team Lead',
                                'section_lead' => 'Section Lead',
                                'post_commander' => 'Post Commander'
                            ];
                            $roleName = $roleNames[$supervisorRole] ?? ucfirst(str_replace('_', ' ', $supervisorRole));
                            
                            $roleColors = [
                                'post_supervisor' => 'info',
                                'shift_supervisor' => 'primary',
                                'area_supervisor' => 'success',
                                'relief_supervisor' => 'warning',
                                'training_supervisor' => 'secondary',
                                'team_lead' => 'warning',
                                'section_lead' => 'danger',
                                'post_commander' => 'danger'
                            ];
                            $roleColor = $roleColors[$supervisorRole] ?? 'secondary';
                        }
                    }
                @endphp

                <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid var(--border-color);">
                    <h4 class="text-sm font-semibold mb-2" style="color: var(--text-secondary);">
                        <i class="fas fa-user-tie mr-2" style="color: var(--primary);"></i>
                        Current Supervisor Status
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <span class="text-xs" style="color: var(--text-secondary);">Eligibility:</span>
                            <span class="ml-2 px-2 py-1 text-xs rounded-full {{ $personnel->can_be_supervisor ? 'badge-success' : 'badge-secondary' }}">
                                {{ $personnel->can_be_supervisor ? 'Eligible' : 'Not Eligible' }}
                            </span>
                        </div>
                        <div>
                            <span class="text-xs" style="color: var(--text-secondary);">Current Role:</span>
                            <span class="ml-2 px-2 py-1 text-xs rounded-full badge-{{ $roleColor }}">
                                {{ $roleName }}
                            </span>
                        </div>
                        <div>
                            <span class="text-xs" style="color: var(--text-secondary);">Assignment Type:</span>
                            <span class="ml-2 text-xs" style="color: var(--text-primary);">
                                @if($hasSupervisorAssignment && isset($activeAssignment))
                                    {{ $activeAssignment->metadata['assignment_type'] ?? ($activeAssignment->security_post_id ? 'Post Specific' : 'Role Only') }}
                                @else
                                    N/A
                                @endif
                            </span>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="{{ route('security.personnel.supervisor.assign', $personnel->id) }}"
                           class="btn-primary px-4 py-2 rounded-lg text-sm inline-flex items-center">
                            <i class="fas fa-user-edit mr-2"></i> 
                            {{ $hasSupervisorAssignment ? 'Edit Supervisor Assignment' : 'Assign Supervisor Role' }}
                        </a>
                        @if($hasSupervisorAssignment && $supervisorRole !== 'post_commander' && $supervisorRole !== 'area_supervisor')
                            <button onclick="removeSupervisor({{ $personnel->id }}, '{{ addslashes($personnel->name) }}')"
                                    class="btn-danger px-4 py-2 rounded-lg text-sm inline-flex items-center ml-2">
                                <i class="fas fa-user-slash mr-2"></i> Remove Supervisor
                            </button>
                        @endif
                        @if($hasSupervisorAssignment && $supervisorRole !== 'post_commander' && $supervisorRole !== 'area_supervisor')
                            <a href="{{ route('security.supervisor-assignments.edit', $activeAssignment->id ?? 0) }}"
                               class="btn-info px-4 py-2 rounded-lg text-sm inline-flex items-center ml-2"
                               style="background: linear-gradient(135deg, var(--info) 0%, #2563eb 100%); color: white; border: none;">
                                <i class="fas fa-cog mr-2"></i> Manage Permissions
                            </a>
                        @endif
                    </div>
                </div>
            </div>
            <!-- ============================================================ -->
            <!-- END SUPERVISOR ELIGIBILITY SECTION                           -->
            <!-- ============================================================ -->

            <!-- Form Actions -->
            <div class="card p-6">
                <div class="flex justify-between items-center">
                    <div class="text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-asterisk text-red-500 mr-1"></i> Required fields
                    </div>
                    <div class="flex space-x-4">
                        <button type="submit" class="btn-primary px-6 py-2.5 rounded-lg text-sm font-medium inline-flex items-center" id="submit-button">
                            <i class="fas fa-save mr-2"></i> Update Personnel
                        </button>
                        <a href="{{ route('security.personnel.index') }}" class="btn-secondary px-6 py-2.5 rounded-lg">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Remove Supervisor Modal -->
<div id="removeSupervisorModal" class="fixed inset-0 z-50 hidden overflow-y-auto" style="background-color: rgba(0,0,0,0.5);">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="card w-full max-w-md">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Remove Supervisor Role</h3>
            </div>
            <div class="p-6">
                <p style="color: var(--text-primary);" id="removeSupervisorName"></p>
                <div class="mt-3 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                    <p class="text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-exclamation-triangle mr-2" style="color: var(--warning);"></i>
                        This will remove the supervisor role from this personnel. They will no longer appear in supervisor assignment dropdowns.
                        Any active supervisor assignments will be deactivated.
                    </p>
                </div>
            </div>
            <div class="p-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                <button type="button" onclick="closeRemoveModal()" class="btn-secondary px-4 py-2 rounded-lg">Cancel</button>
                <form id="removeSupervisorForm" method="POST" action="" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger px-4 py-2 rounded-lg">
                        <i class="fas fa-user-slash mr-2"></i> Remove Supervisor
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border: none;
    transition: all 0.2s ease;
    color: white;
}
.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}
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
.btn-danger {
    background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
    border: none;
    transition: all 0.2s ease;
    color: white;
}
.btn-danger:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--danger-rgb), 0.3);
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
.btn-info {
    background: linear-gradient(135deg, var(--info) 0%, #2563eb 100%);
    border: none;
    transition: all 0.2s ease;
    color: white;
}
.btn-info:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--info-rgb), 0.3);
}
.badge-success { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3); }
.badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3); }
.badge-info { background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3); }
.badge-warning { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3); }
.badge-primary { background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3); }
.badge-danger { background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3); }
.hidden { display: none !important; }

/* Toggle animation */
#supervisor-toggle {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
#supervisor-toggle:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
#supervisor-toggle:active {
    transform: translateY(0);
}

#supervisor-fields {
    transition: all 0.3s ease;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Security Personnel Edit Form Initialized');

    // DOM Elements
    const supervisorToggle = document.getElementById('supervisor-toggle');
    const canBeSupervisor = document.getElementById('can_be_supervisor');
    const supervisorFields = document.getElementById('supervisor-fields');
    const supervisorToggleText = document.getElementById('supervisor-toggle-text');

    // ==================== SUPERVISOR TOGGLE (SIMPLIFIED) ====================
    if (supervisorToggle) {
        supervisorToggle.addEventListener('click', function() {
            const isEnabled = canBeSupervisor.value == '1';
            const newValue = isEnabled ? '0' : '1';
            
            canBeSupervisor.value = newValue;
            
            if (newValue == '1') {
                supervisorFields.classList.remove('hidden');
                supervisorToggle.style.backgroundColor = 'var(--success)';
                supervisorToggle.style.color = 'white';
                supervisorToggle.style.borderColor = 'var(--success)';
                supervisorToggle.querySelector('i').className = 'fas fa-toggle-on mr-2';
                supervisorToggleText.textContent = 'Eligible';
                
                // Show toast notification
                showToast('info', 'User will be eligible for supervisor assignments.');
            } else {
                supervisorFields.classList.add('hidden');
                supervisorToggle.style.backgroundColor = 'var(--bg-secondary)';
                supervisorToggle.style.color = 'var(--text-primary)';
                supervisorToggle.style.borderColor = 'var(--border-color)';
                supervisorToggle.querySelector('i').className = 'fas fa-toggle-off mr-2';
                supervisorToggleText.textContent = 'Not Eligible';
                
                showToast('info', 'User will not be eligible for supervisor assignments.');
            }
        });
    }

    // ==================== REMOVE SUPERVISOR MODAL ====================
    window.closeRemoveModal = function() {
        document.getElementById('removeSupervisorModal').classList.add('hidden');
    }

    window.removeSupervisor = function(id, name) {
        document.getElementById('removeSupervisorName').innerHTML = `Remove supervisor role from <strong>${name}</strong>?`;
        document.getElementById('removeSupervisorForm').action = `/security/personnel/${id}/remove-supervisor`;
        document.getElementById('removeSupervisorModal').classList.remove('hidden');
    }

    // ==================== TOAST NOTIFICATION ====================
    function showToast(type, message) {
        const existingToast = document.querySelector('.custom-toast');
        if (existingToast) existingToast.remove();
        
        const toast = document.createElement('div');
        toast.className = 'custom-toast';
        const colors = {
            success: 'var(--success)',
            error: 'var(--danger)',
            warning: 'var(--warning)',
            info: 'var(--info)'
        };
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            padding: 12px 20px;
            border-radius: 10px;
            background: var(--card-bg);
            border-left: 4px solid ${colors[type] || 'var(--info)'};
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 10px;
            max-width: 400px;
            animation: slideInRight 0.3s ease;
            border: 1px solid var(--border-color);
        `;
        
        toast.innerHTML = `
            <i class="fas ${icons[type]}" style="color: ${colors[type]}; font-size: 1.1rem;"></i>
            <span style="color: var(--text-primary); font-size: 0.9rem;">${message}</span>
            <button onclick="this.parentElement.remove()" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1rem; margin-left: 8px;">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            if (toast.parentElement) {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100px)';
                setTimeout(() => toast.remove(), 300);
            }
        }, 4000);
    }

    // ==================== FORM SUBMISSION ====================
    const form = document.getElementById('edit-personnel-form');
    const submitBtn = document.getElementById('submit-button');
    
    if (form && submitBtn) {
        form.addEventListener('submit', function(e) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Updating...';
        });
    }

    // ==================== MODAL CLOSE ON OUTSIDE CLICK ====================
    window.onclick = function(event) {
        if (event.target.classList.contains('fixed')) {
            closeRemoveModal();
        }
    }

    // ==================== KEYBOARD SHORTCUTS ====================
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeRemoveModal();
        }
    });

    // Add animation keyframes if not already present
    if (!document.getElementById('toast-styles')) {
        const styleSheet = document.createElement('style');
        styleSheet.id = 'toast-styles';
        styleSheet.textContent = `
            @keyframes slideInRight {
                from {
                    opacity: 0;
                    transform: translateX(100px);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
        `;
        document.head.appendChild(styleSheet);
    }

    console.log('Security Personnel Edit Form initialization complete');
});
</script>
@endsection