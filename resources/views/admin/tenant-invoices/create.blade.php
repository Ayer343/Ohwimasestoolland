@extends('layouts.app')

@section('title', 'Create Community Development Invoice')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6">
            <div class="flex items-center mb-4 md:mb-0">
                <i class="fas fa-plus-circle text-2xl mr-3" style="color: var(--primary);"></i>
                <div>
                    <h2 class="text-xl font-semibold" style="color: var(--text-primary);">Create Community Development Invoice</h2>
                    <p class="text-sm" style="color: var(--text-secondary);">Generate a new community development dues invoice for a tenant</p>
                </div>
            </div>
            <a href="{{ route('admin.tenant-invoices.index') }}" class="btn-secondary flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to List
            </a>
        </div>
    </div>

    <!-- System Settings Summary - Shows current tenant invoice configuration -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
        <div class="card p-3" style="background-color: rgba(var(--info-rgb), 0.1);">
            <div class="flex items-center">
                <i class="fas fa-calculator mr-2" style="color: var(--info);"></i>
                <div>
                    <p class="text-xs" style="color: var(--text-secondary);">Tenant Calculation Method</p>
                    <p class="text-sm font-medium" style="color: var(--text-primary);">{{ $calculation_method }}</p>
                    @if($tenant_calculation_method_raw === 'percentage_of_landlord')
                        <p class="text-xs" style="color: var(--info);">({{ $tenant_dues_percentage }}% of landlord dues)</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="card p-3" style="background-color: rgba(var(--success-rgb), 0.1);">
            <div class="flex items-center">
                <i class="fas fa-money-bill-wave mr-2" style="color: var(--success);"></i>
                <div>
                    <p class="text-xs" style="color: var(--text-secondary);">Default Monthly Dues</p>
                    <p class="text-sm font-medium" style="color: var(--text-primary);">{{ $default_monthly_dues }}</p>
                </div>
            </div>
        </div>
        <div class="card p-3" style="background-color: rgba(var(--warning-rgb), 0.1);">
            <div class="flex items-center">
                <i class="fas fa-clock mr-2" style="color: var(--warning);"></i>
                <div>
                    <p class="text-xs" style="color: var(--text-secondary);">Grace Period</p>
                    <p class="text-sm font-medium" style="color: var(--text-primary);">{{ $tenant_grace_period_days }} days</p>
                </div>
            </div>
        </div>
        <div class="card p-3" style="background-color: rgba(var(--primary-rgb), 0.1);">
            <div class="flex items-center">
                <i class="fas fa-calendar-alt mr-2" style="color: var(--primary);"></i>
                <div>
                    <p class="text-xs" style="color: var(--text-secondary);">Default Due Date</p>
                    <p class="text-sm font-medium" style="color: var(--text-primary);">{{ $default_due_date }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Penalty Configuration Summary -->
    @if($tenant_late_payment_percentage > 0 || $tenant_fixed_penalty_amount > 0)
    <div class="card p-3 mb-4" style="background-color: rgba(var(--danger-rgb), 0.05);">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle mr-2" style="color: var(--danger);"></i>
                <span class="text-sm" style="color: var(--text-secondary);">Penalty Configuration:</span>
            </div>
            <div class="flex gap-3">
                @if($tenant_late_payment_percentage > 0)
                <span class="text-xs px-2 py-1 rounded" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                    {{ $tenant_late_payment_percentage }}% of outstanding
                </span>
                @endif
                @if($tenant_fixed_penalty_amount > 0)
                <span class="text-xs px-2 py-1 rounded" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                    Fixed: {{ $system_settings->formatAmount($tenant_fixed_penalty_amount) }}
                </span>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- Error Messages -->
    @if($errors->any())
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Validation Error!</strong>
        <ul class="mt-2 list-disc list-inside">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Warning Message -->
    @if(session('warning'))
    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Warning!</strong>
        <span class="block sm:inline">{{ session('warning') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Create Invoice Form -->
    <div class="card p-6">
        <form method="POST" action="{{ route('admin.tenant-invoices.store') }}" id="invoiceForm">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div class="space-y-6">
                    <!-- Tenant Selection -->
                    <div class="form-group">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            <i class="fas fa-user mr-1"></i> Select Tenant <span class="text-danger">*</span>
                        </label>
                        <div class="relative">
                            <select name="tenant_id" id="tenant_id" class="w-full p-2 border rounded @error('tenant_id') border-danger @enderror" 
                                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" required>
                                <option value="">-- Select Tenant --</option>
                                @foreach($tenants as $tenant)
                                    <option value="{{ $tenant->id }}" {{ old('tenant_id') == $tenant->id ? 'selected' : '' }} 
                                            data-has-unit="{{ $tenant->propertyUnits->isNotEmpty() ? 'true' : 'false' }}">
                                        {{ $tenant->name }} - {{ $tenant->phone }}
                                        @if($tenant->propertyUnits->isNotEmpty())
                                            @php $unit = $tenant->propertyUnits->first(); @endphp
                                            ({{ $unit->property->property_name ?? 'N/A' }} - Unit {{ $unit->unit_number }})
                                        @else
                                            (No Unit Assigned)
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            <button type="button" onclick="showTenantInfo()" class="absolute right-2 top-2 text-info hover:opacity-80" title="View Tenant Info">
                                <i class="fas fa-info-circle"></i>
                            </button>
                        </div>
                        @error('tenant_id')
                            <p class="text-danger text-xs mt-1">{{ $message }}</p>
                        @enderror
                        <p id="tenant_warning" class="text-warning text-xs mt-1 hidden">
                            <i class="fas fa-exclamation-triangle mr-1"></i> This tenant has no property unit assigned. Please assign a unit first.
                        </p>
                    </div>

                    <!-- Property Unit Selection (Dynamic based on tenant) -->
                    <div class="form-group">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            <i class="fas fa-home mr-1"></i> Property Unit <span class="text-danger">*</span>
                        </label>
                        <select name="property_unit_id" id="property_unit_id" class="w-full p-2 border rounded @error('property_unit_id') border-danger @enderror" 
                                style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" required disabled>
                            <option value="">-- First select a tenant --</option>
                        </select>
                        @error('property_unit_id')
                            <p class="text-danger text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Unit Details Display (Auto-filled) -->
                    <div id="unit_details" class="hidden p-4 border rounded" style="background-color: rgba(var(--info-rgb), 0.05); border-color: var(--border-color);">
                        <h4 class="font-medium text-sm mb-3" style="color: var(--text-primary);">
                            <i class="fas fa-info-circle mr-1"></i> Unit Details
                        </h4>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div style="color: var(--text-secondary);">Property:</div>
                            <div id="unit_property" class="font-medium" style="color: var(--text-primary);">-</div>
                            
                            <div style="color: var(--text-secondary);">Unit Number:</div>
                            <div id="unit_number" class="font-medium" style="color: var(--text-primary);">-</div>
                            
                            <div style="color: var(--text-secondary);">Monthly Dues:</div>
                            <div id="unit_dues" class="font-medium" style="color: var(--success);">-</div>
                            
                            <div style="color: var(--text-secondary);">Unit Type:</div>
                            <div id="unit_type" class="font-medium" style="color: var(--text-primary);">-</div>
                            
                            <div style="color: var(--text-secondary);">Calculation Method:</div>
                            <div id="calc_method" class="font-medium" style="color: var(--info);">{{ $calculation_method }}</div>
                        </div>
                        
                        <div class="mt-3 pt-2 border-t" style="border-color: var(--border-color);">
                            <div class="flex items-center">
                                <input type="checkbox" name="apply_penalty_rules" id="apply_penalty_rules" value="1" class="mr-2" {{ old('apply_penalty_rules', true) ? 'checked' : '' }}>
                                <label for="apply_penalty_rules" class="text-sm" style="color: var(--text-secondary);">
                                    Apply penalty rules based on system settings
                                </label>
                            </div>
                            <p class="text-xs text-info mt-1 ml-6">
                                <i class="fas fa-info-circle mr-1"></i> 
                                @if($tenant_late_payment_percentage > 0)
                                    {{ $tenant_late_payment_percentage }}% late payment penalty
                                @endif
                                @if($tenant_fixed_penalty_amount > 0)
                                    {{ $system_settings->formatAmount($tenant_fixed_penalty_amount) }} fixed penalty
                                @endif
                                @if($tenant_late_payment_percentage == 0 && $tenant_fixed_penalty_amount == 0)
                                    No penalties configured
                                @endif
                            </p>
                        </div>
                    </div>

                    <!-- Period / Month -->
                    <div class="form-group">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            <i class="fas fa-calendar-alt mr-1"></i> Invoice Period <span class="text-danger">*</span>
                        </label>
                        <input type="month" name="period" id="period" class="w-full p-2 border rounded @error('period') border-danger @enderror" 
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                               value="{{ old('period', now()->format('Y-m')) }}" required>
                        @error('period')
                            <p class="text-danger text-xs mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-info mt-1">
                            <i class="fas fa-info-circle mr-1"></i> Select the month and year for this invoice
                        </p>
                    </div>

                    <!-- Due Date -->
                    <div class="form-group">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            <i class="fas fa-clock mr-1"></i> Due Date <span class="text-danger">*</span>
                        </label>
                        <input type="date" name="due_date" id="due_date" class="w-full p-2 border rounded @error('due_date') border-danger @enderror" 
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                               value="{{ old('due_date', $default_due_date) }}" required>
                        @error('due_date')
                            <p class="text-danger text-xs mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-info mt-1">
                            <i class="fas fa-info-circle mr-1"></i> Grace period: {{ $tenant_grace_period_days }} days after due date
                        </p>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                    <!-- Community Development Dues -->
                    <div class="form-group">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            <i class="fas fa-building mr-1"></i> Community Development Dues <span class="text-danger">*</span>
                        </label>
                        <input type="number" name="community_dues" id="community_dues" step="0.01" min="0.01" 
                               class="w-full p-2 border rounded @error('community_dues') border-danger @enderror" 
                               style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                               value="{{ old('community_dues', $default_amount) }}" required>
                        @error('community_dues')
                            <p class="text-danger text-xs mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-info mt-1">
                            <i class="fas fa-info-circle mr-1"></i> Base community development fee 
                            @if($tenant_calculation_method_raw === 'fixed')
                                (system default: {{ $default_monthly_dues }})
                            @elseif($tenant_calculation_method_raw === 'percentage_of_landlord')
                                ({{ $tenant_dues_percentage }}% of landlord dues)
                            @elseif($tenant_calculation_method_raw === 'per_property_unit')
                                (per unit configuration)
                            @endif
                        </p>
                    </div>

                    <!-- Total Amount (Calculated) -->
                    <div class="form-group mt-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                            <i class="fas fa-calculator mr-1"></i> Total Amount
                        </label>
                        <div class="p-4 border rounded font-bold text-xl text-center" 
                             style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border-color: var(--border-color);">
                            <span id="total_amount_display">{{ $system_settings->currency_symbol ?? '₵' }}0.00</span>
                        </div>
                        <input type="hidden" name="total_amount" id="total_amount" value="0">
                        <input type="hidden" name="balance" id="balance" value="0">
                        
                        <!-- Calculation Breakdown -->
                        <div id="calculation_breakdown" class="mt-2 text-xs text-right" style="color: var(--text-secondary);">
                            Total: <span id="breakdown_total" class="font-bold" style="color: var(--primary);">0.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Description / Notes -->
            <div class="mt-6">
                <div class="form-group">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-secondary);">
                        <i class="fas fa-sticky-note mr-1"></i> Description / Notes
                    </label>
                    <textarea name="description" rows="3" class="w-full p-2 border rounded @error('description') border-danger @enderror" 
                              style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" 
                              placeholder="Enter any additional notes or description for this invoice">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="text-danger text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Send Notification Option -->
            <div class="mt-6">
                <label class="flex items-center">
                    <input type="checkbox" name="send_notification" value="1" class="mr-2" {{ old('send_notification', $send_tenant_payment_reminders ?? true) ? 'checked' : '' }}>
                    <span class="text-sm" style="color: var(--text-secondary);">
                        <i class="fas fa-bell mr-1"></i> Send notification to tenant about this invoice
                    </span>
                </label>
                <p class="text-xs text-info mt-1 ml-6">
                    <i class="fas fa-info-circle mr-1"></i> Tenant will receive an email with invoice details
                </p>
            </div>

            <!-- Form Actions -->
            <div class="flex justify-end space-x-3 mt-8">
                <a href="{{ route('admin.tenant-invoices.index') }}" class="px-6 py-2 border rounded" 
                   style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2 rounded text-white" style="background-color: var(--primary);" id="submitBtn">
                    <i class="fas fa-save mr-2"></i> Create Invoice
                </button>
            </div>
        </form>
    </div>

    <!-- Recent Invoices for Selected Tenant (Hidden until tenant selected) -->
    <div id="recentInvoicesSection" class="card p-6 hidden">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
            <i class="fas fa-history mr-2"></i> Recent Invoices for this Tenant
        </h3>
        <div id="recentInvoicesContent" class="overflow-x-auto">
            <!-- Content loaded dynamically -->
            <div class="text-center text-gray-500 py-8">
                <i class="fas fa-file-invoice text-4xl mb-3 opacity-50"></i>
                <p>Select a tenant to view recent invoices</p>
            </div>
        </div>
    </div>
</div>

<!-- Tenant Info Modal -->
<div id="tenantInfoModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="card m-4 max-w-2xl w-full">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-user-circle mr-2"></i> Tenant Information
                </h3>
                <button type="button" onclick="closeTenantInfoModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="tenantInfoContent" class="space-y-4">
                <!-- Content loaded dynamically -->
                <div class="text-center text-gray-500 py-4">Loading...</div>
            </div>
            <div class="flex justify-end mt-6">
                <button type="button" onclick="closeTenantInfoModal()" class="px-4 py-2 rounded" style="background-color: var(--primary); color: white;">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initializeTenantSelect();
    initializeAmountCalculations();
    initializeFormValidation();
    initializeDueDateCalculation();
    
    // Auto-hide error messages after 5 seconds
    const errorMessage = document.querySelector('.bg-red-100');
    if (errorMessage) {
        setTimeout(() => {
            errorMessage.style.transition = 'opacity 0.5s';
            errorMessage.style.opacity = '0';
            setTimeout(() => {
                errorMessage.style.display = 'none';
            }, 500);
        }, 5000);
    }
});

