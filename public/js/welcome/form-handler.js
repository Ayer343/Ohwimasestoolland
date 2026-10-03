/**
 * Form Handler Module
 * Handles form submission, validation, and AJAX requests
 */
window.FormHandler = {
    init: function() {
        this.setupFormSubmission();
        this.setupPhoneHandlers();
        this.checkRegistrationStatus();
    },
    
    setupFormSubmission: function() {
        const form = document.getElementById('landlordRegistrationForm');
        const submitBtn = document.getElementById('submitBtn');
        
        if (!form) return;
        
        form.addEventListener('submit', (e) => this.handleSubmit(e));
        
        if (submitBtn) {
            submitBtn.addEventListener('click', () => {
                console.log('Submit button clicked');
            });
        }
    },
    
    handleSubmit: function(e) {
        console.log('Form submission triggered');
        
        const form = e.target;
        const submitBtn = document.getElementById('submitBtn');
        
        // Get registration type and ensure correct values
        const registrationType = document.getElementById('registrationType')?.value;
        const purposeInput = document.getElementById('purpose');
        const includeConstruction = document.getElementById('includeConstruction')?.checked;
        const registerPermanently = document.getElementById('registerPermanently')?.checked;
        const includeConstructionHidden = document.getElementById('includeConstructionHidden');
        
        // Ensure registration_type and purpose are set correctly based on active option
        if (document.getElementById('existingPropertyOption').classList.contains('active')) {
            if (registrationType !== 'property_capture') {
                document.getElementById('registrationType').value = 'property_capture';
            }
            if (purposeInput) purposeInput.value = 'permanent_registration';
        } else if (document.getElementById('vacantLandOption').classList.contains('active')) {
            if (registrationType !== 'construction') {
                document.getElementById('registrationType').value = 'construction';
            }
            
            if (includeConstruction && registerPermanently) {
                if (purposeInput) purposeInput.value = 'both';
            } else {
                if (purposeInput) purposeInput.value = 'construction';
            }
        }
        
        // Update include_construction hidden field
        if (includeConstructionHidden) {
            includeConstructionHidden.value = includeConstruction ? '1' : '0';
        }
        
        // Validate required fields
        const name = document.getElementById('name')?.value.trim();
        const phone = document.getElementById('primary_phone')?.value.trim();
        const propertyName = document.getElementById('property_name')?.value.trim();
        const plotNumber = document.getElementById('plot_number')?.value.trim();
        const streetName = document.getElementById('street_name')?.value.trim();
        const declaration = document.querySelector('input[name="declaration"]')?.checked;
        
        const missingFields = [];
        if (!name) missingFields.push('Full Name');
        if (!phone) missingFields.push('Primary Phone');
        if (!propertyName) missingFields.push('Property Name');
        if (!plotNumber) missingFields.push('Plot Number');
        if (!streetName) missingFields.push('Street Name');
        if (!declaration) missingFields.push('Declaration');
        
        if (missingFields.length > 0) {
            e.preventDefault();
            if (window.Toast) {
                window.Toast.show('Please fill in all required fields: ' + missingFields.join(', '), 'error');
            }
            return false;
        }
        
        // Validate phone number format
        const phoneRegex = /^(\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}$/;
        const cleanPhone = phone.replace(/[\s\-\(\)]/g, '');
        const ghanaPhoneRegex = /^(\+?233|0)\d{9}$/;
        
        if (!phoneRegex.test(phone) && !ghanaPhoneRegex.test(cleanPhone)) {
            e.preventDefault();
            if (window.Toast) {
                window.Toast.show('Please enter a valid phone number (e.g., 0595652410 or +233595652410)', 'error');
            }
            return false;
        }
        
        // Check if this is existing property with tenants
        if (document.getElementById('existingPropertyOption').classList.contains('active')) {
            const isRentedYes = document.getElementById('is_rented_yes');
            const isRented = isRentedYes ? isRentedYes.checked : false;
            
            if (isRented && window.TenantManager && window.TenantManager.tenants.length === 0) {
                e.preventDefault();
                if (window.Toast) {
                    window.Toast.show('Please add at least one tenant for this rented property.', 'error');
                }
                return false;
            }
        }
        
        // Show loading state
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner"></span> Submitting...';
        }
        
        console.log('Form validation passed, submitting...');
        return true;
    },
    
    setupPhoneHandlers: function() {
        const addPhoneBtn = document.getElementById('addPhoneBtn');
        const additionalPhones = document.getElementById('additionalPhones');
        
        if (addPhoneBtn && additionalPhones) {
            addPhoneBtn.addEventListener('click', () => {
                const phoneItem = document.createElement('div');
                phoneItem.className = 'phone-item';
                phoneItem.innerHTML = `
                    <input type="tel" name="additional_phones[]" 
                           placeholder="e.g., 0595652410 or +233595652410"
                           pattern="^(\\+?\\d{1,3}[-.\\s]?)?\\(?\\d{3}\\)?[-.\\s]?\\d{3}[-.\\s]?\\d{4}$"
                           title="Enter a valid phone number (with or without country code)">
                    <button type="button" class="btn-remove-phone" onclick="this.closest('.phone-item').remove()">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                additionalPhones.appendChild(phoneItem);
            });
        }
    },
    
    checkRegistrationStatus: async function() {
        try {
            const response = await fetch('/api/registration/status');
            const data = await response.json();
            
            if (data.success && !data.registration_allowed) {
                const registrationBtn = document.getElementById('landlordRegistrationBtn');
                if (registrationBtn) {
                    const parent = registrationBtn.parentNode;
                    const disabledBtn = document.createElement('button');
                    disabledBtn.className = 'btn btn-secondary';
                    disabledBtn.disabled = true;
                    disabledBtn.style.opacity = '0.7';
                    disabledBtn.style.cursor = 'not-allowed';
                    disabledBtn.title = data.message;
                    disabledBtn.innerHTML = '<i class="fas fa-ban"></i> Registration Currently Disabled';
                    parent.replaceChild(disabledBtn, registrationBtn);
                }
            }
        } catch (error) {
            console.error('Failed to check registration status:', error);
        }
    }
};