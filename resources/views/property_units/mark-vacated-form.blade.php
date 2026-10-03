{{-- resources/views/property_units/mark-vacated-form.blade.php --}}
@php
    $layout = auth()->user()->isLandlord() ? 'layouts.landlord' : 'layouts.app';
    $isLandlord = auth()->user()->isLandlord();
    $isAdmin = auth()->user()->isAdmin();
    $isSuperAdmin = auth()->user()->isSuperAdmin();
    
    // Determine route prefix based on user role
    if ($isLandlord) {
        $routePrefix = 'landlord.property-units';
    } elseif ($isAdmin || $isSuperAdmin) {
        $routePrefix = 'admin.property-units';
    } else {
        $routePrefix = 'property-units';
    }
@endphp

@extends($layout)

@section('title', 'Mark Tenant as Vacated - ' . $unit->full_unit_identifier)

@section('content')
<div class="max-w-2xl mx-auto p-4">
    <!-- Back Navigation -->
    <div class="mb-6">
        <a href="{{ route('property-units.show', $unit->id) }}" 
           class="inline-flex items-center text-sm font-medium transition-colors duration-200" 
           style="color: var(--primary);">
            <i class="fas fa-arrow-left mr-2"></i> Back to Unit Details
        </a>
    </div>

    <!-- Form Card -->
    <div class="card animate-fadeInUp">
        <div class="p-6">
            <!-- Header -->
            <div class="mb-6">
                <div class="flex items-center space-x-3 mb-2">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-sign-out-alt text-lg" style="color: var(--warning);"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold" style="color: var(--text-primary);">
                            Mark Tenant as Vacated
                        </h2>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            {{ $unit->full_unit_identifier }} - {{ $unit->tenant->name }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            @if(session('success'))
            <div class="mb-6 p-4 rounded-lg border" style="background: rgba(var(--success-rgb), 0.1); border-color: rgba(var(--success-rgb), 0.3);">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-3 text-lg" style="color: var(--success);"></i>
                    <span class="text-sm" style="color: var(--text-primary);">{{ session('success') }}</span>
                </div>
            </div>
            @endif

            @if(session('error'))
            <div class="mb-6 p-4 rounded-lg border" style="background: rgba(var(--danger-rgb), 0.1); border-color: rgba(var(--danger-rgb), 0.3);">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-3 text-lg" style="color: var(--danger);"></i>
                    <span class="text-sm" style="color: var(--text-primary);">{{ session('error') }}</span>
                </div>
            </div>
            @endif

            <!-- Information Alert -->
            <div class="mb-6 p-4 rounded-lg border" style="background: var(--bg-secondary); border-color: var(--border-color);">
                <div class="flex">
                    <i class="fas fa-info-circle mr-3 mt-0.5" style="color: var(--info);"></i>
                    <div>
                        <p class="text-sm mb-2" style="color: var(--text-primary);">
                            This action will:
                        </p>
                        <ul class="mt-1 text-sm space-y-1" style="color: var(--text-secondary);">
                            <li class="flex items-center">
                                <i class="fas fa-circle text-xs mr-2 opacity-60"></i>
                                Mark the tenant as vacated
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-circle text-xs mr-2 opacity-60"></i>
                                Record the move-out date and reason
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-circle text-xs mr-2 opacity-60"></i>
                                Update unit status based on your selection below
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-circle text-xs mr-2 opacity-60"></i>
                                Process security deposit refund if applicable
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Current Tenant Info -->
            <div class="mb-6 p-4 rounded-lg border" style="background: var(--bg-secondary); border-color: var(--border-color);">
                <h3 class="font-semibold mb-3 text-sm" style="color: var(--text-primary);">Current Tenant Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <p class="text-xs" style="color: var(--text-secondary);">Tenant Name</p>
                        <p class="font-medium text-sm" style="color: var(--text-primary);">{{ $unit->tenant->name }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs" style="color: var(--text-secondary);">Move-in Date</p>
                        <p class="font-medium text-sm" style="color: var(--text-primary);">
                            {{ $unit->tenant_move_in_date ? $unit->tenant_move_in_date->format('M d, Y') : 'Not recorded' }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs" style="color: var(--text-secondary);">Current Status</p>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                              style="background: rgba(var(--success-rgb), 0.15); color: var(--success);">
                            <i class="fas fa-check-circle mr-1 text-xs"></i> Approved
                        </span>
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs" style="color: var(--text-secondary);">Security Deposit</p>
                        <p class="font-medium text-sm" style="color: var(--text-primary);">
                            ₵{{ number_format($unit->security_deposit, 2) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Vacating Form -->
            <form action="{{ route($routePrefix . '.mark-vacated', $unit->id) }}" method="POST">
                @csrf
                @method('PUT')
                
                <div class="space-y-6">
                    <!-- Move-out Date -->
                    <div>
                        <label for="move_out_date" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Move-out Date <span class="text-danger ml-1">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-calendar" style="color: var(--text-secondary);"></i>
                            </div>
                            <input type="date" 
                                   id="move_out_date" 
                                   name="move_out_date" 
                                   value="{{ old('move_out_date', date('Y-m-d')) }}"
                                   class="index-custom-input pl-10 w-full"
                                   required
                                   max="{{ date('Y-m-d') }}">
                        </div>
                        <p class="mt-2 text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1 text-xs"></i> The date the tenant actually moved out
                        </p>
                        @error('move_out_date')
                            <p class="mt-2 text-xs flex items-center" style="color: var(--danger);">
                                <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Reason for Vacating -->
                    <div>
                        <label for="reason" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Reason for Vacating <span class="text-danger ml-1">*</span>
                        </label>
                        <select id="reason" name="reason" class="index-custom-dropdown w-full" required>
                            <option value="">Select reason...</option>
                            <option value="lease_ended" {{ old('reason') == 'lease_ended' ? 'selected' : '' }}>Lease Ended</option>
                            <option value="tenant_requested" {{ old('reason') == 'tenant_requested' ? 'selected' : '' }}>Tenant Requested Early Move-out</option>
                            <option value="mutual_agreement" {{ old('reason') == 'mutual_agreement' ? 'selected' : '' }}>Mutual Agreement</option>
                            <option value="eviction" {{ old('reason') == 'eviction' ? 'selected' : '' }}>Eviction</option>
                            <option value="transfer" {{ old('reason') == 'transfer' ? 'selected' : '' }}>Company Transfer</option>
                            <option value="personal_reasons" {{ old('reason') == 'personal_reasons' ? 'selected' : '' }}>Personal Reasons</option>
                            <option value="other" {{ old('reason') == 'other' ? 'selected' : '' }}>Other</option>
                        </select>
                        @error('reason')
                            <p class="mt-2 text-xs flex items-center" style="color: var(--danger);">
                                <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Additional Notes -->
                    <div>
                        <label for="notes" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Additional Notes
                        </label>
                        <textarea id="notes" name="notes" rows="3" 
                                  class="index-custom-textarea w-full resize-none"
                                  placeholder="Any additional information about the tenant vacating...">{{ old('notes') }}</textarea>
                        <p class="mt-2 text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1 text-xs"></i> Optional: Include details about condition of unit, forwarding address, etc.
                        </p>
                        @error('notes')
                            <p class="mt-2 text-xs flex items-center" style="color: var(--danger);">
                                <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Property Condition -->
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Property Condition <span class="text-danger ml-1">*</span>
                        </label>
                        <div class="space-y-2">
                            <div class="flex items-center p-2 rounded-lg transition-colors" 
                                 style="background: rgba(var(--success-rgb), 0.05);">
                                <input type="radio" 
                                       id="condition_good" 
                                       name="property_condition" 
                                       value="good"
                                       class="index-custom-radio"
                                       {{ old('property_condition', 'good') == 'good' ? 'checked' : '' }}>
                                <label for="condition_good" class="ml-3 text-sm cursor-pointer flex-1" style="color: var(--text-primary);">
                                    <span class="font-medium">Good</span>
                                    <span class="block text-xs mt-1" style="color: var(--text-secondary);">No damage or minor wear and tear - No cleaning required</span>
                                </label>
                            </div>
                            
                            <div class="flex items-center p-2 rounded-lg transition-colors" 
                                 style="background: rgba(var(--warning-rgb), 0.05);">
                                <input type="radio" 
                                       id="condition_fair" 
                                       name="property_condition" 
                                       value="fair"
                                       class="index-custom-radio"
                                       {{ old('property_condition') == 'fair' ? 'checked' : '' }}>
                                <label for="condition_fair" class="ml-3 text-sm cursor-pointer flex-1" style="color: var(--text-primary);">
                                    <span class="font-medium">Fair</span>
                                    <span class="block text-xs mt-1" style="color: var(--text-secondary);">Minor damage requiring repair - Cleaning recommended</span>
                                </label>
                            </div>
                            
                            <div class="flex items-center p-2 rounded-lg transition-colors" 
                                 style="background: rgba(var(--danger-rgb), 0.05);">
                                <input type="radio" 
                                       id="condition_poor" 
                                       name="property_condition" 
                                       value="poor"
                                       class="index-custom-radio"
                                       {{ old('property_condition') == 'poor' ? 'checked' : '' }}>
                                <label for="condition_poor" class="ml-3 text-sm cursor-pointer flex-1" style="color: var(--text-primary);">
                                    <span class="font-medium">Poor</span>
                                    <span class="block text-xs mt-1" style="color: var(--text-secondary);">Significant damage requiring major repair - Cleaning required</span>
                                </label>
                            </div>
                        </div>
                        @error('property_condition')
                            <p class="mt-2 text-xs flex items-center" style="color: var(--danger);">
                                <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Unit Status After Vacating -->
                    <div class="p-4 rounded-lg border" style="background: var(--bg-secondary); border-color: var(--border-color);">
                        <label class="block text-sm font-medium mb-3" style="color: var(--text-primary);">
                            <i class="fas fa-flag mr-2" style="color: var(--primary);"></i> Unit Status After Vacating <span class="text-danger ml-1">*</span>
                        </label>
                        
                        <div class="space-y-3">
                            <div class="flex items-center p-3 rounded-lg transition-colors cursor-pointer" 
                                 style="background: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);"
                                 onclick="document.getElementById('status_maintenance').click();">
                                <input type="radio" 
                                       id="status_maintenance" 
                                       name="unit_status_after_vacate" 
                                       value="maintenance"
                                       class="index-custom-radio"
                                       style="accent-color: var(--warning);"
                                       {{ old('unit_status_after_vacate', 'maintenance') == 'maintenance' ? 'checked' : '' }}
                                       required>
                                <label for="status_maintenance" class="ml-3 flex-1 cursor-pointer">
                                    <div class="flex items-center">
                                        <span class="font-medium mr-2" style="color: var(--warning);">Under Maintenance</span>
                                        <span class="text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--warning-rgb), 0.2); color: var(--warning);">Recommended</span>
                                    </div>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Unit needs repairs, cleaning, or inspections before being re-listed. 
                                        Will be marked as under maintenance and unavailable for new tenants until ready.
                                    </p>
                                </label>
                            </div>
                            
                            <div class="flex items-center p-3 rounded-lg transition-colors cursor-pointer" 
                                 style="background: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);"
                                 onclick="document.getElementById('status_available').click();">
                                <input type="radio" 
                                       id="status_available" 
                                       name="unit_status_after_vacate" 
                                       value="available"
                                       class="index-custom-radio"
                                       style="accent-color: var(--success);"
                                       {{ old('unit_status_after_vacate') == 'available' ? 'checked' : '' }}
                                       required>
                                <label for="status_available" class="ml-3 flex-1 cursor-pointer">
                                    <div class="flex items-center">
                                        <span class="font-medium mr-2" style="color: var(--success);">Available Immediately</span>
                                        <span class="text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--success-rgb), 0.2); color: var(--success);">Ready for Rent</span>
                                    </div>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                        Unit is in good condition and ready for new tenants immediately. 
                                        Will be marked as available and can be listed for rent right away.
                                    </p>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Dynamic message based on property condition -->
                        <div id="statusRecommendation" class="mt-3 p-2 rounded-lg text-xs" style="background: rgba(var(--info-rgb), 0.1); color: var(--info); display: none;">
                            <i class="fas fa-info-circle mr-1"></i>
                            <span id="recommendationMessage"></span>
                        </div>
                        
                        @error('unit_status_after_vacate')
                            <p class="mt-2 text-xs flex items-center" style="color: var(--danger);">
                                <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Cleaning Required Checkbox -->
                    <div class="flex items-center p-3 rounded-lg border" 
                         style="background: var(--bg-secondary); border-color: var(--border-color);">
                        <input type="checkbox" 
                               id="cleaning_required" 
                               name="cleaning_required" 
                               value="1"
                               class="index-custom-checkbox"
                               {{ old('cleaning_required', $unit->property_condition === 'poor' || $unit->property_condition === 'fair') ? 'checked' : '' }}>
                        <label for="cleaning_required" class="ml-2 text-sm flex items-center cursor-pointer" style="color: var(--text-primary);">
                            <i class="fas fa-broom mr-2" style="color: var(--primary);"></i> 
                            <span>Cleaning Required</span>
                            <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">Recommended for Fair/Poor condition</span>
                        </label>
                    </div>

                    <!-- Damages Noted Textarea -->
                    <div>
                        <label for="damages_noted" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Damages Noted
                        </label>
                        <textarea id="damages_noted" name="damages_noted" rows="3" 
                                  class="index-custom-textarea w-full resize-none"
                                  placeholder="Describe any damages to the property, repairs needed, items requiring replacement, etc.">{{ old('damages_noted') }}</textarea>
                        <p class="mt-2 text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1 text-xs"></i> Document any damages for deposit deduction records and maintenance planning
                        </p>
                        @error('damages_noted')
                            <p class="mt-2 text-xs flex items-center" style="color: var(--danger);">
                                <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Security Deposit Refund -->
                    <div class="p-4 rounded-lg border" style="background: var(--bg-secondary); border-color: var(--border-color);">
                        <div class="flex items-center mb-3">
                            <input type="checkbox" 
                                   id="refund_deposit" 
                                   name="refund_deposit" 
                                   value="1"
                                   class="index-custom-checkbox"
                                   {{ old('refund_deposit') ? 'checked' : '' }}>
                            <label for="refund_deposit" class="ml-2 block text-sm font-medium" style="color: var(--text-primary);">
                                Process Security Deposit Refund
                            </label>
                        </div>
                        <p class="text-sm mb-3" style="color: var(--text-secondary);">
                            Security deposit amount: <span class="font-semibold" style="color: var(--text-primary);">₵{{ number_format($unit->security_deposit, 2) }}</span>
                        </p>
                        
                        <!-- Refund Amount Field (shown when checkbox is checked) -->
                        <div id="refundAmountContainer" class="space-y-3" style="display: none;">
                            <div>
                                <label for="deposit_refund_amount" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                    Refund Amount <span class="text-danger ml-1">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span style="color: var(--text-secondary);">₵</span>
                                    </div>
                                    <input type="number" 
                                           id="deposit_refund_amount" 
                                           name="deposit_refund_amount" 
                                           min="0" 
                                           max="{{ $unit->security_deposit }}" 
                                           step="0.01"
                                           value="{{ old('deposit_refund_amount', $unit->security_deposit) }}"
                                           class="index-custom-input pl-10 w-full"
                                           placeholder="0.00">
                                </div>
                                <p class="mt-2 text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1 text-xs"></i> Maximum refundable amount: ₵{{ number_format($unit->security_deposit, 2) }}
                                </p>
                                @error('deposit_refund_amount')
                                    <p class="mt-2 text-xs flex items-center" style="color: var(--danger);">
                                        <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                    </p>
                                @enderror
                            </div>
                            
                            <div>
                                <label for="refund_notes" class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                    Refund Notes (Optional)
                                </label>
                                <textarea id="refund_notes" name="refund_notes" rows="2"
                                          class="index-custom-textarea w-full resize-none"
                                          placeholder="Notes about deductions or refund details...">{{ old('refund_notes') }}</textarea>
                                <p class="mt-2 text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1 text-xs"></i> Explain any deductions from the security deposit
                                </p>
                                @error('refund_notes')
                                    <p class="mt-2 text-xs flex items-center" style="color: var(--danger);">
                                        <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                    </p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Final Keys Returned -->
                    <div class="flex items-center p-3 rounded-lg border" 
                         style="background: var(--bg-secondary); border-color: var(--border-color);">
                        <input type="checkbox" 
                               id="keys_returned" 
                               name="keys_returned" 
                               value="1"
                               class="index-custom-checkbox"
                               {{ old('keys_returned') ? 'checked' : '' }}>
                        <label for="keys_returned" class="ml-2 text-sm flex items-center cursor-pointer" style="color: var(--text-primary);">
                            <i class="fas fa-key mr-2" style="color: var(--primary);"></i> All keys have been returned by tenant
                        </label>
                    </div>

                    <!-- Final Inspection Completed -->
                    <div class="flex items-center p-3 rounded-lg border" 
                         style="background: var(--bg-secondary); border-color: var(--border-color);">
                        <input type="checkbox" 
                               id="inspection_completed" 
                               name="inspection_completed" 
                               value="1"
                               class="index-custom-checkbox"
                               {{ old('inspection_completed') ? 'checked' : '' }}>
                        <label for="inspection_completed" class="ml-2 text-sm flex items-center cursor-pointer" style="color: var(--text-primary);">
                            <i class="fas fa-clipboard-check mr-2" style="color: var(--primary);"></i> Final inspection has been completed
                        </label>
                    </div>

                    <!-- Confirmation Checkbox -->
                    <div class="p-4 rounded-lg border" 
                         style="background: rgba(var(--danger-rgb), 0.1); border-color: rgba(var(--danger-rgb), 0.3);">
                        <div class="flex items-start">
                            <input type="checkbox" 
                                   id="confirm_action" 
                                   name="confirm_action" 
                                   value="1"
                                   class="index-custom-checkbox mt-1"
                                   required>
                            <label for="confirm_action" class="ml-2 text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-exclamation-triangle mr-1" style="color: var(--danger);"></i>
                                <span class="font-medium">I confirm that:</span>
                                <ul class="list-disc ml-5 mt-1 text-xs space-y-1" style="color: var(--text-secondary);">
                                    <li>The tenant has permanently vacated the unit</li>
                                    <li>The move-out date is accurate</li>
                                    <li>All information provided about property condition is truthful</li>
                                    <li>I have the authority to mark this unit as vacated</li>
                                </ul>
                                <span class="ml-1 text-xs block mt-2" style="color: var(--danger);">* Required</span>
                            </label>
                        </div>
                        @error('confirm_action')
                            <p class="mt-2 text-xs flex items-center" style="color: var(--danger);">
                                <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="mt-6 pt-6 border-t flex justify-end space-x-3" style="border-color: var(--border-color);">
                    <a href="{{ route('property-units.show', $unit->id) }}" 
                       class="px-5 py-2.5 text-sm font-medium rounded-lg border transition-all duration-200 hover:transform hover:-translate-y-0.5 btn-secondary"
                       style="background: var(--bg-secondary); border-color: var(--border-color); color: var(--text-primary);">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-5 py-2.5 text-sm font-medium rounded-lg transition-all duration-200 hover:transform hover:-translate-y-0.5 shadow-lg hover:shadow-xl"
                            style="background: linear-gradient(135deg, var(--warning), #ff9f43); color: white; border: none;">
                        <i class="fas fa-sign-out-alt mr-2"></i> Mark as Vacated
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" style="position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 350px;"></div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle refund amount field
    const refundCheckbox = document.getElementById('refund_deposit');
    const refundContainer = document.getElementById('refundAmountContainer');
    const refundAmountInput = document.getElementById('deposit_refund_amount');
    const refundNotesInput = document.getElementById('refund_notes');
    
    if (refundCheckbox && refundContainer) {
        refundCheckbox.addEventListener('change', function() {
            if (this.checked) {
                refundContainer.style.display = 'block';
                if (refundAmountInput) {
                    refundAmountInput.required = true;
                    refundAmountInput.focus();
                }
            } else {
                refundContainer.style.display = 'none';
                if (refundAmountInput) {
                    refundAmountInput.required = false;
                }
            }
        });
        
        // Initialize on page load
        if (refundCheckbox.checked) {
            refundContainer.style.display = 'block';
            if (refundAmountInput) {
                refundAmountInput.required = true;
            }
        }
    }
    
    // Validate refund amount doesn't exceed security deposit
    if (refundAmountInput) {
        refundAmountInput.addEventListener('input', function() {
            const maxAmount = parseFloat('{{ $unit->security_deposit }}');
            const currentValue = parseFloat(this.value);
            
            if (currentValue > maxAmount) {
                this.value = maxAmount.toFixed(2);
                showToast('warning', `Maximum refund amount is ₵${maxAmount.toFixed(2)}`);
            }
            
            if (currentValue < maxAmount && refundNotesInput && !refundNotesInput.value.trim()) {
                refundNotesInput.style.borderColor = 'var(--warning)';
            } else if (refundNotesInput) {
                refundNotesInput.style.borderColor = '';
            }
        });
    }
    
    // Auto-populate damages_noted based on property condition
    const propertyConditionRadios = document.querySelectorAll('input[name="property_condition"]');
    const damagesNotedField = document.getElementById('damages_noted');
    const cleaningRequiredCheckbox = document.getElementById('cleaning_required');
    const statusMaintenance = document.getElementById('status_maintenance');
    const statusAvailable = document.getElementById('status_available');
    const recommendationDiv = document.getElementById('statusRecommendation');
    const recommendationMessage = document.getElementById('recommendationMessage');
    
    propertyConditionRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'poor') {
                // Auto-suggest damages for poor condition
                if (damagesNotedField && !damagesNotedField.value.trim()) {
                    damagesNotedField.value = 'Significant damage requiring major repair. Please specify details below.';
                }
                // Auto-check cleaning required for poor condition
                if (cleaningRequiredCheckbox) {
                    cleaningRequiredCheckbox.checked = true;
                }
                // Show recommendation for status
                if (recommendationDiv && recommendationMessage) {
                    recommendationDiv.style.display = 'block';
                    recommendationMessage.textContent = 'Based on Poor condition, we strongly recommend "Under Maintenance" status.';
                }
                // Auto-select maintenance for poor condition if nothing selected
                if (statusMaintenance && !statusMaintenance.checked && !statusAvailable.checked) {
                    statusMaintenance.checked = true;
                }
            } else if (this.value === 'fair') {
                // Auto-suggest damages for fair condition
                if (damagesNotedField && !damagesNotedField.value.trim()) {
                    damagesNotedField.value = 'Minor damage requiring some repair. Please specify details below.';
                }
                // Auto-check cleaning required for fair condition
                if (cleaningRequiredCheckbox) {
                    cleaningRequiredCheckbox.checked = true;
                }
                // Show recommendation for status
                if (recommendationDiv && recommendationMessage) {
                    recommendationDiv.style.display = 'block';
                    recommendationMessage.textContent = 'Based on Fair condition, we recommend "Under Maintenance" status.';
                }
            } else if (this.value === 'good') {
                // Clear damages for good condition
                if (damagesNotedField && (damagesNotedField.value.includes('Significant damage') || 
                    damagesNotedField.value.includes('Minor damage'))) {
                    damagesNotedField.value = '';
                }
                // Optionally uncheck cleaning for good condition
                if (cleaningRequiredCheckbox && !cleaningRequiredCheckbox.hasAttribute('data-manually-changed')) {
                    cleaningRequiredCheckbox.checked = false;
                }
                // Hide recommendation
                if (recommendationDiv) {
                    recommendationDiv.style.display = 'none';
                }
            }
        });
    });
    
    // Track manual changes to cleaning required
    if (cleaningRequiredCheckbox) {
        cleaningRequiredCheckbox.addEventListener('change', function() {
            this.setAttribute('data-manually-changed', 'true');
        });
    }
    
    // Set max date for move-out date to today
    const moveOutDateInput = document.getElementById('move_out_date');
    if (moveOutDateInput) {
        const today = new Date().toISOString().split('T')[0];
        moveOutDateInput.max = today;
        
        // Validate date isn't in future on change
        moveOutDateInput.addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate > today) {
                this.value = today.toISOString().split('T')[0];
                showToast('warning', 'Move-out date cannot be in the future');
            }
        });
    }
    
    // Add focus styles for form elements
    const formInputs = document.querySelectorAll('.index-custom-input, .index-custom-dropdown, .index-custom-textarea');
    formInputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.style.borderColor = 'var(--primary)';
            this.style.boxShadow = '0 0 0 3px rgba(var(--primary-rgb), 0.2)';
        });
        
        input.addEventListener('blur', function() {
            this.style.borderColor = '';
            this.style.boxShadow = '';
        });
    });
    
    // Add hover effects to checkboxes and radio buttons
    const interactiveInputs = document.querySelectorAll('input[type="checkbox"], input[type="radio"]');
    interactiveInputs.forEach(input => {
        input.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.1)';
            this.style.transition = 'transform 0.2s ease';
        });
        
        input.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
    
    // Form validation before submit
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const confirmCheckbox = document.getElementById('confirm_action');
            if (!confirmCheckbox.checked) {
                e.preventDefault();
                showToast('error', 'You must confirm that the tenant has vacated the unit');
                confirmCheckbox.focus();
                return;
            }
            
            const reasonSelect = document.getElementById('reason');
            if (!reasonSelect.value) {
                e.preventDefault();
                showToast('error', 'Please select a reason for vacating');
                reasonSelect.focus();
                return;
            }
            
            const propertyCondition = document.querySelector('input[name="property_condition"]:checked');
            if (!propertyCondition) {
                e.preventDefault();
                showToast('error', 'Please select the property condition');
                return;
            }
            
            const unitStatus = document.querySelector('input[name="unit_status_after_vacate"]:checked');
            if (!unitStatus) {
                e.preventDefault();
                showToast('error', 'Please select the unit status after vacating');
                return;
            }
            
            const moveOutDate = document.getElementById('move_out_date');
            if (moveOutDate && !moveOutDate.value) {
                e.preventDefault();
                showToast('error', 'Please select a move-out date');
                moveOutDate.focus();
                return;
            }
            
            // Validate refund fields if checked
            if (refundCheckbox && refundCheckbox.checked) {
                if (!refundAmountInput.value || parseFloat(refundAmountInput.value) <= 0) {
                    e.preventDefault();
                    showToast('error', 'Please enter a valid refund amount');
                    refundAmountInput.focus();
                    return;
                }
                
                if (parseFloat(refundAmountInput.value) > parseFloat(refundAmountInput.max)) {
                    e.preventDefault();
                    showToast('error', `Refund amount cannot exceed ₵${refundAmountInput.max}`);
                    refundAmountInput.focus();
                    return;
                }
            }
        });
    }
    
    // Toast notification function
    function showToast(type, message) {
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                max-width: 350px;
            `;
            document.body.appendChild(toastContainer);
        }
        
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.style.cssText = `
            padding: 12px 16px;
            margin-bottom: 10px;
            border-radius: 12px;
            font-size: 14px;
            animation: slideInRight 0.3s ease-out;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        `;
        
        const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
        const colors = {
            success: {
                bg: isDarkMode ? 'rgba(40, 199, 111, 0.2)' : 'rgba(40, 199, 111, 0.1)',
                border: 'rgba(40, 199, 111, 0.4)',
                text: isDarkMode ? '#3ae187' : '#28c76f'
            },
            error: {
                bg: isDarkMode ? 'rgba(234, 84, 85, 0.2)' : 'rgba(234, 84, 85, 0.1)',
                border: 'rgba(234, 84, 85, 0.4)',
                text: isDarkMode ? '#ff6b6b' : '#ea5455'
            },
            warning: {
                bg: isDarkMode ? 'rgba(255, 159, 67, 0.2)' : 'rgba(255, 159, 67, 0.1)',
                border: 'rgba(255, 159, 67, 0.4)',
                text: isDarkMode ? '#ffb74d' : '#ff9f43'
            },
            info: {
                bg: isDarkMode ? 'rgba(0, 207, 232, 0.2)' : 'rgba(0, 207, 232, 0.1)',
                border: 'rgba(0, 207, 232, 0.4)',
                text: isDarkMode ? '#45d4e8' : '#00cfe8'
            }
        };
        
        const colorSet = colors[type] || colors.info;
        toast.style.backgroundColor = colorSet.bg;
        toast.style.borderColor = colorSet.border;
        toast.style.color = colorSet.text;
        
        toast.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} mr-3 text-lg"></i>
                <span>${message}</span>
            </div>
            <button type="button" class="ml-4 opacity-70 hover:opacity-100 transition-opacity" style="background: none; border: none; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        const closeBtn = toast.querySelector('button');
        closeBtn.addEventListener('click', () => {
            toast.style.animation = 'slideOutRight 0.3s ease-out forwards';
            setTimeout(() => toast.remove(), 300);
        });
        
        setTimeout(() => {
            if (toast.parentNode) {
                toast.style.animation = 'slideOutRight 0.3s ease-out forwards';
                setTimeout(() => toast.remove(), 300);
            }
        }, 5000);
        
        toastContainer.appendChild(toast);
    }
    
    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-fadeInUp {
            animation: fadeInUp 0.5s ease-out;
        }
        
        .card {
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        /* Ensure proper focus styles */
        :focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }
    `;
    document.head.appendChild(style);
});
</script>
@endsection