// System settings from PHP
const systemSettings = {
    currencySymbol: '{{ $system_settings->currency_symbol ?? "₵" }}',
    decimalPlaces: {{ $system_settings->decimal_places ?? 2 }},
    calculationMethod: '{{ $tenant_calculation_method_raw }}',
    calculationMethodText: '{{ $calculation_method }}',
    tenantDuesPercentage: {{ $tenant_dues_percentage ?? 50 }},
    tenantMonthlyDues: {{ $tenant_monthly_dues_amount ?? 0 }},
    enablePenalties: {{ ($tenant_late_payment_percentage > 0 || $tenant_fixed_penalty_amount > 0) ? 'true' : 'false' }},
    latePaymentPercentage: {{ $tenant_late_payment_percentage ?? 0 }},
    fixedPenaltyAmount: {{ $tenant_fixed_penalty_amount ?? 0 }}
};

// Tenant data storage with pre-calculated tenant dues
const tenantUnits = {};

function initializeTenantSelect() {
    const tenantSelect = document.getElementById('tenant_id');
    
    // Build tenant units data from the backend with CORRECT tenant dues calculation
    @foreach($tenants as $tenant)
        tenantUnits[{{ $tenant->id }}] = [
            @foreach($tenant->propertyUnits as $unit)
                {
                    id: {{ $unit->id }},
                    unit_number: '{{ $unit->unit_number }}',
                    property_name: '{{ addslashes($unit->property->property_name ?? "N/A") }}',
                    // Use the pre-calculated tenant dues from the controller
                    tenant_dues: {{ $tenantDuesData[$tenant->id][$unit->id] ?? $default_amount }},
                    // Keep landlord dues for reference/display in calculation explanation
                    landlord_dues: {{ $unit->monthly_dues ?? $unit->monthly_rent ?? 0 }},
                    unit_type: '{{ $unit->unit_type ?? "Standard" }}',
                    property_id: {{ $unit->property->id ?? 0 }}
                },
            @endforeach
        ];
    @endforeach
    
    tenantSelect.addEventListener('change', function() {
        const tenantId = this.value;
        const selectedOption = this.options[this.selectedIndex];
        const hasUnit = selectedOption?.dataset.hasUnit === 'true';
        
        if (tenantId) {
            updatePropertyUnits(tenantId);
            fetchRecentInvoices(tenantId);
            
            // Show warning if tenant has no units
            const warningEl = document.getElementById('tenant_warning');
            if (!hasUnit) {
                warningEl.classList.remove('hidden');
            } else {
                warningEl.classList.add('hidden');
            }
        } else {
            // Reset unit selection
            resetUnitSelection();
            
            // Hide recent invoices
            document.getElementById('recentInvoicesSection').classList.add('hidden');
            
            // Hide warning
            document.getElementById('tenant_warning').classList.add('hidden');
        }
    });

    // Trigger change if there's an old value
    if (tenantSelect.value) {
        tenantSelect.dispatchEvent(new Event('change'));
        
        // If there's an old property_unit_id, select it
        @if(old('property_unit_id'))
        setTimeout(() => {
            document.getElementById('property_unit_id').value = '{{ old('property_unit_id') }}';
            updateUnitDetails();
        }, 500);
        @endif
    }
}

