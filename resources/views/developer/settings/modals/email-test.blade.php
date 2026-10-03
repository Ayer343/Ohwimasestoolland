{{-- developer/settings/modals/email-test.blade.php --}}
<!-- Email Test Modal -->
<div id="emailTestModal" class="modal hidden">
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-envelope mr-2"></i>
                Test Email Configuration
            </h3>
            <button type="button" class="modal-close-btn" onclick="hideEmailTestModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <div id="emailTestContent">
                <!-- Step 1: Select Test Type -->
                <div id="emailTestStep1">
                    <div class="mb-4">
                        <p class="form-help">
                            Select the type of email test you want to perform.
                        </p>
                    </div>
                    
                    <div class="space-y-3">
                        <button type="button" class="email-test-option" onclick="selectEmailTestType('connection')">
                            <div class="flex items-center">
                                <div class="test-option-icon">
                                    <i class="fas fa-plug"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="toggle-title">Connection Test</p>
                                    <p class="toggle-description">
                                        Test SMTP server connection and authentication
                                    </p>
                                </div>
                            </div>
                        </button>
                        
                        <button type="button" class="email-test-option" onclick="selectEmailTestType('send')">
                            <div class="flex items-center">
                                <div class="test-option-icon">
                                    <i class="fas fa-paper-plane"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="toggle-title">Send Test Email</p>
                                    <p class="toggle-description">
                                        Send a test email to verify sending capability
                                    </p>
                                </div>
                            </div>
                        </button>
                        
                        <button type="button" class="email-test-option" onclick="selectEmailTestType('template')">
                            <div class="flex items-center">
                                <div class="test-option-icon">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="toggle-title">Template Test</p>
                                    <p class="toggle-description">
                                        Test email template rendering and delivery
                                    </p>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
                
                <!-- Step 2: Enter Email Address -->
                <div id="emailTestStep2" class="hidden">
                    <div class="mb-4">
                        <p class="form-help">
                            Enter the email address where the test should be sent.
                        </p>
                    </div>
                    
                    <div class="space-y-4">
                        <!-- CSRF Token Field -->
                        <input type="hidden" id="csrf_token" value="{{ csrf_token() }}">
                        
                        <div>
                            <label class="form-label">
                                Test Email Address *
                            </label>
                            <input type="email" 
                                   id="testEmailAddress"
                                   class="form-input w-full"
                                   placeholder="test@example.com"
                                   value="{{ auth()->user()->email }}">
                            <p class="form-help">
                                The email will be sent to this address
                            </p>
                        </div>
                        
                        <div id="emailTemplateSection" class="hidden">
                            <label class="form-label">
                                Select Template
                            </label>
                            <select id="emailTemplateSelect" class="form-select w-full">
                                <option value="welcome">Welcome Email</option>
                                <option value="invoice">Invoice Template</option>
                                <option value="notification">Notification Template</option>
                                <option value="alert">Alert Template</option>
                            </select>
                        </div>
                        
                        <div class="flex items-center justify-between pt-4 border-t section-divider">
                            <button type="button" 
                                    onclick="backToTestType()"
                                    class="btn btn-secondary">
                                <i class="fas fa-arrow-left mr-2"></i> Back
                            </button>
                            <button type="button" 
                                    onclick="startEmailTest()"
                                    class="btn btn-primary">
                                <i class="fas fa-play mr-2"></i> Start Test
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Step 3: Test Progress -->
                <div id="emailTestStep3" class="hidden">
                    <div class="text-center py-6">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-4 test-progress-icon">
                            <i class="fas fa-spinner fa-spin text-2xl"></i>
                        </div>
                        <h4 class="section-subtitle">
                            Testing Email Configuration
                        </h4>
                        <p class="form-help mb-4" id="testProgressText">
                            Initializing test...
                        </p>
                        
                        <!-- Progress Bar -->
                        <div class="w-full bg-gray-200 rounded-full h-2 mb-4">
                            <div id="testProgressBar" 
                                 class="h-2 rounded-full test-progress-bar transition-all duration-300"
                                 style="width: 0%"></div>
                        </div>
                        
                        <!-- Test Steps -->
                        <div class="space-y-2 text-left">
                            <div class="test-step" id="stepConnect">
                                <i class="fas fa-circle mr-2 test-step-icon"></i>
                                <span>Connecting to SMTP server</span>
                            </div>
                            <div class="test-step" id="stepAuth">
                                <i class="fas fa-circle mr-2 test-step-icon"></i>
                                <span>Authenticating credentials</span>
                            </div>
                            <div class="test-step" id="stepSend">
                                <i class="fas fa-circle mr-2 test-step-icon"></i>
                                <span>Sending test email</span>
                            </div>
                            <div class="test-step" id="stepVerify">
                                <i class="fas fa-circle mr-2 test-step-icon"></i>
                                <span>Verifying delivery</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Step 4: Test Results -->
                <div id="emailTestStep4" class="hidden">
                    <div id="testResultContent">
                        <!-- Success Result -->
                        <div id="testSuccessResult" class="hidden">
                            <div class="text-center py-6">
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-4 test-success-icon">
                                    <i class="fas fa-check-circle text-2xl"></i>
                                </div>
                                <h4 class="section-subtitle">
                                    Test Successful!
                                </h4>
                                <p class="form-help mb-4" id="successMessage">
                                    Email configuration is working correctly.
                                </p>
                                
                                <div class="result-card success-card mb-4">
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between items-center py-2 border-b result-item">
                                            <span class="result-label">Test Type:</span>
                                            <span id="resultTestType" class="result-value"></span>
                                        </div>
                                        <div class="flex justify-between items-center py-2 border-b result-item">
                                            <span class="result-label">Recipient:</span>
                                            <span id="resultRecipient" class="result-value"></span>
                                        </div>
                                        <div class="flex justify-between items-center py-2 result-item">
                                            <span class="result-label">Status:</span>
                                            <span class="badge badge-success">Success</span>
                                        </div>
                                        @if($emailTestResult && isset($emailTestResult['connection_time_ms']))
                                        <div class="flex justify-between items-center py-2 border-t mt-2 pt-2 result-item">
                                            <span class="result-label">Connection Time:</span>
                                            <span id="resultConnectionTime" class="result-value">{{ $emailTestResult['connection_time_ms'] }}ms</span>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Error Result -->
                        <div id="testErrorResult" class="hidden">
                            <div class="text-center py-6">
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-4 test-error-icon">
                                    <i class="fas fa-exclamation-circle text-2xl"></i>
                                </div>
                                <h4 class="section-subtitle">
                                    Test Failed
                                </h4>
                                <p class="form-help mb-4" id="errorMessage">
                                    There was an issue with the email configuration.
                                </p>
                                
                                <div class="result-card error-card mb-4">
                                    <div class="font-medium mb-2 error-header">
                                        <i class="fas fa-bug mr-1"></i> Error Details:
                                    </div>
                                    <div class="error-content">
                                        <pre id="errorDetails" class="text-xs whitespace-pre-wrap">
                                        </pre>
                                    </div>
                                </div>
                                
                                <div class="suggestions-card">
                                    <p class="suggestions-title">
                                        <i class="fas fa-lightbulb mr-1"></i> Suggested Actions:
                                    </p>
                                    <ul class="suggestions-list">
                                        <li class="suggestion-item">
                                            <i class="fas fa-check-circle suggestion-icon"></i>
                                            <span>Check SMTP credentials</span>
                                        </li>
                                        <li class="suggestion-item">
                                            <i class="fas fa-check-circle suggestion-icon"></i>
                                            <span>Verify port and encryption settings</span>
                                        </li>
                                        <li class="suggestion-item">
                                            <i class="fas fa-check-circle suggestion-icon"></i>
                                            <span>Ensure firewall allows outbound SMTP</span>
                                        </li>
                                        <li class="suggestion-item">
                                            <i class="fas fa-check-circle suggestion-icon"></i>
                                            <span>Check recipient email address</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="pt-4 border-t section-divider">
                        <div class="flex justify-between">
                            <button type="button" 
                                    onclick="backToTestType()"
                                    class="btn btn-secondary">
                                <i class="fas fa-redo mr-2"></i> Test Again
                            </button>
                            <button type="button" 
                                    onclick="hideEmailTestModal()"
                                    class="btn btn-primary">
                                <i class="fas fa-check mr-2"></i> Done
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Email Test Modal Specific Styles */
.modal-container {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 0.75rem;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.modal-header {
    background-color: var(--bg-secondary);
    border-bottom: 1px solid var(--border-color);
    border-top-left-radius: 0.75rem;
    border-top-right-radius: 0.75rem;
}

.email-test-option {
    width: 100%;
    padding: 1rem;
    border: 1px solid var(--border-color);
    border-radius: 0.5rem;
    cursor: pointer;
    transition: all 0.2s ease;
    background-color: var(--card-bg);
    text-align: left;
    border: none;
    display: block;
}

.email-test-option:hover {
    background-color: var(--bg-secondary);
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.email-test-option.selected {
    background-color: var(--bg-secondary);
    border: 2px solid var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.test-option-icon {
    width: 3rem;
    height: 3rem;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: var(--primary);
    color: white;
    flex-shrink: 0;
}

.test-step {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    border-radius: 0.5rem;
    background-color: var(--bg-secondary);
    border: 1px solid var(--border-color);
    margin-bottom: 0.5rem;
}

.test-step-icon {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.test-step.completed {
    background-color: #f0fdf4;
    border-color: #86efac;
}

.test-step.completed .test-step-icon {
    color: #16a34a;
}

.test-step.active {
    background-color: #eff6ff;
    border-color: #93c5fd;
}

.test-step.active .test-step-icon {
    color: #3b82f6;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.test-progress-icon {
    background-color: var(--primary);
    color: white;
}

.test-success-icon {
    background-color: var(--success);
    color: white;
}

.test-error-icon {
    background-color: var(--danger);
    color: white;
}

.test-progress-bar {
    background-color: var(--primary);
}

/* Result Cards */
.result-card {
    padding: 1.25rem;
    border-radius: 0.5rem;
    border: 1px solid;
}

.success-card {
    background-color: #f0fdf4;
    border-color: #86efac;
}

.error-card {
    background-color: #fef2f2;
    border-color: #fecaca;
}

.result-item {
    padding: 0.5rem 0;
}

.result-item:not(:last-child) {
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

[data-theme="dark"] .result-item:not(:last-child) {
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.result-label {
    color: var(--text-secondary);
    font-weight: 500;
}

.result-value {
    color: var(--text-primary);
    font-weight: 600;
}

/* Error Details */
.error-header {
    color: var(--danger);
    padding-bottom: 0.5rem;
    border-bottom: 1px solid rgba(239, 68, 68, 0.2);
}

.error-content {
    margin-top: 0.75rem;
    padding: 0.75rem;
    background-color: white;
    border-radius: 0.375rem;
    border: 1px solid #fecaca;
    max-height: 150px;
    overflow-y: auto;
}

[data-theme="dark"] .error-content {
    background-color: #1f2937;
    border-color: #7f1d1d;
}

#errorDetails {
    color: #dc2626;
    font-family: 'Courier New', monospace;
    margin: 0;
    line-height: 1.4;
}

[data-theme="dark"] #errorDetails {
    color: #fca5a5;
}

/* Suggestions */
.suggestions-card {
    background-color: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-top: 1rem;
}

[data-theme="dark"] .suggestions-card {
    background-color: #451a03;
    border-color: #92400e;
}

.suggestions-title {
    color: #d97706;
    font-weight: 600;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
}

[data-theme="dark"] .suggestions-title {
    color: #fbbf24;
}

.suggestions-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.suggestion-item {
    display: flex;
    align-items: center;
    padding: 0.375rem 0;
    color: var(--text-secondary);
}

.suggestion-icon {
    color: #16a34a;
    margin-right: 0.5rem;
    font-size: 0.875rem;
}

/* Form Elements */
.form-input, .form-select {
    background-color: white;
    border: 1px solid #d1d5db;
}

[data-theme="dark"] .form-input,
[data-theme="dark"] .form-select {
    background-color: #374151;
    border-color: #4b5563;
}

.form-input:focus, .form-select:focus {
    background-color: white;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

[data-theme="dark"] .form-input:focus,
[data-theme="dark"] .form-select:focus {
    background-color: #374151;
}

/* Buttons */
.btn {
    font-weight: 500;
    border: 1px solid transparent;
    transition: all 0.2s ease;
}

.btn-primary {
    background-color: var(--primary);
    color: white;
}

.btn-primary:hover {
    background-color: var(--secondary);
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.btn-secondary {
    background-color: #f3f4f6;
    color: var(--text-primary);
    border-color: #d1d5db;
}

[data-theme="dark"] .btn-secondary {
    background-color: #4b5563;
    border-color: #6b7280;
    color: white;
}

.btn-secondary:hover {
    background-color: #e5e7eb;
    border-color: #9ca3af;
}

[data-theme="dark"] .btn-secondary:hover {
    background-color: #6b7280;
    border-color: #9ca3af;
}

/* Progress Bar Background */
.bg-gray-200 {
    background-color: #e5e7eb;
}

[data-theme="dark"] .bg-gray-200 {
    background-color: #4b5563;
}

/* Section Divider */
.section-divider {
    border-color: var(--border-color);
}

/* Badge */
.badge {
    font-weight: 600;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    border: 1px solid;
}

.badge-success {
    background-color: #dcfce7;
    color: #16a34a;
    border-color: #86efac;
}

[data-theme="dark"] .badge-success {
    background-color: #14532d;
    color: #86efac;
    border-color: #22c55e;
}

/* Modal Close Button */
.modal-close-btn {
    background-color: #f3f4f6;
    border: 1px solid #d1d5db;
    color: var(--text-secondary);
    width: 2rem;
    height: 2rem;
    border-radius: 0.375rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.modal-close-btn:hover {
    background-color: #e5e7eb;
    color: var(--text-primary);
}

[data-theme="dark"] .modal-close-btn {
    background-color: #4b5563;
    border-color: #6b7280;
    color: #9ca3af;
}

[data-theme="dark"] .modal-close-btn:hover {
    background-color: #6b7280;
    color: white;
}
</style>

<script>
// Get CSRF token from hidden input
function getCsrfToken() {
    return document.getElementById('csrf_token')?.value || 
           document.querySelector('meta[name="csrf-token"]')?.content || 
           '{{ csrf_token() }}';
}

let currentTestType = '';
let testEmail = '';
let testTemplate = '';

function showEmailTestModal() {
    resetEmailTestModal();
    const modal = document.getElementById('emailTestModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function hideEmailTestModal() {
    const modal = document.getElementById('emailTestModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function resetEmailTestModal() {
    // Reset all steps
    const step1 = document.getElementById('emailTestStep1');
    const step2 = document.getElementById('emailTestStep2');
    const step3 = document.getElementById('emailTestStep3');
    const step4 = document.getElementById('emailTestStep4');
    
    if (step1) step1.classList.remove('hidden');
    if (step2) step2.classList.add('hidden');
    if (step3) step3.classList.add('hidden');
    if (step4) step4.classList.add('hidden');
    
    // Reset selections
    document.querySelectorAll('.email-test-option').forEach(option => {
        option.classList.remove('selected');
    });
    
    // Reset form
    const emailInput = document.getElementById('testEmailAddress');
    const templateSelect = document.getElementById('emailTemplateSelect');
    if (emailInput) emailInput.value = '{{ auth()->user()->email }}';
    if (templateSelect) templateSelect.value = 'welcome';
    
    // Reset results
    const successResult = document.getElementById('testSuccessResult');
    const errorResult = document.getElementById('testErrorResult');
    if (successResult) successResult.classList.add('hidden');
    if (errorResult) errorResult.classList.add('hidden');
}

function selectEmailTestType(type) {
    currentTestType = type;
    
    // Update UI
    document.querySelectorAll('.email-test-option').forEach(option => {
        option.classList.remove('selected');
    });
    event.currentTarget.classList.add('selected');
    
    // Show/hide template section
    const templateSection = document.getElementById('emailTemplateSection');
    if (templateSection) {
        if (type === 'template') {
            templateSection.classList.remove('hidden');
        } else {
            templateSection.classList.add('hidden');
        }
    }
    
    // Proceed to next step
    setTimeout(() => {
        const step1 = document.getElementById('emailTestStep1');
        const step2 = document.getElementById('emailTestStep2');
        if (step1 && step2) {
            step1.classList.add('hidden');
            step2.classList.remove('hidden');
        }
    }, 300);
}

function backToTestType() {
    const step2 = document.getElementById('emailTestStep2');
    const step3 = document.getElementById('emailTestStep3');
    const step4 = document.getElementById('emailTestStep4');
    const step1 = document.getElementById('emailTestStep1');
    
    if (step2) step2.classList.add('hidden');
    if (step3) step3.classList.add('hidden');
    if (step4) step4.classList.add('hidden');
    if (step1) step1.classList.remove('hidden');
}

function startEmailTest() {
    const emailInput = document.getElementById('testEmailAddress');
    const templateSelect = document.getElementById('emailTemplateSelect');
    
    if (!emailInput) return;
    
    testEmail = emailInput.value;
    testTemplate = templateSelect ? templateSelect.value : 'welcome';
    
    // Validate email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(testEmail)) {
        showToast('Please enter a valid email address', 'error');
        emailInput.focus();
        return;
    }
    
    // Proceed to progress step
    const step2 = document.getElementById('emailTestStep2');
    const step3 = document.getElementById('emailTestStep3');
    
    if (step2 && step3) {
        step2.classList.add('hidden');
        step3.classList.remove('hidden');
    }
    
    // Start the test
    performEmailTest();
}

function performEmailTest() {
    // Reset progress
    resetTestProgress();
    
    // Start progress animation
    updateTestProgress('Initializing test...', 10);
    
    // Simulate connection step
    setTimeout(() => {
        updateTestStep('stepConnect', true);
        updateTestProgress('Connecting to SMTP server...', 30);
        
        setTimeout(() => {
            updateTestStep('stepAuth', true);
            updateTestProgress('Authenticating credentials...', 50);
            
            // Get CSRF token
            const csrfToken = getCsrfToken();
            if (!csrfToken) {
                console.error('CSRF token not found');
                showTestResults({
                    success: false,
                    message: 'CSRF token not found. Please refresh the page and try again.',
                    details: 'Unable to retrieve CSRF token for request.'
                });
                return;
            }
            
            // Create FormData instead of JSON (better for Laravel)
            const formData = new FormData();
            formData.append('_token', csrfToken);
            formData.append('test_email', testEmail);
            formData.append('test_type', currentTestType);
            if (currentTestType === 'template') {
                formData.append('template', testTemplate);
            }
            
            // Make AJAX request with FormData
            fetch('{{ route("developer.email.test") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                // Check content type
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json().then(data => {
                        if (!response.ok) {
                            throw new Error(data.message || `HTTP error! status: ${response.status}`);
                        }
                        return data;
                    });
                } else {
                    return response.text().then(text => {
                        console.log('Raw response:', text);
                        try {
                            return JSON.parse(text);
                        } catch {
                            throw new Error(`Invalid response format: ${text.substring(0, 100)}...`);
                        }
                    });
                }
            })
            .then(data => {
                console.log('Test response data:', data);
                updateTestStep('stepSend', true);
                updateTestProgress('Sending test email...', 80);
                
                setTimeout(() => {
                    updateTestStep('stepVerify', true);
                    updateTestProgress('Verifying delivery...', 100);
                    
                    setTimeout(() => {
                        showTestResults(data);
                    }, 500);
                }, 500);
            })
            .catch(error => {
                console.error('Email test error:', error);
                showTestResults({
                    success: false,
                    message: error.message || 'Test failed unexpectedly',
                    details: error.toString()
                });
            });
            
        }, 1000);
    }, 1000);
}

function resetTestProgress() {
    // Reset steps
    document.querySelectorAll('.test-step').forEach(step => {
        step.classList.remove('completed', 'active');
        const icon = step.querySelector('i');
        if (icon) {
            icon.className = 'fas fa-circle mr-2 test-step-icon';
        }
    });
    
    // Reset progress bar
    const progressBar = document.getElementById('testProgressBar');
    if (progressBar) {
        progressBar.style.width = '0%';
    }
}

function updateTestProgress(text, percentage) {
    const progressText = document.getElementById('testProgressText');
    const progressBar = document.getElementById('testProgressBar');
    
    if (progressText) progressText.textContent = text;
    if (progressBar) progressBar.style.width = percentage + '%';
}

function updateTestStep(stepId, completed = false) {
    const step = document.getElementById(stepId);
    if (!step) return;
    
    const icon = step.querySelector('i');
    
    // Remove active from all steps
    document.querySelectorAll('.test-step').forEach(s => {
        s.classList.remove('active');
    });
    
    if (completed) {
        step.classList.add('completed');
        step.classList.remove('active');
        if (icon) {
            icon.className = 'fas fa-check-circle mr-2 test-step-icon';
            icon.style.color = '#16a34a';
        }
    } else {
        step.classList.add('active');
        step.classList.remove('completed');
        if (icon) {
            icon.className = 'fas fa-circle mr-2 test-step-icon';
            icon.style.color = '#3b82f6';
        }
    }
}

function showTestResults(data) {
    // Show results step
    const step3 = document.getElementById('emailTestStep3');
    const step4 = document.getElementById('emailTestStep4');
    
    if (step3) step3.classList.add('hidden');
    if (step4) step4.classList.remove('hidden');
    
    if (data.success) {
        // Show success result
        const successResult = document.getElementById('testSuccessResult');
        const errorResult = document.getElementById('testErrorResult');
        
        if (successResult) successResult.classList.remove('hidden');
        if (errorResult) errorResult.classList.add('hidden');
        
        // Update success details
        const successMessage = document.getElementById('successMessage');
        const resultTestType = document.getElementById('resultTestType');
        const resultRecipient = document.getElementById('resultRecipient');
        
        if (successMessage) successMessage.textContent = data.message || 'Email configuration is working correctly.';
        if (resultTestType) resultTestType.textContent = formatTestType(currentTestType);
        if (resultRecipient) resultRecipient.textContent = testEmail;
        
        // Show toast notification
        showToast('Email test successful! ' + (data.message || ''), 'success');
        
    } else {
        // Show error result
        const successResult = document.getElementById('testSuccessResult');
        const errorResult = document.getElementById('testErrorResult');
        
        if (successResult) successResult.classList.add('hidden');
        if (errorResult) errorResult.classList.remove('hidden');
        
        // Update error details
        const errorMessage = document.getElementById('errorMessage');
        const errorDetails = document.getElementById('errorDetails');
        
        if (errorMessage) errorMessage.textContent = data.message || 'There was an issue with the email configuration.';
        if (errorDetails) {
            // Check for Laravel validation errors
            if (data.errors && typeof data.errors === 'object') {
                const errors = [];
                Object.keys(data.errors).forEach(key => {
                    if (Array.isArray(data.errors[key])) {
                        errors.push(...data.errors[key]);
                    } else {
                        errors.push(data.errors[key]);
                    }
                });
                errorDetails.textContent = errors.join('\n');
            } else {
                errorDetails.textContent = data.details || data.message || 'No additional details available.';
            }
        }
        
        // Show toast notification
        showToast('Email test failed: ' + (data.message || 'Unknown error'), 'error');
    }
}

function formatTestType(type) {
    const types = {
        'connection': 'Connection Test',
        'send': 'Send Test Email',
        'template': 'Template Test'
    };
    return types[type] || type;
}

// Helper function for toast notifications
function showToast(message, type = 'info') {
    // Use your existing toast function from the main blade
    if (typeof window.showToast === 'function') {
        window.showToast(message, type);
    } else {
        // Create a simple toast if function doesn't exist
        const toast = document.createElement('div');
        toast.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg ${
            type === 'success' ? 'bg-green-500 text-white' :
            type === 'error' ? 'bg-red-500 text-white' :
            'bg-blue-500 text-white'
        }`;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 5000);
    }
}

// Initialize modal event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideEmailTestModal();
        }
    });
    
    // Ensure modal exists
    if (!document.getElementById('emailTestModal')) {
        console.warn('Email test modal not found in DOM');
    }
});
</script>