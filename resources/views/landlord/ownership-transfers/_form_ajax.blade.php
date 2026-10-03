{{-- 
    AJAX Form Partial for Ownership Transfer
    This is loaded dynamically into the modal
--}}

<div id="formErrors" class="mb-4 hidden"></div>

{{-- Scroll Progress Indicator --}}
<div id="scrollProgress" class="fixed top-0 left-0 w-full h-1 z-50 hidden" style="background-color: rgba(0,0,0,0.05);">
    <div id="scrollProgressBar" class="h-full transition-all duration-300" style="width: 0%; background: linear-gradient(90deg, var(--primary), var(--secondary));"></div>
</div>

{{-- Scroll Navigation Buttons --}}
<div id="scrollNavButtons" class="fixed right-4 z-50 flex flex-col gap-2 hidden" style="top: 50%; transform: translateY(-50%);">
    <button id="scrollToTopBtn" 
            class="w-10 h-10 rounded-full shadow-lg flex items-center justify-center transition-all duration-200 hover:scale-110"
            style="background-color: var(--card-bg); color: var(--text-primary); border: 1px solid var(--border-color);"
            title="Scroll to Top (Ctrl+Shift+↑)">
        <i class="fas fa-chevron-up text-sm"></i>
    </button>
    <button id="scrollToBottomBtn" 
            class="w-10 h-10 rounded-full shadow-lg flex items-center justify-center transition-all duration-200 hover:scale-110"
            style="background-color: var(--card-bg); color: var(--text-primary); border: 1px solid var(--border-color);"
            title="Scroll to Bottom (Ctrl+Shift+↓)">
        <i class="fas fa-chevron-down text-sm"></i>
    </button>
    <button id="scrollToSubmitBtn" 
            class="w-10 h-10 rounded-full shadow-lg flex items-center justify-center transition-all duration-200 hover:scale-110"
            style="background-color: var(--primary); color: white; border: none;"
            title="Scroll to Submit (Ctrl+Shift+End)">
        <i class="fas fa-check text-sm"></i>
    </button>
</div>