function resetUnitSelection() {
    const unitSelect = document.getElementById('property_unit_id');
    unitSelect.innerHTML = '<option value="">-- First select a tenant --</option>';
    unitSelect.disabled = true;
    document.getElementById('unit_details').classList.add('hidden');
}

function updatePropertyUnits(tenantId) {
    const unitSelect = document.getElementById('property_unit_id');
    const units = tenantUnits[tenantId] || [];
    
    if (units.length > 0) {
        let options = '<option value="">-- Select Property Unit --</option>';
        units.forEach(unit => {
            // Calculate the display text with tenant dues info
            let displayText = `${unit.property_name} - Unit ${unit.unit_number} (${unit.unit_type})`;
            
            // Add calculation method info to help admin understand the amount
            @if($tenant_calculation_method_raw === 'percentage_of_landlord')
                displayText += ` - Tenant: ${systemSettings.currencySymbol}${unit.tenant_dues.toFixed(systemSettings.decimalPlaces)} (${systemSettings.tenantDuesPercentage}% of landlord: ${systemSettings.currencySymbol}${unit.landlord_dues.toFixed(systemSettings.decimalPlaces)})`;
            @elseif($tenant_calculation_method_raw === 'fixed')
                displayText += ` - Tenant Dues: ${systemSettings.currencySymbol}${unit.tenant_dues.toFixed(systemSettings.decimalPlaces)}`;
            @elseif($tenant_calculation_method_raw === 'per_property_unit')
                displayText += ` - Tenant Dues: ${systemSettings.currencySymbol}${unit.tenant_dues.toFixed(systemSettings.decimalPlaces)} (Per Unit Configuration)`;
            @endif
            
            options += `<option value="${unit.id}" 
                               data-tenant-dues="${unit.tenant_dues}" 
                               data-landlord-dues="${unit.landlord_dues}"
                               data-unit="${unit.unit_number}" 
                               data-property="${unit.property_name}" 
                               data-type="${unit.unit_type}">`;
            options += displayText;
            options += `</option>`;
        });
        unitSelect.innerHTML = options;
        unitSelect.disabled = false;
        
        // Remove existing listener and add new one
        unitSelect.removeEventListener('change', updateUnitDetails);
        unitSelect.addEventListener('change', updateUnitDetails);
        
        // Auto-select first unit if available
        if (units.length === 1) {
            unitSelect.value = units[0].id;
            updateUnitDetails();
        }
    } else {
        unitSelect.innerHTML = '<option value="">-- No Units Available --</option>';
        unitSelect.disabled = true;
        document.getElementById('unit_details').classList.add('hidden');
    }
}

