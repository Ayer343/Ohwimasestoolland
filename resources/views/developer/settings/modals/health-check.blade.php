{{-- resources/views/developer/settings/modals/health-check.blade.php --}}
<div id="healthCheckModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full max-h-[80vh] overflow-hidden">
        <div class="p-6 border-b dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold dark:text-gray-100">
                        <i class="fas fa-heartbeat mr-2 text-green-500"></i>
                        System Health Check
                    </h3>
                    <p class="text-sm mt-1 dark:text-gray-400">
                        Comprehensive system diagnostics
                    </p>
                </div>
                <button onclick="closeHealthCheckModal()" 
                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <div class="p-6 overflow-y-auto max-h-[60vh]">
            <div id="healthCheckResults">
                <!-- Results will be loaded here -->
            </div>
        </div>
        
        <div class="p-6 border-t dark:border-gray-700">
            <div class="flex justify-between">
                <div>
                    <button onclick="runHealthCheck()" 
                            class="btn btn-primary px-4 py-2 rounded-lg">
                        <i class="fas fa-redo mr-2"></i> Run Again
                    </button>
                    <button onclick="downloadHealthReport()" 
                            class="btn btn-info px-4 py-2 rounded-lg ml-2">
                        <i class="fas fa-download mr-2"></i> Download Report
                    </button>
                </div>
                <button onclick="closeHealthCheckModal()" 
                        class="btn btn-secondary px-4 py-2 rounded-lg dark:bg-gray-700 dark:text-gray-200">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function showHealthCheckModal() {
    const modal = document.getElementById('healthCheckModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    runHealthCheck();
}

function closeHealthCheckModal() {
    const modal = document.getElementById('healthCheckModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function runHealthCheck() {
    const resultsDiv = document.getElementById('healthCheckResults');
    resultsDiv.innerHTML = `
        <div class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-2xl text-blue-500 dark:text-blue-400 mb-4"></i>
            <p class="dark:text-gray-300">Running health checks...</p>
        </div>
    `;
    
    fetch('{{ route("developer.tools.health-check") }}')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderHealthCheckResults(data.checks);
            } else {
                resultsDiv.innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-triangle text-2xl text-red-500 dark:text-red-400 mb-4"></i>
                        <p class="dark:text-gray-300">Health check failed</p>
                        <p class="text-sm mt-2 dark:text-gray-400">${data.message || 'Unknown error'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            resultsDiv.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-2xl text-red-500 dark:text-red-400 mb-4"></i>
                    <p class="dark:text-gray-300">Health check failed</p>
                    <p class="text-sm mt-2 dark:text-gray-400">${error.message}</p>
                </div>
            `;
        });
}

function renderHealthCheckResults(checks) {
    const resultsDiv = document.getElementById('healthCheckResults');
    const healthyCount = checks.filter(c => c.status === 'healthy').length;
    const warningCount = checks.filter(c => c.status === 'warning').length;
    const unhealthyCount = checks.filter(c => c.status === 'unhealthy').length;
    
    resultsDiv.innerHTML = `
        <div class="mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="text-center p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">${healthyCount}</div>
                    <div class="text-sm dark:text-gray-300">Healthy</div>
                </div>
                <div class="text-center p-4 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800">
                    <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">${warningCount}</div>
                    <div class="text-sm dark:text-gray-300">Warnings</div>
                </div>
                <div class="text-center p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                    <div class="text-2xl font-bold text-red-600 dark:text-red-400">${unhealthyCount}</div>
                    <div class="text-sm dark:text-gray-300">Unhealthy</div>
                </div>
            </div>
            
            <div class="space-y-3">
                ${checks.map(check => `
                    <div class="flex items-center justify-between p-3 rounded-lg border dark:border-gray-700">
                        <div class="flex-1">
                            <div class="flex items-center">
                                <span class="w-2 h-2 rounded-full mr-3 ${getStatusColor(check.status)}"></span>
                                <div>
                                    <p class="font-medium dark:text-gray-200">${check.name}</p>
                                    <p class="text-sm mt-1 dark:text-gray-400">${check.description}</p>
                                    ${check.details ? `
                                        <div class="mt-2 p-2 bg-gray-50 dark:bg-gray-700 rounded text-xs">
                                            <pre class="whitespace-pre-wrap">${check.details}</pre>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                        <span class="badge badge-${getStatusBadge(check.status)} ml-4">
                            ${check.status.charAt(0).toUpperCase() + check.status.slice(1)}
                        </span>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
}

function getStatusColor(status) {
    switch (status) {
        case 'healthy': return 'bg-green-500';
        case 'warning': return 'bg-yellow-500';
        case 'unhealthy': return 'bg-red-500';
        default: return 'bg-gray-500';
    }
}

function getStatusBadge(status) {
    switch (status) {
        case 'healthy': return 'success';
        case 'warning': return 'warning';
        case 'unhealthy': return 'danger';
        default: return 'secondary';
    }
}

function downloadHealthReport() {
    fetch('{{ route("developer.tools.health-report") }}')
        .then(response => response.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `health-report-${new Date().toISOString().split('T')[0]}.json`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            showToast('Health report downloaded', 'success');
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to download report: ' + error.message, 'error');
        });
}
</script>