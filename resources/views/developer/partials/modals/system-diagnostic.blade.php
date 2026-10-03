{{-- System Diagnostic Modal --}}
<div id="systemDiagnosticModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" onclick="closeDiagnosticModal()"></div>
    
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="relative w-full max-w-4xl transform rounded-lg transition-all" style="background: var(--card-bg);">
            <!-- Modal Header -->
            <div class="p-6" style="border-bottom: 1px solid var(--border-color);">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-semibold text-[var(--text-primary)]">
                            <i class="fas fa-stethoscope mr-2" style="color: var(--primary);"></i>
                            System Diagnostic
                        </h3>
                        <p class="text-sm text-[var(--text-secondary)] mt-1">
                            Comprehensive system health check and analysis
                        </p>
                    </div>
                    <button onclick="closeDiagnosticModal()" class="p-2 rounded-lg hover:bg-[var(--bg-secondary)] transition-colors">
                        <i class="fas fa-times text-lg" style="color: var(--text-secondary);"></i>
                    </button>
                </div>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6 max-h-[70vh] overflow-y-auto">
                <!-- Diagnostic Progress -->
                <div id="diagnosticProgress" class="hidden">
                    <div class="text-center py-8">
                        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-[var(--primary)] mb-4"></div>
                        <h4 class="font-medium text-[var(--text-primary)] mb-2">Running Diagnostic Tests...</h4>
                        <p class="text-sm text-[var(--text-secondary)] mb-4" id="currentTest">Initializing diagnostic tests</p>
                        
                        <div class="w-full rounded-full h-2" style="background: var(--border-color);">
                            <div id="diagnosticProgressBar" class="h-2 rounded-full" style="background: var(--primary); width: 0%"></div>
                        </div>
                        <div class="flex justify-between text-xs mt-2" style="color: var(--text-secondary);">
                            <span id="progressText">0% Complete</span>
                            <span id="estimatedTime">Estimating...</span>
                        </div>
                    </div>
                </div>
                
                <!-- Diagnostic Results -->
                <div id="diagnosticResults" class="hidden">
                    <!-- Health Score -->
                    <div class="mb-8">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="font-medium text-[var(--text-primary)]">Diagnostic Results</h4>
                            <span class="px-3 py-1 rounded-full text-sm font-medium" id="overallStatusLabel">Loading...</span>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="rounded-lg p-4" style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <div class="text-2xl font-bold text-[var(--text-primary)] text-center" id="healthScoreResult">0</div>
                                <div class="text-sm text-center" style="color: var(--text-secondary);">Health Score</div>
                            </div>
                            <div class="rounded-lg p-4" style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <div class="text-2xl font-bold text-[var(--text-primary)] text-center" id="testsPassed">0</div>
                                <div class="text-sm text-center" style="color: var(--text-secondary);">Tests Passed</div>
                            </div>
                            <div class="rounded-lg p-4" style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <div class="text-2xl font-bold text-[var(--text-primary)] text-center" id="diagnosticTime">0ms</div>
                                <div class="text-sm text-center" style="color: var(--text-secondary);">Duration</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Test Results -->
                    <div class="mb-8">
                        <h4 class="font-medium text-[var(--text-primary)] mb-4">Test Details</h4>
                        <div class="space-y-3" id="testResultsList">
                            <!-- Test results will be populated here -->
                        </div>
                    </div>
                    
                    <!-- Issues & Recommendations -->
                    <div class="mb-8" id="issuesSection" style="display: none;">
                        <h4 class="font-medium text-[var(--text-primary)] mb-4">Issues Found</h4>
                        <div class="space-y-3" id="issuesList">
                            <!-- Issues will be populated here -->
                        </div>
                    </div>
                    
                    <!-- Recommendations -->
                    <div id="recommendationsSection" style="display: none;">
                        <h4 class="font-medium text-[var(--text-primary)] mb-4">Recommendations</h4>
                        <div class="space-y-3" id="recommendationsList">
                            <!-- Recommendations will be populated here -->
                        </div>
                    </div>
                </div>
                
                <!-- Diagnostic Failed -->
                <div id="diagnosticFailed" class="hidden text-center py-8">
                    <i class="fas fa-exclamation-triangle text-4xl mb-4" style="color: var(--danger);"></i>
                    <h4 class="font-medium text-[var(--text-primary)] mb-2">Diagnostic Failed</h4>
                    <p class="text-sm text-[var(--text-secondary)] mb-4" id="errorMessage">An error occurred during diagnostic</p>
                    <button onclick="retryDiagnostic()" class="px-4 py-2 rounded-lg font-medium transition-colors" style="background: var(--primary); color: white;">
                        <i class="fas fa-redo mr-2"></i> Retry Diagnostic
                    </button>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="p-6" style="border-top: 1px solid var(--border-color);">
                <div class="flex justify-between">
                    <button onclick="closeDiagnosticModal()" class="px-4 py-2 rounded-lg font-medium transition-colors" style="background: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                        Close
                    </button>
                    <div class="flex space-x-2">
                        <button onclick="exportDiagnosticResults()" class="px-4 py-2 rounded-lg font-medium transition-colors" style="background: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                            <i class="fas fa-download mr-2"></i> Export
                        </button>
                        <button onclick="runFullDiagnostic()" class="px-4 py-2 rounded-lg font-medium transition-colors" style="background: var(--primary); color: white;">
                            <i class="fas fa-play mr-2"></i> Run Diagnostic
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Diagnostic Modal Functions
function showDiagnosticModal() {
    const modal = document.getElementById('systemDiagnosticModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeDiagnosticModal() {
    const modal = document.getElementById('systemDiagnosticModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function runFullDiagnostic() {
    // Show progress
    document.getElementById('diagnosticProgress').classList.remove('hidden');
    document.getElementById('diagnosticResults').classList.add('hidden');
    document.getElementById('diagnosticFailed').classList.add('hidden');
    
    // Reset progress
    updateDiagnosticProgress(0, 'Initializing diagnostic tests...');
    
    // Run diagnostic
    fetch('{{ route("developer.diagnostics.run") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        // Hide progress
        document.getElementById('diagnosticProgress').classList.add('hidden');
        
        if (data.success) {
            showDiagnosticResults(data);
        } else {
            showDiagnosticFailed(data.message);
        }
    })
    .catch(error => {
        document.getElementById('diagnosticProgress').classList.add('hidden');
        showDiagnosticFailed(error.message);
    });
    
    // Simulate progress updates (for UI feedback)
    simulateDiagnosticProgress();
}

function simulateDiagnosticProgress() {
    let progress = 0;
    const tests = [
        'Checking database connection...',
        'Testing cache performance...',
        'Verifying storage access...',
        'Checking queue status...',
        'Testing external services...',
        'Analyzing performance metrics...',
        'Checking security settings...',
        'Generating recommendations...'
    ];
    
    const interval = setInterval(() => {
        if (progress >= 90) {
            clearInterval(interval);
            return;
        }
        
        progress += 10;
        const testIndex = Math.floor(progress / 10) - 1;
        const testName = tests[testIndex] || 'Finalizing diagnostic...';
        
        updateDiagnosticProgress(progress, testName);
        
        if (progress >= 90) {
            clearInterval(interval);
        }
    }, 500);
}

function updateDiagnosticProgress(percent, currentTest) {
    const progressBar = document.getElementById('diagnosticProgressBar');
    const progressText = document.getElementById('progressText');
    const currentTestElement = document.getElementById('currentTest');
    const estimatedTime = document.getElementById('estimatedTime');
    
    if (progressBar) progressBar.style.width = percent + '%';
    if (progressText) progressText.textContent = percent + '% Complete';
    if (currentTestElement) currentTestElement.textContent = currentTest;
    
    // Calculate estimated time remaining
    const remaining = 100 - percent;
    const secondsRemaining = Math.round(remaining / 10) * 5;
    if (estimatedTime) {
        estimatedTime.textContent = secondsRemaining > 0 ? 
            `${secondsRemaining}s remaining` : 'Almost done...';
    }
}

function showDiagnosticResults(data) {
    const resultsElement = document.getElementById('diagnosticResults');
    resultsElement.classList.remove('hidden');
    
    // Update health score
    const healthScore = document.getElementById('healthScoreResult');
    if (healthScore && data.health_score) {
        healthScore.textContent = data.health_score;
    }
    
    // Update overall status
    const statusLabel = document.getElementById('overallStatusLabel');
    if (statusLabel) {
        statusLabel.textContent = data.health_status ? 
            data.health_status.charAt(0).toUpperCase() + data.health_status.slice(1) : 
            'Unknown';
        
        const computedStyle = getComputedStyle(document.body);
        let bgColor, textColor;
        
        switch(data.health_status) {
            case 'excellent':
            case 'good':
                bgColor = hexToRgba(computedStyle.getPropertyValue('--success').trim(), 0.1);
                textColor = computedStyle.getPropertyValue('--success').trim();
                break;
            case 'fair':
                bgColor = hexToRgba(computedStyle.getPropertyValue('--warning').trim(), 0.1);
                textColor = computedStyle.getPropertyValue('--warning').trim();
                break;
            case 'poor':
            case 'critical':
                bgColor = hexToRgba(computedStyle.getPropertyValue('--danger').trim(), 0.1);
                textColor = computedStyle.getPropertyValue('--danger').trim();
                break;
            default:
                bgColor = hexToRgba(computedStyle.getPropertyValue('--text-secondary').trim(), 0.1);
                textColor = computedStyle.getPropertyValue('--text-secondary').trim();
        }
        
        statusLabel.style.cssText = `
            background-color: ${bgColor};
            color: ${textColor};
            border: 1px solid ${hexToRgba(textColor, 0.3)};
        `;
    }
    
    // Update tests passed
    const testsPassed = document.getElementById('testsPassed');
    if (testsPassed && data.diagnostics) {
        const passedTests = Object.values(data.diagnostics).filter(test => test.healthy).length;
        testsPassed.textContent = `${passedTests}/${Object.keys(data.diagnostics).length}`;
    }
    
    // Update duration
    const diagnosticTime = document.getElementById('diagnosticTime');
    if (diagnosticTime && data.diagnostic_time_ms) {
        diagnosticTime.textContent = data.diagnostic_time_ms + 'ms';
    }
    
    // Populate test results
    const testResultsList = document.getElementById('testResultsList');
    if (testResultsList && data.diagnostics) {
        let html = '';
        const computedStyle = getComputedStyle(document.body);
        
        Object.entries(data.diagnostics).forEach(([testName, testResult]) => {
            const isHealthy = testResult.healthy;
            const color = isHealthy ? 
                computedStyle.getPropertyValue('--success').trim() : 
                computedStyle.getPropertyValue('--danger').trim();
            const icon = isHealthy ? 'check-circle' : 'exclamation-circle';
            
            html += `
                <div class="p-3 rounded-lg transition-all" style="
                    background-color: ${hexToRgba(color, 0.1)};
                    border: 1px solid ${hexToRgba(color, 0.3)};
                ">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <i class="fas fa-${icon} mr-2" style="color: ${color};"></i>
                            <span class="font-medium" style="color: var(--text-primary);">${testName.replace('_', ' ').toUpperCase()}</span>
                        </div>
                        <span class="px-2 py-1 text-xs rounded-full" style="
                            background-color: ${hexToRgba(color, 0.2)};
                            color: ${color};
                            border: 1px solid ${hexToRgba(color, 0.3)};
                        ">
                            ${isHealthy ? 'PASSED' : 'FAILED'}
                        </span>
                    </div>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">${testResult.message || 'Test completed'}</p>
                    ${testResult.error ? `
                        <p class="text-xs mt-1" style="color: ${hexToRgba(color, 0.7)};">Error: ${testResult.error}</p>
                    ` : ''}
                </div>
            `;
        });
        
        testResultsList.innerHTML = html;
    }
    
    // Show issues if any
    const issuesSection = document.getElementById('issuesSection');
    const issuesList = document.getElementById('issuesList');
    if (issuesSection && issuesList && data.critical_issues && data.critical_issues.length > 0) {
        issuesSection.style.display = 'block';
        
        let html = '';
        const computedStyle = getComputedStyle(document.body);
        const dangerColor = computedStyle.getPropertyValue('--danger').trim();
        
        data.critical_issues.forEach(issue => {
            html += `
                <div class="p-3 rounded-lg" style="
                    background-color: ${hexToRgba(dangerColor, 0.1)};
                    border: 1px solid ${hexToRgba(dangerColor, 0.3)};
                ">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2" style="color: ${dangerColor};"></i>
                        <span class="font-medium" style="color: var(--text-primary);">${issue}</span>
                    </div>
                </div>
            `;
        });
        
        issuesList.innerHTML = html;
    }
    
    // Show recommendations if any
    const recommendationsSection = document.getElementById('recommendationsSection');
    const recommendationsList = document.getElementById('recommendationsList');
    if (recommendationsSection && recommendationsList && data.recommendations && data.recommendations.length > 0) {
        recommendationsSection.style.display = 'block';
        
        let html = '';
        const computedStyle = getComputedStyle(document.body);
        const primaryColor = computedStyle.getPropertyValue('--primary').trim();
        
        data.recommendations.forEach((recommendation, index) => {
            html += `
                <div class="p-3 rounded-lg" style="
                    background-color: ${hexToRgba(primaryColor, 0.05)};
                    border: 1px solid ${hexToRgba(primaryColor, 0.2)};
                ">
                    <div class="flex">
                        <span class="text-sm mr-3" style="color: ${primaryColor};">${index + 1}.</span>
                        <span class="text-sm" style="color: var(--text-primary);">${recommendation}</span>
                    </div>
                </div>
            `;
        });
        
        recommendationsList.innerHTML = html;
    }
}

function showDiagnosticFailed(errorMessage) {
    const failedElement = document.getElementById('diagnosticFailed');
    const errorMessageElement = document.getElementById('errorMessage');
    
    failedElement.classList.remove('hidden');
    if (errorMessageElement && errorMessage) {
        errorMessageElement.textContent = errorMessage;
    }
}

function retryDiagnostic() {
    runFullDiagnostic();
}

function exportDiagnosticResults() {
    // This would export the diagnostic results as PDF or JSON
    alert('Export functionality would be implemented here');
}

// Utility function from dashboard.js
function hexToRgba(hex, alpha = 1) {
    let r = 0, g = 0, b = 0;
    
    if (hex.length === 4) {
        r = parseInt(hex[1] + hex[1], 16);
        g = parseInt(hex[2] + hex[2], 16);
        b = parseInt(hex[3] + hex[3], 16);
    } else if (hex.length === 7) {
        r = parseInt(hex[1] + hex[2], 16);
        g = parseInt(hex[3] + hex[4], 16);
        b = parseInt(hex[5] + hex[6], 16);
    }
    
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

// Make functions globally available
window.showDiagnosticModal = showDiagnosticModal;
window.closeDiagnosticModal = closeDiagnosticModal;
window.runFullDiagnostic = runFullDiagnostic;
window.retryDiagnostic = retryDiagnostic;
window.exportDiagnosticResults = exportDiagnosticResults;
</script>