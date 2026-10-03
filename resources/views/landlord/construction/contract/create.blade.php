@extends('layouts.landlord')

@section('title', 'Create Construction Contract')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-wrap justify-between items-center p-6">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Create Construction Contract</h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1"></i> 
                    Submit a new construction contract for admin approval
                </p>
            </div>
            <div class="flex flex-wrap gap-2 mt-2 sm:mt-0">
                <a href="{{ route('landlord.construction.contract.index') }}" 
                   class="px-4 py-2 border rounded-lg hover:bg-gray-50 flex items-center transition-colors" 
                   style="border-color: var(--border-color); color: var(--text-primary);">
                    <i class="fas fa-arrow-left mr-2"></i> 
                    <span>Back to Contracts</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Form Card -->
    <div class="card p-6">
        <form id="contractForm" action="{{ route('landlord.construction.contract.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            
            <!-- ============================================ -->
            <!-- SECTION 1: BASIC INFORMATION -->
            <!-- ============================================ -->
            <div class="form-section" style="background: var(--bg-secondary, #f8f9fa); border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.25rem;">
                <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--primary, #3b82f6); display: inline-block; color: var(--text-primary, #1a1a2e);">
                    <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> Basic Information
                </h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Property Selection -->
                <div>
                    <label for="property_id" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                        Property <span style="color: var(--danger, #ef4444);">*</span>
                    </label>
                    <select id="property_id" name="property_id" class="w-full p-2 rounded-lg border @error('property_id') border-red-500 @enderror" 
                            style="border-color: var(--border-color); background: var(--bg-primary); color: var(--text-primary); min-height: 44px;" required>
                        <option value="">Select a property...</option>
                        @foreach($properties ?? [] as $prop)
                            <option value="{{ $prop->id }}" 
                                    {{ old('property_id', $property->id ?? '') == $prop->id ? 'selected' : '' }}
                                    data-status="{{ $prop->status }}"
                                    data-construction-status="{{ $prop->construction_status }}">
                                {{ $prop->property_name }} 
                                @if($prop->digital_address)
                                    - {{ $prop->digital_address }}
                                @elseif($prop->street_name)
                                    - {{ $prop->street_name }}
                                @endif
                                <span style="color: var(--text-secondary); font-size: 0.8rem;">
                                    ({{ $prop->getContractDisplayStatus() }})
                                </span>
                            </option>
                        @endforeach
                    </select>
                    @error('property_id')
                        <p class="text-xs mt-1" style="color: var(--danger, #ef4444);">{{ $message }}</p>
                    @enderror
                </div>
                    
                    <!-- Contract Title -->
                    <div>
                        <label for="title" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Contract Title <span style="color: var(--danger, #ef4444);">*</span>
                        </label>
                        <input type="text" id="title" name="title" value="{{ old('title') }}" 
                               class="w-full p-2 rounded-lg border @error('title') border-red-500 @enderror" 
                               style="border-color: var(--border-color); background: var(--bg-primary); color: var(--text-primary); min-height: 44px;" 
                               placeholder="e.g., Residential Building Construction" required>
                        @error('title')
                            <p class="text-xs mt-1" style="color: var(--danger, #ef4444);">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                
                <!-- Description -->
                <div class="mt-3">
                    <label for="description" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                        Description
                    </label>
                    <textarea id="description" name="description" rows="3" 
                              class="w-full p-2 rounded-lg border @error('description') border-red-500 @enderror" 
                              style="border-color: var(--border-color); background: var(--bg-primary); color: var(--text-primary); resize: vertical; min-height: 80px;" 
                              placeholder="Provide a detailed description of the construction project...">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="text-xs mt-1" style="color: var(--danger, #ef4444);">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- ============================================ -->
            <!-- SECTION 2: CONTRACTOR INFORMATION -->
            <!-- ============================================ -->
            <div class="form-section" style="background: var(--bg-secondary, #f8f9fa); border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.25rem;">
                <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--primary, #3b82f6); display: inline-block; color: var(--text-primary, #1a1a2e);">
                    <i class="fas fa-user-tie mr-2" style="color: var(--primary);"></i> Contractor Information
                </h4>
                
                <!-- Contractor Type -->
                <div class="mb-3">
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                        Contractor Type <span style="color: var(--danger, #ef4444);">*</span>
                    </label>
                    <div class="flex gap-4">
                        <label class="flex items-center">
                            <input type="radio" name="contractor_type" value="individual" {{ old('contractor_type') == 'individual' ? 'checked' : '' }} 
                                   class="mr-2" style="min-width: 18px; min-height: 18px;" required>
                            Individual
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="contractor_type" value="company" {{ old('contractor_type') == 'company' ? 'checked' : '' }} 
                                   class="mr-2" style="min-width: 18px; min-height: 18px;" required>
                            Company
                        </label>
                    </div>
                    @error('contractor_type')
                        <p class="text-xs mt-1" style="color: var(--danger, #ef4444);">{{ $message }}</p>
                    @enderror
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Contractor Name -->
                    <div>
                        <label for="contractor_name" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Contractor Name <span style="color: var(--danger, #ef4444);">*</span>
                        </label>
                        <input type="text" id="contractor_name" name="contractor_name" value="{{ old('contractor_name') }}" 
                               class="w-full p-2 rounded-lg border @error('contractor_name') border-red-500 @enderror" 
                               style="border-color: var(--border-color); background: var(--bg-primary); color: var(--text-primary); min-height: 44px;" 
                               placeholder="Full name or company name" required>
                        @error('contractor_name')
                            <p class="text-xs mt-1" style="color: var(--danger, #ef4444);">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <!-- Contractor Phone -->
                    <div>
                        <label for="contractor_phone" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Phone Number <span style="color: var(--danger, #ef4444);">*</span>
                        </label>
                        <input type="text" id="contractor_phone" name="contractor_phone" value="{{ old('contractor_phone') }}" 
                               class="w-full p-2 rounded-lg border @error('contractor_phone') border-red-500 @enderror" 
                               style="border-color: var(--border-color); background: var(--bg-primary); color: var(--text-primary); min-height: 44px;" 
                               placeholder="e.g., 0244123456" required>
                        @error('contractor_phone')
                            <p class="text-xs mt-1" style="color: var(--danger, #ef4444);">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                    <!-- Contractor Email -->
                    <div>
                        <label for="contractor_email" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Email Address
                        </label>
                        <input type="email" id="contractor_email" name="contractor_email" value="{{ old('contractor_email') }}" 
                               class="w-full p-2 rounded-lg border @error('contractor_email') border-red-500 @enderror" 
                               style="border-color: var(--border-color); background: var(--bg-primary); color: var(--text-primary); min-height: 44px;" 
                               placeholder="contractor@example.com">
                        @error('contractor_email')
                            <p class="text-xs mt-1" style="color: var(--danger, #ef4444);">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <!-- Contractor Address -->
                    <div>
                        <label for="contractor_address" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Address
                        </label>
                        <input type="text" id="contractor_address" name="contractor_address" value="{{ old('contractor_address') }}" 
                               class="w-full p-2 rounded-lg border @error('contractor_address') border-red-500 @enderror" 
                               style="border-color: var(--border-color); background: var(--bg-primary); color: var(--text-primary); min-height: 44px;" 
                               placeholder="Physical address">
                        @error('contractor_address')
                            <p class="text-xs mt-1" style="color: var(--danger, #ef4444);">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                
                <!-- Company Details (hidden by default) -->
                <div id="companyDetails" style="display: {{ old('contractor_type') == 'company' ? 'block' : 'none' }}; margin-top: 0.75rem;">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="company_registration_number" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                Registration Number
                            </label>
                            <input type="text" id="company_registration_number" name="company_registration_number" value="{{ old('company_registration_number') }}" 
                                   class="w-full p-2 rounded-lg border @error('company_registration_number') border-red-500 @enderror" 
                                   style="border-color: var(--border-color); background: var(--bg-primary); color: var(--text-primary); min-height: 44px;" 
                                   placeholder="Company registration number">
                            @error('company_registration_number')
                                <p class="text-xs mt-1" style="color: var(--danger, #ef4444);">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="company_tin" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                Tax Identification Number (TIN)
                            </label>
                            <input type="text" id="company_tin" name="company_tin" value="{{ old('company_tin') }}" 
                                   class="w-full p-2 rounded-lg border @error('company_tin') border-red-500 @enderror" 
                                   style="border-color: var(--border-color); background: var(--bg-primary); color: var(--text-primary); min-height: 44px;" 
                                   placeholder="Company TIN">
                            @error('company_tin')
                                <p class="text-xs mt-1" style="color: var(--danger, #ef4444);">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- SECTION 3: CONTRACT DETAILS -->
            <!-- ============================================ -->
            <div class="form-section" style="background: var(--bg-secondary, #f8f9fa); border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.25rem;">
                <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--primary, #3b82f6); display: inline-block; color: var(--text-primary, #1a1a2e);">
                    <i class="fas fa-file-contract mr-2" style="color: var(--primary);"></i> Contract Details
                </h4>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Contract Amount -->
                    <div>
                        <label for="contract_amount" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Contract Amount (₵)
                        </label>
                        <input type="number" id="contract_amount" name="contract_amount" value="{{ old('contract_amount') }}" 
                               class="w-full p-2 rounded-lg border @error('contract_amount') border-red-500 @enderror" 
                               style="border-color: var(--border-color); background: var(--bg-primary); color: var(--text-primary); min-height: 44px;" 
                               step="0.01" min="0" placeholder="0.00">
                        @error('contract_amount')
                            <p class="text-xs mt-1" style="color: var(--danger, #ef4444);">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <!-- Start Date -->
                    <div>
                        <label for="contract_start_date" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Start Date <span style="color: var(--danger, #ef4444);">*</span>
                        </label>
                        <input type="date" id="contract_start_date" name="contract_start_date" value="{{ old('contract_start_date') }}" 
                               class="w-full p-2 rounded-lg border @error('contract_start_date') border-red-500 @enderror" 
                               style="border-color: var(--border-color); background: var(--bg-primary); color: var(--text-primary); min-height: 44px;" required>
                        @error('contract_start_date')
                            <p class="text-xs mt-1" style="color: var(--danger, #ef4444);">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <!-- Estimated Completion Date -->
                    <div>
                        <label for="estimated_completion_date" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                            Estimated Completion <span style="color: var(--danger, #ef4444);">*</span>
                        </label>
                        <input type="date" id="estimated_completion_date" name="estimated_completion_date" value="{{ old('estimated_completion_date') }}" 
                               class="w-full p-2 rounded-lg border @error('estimated_completion_date') border-red-500 @enderror" 
                               style="border-color: var(--border-color); background: var(--bg-primary); color: var(--text-primary); min-height: 44px;" required>
                        @error('estimated_completion_date')
                            <p class="text-xs mt-1" style="color: var(--danger, #ef4444);">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- SECTION 4: WORK SCOPE (Optional) -->
            <!-- ============================================ -->
            <div class="form-section" style="background: var(--bg-secondary, #f8f9fa); border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.25rem;">
                <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--primary, #3b82f6); display: inline-block; color: var(--text-primary, #1a1a2e);">
                    <i class="fas fa-tasks mr-2" style="color: var(--primary);"></i> Work Scope (Optional)
                </h4>
                
                <div id="workScopeContainer">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3" id="workScopeItems">
                        <div class="work-scope-item flex gap-2">
                            <input type="text" name="work_scope_items[]" 
                                   class="flex-1 p-2 rounded-lg border" 
                                   style="border-color: var(--border-color); background: var(--bg-primary); color: var(--text-primary); min-height: 40px;" 
                                   placeholder="e.g., Foundation work">
                            <button type="button" onclick="removeWorkScope(this)" 
                                    class="text-red-500 hover:text-red-700 p-2" 
                                    style="background: none; border: none; cursor: pointer;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <button type="button" onclick="addWorkScope()" class="mt-2 text-sm text-blue-600 hover:text-blue-800">
                        <i class="fas fa-plus mr-1"></i> Add Work Scope Item
                    </button>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- SECTION 5: DECLARATION -->
            <!-- ============================================ -->
            <div class="form-section" style="background: var(--bg-secondary, #f8f9fa); border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.25rem;">
                <div class="flex items-start gap-3">
                    <input type="checkbox" id="declaration" name="declaration" value="1" 
                           class="mt-1" style="min-width: 20px; min-height: 20px; cursor: pointer;" required>
                    <label for="declaration" style="cursor: pointer; font-size: 0.95rem; color: var(--text-primary, #1a1a2e);">
                        I hereby declare that all information provided in this construction contract is true and correct to the best of my knowledge. 
                        I understand that providing false information may result in contract termination. <span style="color: var(--danger, #ef4444);">*</span>
                    </label>
                </div>
                @error('declaration')
                    <p class="text-xs mt-1" style="color: var(--danger, #ef4444);">{{ $message }}</p>
                @enderror
            </div>

            <!-- ============================================ -->
            <!-- FORM ACTIONS -->
            <!-- ============================================ -->
            <div class="flex flex-wrap justify-end gap-3 pt-4 border-t" style="border-color: var(--border-color);">
                <a href="{{ route('landlord.construction.contract.index') }}" 
                   class="px-6 py-2 border rounded-lg hover:bg-gray-50 transition-colors" 
                   style="border-color: var(--border-color); color: var(--text-primary); min-height: 44px; display: inline-flex; align-items: center;">
                    Cancel
                </a>
                <button type="submit" id="submitBtn" 
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center min-height: 44px;">
                    <i class="fas fa-paper-plane mr-2"></i> 
                    Submit Contract
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    border-top-color: #fff;
    animation: spin 0.6s ease-in-out infinite;
    margin-right: 8px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.work-scope-item {
    transition: all 0.3s ease;
}

