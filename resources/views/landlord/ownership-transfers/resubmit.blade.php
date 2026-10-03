@extends('layouts.landlord')

@section('title', 'Resubmit Ownership Transfer')

@section('content')
<div class="max-w-4xl mx-auto py-6">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-redo mr-2" style="color: var(--primary);"></i> 
                    Resubmit Transfer Request
                </h1>
                <p class="mt-1" style="color: var(--text-secondary);">
                    Property: <strong>{{ $property->property_name }}</strong> | 
                    Document Ref: <strong>{{ $documentReference }}</strong>
                </p>
            </div>
            <a href="{{ route('landlord.ownership-transfers.index') }}" 
               class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Transfers
            </a>
        </div>
    </div>

    <!-- Rejection Reason Card -->
    <div class="card mb-6" style="border-left: 4px solid var(--danger);">
        <div class="p-4">
            <div class="flex items-start">
                <i class="fas fa-exclamation-circle text-xl mr-3" style="color: var(--danger);"></i>
                <div>
                    <h3 class="font-semibold" style="color: var(--danger);">Previous Request Was Rejected</h3>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        <strong>Reason for rejection:</strong> {{ $rejectionReason ?? 'No reason provided' }}
                    </p>
                    <p class="text-sm mt-2" style="color: var(--warning);">
                        <i class="fas fa-info-circle mr-1"></i>
                        Please review the rejection reason above and make the necessary corrections before resubmitting.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Resubmit Form -->
    <div class="card">
        <div class="p-6">
            {{-- FIXED: Changed route to process-resubmit (POST route) --}}
            <form method="POST" 
                  action="{{ route('properties.ownership-transfers.process-resubmit', [$property->id, $transfer->id]) }}" 
                  enctype="multipart/form-data"
                  class="space-y-6">
                @csrf
                
                <!-- Form Fields (pre-filled with previous data) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- New Owner Section -->
                    <div class="md:col-span-2">
                        <h3 class="text-lg font-semibold mb-4 pb-2 border-b" style="color: var(--text-primary); border-color: var(--border-color);">
                            <i class="fas fa-user-plus mr-2" style="color: var(--primary);"></i> New Owner Information
                        </h3>
                    </div>
                    
                    <!-- Existing Landlord Toggle -->
                    <div class="md:col-span-2">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" 
                                   id="use_existing_landlord" 
                                   name="use_existing_landlord" 
                                   value="1"
                                   {{ $isExistingLandlord ? 'checked' : '' }}
                                   class="form-checkbox h-4 w-4 rounded"
                                   style="color: var(--primary);">
                            <span class="ml-2 text-sm" style="color: var(--text-primary);">
                                This new owner already has a landlord account in the system
                            </span>
                        </label>
                    </div>
                    
                    <!-- Existing Landlord Select (hidden by default) -->
                    <div id="existing_landlord_section" class="md:col-span-2" style="{{ $isExistingLandlord ? '' : 'display: none;' }}">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Select Existing Landlord <span class="text-danger">*</span>
                        </label>
                        <select name="existing_landlord_id" 
                                id="existing_landlord_id"
                                class="index-custom-select w-full">
                            <option value="">-- Select Landlord --</option>
                            @foreach(\App\Models\User::where('type', \App\Models\User::TYPE_LANDLORD)->orderBy('name')->get() as $landlord)
                                <option value="{{ $landlord->id }}" {{ $existingLandlordId == $landlord->id ? 'selected' : '' }}>
                                    {{ $landlord->name }} - {{ $landlord->email }} ({{ $landlord->phone }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <!-- New Landlord Fields (visible when not using existing) -->
                    <div id="new_landlord_section" class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6" style="{{ $isExistingLandlord ? 'display: none;' : '' }}">
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                Full Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   name="new_owner_name" 
                                   value="{{ old('new_owner_name', $newOwnerName) }}"
                                   class="index-custom-input w-full @error('new_owner_name') border-danger @enderror"
                                   required>
                            @error('new_owner_name')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                Phone Number <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   name="new_owner_phone" 
                                   value="{{ old('new_owner_phone', $newOwnerPhone) }}"
                                   class="index-custom-input w-full @error('new_owner_phone') border-danger @enderror"
                                   required>
                            @error('new_owner_phone')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                Email Address
                            </label>
                            <input type="email" 
                                   name="new_owner_email" 
                                   value="{{ old('new_owner_email', $newOwnerEmail) }}"
                                   class="index-custom-input w-full @error('new_owner_email') border-danger @enderror">
                            @error('new_owner_email')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                Physical Address
                            </label>
                            <input type="text" 
                                   name="new_owner_address" 
                                   value="{{ old('new_owner_address', $newOwnerAddress) }}"
                                   class="index-custom-input w-full @error('new_owner_address') border-danger @enderror">
                            @error('new_owner_address')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    
                    <!-- Transfer Details -->
                    <div class="md:col-span-2">
                        <h3 class="text-lg font-semibold mb-4 pb-2 border-t pt-4" style="color: var(--text-primary); border-color: var(--border-color);">
                            <i class="fas fa-file-alt mr-2" style="color: var(--primary);"></i> Transfer Details
                        </h3>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Transfer Date <span class="text-danger">*</span>
                        </label>
                        <input type="date" 
                               name="transfer_date" 
                               value="{{ old('transfer_date', $transferDate) }}"
                               class="index-custom-input w-full @error('transfer_date') border-danger @enderror"
                               required>
                        @error('transfer_date')
                            <p class="text-danger text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Sale Amount (GHS)
                        </label>
                        <input type="number" 
                               name="sale_amount" 
                               value="{{ old('sale_amount', $saleAmount) }}"
                               step="0.01"
                               class="index-custom-input w-full @error('sale_amount') border-danger @enderror">
                        @error('sale_amount')
                            <p class="text-danger text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Document Type <span class="text-danger">*</span>
                        </label>
                        <select name="document_type" 
                                class="index-custom-select w-full @error('document_type') border-danger @enderror"
                                required>
                            @foreach($documentTypes as $key => $label)
                                <option value="{{ $key }}" {{ old('document_type', $documentType) == $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('document_type')
                            <p class="text-danger text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Document Reference <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               name="document_reference" 
                               value="{{ old('document_reference', $documentReference) }}"
                               class="index-custom-input w-full @error('document_reference') border-danger @enderror"
                               required>
                        @error('document_reference')
                            <p class="text-danger text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Transfer Document (Optional - leave empty to reuse previous document)
                        </label>
                        <input type="file" 
                               name="transfer_document" 
                               accept=".pdf,.jpg,.jpeg,.png"
                               class="index-custom-input w-full @error('transfer_document') border-danger @enderror">
                        @if($previousDocumentUrl)
                            <p class="text-xs mt-1" style="color: var(--success);">
                                <i class="fas fa-check-circle mr-1"></i>
                                Previous document: {{ basename($previousDocumentUrl) }}
                            </p>
                        @endif
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Accepted formats: PDF, JPG, JPEG, PNG. Max size: 5MB
                        </p>
                        @error('transfer_document')
                            <p class="text-danger text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Reason for Transfer
                        </label>
                        <textarea name="reason_for_transfer" 
                                  rows="3"
                                  class="index-custom-textarea w-full @error('reason_for_transfer') border-danger @enderror"
                                  placeholder="e.g., Sale of property, Gift, Inheritance, etc.">{{ old('reason_for_transfer', $reasonForTransfer) }}</textarea>
                        @error('reason_for_transfer')
                            <p class="text-danger text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Additional Notes
                        </label>
                        <textarea name="notes" 
                                  rows="2"
                                  class="index-custom-textarea w-full @error('notes') border-danger @enderror"
                                  placeholder="Any additional information for the admin...">{{ old('notes', $notes) }}</textarea>
                        @error('notes')
                            <p class="text-danger text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                
                <!-- Submit Buttons -->
                <div class="flex justify-end space-x-3 pt-4 border-t" style="border-color: var(--border-color);">
                    <a href="{{ route('landlord.ownership-transfers.index') }}" 
                       class="btn-secondary px-6 py-2 rounded-lg font-medium">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="btn-primary px-6 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-paper-plane mr-2"></i> Resubmit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const useExistingCheckbox = document.getElementById('use_existing_landlord');
    const existingSection = document.getElementById('existing_landlord_section');
    const newSection = document.getElementById('new_landlord_section');
    
    if (useExistingCheckbox) {
        useExistingCheckbox.addEventListener('change', function() {
            if (this.checked) {
                existingSection.style.display = 'block';
                newSection.style.display = 'none';
                // Disable new landlord fields
                document.querySelectorAll('#new_landlord_section input, #new_landlord_section select, #new_landlord_section textarea').forEach(field => {
                    field.disabled = true;
                });
                // Enable existing landlord select
                document.getElementById('existing_landlord_id').disabled = false;
            } else {
                existingSection.style.display = 'none';
                newSection.style.display = 'grid';
                // Enable new landlord fields
                document.querySelectorAll('#new_landlord_section input, #new_landlord_section select, #new_landlord_section textarea').forEach(field => {
                    field.disabled = false;
                });
                // Disable existing landlord select
                document.getElementById('existing_landlord_id').disabled = true;
            }
        });
        
        // Trigger initial state
        useExistingCheckbox.dispatchEvent(new Event('change'));
    }
});
</script>

<style>
.index-custom-input,
.index-custom-select,
.index-custom-textarea {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.5rem;
    padding: 0.625rem 0.875rem;
    transition: all 0.2s ease;
}

.index-custom-input:focus,
.index-custom-select:focus,
.index-custom-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.form-checkbox {
    border-radius: 0.25rem;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
}

.form-checkbox:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

.text-danger {
    color: var(--danger);
}

.border-danger {
    border-color: var(--danger);
}
</style>
@endsection