<form id="transferForm" 
      action="{{ route('properties.ownership-transfers.store', $property) }}" 
      method="POST" 
      enctype="multipart/form-data"
      class="space-y-4">

    @csrf
    <input type="hidden" name="property_id" value="{{ $property->id }}">
    <input type="hidden" name="property_ids[]" value="{{ $property->id }}">

    {{-- Form Section: Basic Information --}}
    <div class="form-section" data-section="basic">
        <div class="flex items-center gap-2 mb-3 pb-2 border-b" style="border-color: var(--border-color);">
            <div class="w-1 h-5 rounded-full" style="background: var(--primary);"></div>
            <h4 class="text-sm font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-info-circle mr-1" style="color: var(--primary);"></i>
                Basic Information
            </h4>
            <span class="ml-auto text-xs px-2 py-0.5 rounded-full" style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                Step 1 of 4
            </span>
        </div>

        {{-- Transfer Date --}}
        <div>
            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                Transfer Date *
            </label>
            <input type="date" name="transfer_date" 
                   value="{{ date('Y-m-d') }}"
                   min="{{ date('Y-m-d') }}"
                   max="{{ date('Y-m-d', strtotime('+1 year')) }}"
                   class="w-full px-4 py-2 rounded-lg transition-colors duration-200 focus:ring-2 focus:ring-primary focus:ring-opacity-50"
                   style="background-color: var(--bg-secondary); 
                          color: var(--text-primary);
                          border: 1px solid var(--border-color);
                          outline: none;"
                   required>
        </div>

        {{-- Owner Type Selection --}}
        <div>
            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                New Owner Type *
            </label>
            <div class="flex space-x-4">
                <label class="flex items-center cursor-pointer hover:opacity-80 transition-opacity">
                    <input type="radio" name="owner_type" value="existing" checked
                           class="mr-2 transfer-owner-type transition-colors duration-200"
                           style="accent-color: var(--primary);">
                    <span style="color: var(--text-secondary);">Existing Landlord</span>
                </label>
                <label class="flex items-center cursor-pointer hover:opacity-80 transition-opacity">
                    <input type="radio" name="owner_type" value="new"
                           class="mr-2 transfer-owner-type transition-colors duration-200"
                           style="accent-color: var(--primary);">
                    <span style="color: var(--text-secondary);">New Owner</span>
                </label>
            </div>
        </div>
    </div>

    {{-- Form Section: Owner Details --}}
    <div class="form-section" data-section="owner">
        <div class="flex items-center gap-2 mb-3 pb-2 border-b" style="border-color: var(--border-color);">
            <div class="w-1 h-5 rounded-full" style="background: var(--success);"></div>
            <h4 class="text-sm font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-user mr-1" style="color: var(--success);"></i>
                Owner Details
            </h4>
            <span class="ml-auto text-xs px-2 py-0.5 rounded-full" style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                Step 2 of 4
            </span>
        </div>

        {{-- Existing Landlord Selection --}}
        <div id="existingLandlordGroup">
            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                Select Existing Landlord *
            </label>
            <select name="existing_landlord_id" 
                    class="w-full px-4 py-2 rounded-lg transition-colors duration-200 focus:ring-2 focus:ring-primary focus:ring-opacity-50 appearance-none"
                    style="background-color: var(--bg-secondary); 
                           color: var(--text-primary);
                           border: 1px solid var(--border-color);
                           outline: none;
                           background-image: url(\"data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e\");
                           background-position: right 0.5rem center;
                           background-repeat: no-repeat;
                           background-size: 1.5em 1.5em;
                           padding-right: 2.5rem;">
                <option value="">-- Select Existing Landlord --</option>
                @foreach($existingLandlords as $landlord)
                    <option value="{{ $landlord->id }}">
                        {{ $landlord->name }} - {{ $landlord->email }}
                    </option>
                @endforeach
            </select>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                <i class="fas fa-info-circle mr-1"></i>
                Select from existing landlords in the system
            </p>
        </div>

        {{-- New Owner Details --}}
        <div id="newOwnerGroup" style="display: none;">
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                        New Owner Name *
                    </label>
                    <input type="text" name="new_owner_name" 
                           placeholder="Enter full name"
                           class="w-full px-4 py-2 rounded-lg transition-colors duration-200 focus:ring-2 focus:ring-primary focus:ring-opacity-50"
                           style="background-color: var(--bg-secondary); 
                                  color: var(--text-primary);
                                  border: 1px solid var(--border-color);
                                  outline: none;">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                        Phone Number *
                    </label>
                    <input type="tel" name="new_owner_phone" 
                           placeholder="Enter phone number"
                           class="w-full px-4 py-2 rounded-lg transition-colors duration-200 focus:ring-2 focus:ring-primary focus:ring-opacity-50"
                           style="background-color: var(--bg-secondary); 
                                  color: var(--text-primary);
                                  border: 1px solid var(--border-color);
                                  outline: none;">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                        Email Address
                    </label>
                    <input type="email" name="new_owner_email" 
                           placeholder="Enter email address"
                           class="w-full px-4 py-2 rounded-lg transition-colors duration-200 focus:ring-2 focus:ring-primary focus:ring-opacity-50"
                           style="background-color: var(--bg-secondary); 
                                  color: var(--text-primary);
                                  border: 1px solid var(--border-color);
                                  outline: none;">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                        Address
                    </label>
                    <textarea name="new_owner_address" rows="2"
                              placeholder="Enter full address"
                              class="w-full px-4 py-2 rounded-lg transition-colors duration-200 focus:ring-2 focus:ring-primary focus:ring-opacity-50"
                              style="background-color: var(--bg-secondary); 
                                     color: var(--text-primary);
                                     border: 1px solid var(--border-color);
                                     outline: none;
                                     resize: vertical;"></textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- Form Section: Financial & Document Details --}}
    <div class="form-section" data-section="financial">
        <div class="flex items-center gap-2 mb-3 pb-2 border-b" style="border-color: var(--border-color);">
            <div class="w-1 h-5 rounded-full" style="background: var(--warning);"></div>
            <h4 class="text-sm font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-file-invoice mr-1" style="color: var(--warning);"></i>
                Financial & Document Details
            </h4>
            <span class="ml-auto text-xs px-2 py-0.5 rounded-full" style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                Step 3 of 4
            </span>
        </div>

        {{-- Sale Amount (Optional) --}}
        <div>
            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                Sale Amount (Optional)
            </label>
            <div class="relative">
                <span class="absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--text-secondary);">₵</span>
                <input type="number" name="sale_amount" 
                       placeholder="Enter sale amount"
                       step="0.01"
                       min="0"
                       class="w-full pl-8 pr-4 py-2 rounded-lg transition-colors duration-200 focus:ring-2 focus:ring-primary focus:ring-opacity-50"
                       style="background-color: var(--bg-secondary); 
                              color: var(--text-primary);
                              border: 1px solid var(--border-color);
                              outline: none;">
            </div>
        </div>

        {{-- Document Type --}}
        <div>
            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                Document Type *
            </label>
            <select name="document_type" 
                    class="w-full px-4 py-2 rounded-lg transition-colors duration-200 focus:ring-2 focus:ring-primary focus:ring-opacity-50 appearance-none"
                    style="background-color: var(--bg-secondary); 
                           color: var(--text-primary);
                           border: 1px solid var(--border-color);
                           outline: none;
                           background-image: url(\"data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e\");
                           background-position: right 0.5rem center;
                           background-repeat: no-repeat;
                           background-size: 1.5em 1.5em;
                           padding-right: 2.5rem;"
                    required>
                <option value="sale_agreement">Sale Agreement</option>
                <option value="transfer_deed">Transfer Deed</option>
                <option value="gift_deed">Gift Deed</option>
                <option value="court_order">Court Order</option>
                <option value="other">Other</option>
            </select>
        </div>

        {{-- Document Reference --}}
        <div>
            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                Document Reference *
            </label>
            <input type="text" name="document_reference" 
                   value="{{ $initialDocRef }}"
                   placeholder="Enter document reference"
                   class="w-full px-4 py-2 rounded-lg transition-colors duration-200 focus:ring-2 focus:ring-primary focus:ring-opacity-50"
                   style="background-color: var(--bg-secondary); 
                          color: var(--text-primary);
                          border: 1px solid var(--border-color);
                          outline: none;"
                   required>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                <i class="fas fa-info-circle mr-1"></i>
                Unique reference number for this transfer document
            </p>
        </div>

        {{-- Document Upload --}}
        <div>
            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                Transfer Document *
            </label>
            <div class="relative">
                <input type="file" name="transfer_document" id="transfer_document"
                       accept=".pdf,.jpg,.jpeg,.png"
                       class="w-full px-4 py-2 rounded-lg transition-colors duration-200 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:cursor-pointer focus:ring-2 focus:ring-primary focus:ring-opacity-50"
                       style="background-color: var(--bg-secondary); 
                              color: var(--text-primary);
                              border: 1px solid var(--border-color);
                              outline: none;
                              file:background-color: var(--primary);
                              file:color: white;
                              file:transition: background-color 0.2s;"
                       required>
            </div>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                <i class="fas fa-info-circle mr-1"></i>
                Accepted formats: PDF, JPG, JPEG, PNG. Max size: {{ $maxFileSize / 1024 / 1024 }}MB
            </p>
            <div id="file_name_display" class="text-xs mt-1 hidden" style="color: var(--success);">
                <i class="fas fa-check-circle mr-1"></i>
                Selected: <span id="fileNameText"></span>
            </div>
        </div>
    </div>

    {{-- Form Section: Additional Information --}}
    <div class="form-section" data-section="additional">
        <div class="flex items-center gap-2 mb-3 pb-2 border-b" style="border-color: var(--border-color);">
            <div class="w-1 h-5 rounded-full" style="background: var(--info);"></div>
            <h4 class="text-sm font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-plus-circle mr-1" style="color: var(--info);"></i>
                Additional Information
            </h4>
            <span class="ml-auto text-xs px-2 py-0.5 rounded-full" style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                Step 4 of 4
            </span>
        </div>

        {{-- Reason for Transfer --}}
        <div>
            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                Reason for Transfer (Optional)
            </label>
            <textarea name="reason_for_transfer" rows="3"
                      placeholder="Provide a reason for this transfer"
                      class="w-full px-4 py-2 rounded-lg transition-colors duration-200 focus:ring-2 focus:ring-primary focus:ring-opacity-50"
                      style="background-color: var(--bg-secondary); 
                             color: var(--text-primary);
                             border: 1px solid var(--border-color);
                             outline: none;
                             resize: vertical;"></textarea>
        </div>

        {{-- Additional Notes --}}
        <div>
            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                Additional Notes (Optional)
            </label>
            <textarea name="notes" rows="2"
                      placeholder="Any additional notes"
                      class="w-full px-4 py-2 rounded-lg transition-colors duration-200 focus:ring-2 focus:ring-primary focus:ring-opacity-50"
                      style="background-color: var(--bg-secondary); 
                             color: var(--text-primary);
                             border: 1px solid var(--border-color);
                             outline: none;
                             resize: vertical;"></textarea>
        </div>
    </div>

    {{-- Tenant Warning (if property has tenants) --}}
    @if($unitsWithTenants->count() > 0)
        <div class="form-section p-4 rounded-lg" 
             style="background-color: rgba(239, 68, 68, 0.1); 
                    border: 1px solid rgba(239, 68, 68, 0.3);">
            <p class="text-sm font-medium" style="color: #ef4444;">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                Tenant Alert
            </p>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                This property has <strong>{{ $unitsWithTenants->count() }}</strong> unit(s) with tenants.
                The new owner will need to honor existing tenancy agreements.
            </p>
            <div class="mt-2">
                <label class="flex items-center cursor-pointer hover:opacity-80 transition-opacity">
                    <input type="checkbox" name="confirm_tenant_transfer" id="confirm_tenant_transfer"
                           class="mr-2 transition-colors duration-200"
                           style="accent-color: var(--primary);">
                    <span class="text-sm" style="color: var(--text-secondary);">
                        I confirm that tenants have been notified of this ownership transfer
                    </span>
                </label>
            </div>
        </div>
    @endif

    {{-- Terms and Conditions --}}
    <div class="form-section p-4 rounded-lg" 
         style="background-color: rgba(var(--primary-rgb), 0.05); 
                border: 1px solid rgba(var(--primary-rgb), 0.2);">
        <label class="flex items-start cursor-pointer hover:opacity-80 transition-opacity">
            <input type="checkbox" name="terms" id="terms"
                   class="mr-2 mt-0.5 transition-colors duration-200"
                   style="accent-color: var(--primary);"
                   required>
            <span class="text-sm" style="color: var(--text-secondary);">
                I confirm that the information provided is accurate and complete. I understand that 
                this transfer request will be reviewed by an administrator and may be subject to 
                verification.
            </span>
        </label>
    </div>

    {{-- Submit Button --}}
    <button type="submit" id="submitTransferBtn"
            class="w-full px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed hover:shadow-lg"
            style="background: linear-gradient(135deg, var(--primary), var(--secondary)); 
                   color: white;"
            disabled>
        <i class="fas fa-paper-plane mr-2"></i>
        Submit Transfer Request
    </button>
</form>

{{-- JavaScript for form interactions with scrolling effects --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    // =====================================================================
    // OWNER TYPE TOGGLE - FIXED: Ensure visibility
    // =====================================================================
    const ownerTypeRadios = document.querySelectorAll('.transfer-owner-type');
    const existingGroup = document.getElementById('existingLandlordGroup');
    const newGroup = document.getElementById('newOwnerGroup');
    
    if (ownerTypeRadios.length) {
        // Set initial state based on checked radio
        const checkedRadio = document.querySelector('.transfer-owner-type:checked');
        if (checkedRadio) {
            if (checkedRadio.value === 'existing') {
                if (existingGroup) { existingGroup.style.display = 'block'; existingGroup.style.opacity = '1'; }
                if (newGroup) { newGroup.style.display = 'none'; newGroup.style.opacity = '0'; }
            } else {
                if (existingGroup) { existingGroup.style.display = 'none'; existingGroup.style.opacity = '0'; }
                if (newGroup) { newGroup.style.display = 'block'; newGroup.style.opacity = '1'; }
            }
        }
        
        ownerTypeRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'existing') {
                    if (existingGroup) {
                        existingGroup.style.display = 'block';
                        existingGroup.style.opacity = '1';
                        existingGroup.style.animation = 'fadeIn 0.3s ease-out';
                    }
                    if (newGroup) {
                        newGroup.style.display = 'none';
                        newGroup.style.opacity = '0';
                    }
                } else {
                    if (existingGroup) {
                        existingGroup.style.display = 'none';
                        existingGroup.style.opacity = '0';
                    }
                    if (newGroup) {
                        newGroup.style.display = 'block';
                        newGroup.style.opacity = '1';
                        newGroup.style.animation = 'fadeIn 0.3s ease-out';
                    }
                }
                
                // Trigger scroll update after content change
                setTimeout(updateScrollControls, 100);
            });
        });
    }
    
    // =====================================================================
    // FILE UPLOAD PREVIEW
    // =====================================================================
    const documentInput = document.getElementById('transfer_document');
    const fileNameDisplay = document.getElementById('file_name_display');
    const fileNameText = document.getElementById('fileNameText');
    
    if (documentInput && fileNameDisplay && fileNameText) {
        documentInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                fileNameText.textContent = this.files[0].name;
                fileNameDisplay.style.display = 'block';
                fileNameDisplay.style.animation = 'fadeIn 0.3s ease-out';
            } else {
                fileNameDisplay.style.display = 'none';
            }
        });
    }
    
    // =====================================================================
    // TERMS CHECKBOX - ENABLE SUBMIT
    // =====================================================================
    const termsCheckbox = document.getElementById('terms');
    const submitBtn = document.getElementById('submitTransferBtn');
    
    if (termsCheckbox && submitBtn) {
        termsCheckbox.addEventListener('change', function() {
            submitBtn.disabled = !this.checked;
            if (this.checked) {
                submitBtn.style.transform = 'scale(1.02)';
                setTimeout(() => {
                    submitBtn.style.transform = 'scale(1)';
                }, 200);
            }
        });
    }

    // =====================================================================
    // FIXED: SCROLLING EFFECTS - Modal Container Based
    // =====================================================================
    const form = document.getElementById('transferForm');
    const scrollProgress = document.getElementById('scrollProgress');
    const scrollProgressBar = document.getElementById('scrollProgressBar');
    const scrollNavButtons = document.getElementById('scrollNavButtons');
    const scrollToTopBtn = document.getElementById('scrollToTopBtn');
    const scrollToBottomBtn = document.getElementById('scrollToBottomBtn');
    const scrollToSubmitBtn = document.getElementById('scrollToSubmitBtn');
    const formSections = document.querySelectorAll('.form-section');

    // Get the modal container
    const modalContainer = document.querySelector('.email-modal, .transfer-modal-container, .modal-body');
    const modalBody = document.querySelector('.modal-body');
    
    // Function to get the scrollable container
    function getScrollContainer() {
        // Check if form is inside a modal with overflow
        let container = form?.closest('.overflow-y-auto, .modal-body, [style*="overflow-y"]');
        if (container) {
            return container;
        }
        // Fallback to window
        return window;
    }

    // Show/hide scroll controls based on scroll position
    function updateScrollControls() {
        if (!form) return;

        const container = getScrollContainer();
        let scrollTop, clientHeight, scrollHeight;
        
        if (container === window) {
            scrollTop = window.scrollY || document.documentElement.scrollTop;
            clientHeight = window.innerHeight;
            scrollHeight = document.documentElement.scrollHeight;
        } else {
            scrollTop = container.scrollTop || 0;
            clientHeight = container.clientHeight || 0;
            scrollHeight = container.scrollHeight || 0;
        }
        
        // Check if form is visible
        const formRect = form.getBoundingClientRect();
        const isVisible = formRect.top < clientHeight && formRect.bottom > 0;
        
        if (isVisible) {
            scrollProgress?.classList.remove('hidden');
            scrollNavButtons?.classList.remove('hidden');
            
            // Calculate progress within the form
            const formTop = formRect.top;
            const formHeight = formRect.height;
            const visibleHeight = Math.min(clientHeight, formHeight);
            const scrolled = Math.max(0, -formTop);
            const progress = formHeight > clientHeight ? Math.min(100, (scrolled / (formHeight - clientHeight)) * 100) : 0;
            
            if (scrollProgressBar) {
                scrollProgressBar.style.width = progress + '%';
            }
        } else {
            scrollProgress?.classList.add('hidden');
            scrollNavButtons?.classList.add('hidden');
        }
    }

    // Scroll to specific element within the modal
    function scrollToElement(element, offset = 20) {
        if (!element) return;
        
        const container = getScrollContainer();
        const elementRect = element.getBoundingClientRect();
        const containerRect = container.getBoundingClientRect ? container.getBoundingClientRect() : { top: 0 };
        
        let targetY;
        if (container === window) {
            targetY = elementRect.top + window.scrollY - offset;
        } else {
            targetY = elementRect.top - containerRect.top + container.scrollTop - offset;
        }
        
        if (container === window) {
            window.scrollTo({
                top: targetY,
                behavior: 'smooth'
            });
        } else {
            container.scrollTo({
                top: targetY,
                behavior: 'smooth'
            });
        }
        
        // Highlight the element
        element.style.transition = 'all 0.3s ease';
        element.style.boxShadow = '0 0 0 2px var(--primary)';
        setTimeout(() => {
            element.style.boxShadow = 'none';
        }, 1500);
    }

    // Scroll to Top
    if (scrollToTopBtn) {
        scrollToTopBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const firstSection = document.querySelector('.form-section');
            if (firstSection) {
                scrollToElement(firstSection, 10);
            } else {
                const container = getScrollContainer();
                if (container === window) {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    container.scrollTo({ top: 0, behavior: 'smooth' });
                }
            }
        });
    }

    // Scroll to Bottom (Submit button)
    if (scrollToBottomBtn) {
        scrollToBottomBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const submitBtn = document.getElementById('submitTransferBtn');
            if (submitBtn) {
                scrollToElement(submitBtn);
            } else {
                const container = getScrollContainer();
                if (container === window) {
                    window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
                } else {
                    container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
                }
            }
        });
    }

    // Scroll to Submit Button
    if (scrollToSubmitBtn) {
        scrollToSubmitBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const submitBtn = document.getElementById('submitTransferBtn');
            if (submitBtn) {
                scrollToElement(submitBtn);
                // Pulse animation on submit button
                submitBtn.style.animation = 'pulse 0.6s ease 3';
                setTimeout(() => {
                    submitBtn.style.animation = '';
                }, 1800);
            }
        });
    }

    // Update on scroll with throttling
    let scrollTimeout;
    const container = getScrollContainer();
    
    if (container === window) {
        window.addEventListener('scroll', function() {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(updateScrollControls, 50);
        });
        window.addEventListener('resize', function() {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(updateScrollControls, 100);
        });
    } else {
        container.addEventListener('scroll', function() {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(updateScrollControls, 50);
        });
        window.addEventListener('resize', function() {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(updateScrollControls, 100);
        });
    }

    // Initial update
    setTimeout(updateScrollControls, 300);

    // Also update when modal content changes
    if (window.MutationObserver) {
        const observer = new MutationObserver(function() {
            setTimeout(updateScrollControls, 100);
        });
        observer.observe(form, { childList: true, subtree: true, attributes: true });
    }

    // =====================================================================
    // KEYBOARD SHORTCUTS FOR SCROLLING
    // =====================================================================
    document.addEventListener('keydown', function(e) {
        // Ctrl+Shift+Up: Scroll to top
        if (e.ctrlKey && e.shiftKey && e.key === 'ArrowUp') {
            e.preventDefault();
            scrollToTopBtn?.click();
        }
        // Ctrl+Shift+Down: Scroll to bottom
        if (e.ctrlKey && e.shiftKey && e.key === 'ArrowDown') {
            e.preventDefault();
            scrollToBottomBtn?.click();
        }
        // Ctrl+Shift+End: Scroll to submit
        if (e.ctrlKey && e.shiftKey && e.key === 'End') {
            e.preventDefault();
            scrollToSubmitBtn?.click();
        }
    });

    // =====================================================================
    // ADD CSS ANIMATIONS
    // =====================================================================
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .form-section {
            transition: all 0.3s ease;
        }
        
        .form-section:hover {
            transform: translateX(4px);
        }
        
        #scrollProgressBar {
            transition: width 0.3s ease;
        }
        
        #scrollNavButtons button {
            transition: all 0.2s ease;
            backdrop-filter: blur(10px);
        }
        
        #scrollNavButtons button:hover {
            transform: scale(1.15) !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        
        #scrollNavButtons button:active {
            transform: scale(0.95) !important;
        }
    `;
    document.head.appendChild(style);

    console.log('✅ Transfer form initialized with scrolling effects');
});
</script>