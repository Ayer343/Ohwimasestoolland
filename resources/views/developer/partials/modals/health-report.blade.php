{{-- Health Report Modal --}}
<div id="healthReportModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" onclick="closeHealthReportModal()"></div>
    
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="relative w-full max-w-2xl transform rounded-lg transition-all" style="background: var(--card-bg);">
            <!-- Modal Header -->
            <div class="p-6" style="border-bottom: 1px solid var(--border-color);">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-semibold text-[var(--text-primary)]">
                            <i class="fas fa-file-pdf mr-2" style="color: var(--primary);"></i>
                            Generate Health Report
                        </h3>
                        <p class="text-sm text-[var(--text-secondary)] mt-1">
                            Export system health data in various formats
                        </p>
                    </div>
                    <button onclick="closeHealthReportModal()" class="p-2 rounded-lg hover:bg-[var(--bg-secondary)] transition-colors">
                        <i class="fas fa-times text-lg" style="color: var(--text-secondary);"></i>
                    </button>
                </div>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6">
                <!-- Format Selection -->
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-file-alt mr-2"></i>Report Format
                    </label>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <label class="format-option">
                            <input type="radio" name="reportFormat" value="json" class="hidden" checked>
                            <div class="p-4 rounded-lg border-2 text-center cursor-pointer transition-all" style="border-color: var(--border-color);">
                                <i class="fas fa-code text-2xl mb-2" style="color: var(--primary);"></i>
                                <div class="font-medium" style="color: var(--text-primary);">JSON</div>
                                <div class="text-xs" style="color: var(--text-secondary);">API Data</div>
                            </div>
                        </label>
                        <label class="format-option">
                            <input type="radio" name="reportFormat" value="html" class="hidden">
                            <div class="p-4 rounded-lg border-2 text-center cursor-pointer transition-all" style="border-color: var(--border-color);">
                                <i class="fas fa-file-code text-2xl mb-2" style="color: var(--success);"></i>
                                <div class="font-medium" style="color: var(--text-primary);">HTML</div>
                                <div class="text-xs" style="color: var(--text-secondary);">Web View</div>
                            </div>
                        </label>
                        <label class="format-option">
                            <input type="radio" name="reportFormat" value="pdf" class="hidden">
                            <div class="p-4 rounded-lg border-2 text-center cursor-pointer transition-all" style="border-color: var(--border-color);">
                                <i class="fas fa-file-pdf text-2xl mb-2" style="color: var(--danger);"></i>
                                <div class="font-medium" style="color: var(--text-primary);">PDF</div>
                                <div class="text-xs" style="color: var(--text-secondary);">Printable</div>
                            </div>
                        </label>
                        <label class="format-option">
                            <input type="radio" name="reportFormat" value="csv" class="hidden">
                            <div class="p-4 rounded-lg border-2 text-center cursor-pointer transition-all" style="border-color: var(--border-color);">
                                <i class="fas fa-table text-2xl mb-2" style="color: var(--info);"></i>
                                <div class="font-medium" style="color: var(--text-primary);">CSV</div>
                                <div class="text-xs" style="color: var(--text-secondary);">Spreadsheet</div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <!-- Time Range -->
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-clock mr-2"></i>Time Range
                    </label>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <label class="time-option">
                            <input type="radio" name="timeRange" value="1h" class="hidden">
                            <div class="p-3 rounded-lg border-2 text-center cursor-pointer transition-all" style="border-color: var(--border-color);">
                                <div class="font-medium" style="color: var(--text-primary);">1 Hour</div>
                            </div>
                        </label>
                        <label class="time-option">
                            <input type="radio" name="timeRange" value="24h" class="hidden" checked>
                            <div class="p-3 rounded-lg border-2 text-center cursor-pointer transition-all" style="border-color: var(--border-color);">
                                <div class="font-medium" style="color: var(--text-primary);">24 Hours</div>
                            </div>
                        </label>
                        <label class="time-option">
                            <input type="radio" name="timeRange" value="7d" class="hidden">
                            <div class="p-3 rounded-lg border-2 text-center cursor-pointer transition-all" style="border-color: var(--border-color);">
                                <div class="font-medium" style="color: var(--text-primary);">7 Days</div>
                            </div>
                        </label>
                        <label class="time-option">
                            <input type="radio" name="timeRange" value="30d" class="hidden">
                            <div class="p-3 rounded-lg border-2 text-center cursor-pointer transition-all" style="border-color: var(--border-color);">
                                <div class="font-medium" style="color: var(--text-primary);">30 Days</div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <!-- Sections to Include -->
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-layer-group mr-2"></i>Sections to Include
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <label class="flex items-center p-3 rounded-lg" style="background: var(--bg-secondary);">
                            <input type="checkbox" name="sections[]" value="overview" class="mr-3" checked>
                            <span style="color: var(--text-primary);">Health Overview</span>
                        </label>
                        <label class="flex items-center p-3 rounded-lg" style="background: var(--bg-secondary);">
                            <input type="checkbox" name="sections[]" value="metrics" class="mr-3" checked>
                            <span style="color: var(--text-primary);">System Metrics</span>
                        </label>
                        <label class="flex items-center p-3 rounded-lg" style="background: var(--bg-secondary);">
                            <input type="checkbox" name="sections[]" value="performance" class="mr-3" checked>
                            <span style="color: var(--text-primary);">Performance Data</span>
                        </label>
                        <label class="flex items-center p-3 rounded-lg" style="background: var(--bg-secondary);">
                            <input type="checkbox" name="sections[]" value="issues" class="mr-3" checked>
                            <span style="color: var(--text-primary);">Issues & Alerts</span>
                        </label>
                        <label class="flex items-center p-3 rounded-lg" style="background: var(--bg-secondary);">
                            <input type="checkbox" name="sections[]" value="recommendations" class="mr-3" checked>
                            <span style="color: var(--text-primary);">Recommendations</span>
                        </label>
                        <label class="flex items-center p-3 rounded-lg" style="background: var(--bg-secondary);">
                            <input type="checkbox" name="sections[]" value="trends" class="mr-3">
                            <span style="color: var(--text-primary);">Trend Analysis</span>
                        </label>
                    </div>
                </div>
                
                <!-- Report Options -->
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-cog mr-2"></i>Additional Options
                    </label>
                    <div class="space-y-3">
                        <label class="flex items-center">
                            <input type="checkbox" name="includeCharts" class="mr-3" checked>
                            <span style="color: var(--text-primary);">Include Charts & Graphs</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="includeRawData" class="mr-3">
                            <span style="color: var(--text-primary);">Include Raw Data</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="watermark" class="mr-3" checked>
                            <span style="color: var(--text-primary);">Add Watermark</span>
                        </label>
                    </div>
                </div>
                
                <!-- Report Preview -->
                <div class="mb-6" id="reportPreview" style="display: none;">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        <i class="fas fa-eye mr-2"></i>Preview
                    </label>
                    <div class="p-4 rounded-lg" style="background: var(--bg-secondary); border: 1px solid var(--border-color); max-height: 200px; overflow-y: auto;">
                        <pre class="text-xs" id="previewContent" style="color: var(--text-primary); font-family: 'Courier New', monospace;">
