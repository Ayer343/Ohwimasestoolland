/**
 * Tenant Manager Module
 * Handles all tenant-related functionality including adding, editing, and removing tenants
 */
window.TenantManager = {
    tenants: [],
    
    init: function() {
        this.setupEventListeners();
        this.updateRadioStyles();
        this.toggleInvitation();
        
        // Set radio button values to match controller expectations
        const isRentedYes = document.getElementById('is_rented_yes');
        const isRentedNo = document.getElementById('is_rented_no');
        if (isRentedYes) isRentedYes.value = 'yes';
        if (isRentedNo) isRentedNo.value = 'no';
        
        // Setup add tenants button
        const addTenantsBtn = document.getElementById('add-tenants-btn');
        if (addTenantsBtn) {
            addTenantsBtn.addEventListener('click', () => this.openModal());
        }
        
        // Setup tenant count change
        const tenantCountSelect = document.getElementById('tenant-count');
        if (tenantCountSelect) {
            tenantCountSelect.addEventListener('change', (e) => {
                const count = parseInt(e.target.value);
                if (count > 0) {
                    this.generateForms(count);
                    if (this.tenants.length > 0) {
                        this.prefillForms();
                    }
                } else {
                    const container = document.getElementById('tenant-forms-container');
                    if (container) container.innerHTML = '';
                }
            });
        }
    },
    
    setupEventListeners: function() {
        const tenantRadios = document.querySelectorAll('input[name="is_rented"]');
        tenantRadios.forEach(radio => {
            radio.addEventListener('change', () => {
                this.toggleInvitation();
                this.updateRadioStyles();
            });
        });
        
        // Add click handlers to the radio option divs for better UX
        document.querySelectorAll('.modern-radio-option').forEach(option => {
            option.addEventListener('click', (e) => {
                // Prevent if clicking on the radio input itself
                if (e.target.type === 'radio') return;
                const radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                    this.toggleInvitation();
                    this.updateRadioStyles();
                }
            });
        });
    },
    
    selectRadio: function(id) {
        const radio = document.getElementById(id);
        if (radio) {
            radio.checked = true;
            this.toggleInvitation();
            this.updateRadioStyles();
        }
    },
    
    updateRadioStyles: function() {
        const radioOptions = document.querySelectorAll('.modern-radio-option');
        radioOptions.forEach(option => {
            const radio = option.querySelector('input[type="radio"]');
            if (radio && radio.checked) {
                option.classList.add('modern-radio-option--checked');
            } else {
                option.classList.remove('modern-radio-option--checked');
            }
        });
    },
    
    toggleInvitation: function() {
        const isRentedYes = document.getElementById('is_rented_yes');
        const tenantContainer = document.getElementById('tenant-invitation-container');
        
        if (isRentedYes && isRentedYes.checked) {
            tenantContainer.classList.remove('hidden');
        } else {
            tenantContainer.classList.add('hidden');
            this.tenants = [];
            this.updateSummary();
            this.clearHiddenInputs();
        }
    },
    
    openModal: function() {
        const modal = document.getElementById('tenant-modal');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Reset forms
        const container = document.getElementById('tenant-forms-container');
        if (container) container.innerHTML = '';
        
        const countSelect = document.getElementById('tenant-count');
        if (countSelect) countSelect.value = '';
        
        // If we have existing tenants, pre-fill the form
        if (this.tenants.length > 0) {
            if (countSelect) countSelect.value = this.tenants.length;
            this.generateForms(this.tenants.length);
            this.prefillForms();
        }
    },
    
    closeModal: function() {
        const modal = document.getElementById('tenant-modal');
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    },
    
    escapeHtml: function(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    generateForms: function(count) {
        const container = document.getElementById('tenant-forms-container');
        if (!container) return;
        
        container.innerHTML = '';
        
        for (let i = 0; i < count; i++) {
            const tenantForm = document.createElement('div');
            tenantForm.className = 'tenant-form border rounded-lg p-4';
            tenantForm.style.backgroundColor = 'var(--bg-secondary)';
            tenantForm.style.borderColor = 'var(--border-color)';
            
            const existingTenant = this.tenants[i];
            
            tenantForm.innerHTML = `
                <div class="flex items-center justify-between mb-4 pb-2 border-b" style="border-color: var(--border-color);">
                    <h4 class="font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-user mr-2"></i>Tenant ${i + 1}
                    </h4>
                    <span class="text-xs px-2 py-1 rounded-full" style="background-color: var(--primary); color: white;">
                        Required
                    </span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Full Name *
                        </label>
                        <input type="text" 
                               class="w-full p-2 border rounded tenant-name" 
                               style="background-color: var(--bg-tertiary); color: var(--text-primary); border-color: var(--border-color);" 
                               placeholder="e.g., Kwame Osei"
                               data-index="${i}"
                               value="${this.escapeHtml(existingTenant?.name || '')}">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Phone Number *
                        </label>
                        <input type="tel" 
                               class="w-full p-2 border rounded tenant-phone" 
                               style="background-color: var(--bg-tertiary); color: var(--text-primary); border-color: var(--border-color);" 
                               placeholder="e.g., 024 123 4567"
                               data-index="${i}"
                               value="${this.escapeHtml(existingTenant?.phone || '')}">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Email Address (Optional)
                        </label>
                        <input type="email" 
                               class="w-full p-2 border rounded tenant-email" 
                               style="background-color: var(--bg-tertiary); color: var(--text-primary); border-color: var(--border-color);" 
                               placeholder="e.g., tenant@example.com"
                               data-index="${i}"
                               value="${this.escapeHtml(existingTenant?.email || '')}">
                    </div>
                </div>
                
                <div class="mt-4">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Notes (Optional)
                    </label>
                    <textarea class="w-full p-2 border rounded tenant-notes" 
                              style="background-color: var(--bg-tertiary); color: var(--text-primary); border-color: var(--border-color);" 
                              rows="2"
                              placeholder="Any additional notes about this tenant..."
                              data-index="${i}">${this.escapeHtml(existingTenant?.notes || '')}</textarea>
                </div>
            `;
            
            container.appendChild(tenantForm);
        }
    },
    
    prefillForms: function() {
        this.tenants.forEach((tenant, index) => {
            const nameInput = document.querySelector(`.tenant-name[data-index="${index}"]`);
            const phoneInput = document.querySelector(`.tenant-phone[data-index="${index}"]`);
            const emailInput = document.querySelector(`.tenant-email[data-index="${index}"]`);
            const notesInput = document.querySelector(`.tenant-notes[data-index="${index}"]`);
            
            if (nameInput) nameInput.value = tenant.name || '';
            if (phoneInput) phoneInput.value = tenant.phone || '';
            if (emailInput) emailInput.value = tenant.email || '';
            if (notesInput) notesInput.value = tenant.notes || '';
        });
    },
    
    save: function() {
        const tenantCount = parseInt(document.getElementById('tenant-count').value);
        
        if (!tenantCount || tenantCount < 1) {
            if (window.Toast) {
                window.Toast.show('Please select the number of tenants', 'error');
            } else {
                alert('Please select the number of tenants');
            }
            return;
        }
        
        const nameInputs = document.querySelectorAll('.tenant-name');
        const phoneInputs = document.querySelectorAll('.tenant-phone');
        const emailInputs = document.querySelectorAll('.tenant-email');
        const noteInputs = document.querySelectorAll('.tenant-notes');
        
        const newTenants = [];
        let hasErrors = false;
        
        for (let i = 0; i < tenantCount; i++) {
            const name = nameInputs[i]?.value.trim();
            const phone = phoneInputs[i]?.value.trim();
            const email = emailInputs[i]?.value.trim();
            const notes = noteInputs[i]?.value.trim();
            
            // Validate required fields
            if (!name || !phone) {
                hasErrors = true;
                if (nameInputs[i]) nameInputs[i].style.borderColor = '#ef4444';
                if (phoneInputs[i]) phoneInputs[i].style.borderColor = '#ef4444';
                continue;
            }
            
            // Validate phone format
            const cleanPhone = phone.replace(/\D/g, '');
            if (cleanPhone.length < 10) {
                hasErrors = true;
                if (phoneInputs[i]) phoneInputs[i].style.borderColor = '#ef4444';
                continue;
            }
            
            // Reset border colors
            if (nameInputs[i]) nameInputs[i].style.borderColor = '';
            if (phoneInputs[i]) phoneInputs[i].style.borderColor = '';
            if (emailInputs[i]) emailInputs[i].style.borderColor = '';
            
            newTenants.push({
                name: name,
                phone: cleanPhone,
                email: email,
                notes: notes
            });
        }
        
        if (hasErrors) {
            if (window.Toast) {
                window.Toast.show('Please fill in all required fields (Name and Phone are required for each tenant)', 'error');
            } else {
                alert('Please fill in all required fields (Name and Phone are required for each tenant)');
            }
            return;
        }
        
        if (newTenants.length === 0) {
            if (window.Toast) {
                window.Toast.show('No valid tenant information provided', 'error');
            } else {
                alert('No valid tenant information provided');
            }
            return;
        }
        
        this.tenants = newTenants;
        this.updateSummary();
        this.updateHiddenInputs();
        this.closeModal();
        
        if (window.Toast) {
            window.Toast.show(`${this.tenants.length} tenant(s) added successfully`, 'success');
        }
    },
    
    updateSummary: function() {
        const summaryContainer = document.getElementById('tenants-summary');
        const tenantsList = document.getElementById('tenants-list');
        const tenantsCount = document.getElementById('tenants-count');
        
        if (!summaryContainer || !tenantsList || !tenantsCount) return;
        
        if (this.tenants.length === 0) {
            summaryContainer.classList.add('hidden');
            return;
        }
        
        tenantsList.innerHTML = '';
        
        this.tenants.forEach((tenant, index) => {
            const tenantItem = document.createElement('div');
            tenantItem.className = 'flex items-center justify-between p-3 border rounded';
            tenantItem.style.backgroundColor = 'var(--bg-secondary)';
            tenantItem.style.borderColor = 'var(--border-color)';
            
            tenantItem.innerHTML = `
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center" style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-user" style="color: var(--primary);"></i>
                    </div>
                    <div>
                        <div class="font-medium" style="color: var(--text-primary);">${this.escapeHtml(tenant.name)}</div>
                        <div class="text-sm" style="color: var(--text-secondary);">${this.escapeHtml(tenant.phone)}</div>
                        ${tenant.email ? `<div class="text-xs" style="color: var(--text-secondary);">${this.escapeHtml(tenant.email)}</div>` : ''}
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <button type="button" onclick="window.TenantManager.removeTenant(${index})" class="text-red-500 hover:text-red-700 p-1" style="color: var(--danger);">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            tenantsList.appendChild(tenantItem);
        });
        
        tenantsCount.textContent = `${this.tenants.length} tenant(s) added`;
        summaryContainer.classList.remove('hidden');
    },
    
    removeTenant: function(index) {
        if (confirm('Are you sure you want to remove this tenant?')) {
            this.tenants.splice(index, 1);
            this.updateSummary();
            this.updateHiddenInputs();
            
            if (this.tenants.length === 0) {
                const summaryContainer = document.getElementById('tenants-summary');
                if (summaryContainer) {
                    summaryContainer.classList.add('hidden');
                }
            }
        }
    },
    
    updateHiddenInputs: function() {
        const container = document.getElementById('tenant-hidden-inputs');
        if (!container) return;
        
        container.innerHTML = '';
        
        if (this.tenants.length > 0) {
            // Add has_tenants = 'yes'
            const flagInput = document.createElement('input');
            flagInput.type = 'hidden';
            flagInput.name = 'has_tenants';
            flagInput.value = 'yes';
            container.appendChild(flagInput);
            
            // Add tenant count
            const countInput = document.createElement('input');
            countInput.type = 'hidden';
            countInput.name = 'tenant_count';
            countInput.value = this.tenants.length;
            container.appendChild(countInput);
            
            // Add tenant data as JSON
            const tenantsJsonInput = document.createElement('input');
            tenantsJsonInput.type = 'hidden';
            tenantsJsonInput.name = 'tenant_data_json';
            tenantsJsonInput.value = JSON.stringify(this.tenants);
            container.appendChild(tenantsJsonInput);
            
            // Add individual tenant fields for backward compatibility
            this.tenants.forEach((tenant, index) => {
                const nameInput = document.createElement('input');
                nameInput.type = 'hidden';
                nameInput.name = `tenant_name_${index}`;
                nameInput.value = tenant.name;
                container.appendChild(nameInput);
                
                const phoneInput = document.createElement('input');
                phoneInput.type = 'hidden';
                phoneInput.name = `tenant_phone_${index}`;
                phoneInput.value = tenant.phone;
                container.appendChild(phoneInput);
                
                if (tenant.email) {
                    const emailInput = document.createElement('input');
                    emailInput.type = 'hidden';
                    emailInput.name = `tenant_email_${index}`;
                    emailInput.value = tenant.email;
                    container.appendChild(emailInput);
                }
                
                if (tenant.notes) {
                    const notesInput = document.createElement('input');
                    notesInput.type = 'hidden';
                    notesInput.name = `tenant_notes_${index}`;
                    notesInput.value = tenant.notes;
                    container.appendChild(notesInput);
                }
            });
        } else {
            const flagInput = document.createElement('input');
            flagInput.type = 'hidden';
            flagInput.name = 'has_tenants';
            flagInput.value = 'no';
            container.appendChild(flagInput);
        }
    },
    
    clearHiddenInputs: function() {
        const container = document.getElementById('tenant-hidden-inputs');
        if (container) {
            container.innerHTML = '';
            const flagInput = document.createElement('input');
            flagInput.type = 'hidden';
            flagInput.name = 'has_tenants';
            flagInput.value = 'no';
            container.appendChild(flagInput);
        }
    }
};