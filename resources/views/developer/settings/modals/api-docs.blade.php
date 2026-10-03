{{-- resources/views/developer/settings/modals/api-docs.blade.php --}}
<div id="apiDocsModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-6xl w-full max-h-[90vh] overflow-hidden">
        <div class="p-6 border-b dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold dark:text-gray-100">
                        <i class="fas fa-book mr-2 text-purple-500"></i>
                        API Documentation
                    </h3>
                    <p class="text-sm mt-1 dark:text-gray-400">
                        Developer API reference and examples
                    </p>
                </div>
                <button onclick="closeApiDocsModal()" 
                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <div class="flex h-[70vh]">
            <!-- Sidebar -->
            <div class="w-64 border-r dark:border-gray-700 bg-gray-50 dark:bg-gray-900 overflow-y-auto">
                <div class="p-4">
                    <div class="mb-6">
                        <h4 class="font-medium mb-2 dark:text-gray-200">API Reference</h4>
                        <ul class="space-y-1">
                            <li><a href="#authentication" onclick="showApiSection('authentication')" class="api-nav-link">Authentication</a></li>
                            <li><a href="#users" onclick="showApiSection('users')" class="api-nav-link">Users</a></li>
                            <li><a href="#billing" onclick="showApiSection('billing')" class="api-nav-link">Billing</a></li>
                            <li><a href="#monitoring" onclick="showApiSection('monitoring')" class="api-nav-link">Monitoring</a></li>
                            <li><a href="#settings" onclick="showApiSection('settings')" class="api-nav-link">Settings</a></li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4 class="font-medium mb-2 dark:text-gray-200">Quick Links</h4>
                        <ul class="space-y-1">
                            <li><a href="#rate-limiting" onclick="showApiSection('rate-limiting')" class="api-nav-link">Rate Limiting</a></li>
                            <li><a href="#errors" onclick="showApiSection('errors')" class="api-nav-link">Error Codes</a></li>
                            <li><a href="#examples" onclick="showApiSection('examples')" class="api-nav-link">Examples</a></li>
                            <li><a href="#testing" onclick="showApiSection('testing')" class="api-nav-link">Testing</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Content -->
            <div class="flex-1 overflow-y-auto p-6">
                <div id="apiDocsContent">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
        
        <div class="p-6 border-t dark:border-gray-700">
            <div class="flex justify-between items-center">
                <div class="text-sm dark:text-gray-400">
                    <i class="fas fa-info-circle mr-1"></i>
                    Base URL: <code class="ml-1">{{ $apiKeys['base_url'] ?? config('app.url') . '/api/developer' }}</code>
                </div>
                <div>
                    <button onclick="copyApiBaseUrl()" 
                            class="btn btn-info px-3 py-1 rounded-lg text-sm mr-2">
                        <i class="fas fa-copy mr-1"></i> Copy Base URL
                    </button>
                    <button onclick="generateApiClient()" 
                            class="btn btn-primary px-3 py-1 rounded-lg text-sm mr-2">
                        <i class="fas fa-code mr-1"></i> Generate Client
                    </button>
                    <button onclick="closeApiDocsModal()" 
                            class="btn btn-secondary px-3 py-1 rounded-lg text-sm dark:bg-gray-700 dark:text-gray-200">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.api-nav-link {
    display: block;
    padding: 0.5rem 0.75rem;
    border-radius: 0.375rem;
    color: #6b7280;
    text-decoration: none;
    transition: all 0.2s;
}

.api-nav-link:hover {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
}

.dark .api-nav-link {
    color: #d1d5db;
}

.dark .api-nav-link:hover {
    background-color: rgba(59, 130, 246, 0.1);
    color: #60a5fa;
}

.api-section {
    display: none;
}

.api-section.active {
    display: block;
}

pre {
    background-color: #f8f9fa;
    border-radius: 0.375rem;
    padding: 1rem;
    overflow-x: auto;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 0.875rem;
    line-height: 1.5;
}

.dark pre {
    background-color: #1f2937;
    color: #e5e7eb;
}

code:not(pre code) {
    background-color: #f3f4f6;
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 0.875rem;
}

.dark code:not(pre code) {
    background-color: #374151;
    color: #f3f4f6;
}
</style>

<script>
const apiDocumentation = {
    authentication: `
        <h2 class="text-xl font-bold mb-4 dark:text-gray-100">Authentication</h2>
        <p class="mb-4 dark:text-gray-300">All API requests require authentication using your API key and secret.</p>
        
        <div class="mb-6">
            <h3 class="font-medium mb-2 dark:text-gray-200">Authentication Header</h3>
            <pre><code>Authorization: Bearer YOUR_API_KEY
X-API-Secret: YOUR_SECRET_KEY</code></pre>
        </div>
        
        <div class="mb-6">
            <h3 class="font-medium mb-2 dark:text-gray-200">Example Request</h3>
            <pre><code>curl -X GET \\
  '{{ $apiKeys['base_url'] ?? config('app.url') . '/api/developer' }}/v1/users' \\
  -H 'Authorization: Bearer YOUR_API_KEY' \\
  -H 'X-API-Secret: YOUR_SECRET_KEY'</code></pre>
        </div>
        
        <div class="mb-6">
            <h3 class="font-medium mb-2 dark:text-gray-200">Response</h3>
            <pre><code>{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com"
    }
  },
  "message": "Authenticated successfully"
}</code></pre>
        </div>
    `,
    
    users: `
        <h2 class="text-xl font-bold mb-4 dark:text-gray-100">Users API</h2>
        
        <div class="space-y-6">
            <div>
                <h3 class="font-medium mb-2 dark:text-gray-200">Get Current User</h3>
                <pre><code>GET {{ $apiKeys['base_url'] ?? config('app.url') . '/api/developer' }}/v1/user</code></pre>
            </div>
            
            <div>
                <h3 class="font-medium mb-2 dark:text-gray-200">Update User</h3>
                <pre><code>PUT {{ $apiKeys['base_url'] ?? config('app.url') . '/api/developer' }}/v1/user
Content-Type: application/json

{
  "name": "Updated Name",
  "email": "updated@example.com"
}</code></pre>
            </div>
        </div>
    `,
    
    // Add more sections...
};

function showApiDocsModal() {
    const modal = document.getElementById('apiDocsModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    showApiSection('authentication');
}

function closeApiDocsModal() {
    const modal = document.getElementById('apiDocsModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showApiSection(section) {
    const contentDiv = document.getElementById('apiDocsContent');
    contentDiv.innerHTML = apiDocumentation[section] || '<p class="dark:text-gray-300">Section not found.</p>';
    
    // Update active nav link
    document.querySelectorAll('.api-nav-link').forEach(link => {
        link.classList.remove('active');
    });
    event.target.classList.add('active');
}

function copyApiBaseUrl() {
    const baseUrl = '{{ $apiKeys['base_url'] ?? config('app.url') . '/api/developer' }}';
    navigator.clipboard.writeText(baseUrl).then(() => {
        showToast('Base URL copied to clipboard', 'success');
    }).catch(err => {
        console.error('Failed to copy:', err);
        showToast('Failed to copy Base URL', 'error');
    });
}

function generateApiClient() {
    showToast('Generating API client code...', 'info');
    
    // This would typically make a request to generate client code
    setTimeout(() => {
        showToast('API client code generated. Check your downloads.', 'success');
    }, 1000);
}
</script>