.work-scope-item:hover {
    border-color: var(--primary, #3b82f6);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Contractor Type Toggle
    const contractorTypeRadios = document.querySelectorAll('input[name="contractor_type"]');
    const companyDetails = document.getElementById('companyDetails');
    
    contractorTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'company') {
                companyDetails.style.display = 'block';
            } else {
                companyDetails.style.display = 'none';
            }
        });
    });
    
    // Work Scope Functions
    window.addWorkScope = function() {
        const container = document.getElementById('workScopeItems');
        const html = `
            <div class="work-scope-item flex gap-2">
                <input type="text" name="work_scope_items[]" 
                       class="flex-1 p-2 rounded-lg border" 
                       style="border-color: var(--border-color); background: var(--bg-primary); color: var(--text-primary); min-height: 40px;" 
                       placeholder="e.g., Electrical installation">
                <button type="button" onclick="removeWorkScope(this)" 
                        class="text-red-500 hover:text-red-700 p-2" 
                        style="background: none; border: none; cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
    };
    
    window.removeWorkScope = function(button) {
        const container = document.getElementById('workScopeItems');
        const items = container.querySelectorAll('.work-scope-item');
        if (items.length <= 1) {
            alert('You must have at least one work scope item.');
            return;
        }
        button.closest('.work-scope-item').remove();
    };
    
    // Form Submission
    document.getElementById('contractForm')?.addEventListener('submit', function(e) {
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner"></span> Submitting...';
    });
});
</script>
@endsection