@section('styles')
<style>
/* Card Animation */
.animate-fadeInUp {
    animation: fadeInUp 0.5s ease-out;
}

/* Index-specific form control styles matching the main index blade */
.index-custom-dropdown {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 2.5rem;
}

/* Update SVG icon color for dark/light mode */
[data-theme="dark"] .index-custom-dropdown {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%23e4e4e4' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
}

[data-theme="light"] .index-custom-dropdown {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%234b4b4b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
}

.index-custom-dropdown:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

/* Custom input styles */
.index-custom-input {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
}

.index-custom-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

/* Custom textarea styles */
.index-custom-textarea {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
    transition: all 0.3s ease;
    resize: vertical;
}

.index-custom-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

/* Custom checkbox styles */
.index-custom-checkbox {
    width: 1rem;
    height: 1rem;
    border-radius: 0.25rem;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
    cursor: pointer;
    transition: all 0.2s;
}

.index-custom-checkbox:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

/* Custom radio styles */
.index-custom-radio {
    width: 1rem;
    height: 1rem;
    cursor: pointer;
    accent-color: var(--primary);
}

/* Button styles */
.btn-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
    transition: all 0.2s ease;
}

.btn-secondary:hover {
    background-color: rgba(var(--secondary-rgb), 0.2) !important;
    transform: translateY(-1px);
}

/* Clickable card styles */
.cursor-pointer {
    cursor: pointer;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .max-w-2xl {
        max-width: 100%;
        padding: 0 1rem;
    }
    
    .grid.grid-cols-1.md\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .flex.justify-end.space-x-3 {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .flex.justify-end.space-x-3 > * {
        width: 100%;
        text-align: center;
    }
}

/* Print styles */
@media print {
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    
    button, a, .btn-secondary, .btn-primary, #toast-container {
        display: none !important;
    }
    
    [style*="background"] {
        background: #f5f5f5 !important;
        color: black !important;
    }
}

/* Accessibility focus styles */
:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

/* Card hover effect */
.card {
    transition: transform 0.3s, box-shadow 0.3s;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}
</style>
@endsection