function updateUnitDetails() {
    const unitSelect = document.getElementById('property_unit_id');
    const selectedOption = unitSelect.options[unitSelect.selectedIndex];
    
    if (selectedOption && selectedOption.value) {
        const tenantDues = parseFloat(selectedOption.dataset.tenantDues);
        const landlordDues = parseFloat(selectedOption.dataset.landlordDues);
        const unitNumber = selectedOption.dataset.unit;
        const propertyName = selectedOption.dataset.property;
        const unitType = selectedOption.dataset.type;
        
        // Update details display - show tenant dues, NOT landlord dues
        document.getElementById('unit_property').textContent = propertyName;
        document.getElementById('unit_number').textContent = unitNumber;
        document.getElementById('unit_dues').textContent = systemSettings.currencySymbol + tenantDues.toFixed(systemSettings.decimalPlaces);
        document.getElementById('unit_type').textContent = unitType;
        
        // Add calculation explanation based on the method
        let calcExplanation = '';
        if (systemSettings.calculationMethod === 'percentage_of_landlord') {
            calcExplanation = ` (${systemSettings.tenantDuesPercentage}% of landlord dues: ${systemSettings.currencySymbol}${landlordDues.toFixed(systemSettings.decimalPlaces)})`;
        } else if (systemSettings.calculationMethod === 'fixed') {
            calcExplanation = ` (System default: ${systemSettings.currencySymbol}${systemSettings.tenantMonthlyDues.toFixed(systemSettings.decimalPlaces)})`;
        } else if (systemSettings.calculationMethod === 'per_property_unit') {
            calcExplanation = ` (Based on unit-specific tenant configuration)`;
        }
        
        // Update calculation method display with explanation
        document.getElementById('calc_method').innerHTML = systemSettings.calculationMethodText + '<span class="text-xs text-info ml-1">' + calcExplanation + '</span>';
        
        // Pre-fill community dues with the correct tenant dues amount
        const duesField = document.getElementById('community_dues');
        duesField.value = tenantDues.toFixed(systemSettings.decimalPlaces);
        calculateTotal();
        
        document.getElementById('unit_details').classList.remove('hidden');
    } else {
        document.getElementById('unit_details').classList.add('hidden');
    }
}