Loading preview...
                        </pre>
                    </div>
                </div>
                
                <!-- Report Generation Progress -->
                <div class="mb-6 hidden" id="reportProgress">
                    <div class="text-center py-4">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-[var(--primary)] mb-3"></div>
                        <h4 class="font-medium text-[var(--text-primary)] mb-2">Generating Report...</h4>
                        <p class="text-sm text-[var(--text-secondary)]" id="reportStatus">Preparing report data</p>
                    </div>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="p-6" style="border-top: 1px solid var(--border-color);">
                <div class="flex justify-between">
                    <button onclick="closeHealthReportModal()" class="px-4 py-2 rounded-lg font-medium transition-colors" style="background: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                        Cancel
                    </button>
                    <div class="flex space-x-2">
                        <button onclick="previewReport()" class="px-4 py-2 rounded-lg font-medium transition-colors" style="background: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                            <i class="fas fa-eye mr-2"></i> Preview
                        </button>
                        <button onclick="generateReport()" class="px-4 py-2 rounded-lg font-medium transition-colors" style="background: var(--primary); color: white;">
                            <i class="fas fa-download mr-2"></i> Generate Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.format-option input:checked + div {
    border-color: var(--primary);
    background-color: rgba(var(--primary-rgb), 0.1);
    transform: translateY(-2px);
}

.time-option input:checked + div {
    border-color: var(--primary);
    background-color: rgba(var(--primary-rgb), 0.1);
}
</style>

