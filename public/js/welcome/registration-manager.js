/**
 * Registration Manager Module
 * Handles registration type toggling (Vacant Land vs Existing Property)
 */
window.RegistrationManager = {
    init: function() {
        this.setupEventListeners();
        this.setupConstructionToggle();
        this.setupPropertyTypeHandlers();
        this.setupFileUploads();
        this.updateRequiredAttributes();
    },
    
    setupEventListeners: function() {
        const vacantLandOption = document.getElementById('vacantLandOption');
        const existingPropertyOption = document.getElementById('existingPropertyOption');
        
        if (vacantLandOption) {
            vacantLandOption.addEventListener('click', () => this.setActiveOption('vacant'));
        }
        
        if (existingPropertyOption) {
            existingPropertyOption.addEventListener('click', () => this.setActiveOption('existing'));
        }
        
        // Setup registration type observer
        this.setupRegistrationTypeObserver();
    },
    
    setupRegistrationTypeObserver: function() {
        const registrationTypeInput = document.getElementById('registrationType');
        if (registrationTypeInput) {
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                        console.log('registration_type changed to:', registrationTypeInput.value);
                    }
                });
            });
            observer.observe(registrationTypeInput, { attributes: true });
        }
    },
    
    setActiveOption: function(type) {
        const vacantLandOption = document.getElementById('vacantLandOption');
        const existingPropertyOption = document.getElementById('existingPropertyOption');
        const registrationType = document.getElementById('registrationType');
        const purposeInput = document.getElementById('purpose');
        const constructionToggleSection = document.getElementById('constructionToggleSection');
        const existingPropertySection = document.getElementById('existingPropertySection');
        const tenantSection = document.getElementById('tenantSection');
        const includeConstruction = document.getElementById('includeConstruction');
        const constructionDetails = document.getElementById('constructionDetails');
        const includeConstructionHidden = document.getElementById('includeConstructionHidden');
        
        // Remove active class from both
        if (vacantLandOption) vacantLandOption.classList.remove('active');
        if (existingPropertyOption) existingPropertyOption.classList.remove('active');
        
        if (type === 'vacant') {
            if (vacantLandOption) vacantLandOption.classList.add('active');
            if (registrationType) registrationType.value = 'construction';
            
            // Set purpose based on construction toggle
            if (includeConstruction && includeConstruction.checked) {
                const registerPermanently = document.getElementById('registerPermanently');
                if (registerPermanently && registerPermanently.checked) {
                    if (purposeInput) purposeInput.value = 'both';
                } else {
                    if (purposeInput) purposeInput.value = 'construction';
                }
            } else {
                if (purposeInput) purposeInput.value = 'construction';
            }
            
            // Show construction toggle section, hide existing property section
            if (constructionToggleSection) constructionToggleSection.style.display = 'block';
            if (existingPropertySection) existingPropertySection.style.display = 'none';
            if (tenantSection) tenantSection.classList.remove('visible');
            
            // Disable all existing property fields
            this.disableExistingPropertyFields(true);
            this.disableConstructionFields(true);
            
            // Reset construction toggle if needed
            if (includeConstruction && !includeConstruction.checked) {
                if (constructionDetails) constructionDetails.classList.add('hidden');
                if (includeConstructionHidden) includeConstructionHidden.value = '0';
            } else if (includeConstruction && includeConstruction.checked) {
                if (includeConstructionHidden) includeConstructionHidden.value = '1';
            }
            
        } else {
            if (existingPropertyOption) existingPropertyOption.classList.add('active');
            if (registrationType) registrationType.value = 'property_capture';
            if (purposeInput) purposeInput.value = 'permanent_registration';
            
            // Hide construction toggle section, show existing property section
            if (constructionToggleSection) constructionToggleSection.style.display = 'none';
            if (existingPropertySection) existingPropertySection.style.display = 'block';
            if (tenantSection) tenantSection.classList.add('visible');
            
            // Disable all construction fields
            this.disableConstructionFields(true);
            this.enableExistingPropertyFields(true);
            
            // Reset construction toggle
            if (includeConstruction) {
                includeConstruction.checked = false;
                if (includeConstructionHidden) includeConstructionHidden.value = '0';
            }
            if (constructionDetails) {
                constructionDetails.classList.add('hidden');
            }
            
            // Set default status to active
            const existingPropertyStatus = document.getElementById('existing_property_status');
            if (existingPropertyStatus) existingPropertyStatus.value = 'active';
        }
        
        this.updateRequiredAttributes();
    },
    
    setupConstructionToggle: function() {
        const includeConstruction = document.getElementById('includeConstruction');
        if (!includeConstruction) return;
        
        includeConstruction.addEventListener('change', function() {
            const constructionDetails = document.getElementById('constructionDetails');
            const purposeInput = document.getElementById('purpose');
            const registerPermanently = document.getElementById('registerPermanently');
            const registrationType = document.getElementById('registrationType')?.value;
            const includeConstructionHidden = document.getElementById('includeConstructionHidden');
            
            if (includeConstructionHidden) {
                includeConstructionHidden.value = this.checked ? '1' : '0';
            }
            
            if (this.checked) {
                if (constructionDetails) constructionDetails.classList.remove('hidden');
                window.RegistrationManager.enableConstructionFields(true);
                
                if (registrationType === 'construction') {
                    if (registerPermanently && registerPermanently.checked) {
                        purposeInput.value = 'both';
                    } else {
                        purposeInput.value = 'construction';
                    }
                }
            } else {
                if (constructionDetails) constructionDetails.classList.add('hidden');
                window.RegistrationManager.disableConstructionFields(true);
                
                if (registrationType === 'construction') {
                    purposeInput.value = 'construction';
                }
            }
            
            window.RegistrationManager.updateRequiredAttributes();
        });
        
        // Handle permanent registration checkbox
        const registerPermanently = document.getElementById('registerPermanently');
        if (registerPermanently) {
            registerPermanently.addEventListener('change', function() {
                const purposeInput = document.getElementById('purpose');
                const registrationType = document.getElementById('registrationType')?.value;
                const includeConstruction = document.getElementById('includeConstruction')?.checked;
                
                if (registrationType === 'construction' && includeConstruction) {
                    purposeInput.value = this.checked ? 'both' : 'construction';
                }
            });
        }
    },
    
    setupPropertyTypeHandlers: function() {
        // Construction property type
        const propertyType = document.getElementById('property_type');
        const customTypeGroup = document.getElementById('customTypeGroup');
        
        if (propertyType) {
            propertyType.addEventListener('change', function() {
                if (this.value === 'other') {
                    if (customTypeGroup) customTypeGroup.style.display = 'block';
                    const customPropertyType = document.getElementById('custom_property_type');
                    if (customPropertyType) customPropertyType.setAttribute('required', 'required');
                } else {
                    if (customTypeGroup) customTypeGroup.style.display = 'none';
                    const customPropertyType = document.getElementById('custom_property_type');
                    if (customPropertyType) {
                        customPropertyType.removeAttribute('required');
                        customPropertyType.value = '';
                    }
                }
            });
        }
        
        // Existing property type
        const existingPropertyType = document.getElementById('existing_property_type');
        const existingCustomTypeGroup = document.getElementById('existingCustomTypeGroup');
        
        if (existingPropertyType) {
            existingPropertyType.addEventListener('change', function() {
                if (this.value === 'other') {
                    if (existingCustomTypeGroup) existingCustomTypeGroup.style.display = 'block';
                    const existingCustomPropertyType = document.getElementById('existing_custom_property_type');
                    if (existingCustomPropertyType) existingCustomPropertyType.setAttribute('required', 'required');
                } else {
                    if (existingCustomTypeGroup) existingCustomTypeGroup.style.display = 'none';
                    const existingCustomPropertyType = document.getElementById('existing_custom_property_type');
                    if (existingCustomPropertyType) {
                        existingCustomPropertyType.removeAttribute('required');
                        existingCustomPropertyType.value = '';
                    }
                }
            });
        }
    },
    
    disableConstructionFields: function(disable) {
        const constructionFields = [
            'property_type',
            'custom_property_type',
            'property_status_construction',
            'estimated_bedrooms',
            'estimated_completion',
            'construction_documents'
        ];
        
        constructionFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.disabled = disable;
                if (disable && field.type !== 'file') field.value = '';
            }
        });
        
        // Handle radio buttons
        document.querySelectorAll('input[name="has_plans"]').forEach(radio => {
            radio.disabled = disable;
            if (disable) {
                radio.checked = false;
                const noRadio = document.querySelector('input[name="has_plans"][value="no"]');
                if (noRadio) noRadio.checked = true;
            }
        });
        
        const customTypeGroup = document.getElementById('customTypeGroup');
        if (customTypeGroup && disable) {
            customTypeGroup.style.display = 'none';
        }
    },
    
    enableConstructionFields: function(enable) {
        const constructionFields = [
            'property_type',
            'custom_property_type',
            'property_status_construction',
            'estimated_bedrooms',
            'estimated_completion'
        ];
        
        constructionFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.disabled = !enable;
            }
        });
        
        const constructionDocs = document.getElementById('construction_documents');
        if (constructionDocs) {
            constructionDocs.disabled = !enable;
        }
        
        document.querySelectorAll('input[name="has_plans"]').forEach(radio => {
            radio.disabled = !enable;
        });
    },
    
    disableExistingPropertyFields: function(disable) {
        const existingFields = [
            'existing_property_type',
            'existing_custom_property_type',
            'existing_property_status',
            'year_built',
            'existing_bedrooms',
            'existing_bathrooms',
            'property_photos',
            'property_documents'
        ];
        
        existingFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.disabled = disable;
                if (disable && field.type !== 'file') field.value = '';
            }
        });
        
        document.querySelectorAll('input[name="is_rented"]').forEach(radio => {
            radio.disabled = disable;
            if (disable) {
                radio.checked = false;
                const noRadio = document.getElementById('is_rented_no');
                if (noRadio) noRadio.checked = true;
            }
        });
        
        const addTenantsBtn = document.getElementById('add-tenants-btn');
        if (addTenantsBtn) {
            addTenantsBtn.disabled = disable;
        }
        
        if (disable) {
            if (window.TenantManager) {
                window.TenantManager.tenants = [];
                window.TenantManager.updateSummary();
                window.TenantManager.clearHiddenInputs();
            }
        }
        
        const existingCustomTypeGroup = document.getElementById('existingCustomTypeGroup');
        if (existingCustomTypeGroup && disable) {
            existingCustomTypeGroup.style.display = 'none';
        }
    },
    
    enableExistingPropertyFields: function(enable) {
        const existingFields = [
            'existing_property_type',
            'existing_custom_property_type',
            'existing_property_status',
            'year_built',
            'existing_bedrooms',
            'existing_bathrooms'
        ];
        
        existingFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.disabled = !enable;
            }
        });
        
        const propertyPhotos = document.getElementById('property_photos');
        if (propertyPhotos) propertyPhotos.disabled = !enable;
        
        const propertyDocs = document.getElementById('property_documents');
        if (propertyDocs) propertyDocs.disabled = !enable;
        
        document.querySelectorAll('input[name="is_rented"]').forEach(radio => {
            radio.disabled = !enable;
        });
        
        const addTenantsBtn = document.getElementById('add-tenants-btn');
        if (addTenantsBtn) addTenantsBtn.disabled = !enable;
    },
    
    updateRequiredAttributes: function() {
        const registrationType = document.getElementById('registrationType')?.value;
        const includeConstruction = document.getElementById('includeConstruction');
        const constructionDetails = document.getElementById('constructionDetails');
        
        // First, remove required from all conditional fields
        const constructionFields = [
            document.getElementById('property_type'),
            document.getElementById('custom_property_type'),
            document.getElementById('property_status_construction'),
            document.getElementById('estimated_bedrooms'),
            document.getElementById('estimated_completion')
        ];
        
        const existingPropertyFields = [
            document.getElementById('existing_property_type'),
            document.getElementById('existing_custom_property_type'),
            document.getElementById('existing_property_status'),
            document.getElementById('year_built'),
            document.getElementById('existing_bedrooms'),
            document.getElementById('existing_bathrooms')
        ];
        
        // Remove required from all fields first
        [...constructionFields, ...existingPropertyFields].forEach(field => {
            if (field) field.removeAttribute('required');
        });
        
        // Set required based on registration type and visibility
        if (registrationType === 'construction') {
            if (includeConstruction && includeConstruction.checked && constructionDetails && !constructionDetails.classList.contains('hidden')) {
                const propertyType = document.getElementById('property_type');
                const propertyStatus = document.getElementById('property_status_construction');
                
                if (propertyType) propertyType.setAttribute('required', 'required');
                if (propertyStatus) propertyStatus.setAttribute('required', 'required');
                
                if (propertyType && propertyType.value === 'other') {
                    const customType = document.getElementById('custom_property_type');
                    if (customType) customType.setAttribute('required', 'required');
                }
            }
        } else if (registrationType === 'property_capture') {
            const existingPropertyType = document.getElementById('existing_property_type');
            const existingPropertyStatus = document.getElementById('existing_property_status');
            
            if (existingPropertyType) existingPropertyType.setAttribute('required', 'required');
            if (existingPropertyStatus) existingPropertyStatus.setAttribute('required', 'required');
            
            if (existingPropertyType && existingPropertyType.value === 'other') {
                const customType = document.getElementById('existing_custom_property_type');
                if (customType) customType.setAttribute('required', 'required');
            }
        }
    },
    
    setupFileUploads: function() {
        this.setupFileUpload('land_ownership_document', 'ownershipFileList', 'ownershipDocArea');
        this.setupFileUpload('construction_documents', 'constructionDocsFileList', 'constructionDocsArea');
        this.setupFileUpload('property_photos', 'propertyPhotosFileList', 'propertyPhotosArea');
        this.setupFileUpload('property_documents', 'propertyDocsFileList', 'propertyDocsArea');
    },
    
    setupFileUpload: function(inputId, listId, areaId) {
        const input = document.getElementById(inputId);
        const list = document.getElementById(listId);
        const area = document.getElementById(areaId);
        
        if (!input || !list) return;
        
        if (area) {
            area.addEventListener('click', () => input.click());
            
            area.addEventListener('dragover', (e) => {
                e.preventDefault();
                area.style.borderColor = 'var(--primary)';
                area.style.background = 'rgba(var(--primary-rgb), 0.02)';
            });
            
            area.addEventListener('dragleave', () => {
                area.style.borderColor = 'var(--border-color)';
                area.style.background = '';
            });
            
            area.addEventListener('drop', (e) => {
                e.preventDefault();
                area.style.borderColor = 'var(--border-color)';
                area.style.background = '';
                
                if (e.dataTransfer.files.length > 0) {
                    if (input.multiple) {
                        const dataTransfer = new DataTransfer();
                        if (input.files) {
                            for (let i = 0; i < input.files.length; i++) {
                                dataTransfer.items.add(input.files[i]);
                            }
                        }
                        for (let i = 0; i < e.dataTransfer.files.length; i++) {
                            dataTransfer.items.add(e.dataTransfer.files[i]);
                        }
                        input.files = dataTransfer.files;
                    } else {
                        input.files = e.dataTransfer.files;
                    }
                    input.dispatchEvent(new Event('change'));
                }
            });
            
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                area.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                }, false);
            });
        }
        
        input.addEventListener('change', () => {
            this.updateFileList(input, list, input.multiple);
        });
    },
    
    updateFileList: function(input, list, multiple) {
        if (!list) return;
        list.innerHTML = '';
        
        if (input.files.length === 0) return;
        
        if (multiple) {
            for (let i = 0; i < input.files.length; i++) {
                this.addFileToList(input.files[i], list, i, input);
            }
        } else {
            this.addFileToList(input.files[0], list, 0, input);
        }
    },
    
    addFileToList: function(file, list, index, input) {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        fileItem.innerHTML = `
            <i class="fas fa-file"></i>
            <span class="file-name">${file.name} (${(file.size / 1024).toFixed(2)} KB)</span>
            <i class="fas fa-times file-remove" data-index="${index}"></i>
        `;
        
        list.appendChild(fileItem);
        
        fileItem.querySelector('.file-remove').addEventListener('click', (e) => {
            e.stopPropagation();
            
            if (input.multiple) {
                const dataTransfer = new DataTransfer();
                for (let i = 0; i < input.files.length; i++) {
                    if (i !== index) {
                        dataTransfer.items.add(input.files[i]);
                    }
                }
                input.files = dataTransfer.files;
            } else {
                input.value = '';
            }
            
            input.dispatchEvent(new Event('change'));
        });
    }
};