function fetchRecentInvoices(tenantId) {
    fetch(`/api/admin/tenants/${tenantId}/recent-invoices`)
        .then(response => response.json())
        .then(data => {
            const section = document.getElementById('recentInvoicesSection');
            const content = document.getElementById('recentInvoicesContent');
            
            if (data.success && data.invoices && data.invoices.length > 0) {
                let html = '<table class="w-full"><thead><tr class="border-b" style="border-color: var(--border-color);">';
                html += '<th class="text-left p-2" style="color: var(--text-secondary);">Invoice #</th>';
                html += '<th class="text-left p-2" style="color: var(--text-secondary);">Period</th>';
                html += '<th class="text-left p-2" style="color: var(--text-secondary);">Unit</th>';
                html += '<th class="text-left p-2" style="color: var(--text-secondary);">Amount</th>';
                html += '<th class="text-left p-2" style="color: var(--text-secondary);">Status</th>';
                html += '<th class="text-left p-2" style="color: var(--text-secondary);">Due Date</th>';
                html += ' </thead><tbody>';
                
                data.invoices.forEach(inv => {
                    const statusColors = {
                        'paid': 'success',
                        'pending': 'warning',
                        'overdue': 'danger',
                        'cancelled': 'secondary'
                    };
                    const statusColor = statusColors[inv.status] || 'secondary';
                    
                    html += `<tr class="border-b hover:bg-opacity-50" style="border-color: var(--border-color);">`;
                    html += `<td class="p-2"><span class="font-medium" style="color: var(--text-primary);">${inv.invoice_number}</span>`;
                    html += `<td class="p-2" style="color: var(--text-primary);">${inv.month_name || inv.period}`;
                    html += `<td class="p-2" style="color: var(--text-primary);">${inv.property_unit || 'N/A'}`;
                    html += `<td class="p-2" style="color: var(--text-primary);">${inv.formatted_amount || formatAmount(inv.total_amount)}`;
                    html += `<td class="p-2"><span class="px-2 py-1 rounded-full text-xs" style="background-color: rgba(var(--${statusColor}-rgb), 0.2); color: var(--${statusColor});">${inv.status}</span>`;
                    html += `<td class="p-2" style="color: var(--text-primary);">${inv.due_date}`;
                    html += ` `;
                });
                
                html += '</tbody> ';
                content.innerHTML = html;
                section.classList.remove('hidden');
            } else {
                content.innerHTML = '<div class="text-center text-gray-500 py-8"><i class="fas fa-file-invoice text-4xl mb-3 opacity-50"></i><p>No recent invoices found for this tenant</p></div>';
                section.classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('Error fetching recent invoices:', error);
            document.getElementById('recentInvoicesContent').innerHTML = '<div class="text-center text-danger py-4">Error loading recent invoices</div>';
        });
}

function initializeAmountCalculations() {
    const communityDues = document.getElementById('community_dues');
    
    if (communityDues) {
        communityDues.addEventListener('input', calculateTotal);
        communityDues.addEventListener('blur', function() {
            if (this.value === '') this.value = 0;
            if (parseFloat(this.value) < 0) this.value = 0;
            calculateTotal();
        });
    }
    
    calculateTotal(); // Initial calculation
}

function calculateTotal() {
    const dues = parseFloat(document.getElementById('community_dues').value) || 0;
    
    const total = dues;
    
    // Format and display
    const formattedTotal = total.toFixed(systemSettings.decimalPlaces);
    
    document.getElementById('total_amount_display').textContent = systemSettings.currencySymbol + formattedTotal;
    document.getElementById('total_amount').value = total.toFixed(systemSettings.decimalPlaces);
    document.getElementById('balance').value = total.toFixed(systemSettings.decimalPlaces); // Initial balance equals total
    
    // Update breakdown
    document.getElementById('breakdown_total').textContent = formattedTotal;
}

function formatAmount(amount) {
    return systemSettings.currencySymbol + parseFloat(amount).toFixed(systemSettings.decimalPlaces);
}

function initializeDueDateCalculation() {
    const periodInput = document.getElementById('period');
    const dueDateInput = document.getElementById('due_date');
    
    if (periodInput && dueDateInput) {
        periodInput.addEventListener('change', function() {
            if (this.value && !dueDateInput.value) {
                // Set due date to 5th of next month by default
                const periodDate = new Date(this.value + '-01');
                periodDate.setMonth(periodDate.getMonth() + 1);
                periodDate.setDate(5);
                
                const year = periodDate.getFullYear();
                const month = String(periodDate.getMonth() + 1).padStart(2, '0');
                const day = String(periodDate.getDate()).padStart(2, '0');
                dueDateInput.value = `${year}-${month}-${day}`;
            }
        });
    }
}

function initializeFormValidation() {
    const form = document.getElementById('invoiceForm');
    
    form.addEventListener('submit', function(e) {
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Creating...';
        
        // Validate tenant selection
        const tenantId = document.getElementById('tenant_id').value;
        if (!tenantId) {
            e.preventDefault();
            alert('Please select a tenant');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Create Invoice';
            return;
        }
        
        // Validate property unit selection
        const propertyUnitId = document.getElementById('property_unit_id').value;
        if (!propertyUnitId) {
            e.preventDefault();
            alert('Please select a property unit');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Create Invoice';
            return;
        }
        
        // Validate community dues
        const dues = parseFloat(document.getElementById('community_dues').value) || 0;
        if (dues <= 0) {
            e.preventDefault();
            alert('Please enter a valid community development dues amount (greater than 0)');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Create Invoice';
            return;
        }
        
        // Validate period
        const period = document.getElementById('period').value;
        if (!period) {
            e.preventDefault();
            alert('Please select an invoice period');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Create Invoice';
            return;
        }
        
        // Validate due date
        const dueDate = document.getElementById('due_date').value;
        if (!dueDate) {
            e.preventDefault();
            alert('Please select a due date');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Create Invoice';
            return;
        }
    });
}

function showTenantInfo() {
    const tenantSelect = document.getElementById('tenant_id');
    const tenantId = tenantSelect.value;
    
    if (!tenantId) {
        alert('Please select a tenant first');
        return;
    }
    
    fetch(`/api/admin/tenants/${tenantId}/details`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const tenant = data.tenant;
                const settings = data.system_settings;
                
                let html = `
                    <div class="p-4 rounded" style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <h4 class="font-medium mb-2" style="color: var(--text-primary);">Personal Information</h4>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div style="color: var(--text-secondary);">Name:</div>
                            <div style="color: var(--text-primary);">${tenant.name}</div>
                            <div style="color: var(--text-secondary);">Email:</div>
                            <div style="color: var(--text-primary);">${tenant.email || 'N/A'}</div>
                            <div style="color: var(--text-secondary);">Phone:</div>
                            <div style="color: var(--text-primary);">${tenant.phone || 'N/A'}</div>
                        </div>
                    </div>
                `;
                
                if (tenant.property_unit) {
                    html += `
                        <div class="p-4 rounded" style="background-color: rgba(var(--success-rgb), 0.1);">
                            <h4 class="font-medium mb-2" style="color: var(--text-primary);">Property Unit Details</h4>
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div style="color: var(--text-secondary);">Property:</div>
                                <div style="color: var(--text-primary);">${tenant.property_unit.property_name}</div>
                                <div style="color: var(--text-secondary);">Unit Number:</div>
                                <div style="color: var(--text-primary);">${tenant.property_unit.unit_number}</div>
                                <div style="color: var(--text-secondary);">Monthly Dues:</div>
                                <div style="color: var(--success);">${tenant.property_unit.formatted_default_dues}</div>
                            </div>
                        </div>
                    `;
                }
                
                html += `
                    <div class="p-4 rounded" style="background-color: rgba(var(--info-rgb), 0.1);">
                        <h4 class="font-medium mb-2" style="color: var(--text-primary);">System Settings</h4>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div style="color: var(--text-secondary);">Invoicing Enabled:</div>
                            <div style="color: var(--text-primary);">${settings.enable_tenant_invoicing ? 'Yes' : 'No'}</div>
                            <div style="color: var(--text-secondary);">Calculation Method:</div>
                            <div style="color: var(--text-primary);">${settings.tenant_calculation_method || 'Fixed'}</div>
                            <div style="color: var(--text-secondary);">Grace Period:</div>
                            <div style="color: var(--text-primary);">${settings.tenant_grace_period_days} days</div>
                        </div>
                    </div>
                `;
                
                document.getElementById('tenantInfoContent').innerHTML = html;
                document.getElementById('tenantInfoModal').classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('Error fetching tenant info:', error);
            alert('Error loading tenant information');
        });
}