<script>
// Health Report Modal Functions
function showHealthReportModal() {
    const modal = document.getElementById('healthReportModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeHealthReportModal() {
    const modal = document.getElementById('healthReportModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function getSelectedFormat() {
    const selected = document.querySelector('input[name="reportFormat"]:checked');
    return selected ? selected.value : 'json';
}

function getSelectedTimeRange() {
    const selected = document.querySelector('input[name="timeRange"]:checked');
    return selected ? selected.value : '24h';
}

function getSelectedSections() {
    const checkboxes = document.querySelectorAll('input[name="sections[]"]:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}

function getReportOptions() {
    const includeCharts = document.querySelector('input[name="includeCharts"]').checked;
    const includeRawData = document.querySelector('input[name="includeRawData"]').checked;
    const watermark = document.querySelector('input[name="watermark"]').checked;
    
    return {
        includeCharts,
        includeRawData,
        watermark
    };
}

async function previewReport() {
    const previewElement = document.getElementById('reportPreview');
    const previewContent = document.getElementById('previewContent');
    
    previewElement.style.display = 'block';
    previewContent.textContent = 'Generating preview...';
    
    // Simulate API call for preview
    setTimeout(() => {
        const format = getSelectedFormat();
        const sections = getSelectedSections();
        
        let previewText = '';
        
        switch(format) {
            case 'json':
                previewText = JSON.stringify({
                    report: {
                        format: 'json',
                        sections: sections,
                        timeRange: getSelectedTimeRange(),
                        generatedAt: new Date().toISOString(),
                        sampleData: {
                            health_score: 85,
                            status: 'good',
                            metrics: {
                                cpu_usage: '45%',
                                memory_usage: '65%',
                                disk_usage: '72%'
                            }
                        }
                    }
                }, null, 2);
                break;
                
            case 'html':
                previewText = '<!DOCTYPE html>\n<html>\n<head>\n    <title>System Health Report</title>\n</head>\n<body>\n    <h1>System Health Report</h1>\n    <p>Generated on: ' + new Date().toLocaleString() + '</p>\n    <!-- Report content would be here -->\n</body>\n</html>';
                break;
                
            case 'csv':
                previewText = 'Metric,Value,Status\nCPU Usage,45%,Good\nMemory Usage,65%,Warning\nDisk Usage,72%,Good\nResponse Time,120ms,Good\nActive Users,150,Good';
                break;
                
            default:
                previewText = 'Preview not available for PDF format';
        }
        
        previewContent.textContent = previewText;
    }, 500);
}

function generateReport() {
    const format = getSelectedFormat();
    const timeRange = getSelectedTimeRange();
    const sections = getSelectedSections();
    const options = getReportOptions();
    
    // Show progress
    const progressElement = document.getElementById('reportProgress');
    const reportStatus = document.getElementById('reportStatus');
    progressElement.classList.remove('hidden');
    
    // Update status
    reportStatus.textContent = 'Collecting system data...';
    
    // Simulate report generation
    setTimeout(() => {
        reportStatus.textContent = 'Processing metrics...';
    }, 1000);
    
    setTimeout(() => {
        reportStatus.textContent = 'Generating report...';
    }, 2000);
    
    setTimeout(() => {
        reportStatus.textContent = 'Finalizing report...';
    }, 3000);
    
    setTimeout(() => {
        progressElement.classList.add('hidden');
        
        // Based on format, trigger download
        switch(format) {
            case 'json':
                downloadJSONReport();
                break;
            case 'csv':
                downloadCSVReport();
                break;
            case 'pdf':
                downloadPDFReport();
                break;
            case 'html':
                downloadHTMLReport();
                break;
        }
        
        showNotification('Report generated successfully!', 'success');
        closeHealthReportModal();
    }, 4000);
}

function downloadJSONReport() {
    const data = {
        report: {
            format: 'json',
            generatedAt: new Date().toISOString(),
            timeRange: getSelectedTimeRange(),
            sections: getSelectedSections(),
            options: getReportOptions(),
            health: {
                score: 85,
                status: 'good',
                message: 'System is operating normally'
            },
            metrics: {
                cpu: { usage: '45%', load: [1.2, 1.1, 0.9] },
                memory: { usage: '65%', used: '4.2GB', total: '6.4GB' },
                disk: { usage: '72%', used: '360GB', total: '500GB' }
            }
        }
    };
    
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `health-report-${new Date().toISOString().split('T')[0]}.json`;
    document.body.appendChild(a);
    a.click();
    URL.revokeObjectURL(url);
    document.body.removeChild(a);
}

function downloadCSVReport() {
    const csv = `Metric,Value,Unit,Status,Timestamp
CPU Usage,45,%,Good,${new Date().toISOString()}
Memory Usage,65,%,Warning,${new Date().toISOString()}
Disk Usage,72,%,Good,${new Date().toISOString()}
Response Time,120,ms,Good,${new Date().toISOString()}
Active Users,150,count,Good,${new Date().toISOString()}
Database Connections,12,count,Good,${new Date().toISOString()}
Queue Jobs,8,count,Good,${new Date().toISOString()}
Error Rate,0.5,%,Good,${new Date().toISOString()}`;
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `health-report-${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    URL.revokeObjectURL(url);
    document.body.removeChild(a);
}

function downloadHTMLReport() {
    const html = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Health Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .header { text-align: center; margin-bottom: 40px; }
        .metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .metric-card { padding: 20px; border-radius: 8px; border: 1px solid #ddd; }
        .score { font-size: 48px; font-weight: bold; text-align: center; }
        .status-good { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-error { color: #dc3545; }
    </style>
</head>
<body>
    <div class="header">
        <h1>System Health Report</h1>
        <p>Generated: ${new Date().toLocaleString()}</p>
        <p>Time Range: ${getSelectedTimeRange()}</p>
    </div>
    
    <div class="score status-good">85/100</div>
    <p style="text-align: center; color: #28a745; font-weight: bold;">GOOD</p>
    
    <div class="metrics">
        <div class="metric-card">
            <h3>CPU Usage</h3>
            <p class="status-good">45%</p>
        </div>
        <div class="metric-card">
            <h3>Memory Usage</h3>
            <p class="status-warning">65%</p>
        </div>
        <div class="metric-card">
            <h3>Disk Usage</h3>
            <p class="status-good">72%</p>
        </div>
        <div class="metric-card">
            <h3>Response Time</h3>
            <p class="status-good">120ms</p>
        </div>
    </div>
    
    <div style="margin-top: 40px;">
        <h2>Report Details</h2>
        <ul>
            <li>Generated at: ${new Date().toISOString()}</li>
            <li>Format: HTML</li>
            <li>Sections included: ${getSelectedSections().join(', ')}</li>
            <li>Includes charts: ${getReportOptions().includeCharts ? 'Yes' : 'No'}</li>
        </ul>
    </div>
</body>
</html>`;
    
    const blob = new Blob([html], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `health-report-${new Date().toISOString().split('T')[0]}.html`;
    document.body.appendChild(a);
    a.click();
    URL.revokeObjectURL(url);
    document.body.removeChild(a);
}

function downloadPDFReport() {
    // For PDF, we would typically use a server-side library
    // This is a mock implementation
    showNotification('PDF generation would require server-side processing. Using JSON format instead.', 'info');
    downloadJSONReport();
}

// Make functions globally available
window.showHealthReportModal = showHealthReportModal;
window.closeHealthReportModal = closeHealthReportModal;
window.generateReport = generateReport;
window.previewReport = previewReport;

// Add event listeners for option selection
document.addEventListener('DOMContentLoaded', function() {
    // Format selection
    document.querySelectorAll('.format-option').forEach(option => {
        option.addEventListener('click', function() {
            document.querySelectorAll('.format-option').forEach(o => {
                o.querySelector('div').style.borderColor = 'var(--border-color)';
                o.querySelector('div').style.backgroundColor = '';
            });
            this.querySelector('div').style.borderColor = 'var(--primary)';
            this.querySelector('div').style.backgroundColor = 'rgba(var(--primary-rgb), 0.1)';
        });
    });
    
    // Time range selection
    document.querySelectorAll('.time-option').forEach(option => {
        option.addEventListener('click', function() {
            document.querySelectorAll('.time-option').forEach(o => {
                o.querySelector('div').style.borderColor = 'var(--border-color)';
                o.querySelector('div').style.backgroundColor = '';
            });
            this.querySelector('div').style.borderColor = 'var(--primary)';
            this.querySelector('div').style.backgroundColor = 'rgba(var(--primary-rgb), 0.1)';
        });
    });
});
</script>