function closeTenantInfoModal() {
    document.getElementById('tenantInfoModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('tenantInfoModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeTenantInfoModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('tenantInfoModal');
        if (modal && !modal.classList.contains('hidden')) {
            closeTenantInfoModal();
        }
    }
});
</script>

<style>
/* Form styles */
.form-group {
    margin-bottom: 1rem;
}

input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.2);
}

/* Button styles */
.btn-secondary {
    background-color: var(--secondary);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    border: none;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
    cursor: pointer;
}

.btn-secondary:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

/* Validation styles */
.border-danger {
    border-color: var(--danger) !important;
}

.text-danger {
    color: var(--danger);
}

.text-warning {
    color: var(--warning);
}

.text-info {
    color: var(--info);
}

.text-success {
    color: var(--success);
}

/* Modal styles */
#tenantInfoModal {
    transition: opacity 0.3s ease;
}

#tenantInfoModal.hidden {
    opacity: 0;
    pointer-events: none;
}

#tenantInfoModal:not(.hidden) {
    opacity: 1;
    pointer-events: auto;
}

/* Card styles */
.card {
    transition: all 0.2s ease;
}

/* Dark mode adjustments */
.dark input:disabled,
.dark select:disabled {
    background-color: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.5);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid {
        gap: 1rem;
    }
}

/* Animation for recent invoices section */
#recentInvoicesSection {
    transition: all 0.3s ease;
}

/* Unit details card */
#unit_details {
    transition: all 0.3s ease;
}

#unit_details.hidden {
    display: none;
}

/* Loading spinner */
.fa-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* System settings summary cards */
.grid-cols-4 .card {
    transition: transform 0.2s ease;
}

.grid-cols-4 .card:hover {
    transform: translateY(-2px);
}

/* Calculation breakdown */
#calculation_breakdown {
    font-family: monospace;
}

/* Tooltip styles */
[title] {
    cursor: help;
}

/* Responsive table */
@media (max-width: 768px) {
    .overflow-x-auto {
        margin: 0 -1rem;
    }
    
    table {
        min-width: 600px;
    }
}
</style>
@endsection